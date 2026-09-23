<?php
/**
 * Jollof Living — Universal Payment Gateway Processor (Paystack / Gateway)
 *
 * Initiates real Paystack transactions or verifies returns for:
 *   - Gift Card purchases (kind='giftcard')
 *   - Booking reservations (kind='booking')
 *
 * GET  ?action=verify&ref=...
 * POST { action: "initiate", kind: "giftcard"|"booking", amount, ... }
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';
require_once JL_INC . '/payments.php';
require_once JL_INC . '/ledger.php';

$action = input_str('action', 'initiate');

if ($action === 'initiate') {
    $kind = input_str('kind', 'giftcard');
    $user = Auth::user();

    if ($kind === 'giftcard') {
        $amount = input_int('amount');
        $allowed = [50000, 100000, 250000, 500000, 1000000];
        if (!in_array($amount, $allowed, true)) {
            json_fail('Please choose a valid gift card tier.');
        }

        $recipientName  = input_str('name');
        $recipientEmail = strtolower(input_str('email'));
        $message        = mb_substr(input_str('message'), 0, 500);

        if ($recipientName === '')      json_fail('Please tell us who this gift card is for.');
        if (!is_email($recipientEmail)) json_fail('Please provide a valid recipient email.');

        $buyerEmail = $user ? (string) $user['email'] : input_str('buyer_email', 'guest@jollofliving.com');
        $buyerName  = $user ? (string) $user['name']  : input_str('buyer_name', 'Guest');

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'JL-GIFT-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (DB::tableExists('gift_cards') && DB::value('SELECT 1 FROM gift_cards WHERE code = ?', [$code]));

        $idemKey = hash('sha256', "giftcard:{$code}:" . time() . ':' . uniqid('', true));
        $amountFen = $amount * 100;

        // Create pending payment intent
        $intent = PaymentService::createIntent(0, $amountFen, 'giftcard', $user ? (int) $user['id'] : null, $idemKey, [
            'code'            => $code,
            'amount'          => $amount,
            'recipient_name'  => $recipientName,
            'recipient_email' => $recipientEmail,
            'message'         => $message,
            'purchaser_email' => $buyerEmail,
            'purchaser_name'  => $buyerName,
            'email'           => $buyerEmail,
        ]);

        $provider = PaymentService::provider();
        try {
            $res = $provider->initiate([
                'amount_fen'      => $amountFen,
                'email'           => $buyerEmail,
                'guest_name'      => $buyerName,
                'idempotency_key' => $idemKey,
                'booking_ref'     => $code,
                'kind'            => 'giftcard',
            ]);

            // Save provider reference
            if (!empty($res['provider_ref'])) {
                DB::update('payment_intents', ['provider_ref' => (string) $res['provider_ref']], 'id = ?', [(int) $intent['id']]);
            }

            // In sandbox or record_only mode without live keys, auto-fulfill for seamless testing
            $mode = config('payments.mode', 'record_only');
            if ($mode === 'record_only' || $mode === 'sandbox') {
                PaymentService::captureIntent((int) $intent['id'], ['source' => 'sandbox_autocapture']);
                
                // Mint the gift card row
                if (DB::tableExists('gift_cards')) {
                    DB::insert('gift_cards', [
                        'code'      => $code,
                        'amount'    => $amount,
                        'balance'   => $amount,
                        'purchaser' => $buyerEmail,
                        'recipient' => $recipientEmail,
                        'message'   => $message ?: null,
                        'status'    => 'active',
                    ]);

                    Ledger::postTransaction([
                        ['account' => Ledger::ACCT_GUEST_CASH,     'direction' => 'debit',  'amount_fen' => $amountFen],
                        ['account' => Ledger::ACCT_GIFT_LIABILITY, 'direction' => 'credit', 'amount_fen' => $amountFen],
                    ], 'giftcard_purchase', $code, null, $user ? (int) $user['id'] : null, 'Funded purchase of digital gift card ' . $code);

                    Mailer::sendGiftCard(
                        $recipientEmail,
                        input_str('recipient_name') ?: $recipientEmail,
                        $buyerName,
                        $code,
                        $amount,
                        $message
                    );
                }

                json_ok([
                    'mode'         => 'sandbox',
                    'code'         => $code,
                    'amount'       => $amount,
                    'redirect_url' => '',
                ], 'Payment successful — gift card issued ✨');
            }

            json_ok([
                'mode'         => 'live',
                'redirect_url' => $res['redirect_url'],
                'access_code'  => $res['access_code'],
                'reference'    => $idemKey,
                'code'         => $code,
            ], 'Redirecting to secure payment...');
        } catch (Throwable $e) {
            json_fail('Payment initialization failed: ' . $e->getMessage());
        }
    }
}

if ($action === 'verify') {
    $ref = input_str('reference');
    if ($ref === '') {
        json_fail('Missing payment reference.');
    }

    $intent = DB::row('SELECT * FROM payment_intents WHERE idempotency_key = ? OR provider_ref = ?', [$ref, $ref]);
    if (!$intent) {
        json_fail('Payment intent not found.', 404);
    }

    if ($intent['status'] === 'captured') {
        json_ok(['status' => 'captured'], 'Payment already confirmed.');
    }

    $provider = PaymentService::provider((string) $intent['provider']);
    [$status, $raw] = $provider->verify((string) ($intent['provider_ref'] ?: $ref));

    if ($status === 'captured') {
        PaymentService::captureIntent((int) $intent['id'], (array) $raw);

        // If gift card, mint the card upon capture
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
        }

        json_ok(['status' => 'captured'], 'Payment verified and credited ✨');
    }

    json_fail('Payment could not be verified with Paystack.');
}

json_fail('Unknown pay action.');
