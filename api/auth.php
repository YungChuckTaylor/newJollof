<?php
/** Register / sign in. */
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
    Repo::notify($uid, 'Welcome to Jollof Living ✨', 'Your account is ready. Explore the collections and save your first residence.', 'spark');
    Mailer::welcome($email, $name);
    audit($email, 'Account created', 'ok');

    // Owners land in their workspace; customers land in their account.
    $isOwner = $accountType === 'host';
    json_ok_state(
        ['redirect' => url($isOwner ? 'host-dashboard.php' : 'account.php')],
        $isOwner ? 'Welcome to Jollof Living — your owner workspace is ready ✨' : 'Welcome to Jollof Living ✨'
    );
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

Repo::ensureConversations((int) $u['id']);
audit($email, 'Signed in', 'ok');

json_ok_state(['redirect' => url(Auth::isHost() ? 'host-dashboard.php' : 'account.php')], 'Welcome back ✨');
