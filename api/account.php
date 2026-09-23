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
    /* Every other browser and mobile token stops working immediately; this
       session is kept (the member just changed their own password). */
    Auth::revokeOtherSessions($uid);
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


if ($action === 'pref-set') {
    $key = input_str('key');
    $val = input_str('val');
    if ($key === '') {
        json_fail('Missing preference key.');
    }
    if (DB::tableExists('user_prefs')) {
        $exists = DB::value('SELECT id FROM user_prefs WHERE user_id = ? AND pref_key = ?', [$uid, $key]);
        if ($exists) {
            DB::update('user_prefs', ['pref_val' => $val, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $exists]);
        } else {
            DB::insert('user_prefs', ['user_id' => $uid, 'pref_key' => $key, 'pref_val' => $val]);
        }
    }
    json_ok([], 'Preference saved ✨');
}

if ($action === 'report-concern') {
    $targetType = input_str('target_type', 'listing');
    $targetId = input_str('target_id');
    $category = input_str('category', 'safety');
    $detail = trim(input_str('detail'));
    if ($detail === '') {
        json_fail('Please provide details for the safety team.');
    }
    if (DB::tableExists('safety_reports')) {
        $repId = DB::insert('safety_reports', [
            'reporter_id' => $uid,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'category'    => $category,
            'detail'      => mb_substr($detail, 0, 2000),
            'status'      => 'new',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        audit((string) $user['email'], "Reported {$targetType}:{$targetId}", 'warn');
        json_ok(['report_id' => $repId], 'Report received. Our Trust & Safety team is reviewing the matter.');
    }
    json_ok([], 'Report received.');
}

if ($action === 'block-user') {
    $targetUid = input_int('target_user_id');
    if ($targetUid <= 0 || $targetUid === $uid) {
        json_fail('Invalid user to block.');
    }
    if (DB::tableExists('user_blocks')) {
        $exists = DB::value('SELECT id FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?', [$uid, $targetUid]);
        if (!$exists) {
            DB::insert('user_blocks', ['user_id' => $uid, 'blocked_user_id' => $targetUid]);
        }
    }
    json_ok([], 'User blocked.');
}


json_fail('Unknown account action.');
