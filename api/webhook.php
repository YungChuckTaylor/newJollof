<?php
/**
 * Jollof Living — Unified Payment & Gateway Webhook Endpoint (WP04)
 *
 * Receives, validates signatures, verifies amounts, and atomically advances
 * payment intents and bookings. Completely idempotent.
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';
require_once JL_INC . '/payments.php';
require_once JL_INC . '/ledger.php';

$rawBody = (string) file_get_contents('php://input');
$headers = getallheaders() ?: [];
$providerName = input_str('provider', (string) config('payments.mode', 'record_only'));

$provider = PaymentService::provider($providerName);
if (!$provider->webhookAuth($rawBody, $headers)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid webhook signature']));
}

$payload = json_decode($rawBody, true) ?: [];

// Resolve idempotency reference
$ref = '';
if ($providerName === 'paystack') {
    $ref = (string) ($payload['data']['reference'] ?? '');
} elseif ($providerName === 'flutterwave') {
    $ref = (string) ($payload['data']['tx_ref'] ?? '');
} else {
    $ref = (string) ($payload['reference'] ?? $payload['idempotency_key'] ?? '');
}

if ($ref === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing transaction reference']));
}

$intent = DB::row('SELECT * FROM payment_intents WHERE idempotency_key = ? OR provider_ref = ?', [$ref, $ref]);
if (!$intent) {
    // Acknowledge receipt even if not found locally, to stop retries
    http_response_code(200);
    exit(json_encode(['status' => 'ignored_unknown_reference']));
}

if ($intent['status'] === 'captured') {
    http_response_code(200);
    exit(json_encode(['status' => 'already_captured']));
}

// Verify status and amount with provider
[$verifiedStatus, $verifiedData] = $provider->verify($ref);
if ($verifiedStatus === 'captured') {
    PaymentService::captureIntent((int) $intent['id'], $verifiedData);
    http_response_code(200);
    exit(json_encode(['status' => 'captured']));
}

http_response_code(200);
exit(json_encode(['status' => 'acknowledged']));
