<?php
/**
 * Jollof Automations — Subsidiary Index
 * A Subsidiary of Jollof Living.
 */
declare(strict_types=1);

$phpIndex = dirname(__DIR__) . '/jollof-automations/index.php';
if (file_exists($phpIndex)) {
    require $phpIndex;
    exit;
}

$htmlFile = dirname(__DIR__) . '/jollof-automations/index.html';
if (file_exists($htmlFile)) {
    readfile($htmlFile);
    exit;
}

header('Location: ../');
exit;
