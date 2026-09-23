# Jollof Living — Full-Functionality Implementation Plan

**Purpose:** convert every Partially functional (49) and Not working at all (29) capability identified by the re-audit of `arena/01a096cd-newjollof` @ `19363b928dd2` into a fully working, dynamic, database-driven feature — and prove it the same way the audit proved the defects: end-to-end executable checks, not code inspection alone.

**Baseline:** branch `arena/01a096cd-newjollof`, commit `19363b928dd2394b93010fde09a72014c95e6cab` ("Fix the live 500: `load` is a MySQL reserved word" + re-runnable migration). Evidence base: 367 executable checks and 375 embedded records in `docs/JOLLOF_LIVING_REAUDIT_01a096cd.html`.

**Read together with:** `docs/JOLLOF_LIVING_REAUDIT_01a096cd.md` (what fails today), the re-audit CSV (per-feature finding/source refs), and this document (how to make it pass). Every step below cites real files from the baseline; every work package ends in re-runnable acceptance tests.

---

## 1. Executive summary

The platform today is a convincing front end over a partly-built back office. The discovery UI, accounts basics, the new live-chat/disputes modules, CSV exports, review moderation transitions, and the full checkout *flow* are real. The money layer (payment capture, escrow, refunds, payouts, deposits, gift funding), the booking-integrity layer (calendar/price consumption, concurrency, host decisions), the identity layer (verification, 2FA, KYC, recovery), and every scheduled/external capability (email channels, waitlists, channel sync, locks, AI services) are either labels on database rows or toasts in the browser.

Making the platform "fully functional, dynamic and database-driven" therefore requires more than bug fixes. It requires five shared services that most features currently bypass:

1. **One authoritative quote/availability engine** (server-side, date-aware) consumed identically by the detail page, search, checkout, host calendar, and mobile app. Today `Pricing::quote()` takes nights but no dates, so calendar overrides, rules and blocked days can never reach the price.
2. **A real money machine:** provider-verified capture → double-entry ledger → derived escrow → policy-computed refunds → scheduled payouts. Today `payments.mode = 'record_only'` writes optimistic labels ("escrow held", "paid", "confirmed") with no funds movement.
3. **One identity/trust model:** single account-state field, real email/phone verification, TOTP 2FA, evidence-backed KYC, unified session/token revocation, and a permission model that is not the `users.role` string.
4. **One JSON contract** (`{ok, data}`) with every mutation re-rendering from the server copy — this alone converts a dozen "partially functional" rows that fail purely because the client reads the wrong envelope level.
5. **One outbox/workers runtime** (mail, notifications, waitlist matching, expiry sweeps, scheduled messages, payout runs, channel iCal) — shared-hosting-safe via a token-guarded cron endpoint — because a large class of "no worker exists" findings cannot be closed inside request handlers.

With those in place, the 78 features resolve through **37 work packages** across **nine phases** (~262 dev-days for one full-stack engineer; ~5 months with a team of two plus QA). Three features (native app `F81`, social sign-in `F17`, advanced AI suite `F54`) have prerequisites outside the codebase (store accounts, OAuth apps, model keys); this plan sequences them last, with honest "unavailable" states until each prerequisite lands.

**Definition of "fully functional" here** (the audit's own bar): a real user action completes its advertised effect — data moves correctly through UI → API → database → dependent modules → notifications, guards reject what the copy says they reject, and an executable test proves it. A toast, a DB label, or a page that "looks right" never counts.

---

## 2. How to use this document

Each work package (WP) follows the same template:

- **Converts:** the `F#` feature IDs it closes, with the audit test IDs that currently fail and must pass after.
- **Design decisions:** the one or two choices that matter most, made explicitly so implementation cannot drift.
- **Steps:** numbered, concrete actions — schema DDL sketches, endpoint contracts, logic rules, UI wiring, worker behavior. DDL is MySQL-first (production rail); every package notes the SQLite-dialect twin (dev/CI rail).
- **Acceptance:** re-runnable checks, expressed as audit-style tests (`X##-…`). A WP is *done* only when its acceptance checks pass end-to-end and no prior passing check regressed.

Global rules that apply to **every** package:

- **R1 — Claims gate.** UI copy may describe only what is enabled and proven. Wire marketing claims to `feature_flags` (§4.9): a flag flips to `on` only when its service passes its acceptance suite. This is the mechanism that keeps "smart-lock synchronized", "KYC verified", "AI live" from outliving their implementations.
- **R2 — No business data in JavaScript.** Anything currently hardcoded in `assets/js/site.js` (collection maps, 12 map pins, demo gift code, referral sample stats, fixed `4471#` access code, review star defaults) moves to database tables or API responses. The client renders; the server decides.
- **R3 — Server-authoritative money and inventory.** Prices, totals, discounts, deposits, refunds, points, gift credit, availability, capacity and policy selection are computed server-side; the client submits *intent* (listing, dates, promo code, guests), never amounts.
- **R4 — One ledger for one money story.** Every naira that matters is a `ledger_entries` row with an idempotency key; "escrow held / refunded / paid out" become *derived states*, never hand-edited labels.
- **R5 — Every mutation is transactional and idempotent.** Multi-row changes (booking + payment + points + event + notification) commit atomically or not at all; retries (webhooks, cron, flaky clients) must be safe via unique dedupe keys.
- **R6 — Dual-dialect migrations only.** Schema changes ship as `install/schema/` migrations runnable on MySQL and translated cleanly for SQLite/CI (this audit already proved where dialect drift bites: T06).
- **R7 — Authorization by capability check, not page presence.** Each API validates ownership/scope/active-state server-side at handler level; UI hiding is cosmetic.
- **R8 — Evidence trail.** Status transitions (booking, payment, escrow, dispute, KYC, account state) write immutable events with actor + timestamp; audit-log entries chain-hash (§4.8) so "immutable" becomes true.

---

## 3. Stop-the-bleeding list (do these before anything structural)

These are the highest-harm defects with the smallest fixes; each is a one-to-two-day patch, several are a few lines. They are formally owned by the WPs noted, but ship first.

1. **`saveAgent` role escalation** — setting an agent temp-password writes `users.role = 'admin'` (C32), which then unlocks every-user CSV export (C33) and survives agent pause (C34). *Remove the role write from `includes/livechat.php` saveAgent path entirely; grant platform admin only through the dedicated admin role editor.* (WP01)
2. **Chat `close` leaks transcripts** — `api/chat.php:211-233` accepts any session ref, no visitor-token/ownership check, and returns the full conversation (C21/C41). *Require the same private-token check `post`/`rate` use; return ok-only.* (WP02)
3. **Suspension no-op** — admin suspend writes `users.status`; login and mobile auth inspect `users.status_level` (A72): suspended users keep signing in. *Single state column + token/session revoke on suspend.* (WP01)
4. **Admin 2FA bypass** — ordinary login (`api/auth.php:46-65`) ignores the configured admin OTP (A13). *Enforce the second factor for every authentication path from one middleware.* (WP01)
5. **Check-in releases escrow early** — `includes/pricing.php:347-395` flips escrow to released and payment to paid on future, unpaid bookings (A53). *Gate on date + captured payment; escrow release moves to checkout (§7 WP05).* (WP10 interim gate)
6. **Gift-card instant credit** — client-declared numeric "gift" values zero any reservation (A58–A61, B10). *Delete client gift-amount intake; redemptions only via authenticated server-side code lookup with balance.* (WP07 interim: reject `gift` input with a validation error)
7. **Dispute withdraw/decide integrity** — counterparty can withdraw a case (D17) and a withdrawn case can be decided (D18). *Owner-only withdraw; decided transitions reject `withdrawn`.* (WP02)
8. **`javascript:` evidence URLs** — dispute evidence links render unsafe schemes (D10). *Scheme allowlist (`http/https`) on store and render.* (WP02)
9. **`agent.php` unguarded 500** — missing chat tables hard-crash the page (T12). *Wrap in the same `tableExists` degradation as diagnose.* (WP31)
10. **Repo secrets** — `includes/config.php` with live DB password, `wal7zkit_jollof.sql` (private rows), `public_html.zip` are tracked. *Rotate the DB password now; untrack and gitignore in WP32; treat credential rotation as a hard prerequisite, not a cleanup chore.* (WP32)

---

## 4. Shared architecture (the dynamic, database-driven core)

Implement these shared services first (they are referenced by nearly every WP). Section numbers double as WP dependencies.

### 4.1 Unified services layer (`includes/`)

Today logic is split across `includes/pricing.php`, `includes/repo.php`, `includes/livechat.php`, `includes/disputes.php`, and duplicated partially in `api/mobile/*`. Create a thin, testable service layer and make **all** entry points (web pages, `api/*`, `api/mobile/*`, install tools) call it — no endpoint composes business rules by hand again:

- `Pricing::quote(listing, dates[], guests, addons, promoCode, giftRedemptionIds)` — date-aware; consumed by search, detail, checkout, host previews, mobile.
- `Availability::nightStatus(listingId, date)` / `Availability::assertBookable(...)` — bookings ∪ `property_calendar` blocks ∪ listing status (paused/pending removed from inventory).
- `Ledger` (post, reverse, balanceOf, reconciliations) and `Settlement` (capture, refund, payout via provider).
- `Identity` (account state, verification, 2FA, session/token lifecycle) and `Access::can($user, $capability, $context)`.
- `Notify::dispatch(event, audience, channels[])` — renders, persists in-app rows, enqueues email/push/SMS/WhatsApp via the outbox (§4.5).
- `Outbox` + `Worker` runtime (§4.5); `Media` (§4.6); `Audit` (§4.8).

**Rule:** `api/mobile/action.php` keeps its transport (bearer auth, replay keys) but its bodies become calls into these services — which is exactly how the mobile-vs-web divergence findings (M03–M12) get closed for free.

### 4.2 One JSON contract

`api/_api.php:64-72` already standardizes some responses, but `api/state.php` returns the mutable state at the root while the client refresh (`assets/js/site.js:333-341`) reads `r.data` — one of the most expensive single bugs in the audit (F83; poisons wishlist/compare/trips/dispute-list UX: B13–B15, U15). Fix once, globally:

- Every endpoint returns `{ok: bool, data: {...}, error?: {code, message}, state?: {...}}`; `state.php` returns `data` = the state document.
- `JL.refresh()` hydrates only from `data`; mutation handlers `await JL.refresh()` instead of local guess-patches.
- Add CI contract test: enumerate every `api/*.php` route, assert response shape keys; a missing `data` fails the build.

### 4.3 Money machine (provider abstraction + ledger)

The `payments` table and `pay_methods` exist; `config.php` already exposes `payments.mode` = `'record_only' | 'paystack' | 'flutterwave'`. `record_only` stays as an explicitly-labelled sandbox for demos, but **booking confirmation and every money label derive from verified provider state**:

```sql
CREATE TABLE payment_intents (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT NULL, kind ENUM('booking','giftcard','deposit','installment','experience') NOT NULL,
  amount_fen BIGINT NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'NGN',
  provider VARCHAR(32) NOT NULL,            -- paystack|flutterwave|sandbox
  provider_ref VARCHAR(191) NULL,           -- access_code / tx_ref
  status ENUM('initiated','authorized','captured','failed','refunded','partial_refund') NOT NULL DEFAULT 'initiated',
  verified_at DATETIME NULL, idempotency_key CHAR(64) NOT NULL,
  payload_hash CHAR(64) NULL,              -- last webhook body hash
  UNIQUE KEY uq_idem (idempotency_key), KEY ix_booking (booking_id), KEY ix_provref (provider, provider_ref)
);
CREATE TABLE ledger_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY, key_name VARCHAR(64) NOT NULL UNIQUE,  -- guest_cash, escrow, host_earnings, platform_fee, gift_liability, loyalty_redeemed, refund_out
  currency CHAR(3) NOT NULL DEFAULT 'NGN'
);
CREATE TABLE ledger_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  account_key VARCHAR(64) NOT NULL, booking_id BIGINT NULL, user_id BIGINT NULL,
  direction ENUM('debit','credit') NOT NULL, amount_fen BIGINT NOT NULL CHECK (amount_fen > 0),
  ref_type VARCHAR(32) NOT NULL, ref_id BIGINT NOT NULL,      -- payment|refund|payout|points|gift|dispute_decision
  idempotency_key CHAR(64) NOT NULL, reverses_entry_id BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ledger_idem (idempotency_key)
);
```

Rules: every entry pair sums to zero (double-entry); reversals reference the original; balances (escrow held per booking, host payable, gift liability, points) are **views over the ledger**; reconciliation job (§4.5) compares provider state to ledger and raises `reconciliation_diff` rows. Webhooks (`api/webhook.php`) verify provider signature (HMAC/MD5 per provider docs), amount, currency and reference before any status change; out-of-order webhooks resolve via a strict status machine (initiated → authorized → captured → {partial_refund, refunded}; no backwards transitions except explicit `failed` from `authorized` timeouts).

Booking state machine (replaces the current free-form `bookings.status` writes):

```
pending_host → payment_required → payment_hold → confirmed → checked_in → checked_out → settled
                     ↓ (host decline / expiry / no payment)          ↓ cancel per policy
                  expired                                        cancelled [refund computed]
```

`payments initiated / record_only` never produces `confirmed` (kills A44); `settled` requires checkout evidence + ledger release (kills A53).

### 4.4 Availability & pricing engine

Single source per night per listing: `bookings` (occupancy, confirmed/checked-in states) ∪ `property_calendar` (owner blocks/overrides — table exists, already written by host-action, never read by guests) ∪ listing status. Add columns if absent: `property_calendar(type ENUM('block','price_override','min_stay'), price_fen, hold_src VARCHAR, source ENUM('host','channel','system'))`. `Pricing::quote()` becomes date-driven:

1. nights = calendar nights; any night `blocked/unavailable/paused` ⇒ quote error `night_unavailable` (kills B03/A51).
2. base = `properties.base_price`; per-night override from calendar; then `pricing_rules` (weekly/monthly/seasonal/long-stay %) applied by rule precedence stored in DB, not client order (kills A52, B31).
3. addons from `addons` table; promos: campaigns unified into `promos` (§WP27) with server-side validation of window/eligibility/usage (kills A68 mismatch of ₦10,548, B11); gift redemptions = list of `gift_redemptions` IDs, not a number (WP07).
4. taxes/fees from `settings` (FX from `fx_rates`), cancellation policy fetched from the **listing row** (kills the client-chosen policy in A47).
5. Quote result is stored on booking creation as frozen snapshot (already partly done) **and revalidated**: totals recomputed server-side at submit; mismatch ⇒ `409 quote_stale`, UI re-quotes (this closes the whole class of client/server drift, F25/F27).

Concurrency (MySQL rail): inside `booking-create` transaction — `SELECT ... FOR UPDATE` on listing, re-run `Availability::assertBookable`, insert `night_holds` rows with `UNIQUE(listing_id, stay_date, active_key)`; SQLite/CI rail: `BEGIN IMMEDIATE` + the same unique constraint (kills double-book race, A45–A47 hardened).

### 4.5 Outbox & workers (makes every "no job exists" finding fixable)

New tables `outbox_events` (event_type, payload JSON, status, attempts, run_at, lease_until, dedupe_key UNIQUE) and `job_locks`. `api/cron.php` (POST-only, shared secret from config `cron.key`, logs last run) drains due events; on shared hosting a cPanel/interval cron hits it; absent that, a tick-at-request fallback drains N events per authenticated request when the last sweep > 60s (bounded, off the response path). Event types (each owned by a WP): `mail.send(+retry)`, `notify.email/push/sms/whatsapp`, `booking.expire_pending`, `hostmsg.trigger`, `waitlist.match`, `price.alert`, `points.settle`, `payout.run`, `channel.ical_pull`, `dispute.sla`, `escrow.due`, `ledger.reconcile`, `sessions.revoke`. All consumers idempotent via `dedupe_key`.

Email reliability (F51): `Mailer` gains SMTP reply-code validation (expect 250/25x/3xx where required per command), timeout, and returns structured results; every call site (invoice, chat transcripts, dispute notices, this branch already expanded them in `includes/livechat.php`/`includes/disputes.php`) logs the result to `outbox_events` and exposes failures in the admin delivery log instead of ignoring them.

### 4.6 Media pipeline (photos, KYC docs, tours, lease PDFs)

`api/upload.php` (multipart, auth'd, CSRF): validate MIME + getimagesize + max bytes per purpose; images re-encoded via GD to strip payloads; store under `storage/uploads/{year}/{month}/{ulid}.{ext}` outside webroot, serve through `api/file.php?k=…` with ownership/URL-token checks; rows in `property_images` (extend: `media_type ENUM('photo','panorama','floorplan')`, `alt`, `sort`, `width/height`), `kyc_documents` (owner-only + reviewer access via `api/file.php?scope=kyc`, audit-logged). Thumbnails generated at upload (listing card sizes). This single package feeds F06 gallery, F57/F58/F60, F09 tours/plans, F19 vault, F52 attachments, F37 lease PDFs.

### 4.7 Identity, sessions & roles

- **Single account state:** one `users.account_status ENUM('active','pending','suspended','banned','erased')`; drop `status_level` after backfill; `api/auth.php`, `includes/auth.php:219-235`, `api/mobile/_mobile.php:106-124`, host-agent gates, and admin actions all consult `Access::canLogin()/canAct()`. Suspension deletes `api_tokens` rows and session records ⇒ instant everywhere-effect (closes F72, part of A71/A72, and gives C34 agent-pause its revocation for free).
- **Sessions:** move from pure cookie semantics to a `sessions` table (user, UA, IP, created/last_seen, revoked_at) so "revoke all sessions", "suspend", "password changed" and "agent paused" are one shared operation (F16/F21/F22 depend on it).
- **Capabilities, not role strings:** `roles(id, key)` × `role_permissions(role, capability)` — capabilities like `admin.csv.users`, `dispute.decide`, `chat.agent`, `host.manage(listing)`. `users.role` stays only as a coarse display enum. Co-hosts (F64), admin staff (F90/D29) and chat-agent limits (F88) all become rows here.
- **Verified-ness columns:** `users.email_verified_at`, `phone_verified_at`, `kyc_status/kyc_verified_at` — written **only** by their challenge flows (WP12/WP14); `includes/view.php:104-118` "inferred verified" removed (F20).

### 4.8 Audit, flags & settings

- `settings` (exists) becomes the typed key/value store (JSON values, updated_at, updated_by) consumed via `Repo::setting()`; anything currently in `config.php` that is operational (fees %, tax rate, loyalty thresholds, dispute SLAs, chat sweep intervals, payout schedule) moves here so admin can change behavior without deploys — this is the "database driven" requirement applied to policy.
- `feature_flags(key, enabled, gated_ui_copies)` + helper `JL_flag('escrow_v2')`; page templates and the SPA manifest endpoint expose flags; R1 claims gate reads them. New modules launch dark and flip on after their suite passes.
- `audit_log` hardening (F79): event catalog (constant list, every admin/state-transition action logs actor, target, before/after digest); **chain integrity**: `prev_hash`/`entry_mac = HMAC-SHA256(key, entry‖prev)`; app MySQL user loses `UPDATE/DELETE` on `audit_log` + `ledger_entries` (grants documented in WP32 runbook) so "append-only/immutable" is true, not copy.

### 4.9 Migrations, install & packaging

`install/` becomes the real installer (kills F84): repo ships `database/schema.sql` (full current schema incl. support-module ten tables: `chat_agents`, `chat_agent_departments`, `chat_canned`, `chat_departments`, `chat_events`, `chat_messages`, `chat_sessions`, `dispute_categories`, `dispute_events`, `disputes`) + `database/seed.sql` (public demo subset only) + versioned `install/schema/*.sql`. `install/index.php` runs schema → seeds → module migrations in order and writes `installed.lock` (already respected by `install/migrate.php:36-71`); `install/` self-locks everything except `diagnose.php` (admin-only) after install (kills the exposure risk in F92); the private dump `wal7zkit_jollof.sql` and `public_html.zip` leave the repo (WP32). Migration runner rules per T06 lesson: seed inserts always `WHERE NOT EXISTS`; unique-key repair dialect-aware (MySQL `ALTER … ADD UNIQUE` vs SQLite `CREATE UNIQUE INDEX`); post-run assertions (expected row counts, created tables) printed and non-zero exit on mismatch; a `--dry-run` flag composes the same pipeline.

---
## 5. Roadmap

| Phase | Theme | Work packages | Exit criteria (re-audit language) | Est. |
|---|---|---|---|---|
| 0 | Lockdown & honesty | WP01–WP03 | C32–C34, C21/C41, A12–A14, A71/A72, T12, D10/D17/D18 pass; no UI claims a flagless capability | 17 d |
| 1 | Money core | WP04–WP07 | A44/B37/B39 flip; sandbox-gateway E2E booking→capture→refund→payout reconciles; gift redemptions atomic | 38 d |
| 2 | Booking integrity | WP08–WP11 | A45–A47, A51, A52, A65, A66, B03, B11, B38, M08 pass; double-book race rejected on MySQL and SQLite | 29 d |
| 3 | Trust & identity | WP12–WP16 | F16/F19/F20/F21/F22/F55 suites pass; verified badges only after real challenges | 34 d |
| 4 | Customer tools & comms | WP17–WP22 | B13–B15, A16–A21, A64, A19, loyalty/referral/waitlist suites pass; mail sandbox receipts | 36 d |
| 5 | Owner workspace | WP23–WP26 | A24/A25, A30/A34–A42, B28–B31, F62–F69 suites pass; uploads round-trip; payouts reconcile in sandbox | 44 d |
| 6 | Back office complete | WP27 | A31–A33, A55–A57, A67–A71, A76, R08 pass; append-only audit proven by grants + MAC verify | 12 d |
| 7 | Support modules finished | WP28–WP31 | Remaining C/D/U failures pass; T06 double-run idempotent (SQLite + MySQL); T12 guarded | 15 d |
| 8 | Platform reach & polish | WP32–WP37 | F84 clean-install rehearsal; axe 0 on core flows; M01–M12 pass; discovery suites | 37 d |

Sequence rationale: Phase 0 removes active harm; Phase 1 builds the ledger everything else hangs on (booking integrity without money is theater, and escrow labels without a ledger would have to be rebuilt); Phases 2–4 make the core transaction honest and self-healing; Phases 5–7 finish supply side, back office, and the new support modules; Phase 8 broadens (install, SEO, a11y, mobile, corporate, discovery). Packages within a phase are parallelizable; across phases, respect the named dependencies.

---

## 6. Phase 0 — Lockdown & honesty (17 dev-days)

### WP01 — Authentication, sessions, suspension, roles (8 d)

**Converts:** F70 (P0), F72 (P0), F88 partial (C32/C33/C34 role escalation + pause), groundwork for F16/F21/F22/F64.
**Failing tests to flip:** A12–A14 (admin OTP bypass), A71/A72 (suspend no-op), C32/C33/C34 (agent→admin escalation survives pause).

1. **Kill the role write in live-chat staff management.** In `includes/livechat.php` saveAgent (≈976–990 and the user-provisioning path): remove any `INSERT/UPDATE users ... role='admin'`. New agents are provisioned as ordinary members; console capability comes solely from a `chat_agents` row + capability `chat.agent` (§4.7). If the referenced user already exists, agent-save updates only display fields. *(C32/C33 by construction.)*
2. **Unify account state.** Migration: copy `users.status_level` semantics into new `users.account_status` (§4.7), then every read flips to it: `api/auth.php` login, `includes/auth.php:219-235` bootstrap gate, `api/mobile/_mobile.php:106-124` token verify, host/admin gates. `api/admin-action.php:54-62` suspend/activate writes `account_status` + revokes sessions and `api_tokens`.
3. **Sessions table + revocation.** Add `sessions` (§4.7); login creates, logout/pw-change/suspend/pause-agent revokes; admin "revoke all sessions for user" button on the users table row. Agent pause (chat settings) revokes that user's console session immediately *(C34)*.
4. **MFA middleware.** Replace the static `security.admin_otp` with TOTP enforcement (§WP13) but ship Phase 0 as: `Auth::requireFactor()` called by **every** login path (web, admin-login, mobile) when the account demands a factor — including the ordinary login form for admins *(A13/A14)*. Admin CSV capabilities (§4.7) move from `role==='admin'` string checks to `Access::can('admin.csv.users')`, so an escalated-but-paused account can never reach exports *(C33)*.
5. **Login hardening.** `login_attempts` (exists) gains: exponential backoff per email+IP, lockout notice, and captcha field only after N failures (no captcha service — image-free math prompt acceptable interim).

**Acceptance:** A13: admin login via ordinary form without OTP ⇒ 403 `factor_required`. A72: suspend ⇒ immediate 401 on next web request and mobile token use; re-login blocked with `account_suspended`. C32–C34: agent password save never touches `users.role`; paused agent's open console session dies ≤ next poll; escalated synthetic admin cannot read `/api/report.php` exports. `node --check` + PHP lint clean; session revoke audit rows present.

### WP02 — Support-module P0 authorization hotfixes (5 d)

**Converts:** F87 (close/leak half), F88 (scope/validity half), F89 (withdraw/decide/scheme half), F92 (T12 guard). Remainder of these features is finished in WP28–WP31.
**Failing tests to flip:** C21, C41, C14, C15, C17, C30, C38, C39, C40, D10, D17, D18, U19, T12.

1. **Chat visitor endpoints token-checked uniformly** (`api/chat.php:91-112, 211-233, 249-262`): every visitor call — post, typing, rate, close — requires `X-Chat-Token` matching `chat_sessions.visitor_token_hash`; `close` returns `{ok:true}` only; transcript never serialized to non-owners (agents via queue scope; visitor via token). *(C21/C41.)*
2. **Typing/rate state checks:** typing beacon on closed/queued session ⇒ 409; rate twice ⇒ 409 *(C14/C15)*.
3. **Agent scoping at the service layer:** every read (`transcript`, `open`, list) goes through `LiveChat::assertAgentScope($agentId, $sessionId)` using `chat_agent_departments` membership + agent `is_active` *(C17)*.
4. **Canned responses:** list and "use" filter `is_active=1 AND (scope='team' OR (scope='personal' AND agent_id=:me))` — sending another agent's personal reply or a disabled reply ⇒ 400 *(C38/C39)*.
5. **Agent form integrity:** PATCH semantics — absent fields keep stored values (`max_chats` reset bug *(C30)*); validate fully before persisting; on validation failure persist nothing and echo field errors *(C40)*.
6. **Dispute integrity:** `withdraw` accepts claimant only (403 counterparty) *(D17)*; `decide` rejects `withdrawn`/`closed` cases with 409 *(D18)*; reopen button sends `status:'in_review'` payload the endpoint understands — align `assets/js/chat.js:914-950` with `api/dispute.php` transitions *(U19)*; evidence link stores only after `filter_var($u, FILTER_VALIDATE_URL)` + scheme allowlist, and renders with `rel="noopener"` *(D10)*.
7. **`agent.php:14-22`** wraps its `chat_agents` lookup in the `tableExists()` + try/catch degradation used by `install/diagnose.php` — missing module table ⇒ the "run migrate first" notice, never a 500 *(T12)*.

**Acceptance:** the 14 listed checks flip pass in the module harness; new: X01 forged token on close ⇒ 403; X02 cross-queue transcript read ⇒ 403; X03 disabled canned reply send ⇒ 400.

### WP03 — Claims gate & honest states (4 d)

**Converts:** the deceptive half of F17, F19, F21, F22, F35, F37, F38, F45, F49, F54, F67, F69 (toasts/labels with no engine), and the fixed-code display in F31.

1. **Ship `feature_flags` + settings plumbing** (§4.8) and an `/api/flags` snapshot consumed at boot by `includes/view.php` and `site.js`.
2. **Gate every unimplemented affordance** behind its flag (default `off`): social sign-in buttons (F17), Manage Documents / selfie vault (F19), Manage Security / "SMS + authenticator" label (F21), export/erase buttons (F22), smart-lock copy + fixed `4471#` code (F35; trips display switches to the booking's stored code or "issued at check-in"), lease "signed & stored" copy (F37), calendar/wallet "connected" states (F38), loyalty redeem buttons (F45 — disabled + "coming" state instead of success toast), push/SMS/WhatsApp toggle claims (F49), "AI: all live" copy (F54), channel "connected/last-sync" labels (F67 — shows *Unconfigured* until a real round-trip), inspection/photography/AI-rewrite buttons (F69). Off ⇒ controls render disabled with an honest tooltip or are removed from DOM; **no toast masquerades as an operation**.
3. **Search-and-destroy rule for demos:** grep `site.js` for hardcoded demo constants beyond these (referral stats, review stars default, COLLECTION_MAP, map pins, gift `60000` code) and register each as a flag or replace with data fetch now (full data work in WP37/WP21/etc — here only the *fake-value removal* so nothing regresses later into "looks real").
4. `help/faq/roadmap` content that promises removed capabilities is updated in `cms_blocks`/`faqs` rows (public-facing honesty is content, edit it in DB, not code).

**Acceptance:** with flags off, the account page renders zero "verified/connected/ready" claims for gated features (DOM assertions, axe-friendly text), B24/B25/B26/B27 re-run expecting honest disabled states; the future WPs flip flags in their acceptance tests.

---

## 7. Phase 1 — Money core (38 dev-days)

### WP04 — Payment provider capture & verification (12 d)

**Converts:** F28 (P0, Not working) — the root of A44/B37. **Enables:** WP05/06/07/20/21/25.

1. `includes/payments.php`: `Provider` interface (`initiate`, `verify`, `refund`, `transfer`, `resolveAccount`, `webhookAuth`) with `SandboxProvider` (local: auto-capture on poll for tests; clearly labeled in UI), `PaystackProvider`, `FlutterwaveProvider` (official HTTP APIs; test keys from `config.payments`). Config gains `webhook_secret`, `plan_id` optional.
2. Checkout submit: `booking-create` (currently `api/booking-create.php:1-36`, `pricing.php:180-230`) stops writing "initiated/paid/escrow-held/confirmed" itself. It: creates booking in `payment_required` → server-side quote (§4.4) → `payment_intents` row (idempotency key = booking nonce) → provider initiate → returns redirect/access_code. Client (`site.js:1460-1506`) becomes a *handler*: redirect + poll, never a decider.
3. Return URL `confirm.php` + webhook `api/webhook.php`: signature verify → amount/currency/email/reference match → `captured` (compare against stored quote — mismatch ⇒ flag + hold). Booking advances to `confirmed` only from `captured` (or from host-approval rules when listing requires it). Duplicate webhooks no-op via idempotency. `pay_methods` rows gate which providers render.
4. `record_only` mode: kept for demos, but booking lands in `confirmed_demo` (distinct status), UI shows a persistent "Demo — no funds moved" banner (flag `payments.demo_label`), and audit rows say so. Reconciliation job marks demo bookings excluded from revenue everywhere (host dashboards §F63, admin KPIs).
5. Mobile: `api/mobile/action.php` booking path calls the same service (its replay-protection becomes the provider idempotency key).

**Acceptance:** new P-series: P01 sandbox booking → poll → confirmed *only after* capture event (A44 flips to expecting this); P02 webhook with wrong signature/amount ⇒ 400 + no state change; P03 replayed webhook idempotent; P04 cancel-before-capture leaves no `paid` label; B37 (checkout wizard E2E) passes with capture-gated confirmation; demo mode never writes `captured`.

### WP05 — Ledger, escrow machine, refunds, invoices, dispute executor (14 d)

**Converts:** F29 (P0), F33 (P0), F36 (P1), the money half of F90 (D19/D20/D24/D30/D31/D34); gives F44 honest settlement (§ WP20).

1. Create `ledger_accounts/ledger_entries` (§4.3). Posting points: capture (`guest_cash→escrow` + `platform_fee`), refund, payout, gift redemption, points cash-out equivalents. **Remove** direct `payments.status`/`bookings.escrow_status` label writes — escrow state becomes `SELECT status FROM escrow_view` (derived).
2. Cancellation engine (`includes/pricing.php:357-388` rewritten as `Cancellations::compute(booking, now)`): policy from the **listing** row (`settings` keys per policy: windows/percentages, editable in admin — DB-driven); computes refundable (cash vs gift vs points split); executes provider refund (webhook-verified); posts reversing ledger + reversing `points_ledger` rows (fixes A63/A64); releases nights (delete `night_holds`); emits `booking.cancelled` outbox (emails host, updates waitlist matching §WP19).
3. Check-in/check-out gates (§WP10 detail): at `checked_out`, `escrow → host_earnings + platform_fee` posting; `confirmed` never auto-releases at check-in (A53 dead: escrow release only at checkout event with evidence row).
4. Invoice/receipt (F36): `api/invoice.php` renders from frozen quote **plus ledger posting state** (paid amount, refunds, outstanding); "receipts" list shows only real capture events (no invented rows, `site.js:3286-3297` demo receipts deleted); wrong-owner GET returns 403 not 200 (both endpoints: `api/invoice.php:127-149`, `payments` page fetch).
5. Dispute decision executor (upgrade `includes/disputes.php:608-707` record-only refunds): `Decide` becomes: validate transition (withdrawn ⇒ reject, D18 kept) → compute refund = min(amount, captured − already refunded) (D31) → if `no_action`, ignore any typed amount (D34) → open **transaction**: update dispute + booking status per decision map (D30: full refund also sets booking `cancelled`, never a half-migration) + ledger entries + refund intent (WP04 provider call queued via outbox) + fraud flag (keep D21 behavior) + per-party notifications (with §WP30 visibility rules) → idempotency key per (dispute, round) so re-decide returns the stored result, never 500 (D24). `gateway` column stores `sandbox|paystack|…` and a `settlement_status` (`recorded → initiated → verified`); UI reads it honestly: "Refund recorded — awaiting transfer" until provider confirms (D19/D20 interim; auto-verified in sandbox).

**Acceptance:** P05 cancel at policy window edge ⇒ refund amount equals policy math (server test of `Cancellations::compute` table-driven incl. 364 cases of boundary hours); P06 points reversed on cancel (A63/A64 flip); P07 ledger sums to zero across a full booking lifecycle incl. partial refund; P08 invoice 403 cross-owner; D19/D20/D24/D30/D31/D34 flip in sandbox mode; escrow label never written outside ledger.

### WP06 — Deposits, split & installment collection (6 d)

**Converts:** F30 (P0, Not working).

1. Listing gains deposit policy in DB: `properties.deposit_mode ENUM('none','pct','flat'), deposit_value, split_min_nights` (settings default 30). Checkout quote splits lines (`pricing.php:93-94,194-228`): today-now portion vs `installment_plans` row (booking, due_date, amount_fen, status, payment_intent_id). Eligibility: server rejects `split` unless nights ≥ `split_min_nights` (fixes B39 three-night request) — client menu option hidden from flags/quote response.
2. Deposit hold: provider reality check — Nigerian rails have no card auth-hold; model the hold as **captured-to-escrow refundable line** (already honest via ledger) and document it; UI copy: "secured deposit", never "hold pending". If a provider later supports pre-auth, `Provider::hold/release` interface slot exists.
3. Due-date enforcement: worker `installment.due` (outbox) → reminder email (72h before, on due) → grace (settings `grace_days`) → booking `expired` + nights released + `ledger.reversal` of prior portion to refund queue (policy: non-refundable share per listing cancellation terms). All transitions event-logged.
4. `payments` page shows the plan with per-tranche invoice links (WP05 invoices), and admin can waive/re-date a tranche (audited).

**Acceptance:** P09 split on 3-night booking ⇒ 400 `split_not_eligible`; P10 sandbox deposit+balance lifecycle confirms booking only after final tranche captured (unless policy = confirm-at-deposit, a listing setting — flag `booking.confirm_on_deposit`, default off); P11 overdue tranche expires + releases nights atomically; B39 flips.

### WP07 — Gift cards, funded and atomic (6 d)

**Converts:** F40 (P0). Flips A58–A61, B10.

1. **Purchase flow:** `giftcards.php` buy form → payment intent (WP04, `kind='giftcard'`) → on capture, mint `gift_cards` row: unique code (server-generated `JL-XXXX-XXXX-XXXX`, hash stored for lookup-by-code + display code), face value, fee, `status='active'`, funded ledger posting (`platform_fee`, `gift_liability` credit). **Cards are only ever created by a captured payment** — admin "issue" requires a payment or a credited `platform_grant` ledger entry (audited, R8). Remove `api/giftcard.php:9-68` "create with balance now" path.
2. **Redemption:** new `api/giftcard.php action=redeem` (auth'd): validate code/status/owner-eligibility (own card, or recipient claim), apply to quote: returns `redemption_id, amount_applied` — stored pending on the booking creation (idempotency; expired pending bookings release the redemption after sweep). On capture: `gift_liability → escrow` posting; balance decrements in `gift_cards` (atomic, row-locked); over-spend rejected. Checkout stops accepting `gift` numerics from the client entirely (`pricing.php:69-71` param removed; A58/A59/A60 structurally impossible).
3. Transfer/claim: recipient signs up (or claims while logged in) to redeem; `gift_cards.owner_id` binds; recipient claim flow with email invite (outbox).
4. Lookups: balance check endpoint (rate-limited per IP, code+last-4 verification to avoid enumeration); admin void/freeze with audit.
5. Fix `giftcard.php` purchase page copy ("cards fund themselves") by replacing with the real flow; the old demo code `60000` path deleted (WP03 already hid it).

**Acceptance:** P12 unfunded creation endpoint gone (410); P13 redemption debit + booking discount match to the fen; P14 double-spend race ⇒ one redemption succeeds (both rails); P15 arbitrary client gift value rejected (A58–A61, B10 flip); balance 0 ⇒ status `exhausted`, ledger still ties.

---

## 8. Phase 2 — Booking integrity (29 dev-days)

### WP08 — Date-aware quote engine & checkout parity (8 d)

**Converts:** F25 (P0), F27 (P0), the price half of F61. Flips A43/B08/B09/B37/B11/A52/A68.

1. Rebuild `Pricing::quote()` per §4.4.1–5: signature `quote(array $listing, array $dates, int $guests, array $addons, ?string $promo, array $redemptions)`; consume `property_calendar` overrides (A52: the ₦400,000 override now prices), `pricing_rules` (long-stay/weekly), promos/campaigns via `Repo::promo` with window+eligibility+usage-count checks (A68/B11: JOLLOF10 math equals PHP, to the fen), and `settings` taxes/fees.
2. New endpoint `api/quote.php` (POST listing, dates, guests, addons, promo, redemption ids) → canonical breakdown object. **The site.js quote path (423-449) is deleted** — the wizard renders server breakdown only; recompute on every input change (debounced) and at submit; `409 quote_stale` re-quotes inline (fixes the ₦ delta class permanently).
3. Wizard state (B08/B09/A43): pass selected dates/guests through detail→checkout via the quote call (session store `JL.pendingQuote` keyed by listing) — no more lost `stayDates`; step-1 input rebinding fixed (event delegation on the wizard root instead of per-render binds); back-navigation keeps values because values live in the quote request object, not only in DOM.
4. Long-stay & min-stay: minimum-stay from listing (server validation at quote: nights < min ⇒ `422 min_stay`, message includes the listing's own number); experience/addon price parity via `addons` rows.
5. Host-facing preview: host dashboard calendar shows the same computed nightly price guests see (single engine, two views — closes the "host believes ₦400k, guest pays ₦155k" class).

**Acceptance:** Q-series: Q01 detail→checkout preserves dates + selected policy shown (B08/B09 pass); Q02 override+rule pricing parity server vs rendered client (A52, B31 flip); Q03 promo math identical at 300 sample inputs (property-based test, A68/B11 flip); Q04 submit with tampered totals ⇒ 409; Q05 min-stay enforced on API (A47/B38 client-chosen-policy half: policy field removed from request — server takes listing's).

### WP09 — Availability, blocks, capacity, concurrency (7 d)

**Converts:** F08 (P0), F26 (P0), F60 enforcement half. Flips A45–A47, A51, A65, A66, B03.

1. `Availability::nightStatus`: status ∈ {open, booked, blocked, min-stay-gap}; from bookings ∪ `property_calendar` ∪ listing status. Frontend calendars (site.js:1100-1134, 1584-1592) consume `api/calendar.php?listing=&month=` (new, DB-driven, replaces sold-out demo ranges) — host calendar and guest calendar become the same component fed by this endpoint *(F08)*.
2. `booking-create` wraps in transaction (§4.4.5): `assertBookable` (any blocked/booked/paused night ⇒ 409 with offending dates), capacity: `guests > listing.max_guests` ⇒ 422 (no silent clamps — A46), occupancy of extra guests priced only if listing allows (`settings` extra-guest price), past-date check server-side again, and `paused/pending` listing ⇒ 403 `listing_not_bookable` *(A65/A66/B03 flip)*.
3. Host blocked days become unbookable everywhere: web wizard, mobile (`api/mobile/action.php` booking), and admin-override action (audited + flagged `override` in booking_events).
4. MySQL rail adds `night_holds` unique constraint as the hard guard; SQLite rail relies on the constraint + `BEGIN IMMEDIATE`. Load test: N=20 parallel creates on the same 3-night window ⇒ exactly one wins (both rails).
5. Listing pause from host UI (§F60) flips availability immediately via a `listing.updated` outbox event invalidating quote caches (cache: per-listing availability memo with 60s TTL, invalidated on write).

**Acceptance:** R01 blocked day ⇒ quote error + calendar red cell (A51, B03 flip); R02 paused-listing direct API booking ⇒ 403 (A66); R03 over-capacity ⇒ 422 (A46); R04 race test single-winner; R05 calendar API equals availability DB truth for 40 random listings/months.

### WP10 — Booking lifecycle: confirm, trips, check-in, modifications (9 d)

**Converts:** F25 (gating half), F31 (P1), F32 (P1), F34 (P0). Flips A53/A54, A62, B15 display half.

1. Confirmation gate: `confirmed` iff (`captured` payment OR listing allows request-without-payment AND host approves) — per listing `booking_mode` (instant|request, already seeded concept in properties) + flag; UI copy on wizard matches mode (R1).
2. Trips page (site.js:1713-1782): every field from booking API (status from §4.3 machine, access code from §WP26.5, host name from users row, cancellation policy from listing) — **no hardcodes**; refresh via WP17 contract makes status live after mutations (B15).
3. Check-in/check-out service (`includes/pricing.php:347-395` → `Bookings::transition`): host action `checkin` requires (a) `today ≥ checkin_date − allow_early_hours (settings, default 0 ⇒ early check-in rejected, A53)`, (b) payment `captured` (or `request` listing approved), (c) booking status `confirmed`; records `checkin_at`, geo/device optional payload; `checkout` triggers escrow settle (§WP05.3). Each transition = booking_events row + audit + notify(guest) via outbox. Wrong window ⇒ 409 with machine-readable reason.
4. Modification workflow (A62): `booking_changes` table (booking, proposed dates/guests, delta_quote, status pending_host|accepted|declined, expiry 72h). Guest proposes → server re-quotes both states, stores delta; host accepts → apply: nights re-validated via WP09, booking updated, delta charged via payment intent (or refunded via WP05), all-or-nothing transaction; decline leaves original intact; expiry worker reverts `pending_host`. "Rates update instantly" copy only after capture (R1).
5. Notifications: transition emails through outbox templates (host and guest), including modification events — feeds §WP18.

**Acceptance:** L01 future unpaid booking check-in ⇒ 409 (A53/A54 flip: only date-valid paid bookings check in); L02 modification accept reprices and reconciles ledger (A62 flips); L03 trips UI shows server status immediately after cancel action (B15 with WP17); L04 early check-in honored only if listing sets `allow_early>0` (settings-driven).

### WP11 — Host approve/decline & expiry (5 d)

**Converts:** F62 (P0, Not working). Flips M08; replaces admin-only transition.

1. `api/host-action.php` gains `decideRequest(bookingId, action=approve|decline, reason?)`: ownership check (listing owner or co-host capability §WP24.4), booking must be `pending_host`; approve → quote revalidation (WP08) → status `payment_required` (guest pays) or `confirmed` (pay-first flows) → notify; decline → nights released → reason in booking_events → notify. `host.php` "Requests" tab renders actionable cards (approve/decline/decline-with-alternative-dates → creates WP10 modification proposal automatically) instead of view-only list (site.js:2802-2816).
2. Mobile: `api/mobile/action.php:72-93` — remove guest self-approve; add host-role endpoints calling the same service (auth via §4.7 capability `host.decide`). *(M08 flip.)*
3. Expiry sweep: worker `booking.expire_pending` (outbox cron): `pending_host` > listing `hold_hours` (settings, default 24) ⇒ auto-decline `expired_hold`, release nights, notify both; `payment_required` > 20min (quote TTL from settings) ⇒ expire payment intent.
4. Admin `api/admin-action.php:151-158` manual transition kept but logs `override_by_admin` and requires reason (audit).

**Acceptance:** H01 approve E2E: request→approve→pay→confirmed (sandbox); H02 decline releases nights immediately (quote of same window succeeds); H03 guest mobile approve endpoint ⇒ 403; H04 expiry sweep flips state exactly once for 50 seeded stale rows; auto-decline email in outbox log.

---
## 9. Phase 3 — Trust & identity (34 dev-days)

### WP12 — Email/phone verification & password recovery (5 d)

**Converts:** F16 (P1), F20 (P0).

1. Tables: `verify_tokens(id, user_id, purpose ENUM('email','phone','password_reset'), channel_hash CHAR(64) UNIQUE, code_hash CHAR(64), expires_at, consumed_at, attempts TINYINT)`. Codes: 6-digit OTP for phone/email-link token for email (random 32-byte, single use, TTL 60min email / 10min OTP, max 5 attempts, rate limit 3/hour per user+purpose via `login_attempts`-style counter).
2. Email flow: on registration, send link `auth.php?verify=token` → endpoint marks `users.email_verified_at` (only here — §4.7) + welcome notification. Resend action on account page. Phone: enter number → OTP → `phone_verified_at`.
3. Password recovery (F16): "Forgot password" → email token link → `reset.php` (new page): set new password (length/breach-check vs current hash), revoke all sessions (§4.7), force logout everywhere, audit row. No password content in emails, ever.
4. Enforcement policy in `settings`: `require_verified_email_for` = [booking, review, message] (default booking+review) — checks added in the corresponding services (soft-enforce: block action with "verify email first" deep-link rather than blocking login).
5. Remove `includes/view.php:104-118` inference ("phone present ⇒ verified", admin label ⇒ email verified); badges read `*_verified_at` only.

**Acceptance:** V01 reset token single-use + expiry enforced (forged/expired/second-use all rejected); V02 sessions revoked after reset (old cookie ⇒ 401); V03 verified badges flip only post-challenge (F20 checks pass); V04 unverified email cannot leave a review (settings on).

### WP13 — TOTP 2FA for everyone, admin-required; optional social sign-in (7 d)

**Converts:** F21 (P0), F17 (P2 — removal now per WP03, full option later).

1. TOTP (RFC 6238, 30s, SHA-1, 6 digits — implement in `includes/totp.php`, no composer deps; constant-time compare ±1 window). Enrolment: `api/account.php action=2fa_start` returns secret + `otpauth://` URI + QR (server-rendered SVG QR via a pure-PHP generator, stored in `settings` cacheable) → user proves one code → `users.totp_secret` (encrypted at rest with app key from config `security.encryption_key`) + `recovery_codes` (10× hashed single-use, shown once).
2. Enforcement: `Auth::requireFactor()` (§WP01.4) — admin role always; members when enrolled (optional account setting; admin can force via settings `require_2fa=all`). Applies to web, admin-login, and mobile (mobile: `api/mobile/auth.php` gains `purpose=2fa` step + client code entry; recovery codes accepted).
3. Replace `config.security.admin_otp` shared code entirely (deprecated in sample; `install` warns if legacy key present). Static-OTP code path deleted so A12–A14 semantics change to per-user TOTP.
4. Security centre page (account): enrolled factor list, enrol/unenrol (re-verify password), session list w/ revoke, recovery codes regeneration. F21 "Manage Security" button becomes this panel (flag `account.security` flips on with WP).
5. **Social sign-in (deferred slot):** `auth.php?action=oauth_start&provider=google` pattern + `oauth/callback.php` (state=nonce, exchange, email verified passthrough, account link/unlink in security centre) implemented against Google OAuth only, flag `auth.oauth.google` default off (needs the owner's client ID — external prerequisite §14). Okta SSO and phone-sign-in stay flagged off; buttons hidden (WP03) until a paid configuration exists. No stubs shipped "visible".

**Acceptance:** S01 TOTP enrol→login demands factor (admin: cannot bypass via ordinary form — A13 stays fixed with real TOTP); S02 wrong/expired code rejected, ±1 window accepted; S03 recovery code single-use; S04 unenrol re-verifies password; S05 mobile 2FA round-trip; S06 (optional slot, when configured) Google callback creates/links account with `email_verified_at` set (Google-verified) — else stays dark.

### WP14 — KYC: documents, selfie, reviewer workflow (8 d)

**Converts:** F19 (P0, Not working). Depends on §4.6 media pipeline.

1. Tables: `kyc_documents(id, user_id, kind ENUM('gov_id','proof_of_address','selfie','business_reg'), storage_key, mime, bytes, sha256, status ENUM('uploaded','in_review','verified','rejected'), review_note, reviewed_by, reviewed_at, expires_at)`; `users.kyc_status` derived from verified `gov_id` (+ `selfie` match if policy `kyc.require_selfie_match=true`).
2. Upload (account → Manage Documents, flag `account.kyc`): multipart to `api/upload.php?scope=kyc`; server-side magic-byte + size checks; encrypted-at-rest option (`sodium_crypto_secretbox` with storage key) — storage outside webroot; viewer `api/file.php?scope=kyc&doc=` allows owner + `kyc.review` capability only, every view audited (R8).
3. Reviewer workflow in admin Users (extends §WP27.1 user drawer): queue of `in_review` docs, side-by-side selfie/ID viewer, approve/reject with mandatory note; approve writes `kyc_documents.status='verified'` + `users.kyc_verified_at` + notifies user (outbox); reject allows resubmission (attempts counter in settings).
4. Selfie liveness: **no biometric SDK is claimed** — Phase-3 implementation = explicit photo upload + human face match against gov-ID portrait by the reviewer (this is a real, auditable workflow, and the copy says exactly that). SDK face-match slot: interface `FaceMatch::score()` returns null when unconfigured; only then may "automated" copy appear (R1).
5. Remove `api/admin-action.php:48-52` bare flag writes: admin "verify" opens the reviewer queue instead (one true path); `site.js:2197-2200` toast path deleted.
6. Exposure policy: verification required before hosting a listing or first booking > settings `kyc.threshold_fen` (checked in listing-publish and payment-capture services; soft-block = in-product prompt).

**Acceptance:** K01 upload→review→verify full loop (doc states transition only via reviewer); K02 owner-only file access (other member + anonymous ⇒ 403; all views in audit log); K03 admin "verify" no longer sets flags without documents (queue requires doc rows); K04 booking above threshold by unverified account ⇒ action-required response; badges honest (F19 "unearned claims" check passes with flag off/on both).

### WP15 — Profile editing, preferences, data export/erasure, localization (8 d)

**Converts:** F18 (P1), F22 (P1), F24 (P2).

1. **Profile edit (F18):** account page gets a real form (name, phone, email-with-verify-flow, photo via media, city/country free-text → `users` + `user_prefs.avatar_key`); `api/account.php:30-41` accepts the same field set (whitelist) that display uses; email change ⇒ new-verify-token to target address, applies on confirm (never instant).
2. **Preferences persisted separately (F18):** `user_prefs` keys become typed rows (already key/value table — enforce stable keys: `pref.currency`, `pref.language`, `pref.marketing`, `pref.whatsapp`, `pref.notifications.*` aligned with WP18 vocabulary). Currency keeps working; language now round-trips; marketing/WhatsApp checkboxes write DB and display from DB (no more currency-only saves).
3. **Export (F22):** `api/account.php action=export` builds a personal data package (JSON: identity, prefs, bookings + events, payments + ledger refs, reviews, messages, disputes, chat sessions, loyalty, gift cards, waitlist, notifications) via a per-table allowlist constant (`DataExport::TABLES` — single reviewable place); returns zip through `api/file.php?scope=dsar`; emailed on completion; heavy builds via outbox job + progress banner. Password re-verify required (step-up).
4. **Erasure (F22):** `account_erasure_requests(id, user_id, status, window_confirmed, completed_at)`: soft window (settings 14 days, cancelable by login), then anonymization job: `users` PII fields replaced with hashes + `account_status='erased'`; child rows keep referential integrity with anonymized display (bookings retain financial truth for tax — `settings.retention.finance_years`, erasure report states it); ledger untouched (compliance note). Admin DSAR log view; legal-review gate flag `compliance.erasure` default off until counsel signs (§14).
5. **Localization (F24):** `i18n_strings(locale, key, text)` + `i18n_packs(locale, enabled)`; server chrome (`includes/view.php`) and email templates translate via `__(key)` fallback en; SPA strings move to `/api/i18n?locale=` dictionary consumed at boot (cache in `localStorage` with version). First pack: `en` (identity) + `yo` and `fr` demo-translation of checkout/account chrome to prove the pipeline (content translation = ongoing editorial task, tracked as data, not code). Language switch (site.js:2212-2216 + account select) sets `pref.language` and reloads dictionary + server render; `<html lang>` set server-side.

**Acceptance:** T01 profile fields persist and display (A74 extended); T02 each pref key round-trips independently; T03 export contains rows for every allowlisted table for a fixture user (fixture = synthetic multi-activity account) and 403s for other members; T04 erasure window + cancel works; erased user cannot log in; finance rows survive with anonymized counterparties; T05 `fr` locale renders chrome + dictionary endpoint contract-tested; missing key falls back to en, never blanks.

### WP16 — Community safety: reports, blocks, emergency (6 d)

**Converts:** F55 (P0, Not working). Flips B27.

1. `reports(id, reporter_id, target_type ENUM('user','listing','review','message','dispute'), target_id, category (settings list), detail, status ENUM('new','reviewing','actioned','rejected'), assigned_admin, resolution_note, created_at)` — "Report concern" buttons (site.js:1976, 2223-2229, listing/review/message surfaces) POST here (auth'd, rate-limited per user+target); email host notification only *if* configured, but success UI reflects the stored case (id shown).
2. Admin Cases view (part of §WP27 fraud/queues): filter by category/status; actions: dismiss (note), remove content (calls listing/review state transitions — audited), suspend user (WP01 flow), ban from listing (block relation).
3. `blocks(user_id, blocked_user_id)` — enforced **server-side** in messaging (threads with blocked counterpart refuse send/read — `api/message-send.php`, chat handoff), reviews (option to hide blocked users' reviews from your view), and wishlist/compare visibility. Blocked party gets no signal (no leak of block existence).
4. Emergency affordance: replace "Dialling…" toasts with real `tel:+234…` links per venue city (data table `emergency_numbers(country, service, number)` seeded NG: 112/199/116 police variants — content team verifies numbers before flag `safety.emergency` flips on), plus a "share my booking + location with a trusted contact" one-tap email (outbox). Copy states clearly: this device dials; the platform does not monitor calls.
5. Wire report → fraud flag: repeated reports on a subject auto-raise `fraud_flags` row (ties §WP27.5) with source `user_reports`.

**Acceptance:** N01 report stores + appears in admin queue + reporter sees case id; N02 block prevents message delivery both directions (API-level, not just UI); N03 emergency link markup verified, flag off ⇒ buttons hidden (B27 honest-state passes); N04 3 reports same target ⇒ flag row.

---

## 10. Phase 4 — Customer tools & communication (36 dev-days)

### WP17 — State contract & mutation re-render (4 d)

**Converts:** F83 (P1, Not working), F41 (P1), F42 (P1), the list-refresh half of F89 (U15). Flips B13, B14, B15, U15, A16–A19 envelope.

1. Convert `api/state.php` to `{ok, data: {...}}` (§4.2) and update `JL.refresh` + all readers once (grep `r.data` / `r.state` in `site.js`; every mutation handler uses the returned `data` slice where the response includes one).
2. Wishlist: create list response returns the created list; `site.js:1862-1899` reads `r.data.slug` (bug) → uses payload for optimistic insert **then** authoritative re-render; `r.saved` flag read from correct level (kills "Removed" toast on save — B13).
3. Compare: add/remove returns `data.compare` (api `compare.php:1-19` already computes server-side) → tray/table update from response, no reload needed (B14).
4. Dispute list: after `fileCase`, handler re-fetches `disputes` section from state (U15 passes via the shared mechanism).
5. Test harness: contract suite (§12) enumerates 12 mutation endpoints asserting `data` + immediate-DOM-effect (Playwright: action → no reload → assert re-render) — this WP defines the pattern reused by Phases 5–7.

**Acceptance:** B13–B15, U15, A16–A19 pass; new: X10 "add compare item visible in tray without reload"; X11 "create wishlist rehydrates list selector".

### WP18 — Notifications platform: preferences, outbox delivery, channels (9 d)

**Converts:** F48 (P1), F49 (P1), F51 (P1).

1. Preference vocabulary (A21): single key namespace `notify.<type>.<channel>` with value ∈ {on, off, digest}; `api/notifications.php:24-31` reads/writes exactly those keys; SPA (site.js:2036-2065) sends/loads the same (kills `on` vs `value` mismatch). Defaults modal reads stored values; per-type description strings generated from `settings.channels.available` so it can't claim SMS when no SMS provider is configured (R1).
2. Delivery pipeline: `Notify::dispatch(event, userIds, channels)` writes `notifications` rows (in-app, exists) + `outbox_events(type='deliver', payload{channel, template, recipient, booking ctx})`; dispatcher adapters: `email` (Mailer §4.5), `web_push` (VAPID keys in config; `push_subscriptions` table storing browser subscriptions; payload = title, body, url), `fcm/apns` stubs that fail loudly-until-configured (mobile token store `api/mobile/auth.php:95-106` already accepts tokens — dispatcher uses them once keys exist), `sms`/`whatsapp` provider interfaces (Termii/Twilio-style; keys external — flag gated).
3. Retries/receipts: exponential backoff 3 attempts; `delivery_receipts(channel, ref, status, provider_id, error)`; admin delivery log page (status, template, recipient, failure reasons); unsubscribe links on marketing emails (`subscribers` + `user_prefs` respected).
4. Templates: `templates(key, channel, subject, html, text, version)` DB-driven (admin-editable, audited, versioned) — booking, payment, cancellation, checkin, dispute, chat, waitlist alerts all migrate from inline strings to named templates with variables; HTML emails minimal/inline-CSS; text alternative always sent.
5. Scheduled transactional jobs via worker (outbox types): `remind.upcoming_stay` (T-72h), `msg.unread_nudge` (respect live-chat offline-agent nudge already in module — now through outbox so failures are visible), `dispute.sla_notice`, `payout.statement` (WP25), `waitlist.hit` (WP19).
6. Wire existing dead fields: push token registration endpoint exists → surface "enable notifications" prompt in UI gated by `push_subscriptions` support at runtime.

**Acceptance:** C10 (renumbered in-suite; distinct from chat C10 — new series `NTF-`): NTF1 toggling a pref writes and returns same key/value (A21 flip); NTF2 with mail configured to a **sandbox** (Mailtrap-class credentials — external prerequisite; without them, sandbox SMTP stub server verifies full handshake incl. reply-code checks offline); NTF3 failed send → 3 retries → receipt row `failed` + admin log; NTF4 unsubscribe honored; NTF5 web-push subscribe→dispatch→receipt in dev (no browser: verify payload construction); NTF6 templates render variables for all 12 defined events (snapshot tests).

### WP19 — Waitlists that alert (4 d)

**Converts:** F43 (P1).

1. `waitlist` (exists) gains: `status ENUM('active','hit','expired','cancelled')`, `checked_at`, `expires_at` (default settings `waitlist.ttl_days=90`), `unsubscribe_token`.
2. Matching: on `night.available` events (WP09: cancellation/decline/expiry/block-removal) and on `price.changed` (host override change) — worker `waitlist.match` scans active rows overlapping that listing/date range: capacity fits (guests ≤ max), price ≤ `max_price` when set → alert via WP18 (email + in-app), mark `hit`, attach a single-use booking deep-link token (`waitlist_tokens`, TTL 48h, on use converts: pre-fills checkout and shortens TTL of hold — no hard inventory reservation, stated in copy).
3. Price-drop monitor: nightly job compares current quote to the price stored at signup (quote snapshot column); drop ≥ threshold (settings %) ⇒ "price dropped" alert (one per 7 days per listing/user).
4. Controls: cancel row from account page (endpoint exists `api/waitlist.php:9-33` — add list/cancel actions), email one-click unsubscribe, expiry sweep job; duplicate prevention already works (kept).
5. Honest UI: "You'll be emailed when dates open" only when flag `waitlist.alerts` (flip with worker smoke-test).

**Acceptance:** W01 cancel booking in waited window ⇒ alert email in outbox with deep link; W02 price drop fires once per cadence; W03 expired rows silent; W04 unsubscribe stops all waitlist mail; A22 extended for full lifecycle.

### WP20 — Loyalty: earn on settled money, reverse cleanly, redeem for real (6 d)

**Converts:** F44 (P1), F45 (P1). Flips A64.

1. Earn: `points` accrue **on `captured`** (or on checkout for earn-at-stay policy — settings `loyalty.earn_at`), posted as ledger-linked `points_ledger` rows with `ref_type='payment'`; rate from `tiers`/`settings` (DB). Nothing accrues at booking-creation anymore.
2. Reverse: refund/cancel before earn-at-stay ⇒ compensating `points_ledger` row linked to reversal entry (A63/A64 flip); balance = ledger sum, never a cached column drift (drop denormalized `users.points` or make it a nightly-reconciled cache).
3. Tiers: `tiers.minPts` already seeded (repo.php:492-510) — membership progress reads `minPts` numerically (kills `parseInt("10,000 points")` → 10 bug, site.js:3367-3386); tier promotion on accrual + nightly job, stored `users.tier_id, tier_since`; perk application (discounts, late check-out privilege) consumed in quote/booking services, gated by real availability.
4. Redemption (F45): `rewards` table (from `tier_perks` + admin-created catalogue: type ENUM('naira_credit','discount_pct','free_night_voucher','perks'), cost_points, stock, validity); `api/loyalty.php action=redeem`: atomic (row-locked balance check → `points_ledger` debit + `redemptions` row (status issued/used/expired) → voucher code if applicable) — success toast now follows a committed row; fulfilment queue in admin (issue/decline with reason → notify).
5. Demo removal: membership-page stats from DB aggregates.

**Acceptance:** PT1 accrual timing (booking vs captured vs checkout policy respected); PT2 refund reverses exactly; PT3 over-balance redeem ⇒ 402 `insufficient_points` with no partial state; PT4 double-click race ⇒ one redemption (dedupe key = request uuid); PT5 progress bar math property-tested; B24 flips to real redeems (flag on).

### WP21 — Referrals & affiliates (5 d)

**Converts:** F46 (P1). Flips B25 (honesty first via WP03; this WP makes it real).

1. Attribution: signup accepts `?ref=CODE` → `users.referred_by_id + attribution_code_snapshot`; also stored in `localStorage` 30-day pending code for anonymous visitors, applied at signup server-side (endpoint `api/referral.php action=attach`); codes come from `users.referral_code` (real, already generated `includes/auth.php:206`) — the **fixed sample code/stats in site.js:3390-3399/3436-3451 are deleted** (R2; referral centre fetches `/api/referral` summary: your code, link, clicks (tracked via `referral_clicks`), signups, qualified, credits, balance).
2. Qualification & credit: referred user's first `captured` payment above settings `referral.min_fen` ⇒ job `referral.qualify`: bilateral credit (referrer reward, referee bonus) posted to points **and/or cash** per `settings.referral.mode`; `referrals` table (exists) extended: `qualified_at, credited_at, reward_type, amount`.
3. Anti-fraud: self-referral, same-device/payment-fingerprint, disposable-email patterns ⇒ flag (fraud engine §WP27.5), credit held in `pending_clawback` until refund window passes; chargebacks reverse credits (ledger reversals).
4. Affiliate tier: capability `affiliate` (role table) unlocks percentage-of-captured-commission model: `affiliate_commissions(ref_id, booking, basis_fen, pct, status)` scheduled to pay-out via payouts engine (WP25) at settle+7; admin approves rates per partner (DB-driven).
5. `referral.php` and `affiliate.php` pages wired to real data (their static mock rows are removed).

**Acceptance:** RF1 click→signup→first payment ⇒ bilateral credit exactly once (idempotent re-fire safe); RF2 self/same-fp referral flagged not credited; RF3 refund within window ⇒ clawback; RF4 referral centre numbers equal DB aggregates (fixture: 3 signups, 2 qualified); no hardcoded strings remain (grep gate).

### WP22 — Messaging, threads & concierge honesty (8 d)

**Converts:** F52 (P0), F53 (P1), F54 (P2 honesty scope — implementation slot deferred), B18 XSS.

1. Thread model: `conversations` (exists) gets `type ENUM('host','concierge','support')`, `listing_id`, `subject`; **participants become rows**: `conversation_participants(conversation_id, user_id, role, last_read_at)` — host thread auto-created when: enquiry/message intent targets a listing without one (both participants bound), so a fresh account's listing inquiry goes to the *host*, not the concierge (fixes the CTA mis-wire, site.js:1945-2019 + 4054; stays route "Message host" opens `messages.php?c=<id>` after ensuring the thread via API `action=ensure_host_thread&listing=`).
2. Security: messages stored raw; render path uses `textContent`/escaped templates only — audit both send and fetch render (B18 flips: stored `<img onerror>` never executes); links auto-linkified with scheme allowlist (share the filter introduced in WP02.6).
3. Delivery semantics: `messages` gains `delivered_at/read_at` per participant (read receipts UI when both sides' web/mobile poll updates them — replaces the unsupported claim), attachments via media pipeline (image limit), "translate" button hidden (flag `msg.translate`) until a translation provider is configured — when configured: `api/translate.php` wraps provider (keys external §14), caches `translated_messages`.
4. Legacy inbox vs live chat: concierge replies persist today (kept); route "urgent" intents: concierge offers handoff — creates a live-chat session in the right queue (module exists — reuse `api/chat.php` open with prefilled transcript context), giving F52 a supported escalation ladder. Legacy `messages` page label corrected to "Threads with hosts & concierge" (no more "live agent" implication).
5. Concierge grounding (F53): availability-intent answers call `Availability`/`Pricing` for the **user-stated dates** (parser already extracts ranges — use them; fixed near-term window deleted); answers only cite data the API returned (prices, availability) and stop offering services that don't exist (translation, booking-arranging) unless flags on; "book now" handoff → prefilled checkout deep link when listing instant-book + dates open (action, not a promise).
6. F54 (advertised AI suite): each advertised capability becomes a flag with its own gate; `repo.php:1269-1305` rule-based helpers relabelled in UI as "automated suggestions"; nothing claims "live AI" without a configured model + eval notes in admin. Optional model hook (concierge.php already has one) stays config-off by default; when on: prompts + JSON-guarded replies + rate limit + logged transcripts in `ai_interactions` table (feedback loop for future tuning).

**Acceptance:** M01 host-thread created on first listing inquiry both-parties can exchange (A27–A29 extended); M02 B18 payload renders inert; M03 read receipts transition; M04 concierge quotes real availability for stated dates (fixture listings with blocked days ⇒ refuses correctly — A26 rerun); M05 "AI live" copy absent with flags off (B27-style DOM assert); M06 handoff creates live-chat session visible in agent desk.

---
## 11. Phase 5 — Owner workspace (44 dev-days)

### WP23 — Full listing pipeline: fields, photos, drafts, editing (10 d)

**Converts:** F57 (P1), F58 (P1), F59 (P1), F60 (P1, editing half). Flips A30, B28, B29, B30.

1. **Canonical listing schema.** Define `includes/listing_schema.php` — one declarative spec (field, type, required, min/max, enum options, default) used by: wizard steps, server validation, draft merge, host edit UI, admin moderation view, and (later) import/export. Ensures no field is "collected but dropped" again. DB columns added where missing (`properties`: `city_id`, `area`, `property_type`, `min_stay`, `allow_extra_guests/price`, `cancellation_policy`, `checkin_window`, `house_rules JSON`, `deposit_*` (WP06), `booking_mode` (WP10)).
2. **Wizard fix (B28):** `wizGather` (site.js:2470-2506) is replaced by field-by-field writes to `api/listing-draft.php` (below) and submit sends the *server draft id*, so submitted values equal persisted values — `api/listing-create.php:12-66` validates against the schema and inserts all fields (kills "Lagos/Apartment/moderate" defaulting, A30/B28).
3. **Drafts (B30, F59):** `listing_drafts(id, user_id, listing_id NULL, payload JSON, version INT, updated_at)`; autosave on step change/debounce; resumable on reload ("Progress is never lost" finally true); version guard (client sends base version; conflict ⇒ "restore/merge" dialog). Submit converts draft→listing (pending) and archives draft row.
4. **Photos (B29, F58):** media pipeline (§4.6) with `scope=listing`: upload during step-4 (progress UI), re-encode, store rows in `property_images` (cover flag, alt from filename, reorder endpoint), delete/reorder persisted; `api/listing-create.php:55-60` stops storing bare filenames; gallery render uses stored URLs (404-photo bug gone — A30 extended asserts URL fetch ⇒ 200).
5. **Full edit UI (F60):** host listing editor reuses the wizard form engine seeded from a listing (`api/listing.php action=fetch&listing=` returns schema-shaped payload); edits on a live listing keep `approved` status but flag `content_changed` to moderation (§WP27.2 queue) when key fields change (price/title/amenities free; policy/capacity changes re-review).
6. **Crash-proof details (ties F06/WP37):** newly created listings always carry schema defaults so `Object.entries(p.scores)` never receives null; a validation gate prevents publish without ≥1 photo + 1 amenity + coords (admin can override, audited).

**Acceptance:** D01 wizard round-trip: submitted payload == DB row for every schema field (A30 flip); D02 upload→gallery→detail-page image 200 (B29 flip); D03 reload mid-wizard restores values (B30 flip); D04 new listing detail renders for fresh accounts; D05 edit price → guest quote changes (with WP08/09); D06 schema spec is single-source (grep gate: no second field list).

### WP24 — Host operations: automation, team, dashboard, calendars UX (8 d)

**Converts:** F61 (UX half), F63 (P1), F64 (P1), F66 (P1, Not working).

1. **Calendar/rule editors (F61):** host calendar month view (already persists overrides/blocks via `api/host-action.php:38-114`) gains: state legend fed from `api/calendar.php` (same endpoint as guests — single source), bulk "block weekdays/season" operations, per-night price editor with preview "guests will pay ₦X" (server-computed), rule CRUD surfaced from `pricing_rules` (currently write-only rows). Validation: override cannot be set on a booked night (409).
2. **Automatic guest messages (F66):** `host_templates` rows (exists: trigger selection persists via host-action:231-247 — the missing piece is consumption): worker `hostmsg.trigger` fires on `booking.confirmed` / `t_minus_48h` / `checked_in` / `post_stay_24h` per enabled template; variables `{guest_first} {checkin_date} {address_note}`; delivery via WP18 email+in-app; `template_sends(template_id, booking_id UNIQUE)` dedupe ⇒ idempotent even if cron replays; per-booking disable switch; failures visible in host UI (outbox log slice).
3. **Co-hosts real (F64):** `host_team` (exists: invite/revoke records) extended: `invitations(id, listing_id NULL|host-wide, email, capability_key, token, expires_at 14d, accepted_at)`; invite sends email (outbox) with accept link → creates/links account preference (`accept` on existing user = join; on new = register flow with `pending_invite` gate); accept writes `host_staff(host_id, user_id, capability)` rows; **middleware enforcement:** every host endpoint (calendar, listings, inbox, payouts settings) checks `Access::canHost(user, listing, capability)` — view-only co-host cannot edit price etc (R7). Revoke deletes rows and invalidates nothing else (co-host has own sessions; scoping is live-check).
4. **Dashboard honesty (F63):** occupancy = booked-nights ÷ sellable-nights in window from `Availability` (not sold/90), revenue figures = ledger settled only (demo/captured split, `confirmed_demo` excluded), forecasts = trailing-30 avg per `settings` formula shown in tooltip; home estimator multiplier parity fixed (site.js:821-829 uses the same ×1000 semantics as host page: compute server-side in `/api/host/insights` — `host_insights` table feeds, delete client math).

**Acceptance:** H10 confirmation triggers template send exactly once (re-run safe); H11 co-host invited→accepts→can edit calendar but not payouts (capability matrix); H12 dashboard occupancy vs fixture calendar (2 booked nights of 4 available ⇒ 50% not 2.2%); H13 override-on-booked-night rejected; H14 edit price reflects in guest quote ≤60s (WP09 cache invalidation).

### WP25 — Payouts & channel manager (12 d)

**Converts:** F68 (P0), F67 (P0, Not working).

1. **Beneficiaries (F68):** `host_payout_settings` (exists) extended full fields (account number, bank code, beneficiary name, KYC tie-in: bank name verified via provider `resolveAccount` — Paystack `/:number/banks` lookup; Flutterwave equivalent) → `bank_name_resolved` mismatch blocks save ("account name doesn't match {name}"), stores verification date; changes re-verify + audit + notify.
2. **Payout engine:** `payouts` (exists) becomes scheduled transfers: policy (settings: `payout.schedule ∈ weekly|biweekly|monthly`, `min_amount`, `hold_days_after_checkout` = dispute window 7); job `payout.run`: transferable = ledger host_earnings − already scheduled − open-dispute holds → creates payout batch rows → provider transfer API (sandbox: instant) → status `initiated→verified` via webhook/poll; failures retry ×3 then `held_for_review` + host notified; statement email per cycle (WP18 template). History page + CSV export read the real batch rows (A42 extended: export equals ledger aggregate to the fen).
3. **Channel manager, honest order (F67):**
   - **iCal first (works without partner accounts):** outbound `GET /ical/{listing_token}.ics` (VCALENDAR of blocked nights from `Availability` — secret token per listing, revocable); inbound `channel.ical_pull` worker every settings interval: fetch → parse → diff → write `property_calendar` rows `source='channel:{id}', hold_src` + conflict report rows (overlaps flagged to host, never silently override a real booking — bookings always win locally; policy configurable `channel.conflict=local_wins|ask`).
   - **Provider APIs** (Airbnb API, Booking.com Demand API, VRBO) = documented slots `ChannelAdapter` behind credentials application status (external prerequisites §14) — UI shows "iCal sync (one-way import/export)" until a real adapter authenticates. `host_channels` row: `kind ENUM('ical','airbnb_api','booking_api','vrbo')`, state machine `unverified→connected→error` — **"connected" only after a successful authenticated round-trip** (fixes A41 flag-flip); sync log = `sync_operations` (exists) rows (pulled/pushed counts, errors, last_success_at) surfaced in UI.
   - Push on change: booking confirmed/cancelled ⇒ re-render outbound feed + (API channels only) push unavailability.
4. Rate/plan mapping (API channels): explicit mapping UI per room type → our listing; price push uses WP08 quote engine output (calendar overrides as rate deltas).

**Acceptance:** PAY1 beneficiary mismatch rejected; PAY2 weekly run pays exact ledger-derived amount, idempotent (second run same cycle ⇒ 0 new); PAY3 transfer failure path + host notice; ICS1 outbound feed contains blocked nights, token revoke ⇒ 404; ICS2 pull writes blocks, overlap with real booking produces conflict row not data loss; CH1 "connected" requires round-trip success (A41 flips); sync log shows last run outcome.

### WP26 — Experiences, media extras, access, documents (14 d)

**Converts:** F39 (P1), F09 (P2), F35 (P0), F37 (P1), F38 (P1), F69 (P2).

1. **Experiences as real products (F39):** `experiences` rows get capacity/schedule model (`experience_sessions(id, experience_id, starts_at, capacity, booked_count, price_fen)`); `api/experience-book.php:1-51` becomes: validate (past date ⇒ 422, party > remaining capacity ⇒ 422) → `experience_bookings` row (exists) with WP04 payment intent (per-person price) → session inventory guarded by same unique-hold technique (hold per booking) → fulfilment: host marks attended/no-show; ticket email with QR (signed payload, verified at venue by host mobile view); refunds via WP05 cancellation terms at experience level (settings). Enquiry path kept only for "ask about experience" button (label corrected).
2. **360° tours & floor plans (F09):** `property_images.media_type ∈ (photo|panorama|floorplan)` (from §4.6): panorama = equirectangular JPG ≥ 4096px validated; viewer (already pans/zooms) now fed by real assets + per-property floorplan (SVG/PNG + optional `plans_meta` JSON: rooms, sizes — rendered beside image); upload paths in host editor (WP23); listing detail shows tabs only when assets exist (no global generic-SVG fallback; the generated placeholder is kept only as clearly-labeled "illustrative" when none — R1).
3. **Access codes, honest lifecycle (F35):** interim product: `access_codes(booking_id, code, issued_at, valid_from, valid_until=checkout+6h, rotation_of)`; issued by `booking.confirmed` handler (validity from stay window), shown on trips **from the booking row** (fixed `4471#` deleted — R2), emailed at T-24h (WP18 template), regenerated on modification/cancel (old row `revoked`); host-side "rotate after stay" one-click logs to audit. Smart-lock integration remains a flag: `Provider lockAdapter` interface (`provision/rotate/expire/revoke`) — when a vendor exists (external), codes push to devices; until then every "smart/synchronized" string is flag-gated off (WP03).
4. **Lease agreements (F37):** `leases(id, booking_id, template_id, doc_hash, status draft|sent|signed, signed_at, signed_ip)`; template in `templates` (HTML, admin-editable, `lease.tenant_name` etc variables); generation on booking (auto for long stays per listing flag `requires_lease`): render print-styled page `api/lease.php?action=view` (same engine as invoices) — signing = step-up password + checkbox → store `doc_hash=sha256(rendered)` + signature text row in `signed_agreements` (immutable-ish: audit + hash); both parties get the signed copy (email + vault link in account → Documents); **explicit copy:** "electronic acceptance recorded; not a qualified legal opinion — consult counsel" (legal review prerequisite §14). Third-party e-sign (DocuSign-class) stays adapter slot behind config.
5. **Calendar & passes (F38):** `api/calendar.ics?feed=trips|host&token=` (per-user token in account page; trips feed = VEVENTs of real bookings — replaces today+7/today+10 Google template hardcode at site.js:1163-1166, which now uses the booking's actual dates in the link params); host availability feed reuses §WP25 ICS builder; wallet: Apple PKPass generation kept behind flag `wallet.passes` with its own build notes (needs pass-type ID + certs — external), Android Wallet JIT API slot likewise; UI "Add to Google Calendar" works with real dates everywhere; "wallet pass" language hidden until flag (already dark via WP03).
6. **Inspection/photography/AI-rewrite (F69):** turn into real, simple products: `service_requests(id, host_id, kind ENUM('inspection','photo_shoot','tour'), listing_id, preferred_date, notes, status requested|scheduled|done|declined, scheduled_at, assigned_vendor NULL)` + admin vendor board (schedule/assign/decline, notifications) — the buttons (site.js:2405-2407, 2523-2532) POST here, show ticket number, appear in host "Requests" list. AI listing rewrite: explicit action in listing editor — server-side generator (rule-based improver using schema gaps: "missing amenity: pool", house-rules cleaner; optional model behind config same as WP22.6) → **preview + adopt button** writes through listing edit (audited) — no silent content changes; "KYC complete"/score chips read real `kyc_status` (WP14) and listing completeness % computed from schema coverage, replacing statics.

**Acceptance:** EX1 past-date/oversize bookings rejected, capacity decrements atomically (race: exactly capacity winners), ticket QR verifies; TR1 panorama upload→viewer shows uploaded asset (media_type row) — generic fallback only for listings without assets; AC1 code lifecycle (issue on confirm, rotate on cancel, trips page shows own code only); LS1 lease sign stores hash and copy matches byte-for-byte later (tamper check) + audit; CAL1 ICS feeds contain real dates (fetch + parse assert); SR1 service request round-trip host↔admin; WA1 flags-off UI honesty (re-run of B24-style DOM checks passes with flags on too).

---

## 12. Phase 6 — Back office completeness (12 dev-days)

### WP27 — Admin operations (12 d)

**Converts:** F71 (P1), F73 (P0), F74 (P1), F75 (P1), F76 (P1), F77 (P1), F79 (P1), plus admin surfaces for KYC (§WP14), cases (§WP16), delivery log (§WP18), payout review (§WP25), fraud queues.

1. **User management at scale (F71):** replace first-200 client filter (`repo.php:813-825`) with server-side paginated search (`api/admin-list.php` new: filters email/name/status/kyc/tier/segment, cursor pages, sortable, CSV export honors filters + capability `admin.csv.users`); user drawer shows verification evidence (links into WP14 doc review), sessions (revoke-all), balance aggregates (ledger), open disputes/cases.
2. **Listing moderation (F73):** queue rows gain `reviewer_note` (collected + shown to host on rejection, templated); authenticated **preview link** for pending listings: `stay.php?p=…&preview=<signed token tied to listing+moderator>` (owner + admin only; fixes the 404 preview and mobile publish hole via shared guard); publish-readiness validation gate (WP23.6) blocks approve when mandatory media/coords missing with named reasons; mobile `action.php:96-109`-style publish paths removed (only moderation flips `approved`; M11 flips).
3. **Reviews (F74):** aggregate recalculation **including the zero case** (reject last published ⇒ rating/count reset to null/0 everywhere — kills A76 staleness; recompute runs inside the moderation transaction); category score recalculation from `property_scores` (consistent formula, single function used by mobile + web); eligibility unification: web and mobile submit go through `Reviews::assertEligible` (completed stay within `reviews.window_days` — kills M05 weaker rule).
4. **Campaigns ⇒ promos (F75):** admin campaign form writes `promos` (code, type flat/pct, window, eligibility JSON (city/type/min-nights/first-stay), usage caps, campaign_id backref) — checkout consumes `Repo::promo` (single path); campaign list shows real redemptions (booking join) + attributed revenue (ledger); scheduling via worker (auto start/end status flips); edit invalidates quote cache + logs diff (audit).
5. **CMS consumed (F76):** public templates fetch `cms_blocks` by key with typed render (hero copy, trust badges, FAQ rows, journal posts from `blog_posts` already public) — publishing a unique block visibly changes the home page (A69/A70 flip); editor: block fields typed by key (image/text/rows), draft vs live versions (`cms_blocks.draft JSON`), preview param `?preview=` (admin/owner token), publish writes audit; journal editor (posts CRUD, slugs, cover via media, status) closes "no content editor" finding.
6. **Fraud engine (F77):** `fraud_signals(rule_key, subject_type, subject_id, score, evidence JSON)` evaluated by hooks (booking create/capture, gift redeem, referral qualify, dispute refund — D21 path already real) with rules table (velocity: bookings/IP-hour, card-name count, amount anomaly vs city median, device reuse across accounts); `fraud_flags` (exists) gains `assigned_admin, sla_due_at, escalation_level, outcome`; routing: unassigned after 24h → supervisor (capability `fraud.escalate`) → admin alert (notify); admin queue UI: score + evidence + one-click actions (hold payout via §WP25 hold, suspend, void gift card, release with reason); "AI monitoring" copy ships only with `flags.ai_risk` on (§WP22.6 model slot) — rule engine is honestly labeled "automated rules" (kills the unsupported live-AI assurance).
7. **Audit integrity (F79):** event catalog (§4.8) enforced by `Audit::log(catalog_key, …)` (free-text keys rejected); chain MAC + `prev_hash`; verification endpoint `api/admin-action.php?action=audit_verify` recomputes chain (R08 upgrade: also asserts app user lacks UPDATE/DELETE on `audit_log`+`ledger_entries` via `SHOW GRANTS` when on MySQL); retention policy job (archive > 2 years to `audit_log_archive`, never delete in-place); viewer filters/export (already present) stay.

**Acceptance:** AD1 user search 5k-row fixture returns pages < 100ms (SQLite acceptable with indexes), filters compose; AD2 pending preview token flow (owner yes, other member no, expired no); AD3 reject-last-review zeroes aggregates (A76 flip); AD4 Live campaign ⇒ code usable at checkout exactly within window + caps (A67/A68 flip); AD5 block publish ⇒ home page renders block (A69/A70 flip); AD6 synthetic velocity pattern ⇒ flag raised with evidence; AD7 audit chain tamper test: manual UPDATE (as superuser) ⇒ verify endpoint reports break; AD8 grant assertions on MySQL rail.

---

## 13. Phase 7 — Support modules finished (15 dev-days)

The live-chat/disputes module is structurally sound (its queues, tokens, SLAs and events exist); this phase closes the residue that Phases 0–1 left open, and hardens it against the same failure classes found by the audit.

### WP28 — Live-chat completion (4 d)

**Converts:** F87 (remaining: U04, C14/C15 if not done in WP02, attachments/translation scope).

1. **Draft preservation (U04):** `chat.js` render loop diffs (only append new messages; never rebuild the input); unsent draft kept in component state + `sessionStorage` per session id (restore on reopen).
2. Attachments: image/file in chat via media pipeline (§4.6, sender-owned rows, virus-size limits, AVIF/JPEG/PNG/PDF allowlist); transcripts on close attach file list (links through authenticated `api/file.php`); typing indicator already works — keep.
3. Translation of chat behind provider config (flag; adapter same as §WP22.6) — marketing elsewhere corrected by WP03 in the meantime.
4. Offline/queue UX polish: position ETA from settings, email handoff when visitor closes while queued (template + outbox), reopen same conversation within 24h keeps history (already resumes — make "new conversation" explicit button).

**Acceptance:** C-series re-run: U04 draft survives 3 poll cycles with typing paused; C14/C15 (kept green); U01–U03, U09/U10/U23/U27 stay green; new C50: attachment round-trip visitor→agent visible + auth-guarded fetch.

### WP29 — Agent console integrity (4 d)

**Converts:** F88 (remaining after WP01/WP02 hotfixes).

1. Queue membership scoping **everywhere**: transfer, claim, notes, canned, metrics (C17 class) — all go through `LiveChat::assertAgentScope` (added in WP02 for reads; extend writes); cross-queue supervisor-only actions gated by `chat_agent_departments.supervisor` flag (exists pattern) + capability.
2. Canned response management: CRUD enforces active/scope semantics both directions (admin sees all, agent sees own+team active); "insert" validates at send-time (defense-in-depth, not just list filter) — keeps C38/C39 green structurally.
3. Metrics page honest: first-response/avg-time computed from `chat_events` (rows exist) — replace any statics; SLA breach counters from settings; CSV export of transcripts gated to `chat.export` capability + audited (R8).
4. Settings persistence audit: every console setting (max chats, away note, sweep interval) round-trips; unknown-key rejection tested.

**Acceptance:** C09/C12/C16–C20/C26–C31/C36–C40 stay green after refactor; new C51 limited-agent cross-queue claim ⇒ 403; C52 transcript export audited; U05–U08, U26 re-run green; saveAgent no-path touches `users.role` regression test (lock-in WP01).

### WP30 — Dispute centre correctness (4 d)

**Converts:** F89 (remaining: U22, D16/D23/D32), and the visibility half of F90.

1. **Shareable case link (U22):** `dispute_case_tokens(dispute_id, token, issued_to NULL, single_use BOOL, expires_at, revoked_at)`; email/confirmation print includes `disputes.php?case=…&t=token` (anonymous read-only view: timeline + status, **no internal notes**); token exchange upgrades anonymous claimant to a claimed case (optional sign-up prompt, claim = bind case to account, token revoked); agent/admin can regenerate/revoke tokens (audited) — "private link" finally matches its promise.
2. **Party-scoped fields (D16/D23):** notifications render per-recipient template slices (staff notes column never interpolated into claimant/host emails — move note into `dispute_events.visibility='staff'` and filter at render, both UI and mail); ratings keyed `dispute_ratings(dispute_id, user_id)` (exists table? else add) — second party cannot overwrite the first's (D23 flip); read model shows per-party.
3. **Privacy of public stats (D32):** `api/dispute-stats.php` aggregates only (counts, medians, categories) — no recent-case subject/ref fields in the public payload (remove from SELECT; internal admin list unaffected).
4. Evidence: `dispute_evidence` stores media rows (upload via §4.6, scheme filter from WP02 kept) — claimant/host see both sides' evidence, staff notes marked; download URLs signed.
5. List/status UX: case status chips from server (`status + SLA due from categories`), withdrawn state rendered distinctly (aligns with U15/WP17 refresh).

**Acceptance:** D-series re-run: D13/D15/D16/D22/D23/D25–D28/D32/D33 green (D33 staff alert = outbox notification to queue on open — small job); U22 token link opens on second device/browser, revoked after claim (re-fetch ⇒ 410); D16 leak check: fixture note text must not appear in claimant email render (string assert).

### WP31 — Migration & install robustness (3 d)

**Converts:** F91 (P1), F92 (P1 remaining).

1. **Dialect-aware repair (T06):** migrate runner: detect driver; for seeds use `INSERT … SELECT … WHERE NOT EXISTS` wrappers (pattern already present for some statements); for unique constraints: MySQL → `ALTER TABLE … ADD UNIQUE IF NOT EXISTS`-equivalent (information_schema check), SQLite → `CREATE UNIQUE INDEX IF NOT EXISTS uq_<table>_<cols>`; **post-verify**: expected counts per seed set (assert 8/5/7/1-style), mismatch ⇒ non-zero exit + per-row report; second run ⇒ 0 changes (proved on both rails in CI).
2. Failure traps: every statement executed with per-file section labels + errors collected (diagnose already renders them — feed both); `--dry-run` prints the composed plan (no writes); `why.php` kept dev-only: refuse to run when `app_env=production` (403 + audit) and document removal in deploy runbook (WP32).
3. `agent.php` guard (T12 — done WP02.7) **plus** `install/diagnose.php` extended to check all module tables existence for *runtime* pages (stale-install detection), still admin-only (T09/T10 stay green).
4. Deploys guide (repo root `DEPLOY.md` if present else create): migrate-before-expose warning, run order (schema → seed → migrate → flags), cron key setup, grants snippet for append-only tables.

**Acceptance:** T01–T13 full suite green on SQLite CI (T06 flips); MySQL rail: same suite on real MySQL in staging (prerequisite §14) — this is the point where "unverified on MySQL" findings retire; double-run seed counts equal; T12 guard regression (drop a table, page degrades); `--dry-run` produces no writes (hash DB before/after).

---
## 14. Phase 8 — Platform reach & polish (37 dev-days)

### WP32 — Clean install & repository hygiene (6 d)

**Converts:** F84 (P1, Not working); executes stop-the-bleeding item 10 fully.

1. Export current full schema (59 core + 10 support tables + all WP migrations) into `database/schema.sql` and a public demo seed into `database/seed.sql` (strip every row containing member PII from the dump lineage — regenerate from the synthetic generator used by the audit, never from production data); `install/index.php:10-22,54-64` runs schema → seed → `install/schema/*.sql` migrations (it already appends the module files — generalize the glob to all versioned files) and writes `installed.lock` (respects existing locks).
2. Untrack and remove `wal7zkit_jollof.sql`, `public_html.zip`; `includes/config.php` untracked (kept in `config.sample.php` with env-var fallbacks: `getenv('JL_DB_PASS')` etc.); `.gitignore` extended (`/storage/*`, `includes/config.php`, `install/installed.lock`). **Force-push hygiene requires history purge (git filter-repo) — schedule with repo owner + rotate every credential ever committed (DB password, mail, any gateway keys)**; until purge is agreed, the tracked files at least stop receiving new data (dump regeneration excluded from repo).
3. Fresh-install rehearsal CI job: empty MySQL + empty SQLite both ⇒ `install` completes ⇒ smoke suite hits every top-level page (200s, no fatals) ⇒ `install/diagnose` reports green ⇒ second migrate run is no-op (WP31).
4. `.htaccess`/nginx sample: deny `/install` post-lock (403 by rule, not just by code), deny `includes/`, `storage/`; security headers (X-Content-Type-Options, Referrer-Policy, frame deny, CSP report-only first); `session.cookie_secure/httponly/samesite=Lax` set in bootstrap; cookies verified HttpOnly in audit re-run.

**Acceptance:** IN1 fresh-clone install on both rails passes; IN2 tracked-file scan finds no secrets/PII/dumps; IN3 post-install `install/` returns 403 to non-admins; IN4 header/cookie assertions pass; IN5 config sample documents every new key added by this plan (mail, gateway, cron key, flags).

### WP33 — SEO, URLs & server-render (3 d)

**Converts:** F85 (P1).

1. Canonicals (`includes/view.php:226-258`): stay pages canonicalize to `/stay/{slug}` (pretty form) keeping identity (query-stripping bug fixed by slug adoption rather than param retention); OG image per listing = first `property_images` row via `api/og.php?p=` (server-rendered PNG card: title, price, city — built once, cached on disk); JSON-LD `LodgingBusiness`/`Product` block with availability from `Availability` (machine-readable truth).
2. Rewrites shipped: `.htaccess` `RewriteRule ^stay/([a-z0-9-]+)/?$ stay.php?p=$1 [L,QSA]` (+ resolver accepts slugs in `stay.php`, `api` slug lookup indexed); nginx equivalent in deploy samples (kills the "/stay/onyx → home page" resolution failure from the audit environment class).
3. `sitemap.xml`: sitemap.php emits and a rewrite maps `/sitemap.xml` to it (robots.txt target exists, verified); lastmod from updated_at; images sitemap later.
4. Server-render pass: home + listing detail render first-paint HTML from DB (the JSON API remains for the SPA layer) — measurable: curl detail page contains title/city/price text; progressive enhancement for the rest of the catalogue pages tracked as follow-up.

**Acceptance:** SE1 canonical/OG/JSON-LD fixtures per listing; SE2 `/stay/{slug}` returns 200 with the right property on both server configs; SE3 `/sitemap.xml` reachable + parse-valid, robots target exists; SE4 curl-visible server HTML assertions (no-JS content present for core pages).

### WP34 — Accessibility & responsive completion (5 d)

**Converts:** F86 (P1, remaining axe findings).

1. Fix the eight axe classes at their source: contrast tokens (site.css:155-180 — audit both themes against WCAG 2.2 AA, token-level so components inherit), accessible names for all inputs/selects/buttons incl. the live-chat home screen and dispute form (labels or `aria-label` at component creation in `site.js`/`chat.js`), `role="region"` + `tabindex="0"` for the agent-desk scroll container (unfocusable-region finding), dialog focus traps on modal reuse (wizard, dispute, chat), skip-link target, heading order passes on every page template, `prefers-reduced-motion` respected in carousel/poll animations, 44px hit targets in nav chips.
2. Keyboard path: catalogue → detail → checkout wizard → payment redirect; chat composer; dispute filing — Tab/Shift-Tab/Enter/Escape verified with axe + Playwright keyboard asserts (extend RESP-*/U-* suites with `keyboardOnly=true`).
3. Screen-reader spot check (manual, NVDA+Firefox and VoiceOver+Safari): account, checkout, chat, disputes; findings logged as issues, not guesses.
4. CI gate: axe scans (the audit's `quality-audit.mjs` pattern: same tooling) on the 8 sampled surfaces + new chat/dispute/agent pages must report 0 serious/critical violations; 390px + 1920px overflow checks retained (U23/U24 green).

**Acceptance:** AX1 axe 0-violations on the sampled matrix; AX2 keyboard flows complete checkout and file a dispute without a mouse; AX3 themes: light+dark contrast audit tables in PR; AX4 reduced-motion honored (animation test); AX5 overflow suites stay green.

### WP35 — Mobile backend parity & app runway (7 d)

**Converts:** F82 (P0), F81 (P2 runway).

1. Schema: mobile expectations match core — `notifications.read_at` (M03/M04 flip: column added by migration, used by `api/mobile/action.php:122-145` read flow — after §4.1 services, read/mark all = capability call), removed duplicate user/profile field expectations; `api/mobile/*` responses adopt the §4.2 envelope (apps already tolerate `data`).
2. Authorization parity: all mobile actions route through the shared services (`Access::can*` + state gates): review eligibility (§WP27.3), host decide (§WP11.3), publish-without-approval removed (§WP27.2), check-in gates (§WP10.3) — mobile cannot exceed web by construction (M05/M08/M11 stay green).
3. Idempotent replay: client generates `X-Idempotency-Key` (uuid per user action, reused on retry); server stores `mobile_replays(key, response_hash)` 24h — wishlist double-toggle fixed (replay returns first result), payment intents already idempotent via WP04; token revocation on suspend/password-change (from §4.7).
4. Push: FCM/APNs keys + registration endpoint exists (`api/mobile/auth.php:95-106`) — dispatcher adapter from §WP18 consumes stored tokens; delivery receipts shared table.
5. App runway (F81): produce `/api/mobile/OPENAPI.yaml` (hand-maintained spec of every endpoint + fixtures — becomes the contract the RN/Flutter app consumes); PWA manifest + offline shell for the site as the interim "installable app"; native build/store distribution remains a separate product project (prerequisites: developer accounts — §14); "demo/coming soon" store buttons hide behind flag (WP03) until a build URL exists.
6. CI: run the audit's mobile API series (M01–M12) on every PR after Phase 1.

**Acceptance:** MB1 M01–M12 all green on both rails; MB2 replay fuzz: N duplicate wishlist toggles with same key ⇒ net single toggle; MB3 spec fixtures match live responses (contract test against recorded payloads); MB4 push dispatch end-to-end in sandbox transport.

### WP36 — Business services: real leads then portal (8 d)

**Converts:** F80 (P1, Not working).

1. Stage 1 (now): business demo-request form (site.js:3315-3329 → real POST `api/business.php action=demo_request`): fields (company, contact, size, city, dates, needs, consent), stored `leads(kind, payload, status new|contacted|scheduled|won|lost, assigned, note)` + admin Leads tab (+ notify via §WP18) + auto-ack email with calendar link template; corporate metric tiles read aggregates (no fixed numbers — R2).
2. Stage 2: `companies(id, name, billing_email, tax_id, terms, manager_id)` + `company_members(company_id, user_id, role, policy JSON)` + booking attribution (`bookings.company_id` nullable) — booking under company flow (member selects "on behalf of", policy engine checks travel policy rows: max price, cities, approval-required tiers → approver notified; approvals = pending_host-like state reusing §WP11 machinery).
3. Consolidated billing: company-level invoice (ledger rollup by company within period — §WP05 ledger makes this a query), PDF via invoice engine, NET-terms collection via WP04 (corporate payment profile / bank transfer recorded as settlement batch with reconciliation), per-trip receipts still to travelers.
4. PO workflow: `purchase_orders(company_id, amount, status)` attached to bookings/invoices; validation at booking (PO balance) — all audited.

**Acceptance:** BZ1 lead submits→row+ack email+admin view (B22 flips from "nothing happens" to stored); BZ2 member/non-member policy enforcement (out-of-policy blocked with reason; approver flow works); BZ3 consolidated invoice = sum of member ledger postings to the fen; BZ4 PO over-commitment rejected; stage 2/3 flags (`business.portal`) gate UI until green.

### WP37 — Discovery & content correctness (8 d)

**Converts:** F03, F05, F06, F07, F12 (all P1 discovery residuals).

1. **Server-side search (F03):** `api/search.php` (listing, guests, checkin, checkout, type, price range, city_id AND area, amenities, min-stay) composing `Availability` + `Pricing` (exact nightly prices for chosen dates) + `collections` joins; typed filters with explicit AND between city and area (kills the `Ikoyi OR Lagos` widening — city/area become `neighborhoods`-linked foreign keys, no text matching); tab counts from the same endpoint (fixes missing-element error class B05/B40); flexible dates = availability-aware ±2-day probe. Site.js:840-958 client predicates reduced to display sort; B02/B03/B05/B40 flip.
2. **Collections DB-driven (F05):** `collections`/`collection_properties` (exist) are the only source — `COLLECTION_MAP` constant deleted; `?col=<slug>` filters through `api/search.php?collection=slug`; member counts from join; selection restricts the list (B04 flip).
3. **Detail page data-complete (F06):** API returns full property doc with `scores` default `{}` and every optional section defaulted (null-safe render, kills the 404 fallback for new listings — with WP23.6 this closes both directions); host card renders real owner public profile (name/photo/bio from `users`+`user_prefs`, opt-in; "Superhost" badge from `tiers`/`users.host_badges` column set by admin (audited) — not hardcoded strings); contact/message wiring to §WP22 host threads.
4. **Reviews display (F12):** per-review stars from `reviews.rating` (site.js:3867-3905 — five-star paint replaced with actual score render incl. half-star); category metrics from `property_scores` aggregates; "verified completed stay" badge only for rows matching a checked-out booking (server-computed `stayed=true`); "AI summary" label = "Guests often mention…" keyword-frequency rollup computed server-side from published reviews (deterministic, refresh job on moderation changes) — no unattributed "AI" claim, model-based summaries stay flag-gated (WP22.6).
5. **Map from data (F07):** `api/map-points.php` (filtered result set → `{id, lat, lng, title, price, url}`), pins rendered from it (12 hardcoded pins in site.js:894-899/1178-1224 deleted); pin click → mini card with live price (quote endpoint); list↔map sync via shared filter state; map route default mode fixed (map.php opens map view; site.js:4037-4038).

**Acceptance:** DS1 Ikoyi search excludes non-Ikoyi Lagos stays (B02/B03/B05/B40 flip); DS2 collection filter restricts (B04); DS3 new-listing detail renders for 40 synthetic listings incl. zero-review and single-photo (route checks flip); DS4 stars match stored ratings (sample audit of 100 rows); DS5 map points == filtered DB set; DS6 home search card prices == detail quote for same dates (single engine proof); axe/DOM: no missing-element errors in tab handlers (B40 class).

---

## 15. Coverage matrix (all 78 features)

Every partially functional or broken feature from the re-audit, its owning package/phase, and the acceptance evidence that flips it. `Done-when` summarizes the audit's own next-step column; the full per-package criteria above govern.

| ID | Capability | Today | P | Work package | Phase | Done-when (flip these checks / evidence) |
|---|---|---|---|---|---|---|
| F03 | Stay search and filtering | Partial | P1 | WP37 | 8 | B02, B03, B05, B40 |
| F05 | Curated collections | Partial | P1 | WP37 | 8 | B04 |
| F06 | Residence detail pages | Partial | P1 | WP37 | 8 | 3 failed stay route checks; B17 |
| F07 | Map discovery | Partial | P1 | WP37 | 8 | B06, B07 |
| F08 | Availability calendars | Partial | P0 | WP09 | 2 | A45, A51; B03; source review |
| F09 | True 360-degree tours and property-specific floor plans | Not working | P2 | WP26 | 5 | Source review |
| F12 | Public reviews and trust presentation | Partial | P1 | WP37 | 8 | reviews route; A55-A57, A76, M05; source review |
| F16 | Forgotten-password recovery | Not working | P1 | WP12 | 3 | Repository endpoint inventory; source review |
| F17 | Social/phone sign-in and Okta SSO | Not working | P2 | WP13 (removed via WP03) | 3 | Repository endpoint inventory; source review |
| F18 | Profile and personal preference editing | Partial | P1 | WP15 | 3 | A74; account route; B19 |
| F19 | Identity verification, selfie checks and document vault | Not working | P0 | WP14 | 3 | Source review; handler inventory |
| F20 | Email and phone verification | Not working | P0 | WP12 | 3 | Source review |
| F21 | Customer two-factor authentication/security centre | Not working | P0 | WP13 | 3 | Source review |
| F22 | Personal data export/erasure | Not working | P1 | WP15 | 3 | Repository endpoint inventory |
| F24 | Website language selection/localization | Not working | P2 | WP15 | 3 | Source review |
| F25 | Checkout wizard and reservation/request creation | Partial | P0 | WP08 → WP10 | 2 | A43, B08, B09, B37 |
| F26 | Booking validation and inventory protection | Partial | P0 | WP09 | 2 | A45-A47, A51, A66, B38 |
| F27 | Quotes, promotions, long-stay discounts and add-on pricing | Partial | P0 | WP08 | 2 | A52, A68, B11 |
| F28 | Actual payment collection and gateway verification | Not working | P0 | WP04 | 1 | A44, B37; integration inventory |
| F29 | Real escrow, refunds and host transfers | Not working | P0 | WP05 | 1 | A44, A53, A63; integration inventory |
| F30 | Split-payment collection and security-deposit holds | Not working | P0 | WP06 | 1 | B39; source review |
| F31 | My Trips/history and status display | Partial | P1 | WP10 | 2 | guest trips route; B15 |
| F32 | Reservation modifications | Partial | P1 | WP10 | 2 | A62 |
| F33 | Cancellation handling | Partial | P0 | WP05 | 1 | A63, A64; source review |
| F34 | Guest check-in/check-out | Partial | P0 | WP10 | 2 | A53, A54, B15 |
| F35 | Smart-lock/keyless entry integration | Not working | P0 | WP26 (dark via WP03) | 5 | Source/integration inventory |
| F36 | Invoices and receipts | Partial | P1 | WP05 | 1 | A48-A50; payments route checks |
| F37 | Digital lease agreements for long stays | Not working | P1 | WP26 (dark via WP03) | 5 | Source review |
| F38 | Calendar exports and wallet passes | Partial | P1 | WP26 (dark via WP03) | 5 | Source/integration inventory |
| F39 | Experience reservations and service fulfilment | Partial | P1 | WP26 | 5 | A24, A25; experiences route |
| F40 | Gift cards | Partial | P0 | WP07 | 1 | A58-A61, B10 |
| F41 | Wishlists and named lists | Partial | P1 | WP17 | 4 | A16-A18, B12, B13 |
| F42 | Property comparison | Partial | P1 | WP17 | 4 | A19, B14 |
| F43 | Waitlist and availability-alert service | Partial | P1 | WP19 | 4 | A22; handler/worker inventory |
| F44 | Loyalty points, ledger and tier progression | Partial | P1 | WP20 | 4 | A64; membership route; seeded tier calculation check |
| F45 | Loyalty reward redemption | Not working | P1 | WP20 (disabled via WP03) | 4 | B24 |
| F46 | Referral and affiliate earnings | Not working | P1 | WP21 | 4 | B25; repository inventory |
| F48 | Notification preferences | Partial | P1 | WP18 | 4 | A21 |
| F49 | Push, SMS and WhatsApp notifications | Not working | P1 | WP18 (dark via WP03) | 4 | Integration/worker inventory |
| F51 | Transactional email delivery | Partial | P1 | WP18 | 4 | C/D-series Mailer call sites reviewed; A50; source review; external… |
| F52 | Legacy inbox, concierge/support threads and host chat | Partial | P0 | WP22 | 4 | A27-A29, B17, B18; route checks |
| F53 | AI concierge | Partial | P1 | WP22 | 4 | A26, A27; concierge route; external model untested |
| F54 | Advertised advanced AI suite | Not working | P2 | WP22 (dark via WP03) | 4 | Source/integration inventory |
| F55 | Community reporting/blocking and emergency actions | Not working | P0 | WP16 | 3 | B27; source review |
| F57 | Listing creation wizard and submission | Partial | P1 | WP23 | 5 | A30, B28; failed new-listing detail routes |
| F58 | Listing photo upload/storage | Not working | P1 | WP23 | 5 | B29 |
| F59 | Save-and-return listing drafts | Not working | P1 | WP23 | 5 | B30 |
| F60 | Manage listing base price and publication status | Partial | P1 | WP23 (WP09 enforcement) | 5 | A34, A35, A65, A66 |
| F61 | Calendar blocking, per-date prices and pricing rules | Partial | P0 | WP08 → WP24 | 5 | A36-A38, A51, A52, B31 |
| F62 | Host approval/decline of booking requests | Not working | P0 | WP11 | 2 | M08; source/handler inventory |
| F63 | Host dashboard, analytics and earnings forecasts | Partial | P1 | WP24 | 5 | 10 owner tab checks; source review |
| F64 | Co-host/team invitations and permissions | Partial | P1 | WP24 | 5 | A39; team route; authorization inventory |
| F66 | Automatic scheduled/triggered guest messages | Not working | P1 | WP24 | 5 | Repository worker/call-site inventory |
| F67 | Two-way Airbnb/Booking.com/VRBO channel manager | Not working | P0 | WP25 (dark via WP03) | 5 | A41 |
| F68 | Payout settings and payout history | Partial | P0 | WP25 | 5 | A42, H02; payout route |
| F69 | Inspection, photography/tour scheduling and AI listing rewrite | Not working | P2 | WP26 (dark via WP03) | 5 | Source review |
| F70 | Admin authentication and role gate | Partial | P0 | WP01 | 0 | A12-A14; admin route checks |
| F71 | User search and manual verification management | Partial | P1 | WP27 | 6 | A71, B33; source review |
| F72 | Account suspension enforcement | Not working | P0 | WP01 | 0 | A71, A72 |
| F73 | Listing moderation | Partial | P0 | WP27 | 6 | A31-A33, M11; detail route failures |
| F74 | Review publication/rejection and aggregate recalculation | Partial | P1 | WP27 | 6 | A55-A57, A76 |
| F75 | Promotion/campaign management | Partial | P1 | WP27 | 6 | A67, A68, B34 |
| F76 | Content management/public publishing | Partial | P1 | WP27 | 6 | A69, A70, B35; public-template inventory |
| F77 | Fraud case monitoring and escalation | Partial | P1 | WP27 | 6 | D21; fraud route; source/call-site inventory |
| F79 | Audit logging and audit-log viewer | Partial | P1 | WP27 | 6 | R08; admin audit route; source review |
| F80 | Corporate demo requests, company travel portal and… | Not working | P1 | WP36 | 8 | B22; repository endpoint inventory |
| F81 | Native mobile app and store distribution | Not working | P2 | WP35 | 8 | B26; tracked-file inventory |
| F82 | Mobile backend API | Partial | P0 | WP35 | 8 | M01-M12 |
| F83 | Client/server state refresh after mutations | Not working | P1 | WP17 | 4 | B13-B15, U15 |
| F84 | Clean installation from this repository alone | Not working | P1 | WP32 | 8 | Tracked-file inventory; T04/T05 context |
| F85 | SEO metadata, sitemap and clean URLs | Partial | P1 | WP33 | 8 | Local canonical/sitemap probes; source review |
| F86 | Responsive and accessible user interface | Partial | P1 | WP34 | 8 | 7 RESP-* checks, U23, U24, B36; eight axe scans |
| F87 | Live-chat visitor widget and queues | Partial | P0 | WP28 (hotfix WP02) | 7 | C01, C02, C05-C11, C13-C15, C21 (fail), C41 (fail), U01-U04, U09, U10,… |
| F88 | Agent console, routing and staff management | Partial | P0 | WP29 (hotfixes WP01/02) | 7 | C09, C12, C14-C20, C26-C31, C33, C34, C36-C40, U05-U08, U26; agent… |
| F89 | Dispute resolution centre for guests and hosts | Partial | P1 | WP30 (hotfix WP02) | 7 | D01-D11, D17-D19, D22, D23, D25-D27, D32, U11-U15, U21, U22 |
| F90 | Mediation back office and decision outcomes | Partial | P0 | WP05 → WP30 | 7 | D12-D16, D18-D21, D24, D27-D34, U16-U19; disputes admin route |
| F91 | Support-module database migration runner | Partial | P1 | WP31 | 7 | T01-T07, T11 |
| F92 | Deployment self-check and failure-trap tooling | Partial | P1 | WP02(T12) → WP31 | 7 | T09, T10, T12 (fail), T13; DEPLOY guide §0 review |

## 16. Testing, verification & the re-audit exit

1. **Harness reuse.** The audit harness (API suite `A/T` 98 checks, module suites `C/D`, browser suites `B/U/GATE/RESP`, route/role checks, axe) is promoted into CI: PHP lint + `node --check`, then the isolated PHP-WASM + SQLite environment runs the full suite on every PR to `main`. Playwright + axe run on the nightly lane. New check series added by this plan: `P` (payments), `Q` (quote), `R` (availability), `L` (lifecycle), `V` (verify/identity), `S` (2FA), `K` (KYC), `T01-` (privacy/prefs — rename to avoid the migration `T` series; use `PR`), `NTF` (notifications), `W` (waitlist), `PT` (points), `RF` (referral), `M0x` (messaging — use `MSG`), `D0x/PAY/ICS/CH`, `EX/TR/AC/LS/CAL/SR/WA`, `AD`, `C5x`, `H1x`, `T` (migrations, retained), `IN`, `SE`, `AX`, `MB`, `BZ`, `DS`. Each WP lists the checks it must flip; CI enforces "flipped stays green" (a regressed flipped check blocks merge).
2. **Property tests** for the math-heavy engines: quote parity (client never computes), cancellation policy boundaries (364-case table), ledger zero-sum invariant (every lifecycle sim), availability invariants (no night both open and held; blocks symmetric host/guest).
3. **Re-audit milestones.** Mini re-audits at each phase exit (same classifiers, same three buckets), full re-audit at Phase 4 exit and at completion. Target trajectory: after Phase 0: 14 / 46 / 22 with zero critical security findings; after Phase 2: money features classified on their own terms; after Phase 8: **92/92 "fully functional" or honestly-flagged-optional** — the only acceptable non-working rows at completion are capability slots whose external prerequisites (§17) were not provisioned, and those must render as explicitly-unavailable, never as broken.
4. **Evidence discipline.** Every acceptance check ships as harness code (not manual QA notes), so the final deliverable regenerates the same HTML report with new results — the same report format the user already reviews.

## 17. Prerequisites & risk register

**External prerequisites (owner-provided; each blocks exactly the WPs named):**

| Prerequisite | Blocks | Notes |
|---|---|---|
| Paystack + Flutterwave **test** accounts (prod later) | WP04/05/06/07/25 | Sandbox rails let full suites pass before prod money moves; prod key exchange documented in runbook |
| SMTP/sandbox mailbox (Mailtrap-class) + sending domain SPF/DKIM/DMARC | WP18 (and all email-dependent accepts) | Audit environment intentionally had mail off; deliverability needs a real (sandbox) endpoint |
| Native MySQL instance for CI/staging | WP05/09/25/31 concurrency + double-run proofs | T06-class dialect artifacts must be cleared on the production rail |
| cPanel/Hosting cron + deploy credentials | §4.5 worker scheduling | Tick-fallback exists but cron is the supported mode |
| Google OAuth client (optional F17), FCM/APNs keys (optional native push), lock vendor account (optional F35), e-sign provider (optional F37), translation model key (optional), WhatsApp Business/SMS vendor (optional F49) | Respective flags | All stay dark (WP03) until issued — that is an honest state, not a failure |
| Legal review: erasure/retention (WP15), lease templates (WP26.4), KYC handling (WP14) | compliance flags | Plan ships the workflow; counsel approves the copy/retention windows |
| App store developer accounts (optional F81) | native runway only | PWA + API spec shipped regardless |

**Top risks & mitigations:**

1. **Ledger migration of live data** (if production already holds bookings/payments rows): backfill script derives initial ledger postings from existing `payments`/`bookings` with a reconciliation report before cutover; cutover in a maintenance window with `record_only` demo labels stripped; parallel-run (old labels + derived states) for one cycle.
2. **Behavior change shock** (confirmation no longer instant, guests now pay first): feature flags per listing (grandfather via settings toggle `legacy_instant_confirm`, default off for new, on for existing until host opts), comms templates prepared, launch note in-app.
3. **Shared-host limits** (long cron intervals, low memory): outbox batches bounded (settings), per-tick budgets, worker leases so overlapping ticks are safe.
4. **Concurrency on MySQL not verifiable in sandbox** → the CI MySQL lane (§16) is mandatory before claiming A45/A46/A47-style fixes closed; plan marks "unverified on MySQL" exactly where true, like this audit did (T06 lesson).
5. **Scope creep on AI/lock/channel vendor work** → those are adapter slots + flags; plan completion ≠ vendor integration completion, stated in each WP ("until configured" honest state per R1).
6. **History purge of tracked secrets** requires force-push coordination → treated as a scheduled event with all members' work rebased; DB password rotation happens immediately (stop-the-bleeding), purge follows.

## 18. Effort model & sequencing summary

| Phase | 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 |
|---|---|---|---|---|---|---|---|---|---|
| Dev-days | 17 | 38 | 29 | 34 | 36 | 44 | 12 | 15 | 37 |

**Total ≈ 262 dev-days** (one senior full-stack engineer at ~4.5 effective days/week ≈ 13 months; with a second engineer on parallel WPs inside phases ≈ 6–7 calendar months; add QA ~25%). Order inside phases is flexible except named dependencies (`WP04 → WP05 → WP06/07`; `§4.4 engine → WP08 → WP09 → WP10/11`; `§4.5 → WP18 → WP19/24.2`; `WP23 → WP24/WP37.3`; `WP01/WP02 → all support-module work`). The stop-the-bleeding list (§3) ships as first PRs within Week 1, independently mergeable.

## 19. What "done" looks like

Re-run the full audit at the end: **all 92 capabilities classified fully functional** (or explicitly-unavailable optional slots that never claim otherwise), every previously-failing check in the 367-check suite passing plus the ~120 new acceptance checks, `php -l`/`node --check` clean, axe 0 serious on all sampled surfaces, install rehearsed from a fresh clone on MySQL and SQLite, and the evidence HTML regenerated with the same drill-down this plan cites. From that day, the audit's classification test — *did the real user action complete its advertised effect, proven by an executable check?* — is the standing CI bar for every future feature.

---

*Prepared 2026-09-22 · Baseline `arena/01a096cd-newjollof` @ `19363b9` · Companion artifacts: `docs/JOLLOF_LIVING_REAUDIT_01a096cd.{html,md}`, `docs/jollof-living-feature-matrix-reaudit.csv`, `docs/jollof-living-audit-evidence-reaudit.json`.*
