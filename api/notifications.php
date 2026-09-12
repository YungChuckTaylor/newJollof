<?php
/** Mark notifications read and save delivery preferences. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];
$action = input_str('action', 'read');

if ($action === 'read') {
    $id = input_int('id');
    DB::run('UPDATE notifications SET read_flag = 1 WHERE id = ? AND user_id = ?', [$id, $uid]);
    json_ok_state([], '');
}

if ($action === 'read-all') {
    DB::run('UPDATE notifications SET read_flag = 1 WHERE user_id = ? AND read_flag = 0', [$uid]);
    json_ok_state([], 'All caught up ✨');
}

if ($action === 'pref') {
    $key = preg_replace('~[^a-z0-9_.-]~i', '', input_str('key'));
    if ($key === '') json_fail('Unknown preference.');
    $on = input_bool('value');

    $existing = DB::row('SELECT id FROM user_prefs WHERE user_id = ? AND pref_key = ?', [$uid, $key]);
    if ($existing) {
        DB::update('user_prefs', ['pref_value' => $on ? '1' : '0'], 'id = ?', [(int) $existing['id']]);
    } else {
        DB::insert('user_prefs', ['user_id' => $uid, 'pref_key' => $key, 'pref_value' => $on ? '1' : '0']);
    }
    json_ok([], 'Preference saved');
}

if ($action === 'clear') {
    DB::run('DELETE FROM notifications WHERE user_id = ?', [$uid]);
    json_ok_state([], 'Notifications cleared');
}

json_fail('Unknown notifications action.');
