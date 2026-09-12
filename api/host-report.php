<?php
/** CSV exports for the host dashboard — scoped to the signed-in host. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

$user = api_user();
$uid = (int) $user['id'];

$reports = [
    'earnings' => [
        ['Reference', 'Property', 'Guest', 'Check-in', 'Check-out', 'Nights',
         'Gross (NGN)', 'Platform fee (NGN)', 'Net to host (NGN)', 'Status'],
        "SELECT b.ref, p.name AS property, b.guest_name, b.checkin, b.checkout, b.nights,
                b.total, b.status
           FROM bookings b JOIN properties p ON p.id = b.property_id
          WHERE p.host_id = ? AND b.status <> 'cancelled'
          ORDER BY b.checkin DESC",
    ],
    'payouts' => [
        ['Reference', 'Property', 'Paid out on', 'Gross (NGN)', 'Net to host (NGN)', 'Escrow'],
        "SELECT b.ref, p.name AS property, b.updated_at, b.total, b.escrow_status
           FROM bookings b JOIN properties p ON p.id = b.property_id
          WHERE p.host_id = ? AND b.escrow_status = 'released'
          ORDER BY b.updated_at DESC",
    ],
];

$key = (string) ($_GET['r'] ?? 'earnings');
if (!isset($reports[$key])) {
    json_fail('Unknown report.', 404);
}
[$headers, $sql] = $reports[$key];
$rows = DB::all($sql, [$uid]);

$take = (float) Repo::setting('host_take_rate', 0.12);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="jollofliving-' . $key . '-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $headers, ',', '"', '\\');

foreach ($rows as $r) {
    $gross = (int) $r['total'];
    $fee = (int) round($gross * $take);
    $net = $gross - $fee;

    if ($key === 'earnings') {
        $line = [$r['ref'], $r['property'], $r['guest_name'], $r['checkin'], $r['checkout'],
                 $r['nights'], $gross, $fee, $net, $r['status']];
    } else {
        $line = [$r['ref'], $r['property'], substr((string) $r['updated_at'], 0, 10),
                 $gross, $net, $r['escrow_status']];
    }
    fputcsv($out, array_map(static fn($v) => $v === null ? '' : (string) $v, $line), ',', '"', '\\');
}
fclose($out);
