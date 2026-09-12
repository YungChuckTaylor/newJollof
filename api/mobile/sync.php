<?php
/**
 * Mobile sync — the single call the app makes to refresh itself.
 *
 * Returns the public catalogue plus, when a token is supplied, everything
 * scoped to that member: trips, wishlist, points, notifications and the
 * owner workspace. It reads through the same Repo the website renders from,
 * so the two clients cannot show different numbers.
 *
 * GET  ?since=ISO8601   — omit the catalogue when nothing has changed
 *      &scope=all|catalogue|me|owner
 */
declare(strict_types=1);

require __DIR__ . '/_mobile.php';

$scope = (string) ($_GET['scope'] ?? 'all');
$since = trim((string) ($_GET['since'] ?? ''));
$user  = MobileAuth::user();          // optional: browsing works signed out

/**
 * A cheap fingerprint of the catalogue. The app sends back the version it
 * holds and we skip resending twelve properties when nothing has moved.
 */
function catalogue_version(): string
{
    $parts = [
        (string) DB::value('SELECT COUNT(*) FROM properties', [], 0),
        (string) DB::value('SELECT COALESCE(MAX(updated_at), "") FROM properties', [], ''),
        (string) DB::value('SELECT COALESCE(MAX(created_at), "") FROM properties', [], ''),
        (string) DB::value('SELECT COUNT(*) FROM experiences', [], 0),
        (string) DB::value('SELECT COALESCE(MAX(created_at), "") FROM reviews', [], ''),
    ];
    return substr(hash('sha256', implode('|', $parts)), 0, 16);
}

$version = catalogue_version();
$out = [
    'version'  => $version,
    'serverTime' => date('c'),
];

/* ------------------------------------------------------- the catalogue */
$clientVersion = trim((string) ($_GET['version'] ?? ''));
$wantCatalogue = in_array($scope, ['all', 'catalogue'], true);

if ($wantCatalogue && $clientVersion !== '' && $clientVersion === $version) {
    $out['catalogueUnchanged'] = true;
} elseif ($wantCatalogue) {
    $out['catalogue'] = [
        'properties'    => Repo::properties(true),
        'collections'   => Repo::collections(),
        'collectionMap' => Repo::collectionMap(),
        'neighborhoods' => Repo::neighborhoods(true),
        'experiences'   => Repo::experiences(),
        'blog'          => Repo::blogPosts(),
        'testimonials'  => Repo::testimonials(),
        'faqs'          => Repo::faqs(),
        'tiers'         => Repo::tiers(),
        'areas'         => Repo::areas(),
        'propertyTypes' => Repo::propertyTypes(),
        'rates'         => Repo::rates(),
        'fx'            => Repo::fxRates(),
        'addons'        => Repo::addons(),
        'promos'        => Repo::promos(),
        'payMethods'    => Repo::payMethods(),
        'reviews'       => Repo::latestReviews(24),
    ];
}

/* ------------------------------------------------------ the member's own */
if ($user && in_array($scope, ['all', 'me', 'owner'], true)) {
    $uid = (int) $user['id'];

    // View::state() is what the website renders from. Reusing it means the
    // app's trips, wishlist and points are literally the same computation,
    // so a change to one client can never leave the other behind.
    $out['me'] = View::state() + [
        'user'          => mobile_user_payload($user),
        'notifications' => Repo::notifications($uid),
        'conversations' => Repo::conversations($uid),
        'pointsLedger'  => Repo::pointsLedger($uid),
    ];

    // The owner workspace, only for owners and only when asked for.
    $isHost = (int) ($user['is_host'] ?? 0) === 1
        || in_array($user['role'] ?? '', ['host', 'admin'], true);
    if ($isHost && in_array($scope, ['all', 'owner'], true)) {
        $out['owner'] = Repo::hostState($uid);
    }
}

mobile_ok($out);
