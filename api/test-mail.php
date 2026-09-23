<?php
/**
 * Jollof Living — SMTP Diagnostic & Connectivity Test Tool
 *
 * Verifies SMTP socket connectivity, TLS/SSL handshake, authentication,
 * and test message dispatch with complete error transcripts.
 */
declare(strict_types=1);

require_once __DIR__ . '/_api.php';

$mailConfig = config('mail', []);
$smtp = $mailConfig['smtp'] ?? [];
$to = input_str('to') ?: (string) ($mailConfig['admin_to'] ?? '');
if (!is_email($to)) {
    $to = (string) ($smtp['user'] ?? '');
}

$user = (string) ($smtp['user'] ?? '');
$host = (string) ($smtp['host'] ?? '');
$port = (int) ($smtp['port'] ?? 465);
$secure = (string) ($smtp['secure'] ?? 'ssl');
$method = (string) ($mailConfig['method'] ?? 'smtp');

$diag = [
    'time'       => date('Y-m-d H:i:s T'),
    'configured' => [
        'mail_enabled' => (bool) ($mailConfig['enabled'] ?? false),
        'method'       => $method,
        'from_email'   => $mailConfig['from_email'] ?? '',
        'from_name'    => $mailConfig['from_name'] ?? '',
        'admin_to'     => $mailConfig['admin_to'] ?? '',
        'smtp_host'    => $host,
        'smtp_port'    => $port,
        'smtp_secure'  => $secure,
        'smtp_user'    => $user ? substr($user, 0, 3) . '***' . strstr($user, '@') : 'not set',
        'smtp_pass'    => !empty($smtp['pass']) ? 'configured (' . strlen((string)$smtp['pass']) . ' chars)' : 'empty',
    ],
    'steps' => [],
];

$step = function (string $name, bool $ok, string $detail) use (&$diag) {
    $diag['steps'][] = [
        'step'   => $name,
        'status' => $ok ? 'OK' : 'FAIL',
        'detail' => $detail,
    ];
};

if (empty($host)) {
    $step('Configuration Check', false, 'SMTP host is not defined in includes/config.php');
    json_fail('SMTP host missing in includes/config.php', 400, $diag);
}

// 1. Socket connection test
$remote = ($secure === 'ssl') ? ('ssl://' . preg_replace('~^ssl://~i', '', $host)) : preg_replace('~^ssl://~i', '', $host);
$start = microtime(true);
$errno = 0;
$errstr = '';

$cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
}
if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
}

$ctx = stream_context_create([
    'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
        'crypto_method'     => $cryptoMethod,
    ]
]);

$fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
$duration = round((microtime(true) - $start) * 1000, 2);

if (!$fp) {
    $step('Socket Connection', false, "Failed to connect to {$remote}:{$port} in {$duration}ms: {$errstr} ({$errno})");
    json_fail("Could not establish socket connection to {$remote}:{$port}", 502, $diag);
}

$step('Socket Connection', true, "Connected to {$remote}:{$port} in {$duration}ms");
stream_set_timeout($fp, 12);

$read = static function () use ($fp, &$lastCode): string {
    $data = '';
    $lastCode = 0;
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if ($line === false) break;
        $data .= $line;
        if (preg_match('/^([0-9]{3})(?:[ -]|$)/', $line, $m)) {
            $lastCode = (int) $m[1];
        }
        if (strlen($line) >= 4 && $line[3] !== '-') break;
        if (strlen($line) === 3) break;
    }
    return trim($data);
};

$cmd = static function (string $s, array $expected) use ($fp, $read, &$lastCode): array {
    fwrite($fp, $s . "\r\n");
    $resp = $read();
    return [in_array($lastCode, $expected, true), $lastCode, $resp];
};

// 2. Greeting Banner
$banner = $read();
$step('Initial Banner', $lastCode === 220, "Code [{$lastCode}]: " . substr($banner, 0, 120));

// 3. EHLO
$helo = 'localhost';
[$ehloOk, $code, $resp] = $cmd("EHLO {$helo}", [250]);
if (!$ehloOk) {
    [$ehloOk, $code, $resp] = $cmd("HELO {$helo}", [250]);
}
$step('EHLO/HELO Handshake', $ehloOk, "Code [{$code}]: " . substr($resp, 0, 120));

// 4. STARTTLS (if tls)
if ($secure === 'tls' && $ehloOk) {
    [$tlsOk, $code, $resp] = $cmd('STARTTLS', [220]);
    if ($tlsOk && @stream_socket_enable_crypto($fp, true, $cryptoMethod)) {
        $step('STARTTLS Upgrade', true, 'TLS encryption established.');
        [$ehloOk, $code, $resp] = $cmd("EHLO {$helo}", [250]);
    } else {
        $step('STARTTLS Upgrade', false, "STARTTLS failed [{$code}]: {$resp}");
    }
}

// 5. Authentication
if (!empty($smtp['user']) && $ehloOk) {
    [$authOk, $code, $resp] = $cmd('AUTH LOGIN', [334]);
    if ($authOk) {
        [$uOk, $code, $resp] = $cmd(base64_encode((string) $smtp['user']), [334]);
        if ($uOk) {
            [$pOk, $code, $resp] = $cmd(base64_encode((string) $smtp['pass']), [235, 250]);
            $step('Authentication (AUTH LOGIN)', $pOk, $pOk ? 'Authentication successful!' : "Password rejected [{$code}]: {$resp}");
        } else {
            $step('Authentication (AUTH LOGIN)', false, "Username rejected [{$code}]: {$resp}");
        }
    } else {
        $token = base64_encode("\0" . (string) $smtp['user'] . "\0" . (string) $smtp['pass']);
        [$plainOk, $code, $resp] = $cmd('AUTH PLAIN ' . $token, [235, 250]);
        $step('Authentication (AUTH PLAIN)', $plainOk, $plainOk ? 'Authentication successful!' : "Auth plain rejected [{$code}]: {$resp}");
    }
}

$cmd('QUIT', [221]);
fclose($fp);

// Optional: send real test message
$testSend = input_bool('send') || !empty(input_str('send_test'));
if ($testSend && is_email($to)) {
    $testBody = '<h2 style="color:#12180f">SMTP Configuration Test</h2>'
        . '<p>This is a live test email sent from <b>' . e($siteName) . '</b>.</p>'
        . '<p>Your SMTP mail transport is operational and correctly connected to <code>' . e($host) . ':' . $port . '</code>.</p>'
        . '<p>Timestamp: ' . date('r') . '</p>';

    $sent = Mailer::send($to, 'Jollof Living · SMTP Diagnostic Test Email', $testBody);
    $step('Test Email Dispatch', $sent, $sent ? "Test email successfully delivered to {$to}" : 'Failed: ' . Mailer::lastError());
}

json_ok($diag, 'SMTP diagnostics completed.');
