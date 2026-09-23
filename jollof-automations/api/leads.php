<?php
/**
 * Jollof Automations — Site Survey & Consultation Lead Endpoint
 *
 * Persists records into MySQL `automations_leads` table, parent `business_leads`, and triggers notifications.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Bootstrap Jollof Automations environment
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Read JSON input or POST form
$raw = file_get_contents('php://input');
$input = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) {
    $input = $_POST;
}

$name     = trim((string) ($input['name'] ?? ''));
$email    = strtolower(trim((string) ($input['email'] ?? '')));
$phone    = trim((string) ($input['phone'] ?? ''));
$city     = trim((string) ($input['city'] ?? 'Lagos (Ikoyi / VI / Lekki)'));
$propType = trim((string) ($input['property_type'] ?? 'Luxury Apartment'));
$propSize = trim((string) ($input['property_size'] ?? '3 Bedrooms'));
$estimate = trim((string) ($input['estimated_budget'] ?? '₦3,500,000 - ₦6,000,000'));
$scope    = (array) ($input['scope'] ?? []);
$surveyDate = trim((string) ($input['preferred_date'] ?? ''));
$notes    = trim((string) ($input['notes'] ?? ''));

if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Please provide your full name.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}
if ($phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Please provide your direct telephone number.']);
    exit;
}

// Create lead in MySQL automations_leads
$leadResult = AutoRepo::createLead([
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'city' => $city,
    'property_type' => $propType,
    'property_size' => $propSize,
    'estimated_budget' => $estimate,
    'scope' => $scope,
    'preferred_date' => $surveyDate,
    'notes' => $notes,
]);

$leadRef = $leadResult['reference'];

// Also bridge with Parent Jollof Living database if available
$parentApi = dirname(__DIR__, 2) . '/api/_api.php';
$parentIntegrated = false;

if (file_exists($parentApi)) {
    try {
        require_once $parentApi;
        $parentIntegrated = true;

        $formattedScope = !empty($scope) ? implode(', ', array_map('htmlspecialchars', $scope)) : 'Complete Smart Home Conversion';
        $reqText = "Reference: {$leadRef}\n"
            . "Property Type: {$propType}\n"
            . "Size: {$propSize}\n"
            . "City / Area: {$city}\n"
            . "Selected Systems: {$formattedScope}\n"
            . "Configured Estimate: {$estimate}\n"
            . "Preferred Site Survey Date: " . ($surveyDate ?: 'As soon as possible') . "\n"
            . "Client Notes: " . ($notes ?: 'None');

        if (class_exists('DB') && DB::tableExists('business_leads')) {
            DB::insert('business_leads', [
                'user_id'      => class_exists('Auth') ? Auth::id() : null,
                'company_name' => "Jollof Automations · {$propType} ({$city})",
                'contact_name' => $name,
                'contact_email'=> $email,
                'contact_phone'=> $phone,
                'team_size'    => $propSize,
                'city'         => $city,
                'requirements' => $reqText,
                'status'       => 'new',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        if (class_exists('Mailer')) {
            $adminTo = (string) config('mail.admin_to', 'concierge@jollofliving.com');
            $adminSubject = "⚡ New Smart Home Consultation Request: {$name} ({$city}) · {$leadRef}";
            $adminBody = "<h2 style='color:#173d30;margin:0 0 12px'>New Smart Home Conversion Request</h2>"
                . "<p>A new site survey request has been submitted on <b>Jollof Automations</b>.</p>"
                . "<table cellpadding='8' style='border-collapse:collapse;width:100%;font-size:14px;border:1px solid #d9dfd4'>"
                . "<tr style='background:#f7f6f0'><td><b>Reference</b></td><td>{$leadRef}</td></tr>"
                . "<tr><td><b>Client Name</b></td><td>" . htmlspecialchars($name) . "</td></tr>"
                . "<tr style='background:#f7f6f0'><td><b>Email</b></td><td><a href='mailto:{$email}'>{$email}</a></td></tr>"
                . "<tr><td><b>Phone</b></td><td><a href='tel:{$phone}'>{$phone}</a></td></tr>"
                . "<tr style='background:#f7f6f0'><td><b>Property Type / Area</b></td><td>" . htmlspecialchars($propType) . " · " . htmlspecialchars($city) . "</td></tr>"
                . "<tr style='background:#f7f6f0'><td><b>Configured Estimate</b></td><td><b>" . htmlspecialchars($estimate) . "</b></td></tr>"
                . "<tr style='background:#f7f6f0'><td><b>Preferred Date</b></td><td>" . htmlspecialchars($surveyDate ?: 'Flexible') . "</td></tr>"
                . "<tr><td><b>Client Brief</b></td><td>" . nl2br(htmlspecialchars($notes ?: '—')) . "</td></tr>"
                . "</table>";
            Mailer::send($adminTo, $adminSubject, $adminBody);
        }
    } catch (\Throwable $e) {
        error_log('Parent Integration notice: ' . $e->getMessage());
    }
}

echo json_encode([
    'ok' => true,
    'message' => "Thank you, {$name}. Your site survey request ({$leadRef}) has been received. Our lead systems engineer will reach out to you within 24 hours.",
    'data' => [
        'lead_id'   => $leadResult['id'],
        'reference' => $leadRef,
        'estimate'  => $estimate,
        'mysql_connected' => AutoDB::isConnected(),
        'parent_integrated' => $parentIntegrated
    ]
]);
