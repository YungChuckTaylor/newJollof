<?php
/**
 * Printable invoice for a reservation.
 *   GET  ?ref=JL-2026-1234        → HTML invoice (browser "Print → Save as PDF")
 *   POST {ref, action:"email"}    → emails the same invoice to the guest
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPost) {
    require_csrf();
}

$user = api_user();
$ref = input_str('ref');

$b = $ref !== '' ? BookingService::find($ref) : null;
if (!$b) {
    $isPost ? json_fail('Reservation not found.', 404) : exit('Reservation not found.');
}
if ((int) $b['user_id'] !== (int) $user['id'] && !Auth::isAdmin()) {
    $isPost ? json_fail('That reservation is not yours.', 403) : exit('Not authorised.');
}

$rates = Repo::rates();
$nights = (int) $b['nights'];

/* Replay the frozen quote captured when the booking was made. Older rows
   without one fall back to the stored totals — never to a re-calculation,
   which could disagree with what the guest actually paid. */
$q = json_decode((string) ($b['breakdown'] ?? ''), true);

$lines = [];
if (is_array($q) && isset($q['base'])) {
    $lines[] = [
        'label' => money((int) $q['nightly']) . ' × ' . $nights . ' night' . ($nights === 1 ? '' : 's'),
        'qty'   => $nights,
        'rate'  => money((int) $q['nightly']),
        'amount'=> (int) $q['base'],
    ];
    if ((int) $q['lengthDiscount'] > 0) {
        $lines[] = ['label' => 'Long-stay discount (' . round(((float) $q['lengthDiscRate']) * 100) . '%)',
                    'qty' => 1, 'rate' => '—', 'amount' => -(int) $q['lengthDiscount']];
    }
    foreach ((array) $q['addons'] as $a) {
        $lines[] = ['label' => (string) $a['name'], 'qty' => 1, 'rate' => money((int) $a['amount']), 'amount' => (int) $a['amount']];
    }
    $lines[] = ['label' => 'Cleaning fee', 'qty' => 1, 'rate' => money((int) $q['cleaning']), 'amount' => (int) $q['cleaning']];
    $lines[] = ['label' => 'Service fee (' . round(((float) $rates['service']) * 100, 1) . '%)', 'qty' => 1, 'rate' => '—', 'amount' => (int) $q['service']];
    $lines[] = ['label' => 'VAT (' . round(((float) $rates['vat']) * 100, 1) . '%)', 'qty' => 1, 'rate' => '—', 'amount' => (int) $q['vat']];
    if ((int) $q['promoDiscount'] > 0) {
        $lines[] = ['label' => 'Promotion' . ($q['promoCode'] ? ' (' . $q['promoCode'] . ')' : ''),
                    'qty' => 1, 'rate' => '—', 'amount' => -(int) $q['promoDiscount']];
    }
    if ((int) $q['gift'] > 0) {
        $lines[] = ['label' => 'Gift card credit', 'qty' => 1, 'rate' => '—', 'amount' => -(int) $q['gift']];
    }
} else {
    $lines[] = ['label' => 'Accommodation (' . $nights . ' night' . ($nights === 1 ? '' : 's') . ')',
                'qty' => $nights, 'rate' => money((int) $b['nightly']), 'amount' => (int) $b['subtotal']];
    if ((int) $b['discount'] > 0) {
        $lines[] = ['label' => 'Discount', 'qty' => 1, 'rate' => '—', 'amount' => -(int) $b['discount']];
    }
    $lines[] = ['label' => 'Cleaning & service fees', 'qty' => 1, 'rate' => '—', 'amount' => (int) $b['fees']];
    $lines[] = ['label' => 'VAT', 'qty' => 1, 'rate' => '—', 'amount' => (int) $b['taxes']];
}

$payName = 'Card';
foreach (Repo::payMethods() as $m) {
    if ($m['id'] === $b['pay_method']) {
        $payName = $m['name'];
    }
}

ob_start();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<title>Invoice <?= e($b['ref']) ?> · Jollof Living</title>
<style>
  @page { margin: 18mm; }
  body{font:14px/1.6 -apple-system,"Segoe UI",system-ui,sans-serif;color:#1c1a15;max-width:760px;margin:32px auto;padding:0 22px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}
  h1{font-size:24px;margin:0 0 2px;letter-spacing:-.02em}
  .k{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#8d8674}
  .muted{color:#6f6959}
  hr{border:0;border-top:1px solid #e4ddcc;margin:22px 0}
  .cols{display:flex;gap:40px;margin-bottom:22px}
  table{width:100%;border-collapse:collapse;font-size:13.5px}
  th{text-align:left;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#8d8674;border-bottom:1px solid #e4ddcc;padding:8px 6px}
  td{padding:9px 6px;border-bottom:1px solid #f0ebdd}
  td.r,th.r{text-align:right}
  .total td{border-top:2px solid #1c1a15;border-bottom:0;font-weight:700;font-size:16px;padding-top:12px}
  .pill{display:inline-block;padding:3px 10px;border-radius:99px;background:#f3eee0;font-size:12px}
  .foot{margin-top:26px;font-size:12px;color:#8d8674}
  @media print{ .noprint{display:none} }
</style></head><body>
<div class="top">
  <div><h1>Jollof Living</h1><div class="muted">Luxury Living, African Soul</div></div>
  <div style="text-align:right">
    <div class="k">Invoice</div>
    <div style="font-size:18px;font-weight:700"><?= e($b['ref']) ?></div>
    <div class="muted"><?= e(date('j F Y', strtotime((string) $b['created_at']))) ?></div>
    <div class="pill" style="margin-top:6px"><?= e(ucfirst((string) $b['status'])) ?></div>
  </div>
</div>
<hr>
<div class="cols">
  <div style="flex:1"><div class="k">Billed to</div>
    <?= e((string) $b['guest_name']) ?><br>
    <span class="muted"><?= e((string) $b['guest_email']) ?><?= $b['guest_phone'] ? '<br>' . e((string) $b['guest_phone']) : '' ?></span>
  </div>
  <div style="flex:1"><div class="k">Stay</div>
    <?= e((string) $b['property_name']) ?><br>
    <span class="muted"><?= e((string) $b['area']) ?>, <?= e((string) $b['city']) ?><br>
    <?= e(date('j M Y', strtotime((string) $b['checkin']))) ?> → <?= e(date('j M Y', strtotime((string) $b['checkout']))) ?>
    · <?= (int) $b['guests'] ?> guest<?= (int) $b['guests'] === 1 ? '' : 's' ?></span>
  </div>
</div>
<table>
  <thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th class="r">Amount</th></tr></thead>
  <tbody>
    <?php foreach ($lines as $l): ?>
      <tr><td><?= e($l['label']) ?></td><td><?= (int) $l['qty'] ?></td><td><?= e($l['rate']) ?></td>
          <td class="r"><?= ((int) $l['amount'] < 0 ? '−' : '') . e(money(abs((int) $l['amount']))) ?></td></tr>
    <?php endforeach; ?>
    <tr class="total"><td colspan="3">Total<?= (int) $b['split_payment'] === 1 ? ' (split: 50% now)' : '' ?> · paid by <?= e($payName) ?></td>
        <td class="r"><?= e(money((int) $b['total'])) ?></td></tr>
  </tbody>
</table>
<p class="foot">
  VAT computed at <?= round(((float) $rates['vat']) * 100, 1) ?>% · withholding tax applies to host payouts ·
  payment held in escrow until check-in is confirmed (currently: <?= e((string) $b['escrow_status']) ?>).<br>
  <?= e((string) Repo::setting('site_name', 'Jollof Living')) ?> ·
  <?= e((string) Repo::setting('site_domain', 'www.jollofliving.com')) ?> ·
  <?= e((string) Repo::setting('contact_email', '')) ?>
</p>
<p class="noprint"><button onclick="window.print()" style="padding:10px 18px;border:0;border-radius:8px;background:#c9a227;font-weight:700;cursor:pointer">Print / Save as PDF</button></p>
</body></html>
<?php
$html = (string) ob_get_clean();

if ($isPost && input_str('action') === 'email') {
    $sent = Mailer::send((string) $b['guest_email'], 'Your Jollof Living invoice · ' . $b['ref'], $html);
    audit((string) $user['email'], 'Invoice emailed for ' . $b['ref'], 'info');
    json_response([
        'ok'      => true,
        'message' => $sent ? 'Invoice sent to ' . $b['guest_email'] : 'Email is disabled on this site — use the printable invoice instead.',
    ]);
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
