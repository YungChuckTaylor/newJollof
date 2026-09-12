<?php
/**
 * Jollof Living — configuration
 * ---------------------------------------------------------------
 * HostGator / cPanel setup:
 *   1. cPanel → MySQL® Databases → create a database  (e.g. cpuser_jollof)
 *   2. Create a MySQL user and ADD IT TO the database with ALL PRIVILEGES
 *   3. Copy this file to  includes/config.php  and fill in the values below
 *   4. Visit  https://yourdomain.com/install/  once to create the tables
 *
 * Never commit the real config.php — it holds your database password.
 */

// Defence in depth: this file holds the database password and must never be
// served over HTTP. .htaccess denies the folder; this is the second lock.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}

return [
    // ---------------------------------------------------------- database
    'db' => [
        'driver'   => 'mysql',          // 'mysql' on HostGator ('sqlite' only for local dev)
        'host'     => 'localhost',      // HostGator shared hosting always uses localhost
        'port'     => 3306,
        'name'     => 'cpuser_jollof',  // cPanel prefixes DB names with your cPanel user
        'user'     => 'cpuser_jolluser',
        'pass'     => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
        // used only when driver = sqlite (local development)
        'sqlite_path' => __DIR__ . '/../../storage/jollof.sqlite',
    ],

    // ------------------------------------------------------------- site
    'site' => [
        'name'      => 'Jollof Living',
        'tagline'   => 'Luxury Living, African Soul',
        // Leave base_url empty to auto-detect. Set it if the site lives in a
        // sub-folder, e.g. '/jollof'
        'base_url'  => '',
        'url'       => 'https://www.jollofliving.com',
        'timezone'  => 'Africa/Lagos',
        'currency'  => 'NGN',
    ],

    // ------------------------------------------------------------ email
    // HostGator: use the mail() function or an SMTP account created in cPanel
    'mail' => [
        'enabled'    => true,
        'method'     => 'mail',                       // 'mail' | 'smtp'
        'from_email' => 'no-reply@jollofliving.com',
        'from_name'  => 'Jollof Living',
        'admin_to'   => 'reservations@jollofliving.com',
        'smtp' => [
            'host' => 'mail.jollofliving.com',
            'port' => 465,
            'user' => '',
            'pass' => '',
            'secure' => 'ssl',
        ],
    ],

    // --------------------------------------------------------- payments
    // Bookings are recorded in the database; no charge is attempted unless
    // a gateway is enabled and keys are supplied.
    'payments' => [
        'mode'    => 'record_only',   // 'record_only' | 'paystack' | 'flutterwave'
        'paystack' => [
            'public_key' => '',
            'secret_key' => '',
        ],
        'flutterwave' => [
            'public_key' => '',
            'secret_key' => '',
            'encryption_key' => '',
        ],
    ],

    // -------------------------------------------------------- bookings
    'booking' => [
        // Allow checkout without an account (the reservation is still stored).
        'allow_guest_checkout' => false,
        // Hours a pending request stays open before it auto-expires.
        'request_expiry_hours' => 24,
    ],

    // --------------------------------------------------------- reviews
    'reviews' => [
        // false = new reviews wait in the moderation queue
        'auto_publish' => false,
    ],

    // -------------------------------------------------------- security
    'security' => [
        // Change this to any long random string. It signs sessions/tokens.
        'app_key'            => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
        'session_name'       => 'jollof_session',
        // Where PHP stores session files. Leave empty to auto-detect: the app
        // uses the host's own path when it works, otherwise it creates
        // storage/sessions itself. Set an absolute path only to override, e.g.
        //   '/home/USER/sessions'
        'session_path'       => '',
        'session_lifetime'   => 60 * 60 * 12,   // 12 hours
        'admin_2fa_required' => false,          // set true to require the code below
        'admin_otp'          => '',             // shared 6-digit code when 2FA is on
        'max_login_attempts' => 8,
        'lockout_minutes'    => 15,
    ],

    // ------------------------------------------------- Android app
    // Only used by public_html/api/mobile/. The defaults are correct for
    // a normal Capacitor build, so most people never touch this.
    'mobile' => [
        // How long a phone stays signed in before it must log in again.
        'token_days' => 90,
        // Extra origins allowed to call the mobile API, comma separated.
        // A packaged Android app sends "https://localhost", which is
        // already allowed. Add entries here only if you also serve the
        // app from a real domain, e.g. 'https://app.yourdomain.com'.
        'origins'    => '',
    ],

    // ------------------------------------------------------------ debug
    // Turn OFF on the live site.
    'debug' => false,
];
