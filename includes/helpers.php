<?php
/**
 * Jollof Living — shared helpers
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


/** HTML escape. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON encode for embedding into a <script> block. */
function json_js($v): string
{
    return json_encode(
        $v,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

/** Application base URL path, e.g. '' or '/jollof'. */
function base_path(): string
{
    static $bp = null;
    if ($bp !== null) {
        return $bp;
    }
    $cfg = trim((string) config('site.base_url', ''));
    if ($cfg !== '') {
        return $bp = '/' . trim($cfg, '/');
    }
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $script = rtrim($script, '/');
    // Strip a trailing /admin, /api or /install segment so links -- and, more
    // importantly, the session cookie path -- resolve to the SITE root rather
    // than the sub-directory the current script happens to live in.
    foreach (['/admin', '/api', '/install'] as $sub) {
        if (substr($script, -strlen($sub)) === $sub) {
            $script = substr($script, 0, -strlen($sub));
        }
    }
    return $bp = $script === '/' ? '' : $script;
}

/** Build a site URL from a path: url('stays') → /stays */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return base_path() . '/' . $path;
}

/** Asset URL with a cache-busting stamp. */
function asset(string $path): string
{
    $rel = ltrim($path, '/');
    $file = JL_ROOT . '/' . $rel;
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return base_path() . '/' . $rel . '?v=' . $v;
}

/** Redirect and stop. */
function redirect(string $path, int $code = 302): void
{
    $to = preg_match('~^https?://~', $path) ? $path : url($path);
    header('Location: ' . $to, true, $code);
    exit;
}

/** JSON response and stop. */
function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok($data = [], string $message = ''): void
{
    json_response(['ok' => true, 'message' => $message] + (is_array($data) ? ['data' => $data] : ['data' => $data]));
}

function json_fail(string $message, int $code = 400, $extra = null): void
{
    json_response(['ok' => false, 'message' => $message, 'errors' => $extra], $code);
}

/** Read the JSON or form body of the current request. */
function request_body(): array
{
    static $body = null;
    if ($body !== null) {
        return $body;
    }
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return $body = is_array($decoded) ? $decoded : [];
    }
    return $body = $_POST;
}

/** Fetch an input value from body or query string. */
function input(string $key, $default = null)
{
    $b = request_body();
    if (array_key_exists($key, $b)) {
        return $b[$key];
    }
    return $_GET[$key] ?? $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key, $default);
    return is_numeric($v) ? (int) $v : $default;
}

function input_str(string $key, string $default = ''): string
{
    $v = input($key, $default);
    return is_scalar($v) ? trim((string) $v) : $default;
}

function input_bool(string $key, bool $default = false): bool
{
    $v = input($key, $default);
    if (is_bool($v)) return $v;
    return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
}

/** A slug-safe string. */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? '';
    return trim($s, '-') ?: 'item';
}

function is_email(string $s): bool
{
    return (bool) filter_var($s, FILTER_VALIDATE_EMAIL);
}

function client_ip(): string
{
    return (string) ($_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '');
}

/** Current page key from the running script, e.g. 'stays'. */
function current_page(): string
{
    $f = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return preg_replace('~\.php$~', '', $f) ?: 'index';
}

/** Nights between two Y-m-d dates. */
function nights_between(?string $in, ?string $out): int
{
    if (!$in || !$out) {
        return 0;
    }
    try {
        $a = new DateTimeImmutable($in);
        $b = new DateTimeImmutable($out);
    } catch (Throwable $e) {
        return 0;
    }
    $d = (int) $a->diff($b)->format('%r%a');
    return max(0, $d);
}

function today_plus(int $days = 0): string
{
    return (new DateTimeImmutable('today'))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

/** Format an NGN amount in the active currency (server-side mirror of the JS fmt()). */
function money(int $ngn, ?string $code = null): string
{
    $code = $code ?: active_currency();
    $fx = Repo::fxRates();
    $c = $fx[$code] ?? $fx['NGN'] ?? ['symbol' => '₦', 'rate' => 1, 'decimals' => 0];
    $v = $ngn * (float) $c['rate'];
    return $c['symbol'] . ($v >= 1000
        ? number_format(round($v))
        : number_format($v, (int) $c['decimals']));
}

function active_currency(): string
{
    $c = strtoupper((string) ($_COOKIE['jl_currency'] ?? ($_SESSION['currency'] ?? config('site.currency', 'NGN'))));
    return preg_match('~^[A-Z]{3}$~', $c) ? $c : 'NGN';
}

/** CSRF token for forms and API calls. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = (string) (request_body()['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf'] ?? '');
    return $sent !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent);
}

function require_csrf(): void
{
    if (!csrf_check()) {
        json_fail('Your session expired — please refresh the page and try again.', 419);
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_fail('POST required', 405);
    }
}

/** Write an entry to the audit log. */
function audit(string $actor, string $action, string $level = 'info'): void
{
    try {
        DB::insert('audit_log', [
            'actor'      => mb_substr($actor, 0, 140),
            'action'     => mb_substr($action, 0, 240),
            'level'      => in_array($level, ['ok', 'info', 'warn', 'bad'], true) ? $level : 'info',
            'ip'         => client_ip(),
            'time_label' => date('H:i'),
        ]);
    } catch (Throwable $e) {
        // never let logging break a request
    }
}

/** Generate a booking reference. */
function booking_ref(): string
{
    return 'JL-' . date('Y') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
}

/** Legacy .html links → their .php equivalents, so old bookmarks keep working. */
function page_url(string $key, array $query = []): string
{
    $u = url($key === 'index' ? '' : $key . '.php');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}
