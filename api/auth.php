<?php
/** Register / sign in / email verification. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$action = input_str('action', 'login');

if ($action === 'register') {
    api_throttle('register', 5, 900);

    $name  = input_str('name');
    $email = strtolower(input_str('email'));
    $pass  = (string) input('password', '');
    $phone = input_str('phone');

    if (mb_strlen($name) < 2)        json_fail('Please tell us your name.');
    if (!is_email($email))           json_fail('That email address does not look right.');
    if (mb_strlen($pass) < 8)        json_fail('Your password needs at least 8 characters.');
    if (!input_bool('terms'))        json_fail('Please accept the terms to continue.');

    // Only 'customer' and 'owner' can be chosen at signup. Anything else is
    // ignored and treated as a customer, so 'admin' can never be self-granted.
    $accountType = input_str('account_type') === 'owner' ? 'host' : 'guest';

    [$ok, $message, $uid] = Auth::register($name, $email, $pass, $phone, $accountType);
    if (!$ok) {
        json_fail($message);
    }

    $uid = (int) $uid;
    Auth::login($uid);   // sign the new member straight in
    Repo::ensureConversations($uid);
    Repo::notify($uid, 'Welcome to Jollof Living ✨', 'Please verify your email address to activate your full account privileges and escrow reservations.', 'mail');

    // Generate secure verification challenge and send verification email
    $verifSent = VerifyService::sendEmailVerification($uid);

    audit($email, 'Account created' . ($verifSent ? ' (verification email sent)' : ''), 'ok');

    // Owners land in their workspace; customers land in their account.
    $isOwner = $accountType === 'host';
    json_ok_state(
        ['redirect' => url($isOwner ? 'host-dashboard.php' : 'account.php')],
        $verifSent
            ? 'Account created! We’ve sent a verification link to your email.'
            : ($isOwner ? 'Welcome to Jollof Living — your owner workspace is ready ✨' : 'Welcome to Jollof Living ✨')
    );
}

/* ---- resend verification email ---- */
if ($action === 'resend_verification') {
    api_throttle('resend_verif', 5, 300);

    $uid = Auth::id();
    $email = strtolower(input_str('email'));

    if (!$uid && empty($email)) {
        json_fail('Please sign in or enter your email address to request a verification link.');
    }

    $res = VerifyService::resendVerification($uid, $email);
    if (!$res['ok']) {
        json_fail($res['message']);
    }

    json_ok(['already_verified' => !empty($res['already_verified'])], $res['message']);
}

/* ---- verify email with token or code ---- */
if ($action === 'verify_email') {
    api_throttle('verify_email', 10, 300);

    $token = input_str('token');
    $code  = input_str('code');

    if (!empty($token)) {
        $res = VerifyService::verifyEmailToken($token);
        if (!$res['ok']) {
            json_fail($res['message']);
        }
        json_ok(['user' => $res['user']], $res['message']);
    }

    if (!empty($code)) {
        $uid = Auth::id();
        if (!$uid) {
            $email = strtolower(input_str('email'));
            $user = DB::row('SELECT id FROM users WHERE email = ?', [$email]);
            $uid = $user ? (int) $user['id'] : 0;
        }
        if (!$uid) {
            json_fail('Please sign in or provide your email address along with the code.');
        }
        $res = VerifyService::verifyEmailCode($uid, $code);
        if (!$res['ok']) {
            json_fail($res['message']);
        }
        json_ok(['user' => $res['user']], $res['message']);
    }

    json_fail('Please provide a verification token or 6-digit code.');
}

/* ---- forgot password request (WP12 / F16) ---- */
if ($action === 'forgot_password') {
    api_throttle('forgot_pw', 5, 300);

    $email = strtolower(input_str('email'));
    if ($email === '') {
        json_fail('Please enter your account email address.');
    }

    $res = VerifyService::sendPasswordReset($email);
    if (!$res['ok']) {
        json_fail($res['message']);
    }

    json_ok([], $res['message']);
}

/* ---- reset password with token (WP12 / F16) ---- */
if ($action === 'reset_password') {
    api_throttle('reset_pw', 10, 300);

    $token = input_str('token');
    $pass  = (string) input('password', '');

    if ($token === '') {
        json_fail('Missing password recovery token.');
    }
    if (mb_strlen($pass) < 8) {
        json_fail('Your new password must be at least 8 characters.');
    }

    $res = VerifyService::resetPasswordWithToken($token, $pass);
    if (!$res['ok']) {
        json_fail($res['message']);
    }

    if (!empty($res['user'])) {
        Auth::login((int) $res['user']['id'], ($res['user']['role'] ?? '') === 'admin', $res['user']);
    }

    json_ok_state(['redirect' => url('account.php')], $res['message']);
}

/* ---- sign in ---- */
api_throttle('login', 10, 900);

$email = strtolower(input_str('email'));
$pass  = (string) input('password', '');

if ($email === '' || $pass === '') {
    json_fail('Please enter your email and password.');
}

[$ok, $message, $u] = Auth::attempt($email, $pass);
if (!$ok) {
    audit($email, 'Failed sign-in attempt', 'warn');
    json_fail($message, 401);
}

/* Administrator accounts clear the second factor here too — this login form
   and the admin console share the rule, so "require 2FA" cannot be sidestepped
   by choosing the other sign-in page (A13). */
if (($u['role'] ?? '') === 'admin' && Auth::adminFactorRequired()) {
    $otp = input_str('otp');
    if ($otp === '') {
        Auth::logout();
        json_fail('Administrator sign-in needs the authentication code. Enter it below, or use the admin console.', 401, ['needsOtp' => true]);
    }
    if (!Auth::checkAdminOtp($otp)) {
        Auth::logout();
        audit($email, 'Admin 2FA failed (site sign-in)', 'bad');
        json_fail('That authentication code is not valid.', 401, ['needsOtp' => true]);
    }
}

Repo::ensureConversations((int) $u['id']);
audit($email, 'Signed in', 'ok');

json_ok_state(['redirect' => url(Auth::isHost() ? 'host-dashboard.php' : 'account.php')], 'Welcome back ✨');
