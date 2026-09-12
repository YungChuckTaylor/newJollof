<?php
/** Create a reservation (or a booking request for non-instant homes). */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
api_throttle('booking', 12, 600);

$user = Auth::user();
if (!$user && !config('booking.allow_guest_checkout')) {
    json_response([
        'ok'           => false,
        'requiresAuth' => true,
        'message'      => 'Please sign in to complete your reservation.',
    ], 401);
}

[$ok, $message, $booking] = BookingService::create([
    'property' => input_str('property'),
    'checkin'  => input_str('checkin'),
    'checkout' => input_str('checkout'),
    'guests'   => input_int('guests', 1),
    'policy'   => input_str('policy', 'moderate'),
    'method'   => input_str('method', 'card'),
    'addons'   => (array) input('addons', []),
    'promo'    => input_str('promo'),
    'gift'     => input_int('gift', 0),
    'split'    => input_bool('split'),
    'request'  => input_bool('request'),
    'name'     => input_str('name'),
    'email'    => input_str('email'),
    'phone'    => input_str('phone'),
    'notes'    => input_str('notes'),
]);

if (!$ok) {
    json_fail($message);
}

json_ok_state(
    ['ref' => $booking['ref'], 'total' => (int) $booking['total'], 'status' => $booking['status'],
     'redirect' => url('confirm.php?ref=' . urlencode((string) $booking['ref']))],
    $message
);
