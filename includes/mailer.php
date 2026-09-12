<?php
/**
 * Jollof Living — transactional email.
 * Uses PHP mail() by default, which works out of the box on HostGator
 * shared hosting. Switch to SMTP in config.php if you prefer.
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
    {
        if (!config('mail.enabled') || !is_email($to)) {
            return false;
        }
        $fromEmail = (string) config('mail.from_email', 'no-reply@localhost');
        $fromName  = (string) config('mail.from_name', 'Jollof Living');

        if (config('mail.method') === 'smtp') {
            return self::smtp($to, $subject, $htmlBody, $replyTo);
        }

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
            return @mail($to, self::encodeName($subject), self::wrap($subject, $htmlBody), implode("\r\n", $headers), '-f' . $fromEmail);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Minimal SMTP client so no Composer dependency is needed. */
    private static function smtp(string $to, string $subject, string $html, string $replyTo = ''): bool
    {
        $c = config('mail.smtp');
        $host = ($c['secure'] ?? '') === 'ssl' ? 'ssl://' . $c['host'] : (string) $c['host'];
        $fp = @stream_socket_client($host . ':' . (int) $c['port'], $errno, $errstr, 15);
        if (!$fp) {
            error_log('SMTP connect failed: ' . $errstr);
            return false;
        }
        $read = static function () use ($fp) {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = static function (string $s) use ($fp, $read) {
            fwrite($fp, $s . "\r\n");
            return $read();
        };
        $read();
        $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (($c['secure'] ?? '') === 'tls') {
            $cmd('STARTTLS');
            @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }
        if (!empty($c['user'])) {
            $cmd('AUTH LOGIN');
            $cmd(base64_encode((string) $c['user']));
            $cmd(base64_encode((string) $c['pass']));
        }
        $from = (string) config('mail.from_email');
        $cmd('MAIL FROM:<' . $from . '>');
        $cmd('RCPT TO:<' . $to . '>');
        $cmd('DATA');
        $headers = "From: " . self::encodeName((string) config('mail.from_name')) . " <$from>\r\n"
            . "To: <$to>\r\n"
            . 'Subject: ' . self::encodeName($subject) . "\r\n"
            . ($replyTo ? "Reply-To: <$replyTo>\r\n" : '')
            . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
        $cmd($headers . self::wrap($subject, $html) . "\r\n.");
        $cmd('QUIT');
        fclose($fp);
        return true;
    }

    private static function encodeName(string $s): string
    {
        return preg_match('~[^\x20-\x7E]~', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    /** House-style HTML wrapper matching the brand. */
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
    <tr><td style="background:#12180f;padding:22px 28px;text-align:center">
      <div style="font-size:24px;letter-spacing:.02em;color:#e9c667">{$site}</div>
      <div style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:#c9c4b5;margin-top:6px">{$tag}</div>
    </td></tr>
    <tr><td style="padding:30px 28px;font-size:15px;line-height:1.65">{$body}</td></tr>
    <tr><td style="background:#faf8f3;border-top:1px solid #e7e1d4;padding:18px 28px;font-size:12px;color:#7d7768;text-align:center">
      {$url} · Funds are held in escrow and released after check-in confirmation.
    </td></tr>
  </table>
</td></tr></table></body></html>
HTML;
    }

    /* --------------------------------------------------------- templates */

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
