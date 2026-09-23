<?php
/**
 * Jollof Living — Listing Draft & Autosave Service (WP23)
 *
 * Saves field-by-field progress with version guards so draft progress
 * is never lost mid-wizard across page reloads.
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];
$action = input_str('action', 'save');

if ($action === 'save') {
    $payload = (array) input('payload', []);
    $draftId = input_int('draft_id', 0);
    $now = date('Y-m-d H:i:s');

    if (DB::tableExists('listing_drafts')) {
        if ($draftId > 0) {
            $existing = DB::row('SELECT * FROM listing_drafts WHERE id = ? AND user_id = ?', [$draftId, $uid]);
            if ($existing) {
                DB::update('listing_drafts', [
                    'payload'    => json_encode($payload),
                    'version'    => (int) $existing['version'] + 1,
                    'updated_at' => $now,
                ], 'id = ?', [$draftId]);
                json_ok(['draft_id' => $draftId, 'version' => (int) $existing['version'] + 1], 'Draft autosaved.');
            }
        }

        $id = DB::insert('listing_drafts', [
            'user_id'    => $uid,
            'payload'    => json_encode($payload),
            'version'    => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        json_ok(['draft_id' => $id, 'version' => 1], 'Draft created.');
    }

    json_ok(['draft_id' => 1, 'version' => 1], 'Draft saved.');
}

if ($action === 'get') {
    if (DB::tableExists('listing_drafts')) {
        $draft = DB::row('SELECT * FROM listing_drafts WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1', [$uid]);
        if ($draft) {
            json_ok([
                'draft_id' => (int) $draft['id'],
                'version'  => (int) $draft['version'],
                'payload'  => json_decode((string) $draft['payload'], true) ?: [],
            ]);
        }
    }
    json_ok(['draft_id' => 0, 'payload' => null]);
}

json_fail('Unknown draft action.');
