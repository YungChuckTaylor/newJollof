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

                    Mailer::sendGiftCard(
                        (string) ($meta['recipient_email'] ?? ''),
                        (string) ($meta['recipient_name'] ?? ''),
                        (string) ($meta['purchaser_name'] ?? 'A member of Jollof Living'),
                        $code,
                        $amount,
                        (string) ($meta['message'] ?? '')
                    );
                }
            }
            header('Location: ' . url('giftcards.php?paid=1&code=' . urlencode($code)));
            exit;
        }

        // If booking, send receipt and forward to confirm page
        if ($intent['kind'] === 'booking') {
            $bookingId = (int) ($intent['booking_id'] ?? 0);
            $booking = BookingService::find($bookingId);
            if ($booking) {
                Mailer::sendBookingReceipt($booking);
                if (!empty($booking['user_id'])) {
                    Repo::notify((int) $booking['user_id'], 'Payment received ✨', 'Official receipt dispatched for reservation ' . $booking['ref'], 'card');
                }
                header('Location: ' . url('confirm.php?ref=' . urlencode((string) $booking['ref'])));
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
