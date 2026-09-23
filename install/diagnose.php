<?php
/**
 * Jollof Living — support-module self check.
 *
 * Answers the question "why is /help, /agent.php or /admin.php unhappy?" on a
 * live host where display_errors is off. It reports the PHP build, the state of
 * every table the dispute centre and the live chat desk need (including missing
 * columns), whether the module files load, and it runs the exact payload calls
 * those three pages make — each inside its own try/catch, so one broken piece
 * cannot hide the rest.
 *
 *     https://yourdomain.com/install/diagnose.php
 *
 * Read-only: it never writes to the database and never changes a file.
 * It is limited to a signed-in administrator.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/includes/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Configuration missing</title></head>
    <body style="font:15px/1.6 system-ui,sans-serif;padding:40px;max-width:640px;margin:auto">
    <h1 style="font-size:22px">Configuration missing</h1>
    <p>Copy <code>includes/config.sample.php</code> to <code>includes/config.php</code>, fill in your
    cPanel MySQL details, then reload this page.</p></body></html><?php
    exit;
}

require_once $root . '/includes/bootstrap.php';

$isAdmin = Auth::isAdmin();


/**
 * Identifiers MySQL reserves, that SQLite is happy to accept unquoted.
 *
 * `AS load` is the classic: the development rig runs on SQLite and works, the
 * live host runs MySQL and answers with
 *     SQLSTATE[42000] ... near 'load'
 * Because it cannot be reproduced in the rig, the source text is checked here.
 *
 * @return array<int,array{file:string,line:int,kind:string,word:string}>
 */
function reserved_word_findings(): array
{
    static $reserved = null;
    if ($reserved === null) {
        $reserved = array_flip(explode(' ', 'ACCESSIBLE ADD ALL ALTER ANALYZE AND AS ASC ASENSITIVE BEFORE BETWEEN BIGINT BINARY BLOB BOTH BY CALL CASCADE CASE CHANGE CHAR CHARACTER CHECK COLLATE COLUMN CONDITION CONSTRAINT CONTINUE CONVERT CREATE CROSS CUBE CUME_DIST CURRENT_DATE CURRENT_TIME CURRENT_TIMESTAMP CURRENT_USER CURSOR DATABASE DATABASES DAY_HOUR DAY_MICROSECOND DAY_MINUTE DAY_SECOND DEC DECIMAL DECLARE DEFAULT DELAYED DELETE DENSE_RANK DESC DESCRIBE DETERMINISTIC DISTINCT DISTINCTROW DIV DOUBLE DROP DUAL EACH ELSE ELSEIF EMPTY ENCLOSED ESCAPED EXCEPT EXISTS EXIT EXPLAIN FALSE FETCH FIRST_VALUE FLOAT FLOAT4 FLOAT8 FOR FORCE FOREIGN FROM FULLTEXT FUNCTION GENERATED GET GRANT GROUP GROUPING GROUPS HAVING HIGH_PRIORITY HOUR_MICROSECOND HOUR_MINUTE HOUR_SECOND IF IGNORE IN INDEX INFILE INNER INOUT INSENSITIVE INSERT INT INT1 INT2 INT3 INT4 INT8 INTEGER INTERSECT INTERVAL INTO IS ITERATE JOIN JSON_TABLE KEY KEYS KILL LAG LAST_VALUE LATERAL LEAD LEADING LEAVE LEFT LIKE LIMIT LINEAR LINES LOAD LOCALTIME LOCALTIMESTAMP LOCK LONG LONGBLOB LONGTEXT LOOP LOW_PRIORITY MATCH MAXVALUE MEDIUMBLOB MEDIUMINT MEDIUMTEXT MIDDLEINT MINUTE_MICROSECOND MINUTE_SECOND MOD MODIFIES NATURAL NOT NO_WRITE_TO_BINLOG NTH_VALUE NTILE NULL NUMERIC OF ON OPTIMIZE OPTION OPTIONALLY OR ORDER OUT OUTER OUTFILE OVER PARTITION PERCENT_RANK PRECISION PRIMARY PROCEDURE PURGE RANGE RANK READ READS READ_WRITE REAL RECURSIVE REFERENCES REGEXP RELEASE RENAME REPEAT REPLACE REQUIRE RESIGNAL RESTRICT RETURN REVOKE RIGHT RLIKE ROW ROWS ROW_NUMBER SCHEMA SCHEMAS SECOND_MICROSECOND SELECT SENSITIVE SEPARATOR SET SHOW SIGNAL SMALLINT SPATIAL SPECIFIC SQL SQLEXCEPTION SQLSTATE SQLWARNING SSL STARTING STORED STRAIGHT_JOIN SYSTEM TABLE TERMINATED THEN TINYBLOB TINYINT TINYTEXT TO TRAILING TRIGGER TRUE UNDO UNION UNIQUE UNLOCK UNSIGNED UPDATE USAGE USE USING UTC_DATE UTC_TIME UTC_TIMESTAMP VALUES VARBINARY VARCHAR VARCHARACTER VARYING VIRTUAL WHEN WHERE WHILE WINDOW WITH WRITE XOR YEAR_MONTH ZEROFILL'));
    }

    $files = array_merge(
        glob(JL_INC . '/*.php') ?: [],
        glob(JL_ROOT . '/api/*.php') ?: [],
        glob(JL_ROOT . '/*.php') ?: [],
        glob(JL_ROOT . '/install/schema/*.sql') ?: []
    );

    $out = [];
    foreach ($files as $file) {
        $isSql = str_ends_with($file, '.sql');
        foreach (file($file) ?: [] as $i => $line) {
            if (!$isSql && !preg_match('~\bselect\b|\bfrom\b|\bjoin\b|\binsert\b|\bupdate\b|\bdelete\b~i', $line)) {
                continue;                       // only SQL-ish lines
            }
            if (!$isSql && preg_match('~(?:[A-Z]{2,}[ ,]){5,}~', $line)) {
                continue;                       // a list of keywords, not a query
            }
            if ($isSql && preg_match('~^\s*--~', $line)) {
                continue;                       // comment
            }
            if (preg_match_all('~\bAS\s+([A-Za-z_][A-Za-z0-9_]*)\b(?!`)~i', $line, $m)) {
                foreach ($m[1] as $word) {
                    if (isset($reserved[strtoupper($word)])) {
                        $out[] = ['file' => basename(dirname($file)) . '/' . basename($file), 'line' => $i + 1, 'kind' => 'alias', 'word' => $word];
                    }
                }
            }
            if ($isSql && preg_match_all('~^\s*([A-Za-z_][A-Za-z0-9_]*)\s+(INT|BIGINT|VARCHAR|TEXT|TINYINT|DATETIME|TIMESTAMP|ENUM|DECIMAL|CHAR|SMALLINT)\b~i', $line, $m)) {
                foreach ($m[1] as $word) {
                    if (isset($reserved[strtoupper($word)])) {
                        $out[] = ['file' => basename(dirname($file)) . '/' . basename($file), 'line' => $i + 1, 'kind' => 'column', 'word' => $word];
                    }
                }
            }
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ probes */

/** Every table the support module owns, with the columns the code expects. */
function expected_schema(): array
{
    return [
        'dispute_categories'     => ['id', 'slug', 'name', 'description', 'sla_hours', 'icon', 'sort_order', 'is_active'],
        'disputes'               => ['id', 'ref', 'user_id', 'booking_id', 'booking_ref', 'property_id', 'category_id', 'counterparty_id', 'against', 'subject', 'description', 'desired_outcome', 'amount_claimed', 'currency', 'status', 'priority', 'assigned_to', 'resolution_outcome', 'resolution_note', 'refund_amount', 'evidence_deadline', 'sla_due_at', 'first_response_at', 'resolved_at', 'satisfaction', 'satisfaction_note', 'contact_name', 'contact_email', 'contact_phone', 'access_token', 'ip', 'created_at', 'updated_at'],
        'dispute_events'         => ['id', 'dispute_id', 'actor_id', 'actor_role', 'actor_name', 'kind', 'body', 'attachment', 'meta', 'visibility', 'created_at'],
        'chat_departments'       => ['id', 'slug', 'name', 'description', 'routing', 'welcome_message', 'business_hours', 'color', 'is_active', 'sort_order', 'created_at'],
        'chat_agents'            => ['id', 'user_id', 'name', 'email', 'phone', 'agent_role', 'department_id', 'status', 'max_chats', 'avatar', 'timezone', 'signature', 'last_seen_at', 'last_assigned_at', 'is_active', 'created_at', 'updated_at'],
        'chat_agent_departments' => ['agent_id', 'department_id'],
        'chat_sessions'          => ['id', 'ref', 'visitor_id', 'visitor_name', 'visitor_email', 'visitor_phone', 'visitor_token', 'department_id', 'agent_id', 'transferred_from', 'status', 'priority', 'subject', 'source', 'page_url', 'user_agent', 'ip', 'tags', 'visitor_typing_at', 'agent_typing_at', 'first_response_at', 'agent_joined_at', 'last_message_at', 'closed_at', 'closed_by', 'close_note', 'rating', 'rating_comment', 'unread_agent', 'unread_visitor', 'wait_seconds', 'created_at', 'updated_at'],
        'chat_messages'          => ['id', 'session_id', 'sender', 'agent_id', 'author_name', 'body', 'meta', 'read_flag', 'created_at'],
        'chat_events'            => ['id', 'session_id', 'kind', 'actor_type', 'actor_id', 'actor_name', 'detail', 'created_at'],
        'chat_canned'            => ['id', 'shortcut', 'title', 'body', 'scope', 'department_id', 'agent_id', 'uses', 'sort_order', 'is_active', 'created_at', 'updated_at'],
    ];
}

/** Column names of a table, or null when it does not exist. */
function table_columns(string $table): ?array
{
    try {
        if (DB::isSqlite()) {
            $rows = DB::all("PRAGMA table_info($table)");
            if (!$rows) {
                return null;
            }
            return array_map(static fn($r) => (string) $r['name'], $rows);
        }
        $rows = DB::all(
            'SELECT COLUMN_NAME AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        if (!$rows) {
            return null;
        }
        return array_map(static fn($r) => (string) $r['c'], $rows);
    } catch (Throwable $e) {
        return null;
    }
}

/** Run a probe and capture its outcome instead of letting it kill the page. */
function probe(callable $fn): array
{
    $started = microtime(true);
    try {
        $value = $fn();
        return ['ok' => true, 'ms' => (int) round((microtime(true) - $started) * 1000), 'value' => $value, 'error' => ''];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'value' => null,
            'error' => get_class($e) . ': ' . $e->getMessage() . "\n  at " . $e->getFile() . ':' . $e->getLine(),
        ];
    }
}

/** Last lines of a log file, safely. */
function tail_file(string $path, int $lines = 40): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $size = (int) filesize($path);
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return [];
    }
    $chunk = 64 * 1024;
    $offset = max(0, $size - $chunk);
    fseek($fh, $offset);
    $data = (string) fread($fh, $chunk);
    fclose($fh);
    $all = preg_split("~\r?\n~", trim($data)) ?: [];
    return array_slice($all, -$lines);
}

if (!$isAdmin) {
    render_diagnose(false, [], [], [], []);
    exit;
}

/* ------------------------------------------------------------- environment */

$driver = (string) config('db.driver', 'mysql');
$env = [
    'PHP version'        => PHP_VERSION,
    'PHP SAPI'           => PHP_SAPI,
    'Server software'    => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
    'Database driver'    => $driver,
    'Database name'      => (string) ($driver === 'sqlite'
        ? config('db.sqlite_path', '')
        : config('db.name', '')),
    'Database host'      => (string) ($driver === 'sqlite' ? 'local file' : config('db.host', '')),
    'Site base_url'      => (string) config('site.base_url', '') ?: '(web root)',
    'Application root'   => JL_ROOT,
    'Debug flag'         => config('debug') ? 'true (errors show on screen)' : 'false (errors go to the log)',
    'display_errors'     => (string) ini_get('display_errors') ?: 'off',
    'error_log'          => (string) ini_get('error_log') ?: '(none set)',
    'Session save path'  => session_save_path() ?: '(system default)',
    'Session writable'   => is_writable(session_save_path() ?: sys_get_temp_dir()) ? 'yes' : 'NO — writes will fail',
];

$extensions = [];
foreach (['pdo_mysql', 'pdo_sqlite', 'mbstring', 'json', 'session', 'openssl', 'curl', 'zlib'] as $ext) {
    $extensions[$ext] = extension_loaded($ext) ? 'loaded' : 'missing';
}

/* ---------------------------------------------------------------- database */

$columns = [];
$tableState = [];
foreach (expected_schema() as $table => $expected) {
    $cols = table_columns($table);
    $exists = $cols !== null;
    $missing = $exists ? array_values(array_diff($expected, $cols)) : $expected;
    $count = 0;
    if ($exists) {
        $probe = probe(static fn() => (int) DB::value("SELECT COUNT(*) FROM `$table`", [], 0));
        $count = $probe['ok'] ? (int) $probe['value'] : -1;
    }
    $columns[$table] = ['exists' => $exists, 'missing' => $missing, 'count' => $count];
    $tableState[] = [$table, $exists ? 'ready' : 'missing', $count, $exists && !$missing ? 'ok' : ($missing ? implode(', ', array_slice($missing, 0, 6)) : '')];
}

/* ------------------------------------------------------------------ module */

$files = [
    'disputes.php' => JL_INC . '/disputes.php',
    'livechat.php' => JL_INC . '/livechat.php',
    'view.php'     => JL_INC . '/view.php',
];
$fileState = [];
foreach ($files as $label => $path) {
    $fileState[$label] = [
        'file' => is_file($path) ? 'present' : 'MISSING — upload includes/' . $label,
        'load' => probe(static function () use ($path) {
            if (!is_file($path)) {
                throw new RuntimeException('file not found at ' . $path);
            }
            require_once $path;   // a parse error here is a catchable ParseError
            return 'loaded';
        }),
    ];
}

/* Each probe below is one of the calls the /help, /agent.php and /admin.php
   payloads make. */
$uid = (int) (Auth::id() ?? 0);
$probes = [
    'DisputeService::categories()'       => static fn() => count(DisputeService::categories()) . ' categories',
    'DisputeService::stats()'            => static fn() => json_encode(DisputeService::stats()),
    'DisputeService::forUser(me)'        => static fn() => count(DisputeService::forUser($uid, 12)) . ' of my cases',
    'LiveChat::settings()'               => static fn() => json_encode(LiveChat::settings()),
    'LiveChat::departments(true)'        => static fn() => count(LiveChat::departments(true)) . ' active queues',
    'LiveChat::agentForUser(me)'         => static fn() => json_encode(LiveChat::agentForUser($uid)),
    'admin.php: cms_blocks'              => static fn() => count(DB::all('SELECT * FROM cms_blocks ORDER BY id')) . ' CMS blocks',
    'admin.php: campaigns'               => static fn() => count(DB::all('SELECT * FROM campaigns ORDER BY id')) . ' campaigns',
    'admin.php: users by role'           => static fn() => count(DB::all('SELECT role, COUNT(*) AS n FROM users GROUP BY role ORDER BY n DESC')) . ' role rows',
    'admin.php: Repo::revenueSeries(12)' => static fn() => count(Repo::revenueSeries(12)) . ' months of revenue',
    'admin.php: Repo::revenueByCity()'   => static fn() => count(Repo::revenueByCity()) . ' cities',
    'admin.php: Repo::adminState()'      => static fn() => 'ok — ' . count((array) Repo::adminState()) . ' keys',
    'admin.php: Repo::adminStats()'      => static fn() => 'ok — ' . count((array) Repo::adminStats()) . ' keys',
    'View::payload("admin")'             => static function () {
        require_once JL_INC . '/view.php';
        $p = View::payload('admin');
        $data = $p['data'] ?? [];
        return 'ok — keys: ' . implode(', ', array_filter([
            isset($data['disputeCategories']) ? 'disputeCategories' : null,
            isset($data['chat']) ? 'chat' : null,
            isset($data['chatAgent']) ? 'chatAgent' : null,
        ])) ?: 'ok (no support keys)';
    },
    'View::payload("help")'              => static function () {
        require_once JL_INC . '/view.php';
        View::payload('help');
        return 'ok';
    },
    'View::header("admin")'              => static function () {
        require_once JL_INC . '/view.php';
        $chrome = [
            'charts'    => ['revenue' => [], 'byCity' => []],
            'cmsBlocks' => [],
            'campaigns' => [],
            'roles'     => [],
        ];
        ob_start();
        View::header('admin', ['extra' => $chrome]);
        $html = (string) ob_get_clean();
        return 'rendered ' . strlen($html) . ' bytes of chrome';
    },
];
$results = [];
foreach ($probes as $label => $fn) {
    $results[] = [$label, probe($fn)];
}

/* ------------------------------------------------------------------- logs */

$parent = dirname(JL_ROOT);
$logFiles = array_filter(array_merge(
    [(string) ini_get('error_log')],
    glob(JL_ROOT . '/error_log') ?: [],
    glob(JL_ROOT . '/../storage/logs/*.log') ?: [],
    glob(JL_ROOT . '/storage/logs/*.log') ?: [],
    glob($parent . '/logs/*.log') ?: [],
    glob($parent . '/../logs/*.log') ?: []
), static fn($p) => $p !== '' && is_file($p) && is_readable($p));
$logFiles = array_slice(array_unique($logFiles), 0, 4);
$logs = [];
foreach (array_unique($logFiles) as $path) {
    $logs[$path] = tail_file($path, 40);
}

/* Where did the upload land? The layout matters more than anything else on a
   subfolder install: a zip extracted one level too high leaves the live app
   untouched, and the two "copies found elsewhere" rows below prove it. */
$layout = [];
foreach ([
    'agent.php', 'includes/view.php', 'includes/disputes.php', 'includes/livechat.php',
    'assets/js/chat.js', 'assets/js/site.js', 'assets/css/site.css',
    'api/chat.php', 'api/dispute.php', 'install/migrate.php',
    'install/schema/2026_09_12_disputes_and_live_chat.sql',
] as $rel) {
    $here = is_file(JL_ROOT . '/' . $rel);
    $above = is_file($parent . '/' . $rel);
    $nested = is_file(JL_ROOT . '/public_html/' . $rel);
    $sibling = is_file($parent . '/public_html/' . $rel);
    $where = $here ? 'in the live folder'
        : ($above ? 'ONE LEVEL TOO HIGH — in ' . $parent
        : ($sibling ? 'in ' . $parent . '/public_html'
        : ($nested ? 'in a nested ' . JL_ROOT . '/public_html' : 'not found in any neighbouring folder')));
    $layout[] = [$rel, $here ? 'ok' : 'bad', $where];
}

$sqlWords = reserved_word_findings();

$missingFiles = array_filter($fileState, static fn($f) => $f['file'] !== 'present');
$brokenLoad = array_filter($fileState, static fn($f) => !$f['load']['ok']);
$brokenProbes = array_filter($results, static fn($r) => !$r[1]['ok']);
$missingTables = array_filter($columns, static fn($c) => !$c['exists']);
$drifted = array_filter($columns, static fn($c) => $c['exists'] && $c['missing']);

render_diagnose(true, [
    'env' => $env,
    'extensions' => $extensions,
    'tables' => $tableState,
    'files' => $fileState,
    'results' => $results,
    'logs' => $logs,
    'layout' => $layout,
    'sqlwords' => $sqlWords,
    'summary' => [
        'missingFiles' => array_keys($missingFiles),
        'brokenLoad' => array_keys($brokenLoad),
        'brokenProbes' => array_map(static fn($r) => $r[0], $brokenProbes),
        'missingTables' => array_keys($missingTables),
        'drifted' => array_keys($drifted),
        'sqlWords' => $sqlWords,
    ],
], $columns, $probes, $results);

/* Running the real pages in *this* request is impossible: the framework is
   already loaded here, so including admin.php would re-declare its functions
   and fatal. install/why.php exists for that: it traps the fatal in a fresh
   request and prints the message. */
if (isset($_GET['full']) && $isAdmin) {
    echo '<div class="card"><h2>Run the real pages</h2>'
        . '<p>This report cannot include the pages themselves (the framework is already loaded in this request). '
        . 'Use the trap page, which runs one page in its own request and prints the fatal it produces:</p>'
        . '<p>'
        . '<a href="' . e(url('install/why.php?page=admin.php')) . '">admin.php</a> · '
        . '<a href="' . e(url('install/why.php?page=help.php')) . '">help.php</a> · '
        . '<a href="' . e(url('install/why.php?page=agent.php')) . '">agent.php</a> · '
        . '<a href="' . e(url('install/why.php')) . '">why.php</a></p></div></body></html>';
}

/* ------------------------------------------------------------------- view */

/**
 * @param array<string,string> $env
 * @param array<string,string> $extensions
 * @param array<int,array{0:string,1:string,2:int,3:string}> $tables
 * @param array<string,array{file:string,load:array}> $files
 * @param array<int,array{0:string,1:array}> $results
 * @param array<string,string[]> $logs
 * @param array<string,string[]> $summary
 * @param array<string,array{exists:bool,missing:string[],count:int}> $columns
 * @param array<string,callable> $probes
 */
function render_diagnose(bool $isAdmin, array $sections, array $columns, array $probes, array $results): void
{
    $ok = static function (bool $cond): string { return $cond ? 'pill ok' : 'pill bad'; };
    $badge = static function (bool $cond): string { return $cond ? 'ok' : 'bad'; };
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Support module self-check · Jollof Living</title>
    <style>
      :root{--gold:#c9a227;--ink:#1c1a15;--paper:#faf7f0;--line:#e4ddcc;--ok:#2c6e49;--bad:#a3341f}
      *{box-sizing:border-box}
      body{margin:0;background:var(--paper);color:var(--ink);font:14.5px/1.65 system-ui,-apple-system,"Segoe UI",sans-serif;padding:36px 16px}
      .card{max-width:980px;margin:0 auto 20px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:26px 26px 22px;box-shadow:0 16px 40px rgba(40,32,10,.06)}
      h1{font-size:22px;margin:0 0 4px;letter-spacing:-.02em}
      h2{font-size:16px;margin:24px 0 8px;letter-spacing:-.01em}
      .sub{color:#7d7768;margin:0 0 14px;font-size:13.5px}
      table{width:100%;border-collapse:collapse;margin:6px 0 4px;font-size:13.5px}
      th,td{text-align:left;padding:7px 9px;border-bottom:1px solid var(--line);vertical-align:top}
      th{font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:#8d8574}
      code,pre{background:#f3eee0;border-radius:6px;font-size:12.5px}
      code{padding:1px 5px}
      pre{padding:12px 14px;overflow:auto;white-space:pre-wrap;word-break:break-word;margin:6px 0}
      .bad{color:var(--bad)}.ok{color:var(--ok)}
      .pill{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11.5px;white-space:nowrap}
      .pill.ok{background:rgba(44,110,73,.12);color:var(--ok)}
      .pill.bad{background:rgba(163,52,31,.12);color:var(--bad)}
      .grid{display:grid;grid-template-columns:220px 1fr;gap:2px 14px;font-size:13.5px}
      .grid div:nth-child(odd){color:#8d8574}
      a{color:#8a6d10}
      .note{background:#f7f3e8;border:1px solid var(--line);border-radius:10px;padding:13px 15px;margin:14px 0;font-size:13.5px}
      button{margin-top:16px;padding:11px 18px;border:0;border-radius:9px;background:var(--gold);color:#231a05;font-weight:700;cursor:pointer}
    </style></head><body>

    <div class="card">
      <h1>Support module self-check</h1>
      <p class="sub">Dispute centre + live chat · read-only · Jollof Living</p>
      <?php if (!$isAdmin): ?>
        <p class="bad"><b>Administrator sign-in required.</b></p>
        <p><a href="<?= e(url('admin-login.php?next=' . urlencode('/install/diagnose.php'))) ?>">Sign in to the back office →</a></p>
        </div></body></html><?php
        return;
      endif; ?>

      <?php $s = $sections['summary'] ?? []; ?>
      <div class="note">
        <?php if (!$s['missingFiles'] && !$s['brokenLoad'] && !$s['brokenProbes'] && !$s['missingTables'] && !$s['drifted'] && !$s['sqlWords']): ?>
          <b class="ok">All clear.</b> Every table, file and payload call the support module needs works on this server.
          If a page still fails, the cause is elsewhere — check the error log below and the browser console.
        <?php else: ?>
          <b class="bad">Problems found — the first one listed is almost always the cause:</b>
          <ul style="margin:8px 0 0 18px">
            <?php foreach ($s['missingFiles'] as $f): ?><li>Missing file: <code>includes/<?= e($f) ?></code> — upload it again.</li><?php endforeach; ?>
            <?php foreach ($s['brokenLoad'] as $f): ?><li><code>includes/<?= e($f) ?></code> will not load (see the detail below). Often this is a partial upload or a PHP version that is too old.</li><?php endforeach; ?>
            <?php foreach ($s['missingTables'] as $t): ?><li>Table <code><?= e($t) ?></code> is missing — run <a href="<?= e(url('install/migrate.php')) ?>">install/migrate.php</a>.</li><?php endforeach; ?>
            <?php foreach ($s['drifted'] as $t): ?><li>Table <code><?= e($t) ?></code> exists but is not the shape this version expects — the migration may have failed halfway. Re-run it.</li><?php endforeach; ?>
            <?php foreach ($s['brokenProbes'] as $p): ?><li><code><?= e($p) ?></code> throws — see the payload section.</li><?php endforeach; ?>
            <?php foreach ($s['sqlWords'] as $hit): ?><li><code><?= e($hit['file']) ?></code> line <?= (int) $hit['line'] ?> uses <code><?= e($hit['word']) ?></code> as <?= e($hit['kind']) ?> — MySQL reserves that word; it needs back-quotes.</li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <h2>1 · Environment</h2>
      <div class="grid">
        <?php foreach ($sections['env'] as $label => $value): ?>
          <div><?= e($label) ?></div><div><?= e((string) $value) ?></div>
        <?php endforeach; ?>
      </div>
      <h2>PHP extensions</h2>
      <div class="grid">
        <?php foreach ($sections['extensions'] as $ext => $state): ?>
          <div><?= e($ext) ?></div><div class="<?= $state === 'loaded' ? 'ok' : 'bad' ?>"><?= e($state) ?></div>
        <?php endforeach; ?>
      </div>

      <h2>2 · Module files</h2>
      <table>
        <thead><tr><th>File</th><th>Present</th><th>Loads</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($sections['files'] as $label => $info): ?>
          <tr>
            <td><code>includes/<?= e($label) ?></code></td>
            <td><span class="<?= $ok($info['file'] === 'present') ?>"><?= e($info['file']) ?></span></td>
            <td><span class="<?= $ok((bool) $info['load']['ok']) ?>"><?= $info['load']['ok'] ? 'yes' : 'no' ?></span></td>
            <td><?php if (!$info['load']['ok']): ?><pre class="bad"><?= e($info['load']['error']) ?></pre><?php else: ?><span class="ok"><?= e((string) $info['load']['value']) ?> in <?= (int) $info['load']['ms'] ?> ms</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h2>3 · Database tables</h2>
      <table>
        <thead><tr><th>Table</th><th>State</th><th>Rows</th><th>Missing columns</th></tr></thead>
        <tbody>
        <?php foreach ($sections['tables'] as [$table, $state, $count, $detail]): ?>
          <tr>
            <td><code><?= e($table) ?></code></td>
            <td><span class="<?= $ok($state === 'ready') ?>"><?= e($state) ?></span></td>
            <td><?= $count < 0 ? '—' : (int) $count ?></td>
            <td class="<?= $detail === 'ok' || $detail === '' ? 'ok' : 'bad' ?>"><?= e($detail === 'ok' ? 'none' : ($detail ?: '—')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h2>4 · The calls the pages make</h2>
      <table>
        <thead><tr><th>Call</th><th>Result</th><th>Time</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($sections['results'] as [$label, $r]): ?>
          <tr>
            <td><code><?= e($label) ?></code></td>
            <td><span class="<?= $ok((bool) $r['ok']) ?>"><?= $r['ok'] ? 'ok' : 'FAILED' ?></span></td>
            <td><?= (int) $r['ms'] ?> ms</td>
            <td><?php if ($r['ok']): ?><span class="muted"><?= e(mb_substr((string) $r['value'], 0, 400)) ?></span><?php else: ?><pre class="bad"><?= e($r['error']) ?></pre><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h2>5 · Where the files are</h2>
      <p class="sub">Every path below is relative to the folder this site runs from:
        <code><?= e(JL_ROOT) ?></code>. If a row says <b class="bad">ONE LEVEL TOO HIGH</b>,
        the zip was extracted outside the live application — extract it again into the folder
        that holds <code>admin.php</code>.</p>
      <table>
        <thead><tr><th>File</th><th>In place</th><th>Found</th></tr></thead>
        <tbody>
        <?php foreach ($sections['layout'] ?? [] as [$rel, $state, $where]): ?>
          <tr><td><code><?= e($rel) ?></code></td>
              <td><span class="pill <?= $state ?>"><?= $state === 'ok' ? 'yes' : 'no' ?></span></td>
              <td class="<?= $state === 'ok' ? 'ok' : 'bad' ?>"><?= e($where) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h2>6 · SQL identifiers MySQL reserves</h2>
      <p class="sub">SQLite accepts these unquoted, MySQL rejects them with
        <code>SQLSTATE[42000] … near '&lt;word&gt;'</code> — the reason a module can pass every test in
        development and fail on this host. Aliases must be back-quoted: <code>AS `load`</code>.</p>
      <?php if (!$sections['sqlwords']): ?>
        <p class="ok"><b>Clean</b> — no unquoted reserved identifiers in includes/, api/ or install/schema/.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>File</th><th>Line</th><th>Kind</th><th>Word</th></tr></thead>
          <tbody>
          <?php foreach ($sections['sqlwords'] as $hit): ?>
            <tr><td><code><?= e($hit['file']) ?></code></td><td><?= (int) $hit['line'] ?></td>
                <td><?= e($hit['kind']) ?></td><td class="bad"><code><?= e($hit['word']) ?></code></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="bad">Upload the current <b>includes/livechat.php</b> from the update zip, then re-run this check.</p>
      <?php endif; ?>

      <h2>7 · Error log</h2>
      <?php if (!$sections['logs']): ?>
        <p class="sub">No log file found. Set <code>'debug' =&gt; true</code> in <code>includes/config.php</code>,
        reload the failing page, and the error will be printed on screen instead.</p>
      <?php endif; ?>
      <?php foreach ($sections['logs'] as $path => $lines): ?>
        <p class="sub"><code><?= e($path) ?></code> — last <?= count($lines) ?> lines</p>
        <pre><?= e(implode("\n", $lines)) ?></pre>
      <?php endforeach; ?>

      <form method="get"><button type="submit">Run the check again</button></form>
      <p style="margin-top:14px"><a href="<?= e(url('')) ?>">← Back to the site</a> ·
         <a href="<?= e(url('install/migrate.php')) ?>">Run the migration</a> ·
         <a href="<?= e(url('admin.php')) ?>">Back office</a> ·
         <a href="<?= e(url('install/diagnose.php?full=1')) ?>">Open the page trap (why.php)</a></p>
    </div>
    </body></html><?php
}
