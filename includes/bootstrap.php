<?php
/**
 * Jollof Living — application bootstrap
 * Loaded first by every public page and API endpoint.
 */
declare(strict_types=1);

// loading twice (installer, CLI tooling) must be harmless
if (defined('JL_ROOT')) {
    return;
}

define('JL_ROOT', dirname(__DIR__));          // public_html
define('JL_INC', JL_ROOT . '/includes');
define('JL_START', microtime(true));

/* ------------------------------------------------------------- config */
$configFile = JL_INC . '/config.php';
if (!is_file($configFile)) {
    $sample = JL_INC . '/config.sample.php';
    if (is_file(JL_ROOT . '/../install/index.php') || is_dir(JL_ROOT . '/install')) {
        header('Location: install/');
        exit;
    }
    http_response_code(500);
    exit('Missing includes/config.php — copy includes/config.sample.php and fill in your database details.');
}
$GLOBALS['JL_CONFIG'] = require $configFile;

/**
 * Read a config value using dot notation: config('db.host')
 */
function config(string $key, $default = null)
{
    $node = $GLOBALS['JL_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

/* ------------------------------------------------------------ runtime */
date_default_timezone_set((string) config('site.timezone', 'Africa/Lagos'));
mb_internal_encoding('UTF-8');

if (config('debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!is_dir(JL_ROOT . '/../storage/logs')) {
        @mkdir(JL_ROOT . '/../storage/logs', 0775, true);
    }
    ini_set('error_log', JL_ROOT . '/../storage/logs/php-error.log');
}

/* ------------------------------------------------------------ fatals */
/* A hard PHP fatal (missing file, parse error in an include, class not
   found — usually a partially-uploaded deployment) must never leave a
   browser staring at a blank "HTTP ERROR 500". Render a short, honest
   notice instead; the cause is always in the log. With debug => true in
   config.php the real message is printed on screen as well. */
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
        return;
    }
    error_log('Jollof fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    if (config('debug') || headers_sent()) {
        return; // debug already displays it; too late to send clean headers
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Jollof Living — temporary error</title></head>'
        . '<body style="font-family:system-ui,sans-serif;background:#f6f2e9;color:#191612;display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<div style="max-width:520px;padding:32px;text-align:center">'
        . '<h1 style="font-size:22px;margin:0 0 10px">Something went wrong</h1>'
        . '<p style="color:#635d4f;line-height:1.6;margin:0 0 18px">The page failed to render. This is almost always a missing or outdated file after an upload — '
        . 'open <strong>health.php</strong> in the site folder for a point-and-click diagnosis.</p>'
        . '<p style="color:#8d8574;font-size:13px;margin:0">Site admins: the exact cause is in <code>storage/logs/php-error.log</code>, '
        . 'or set <code>\'debug\' => true</code> in <code>includes/config.php</code> to see it here.</p>'
        . '</div></body></html>';
});

/* ----------------------------------------------------------- includes */
require_once JL_INC . '/db.php';
require_once JL_INC . '/helpers.php';
require_once JL_INC . '/auth.php';
require_once JL_INC . '/repo.php';
require_once JL_INC . '/pricing.php';
require_once JL_INC . '/payments.php';
require_once JL_INC . '/ledger.php';
require_once JL_INC . '/cancellations.php';
require_once JL_INC . '/mailer.php';
require_once JL_INC . '/verify.php';
require_once JL_INC . '/concierge.php';

/* ------------------------------------------------------------ session */
Auth::startSession();
