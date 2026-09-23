<?php
/**
 * Jollof Automations — Configuration Sample
 * Copy to config.php and enter your MySQL credentials.
 */
declare(strict_types=1);

return [
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'wal7zkit_jollof',
        'user'     => 'wal7zkit_jollof',
        'pass'     => 'your_password_here',
        'charset'  => 'utf8mb4',
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
        'from_email' => 'automations@jollofliving.com',
        'from_name'  => 'Jollof Automations',
        'admin_to'   => 'concierge@jollofliving.com',
    ],

    'security' => [
        'admin_key' => 'ja_admin_secret_change_me',
    ],

    'debug' => false,
];
