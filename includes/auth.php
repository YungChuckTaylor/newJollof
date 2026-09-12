<?php
/**
 * Jollof Living — sessions, users, admin gate
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


final class Auth
{
    /** Set when session_start() fails, so callers can report the real reason. */
    private static ?string $sessionError = null;

    public static function sessionError(): ?string
    {
        return self::$sessionError;
    }

    /**
     * Make sure PHP has somewhere writable to store sessions.
     *
     * Several shared hosts (HostGator/cPanel in particular) ship a
     * session.save_path such as /var/cpanel/php/sessions/ea-php83 that the
     * account cannot write to. PHP then fails to start a session at all, which
     * surfaces as a baffling "session expired" on every form submit.
     *
     * Rather than require a php.ini change, fall back to a private directory
     * inside the application (storage/sessions, above the web root when the
     * standard layout is used).
     */
    private static function ensureWritableSavePath(): void
    {
        $configured = (string) config('security.session_path', '');
        if ($configured === '') {
            $current = (string) session_save_path();
            // Nothing to do when the host's own path already works.
            if ($current !== '' && is_dir($current) && is_writable($current)) {
                return;
            }
        }

        $dir = $configured !== '' ? $configured : JL_ROOT . '/../storage/sessions';

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            // Last resort: the system temp directory. Not ideal on shared
            // hosting (other accounts may share it) but better than no session.
            $tmp = sys_get_temp_dir() . '/jollof-sessions';
            if (!is_dir($tmp)) {
                @mkdir($tmp, 0700, true);
            }
            $dir = is_dir($tmp) && is_writable($tmp) ? $tmp : '';
        }

        if ($dir !== '') {
            session_save_path($dir);
            ini_set('session.save_path', $dir);
            // Keep the directory listing private if .htaccess is honoured.
            $guard = $dir . '/.htaccess';
            if (!is_file($guard)) {
                @file_put_contents($guard, "Require all denied\n");
            }
        }
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $name = (string) config('security.session_name', 'jollof_session');
        $life = (int) config('security.session_lifetime', 43200);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_name($name);
        self::ensureWritableSavePath();
        session_set_cookie_params([
            'lifetime' => $life,
            // Always the SITE root (base_path strips /api, /admin, /install), so
            // one session is shared by every page instead of being scoped to a
            // sub-directory -- which silently breaks CSRF between form and POST.
            'path'     => base_path() ?: '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // If the session cannot start (unwritable save_path is the usual cause on
        // shared hosting) every CSRF check fails with a confusing "session
        // expired". Surface that instead of hiding it behind @.
        if (!@session_start()) {
            self::$sessionError = 'PHP could not start a session. The session save path ('
                . (session_save_path() ?: 'system default')
                . ') is not writable, and the fallback directory could not be created either.';
            return;
        }

        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - (int) $_SESSION['created'] > $life) {
            session_unset();
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    /* ------------------------------------------------------------ user */

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    /** The signed-in user row, or null. */
    public static function user(): ?array
    {
        static $cache = null;
        static $cachedId = -1;
        $id = self::id();
        if ($id === null) {
            return null;
        }
        if ($cachedId === $id) {
            return $cache;
        }
        $cachedId = $id;
        return $cache = DB::row('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** Display name used across the chrome. */
    public static function displayName(): string
    {
        $u = self::user();
        return $u ? (string) $u['name'] : 'Guest';
    }

    public static function login(int $userId, bool $isAdmin = false): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['created'] = time();
        if ($isAdmin) {
            $_SESSION['admin'] = 1;
        }
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    /**
     * Register a new guest. Returns [ok, message, userId].
     */
    public static function register(string $name, string $email, string $password, string $phone = '', string $accountType = 'guest'): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '') {
            return [false, 'Please enter your name.', 0];
        }
        if (!is_email($email)) {
            return [false, 'Please enter a valid email address.', 0];
        }
        if (strlen($password) < 8) {
            return [false, 'Password must be at least 8 characters.', 0];
        }
        if (DB::value('SELECT id FROM users WHERE email = ?', [$email])) {
            return [false, 'An account with that email already exists.', 0];
        }
        // Only these two types may be chosen at signup; 'admin' and
        // 'corporate' are assigned internally and can never be self-granted.
        $isOwner = $accountType === 'host';

        $id = DB::insert('users', [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone ?: null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $isOwner ? 'host' : 'guest',
            'is_host'       => $isOwner ? 1 : 0,
            'tier'          => 'bronze',
            'points'        => 0,
            'status'        => 'Pending ID',
            'status_level'  => 'warn',
            'referral_code' => strtoupper(preg_replace('~[^A-Za-z]~', '', $name) ?: 'JOLLOF') . random_int(10, 99),
        ]);
        DB::insert('wishlists', ['user_id' => $id, 'slug' => 'default', 'name' => 'My wishlist']);
        if ($isOwner) {
            self::provisionHost((int) $id);
        }
        audit($email, $isOwner ? 'New owner account created' : 'New account created', 'ok');
        return [true, 'Welcome to Jollof Living.', $id];
    }

    /**
     * Attempt a sign in. Returns [ok, message, user|null].
     */
    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        if (self::isLockedOut($email)) {
            return [false, 'Too many attempts. Please try again in a few minutes.', null];
        }
        $u = DB::row('SELECT * FROM users WHERE email = ?', [$email]);
        $ok = $u && $u['password_hash'] && password_verify($password, (string) $u['password_hash']);
        self::recordAttempt($email, $ok);
        if (!$ok) {
            return [false, 'Those details do not match our records.', null];
        }
        if (($u['status_level'] ?? 'ok') === 'bad') {
            return [false, 'This account is suspended. Please contact support.', null];
        }
        self::login((int) $u['id'], ($u['role'] ?? '') === 'admin');
        return [true, 'Welcome back.', $u];
    }

    private static function recordAttempt(string $identifier, bool $success): void
    {
        try {
            DB::insert('login_attempts', [
                'identifier' => mb_substr($identifier, 0, 190),
                'ip'         => client_ip(),
                'success'    => $success ? 1 : 0,
            ]);
        } catch (Throwable $e) {
        }
    }

    private static function isLockedOut(string $identifier): bool
    {
        $max = (int) config('security.max_login_attempts', 8);
        $mins = (int) config('security.lockout_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - $mins * 60);
        $n = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND success = 0 AND created_at > ?',
            [$identifier, $since],
            0
        );
        return $n >= $max;
    }

    /* ------------------------------------------------------ owner/host */

    /**
     * Is the signed-in member a property owner? Admins are deliberately
     * included so support staff can inspect the owner workspace.
     */
    public static function isHost(): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        return (int) ($u['is_host'] ?? 0) === 1
            || ($u['role'] ?? '') === 'host'
            || ($u['role'] ?? '') === 'admin';
    }

    /** Gate an owner-only page. */
    public static function requireHost(): void
    {
        self::requireLogin();
        if (!self::isHost()) {
            // A signed-in customer is not an error: send them to the page that
            // explains hosting and offers the upgrade.
            redirect('host.php?upgrade=1');
        }
    }

    /**
     * Give an owner account the rows its dashboard expects. Idempotent, so an
     * existing customer upgrading to an owner gets the same starting point as
     * a brand-new owner without ever duplicating rows.
     */
    public static function provisionHost(int $userId): void
    {
        DB::run(
            'UPDATE users SET role = CASE WHEN role = ? THEN ? ELSE role END, is_host = 1 WHERE id = ?',
            ['guest', 'host', $userId]
        );

        if (!DB::value('SELECT host_id FROM host_payout_settings WHERE host_id = ?', [$userId])) {
            DB::insert('host_payout_settings', [
                'host_id'  => $userId,
                'schedule' => 'weekly',
            ]);
        }

        foreach (['Airbnb', 'Booking.com', 'VRBO / Expedia'] as $channel) {
            if (!DB::value('SELECT id FROM host_channels WHERE host_id = ? AND channel = ?', [$userId, $channel])) {
                DB::insert('host_channels', [
                    'host_id' => $userId,
                    'channel' => $channel,
                    'status'  => 'disconnected',
                ]);
            }
        }
        if (!DB::value('SELECT id FROM host_channels WHERE host_id = ? AND channel = ?', [$userId, 'Direct'])) {
            DB::insert('host_channels', [
                'host_id'      => $userId,
                'channel'      => 'Direct',
                'status'       => 'connected',
                'last_sync_at' => date('Y-m-d H:i:s'),
                'note'         => 'Always in sync',
            ]);
        }

        if (!DB::value('SELECT id FROM host_templates WHERE host_id = ?', [$userId])) {
            $starter = [
                ['Booking confirmation', 'Hi {name}! Your reservation at {property} is confirmed. {dates}. We cannot wait to host you.', 'confirmed', 'send'],
                ['Check-in instructions', 'Welcome! The smart lock opens with {code}. Wi-Fi details and parking are in your trip notes.', 'checkin', 'key'],
                ['Check-out reminder', 'Check-out is at 11am tomorrow. Message us if you would like a late check-out.', 'checkout', 'clock'],
            ];
            foreach ($starter as [$title, $body, $trigger, $icon]) {
                DB::insert('host_templates', [
                    'host_id'    => $userId,
                    'title'      => $title,
                    'body'       => $body,
                    'trigger_on' => $trigger,
                    'icon'       => $icon,
                ]);
            }
        }
    }

    /* ----------------------------------------------------------- admin */

    public static function isAdmin(): bool
    {
        if (empty($_SESSION['admin'])) {
            return false;
        }
        $u = self::user();
        return $u !== null && ($u['role'] ?? '') === 'admin';
    }

    /** Gate an admin page: bounce to the login screen when not signed in. */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            redirect('admin-login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        }
    }

    /** Gate a guest page. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('auth.php?mode=signin&next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        }
    }

    /**
     * Anonymous visitors still get a stable key so wishlist/compare/recent
     * work before they sign up; it is merged into their account on signup.
     */
    public static function sessionKey(): string
    {
        if (empty($_SESSION['skey'])) {
            $_SESSION['skey'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['skey'];
    }
}
