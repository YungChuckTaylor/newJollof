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

/* ----------------------------------------------------------- includes */
require_once JL_INC . '/db.php';
require_once JL_INC . '/helpers.php';
require_once JL_INC . '/auth.php';
require_once JL_INC . '/repo.php';
require_once JL_INC . '/pricing.php';
require_once JL_INC . '/mailer.php';
require_once JL_INC . '/concierge.php';

/* ------------------------------------------------------------ session */
Auth::startSession();
