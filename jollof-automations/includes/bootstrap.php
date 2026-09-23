<?php
/**
 * Jollof Automations — Bootstrap
 * Initializes session, error handling, configuration and database access.
 */
declare(strict_types=1);

if (!defined('JA_ROOT')) {
    define('JA_ROOT', dirname(__DIR__));
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function ja_config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $configFile = JA_ROOT . '/config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            $sample = JA_ROOT . '/config.sample.php';
            $config = file_exists($sample) ? require $sample : [];
        }
    }
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $cur = $config;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) {
            return $default;
        }
        $cur = $cur[$p];
    }
    return $cur;
}

function ja_e(?string $str): string
{
    return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ja_currency(float|int $amount): string
{
    $sym = (string) ja_config('site.currency_symbol', '₦');
    return $sym . number_format($amount, 0);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/models.php';
