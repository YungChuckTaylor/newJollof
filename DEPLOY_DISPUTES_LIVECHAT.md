# Deploying the Dispute Centre + Live Chat update

**Target:** an already-running Jollof Living site on HostGator shared hosting (cPanel + phpMyAdmin + File Manager).
**Effort:** ~15 minutes. **Downtime:** none — nothing existing is overwritten in a breaking way, and the migration only adds tables.
**Rollback:** restore the two backups from Step 1 (5 minutes).

---

## 0. Read this first if you already deployed and got a 500

Two things fix almost every deployment problem:

1. **The support module can no longer take a page down.** `includes/view.php` now fetches the dispute/chat data inside a guard: if a table is missing, the migration half-finished or a file was uploaded incompletely, the page still renders (the support blocks simply don’t appear) and the reason is written to the PHP error log. Re-upload **`includes/view.php`** from the zip to get this.
2. **Run the self-check.** Visit

   ```
   https://yourdomain.com/jollof/install/diagnose.php
   ```

   It prints your PHP version and extensions, whether each of the 10 tables exists **with the columns this version expects**, **where the uploaded files actually landed** (a zip extracted one level above the live folder is the single most common cause of “nothing changed” or a mixed-version 500), whether each module file loads, the calls `admin.php` makes before the support module even loads (`cms_blocks`, `campaigns`, revenue, admin state), and the exact calls `help.php` / `agent.php` / `admin.php` make — each in isolation, so the broken piece names itself. It also prints the tail of the PHP error log. Read-only.

3. **If a page still 500s, run the trap:**

   ```
   https://yourdomain.com/jollof/install/why.php            ← tests admin.php
   https://yourdomain.com/jollof/install/why.php?page=help.php
   ```

   It runs that page in its own request and prints the fatal error the host hides, plus a plain-English note on what that error means. The same line is written to `storage/logs/jollof-trap.log`, which the self-check displays.

If you want the raw error on screen instead, set `'debug' => true` in `includes/config.php`, reload the failing page, then set it back.

---

## 1. What this update contains

### New files (upload these)

| File | What it is |
|---|---|
| `includes/disputes.php` | `DisputeService` — all dispute logic: categories & SLAs, case files, evidence, mediator assignment, status workflow, decisions, refunds, ratings, stats |
| `includes/livechat.php` | `LiveChat` — departments & routing, agents, sessions (queue → active → closed), messages, internal notes, canned replies, metrics, stale-chat sweep |
| `api/dispute.php` | JSON endpoint for the dispute centre (visitor + staff actions) |
| `api/chat.php` | JSON endpoint for the widget, the agent console and the admin chat desk |
| `assets/js/chat.js` | The browser code: floating chat widget, agent console at `/agent.php`, dispute centre on `/help`, plus the two back-office tabs |
| `agent.php` | The agent workspace page (`/agent.php`) |
| `install/migrate.php` | Browser migration runner for a live site |
| `install/diagnose.php` | Self-check page (see §0) |
| `install/why.php` | “Why is this page failing?” trap — runs one page and prints its fatal error (see §0) |
| `install/schema/2026_09_12_disputes_and_live_chat.sql` | The migration itself — 10 tables + seed rows |

### Existing files that must be replaced (the update patches them)

| File | Change |
|---|---|
| `assets/js/site.js` | The fake “File a dispute” toast is gone; `/help` renders the real dispute centre, the chat bubble opens the live widget, `/agent` is a route, and the back office gained **Live chat desk** + **Dispute resolution** tabs |
| `assets/css/site.css` | Styles for the widget, the console and the dispute cards |
| `includes/view.php` | Injects the dispute/chat data into the page payload (only for `help`, `agent`, `admin`, only when the tables exist, and guarded so a failure cannot 500 the page) and loads `chat.js` before `site.js` |
| `install/index.php` | Fresh installs now apply `install/schema/*.sql` too *(only matters for brand-new installs)* |
| `wal7zkit_jollof.sql` | Full dump now includes the new module *(only matters for a full restore)* |

> ⚠️ **Never overwrite `includes/config.php`** — it holds your database credentials. It is intentionally **not** in the update zip.

### What the migration adds to the database

10 new tables — `dispute_categories`, `disputes`, `dispute_events`, `chat_departments`, `chat_agents`, `chat_agent_departments`, `chat_sessions`, `chat_messages`, `chat_events`, `chat_canned` — plus seed rows: 8 dispute categories (4–72 h service levels), 5 chat queues, 7 canned replies, 9 chat settings, page metadata for `/agent.php`, and your administrator account linked as the first **supervisor** agent.

Every statement is idempotent (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`). **No existing table is altered, renamed or dropped.**

---

## 2. Back up first (2 minutes)

1. **Files** — cPanel → **File Manager** → open `public_html` → **Select All** → **Compress** → download the `.zip`, or use **Download** on the folder.
2. **Database** — cPanel → **phpMyAdmin** → click your database → **Export** → *Quick*, format *SQL* → **Go**. Keep the `.sql` file.

Those two files are your rollback.

---

## 3. Upload the files

The update zip **[`jollof-update-disputes-livechat.zip`](jollof-update-disputes-livechat.zip)** (182 KB, 14 files) has **no wrapper folder** — the paths inside it (`agent.php`, `api/…`, `assets/…`, `includes/…`, `install/…`) are exactly the layout of the folder that holds your `admin.php`.

**Find that folder:** it is wherever `admin.php` lives. If your site opens at `https://yoursite.com/jollof/admin.php`, that folder is `public_html/jollof`. If it opens at `https://yoursite.com/admin.php`, it is `public_html`.

> ### ⚠️ This is the step that most often goes wrong
> An earlier version of this guide assumed the site sat directly in `public_html`. Yours does not — it is in a **`jollof`** subfolder (`…/jollof/admin.php`), so a zip that carried a `public_html/` prefix would have landed **outside** the live application and changed nothing (or produced a nested `public_html/public_html`). The zip above is prefix-free so this cannot happen: extract it **into the folder that contains `admin.php`**.

1. cPanel → **File Manager** → open the folder containing `admin.php` (e.g. `public_html/jollof`).
2. **Upload** `jollof-update-disputes-livechat.zip` there.
3. Right-click the uploaded zip → **Extract** → confirm → then delete the zip.
4. Confirm 14 files landed, e.g. `…/jollof/agent.php`, `…/jollof/includes/livechat.php`, `…/jollof/assets/js/chat.js`, `…/jollof/install/diagnose.php`.

Permissions: files `644`, folders `755` (cPanel’s defaults). Never overwrite `includes/config.php`.

**Alternative (cPanel Git):** cPanel → **Git™ Version Control** → create against your repository and the deployment branch, then copy the files into the live folder; keep your own `includes/config.php`.

---

## 4. Run the database migration — pick **one**

### A. Browser runner (recommended — it verifies the result)

1. Visit `https://yourdomain.com/jollof/install/migrate.php`
2. If it asks you to sign in, use your **administrator** login (`/jollof/admin-login.php`), then return to the page.
3. Click **Run migration now**.
4. You should see a green “Migration complete” line and the 10 tables listed as **ready** with row counts.

### B. phpMyAdmin import

1. cPanel → **phpMyAdmin** → select your database.
2. **Import** → *Choose File* → `install/schema/2026_09_12_disputes_and_live_chat.sql` → **Go**.
3. Reload `…/jollof/install/migrate.php` to confirm all 10 tables read **ready**.

### C. Then confirm with the self-check

`https://yourdomain.com/jollof/install/diagnose.php` should say **“All clear”**. If it does not, it names the exact table, column, file or call that is wrong.

> Both routes are safe to repeat. If `install/migrate.php` reports a table as *missing*, you almost certainly ran it against a different database than the one in `includes/config.php` — check that file.

---

## 5. Verify it works (5-minute checklist)

1. **`/jollof/help`** — the *Dispute resolution* panel now shows live counters (open, resolved, avg decision, on-time) with **File a dispute** and **Track a case**.
2. **File a test dispute** — pick a category, attach it to one of your bookings, submit. You get a reference like `D-2026-0007` and the case appears under “Your cases”; open it to see the timeline, evidence and the mediator thread.
3. **Live chat** — on any page, the round button bottom-right opens the **live chat widget**: choose a queue, send a message. With no agent online it shows a queue position instead of dropping you.
4. **`/jollof/agent.php`** — sign in as the administrator: the desk lists waiting chats, your own chats, the team, queues, canned replies and settings. Switch your status to **Online** so new chats route to you.
5. **Back office** — `/jollof/admin.php?tab=chat` (overview, agents, queues, canned replies, widget settings) and `/jollof/admin.php?tab=disputes` (the mediation queue).

**Then delete or resolve your test dispute/chat** before going live.

---

## 6. Set up your support team

1. Back office → **Live chat desk** → **Agents** → **New agent**. Set name, email, role (**agent** or **supervisor**), the queues they cover and their maximum concurrent chats. Add a password to create a sign-in account, or link an existing user by using their email.
2. Agents sign in at `/agent.php` and set themselves **Online / Away / Busy**.
3. **Routing** lives per queue (fewest-busy, round-robin or manual) and global widget settings (welcome text, offline text, max queue, auto-close minutes, rating on/off) under the console’s **Settings** tab.
4. **Mediators** — any chat agent can work the mediation queue in `/admin.php?tab=disputes`; supervisors/admins can assign cases, change priority, post decisions and issue refunds (written to the booking and to `payments`).

---

## 7. Troubleshooting

**Start here:** `…/jollof/install/diagnose.php` (administrator sign-in). It tells you which of the three layers is broken — page, module file, or database — and prints the error log.

| Symptom | Cause / fix |
|---|---|
| **`admin.php` shows HTTP ERROR 500** | Fixed by the guarded payload in the new `includes/view.php` — make sure you uploaded it. If it still fails: run `…/jollof/install/why.php`, which prints the fatal error itself (and writes it to `storage/logs/jollof-trap.log`, shown by the self-check). The self-check also runs every query `admin.php` makes before the support module loads, so a database missing `cms_blocks`, `campaigns` or similar is named too. |
| The self-check reports **problems** in its red summary | Start at the top: a missing file means a partial upload, “exists but is not the shape this version expects” means the migration half-ran (run `install/migrate.php` again), and a red row in §4 is the exact call that throws — the message next to it is the answer. |
| Chat bubble still opens the AI concierge / the dispute panel still shows the old mock button | Old JS is cached or `assets/js/site.js` was not replaced. Hard-refresh (`Ctrl` + `F5`) and confirm both `chat.js` and `site.js` are in the live folder. Asset URLs are cache-busted by file time. |
| An API reply says `needsMigration` | Step 4 has not run against the database in `includes/config.php`. |
| The site still looks exactly as before after extracting | The zip was extracted **outside** the folder holding `admin.php` (see the warning in §3). Re-extract one level deeper. |
| `/agent.php` redirects to the sign-in page | You are not signed in, or your account is not a chat agent yet — create/link it in Back office → Live chat desk → Agents. |
| Visitors are told “offline” and chats only queue | No agent has status **Online**. Set it at `/agent.php`; queued chats are kept and can be claimed later. |
| 500 error on `/agent.php` only | Check the PHP version (cPanel → **MultiPHP Manager**): the site needs **PHP 8.0+**; the self-check prints the version in use. |
| Dispute panel shows counters but no categories | Re-run the migration; then check `install/diagnose.php` → the `dispute_categories` row should read **ready** with 8 rows. |

---

## 8. Optional cleanup and hardening

* After the migration you can delete `install/migrate.php`, `install/diagnose.php` and `install/schema/` (or leave them — both runners only work for a signed-in administrator).
* Keep `wal7zkit_jollof.sql` **out** of `public_html`; it is a full database dump. Upload it only when you need to restore, and to a non-public folder.
* No cron job is required. An optional daily sweep (auto-closing abandoned chats) can be triggered from the console’s **Settings → Sweep stale chats**.

---

## 9. Rollback

1. phpMyAdmin → **Import** the SQL export from Step 1 (or drop the 10 new `chat_*` / `dispute*` tables — nothing else references them).
2. Restore the file backup over the live folder.

The website returns to its previous state; the new tables are purely additive, so leaving them in place is harmless if you only roll back the files.
