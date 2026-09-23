<?php
/**
 * Jollof Automations — Configuration File
 * A Subsidiary of Jollof Living Limited.
 */
declare(strict_types=1);

// Attempt to inherit from parent Jollof Living configuration if available
$parentConfigFile = dirname(__DIR__) . '/includes/config.php';
$parentConfig = [];
if (file_exists($parentConfigFile)) {
    if (!defined('JL_ROOT')) {
        define('JL_ROOT', dirname(__DIR__));
    }
    try {
        $parentConfig = require $parentConfigFile;
    } catch (\Throwable $e) {
        $parentConfig = [];
    }
}

return [
    'db' => [
        'driver'   => $parentConfig['db']['driver']   ?? 'mysql',
        'host'     => $parentConfig['db']['host']     ?? 'localhost',
        'port'     => (int) ($parentConfig['db']['port'] ?? 3306),
        'name'     => $parentConfig['db']['name']     ?? 'wal7zkit_jollof',
        'user'     => $parentConfig['db']['user']     ?? 'wal7zkit_jollof',
        'pass'     => $parentConfig['db']['pass']     ?? 'Chinwike@100',
        'charset'  => 'utf8mb4',
        'sqlite_path' => __DIR__ . '/storage/automations.sqlite',
    ],

    'site' => [
        'name'      => 'Jollof Automations',
        'tagline'   => 'Turn Your Residence Into an Intelligent Smart Home',
        'base_url'  => '',
        'parent_url'=> '../',
        'email'     => 'automations@jollofliving.com',
        'phone'     => '+234 1 888 5655',
        'currency'  => 'NGN',
        'currency_symbol' => '₦',
    ],

    'mail' => [
        'enabled'    => true,
        'from_email' => $parentConfig['mail']['from_email'] ?? 'automations@jollofliving.com',
        'from_name'  => 'Jollof Automations',
        'admin_to'   => 'concierge@jollofliving.com',
    ],

    'security' => [
        'admin_key' => 'ja_admin_secret_2026_smart_homes',
    ],

    'debug' => false,
];
