<?php
/**
 * Jollof Living — Payment Provider Abstraction & Engine (WP04)
 *
 * Implements the Provider interface with SandboxProvider (local test/demo),
 * PaystackProvider and FlutterwaveProvider.
 *
 * All state changes flow through captured webhook or verified poll events.
 */
declare(strict_types=1);

interface PaymentProvider
{
    /** Initiate payment and return {redirect_url, access_code, provider_ref} */
    public function initiate(array $intent): array;

    /** Verify payment status directly with provider. Returns [status, raw_payload] */
    public function verify(string $providerRef): array;

    /** Initiate a refund. Returns [ok, refund_ref, message] */
    public function refund(string $providerRef, int $amountFen, string $reason = ''): array;

    /** Verify webhook authenticity. Returns true if valid signature */
    public function webhookAuth(string $rawBody, array $headers): bool;
}

final class SandboxProvider implements PaymentProvider
{
    public function initiate(array $intent): array
    {
        $ref = 'SBX-' . strtoupper(bin2hex(random_bytes(6)));
        return [
            'redirect_url' => url('confirm.php?ref=' . urlencode((string) ($intent['booking_ref'] ?? '')) . '&sbx=1&intent=' . $intent['idempotency_key']),
            'access_code'  => $ref,
            'provider_ref' => $ref,
        ];
    }

    public function verify(string $providerRef): array
    {
        // Sandbox auto-captures on verification
        return ['captured', ['reference' => $providerRef, 'mode' => 'sandbox', 'status' => 'success']];
    }

    public function refund(string $providerRef, int $amountFen, string $reason = ''): array
    {
        return [true, 'SBX-REF-' . strtoupper(bin2hex(random_bytes(4))), 'Sandbox refund recorded'];
    }

    public function webhookAuth(string $rawBody, array $headers): bool
    {
        // Local sandbox accepts test calls with signature = sandbox or header X-Sandbox: 1
        return ($headers['x-sandbox'] ?? '') === '1' || ($headers['X-Sandbox'] ?? '') === '1';
    }
}

final class PaystackProvider implements PaymentProvider
{
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $sec = (string) config('payments.paystack.secret_key', '');
        if ($sec === '') {
            $sec = (string) (getenv('PAYSTACK_SECRET_KEY') ?: '');
        }
        if ($sec === '' && DB::tableExists('settings')) {
            $sec = (string) DB::value("SELECT svalue FROM settings WHERE skey = 'paystack_secret_key'", [], '');
        }
        $this->secretKey = $sec;

        $whSec = (string) config('payments.paystack.webhook_secret', '');
        if ($whSec === '') {
            $whSec = (string) (getenv('PAYSTACK_WEBHOOK_SECRET') ?: '');
        }
        if ($whSec === '' && DB::tableExists('settings')) {
            $whSec = (string) DB::value("SELECT svalue FROM settings WHERE skey = 'paystack_webhook_secret'", [], '');
        }
        if ($whSec === '') {
            $whSec = $this->secretKey;
        }
        $this->webhookSecret = $whSec;
    }

    public function initiate(array $intent): array
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Paystack secret key is not configured. Please add your Paystack API keys to config.php.');
        }

        $callbackUrl = ($intent['kind'] ?? '') === 'giftcard'
            ? absolute_url('api/verify-payment.php?trxref=' . urlencode((string) $intent['idempotency_key']))
            : absolute_url('confirm.php?ref=' . urlencode((string) ($intent['booking_ref'] ?? '')));

        $url = 'https://api.paystack.co/transaction/initialize';
        $payload = [
            'amount'       => (int) $intent['amount_fen'], // Kobo
            'email'        => (string) ($intent['email'] ?? 'guest@jollofliving.com'),
            'reference'    => (string) $intent['idempotency_key'],
            'callback_url' => $callbackUrl,
            'metadata'     => [
                'booking_id'  => $intent['booking_id'] ?? null,
                'booking_ref' => $intent['booking_ref'] ?? null,
                'kind'        => $intent['kind'] ?? 'booking',
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException('Paystack communication failure: ' . $err);
        }

        $data = json_decode((string) $res, true);
        if (empty($data['status'])) {
            throw new RuntimeException('Paystack initialization error: ' . ($data['message'] ?? 'Unknown error'));
        }

        return [
            'redirect_url' => (string) ($data['data']['authorization_url'] ?? ''),
            'access_code'  => (string) ($data['data']['access_code'] ?? ''),
            'provider_ref' => (string) ($data['data']['reference'] ?? $intent['idempotency_key']),
        ];
    }

    public function verify(string $providerRef): array
    {
        if ($this->secretKey === '') {
            return ['failed', ['error' => 'No secret key']];
        }

        $url = 'https://api.paystack.co/transaction/verify/' . urlencode($providerRef);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $res, true);
        if (!empty($data['status']) && ($data['data']['status'] ?? '') === 'success') {
            return ['captured', $data['data']];
        }

        return ['failed', $data ?? []];
    }

    public function refund(string $providerRef, int $amountFen, string $reason = ''): array
    {
        if ($this->secretKey === '') {
            return [false, '', 'Paystack secret key missing'];
        }

        $url = 'https://api.paystack.co/refund';
        $payload = [
            'transaction'     => $providerRef,
            'amount'          => $amountFen,
            'merchant_note'   => mb_substr($reason, 0, 200),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $res, true);
        if (!empty($data['status'])) {
            return [true, (string) ($data['data']['id'] ?? 'PS-REF'), 'Refund initiated'];
        }

        return [false, '', $data['message'] ?? 'Paystack refund declined'];
    }

    public function webhookAuth(string $rawBody, array $headers): bool
    {
        $signature = $headers['x-paystack-signature'] ?? $headers['X-Paystack-Signature'] ?? '';
        if ($signature === '' || $this->webhookSecret === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha512', $rawBody, $this->webhookSecret), $signature);
    }
}

final class FlutterwaveProvider implements PaymentProvider
{
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = (string) config('payments.flutterwave.secret_key', '');
        $this->webhookSecret = (string) config('payments.flutterwave.webhook_secret', $this->secretKey);
    }

    public function initiate(array $intent): array
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }

        $url = 'https://api.flutterwave.com/v3/payments';
        $payload = [
            'tx_ref'          => (string) $intent['idempotency_key'],
            'amount'          => (float) ($intent['amount_fen'] / 100.0), // Naira
            'currency'        => 'NGN',
            'redirect_url'    => url('confirm.php?ref=' . urlencode((string) ($intent['booking_ref'] ?? ''))),
            'customer'        => [
                'email' => (string) ($intent['email'] ?? 'guest@jollofliving.com'),
                'name'  => (string) ($intent['guest_name'] ?? 'Guest'),
            ],
            'meta'            => [
                'booking_id'  => $intent['booking_id'] ?? null,
                'booking_ref' => $intent['booking_ref'] ?? null,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException('Flutterwave communication failure: ' . $err);
        }

        $data = json_decode((string) $res, true);
        if (empty($data['status']) || $data['status'] !== 'success') {
            throw new RuntimeException('Flutterwave init error: ' . ($data['message'] ?? 'Unknown error'));
        }

        return [
            'redirect_url' => (string) ($data['data']['link'] ?? ''),
            'access_code'  => (string) $intent['idempotency_key'],
            'provider_ref' => (string) $intent['idempotency_key'],
        ];
    }

    public function verify(string $providerRef): array
    {
        if ($this->secretKey === '') {
            return ['failed', ['error' => 'No secret key']];
        }

        $url = 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . urlencode($providerRef);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $res, true);
        if (!empty($data['status']) && $data['status'] === 'success' && ($data['data']['status'] ?? '') === 'successful') {
            return ['captured', $data['data']];
        }

        return ['failed', $data ?? []];
    }

    public function refund(string $providerRef, int $amountFen, string $reason = ''): array
    {
        if ($this->secretKey === '') {
            return [false, '', 'Flutterwave secret key missing'];
        }

        $url = 'https://api.flutterwave.com/v3/transactions/' . urlencode($providerRef) . '/refund';
        $payload = [
            'amount' => (float) ($amountFen / 100.0),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $res, true);
        if (!empty($data['status']) && $data['status'] === 'success') {
            return [true, (string) ($data['data']['id'] ?? 'FLW-REF'), 'Refund initiated'];
        }

        return [false, '', $data['message'] ?? 'Flutterwave refund declined'];
    }

    public function webhookAuth(string $rawBody, array $headers): bool
    {
        $signature = $headers['verif-hash'] ?? $headers['Verif-Hash'] ?? '';
        if ($signature === '' || $this->webhookSecret === '') {
            return false;
        }
        return hash_equals($this->webhookSecret, $signature);
    }
}

final class PaymentService
{
    public static function provider(?string $name = null): PaymentProvider
    {
        $mode = $name ?: (string) config('payments.mode', 'record_only');
        switch ($mode) {
            case 'paystack':
                return new PaystackProvider();
            case 'flutterwave':
                return new FlutterwaveProvider();
            case 'sandbox':
            case 'record_only':
            default:
                return new SandboxProvider();
        }
    }

    /**
     * Create or retrieve a payment intent.
     */
    public static function createIntent(
        int $bookingId,
        int $amountFen,
        string $kind = 'booking',
        ?int $userId = null,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): array {
        if (!DB::tableExists('payment_intents')) {
            return ['id' => 0, 'idempotency_key' => 'legacy', 'status' => 'initiated', 'amount_fen' => $amountFen];
        }

        $key = $idempotencyKey ?: hash('sha256', "intent:$bookingId:$amountFen:$kind:" . uniqid('', true));
        $existing = DB::row('SELECT * FROM payment_intents WHERE idempotency_key = ?', [$key]);
        if ($existing) {
            return $existing;
        }

        $providerName = (string) config('payments.mode', 'record_only');
        $id = DB::insert('payment_intents', [
            'booking_id'      => $bookingId,
            'user_id'         => $userId,
            'kind'            => $kind,
            'amount_fen'      => $amountFen,
            'currency'        => 'NGN',
            'provider'        => $providerName,
            'status'          => 'initiated',
            'idempotency_key' => $key,
            'metadata'        => json_encode($metadata),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return DB::row('SELECT * FROM payment_intents WHERE id = ?', [$id]);
    }

    /**
     * Transition an intent to captured status, post double-entry ledger rows,
     * and advance the booking to confirmed.
     */
    public static function captureIntent(int $intentId, array $rawPayload = []): bool
    {
        if (!DB::tableExists('payment_intents')) {
            return false;
        }

        $intent = DB::row('SELECT * FROM payment_intents WHERE id = ?', [$intentId]);
        if (!$intent || $intent['status'] === 'captured') {
            return true; // Already captured / idempotent
        }

        $now = date('Y-m-d H:i:s');
        $bookingId = (int) ($intent['booking_id'] ?? 0);
        $amountFen = (int) $intent['amount_fen'];

        DB::begin();
        try {
            DB::update('payment_intents', [
                'status'       => 'captured',
                'verified_at'  => $now,
                'payload_hash' => hash('sha256', json_encode($rawPayload)),
                'updated_at'   => $now,
            ], 'id = ?', [$intentId]);

            // Post double entry ledger transaction: guest cash -> escrow
            Ledger::postTransaction([
                ['account' => Ledger::ACCT_GUEST_CASH, 'direction' => 'debit',  'amount_fen' => $amountFen],
                ['account' => Ledger::ACCT_ESCROW,     'direction' => 'credit', 'amount_fen' => $amountFen],
            ], 'payment', (string) $intent['idempotency_key'], $bookingId, (int) ($intent['user_id'] ?? 0), 'Payment captured into escrow');

            // If attached to a booking, advance booking status
            if ($bookingId > 0 && DB::tableExists('bookings')) {
                $b = DB::row('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
                if ($b) {
                    $newStatus = ((int) ($b['is_request'] ?? 0) === 1) ? 'pending' : 'confirmed';
                    DB::update('bookings', [
                        'status'        => $newStatus,
                        'escrow_status' => 'held',
                        'updated_at'    => $now,
                    ], 'id = ?', [$bookingId]);

                    DB::insert('booking_events', [
                        'booking_id' => $bookingId,
                        'event'      => 'payment_captured',
                        'detail'     => 'Payment intent ' . $intent['idempotency_key'] . ' verified and captured into escrow (' . money((int) round($amountFen / 100)) . ')',
                        'created_at' => $now,
                    ]);

                    // Sync legacy payments table if it exists
                    if (DB::tableExists('payments')) {
                        DB::run("UPDATE payments SET status = 'paid' WHERE booking_id = ? AND status <> 'paid'", [$bookingId]);
                    }
                }
            }

            DB::commit();

            // Dispatch confirmation emails and host alert once booking is captured
            if ($bookingId > 0 && class_exists('BookingService') && class_exists('Mailer')) {
                try {
                    $bData = BookingService::find($bookingId);
                    if ($bData && $bData['status'] === 'confirmed') {
                        Mailer::bookingConfirmation($bData);
                        Mailer::hostBookingAlert($bData);
                        Mailer::sendBookingReceipt($bData);
                    }
                } catch (Throwable $mailEx) {
                    error_log('Booking payment email alert failure: ' . $mailEx->getMessage());
                }
            }

            return true;
        } catch (Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }
}
