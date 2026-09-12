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
    render_shell('Configuration missing',
        '<p class="bad">Copy <code>includes/config.sample.php</code> to <code>includes/config.php</code> and fill in your cPanel MySQL details, then reload this page.</p>');
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
