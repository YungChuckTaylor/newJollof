<?php
/**
 * Jollof Automations — Admin Lead Management Portal
 * View and manage incoming site survey requests and smart home consultations from MySQL.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Site Survey Leads & Inquiries · Jollof Automations Management';
$activeNav = 'leads';

// Handle status updates
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $leadId = (int) ($_POST['lead_id'] ?? 0);
    $newStatus = trim((string) ($_POST['status'] ?? 'new'));
    $allowed = ['new', 'contacted', 'survey_scheduled', 'quoted', 'in_progress', 'completed', 'declined'];

    if ($leadId > 0 && in_array($newStatus, $allowed, true)) {
        if (AutoDB::isConnected() && AutoDB::tableExists('automations_leads')) {
            AutoDB::update('automations_leads', ['status' => $newStatus], '`id` = :lid', [':lid' => $leadId]);
            $msg = "Lead #{$leadId} status updated to '{$newStatus}'.";
        }
    }
}

// Fetch leads from database
$leads = AutoRepo::getLeads(100);

require_once __DIR__ . '/includes/header.php';
?>

<div class="wrap" style="padding: 40px 0 80px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:16px;">
    <div>
      <div class="eyebrow">Enterprise Operations</div>
      <h1 style="font-size:32px;font-weight:800;color:var(--forest);margin:4px 0 8px;">
        Site Survey Consultation Leads
      </h1>
      <p style="color:var(--muted);font-size:14px;margin:0;">
        Real-time prospective smart home conversions from private residences, villas, and short-let hospitality fleets.
      </p>
    </div>

    <div style="display:flex;gap:12px;align-items:center;">
      <span class="corp-tag" style="background:#e4efe7;color:var(--green);font-size:13px;padding:8px 14px">
        MySQL Connected: <?= AutoDB::isConnected() ? 'ONLINE ✓' : 'STANDALONE LOG MODE' ?>
      </span>
      <a href="index.php#survey" class="btn-corp btn-corp-primary" style="padding:10px 16px;font-size:13px">
        + New Booking
      </a>
    </div>
  </div>

  <?php if ($msg): ?>
    <div style="background:#e8f4ed;border:1px solid #b7dac4;color:var(--green);padding:14px 20px;border-radius:8px;margin-bottom:24px;font-size:14px;font-weight:600;">
      ✓ <?= ja_e($msg) ?>
    </div>
  <?php endif; ?>

  <div style="background:#ffffff;border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.03);">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13.5px;">
        <thead>
          <tr style="background:var(--paper);border-bottom:1px solid var(--line);color:var(--forest);font-family:'Space Grotesk',sans-serif;font-weight:700;">
            <th style="padding:16px 20px;">Reference</th>
            <th style="padding:16px 20px;">Client Contact</th>
            <th style="padding:16px 20px;">Property &amp; Location</th>
            <th style="padding:16px 20px;">Estimated Budget</th>
            <th style="padding:16px 20px;">Survey Date</th>
            <th style="padding:16px 20px;">Status</th>
            <th style="padding:16px 20px;text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($leads)): ?>
            <tr>
              <td colspan="7" style="padding:48px 20px;text-align:center;color:var(--muted)">
                <div style="font-size:32px;margin-bottom:12px">⚡</div>
                <b>No consultation leads registered yet.</b>
                <p style="margin:4px 0 0;font-size:13px">Submissions from the <a href="index.php#survey" style="color:var(--green);text-decoration:underline">Site Survey Form</a> will appear here automatically.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($leads as $lead): 
              $scopeItems = [];
              if (!empty($lead['scope_json'])) {
                  $scopeItems = is_array($lead['scope_json']) ? $lead['scope_json'] : (json_decode($lead['scope_json'], true) ?: []);
              } elseif (!empty($lead['scope'])) {
                  $scopeItems = (array) $lead['scope'];
              }
            ?>
              <tr style="border-bottom:1px solid var(--line);vertical-align:top;">
                <td style="padding:18px 20px;font-family:'Space Grotesk',sans-serif;font-weight:700;color:var(--forest)">
                  <?= ja_e($lead['reference']) ?>
                  <div style="font-size:11px;color:var(--muted);font-weight:400"><?= date('M j, Y g:ia', strtotime($lead['created_at'])) ?></div>
                </td>

                <td style="padding:18px 20px;">
                  <b style="color:var(--forest);font-size:14px"><?= ja_e($lead['full_name'] ?? $lead['name'] ?? '—') ?></b>
                  <div style="font-size:12px;color:var(--muted);margin-top:2px">
                    <a href="mailto:<?= ja_e($lead['email']) ?>" style="color:inherit;text-decoration:underline"><?= ja_e($lead['email']) ?></a>
                  </div>
                  <div style="font-size:12px;color:var(--muted);margin-top:2px">
                    <a href="tel:<?= ja_e($lead['phone']) ?>" style="color:var(--green);font-weight:600"><?= ja_e($lead['phone']) ?></a>
                  </div>
                </td>

                <td style="padding:18px 20px;">
                  <div style="font-weight:600;color:var(--ink)"><?= ja_e($lead['property_type']) ?></div>
                  <div style="font-size:12px;color:var(--muted)"><?= ja_e($lead['city']) ?></div>
                  <?php if (!empty($scopeItems)): ?>
                    <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">
                      <?php foreach (array_slice($scopeItems, 0, 3) as $mod): ?>
                        <span style="font-size:10.5px;background:var(--paper);border:1px solid var(--line);padding:2px 6px;border-radius:4px;color:var(--muted)">
                          <?= ja_e($mod) ?>
                        </span>
                      <?php endforeach; ?>
                      <?php if (count($scopeItems) > 3): ?>
                        <span style="font-size:10.5px;color:var(--muted)">+<?= count($scopeItems) - 3 ?> more</span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>

                <td style="padding:18px 20px;font-family:'Space Grotesk',sans-serif;font-weight:700;color:var(--forest);">
                  <?= ja_e($lead['estimated_budget']) ?>
                </td>

                <td style="padding:18px 20px;font-size:13px;color:var(--ink)">
                  <?= !empty($lead['preferred_date']) ? date('M j, Y', strtotime($lead['preferred_date'])) : '<span style="color:var(--muted)">Flexible</span>' ?>
                </td>

                <td style="padding:18px 20px;">
                  <?php
                    $st = $lead['status'] ?? 'new';
                    $badgeBg = '#e8f4ed';
                    $badgeColor = '#1a6744';
                    if ($st === 'new') { $badgeBg = '#fdf4db'; $badgeColor = '#a87818'; }
                    elseif ($st === 'survey_scheduled') { $badgeBg = '#e3f2fd'; $badgeColor = '#0d47a1'; }
                    elseif ($st === 'completed') { $badgeBg = '#e8f5e9'; $badgeColor = '#2e7d32'; }
                    elseif ($st === 'declined') { $badgeBg = '#ffebee'; $badgeColor = '#c62828'; }
                  ?>
                  <span style="display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $badgeBg ?>;color:<?= $badgeColor ?>;text-transform:uppercase;letter-spacing:0.04em;">
                    <?= ja_e(str_replace('_', ' ', $st)) ?>
                  </span>
                </td>

                <td style="padding:18px 20px;text-align:right;">
                  <form method="POST" style="display:inline-flex;gap:6px;align-items:center;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="lead_id" value="<?= (int)($lead['id'] ?? 0) ?>">
                    <select name="status" class="form-control" style="font-size:12px;padding:4px 8px;height:auto;" onchange="this.form.submit()">
                      <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'survey_scheduled' => 'Survey Scheduled', 'quoted' => 'Quoted', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'declined' => 'Declined'] as $sVal => $sLbl): ?>
                        <option value="<?= $sVal ?>" <?= ($lead['status'] ?? 'new') === $sVal ? 'selected' : '' ?>><?= $sLbl ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
