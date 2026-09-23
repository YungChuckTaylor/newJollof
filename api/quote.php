<?php
/**
 * Jollof Living — Server-Authoritative Quote Engine Endpoint (WP08)
 *
 * Computes exact canonical price breakdown with property calendar overrides,
 * seasonal rules, promo validation, and add-ons.
 *
 * POST { property, checkin, checkout, guests, addons, promo, giftCode }
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

$propSlug = input_str('property');
$p = Repo::propertyBySlug($propSlug) ?: Repo::propertyById((int) $propSlug);
if (!$p) {
    json_fail('Property not found.', 404);
}

$checkin = input_str('checkin');
$checkout = input_str('checkout');
$guests = input_int('guests', 1);
$addons = (array) input('addons', []);
$promo = input_str('promo');
$giftCode = strtoupper(trim(input_str('giftCode')));

if ($checkin === '' || $checkout === '') {
    json_fail('Please choose check-in and check-out dates.');
}

$nights = (int) round((strtotime($checkout) - strtotime($checkin)) / 86400);
if ($nights <= 0) {
    json_fail('Check-out must be after check-in.');
}

// WP09 capacity check
$maxGuests = (int) ($p['guests'] ?? 2);
if ($guests > $maxGuests) {
    json_response([
        'ok'      => false,
        'code'    => 'over_capacity',
        'message' => "This residence accommodates up to {$maxGuests} guests.",
    ], 422);
}

// Compute quote with real calendar and database rules
$quote = Pricing::quote($p, $nights, $addons, $promo, 0, $checkin, $checkout);

// If a gift card code is provided, resolve its balance
if ($giftCode !== '') {
    $gc = DB::tableExists('gift_cards')
        ? DB::row('SELECT balance, status FROM gift_cards WHERE code = ?', [$giftCode])
        : null;
    if ($gc && $gc['status'] === 'active' && (int) $gc['balance'] > 0) {
        $giftBal = (int) $gc['balance'];
        $applied = min($giftBal, (int) $quote['total']);
        $quote['gift'] = $applied;
        $quote['giftCode'] = $giftCode;
        $quote['total'] = max(0, (int) $quote['total'] - $applied);
        $quote['discount'] += $applied;
        $quote['halfNow'] = (int) round($quote['total'] / 2);
    }
}

json_ok($quote, 'Quote computed successfully.');
