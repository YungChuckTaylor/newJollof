<?php
/**
 * Jollof Living — one-page deployment health check.
 *
 * STANDALONE ON PURPOSE: this file deliberately does NOT load the app
 * (no bootstrap.php), so it can never die with the same fatal error it is
 * trying to diagnose. Open it in a browser:
 *
 *     https://<your-host>/jollof/health.php
 *
 * It checks, in order:
 *   1. PHP version + required extensions
 *   2. Presence of every file the app requires (catches partial uploads —
 *      the #1 cause of a blank HTTP 500 after a manual cPanel upload)
 *   3. storage/ writability (where PHP errors are logged)
 *   4. Database connection — and, because cPanel prefixes DB names with the
 *      account user, it ALSO tries the prefixed variant automatically.
 *
 * ⚠️  DELETE THIS FILE once your site is up.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$CFG = @include __DIR__ . '/includes/config.php';
$debug = is_array($CFG) && !empty($CFG['debug']);

$ok   = static fn(string $s) => '<span style="color:#0a7d33;font-weight:700">PASS</span> ' . htmlspecialchars($s);
$fail = static fn(string $s) => '<span style="color:#c0392b;font-weight:700">FAIL</span> ' . htmlspecialchars($s);
$warn = static fn(string $s) => '<span style="color:#b07d00;font-weight:700">WARN</span> ' . htmlspecialchars($s);

$rows = [];
$allOk = true;
$check = static function (string $label, string $status, string $note = '') use (&$rows, &$allOk) {
    $rows[] = [$status, $label, $note];
    if ($status === 'fail') { $allOk = false; }
};

/* ---------------------------------------------------------------- 1. PHP */
$check('PHP version', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'pass' : 'fail',
    'running ' . PHP_VERSION . ' — this app requires 8.0+');

$exts = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session', 'gd', 'curl'];
foreach ($exts as $ext) {
    $check("Extension: $ext", extension_loaded($ext) ? 'pass' : (($ext === 'gd' || $ext === 'curl') ? 'warn' : 'fail'),
        extension_loaded($ext) ? 'loaded' : 'NOT loaded');
}

/* ------------------------------------------------------- 2. app files */
$required = [
    'includes/bootstrap.php', 'includes/config.php', 'includes/db.php',
    'includes/helpers.php',   'includes/auth.php',     'includes/repo.php',
    'includes/pricing.php',   'includes/payments.php', 'includes/ledger.php',
    'includes/cancellations.php', 'includes/mailer.php', 'includes/verify.php',
    'includes/concierge.php', 'includes/view.php',
    'includes/ssr.php',       // ← NEW in the SEO update; missing file = blank 500
    'index.php', 'stays.php', 'stay.php', 'sitemap.php', '404.php',
];
foreach ($required as $rel) {
    if (is_file(__DIR__ . '/' . $rel)) {
        $m = @filemtime(__DIR__ . '/' . $rel);
        $check("File: $rel", 'pass', $m ? 'modified ' . date('Y-m-d H:i', $m) : '');
    } else {
        $check("File: $rel", 'fail', 'MISSING ON SERVER — re-upload it');
    }
}

/* --------------------------------------------------- 3. writable dirs */
$storage = dirname(__DIR__) === '' ? __DIR__ . '/storage' : __DIR__ . '/../storage';
$storage = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
if (!is_dir($storage . '/logs')) {
    @mkdir($storage . '/logs', 0775, true);
}
$check('storage/logs writable (PHP error log location)',
    is_writable($storage . '/logs') || @mkdir($storage . '/logs', 0775, true) ? 'pass' : 'warn',
    $storage . '/logs');

/* ------------------------------------------------------------ 4. DB */
if (!is_array($CFG)) {
    $check('includes/config.php', 'fail', 'file exists but did not return an array — check for a syntax error');
} else {
    $db   = $CFG['db'] ?? [];
    $host = (string) ($db['host'] ?? 'localhost');
    $name = (string) ($db['name'] ?? '');
    $user = (string) ($db['user'] ?? '');
    $pass = (string) ($db['pass'] ?? '');

    $tryConnect = static function (string $dbName, string $dbUser) use ($host, $pass): string {
        try {
            new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $dbUser, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            return 'OK';
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    };

    /* cPanel prefixes both DB name and user with the account name. Guess the
       prefix from the home directory path: /home2/wal7zkit4bnv/… */
    $cpanelUser = '';
    if (preg_match('#/home\d+/([A-Za-z0-9_-]+)/#', __DIR__, $m)) {
        $cpanelUser = $m[1];
    }
    $prefixedName = $cpanelUser !== '' && strpos($name, $cpanelUser . '_') !== 0 ? $cpanelUser . '_' . ltrim($name, '_') : $name;
    $prefixedUser = $cpanelUser !== '' && strpos($user, $cpanelUser . '_') !== 0 ? $cpanelUser . '_' . ltrim($user, '_') : $user;

    $r1 = $tryConnect($name, $user);
    if ($r1 === 'OK') {
        $check('Database connect (as configured)', 'pass', "$user @$name");
    } else {
        $r2 = $tryConnect($prefixedName, $prefixedUser);
        if ($r2 === 'OK') {
            $check('Database connect (as configured)', 'fail', "access failed — BUT the cPanel-prefixed variant WORKS");
            $check('Fix your config.php', 'fail',
                "set  'name' => '$prefixedName'  and  'user' => '$prefixedUser'  in includes/config.php");
        } else {
            $check('Database connect (as configured)', 'fail', $debug ? $r1 : 'failed (set debug => true in config.php to see why)');
            if ($prefixedName !== $name) {
                $check('Database connect (prefixed variant)', 'fail', $debug ? $r2 : 'also failed — create the DB in cPanel → MySQL® Databases and import wal7zkit_jollof.sql');
            }
        }
    }

    /* is there actually data in it? */
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$prefixedName;charset=utf8mb4", $prefixedUser, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $n = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
        $check('Catalogue data', $n > 0 ? 'pass' : 'warn', "$n live properties — " . ($n > 0 ? 'looks imported' : 'import wal7zkit_jollof.sql via cPanel → phpMyAdmin'));
    } catch (Throwable $e) { /* reported above */ }
}

/* ------------------------------------------------------------- output */
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Jollof Living — deployment health check</title>
<style>
  body{font:15px/1.6 -apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f6f2e9;color:#191612}
  .wrap{max-width:860px;margin:0 auto;padding:40px 20px}
  h1{font-size:26px;margin:0 0 4px}
  .sub{color:#635d4f;margin:0 0 24px}
  table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  td{padding:10px 14px;border-bottom:1px solid #eee;vertical-align:top}
  tr:last-child td{border-bottom:0}
  .verdict{margin:22px 0;padding:16px 20px;border-radius:12px;font-weight:700}
  .good{background:#e5f5ea;color:#0a7d33}.bad{background:#fdecea;color:#c0392b}
  .note{background:#fff;border-radius:12px;padding:16px 20px;margin-top:22px;color:#635d4f;font-size:13px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Jollof Living — deployment health check</h1>
  <p class="sub">Standalone diagnostic — loads nothing from the app, so it can always run.</p>
  <table>
    <?php foreach ($rows as [$status, $label, $note]): ?>
    <tr>
      <td style="width:70px"><?= $status === 'pass' ? $ok('PASS') : ($status === 'warn' ? $warn('WARN') : $fail('FAIL')) ?></td>
      <td><strong><?= htmlspecialchars($label) ?></strong><?php if ($note !== ''): ?><br><span style="color:#635d4f;font-size:13px"><?= htmlspecialchars($note) ?></span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="verdict <?= $allOk ? 'good' : 'bad' ?>">
    <?= $allOk ? '✅ Everything checks out — the site should load. If it still 500s, set debug => true in includes/config.php and reload the homepage to see the exact error.' : '❌ Fix the FAIL rows above (they are listed in the order to fix them), then reload this page.' ?>
  </div>
  <div class="note">
    <strong>Reminder:</strong> delete <code>health.php</code> from the server once you are done.
    · New/changed files from the SEO update that must exist on the server:
    <code>includes/ssr.php</code>, <code>.htaccess</code>, <code>sitemap.php</code>,
    <code>install/schema/2026_09_24_seo_metadata.sql</code>, <code>assets/img/*.webp</code>,
    <code>assets/js/site.min.js</code>, <code>assets/css/site.min.css</code>.
  </div>
</div>
</body>
</html>
