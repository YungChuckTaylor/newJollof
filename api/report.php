<?php
/** CSV exports for the admin reports tab. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_admin();

$reports = [
    'bookings' => [
        'Bookings',
        ['Ref', 'Guest', 'Email', 'Property', 'City', 'Check-in', 'Check-out', 'Nights', 'Guests', 'Total (NGN)', 'Status', 'Escrow', 'Created'],
        'SELECT b.ref, b.guest_name, b.guest_email, p.name AS property, p.city, b.checkin, b.checkout,
                b.nights, b.guests, b.total, b.status, b.escrow_status, b.created_at
         FROM bookings b LEFT JOIN properties p ON p.id = b.property_id ORDER BY b.id DESC',
    ],
    'revenue' => [
        'Revenue by month',
        ['Month', 'Bookings', 'Gross (NGN)'],
        "SELECT SUBSTR(b.created_at, 1, 7) AS month, COUNT(*) AS bookings, SUM(b.total) AS gross
         FROM bookings b WHERE b.status <> 'cancelled' GROUP BY SUBSTR(b.created_at, 1, 7) ORDER BY month",
    ],
    'properties' => [
        'Properties',
        ['ID', 'Name', 'Area', 'City', 'Type', 'Rate (NGN)', 'Guests', 'Rating', 'Reviews', 'Status'],
        'SELECT slug, name, area, city, ptype, price, guests, rating, reviews_count, status FROM properties ORDER BY id',
    ],
    'users' => [
        'Users',
        ['ID', 'Name', 'Email', 'Phone', 'Role', 'Tier', 'Points', 'Host', 'Status', 'Joined'],
        'SELECT id, name, email, phone, role, tier, points, is_host, status, created_at FROM users ORDER BY id',
    ],
    'reviews' => [
        'Reviews',
        ['ID', 'Property', 'Author', 'Rating', 'Status', 'Body', 'Created'],
        'SELECT r.id, p.name AS property, r.author, r.rating, r.status, r.body, r.created_at
         FROM reviews r LEFT JOIN properties p ON p.id = r.property_id ORDER BY r.id DESC',
    ],
    'subscribers' => [
        'Newsletter subscribers',
        ['Email', 'Source', 'Status', 'Joined'],
        'SELECT email, source, status, created_at FROM subscribers ORDER BY id DESC',
    ],
    'hosts' => [
        'Property owners',
        ['ID', 'Name', 'Email', 'Listings', 'Live', 'Gross (NGN)', 'Joined'],
        "SELECT u.id, u.name, u.email,
                (SELECT COUNT(*) FROM properties p WHERE p.host_id = u.id) AS listings,
                (SELECT COUNT(*) FROM properties p WHERE p.host_id = u.id AND p.status = 'live') AS live,
                (SELECT COALESCE(SUM(b.total),0) FROM bookings b
                   JOIN properties p ON p.id = b.property_id
                  WHERE p.host_id = u.id AND b.status <> 'cancelled') AS gross,
                u.created_at
           FROM users u WHERE u.is_host = 1 OR u.role = 'host' ORDER BY u.id",
    ],
    'audit' => [
        'Audit log',
        ['ID', 'Actor', 'Action', 'Level', 'IP', 'Created'],
        'SELECT id, actor, action, level, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 5000',
    ],
];

$key = (string) ($_GET['r'] ?? '');
if (!isset($reports[$key])) {
    json_fail('Unknown report.', 404);
}
[$label, $headers, $sql] = $reports[$key];
$rows = DB::all($sql);

audit((string) (Auth::user()['email'] ?? 'admin'), 'Exported report: ' . $label, 'info');

$filename = 'jollofliving-' . $key . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads the ₦ figures correctly
// the $escape argument is explicit: its default changes in PHP 8.4+
fputcsv($out, $headers, ',', '"', '\\');
foreach ($rows as $r) {
    fputcsv($out, array_map(static fn($v) => $v === null ? '' : (string) $v, array_values($r)), ',', '"', '\\');
}
fclose($out);
