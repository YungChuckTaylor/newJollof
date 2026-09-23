<?php
/**
 * Jollof Automations — Root Entrypoint
 * A Subsidiary of Jollof Living.
 */
declare(strict_types=1);

$phpIndex = __DIR__ . '/jollof-automations/index.php';
if (file_exists($phpIndex)) {
    require $phpIndex;
    exit;
}

$htmlFile = __DIR__ . '/jollof-automations/index.html';
if (file_exists($htmlFile)) {
    readfile($htmlFile);
    exit;
}

header('Location: /');
exit;
