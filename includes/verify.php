<?php
/**
 * Jollof Living — Email & Phone Verification & Token Service (WP12)
 *
 * Handles cryptographic verification tokens for email confirmation,
 * phone OTP, and account recovery challenges.
 */
declare(strict_types=1);

if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}

final class VerifyService
{
    private static bool $schemaChecked = false;

    /** Ensure verify_tokens table and verified_at columns exist. */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $isSqlite = DB::isSqlite();
            if (!DB::tableExists('verify_tokens')) {
                if ($isSqlite) {
                    DB::run('CREATE TABLE IF NOT EXISTS verify_tokens (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        purpose VARCHAR(32) NOT NULL DEFAULT "email",
                        token_hash CHAR(64) NOT NULL,
                        code_hash CHAR(64) NULL,
                        attempts INTEGER NOT NULL DEFAULT 0,
                        expires_at DATETIME NOT NULL,
                        consumed_at DATETIME NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )');
                    DB::run('CREATE INDEX IF NOT EXISTS ix_verify_token_hash ON verify_tokens (token_hash)');
                    DB::run('CREATE INDEX IF NOT EXISTS ix_verify_user_purpose ON verify_tokens (user_id, purpose)');
                } else {
                    DB::run('CREATE TABLE IF NOT EXISTS `verify_tokens` (
                        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        `user_id` int(10) UNSIGNED NOT NULL,
                        `purpose` varchar(32) NOT NULL DEFAULT "email",
                        `token_hash` char(64) NOT NULL,
                        `code_hash` char(64) DEFAULT NULL,
                        `attempts` tinyint(3) NOT NULL DEFAULT 0,
                        `expires_at` datetime NOT NULL,
                        `consumed_at` datetime DEFAULT NULL,
                        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY `uq_verify_token_hash` (`token_hash`),
                        KEY `ix_verify_user_purpose` (`user_id`, `purpose`),
                        KEY `ix_verify_expires` (`expires_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                }
            }

            if (!DB::columnExists('users', 'email_verified_at')) {
                DB::run('ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL');
            }
            if (!DB::columnExists('users', 'phone_verified_at')) {
                DB::run('ALTER TABLE users ADD COLUMN phone_verified_at DATETIME DEFAULT NULL');
            }
        } catch (Throwable $e) {
            error_log('VerifyService::ensureSchema error: ' . $e->getMessage());
        }
    }

    /**
     * Generate an email verification challenge token & 6-digit numeric OTP.
     */
    public static function createEmailToken(int $userId): array
    {
        self::ensureSchema();

        $rawToken = bin2hex(random_bytes(24));
        $rawCode  = sprintf('%06d', random_int(100000, 999999));
        $tokenHash = hash('sha256', $rawToken);
        $codeHash  = hash('sha256', $rawCode);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24-hour expiration

        // Invalidate older unused email verification tokens for this user
        try {
            DB::run('UPDATE verify_tokens SET consumed_at = ? WHERE user_id = ? AND purpose = "email" AND consumed_at IS NULL', [
                date('Y-m-d H:i:s'),
                $userId,
            ]);
        } catch (Throwable $e) {
        }

        DB::insert('verify_tokens', [
            'user_id'    => $userId,
            'purpose'    => 'email',
            'token_hash' => $tokenHash,
            'code_hash'  => $codeHash,
            'attempts'   => 0,
            'expires_at' => $expiresAt,
        ]);

        $siteUrl = rtrim((string) config('site.url', ''), '/');
        if ($siteUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $siteUrl = $scheme . '://' . $host . rtrim(base_path(), '/');
        }

        $verifyUrl = $siteUrl . '/verify-email.php?token=' . urlencode($rawToken);

        return [
            'token'      => $rawToken,
            'code'       => $rawCode,
            'verify_url' => $verifyUrl,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Dispatch the verification email to a user.
     */
    public static function sendEmailVerification(int $userId): bool
    {
        self::ensureSchema();
        $user = DB::row('SELECT id, name, email, email_verified_at FROM users WHERE id = ?', [$userId]);
        if (!$user || empty($user['email'])) {
            return false;
        }

        $t = self::createEmailToken($userId);
        $sent = Mailer::sendVerificationEmail(
            (string) $user['email'],
            (string) ($user['name'] ?: 'Guest'),
            $t['verify_url'],
            $t['code']
        );

        audit((string) $user['email'], 'Verification email ' . ($sent ? 'dispatched' : 'delivery failed'), $sent ? 'ok' : 'warn');
        return $sent;
    }

    /**
     * Validate a token from the verification link.
     */
    public static function verifyEmailToken(string $rawToken): array
    {
        self::ensureSchema();
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return ['ok' => false, 'message' => 'Invalid or missing verification link.'];
        }

        $tokenHash = hash('sha256', $rawToken);
        $row = DB::row(
            'SELECT * FROM verify_tokens WHERE token_hash = ? AND purpose = "email" ORDER BY id DESC LIMIT 1',
            [$tokenHash]
        );

        if (!$row) {
            return ['ok' => false, 'message' => 'This verification link is invalid or does not exist.'];
        }

        if (!empty($row['consumed_at'])) {
            return ['ok' => false, 'message' => 'This verification link has already been used.'];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'message' => 'This verification link has expired. Please request a fresh one.'];
        }

        $userId = (int) $row['user_id'];
        $now = date('Y-m-d H:i:s');

        // Mark token consumed
        DB::update('verify_tokens', ['consumed_at' => $now], 'id = :id', ['id' => $row['id']]);

        // Mark user verified
        DB::update('users', [
            'email_verified_at' => $now,
            'status'            => 'Verified',
            'status_level'      => 'ok',
        ], 'id = :id', ['id' => $userId]);

        Auth::flushCache();
        $user = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);

        if ($user) {
            audit((string) $user['email'], 'Email verified via link', 'ok');
            Repo::notify($userId, 'Email verified ✨', 'Your email address is verified. Welcome to the Jollof Living community.', 'check');
        }

        return [
            'ok'      => true,
            'message' => 'Your email has been verified successfully!',
            'user'    => $user,
        ];
    }

    /**
     * Validate by manual 6-digit code entry.
     */
    public static function verifyEmailCode(int $userId, string $code): array
    {
        self::ensureSchema();
        $code = preg_replace('~\D~', '', trim($code));
        if (strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'Please enter the 6-digit numeric verification code.'];
        }

        $codeHash = hash('sha256', $code);
        $row = DB::row(
            'SELECT * FROM verify_tokens WHERE user_id = ? AND purpose = "email" AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$userId]
        );

        if (!$row) {
            return ['ok' => false, 'message' => 'No active verification code found. Please request a new one.'];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'message' => 'The verification code has expired. Please request a new one.'];
        }

        if ((int) $row['attempts'] >= 5) {
            return ['ok' => false, 'message' => 'Too many invalid attempts. Please request a fresh code.'];
        }

        if (!hash_equals((string) $row['code_hash'], $codeHash)) {
            DB::run('UPDATE verify_tokens SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return ['ok' => false, 'message' => 'Incorrect verification code. Please check your email and try again.'];
        }

        $now = date('Y-m-d H:i:s');
        DB::update('verify_tokens', ['consumed_at' => $now], 'id = :id', ['id' => $row['id']]);
        DB::update('users', [
            'email_verified_at' => $now,
            'status'            => 'Verified',
            'status_level'      => 'ok',
        ], 'id = :id', ['id' => $userId]);

        Auth::flushCache();
        $user = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);

        if ($user) {
            audit((string) $user['email'], 'Email verified via code', 'ok');
            Repo::notify($userId, 'Email verified ✨', 'Your email address is verified. Welcome to the Jollof Living community.', 'check');
        }

        return [
            'ok'      => true,
            'message' => 'Your email has been verified successfully!',
            'user'    => $user,
        ];
    }

    /**
     * Resend verification email to user (throttled to 60s cooldown).
     */
    public static function resendVerification(?int $userId = null, ?string $email = null): array
    {
        self::ensureSchema();

        $user = null;
        if ($userId !== null && $userId > 0) {
            $user = DB::row('SELECT * FROM users WHERE id = ?', [$userId]);
        } elseif (!empty($email)) {
            $user = DB::row('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
        }

        if (!$user) {
            return [
                'ok'      => true,
                'message' => 'If an account exists with that email address, a verification link has been sent.',
            ];
        }

        if (!empty($user['email_verified_at'])) {
            return [
                'ok'               => true,
                'message'          => 'Your email is already verified! You have full access to your account.',
                'already_verified' => true,
            ];
        }

        // Throttle: 60-second cooldown between resends
        $recent = DB::row(
            'SELECT created_at FROM verify_tokens WHERE user_id = ? AND purpose = "email" ORDER BY id DESC LIMIT 1',
            [(int) $user['id']]
        );

        if ($recent) {
            $elapsed = time() - strtotime((string) $recent['created_at']);
            if ($elapsed < 60) {
                $wait = 60 - $elapsed;
                return [
                    'ok'      => false,
                    'message' => "Please wait {$wait} seconds before requesting another verification email.",
                ];
            }
        }

        $sent = self::sendEmailVerification((int) $user['id']);

        return [
            'ok'         => true,
            'message'    => $sent
                ? 'A new verification email has been sent to your address. Please check your inbox and spam folder.'
                : 'A verification link was generated, but the mail server could not dispatch the email. Please check your SMTP settings.',
            'email_sent' => $sent,
        ];
    }
}
