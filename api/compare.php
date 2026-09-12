<?php
/** Add or remove a residence from the comparison tray (max 4). */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$slug = input_str('property');

$pid = Repo::propertyIdBySlug($slug);
if (!$pid) json_fail('We could not find that residence.', 404);

[$ok, $message] = Repo::toggleCompare((int) $user['id'], $pid);
if (!$ok) {
    json_fail($message);
}

$compare = Repo::compareSlugs((int) $user['id']);
json_ok_state(['compare' => $compare, 'added' => in_array($slug, $compare, true)], $message);
