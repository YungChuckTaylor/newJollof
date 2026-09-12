<?php
/** Guest-side booking lifecycle: check-in, check-out, cancel, modify. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];

$ref = input_str('ref');
$action = input_str('action');

if ($ref === '') json_fail('Missing reservation reference.');

$booking = BookingService::find($ref);
if (!$booking) json_fail('Reservation not found.', 404);
if ((int) $booking['user_id'] !== $uid && !Auth::isAdmin()) {
    json_fail('You cannot modify that reservation.', 403);
}

if ($action === 'modify') {
    [$ok, $message] = BookingService::requestModification($ref, [
        'checkin'  => input_str('checkin'),
        'checkout' => input_str('checkout'),
        'guests'   => input_int('guests', (int) $booking['guests']),
        'note'     => input_str('note'),
    ]);
    if (!$ok) json_fail($message);
    Repo::notify($uid, 'Modification requested', 'We are checking availability for ' . $ref . ' and will confirm within a few hours.', 'calendar');
    json_ok_state([], $message);
}

if (!in_array($action, ['checkin', 'checkout', 'cancel'], true)) {
    json_fail('Unknown booking action.');
}

if ($action === 'cancel') {
    $reason = input_str('reason');
    if ($reason !== '') {
        DB::insert('booking_events', [
            'booking_id' => (int) $booking['id'],
            'event'      => 'cancel_reason',
            'detail'     => mb_substr($reason, 0, 500),
        ]);
    }
}

[$ok, $message] = BookingService::transition($ref, $action, $uid);
if (!$ok) json_fail($message);

$titles = [
    'checkin'  => 'Checked in ✨',
    'checkout' => 'Checked out',
    'cancel'   => 'Reservation cancelled',
];
Repo::notify($uid, $titles[$action], $message . ' · ' . $ref, $action === 'cancel' ? 'x' : 'check');

json_ok_state([], $message);
