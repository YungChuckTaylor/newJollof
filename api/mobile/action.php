<?php
/**
 * Mobile actions — every write the app can perform.
 *
 * One endpoint keyed by `do`, because the app's offline queue replays a
 * uniform envelope. Each action delegates to the same service the website
 * calls, so business rules (pricing, escrow, points, moderation) are
 * defined once and behave identically on both clients.
 *
 * Send X-Client-Key with anything queued offline: a replayed request
 * returns the original response instead of acting twice.
 */
declare(strict_types=1);

require __DIR__ . '/_mobile.php';
mobile_post();

$do = input_str('do');

/* ---------------------------------------------------------- wishlist */
if ($do === 'wishlist-toggle') {
    $u = mobile_user();
    $slug = input_str('property');
    $pid = Repo::propertyIdBySlug($slug);
    if (!$pid) {
        json_fail('That home is no longer available.');
    }
    $on = Repo::toggleWishlist((int) $u['id'], $pid, input_str('list', 'default'));
    mobile_ok(['saved' => $on, 'property' => $slug], $on ? 'Saved to your wishlist' : 'Removed from your wishlist');
}

/* ----------------------------------------------------------- compare */
if ($do === 'compare-toggle') {
    $u = mobile_user();
    $pid = Repo::propertyIdBySlug(input_str('property'));
    if (!$pid) {
        json_fail('That home is no longer available.');
    }
    $res = Repo::toggleCompare((int) $u['id'], $pid);
    mobile_ok(['compare' => Repo::compareSlugs((int) $u['id'])], is_array($res) ? ($res['message'] ?? '') : '');
}

/* ----------------------------------------------------------- booking */
if ($do === 'booking-create') {
    $u = mobile_user();
    mobile_replay((int) $u['id'], 'booking-create');

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
    $payload = [
        'ok' => true,
        'message' => $message,
        'data' => [
            'ref'    => $booking['ref'],
            'total'  => (int) $booking['total'],
            'status' => $booking['status'],
        ],
    ];
    mobile_remember((int) $u['id'], 'booking-create', $payload);
    json_response($payload);
}

if ($do === 'booking-action') {
    $u = mobile_user();
    mobile_replay((int) $u['id'], 'booking-action');
    [$ok, $message] = BookingService::transition(
        input_str('ref'),
        input_str('action'),
        (int) $u['id']
    );
    $payload = ['ok' => $ok, 'message' => $message, 'data' => []];
    if ($ok) {
        mobile_remember((int) $u['id'], 'booking-action', $payload);
    }
    json_response($payload, $ok ? 200 : 400);
}

/* ------------------------------------------------------------ quote */
if ($do === 'quote') {
    // Priced by the same Pricing::quote the website and the booking use,
    // so the figure in the app is never a near-enough estimate.
    $prop = Repo::property(input_str('property'));
    if (!$prop) {
        json_fail('That home is no longer available.');
    }
    $in  = input_str('checkin');
    $out = input_str('checkout');
    $nights = 1;
    if ($in !== '' && $out !== '') {
        $nights = (int) floor((strtotime($out) - strtotime($in)) / 86400);
    }
    if ($nights < 1) {
        json_fail('Please choose a check-out date after your check-in.');
    }
    mobile_ok(Pricing::quote(
        $prop,
        $nights,
        (array) input('addons', []),
        input_str('promo'),
        input_int('gift', 0)
    ));
}

/* ----------------------------------------------------------- reviews */
if ($do === 'review-create') {
    $u = mobile_user();
    mobile_replay((int) $u['id'], 'review-create');
    $pid = Repo::propertyIdBySlug(input_str('property'));
    if (!$pid) {
        json_fail('That home is no longer available.');
    }
    $body = input_str('body');
    $rating = input_int('rating', 5);
    if (mb_strlen($body) < 10) {
        json_fail('Please write a little more about your stay.');
    }
    Repo::addReview($pid, [
        'user_id'     => (int) $u['id'],
        'author'      => (string) $u['name'],
        'body'        => $body,
        'rating'      => max(1, min(5, $rating)),
        'booking_ref' => input_str('ref'),
        'status'      => 'pending',
    ]);
    $payload = ['ok' => true, 'message' => 'Thank you — your review is with our team for checking.', 'data' => []];
    mobile_remember((int) $u['id'], 'review-create', $payload);
    json_response($payload);
}

/* ---------------------------------------------------------- messages */
if ($do === 'message-send') {
    $u = mobile_user();
    mobile_replay((int) $u['id'], 'message-send');
    $conv = input_int('conversation');
    $text = input_str('body');
    if ($text === '') {
        json_fail('Please write a message first.');
    }
    $owns = DB::value('SELECT COUNT(*) FROM conversations WHERE id = ? AND user_id = ?', [$conv, (int) $u['id']], 0);
    if (!$owns) {
        json_fail('That conversation is not on your account.');
    }
    DB::insert('messages', [
        'conversation_id' => $conv,
        'sender'          => 'guest',
        'body'            => $text,
        'read_at'         => null,
    ]);
    DB::run('UPDATE conversations SET last_message_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $conv]);
    $payload = ['ok' => true, 'message' => 'Sent', 'data' => ['conversation' => $conv]];
    mobile_remember((int) $u['id'], 'message-send', $payload);
    json_response($payload);
}

/* ----------------------------------------------------- notifications */
if ($do === 'notifications-read') {
    $u = mobile_user();
    DB::run('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL',
        [date('Y-m-d H:i:s'), (int) $u['id']]);
    mobile_ok([], 'All caught up ✨');
}

/* --------------------------------------------------------- account */
if ($do === 'account-update') {
    $u = mobile_user();
    $name = input_str('name');
    $phone = input_str('phone');
    $city = input_str('city');
    DB::run('UPDATE users SET name = ?, phone = ?, city = ? WHERE id = ?', [
        $name === '' ? (string) $u['name'] : $name,
        $phone === '' ? (string) ($u['phone'] ?? '') : $phone,
        $city === '' ? (string) ($u['city'] ?? '') : $city,
        (int) $u['id'],
    ]);
    $fresh = DB::row('SELECT * FROM users WHERE id = ?', [(int) $u['id']]);
    mobile_ok(['user' => mobile_user_payload($fresh ?: $u)], 'Profile updated ✨');
}

/* ------------------------------------------------------------- owner */
if ($do === 'listing-create') {
    // Goes into the same moderation queue the admin approves from, so a
    // listing submitted on the phone appears in the back office at once.
    $u = mobile_host();
    mobile_replay((int) $u['id'], 'listing-create');

    $title = input_str('title');
    $price = input_int('price');
    if (mb_strlen($title) < 4) {
        json_fail('Please give your home a longer title.');
    }
    if ($price < 1000) {
        json_fail('Please set a nightly rate of at least ₦1,000.');
    }
    $slug = slugify($title) . '-' . substr((string) time(), -5);
    $pid = DB::insert('properties', [
        'slug'        => $slug,
        'name'        => $title,
        'area'        => input_str('area', 'Lagos'),
        'city'        => input_str('city', 'Lagos'),
        'img'         => 'p1',
        'price'       => $price,
        'beds'        => input_int('beds', 1),
        'baths'       => input_int('baths', 1),
        'guests'      => input_int('guests', 2),
        'ptype'       => input_str('ptype', 'Apartment'),
        'description' => input_str('description'),
        'host_id'     => (int) $u['id'],
        'status'      => 'pending',
    ]);
    Repo::flush();
    $payload = ['ok' => true, 'message' => 'Listing submitted — our team will verify within 24h ✨',
                'data' => ['slug' => $slug, 'id' => $pid]];
    mobile_remember((int) $u['id'], 'listing-create', $payload);
    json_response($payload);
}

if ($do === 'listing-status' || $do === 'listing-price') {
    $u = mobile_host();
    $slug = input_str('property');
    $prop = DB::row('SELECT * FROM properties WHERE slug = ?', [$slug]);
    if (!$prop || (int) $prop['host_id'] !== (int) $u['id']) {
        json_fail('That listing is not on your account.');
    }
    if ($do === 'listing-price') {
        $price = input_int('price');
        if ($price < 1000) {
            json_fail('Please set a nightly rate of at least ₦1,000.');
        }
        DB::run('UPDATE properties SET price = ? WHERE id = ?', [$price, (int) $prop['id']]);
        Repo::flush();
        mobile_ok([], 'Nightly rate updated.');
    }
    $status = input_str('status') === 'paused' ? 'paused' : 'live';
    DB::run('UPDATE properties SET status = ? WHERE id = ?', [$status, (int) $prop['id']]);
    Repo::flush();
    mobile_ok([], $status === 'live' ? 'Listing is live again.' : 'Listing paused.');
}

/* ------------------------------------------------------- newsletter */
if ($do === 'subscribe') {
    $email = strtolower(input_str('email'));
    if (!is_email($email)) {
        json_fail('That email address does not look right.');
    }
    $exists = DB::value('SELECT COUNT(*) FROM subscribers WHERE email = ?', [$email], 0);
    if (!$exists) {
        DB::insert('subscribers', ['email' => $email, 'source' => 'android-app']);
    }
    mobile_ok([], 'You are on the list ✨');
}

json_fail('Unknown action.');
