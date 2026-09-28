<?php
/**
 * Jollof — runtime diagnostic (SAFE, standalone-ish).
 * Drop in public_html/jollof/ and open https://<host>/jollof/_diag.php
 * It prints plain text: what's really on disk, whether OPcache is serving
 * stale code, whether index.php loads view.php, and whether SSR ends up
 * defined. Delete it afterwards.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$EXPECT = [
    'index.php'              => '9f1b01eba51d2ced97b096476f39560e',
    'includes/view.php'      => '360e6cb5386f619e35afe2293b807550',
    'includes/ssr.php'       => '99bc5f9905b6d11387f5aabd09907114',
    'includes/bootstrap.php' => '3a499df3c14d9bf1c5c912213e6d7bd9',
];

echo "JOLLOF RUNTIME DIAGNOSTIC  " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 64) . "\n\n";

echo "[1] PHP + OPcache\n";
echo "  PHP version .................. " . PHP_VERSION . "\n";
echo "  opcache.enable ............... " . var_export((bool) ini_get('opcache.enable'), true) . "\n";
echo "  opcache.validate_timestamps .. " . var_export(ini_get('opcache.validate_timestamps'), true)
    . "   (0 = never auto-refresh; stale until PHP restarts)\n";
$scripts = [];
if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(true);
    if (is_array($s) && !empty($s['scripts'])) { $scripts = $s['scripts']; }
}
echo "  opcache cached scripts ....... " . count($scripts) . "\n\n";

echo "[2] Files on disk (at the path PHP resolves) vs expected\n";
foreach ($EXPECT as $rel => $md5) {
    $abs = __DIR__ . '/' . $rel;
    if (!is_file($abs)) { echo "  " . str_pad($rel, 24) . " MISSING\n"; continue; }
    $got = md5_file($abs);
    $verdict = hash_equals($md5, $got) ? 'PASS (current)' : 'MISMATCH <-- not the current file';
    echo "  " . str_pad($rel, 24) . str_pad(filesize($abs) . 'B', 9) . ' md5 ' . $got . '  ' . $verdict . "\n";
}
echo "\n";

echo "[3] OPcache staleness (cached mtime vs file mtime)\n";
if (!$scripts) {
    echo "  (opcache not reporting scripts)\n";
} else {
    foreach (['index.php', 'includes/view.php', 'includes/ssr.php'] as $rel) {
        $abs = realpath(__DIR__ . '/' . $rel) ?: (__DIR__ . '/' . $rel);
        $hit = null;
        foreach ($scripts as $path => $info) {
            if (realpath($path) === $abs || $path === $abs) { $hit = $info; break; }
        }
        if ($hit === null) {
            echo "  " . str_pad($rel, 24) . "not cached (compiled fresh)\n";
        } else {
            $fm = @filemtime($abs);
            $cm = $hit['timestamp'] ?? null;
            $sync = ($cm === $fm) ? '(in sync)' : '<-- STALE: opcache is running OLD code';
            echo "  " . str_pad($rel, 24) . "cached_mtime=" . $cm . "  file_mtime=" . $fm . "  " . $sync . "\n";
        }
    }
}
echo "\n";

echo "[4] index.php source checks\n";
$idx = @file_get_contents(__DIR__ . '/index.php');
echo "  loads view.php? .............. " . (strpos($idx, 'view.php') !== false ? 'YES' : 'NO  <-- stale index.php; SSR never loaded') . "\n";
echo "  uses SSR:: ? ................. " . (strpos($idx, 'SSR::') !== false ? 'YES' : 'no') . "\n\n";

echo "[5] Reproduce index.php's first steps, then check SSR\n";
try {
    require __DIR__ . '/includes/bootstrap.php';   // defines JL_ROOT + loads includes
    require __DIR__ . '/includes/view.php';         // must define SSR (real class or shim)
    if (class_exists('SSR', false)) {
        $f = (new ReflectionClass('SSR'))->getFileName();
        echo "  class_exists('SSR') ......... YES  (defined in " . $f . ")\n";
    } else {
        echo "  class_exists('SSR') ......... NO   <-- the view.php that RAN did not define SSR\n";
    }
    echo "  class_exists('View') ........ " . (class_exists('View', false) ? 'YES' : 'no') . "\n";
} catch (\Throwable $e) {
    echo "  EXCEPTION " . get_class($e) . ": " . $e->getMessage() . "\n";
}
echo "\nDone. Paste this whole output back.\n";
