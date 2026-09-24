<?php
/**
 * Jollof Living — view layer.
 * Builds the window.JL payload (all content straight from MySQL) and
 * renders the shared chrome: header, drawer, footer, overlays.
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}


if (class_exists('View', false)) { return; }

/* SSR builders. Defence in depth: if includes/ssr.php is missing or was
   truncated by a half-finished upload, degrade to the inline shim below
   instead of fataling every page with "Class SSR not found". */
if (is_file(__DIR__ . '/ssr.php')) {
    require_once __DIR__ . '/ssr.php';
}

if (!class_exists('SSR', false)) {

/**
 * Degraded SSR shim — only used when includes/ssr.php cannot be loaded.
 * Renders simple, valid, crawlable HTML so the site stays up until the
 * real file is restored.
 */
final class SSR
{
    public static function icon(string $name): string
    {
        return '';
    }

    public static function crumbs(array $items): string
    {
        $out = '<nav class="ssr-crumbs wrap">';
        $last = count($items) - 1;
        foreach (array_values($items) as $i => [$label, $href]) {
            $out .= ($href !== null && $i !== $last)
                ? '<a href="' . e((string) $href) . '">' . e((string) $label) . '</a>'
                : '<span>' . e((string) $label) . '</span>';
            if ($i !== $last) {
                $out .= ' / ';
            }
        }
        return $out . '</nav>';
    }

    public static function secHead(string $eyebrow, string $h2, string $lead = '', bool $center = false, string $h2Tag = 'h2'): string
    {
        $h = '<div class="sec-head">';
        if ($eyebrow !== '') {
            $h .= '<span class="eyebrow">' . e($eyebrow) . '</span>';
        }
        $h .= '<' . $h2Tag . '>' . e($h2) . '</' . $h2Tag . '>';
        if ($lead !== '') {
            $h .= '<p>' . e($lead) . '</p>';
        }
        return $h . '</div>';
    }

    public static function cta(string $href, string $label, string $ghostHref = '', string $ghostLabel = ''): string
    {
        $h = '<p><a class="btn btn-gold" href="' . e($href) . '">' . e($label) . '</a>';
        if ($ghostHref !== '') {
            $h .= ' <a class="btn btn-ghost" href="' . e($ghostHref) . '">' . e($ghostLabel) . '</a>';
        }
        return $h . '</p>';
    }

    public static function stayCard(array $p, bool $lazy = true): string
    {
        return '';
    }

    public static function stayGrid(array $props, int $limit = 6, bool $lazy = true): string
    {
        $lis = '';
        $n = 0;
        foreach ($props as $p) {
            if ($n++ >= $limit) {
                break;
            }
            $href = url('stay.php?p=' . rawurlencode((string) ($p['id'] ?? '')));
            $lis .= '<li><a href="' . e($href) . '">' . e((string) ($p['name'] ?? 'Residence')) . '</a> — '
                . e(money((int) ($p['price'] ?? 0))) . '/night</li>';
        }
        return $lis === '' ? '' : '<ul class="ssr-links">' . $lis . '</ul>';
    }

    public static function linkCards(array $items, int $limit = 8): string
    {
        $lis = '';
        $n = 0;
        foreach ($items as $it) {
            if ($n++ >= $limit) {
                break;
            }
            $lis .= '<li><a href="' . e((string) $it['href']) . '">' . e((string) $it['title']) . '</a></li>';
        }
        return $lis === '' ? '' : '<ul class="ssr-links">' . $lis . '</ul>';
    }

    public static function linkList(array $items, int $limit = 12): string
    {
        $lis = '';
        $n = 0;
        foreach ($items as $label => $href) {
            if ($n++ >= $limit) {
                break;
            }
            $lis .= '<li><a href="' . e((string) $href) . '">' . e((string) $label) . '</a></li>';
        }
        return $lis === '' ? '' : '<ul class="ssr-links">' . $lis . '</ul>';
    }

    public static function faqs(array $faqs, int $limit = 14): string
    {
        $h = '<dl>';
        $n = 0;
        foreach ($faqs as [$q, $a]) {
            if ($n++ >= $limit) {
                break;
            }
            $h .= '<dt><strong>' . e((string) $q) . '</strong></dt><dd>' . e((string) $a) . '</dd>';
        }
        return $h . '</dl>';
    }

    public static function testimonials(array $list, int $limit = 3): string
    {
        $h = '';
        $n = 0;
        foreach ($list as [$author, $meta, $quote]) {
            if ($n++ >= $limit) {
                break;
            }
            $h .= '<blockquote><p>“' . e((string) $quote) . '”</p><footer>' . e((string) $author) . '</footer></blockquote>';
        }
        return $h;
    }

    public static function hero(string $eyebrow, string $h1, string $lead = '', string $ctaHref = '', string $ctaLabel = ''): string
    {
        $h = '<header><div class="wrap">';
        if ($eyebrow !== '') {
            $h .= '<span class="eyebrow">' . e($eyebrow) . '</span>';
        }
        $h .= '<h1>' . e($h1) . '</h1>';
        if ($lead !== '') {
            $h .= '<p>' . e($lead) . '</p>';
        }
        if ($ctaHref !== '') {
            $h .= '<p><a class="btn btn-gold" href="' . e($ctaHref) . '">' . e($ctaLabel) . '</a></p>';
        }
        return $h . '</div></header>';
    }
}

}

final class View
{
    /** Pages that must never be indexed (private app surfaces + error page). */
    private const NOINDEX_PAGES = [
        '404', 'auth', 'account', 'trips', 'messages', 'notifications', 'payments',
        'confirm', 'host-dashboard', 'host-onboarding', 'admin', 'admin-login',
        'agent', 'reset', 'verify-email', 'wishlist',
    ];

    /** Public pages that have clean pretty URLs (mirrors .htaccess + site.js PAGE_MAP). */
    private const PRETTY_PATHS = [
        'index.php'        => '/',
        'stays.php'        => '/stays',
        'experiences.php'  => '/experiences',
        'neighborhoods.php'=> '/neighborhoods',
        'collections.php'  => '/collections',
        'membership.php'   => '/membership',
        'blog.php'         => '/blog',
        'reviews.php'      => '/reviews',
        'help.php'         => '/help',
        'host.php'         => '/host',
        'about.php'        => '/about',
        'business.php'     => '/business',
        'giftcards.php'    => '/giftcards',
        'referral.php'     => '/referral',
        'app.php'          => '/app',
        'future.php'       => '/future',
        'map.php'          => '/map',
        'concierge.php'    => '/concierge',
        'compare.php'      => '/compare',
        'auth.php'         => '/auth',
        'automations.php'  => '/automations/',
    ];

    /** Registry of the images shipped in assets/img. WebP copies win when present. */
    public static function images(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $dir = JL_ROOT . '/assets/img';
        foreach (glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
            $base = basename($f);
            $key = pathinfo($base, PATHINFO_FILENAME);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if ($ext === 'webp' || !isset($map[$key])) {
                $map[$key] = $base;
            }
        }
        return $map;
    }

    /** Resolve an image key to a site-relative URL. $webp=false forces a raster fallback for og:image. */
    public static function imgUrl(?string $key, bool $webp = true): string
    {
        $key = (string) $key;
        if ($webp) {
            $map = self::images();
            if (isset($map[$key])) {
                return base_path() . '/assets/img/' . $map[$key];
            }
        }
        foreach (['jpg', 'jpeg', 'png'] as $ext) {
            if (is_file(JL_ROOT . '/assets/img/' . $key . '.' . $ext)) {
                return base_path() . '/assets/img/' . $key . '.' . $ext;
            }
        }
        return base_path() . '/assets/img/hero.jpg';
    }

    /**
     * Everything the browser bundle needs for this page.
     * Pass extra per-page data with $extra (e.g. ['booking' => …]).
     */
    public static function payload(string $page, array $extra = []): array
    {
        $user = Auth::user();
        $uid = $user ? (int) $user['id'] : null;

        $fx = [];
        foreach (Repo::fxRates() as $code => $f) {
            $fx[$code] = ['s' => $f['symbol'], 'r' => $f['rate'], 'd' => $f['decimals']];
        }

        $addons = [];
        foreach (Repo::addons() as $k => $a) {
            $addons[$k] = ['name' => $a['name'], 'price' => $a['price'], 'ico' => $a['ico'], 'note' => $a['note']];
        }

        $data = [
            'fx'            => $fx,
            'rates'         => Repo::rates(),
            'promos'        => Repo::promos(),
            'addons'        => $addons,
            'payMethods'    => Repo::payMethods(),
            'properties'    => Repo::properties(true),
            'collections'   => Repo::collections(),
            'collectionMap' => Repo::collectionMap(),
            'neighborhoods' => Repo::neighborhoods(true),
            'experiences'   => Repo::experiences(),
            'blog'          => Repo::blogPosts(),
            'testimonials'  => Repo::testimonials(),
            'faqs'          => Repo::faqs(),
            'helpCategories'=> Repo::helpCategories(),
            'roadmap'       => Repo::roadmap(),
            'tiers'         => Repo::tiers(),
            'pointsLedger'  => $uid ? Repo::pointsLedger($uid) : [],
            'notifications' => Repo::notifications($uid),
            'conversations' => Repo::conversations($uid),
            'contactEmail'  => (string) Repo::setting('contact_email', 'hello@jollofliving.com'),
            'contactPhone'  => (string) Repo::setting('contact_phone', ''),
            'siteName'      => (string) Repo::setting('site_name', 'Jollof Living'),
            'takeRate'      => (float) Repo::setting('host_take_rate', 0.12),
        ];

        /* The dispute centre and the live chat desk are optional: they appear
           once the migration has run, and their data is fetched per page so the
           payload stays small.

           The whole block is defensive on purpose. A support table that is
           missing, a half-finished migration or a partially uploaded include
           must never take a page down — least of all the back office — so
           anything that goes wrong here is logged and the page simply renders
           without the support blocks. install/diagnose.php reports the cause. */
        if (in_array($page, ['help', 'agent', 'admin'], true)) {
            $support = [];
            try {
                if (DB::tableExists('disputes')) {
                    require_once JL_INC . '/disputes.php';
                    $support['disputeCategories'] = DisputeService::categories();
                    $support['disputeWants']      = DisputeService::WANTS;
                    $support['disputeOutcomes']   = DisputeService::OUTCOMES;
                    $support['disputeStats']      = DisputeService::stats();
                    $support['disputes']          = $uid ? DisputeService::forUser($uid, 12) : [];
                }
                if (DB::tableExists('chat_sessions')) {
                    require_once JL_INC . '/livechat.php';
                    $support['chat'] = [
                        'settings'    => LiveChat::settings(),
                        'departments' => LiveChat::departments(true),
                    ];
                    $support['chatAgent'] = $uid ? LiveChat::agentForUser($uid) : null;
                }
            } catch (Throwable $e) {
                // Not fatal by design: log it, ship what we have, keep the page.
                $support = [];
                error_log('Jollof support payload (' . $page . '): ' . get_class($e) . ': ' . $e->getMessage()
                    . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            $data = array_merge($data, $support);
        }

        if (Auth::isAdmin()) {
            $data['admin'] = Repo::adminState();
            $data['adminStats'] = Repo::adminStats();
        }

        // The owner workspace is heavy, so only build it for owners on the
        // pages that actually render it.
        if ($uid !== null && Auth::isHost() && in_array($page, ['host-dashboard', 'host', 'payments'], true)) {
            $data['host'] = Repo::hostState($uid);
        }

        $payload = [
            'base'    => rtrim(base_path(), '/') . '/',
            'apiBase' => rtrim(base_path(), '/') . '/api/',
            'imgBase' => rtrim(base_path(), '/') . '/assets/img/',
            'images'  => self::images(),
            'csrf'    => csrf_token(),
            'page'    => $page,
            'currency'=> active_currency(),
            'isAdmin' => Auth::isAdmin(),
            'admin2fa'=> (bool) config('security.admin_2fa_required'),
            /* Capability ledger (R1 claims gate): the client may only render
               promises about outside services whose flag is switched on. */
            'flags'   => Repo::flagsForClient(),
            'user'    => $user ? [
                'id'            => (int) $user['id'],
                'name'          => $user['name'],
                'email'         => $user['email'],
                'phone'         => $user['phone'],
                'tier'          => $user['tier'],
                'points'        => (int) $user['points'],
                'role'          => $user['role'],
                'isHost'        => Auth::isHost(),
                'accountType'   => Auth::isHost() ? 'owner' : 'customer',
                'kyc'           => (int) ($user['kyc_verified'] ?? 0) === 1,
                'emailVerified' => !empty($user['email_verified_at']) || ($user['status'] ?? '') === 'Verified',
                'phoneVerified' => !empty($user['phone_verified_at']),
                'referral'        => $user['referral_code'],
                'referralCount'   => (int) (DB::tableExists('users') ? DB::value('SELECT COUNT(*) FROM users WHERE referral_code = ?', [$user['referral_code']]) : 0),
                'referralCredits' => (int) (DB::tableExists('points_ledger') ? DB::value('SELECT COALESCE(SUM(amount), 0) FROM points_ledger WHERE user_id = ? AND kind = ?', [(int) $user['id'], 'referral']) : 0),
                'memberSince'     => date('M Y', strtotime((string) $user['created_at'])),
                'lastLogin'     => $user['last_login_at'] ? date('M j, Y H:i', strtotime((string) $user['last_login_at'])) : null,
            ] : null,
            'state'   => self::state(),
            'data'    => $data,
        ];

        return array_merge($payload, $extra);
    }

    /** The mutable, user-owned slice of state. */
    public static function state(): array
    {
        $user = Auth::user();
        if (!$user) {
            return [
                'wishlists' => ['default' => []],
                'activeWishlist' => 'default',
                'compare'   => [],
                'bookings'  => [],
                'waitlist'  => [],
                'recent'    => Repo::recentSlugs(),
                'points'    => 0,
                'tier'      => 'bronze',
                'hostListings' => [],
                'unreadMsgs' => 0,
                'unreadNotifs' => 0,
            ];
        }
        $uid = (int) $user['id'];

        $wishlists = [];
        foreach (Repo::wishlists($uid) as $l) {
            $wishlists[$l['slug']] = $l['items'];
        }
        if (!$wishlists) {
            $wishlists = ['default' => []];
        }

        $bookings = array_map(static fn($b) => [
            'ref'          => $b['ref'],
            'prop'         => $b['property_slug'],
            'name'         => $b['property_name'],
            'img'          => $b['img'],
            'area'         => $b['area'],
            'city'         => $b['city'],
            'in'           => $b['checkin'],
            'out'          => $b['checkout'],
            'nights'       => (int) $b['nights'],
            'guests'       => (int) $b['guests'],
            'policy'       => $b['policy'],
            'method'       => $b['pay_method'],
            'addons'       => $b['addons'],
            'split'        => (int) $b['split_payment'] === 1,
            'req'          => (int) $b['is_request'] === 1,
            'total'        => (int) $b['total'],
            'status'       => $b['status'],
            'escrow'       => $b['escrow_status'],
            'code'         => $b['checkin_code'],
            'pointsEarned' => (int) $b['points_earned'],
            'createdAt'    => $b['created_at'],
        ], BookingService::forUser($uid));

        $hostListings = [];
        if ((int) $user['is_host'] === 1) {
            $hostListings = array_map(static fn($p) => [
                'id'     => $p['slug'],
                'title'  => $p['name'],
                'area'   => $p['area'],
                'rate'   => (int) $p['price'],
                'photos' => (int) DB::value('SELECT COUNT(*) FROM property_images WHERE property_id = ?', [(int) $p['id']], 0),
                'status' => $p['status'] === 'live' ? 'live' : ($p['status'] === 'pending' ? 'verification' : $p['status']),
                'added'  => date('Y-m-d', strtotime((string) $p['created_at'])),
            ], DB::all('SELECT * FROM properties WHERE host_id = ? ORDER BY id DESC', [$uid]));
        }

        $waitlist = array_map(static fn($w) => [
            'id'     => $w['slug'],
            'name'   => $w['name'],
            'window' => $w['date_from'] ? ($w['date_from'] . ' → ' . $w['date_to']) : 'any dates',
        ], DB::all(
            'SELECT w.date_from, w.date_to, p.slug, p.name FROM waitlist w JOIN properties p ON p.id = w.property_id WHERE w.user_id = ?',
            [$uid]
        ));

        return [
            'wishlists'      => $wishlists,
            'activeWishlist' => (string) ($_SESSION['activeWishlist'] ?? 'default'),
            'compare'        => Repo::compareSlugs($uid),
            'bookings'       => $bookings,
            'waitlist'       => $waitlist,
            'recent'         => Repo::recentSlugs(),
            'points'         => (int) $user['points'],
            'tier'           => (string) $user['tier'],
            'hostListings'   => $hostListings,
            'unreadMsgs'     => Repo::unreadMessages($uid),
            'unreadNotifs'   => Repo::unreadNotifications($uid),
            'signedIn'       => true,
        ];
    }

    /* ===================================================== page chrome */

    /**
     * Render the top of the document.
     *
     * @param string $page   page key used by <body data-page> and page_meta
     * @param array  $opts   title, desc, extra (extra JL payload keys), bodyClass
     */
    public static function header(string $page, array $opts = []): void
    {
        $meta = Repo::pageMeta($opts['metaKey'] ?? $page);
        $title = $opts['title'] ?? $meta['title'];
        $desc  = $opts['desc'] ?? $meta['desc'];
        /* SERP snippets truncate around 155-160 characters — keep the meta
           description inside that budget no matter what the CMS row holds. */
        if (mb_strlen($desc) > 158) {
            $desc = rtrim(mb_substr($desc, 0, 155)) . '…';
        }
        $payload = self::payload($page, $opts['extra'] ?? []);
        /* og:image MUST be an absolute URL — Facebook, LinkedIn and WhatsApp
           ignore relative ones, and WhatsApp is a primary share channel. */
        $ogImage = self::absoluteAsset($opts['image'] ?? '/assets/img/hero.jpg');
        $canonical = self::canonical();
        $nav = self::navSection($page);
        $state = $payload['state'];
        $unreadN = (int) ($state['unreadNotifs'] ?? 0);
        $unreadM = (int) ($state['unreadMsgs'] ?? 0);
        $wlCount = array_sum(array_map('count', $state['wishlists'] ?? []));
        $user = $payload['user'];

        /* --- SEO plumbing --------------------------------------------- */
        $noindex = $opts['noindex'] ?? in_array($page, self::NOINDEX_PAGES, true);
        $cssHref = is_file(JL_ROOT . '/assets/css/site.min.css')
            ? asset('assets/css/site.min.css') : asset('assets/css/site.css');
        $ga4 = trim((string) config('site.ga4_id', ''));
        $gtm = trim((string) config('site.gtm_id', ''));
        $gsc = trim((string) config('site.google_verification', ''));
        $bing = trim((string) config('site.bing_verification', ''));
        $siteName = (string) ($payload['siteName'] ?? 'Jollof Living');
        /* Structured data: an Organization + WebSite graph site-wide, plus
           whatever page-level nodes the template passed in. */
        $orgId = rtrim(self::canonicalBase(), '/') . '/#organization';
        $jsonld = array_merge([
            [
                '@context' => 'https://schema.org',
                '@type'    => 'Organization',
                '@id'      => $orgId,
                'name'     => $siteName,
                'url'      => rtrim(self::canonicalBase(), '/') . '/',
                'logo'     => self::absoluteAsset('/assets/img/logo-dark.png'),
                'contactPoint' => [[
                    '@type'       => 'ContactPoint',
                    'email'       => (string) ($payload['contactEmail'] ?? 'hello@jollofliving.com'),
                    'contactType' => 'customer service',
                    'areaServed'  => 'NG',
                ]],
            ],
            [
                '@context'   => 'https://schema.org',
                '@type'      => 'WebSite',
                '@id'        => rtrim(self::canonicalBase(), '/') . '/#website',
                'url'        => rtrim(self::canonicalBase(), '/') . '/',
                'name'       => $siteName,
                'publisher'  => ['@id' => $orgId],
                'inLanguage' => 'en-NG',
            ],
        ], $opts['jsonld'] ?? []);
        ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, follow">
<?php endif;
if ($gsc !== ''): ?><meta name="google-site-verification" content="<?= e($gsc) ?>">
<?php endif;
if ($bing !== ''): ?><meta name="msvalidate.01" content="<?= e($bing) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="en_NG">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= e($opts['ogType'] ?? 'website') ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<link rel="icon" type="image/png" href="<?= e(asset('assets/img/favicon.png')) ?>">
<link rel="preload" as="style" href="<?= e($cssHref) ?>">
<link rel="stylesheet" href="<?= e($cssHref) ?>">
<link rel="preload" as="font" type="font/woff2" href="<?= e(asset('assets/fonts/cg-normal.woff2')) ?>" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= e(asset('assets/fonts/jost-normal.woff2')) ?>" crossorigin>
<?php if (!empty($opts['preloadImage'])): ?><link rel="preload" as="image" href="<?= e(self::absoluteAsset((string) $opts['preloadImage'])) ?>" fetchpriority="high">
<?php endif;
foreach ($jsonld as $node):
    if (empty($node['@type'])) { continue; }
?>
<script type="application/ld+json"><?= json_js($node) ?></script>
<?php endforeach;
if ($gtm !== ''): ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($gtm) ?>');</script>
<?php elseif ($ga4 !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga4) ?>');</script>
<?php endif; ?>
<script>window.JL = <?= json_js($payload) ?>;</script>
</head>
<body data-page="<?= e($page) ?>"<?= isset($opts['bodyClass']) ? ' class="' . e($opts['bodyClass']) . '"' : '' ?>>
<?php if ($gtm !== ''): ?><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e($gtm) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>

<!-- ================= HEADER ================= -->
<header id="header">
  <div class="wrap nav">
    <a class="brand" href="<?= e(url('')) ?>" aria-label="Jollof Living home">
      <img id="brandImg" src="<?= e(View::imgUrl('logo-dark')) ?>" alt="Jollof Living — luxury stays in Lagos &amp; Abuja" width="150" height="40">
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Primary">
      <a href="<?= e(url('')) ?>" data-r="/"<?= $nav === '/' ? ' class="active"' : '' ?>>Home</a>
      <a href="<?= e(url('stays.php')) ?>" data-r="/stays"<?= $nav === '/stays' ? ' class="active"' : '' ?>>Stays</a>
      <a href="<?= e(url('experiences.php')) ?>" data-r="/experiences"<?= $nav === '/experiences' ? ' class="active"' : '' ?>>Experiences</a>
      <a href="<?= e(url('neighborhoods.php')) ?>" data-r="/neighborhoods"<?= $nav === '/neighborhoods' ? ' class="active"' : '' ?>>Neighbourhoods</a>
      <a href="<?= e(url('host.php')) ?>" data-r="/host"<?= $nav === '/host' ? ' class="active"' : '' ?>>Host</a>
      <a href="<?= e(url('membership.php')) ?>" data-r="/membership"<?= $nav === '/membership' ? ' class="active"' : '' ?>>Jollof Club</a>
      <a href="<?= e(url('automations.php')) ?>" data-r="/automations" style="color:var(--accent);font-weight:700">⚡ Automations</a>
      <a href="<?= e(url('help.php')) ?>" data-r="/help"<?= $nav === '/help' ? ' class="active"' : '' ?>>Help</a>
    </nav>
    <div class="nav-actions">
      <button class="icon-btn" id="notifBtn" aria-label="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9z"/><path d="M10 20a2.2 2.2 0 0 0 4 0"/></svg>
        <span class="badge-count" id="notifCount"<?= $unreadN ? '' : ' style="display:none"' ?>><?= $unreadN ?></span>
      </button>
      <button class="icon-btn" id="msgBtn" aria-label="Messages">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5c-1.5 0-2.9-.4-4.1-1L3 21l1.6-5A8.5 8.5 0 1 1 21 12z"/></svg>
        <span class="badge-count" id="msgCount"<?= $unreadM ? '' : ' style="display:none"' ?>><?= $unreadM ?></span>
      </button>
      <button class="icon-btn" id="wlBtn" aria-label="Wishlist">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20.5S3.5 15 3.5 9.1A4.6 4.6 0 0 1 12 6.6a4.6 4.6 0 0 1 8.5 2.5c0 5.9-8.5 11.4-8.5 11.4z"/></svg>
        <span class="badge-count" id="wlCount"><?= (int) $wlCount ?></span>
      </button>
      <button class="icon-btn" id="themeBtn" aria-label="Toggle theme" title="Toggle light / dark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ic-sun"><circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.5 4.5l1.8 1.8M17.7 17.7l1.8 1.8M4.5 19.5l1.8-1.8M17.7 6.3l1.8-1.8"/></svg>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ic-moon"><path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11z"/></svg>
      </button>
      <?php if ($user && Auth::isHost()): ?>
        <a class="btn btn-gold btn-sm" href="<?= e(url('host-dashboard.php')) ?>" id="navListBtn">Owner dashboard</a>
      <?php else: ?>
        <a class="btn btn-gold btn-sm" href="<?= e(url('host-onboarding.php')) ?>" id="navListBtn">List your home</a>
      <?php endif; ?>
      <?php if ($user): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('account.php')) ?>" id="navAccountBtn"><span class="avatar sm" id="navAvatar"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span> <?= e(explode(' ', (string) $user['name'])[0]) ?></a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>" id="navLogoutBtn" title="Log out">Log out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('auth.php')) ?>" id="navAccountBtn">Sign in</a>
      <?php endif; ?>
      <button class="icon-btn burger" id="burgerBtn" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- mobile drawer -->
<div class="drawer" id="drawer">
  <div class="scrim" data-close></div>
  <aside class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <img id="drawerLogo" alt="Jollof Living" style="height:52px">
      <button class="icon-btn" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    </div>
    <?php
      // The drawer is the ONLY navigation below 1160px, where the header
      // buttons are hidden. It is rendered here rather than left to
      // site.js so that a JavaScript error can never strand a signed-in
      // person with no way to log out.
      $isHost = $user && Auth::isHost();
      $dLinks = [
        [url(''), 'Home'], [url('stays.php'), 'Stays'], [url('experiences.php'), 'Experiences'],
        [url('neighborhoods.php'), 'Neighbourhoods'], [url('concierge.php'), 'AI Concierge'],
        [url('trips.php'), 'My trips'], [url('wishlist.php'), 'Wishlist'], [url('host.php'), 'Host'],
      ];
      if ($isHost) { $dLinks[] = [url('host-dashboard.php'), 'Owner dashboard']; }
      foreach ([[url('membership.php'), 'Jollof Club'], [url('reviews.php'), 'Reviews'],
                [url('blog.php'), 'Journal'], [url('help.php'), 'Help']] as $l) { $dLinks[] = $l; }
    ?>
    <div id="drawerLinks">
      <?php foreach ($dLinks as $l): ?><a href="<?= e($l[0]) ?>"><?= e($l[1]) ?></a><?php endforeach; ?>
    </div>
    <div style="margin-top:22px;display:grid;gap:10px" id="drawerCtas">
      <?php if ($user): ?>
        <a class="btn btn-gold" href="<?= e(url($isHost ? 'host-dashboard.php' : 'host-onboarding.php')) ?>"><?= $isHost ? 'Owner dashboard' : 'List your home' ?></a>
        <a class="btn btn-ghost" href="<?= e(url('account.php')) ?>">My account</a>
        <a class="btn btn-ghost" href="<?= e(url('logout.php')) ?>" id="drawerLogout">Log out</a>
      <?php else: ?>
        <a class="btn btn-gold" href="<?= e(url('host-onboarding.php')) ?>">List your home</a>
        <a class="btn btn-ghost" href="<?= e(url('auth.php')) ?>">Sign in / Join</a>
        <a class="btn btn-ghost" href="<?= e(url('auth.php?mode=register')) ?>">Create account</a>
      <?php endif; ?>
    </div>
  </aside>
</div>

<main id="view"><?php
$ssr = $opts['ssr'] ?? null;
if (is_string($ssr) && $ssr !== '') {
    /* Server-rendered content: crawlable, no-JS friendly, instant paint.
       site.js replaces #view's contents wholesale once it boots. */
    echo '<div id="ssr-content" data-ssr="' . e($page) . '">' . $ssr . '</div>';
} else {
    echo '<div class="page-loading"><div class="pl-mark"></div><p>Jollof Living</p></div>';
}
?></main>
<?php
    }

    /** Which primary nav item is active for this page. */
    private static function navSection(string $page): string
    {
        if ($page === 'index') return '/';
        if (in_array($page, ['stays', 'map', 'collections'], true) || strpos($page, 'stay') === 0 || strpos($page, 'booking') === 0) return '/stays';
        if ($page === 'experiences') return '/experiences';
        if (strpos($page, 'neighborhood') === 0) return '/neighborhoods';
        if (strpos($page, 'host') === 0) return '/host';
        if ($page === 'membership') return '/membership';
        if ($page === 'help') return '/help';
        return '';
    }

    /** Site base URL without trailing slash (config, or detected from the request). */
    private static function canonicalBase(): string
    {
        $base = rtrim((string) config('site.url', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
            $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return $base;
    }

    /** Turn a site-relative path (or any URL) into an absolute URL. */
    public static function absoluteAsset(string $path): string
    {
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }
        return absolute_url($path);
    }

    /**
     * Canonical URL for the current request. Query-string entry points are
     * canonicalised onto their pretty path (/stay.php?p=onyx → /stay/onyx)
     * so search engines only ever see one URL per page.
     */
    private static function canonical(): string
    {
        $base = self::canonicalBase();
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $q = static fn(string $k): string => trim((string) ($_GET[$k] ?? ''));

        $path = self::PRETTY_PATHS[$script] ?? null;
        if ($script === 'stay.php' && $q('p') !== '') {
            $path = '/stay/' . rawurlencode($q('p'));
        } elseif ($script === 'booking.php' && $q('p') !== '') {
            $path = '/booking/' . rawurlencode($q('p'));
        } elseif ($script === 'neighborhood.php' && $q('n') !== '') {
            $path = '/neighborhood/' . rawurlencode($q('n'));
        } elseif ($script === 'blog-post.php') {
            $path = $q('s') !== '' ? '/blog/' . rawurlencode($q('s')) : '/blog';
        } elseif ($script === 'confirm.php' && $q('ref') !== '') {
            $path = '/confirm/' . rawurlencode($q('ref'));
        }
        if ($path === null) {
            // Unmapped script (private app pages, API, …): keep the request path.
            $path = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        }
        return $base . $path;
    }

    /** Render the footer and close the document. */
    public static function footer(): void
    {
        $year = date('Y');
        $domain = (string) Repo::setting('site_domain', 'www.jollofliving.com');
        $cur = active_currency();
        $contactEmail = (string) Repo::setting('contact_email', 'hello@jollofliving.com');
        $contactPhone = (string) Repo::setting('contact_phone', '');
        ?>
<!-- ================= FOOTER ================= -->
<footer id="footer">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <img id="footLogo" src="<?= e(View::imgUrl('logo-light')) ?>" alt="Jollof Living" width="150" height="40">
        <p>A premium platform for luxurious high-end apartments — exclusive short and long-term stays, inspired by the vibrant culture and warmth of Nigeria.</p>
          <address class="ssr-nap">
            Lagos &middot; Abuja, Nigeria<br>
            <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a><?php if ($contactPhone !== ''): ?><br>
            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= e($contactPhone) ?></a><?php endif; ?>
          </address>
        <div class="newsletter">
          <input type="email" id="newsInput" placeholder="Email for private openings" autocomplete="email">
          <button id="newsBtn" aria-label="Subscribe"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg></button>
        </div>
      </div>
      <div>
        <h4>Discover</h4>
        <ul>
          <li><a href="<?= e(url('stays.php')) ?>">All residences</a></li>
          <li><a href="<?= e(url('collections.php')) ?>">Curated collections</a></li>
          <li><a href="<?= e(url('neighborhoods.php')) ?>">Neighbourhood guides</a></li>
          <li><a href="<?= e(url('experiences.php')) ?>">Experiences</a></li>
          <li><a href="<?= e(url('map.php')) ?>">Explore the map</a></li>
        </ul>
      </div>
      <div>
        <h4>Guests</h4>
        <ul>
          <li><a href="<?= e(url('trips.php')) ?>">My trips</a></li>
          <li><a href="<?= e(url('wishlist.php')) ?>">Wishlist</a></li>
          <li><a href="<?= e(url('membership.php')) ?>">Jollof Club</a></li>
          <li><a href="<?= e(url('giftcards.php')) ?>">Gift cards</a></li>
          <li><a href="<?= e(url('referral.php')) ?>">Refer &amp; earn</a></li>
        </ul>
      </div>
      <div>
        <h4>Hosts</h4>
        <ul>
          <li><a href="<?= e(url('host.php')) ?>">List your residence</a></li>
          <li><a href="<?= e(url('host-dashboard.php')) ?>">Host dashboard</a></li>
          <li><a href="<?= e(url('payments.php')) ?>">Earnings &amp; payouts</a></li>
          <li><a href="<?= e(url('host-onboarding.php')) ?>">Listing wizard</a></li>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="<?= e(url('about.php')) ?>">About &amp; compliance</a></li>
          <li><a href="<?= e(base_path() . '/automations/') ?>"><b>Jollof Automations</b> ⚡</a></li>
          <li><a href="<?= e(url('business.php')) ?>">Jollof for Business</a></li>
          <li><a href="<?= e(url('blog.php')) ?>">Journal &amp; guides</a></li>
          <li><a href="<?= e(url('help.php')) ?>">Help centre</a></li>
          <li><a href="<?= e(url('future.php')) ?>">Roadmap</a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <span id="yearNow"><?= $year ?></span> Jollof Living. All rights reserved. · Luxury Living, African Soul</span>
      <span><?= e($domain) ?></span>
      <div class="right">
        <label class="currency-select"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.8 2.6 4.2 5.6 4.2 9S14.8 18.4 12 21c-2.8-2.6-4.2-5.6-4.2-9S9.2 5.6 12 3z"/></svg>
          <select id="currencySel" aria-label="Currency">
            <?php foreach (Repo::fxRates() as $code => $f): ?>
              <option value="<?= e($code) ?>"<?= $code === $cur ? ' selected' : '' ?>><?= e($code . ' ' . $f['symbol']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="pay-chips">
          <?php foreach (Repo::payMethods() as $m): ?><span><?= e($m['name']) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</footer>

<!-- overlays -->
<div class="modal-root" id="modalRoot"><div class="scrim" data-closeall></div><div class="modal-panel" id="modalPanel"><button class="modal-x" id="modalX" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button><div class="modal-body" id="modalBody"></div></div></div>
<div class="sheet" id="sheetRoot"><div class="scrim" data-closeall></div><aside class="panel" id="sheetPanel"></aside></div>
<div class="toasts" id="toasts"></div>
<button class="chat-fab" id="chatFab" aria-label="Chat with concierge" title="Ask Jollof, your AI concierge">
  <span class="pulse"></span>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5c-1.5 0-2.9-.4-4.1-1L3 21l1.6-5A8.5 8.5 0 1 1 21 12z"/></svg>
</button>

<script src="<?= e(is_file(JL_ROOT . '/assets/js/chat.min.js') ? asset('assets/js/chat.min.js') : asset('assets/js/chat.js')) ?>" defer></script>
<script src="<?= e(is_file(JL_ROOT . '/assets/js/site.min.js') ? asset('assets/js/site.min.js') : asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
<?php
    }

    /**
     * Server-rendered fallback so search engines and no-JS visitors get
     * real content. Injected inside <main> before the bundle paints.
     */
    public static function noscript(string $html): void
    {
        echo '<noscript><div class="wrap" style="padding:calc(var(--header-h) + 40px) 0 60px">' . $html . '</div></noscript>';
    }
}
