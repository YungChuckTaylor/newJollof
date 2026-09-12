<?php
/**
 * Owner (host) workspace actions.
 *
 * Every action is scoped to the signed-in owner. Anything that touches a
 * property re-checks properties.host_id first, so an owner can never read or
 * write another owner's data by guessing an id.
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];

$action = input_str('action');

// Becoming an owner is the one action a customer is allowed to take here, so
// it has to be handled before the owner guard below.
if ($action === 'upgrade') {
    if (!Auth::isHost()) {
        Auth::provisionHost($uid);
        Repo::notify($uid, 'Hosting enabled ✨', 'Your owner workspace is ready. Add your first listing to start earning.', 'spark');
        audit((string) $user['email'], 'Upgraded to an owner account', 'ok');
    }
    json_ok_state(['redirect' => url('host-dashboard.php')], 'Hosting enabled — welcome aboard ✨');
}

if (!Auth::isHost()) {
    json_fail('Your account is not set up for hosting yet.', 403, ['requiresHost' => true]);
}

/** Confirm a property belongs to this owner before touching it. */
$ownProperty = static function (int $pid) use ($uid): array {
    $p = DB::row('SELECT * FROM properties WHERE id = ? AND host_id = ?', [$pid, $uid]);
    if (!$p) {
        json_fail('That listing is not on your account.', 403);
    }
    return $p;
};

switch ($action) {

    /* ------------------------------------------------ calendar & pricing */

    case 'calendar':
        $pid = input_int('property');
        $month = input_str('month') ?: date('Y-m');
        if (!preg_match('~^\d{4}-\d{2}$~', $month)) {
            json_fail('That month is not valid.');
        }
        json_ok(['calendar' => Repo::hostCalendar($uid, $pid ?: null, $month)]);
        // no break — json_ok exits

    case 'set-day':
        $pid = input_int('property');
        $ownProperty($pid);
        $day = input_str('day');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $day)) {
            json_fail('That date is not valid.');
        }
        $price = input_int('price');
        $blocked = input_bool('blocked');
        if ($price < 0 || $price > 100000000) {
            json_fail('That nightly rate is out of range.');
        }

        $existing = DB::value('SELECT id FROM property_calendar WHERE property_id = ? AND day = ?', [$pid, $day]);
        $fields = [
            'price'  => $price > 0 ? $price : null,
            'status' => $blocked ? 'blocked' : 'open',
            'note'   => input_str('note') ?: null,
        ];
        if ($existing) {
            DB::update('property_calendar', $fields, 'id = ?', [(int) $existing]);
        } else {
            DB::insert('property_calendar', $fields + ['property_id' => $pid, 'day' => $day]);
        }
        audit((string) $user['email'], "Calendar updated for {$day}", 'ok');
        json_ok(['calendar' => Repo::hostCalendar($uid, $pid, substr($day, 0, 7))], $blocked ? 'Date blocked.' : 'Date updated.');

    case 'bulk-price':
        $pid = input_int('property');
        $ownProperty($pid);
        $from = input_str('from');
        $to = input_str('to');
        $price = input_int('price');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $from) || !preg_match('~^\d{4}-\d{2}-\d{2}$~', $to)) {
            json_fail('Please choose a valid date range.');
        }
        if (strtotime($to) < strtotime($from)) {
            json_fail('The end date comes before the start date.');
        }
        if ($price < 1000) {
            json_fail('Please set a nightly rate of at least ₦1,000.');
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 366) {
            json_fail('Please choose a range of a year or less.');
        }

        $cur = strtotime($from);
        $n = 0;
        while ($cur <= strtotime($to)) {
            $day = date('Y-m-d', $cur);
            $existing = DB::value('SELECT id FROM property_calendar WHERE property_id = ? AND day = ?', [$pid, $day]);
            if ($existing) {
                DB::update('property_calendar', ['price' => $price], 'id = ?', [(int) $existing]);
            } else {
                DB::insert('property_calendar', ['property_id' => $pid, 'day' => $day, 'price' => $price]);
            }
            $n++;
            $cur = strtotime('+1 day', $cur);
        }
        audit((string) $user['email'], "Bulk price applied to {$n} nights", 'ok');
        json_ok(['calendar' => Repo::hostCalendar($uid, $pid, substr($from, 0, 7))], "Updated {$n} night" . ($n === 1 ? '' : 's') . '.');

    /* -------------------------------------------------------- listings */

    case 'listing-status':
        $pid = input_int('property');
        $p = $ownProperty($pid);
        $to = input_str('status');
        if (!in_array($to, ['live', 'paused'], true)) {
            json_fail('That status is not available.');
        }
        if (in_array($p['status'], ['pending', 'draft', 'rejected'], true)) {
            json_fail('This listing is still in verification.');
        }
        DB::update('properties', ['status' => $to], 'id = ?', [$pid]);
        Repo::flush();
        audit((string) $user['email'], "Listing {$p['slug']} set to {$to}", 'ok');
        json_ok(['host' => Repo::hostState($uid)], $to === 'live' ? 'Listing is live again.' : 'Listing paused — your calendar is preserved.');

    case 'listing-price':
        $pid = input_int('property');
        $ownProperty($pid);
        $price = input_int('price');
        if ($price < 1000) {
            json_fail('Please set a nightly rate of at least ₦1,000.');
        }
        DB::update('properties', ['price' => $price], 'id = ?', [$pid]);
        Repo::flush();
        json_ok(['host' => Repo::hostState($uid)], 'Nightly rate updated.');

    /* --------------------------------------------------- pricing rules */

    case 'rule-add':
        $name = input_str('name');
        $pct = (float) input('adjust', 0);
        if (mb_strlen($name) < 2) {
            json_fail('Give the rule a name.');
        }
        if ($pct < -90 || $pct > 300) {
            json_fail('That adjustment is out of range.');
        }
        $kind = input_str('kind', 'seasonal');
        if (!in_array($kind, ['seasonal', 'weekend', 'lastminute', 'length', 'custom'], true)) {
            $kind = 'custom';
        }
        DB::insert('pricing_rules', [
            'host_id'    => $uid,
            'name'       => mb_substr($name, 0, 140),
            'kind'       => $kind,
            'adjust_pct' => $pct,
            'starts_on'  => input_str('starts') ?: null,
            'ends_on'    => input_str('ends') ?: null,
        ]);
        json_ok(['host' => Repo::hostState($uid)], 'Pricing rule added.');

    case 'rule-toggle':
        $id = input_int('id');
        $r = DB::row('SELECT * FROM pricing_rules WHERE id = ? AND host_id = ?', [$id, $uid]);
        if (!$r) {
            json_fail('That rule is not on your account.', 403);
        }
        DB::update('pricing_rules', ['active' => (int) $r['active'] === 1 ? 0 : 1], 'id = ?', [$id]);
        json_ok(['host' => Repo::hostState($uid)], 'Rule updated.');

    case 'rule-delete':
        $id = input_int('id');
        if (!DB::value('SELECT id FROM pricing_rules WHERE id = ? AND host_id = ?', [$id, $uid])) {
            json_fail('That rule is not on your account.', 403);
        }
        DB::run('DELETE FROM pricing_rules WHERE id = ?', [$id]);
        json_ok(['host' => Repo::hostState($uid)], 'Rule removed.');

    /* ------------------------------------------------------------ team */

    case 'team-invite':
        $name = input_str('name');
        $email = strtolower(input_str('email'));
        if (mb_strlen($name) < 2) {
            json_fail('Please enter the person\'s name.');
        }
        if (!is_email($email)) {
            json_fail('Please enter a valid email address.');
        }
        if (DB::value("SELECT id FROM host_team WHERE host_id = ? AND email = ? AND status <> 'revoked'", [$uid, $email])) {
            json_fail('That person is already on your team.');
        }
        $perms = input_str('permissions', 'calendar,messages');
        DB::insert('host_team', [
            'host_id'     => $uid,
            'member_id'   => DB::value('SELECT id FROM users WHERE email = ?', [$email]) ?: null,
            'name'        => mb_substr($name, 0, 120),
            'email'       => $email,
            'team_role'   => input_str('role', 'cohost'),
            'permissions' => mb_substr($perms, 0, 240),
            'status'      => 'invited',
        ]);
        audit((string) $user['email'], "Invited {$email} as a co-host", 'ok');
        json_ok(['host' => Repo::hostState($uid)], 'Invitation sent.');

    case 'team-revoke':
        $id = input_int('id');
        if (!DB::value('SELECT id FROM host_team WHERE id = ? AND host_id = ?', [$id, $uid])) {
            json_fail('That team member is not on your account.', 403);
        }
        DB::update('host_team', ['status' => 'revoked'], 'id = ?', [$id]);
        audit((string) $user['email'], 'Revoked co-host access', 'warn');
        json_ok(['host' => Repo::hostState($uid)], 'Access revoked.');

    /* ------------------------------------------------------- templates */

    case 'template-save':
        $id = input_int('id');
        $title = input_str('title');
        $body = input_str('body');
        if (mb_strlen($title) < 2) {
            json_fail('Give the template a title.');
        }
        if (mb_strlen($body) < 4) {
            json_fail('The template needs a message body.');
        }
        $fields = [
            'title'      => mb_substr($title, 0, 140),
            'body'       => $body,
            'trigger_on' => input_str('trigger', 'manual'),
        ];
        if ($id > 0) {
            if (!DB::value('SELECT id FROM host_templates WHERE id = ? AND host_id = ?', [$id, $uid])) {
                json_fail('That template is not on your account.', 403);
            }
            DB::update('host_templates', $fields, 'id = ?', [$id]);
        } else {
            DB::insert('host_templates', $fields + ['host_id' => $uid]);
        }
        json_ok(['host' => Repo::hostState($uid)], 'Template saved.');

    case 'template-delete':
        $id = input_int('id');
        if (!DB::value('SELECT id FROM host_templates WHERE id = ? AND host_id = ?', [$id, $uid])) {
            json_fail('That template is not on your account.', 403);
        }
        DB::run('DELETE FROM host_templates WHERE id = ?', [$id]);
        json_ok(['host' => Repo::hostState($uid)], 'Template removed.');

    /* -------------------------------------------------------- channels */

    case 'channel-toggle':
        $id = input_int('id');
        $c = DB::row('SELECT * FROM host_channels WHERE id = ? AND host_id = ?', [$id, $uid]);
        if (!$c) {
            json_fail('That channel is not on your account.', 403);
        }
        $connect = $c['status'] !== 'connected';
        DB::update('host_channels', [
            'status'       => $connect ? 'connected' : 'disconnected',
            'last_sync_at' => $connect ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$id]);
        json_ok(
            ['host' => Repo::hostState($uid)],
            $connect ? "{$c['channel']} connected — availability now syncs both ways." : "{$c['channel']} disconnected."
        );

    /* --------------------------------------------------------- payouts */

    case 'payout-settings':
        $schedule = input_str('schedule', 'weekly');
        if (!in_array($schedule, ['daily', 'weekly', 'monthly'], true)) {
            json_fail('Please choose a valid payout schedule.');
        }
        $acct = preg_replace('~\D~', '', input_str('account'));
        $fields = [
            'schedule'     => $schedule,
            'bank_name'    => input_str('bank') ?: null,
            'account_name' => input_str('account_name') ?: null,
            'account_last' => $acct ? substr($acct, -4) : null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if (DB::value('SELECT host_id FROM host_payout_settings WHERE host_id = ?', [$uid])) {
            DB::update('host_payout_settings', $fields, 'host_id = ?', [$uid]);
        } else {
            DB::insert('host_payout_settings', $fields + ['host_id' => $uid]);
        }
        audit((string) $user['email'], 'Payout settings updated', 'ok');
        json_ok(['host' => Repo::hostState($uid)], 'Payout settings saved.');

    default:
        json_fail('Unknown action.');
}
