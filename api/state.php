<?php
/** GET the current client state slice (wishlists, bookings, badges…). */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

/* One envelope for every API response (F83): the state lives in `data`,
   exactly as the client's syncState already reads it. `state` stays aliased
   for anything that has not moved over yet. */
$state = View::state();
$user  = Auth::user();

json_response([
    'ok'    => true,
    'data'  => [
        'user'  => $user ? [
            'id'     => (int) $user['id'],
            'name'   => (string) $user['name'],
            'email'  => (string) $user['email'],
            'tier'   => (string) ($user['tier'] ?? ''),
            'points' => (int) ($user['points'] ?? 0),
        ] : null,
    ] + $state,
    'state' => $state,
    'user'  => $user ? [
        'id'     => (int) $user['id'],
        'name'   => (string) $user['name'],
        'email'  => (string) $user['email'],
        'tier'   => (string) ($user['tier'] ?? ''),
        'points' => (int) ($user['points'] ?? 0),
    ] : null,
]);
