<?php
/**
 * Jollof Living — Availability & Calendar Endpoint (WP09)
 *
 * Provides real-time date availability, calendar blocks, and daily rates
 * derived directly from bookings and property_calendar tables.
 *
 * GET ?property=onyx&month=2026-10
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

$propSlug = input_str('property');
$p = Repo::propertyBySlug($propSlug) ?: Repo::propertyById((int) $propSlug);
if (!$p) {
    json_fail('Property not found.', 404);
}

$month = input_str('month', date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$cal = Repo::hostCalendar((int) $p['pid'], $month);

json_ok($cal);
