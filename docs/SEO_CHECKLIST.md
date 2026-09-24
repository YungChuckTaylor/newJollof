# Jollof Living — SEO Implementation Guide

This document explains every SEO improvement shipped in this changeset, why it
matters, and anything you need to do at deploy time. Nothing here requires a
framework change — it is all plain PHP/Apache configured for cPanel hosting.

---

## 1. What was implemented

### 1.1 Crawlable HTML (server-side rendering) — the big one
Before this change, every page shipped a `<main id="view">` loading splash and
required `site.js` (304 KB) to paint **all** content. Crawlers that don't run
JavaScript saw an empty page.

Now every public page server-renders real content into `#view` before the JS
bundle boots:

| File | What it renders |
|---|---|
| `includes/ssr.php` (new) | Reusable SSR builders: hero, breadcrumbs, stay cards, link cards, FAQs, testimonials, article body |
| `index.php` | H1 + featured stays + collections + experiences + testimonials |
| `stays.php` | H1 + all live residences as real `<a>`-linked cards |
| `collections.php`, `neighborhoods.php`, `experiences.php`, `blog.php` | H1 + full link cards |
| `stay.php` | Full listing: breadcrumbs, H1, photo, description, specs, price, booking CTA, related stays |
| `neighborhood.php` | Guide + the residences inside that neighbourhood |
| `blog-post.php` | Full article body + related posts |
| `reviews.php` | Latest verified reviews |
| `help.php` | Categories + full FAQ text |
| `membership.php` | Tier cards + perks |
| `404.php` | Helpful 404 with links |
| all remaining public pages | Keyword hero + copy + links |

The SSR markup reuses the design-system classes, so it looks identical to the
JS-rendered app; `site.js` still takes over seamlessly when it loads. Crawlers
and no-JS visitors now get the full page.

### 1.2 Sitemap (`sitemap.php`)
- Now emits the **pretty canonical URLs** (`/stay/onyx`, `/blog/…`, `/neighborhood/…`)
- Adds `<lastmod>` from real DB timestamps / file mtimes (Google ignores
  `changefreq`/`priority`; `lastmod` is the signal that matters)
- Adds the **image sitemap extension** for every property and journal photo
- Includes the **Jollof Automations subsite** (`/automations/…`)
- Served at `/sitemap.xml` via the rewrite rule in `.htaccess`
- `robots.txt` points to it (production URL)

### 1.3 Pretty URLs + `.htaccess` (new file)
- `stay.php?p=onyx` ⇄ `/stay/onyx`, plus `booking`, `neighborhood`, `blog`, `confirm`
- Clean static pages: `/stays`, `/experiences`, `/neighborhoods`, …
- Subsidiary site consolidated: `/jollof-automations/*` → **301** → `/automations/*`;
  `automations.php` → **301** → `/automations/` (the stub now redirects in PHP too)
- The rules are written **relative to the folder containing index.php**, so they
  work both at the document root and in a sub-folder install (the current
  staging setup lives in `/jollof`).
- Compression (mod_deflate), 1-year immutable caching for fonts/images/CSS/JS,
  `ErrorDocument 404 /404.php`, and hardening that blocks `.sql`, `.zip`, `.git`,
  `docs/` and dotfiles from ever being served.

> **Deploy note:** upload `.htaccess` next to `index.php`. It is required for
> the pretty URLs in the sitemap/canonical tags to resolve. If the site lives
> in a sub-folder, change `ErrorDocument 404` to `/subfolder/404.php`
> (marked with a SUB-FOLDER NOTE in the file).

### 1.4 Canonical URLs & duplicate cleanup (`includes/view.php`)
- Every page prints a canonical tag that maps query URLs onto the pretty path
  (`/stay.php?p=onyx` → `…/stay/onyx`), so search engines keep one URL per page
  even though internal links use the resilient `.php` form.
- `404.php` now sends a real **404 status** (was a soft-404 200).
- Private surfaces (`account`, `trips`, `messages`, `payments`, `confirm`,
  dashboards, auth, reset, agent…) print `<meta name="robots" content="noindex">`.
- The **Platform admin** link was removed from the footer of every page.

### 1.5 Titles & meta descriptions
- `Repo::pageMeta()` has keyword-first defaults per page ("Luxury Short-Let
  Apartments in Lagos & Abuja | Jollof Living" — brand last).
- A migration updates the seeded `page_meta` rows (see 1.8) and descriptions
  are hard-truncated to ~158 chars in `View::header()` so snippets never cut off.
- Meta tags added: `og:site_name`, `og:locale` (en_NG), `twitter:title`,
  `twitter:description`, `twitter:image`, `article` og:type on journal posts.

### 1.6 Open Graph / WhatsApp fix
`og:image` was a **relative** path (`/assets/img/hero.jpg`) — Facebook,
LinkedIn and WhatsApp ignore those. It is now always absolute
(`View::absoluteAsset()`), using JPG copies (max compatibility) and the
listing's own photo on stay pages.

### 1.7 Structured data (JSON-LD) — none existed before
- **Organization + WebSite** graph on every main-site page
- **Accommodation + Offer + AggregateRating + BreadcrumbList** on stay pages
  (feeds Google's rich results for the ratings you already collect)
- **Article + publisher + breadcrumbs** on Journal posts
- **FAQPage** on the Help Centre
- **HomeAndConstructionBusiness + parentOrganization** on Jollof Automations
- Validate after deploy: https://search.google.com/test/rich-results

### 1.8 Migration: `install/schema/2026_09_24_seo_metadata.sql`
Keyword-first titles/descriptions for all `page_meta` rows. Idempotent: it only
overwrites rows that still hold the original copy, and inserts rows that are
missing. `install/migrate.php` now runs it automatically on migration.

### 1.9 Core Web Vitals
- **WebP** copies of all 17 photos (`assets/img/*.webp`, ~35–45 % smaller);
  `View::images()` prefers WebP everywhere automatically
- **Minified bundles**: `site.min.js`, `chat.min.js`, `site.min.css` —
  `View` serves them automatically when present.
  After editing JS/CSS run `npm install && npm run build`
  (`tools/build-assets.mjs`), or delete the `.min` files while developing.
- **Preloads**: the two variable fonts (Cormorant Garamond, Jost) and the
  page hero image (`fetchpriority=high` on the homepage)
- Intrinsic `width`/`height` on content images (less layout shift)
- Long-lived immutable caching + gzip via `.htaccess`
- Jollof Automations: Cormorant Garamond now self-hosted (was a render-blocking
  Google Fonts download for that face)

### 1.10 Analytics & Search Console plumbing
New optional config keys (`includes/config.sample.php`):

```php
'ga4_id'              => 'G-XXXXXXXXXX',   // or use gtm_id instead
'gtm_id'              => 'GTM-XXXXXXX',
'google_verification' => '…',              // <meta> content value
'bing_verification'   => '…',
```

Snippet output is completely skipped while the keys are empty.

### 1.11 Internal linking / topic clusters
- Neighbourhood guides now list the residences inside them
- Stay pages link to related residences in the same city
- Journal posts link to further reading + stays
- Footer NAP block (city + email + phone) for local SEO
- Automations footer no longer links the internal Leads Desk

### 1.12 Jollof Automations subsite
- Unique keyword-first `<title>` + `<meta description>` per page
- Canonical (`/automations/…`), OG + Twitter cards, `og:image`
- JSON-LD `HomeAndConstructionBusiness`
- `leads.php`: `noindex, nofollow`
- Local Cormorant font

---

## 2. Deploy checklist (in order)

1. **Upload `.htaccess`** next to `index.php` (required — the sitemap and
   canonical tags point at pretty URLs that resolve through it).
   - Sub-folder install: adjust the `ErrorDocument 404` path.
2. **Upload the code + `assets/`** (including the new `.webp` and `.min.*`
   files — deploy the built assets, not just sources).
3. **Run the migration**: sign in as an admin and open
   `/install/migrate.php` → press *Run migrations* (applies the SEO metadata).
4. **Set the production URL** in `includes/config.php`
   (`site.url`) when you go live — every canonical, OG image and sitemap URL
   is built from it. (Currently the staging URL `kxq.lop.temporary.site/jollof`.)
5. Optionally uncomment the **force-https/www** block in `.htaccess` once the
   production certificate is in place.
6. **Google Search Console** (https://search.google.com/search-console):
   - Add property `https://www.jollofliving.com`
   - Put the verification token in `config.php` → `google_verification`
   - Submit `https://www.jollofliving.com/sitemap.xml`
   - Also add `https://www.jollofliving.com/automations/` coverage via the same sitemap (it includes those URLs)
7. **Bing Webmaster Tools**: import from GSC, set `bing_verification`.
8. **GA4**: create a property, put `G-…` into `ga4_id` (or GTM container into `gtm_id`).
9. **Rich results test**: run the homepage, one stay page and one blog post
   through https://search.google.com/test/rich-results.
10. **Google Business Profile**: create/claim a profile for the Lagos business
    address — the footer NAP and `Organization` contactPoint are consistent
    with it. This is what wins the local pack for "luxury short-let Lagos".

---

## 3. Ongoing editorial SEO (no code required)

- Keep publishing Journal posts that link to the stays they mention
  (2–4 per month is plenty) — the SSR article template already handles markup.
- Write unique 1–2 sentence intros for every neighbourhood guide.
- Fill every property's `description` with at least 3–4 specific sentences
  (it feeds the meta description, OG preview and `Accommodation` schema).
- Get verified guests to leave reviews — they feed `AggregateRating`.

---

---

## 4. Troubleshooting a blank HTTP 500 after upload (read this first!)

The cPanel **Metrics → Errors** page only shows *Apache* errors. PHP fatal
errors are logged by the app itself to **`public_html/storage/logs/php-error.log`**.

### Where is my site root? (HostGator temporary domains)

A 404 for `/jollof/health.php` means the file is not in the folder that
serves this URL. On this account, subdomains live in different places:

- `File Manager → public_html → jollof/` — if this folder exists, then
  `/jollof/…` URLs are served from `public_html/jollof/`.
- Some subdomains get their OWN folder instead, e.g.
  `/home2/wal7zkit4bnv/kxq.lop.temporary.site/` (the error log shows this
  pattern for other sites on the account).

**10-second probe:** in cPanel File Manager, create `probe.php` inside the
folder you believe serves the site, containing exactly:

```php
<?php echo __DIR__;
```

then open the matching URL (e.g. `…/jollof/probe.php`). If you get a 404 →
wrong folder. If it prints a path → THAT is the folder serving your URL;
upload all site files there. Delete the probe afterwards.

**Getting the latest files:** download the whole updated branch in one click
and extract it over the site folder (tick "overwrite existing files"):

```
https://github.com/YungChuckTaylor/newJollof/archive/refs/heads/arena/01a0d3cf-newjollof.zip
```

**Fastest diagnosis path:**

1. Upload **`health.php`** (in the repo root) next to `index.php` and open
   `https://<host>/jollof/health.php`. It is standalone (loads nothing from
   the app) and checks PHP version, extensions, every required file
   (catches partial uploads), storage writability, and the DB connection —
   including the classic cPanel gotcha of missing the account-name prefix.
2. Set `'debug' => true` in `includes/config.php`, reload the homepage, and
   the real error prints on screen. Set it back to `false` afterwards.
3. New fatal-error handler (added to `includes/bootstrap.php`): any hard
   fatal now renders a friendly page pointing at the log instead of a blank 500.

**The two causes this deployment actually had:**

| Symptom | Cause | Fix |
|---|---|---|
| Blank 500, empty Apache log | Partial upload — e.g. new pages calling `SSR::` while `includes/ssr.php` was never uploaded (or an old `view.php` mixed with new pages) | Re-upload the **whole** folder; `health.php` lists any missing file by name |
| 503 "Database connection failed" | cPanel prefixes DB name/user with the account name (`wal7zkit4bnv_…`) — config shipped unprefixed values | cPanel → MySQL® Databases: create DB + user, import `wal7zkit_jollof.sql` via phpMyAdmin, then put the exact prefixed values in `includes/config.php` |

Upload checklist of files NEW in the SEO update (all must exist on the server):

```
.htaccess                                  → public_html/jollof/
health.php                                 → public_html/jollof/  (delete after use)
includes/ssr.php                           → public_html/jollof/includes/
install/schema/2026_09_24_seo_metadata.sql → public_html/jollof/install/schema/
sitemap.php  (replaced)                    → public_html/jollof/
assets/img/*.webp   (17 new files)         → public_html/jollof/assets/img/
assets/js/site.min.js, assets/js/chat.min.js, assets/css/site.min.css
tools/build-assets.mjs, package.json, docs/SEO_CHECKLIST.md
```

---

## 5. Security note (spotted while auditing)

`includes/config.php` and `jollof-automations/config.php` are **tracked in
git and contain live database credentials** (`Chinwike@100`, admin keys). The
sample config explicitly says these must never be committed. Consider rotating
that password, moving it out of git (`git rm --cached` + `.gitignore`, which
this change adds), and sharing it via cPanel directly. The new `.htaccess`
also blocks `*.sql`/`*.zip` dumps from being downloaded.
