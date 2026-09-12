<?php
/** Newsletter subscription (footer form). */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
api_throttle('newsletter', 8, 900);

$email = strtolower(input_str('email'));
if (!is_email($email)) {
    json_fail('Please enter a valid email address.');
}

$existing = DB::row('SELECT id, status FROM subscribers WHERE email = ?', [$email]);
if ($existing) {
    if (($existing['status'] ?? '') !== 'active') {
        DB::update('subscribers', ['status' => 'active'], 'id = ?', [(int) $existing['id']]);
    }
    json_ok([], "You're already on the list — welcome back ✨");
}

DB::insert('subscribers', [
    'email'  => $email,
    'source' => mb_substr(input_str('source', 'footer'), 0, 60),
    'status' => 'active',
    'ip'     => client_ip(),
]);

Mailer::send($email, 'Welcome to the Jollof Living inner circle',
    '<p>Thank you for subscribing.</p><p>You will be first to hear about private openings, new residences and member-only rates across Lagos and Abuja.</p>');

audit($email, 'Newsletter subscription', 'info');
json_ok([], 'Welcome to the inner circle — check your inbox ✨');
