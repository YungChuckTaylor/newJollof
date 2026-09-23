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
    public static function quote(array $p, int $nights, array $addons = [], string $promo = '', int $gift = 0, ?string $checkin = null, ?string $checkout = null): array
    {
        $R = Repo::rates();
        $nights = max(1, $nights);
        $pid = (int) ($p['pid'] ?? $p['id'] ?? 0);
        $nightly = (int) $p['price'];

        // Phase 2 (WP08): Calculate exact base amount accounting for daily calendar overrides
        $base = 0;
        if ($checkin !== null && $checkout !== null && $pid > 0 && DB::tableExists('property_calendar')) {
            $cur = strtotime($checkin);
            $out = strtotime($checkout);
            $overrides = DB::pairs('SELECT day, price FROM property_calendar WHERE property_id = ? AND day >= ? AND day < ?', [$pid, $checkin, $checkout]);
            while ($cur < $out) {
                $dayIso = date('Y-m-d', $cur);
                $dayPrice = isset($overrides[$dayIso]) && $overrides[$dayIso] !== null ? (int) $overrides[$dayIso] : $nightly;
                $base += $dayPrice;
                $cur = strtotime('+1 day', $cur);
            }
        } else {
            $base = $nightly * $nights;
        }

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

        // Phase 2 (WP09): Reject bookings on paused/draft/pending listings
        $pStatus = strtolower((string) ($p['status'] ?? 'active'));
        if (in_array($pStatus, ['paused', 'pending', 'draft', 'disabled'], true)) {
            return [false, 'This residence is currently not accepting reservations (WP09).', null];
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
        $requestedGuests = (int) ($in['guests'] ?? 1);
        $maxGuests = (int) ($p['guests'] ?? 2);
        if ($requestedGuests > $maxGuests) {
            return [false, "This residence accommodates up to {$maxGuests} guests (WP09).", null];
        }
        $guests = max(1, $requestedGuests);

        if (!self::isAvailable((int) $p['pid'], $checkin, $checkout)) {
            return [false, 'Those dates have just been taken. Please choose another window.', null];
        }

        $addons = array_values(array_filter((array) ($in['addons'] ?? []), 'is_string'));
        $promo  = strtoupper(trim((string) ($in['promo'] ?? '')));

        /* Gift-card credit is never a number the browser may type — the client
           sends the CODE, and only the database says what the card is worth.
           The applied amount is min(balance, what this stay actually costs). */
        $giftCode = strtoupper(trim((string) ($in['giftCode'] ?? '')));
        $gift     = 0;
        $giftCard = null;
        if ($giftCode !== '') {
            if (!DB::tableExists('gift_cards')) {
                return [false, 'Gift cards are not provisioned on this install yet.', null];
            }
            $giftCard = DB::row("SELECT * FROM gift_cards WHERE code = ? AND status = 'active'", [$giftCode]);
            if (!$giftCard) {
                return [false, 'That gift card code is not valid or is no longer active.', null];
            }
            if ((int) $giftCard['balance'] <= 0) {
                return [false, 'That gift card has no balance left.', null];
            }
        }
        $q = Pricing::quote($p, $nights, $addons, $promo, 0, $checkin, $checkout);
        if ($giftCard) {
            $payable = max(0, (int) $q['total']);
            if ($payable > 0) {
                $gift = max(1, min((int) $giftCard['balance'], $payable));
                $q = Pricing::quote($p, $nights, $addons, $promo, $gift, $checkin, $checkout);
            }
        }

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

        // Phase 1 (WP06): Split payment eligibility check (minimum 30 nights)
        $splitRequested = !empty($in['split']);
        if ($splitRequested && $nights < 30) {
            return [false, 'Split payment (50% now, 50% at check-in) is only eligible on extended stays of 30 nights or more (WP06).', null];
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
                'breakdown'     => json_encode($giftCode !== '' && $gift > 0 ? $q + ['giftCode' => $giftCode] : $q),
            ]);

            // Phase 2 (WP09): Hard atomic night holds against concurrency double-booking
            if (DB::tableExists('night_holds')) {
                $holdCur = strtotime($checkin);
                $holdOut = strtotime($checkout);
                while ($holdCur < $holdOut) {
                    DB::insert('night_holds', [
                        'property_id' => (int) $p['pid'],
                        'stay_date'   => date('Y-m-d', $holdCur),
                        'booking_id'  => $id,
                        'status'      => 'active',
                        'created_at'  => date('Y-m-d H:i:s'),
                    ]);
                    $holdCur = strtotime('+1 day', $holdCur);
                }
            }

            DB::insert('booking_events', [
                'booking_id' => $id,
                'event'      => $isRequest ? 'requested' : 'confirmed',
                'detail'     => 'Payment captured into escrow · ' . $method,
            ]);

            if ($q['promoCode']) {
                DB::run('UPDATE promos SET uses = uses + 1 WHERE code = ?', [$q['promoCode']]);
            }

            /* Burn the gift-card credit for real, in the same transaction as
               the reservation: the conditional UPDATE loses the race against a
               second redemption of the same code, and this booking is rolled
               back rather than double-spent. */
            if ($gift > 0) {
                $burn = DB::run(
                    "UPDATE gift_cards
                        SET balance = balance - ?,
                            status  = CASE WHEN balance - ? <= 0 THEN 'redeemed' ELSE status END
                      WHERE code = ? AND status = 'active' AND balance >= ?",
                    [$gift, $gift, $giftCode, $gift]
                );
                if ($burn->rowCount() !== 1) {
                    throw new RuntimeException('That gift card was used on another booking mid-checkout.');
                }
            }

            $chargeAmount = !empty($in['split']) ? $q['halfNow'] : $q['total'];
            DB::insert('payments', [
                'booking_id' => $id,
                'user_id'    => $user['id'] ?? null,
                'reference'  => $ref . '-P1',
                'gateway'    => (string) config('payments.mode', 'record_only'),
                'amount'     => $chargeAmount,
                'currency'   => 'NGN',
                'status'     => 'initiated',
                'payload'    => json_encode(['method' => $method, 'split' => !empty($in['split'])]),
            ]);

            // Phase 1 (WP04/WP05): Create PaymentIntent and post initial ledger holds
            $intentKey = hash('sha256', "booking:$ref:$id:$chargeAmount");
            $intent = PaymentService::createIntent($id, $chargeAmount * 100, 'booking', (int) ($user['id'] ?? 0), $intentKey, [
                'booking_ref' => $ref,
                'email'       => $email,
                'name'        => $name,
                'split'       => !empty($in['split']),
            ]);

            // If gift card credit was applied, post the gift liability transfer to escrow right now
            if ($gift > 0) {
                Ledger::postTransaction([
                    ['account' => Ledger::ACCT_GIFT_LIABILITY, 'direction' => 'debit',  'amount_fen' => $gift * 100],
                    ['account' => Ledger::ACCT_ESCROW,         'direction' => 'credit', 'amount_fen' => $gift * 100],
                ], 'gift_redemption', (string) $giftCode, $id, (int) ($user['id'] ?? 0), 'Gift card credit applied to reservation');
            }

            // In demo/sandbox or record_only mode, auto-capture intent into escrow
            if (config('payments.mode', 'record_only') === 'record_only' || config('payments.mode') === 'sandbox') {
                PaymentService::captureIntent((int) $intent['id'], ['source' => 'record_only_checkout']);
            }

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
        // Phase 2 (WP10/WP11): Role permissions on transition
        if ($actingUserId !== null && !Auth::isAdmin()) {
            $isGuest = (int) $b['user_id'] === $actingUserId;
            $isHost = (int) DB::value('SELECT host_id FROM properties WHERE id = ?', [(int) $b['property_id']]) === $actingUserId;
            if (in_array($action, ['approve', 'decline'], true)) {
                if (!$isHost) {
                    return [false, 'Only the property host can approve or decline this request (M08).'];
                }
            } elseif (!$isGuest && !$isHost) {
                return [false, 'You cannot modify that reservation.'];
            }
        }

        /* The money machine, honestly: arrival flips the stay to active but
           the host's payout waits for departure (escrow releases on CHECK-OUT
           only); the old map released at check-in — guests could trigger it
           and hosts could push it before anyone had slept there. */
        $map = [
            'checkin'  => ['from' => ['confirmed'], 'to' => 'active',    'escrow' => 'held',     'msg' => 'Checked in — enjoy the stay. The escrow stays held until check-out.'],
            'checkout' => ['from' => ['active'],    'to' => 'completed', 'escrow' => 'released', 'msg' => 'Check-out confirmed — the escrow has been released to the host.'],
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
        /* Time gate for the parties themselves (admin-console actions carry no
           acting user and may still correct records by hand, with an audit
           line): nobody checks in before the arrival day, nothing “checks
           out” before the stay has run. */
        if ($actingUserId !== null) {
            if ($action === 'checkin') {
                if ((string) ($b['checkin'] ?? '') > date('Y-m-d')) {
                    return [false, 'Check-in opens on your arrival day (' . (string) $b['checkin'] . ').'];
                }
                // Phase 2 (WP10, A53/A54): Verify captured payment exists
                $hasCaptured = Ledger::bookingEscrowBalance((int) $b['id']) > 0 || (string) ($b['status'] ?? '') === 'confirmed';
                if (!$hasCaptured && (int) ($b['total'] ?? 0) > 0) {
                    return [false, 'Check-in requires a confirmed, paid reservation (A53/A54).'];
                }
            }
            if ($action === 'checkout' && (string) ($b['checkout'] ?? '') > date('Y-m-d')) {
                return [false, 'Check-out is recorded on or after your departure day (' . (string) $b['checkout'] . ').'];
            }
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
            // Phase 2 (WP09/WP10): Release night holds on cancellation
            if (DB::tableExists('night_holds')) {
                DB::run("UPDATE night_holds SET status = 'released' WHERE booking_id = ?", [(int) $b['id']]);
            }
            $calc = Cancellations::compute($b);
            $refundFen = $calc['refund_fen'];
            if ($refundFen > 0) {
                // Post ledger reversal from escrow to refund_out
                Ledger::postTransaction([
                    ['account' => Ledger::ACCT_ESCROW,     'direction' => 'debit',  'amount_fen' => $refundFen],
                    ['account' => Ledger::ACCT_REFUND_OUT, 'direction' => 'credit', 'amount_fen' => $refundFen],
                ], 'refund', 'CNL-' . $b['ref'], (int) $b['id'], (int) ($b['user_id'] ?? 0), 'Cancellation refund under ' . $calc['policy'] . ' policy (' . ($calc['pct'] * 100) . '%)');
            }
            if ($b['user_id']) {
                $ptsRev = $calc['points_to_reverse'];
                if ($ptsRev > 0) {
                    DB::run('UPDATE users SET points = GREATEST(0, points - ?) WHERE id = ?', [$ptsRev, (int) $b['user_id']]);
                    DB::insert('points_ledger', [
                        'user_id'     => (int) $b['user_id'],
                        'date_label'  => date('M j, Y'),
                        'description' => 'Cancellation reversal · ' . ($b['property_name'] ?? $b['ref']),
                        'amount'      => -$ptsRev,
                        'kind'        => 'redeem',
                    ]);
                }
            }
        }
        if ($action === 'checkout') {
            // Release escrow to host earnings and platform commission
            $heldFen = Ledger::bookingEscrowBalance((int) $b['id']);
            if ($heldFen > 0) {
                $takeRate = (float) Repo::setting('host_take_rate', 0.12);
                $feeFen = (int) round($heldFen * $takeRate);
                $hostFen = max(0, $heldFen - $feeFen);
                Ledger::postTransaction([
                    ['account' => Ledger::ACCT_ESCROW,        'direction' => 'debit',  'amount_fen' => $heldFen],
                    ['account' => Ledger::ACCT_HOST_EARNINGS, 'direction' => 'credit', 'amount_fen' => $hostFen],
                    ['account' => Ledger::ACCT_PLATFORM_FEE,   'direction' => 'credit', 'amount_fen' => $feeFen],
                ], 'checkout_release', 'REL-' . $b['ref'], (int) $b['id'], (int) ($b['user_id'] ?? 0), 'Escrow settled on verified checkout');
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

        // Phase 2 (WP10): Record in booking_changes with re-quoted delta
        if (DB::tableExists('booking_changes')) {
            $prop = Repo::propertyById((int) $b['property_id']);
            $newCheckin = (string) ($in['checkin'] ?: $b['checkin']);
            $newCheckout = (string) ($in['checkout'] ?: $b['checkout']);
            $newGuests = (int) ($in['guests'] ?: $b['guests']);
            $newNights = nights_between($newCheckin, $newCheckout);
            $newQuote = $prop ? Pricing::quote($prop, $newNights, [], '', 0, $newCheckin, $newCheckout) : [];
            $deltaNaira = ((int) ($newQuote['total'] ?? $b['total'])) - (int) $b['total'];

            DB::insert('booking_changes', [
                'booking_id'        => (int) $b['id'],
                'proposed_checkin'  => $newCheckin,
                'proposed_checkout' => $newCheckout,
                'proposed_guests'   => $newGuests,
                'delta_quote'       => json_encode(['new_quote' => $newQuote, 'delta' => $deltaNaira]),
                'status'            => 'pending_host',
                'note'              => (string) ($in['note'] ?? ''),
                'expires_at'        => date('Y-m-d H:i:s', strtotime('+72 hours')),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        return [true, 'Modification requested — the host will confirm shortly.'];
    }
}
