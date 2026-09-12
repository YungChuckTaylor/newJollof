# Deploying the Dispute Centre + Live Chat update

**Target:** an already-running Jollof Living site on HostGator shared hosting (cPanel + phpMyAdmin + File Manager).
**Effort:** ~15 minutes. **Downtime:** none — nothing existing is overwritten in a breaking way, and the migration only adds tables.
**Rollback:** restore the two backups from Step 1 (5 minutes).

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
| `install/schema/2026_09_12_disputes_and_live_chat.sql` | The migration itself — 10 tables + seed rows |

### Existing files that must be replaced (the update patches them)

| File | Change |
|---|---|
| `assets/js/site.js` | The fake “File a dispute” toast is gone; `/help` now renders the real dispute centre, the chat bubble opens the live widget, `/agent` is a route, and the back office gained **Live chat desk** + **Dispute resolution** tabs |
| `assets/css/site.css` | Styles for the widget, the console and the dispute cards |
| `includes/view.php` | Injects the dispute/chat data into the page payload (only for `help`, `agent`, `admin`, and only when the tables exist) and loads `chat.js` before `site.js` |
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

### Option A — the ready-made update zip (recommended)

Download **[`public_html_update_disputes_livechat.zip`](public_html_update_disputes_livechat.zip)** (170 KB, 12 files) from the repo/branch, then:

1. cPanel → **File Manager**.
2. Navigate to your **home directory** — the folder that *contains* `public_html` (in cPanel’s left tree it is the top entry, usually shown as `/home/yourusername`).
3. **Upload** the zip there.
4. Right-click the uploaded zip → **Extract** → confirm. The archive already contains the `public_html/…` prefix, so it merges straight into your live site.

> ❗ Do **not** extract the zip *inside* `public_html` — you would end up with `public_html/public_html/…`. Always extract one level above.

### Option B — individual files

Upload the 8 new files and the 5 replacements from §1 through File Manager, keeping the exact folder names (`api/`, `assets/js/`, `assets/css/`, `includes/`, `install/schema/`). Permissions: files `644`, folders `755` (cPanel’s defaults).

### Option C — cPanel Git (if you have it)

cPanel → **Git™ Version Control** → *Create* against your repository and the deployment branch, or *Pull* an existing checkout. Then copy/symlink the files into `public_html`. **Keep your own `includes/config.php`** — don’t let the checkout replace it.

---

## 4. Run the database migration — pick **one**

### A. Browser runner (recommended — it verifies the result)

1. Visit `https://yourdomain.com/install/migrate.php`
2. If it asks you to sign in, use your **administrator** login (`/admin-login.php`), then return to the page.
3. Click **Run migration now**.
4. You should see a green “Migration complete” line and the 10 tables listed as **ready** with row counts.

### B. phpMyAdmin import

1. cPanel → **phpMyAdmin** → select your database.
2. **Import** → *Choose File* → `install/schema/2026_09_12_disputes_and_live_chat.sql` → **Go**.
3. Reload `https://yourdomain.com/install/migrate.php` to confirm all 10 tables read **ready**.

> Both routes are safe to repeat. If `install/migrate.php` reports a table as *missing*, you almost certainly ran it against a different database than the one in `includes/config.php` — check that file.

---

## 5. Verify it works (5-minute checklist)

1. **`/help`** — the *Dispute resolution* panel now shows live counters (open, resolved, avg decision, on-time) with **File a dispute** and **Track a case**.
2. **File a test dispute** — pick a category, attach it to one of your bookings, submit. You get a reference like `D-2026-0007` and the case appears under “Your cases”; open it to see the timeline, evidence and the mediator thread.
3. **Live chat** — on any page, the round button bottom-right (previously the AI concierge) now opens the **live chat widget**: choose a queue, send a message. With no agent online it shows a queue position instead of dropping you.
4. **`/agent.php`** — sign in as the administrator: the desk lists waiting chats, your own chats, the team, queues, canned replies and settings. Switch your status to **Online** so new chats route to you.
5. **Back office** — `/admin.php?tab=chat` (overview, agents, queues, canned replies, widget settings) and `/admin.php?tab=disputes` (the mediation queue).

**Then delete your test dispute/chat** (or just resolve it) before going live.

---

## 6. Set up your support team

1. Back office → **Live chat desk** → **Agents** → **New agent**. Set name, email, role (**agent** or **supervisor**), the queues they cover and their maximum concurrent chats. Add a password to create a sign-in account, or link an existing user by using their email.
2. Agents sign in at `/agent.php` and set themselves **Online / Away / Busy**.
3. **Routing** lives per queue (fewest-busy, round-robin or manual) and global widget settings (welcome text, offline text, max queue, auto-close minutes, rating on/off) under the console’s **Settings** tab.
4. **Mediators** — any chat agent can work the mediation queue in `/admin.php?tab=disputes`; supervisors/admins can assign cases, change priority, post decisions and issue refunds (which are written to the booking and to `payments`).

---

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| Chat bubble still opens the AI concierge / the dispute panel still shows the old mock button | Old JS is cached. Hard-refresh (`Ctrl` + `F5`, or `Cmd` + `Shift` + `R`) and confirm both `assets/js/chat.js` **and** `assets/js/site.js` were uploaded. Asset URLs are cache-busted by file time, so a fresh upload is picked up automatically. |
| An API reply says `needsMigration` | Step 4 has not run against the database in `includes/config.php`. |
| `/agent.php` redirects to the sign-in page | You are not signed in, or your account is not a chat agent yet — create/link it in Back office → Live chat desk → Agents. |
| Visitors are told “offline” and chats only queue | No agent has status **Online**. Set it at `/agent.php`, or leave it — queued chats are kept and can be claimed later. |
| 500 error on `/agent.php` | Check the PHP version (cPanel → **MultiPHP Manager**): the site needs **PHP 8.0+**. |
| Dispute panel shows counters but no categories | Re-import the SQL, or delete the empty `dispute_categories` rows and re-run the migration. |

---

## 8. Optional cleanup and hardening

* After the migration you can delete `install/migrate.php` and `install/schema/` (or leave them — the runner only works for a signed-in administrator).
* Keep `wal7zkit_jollof.sql` **out** of `public_html`; it is a full database dump. Upload it only when you need to restore, and to a non-public folder.
* No cron job is required. An optional daily sweep (auto-closing abandoned chats) can be triggered from the console’s **Settings → Sweep stale chats**.

---

## 9. Rollback

1. phpMyAdmin → **Import** the SQL export from Step 1 (or drop the 10 new `chat_*` / `dispute*` tables — nothing else references them).
2. Restore the file backup over `public_html`.

The website returns to its previous state; the new tables are purely additive, so leaving them in place is harmless if you only roll back the files.
