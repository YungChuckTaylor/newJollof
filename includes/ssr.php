<?php
/**
 * Jollof Living — server-side rendering (SSR) builders.
 *
 * Every public page passes View::header(['ssr' => SSR::…()]) real HTML so
 * search engines and no-JS visitors get the full content in the response.
 * The markup intentionally re-uses the design-system classes (.stay-card,
 * .sec-head, .grid-3 …) that the client bundle paints with, so the static
 * content looks identical before site.js takes over.
 *
 * Links inside SSR blocks deliberately point at the resilient *.php entry
 * points (stay.php?p=slug) rather than the pretty /stay/slug URLs: they must
 * resolve even on hosts where the .htaccess rewrites are missing. Canonical
 * tags, the sitemap and the 301 rules converge everything on the pretty URLs.
 */

if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}

if (!class_exists('SSR', false)) {

final class SSR
{
    /* ------------------------------------------------------------ icons */

    private const I_STAR  = '<svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/></svg>';
    private const I_PIN   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>';
    private const I_BED   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" aria-hidden="true"><path d="M3 18v-8m0 4h18v4m0-4v-2a3 3 0 0 0-3-3H9v5"/><circle cx="6.5" cy="9.5" r="1.5"/></svg>';
    private const I_BATH  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" aria-hidden="true"><path d="M4 12h16v2a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5v-2zm2 0V5.5A1.5 1.5 0 0 1 7.5 4c.9 0 1.5.6 1.5 1.5M7 19l-1 2m11-2 1 2"/></svg>';
    private const I_USERS = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="12" height="12" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.4a3.2 3.2 0 0 1 0 5.9m1.3 2.2A6.5 6.5 0 0 1 21.5 20"/></svg>';

    /* ==================================================== building blocks */

    /** Reusable inline SVG icon (kept public for the page templates). */
    public static function icon(string $name): string
    {
        return match ($name) {
            'star'  => self::I_STAR,
            'pin'   => self::I_PIN,
            'bed'   => self::I_BED,
            'bath'  => self::I_BATH,
            'users' => self::I_USERS,
            default => '',
        };
    }

    /** Breadcrumb trail: [['Home', '/'], ['Stays', '/stays.php'], ['Onyx', null]] */
    public static function crumbs(array $items): string
    {
        $out = '<nav class="ssr-crumbs wrap" aria-label="Breadcrumb">';
        $last = count($items) - 1;
        foreach (array_values($items) as $i => [$label, $href]) {
            $out .= $href !== null && $i !== $last
                ? '<a href="' . e((string) $href) . '">' . e((string) $label) . '</a>'
                : '<span aria-current="page">' . e((string) $label) . '</span>';
            if ($i !== $last) {
                $out .= '<i aria-hidden="true">/</i>';
            }
        }
        return $out . '</nav>';
    }

    /** Section heading: eyebrow + h2 + lead paragraph. */
    public static function secHead(string $eyebrow, string $h2, string $lead = '', bool $center = false, string $h2Tag = 'h2'): string
    {
        $h = '<div class="sec-head' . ($center ? ' center' : '') . '">';
        if ($eyebrow !== '') {
            $h .= '<span class="eyebrow">' . e($eyebrow) . '</span>';
        }
        $h .= '<' . $h2Tag . '>' . e($h2) . '</' . $h2Tag . '>';
        if ($lead !== '') {
            $h .= '<p>' . e($lead) . '</p>';
        }
        return $h . '</div>';
    }

    /** Primary call-to-action row. */
    public static function cta(string $href, string $label, string $ghostHref = '', string $ghostLabel = ''): string
    {
        $h = '<div class="ssr-cta-row"><a class="btn btn-gold" href="' . e($href) . '">' . e($label) . '</a>';
        if ($ghostHref !== '') {
            $h .= ' <a class="btn btn-ghost" href="' . e($ghostHref) . '">' . e($ghostLabel) . '</a>';
        }
        return $h . '</div>';
    }

    /* ==================================================== data-driven blocks */

    /** One property card — mirrors the client-side stayCard() markup. */
    public static function stayCard(array $p, bool $lazy = true): string
    {
        $href     = url('stay.php?p=' . rawurlencode((string) ($p['id'] ?? '')));
        $imgFile  = View::images()[(string) ($p['img'] ?? 'p1')] ?? 'hero.jpg';
        $alt      = ($p['name'] ?? 'Residence') . ' — ' . ($p['area'] ?? '') . ', ' . ($p['city'] ?? '');
        $badge    = (string) ($p['badge'] ?? '');
        $oldPrice = $p['oldPrice'] ?? null;

        $h = '<article class="stay-card ssr-card">';
        $h .= '<a class="stay-media" href="' . e($href) . '" aria-label="' . e((string) $alt) . '">';
        $h .= '<img src="' . e(base_path() . '/assets/img/' . $imgFile) . '" alt="' . e($alt) . '"'
            . ($lazy ? ' loading="lazy"' : '') . ' width="800" height="600">';
        if ($badge !== '') {
            $h .= '<span class="ribbon' . (!empty($p['badgeGold']) ? ' gold' : '') . '">' . e($badge) . '</span>';
        }
        $h .= '</a><div class="stay-body"><div class="stay-top"><div>';
        $h .= '<h3><a href="' . e($href) . '">' . e((string) $p['name']) . '</a></h3>';
        $h .= '<div class="stay-loc">' . self::I_PIN . e((string) $p['area']) . ', ' . e((string) $p['city']) . '</div>';
        $h .= '</div><div class="stay-rating">' . self::I_STAR . e(number_format((float) ($p['rating'] ?? 0), 2))
            . '<span class="rev">(' . (int) ($p['reviews'] ?? 0) . ')</span></div></div>';
        $h .= '<div class="stay-specs"><span class="spec">' . self::I_BED . e(rtrim(rtrim((string) ($p['beds'] ?? 1), '0'), '.')) . ' bd</span>'
            . '<span class="spec">' . self::I_BATH . e(rtrim(rtrim((string) ($p['baths'] ?? 1), '0'), '.')) . ' ba</span>'
            . '<span class="spec">' . self::I_USERS . (int) ($p['guests'] ?? 2) . ' guests</span></div>';
        $h .= '<div class="stay-foot"><div class="price">'
            . ($oldPrice ? '<span class="old-price">' . e(money((int) $oldPrice)) . '</span>' : '')
            . '<span class="amt">' . e(money((int) ($p['price'] ?? 0))) . '</span><span class="per">/night</span></div>';
        $h .= '<div class="btnrow"><a class="btn btn-green btn-sm" href="' . e($href) . '">View</a></div>';
        return $h . '</div></div></article>';
    }

    /** Grid of property cards. */
    public static function stayGrid(array $props, int $limit = 6, bool $lazy = true): string
    {
        $cards = '';
        $n = 0;
        foreach ($props as $p) {
            if ($n++ >= $limit) {
                break;
            }
            $cards .= self::stayCard($p, $lazy && $n > 1);
        }
        return $cards === '' ? '' : '<div class="grid-3 ssr-grid">' . $cards . '</div>';
    }

    /** Generic image card (collections, neighbourhoods, experiences, journal). */
    public static function linkCards(array $items, int $limit = 8): string
    {
        $cards = '';
        $n = 0;
        foreach ($items as $it) {
            if ($n++ >= $limit) {
                break;
            }
            $meta = isset($it['meta']) ? (string) $it['meta'] : '';
            $cards .= '<a class="ssr-link-card" href="' . e((string) $it['href']) . '">'
                . '<span class="ssr-link-media"><img src="' . e((string) $it['img']) . '" alt="' . e((string) $it['alt'])
                . '" loading="lazy" width="640" height="480"></span>'
                . '<span class="ssr-link-body"><strong>' . e((string) $it['title']) . '</strong>'
                . ($meta !== '' ? '<span class="ssr-link-meta">' . $meta . '</span>' : '')
                . '</span></a>';
        }
        return $cards === '' ? '' : '<div class="ssr-cards ssr-grid">' . $cards . '</div>';
    }

    /** Simple inline text list of links (footer-style). */
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

    /** FAQ accordion content (static, real text). */
    public static function faqs(array $faqs, int $limit = 14): string
    {
        $h = '';
        $n = 0;
        foreach ($faqs as [$q, $a]) {
            if ($n++ >= $limit) {
                break;
            }
            $h .= '<details class="ssr-faq"><summary>' . e((string) $q) . '</summary><p>' . e((string) $a) . '</p></details>';
        }
        return $h === '' ? '' : '<div class="ssr-faqs">' . $h . '</div>';
    }

    /** A single testimonial quote. */
    public static function testimonials(array $list, int $limit = 3): string
    {
        $h = '<div class="ssr-quotes">';
        $n = 0;
        foreach ($list as [$author, $meta, $quote]) {
            if ($n++ >= $limit) {
                break;
            }
            $h .= '<blockquote class="ssr-quote"><p>“' . e((string) $quote) . '”</p><footer>' . e((string) $author)
                . ($meta !== '' ? ' · ' . e((string) $meta) : '') . '</footer></blockquote>';
        }
        return $h . '</div>';
    }

    /* ==================================================== page composites */

    /** Page-opening hero used by marketing pages. */
    public static function hero(string $eyebrow, string $h1, string $lead, string $ctaHref = '', string $ctaLabel = ''): string
    {
        $h = '<header class="ssr-hero"><div class="wrap">';
        if ($eyebrow !== '') {
            $h .= '<span class="eyebrow">' . e($eyebrow) . '</span>';
        }
        $h .= '<h1>' . e($h1) . '</h1>';
        if ($lead !== '') {
            $h .= '<p class="ssr-lead">' . e($lead) . '</p>';
        }
        if ($ctaHref !== '') {
            $h .= '<div class="ssr-cta-row"><a class="btn btn-gold" href="' . e($ctaHref) . '">' . e($ctaLabel) . '</a></div>';
        }
        return $h . '</div></header>';
    }
}
}
