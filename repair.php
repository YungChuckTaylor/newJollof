<?php
/*
 * Jollof repair - step 1 of 1. Paste THIS file only (it is small on purpose:
 * a partial paste of a big file is how the site ended up half-updated).
 * Then open:  repair.php?key=jl-fix-2026
 */
declare(strict_types=1);

if (!isset($_GET['key']) || $_GET['key'] !== 'jl-fix-2026') {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
@set_time_limit(120);

const JL_SRC = 'https://raw.githubusercontent.com/YungChuckTaylor/newJollof/a4fc2b14e59924c849e8e15ae4292bb4f54c8686/repair-worker.php';
const JL_WORKER = __DIR__ . '/repair-worker.php';

echo "fetching repair worker ...\n";

$body = @file_get_contents(JL_SRC, false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]));
if (!is_string($body) || strlen($body) < 2000) {
    if (function_exists('curl_init')) {
        $ch = curl_init(JL_SRC);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true]);
        $b2 = curl_exec($ch);
        curl_close($ch);
        if (is_string($b2)) { $body = $b2; }
    }
}

if (!is_string($body) || strlen($body) < 2000) {
    http_response_code(500);
    exit("Could not download the worker from GitHub.\n"
        . "Most likely this server blocks outbound connections.\n"
        . "Plan B (no GitHub needed): open repair.php?key=jl-fix-2026&plan=b\n");
}

file_put_contents(JL_WORKER, $body);
@chmod(JL_WORKER, 0644);
echo "worker saved (" . strlen($body) . " bytes) - running it ...\n\n";
require JL_WORKER;
