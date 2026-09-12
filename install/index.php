<?php
/**
 * Jollof Living — one-time installer.
 *
 * Creates the schema, loads the seed content and sets the administrator
 * password. Delete this folder (or leave the lock file in place) once done.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/includes/config.php';
$lockFile   = __DIR__ . '/installed.lock';

if (!is_file($configFile)) {
    render_shell('Configuration missing',
        '<p class="bad">Copy <code>includes/config.sample.php</code> to <code>includes/config.php</code> and fill in your cPanel MySQL details, then reload this page.</p>');
    exit;
}

require_once $root . '/includes/bootstrap.php';

$done   = is_file($lockFile);
$errors = [];
$notes  = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$done) {
    if (!csrf_check()) {
        // Work out WHY, so the user is not left guessing.
        if (Auth::sessionError()) {
            $errors[] = Auth::sessionError()
                . ' Ask your host to make it writable, or set a writable session.save_path'
                . ' in cPanel → MultiPHP INI Editor.';
        } elseif (empty($_COOKIE[(string) config('security.session_name', 'jollof_session')])) {
            $errors[] = 'Your browser did not send the session cookie back. '
                . 'Check that cookies are enabled, then load this page again at '
                . e(rtrim(base_path(), '/')) . '/install/ (with the trailing slash) and resubmit.';
        } elseif (empty($_SESSION['csrf'])) {
            $errors[] = 'The session was empty on submit — the server may have cleared it. '
                . 'Reload this page and submit again straight away.';
        } else {
            $errors[] = 'Security token mismatch — this usually means the form was opened '
                . 'in another tab or reloaded from cache. Reload the page and submit again.';
        }
    } else {
        $adminName  = trim((string) ($_POST['admin_name'] ?? ''));
        $adminEmail = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
        $adminPass  = (string) ($_POST['admin_pass'] ?? '');
        $withSeed   = !empty($_POST['seed']);

        if (mb_strlen($adminName) < 2)  $errors[] = 'Enter the administrator name.';
        if (!is_email($adminEmail))     $errors[] = 'Enter a valid administrator email.';
        if (mb_strlen($adminPass) < 10) $errors[] = 'Use an administrator password of at least 10 characters.';

        if (!$errors) {
            try {
                $notes[] = run_sql_file($root . '/../database/schema.sql') . ' schema statements executed.';

                if ($withSeed) {
                    $seeded = (int) DB::value('SELECT COUNT(*) FROM properties', [], 0);
                    if ($seeded > 0) {
                        $notes[] = 'Seed data skipped — the properties table already has ' . $seeded . ' rows.';
                    } else {
                        $notes[] = run_sql_file($root . '/../database/seed.sql') . ' seed statements executed.';
                    }
                }

                // administrator account
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $existing = DB::row('SELECT id FROM users WHERE email = ?', [$adminEmail]);
                if ($existing) {
                    DB::update('users', [
                        'name' => $adminName, 'password_hash' => $hash,
                        'role' => 'admin', 'status' => 'Verified', 'kyc_verified' => 1,
                    ], 'id = ?', [(int) $existing['id']]);
                    $notes[] = 'Existing account ' . e($adminEmail) . ' promoted to administrator.';
                } else {
                    DB::insert('users', [
                        'name' => $adminName, 'email' => $adminEmail, 'password_hash' => $hash,
                        'role' => 'admin', 'tier' => 'platinum', 'status' => 'Verified',
                        'kyc_verified' => 1, 'referral_code' => strtoupper(substr(md5($adminEmail), 0, 8)),
                    ]);
                    $notes[] = 'Administrator ' . e($adminEmail) . ' created.';
                }

                // any seeded account still carrying a placeholder hash is locked out
                $locked = DB::run("UPDATE users SET password_hash = '' WHERE password_hash LIKE '\$2y\$10\$SEED%'");
                $notes[] = 'Placeholder passwords on seeded demo accounts cleared.';

                Repo::saveSetting('installed_at', date('c'));
                Repo::flush();

                @file_put_contents($lockFile, 'Installed ' . date('c') . "\n");
                $done = true;
            } catch (Throwable $ex) {
                $errors[] = 'Installation failed: ' . $ex->getMessage();
            }
        }
    }
}

/* --------------------------------------------------------------- helpers */

/** Execute a .sql file statement by statement. Returns the statement count. */
function run_sql_file(string $path): int
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing SQL file: ' . basename($path));
    }
    $sql = (string) file_get_contents($path);

    $pdo = DB::pdo();
    $isSqlite = DB::isSqlite();
    $n = 0;
    foreach (split_sql($sql) as $st) {
        if ($isSqlite) {
            $st = sqlite_translate($st);
            if ($st === '') {
                continue;
            }
        }
        $pdo->exec($st);
        $n++;
    }
    return $n;
}

/**
 * Split a SQL script into statements, honouring quoted strings, backticks
 * and comments — a naive explode(';') corrupts any row containing a semicolon.
 *
 * @return string[]
 */
function split_sql(string $sql): array
{
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = '';       // active quote character, '' when outside a string
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($c === "\n") { $lineComment = false; $buf .= $c; }
            continue;
        }
        if ($blockComment) {
            if ($c === '*' && $next === '/') { $blockComment = false; $i++; }
            continue;
        }
        if ($quote !== '') {
            $buf .= $c;
            if ($c === '\\' && $quote !== '`') {   // escaped char inside a string
                if ($next !== '') { $buf .= $next; $i++; }
                continue;
            }
            if ($c === $quote) {
                // a doubled quote is a literal quote, not a terminator
                if ($next === $quote) { $buf .= $next; $i++; continue; }
                $quote = '';
            }
            continue;
        }

        if ($c === '-' && $next === '-') { $lineComment = true; $i++; continue; }
        if ($c === '#') { $lineComment = true; continue; }
        if ($c === '/' && $next === '*') { $blockComment = true; $i++; continue; }

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
    if (preg_match('~^\s*(SET|ALTER\s+DATABASE)\b~i', $sql)) {
        return '';
    }
    $sql = preg_replace('~\s*ENGINE=\w+\s*(DEFAULT\s+)?CHARSET=\w+(\s+COLLATE=\w+)?~i', '', $sql) ?? $sql;
    $sql = preg_replace('~INT\s+UNSIGNED\s+NOT\s+NULL\s+AUTO_INCREMENT\s+PRIMARY\s+KEY~i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
    $sql = preg_replace('~\bUNSIGNED\b~i', '', $sql) ?? $sql;
    $sql = preg_replace('~\bAUTO_INCREMENT\b~i', '', $sql) ?? $sql;
    $sql = preg_replace('~\bENUM\s*\([^)]*\)~i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('~\bMEDIUMTEXT\b|\bLONGTEXT\b~i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('~\bDATETIME\b~i', 'TEXT', $sql) ?? $sql;
    $sql = preg_replace('~\bTINYINT\s*\(\d+\)~i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('~\bINT\s*\(\d+\)~i', 'INTEGER', $sql) ?? $sql;
    $sql = preg_replace('~ON UPDATE CURRENT_TIMESTAMP~i', '', $sql) ?? $sql;
    // inline KEY / index definitions are not valid inside a SQLite CREATE TABLE
    $sql = preg_replace('~,\s*(UNIQUE\s+|FULLTEXT\s+)?KEY\s+`?\w+`?\s*\([^)]*\)~i', '', $sql) ?? $sql;
    $sql = preg_replace('~,\s*CONSTRAINT\s+`?\w+`?\s+FOREIGN\s+KEY~i', ', FOREIGN KEY', $sql) ?? $sql;
    // a removed clause can leave a dangling comma before the closing paren
    $sql = preg_replace('~,\s*\)~', ')', $sql) ?? $sql;
    return trim($sql);
}

function render_shell(string $title, string $body): void
{
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title) ?> · Jollof Living installer</title>
    <style>
      :root{--gold:#c9a227;--ink:#1c1a15;--paper:#faf7f0;--line:#e4ddcc}
      *{box-sizing:border-box}
      body{margin:0;background:var(--paper);color:var(--ink);font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;padding:40px 18px}
      .card{max-width:620px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:16px;padding:32px 30px;box-shadow:0 20px 50px rgba(40,32,10,.07)}
      h1{font-size:24px;margin:0 0 6px;letter-spacing:-.02em}
      .sub{color:#7d7768;margin:0 0 22px;font-size:14px}
      label{display:block;font-weight:600;font-size:13px;margin:16px 0 5px}
      input[type=text],input[type=email],input[type=password]{width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:9px;font-size:15px;background:#fffdf8}
      input:focus{outline:2px solid var(--gold);outline-offset:1px}
      .row{display:flex;gap:9px;align-items:flex-start;margin-top:16px;font-size:14px}
      button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:10px;background:var(--gold);color:#231a05;font-weight:700;font-size:15px;cursor:pointer}
      button:hover{filter:brightness(1.06)}
      code{background:#f3eee0;padding:1px 6px;border-radius:5px;font-size:13px}
      .bad{color:#a3341f}.ok{color:#2c6e49}
      ul{padding-left:20px;margin:10px 0}
      .note{background:#f7f3e8;border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin:18px 0;font-size:14px}
      a{color:#8a6d10}
    </style></head><body><div class="card"><h1>Jollof Living</h1>
    <p class="sub">Luxury Living, African Soul — installer</p><?= $body ?></div></body></html><?php
}

/* ------------------------------------------------------------------ view */

if ($done) {
    $body = '<h2 style="font-size:18px;margin:0 0 10px" class="ok">Installation complete ✨</h2>';
    if ($notes) {
        $body .= '<div class="note"><ul><li>' . implode('</li><li>', array_map('e', $notes)) . '</li></ul></div>';
    }
    $body .= '<p><b>Finish up:</b></p><ul>'
        . '<li>Delete the <code>install/</code> folder from your server.</li>'
        . '<li>Make sure <code>includes/config.php</code> is not world-readable (chmod 600).</li>'
        . '<li>Set <code>debug =&gt; false</code> in the config for the live site.</li>'
        . '</ul>'
        . '<p style="margin-top:20px"><a href="' . e(url('')) . '">Open the website</a> · '
        . '<a href="' . e(url('admin-login.php')) . '">Admin console</a></p>';
    render_shell('Complete', $body);
    exit;
}

$body = '';
if ($errors) {
    $body .= '<div class="note bad"><ul><li>' . implode('</li><li>', array_map('e', $errors)) . '</li></ul></div>';
}
$cfg = config('db');
$body .= '<div class="note">Target database: <code>' . e((string) ($cfg['driver'] ?? '')) . '</code> · <code>'
    . e((string) ($cfg['name'] ?? $cfg['sqlite_path'] ?? '')) . '</code></div>';

// Post back to this exact script. Without it, requesting /install (no trailing
// slash) makes the browser resolve a relative action one level up, sending the
// form to the wrong URL -- where the session cookie may not apply.
$selfUrl = e((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));

$body .= '<form method="post" action="' . $selfUrl . '">' . csrf_field()
    . '<label for="admin_name">Administrator name</label><input id="admin_name" type="text" name="admin_name" value="' . e((string) ($_POST['admin_name'] ?? '')) . '" required>'
    . '<label for="admin_email">Administrator email</label><input id="admin_email" type="email" name="admin_email" value="' . e((string) ($_POST['admin_email'] ?? '')) . '" required>'
    . '<label for="admin_pass">Administrator password</label><input id="admin_pass" type="password" name="admin_pass" minlength="10" required>'
    . '<div class="row"><input type="checkbox" id="seed" name="seed" value="1" checked><label for="seed" style="margin:0;font-weight:400">Load the demo content (12 residences, collections, neighbourhood guides, journal posts). Untick for an empty catalogue.</label></div>'
    . '<button type="submit">Install Jollof Living</button></form>';

render_shell('Install', $body);
