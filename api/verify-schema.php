<?php
/**
 * Jollof Living — Database Schema & Migration Verification Tool (Phases 0 - 8)
 *
 * Verifies that all tables, columns, indexes, and migrations for Phases 0 through 8
 * are present, healthy, and sufficient in the database.
 */
declare(strict_types=1);

require_once __DIR__ . '/_api.php';

$phases = [
    'Phase 0: Lockdown & Honesty (WP01-WP03)' => [
        'tables' => [
            'feature_flags' => ['feature_key', 'is_enabled'],
        ],
        'columns' => [
            'users' => ['auth_version'],
        ],
    ],
    'Phase 1: Money Machine & Double-Entry Ledger (WP04-WP07)' => [
        'tables' => [
            'payment_intents'   => ['id', 'user_id', 'booking_id', 'amount_fen', 'currency', 'provider', 'status', 'idempotency_key'],
            'ledger_accounts'   => ['id', 'code', 'name', 'account_type', 'currency', 'balance_fen'],
            'ledger_entries'    => ['id', 'txn_ref', 'account_id', 'entry_type', 'amount_fen', 'currency'],
            'installment_plans' => ['id', 'booking_id', 'user_id', 'total_fen', 'deposit_fen', 'status'],
            'outbox_events'     => ['id', 'event_type', 'payload', 'status', 'attempts'],
        ],
    ],
    'Phase 2: Booking Integrity & Anti-Collision (WP08-WP11)' => [
        'tables' => [
            'night_holds'     => ['id', 'property_id', 'booking_id', 'day', 'status', 'expires_at'],
            'booking_changes' => ['id', 'booking_id', 'initiator_type', 'initiator_id', 'change_kind', 'status'],
        ],
    ],
    'Phase 3: Trust, Identity & Verification (WP12-WP16)' => [
        'tables' => [
            'verify_tokens'  => ['id', 'user_id', 'purpose', 'token_hash', 'code_hash', 'expires_at', 'consumed_at'],
            'kyc_documents'  => ['id', 'user_id', 'kind', 'storage_key', 'mime', 'bytes', 'sha256', 'status'],
            'user_prefs'     => ['id', 'user_id', 'pref_key', 'pref_val'],
            'safety_reports' => ['id', 'reporter_id', 'target_type', 'target_id', 'category', 'status'],
            'user_blocks'    => ['id', 'user_id', 'blocked_user_id'],
        ],
        'columns' => [
            'users' => ['email_verified_at', 'phone_verified_at'],
        ],
    ],
    'Phase 5: Owner Workspace & Co-hosting (WP23-WP26)' => [
        'tables' => [
            'listing_drafts'   => ['id', 'host_id', 'title', 'status', 'draft_payload'],
            'host_invitations' => ['id', 'host_id', 'email', 'role', 'status', 'token_hash'],
            'template_sends'   => ['id', 'template_id', 'booking_id', 'host_id', 'recipient_email', 'status'],
            'service_requests' => ['id', 'host_id', 'property_id', 'service_type', 'status'],
        ],
    ],
    'Phase 6: Back Office Completeness & Audit (WP27)' => [
        'tables' => [
            'cms_blocks'    => ['id', 'block_key', 'locale', 'content', 'version'],
            'fraud_signals' => ['id', 'user_id', 'signal_type', 'severity', 'details', 'status'],
        ],
    ],
    'Phase 7: Support Modules & Live Mediation (WP28-WP31)' => [
        'tables' => [
            'dispute_case_tokens'    => ['id', 'dispute_id', 'user_id', 'role', 'token_hash'],
            'dispute_ratings'        => ['id', 'dispute_id', 'user_id', 'role', 'score'],
            'disputes'               => ['id', 'ref', 'user_id', 'status', 'counterparty_id', 'contact_email'],
            'dispute_events'         => ['id', 'dispute_id', 'actor_role', 'kind', 'body'],
            'chat_sessions'          => ['id', 'ref', 'visitor_email', 'status', 'department_id'],
            'chat_messages'          => ['id', 'session_id', 'sender', 'body'],
            'chat_agents'            => ['id', 'name', 'email', 'agent_role', 'status'],
            'chat_departments'       => ['id', 'slug', 'name', 'is_active'],
            'chat_canned'            => ['id', 'shortcut', 'title', 'body'],
        ],
    ],
    'Phase 8: Platform Reach & Lead Generation (WP32-WP37)' => [
        'tables' => [
            'business_leads'     => ['id', 'company_name', 'contact_name', 'contact_email', 'status'],
            'push_subscriptions' => ['id', 'user_id', 'endpoint', 'p256dh', 'auth'],
        ],
    ],
];

$report = [
    'timestamp' => date('Y-m-d H:i:s T'),
    'database_engine' => DB::isSqlite() ? 'SQLite' : 'MySQL',
    'summary' => [
        'total_phases'     => count($phases),
        'phases_passed'    => 0,
        'total_checks'     => 0,
        'checks_passed'    => 0,
        'all_phases_ready' => false,
    ],
    'phases' => [],
];

foreach ($phases as $phaseName => $spec) {
    $phaseResult = [
        'name'        => $phaseName,
        'status'      => 'READY',
        'table_count' => count($spec['tables'] ?? []),
        'tables'      => [],
        'columns'     => [],
    ];

    // Check tables
    foreach ($spec['tables'] ?? [] as $table => $requiredCols) {
        $report['summary']['total_checks']++;
        $tableExists = DB::tableExists($table);

        if (!$tableExists) {
            $phaseResult['status'] = 'INCOMPLETE';
            $phaseResult['tables'][$table] = [
                'exists'       => false,
                'rows'         => 0,
                'missing_cols' => $requiredCols,
            ];
            continue;
        }

        $report['summary']['checks_passed']++;
        $rowCount = (int) DB::value("SELECT COUNT(*) FROM `{$table}`");
        $missingCols = [];

        foreach ($requiredCols as $col) {
            if (!DB::columnExists($table, $col)) {
                $missingCols[] = $col;
            }
        }

        if (!empty($missingCols)) {
            $phaseResult['status'] = 'INCOMPLETE';
        }

        $phaseResult['tables'][$table] = [
            'exists'       => true,
            'rows'         => $rowCount,
            'missing_cols' => $missingCols,
        ];
    }

    // Check columns
    foreach ($spec['columns'] ?? [] as $table => $cols) {
        foreach ($cols as $col) {
            $report['summary']['total_checks']++;
            $colExists = DB::columnExists($table, $col);
            if ($colExists) {
                $report['summary']['checks_passed']++;
            } else {
                $phaseResult['status'] = 'INCOMPLETE';
            }
            $phaseResult['columns']["{$table}.{$col}"] = $colExists ? 'PRESENT' : 'MISSING';
        }
    }

    if ($phaseResult['status'] === 'READY') {
        $report['summary']['phases_passed']++;
    }

    $report['phases'][] = $phaseResult;
}

$report['summary']['all_phases_ready'] = ($report['summary']['phases_passed'] === $report['summary']['total_phases']);

// Support JSON or clean HTML presentation
$format = input_str('format') ?: (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false ? 'json' : 'html');

if ($format === 'json') {
    json_response($report);
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database Migration & Phase Verification · Jollof Living</title>
  <style>
    body { background:#0d120a; color:#ede8dc; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding:32px 16px; margin:0; }
    .wrap { max-width:960px; margin:0 auto; background:#161c12; border:1px solid rgba(233,198,103,0.25); border-radius:16px; padding:32px; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    h1 { font-family:Georgia, serif; font-size:26px; color:#e9c667; margin:0 0 8px; }
    .meta { font-size:13px; color:#9b9588; margin-bottom:24px; }
    .summary-card { background:#1c2417; border-radius:12px; padding:20px; border:1px solid rgba(255,255,255,0.08); margin-bottom:28px; display:flex; gap:20px; flex-wrap:wrap; align-items:center; }
    .score-badge { font-size:32px; font-weight:700; color:#2ecc71; font-family:Georgia, serif; }
    .score-badge.fail { color:#e74c3c; }
    .phase-block { margin-bottom:24px; border:1px solid rgba(255,255,255,0.08); border-radius:10px; overflow:hidden; }
    .phase-header { background:#1f281a; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; font-weight:600; font-size:15px; }
    .phase-body { padding:14px 18px; background:#161c12; }
    .tbl-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px; margin-top:8px; }
    .tbl-item { background:#0d120a; border:1px solid rgba(255,255,255,0.06); border-radius:6px; padding:10px 12px; font-size:13px; display:flex; justify-content:space-between; align-items:center; }
    .pill { display:inline-block; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700; }
    .pill-ok { background:rgba(46,204,113,0.15); color:#2ecc71; border:1px solid #2ecc71; }
    .pill-fail { background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid #e74c3c; }
    a.btn-gold { display:inline-block; background:#e9c667; color:#12180f; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:700; font-size:14px; margin-top:16px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Database Phase &amp; Schema Verification</h1>
    <div class="meta">Engine: <b><?= e($report['database_engine']) ?></b> · Timestamp: <?= e($report['timestamp']) ?></div>

    <div class="summary-card">
      <div>
        <div class="score-badge <?= $report['summary']['all_phases_ready'] ? '' : 'fail' ?>">
          <?= $report['summary']['phases_passed'] ?> / <?= $report['summary']['total_phases'] ?> Phases Ready
        </div>
        <div style="font-size:13px;color:#9b9588;margin-top:4px">
          <?= $report['summary']['checks_passed'] ?> of <?= $report['summary']['total_checks'] ?> table and column constraints satisfied.
        </div>
      </div>
      <div style="margin-left:auto">
        <?php if ($report['summary']['all_phases_ready']): ?>
          <span class="pill pill-ok" style="font-size:13px;padding:6px 14px">✓ ALL PHASES 0-8 READY</span>
        <?php else: ?>
          <span class="pill pill-fail" style="font-size:13px;padding:6px 14px">⚠ MIGRATION REQUIRED</span>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($report['phases'] as $p): ?>
      <div class="phase-block">
        <div class="phase-header">
          <span><?= e($p['name']) ?></span>
          <span class="pill <?= $p['status'] === 'READY' ? 'pill-ok' : 'pill-fail' ?>"><?= e($p['status']) ?></span>
        </div>
        <div class="phase-body">
          <div style="font-size:12px;color:#9b9588;margin-bottom:6px">Required Tables &amp; Records:</div>
          <div class="tbl-grid">
            <?php foreach ($p['tables'] as $tbl => $info): ?>
              <div class="tbl-item">
                <span><code><?= e($tbl) ?></code></span>
                <?php if ($info['exists'] && empty($info['missing_cols'])): ?>
                  <span class="pill pill-ok"><?= (int) $info['rows'] ?> rows</span>
                <?php elseif ($info['exists']): ?>
                  <span class="pill pill-fail">missing cols</span>
                <?php else: ?>
                  <span class="pill pill-fail">MISSING</span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (!empty($p['columns'])): ?>
            <div style="font-size:12px;color:#9b9588;margin:12px 0 6px">Required Columns:</div>
            <div class="tbl-grid">
              <?php foreach ($p['columns'] as $col => $st): ?>
                <div class="tbl-item">
                  <span><code><?= e($col) ?></code></span>
                  <span class="pill <?= $st === 'PRESENT' ? 'pill-ok' : 'pill-fail' ?>"><?= e($st) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="text-align:center;margin-top:28px">
      <a href="../install/migrate.php" class="btn-gold">Run Migration Runner (install/migrate.php)</a>
    </div>
  </div>
</body>
</html>
