<?php
/** Profile updates: password, details, preferences. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];
$action = input_str('action', 'profile');

if ($action === 'password') {
    $current = (string) input('current', '');
    $next    = (string) input('password', '');
    $confirm = (string) input('confirm', '');

    if (!password_verify($current, (string) $user['password_hash'])) {
        audit((string) $user['email'], 'Failed password change', 'warn');
        json_fail('Your current password is not correct.');
    }
    if (mb_strlen($next) < 8)  json_fail('Your new password needs at least 8 characters.');
    if ($next !== $confirm)    json_fail('The two new passwords do not match.');

    DB::update('users', ['password_hash' => password_hash($next, PASSWORD_DEFAULT)], 'id = ?', [$uid]);
    Repo::notify($uid, 'Password changed', 'Your password was updated. If this was not you, contact us immediately.', 'lock');
    audit((string) $user['email'], 'Password changed', 'ok');

    json_ok([], 'Password updated ✨');
}

if ($action === 'profile') {
    $fields = [];
    $name  = input_str('name');
    $phone = input_str('phone');
    if ($name !== '')  $fields['name'] = mb_substr($name, 0, 120);
    if ($phone !== '') $fields['phone'] = mb_substr($phone, 0, 40);
    if (!$fields) {
        json_fail('Nothing to update.');
    }
    DB::update('users', $fields, 'id = ?', [$uid]);
    audit((string) $user['email'], 'Profile updated', 'info');
    json_ok_state([], 'Profile saved ✨');
}

json_fail('Unknown account action.');
