<?php
/** Admin console CRUD: moderation, users, fraud, CMS, campaigns, bookings. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$admin = api_admin();
$actor = (string) $admin['email'];

$entity = input_str('entity');
$action = input_str('action');
$id     = input_int('id');

switch ($entity) {

    /* -------------------------------------------------- listing moderation */
    case 'moderation': {
        $p = DB::row('SELECT * FROM properties WHERE id = ?', [$id]);
        if (!$p) json_fail('That listing no longer exists.', 404);

        if ($action === 'approve') {
            DB::update('properties', ['status' => 'live'], 'id = ?', [$id]);
            if ($p['host_id']) {
                Repo::notify((int) $p['host_id'], 'Listing approved ✨', $p['name'] . ' is now live on Jollof Living.', 'check');
            }
            audit($actor, 'Approved listing #' . $id . ' — ' . $p['name'], 'ok');
            Repo::flush();
            json_ok([], 'Approved — the listing is live ✨');
        }
        if ($action === 'reject') {
            DB::update('properties', ['status' => 'rejected'], 'id = ?', [$id]);
            if ($p['host_id']) {
                Repo::notify((int) $p['host_id'], 'Listing needs changes', $p['name'] . ' was sent back with reviewer notes.', 'x');
            }
            audit($actor, 'Rejected listing #' . $id . ' — ' . $p['name'], 'warn');
            Repo::flush();
            json_ok([], 'Sent back to the host with notes');
        }
        break;
    }

    /* ------------------------------------------------------------- users */
    case 'user': {
        $u = DB::row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) json_fail('That user no longer exists.', 404);
        if ((int) $u['id'] === (int) $admin['id']) json_fail('You cannot action your own account.');

        if ($action === 'verify') {
            DB::update('users', ['status' => 'Verified', 'kyc_verified' => 1], 'id = ?', [$id]);
            Repo::notify($id, 'Account verified ✨', 'Your identity check is complete. Instant booking is now enabled.', 'shield');
            audit($actor, 'Verified user ' . $u['email'], 'ok');
            json_ok([], 'User verified');
        }
        if ($action === 'suspend') {
            DB::update('users', ['status' => 'Suspended'], 'id = ?', [$id]);
            audit($actor, 'Suspended user ' . $u['email'], 'bad');
            json_ok([], 'User suspended — audit record written');
        }
        if ($action === 'restore') {
            DB::update('users', ['status' => 'Verified'], 'id = ?', [$id]);
            audit($actor, 'Restored user ' . $u['email'], 'warn');
            json_ok([], 'User restored');
        }
        break;
    }

    /* ------------------------------------------------------------- fraud */
    case 'fraud': {
        $f = DB::row('SELECT * FROM fraud_flags WHERE id = ?', [$id]);
        if (!$f) json_fail('That case no longer exists.', 404);

        if ($action === 'resolve') {
            DB::update('fraud_flags', ['status' => 'resolved', 'resolved_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            audit($actor, 'Resolved fraud case #' . $id, 'ok');
            json_ok([], 'Case resolved');
        }
        if ($action === 'escalate') {
            DB::update('fraud_flags', ['status' => 'escalated'], 'id = ?', [$id]);
            audit($actor, 'Escalated fraud case #' . $id, 'bad');
            json_ok([], 'Case escalated to the risk team');
        }
        break;
    }

    /* --------------------------------------------------------------- CMS */
    case 'cms': {
        if ($action === 'save') {
            $title = input_str('title');
            if ($title === '') json_fail('Give the block a title.');
            $fields = [
                'title'  => mb_substr($title, 0, 160),
                'body'   => mb_substr(input_str('body'), 0, 20000),
                'status' => input_str('status', 'Draft') === 'Live' ? 'Live' : 'Draft',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_label' => date('M j'),
            ];
            if ($id) {
                DB::update('cms_blocks', $fields, 'id = ?', [$id]);
            } else {
                $key = slugify($title);
                $n = 1;
                while (DB::value('SELECT 1 FROM cms_blocks WHERE block_key = ?', [$key])) {
                    $key = slugify($title) . '-' . (++$n);
                }
                $fields['block_key'] = $key;
                $fields['owner'] = (string) $admin['name'];
                $id = DB::insert('cms_blocks', $fields);
            }
            audit($actor, 'Saved content block #' . $id, 'info');
            json_ok(['id' => $id], 'Content saved');
        }
        if ($action === 'toggle') {
            $b = DB::row('SELECT * FROM cms_blocks WHERE id = ?', [$id]);
            if (!$b) json_fail('That block no longer exists.', 404);
            $next = ($b['status'] ?? '') === 'Live' ? 'Draft' : 'Live';
            DB::update('cms_blocks', ['status' => $next], 'id = ?', [$id]);
            audit($actor, 'Set block #' . $id . ' to ' . $next, 'info');
            json_ok([], $next === 'Live' ? 'Published' : 'Unpublished');
        }
        break;
    }

    /* ---------------------------------------------------------- campaigns */
    case 'campaign': {
        if ($action === 'save') {
            $code = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', input_str('code')) ?? '');
            $name = input_str('name');
            if ($code === '') json_fail('A campaign needs a promo code.');
            if ($name === '') json_fail('Give the campaign a name.');

            $fields = [
                'code'         => mb_substr($code, 0, 32),
                'name'         => mb_substr($name, 0, 160),
                'window_label' => mb_substr(input_str('window'), 0, 60),
                'status'       => input_str('status', 'Draft'),
            ];
            $clash = DB::row('SELECT id FROM campaigns WHERE code = ? AND id <> ?', [$fields['code'], $id]);
            if ($clash) json_fail('That promo code is already in use.');

            if ($id) {
                DB::update('campaigns', $fields, 'id = ?', [$id]);
            } else {
                $id = DB::insert('campaigns', $fields);
            }
            audit($actor, 'Saved campaign ' . $fields['code'], 'info');
            json_ok(['id' => $id], 'Campaign saved');
        }
        break;
    }

    /* ----------------------------------------------------------- bookings */
    case 'booking': {
        $b = DB::row('SELECT ref FROM bookings WHERE id = ?', [$id]);
        if (!$b) json_fail('That reservation no longer exists.', 404);
        [$ok, $message] = BookingService::transition((string) $b['ref'], $action);
        if (!$ok) json_fail($message);
        audit($actor, 'Booking ' . $b['ref'] . ' → ' . $action, 'ok');
        json_ok([], $message);
    }

    /* --------------------------------------------- hand-logged queue flags */
    case 'flag': {
        $m = DB::row('SELECT * FROM moderation_queue WHERE id = ?', [$id]);
        if (!$m) json_fail('That queue item no longer exists.', 404);
        if ($action === 'approve' || $action === 'reject') {
            DB::update('moderation_queue', ['status' => $action === 'approve' ? 'cleared' : 'rejected'], 'id = ?', [$id]);
            audit($actor, ucfirst($action) . 'd queue item — ' . $m['item'], $action === 'approve' ? 'ok' : 'warn');
            json_ok([], $action === 'approve' ? 'Cleared from the queue' : 'Removed from the queue');
        }
        break;
    }

    /* ------------------------------------------------------------ reviews */
    case 'review': {
        $r = DB::row('SELECT * FROM reviews WHERE id = ?', [$id]);
        if (!$r) json_fail('That review no longer exists.', 404);
        if ($action === 'approve') {
            DB::update('reviews', ['status' => 'published'], 'id = ?', [$id]);
            Repo::recalcRating((int) $r['property_id']);
            audit($actor, 'Published review #' . $id, 'ok');
            json_ok([], 'Review published');
        }
        if ($action === 'reject') {
            DB::update('reviews', ['status' => 'rejected'], 'id = ?', [$id]);
            Repo::recalcRating((int) $r['property_id']);
            audit($actor, 'Rejected review #' . $id, 'warn');
            json_ok([], 'Review removed');
        }
        break;
    }

    /* ----------------------------------------------------------- settings */
    case 'setting': {
        if ($action === 'save') {
            $key = preg_replace('~[^a-z0-9_]~i', '', input_str('key')) ?? '';
            if ($key === '') json_fail('Unknown setting.');
            Repo::saveSetting($key, input_str('value'));
            Repo::flush();
            audit($actor, 'Updated setting ' . $key, 'warn');
            json_ok([], 'Setting saved');
        }
        break;
    }
}

json_fail('Unknown admin action.');
