<?php
/**
 * Jollof Automations — Root Entrypoint
 * A Subsidiary of Jollof Living.
 */
declare(strict_types=1);

$htmlFile = __DIR__ . '/jollof-automations/index.html';
if (!file_exists($htmlFile)) {
    $htmlFile = __DIR__ . '/automations/index.html';
}
if (file_exists($htmlFile)) {
    // Deliver the high-performance corporate portal
    readfile($htmlFile);
    exit;
}

header('Location: /');
exit;
