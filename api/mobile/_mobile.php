<?php
/**
 * Jollof Living — mobile API bootstrap.
 *
 * The website's own API authenticates with a PHP session cookie and a CSRF
 * token, which suits a same-origin browser. The Android app is a packaged
 * bundle on a different origin, so it authenticates with a bearer token
 * instead and needs CORS. Everything below that point — the repositories,
 * pricing, booking rules — is the same code the website runs, so the two
 * clients can never drift apart.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once JL_INC . '/view.php';

/* ------------------------------------------------------------------ CORS */

/**
 * A packaged Capacitor app sends Origin: https://localhost (Android) or
 * capacitor://localhost (iOS). Allow those plus anything configured, and
 * echo the origin back so credentials-mode requests are accepted.
 */
function mobile_cors(): void
{
    $allowed = array_filter(array_map('trim', explode(',', (string) config('mobile.origins', ''))));
    $default = ['https://localhost', 'capacitor://localhost', 'http://localhost'];
    $origin  = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

    if ($origin !== '' && (in_array($origin, $default, true) || in_array($origin, $allowed, true)
        || preg_match('~^https?://localhost(:\d+)?$~', $origin))) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Client-Key, X-App-Version');
    header('Access-Control-Max-Age: 86400');
    header('Cache-Control: no-store, max-age=0');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
mobile_cors();

/* ----------------------------------------------------------- token auth */

final class MobileAuth
{
    private static ?array $cached = null;
    private static bool $looked = false;

    /** Raw bearer token from the Authorization header, if any. */
    public static function bearer(): string
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($h === '' && function_exists('apache_request_headers')) {
            foreach ((array) apache_request_headers() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) { $h = (string) $v; break; }
            }
        }
        if (preg_match('~^Bearer\s+(.+)$~i', trim($h), $m)) {
            return trim($m[1]);
        }
        // Fall back to the body so a proxy that strips Authorization still works.
        return (string) (request_body()['token'] ?? '');
    }

    /** Issue a token for a user and return the plaintext exactly once. */
    public static function issue(int $userId, string $device = '', string $platform = 'android'): string
    {
        $plain = bin2hex(random_bytes(32));
        $days  = max(1, (int) config('mobile.token_days', 90));
        DB::insert('api_tokens', [
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $plain),
            'device'     => mb_substr($device, 0, 120) ?: 'Android device',
            'platform'   => $platform,
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
        ]);
        return $plain;
    }

    /** The user this request belongs to, or null. Also refreshes last_used_at. */
    public static function user(): ?array
    {
        if (self::$looked) {
            return self::$cached;
        }
        self::$looked = true;

        $plain = self::bearer();
        if ($plain === '') {
            return self::$cached = null;
        }
        $row = DB::row(
            'SELECT * FROM api_tokens WHERE token_hash = ? AND revoked = 0',
            [hash('sha256', $plain)]
        );
        if (!$row) {
            return self::$cached = null;
        }
        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            return self::$cached = null;
        }
        $u = DB::row('SELECT * FROM users WHERE id = ?', [(int) $row['user_id']]);
        if (!$u || ($u['status_level'] ?? 'ok') === 'bad') {
            return self::$cached = null;
        }
        try {
            DB::run('UPDATE api_tokens SET last_used_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), (int) $row['id']]);
        } catch (Throwable $e) {
        }

        // The shared repositories read Auth::user(), which reads the session.
        // Priming it lets every website service run unchanged for app requests.
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['is_admin'] = ($u['role'] ?? '') === 'admin';

        return self::$cached = $u;
    }

    public static function revoke(string $plain): void
    {
        if ($plain !== '') {
            DB::run('UPDATE api_tokens SET revoked = 1 WHERE token_hash = ?', [hash('sha256', $plain)]);
        }
    }

    public static function revokeAllFor(int $userId): void
    {
        DB::run('UPDATE api_tokens SET revoked = 1 WHERE user_id = ?', [$userId]);
    }
}

/* --------------------------------------------------------------- guards */

/** Require a signed-in app user, or return a 401 the app knows to handle. */
function mobile_user(): array
{
    $u = MobileAuth::user();
    if (!$u) {
        json_response([
            'ok'           => false,
            'requiresAuth' => true,
            'message'      => 'Please sign in to continue.',
        ], 401);
    }
    return $u;
}

/** Require an owner account. */
function mobile_host(): array
{
    $u = mobile_user();
    $isHost = (int) ($u['is_host'] ?? 0) === 1
        || ($u['role'] ?? '') === 'host'
        || ($u['role'] ?? '') === 'admin';
    if (!$isHost) {
        json_response([
            'ok'         => false,
            'needsUpgrade' => true,
            'message'    => 'Your account is not set up for hosting yet.',
        ], 403);
    }
    return $u;
}

function mobile_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_fail('POST required', 405);
    }
}

/* ---------------------------------------------------- offline replay safety */

/**
 * Actions queued offline are replayed on reconnect, and a flaky connection
 * can replay the same one twice. The app sends a stable X-Client-Key per
 * queued action; if we have already carried it out, return the original
 * response rather than booking a second time.
 */
function mobile_client_key(): string
{
    $k = (string) ($_SERVER['HTTP_X_CLIENT_KEY'] ?? request_body()['clientKey'] ?? '');
    return mb_substr(trim($k), 0, 64);
}

function mobile_replay(int $userId, string $endpoint): void
{
    $key = mobile_client_key();
    if ($key === '') {
        return;
    }
    $prev = DB::row(
        'SELECT response FROM sync_operations WHERE user_id = ? AND client_key = ?',
        [$userId, $key]
    );
    if ($prev && $prev['response']) {
        $decoded = json_decode((string) $prev['response'], true);
        if (is_array($decoded)) {
            $decoded['replayed'] = true;
            json_response($decoded);
        }
    }
}

function mobile_remember(int $userId, string $endpoint, array $response): void
{
    $key = mobile_client_key();
    if ($key === '') {
        return;
    }
    try {
        DB::insert('sync_operations', [
            'user_id'    => $userId,
            'client_key' => $key,
            'endpoint'   => mb_substr($endpoint, 0, 80),
            'response'   => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        // A duplicate key means a concurrent replay already stored it.
    }
}

/** Success envelope. Mirrors the website's shape so shared code is reusable. */
function mobile_ok($data = [], string $message = '', array $meta = []): void
{
    json_response(['ok' => true, 'message' => $message, 'data' => $data] + $meta);
}

/** Compact user object the app stores locally. */
function mobile_user_payload(array $u): array
{
    return [
        'id'       => (int) $u['id'],
        'name'     => (string) $u['name'],
        'email'    => (string) $u['email'],
        'phone'    => (string) ($u['phone'] ?? ''),
        'tier'     => (string) ($u['tier'] ?? 'bronze'),
        'points'   => (int) ($u['points'] ?? 0),
        'role'     => (string) ($u['role'] ?? 'guest'),
        'isHost'   => (int) ($u['is_host'] ?? 0) === 1 || in_array($u['role'] ?? '', ['host', 'admin'], true),
        'isAdmin'  => ($u['role'] ?? '') === 'admin',
        'avatar'   => (string) ($u['avatar'] ?? ''),
        'city'     => (string) ($u['city'] ?? ''),
        'referral' => (string) ($u['referral_code'] ?? ''),
        'kyc'      => (int) ($u['kyc_verified'] ?? 0) === 1,
    ];
}
