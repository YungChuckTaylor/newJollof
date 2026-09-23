<?php
/**
 * Jollof Automations — Subsidiary Index
 * A Subsidiary of Jollof Living.
 */
declare(strict_types=1);

$htmlFile = __DIR__ . '/index.html';
if (file_exists($htmlFile)) {
    readfile($htmlFile);
    exit;
}

header('Location: ../');
exit;
