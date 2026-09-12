<?php
/** Join the waitlist for a fully-booked residence. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];

$slug = input_str('property');
$pid = Repo::propertyIdBySlug($slug);
if (!$pid) json_fail('We could not find that residence.', 404);

$from = input_str('from') ?: null;
$to   = input_str('to') ?: null;

$existing = DB::row('SELECT id FROM waitlist WHERE user_id = ? AND property_id = ?', [$uid, $pid]);
if ($existing) {
    json_ok_state([], "You're already on the waitlist — we'll alert you the moment dates open.");
}

DB::insert('waitlist', [
    'user_id'     => $uid,
    'property_id' => $pid,
    'email'       => (string) $user['email'],
    'date_from'   => $from,
    'date_to'     => $to,
]);

$p = Repo::propertyById($pid);
Repo::notify($uid, 'Waitlist confirmed', 'We will alert you the moment dates open at ' . ($p['name'] ?? 'this residence') . '.', 'clock');

json_ok_state([], "You're on the waitlist — we'll alert you the moment dates open.");
