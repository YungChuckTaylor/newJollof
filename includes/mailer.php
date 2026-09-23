<?php
/**
 * Jollof Living — transactional email & robust SMTP transport.
 * Supports direct SMTP (SSL port 465, TLS/STARTTLS port 587) and PHP mail().
 */
declare(strict_types=1);

if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}

final class Mailer
{
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function send(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
    {
        self::$lastError = '';

        if (!config('mail.enabled', true) || !is_email($to)) {
            self::$lastError = 'Mail disabled or invalid destination email: ' . $to;
            return false;
        }

        $method = strtolower((string) config('mail.method', 'smtp'));
        $smtpConfig = config('mail.smtp', []);
        $hasSmtpCreds = !empty($smtpConfig['host']) && !empty($smtpConfig['user']);

        // When method is 'smtp' or when SMTP credentials are supplied in config, use SMTP
        if ($method === 'smtp' || $hasSmtpCreds) {
            $ok = self::smtp($to, $subject, $htmlBody, $replyTo);
            if ($ok) {
                return true;
            }
            // If SMTP was explicitly requested and failed, fail closed with error logged
            if ($method === 'smtp') {
                return false;
            }
        }

        // Native PHP mail() fallback
        $fromEmail = (string) config('mail.from_email', 'no-reply@localhost');
        $fromName  = (string) config('mail.from_name', 'Jollof Living');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . '>',
        ];
        if ($replyTo && is_email($replyTo)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'X-Mailer: JollofLiving';

        try {
            $sent = @mail($to, self::encodeName($subject), self::wrap($subject, $htmlBody), implode("\r\n", $headers), '-f' . $fromEmail);
            if (!$sent) {
                self::$lastError = 'PHP mail() returned false.';
                error_log('Mailer::send PHP mail() failed for ' . $to);
            }
            return $sent;
        } catch (Throwable $e) {
            self::$lastError = 'mail() exception: ' . $e->getMessage();
            error_log(self::$lastError);
            return false;
        }
    }

    /**
     * Enterprise-grade socket SMTP client.
     * Compatible with cPanel, HostGator, Exim, Postfix, and modern TLS/SSL requirements.
     */
    private static function smtp(string $to, string $subject, string $html, string $replyTo = ''): bool
    {
        $c = config('mail.smtp', []);
        $rawHost = trim((string) ($c['host'] ?? ''));
        if ($rawHost === '') {
            self::$lastError = 'SMTP host is empty in includes/config.php';
            error_log(self::$lastError);
            return false;
        }

        $port = (int) ($c['port'] ?? 465);
        if ($port <= 0) {
            $port = 465;
        }

        $secure = strtolower(trim((string) ($c['secure'] ?? '')));
        if ($secure === '') {
            $secure = ($port === 465) ? 'ssl' : (($port === 587) ? 'tls' : '');
        }

        // Clean protocol prefix if host has one
        $cleanHost = preg_replace('~^(ssl|tls|tcp)://~i', '', $rawHost);
        $remote = ($secure === 'ssl') ? ('ssl://' . $cleanHost) : $cleanHost;

        // Shared hosting (cPanel/HostGator) often serves host-level or self-signed certs for mail.domain.
        // We set permissive context options so the connection does not abort on cert name mismatch.
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => $cryptoMethod,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            $remote . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$fp) {
            self::$lastError = "SMTP socket connect failed to {$remote}:{$port} - {$errstr} ({$errno})";
            error_log('Mailer SMTP: ' . self::$lastError);
            return false;
        }

        stream_set_timeout($fp, 15);

        // Helper: read multiline SMTP responses and extract status code
        $lastCode = 0;
        $read = static function () use ($fp, &$lastCode): string {
            $data = '';
            $lastCode = 0;
            while (!feof($fp)) {
                $line = fgets($fp, 1024);
                if ($line === false) {
                    break;
                }
                $data .= $line;
                if (preg_match('/^([0-9]{3})(?:[ -]|$)/', $line, $m)) {
                    $lastCode = (int) $m[1];
                }
                // Last line in multiline SMTP has space after status code or line length is exactly 3
                if (strlen($line) >= 4 && $line[3] !== '-') {
                    break;
                }
                if (strlen($line) === 3) {
                    break;
                }
            }
            return trim($data);
        };

        // Helper: send command and verify response code
        $cmd = static function (string $s, array $expectedCodes) use ($fp, $read, &$lastCode): array {
            fwrite($fp, $s . "\r\n");
            $resp = $read();
            $ok = in_array($lastCode, $expectedCodes, true);
            return [$ok, $lastCode, $resp];
        };

        // 1. Initial greeting (expect 220)
        $banner = $read();
        if ($lastCode !== 220) {
            self::$lastError = "SMTP initial banner rejected [{$lastCode}]: {$banner}";
            error_log('Mailer SMTP: ' . self::$lastError);
            fclose($fp);
            return false;
        }

        // 2. EHLO / HELO
        $heloHost = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? parse_url((string) config('site.url'), PHP_URL_HOST) ?? 'localhost';
        $heloHost = preg_replace('~[^a-zA-Z0-9.-]~', '', (string) $heloHost) ?: 'localhost';

        [$ehloOk, $cCode, $ehloResp] = $cmd("EHLO {$heloHost}", [250]);
        if (!$ehloOk) {
            [$heloOk, $cCode, $heloResp] = $cmd("HELO {$heloHost}", [250]);
            if (!$heloOk) {
                self::$lastError = "SMTP EHLO/HELO failed [{$cCode}]: {$ehloResp}";
                error_log('Mailer SMTP: ' . self::$lastError);
                fclose($fp);
                return false;
            }
        }

        // 3. STARTTLS (for port 587 or tls mode)
        if ($secure === 'tls') {
            [$tlsOk, $cCode, $tlsResp] = $cmd('STARTTLS', [220]);
            if (!$tlsOk) {
                self::$lastError = "SMTP STARTTLS command failed [{$cCode}]: {$tlsResp}";
                error_log('Mailer SMTP: ' . self::$lastError);
                fclose($fp);
                return false;
            }

            if (!@stream_socket_enable_crypto($fp, true, $cryptoMethod)) {
                self::$lastError = 'SMTP stream_socket_enable_crypto failed during STARTTLS';
                error_log('Mailer SMTP: ' . self::$lastError);
                fclose($fp);
                return false;
            }

            // Re-issue EHLO after TLS negotiation (RFC 3207)
            [$ehloOk, $cCode, $ehloResp] = $cmd("EHLO {$heloHost}", [250]);
            if (!$ehloOk) {
                self::$lastError = "SMTP EHLO after TLS failed [{$cCode}]: {$ehloResp}";
                error_log('Mailer SMTP: ' . self::$lastError);
                fclose($fp);
                return false;
            }
        }

        // 4. Authentication (AUTH LOGIN or AUTH PLAIN)
        $user = (string) ($c['user'] ?? '');
        $pass = (string) ($c['pass'] ?? '');
        if ($user !== '') {
            [$authOk, $cCode, $authResp] = $cmd('AUTH LOGIN', [334]);
            if ($authOk) {
                [$uOk, $cCode, $uResp] = $cmd(base64_encode($user), [334]);
                if (!$uOk) {
                    self::$lastError = "SMTP AUTH username rejected [{$cCode}]: {$uResp}";
                    error_log('Mailer SMTP: ' . self::$lastError);
                    fclose($fp);
                    return false;
                }
                [$pOk, $cCode, $pResp] = $cmd(base64_encode($pass), [235, 250]);
                if (!$pOk) {
                    self::$lastError = "SMTP AUTH password rejected [{$cCode}]: {$pResp}";
                    error_log('Mailer SMTP: ' . self::$lastError);
                    fclose($fp);
                    return false;
                }
            } else {
                // Fall back to AUTH PLAIN
                $plainToken = base64_encode("\0" . $user . "\0" . $pass);
                [$plainOk, $cCode, $plainResp] = $cmd('AUTH PLAIN ' . $plainToken, [235, 250]);
                if (!$plainOk) {
                    self::$lastError = "SMTP Authentication failed [{$cCode}]: {$authResp} / {$plainResp}";
                    error_log('Mailer SMTP: ' . self::$lastError);
                    fclose($fp);
                    return false;
                }
            }
        }

        // 5. Envelope sender: cPanel Exim requires sender to match authenticated account or domain
        $from = (string) config('mail.from_email', '');
        if (empty($from) || strpos($from, 'localhost') !== false || ($from === 'no-reply@jollofliving.com' && strpos($user, '@') !== false)) {
            if (strpos($user, '@') !== false) {
                $from = $user;
            }
        }
        if (empty($from)) {
            $from = 'no-reply@' . ($heloHost ?: 'localhost');
        }

        [$fromOk, $cCode, $fromResp] = $cmd("MAIL FROM:<{$from}>", [250]);
        if (!$fromOk) {
            self::$lastError = "SMTP MAIL FROM:<{$from}> rejected [{$cCode}]: {$fromResp}";
            error_log('Mailer SMTP: ' . self::$lastError);
            fclose($fp);
            return false;
        }

        [$rcptOk, $cCode, $rcptResp] = $cmd("RCPT TO:<{$to}>", [250, 251]);
        if (!$rcptOk) {
            self::$lastError = "SMTP RCPT TO:<{$to}> rejected [{$cCode}]: {$rcptResp}";
            error_log('Mailer SMTP: ' . self::$lastError);
            fclose($fp);
            return false;
        }

        [$dataOk, $cCode, $dataResp] = $cmd('DATA', [354]);
        if (!$dataOk) {
            self::$lastError = "SMTP DATA command rejected [{$cCode}]: {$dataResp}";
            error_log('Mailer SMTP: ' . self::$lastError);
            fclose($fp);
            return false;
        }

        // 6. Format headers and body with strict RFC 5321 CRLF line endings & dot-stuffing
        $fromName = (string) config('mail.from_name', 'Jollof Living');
        $headers = "From: " . self::encodeName($fromName) . " <{$from}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: " . self::encodeName($subject) . "\r\n"
            . ($replyTo && is_email($replyTo) ? "Reply-To: <{$replyTo}>\r\n" : '')
            . "Date: " . date('r') . "\r\n"
            . "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . ($heloHost ?: 'localhost') . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n";

        $fullBody = self::wrap($subject, $html);
        $rawMessage = $headers . $fullBody;
        $normalized = preg_replace('~\r\n?|\n~', "\r\n", $rawMessage);

        $lines = explode("\r\n", $normalized);
        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
        }
        $payload = implode("\r\n", $lines) . "\r\n.\r\n";

        fwrite($fp, $payload);
        $dataResult = $read();
        if (!in_array($lastCode, [250], true)) {
            self::$lastError = "SMTP Message content rejected [{$lastCode}]: {$dataResult}";
            error_log('Mailer SMTP: ' . self::$lastError);
            $cmd('QUIT', [221]);
            fclose($fp);
            return false;
        }

        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }

    private static function encodeName(string $s): string
    {
        return preg_match('~[^\x20-\x7E]~', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    /** House-style HTML wrapper matching the luxury brand aesthetic. */
    private static function wrap(string $title, string $body): string
    {
        $site = e((string) config('site.name', 'Jollof Living'));
        $tag  = e((string) config('site.tagline', 'Luxury Living, African Soul'));
        $url  = e((string) config('site.url', ''));
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f6f4ef;font-family:Georgia,'Times New Roman',serif;color:#1b1b17">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f4ef;padding:28px 12px">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e7e1d4;border-radius:14px;overflow:hidden">
    <tr><td style="background:#12180f;padding:24px 28px;text-align:center">
      <div style="font-size:24px;letter-spacing:.02em;color:#e9c667;font-weight:700">{$site}</div>
      <div style="font-size:11px;letter-spacing:.25em;text-transform:uppercase;color:#c9c4b5;margin-top:6px">{$tag}</div>
    </td></tr>
    <tr><td style="padding:32px 28px;font-size:15px;line-height:1.65;color:#2b2923">{$body}</td></tr>
    <tr><td style="background:#faf8f3;border-top:1px solid #e7e1d4;padding:18px 28px;font-size:12px;color:#7d7768;text-align:center">
      {$url} · Funds are held in escrow and released after check-in confirmation.
    </td></tr>
  </table>
</td></tr></table></body></html>
HTML;
    }

    /* --------------------------------------------------------- templates */

    /**
     * Verification email with secure one-click link and numeric code.
     */
    public static function sendVerificationEmail(string $to, string $name, string $verifyUrl, string $code): bool
    {
        $site = e((string) config('site.name', 'Jollof Living'));
        $body = '<h2 style="margin:0 0 12px;font-size:22px;color:#12180f">Verify your email address</h2>'
            . '<p>Hello <b>' . e($name) . '</b>,</p>'
            . '<p>Thank you for creating your account with ' . $site . '. Please verify your email address to complete your registration, access luxury residences, and enjoy escrow-protected bookings.</p>'
            . '<div style="margin:26px 0;text-align:center">'
            . '<a href="' . e($verifyUrl) . '" style="display:inline-block;background:#e9c667;color:#12180f;padding:13px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;letter-spacing:.02em">Verify Email Address</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#7d7768">If the button does not work, copy and paste this link into your browser:<br>'
            . '<a href="' . e($verifyUrl) . '" style="color:#a5851f;word-break:break-all">' . e($verifyUrl) . '</a></p>'
            . '<div style="margin:18px 0;padding:12px 16px;background:#faf8f3;border-left:3px solid #e9c667;border-radius:4px">'
            . '<div style="font-size:12px;color:#7d7768">Or enter this 6-digit confirmation code:</div>'
            . '<div style="font-size:22px;font-weight:700;letter-spacing:.15em;color:#12180f;margin-top:4px">' . e($code) . '</div>'
            . '</div>'
            . '<p style="font-size:12px;color:#9b9588;margin-top:24px">This verification link is valid for 24 hours. If you did not sign up for an account, please disregard this email.</p>';

        return self::send($to, 'Verify your email address · ' . (string) config('site.name', 'Jollof Living'), $body);
    }

    public static function bookingConfirmation(?array $b, ?array $p = null): bool
    {
        if (!$b) {
            return false;
        }
        $isReq = (int) $b['is_request'] === 1;
        $head = $isReq ? 'Your request has been sent' : 'Your reservation is confirmed';
        $total = money((int) $b['total'], 'NGN');
        $body = '<h2 style="margin:0 0 12px;font-size:22px">' . e($head) . '</h2>'
            . '<p>Hello ' . e((string) $b['guest_name']) . ',</p>'
            . '<p>Reference <b style="color:#a5851f">' . e((string) $b['ref']) . '</b></p>'
            . '<table cellpadding="6" style="font-size:14px;border-collapse:collapse;margin:14px 0;width:100%">'
            . '<tr><td style="color:#7d7768">Residence</td><td align="right"><b>' . e((string) $b['property_name']) . '</b></td></tr>'
            . '<tr><td style="color:#7d7768">Where</td><td align="right">' . e((string) $b['area']) . ', ' . e((string) $b['city']) . '</td></tr>'
            . '<tr><td style="color:#7d7768">Dates</td><td align="right">' . e((string) $b['checkin']) . ' → ' . e((string) $b['checkout']) . ' (' . (int) $b['nights'] . ' nights)</td></tr>'
            . '<tr><td style="color:#7d7768">Guests</td><td align="right">' . (int) $b['guests'] . '</td></tr>'
            . '<tr><td style="color:#7d7768">Total</td><td align="right"><b style="font-size:18px">' . e($total) . '</b></td></tr>'
            . ($b['checkin_code'] ? '<tr><td style="color:#7d7768">Check-in code</td><td align="right"><b>' . e((string) $b['checkin_code']) . '</b></td></tr>' : '')
            . '</table>'
            . '<p style="font-size:13px;color:#7d7768">' . ($isReq
                ? 'The host has 24 hours to confirm. Your card has been authorised but not charged.'
                : 'Your payment is held securely in escrow and released to the host only after you confirm check-in.') . '</p>';

        $ok = self::send((string) $b['guest_email'], $head . ' · ' . $b['ref'], $body);
        $adminTo = (string) config('mail.admin_to');
        if ($adminTo) {
            self::send($adminTo, 'New booking ' . $b['ref'] . ' · ' . $b['property_name'], $body, (string) $b['guest_email']);
        }
        return $ok;
    }

    public static function welcome(string $to, string $name): bool
    {
        return self::send($to, 'Welcome to Jollof Living', '<h2 style="margin:0 0 12px;font-size:22px">Welcome, ' . e($name) . '.</h2>'
            . '<p>Your account is ready. Browse the collection, save residences to your wishlist and book with escrow-protected payments.</p>'
            . '<p><a href="' . e((string) config('site.url')) . '/stays.php" style="display:inline-block;background:#e9c667;color:#231a05;padding:11px 20px;border-radius:8px;text-decoration:none;font-weight:600">Browse residences</a></p>');
    }

    public static function enquiry(array $data): bool
    {
        $to = (string) config('mail.admin_to');
        if (!$to) {
            return false;
        }
        $rows = '';
        foreach ($data as $k => $v) {
            $rows .= '<tr><td style="color:#7d7768">' . e(ucfirst((string) $k)) . '</td><td>' . nl2br(e((string) (is_scalar($v) ? $v : json_encode($v)))) . '</td></tr>';
        }
        return self::send($to, 'New enquiry · ' . ($data['kind'] ?? 'contact'),
            '<h2 style="margin:0 0 12px;font-size:20px">New enquiry</h2><table cellpadding="6" style="font-size:14px">' . $rows . '</table>',
            (string) ($data['email'] ?? ''));
    }

    /** Alert the operations inbox. */
    public static function adminNotice(string $subject, string $body): bool
    {
        $to = (string) config('mail.admin_to');
        if (!$to) {
            return false;
        }
        return self::send($to, $subject, '<h2 style="margin:0 0 12px;font-size:20px">' . e($subject) . '</h2><p>' . nl2br(e($body)) . '</p>');
    }

    /** Notify a host that a guest replied in a thread. */
    public static function hostMessage(array $conversation, array $fromUser, string $text): bool
    {
        $to = (string) ($conversation['host_email'] ?? config('mail.admin_to'));
        if (!$to) {
            return false;
        }
        return self::send(
            $to,
            'New message from ' . ($fromUser['name'] ?? 'a guest'),
            '<h2 style="margin:0 0 12px;font-size:20px">New message</h2>'
            . '<p><b>' . e((string) ($fromUser['name'] ?? 'Guest')) . '</b> wrote:</p>'
            . '<blockquote style="border-left:3px solid #e9c667;margin:0;padding:6px 14px;color:#3b3627">' . nl2br(e($text)) . '</blockquote>',
            (string) ($fromUser['email'] ?? '')
        );
    }
}
