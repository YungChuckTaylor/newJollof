<?php
/**
 * Jollof Living — Corporate & Business Leads Endpoint (WP36)
 *
 * Captures demo requests and corporate account inquiries into business_leads.
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

$action = input_str('action', 'demo_request');

if ($action === 'demo_request') {
    $company = trim(input_str('company'));
    $name    = trim(input_str('name'));
    $email   = strtolower(trim(input_str('email')));
    $phone   = trim(input_str('phone'));
    $size    = input_str('size', '10-50');
    $city    = input_str('city', 'Lagos');
    $needs   = trim(input_str('needs'));

    if ($company === '') json_fail('Please provide your company name.');
    if ($name === '')    json_fail('Please provide a contact person.');
    if (!is_email($email)) json_fail('Please enter a valid work email.');

    $user = Auth::user();
    $leadId = 0;

    if (DB::tableExists('business_leads')) {
        $leadId = DB::insert('business_leads', [
            'user_id'       => $user ? (int) $user['id'] : null,
            'company_name'  => mb_substr($company, 0, 160),
            'contact_name'  => mb_substr($name, 0, 120),
            'contact_email' => mb_substr($email, 0, 190),
            'contact_phone' => mb_substr($phone, 0, 40) ?: null,
            'team_size'     => mb_substr($size, 0, 32),
            'city'          => mb_substr($city, 0, 80),
            'requirements'  => mb_substr($needs, 0, 2000) ?: null,
            'status'        => 'new',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        audit($email, "Business inquiry submitted for {$company}", 'info');
    }

    json_ok(['lead_id' => $leadId], 'Thank you — our Corporate Accounts team will reach out within 24 hours ✨');
}

json_fail('Unknown business action.');
