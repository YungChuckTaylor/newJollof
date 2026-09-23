<?php
/**
 * Jollof Living — migration runner.
 *
 * Adds the Dispute Resolution Centre and Live Chat tables to an existing
 * database. Every statement is idempotent, so the page can be run as many
 * times as you like. Visit:
 *
 *     https://yourdomain.com/install/migrate.php
 *
 * The raw SQL lives in install/schema/ for anyone who prefers phpMyAdmin.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/includes/config.php';

if (!is_file($configFile)) {
    // No framework yet at this point, so the notice is rendered inline.
    http_response_code(500);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configuration missing · Jollof Living</title></head>
    <body style="font:15px/1.6 system-ui,sans-serif;padding:40px;max-width:640px;margin:auto">
    <h1 style="font-size:22px">Configuration missing</h1>
    <p>Copy <code>includes/config.sample.php</code> to <code>includes/config.php</code>,
    fill in your cPanel MySQL details, then reload this page.</p>
    </body></html><?php
    exit;
}

require_once $root . '/includes/bootstrap.php';
require_once JL_INC . '/disputes.php';
require_once JL_INC . '/livechat.php';

$admin = Auth::user();
$isAdmin = $admin !== null && ($admin['role'] ?? '') === 'admin' && !empty($_SESSION['admin']);

$migrationFile = __DIR__ . '/schema/2026_09_12_disputes_and_live_chat.sql';
$ran = false;
$steps = [];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $isAdmin && csrf_check()) {
    try {
        // Make sure the seed tables that rely on INSERT IGNORE still have the
        // unique key that makes it idempotent — the first release of this
        // migration shipped chat_canned/chat_agents without one, so a re-run
        // would have duplicated those rows. Runs before the SQL on purpose.
        foreach (ensure_seed_keys() as $line) {
            $steps[] = $line;
        }

        // Phase-0 hardening (WP01/WP03): the session-epoch column and the
        // capability-flag table. These run through PHP on purpose — raw
        // ALTER TABLE is not idempotent in either dialect, and this page
        // must survive being pressed twice.
        foreach (ensure_phase0_hardening() as $line) {
            $steps[] = $line;
        }

        // Phase-1 money machine (WP04-WP07): payment_intents, ledger, installment_plans, outbox
        foreach (ensure_phase1_money_machine() as $line) {
            $steps[] = $line;
        }

        // Phase-2 booking integrity (WP08-WP11): night_holds, booking_changes
        foreach (ensure_phase2_booking_integrity() as $line) {
            $steps[] = $line;
        }

        // Phase-3 trust & identity (WP12-WP16): verify_tokens, kyc_documents, user_prefs, safety
        foreach (ensure_phase3_trust_identity() as $line) {
            $steps[] = $line;
        }

        // Phase-5 owner workspace (WP23-WP26): listing_drafts, host_invitations, templates, services
        foreach (ensure_phase5_owner_workspace() as $line) {
            $steps[] = $line;
        }

        $count = run_migration($migrationFile);
        $steps[] = $count . ' SQL statements executed (tables, indexes and seed rows).';

        // Anything the SQL seed could not cover on a database that already has
        // users: make sure the administrator is also a chat agent, and that the
        // chat settings exist even on an older snapshot.
        $seeded = seed_defaults();
        foreach ($seeded as $line) {
            $steps[] = $line;
        }

        audit((string) ($admin['email'] ?? 'admin'), 'Ran migration 2026_09_12 (disputes + live chat)', 'info');
        Repo::flush();
        $ran = true;
    } catch (Throwable $ex) {
        $errors[] = $ex->getMessage();
    }
}

/* ------------------------------------------------------------------ logic */

/** Execute a .sql file statement by statement. Returns the statement count. */
function run_migration(string $path): int
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing SQL file: ' . basename($path));
    }
    $sql = (string) file_get_contents($path);
    $pdo = DB::pdo();
    $isSqlite = DB::isSqlite();
    $n = 0;
    foreach (split_sql($sql) as $statement) {
        if ($isSqlite) {
            $statement = sqlite_translate($statement);
            if ($statement === '') {
                continue;
            }
        }
        if (preg_match('~^SET\s+~i', $statement)) {
            continue; // SET NAMES … is harmless: PDO already negotiates the charset
        }
        $pdo->exec($statement);
        $n++;
    }
    return $n;
}

/**
 * Idempotent data seeding that needs PHP logic (counting, coalescing).
 *
 * @return string[] human-readable notes
 */
function seed_defaults(): array
{
    $notes = [];

    // 1. Chat settings — inserted only when absent so an operator's edits stick.
    $defaults = [
        'chat_enabled'        => '1',
        'chat_welcome'        => 'Welcome to Jollof Living 👋 Chat with our team about stays, bookings, payments or hosting. A specialist replies in about a minute.',
        'chat_offline'        => 'Our team is offline right now. Leave your question here and we will reply by email — or ask Jollof, our AI concierge, for an instant answer.',
        'chat_routing'        => 'fewest',
        'chat_max_queue'      => '25',
        'chat_auto_close'     => '30',
        'chat_rating_enabled' => '1',
        'chat_hours'          => '24/7 support · median first reply under 3 minutes',
        'chat_transcript'     => '1',
    ];
    $added = 0;
    foreach ($defaults as $key => $value) {
        if (DB::value('SELECT 1 FROM settings WHERE skey = ?', [$key])) {
            continue;
        }
        DB::insert('settings', ['skey' => $key, 'svalue' => $value]);
        $added++;
    }
    if ($added) {
        $notes[] = $added . ' live-chat settings written.';
    }
    Repo::flush();

    // 2. Nothing to chat about without a queue: keep the seeded departments.
    $depts = (int) DB::value('SELECT COUNT(*) FROM chat_departments', [], 0);
    if ($depts === 0) {
        foreach ([
            ['guest-support', 'Guest support', 'Day-to-day questions from guests and travellers.', 'fewest', '24/7', 0],
            ['reservations', 'Reservations desk', 'New bookings, modifications and availability.', 'round_robin', '08:00 – 22:00 WAT', 1],
            ['host-support', 'Host support', 'Help for property owners on listings, calendar and payouts.', 'fewest', '08:00 – 20:00 WAT', 2],
            ['payments', 'Payments & billing', 'Invoices, escrow, refunds and payment issues.', 'fewest', '08:00 – 20:00 WAT', 3],
            ['trust-safety', 'Trust & safety', 'Disputes, safety incidents and account security.', 'manual', '24/7', 4],
        ] as [$slug, $name, $desc, $routing, $hours, $order]) {
            DB::insert('chat_departments', [
                'slug' => $slug, 'name' => $name, 'description' => $desc,
                'routing' => $routing, 'business_hours' => $hours, 'sort_order' => $order,
                'welcome_message' => 'Welcome to Jollof Living — an agent will be with you shortly.',
            ]);
        }
        $notes[] = 'Seeded 5 chat queues.';
    }

    // 3. The administrator is always a chat supervisor, so the console has an owner.
    $admins = DB::all("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY id");
    $linked = 0;
    foreach ($admins as $a) {
        if (DB::value('SELECT 1 FROM chat_agents WHERE user_id = ? OR email = ?', [(int) $a['id'], $a['email']])) {
            continue;
        }
        DB::insert('chat_agents', [
            'user_id'     => (int) $a['id'],
            'name'        => $a['name'],
            'email'       => $a['email'],
            'agent_role'  => 'supervisor',
            'status'      => 'offline',
            'max_chats'   => 5,
            'is_active'   => 1,
            'signature'   => 'Jollof Living support',
        ]);
        $linked++;
    }
    if ($linked) {
        $notes[] = $linked . ' administrator account(s) linked as chat supervisors.';
    }

    // 4. Dispute categories (in case an older, partially-migrated database
    //    predates the seed rows).
    if ((int) DB::value('SELECT COUNT(*) FROM dispute_categories', [], 0) === 0) {
        foreach (DisputeService::defaultCategories() as $c) {
            DB::insert('dispute_categories', $c);
        }
        $notes[] = 'Seeded the dispute category list.';
    }

    return $notes;
}

/**
 * Phase-0 hardening schema (WP01 + WP03).
 *
 *  • users.auth_version — the session epoch every login stamps and every
 *    request re-checks, so suspension, agent pause and password changes kill
 *    live sessions instantly instead of only hiding buttons;
 *  • feature_flags — the capability ledger behind the claims gate: any UI
 *    statement about an outside service (2FA, document vault, calendar sync,
 *    keyless entry, live payments…) may only render while its flag is on.
 *    Everything seeds OFF.
 *
 * Guarded statement by statement, safe to run on any number of occasions,
 * on MySQL and on the SQLite development driver alike.
 *
 * @return string[] notes for the operator
 */
function ensure_phase0_hardening(): array
{
    $lines = [];
    try {
        if (!DB::tableExists('users')) {
            $lines[] = 'users table missing — core schema first; auth_version skipped.';
        } elseif (DB::columnExists('users', 'auth_version')) {
            $lines[] = 'users.auth_version already in place.';
        } else {
            DB::run('ALTER TABLE users ADD COLUMN auth_version INT NOT NULL DEFAULT 0');
            $lines[] = 'users.auth_version added — sessions now end when the account changes.';
        }
    } catch (Throwable $e) {
        $lines[] = 'users.auth_version could not be applied: ' . $e->getMessage();
    }
    try {
        if (!DB::tableExists('feature_flags')) {
            DB::run('CREATE TABLE IF NOT EXISTS feature_flags (
                flag_key   VARCHAR(64) NOT NULL PRIMARY KEY,
                enabled    TINYINT(1)  NOT NULL DEFAULT 0,
                note       VARCHAR(255) NULL,
                updated_at DATETIME NULL
            )');
            $lines[] = 'feature_flags created.';
        }
        $flags = [
            ['account.2fa',         'Two-factor sign-in for member accounts — needs a working OTP channel (WP08).'],
            ['account.documents',   'Document vault for host KYC uploads — needs storage + a review queue (WP08).'],
            ['account.data_rights', 'Self-service NDPR export/erase — needs the erasure worker (WP08).'],
            ['loyalty.redeem',      'Spending points against bookings — needs the rewards ledger (WP07).'],
            ['channels.google_sync','Google Calendar availability sync — needs live OAuth credentials (WP15).'],
            ['channels.airbnb_sync','OTA availability sync — needs partner API keys (WP15).'],
            ['smartlock.sync',      'Keyless entry codes issued to locks — needs the lock integration (WP18).'],
            ['payments.live_mode',  'Charging and payouts through a live gateway — needs webhook verification (WP04).'],
        ];
        $added = 0;
        foreach ($flags as [$key, $note]) {
            if (!DB::value('SELECT 1 FROM feature_flags WHERE flag_key = ?', [$key])) {
                DB::insert('feature_flags', [
                    'flag_key'   => $key,
                    'enabled'    => 0,
                    'note'       => $note,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $added++;
            }
        }
        if ($added) {
            $lines[] = 'feature_flags seeded: ' . $added . ' capability flag(s), all switched off — a feature may not advertise itself before its service is proven.';
        } else {
            $lines[] = 'feature_flags already seeded.';
        }
        if (class_exists('Repo')) {
            Repo::flush();
        }
    } catch (Throwable $e) {
        $lines[] = 'feature_flags could not be prepared: ' . $e->getMessage();
    }
    return $lines;
}

/**
 * Keys the seed rows depend on, plus a tidy-up of any duplicates an earlier run
 * of the migration may already have created.
 *
 * @return string[] notes for the operator
 */

/**
 * Phase-1 money machine schema (WP04-WP07).
 *
 *  • payment_intents — asynchronous, idempotent gateway charge tracker;
 *  • ledger_accounts / ledger_entries — immutable double-entry bookkeeping;
 *  • installment_plans — multi-tranche split payment tracking;
 *  • outbox_events — durable asynchronous event bus for retries and webhooks.
 *
 * @return string[]
 */
function ensure_phase1_money_machine(): array
{
    $lines = [];
    $isSqlite = DB::isSqlite();

    try {
        if (!DB::tableExists('payment_intents')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS payment_intents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    booking_id INTEGER NULL,
                    user_id INTEGER NULL,
                    kind VARCHAR(32) NOT NULL DEFAULT "booking",
                    amount_fen INTEGER NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT "NGN",
                    provider VARCHAR(32) NOT NULL DEFAULT "sandbox",
                    provider_ref VARCHAR(191) NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "initiated",
                    idempotency_key CHAR(64) NOT NULL UNIQUE,
                    payload_hash CHAR(64) NULL,
                    metadata TEXT NULL,
                    verified_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS payment_intents (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    user_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    kind VARCHAR(32) NOT NULL DEFAULT "booking",
                    amount_fen BIGINT(20) NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT "NGN",
                    provider VARCHAR(32) NOT NULL DEFAULT "sandbox",
                    provider_ref VARCHAR(191) DEFAULT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "initiated",
                    idempotency_key CHAR(64) NOT NULL,
                    payload_hash CHAR(64) DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    verified_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_intent_idem (idempotency_key),
                    KEY ix_intent_booking (booking_id),
                    KEY ix_intent_user (user_id),
                    KEY ix_intent_provref (provider, provider_ref),
                    KEY ix_intent_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'payment_intents created (WP04).';
        } else {
            $lines[] = 'payment_intents already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'payment_intents setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('ledger_accounts')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS ledger_accounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    key_name VARCHAR(64) NOT NULL UNIQUE,
                    name VARCHAR(128) NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT "NGN",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS ledger_accounts (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    key_name VARCHAR(64) NOT NULL,
                    name VARCHAR(128) NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT "NGN",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_ledger_acct_key (key_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'ledger_accounts created (WP05).';
        }

        // Seed core ledger accounts
        $accts = [
            ['guest_cash',       'Guest Cash Inflow'],
            ['escrow',           'Guest Escrow Liability'],
            ['host_earnings',    'Host Payable'],
            ['platform_fee',     'Platform Revenue'],
            ['gift_liability',   'Gift Card Liability'],
            ['loyalty_redeemed', 'Loyalty Liability'],
            ['refund_out',       'Refund Disbursed Clearing'],
        ];
        $seededAccts = 0;
        foreach ($accts as [$k, $n]) {
            if (!DB::value('SELECT 1 FROM ledger_accounts WHERE key_name = ?', [$k])) {
                DB::insert('ledger_accounts', ['key_name' => $k, 'name' => $n, 'currency' => 'NGN']);
                $seededAccts++;
            }
        }
        if ($seededAccts > 0) {
            $lines[] = 'Seeded ' . $seededAccts . ' standard chart of accounts into ledger_accounts.';
        }

        if (!DB::tableExists('ledger_entries')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS ledger_entries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_key VARCHAR(64) NOT NULL,
                    booking_id INTEGER NULL,
                    user_id INTEGER NULL,
                    direction VARCHAR(10) NOT NULL,
                    amount_fen INTEGER NOT NULL,
                    ref_type VARCHAR(32) NOT NULL,
                    ref_id VARCHAR(64) NOT NULL,
                    idempotency_key CHAR(64) NOT NULL UNIQUE,
                    reverses_entry_id INTEGER NULL,
                    narration VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS ledger_entries (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    account_key VARCHAR(64) NOT NULL,
                    booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    user_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    direction VARCHAR(10) NOT NULL,
                    amount_fen BIGINT(20) NOT NULL,
                    ref_type VARCHAR(32) NOT NULL,
                    ref_id VARCHAR(64) NOT NULL,
                    idempotency_key CHAR(64) NOT NULL,
                    reverses_entry_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    narration VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_ledger_idem (idempotency_key),
                    KEY ix_ledger_acct (account_key),
                    KEY ix_ledger_booking (booking_id),
                    KEY ix_ledger_user (user_id),
                    KEY ix_ledger_ref (ref_type, ref_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'ledger_entries created (WP05 double-entry escrow ledger).';
        } else {
            $lines[] = 'ledger_entries already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'ledger setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('installment_plans')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS installment_plans (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    booking_id INTEGER NOT NULL,
                    tranche_index INTEGER NOT NULL DEFAULT 1,
                    total_tranches INTEGER NOT NULL DEFAULT 2,
                    due_date DATE NOT NULL,
                    amount_fen INTEGER NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "pending",
                    payment_intent_id INTEGER NULL,
                    paid_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS installment_plans (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    booking_id BIGINT(20) UNSIGNED NOT NULL,
                    tranche_index INT(11) NOT NULL DEFAULT 1,
                    total_tranches INT(11) NOT NULL DEFAULT 2,
                    due_date DATE NOT NULL,
                    amount_fen BIGINT(20) NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "pending",
                    payment_intent_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    paid_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_inst_booking_tranche (booking_id, tranche_index),
                    KEY ix_inst_booking (booking_id),
                    KEY ix_inst_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'installment_plans created (WP06).';
        }
    } catch (Throwable $e) {
        $lines[] = 'installment_plans setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('outbox_events')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS outbox_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_type VARCHAR(64) NOT NULL,
                    payload TEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT "pending",
                    attempts INTEGER NOT NULL DEFAULT 0,
                    run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    lease_until DATETIME NULL,
                    dedupe_key VARCHAR(128) NOT NULL UNIQUE,
                    last_error TEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS outbox_events (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(64) NOT NULL,
                    payload TEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT "pending",
                    attempts INT(11) NOT NULL DEFAULT 0,
                    run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    lease_until DATETIME DEFAULT NULL,
                    dedupe_key VARCHAR(128) NOT NULL,
                    last_error TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_outbox_dedupe (dedupe_key),
                    KEY ix_outbox_status_run (status, run_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'outbox_events created (WP04/WP05 asynchronous bus).';
        }
    } catch (Throwable $e) {
        $lines[] = 'outbox_events setup error: ' . $e->getMessage();
    }

    return $lines;
}



/**
 * Phase-2 booking integrity schema (WP08-WP11).
 *
 *  • night_holds — hard unique concurrency lock on (property_id, stay_date, active);
 *  • booking_changes — formal modification workflow with delta quotes.
 *
 * @return string[]
 */
function ensure_phase2_booking_integrity(): array
{
    $lines = [];
    $isSqlite = DB::isSqlite();

    try {
        if (!DB::tableExists('night_holds')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS night_holds (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    property_id INTEGER NOT NULL,
                    stay_date DATE NOT NULL,
                    booking_id INTEGER NULL,
                    status VARCHAR(20) NOT NULL DEFAULT "active",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (property_id, stay_date, status)
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS night_holds (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    property_id INT(10) UNSIGNED NOT NULL,
                    stay_date DATE NOT NULL,
                    booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT "active",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_night_prop_date (property_id, stay_date, status),
                    KEY ix_night_booking (booking_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'night_holds created (WP09 hard concurrency locks).';
        } else {
            $lines[] = 'night_holds already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'night_holds setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('booking_changes')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS booking_changes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    booking_id INTEGER NOT NULL,
                    proposed_checkin DATE NOT NULL,
                    proposed_checkout DATE NOT NULL,
                    proposed_guests INTEGER NOT NULL,
                    delta_quote TEXT NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "pending_host",
                    note TEXT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS booking_changes (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    booking_id BIGINT(20) UNSIGNED NOT NULL,
                    proposed_checkin DATE NOT NULL,
                    proposed_checkout DATE NOT NULL,
                    proposed_guests INT(11) NOT NULL,
                    delta_quote TEXT NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "pending_host",
                    note TEXT DEFAULT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY ix_change_booking (booking_id),
                    KEY ix_change_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'booking_changes created (WP10 modification workflow).';
        } else {
            $lines[] = 'booking_changes already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'booking_changes setup error: ' . $e->getMessage();
    }

    return $lines;
}



/**
 * Phase-3 trust & identity schema (WP12-WP16).
 *
 *  • verify_tokens — email/phone challenges and password recovery;
 *  • kyc_documents — secure multi-kind KYC document vault and reviewer workflow;
 *  • user_prefs — typed user preference persistence;
 *  • safety_reports & user_blocks — community safety, abuse reporting and 2-way block engine.
 *
 * @return string[]
 */
function ensure_phase3_trust_identity(): array
{
    $lines = [];
    $isSqlite = DB::isSqlite();

    try {
        if (!DB::tableExists('verify_tokens')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS verify_tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    purpose VARCHAR(32) NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    code_hash CHAR(64) NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    consumed_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS verify_tokens (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(10) UNSIGNED NOT NULL,
                    purpose VARCHAR(32) NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    code_hash CHAR(64) DEFAULT NULL,
                    attempts TINYINT(3) NOT NULL DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    consumed_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_verify_token_hash (token_hash),
                    KEY ix_verify_user_purpose (user_id, purpose),
                    KEY ix_verify_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'verify_tokens created (WP12 challenge tokens & recovery).';
        } else {
            $lines[] = 'verify_tokens already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'verify_tokens setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('kyc_documents')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS kyc_documents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    kind VARCHAR(32) NOT NULL,
                    storage_key VARCHAR(255) NOT NULL,
                    mime VARCHAR(64) NOT NULL,
                    bytes INTEGER NOT NULL,
                    sha256 CHAR(64) NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "uploaded",
                    review_note TEXT NULL,
                    reviewed_by INTEGER NULL,
                    reviewed_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS kyc_documents (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(10) UNSIGNED NOT NULL,
                    kind VARCHAR(32) NOT NULL,
                    storage_key VARCHAR(255) NOT NULL,
                    mime VARCHAR(64) NOT NULL,
                    bytes INT(10) UNSIGNED NOT NULL,
                    sha256 CHAR(64) NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "uploaded",
                    review_note TEXT DEFAULT NULL,
                    reviewed_by INT(10) UNSIGNED DEFAULT NULL,
                    reviewed_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY ix_kyc_user (user_id),
                    KEY ix_kyc_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'kyc_documents created (WP14 document vault).';
        } else {
            $lines[] = 'kyc_documents already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'kyc_documents setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('user_prefs')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS user_prefs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    pref_key VARCHAR(64) NOT NULL,
                    pref_val TEXT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (user_id, pref_key)
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS user_prefs (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(10) UNSIGNED NOT NULL,
                    pref_key VARCHAR(64) NOT NULL,
                    pref_val TEXT DEFAULT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_user_pref_key (user_id, pref_key),
                    KEY ix_user_prefs_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'user_prefs created (WP15 preference persistence).';
        } else {
            $lines[] = 'user_prefs already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'user_prefs setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('safety_reports')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS safety_reports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reporter_id INTEGER NOT NULL,
                    target_type VARCHAR(32) NOT NULL,
                    target_id VARCHAR(64) NOT NULL,
                    category VARCHAR(64) NOT NULL,
                    detail TEXT NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "new",
                    assigned_admin INTEGER NULL,
                    resolution_note TEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS safety_reports (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    reporter_id INT(10) UNSIGNED NOT NULL,
                    target_type VARCHAR(32) NOT NULL,
                    target_id VARCHAR(64) NOT NULL,
                    category VARCHAR(64) NOT NULL,
                    detail TEXT NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "new",
                    assigned_admin INT(10) UNSIGNED DEFAULT NULL,
                    resolution_note TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY ix_report_reporter (reporter_id),
                    KEY ix_report_target (target_type, target_id),
                    KEY ix_report_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'safety_reports created (WP16 concern triage).';
        }

        if (!DB::tableExists('user_blocks')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS user_blocks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    blocked_user_id INTEGER NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (user_id, blocked_user_id)
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS user_blocks (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(10) UNSIGNED NOT NULL,
                    blocked_user_id INT(10) UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_user_block_pair (user_id, blocked_user_id),
                    KEY ix_block_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'user_blocks created (WP16 server-side blocks).';
        }
    } catch (Throwable $e) {
        $lines[] = 'safety tables setup error: ' . $e->getMessage();
    }

    return $lines;
}



/**
 * Phase-5 owner workspace & host operations schema (WP23-WP26).
 *
 *  • listing_drafts — field-by-field draft persistence and version guards;
 *  • host_invitations — co-host tokenized invites and capability scoping;
 *  • template_sends — automated guest message delivery tracking and deduplication;
 *  • service_requests — host requests for inspection, photography and 3D tours.
 *
 * @return string[]
 */
function ensure_phase5_owner_workspace(): array
{
    $lines = [];
    $isSqlite = DB::isSqlite();

    try {
        if (!DB::tableExists('listing_drafts')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS listing_drafts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    listing_id INTEGER NULL,
                    payload TEXT NOT NULL,
                    version INTEGER NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS listing_drafts (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(10) UNSIGNED NOT NULL,
                    listing_id INT(10) UNSIGNED DEFAULT NULL,
                    payload TEXT NOT NULL,
                    version INT(11) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY ix_draft_user (user_id),
                    KEY ix_draft_listing (listing_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'listing_drafts created (WP23 listing pipeline).';
        } else {
            $lines[] = 'listing_drafts already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'listing_drafts setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('host_invitations')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS host_invitations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    host_id INTEGER NOT NULL,
                    property_id INTEGER NULL,
                    email VARCHAR(190) NOT NULL,
                    capability VARCHAR(64) NOT NULL DEFAULT "cohost_manage",
                    token VARCHAR(64) NOT NULL UNIQUE,
                    status VARCHAR(32) NOT NULL DEFAULT "pending",
                    expires_at DATETIME NOT NULL,
                    accepted_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS host_invitations (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    host_id INT(10) UNSIGNED NOT NULL,
                    property_id INT(10) UNSIGNED DEFAULT NULL,
                    email VARCHAR(190) NOT NULL,
                    capability VARCHAR(64) NOT NULL DEFAULT "cohost_manage",
                    token VARCHAR(64) NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "pending",
                    expires_at DATETIME NOT NULL,
                    accepted_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_host_inv_token (token),
                    KEY ix_host_inv_host (host_id),
                    KEY ix_host_inv_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'host_invitations created (WP24 co-host team).';
        } else {
            $lines[] = 'host_invitations already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'host_invitations setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('template_sends')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS template_sends (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    template_id INTEGER NOT NULL,
                    booking_id INTEGER NOT NULL,
                    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (template_id, booking_id)
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS template_sends (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    template_id INT(10) UNSIGNED NOT NULL,
                    booking_id BIGINT(20) UNSIGNED NOT NULL,
                    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_template_booking (template_id, booking_id),
                    KEY ix_tmpl_send_booking (booking_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'template_sends created (WP24 automation deduplication).';
        } else {
            $lines[] = 'template_sends already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'template_sends setup error: ' . $e->getMessage();
    }

    try {
        if (!DB::tableExists('service_requests')) {
            if ($isSqlite) {
                DB::run('CREATE TABLE IF NOT EXISTS service_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    host_id INTEGER NOT NULL,
                    property_id INTEGER NULL,
                    kind VARCHAR(32) NOT NULL,
                    preferred_date DATE NULL,
                    notes TEXT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "requested",
                    assigned_vendor VARCHAR(120) NULL,
                    scheduled_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )');
            } else {
                DB::run('CREATE TABLE IF NOT EXISTS service_requests (
                    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    host_id INT(10) UNSIGNED NOT NULL,
                    property_id INT(10) UNSIGNED DEFAULT NULL,
                    kind VARCHAR(32) NOT NULL,
                    preferred_date DATE DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT "requested",
                    assigned_vendor VARCHAR(120) DEFAULT NULL,
                    scheduled_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY ix_srv_host (host_id),
                    KEY ix_srv_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
            $lines[] = 'service_requests created (WP26 vendor board).';
        } else {
            $lines[] = 'service_requests already present.';
        }
    } catch (Throwable $e) {
        $lines[] = 'service_requests setup error: ' . $e->getMessage();
    }

    return $lines;
}


function ensure_seed_keys(): array
{
    $notes = [];
    $wanted = [
        ['chat_canned',  'uq_chat_canned_shortcut', ['shortcut'], true],
        ['chat_agents',  'uq_chat_agent_email',     ['email'],    true],
        ['settings',     'uq_settings_key',         ['skey'],     false],
        ['page_meta',    'uq_page_meta_key',        ['page_key'], false],
    ];
    foreach ($wanted as [$table, $index, $cols, $dedupe]) {
        $note = ensure_unique_index($table, $index, $cols, $dedupe);
        if ($note !== '') {
            $notes[] = $note;
        }
    }
    return $notes;
}

/**
 * Make sure a UNIQUE index exists, so `INSERT IGNORE` seed rows stay idempotent
 * when this migration is run again.
 *
 * Returns a note for the operator, or '' when nothing needed doing. A failure
 * is reported rather than thrown: if the table already holds duplicates, the
 * index cannot be added and the operator has to clean them up first.
 *
 * @param string[] $cols
 */
function ensure_unique_index(string $table, string $index, array $cols, bool $dedupe = false): string
{
    try {
        if (!DB::tableExists($table)) {
            return '';
        }
        if (DB::isSqlite()) {
            foreach (DB::all('PRAGMA index_list(' . $table . ')') as $idx) {
                if (($idx['name'] ?? '') === $index) {
                    return '';
                }
            }
        } else {
            $exists = DB::value(
                'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                [$table, $index]
            );
            if ($exists) {
                return '';
            }
        }
        // An earlier run may have inserted the seed rows more than once; keep
        // the oldest of each set so the key can be added.
        $removed = 0;
        if ($dedupe) {
            $first = '`' . $cols[0] . '`';
            try {
                if (DB::isSqlite()) {
                    $removed = DB::pdo()->exec(
                        'DELETE FROM `' . $table . '` WHERE `id` NOT IN (SELECT MIN(`id`) FROM `' . $table . '` GROUP BY ' . $first . ')'
                    ) ?: 0;
                } else {
                    $removed = DB::pdo()->exec(
                        'DELETE a FROM `' . $table . '` a JOIN `' . $table . '` b ON a.' . $first . ' = b.' . $first . ' AND a.`id` > b.`id`'
                    ) ?: 0;
                }
            } catch (Throwable $e) {
                // Not fatal: the ALTER below reports the real problem.
            }
        }
        $quoted = implode(', ', array_map(static fn($c) => '`' . $c . '`', $cols));
        DB::pdo()->exec('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $index . '` (' . $quoted . ')');
        return 'Added the missing ' . $index . ' key on ' . $table . '.'
            . ($removed > 0 ? ' Removed ' . $removed . ' duplicate row(s) an earlier run created.' : '');
    } catch (Throwable $e) {
        return 'Could not add ' . $index . ' on ' . $table . ' (' . $e->getMessage() . '). '
            . 'If the migration was run more than once, delete the duplicate rows there and run it again.';
    }
}

/** Split a SQL script into statements, honouring strings, backticks and comments. */
function split_sql(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = '';
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($lineComment) { if ($c === "\n") { $lineComment = false; $buf .= $c; } continue; }
        if ($blockComment) { if ($c === '*' && $next === '/') { $blockComment = false; $i++; } continue; }
        if ($quote !== '') {
            $buf .= $c;
            if ($c === '\\' && $quote !== '`') { if ($next !== '') { $buf .= $next; $i++; } continue; }
            if ($c === $quote) {
                if ($next === $quote) { $buf .= $next; $i++; continue; }
                $quote = '';
            }
            continue;
        }
        if ($c === '-' && $next === '-') { $lineComment = true; $i++; continue; }
        if ($c === '#') { $lineComment = true; continue; }
        if ($c === '/' && $next === '*') { $blockComment = true; continue; }
        if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $buf .= $c; continue; }
        if ($c === ';') {
            $st = trim($buf);
            if ($st !== '') { $out[] = $st; }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $st = trim($buf);
    if ($st !== '') { $out[] = $st; }
    return $out;
}

/** Make MySQL DDL runnable on SQLite for local development. */
function sqlite_translate(string $sql): string
{
    if (preg_match('~^(\s*)(SET|ALTER\s+DATABASE)\b~i', $sql)) {
        return '';
    }
    $sql = preg_replace('~\s*ENGINE=\w+\s*(DEFAULT\s+)?CHARSET=\w+(\s+COLLATE=\w+)?~i', '', $sql) ?? $sql;
    $sql = preg_replace('~\s+COLLATE[=\s]+[\w]+~i', '', $sql) ?? $sql;   // SQLite has no utf8mb4 collations
    $sql = preg_replace('~INT\s+UNSIGNED\s+NOT\s+NULL\s+AUTO_INCREMENT\s+PRIMARY\s+KEY~i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
    $sql = preg_replace('~\bUNSIGNED\b~i', '', $sql) ?? $sql;
    $sql = preg_replace('~\bAUTO_INCREMENT\b~i', '', $sql) ?? $sql;
    $sql = preg_replace('~\bENUM\s*\([^)]*\)~i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('~\bDATETIME\b~i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('~\bTINYINT\s*\([^)]*\)~i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('~\bINT\s*\([^)]*\)~i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('~ON UPDATE CURRENT_TIMESTAMP~i', '', $sql) ?? $sql;
    // The column form of the primary key wins; drop the table-level clause
    // so SQLite does not complain about a second primary key.
    if (stripos($sql, 'INTEGER PRIMARY KEY AUTOINCREMENT') !== false) {
        $sql = preg_replace('~,\s*PRIMARY KEY\s*\([^)]*\)~i', '', $sql) ?? $sql;
    }
    $sql = preg_replace('~,\s*(UNIQUE\s+)?KEY\s+`?\w+`?\s*\([^)]*\)~i', '', $sql) ?? $sql;
    $sql = preg_replace('~,\s*\)~', ')', $sql) ?? $sql;
    // `INSERT IGNORE` is MySQL only; SQLite spells it `INSERT OR IGNORE`.
    // This is what keeps the seed rows idempotent on the SQLite dev driver.
    $sql = str_ireplace('INSERT IGNORE', 'INSERT OR IGNORE', $sql);
    return trim($sql);
}

/* -------------------------------------------------------------------- view */

$tables = [
    'dispute_categories'       => 'Dispute categories (SLA per topic)',
    'disputes'                 => 'Disputes raised from the help centre',
    'dispute_events'           => 'Dispute timeline: messages, evidence, status changes',
    'chat_departments'         => 'Live chat queues',
    'chat_agents'              => 'Chat agents the admin creates and assigns',
    'chat_agent_departments'   => 'Agent ↔ queue assignments',
    'chat_sessions'            => 'Chat requests (queued, active, closed)',
    'chat_messages'            => 'Chat transcript lines',
    'chat_events'              => 'Chat audit trail (assign, transfer, close)',
    'chat_canned'              => 'Canned replies for agents',
];

$rows = [];
foreach ($tables as $table => $label) {
    $exists = DB::tableExists($table);
    $rows[] = [$table, $label, $exists ? 'ready' : 'missing', $exists ? (int) DB::value("SELECT COUNT(*) FROM $table", [], 0) : 0];
}

render_shell($isAdmin, $rows, $steps, $errors, $ran, $migrationFile);

/**
 * @param array<int,array{0:string,1:string,2:string,3:int}> $rows
 * @param string[] $steps
 * @param string[] $errors
 */
function render_shell(bool $isAdmin, array $rows, array $steps, array $errors, bool $ran, string $sqlFile): void
{
    $title = 'Migration 2026_09_12';
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · Jollof Living</title>
    <style>
      :root{--gold:#c9a227;--ink:#1c1a15;--paper:#faf7f0;--line:#e4ddcc;--ok:#2c6e49;--bad:#a3341f}
      *{box-sizing:border-box}
      body{margin:0;background:var(--paper);color:var(--ink);font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;padding:40px 18px}
      .card{max-width:760px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:16px;padding:32px 30px;box-shadow:0 20px 50px rgba(40,32,10,.07)}
      h1{font-size:24px;margin:0 0 6px;letter-spacing:-.02em}
      .sub{color:#7d7768;margin:0 0 22px;font-size:14px}
      table{width:100%;border-collapse:collapse;margin:14px 0 6px;font-size:14px}
      th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line)}
      th{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#8d8574}
      code{background:#f3eee0;padding:1px 6px;border-radius:5px;font-size:13px}
      .bad{color:var(--bad)}.ok{color:var(--ok)}
      .pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px}
      .pill.ok{background:rgba(44,110,73,.12)}.pill.bad{background:rgba(163,52,31,.12)}
      button{margin-top:22px;width:100%;padding:13px;border:0;border-radius:10px;background:var(--gold);color:#231a05;font-weight:700;font-size:15px;cursor:pointer}
      .note{background:#f7f3e8;border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin:18px 0;font-size:14px}
      ul{padding-left:20px;margin:10px 0}
      a{color:#8a6d10}
    </style></head><body><div class="card">
    <h1>Dispute centre &amp; live chat</h1>
    <p class="sub">Migration 2026_09_12 · Jollof Living</p>
    <?php foreach ($errors as $err): ?><p class="bad">✕ <?= e($err) ?></p><?php endforeach; ?>
    <?php foreach ($steps as $line): ?><p class="ok">✓ <?= e($line) ?></p><?php endforeach; ?>
    <?php if ($ran): ?><p class="ok"><b>Migration complete.</b> You can return to the site — the help centre now has a working dispute centre and the live chat console is at <a href="<?= e(url('agent.php')) ?>">/agent.php</a>.</p><?php endif; ?>

    <table>
      <thead><tr><th>Table</th><th>Purpose</th><th>State</th><th>Rows</th></tr></thead>
      <tbody>
      <?php foreach ($rows as [$table, $label, $state, $count]): ?>
        <tr><td><code><?= e($table) ?></code></td><td><?= e($label) ?></td>
        <td><span class="pill <?= $state === 'ready' ? 'ok' : 'bad' ?>"><?= e($state) ?></span></td><td><?= (int) $count ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="note">
      <b>What this adds</b>
      <ul>
        <li>A database-driven <b>dispute resolution centre</b> on <code>help.php</code>: guests raise a dispute against a real booking, share evidence, message a mediator and receive a tracked outcome.</li>
        <li>A <b>live chat module</b>: the admin creates agents, assigns them to queues, and agents work chat requests from a console at <code>/agent.php</code>. Visitors chat from the widget on every page or <code>/chat.php</code>.</li>
      </ul>
      Raw SQL for phpMyAdmin: <code><?= e(basename($sqlFile)) ?></code>
    </div>

    <?php if (!$isAdmin): ?>
      <p><b>Administrator sign-in required.</b> The migration changes the database, so it only runs for a signed-in administrator.</p>
      <p><a href="<?= e(url('admin-login.php?next=' . urlencode('/install/migrate.php'))) ?>">Sign in to the back office →</a></p>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <button type="submit">Run migration now</button>
      </form>
    <?php endif; ?>
    <p style="margin-top:18px"><a href="<?= e(url('')) ?>">← Back to jollofliving.com</a> · <a href="<?= e(url('admin.php')) ?>">Back office</a></p>
    </div></body></html><?php
}
