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

/* One rulebook with the ordinary sign-in form (api/auth.php): when the
   requirement is on, no admin gets in through any path without the code. */
if (Auth::adminFactorRequired()) {
    $otp = input_str('otp');
    if ((string) config('security.admin_otp', '') === '') {
        Auth::logout();
        json_fail('Administrator two-factor is enabled but no code is configured — ask whoever manages this deployment to set security.admin_otp.', 503);
    }
    if ($otp === '') {
        Auth::logout();
        json_fail('Enter the administrator authentication code.', 401, ['needsOtp' => true]);
    }
    if (!Auth::checkAdminOtp($otp)) {
        Auth::logout();
        audit($email, 'Admin 2FA failed', 'bad');
        json_fail('That authentication code is not valid.', 401, ['needsOtp' => true]);
    }
}

audit($email, 'Admin signed in', 'ok');
json_ok(['redirect' => url('admin.php')], 'Welcome back to the console.');
