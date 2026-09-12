<?php
/** Admin console sign-in / sign-out. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$action = input_str('action', 'login');

if ($action === 'logout') {
    audit((string) (Auth::user()['email'] ?? 'admin'), 'Admin signed out', 'info');
    Auth::logout();
    json_ok(['redirect' => url('')], 'Signed out of the admin console.');
}

api_throttle('adminlogin', 6, 900);

$email = strtolower(input_str('email'));
$pass  = (string) input('password', '');

[$ok, $message] = Auth::attempt($email, $pass);
if (!$ok) {
    audit($email !== '' ? $email : 'unknown', 'Failed admin sign-in', 'bad');
    json_fail($message, 401);
}

if (!Auth::isAdmin()) {
    Auth::logout();
    audit($email, 'Non-admin attempted admin sign-in', 'bad');
    json_fail('That account does not have administrator access.', 403);
}

if (config('security.admin_2fa_required')) {
    $otp = preg_replace('~\D~', '', input_str('otp'));
    $expected = (string) config('security.admin_otp', '');
    if ($expected !== '' && !hash_equals($expected, (string) $otp)) {
        Auth::logout();
        audit($email, 'Admin 2FA failed', 'bad');
        json_fail('That authentication code is not valid.', 401);
    }
}

audit($email, 'Admin signed in', 'ok');
json_ok(['redirect' => url('admin.php')], 'Welcome back to the console.');
