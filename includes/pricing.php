<?php
/**
 * Jollof Living — booking maths & booking service.
 * The price breakdown is calculated on the SERVER so a guest can never
 * post a tampered total.
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


final class Pricing
{
    /**
     * Full price breakdown for a stay.
     *
     * @param array  $p       property row (from Repo)
     * @param int    $nights
     * @param array  $addons  addon keys
     * @param string $promo   promo code
     * @param int    $gift    gift-card credit in NGN
     */
    public static function quote(array $p, int $nights, array $addons = [], string $promo = '', int $gift = 0): array
    {
        $R = Repo::rates();
        $nights = max(1, $nights);
        $nightly = (int) $p['price'];
        $base = $nightly * $nights;

        $discRate = $nights >= 30 ? (float) $R['monthlyDisc'] : ($nights >= 7 ? (float) $R['weeklyDisc'] : 0.0);
        $lengthDiscount = (int) round($base * $discRate);
        $afterLength = $base - $lengthDiscount;

        $addonTotal = 0;
        $addonLines = [];
        $catalog = Repo::addons();
        foreach ($addons as $key) {
            if (!isset($catalog[$key])) {
                continue;
            }
            $a = $catalog[$key];
            $amount = $a['percent'] ? (int) round($afterLength * $a['price']) : (int) $a['price'];
            $addonTotal += $amount;
            $addonLines[] = ['key' => $key, 'name' => $a['name'], 'amount' => $amount];
        }

        $cleaning = (int) $R['cleaning'];
        $service  = (int) round(($afterLength + $addonTotal) * (float) $R['service']);
        $taxable  = $afterLength + $addonTotal + $cleaning + $service;
        $vat      = (int) round($taxable * (float) $R['vat']);

        $promoDiscount = 0;
        $promoLabel = '';
        if ($promo !== '') {
            $pr = Repo::promo($promo);
            if ($pr) {
                $promoLabel = (string) $pr['label'];
                $promoDiscount = (int) $pr['flat'] > 0
                    ? (int) $pr['flat']
                    : (int) round($afterLength * (float) $pr['off']);
            }
        }

        $gift = max(0, $gift);
        $total = $taxable + $vat - $promoDiscount - $gift;
        $total = max(0, (int) $total);

        return [
            'nights'          => $nights,
            'nightly'         => $nightly,
            'base'            => $base,
            'lengthDiscount'  => $lengthDiscount,
            'lengthDiscRate'  => $discRate,
            'addons'          => $addonLines,
            'addonTotal'      => $addonTotal,
            'cleaning'        => $cleaning,
            'service'         => $service,
            'vat'             => $vat,
            'promoCode'       => $promo !== '' && $promoDiscount > 0 ? strtoupper($promo) : '',
            'promoLabel'      => $promoLabel,
            'promoDiscount'   => $promoDiscount,
            'gift'            => $gift,
            'subtotal'        => $afterLength + $addonTotal,
            'fees'            => $cleaning + $service,
            'taxes'           => $vat,
            'discount'        => $lengthDiscount + $promoDiscount + $gift,
            'total'           => $total,
            'deposit'         => (int) round($total * (float) $R['deposit']),
            'halfNow'         => (int) round($total / 2),
        ];
    }

    /** Points earned for a booking at the guest's tier multiplier. */
    public static function points(int $total, string $tierKey): int
    {
        $t = Repo::tier($tierKey);
        return (int) floor($total / 1000) * (int) ($t['multi'] ?? 5);
    }
}

final class BookingService
{
    /**
     * Create a booking. Returns [ok, message, bookingRow|null].
     *
     * $in keys: property (slug), checkin, checkout, guests, addons[],
     *           promo, gift, method, split, request, name, email, phone, notes
     */
    public static function create(array $in): array
    {
        $slug = (string) ($in['property'] ?? '');
        $p = Repo::property($slug);
        if (!$p) {
            return [false, 'That residence is no longer available.', null];
        }

        $checkin = self::date($in['checkin'] ?? '');
        $checkout = self::date($in['checkout'] ?? '');
        if (!$checkin || !$checkout) {
            return [false, 'Please choose your check-in and check-out dates.', null];
        }
        if ($checkout <= $checkin) {
            return [false, 'Check-out must be after check-in.', null];
        }
        if ($checkin < date('Y-m-d')) {
            return [false, 'Check-in cannot be in the past.', null];
        }

        $nights = nights_between($checkin, $checkout);
        $guests = max(1, min((int) ($in['guests'] ?? 1), (int) $p['guests']));

        if (!self::isAvailable((int) $p['pid'], $checkin, $checkout)) {
            return [false, 'Those dates have just been taken. Please choose another window.', null];
        }

        $addons = array_values(array_filter((array) ($in['addons'] ?? []), 'is_string'));
        $promo  = strtoupper(trim((string) ($in['promo'] ?? '')));
        $gift   = (int) ($in['gift'] ?? 0);
        $q = Pricing::quote($p, $nights, $addons, $promo, $gift);

        // Fall back to the signed-in member's own details. Note the checks are
        // for an EMPTY value, not just a missing key: the booking form omits
        // these fields for a signed-in guest and posts empty strings, which
        // ?? would happily accept.
        $user = Auth::user();
        $name  = trim((string) ($in['name'] ?? ''));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $phone = trim((string) ($in['phone'] ?? ''));
        if ($name === '')  { $name  = trim((string) ($user['name'] ?? '')); }
        if ($email === '') { $email = strtolower(trim((string) ($user['email'] ?? ''))); }
        if ($phone === '') { $phone = trim((string) ($user['phone'] ?? '')); }
        if ($name === '' || !is_email($email)) {
            return [false, 'Please provide your name and a valid email address.', null];
        }

        $isRequest = !empty($in['request']) || !$p['instant'];
        $tier = $user['tier'] ?? 'bronze';
        $points = Pricing::points($q['total'], (string) $tier);

        $method = (string) ($in['method'] ?? 'card');
        $valid = array_column(Repo::payMethods(), 'id');
        if (!in_array($method, $valid, true)) {
            $method = $valid[0] ?? 'card';
        }

        $ref = booking_ref();
        $tries = 0;
        while (DB::value('SELECT 1 FROM bookings WHERE ref = ?', [$ref]) && $tries++ < 8) {
            $ref = booking_ref();
        }

        DB::begin();
        try {
            $id = DB::insert('bookings', [
                'ref'           => $ref,
                'user_id'       => $user['id'] ?? null,
                'property_id'   => (int) $p['pid'],
                'guest_name'    => $name,
                'guest_email'   => $email,
                'guest_phone'   => $phone ?: null,
                'checkin'       => $checkin,
                'checkout'      => $checkout,
                'nights'        => $nights,
                'guests'        => $guests,
                'policy'        => (string) ($in['policy'] ?? $p['policy']),
                'pay_method'    => $method,
                'addons'        => json_encode($addons),
                'promo_code'    => $q['promoCode'] ?: null,
                'split_payment' => !empty($in['split']) ? 1 : 0,
                'is_request'    => $isRequest ? 1 : 0,
                'subtotal'      => $q['subtotal'],
                'fees'          => $q['fees'],
                'taxes'         => $q['taxes'],
                'discount'      => $q['discount'],
                'total'         => $q['total'],
                'currency'      => 'NGN',
                'points_earned' => $points,
                'status'        => $isRequest ? 'pending' : 'confirmed',
                'escrow_status' => 'held',
                'checkin_code'  => str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT) . '#',
                'notes'         => (string) ($in['notes'] ?? '') ?: null,
                // a frozen copy of the quote, so an invoice always shows what was charged
                'breakdown'     => json_encode($q),
            ]);

            DB::insert('booking_events', [
                'booking_id' => $id,
                'event'      => $isRequest ? 'requested' : 'confirmed',
                'detail'     => 'Payment captured into escrow · ' . $method,
            ]);

            if ($q['promoCode']) {
                DB::run('UPDATE promos SET uses = uses + 1 WHERE code = ?', [$q['promoCode']]);
            }

            DB::insert('payments', [
                'booking_id' => $id,
                'user_id'    => $user['id'] ?? null,
                'reference'  => $ref . '-P1',
                'gateway'    => (string) config('payments.mode', 'record_only'),
                'amount'     => !empty($in['split']) ? $q['halfNow'] : $q['total'],
                'currency'   => 'NGN',
                'status'     => 'initiated',
                'payload'    => json_encode(['method' => $method, 'split' => !empty($in['split'])]),
            ]);

            if ($user) {
                DB::run('UPDATE users SET points = points + ? WHERE id = ?', [$points, (int) $user['id']]);
                DB::insert('points_ledger', [
                    'user_id'     => (int) $user['id'],
                    'date_label'  => date('M j, Y'),
                    'description' => 'Stay · ' . $p['name'] . ' (' . $nights . 'n)',
                    'amount'      => $points,
                    'kind'        => 'earn',
                ]);
                Repo::notify(
                    (int) $user['id'],
                    $isRequest ? 'Booking request sent' : 'Booking confirmed',
                    'Your stay at ' . $p['name'] . ' (' . $checkin . ' → ' . $checkout . ') · invoice ' . $ref . '.',
                    $isRequest ? 'clock' : 'check'
                );
            }

            DB::commit();
        } catch (Throwable $ex) {
            DB::rollback();
            if (config('debug')) {
                throw $ex;
            }
            return [false, 'We could not complete that reservation. Please try again.', null];
        }

        audit($email, ($isRequest ? 'Booking request ' : 'Booking ') . $ref . ' · ' . $p['name'], 'ok');
        $booking = self::find($ref);
        Mailer::bookingConfirmation($booking, $p);
        return [true, $isRequest ? 'Request sent to the host.' : 'Reservation confirmed.', $booking];
    }

    private static function date($v): ?string
    {
        $v = trim((string) $v);
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $v)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        return checkdate($m, $d, $y) ? $v : null;
    }

    /** No overlapping live reservation for these dates. */
    public static function isAvailable(int $propertyId, string $checkin, string $checkout, ?int $ignoreId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM bookings
                 WHERE property_id = ?
                   AND status IN ('pending','confirmed','active')
                   AND checkin < ? AND checkout > ?";
        $args = [$propertyId, $checkout, $checkin];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $args[] = $ignoreId;
        }
        return (int) DB::value($sql, $args, 0) === 0;
    }

    /** Dates already reserved, for the calendar widget. */
    public static function blockedRanges(int $propertyId): array
    {
        return array_map(static fn($b) => [$b['checkin'], $b['checkout']], DB::all(
            "SELECT checkin, checkout FROM bookings
              WHERE property_id = ? AND status IN ('pending','confirmed','active') AND checkout >= ?",
            [$propertyId, date('Y-m-d')]
        ));
    }

    public static function find(string $ref): ?array
    {
        $b = DB::row(
            'SELECT b.*, p.slug AS property_slug, p.name AS property_name, p.img, p.area, p.city, p.price AS nightly
               FROM bookings b JOIN properties p ON p.id = b.property_id
              WHERE b.ref = ?',
            [$ref]
        );
        if ($b) {
            $b['addons'] = json_decode((string) $b['addons'], true) ?: [];
        }
        return $b;
    }

    /** Bookings visible to the signed-in guest. */
    public static function forUser(int $userId, string $filter = 'all'): array
    {
        $sql = 'SELECT b.*, p.slug AS property_slug, p.name AS property_name, p.img, p.area, p.city, p.price AS nightly
                  FROM bookings b JOIN properties p ON p.id = b.property_id
                 WHERE b.user_id = ?';
        $args = [$userId];
        $today = date('Y-m-d');
        switch ($filter) {
            case 'upcoming':  $sql .= " AND b.status IN ('pending','confirmed') AND b.checkout >= ?"; $args[] = $today; break;
            case 'active':    $sql .= " AND b.status = 'active'"; break;
            case 'past':      $sql .= " AND (b.status = 'completed' OR (b.status <> 'cancelled' AND b.checkout < ?))"; $args[] = $today; break;
            case 'cancelled': $sql .= " AND b.status = 'cancelled'"; break;
        }
        $sql .= ' ORDER BY b.checkin DESC, b.id DESC';
        $rows = DB::all($sql, $args);
        foreach ($rows as &$r) {
            $r['addons'] = json_decode((string) $r['addons'], true) ?: [];
        }
        return $rows;
    }

    /** Bookings across a host's listings. */
    public static function forHost(int $hostId): array
    {
        return DB::all(
            'SELECT b.*, p.name AS property_name, p.slug AS property_slug, p.img
               FROM bookings b JOIN properties p ON p.id = b.property_id
              WHERE p.host_id = ? ORDER BY b.checkin DESC LIMIT 200',
            [$hostId]
        );
    }

    /** Move a booking through its lifecycle. Returns [ok, message]. */
    public static function transition(string $ref, string $action, ?int $actingUserId = null): array
    {
        $b = self::find($ref);
        if (!$b) {
            return [false, 'Reservation not found.'];
        }
        if ($actingUserId !== null && (int) $b['user_id'] !== $actingUserId && !Auth::isAdmin()) {
            return [false, 'You cannot modify that reservation.'];
        }

        $map = [
            'checkin'  => ['from' => ['confirmed'], 'to' => 'active',    'escrow' => 'released', 'msg' => 'Checked in — escrow released to the host.'],
            'checkout' => ['from' => ['active'],    'to' => 'completed', 'escrow' => 'released', 'msg' => 'Check-out confirmed. Enjoy the rest of your day.'],
            'cancel'   => ['from' => ['pending', 'confirmed'], 'to' => 'cancelled', 'escrow' => 'refunded', 'msg' => 'Cancellation confirmed — refund on its way.'],
            'approve'  => ['from' => ['pending'],   'to' => 'confirmed', 'escrow' => 'held',     'msg' => 'Request approved.'],
            'decline'  => ['from' => ['pending'],   'to' => 'cancelled', 'escrow' => 'refunded', 'msg' => 'Request declined and refunded.'],
        ];
        if (!isset($map[$action])) {
            return [false, 'Unknown action.'];
        }
        $step = $map[$action];
        if (!in_array((string) $b['status'], $step['from'], true)) {
            return [false, 'That action is not available for this reservation.'];
        }

        DB::update('bookings', [
            'status'        => $step['to'],
            'escrow_status' => $step['escrow'],
            'updated_at'    => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $b['id']]);

        DB::insert('booking_events', [
            'booking_id' => (int) $b['id'],
            'event'      => $action,
            'detail'     => $step['msg'],
        ]);

        if ($action === 'cancel' || $action === 'decline') {
            DB::run("UPDATE payments SET status = 'refunded' WHERE booking_id = ?", [(int) $b['id']]);
            if ($b['user_id']) {
                DB::run('UPDATE users SET points = GREATEST(0, points - ?) WHERE id = ?', [(int) $b['points_earned'], (int) $b['user_id']]);
            }
        }
        if ($action === 'checkin') {
            DB::run("UPDATE payments SET status = 'paid' WHERE booking_id = ?", [(int) $b['id']]);
        }

        audit((string) ($b['guest_email'] ?? 'guest'), 'Booking ' . $ref . ' → ' . $step['to'], 'ok');
        return [true, $step['msg']];
    }

    /** Request a date/guest change. */
    public static function requestModification(string $ref, array $in): array
    {
        $b = self::find($ref);
        if (!$b) {
            return [false, 'Reservation not found.'];
        }
        DB::insert('booking_events', [
            'booking_id' => (int) $b['id'],
            'event'      => 'modification_requested',
            'detail'     => json_encode($in),
        ]);
        DB::insert('enquiries', [
            'kind'    => 'modification',
            'name'    => $b['guest_name'],
            'email'   => $b['guest_email'],
            'subject' => 'Modification request · ' . $ref,
            'message' => json_encode($in),
            'meta'    => $ref,
        ]);
        return [true, 'Modification requested — the host will confirm shortly.'];
    }
}
