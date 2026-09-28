# Fixing "Something went wrong" on HostGator (public_html/jollof)

## What that page actually is
The screen you're seeing is **not** a Hostgator error — it is the app's own
built‑in fatal catch‑all, rendered by `register_shutdown_function()` in
`includes/bootstrap.php`. It appears whenever **any PHP fatal happens after the
app has loaded**, and it deliberately hides the real error, pointing you to
`health.php`, the error log, or `'debug' => true`.

That generic page tells us two important things by elimination:

| Situation | What you'd actually see | So it is NOT this |
|---|---|---|
| `includes/config.php` missing | Redirect to `/jollof/install/` | ❌ not your case |
| DB **connection** fails | "The site is temporarily unavailable. (Database connection failed.)" + HTTP 503 | ❌ not your case |
| **Any other PHP fatal** | **"Something went wrong"** | ✅ **this is you** |

In other words: **your `config.php` loads fine and the database *connects* fine,
but a PHP fatal fires on the first real query.**

## Root cause (confirmed)
The database that `includes/config.php` points to has **no tables** — in
particular no `properties` table.

The homepage runs `Repo::properties(true)` → `SELECT * FROM properties …`.
With no such table, PDO throws
`SQLSTATE[42S02]: Base table or view not found: 1146 Table '….properties' doesn't exist`.
`Repo::memo()` has no try/catch, so the exception is uncaught → the shutdown
handler renders "Something went wrong".

Why the tables are missing (all three are true in this repo):

1. **The installer cannot create the base schema.** `install/index.php` runs
   `$root . '/../database/schema.sql'` and `…/database/seed.sql`, but there is
   **no `database/` folder in the repo**. The `install/schema/*.sql` files are
   only module upgrades — none of them `CREATE TABLE properties`.
2. **`install/installed.lock` is committed** ("Installed 2026-09-03…"), so the
   installer thinks it is already done (`$done = true`) and does nothing.
3. **The intended way to populate a cPanel database is a manual phpMyAdmin
   import of `wal7zkit_jollof.sql`** (repo root) — a full schema + seed dump
   (54 INSERTs, real `properties` rows). That step wasn't completed, or it was
   imported into a *different* database than the one in `config.php`.

## Confirm it in 30 seconds (do this first)
1. Open **`https://kxq.lop.temporary.site/jollof/health.php`**.
   Expect: DB connect = PASS, but **"Catalogue data" = 0 / WARN** ("import
   wal7zkit_jollof.sql"). It also lists any genuinely missing files.
2. Read the real error at
   **`public_html/storage/logs/php-error.log`**
   ⚠️ This is **one level ABOVE the `jollof` folder** (`bootstrap.php` logs to
   `JL_ROOT/../storage/logs`). You'll see the `1146 Table '…properties' doesn't
   exist` line.
3. (Alternative) temporarily set `'debug' => true` in
   `includes/config.php` and reload the homepage to print the exact error.

## The fix

> **If your import fails with `#1050 - Table 'addons' already exists`** you have a
> *partial* import: an earlier attempt created the base tables in order
> (`addons` is #1 … `properties` is #39) and stopped before `properties`. The
> base tables use plain `CREATE TABLE` (not `IF NOT EXISTS`), so any re-import
> dies instantly on the first table. **Empty the database first, then import
> once.** (Ready-made drop script: `wipe-database.sql`.)

1. **Empty the database (only if a previous import partially ran)**
   - phpMyAdmin → open `wal7zkit_jollof` → **SQL** tab → paste the contents of
     **`wipe-database.sql`** (it runs `SET FOREIGN_KEY_CHECKS=0;` + `DROP TABLE
     IF EXISTS` for all 75 tables) → **Go**. The DB is now empty.
   - *UI alternative:* in the DB, scroll down → **Check All** → **With selected:
     Drop** → confirm (tick "Disable foreign key checks" if shown).

2. **Import the schema + data (once, into an empty DB)**
   - phpMyAdmin → select the **exact database named in `includes/config.php`**
     (`db.name` — the header shows `wal7zkit_jollof`) → **Import** tab →
     *Choose File* → `wal7zkit_jollof.sql` → **Import/Go**.
   - The dump has **no `CREATE DATABASE`/`USE`**, so it creates all tables in
     whichever DB you select — just make sure it's the same one `config.php`
     reads. It should finish in well under a second (169 KB).
2. **Make `config.php` match cPanel**
   - `db.host` = `localhost`.
   - `db.name` and `db.user` must be the **cPanel‑prefixed** names
     (e.g. `yourcpaneluser_jollof`, `yourcpaneluser_jollofuser`).
   - cPanel → **MySQL® Databases** → confirm that user is added to that database
     with **ALL PRIVILEGES**.
3. **Set the PHP version to 8.1/8.2**
   - cPanel → **MultiPHP Manager** → select the domain → PHP **8.1** (or 8.2).
   - The app requires PHP 8.0+ (it calls `str_contains()` etc., which are fatal
     on 7.4). Ensure `pdo_mysql`, `mbstring`, `json`, `gd`, `curl` are enabled
     (on by default on cPanel).
4. **Reload `https://kxq.lop.temporary.site/jollof/`.** It should render.
5. Set `'debug' => false` again, and **delete `health.php`** (and ideally the
   `install/` folder) from the server.

## If you now see: `Uncaught Error: Class "SSR" not found in index.php:34`
The DB is fixed (no more table error) — this is the **next** problem, and it's an
**outdated file**, exactly what the error page warned about.

- `index.php:34` calls `SSR::hero(...)`. In the **current** repo,
  `includes/view.php` (41 KB) loads `includes/ssr.php` and, if that's missing,
  defines a fallback `SSR` class — so it can *never* throw "Class SSR not found".
- Your server is running an **older `includes/view.php`** with no such protection.
  Proof: the two zips bundled in the repo are stale —
  `jollof_release_upgrade.zip` has a 26 KB `view.php` (Sep 23) and **no
  `ssr.php` at all**; `public_html.zip` has a 23 KB `view.php` (Sep 5). If you
  deployed from one of those, `SSR` is referenced but never defined.

**Fix — get the current code onto the server (pick one):**

- **Fastest:** upload the current **`includes/view.php`** and
  **`includes/ssr.php`** from this repo, overwriting the server copies, then
  reload. (The current `view.php` is self-healing, so this alone clears it.)
- **Robust (recommended):** extract **`jollof-deploy.zip`** (current, 208 files)
  and upload its contents into `public_html/jollof/`, **overwriting** existing
  files. It deliberately **excludes `includes/config.php`** (keeps your DB
  creds) and `storage/` (keeps your logs). This also refreshes any *other* stale
  file so you don't hit the next one a minute later.

Then reload the homepage. Confirm in `storage/logs/php-error.log` that no new
fatal appears.

## If it still fails after the import
- Re‑open `health.php` and fix every **FAIL** row in order.
- Confirm the import went into the **exact** DB in `config.php` (a cPanel prefix
  mismatch = you imported into one DB but the app reads an empty one).
- Confirm **all files uploaded** (a partial upload drops a required include →
  same generic page). `health.php`'s "File: …" rows catch this.

## Note for future deploys (repo-side)
The web installer is not self‑sufficient here: `database/schema.sql` +
`database/seed.sql` are absent and `install/installed.lock` ships pre‑set, so
`/install/` will never populate a fresh host. Either keep importing
`wal7zkit_jollof.sql` (recommended), or add those two files + remove the
committed lock so the installer can run end‑to‑end. Happy to add a
`database/schema.sql` derived from the dump if you want the installer path to
work.
