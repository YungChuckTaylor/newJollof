<?php
/** Request an experience — logged as an enquiry for the concierge desk. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
api_throttle('experience', 10, 600);

$slug = input_str('experience');
$exp = Repo::experience($slug);
if (!$exp) json_fail('We could not find that experience.', 404);

$name  = input_str('name');
$email = strtolower(input_str('email'));
// input_str() yields '' for absent fields, which ?? would happily accept, so
// test for empty explicitly before falling back to the member's own details.
if ($u = Auth::user()) {
    if ($name === '')  $name  = (string) ($u['name'] ?? '');
    if ($email === '') $email = strtolower((string) ($u['email'] ?? ''));
}
if ($name === '')      json_fail('Please tell us your name.');
if (!is_email($email)) json_fail('Please enter a valid email address.');

$date = input_str('date');
$guests = max(1, input_int('guests', 2));

DB::insert('enquiries', [
    'kind'    => 'experience',
    'user_id' => Auth::id(),
    'name'    => $name,
    'email'   => $email,
    'subject' => 'Experience request · ' . $exp['name'],
    'message' => trim("Date: {$date}\nGuests: {$guests}\n\n" . input_str('notes')),
    'meta'    => $slug,
]);

Mailer::enquiry([
    'kind'       => 'experience',
    'experience' => $exp['name'],
    'name'       => $name,
    'email'      => $email,
    'date'       => $date,
    'guests'     => $guests,
    'notes'      => input_str('notes'),
]);

if ($uid = Auth::id()) {
    Repo::notify($uid, 'Experience requested', $exp['name'] . ' — the concierge will confirm availability shortly.', 'spark');
}

json_ok([], 'Requested — the concierge will confirm shortly ✨');
