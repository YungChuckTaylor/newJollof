<?php
/** Wishlist: toggle a stay, create a list, switch the active list. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];
$action = input_str('action', 'toggle');

if ($action === 'toggle') {
    $slug = input_str('property');
    $pid = Repo::propertyIdBySlug($slug);
    if (!$pid) json_fail('We could not find that residence.', 404);

    $list = input_str('list', (string) ($_SESSION['activeWishlist'] ?? 'default'));
    $saved = Repo::toggleWishlist($uid, $pid, $list);

    json_ok_state(['saved' => $saved], $saved ? 'Saved to your wishlist ♥' : 'Removed from your wishlist');
}

if ($action === 'create') {
    $name = input_str('name');
    if (mb_strlen($name) < 2) json_fail('Give your list a name.');

    $slug = slugify($name);
    $exists = DB::row('SELECT id FROM wishlists WHERE user_id = ? AND slug = ?', [$uid, $slug]);
    if ($exists) json_fail('You already have a list with that name.');

    DB::insert('wishlists', ['user_id' => $uid, 'slug' => $slug, 'name' => mb_substr($name, 0, 80)]);
    $_SESSION['activeWishlist'] = $slug;

    json_ok_state(['slug' => $slug], 'List created ✨');
}

if ($action === 'active') {
    $slug = slugify(input_str('list', 'default'));
    $own = DB::row('SELECT id FROM wishlists WHERE user_id = ? AND slug = ?', [$uid, $slug]);
    if (!$own && $slug !== 'default') json_fail('That list does not exist.', 404);
    $_SESSION['activeWishlist'] = $slug;
    json_ok_state(['slug' => $slug]);
}

if ($action === 'delete') {
    $slug = slugify(input_str('list'));
    if ($slug === 'default') json_fail('Your default list cannot be removed.');
    $row = DB::row('SELECT id FROM wishlists WHERE user_id = ? AND slug = ?', [$uid, $slug]);
    if (!$row) json_fail('That list does not exist.', 404);
    DB::run('DELETE FROM wishlist_items WHERE wishlist_id = ?', [(int) $row['id']]);
    DB::run('DELETE FROM wishlists WHERE id = ?', [(int) $row['id']]);
    if (($_SESSION['activeWishlist'] ?? '') === $slug) $_SESSION['activeWishlist'] = 'default';
    json_ok_state([], 'List deleted');
}

json_fail('Unknown wishlist action.');
