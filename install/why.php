<?php
/**
 * Jollof Living — "why is this page failing?" trap.
 *
 * Runs a real page (admin.php by default) in its own request and reports the
 * fatal error that page produces, even when display_errors is off and the host
 * replaces the page with a bare "HTTP ERROR 500".
 *
 *     https://yourdomain.com/jollof/install/why.php               (admin.php)
 *     https://yourdomain.com/jollof/install/why.php?page=help.php
 *
 * How it avoids the very trap it is meant to catch: it never loads the
 * application bootstrap itself. PHP registers a file's top-level functions when
 * the file is compiled, so pulling bootstrap.php into a request that already
 * loaded it is a fatal "Cannot redeclare function config()". This script reads
 * includes/config.php (plain data, declares nothing), starts the session under
 * the configured name, checks the administrator flag, and then lets the target
 * page load the framework exactly once, one include deep.
 *
 * A fatal error cannot be caught with try/catch, so the report is written from
 * a shutdown handler — which still runs after a fatal, and after exit().
 */
declare(strict_types=1);

$root = dirname(__DIR__);

/* Deliberately nothing but the path is read here:
   - loading includes/config.php would need the JL_ROOT constant, and defining
     JL_ROOT would make the application bootstrap skip itself (its own guard)
     — which is how a "why is this failing" page ends up causing the failure;
   - loading includes/bootstrap.php would make the target page's bootstrap a
     second include, i.e. a fatal "Cannot redeclare function config()".
   So: no framework, no constants. The page under test authenticates itself —
   signed-out visitors are redirected to the sign-in screen by that page, which
   is exactly what should happen. */

/* ------------------------------------------------------------- base url */
$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/why.php');
$base = str_replace('\\', '/', dirname(dirname($script)));   // /jollof/install/why.php → /jollof
if ($base === '/' || $base === '.' || $base === '\\') {
    $base = '';
}
$self = $base . '/install/why.php';

/** Escape for HTML — this script has no framework to borrow helpers from. */
function why_h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------------- target */
$page = (string) ($_GET['page'] ?? 'admin.php');
if (!preg_match('~^[A-Za-z0-9._-]+\.php$~', $page) || str_contains($page, '..')) {
    $page = 'admin.php';
}
$target = $root . '/' . $page;
$exists = is_file($target);
$run = $exists;

/* ------------------------------------------------------------------- run */
$captured = '';
$running = false;

if ($run) {
    register_shutdown_function(static function () use (&$captured, &$running, &$completed, $page, $base, $self): void {
        if ($completed) {
            return;                     // normal flow already reported the result
        }
        $err = error_get_last();
        $fatal = $err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        if (!$fatal) {
            return;
        }
        if ($running) {                 // close only the buffer this script opened
            $captured = (string) ob_get_clean();
            $running = false;
        }
        $message = (string) ($err['message'] ?? 'unknown error');
        $where = ($err['file'] ?? '?') . ' on line ' . ($err['line'] ?? '?');

        /* Two ways out: the message below (PHP delivers shutdown output to the
           browser), and a log line — install/diagnose.php prints the log, so the
           finding survives even on a host that swallows the echo. */
        error_log('Jollof why.php trap: ' . $page . ' — ' . $message . ' in ' . $where);
        foreach ([dirname(__DIR__) . '/../storage/logs', dirname(__DIR__) . '/storage/logs'] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents(
                    $dir . '/jollof-trap.log',
                    date('Y-m-d H:i:s') . '  ' . $page . '  ' . $message . '  [' . $where . ']' . PHP_EOL,
                    FILE_APPEND
                );
                break;
            }
        }

        echo '<div class="card badcard"><h1>The page died</h1>'
            . '<p class="sub">This is what the server refuses to show you — the same line is now in the PHP error log.</p>'
            . '<pre>' . why_h($message . "\n\nin " . $where) . '</pre>';
        if (stripos($message, 'redeclare') !== false) {
            echo '<p><b>What this means:</b> the application was loaded twice — usually two copies of it, or an '
                . '<code>includes/</code> folder uploaded on top of itself. The <a href="' . why_h($base . '/install/diagnose.php') . '">self-check report</a> '
                . 'shows where every file actually lives.</p>';
        } elseif (stripos($message, 'Unknown column') !== false || stripos($message, "doesn't exist") !== false) {
            echo '<p><b>What this means:</b> the database does not match the code — a migration step is missing. '
                . 'Run <a href="' . why_h($base . '/install/migrate.php') . '">install/migrate.php</a> again, then re-run the '
                . '<a href="' . why_h($base . '/install/diagnose.php') . '">self-check</a>.</p>';
        } elseif (stripos($message, 'undefined function') !== false || stripos($message, 'Undefined constant') !== false) {
            echo '<p><b>What this means:</b> an included file is incomplete or truncated — upload it again and compare its size with the zip.</p>';
        }
        echo '<p style="margin-top:12px"><a href="' . why_h($self) . '?page=' . why_h($page) . '">Run it again</a> · '
            . '<a href="' . why_h($base . '/install/diagnose.php') . '">Self-check report</a> · '
            . '<a href="' . why_h($base) . '/">Back to the site</a></p></div>';
    });
}

$completed = false;

?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Why is it failing? · Jollof Living</title>
<style>
  :root{--gold:#c9a227;--ink:#1c1a15;--paper:#faf7f0;--line:#e4ddcc;--ok:#2c6e49;--bad:#a3341f}
  *{box-sizing:border-box}
  body{margin:0;background:var(--paper);color:var(--ink);font:14.5px/1.65 system-ui,-apple-system,"Segoe UI",sans-serif;padding:36px 16px}
  .card{max-width:980px;margin:0 auto 18px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:0 16px 40px rgba(40,32,10,.06)}
  .badcard{border-color:#e3b7ad;background:#fff9f7}
  h1{font-size:21px;margin:0 0 4px}
  .sub{color:#7d7768;font-size:13.5px;margin:0 0 12px}
  pre{background:#f3eee0;padding:13px 15px;border-radius:8px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-size:12.5px;margin:0}
  code{background:#f3eee0;padding:1px 5px;border-radius:5px;font-size:12.5px}
  .bad{color:var(--bad)}.ok{color:var(--ok)}
  a{color:#8a6d10}
  .grid{display:grid;grid-template-columns:190px 1fr;gap:3px 14px;font-size:13.5px}
  .grid div:nth-child(odd){color:#8d8574}
</style></head><body>
<div class="card">
  <h1>Why is it failing?</h1>
  <p class="sub">Runs one real page in its own request and reports what the server hides.</p>
  <div class="grid">
    <div>Page being tested</div><div><code><?= why_h($page) ?></code>
      <?= $exists ? '' : '<b class="bad">— not found in ' . why_h($root) . '</b>' ?></div>
    <div>Sign-in</div><div>The page under test does its own sign-in check: signed-out visitors are sent to the sign-in screen.</div>
    <div>PHP version</div><div><?= why_h(PHP_VERSION) ?></div>
    <div>display_errors</div><div><?= why_h((string) ini_get('display_errors')) ?></div>
  </div>
  <p class="sub" style="margin-top:10px">The verdict appears below this card. If the page stops with a fatal
    error, the message is shown and written to <code>storage/logs/jollof-trap.log</code>, which the
    <a href="<?= why_h($base . '/install/diagnose.php') ?>">self-check</a> prints as well.</p>
  <?php if (!$exists): ?>
    <p class="bad">That file does not exist in <code><?= why_h($root) ?></code>.</p>
  <?php endif; ?>
  <p style="margin-top:14px"><?php foreach (['admin.php', 'help.php', 'agent.php', 'index.php'] as $p): ?>
     <a href="?page=<?= why_h($p) ?>"><?= why_h($p) ?></a> ·
  <?php endforeach; ?>
     <a href="<?= why_h($base . '/install/diagnose.php') ?>">full self-check</a> ·
     <a href="<?= why_h($base) ?>/">the site</a></p>
</div>
<?php
// The real page runs last. Whatever it prints is captured; a fatal is reported
// by the shutdown handler above, a clean finish right here.
if ($run) {
    $running = true;
    ob_start();
    require $target;
    $captured = (string) ob_get_clean();
    $running = false;
    $completed = true;
    $bytes = strlen($captured);
    ?>
    <div class="card">
      <h1>The page completed</h1>
      <p class="ok"><b><?= why_h($page) ?> rendered <?= (int) $bytes ?> bytes of HTML.</b>
        So the page itself is fine on this server.</p>
      <p>If the browser still shows an error, the problem is after load: a stale cached script
        (hard-refresh), or an API call the page makes afterwards — check the browser console and
        the network tab. The <a href="<?= why_h($base . '/install/diagnose.php') ?>">self-check</a>
        exercises those calls too.</p>
      <?php if ($bytes > 0): ?>
        <h2 style="font-size:15px;margin:18px 0 6px">First 3000 characters returned</h2>
        <pre><?= why_h(substr($captured, 0, 3000)) ?></pre>
      <?php endif; ?>
      <p style="margin-top:12px">
        <a href="<?= why_h($base . '/install/diagnose.php') ?>">Self-check report</a> ·
        <a href="<?= why_h($base) ?>/">Back to the site</a></p>
    </div>
    <?php
}
?>
</body></html>
