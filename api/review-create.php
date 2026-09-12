<?php
/** Publish a review for a completed stay. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];

$ref  = input_str('ref');
$body = input_str('body');
$scores = (array) input('scores', []);

$booking = BookingService::find($ref);
if (!$booking) json_fail('Reservation not found.', 404);
if ((int) $booking['user_id'] !== $uid) json_fail('You can only review your own stays.', 403);
if (!in_array((string) $booking['status'], ['completed', 'active'], true)) {
    json_fail('You can leave a review once your stay is under way.');
}
if (mb_strlen($body) < 10) json_fail('Please write at least a sentence about your stay.');
if (DB::value('SELECT 1 FROM reviews WHERE booking_ref = ?', [$ref])) {
    json_fail('You have already reviewed this stay.');
}

$axes = ['cleanliness', 'accuracy', 'checkin', 'communication', 'location', 'value'];
$clean = [];
foreach ($axes as $a) {
    $clean[$a] = max(1, min(5, (int) ($scores[$a] ?? 5)));
}
$overall = round(array_sum($clean) / count($clean), 1);

$id = Repo::addReview((int) $booking['property_id'], [
    'booking_ref' => $ref,
    'user_id'     => $uid,
    'author'      => (string) $user['name'],
    'meta'        => $booking['nights'] . '-night stay · ' . date('M Y', strtotime((string) $booking['checkin'])),
    'rating'      => $overall,
    'body'        => mb_substr($body, 0, 2000),
    'scores'      => $clean,
    'status'      => config('reviews.auto_publish') ? 'published' : 'pending',
]);

Repo::recalcRating((int) $booking['property_id']);
Repo::notify($uid, 'Thank you for your review', 'Your review of ' . $booking['property_name'] . ' has been received.', 'star');
audit((string) $user['email'], 'Review submitted for ' . $ref, 'ok');

json_ok_state(['id' => $id, 'rating' => $overall],
    config('reviews.auto_publish') ? 'Review published — thank you ✨' : 'Review received — it will appear once moderated ✨');
