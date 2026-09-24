<?php
/** Sitemap generated from the live catalogue — served at /sitemap.xml. */
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once JL_INC . '/view.php'; // View::imgUrl() for the image extension

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((string) config('site.url', ''), '/');
if ($base === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(base_path(), '/');
}

/* changefreq/priority are ignored by Google; <lastmod> is the signal that
   matters, so every entry carries a real date from the database (or the
   file mtime for static pages). */
$urls = [];
/** @param array<int,array<string,string>> $images optional [loc, title] image entries */
$add = static function (string $loc, ?string $lastmod, string $pri, array $images = []) use (&$urls, $base): void {
    $urls[] = [
        'loc'     => $base . $loc,
        'lastmod' => $lastmod !== null ? gmdate('Y-m-d\TH:i:sP', (int) $lastmod) : null,
        'pri'     => $pri,
        'images'  => $images,
    ];
};

$dbTime = static function (string $sql, array $params = []): ?string {
    try {
        $v = DB::value($sql, $params);
        return $v !== null && $v !== '' ? strtotime((string) $v) ?: null : null;
    } catch (Throwable) {
        return null;
    }
};
$fileTime = static function (string $rel): ?int {
    $f = JL_ROOT . '/' . $rel;
    return is_file($f) ? (int) filemtime($f) : null;
};

$imgEntry = static function (string $key, string $title): array {
    return [
        'loc'   => View::imgUrl($key, false), // absolute path; made absolute below
        'title' => $title,
    ];
};

/* ------------------------------------------------------------- core pages */
$add('/', $fileTime('index.php'), '1.0');
foreach (['stays', 'collections', 'neighborhoods', 'experiences', 'membership', 'blog',
          'reviews', 'help', 'host', 'about', 'business', 'giftcards', 'referral',
          'app', 'future', 'map', 'concierge', 'compare'] as $p) {
    $add('/' . $p . '.php', $fileTime($p . '.php'), '0.8');
}

/* --------------------------------------------------------- dynamic pages */
$props = [];
try {
    $props = DB::all("SELECT slug, img, name, updated_at FROM properties WHERE status = 'live'");
} catch (Throwable) {
}
foreach ($props as $p) {
    $add('/stay/' . rawurlencode((string) $p['slug']), strtotime((string) $p['updated_at']) ?: null, '0.9', [
        $imgEntry((string) $p['img'], (string) $p['name']),
    ]);
}

try {
    foreach (Repo::neighborhoods(false) as $n) {
        $add('/neighborhood/' . rawurlencode((string) $n['id']), null, '0.7');
    }
} catch (Throwable) {
}

try {
    foreach (DB::all('SELECT slug, img, title, created_at FROM blog_posts WHERE published = 1') as $b) {
        $add('/blog/' . rawurlencode((string) $b['slug']), strtotime((string) $b['created_at']) ?: null, '0.6', [
            $imgEntry((string) $b['img'], (string) $b['title']),
        ]);
    }
} catch (Throwable) {
}

/* --------------------------------------------- Jollof Automations subsite */
/* Served under the canonical /automations/ path (see .htaccess). */
$autoTime = static fn(string $f) => $fileTime('jollof-automations/' . $f);
$add('/automations/', $autoTime('index.php'), '0.8');
foreach (['solutions.php', 'lab.php', 'estimator.php', 'case-studies.php', 'process.php', 'book-survey.php'] as $f) {
    $add('/automations/' . $f, $autoTime($f), '0.7');
}

/* ------------------------------------------------------------------ output */
header('X-Robots-Tag: noindex'); // the sitemap itself should not be indexed

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
    . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . e($u['loc']) . "</loc>\n";
    if ($u['lastmod'] !== null) {
        echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    }
    echo '    <priority>' . $u['pri'] . "</priority>\n";
    foreach ($u['images'] as $img) {
        $loc = $img['loc'];
        if (!preg_match('~^https?://~i', $loc)) {
            $loc = $base . $loc;
        }
        echo "    <image:image>\n";
        echo '      <image:loc>' . e($loc) . "</image:loc>\n";
        if (($img['title'] ?? '') !== '') {
            echo '      <image:title>' . e((string) $img['title']) . "</image:title>\n";
        }
        echo "    </image:image>\n";
    }
    echo "  </url>\n";
}
echo '</urlset>';
