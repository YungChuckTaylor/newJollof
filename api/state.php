<?php
/** GET the current client state slice (wishlists, bookings, badges…). */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

json_response([
    'ok'    => true,
    'state' => View::state(),
    'user'  => Auth::user() ? [
        'id'     => Auth::id(),
        'name'   => Auth::user()['name'],
        'email'  => Auth::user()['email'],
        'tier'   => Auth::user()['tier'],
        'points' => (int) Auth::user()['points'],
    ] : null,
]);
