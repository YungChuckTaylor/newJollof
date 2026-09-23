<?php
/**
 * Jollof Automations — Site Survey & Consultation Lead Endpoint
 *
 * Captures consultation requests, smart home system quotes, and on-site audit bookings.
 * Persists records into business_leads and dispatches confirmation notifications.
 */
declare(strict_types=1);

// Locate bootstrap & API guard
$rootPath = dirname(__DIR__, 2);
if (file_exists($rootPath . '/api/_api.php')) {
    require_once $rootPath . '/api/_api.php';
} else {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Backend configuration missing']);
    exit;
}

api_guard();
api_throttle('automations_lead', 10, 300);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_fail('Method not allowed', 405);
}

$name     = trim(input_str('name'));
$email    = strtolower(trim(input_str('email')));
$phone    = trim(input_str('phone'));
$city     = trim(input_str('city', 'Lagos (Ikoyi / VI / Lekki)'));
$propType = trim(input_str('property_type', 'Luxury Apartment'));
$propSize = trim(input_str('property_size', '3 Bedrooms'));
$estimate = trim(input_str('estimated_budget', '₦3,500,000 - ₦6,000,000'));
$scope    = (array) (input('scope') ?: []);
$surveyDate = trim(input_str('preferred_date', ''));
$notes    = trim(input_str('notes', ''));

if ($name === '') {
    json_fail('Please provide your full name.');
}
if (!is_email($email)) {
    json_fail('Please provide a valid email address.');
}
if ($phone === '') {
    json_fail('Please provide your direct telephone number.');
}

// Generate unambiguous lead reference: JA-2026-XXXX
$leadRef = 'JA-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

$formattedScope = !empty($scope) ? implode(', ', array_map('htmlspecialchars', $scope)) : 'Complete Smart Home Conversion';

$reqText = "Reference: {$leadRef}\n"
    . "Property Type: {$propType}\n"
    . "Size: {$propSize}\n"
    . "City / Area: {$city}\n"
    . "Selected Systems: {$formattedScope}\n"
    . "Configured Estimate: {$estimate}\n"
    . "Preferred Site Survey Date: " . ($surveyDate ?: 'As soon as possible') . "\n"
    . "Client Notes: " . ($notes ?: 'None');

$leadId = 0;
if (DB::tableExists('business_leads')) {
    $leadId = DB::insert('business_leads', [
        'user_id'      => Auth::id() ?: null,
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

// Dispatch email alerts if mailer is active
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
        . "<tr><td><b>Property Size</b></td><td>" . htmlspecialchars($propSize) . "</td></tr>"
        . "<tr style='background:#f7f6f0'><td><b>Automation Modules</b></td><td>" . htmlspecialchars($formattedScope) . "</td></tr>"
        . "<tr><td><b>Configured Estimate</b></td><td><b>" . htmlspecialchars($estimate) . "</b></td></tr>"
        . "<tr style='background:#f7f6f0'><td><b>Preferred Date</b></td><td>" . htmlspecialchars($surveyDate ?: 'Flexible') . "</td></tr>"
        . "<tr><td><b>Client Brief</b></td><td>" . nl2br(htmlspecialchars($notes ?: '—')) . "</td></tr>"
        . "</table>";
    Mailer::send($adminTo, $adminSubject, $adminBody);

    // Confirmation receipt to customer
    $clientSubject = "Your Smart Home Consultation Request · Jollof Automations ({$leadRef})";
    $clientBody = "<div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;max-width:600px;margin:0 auto;color:#1c3028'>"
        . "<div style='background:#173d30;padding:24px 28px;border-radius:12px 12px 0 0'>"
        . "<h1 style='color:#ffffff;font-size:20px;margin:0;letter-spacing:-0.02em'>JOLLOF AUTOMATIONS</h1>"
        . "<div style='color:#c29c4b;font-size:12px;margin-top:4px;letter-spacing:1px;text-transform:uppercase'>A Subsidiary of Jollof Living</div>"
        . "</div>"
        . "<div style='background:#ffffff;padding:28px;border:1px solid #d9dfd4;border-top:none;border-radius:0 0 12px 12px'>"
        . "<h2 style='font-size:18px;color:#173d30;margin-top:0'>Consultation Request Received</h2>"
        . "<p>Dear " . htmlspecialchars($name) . ",</p>"
        . "<p>Thank you for contacting <b>Jollof Automations</b>. We have received your smart home conversion request for your property in <b>" . htmlspecialchars($city) . "</b>.</p>"
        . "<p>Your engineering case reference is <b style='color:#1a6744;font-family:monospace;font-size:15px'>{$leadRef}</b>.</p>"
        . "<div style='background:#f7f6f0;border-left:4px solid #1a6744;padding:14px 18px;border-radius:6px;margin:20px 0;font-size:13.5px'>"
        . "<b>Configured Scope Summary:</b><br>"
        . "• Property: " . htmlspecialchars($propType) . " (" . htmlspecialchars($propSize) . ")<br>"
        . "• Modules: " . htmlspecialchars($formattedScope) . "<br>"
        . "• Estimated Investment: " . htmlspecialchars($estimate) . "<br>"
        . "• Target Survey Date: " . htmlspecialchars($surveyDate ?: 'To be confirmed by engineer') . ""
        . "</div>"
        . "<p style='font-size:13.5px;line-height:1.6;color:#536257'>Our certified systems engineer will contact you shortly to confirm the on-site physical wiring and network topology inspection.</p>"
        . "<p style='font-size:13px;color:#7d7768;margin-top:28px'>Warm regards,<br><b>The Engineering Team</b><br>Jollof Automations · Jollof Living Group</p>"
        . "</div>"
        . "</div>";
    Mailer::send($email, $clientSubject, $clientBody);
}

audit($email, "Smart home survey requested: {$leadRef} · {$propType} ({$city})", 'ok');

json_ok([
    'lead_id'   => $leadId,
    'reference' => $leadRef,
    'estimate'  => $estimate,
], "Thank you, {$name}. Your site survey request ({$leadRef}) has been received. Our systems engineer will reach out to you within 24 hours.");
