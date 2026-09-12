<?php
/**
 * Live chat — the visitor widget and the agent console share this endpoint.
 *
 *      action=config       — settings + open queues for the widget
 *      action=start        department, name, email, subject, message, page
 *      action=post         ref, token?, body, as=visitor|agent
 *      action=poll         ref, token?, after, as=visitor|agent
 *      action=typing       ref, token?|as=agent
 *      action=rate         ref, token?, stars, comment
 *      action=close        ref, as=agent|visitor
 *      action=history      — this visitor's past chats
 *
 *   Agent console (signed-in agent, supervisor or admin):
 *      action=console      — queues, my chats, team, metrics
 *      action=open         ref — transcript + visitor dossier
 *      action=claim / assign / transfer / note / use-canned / canned / metrics
 *      action=status       status=online|away|busy|offline
 *
 *   Administration (admin, or a chat supervisor):
 *      action=agents / save-agent / toggle-agent / delete-agent
 *      action=departments / save-department
 *      action=save-canned / delete-canned
 *      action=save-settings / sweep
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';
require_once JL_INC . '/livechat.php';
require_once JL_INC . '/disputes.php';

api_guard();

if (!DB::tableExists('chat_sessions')) {
    json_response([
        'ok'             => false,
        'needsMigration' => true,
        'message'        => 'Live chat needs its database tables — an administrator can finish that from install/migrate.php.',
    ], 503);
}

$action = input_str('action', '');
$admin  = Auth::isAdmin();
$me     = LiveChat::agentForUser(Auth::id());

/**
 * Agent gate. Admins may always work the queues; anyone else needs an active
 * chat_agents row (created from the admin console). $supervisor additionally
 * requires the supervisor role — used for transfers, routing and configuration.
 */
$requireAgent = static function (bool $supervisor = false) use ($admin, $me): array {
    if (!$admin && !$me) {
        json_fail('Agent sign-in required.', 403, ['requiresAgent' => true]);
    }
    if ($me && (int) ($me['active'] ?? 1) !== 1) {
        json_fail('Your agent account is paused — ask an administrator to switch it back on.', 403);
    }
    if ($supervisor && !$admin && (string) ($me['role'] ?? 'agent') !== 'supervisor') {
        json_fail('Supervisor access required.', 403, ['requiresSupervisor' => true]);
    }
    return $me ?: [
        'id' => 0, 'name' => (string) (Auth::user()['name'] ?? 'Admin'), 'role' => 'admin',
        'queues' => [], 'coversAll' => true, 'max' => 99, 'load' => 0,
        'status' => 'online', 'active' => true, 'userId' => Auth::id(),
    ];
};

$agentActor = static function (array $agent): array {
    return ['id' => (int) $agent['id'], 'name' => (string) $agent['name'], 'role' => (string) ($agent['role'] ?? 'agent')];
};

switch ($action) {

    /* ========================================================== visitor side */

    case 'config': {
        $cfg = LiveChat::settings();
        json_ok([
            'enabled'     => (bool) $cfg['enabled'],
            'welcome'     => (string) $cfg['welcome'],
            'offline'     => (string) $cfg['offline'],
            'hours'       => (string) $cfg['hours'],
            'rating'      => (bool) $cfg['rating'],
            'departments' => array_map(static fn($d) => [
                'slug' => $d['slug'], 'name' => $d['name'], 'hours' => $d['hours'],
                'agents' => $d['agents'], 'open' => $d['open'], 'welcome' => $d['welcome'],
            ], LiveChat::departments(true)),
            'signedIn'    => Auth::check(),
        ]);
    }

    case 'start': {
        api_throttle('chat_start', 12, 900);
        $cfg = LiveChat::settings();
        if (!$cfg['enabled']) {
            json_fail((string) $cfg['offline']);
        }
        [$ok, $message, $data] = LiveChat::start([
            'department' => input_str('department'),
            'name'       => input_str('name'),
            'email'      => input_str('email'),
            'phone'      => input_str('phone'),
            'subject'    => input_str('subject'),
            'message'    => input_str('message'),
            'page'       => input_str('page'),
            'source'     => input_str('source', 'widget'),
            'token'      => input_str('token'),
        ], Auth::id());
        if (!$ok) {
            json_fail($message);
        }
        json_ok_state($data, $message);
    }

    case 'history': {
        $token = input_str('token');
        $uid = Auth::id();
        $email = strtolower((string) (Auth::user()['email'] ?? ''));
        if ($token === '' && $uid === null && $email === '') {
            json_ok(['chats' => [], 'bundle' => null]);
        }
        $rows = DB::all(
            "SELECT * FROM chat_sessions
              WHERE visitor_token = ?
                 OR (visitor_id IS NOT NULL AND visitor_id = ?)
                 OR (visitor_email IS NOT NULL AND visitor_email = ? AND visitor_email <> '')
           ORDER BY id DESC LIMIT 12",
            [$token, (int) $uid, $email]
        );
        $live = null;
        foreach ($rows as $r) {
            if (in_array((string) $r['status'], ['queued', 'active'], true)) {
                $live = (string) $r['ref'];
                break;
            }
        }
        json_ok([
            'chats'  => array_map(static fn($r) => [
                'ref'    => (string) $r['ref'],
                'status' => (string) $r['status'],
                'when'   => date('M j', strtotime((string) $r['created_at'])),
                'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
                'subject' => (string) ($r['subject'] ?? ''),
            ], $rows),
            'live'   => $live,
        ]);
    }

    /* ============================================================ agent side */

    case 'console': {
        $agent = $requireAgent();
        $state = LiveChat::consoleState($agent, $admin);
        json_ok([
            'state'    => $state,
            'me'       => $me ? [
                'id'     => (int) $me['id'],
                'name'   => (string) $me['name'],
                'role'   => (string) ($me['role'] ?? 'agent'),
                'status' => (string) ($me['status'] ?? 'offline'),
                'active' => (bool) ($me['active'] ?? true),
                'max'    => (int) ($me['max'] ?? 0),
                'load'   => (int) ($me['load'] ?? 0),
                'queues' => $me['queues'] ?? [],
                'coversAll' => (bool) ($me['coversAll'] ?? false),
                'email'  => (string) ($me['email'] ?? ''),
                'signature' => (string) ($me['signature'] ?? ''),
            ] : null,
            'isAgent'  => $me !== null && (int) ($me['active'] ?? 1) === 1,
            'admin'    => $admin,
            'settings' => LiveChat::settings(),
            'departments' => LiveChat::departments(false),
            'agents'   => $admin ? LiveChat::agents(false) : [],
            'canned'   => LiveChat::allCanned(),
            'metrics'  => LiveChat::metrics(),
        ]);
    }

    case 'open': {
        $agent = $requireAgent();
        $s = LiveChat::session(input_str('ref'));
        if (!$s) {
            json_fail('That chat no longer exists.', 404);
        }
        $bundle = LiveChat::visitorBundle($s, '', 0, 'agent');
        json_ok([
            'session'  => LiveChat::shapeSession($s),
            'messages' => LiveChat::messages((int) $s['id'], 0, 'agent'),
            'visitor'  => LiveChat::visitorProfile($s),
            'events'   => LiveChat::timeline((int) $s['id']),
            'bundle'   => $bundle,
            'isAgent'  => true,
        ]);
    }

    case 'poll': {
        $as = input_str('as', 'visitor');
        $ref = input_str('ref');
        if ($as === 'agent') {
            $agent = $requireAgent();
            [$ok, $message, $data] = LiveChat::poll($ref, '', input_int('after'), 'agent', (int) $agent['id']);
        } else {
            api_throttle('chat_poll', 240, 600);
            [$ok, $message, $data] = LiveChat::poll($ref, input_str('token'), input_int('after'), 'visitor');
        }
        if (!$ok) {
            json_fail($message, is_int($data) ? $data : 400);
        }
        json_ok($data, $message);
    }

    case 'post': {
        $as = input_str('as', 'visitor');
        $body = input_str('body');
        if (trim($body) === '') {
            json_fail('Type a message first.');
        }
        if ($as === 'agent') {
            $agent = $requireAgent();
            [$ok, $message, $data] = LiveChat::post(input_str('ref'), 'agent', $body, (int) $agent['id']);
        } else {
            api_throttle('chat_post', 60, 600);
            [$ok, $message, $data] = LiveChat::post(input_str('ref'), 'visitor', $body, null, input_str('token'));
        }
        if (!$ok) {
            json_fail($message, is_int($data) ? $data : 400);
        }
        $s = LiveChat::session(input_str('ref')) ?: [];
        json_ok([
            'id'     => (int) ($data['id'] ?? 0),
            'time'   => (string) ($data['time'] ?? ''),
            'bundle' => $s ? LiveChat::visitorBundle($s, input_str('token'), 0, $as === 'agent' ? 'agent' : 'visitor') : null,
        ], $message);
    }

    case 'typing': {
        $as = input_str('as', 'visitor');
        if ($as === 'agent') {
            $agent = $requireAgent();
            [$ok, $message] = LiveChat::typing(input_str('ref'), 'agent', $agentActor($agent));
        } else {
            [$ok, $message] = LiveChat::typing(input_str('ref'), 'visitor', ['token' => input_str('token')]);
        }
        if (!$ok) {
            json_fail($message);
        }
        json_ok([], $message);
    }

    case 'close': {
        $as = input_str('as', 'agent');
        if ($as === 'agent') {
            $agent = $requireAgent();
            [$ok, $message, $data] = LiveChat::close(input_str('ref'), 'agent', input_str('note'), $agentActor($agent));
        } else {
            [$ok, $message, $data] = LiveChat::close(input_str('ref'), 'visitor', input_str('note'), ['token' => input_str('token')]);
        }
        if (!$ok) {
            json_fail($message, is_int($data) ? $data : 400);
        }
        $s = LiveChat::session(input_str('ref')) ?: [];
        json_ok($s ? LiveChat::visitorBundle($s, input_str('token')) : [], $message);
    }

    case 'rate': {
        [$ok, $message, $data] = LiveChat::rate(input_str('ref'), input_int('stars'), input_str('comment'), input_str('token'));
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data, $message);
    }

    case 'claim': {
        $agent = $requireAgent();
        [$ok, $message, $data] = LiveChat::claim(input_str('ref'), $agent);
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data, $message);
    }

    case 'note': {
        $agent = $requireAgent();
        [$ok, $message, $data] = LiveChat::post(input_str('ref'), 'note', input_str('body'), (int) $agent['id']);
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data, $message);
    }

    case 'transfer': {
        $agent = $requireAgent(true);
        [$ok, $message, $data] = LiveChat::transfer(input_str('ref'), input_int('agent'), $agentActor($agent));
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data, $message);
    }

    case 'assign': {
        $agent = $requireAgent(true);
        [$ok, $message, $data] = LiveChat::assign(input_str('ref'), input_int('agent'), $agentActor($agent), input_str('note'));
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data, $message);
    }

    case 'use-canned': {
        $agent = $requireAgent();
        $s = LiveChat::session(input_str('ref'));
        if (!$s) {
            json_fail('That chat no longer exists.', 404);
        }
        $canned = null;
        foreach (LiveChat::allCanned() as $c) {
            if ((int) $c['id'] === input_int('id')) {
                $canned = $c;
                break;
            }
        }
        if (!$canned) {
            json_fail('That saved reply no longer exists.', 404);
        }
        $body = str_replace('{agent}', (string) $agent['name'] ?: 'your agent', (string) $canned['body']);
        [$ok, $message, $data] = LiveChat::post((string) $s['ref'], 'agent', $body, (int) $agent['id'] ?: null);
        if (!$ok) {
            json_fail($message);
        }
        DB::run('UPDATE chat_canned SET uses = uses + 1 WHERE id = ?', [(int) $canned['id']]);
        Repo::flush();
        json_ok(['message' => $data, 'canned' => $canned], $message);
    }

    case 'canned': {
        $requireAgent();
        json_ok(['canned' => LiveChat::allCanned()]);
    }

    case 'status': {
        $agent = $requireAgent();
        $message = '';
        if (!empty($agent['id'])) {
            [$ok, $message] = LiveChat::setAgentStatus((int) $agent['id'], input_str('status', 'online'));
            if (!$ok) {
                json_fail($message);
            }
        }
        json_ok(['me' => LiveChat::agent((int) ($agent['id'] ?? 0)), 'console' => LiveChat::consoleState($agent, $admin)], $message);
    }

    case 'metrics': {
        $requireAgent();
        json_ok([
            'metrics'  => LiveChat::metrics(),
            'agents'   => LiveChat::agents(false),
            'disputes' => DB::tableExists('disputes') ? DisputeService::stats() : null,
        ]);
    }

    case 'sweep': {
        $requireAgent(true);
        LiveChat::sweep();
        json_ok(['metrics' => LiveChat::metrics()], 'Stale chats cleared up.');
    }

    /* -------------------------------------------------------- administration */

    case 'agents': {
        $requireAgent(true);
        json_ok([
            'agents'      => LiveChat::agents(false),
            'departments' => LiveChat::departments(false),
            'metrics'     => LiveChat::metrics(),
            'admins'      => array_map(static fn($u) => [
                'id' => (int) $u['id'], 'name' => (string) $u['name'], 'email' => (string) $u['email'],
            ], DB::all("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name")),
        ]);
    }

    case 'save-agent': {
        $requireAgent(true);
        $body = request_body();
        $in = ['id' => (int) ($body['id'] ?? 0)];
        foreach (['name', 'email', 'phone', 'role', 'signature', 'status', 'password'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_str($k);
            }
        }
        foreach (['department', 'max', 'user'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_int($k);
            }
        }
        if (array_key_exists('queues', $body)) {
            $in['queues'] = array_map('intval', (array) $body['queues']);
        }
        if (array_key_exists('active', $body)) {
            $in['active'] = input_bool('active', true);
        }
        [$ok, $message, $data] = LiveChat::saveAgent($in, ['id' => 0, 'name' => (string) (Auth::user()['name'] ?? 'Admin')]);
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data + ['agents' => LiveChat::agents(false)], $message);
    }

    case 'toggle-agent': {
        $requireAgent(true);
        [$ok, $message] = LiveChat::toggleAgent(input_int('id'), input_bool('active', true), ['name' => (string) (Auth::user()['name'] ?? 'Admin')]);
        if (!$ok) {
            json_fail($message);
        }
        json_ok(['agents' => LiveChat::agents(false)], $message);
    }

    case 'delete-agent': {
        $requireAgent(true);
        [$ok, $message] = LiveChat::deleteAgent(input_int('id'), ['name' => (string) (Auth::user()['name'] ?? 'Admin')]);
        if (!$ok) {
            json_fail($message);
        }
        json_ok(['agents' => LiveChat::agents(false)], $message);
    }

    case 'departments': {
        $requireAgent(true);
        json_ok(['departments' => LiveChat::departments(false)]);
    }

    case 'save-department': {
        $requireAgent(true);
        $body = request_body();
        $in = [];
        foreach (['id', 'sort'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_int($k);
            }
        }
        foreach (['name', 'slug', 'description', 'routing', 'welcome', 'hours', 'color'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_str($k);
            }
        }
        if (array_key_exists('active', $body)) {
            $in['active'] = input_bool('active', true);
        }
        [$ok, $message, $data] = LiveChat::saveDepartment($in);
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data + ['departments' => LiveChat::departments(false)], $message);
    }

    case 'save-canned': {
        $requireAgent(true);
        $body = request_body();
        $in = [];
        if (array_key_exists('id', $body)) {
            $in['id'] = input_int('id');
        }
        foreach (['title', 'body', 'shortcut', 'scope'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_str($k);
            }
        }
        foreach (['department', 'agent'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_int($k);
            }
        }
        if (array_key_exists('active', $body)) {
            $in['active'] = input_bool('active', true);
        }
        [$ok, $message, $data] = LiveChat::saveCanned($in, ['name' => (string) (Auth::user()['name'] ?? 'Admin')]);
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data + ['canned' => LiveChat::allCanned()], $message);
    }

    case 'delete-canned': {
        $requireAgent(true);
        [$ok, $message] = LiveChat::deleteCanned(input_int('id'), ['name' => (string) (Auth::user()['name'] ?? 'Admin')]);
        if (!$ok) {
            json_fail($message);
        }
        json_ok(['canned' => LiveChat::allCanned()], $message);
    }

    case 'save-settings': {
        $requireAgent(true);
        $body = request_body();
        $in = [];
        foreach (['welcome', 'offline', 'hours', 'routing'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_str($k);
            }
        }
        foreach (['maxQueue', 'autoClose'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_int($k);
            }
        }
        foreach (['enabled', 'rating', 'transcript'] as $k) {
            if (array_key_exists($k, $body)) {
                $in[$k] = input_bool($k, true);
            }
        }
        [$ok, $message] = LiveChat::saveSettings($in);
        if (!$ok) {
            json_fail($message);
        }
        json_ok(['settings' => LiveChat::settings(), 'departments' => LiveChat::departments(false)], $message);
    }
}

json_fail('Unknown chat action.');
