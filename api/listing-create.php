<?php
/** Host onboarding — submit a new listing for verification. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];

$title = input_str('title');
$area  = input_str('area', 'Ikoyi');
$city  = input_str('city', 'Lagos');
$price = input_int('price');

if (mb_strlen($title) < 4) json_fail('Give your listing a descriptive title.');
if ($price < 1000)         json_fail('Please set a nightly rate of at least ₦1,000.');

$slug = slugify($title);
$n = 1;
while (Repo::propertyIdBySlug($slug)) {
    $slug = slugify($title) . '-' . (++$n);
}

DB::begin();
try {
    $pid = DB::insert('properties', [
        'slug'        => $slug,
        'name'        => mb_substr($title, 0, 160),
        'host_id'     => $uid,
        'area'        => mb_substr($area, 0, 80),
        'city'        => mb_substr($city, 0, 80),
        'ptype'       => mb_substr(input_str('type', 'Apartment'), 0, 60),
        'price'       => $price,
        'guests'      => max(1, input_int('guests', 2)),
        'beds'        => max(1, input_int('beds', 1)),
        'baths'       => max(1, input_int('baths', 1)),
        'description' => mb_substr(input_str('description'), 0, 4000),
        'policy'      => input_str('policy', 'moderate'),
        'instant'     => 0,
        'status'      => 'pending',
        'rating'      => 0,
        'reviews_count' => 0,
        'img'         => 'p1',
    ]);

    foreach (array_slice((array) input('amenities', []), 0, 40) as $i => $a) {
        if (!is_string($a) || $a === '') continue;
        DB::insert('property_amenities', ['property_id' => $pid, 'amenity' => mb_substr($a, 0, 60), 'sort_order' => (int) $i]);
    }
    foreach (array_slice((array) input('photos', []), 0, 30) as $i => $ph) {
        if (!is_string($ph) || $ph === '') continue;
        DB::insert('property_images', ['property_id' => $pid, 'path' => mb_substr($ph, 0, 190), 'sort_order' => (int) $i]);
    }

    DB::commit();
} catch (Throwable $ex) {
    DB::rollback();
    json_fail('We could not save that listing. Please try again.');
}

// Listing a property makes you an owner: promote the account and give it the
// workspace rows the dashboard expects. Idempotent for existing owners.
Auth::provisionHost($uid);

Repo::flush();
Repo::notify($uid, 'Listing submitted', $title . ' is with our verification team — expect an update within 24 hours.', 'home');
Mailer::adminNotice('New listing awaiting verification', $title . ' (' . $area . ', ' . $city . ') submitted by ' . $user['email']);
audit((string) $user['email'], 'Listing submitted: ' . $title, 'info');

json_ok_state(['slug' => $slug], 'Listing submitted — our team will verify within 24h ✨');
