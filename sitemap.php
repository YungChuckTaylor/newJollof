<?php
/** Sitemap generated from the live catalogue. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((string) config('site.url', ''), '/');
if ($base === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(base_path(), '/');
}

$urls = [];
$add = static function (string $loc, string $freq, string $pri) use (&$urls, $base) {
    $urls[] = ['loc' => $base . $loc, 'freq' => $freq, 'pri' => $pri];
};

$add('/', 'daily', '1.0');
foreach (['stays', 'collections', 'neighborhoods', 'experiences', 'membership', 'blog',
          'reviews', 'help', 'host', 'about', 'business', 'giftcards', 'referral',
          'app', 'future', 'map'] as $p) {
    $add('/' . $p . '.php', 'weekly', '0.8');
}
foreach (Repo::propertySlugs() as $slug) {
    $add('/stay/' . rawurlencode($slug), 'daily', '0.9');
}
foreach (Repo::neighborhoodSlugs() as $slug) {
    $add('/neighborhood/' . rawurlencode($slug), 'monthly', '0.7');
}
foreach (Repo::blogSlugs() as $slug) {
    $add('/blog/' . rawurlencode($slug), 'monthly', '0.6');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . e($u['loc']) . "</loc>\n";
    echo '    <changefreq>' . $u['freq'] . "</changefreq>\n";
    echo '    <priority>' . $u['pri'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
