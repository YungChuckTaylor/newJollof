# Jollof Living — Branch Re-audit (arena/01a096cd-newjollof)

**Reviewed:** 22 September 2026 · **Commit:** `19363b928dd2` · **Branch:** `arena/01a096cd-newjollof`
**Supersedes:** the earlier audit of `arena/01a0c952-newjollof` @ `5855dfd` (`JOLLOF_LIVING_FEATURE_AUDIT.md`). The interactive full report with embedded evidence is `JOLLOF_LIVING_REAUDIT_01a096cd.html`; data lives in `jollof-living-feature-matrix-reaudit.csv` and `jollof-living-audit-evidence-reaudit.json`.

## Executive conclusion

**The support modules this branch adds are real; the paid-booking core is exactly as broken as before; and the new modules introduce two severe flaws of their own.** The branch replaces the fake “File a dispute” toast and the concierge-only chat button with a genuine live-chat desk (five queues, an agent console with claims/transfers/internal notes/canned replies/metrics) and a database-backed dispute centre (categories, case files, statuses, decisions, notifications, tokens). It also ships migration, self-check and failure-trap tooling. That is meaningful progress.

However: (1) the visitor `close` action performs **no ownership check** and returns the entire transcript — anyone holding a chat reference can end and read someone else’s support conversation (C21/C41); (2) saving a chat agent with a temporary password **grants the linked user platform-admin rights** regardless of the chosen agent role, unlocking every-user CSV export (C32–C33), and pausing the agent does not revoke the session (C34). Meanwhile the previous audit’s P0 set — no real payment collection, untrusted gift-card credit, calendar/price overrides ignored at booking, suspension and admin-OTP bypasses, mobile `read_at` crashes, committed credentials — reproduces identically on this snapshot (A44, A51–A53, A58–A61, A66, A72, A13, M03–M11).

### Classification totals (92 capabilities = 86 carried + 6 new)

| Classification | Count |
|---|---|
| Fully operational/functional | 14 |
| Partially functional | 49 |
| Not working at all | 29 |

**Evidence base:** 186 API/database checks (136 passed), 84 browser/guard/layout checks (52 passed), 97 route/role render checks (94 passed), 8 axe scans — 367 recorded observations embedded in the HTML, all on synthetic data in an isolated PHP-WASM + SQLite copy of this commit. Mail was disabled; no payment provider, mail server, lock or model was contacted; native MySQL was not executed.

## What changed since the previous audit

- **Support modules went from fake to real** — A full dispute centre and live-chat desk with their own APIs, 10 tables, an agent console and admin tabs *(F87–F90 (new rows))*
- **Live chat works — with two severe flaws** — Queues, routing, notes and canned replies verified working; close has no ownership check, and an agent-password silently grants platform admin *(F87, F88 (P0))*
- **Dispute centre works — money still doesn’t** — Cases, timelines and decisions persist; refunds are record-only rows that can double-count past the booking total *(F89, F90)*
- **Migration tooling added** — install/migrate + diagnose + why; admin-gated and creates everything; double-run duplicates seeds on the SQLite dev path *(F91, F92)*
- **Core defects untouched** — Payments, gift cards, calendar enforcement, suspension, admin OTP bypass, mobile schema crashes, listing/photo/draft gaps, state refresh, committed credentials — all reconfirmed *(A/C/D/B series)*
- **Installer now also runs install/schema/*.sql** — Fresh installs would apply the module SQL, but the base schema/seed files are still absent *(F84)*

## Critical findings (10)

1. **Chat-close discloses transcripts (P0, new).** Any caller with a ref closes any visitor chat and receives the full message list back (C21/C41). Fix: require the visitor token/owner and never return the bundle from `close`.
2. **Agent creation escalates to platform admin (P0, new).** `saveAgent`’s password path sets `users.role=admin` for matched or new accounts (C32), unlocking user CSVs (C33), surviving pause (C34). Fix: remove role/user creation from the support API entirely.
3. **Dispute refunds are bookkeeping that can double-count (P0, new).** `gateway=dispute` rows with no provider movement (D19/D20); repeat decisions exceed the booking total (D31), a `no_action` verdict still stores the typed refund (D34), and re-deciding 500s mid-mutation (D24).
4. **Booking confirms without payment (carried P0).** A44/A53: `confirmed` + escrow “released” states while `payments.status=initiated, gateway=record_only`; arbitrary gift credit zeroes totals (A58–A61).
5. **Calendar doesn’t own availability or price (carried P0).** A51/A52/A66: host-blocked days bookable, a ₦400,000 override + 50% rule still quotes the ₦155,000 base, and paused listings accept direct bookings.
6. **Access-control bypasses (carried P0 + new).** Suspension writes a field login never reads (A72); ordinary login skips the configured admin OTP (A13); mobile bypasses moderation/approval (M08/M11); plus the agent escalation above.
7. **Staff-data scoping and canned-reply leaks (P1, new).** Cross-queue transcript reads (C17); disabled/personal canned replies usable by anyone (C38/C39); partial updates reset chat caps (C30); invalid agent still persists (C40).
8. **Support UX losses (P1, new).** 4s/6s repaints wipe unsent drafts for visitors and agents (U04/U07); the admin-tab widget-settings host is broken (U20); the reopen button sends the wrong status (U19); the anonymous claimant link carries no token (U22).
9. **Migration double-run unsafe on the dev dialect (P1, new).** Seed-unique-key repair is MySQL-only (T06); `ensure_seed_keys` can therefore not dedupe on SQLite, and the “idempotent” promise is unproven on MySQL here.
10. **Deploy/tooling hygiene (P1, new; P0 secret risk carried).** `install/why.php` runs arbitrary in-root pages and `diagnose.php` exposes environment detail if web-reachable (T09/T13); the tracked config/dump/ZIP situation is unchanged (config blob byte-identical to the previous audit).

## Per-capability classifications (92)

Full findings, evidence IDs and source links for every row are in the CSV/HTML; the table keeps the essentials.

### Discovery & content

| ID | Capability | Status | Priority |
|---|---|---|---|
| F01 | Website shell, ordinary navigation, mobile menu and theme | ✅ functional | Maintain |
| F02 | Public residence catalogue and cards | ✅ functional | Maintain |
| F03 | Stay search and filtering | 🟡 partial | P1 |
| F04 | Price/rating sorting | ✅ functional | Maintain |
| F05 | Curated collections | 🟡 partial | P1 |
| F06 | Residence detail pages | 🟡 partial | P1 |
| F07 | Map discovery | 🟡 partial | P1 |
| F08 | Availability calendars | 🟡 partial | P0 |
| F09 | True 360-degree tours and property-specific floor plans | ❌ not working | P2 |
| F10 | Neighbourhood guides and journal/article reading | ✅ functional | Maintain |
| F11 | Help search and FAQ accordion | ✅ functional | Maintain |
| F12 | Public reviews and trust presentation | 🟡 partial | P1 |
| F13 | About, hosting information and roadmap pages | ✅ functional | Maintain |

### Accounts

| ID | Capability | Status | Priority |
|---|---|---|---|
| F14 | Email/password guest and owner registration, login and logout | ✅ functional | Maintain |
| F15 | Change an existing password | ✅ functional | Maintain |
| F16 | Forgotten-password recovery | ❌ not working | P1 |
| F17 | Social/phone sign-in and Okta SSO | ❌ not working | P2 |
| F18 | Profile and personal preference editing | 🟡 partial | P1 |
| F19 | Identity verification, selfie checks and document vault | ❌ not working | P0 |
| F20 | Email and phone verification | ❌ not working | P0 |
| F21 | Customer two-factor authentication/security centre | ❌ not working | P0 |
| F22 | Personal data export/erasure | ❌ not working | P1 |
| F23 | Currency display preference | ✅ functional | Maintain |
| F24 | Website language selection/localization | ❌ not working | P2 |

### Reservations & money

| ID | Capability | Status | Priority |
|---|---|---|---|
| F25 | Checkout wizard and reservation/request creation | 🟡 partial | P0 |
| F26 | Booking validation and inventory protection | 🟡 partial | P0 |
| F27 | Quotes, promotions, long-stay discounts and add-on pricing | 🟡 partial | P0 |
| F28 | Actual payment collection and gateway verification | ❌ not working | P0 |
| F29 | Real escrow, refunds and host transfers | ❌ not working | P0 |
| F30 | Split-payment collection and security-deposit holds | ❌ not working | P0 |
| F31 | My Trips/history and status display | 🟡 partial | P1 |
| F32 | Reservation modifications | 🟡 partial | P1 |
| F33 | Cancellation handling | 🟡 partial | P0 |
| F34 | Guest check-in/check-out | 🟡 partial | P0 |
| F35 | Smart-lock/keyless entry integration | ❌ not working | P0 |
| F36 | Invoices and receipts | 🟡 partial | P1 |
| F37 | Digital lease agreements for long stays | ❌ not working | P1 |
| F38 | Calendar exports and wallet passes | 🟡 partial | P1 |
| F39 | Experience reservations and service fulfilment | 🟡 partial | P1 |
| F40 | Gift cards | 🟡 partial | P0 |

### Customer tools

| ID | Capability | Status | Priority |
|---|---|---|---|
| F41 | Wishlists and named lists | 🟡 partial | P1 |
| F42 | Property comparison | 🟡 partial | P1 |
| F43 | Waitlist and availability-alert service | 🟡 partial | P1 |
| F44 | Loyalty points, ledger and tier progression | 🟡 partial | P1 |
| F45 | Loyalty reward redemption | ❌ not working | P1 |
| F46 | Referral and affiliate earnings | ❌ not working | P1 |

### Communication & support

| ID | Capability | Status | Priority |
|---|---|---|---|
| F47 | In-app notification feed and read/unread state | ✅ functional | Maintain |
| F48 | Notification preferences | 🟡 partial | P1 |
| F49 | Push, SMS and WhatsApp notifications | ❌ not working | P1 |
| F50 | Newsletter signup/data capture | ✅ functional | Maintain |
| F51 | Transactional email delivery | 🟡 partial | P1 |
| F52 | Legacy inbox, concierge/support threads and host chat | 🟡 partial | P0 |
| F53 | AI concierge | 🟡 partial | P1 |
| F54 | Advertised advanced AI suite | ❌ not working | P2 |
| F55 | Community reporting/blocking and emergency actions | ❌ not working | P0 |

### Owner workspace

| ID | Capability | Status | Priority |
|---|---|---|---|
| F56 | Owner account provisioning | ✅ functional | Maintain |
| F57 | Listing creation wizard and submission | 🟡 partial | P1 |
| F58 | Listing photo upload/storage | ❌ not working | P1 |
| F59 | Save-and-return listing drafts | ❌ not working | P1 |
| F60 | Manage listing base price and publication status | 🟡 partial | P1 |
| F61 | Calendar blocking, per-date prices and pricing rules | 🟡 partial | P0 |
| F62 | Host approval/decline of booking requests | ❌ not working | P0 |
| F63 | Host dashboard, analytics and earnings forecasts | 🟡 partial | P1 |
| F64 | Co-host/team invitations and permissions | 🟡 partial | P1 |
| F65 | Manual message-template management | ✅ functional | Maintain |
| F66 | Automatic scheduled/triggered guest messages | ❌ not working | P1 |
| F67 | Two-way Airbnb/Booking.com/VRBO channel manager | ❌ not working | P0 |
| F68 | Payout settings and payout history | 🟡 partial | P0 |
| F69 | Inspection, photography/tour scheduling and AI listing rewrite | ❌ not working | P2 |

### Back office

| ID | Capability | Status | Priority |
|---|---|---|---|
| F70 | Admin authentication and role gate | 🟡 partial | P0 |
| F71 | User search and manual verification management | 🟡 partial | P1 |
| F72 | Account suspension enforcement | ❌ not working | P0 |
| F73 | Listing moderation | 🟡 partial | P0 |
| F74 | Review publication/rejection and aggregate recalculation | 🟡 partial | P1 |
| F75 | Promotion/campaign management | 🟡 partial | P1 |
| F76 | Content management/public publishing | 🟡 partial | P1 |
| F77 | Fraud case monitoring and escalation | 🟡 partial | P1 |
| F78 | Admin and owner CSV record exports | ✅ functional | Maintain |
| F79 | Audit logging and audit-log viewer | 🟡 partial | P1 |

### Business services

| ID | Capability | Status | Priority |
|---|---|---|---|
| F80 | Corporate demo requests, company travel portal and consolidated billing | ❌ not working | P1 |

### Mobile

| ID | Capability | Status | Priority |
|---|---|---|---|
| F81 | Native mobile app and store distribution | ❌ not working | P2 |
| F82 | Mobile backend API | 🟡 partial | P0 |

### Platform

| ID | Capability | Status | Priority |
|---|---|---|---|
| F83 | Client/server state refresh after mutations | ❌ not working | P1 |
| F84 | Clean installation from this repository alone | ❌ not working | P1 |
| F85 | SEO metadata, sitemap and clean URLs | 🟡 partial | P1 |
| F86 | Responsive and accessible user interface | 🟡 partial | P1 |

### Live support & disputes

| ID | Capability | Status | Priority |
|---|---|---|---|
| F87 | Live-chat visitor widget and queues | 🟡 partial | P0 |
| F88 | Agent console, routing and staff management | 🟡 partial | P0 |
| F89 | Dispute resolution centre for guests and hosts | 🟡 partial | P1 |
| F90 | Mediation back office and decision outcomes | 🟡 partial | P0 |
| F91 | Support-module database migration runner | 🟡 partial | P1 |
| F92 | Deployment self-check and failure-trap tooling | 🟡 partial | P1 |

## Feature-by-feature findings


### Discovery & content

**F01 — Website shell, ordinary navigation, mobile menu and theme** · *Fully operational/functional* · Maintain

- Found: Header/footer navigation and mobile drawer work; light/dark preference survives reload. This is the navigation shell, not a certification of every destination or accessibility.
- Sources: includes/view.php:262-350; assets/js/site.js:4098-4165 · Checks: B20, B36; public route checks
- Next step: Retain regression coverage for signed-in and mobile navigation.

**F02 — Public residence catalogue and cards** · *Fully operational/functional* · Maintain

- Found: Live residences are loaded from the database and rendered as catalogue cards. Paused/pending items are omitted from the public catalogue; direct-booking enforcement is a separate defect.
- Sources: includes/repo.php:142-215; assets/js/site.js:653-710 · Checks: A01; home/stays route checks
- Next step: Add tests for large and empty catalogues.

**F03 — Stay search and filtering** · *Partially functional* · P1

- Found: Basic city/capacity/type/price filters exist. Choosing Ikoyi also matches every Lagos residence because the predicate ORs area with city. Dates and flexible-date availability do not filter results; category tabs raise a missing-element error.
- Sources: assets/js/site.js:840-958 · Checks: B02, B03, B05, B40
- Next step: Use explicit city/area identifiers and authoritative date availability; fix tab handlers.

**F04 — Price/rating sorting** · *Fully operational/functional* · Maintain

- Found: Price ascending/descending and rating sorting are implemented on the loaded catalogue; ascending price was exercised successfully. “Newest” is only a new flag, not a publication-date ranking.
- Sources: assets/js/site.js:917-920 · Checks: B02, B40; source review
- Next step: Add stable tie-breaking and a real date field if chronological newest is required.

**F05 — Curated collections** · *Partially functional* · P1

- Found: Collection tiles, descriptions and member counts render, but selecting a collection does not restrict the stays list to its mapped properties.
- Sources: assets/js/site.js:902-921; assets/js/site.js:1228-1237 · Checks: B04
- Next step: Apply COLLECTION_MAP when parsing col-* filters.

**F06 — Residence detail pages** · *Partially functional* · P1

- Found: Established seeded homes render amenities, images and reviews. A pre-existing unreviewed listing and both newly created/published test listings fell back to the 404 UI: Object.entries(p.scores) receives null. Host identity/contact presentation is also hardcoded.
- Sources: assets/js/site.js:961-1098; assets/js/site.js:1025; includes/repo.php:168-215 · Checks: 3 failed stay route checks; B17
- Next step: Provide defaults for optional listing data and render the actual owner and property details.

**F07 — Map discovery** · *Partially functional* · P1

- Found: An illustrated map and clickable pins exist, but the map has 12 hardcoded property pins, does not represent new listings, and map.php initially opens the list instead of the map.
- Sources: assets/js/site.js:894-899; assets/js/site.js:1178-1224; assets/js/site.js:4037-4038 · Checks: B06, B07
- Next step: Generate pins from actual coordinates and filtered results; initialize map mode correctly.

**F08 — Availability calendars** · *Partially functional* · P0

- Found: Date widgets render, and final submission rejects overlaps with existing active reservations. Frontend calendars use sold-out demo ranges rather than JL.blocked; host date blocks are not enforced by booking availability.
- Sources: assets/js/site.js:1100-1134; assets/js/site.js:1584-1592; includes/pricing.php:275-296 · Checks: A45, A51; B03; source review
- Next step: Unify calendar display and booking checks around bookings plus owner blocks, with transactional conflict protection.

**F09 — True 360-degree tours and property-specific floor plans** · *Not working at all* · P2

- Found: The “360°” viewer pans/zooms a single ordinary image. Floor plans are generic generated SVGs, not uploaded or measured layouts for each residence.
- Sources: assets/js/site.js:1135-1157 · Checks: Source review
- Next step: Integrate real panoramas/property plans or relabel these as illustrative previews.

**F10 — Neighbourhood guides and journal/article reading** · *Fully operational/functional* · Maintain

- Found: Database-backed guide and article pages load, including all six neighbourhood and six journal detail routes in the fixture. Editorial accuracy and fresh content are outside this technical check.
- Sources: includes/repo.php:306-477; assets/js/site.js:1240-1278; assets/js/site.js:3458-3483 · Checks: 12 guide/article route checks
- Next step: Keep published content and metadata accurate; fix collection/search destinations separately.

**F11 — Help search and FAQ accordion** · *Fully operational/functional* · Maintain

- Found: FAQ keyword search produces matching answers and the accordion expands/collapses. This does not imply that the support, dispute or emergency services described there are implemented.
- Sources: assets/js/site.js:3485-3503; assets/js/site.js:3524-3533 · Checks: B21; help route check
- Next step: Keep answers aligned with implemented capabilities.

**F12 — Public reviews and trust presentation** · *Partially functional* · P1

- Found: Published review text and aggregate data display. Individual reviews are drawn with five stars regardless of their actual score, some metrics are fixed, “verified completed stay” is not enforced consistently, AI summaries are stored text rather than generated, and rejecting the last published review leaves the stale rating/count in place (reproduced on this branch).
- Sources: assets/js/site.js:3867-3905; includes/repo.php:168-215; api/mobile/action.php:122-145 · Checks: reviews route; A55-A57, A76, M05; source review
- Next step: Render real individual ratings and metrics; unify verified-stay eligibility before making trust claims.

**F13 — About, hosting information and roadmap pages** · *Fully operational/functional* · Maintain

- Found: Informational pages render correctly from templates/catalogue data. Roadmap items labelled future/research are informational, not evidence that those capabilities exist.
- Sources: assets/js/site.js:2355-2414; assets/js/site.js:3839-3865; assets/js/site.js:3928-3942 · Checks: about/host/future route checks
- Next step: Remove inaccurate live-service assertions; keep roadmap status honest.


### Accounts

**F14 — Email/password guest and owner registration, login and logout** · *Fully operational/functional* · Maintain

- Found: Valid registration creates a hashed-password account and session; invalid signup is rejected. Browser login and logout work, and signup cannot grant the admin role. Suspension and MFA are separate failed controls below.
- Sources: api/auth.php:6-65; includes/auth.php:175-235; assets/js/site.js:2258-2345 · Checks: A05-A11, A75, B01; protected-route checks
- Next step: Retest after tightening suspension and MFA requirements.

**F15 — Change an existing password** · *Fully operational/functional* · Maintain

- Found: The current password, minimum length and confirmation are checked; the new hash persists and fresh login with the new password succeeds. This is not forgotten-password recovery or global session revocation.
- Sources: api/account.php:11-27; assets/js/site.js:2234-2247 · Checks: A73; UI handler source review
- Next step: Add session/token revocation policy and browser regression coverage.

**F16 — Forgotten-password recovery** · *Not working at all* · P1

- Found: No reset-token issuance, recovery email or reset endpoint is supplied. Help/forgot-password links are not a recovery workflow.
- Sources: assets/js/site.js:2258-2345; assets/js/site.js:3588; api/auth.php:1-65 · Checks: Repository endpoint inventory; source review
- Next step: Implement expiring, single-use reset tokens and real recovery delivery.

**F17 — Social/phone sign-in and Okta SSO** · *Not working at all* · P2

- Found: Email/password is the implemented authentication path. Social/phone methods named in the frontend constants have no provider callbacks or OTP flows and are not implemented by the current sign-in form; admSSO is explicitly a stub.
- Sources: assets/js/site.js:24; assets/js/site.js:2286-2308; assets/js/site.js:3562-3569 · Checks: Repository endpoint inventory; source review
- Next step: Implement providers securely or remove/disable unavailable sign-in options.

**F18 — Profile and personal preference editing** · *Partially functional* · P1

- Found: Profile display and profile-update APIs work, but the account page lacks a wired profile edit form. Currency works; language and marketing/WhatsApp preference controls are not saved.
- Sources: api/account.php:30-41; api/mobile/action.php:182-194; assets/js/site.js:2178-2255 · Checks: A74; account route; B19
- Next step: Expose and wire profile editing and persist each preference separately.

**F19 — Identity verification, selfie checks and document vault** · *Not working at all* · P0

- Found: KYC is a flag, not a verification workflow. Manage Documents only shows a toast; no secure document upload, selfie matching, reviewer evidence or vault implementation is supplied. Admin “verify” just sets flags.
- Sources: assets/js/site.js:2197-2200; api/admin-action.php:48-52 · Checks: Source review; handler inventory
- Next step: Remove unearned verification claims; implement a genuine verification/evidence process.

**F20 — Email and phone verification** · *Not working at all* · P0

- Found: Phone presence is treated as “Phone verified”; emailVerified is inferred from an admin-set status label. There is no email-token or phone-OTP verification path.
- Sources: includes/view.php:104-118; assets/js/site.js:2188-2199; api/admin-action.php:48-52 · Checks: Source review
- Next step: Track verified timestamps only after independent verification challenges.

**F21 — Customer two-factor authentication/security centre** · *Not working at all* · P0

- Found: Every account is shown “SMS + authenticator”; there is no enrolment, secret/challenge, recovery-code or enforcement workflow. Manage Security only toasts.
- Sources: assets/js/site.js:2202-2207; api/account.php:1-44 · Checks: Source review
- Next step: Do not claim enabled 2FA until enrolment and enforcement exist.

**F22 — Personal data export/erasure** · *Not working at all* · P1

- Found: The account page says export and erase are ready, but no corresponding user workflow or endpoint is supplied. Admin CSV downloads are not personal-data export/erasure.
- Sources: assets/js/site.js:2202-2207; api/account.php:1-44 · Checks: Repository endpoint inventory
- Next step: Implement identity-checked export and retention-aware deletion; obtain compliance review.

**F23 — Currency display preference** · *Fully operational/functional* · Maintain

- Found: NGN/USD/GBP/EUR display conversion uses stored rates; the selected currency persists on reload. This does not provide FX settlement or a live exchange-rate feed: reservations are recorded in NGN.
- Sources: assets/js/site.js:2249-2255; assets/js/site.js:4168-4175; includes/helpers.php:193-207 · Checks: B19
- Next step: Label rates/display currency accurately and separate them from charging currency.

**F24 — Website language selection/localization** · *Not working at all* · P2

- Found: A language dropdown is displayed but has no persistence or localized UI/content pipeline. Concierge language claims are not website localization.
- Sources: assets/js/site.js:2212-2216; assets/js/site.js:2252-2255 · Checks: Source review
- Next step: Implement localization and saved language preference or remove the control.


### Reservations & money

**F25 — Checkout wizard and reservation/request creation** · *Partially functional* · P0

- Found: The complete browser wizard creates a booking and confirmation. Selected detail-page dates/policy are lost on navigation; returning to the first step breaks date-change binding. Confirmation does not depend on payment.
- Sources: assets/js/site.js:1071-1095; assets/js/site.js:1361-1616; includes/pricing.php:112-265 · Checks: A43, B08, B09, B37
- Next step: Preserve wizard state, rebind inputs and gate confirmation on verified payment/approval.

**F26 — Booking validation and inventory protection** · *Partially functional* · P0

- Found: Past dates and existing reservation overlaps are rejected, with ownership checks on web actions. Host-blocked and paused listings can still be booked; excess occupancy is silently clamped and the client can choose the cancellation policy.
- Sources: includes/pricing.php:112-143; includes/pricing.php:190; includes/pricing.php:275-286; api/booking-action.php:15-19 · Checks: A45-A47, A51, A66, B38
- Next step: Validate live status, blocks, capacity, policy and minimum stay server-side; add MySQL concurrency tests.

**F27 — Quotes, promotions, long-stay discounts and add-on pricing** · *Partially functional* · P0

- Found: Server-side totals and frozen breakdowns are real. Client promo math differs from PHP, calendar/rule prices are ignored, and admin campaigns do not create checkout promos. Tested JOLLOF10 quote differed by ₦10,548.
- Sources: includes/pricing.php:28-95; assets/js/site.js:423-449; api/admin-action.php:123-146 · Checks: A52, A68, B11
- Next step: Use one authoritative quote service, connect pricing inputs and revalidate totals before charging.

**F28 — Actual payment collection and gateway verification** · *Not working at all* · P0

- Found: No gateway charge/verification/webhook flow is supplied. Booking creates an initiated payment row, labels escrow held and can immediately mark a reservation confirmed. Choosing Card/Paystack/Flutterwave/USSD/etc. does not collect funds.
- Sources: includes/pricing.php:180-230; assets/js/site.js:1460-1506; api/booking-create.php:1-36 · Checks: A44, B37; integration inventory
- Next step: Integrate provider test mode, signed webhooks, amount/currency verification and idempotent payment state.

**F29 — Real escrow, refunds and host transfers** · *Not working at all* · P0

- Found: Escrow/released/refunded/paid are database labels. Check-in and cancellation change these labels without moving money; there is no custodial settlement/refund/payout implementation.
- Sources: includes/pricing.php:347-395; api/host-action.php:278-296 · Checks: A44, A53, A63; integration inventory
- Next step: Implement reconciled ledger and provider-supported settlement/refund/payout processes; remove unsupported escrow claims.

**F30 — Split-payment collection and security-deposit holds** · *Not working at all* · P0

- Found: A split flag halves the initial recorded amount, but no second charge, mandate or deposit authorization/release exists. A three-night booking can request a feature described as 30+-night-only.
- Sources: includes/pricing.php:93-94; includes/pricing.php:194-228; assets/js/site.js:1444-1451 · Checks: B39; source review
- Next step: Implement staged collection and deposit lifecycle with eligibility validation.

**F31 — My Trips/history and status display** · *Partially functional* · P1

- Found: Own reservations render on reload and are separated by status. Mutation refresh reads the wrong response shape, leaving displayed status stale; some trip messaging/key details are hardcoded.
- Sources: assets/js/site.js:333-341; assets/js/site.js:1713-1782; api/state.php:1-16 · Checks: guest trips route; B15
- Next step: Fix state synchronization and derive trip details from actual booking data.

**F32 — Reservation modifications** · *Partially functional* · P1

- Found: Requests are stored as booking events/enquiries. The booking dates/price are not changed and no host approval/application workflow exists, despite copy promising immediate rate adjustment.
- Sources: includes/pricing.php:399-418; api/booking-action.php:21-30; assets/js/site.js:1784-1800 · Checks: A62
- Next step: Add validated proposal, approval, repricing and payment/refund reconciliation.

**F33 — Cancellation handling** · *Partially functional* · P0

- Found: Cancellation changes booking/payment labels and releases the reservation. It does not calculate the advertised policy-based refund or actually refund; reversing points does not create a compensating ledger entry.
- Sources: includes/pricing.php:357-388; assets/js/site.js:1800-1820 · Checks: A63, A64; source review
- Next step: Calculate policy-based amounts, perform provider refunds and reconcile points and inventory atomically.

**F34 — Guest check-in/check-out** · *Partially functional* · P0

- Found: Status transitions persist, but a future unpaid reservation can be checked in immediately without date or access-code validation, releasing the escrow label and marking the payment paid. UI refresh is stale.
- Sources: includes/pricing.php:347-395; assets/js/site.js:1764-1782 · Checks: A53, A54, B15
- Next step: Enforce schedule, payment and confirmation rules before transitions; record immutable evidence.

**F35 — Smart-lock/keyless entry integration** · *Not working at all* · P0

- Found: A code is generated/stored, but no lock-provider provisioning, activation or expiry occurs. Trips also display a fixed 4471# code while claiming smart-lock synchronization.
- Sources: includes/pricing.php:205; assets/js/site.js:1758; assets/js/site.js:3916 · Checks: Source/integration inventory
- Next step: Hide access claims until a real device lifecycle has been integrated and tested.

**F36 — Invoices and receipts** · *Partially functional* · P1

- Found: An ownership-protected printable HTML invoice uses the frozen server quote and supports browser Save as PDF. The payments page includes invented receipts; no automatic PDF attachment service exists, and the client invoice preview uses current prices. Unauthorized GET content is withheld but incorrectly returns HTTP 200.
- Sources: api/invoice.php:15-59; api/invoice.php:127-149; assets/js/site.js:1658-1697; assets/js/site.js:3286-3297 · Checks: A48-A50; payments route checks
- Next step: Use one immutable invoice source, remove invented receipts and return appropriate HTTP statuses.

**F37 — Digital lease agreements for long stays** · *Not working at all* · P1

- Found: The UI claims an automatically signed/stored lease, but the agreement action only toasts and there is no document-generation/e-signature/vault workflow.
- Sources: assets/js/site.js:1444-1446; assets/js/site.js:1652 · Checks: Source review
- Next step: Build an auditable agreement workflow or remove the promise.

**F38 — Calendar exports and wallet passes** · *Partially functional* · P1

- Found: A Google Calendar template link exists, but it uses today+7/today+10 rather than selected reservation dates. Calendar/Wallet connection and automatic pass-generation claims are unsupported; no wallet-pass generator is supplied.
- Sources: assets/js/site.js:1163-1166; assets/js/site.js:1535; assets/js/site.js:1550; assets/js/site.js:2219 · Checks: Source/integration inventory
- Next step: Generate actual reservation events and passes; do not show fictitious connected states.

**F39 — Experience reservations and service fulfilment** · *Partially functional* · P1

- Found: Experience submission saves an enquiry and attempts email; it does not create an experience booking, reserve inventory or collect payment. The API accepts a past date and 9,999 guests. Stay add-ons can affect price, but supplier fulfilment is absent.
- Sources: api/experience-book.php:1-51; includes/pricing.php:39-50 · Checks: A24, A25; experiences route
- Next step: Distinguish enquiry from booking, validate capacity/dates and add fulfilment/availability workflow.

**F40 — Gift cards** · *Partially functional* · P0

- Found: Creation and balance lookup work as database operations, but cards can be issued unfunded, balances are not debited on checkout, any sufficiently long UI code grants ₦60,000, and arbitrary numeric credit can zero a reservation.
- Sources: api/giftcard.php:9-68; includes/pricing.php:69-71; assets/js/site.js:1699-1710 · Checks: A58-A61, B10
- Next step: Implement paid issuance and atomic authenticated redemption; never accept client-declared gift value.


### Customer tools

**F41 — Wishlists and named lists** · *Partially functional* · P1

- Found: Basic saving and list records persist. New list creation reads r.slug instead of r.data.slug and cannot refresh state; saved items can receive a “Removed” toast because r.saved is read from the wrong level.
- Sources: api/wishlist.php:1-55; assets/js/site.js:1862-1899 · Checks: A16-A18, B12, B13
- Next step: Align response envelopes and rehydrate named lists immediately after mutation.

**F42 — Property comparison** · *Partially functional* · P1

- Found: Server-side add/remove and comparison persistence exist, including a four-item limit. The browser reads r.compare rather than r.data.compare, so an add does not update the displayed comparison until reload.
- Sources: api/compare.php:1-19; assets/js/site.js:1903-1941 · Checks: A19, B14
- Next step: Use the returned compare array and update tray/table synchronously.

**F43 — Waitlist and availability-alert service** · *Partially functional* · P1

- Found: Account enrolment and duplicate prevention work. No cancellation/availability worker or price-drop monitor sends the promised future alerts.
- Sources: api/waitlist.php:9-33; includes/view.php:193-207 · Checks: A22; handler/worker inventory
- Next step: Implement monitored availability matching, notifications and unsubscribe/expiry controls.

**F44 — Loyalty points, ledger and tier progression** · *Partially functional* · P1

- Found: Points and ledger rows are written and displayed, but are awarded before payment; cancellations leave ledger and balance inconsistent. Tier promotion is not implemented. Progress parses “10,000 points” as 10 instead of using minPts.
- Sources: includes/pricing.php:98-109; includes/pricing.php:232-240; includes/pricing.php:384-388; assets/js/site.js:3367-3386; includes/repo.php:492-510 · Checks: A64; membership route; seeded tier calculation check
- Next step: Award only on settled eligible activity, use reversing ledger entries and implement threshold-based tier changes.

**F45 — Loyalty reward redemption** · *Not working at all* · P1

- Found: Redeem buttons only show a success toast. No balance check/deduction, redemption record, inventory reservation or fulfilment is performed.
- Sources: assets/js/site.js:3401-3404 · Checks: B24
- Next step: Implement atomic debit and redemption/fulfilment records before showing success.

**F46 — Referral and affiliate earnings** · *Not working at all* · P1

- Found: Real account referral codes are generated and can be shared from membership, but the referral centre displays a fixed code and fixed statistics. There is no referral attribution, qualification, bilateral credit or affiliate commission/payout engine.
- Sources: includes/auth.php:206; assets/js/site.js:3390-3399; assets/js/site.js:3436-3451 · Checks: B25; repository inventory
- Next step: Implement attribution and qualified-reward ledger; remove sample code/statistics.


### Communication & support

**F47 — In-app notification feed and read/unread state** · *Fully operational/functional* · Maintain

- Found: Notifications are stored per account, displayed, and can be marked read/all-read with persisted state; live-chat assignment and activity now genuinely enqueue in-app alerts for agents. This remains the internal feed only — no push/SMS/WhatsApp dispatch — and opening a new dispute still does not alert the staff queue (D33).
- Sources: includes/repo.php:709-740; api/notifications.php:1-21; assets/js/site.js:2022-2051 · Checks: A20, B16
- Next step: Add pagination and retention tests for long-lived accounts.

**F48 — Notification preferences** · *Partially functional* · P1

- Found: A preference API exists, but the browser sends on while PHP reads value, so enabling a setting saves 0. Modal defaults and delivery-channel descriptions do not reflect stored choices.
- Sources: api/notifications.php:24-31; assets/js/site.js:2036-2065 · Checks: A21
- Next step: Use stable preference keys, load persisted values and apply them to delivery workers.

**F49 — Push, SMS and WhatsApp notifications** · *Not working at all* · P1

- Found: The UI claims these channels are on; only a mobile push-token storage field is provided. No actual dispatch integration or scheduled reminder/price-drop delivery is supplied.
- Sources: assets/js/site.js:2036-2041; api/mobile/auth.php:95-106 · Checks: Integration/worker inventory
- Next step: Integrate providers, consent, queues, retries and delivery receipts.

**F50 — Newsletter signup/data capture** · *Fully operational/functional* · Maintain

- Found: The real footer/API signup validates email, deduplicates and stores subscribers. This does not include newsletter sending; the separate Journal top Subscribe control is only a toast.
- Sources: api/newsletter.php:1-30; assets/js/site.js:4178-4190 · Checks: A23; B23 identifies the separate stub
- Next step: Wire the Journal button to the existing subscription flow and use consent-aware mailing tools.

**F51 — Transactional email delivery** · *Partially functional* · P1

- Found: Email-sending code is expanded on this branch: live-chat transcripts on close, offline-agent nudges, and dispute opening/status/decision emails all call Mailer. Delivery was intentionally disabled in the audit, so no deliverability claim is made. Several callers ignore send results; the SMTP client does not validate SMTP reply status codes.
- Sources: includes/mailer.php:19-95; includes/mailer.php:129-180; api/invoice.php:143-149 · Checks: C/D-series Mailer call sites reviewed; A50; source review; external transport untested
- Next step: Test with a mail sandbox, validate SMTP replies and queue/retry/report failures accurately.

**F52 — Legacy inbox, concierge/support threads and host chat** · *Partially functional* · P0

- Found: The older messages page is unchanged: own conversation messages persist and the concierge can reply, but a listing’s Message CTA still opens concierge instead of the intended host thread, new users have no host thread, stored text still renders as HTML on reopen (B18), and read-receipt/media/translation claims remain unsupported. The genuinely new live-chat desk is assessed separately in F87/F88 and is the supported path for reaching staff.
- Sources: api/message-send.php:1-53; includes/repo.php:745-808; assets/js/site.js:1945-2019; assets/js/site.js:4054 · Checks: A27-A29, B17, B18; route checks
- Next step: Escape/sanitize messages, bind the chosen thread, model both participants and implement delivery/read workflows.

**F53 — AI concierge** · *Partially functional* · P1

- Found: A useful catalogue/rule-based responder works; an optional external model hook exists. It does not actually book, arrange suppliers or translate chat. Availability intent uses a fixed near-term window rather than the requested dates, and fallback replies overstate implemented services.
- Sources: includes/concierge.php:21-171; assets/js/site.js:2069-2174 · Checks: A26, A27; concierge route; external model untested
- Next step: Keep answers grounded in authoritative inventory and capabilities; add controlled, confirmed actions if desired.

**F54 — Advertised advanced AI suite** · *Not working at all* · P2

- Found: No implementations were found for the claimed collaborative recommendations, visual search, predictive pricing, automatic review summarisation, listing A/B optimisation, translation, sustainability scoring or automated risk models. Simple rules and stored text should not be presented as those services.
- Sources: assets/js/site.js:2134-2149; assets/js/site.js:3762-3774; includes/repo.php:1269-1305 · Checks: Source/integration inventory
- Next step: Remove “all live” claims or implement and evaluate each capability separately.

**F55 — Community reporting/blocking and emergency actions** · *Not working at all* · P0

- Found: Report-concern and blocked-users controls only toast, and emergency buttons only say “Dialling/connecting” without initiating a call. The dispute-resolution promise that previously sat on this page has been implemented as a real module and is now assessed in F89/F90; these community-safety controls are unchanged.
- Sources: assets/js/site.js:2223-2229; assets/js/site.js:3510-3519; assets/js/site.js:1976 · Checks: B27; source review
- Next step: Remove false safety confirmations immediately; implement real call links and report/block workflows with case handling.


### Owner workspace

**F56 — Owner account provisioning** · *Fully operational/functional* · Maintain

- Found: Owner signup creates the role/ownership flag, payout-settings row, channel rows and default templates; the owner workspace loads. This provisioning is not evidence of live channels, KYC or payouts.
- Sources: includes/auth.php:191-213; includes/auth.php:291-349; api/host-action.php:10-22 · Checks: A09, A10; owner route checks
- Next step: Test customer-to-owner upgrade alongside new-owner provisioning.

**F57 — Listing creation wizard and submission** · *Partially functional* · P1

- Found: The wizard creates pending listings, but selected city/type/policy are omitted by wizGather and submitted as Lagos/Apartment/moderate. Minimum stay, discount/rule selections and some other fields are not carried through; approved unreviewed listings can break detail rendering.
- Sources: assets/js/site.js:2470-2506; assets/js/site.js:2582-2688; api/listing-create.php:12-66 · Checks: A30, B28; failed new-listing detail routes
- Next step: Define a complete validated listing schema and persist every editable field with safe defaults.

**F58 — Listing photo upload/storage** · *Not working at all* · P1

- Found: Local files produce browser blob previews, but submission sends only filenames and the API stores those names. No binary upload/storage pipeline exists; the resulting uploaded-photo URL returned 404.
- Sources: assets/js/site.js:2545-2560; assets/js/site.js:2675-2680; api/listing-create.php:55-60 · Checks: B29
- Next step: Implement authenticated multipart uploads, validation, durable storage and gallery URLs.

**F59 — Save-and-return listing drafts** · *Not working at all* · P1

- Found: Wizard state exists only in a JavaScript object; refreshing loses title/progress despite the explicit “Progress is never lost” promise.
- Sources: assets/js/site.js:2450-2454; assets/js/site.js:2648-2665 · Checks: B30
- Next step: Persist versioned drafts server-side and restore them per account.

**F60 — Manage listing base price and publication status** · *Partially functional* · P1

- Found: Owners can update their own base price and pause/resume an approved listing, with cross-owner protection. Full detail/media editing is absent and pausing does not stop direct API reservations.
- Sources: api/host-action.php:117-154; assets/js/site.js:2818-2855 · Checks: A34, A35, A65, A66
- Next step: Provide complete listing editing and enforce status in every booking path.

**F61 — Calendar blocking, per-date prices and pricing rules** · *Partially functional* · P0

- Found: Month navigation, day/bulk overrides and rule records persist. Guest availability and Pricing::quote do not consume them: a blocked day remained bookable and a ₦400,000 override with +50% rule still quoted the ₦155,000 base rate.
- Sources: api/host-action.php:38-114; api/host-action.php:156-185; includes/repo.php:1181-1235; includes/pricing.php:28-35 · Checks: A36-A38, A51, A52, B31
- Next step: Integrate owner calendar/rules into one authoritative quote/availability engine.

**F62 — Host approval/decline of booking requests** · *Not working at all* · P0

- Found: Pending requests can be recorded and listed, but the owner workspace has no approve/decline workflow. Admin has a transition endpoint; mobile incorrectly lets the guest approve their own request. No automatic 24-hour expiry job was found.
- Sources: assets/js/site.js:2802-2816; api/host-action.php:1-301; api/admin-action.php:151-158; api/mobile/action.php:72-93 · Checks: M08; source/handler inventory
- Next step: Add host-owned decision endpoints/UI and expiry rules; prevent guest self-approval.

**F63 — Host dashboard, analytics and earnings forecasts** · *Partially functional* · P1

- Found: Dashboard data is queried by owner and charts/what-if tools render. “90-day” occupancy uses all sold nights divided by 90, financial figures include uncollected reservations, and the home-page earnings estimator omits the thousands multiplier used correctly on the host page.
- Sources: includes/repo.php:1010-1045; includes/repo.php:1095-1133; assets/js/site.js:821-829; assets/js/site.js:2422-2432; assets/js/site.js:2944-2955 · Checks: 10 owner tab checks; source review
- Next step: Define time windows and settled-money metrics consistently; correct estimator units.

**F64 — Co-host/team invitations and permissions** · *Partially functional* · P1

- Found: Invite/revoke records are saved and displayed, but no invitation delivery/acceptance, co-host account linkage or effective delegated permissions are implemented.
- Sources: api/host-action.php:188-228; assets/js/site.js:3186-3204 · Checks: A39; team route; authorization inventory
- Next step: Implement expiring invitations and enforce scoped team permissions on every endpoint.

**F65 — Manual message-template management** · *Fully operational/functional* · Maintain

- Found: Templates can be created, edited, removed and viewed for the signed-in owner; browser editing persisted and reloaded. Automatic use of those templates is a separate missing feature.
- Sources: api/host-action.php:231-257; assets/js/site.js:3133-3149; assets/js/site.js:3207-3221 · Checks: A40, B32; ownership/source checks
- Next step: Add full create/delete regression coverage and placeholder validation.

**F66 — Automatic scheduled/triggered guest messages** · *Not working at all* · P1

- Found: Triggers can be selected and stored, but no job or booking-event consumer sends the configured templates.
- Sources: api/host-action.php:231-247; includes/repo.php:1248-1251; assets/js/site.js:3138-3147 · Checks: Repository worker/call-site inventory
- Next step: Implement idempotent trigger processing and scheduled delivery.

**F67 — Two-way Airbnb/Booking.com/VRBO channel manager** · *Not working at all* · P0

- Found: Connect simply changes a database flag and last-sync time, then claims availability syncs both ways. No credentials, provider API, iCal import/export or synchronization worker is supplied.
- Sources: api/host-action.php:260-275; assets/js/site.js:3224-3240 · Checks: A41
- Next step: Do not claim connected until a real integration authenticates and successfully synchronizes.

**F68 — Payout settings and payout history** · *Partially functional* · P0

- Found: Bank name/account name/schedule and the last four digits are stored, and history can be read/exported. The full account/beneficiary information and bank validation needed to actually pay an owner are absent; no transfer execution exists.
- Sources: api/host-action.php:278-296; includes/repo.php:1258-1266; assets/js/site.js:3245-3271 · Checks: A42, H02; payout route
- Next step: Create verified provider beneficiaries and reconcile actual transfers before showing paid balances.

**F69 — Inspection, photography/tour scheduling and AI listing rewrite** · *Not working at all* · P2

- Found: These action buttons only toast; they do not create appointments, contact photographers or rewrite/save listing content. “KYC complete” and listing-optimisation suggestions are static.
- Sources: assets/js/site.js:2405-2407; assets/js/site.js:2523-2532; assets/js/site.js:2636-2643 · Checks: Source review
- Next step: Implement real service requests/content generation or label these as unavailable.


### Back office

**F70 — Admin authentication and role gate** · *Partially functional* · P0

- Found: Ordinary guests are denied admin pages/actions. The dedicated admin login can check a configured static OTP, but the ordinary login authenticates an admin without that OTP even when the option is enabled. The tracked configuration currently disables admin 2FA.
- Sources: api/admin-auth.php:1-43; api/auth.php:46-65; includes/auth.php:219-235 · Checks: A12-A14; admin route checks
- Next step: Enforce MFA centrally for every admin authentication path, including mobile; replace shared static OTP with actual MFA.

**F71 — User search and manual verification management** · *Partially functional* · P1

- Found: Loaded users can be searched and status/KYC flags can be changed. Only the first 200 are loaded with no paging; verification is not backed by an identity workflow and role assignment is not a complete permissions editor.
- Sources: includes/repo.php:813-825; api/admin-action.php:42-63; assets/js/site.js:3713-3747 · Checks: A71, B33; source review
- Next step: Add paginated user management and an evidence-backed verification/role workflow.

**F72 — Account suspension enforcement** · *Not working at all* · P0

- Found: Suspend updates users.status, but login/mobile authorization inspect users.status_level. A freshly suspended synthetic account could sign in again; existing sessions are not revoked by this action.
- Sources: api/admin-action.php:54-62; includes/auth.php:219-235; api/mobile/_mobile.php:106-124 · Checks: A71, A72
- Next step: Use one enforced account-state model; revoke sessions/tokens and block suspended users everywhere.

**F73 — Listing moderation** · *Partially functional* · P0

- Found: Pending listings appear in the queue; approve/reject persists status, audit entries and owner notifications. The preview link returns 404 for pending listings, reviewer notes are not collected, mobile can publish without approval, and newly approved listings can crash their public page.
- Sources: includes/repo.php:866-917; api/admin-action.php:16-38; assets/js/site.js:3684-3711; stay.php:9-16 · Checks: A31-A33, M11; detail route failures
- Next step: Provide authenticated preview, require moderation consistently and validate publish-ready data.

**F74 — Review publication/rejection and aggregate recalculation** · *Partially functional* · P1

- Found: Admin publication/rejection persists and recalculates non-empty published aggregates. Rejecting the last published review leaves its old rating and review count in place rather than resetting them. Mobile review eligibility is also weaker than web.
- Sources: api/admin-action.php:173-187; includes/repo.php:584-596 · Checks: A55-A57, A76
- Next step: Reset zero-review aggregates, calculate category scores consistently and unify submission eligibility.

**F75 — Promotion/campaign management** · *Partially functional* · P1

- Found: Campaign records save and can be edited. Checkout reads promos, not campaigns, so creating a Live campaign does not create a usable discount. Revenue attribution/scheduling is not implemented by those records.
- Sources: api/admin-action.php:123-146; includes/repo.php:108-127; assets/js/site.js:3749-3759; assets/js/site.js:3978-3992 · Checks: A67, A68, B34
- Next step: Unify campaign and promo rules, dates, eligibility, limits and redemption attribution.

**F76 — Content management/public publishing** · *Partially functional* · P1

- Found: CMS blocks can be created, edited and toggled in the back office. Public templates do not consume those blocks; publishing a unique test block did not change the home page. No full journal/property content editor is provided.
- Sources: api/admin-action.php:85-119; includes/repo.php:840-847; assets/js/site.js:3777-3786; assets/js/site.js:3962-3975 · Checks: A69, A70, B35; public-template inventory
- Next step: Map block keys to actual pages and implement preview/publication behavior.

**F77 — Fraud case monitoring and escalation** · *Partially functional* · P1

- Found: Stored flags can be listed, resolved or relabelled escalated, and dispute refunds now raise genuine rule-based flags (D21) that appear in the queue. There is still no risk-signal model/AI engine producing other flags and no escalation routing to a named team; an empty list is not proof the platform is safe.
- Sources: api/admin-action.php:67-82; includes/repo.php:830-832; assets/js/site.js:3762-3774 · Checks: D21; fraud route; source/call-site inventory
- Next step: Implement real signals, case ownership and escalation; remove unsupported live-AI assurances.

**F78 — Admin and owner CSV record exports** · *Fully operational/functional* · Maintain

- Found: All eight supplied admin CSVs and both owner CSVs returned data in CSV form. Admin access and host ownership scoping exist. These export stored records, not independently verified cash, remittances or accounting statements.
- Sources: api/report.php:6-80; api/host-report.php:6-56 · Checks: R01-R08, H01-H02
- Next step: Add accounting reconciliation, spreadsheet-formula hardening and large-export tests.

**F79 — Audit logging and audit-log viewer** · *Partially functional* · P1

- Found: Actions generate database log records which can be viewed/exported. The claimed “immutable” log is an ordinary mutable table without append-only/tamper-evidence controls; not every business claim is backed by a real audited operation.
- Sources: includes/helpers.php:244-255; api/report.php:55-69; assets/js/site.js:3819-3829 · Checks: R08; admin audit route; source review
- Next step: Define audited events and protect logs with restricted append-only storage and retention.


### Business services

**F80 — Corporate demo requests, company travel portal and consolidated billing** · *Not working at all* · P1

- Found: The business demo button does not submit anything; corporate metrics are fixed. No company/team travel portal, purchase-order workflow, departmental policy engine or consolidated invoice implementation is supplied.
- Sources: assets/js/site.js:3315-3329; assets/js/site.js:3535-3556 · Checks: B22; repository endpoint inventory
- Next step: Start with a real enquiry form; build company/employee/billing models before promising a corporate portal.


### Mobile

**F81 — Native mobile app and store distribution** · *Not working at all* · P2

- Found: This checkout includes backend mobile endpoints and an app marketing page, not an Android/iOS application build. Store buttons say demo/coming soon rather than linking to installable builds. Biometrics and voice-assistant booking are not supplied.
- Sources: assets/js/site.js:3909-3925; app.php:1-11 · Checks: B26; tracked-file inventory
- Next step: Treat the app as unshipped until native artifacts and distribution links are delivered.

**F82 — Mobile backend API** · *Partially functional* · P0

- Found: Bearer login, token revocation, catalogue/member sync and sequential booking replay work. Message-send and notifications-read still return 500 against the supplied schema (read_at does not exist — reconfirmed on this branch). Review eligibility, host moderation and approval authorization still differ from web; repeat wishlist replay toggles twice.
- Sources: api/mobile/_mobile.php:71-137; api/mobile/_mobile.php:194-228; api/mobile/action.php:72-177; api/mobile/action.php:235-254 · Checks: M01-M12
- Next step: Share schema and centralized authorization/business validation; make replay atomic and endpoint/payload scoped.


### Platform

**F83 — Client/server state refresh after mutations** · *Not working at all* · P1

- Found: The shared refresh function expects r.data, while state.php sends the mutable state under r.state, so the common refresh never hydrates the client. Individual optimistic/reload-based paths still work. On this branch a freshly filed dispute also fails to appear in the member case list without interaction (U15), while the new chat widget repaints correctly.
- Sources: assets/js/site.js:333-341; api/state.php:1-16; api/_api.php:64-72 · Checks: B13-B15, U15
- Next step: Standardize the JSON contract and test every mutation through to the repainted screen.

**F84 — Clean installation from this repository alone** · *Not working at all* · P1

- Found: The supplied installer is already locked and still references the missing ../database/schema.sql and ../database/seed.sql, so a brand-new install cannot complete from this repository alone — although it now additionally runs install/schema/*.sql, which correctly creates the ten support tables (T05) when reached. A private-data-bearing SQL dump is not a safe substitute for a schema/migration package.
- Sources: install/index.php:10-22; install/index.php:54-64; install/installed.lock · Checks: Tracked-file inventory; T04/T05 context
- Next step: Ship safe schema/migrations/seed data, environment-based config and installation/deployment documentation.

**F85 — SEO metadata, sitemap and clean URLs** · *Partially functional* · P1

- Found: Titles/descriptions/OG metadata and sitemap.php exist. Canonicals strip query parameters, making distinct /stay.php?p=... pages share a canonical. robots.txt points to absent sitemap.xml, and pretty detail URLs lack supplied rewrite rules. In the isolated server, /stay/onyx resolved to the home page, not the listing.
- Sources: includes/view.php:226-258; includes/view.php:369-377; sitemap.php:1-44; robots.txt:1-15 · Checks: Local canonical/sitemap probes; source review
- Next step: Ship server rewrites, correct canonical detail URLs and sitemap location; improve server-rendered public content.

**F86 — Responsive and accessible user interface** · *Partially functional* · P1

- Found: The sampled pages have no horizontal overflow at 390px, the mobile menu works, and the new chat widget and dispute modal also fit a 390px viewport (U23/U24). Eight axe WCAG scans still find serious contrast failures, missing form/select/button accessible names (including inside the live-chat home screen and dispute form) and a scrollable-but-unfocusable region on the agent desk. No screen-reader, Safari/iOS or comprehensive keyboard audit was performed.
- Sources: assets/css/site.css:155-180; includes/view.php:312-350; assets/js/site.js:855-875; assets/js/site.js:1380-1419 · Checks: 7 RESP-* checks, U23, U24, B36; eight axe scans
- Next step: Fix semantic controls/labels, contrast, focus and keyboard behavior; test additional browsers/devices.


### Live support & disputes

**F87 — Live-chat visitor widget and queues** · *Partially functional* · P0

- Found: A real two-way support channel now exists: visitors pick one of five seeded queues, start a session that queues correctly, exchange escaped messages with the assigned agent, see queue position and typing indicators, resume the same chat after reload through a browser-stored private token, end the chat into the rate state, and everything is capped, throttled and CSRF-guarded. Unsent drafts are wiped by the 4-second poll re-render (U04), the visitor close endpoint performs no ownership check and hands the entire transcript back to any caller holding the ref (C21/C41 — private text leaked to a stranger), typing beacons and satisfaction ratings skip their ownership/closed-state rules (C14/C15), and there are no attachments, media or translation despite marketing copy elsewhere.
- Sources: includes/livechat.php:514-614; includes/livechat.php:627-657; includes/livechat.php:814-847; api/chat.php:91-112; api/chat.php:211-233; api/chat.php:249-262; assets/js/chat.js:254-330 · Checks: C01, C02, C05-C11, C13-C15, C21 (fail), C41 (fail), U01-U04, U09, U10, U23, U27
- Next step: Require the visitor token/ownership on close exactly as post/rate do and return no transcript to strangers; persist drafts across re-renders; keep capability claims honest until attachments/media/translation exist.

**F88 — Agent console, routing and staff management** · *Partially functional* · P0

- Found: The day-to-day desk is genuinely built against the database: waiting/mine/team/recent lists, claim with capacity limits, supervisor transfer with audit trail, agent replies, internal notes that stay hidden from visitors (C12), canned replies with {agent} substitution and use counts, online/away/busy status, metrics, manual sweep, and full admin CRUD for agents, queues, replies and settings. But a limited agent can open and read a transcript from another queue (C17); the canned list/“use” paths ignore is_active and personal scope, so a disabled or someone-else’s reply can be sent (C38/C39); a partial agent edit silently resets max_chats to 3 (C30); a failed validation still persists a partial agent row (C40); pausing an agent does not revoke the existing console session (C34); and — most severe — setting a temporary password while saving an agent escalates the matched or newly created user to full platform admin regardless of the chosen agent role (C32), which then reaches every-user CSV exports (C33) and survives pausing.
- Sources: includes/livechat.php:306-434; includes/livechat.php:660-678; includes/livechat.php:976-990; includes/livechat.php:1104-1164; api/chat.php:50-65; api/chat.php:308-337; assets/js/chat.js:363-472; assets/js/chat.js:662-763 · Checks: C09, C12, C14-C20, C26-C31, C33, C34, C36-C40, U05-U08, U26; agent route checks
- Next step: Scope every read/write by queue membership and active/scope flags; honour partial updates; gate user creation/role changes behind an explicit admin action; revoke sessions when an agent is paused; never set roles from saveAgent.

**F89 — Dispute resolution centre for guests and hosts** · *Partially functional* · P1

- Found: The old fake “File a dispute” toast is replaced by a real workflow: eight seeded categories with SLAs, booking linkage that rejects another member’s reservation, case creation for signed-in and anonymous visitors, an escaped timeline both parties can post to, evidence links, withdrawal, post-decision satisfaction ratings, and staff notes hidden from claimants. But withdrawal also accepts the counterparty host (D17), a withdrawn case can still be resolved (D18), evidence accepts unsafe javascript: URLs rendered as clickable links (D10), the back-office “Reopen (decision stands)” button sends the wrong status and does nothing (U19), and the emailed/printed “private link” carries no token — the token lives only in the filing browser’s storage, so an anonymous claimant cannot reopen the case from another device (U22 got a 403 by design of the flow).
- Sources: includes/disputes.php:133-271; includes/disputes.php:282-302; includes/disputes.php:456-515; includes/disputes.php:710-749; api/dispute.php:91-201; assets/js/chat.js:1011-1108 · Checks: D01-D11, D17-D19, D22, D23, D25-D27, D32, U11-U15, U21, U22
- Next step: Restrict withdraw to the claimant, fix the reopen payload, store only safe attachment URLs, reject decisions on withdrawn cases, put the (single-use, revocable) token in the shared link/email, and refresh the case list after filing.

**F90 — Mediation back office and decision outcomes** · *Partially functional* · P0

- Found: Admins claim and assign cases, move them through a real server-validated transition table, and record a written decision that notifies both parties, files a booking event, raises a rule-based fraud flag on host refunds and writes a refund ledger row — a major step beyond the previous toast. The money is still bookkeeping only: gateway “dispute”, no provider movement (D19/D20); several decisions can exceed the booking total (D31) and a full-refund decision can mark escrow refunded while the booking row itself stays pending (D30); a no_action decision still records the typed refund (D34); the second decision on a closed case returns 500 after already mutating the case (D24); internal-note text leaked into the claimant’s notification body (D16); the counterparty can overwrite the claimant’s satisfaction rating (D23); staff power is the platform-admin role only (D29) and new disputes don’t alert the queue (D33).
- Sources: includes/disputes.php:518-566; includes/disputes.php:569-600; includes/disputes.php:608-707; api/dispute.php:205-283; assets/js/chat.js:914-950; assets/js/chat.js:1154-1208 · Checks: D12-D16, D18-D21, D24, D27-D34, U16-U19; disputes admin route
- Next step: Drive financial outcomes through a real settlement pipeline (or label decisions “recorded, not yet paid”), enforce one reconciled refund total, wrap decide+event+flag mutations transactionally with idempotency keys, separate per-party ratings/notification visibility, and add staff roles beyond plain admin.

**F91 — Support-module database migration runner** · *Partially functional* · P1

- Found: install/migrate.php correctly demands an admin session with CSRF (an anonymous run creates nothing, T04), creates all ten tables (T05) and seeds eight dispute categories, five chat queues, seven canned replies and the administrator as first supervisor agent (T07); Help/admin pages then consume the module cleanly. The promised run-again safety holds on MySQL where the SQL declares UNIQUE keys, but not here: the SQLite translator strips those keys and the repair step uses MySQL-only ALTER syntax, so a second run duplicated the seeds (8→16, 5→10, 7→14) while reporting success (T06).
- Sources: install/migrate.php:36-71; install/migrate.php:189-271; install/migrate.php:316-342; install/schema/2026_09_12_disputes_and_live_chat.sql:10-96; install/schema/2026_09_12_disputes_and_live_chat.sql:245-291 · Checks: T01-T07, T11
- Next step: Make seed-key repair dialect-aware (create SQLite unique indexes or wrap seeds in NOT EXISTS like the other statements), verify row counts after every run, and prove double-run idempotency against native MySQL before relying on the deploy guide’s claim.

**F92 — Deployment self-check and failure-trap tooling** · *Partially functional* · P1

- Found: install/diagnose.php answers anonymous visitors with a sign-in prompt and, for an admin, renders the full self-check (extensions, per-table columns, file locations, reserved-word scan) with HTTP 200 (T09/T10). install/why.php runs a chosen page and surfaces its fatal, following that page’s own access rules — a public page stays publicly probeable by design (T13). The reserved-word scan exists and the deployed livechat fix (quoted `AS load`) is in place. Both tools are information-disclosure risks if left on a live host, the scan is heuristic, neither was exercised against native MySQL, and agent.php’s own table lookup sits outside the graceful guard — with the chat table missing it still 500s (T12).
- Sources: install/diagnose.php:173-192; install/diagnose.php:362; install/why.php:14-47; install/why.php:76-110; install/migrate.php:213-271; includes/view.php:83-118; agent.php:14-22 · Checks: T09, T10, T12 (fail), T13; DEPLOY guide §0 review
- Next step: Wrap agent.php’s lookup in the same tableExists/try degradation, then remove or network-restrict the install/ tools in production and keep the guide’s “run migrate before exposing /agent” warning front and centre.

## Repair order

1. **Contain:** fix chat-close authorization and remove the admin escalation from `saveAgent`; revoke paused sessions; keep every carried P0 from the previous audit open until fixed (secret rotation, payments, gift cards, calendar, suspension/OTP, mobile schema).
2. **Make dispute money honest:** reconcile refunds through a real settlement pipeline or label decisions record-only; enforce one total per booking; make decision writes transactional and idempotent; fix reopen payload, withdrawal roles, evidence URL schemes, and share a tokenized claimant link.
3. **Harden the desk:** scope all reads by queue; honour canned-scope/active flags; true partial updates; validate before persisting; keep composer drafts across repaints; repair the admin-tab settings host.
4. **Deploy safety:** make the migration idempotent across both dialects with verified counts; remove or network-restrict `install/` tools on the live host; guard `agent.php`’s table lookup like `view.php` already guards help/admin.

## Method, limits, integrity

Same isolation as the first audit: the tracked `includes/config.php` was parsed for syntax only, never executed; the fixture uses the sample config; mail disabled; synthetic accounts only. This branch’s module migration was applied inside the sandbox so the new APIs could be exercised; the six MySQL-declared UNIQUE indexes were restored by hand for a second fixture run to separate harness artifacts from product behaviour (documented per check).

**Unverified:** native MySQL semantics (migration double-run, the `AS load` reserved-word fix beyond source review), real SMTP/gateway/lock/channel services, the live deployment state, load, full accessibility conformance, devices/browsers, and any operational service performed outside this repository. **No application files were modified**; only this report set under `docs/` was added.