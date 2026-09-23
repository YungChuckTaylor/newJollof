<?php
/**
 * Jollof Living — Callback Return & Payment Verification
 *
 * Handles Paystack redirect callbacks from checkout:
 *   GET ?trxref=...&reference=...
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';
require_once JL_INC . '/payments.php';
require_once JL_INC . '/ledger.php';

$ref = input_str('reference') ?: input_str('trxref');
if ($ref === '') {
    header('Location: ' . url('/'));
    exit;
}

$intent = DB::row('SELECT * FROM payment_intents WHERE idempotency_key = ? OR provider_ref = ?', [$ref, $ref]);
if (!$intent) {
    header('Location: ' . url('/'));
    exit;
}

if ($intent['status'] !== 'captured') {
    $provider = PaymentService::provider((string) $intent['provider']);
    [$status, $raw] = $provider->verify((string) ($intent['provider_ref'] ?: $ref));
    if ($status === 'captured') {
        PaymentService::captureIntent((int) $intent['id'], (array) $raw);

        // If gift card, fulfill and mint row
        if ($intent['kind'] === 'giftcard') {
            $meta = json_decode((string) $intent['metadata'], true) ?: [];
            $code = (string) ($meta['code'] ?? '');
            $amount = (int) ($meta['amount'] ?? 0);
            if ($code !== '' && $amount > 0 && DB::tableExists('gift_cards')) {
                $exists = DB::value('SELECT 1 FROM gift_cards WHERE code = ?', [$code]);
                if (!$exists) {
                    DB::insert('gift_cards', [
                        'code'      => $code,
                        'amount'    => $amount,
                        'balance'   => $amount,
                        'purchaser' => (string) ($meta['purchaser_email'] ?? ''),
                        'recipient' => (string) ($meta['recipient_email'] ?? ''),
                        'message'   => (string) ($meta['message'] ?? '') ?: null,
                        'status'    => 'active',
                    ]);

                    Ledger::postTransaction([
                        ['account' => Ledger::ACCT_GUEST_CASH,     'direction' => 'debit',  'amount_fen' => $amount * 100],
                        ['account' => Ledger::ACCT_GIFT_LIABILITY, 'direction' => 'credit', 'amount_fen' => $amount * 100],
                    ], 'giftcard_purchase', $code, null, (int) ($intent['user_id'] ?? 0), 'Funded purchase of digital gift card ' . $code);

                    Mailer::send((string) ($meta['recipient_email'] ?? ''), 'You have a Jollof Living gift card',
                        '<h2 style="margin:0 0 12px;font-size:20px">A gift from ' . e((string) ($meta['purchaser_name'] ?? 'Guest')) . '</h2>'
                        . (!empty($meta['message']) ? '<p style="font-style:italic">“' . nl2br(e((string) $meta['message'])) . '”</p>' : '')
                        . '<p>Your gift card is worth <b>' . e(money($amount)) . '</b>.</p>'
                        . '<p style="font-size:22px;letter-spacing:.2em;font-weight:700">' . e($code) . '</p>'
                        . '<p>Enter it at checkout on any residence. The balance never expires.</p>');
                }
            }
            header('Location: ' . url('giftcards.php?paid=1&code=' . urlencode($code)));
            exit;
        }

        // If booking, forward to confirm page
        if ($intent['kind'] === 'booking') {
            $bookingId = (int) ($intent['booking_id'] ?? 0);
            $bookingRef = DB::value('SELECT ref FROM bookings WHERE id = ?', [$bookingId]);
            if ($bookingRef) {
                header('Location: ' . url('confirm.php?ref=' . urlencode((string) $bookingRef)));
                exit;
            }
        }
    }
}

if ($intent['kind'] === 'booking') {
    $bookingId = (int) ($intent['booking_id'] ?? 0);
    $bookingRef = DB::value('SELECT ref FROM bookings WHERE id = ?', [$bookingId]);
    header('Location: ' . url('confirm.php?ref=' . urlencode((string) $bookingRef)));
    exit;
}

header('Location: ' . url('/'));
exit;
