<?php
/**
 * Jollof Living — Live Chat.
 *
 * A complete, database-backed live chat desk:
 *
 *   • the site admin creates agents (chat_agents) and assigns them to queues
 *     (chat_departments, chat_agent_departments);
 *   • visitors start a chat from the widget on any page, /chat.php or the
 *     agent console's public view — the desk routes it to the best agent
 *     (fewest open chats, round-robin, or manual for the trust desk);
 *   • agents work requests from /agent.php: claim, reply, canned replies,
 *     internal notes, transfer, close, rate and read the visitor's history;
 *   • everything is stored: chat_sessions, chat_messages, chat_events.
 *
 * Tables are created by install/migrate.php.
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}

final class LiveChat
{
    public const STATUSES = ['queued', 'active', 'closed', 'abandoned'];
    public const AGENT_STATUSES = ['online' => 'Online', 'away' => 'Away', 'busy' => 'Busy (no new chats)', 'offline' => 'Offline'];

    /* --------------------------------------------------------------- settings */

    public static function defaults(): array
    {
        return [
            'enabled'    => '1',
            'welcome'    => 'Welcome to Jollof Living 👋 Chat with our team about stays, bookings, payments or hosting. A specialist replies in about a minute.',
            'offline'    => 'Our team is offline right now. Leave your question here and we will reply by email — or ask Jollof, our AI concierge, for an instant answer.',
            'routing'    => 'fewest',
            'maxQueue'   => '25',
            'autoClose'  => '30',
            'rating'     => '1',
            'hours'      => '24/7 support · median first reply under 3 minutes',
            'transcript' => '1',
        ];
    }

    /** Chat configuration from the settings table (with safe fallbacks). */
    public static function settings(): array
    {
        $out = [];
        foreach (self::defaults() as $key => $fallback) {
            $out[$key] = (string) Repo::setting('chat_' . $key, $fallback);
        }
        $out['enabled']    = $out['enabled'] === '1';
        $out['rating']     = $out['rating'] === '1';
        $out['transcript'] = $out['transcript'] === '1';
        $out['maxQueue']   = max(1, (int) $out['maxQueue']);
        $out['autoClose']  = max(5, (int) $out['autoClose']);
        return $out;
    }

    public static function saveSettings(array $in): array
    {
        $fields = ['enabled', 'welcome', 'offline', 'routing', 'maxQueue', 'autoClose', 'rating', 'hours', 'transcript'];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $in)) {
                continue;
            }
            $value = $in[$f];
            if (in_array($f, ['enabled', 'rating', 'transcript'], true)) {
                $value = in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
            } elseif (in_array($f, ['maxQueue', 'autoClose'], true)) {
                $value = (string) max(1, (int) $value);
            } elseif ($f === 'routing') {
                $value = in_array((string) $value, ['fewest', 'round_robin', 'manual'], true) ? (string) $value : 'fewest';
            }
            Repo::saveSetting('chat_' . $f, (string) $value);
        }
        Repo::flush();
        return [true, 'Chat settings saved.', null];
    }

    /* ------------------------------------------------------------ departments */

    public static function departments(bool $activeOnly = false): array
    {
        $rows = DB::all(
            'SELECT d.*, (SELECT COUNT(*) FROM chat_agents a WHERE a.is_active = 1
                            AND (a.department_id = d.id OR EXISTS (
                                 SELECT 1 FROM chat_agent_departments m WHERE m.agent_id = a.id AND m.department_id = d.id))
                         ) AS agents,
                         (SELECT COUNT(*) FROM chat_sessions s WHERE s.department_id = d.id AND s.status IN (\'queued\',\'active\')) AS open_chats
               FROM chat_departments d '
            . ($activeOnly ? 'WHERE d.is_active = 1 ' : '')
            . 'ORDER BY d.sort_order, d.id'
        );
        return array_map(static fn($d) => [
            'id'         => (int) $d['id'],
            'slug'       => (string) $d['slug'],
            'name'       => (string) $d['name'],
            'description' => (string) ($d['description'] ?? ''),
            'routing'    => (string) $d['routing'],
            'welcome'    => (string) ($d['welcome_message'] ?? ''),
            'hours'      => (string) ($d['business_hours'] ?? ''),
            'color'      => (string) ($d['color'] ?? ''),
            'agents'     => (int) ($d['agents'] ?? 0),
            'open'       => (int) ($d['open_chats'] ?? 0),
            'active'     => (int) $d['is_active'] === 1,
        ], $rows);
    }

    public static function departmentById(?int $id): ?array
    {
        if (!$id) {
            return null;
        }
        foreach (self::departments() as $d) {
            if ($d['id'] === $id) {
                return $d;
            }
        }
        return null;
    }

    public static function resolveDepartment($key, bool $activeOnly = true): ?array
    {
        $list = self::departments($activeOnly);
        if (!$list) {
            return null;
        }
        if ($key === null || $key === '' || $key === 0) {
            return $list[0];
        }
        foreach ($list as $d) {
            if ((string) $d['id'] === (string) $key || $d['slug'] === (string) $key) {
                return $d;
            }
        }
        return $list[0];
    }

    public static function saveDepartment(array $in): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return [false, 'Give the queue a name.', null];
        }
        $id = (int) ($in['id'] ?? 0);
        $fields = [
            'name'            => mb_substr($name, 0, 140),
            'description'     => mb_substr(trim((string) ($in['description'] ?? '')), 0, 255) ?: null,
            'routing'         => in_array((string) ($in['routing'] ?? ''), ['fewest', 'round_robin', 'manual'], true) ? (string) $in['routing'] : 'fewest',
            'welcome_message' => mb_substr(trim((string) ($in['welcome'] ?? '')), 0, 500) ?: null,
            'business_hours'  => mb_substr(trim((string) ($in['hours'] ?? '')), 0, 140) ?: null,
            'color'           => mb_substr(trim((string) ($in['color'] ?? '')), 0, 20) ?: null,
            'is_active'       => !array_key_exists('active', $in) || in_array((string) $in['active'], ['1', 'true', 'on'], true) ? 1 : 0,
            'sort_order'      => (int) ($in['sort'] ?? 0),
        ];
        if ($id) {
            DB::update('chat_departments', $fields, 'id = ?', [$id]);
        } else {
            $slug = slugify($name);
            $n = 1;
            while (DB::value('SELECT 1 FROM chat_departments WHERE slug = ?', [$slug])) {
                $slug = slugify($name) . '-' . (++$n);
            }
            $fields['slug'] = $slug;
            $id = DB::insert('chat_departments', $fields);
        }
        Repo::flush();
        return [true, 'Queue saved.', ['id' => $id]];
    }

    /* ----------------------------------------------------------------- agents */

    public static function agents(bool $activeOnly = false): array
    {
        $rows = DB::all(
            'SELECT a.*, d.name AS department_name,
                    (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = \'active\') AS `load`,
                    (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = \'closed\') AS handled
               FROM chat_agents a
          LEFT JOIN chat_departments d ON d.id = a.department_id '
            . ($activeOnly ? 'WHERE a.is_active = 1 ' : '')
            . 'ORDER BY ' . self::rankCase('a.status', ['online', 'away', 'busy']) . ', a.name'
        );
        $out = [];
        foreach ($rows as $a) {
            $out[] = self::shapeAgent($a);
        }
        return $out;
    }

    /**
     * Agents who can take a chat in a queue.
     *
     * $onlineOnly = true keeps auto-routing to agents who are actually online;
     * manual assignment and reporting also see agents who are away.
     */
    public static function availableAgents(?int $deptId, bool $onlineOnly = true): array
    {
        $sql = "SELECT a.*, (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = 'active') AS `load`
                  FROM chat_agents a
                 WHERE a.is_active = 1 AND a.status IN (" . ($onlineOnly ? "'online'" : "'online','away'") . ')';
        $rows = DB::all($sql);
        $out = [];
        foreach ($rows as $a) {
            $agent = self::shapeAgent($a);
            if ($agent['load'] >= $agent['max']) {
                continue;
            }
            if ($deptId && !$agent['coversAll'] && !in_array($deptId, $agent['queues'], true)) {
                continue;
            }
            $out[] = $agent;
        }
        return $out;
    }

    public static function agent(int $id): ?array
    {
        $a = DB::row(
            'SELECT a.*, d.name AS department_name,
                    (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = \'active\') AS `load`,
                    (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = \'closed\') AS handled
               FROM chat_agents a LEFT JOIN chat_departments d ON d.id = a.department_id WHERE a.id = ?',
            [$id]
        );
        return $a ? self::shapeAgent($a) : null;
    }

    /** The agent row linked to a signed-in member (or null). */
    public static function agentForUser(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }
        $a = DB::row(
            "SELECT a.*, d.name AS department_name,
                    (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = 'active') AS `load`,
                    (SELECT COUNT(*) FROM chat_sessions s WHERE s.agent_id = a.id AND s.status = 'closed') AS handled
               FROM chat_agents a LEFT JOIN chat_departments d ON d.id = a.department_id
              WHERE a.user_id = ? AND a.is_active = 1 LIMIT 1",
            [$userId]
        );
        return $a ? self::shapeAgent($a) : null;
    }

    public static function agentForEmail(string $email): ?array
    {
        $a = DB::row(
            'SELECT * FROM chat_agents WHERE email = ? AND is_active = 1 LIMIT 1',
            [strtolower(trim($email))]
        );
        return $a ? self::shapeAgent($a) : null;
    }

    private static function shapeAgent(array $a): array
    {
        $queues = array_map('intval', array_column(
            DB::all('SELECT department_id FROM chat_agent_departments WHERE agent_id = ?', [(int) $a['id']]),
            'department_id'
        ));
        if (!empty($a['department_id'])) {
            $queues[] = (int) $a['department_id'];
        }
        $queues = array_values(array_unique($queues));
        return [
            'id'        => (int) $a['id'],
            'userId'    => (int) ($a['user_id'] ?? 0),
            'name'      => (string) $a['name'],
            'email'     => (string) $a['email'],
            'phone'     => (string) ($a['phone'] ?? ''),
            'role'      => (string) ($a['agent_role'] ?? 'agent'),
            'departmentId' => (int) ($a['department_id'] ?? 0),
            'department' => (string) ($a['department_name'] ?? 'All queues'),
            'status'    => (string) $a['status'],
            'statusLabel' => self::AGENT_STATUSES[(string) $a['status']] ?? ucfirst((string) $a['status']),
            'max'       => max(1, (int) $a['max_chats']),
            'load'      => (int) ($a['load'] ?? 0),
            'handled'   => (int) ($a['handled'] ?? 0),
            'queues'    => $queues,
            'coversAll' => empty($queues),
            'signature' => (string) ($a['signature'] ?? ''),
            'active'    => (int) $a['is_active'] === 1,
            'lastSeen'  => (string) ($a['last_seen_at'] ?? ''),
        ];
    }

    /**
     * Create or update an agent. $in: id, name, email, phone, role, department,
     * queues[], max, status, user (link to a member account), signature, active, password
     */
    /**
     * Create or update an agent.
     *
     * $in: id, name, email, phone, role, department, queues[], max, status,
     *      user (link to a member account), signature, timezone, active, password
     *
     * When $id is given the call is a partial update: any key you leave out
     * keeps its stored value, so the console can flip a status or a queue
     * without having to send the whole record back.
     */
    public static function saveAgent(array $in, array $actor = []): array
    {
        $id       = (int) ($in['id'] ?? 0);
        $existing = $id ? DB::row('SELECT * FROM chat_agents WHERE id = ?', [$id]) : null;
        if ($id && !$existing) {
            return [false, 'That agent no longer exists.', null];
        }

        $pick = static function (string $key, $fallback, array $in, ?array $existing) {
            if (array_key_exists($key, $in)) {
                return $in[$key];
            }
            return $existing[$key] ?? $fallback;
        };

        $name  = trim((string) $pick('name', '', $in, $existing));
        $email = strtolower(trim((string) $pick('email', '', $in, $existing)));

        if ($name === '') {
            return [false, 'The agent needs a name.', null];
        }
        if (!is_email($email)) {
            return [false, 'Enter a valid email for the agent — it is how they sign in and get notified.', null];
        }
        $clash = DB::row('SELECT id FROM chat_agents WHERE email = ? AND id <> ?', [$email, $id]);
        if ($clash) {
            return [false, 'Another agent already uses that email address.', null];
        }

        $role = (string) $pick('role', 'agent', $in, $existing);
        if (!in_array($role, ['agent', 'supervisor', 'admin'], true)) {
            $role = 'agent';
        }
        $status = (string) $pick('status', 'offline', $in, $existing);
        if (!isset(self::AGENT_STATUSES[$status])) {
            $status = 'offline';
        }

        $fields = [
            'name'        => mb_substr($name, 0, 140),
            'email'       => mb_substr($email, 0, 190),
            'phone'       => mb_substr(trim((string) $pick('phone', '', $in, $existing)), 0, 40) ?: null,
            'agent_role'  => $role,
            'status'      => $status,
            'max_chats'   => max(1, min(20, (int) $pick('max', 3, $in, $existing))),
            'signature'   => mb_substr(trim((string) $pick('signature', '', $in, $existing)), 0, 190) ?: null,
            'timezone'    => mb_substr(trim((string) $pick('timezone', '', $in, $existing)), 0, 60) ?: null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        if (array_key_exists('active', $in)) {
            $fields['is_active'] = in_array((string) $in['active'], ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
        } elseif (!$id) {
            $fields['is_active'] = 1;
        }

        /* Link to a member account so the agent can sign in with normal credentials. */
        $linkedUser = (int) ($existing['user_id'] ?? 0);
        if (!empty($in['user'])) {
            $linkedUser = (int) $in['user'];
        } elseif (array_key_exists('email', $in) && $email !== '') {
            // Changing the email re-resolves the account it belongs to.
            $linkedUser = (int) (DB::value('SELECT id FROM users WHERE email = ?', [$email], 0) ?: 0);
        }
        if (!$linkedUser && $email !== '') {
            $linkedUser = (int) (DB::value('SELECT id FROM users WHERE email = ?', [$email], 0) ?: 0);
        }
        if ($linkedUser) {
            $fields['user_id'] = $linkedUser;
        }

        if ($id) {
            DB::update('chat_agents', $fields, 'id = ?', [$id]);
        } else {
            $id = DB::insert('chat_agents', $fields + ['created_at' => date('Y-m-d H:i:s')]);
        }

        /* Queue assignments: department_id is the primary queue, the join table
           carries the extras so one agent can cover several desks. */
        if (array_key_exists('queues', $in) || array_key_exists('department', $in)) {
            $queues = array_values(array_unique(array_filter(array_map('intval', (array) ($in['queues'] ?? [])))));
            $primary = (int) ($in['department'] ?? 0);
            if ($primary) {
                array_unshift($queues, $primary);
                $queues = array_values(array_unique($queues));
                DB::update('chat_agents', ['department_id' => $primary], 'id = ?', [$id]);
            } elseif ($id && !array_key_exists('department', $in)) {
                $primary = (int) ($existing['department_id'] ?? 0);
            }
            DB::delete('chat_agent_departments', 'agent_id = ?', [$id]);
            foreach ($queues as $q) {
                if ($q > 0) {
                    DB::insert('chat_agent_departments', ['agent_id' => $id, 'department_id' => $q]);
                }
            }
            if ($primary > 0 && !in_array($primary, $queues, true)) {
                DB::insert('chat_agent_departments', ['agent_id' => $id, 'department_id' => $primary]);
            }
        }

        /* Optionally create/refresh the sign-in account for a brand-new agent. */
        $password = (string) ($in['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                return [false, 'A temporary password needs at least 8 characters.', null];
            }
            $existingUser = (int) (DB::value('SELECT id FROM users WHERE email = ?', [$email], 0) ?: 0);
            if ($existingUser) {
                DB::update('users', ['role' => 'admin', 'password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$existingUser]);
                DB::update('chat_agents', ['user_id' => $existingUser], 'id = ?', [$id]);
            } else {
                $newUser = DB::insert('users', [
                    'name'          => $fields['name'],
                    'email'         => $fields['email'],
                    'phone'         => $fields['phone'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => 'admin',
                    'tier'          => 'platinum',
                    'status'        => 'Verified',
                    'kyc_verified'  => 1,
                ]);
                DB::update('chat_agents', ['user_id' => $newUser], 'id = ?', [$id]);
            }
        }

        audit((string) ($actor['name'] ?? 'admin'), ($existing ? 'Updated' : 'Created') . ' chat agent ' . $email, 'info');
        Repo::flush();

        return [true, $existing ? 'Agent updated.' : 'Agent created — share the console link with them.', ['id' => $id]];
    }

    public static function setAgentStatus(int $agentId, string $status, bool $touch = true): array
    {
        if (!isset(self::AGENT_STATUSES[$status])) {
            return [false, 'Unknown status.', null];
        }
        $agent = self::agent($agentId);
        if (!$agent) {
            return [false, 'Agent not found.', null];
        }
        DB::update('chat_agents', [
            'status'       => $status,
            'last_seen_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$agentId]);

        if ($touch) {
            audit($agent['email'], 'Chat status → ' . self::AGENT_STATUSES[$status], 'info');
        }

        // Coming online pulls the oldest waiting chat in their queues straight away.
        if ($status === 'online' || $status === 'away') {
            foreach (DB::all("SELECT id FROM chat_sessions WHERE status = 'queued' ORDER BY priority DESC, id LIMIT 5") as $s) {
                self::autoAssign((int) $s['id']);
            }
        }
        Repo::flush();
        return [true, 'You are ' . strtolower(self::AGENT_STATUSES[$status]) . '.', null];
    }

    public static function toggleAgent(int $id, bool $active, array $actor = []): array
    {
        $agent = self::agent($id);
        if (!$agent) {
            return [false, 'Agent not found.', null];
        }
        DB::update('chat_agents', [
            'is_active' => $active ? 1 : 0,
            'status'    => $active ? $agent['status'] : 'offline',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        audit((string) ($actor['name'] ?? 'admin'), ($active ? 'Enabled' : 'Disabled') . ' chat agent ' . $agent['email'], 'info');
        Repo::flush();
        return [true, $active ? 'Agent enabled.' : 'Agent disabled — their chats return to the queue.', null];
    }

    public static function deleteAgent(int $id, array $actor = []): array
    {
        $agent = self::agent($id);
        if (!$agent) {
            return [false, 'Agent not found.', null];
        }
        $live = (int) DB::value("SELECT COUNT(*) FROM chat_sessions WHERE agent_id = ? AND status = 'active'", [$id], 0);
        if ($live > 0) {
            return [false, 'Reassign their ' . $live . ' live chat(s) first.', null];
        }
        DB::delete('chat_agent_departments', 'agent_id = ?', [$id]);
        DB::delete('chat_agents', 'id = ?', [$id]);
        audit((string) ($actor['name'] ?? 'admin'), 'Deleted chat agent ' . $agent['email'], 'warn');
        Repo::flush();
        return [true, 'Agent removed.', null];
    }

    /* --------------------------------------------------------------- sessions */

    public static function nextRef(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $ref = 'C-' . date('ymd') . '-' . str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
            if (!DB::value('SELECT 1 FROM chat_sessions WHERE ref = ?', [$ref])) {
                return $ref;
            }
        }
        return 'C-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(2)), 0, 3);
    }

    /**
     * Start (or resume) a chat request.
     * $in: department, name, email, phone, subject, page, token, source
     */
    public static function start(array $in, ?int $userId): array
    {
        $cfg = self::settings();
        $user = $userId ? Repo::userRow($userId) : null;

        /* Resume an open session for this visitor rather than opening a new one. */
        $token = (string) ($in['token'] ?? '');
        if ($token === '' && !empty($_SESSION['chat_token'])) {
            $token = (string) $_SESSION['chat_token'];
        }
        $existing = null;
        if ($token !== '') {
            $existing = DB::row(
                "SELECT * FROM chat_sessions WHERE visitor_token = ? AND status IN ('queued','active') ORDER BY id DESC LIMIT 1",
                [$token]
            );
        } elseif ($userId) {
            $existing = DB::row(
                "SELECT * FROM chat_sessions WHERE visitor_id = ? AND status IN ('queued','active') ORDER BY id DESC LIMIT 1",
                [$userId]
            );
        }
        if ($existing) {
            $token = (string) $existing['visitor_token'];
            $_SESSION['chat_token'] = $token;
            return [true, 'Welcome back — resuming your chat.', self::visitorBundle($existing, $token)];
        }

        if (!$cfg['enabled']) {
            return [false, (string) $cfg['offline'], null];
        }
        $queue = (int) DB::value("SELECT COUNT(*) FROM chat_sessions WHERE status = 'queued'", [], 0);
        if ($queue >= $cfg['maxQueue']) {
            return [false, 'Our team is at capacity right now — please leave a message and we will email you back, or start a dispute from the help centre.', null];
        }

        $dept = self::resolveDepartment($in['department'] ?? null);
        if (!$dept) {
            return [false, 'Live chat is not configured yet. Please email us and we will respond.', null];
        }

        $name  = trim((string) ($in['name'] ?? '')) ?: (string) ($user['name'] ?? 'Guest');
        $email = strtolower(trim((string) ($in['email'] ?? ''))) ?: strtolower((string) ($user['email'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? '')) ?: (string) ($user['phone'] ?? '');
        if ($name === '') {
            $name = 'Guest';
        }
        if ($email !== '' && !is_email($email)) {
            return [false, 'That email address does not look right.', null];
        }

        $token  = $token !== '' ? $token : bin2hex(random_bytes(20));
        $now    = date('Y-m-d H:i:s');
        $ref    = self::nextRef();
        $subject = mb_substr(trim((string) ($in['subject'] ?? '')), 0, 200);
        $first  = mb_substr(trim((string) ($in['message'] ?? '')), 0, 4000);

        $id = DB::insert('chat_sessions', [
            'ref'            => $ref,
            'visitor_id'     => $userId,
            'visitor_name'   => mb_substr($name, 0, 140),
            'visitor_email'  => $email !== '' ? mb_substr($email, 0, 190) : null,
            'visitor_phone'  => $phone !== '' ? mb_substr($phone, 0, 40) : null,
            'visitor_token'  => $token,
            'department_id'  => $dept['id'],
            'status'         => 'queued',
            'priority'       => 'normal',
            'subject'        => $subject ?: ($user && Auth::isHost() ? 'Owner enquiry' : 'Guest enquiry'),
            'source'         => mb_substr((string) ($in['source'] ?? 'widget'), 0, 60),
            'page_url'       => mb_substr((string) ($in['page'] ?? ''), 0, 255) ?: null,
            'user_agent'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'ip'             => client_ip(),
            'last_message_at' => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        self::event($id, 'created', 'visitor', $userId, $name, 'Chat requested · ' . $dept['name']);
        self::message($id, 'system', (string) ($dept['welcome'] ?: $cfg['welcome']), null, 'Jollof Living');
        if ($first !== '') {
            self::message($id, 'visitor', $first, null, $name);
        }

        $_SESSION['chat_token'] = $token;
        $assigned = self::autoAssign($id);
        if (!$assigned) {
            // Nobody free: make sure the agents covering this queue know a
            // visitor is waiting rather than relying on them refreshing.
            foreach (self::availableAgents((int) $dept['id'], false) as $a) {
                if ($a['userId']) {
                    Repo::notify($a['userId'], 'Chat waiting · ' . $dept['name'],
                        ($name ?: 'A visitor') . ' · ' . mb_substr((string) $subject ?: 'New enquiry', 0, 100), 'chat');
                }
            }
        }
        $session = DB::row('SELECT * FROM chat_sessions WHERE id = ?', [$id]);

        return [true, $assigned
            ? 'Connected — you are chatting with ' . $assigned['name'] . '.'
            : 'You are in the queue — an agent will join shortly.', self::visitorBundle($session, $token)];
    }

    public static function session(string $ref): ?array
    {
        return DB::row('SELECT * FROM chat_sessions WHERE ref = ?', [trim($ref)]) ?: null;
    }

    /**
     * Session + everything the visitor (or agent) needs in one payload.
     *
     * $viewer = 'agent' keeps internal notes in the transcript; the default
     * visitor view hides them.
     */
    public static function visitorBundle(array $s, string $token, int $afterId = 0, string $viewer = 'visitor'): array
    {
        $agent = !empty($s['agent_id']) ? self::agent((int) $s['agent_id']) : null;
        $dept  = self::departmentById((int) ($s['department_id'] ?? 0));
        $cfg   = self::settings();
        return [
            'ref'        => (string) $s['ref'],
            'token'      => $token,
            'status'     => (string) $s['status'],
            'subject'    => (string) ($s['subject'] ?? ''),
            'department' => $dept['name'] ?? 'Support',
            'deptSlug'   => $dept['slug'] ?? '',
            'agent'      => $agent ? [
                'id'     => $agent['id'],
                'name'   => $agent['name'],
                'role'   => $agent['role'],
                'signature' => $agent['signature'],
                'status' => $agent['status'],
            ] : null,
            'position'   => (string) $s['status'] === 'queued' ? self::queuePosition((int) $s['id']) : 0,
            'messages'   => self::messages((int) $s['id'], $afterId, $viewer),
            'rating'     => $s['rating'] !== null ? (int) $s['rating'] : null,
            'ratingOn'   => (bool) $cfg['rating'],
            'agentTyping' => self::isTyping($s['agent_typing_at'] ?? null),
            'visitorTyping' => self::isTyping($s['visitor_typing_at'] ?? null),
            'created'    => (string) $s['created_at'],
            'wait'       => self::humanWait((int) ($s['wait_seconds'] ?? 0)),
            'hours'      => (string) $cfg['hours'],
            'offline'    => (string) $cfg['offline'],
        ];
    }

    /** The transcript. $viewer 'visitor' hides internal staff notes. */
    public static function messages(int $sessionId, int $afterId = 0, string $viewer = 'agent'): array
    {
        $sql = 'SELECT * FROM chat_messages WHERE session_id = ? AND id > ?';
        if ($viewer === 'visitor') {
            $sql .= " AND sender <> 'note'";
        }
        $sql .= ' ORDER BY id LIMIT 400';
        return array_map(static fn($m) => [
            'id'      => (int) $m['id'],
            'from'    => (string) $m['sender'],
            'agent'   => (int) ($m['agent_id'] ?? 0),
            'name'    => (string) ($m['author_name'] ?? ''),
            'body'    => (string) $m['body'],
            'meta'    => (string) ($m['meta'] ?? ''),
            'at'      => (string) $m['created_at'],
            'time'    => date('H:i', strtotime((string) $m['created_at'])),
            'day'     => date('D j M', strtotime((string) $m['created_at'])),
        ], DB::all($sql, [$sessionId, $afterId]));
    }

    /**
     * Append a line to the transcript.
     *
     * $sender is one of: visitor | agent | note (internal, hidden from the
     * visitor) | system. Internal notes deliberately do not touch the visitor's
     * unread counter or the "last message" preview.
     */
    private static function message(int $sessionId, string $sender, string $body, ?int $agentId, string $authorName): int
    {
        if (!in_array($sender, ['visitor', 'agent', 'note', 'system'], true)) {
            $sender = 'system';
        }
        $now = date('Y-m-d H:i:s');
        $id = DB::insert('chat_messages', [
            'session_id'  => $sessionId,
            'sender'      => $sender,
            'agent_id'    => $agentId,
            'author_name' => mb_substr($authorName, 0, 140),
            'body'        => $body,
            'created_at'  => $now,
        ]);
        if ($sender === 'visitor') {
            DB::run('UPDATE chat_sessions SET unread_agent = unread_agent + 1, last_message_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $sessionId]);
        } elseif ($sender === 'agent') {
            DB::run('UPDATE chat_sessions SET unread_visitor = unread_visitor + 1, last_message_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $sessionId]);
        } else {
            DB::update('chat_sessions', ['updated_at' => $now], 'id = ?', [$sessionId]);
        }
        return $id;
    }

    /** Post a message from either side. Returns [ok, message, data]. */
    public static function post(string $ref, string $sender, string $body, ?int $agentId = null, string $token = '', string $authorName = ''): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'That chat no longer exists.', null];
        }
        if ((string) $s['status'] === 'closed') {
            return [false, 'This chat has ended — start a new one and we will pick it up.', null];
        }
        $body = trim($body);
        if ($body === '') {
            return [false, 'Type a message first.', null];
        }
        if (mb_strlen($body) > 4000) {
            return [false, 'That message is too long — keep it under 4,000 characters.', null];
        }
        if ($sender === 'visitor' && !self::visitorOwns($s, $token, Auth::id())) {
            return [false, 'That chat is not yours.', 403];
        }

        /* An internal note is a staff-only line: agents can write them and the
           visitor never sees them in the transcript or the emailed copy. */
        $internal = $sender === 'note';
        if ($internal) {
            $sender = 'agent';
        }

        /* A visitor writing into the queue keeps it alive; an agent reply joins them. */
        if ($sender === 'visitor' && empty($s['agent_id'])) {
            $assigned = self::autoAssign((int) $s['id']);
            if ($assigned) {
                $s = self::session($ref) ?: $s;
            }
        }

        $agent = null;
        if ($agentId) {
            $agent = self::agent($agentId);
        }
        if ($sender === 'agent') {
            if (!$agent) {
                return [false, 'Agent not recognised.', 403];
            }
            if (empty($s['agent_id'])) {
                self::assign($ref, (int) $agent['id'], ['id' => (int) $agent['id'], 'name' => $agent['name']], 'took the chat');
                $s = self::session($ref) ?: $s;
            } elseif ((int) $s['agent_id'] !== (int) $agent['id'] && $agent['role'] === 'agent') {
                return [false, 'That chat belongs to '
                    . ((self::agent((int) $s['agent_id'])['name'] ?? null) ?: 'another agent') . '.', 403];
            }
        }

        $id = self::message((int) $s['id'], $internal ? 'note' : $sender, mb_substr($body, 0, 4000),
            $sender === 'agent' ? (int) $agent['id'] : null,
            $authorName !== '' ? $authorName : ($sender === 'agent' && $agent ? $agent['name'] : (string) ($s['visitor_name'] ?? 'Visitor')));

        $fields = ['updated_at' => date('Y-m-d H:i:s')];
        if ($sender === 'agent' && empty($s['first_response_at'])) {
            $fields['first_response_at'] = date('Y-m-d H:i:s');
            $fields['wait_seconds'] = max(0, time() - strtotime((string) $s['created_at']));
        }
        if ($sender === 'visitor') {
            $fields['agent_typing_at'] = null;
        } else {
            $fields['visitor_typing_at'] = null;
        }
        DB::update('chat_sessions', $fields, 'id = ?', [(int) $s['id']]);

        /* Notify the other side — but never ping a visitor about an internal note. */
        if ($internal) {
            // staff-only; nothing to send
        } elseif ($sender === 'agent') {
            self::notifyVisitor($s, $agent ? $agent['name'] : 'Support', $body);
        } else {
            $target = !empty($s['agent_id']) ? self::agent((int) $s['agent_id']) : null;
            $title = 'New chat message · ' . ($s['visitor_name'] ?: 'Visitor');
            $snippet = mb_substr($body, 0, 120);
            if ($target && $target['userId']) {
                Repo::notify($target['userId'], $title, $snippet, 'chat');
            }
            if ($target && ($target['status'] === 'offline' || $target['status'] === 'away')) {
                Mailer::send($target['email'], 'Chat waiting · ' . $s['ref'],
                    '<p><b>' . e((string) $s['visitor_name']) . '</b> wrote:</p><blockquote>' . e($snippet)
                    . '</blockquote><p><a href="' . e(url('agent.php?chat=' . urlencode((string) $s['ref']))) . '">Open the chat console</a></p>');
            } elseif (!$target) {
                // Queued with nobody on it: nudge every online agent in the queue.
                foreach (self::availableAgents((int) $s['department_id']) as $a) {
                    if ($a['userId']) {
                        Repo::notify($a['userId'], $title, $snippet, 'chat');
                    }
                }
            }
        }

        Repo::flush();
        return [true, '', ['id' => $id, 'time' => date('H:i'), 'session' => self::visitorBundle(self::session($ref) ?: $s, $token)]];
    }

    /**
     * Poll for new lines. Returns the session state plus any messages after $afterId.
     * $viewer: 'visitor' | 'agent'
     */
    public static function poll(string $ref, string $token, int $afterId, string $viewer, ?int $agentId = null): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'That chat no longer exists.', null];
        }
        if ($viewer === 'visitor' && !self::visitorOwns($s, $token, Auth::id())) {
            return [false, 'That chat is not yours.', 403];
        }
        if ($viewer === 'agent' && $agentId) {
            DB::run('UPDATE chat_messages SET read_flag = 1 WHERE session_id = ? AND sender = ?', [(int) $s['id'], 'visitor']);
            DB::update('chat_sessions', ['unread_agent' => 0], 'id = ?', [(int) $s['id']]);
            DB::update('chat_agents', ['last_seen_at' => date('Y-m-d H:i:s')], 'id = ?', [$agentId]);
        }
        if ($viewer === 'visitor') {
            DB::update('chat_sessions', ['unread_visitor' => 0], 'id = ?', [(int) $s['id']]);
        }

        $s = self::session($ref) ?: $s;
        if ((string) $s['status'] === 'queued') {
            self::autoAssign((int) $s['id']);
            $s = self::session($ref) ?: $s;
        }

        $bundle = self::visitorBundle($s, $token, $afterId, $viewer === 'agent' ? 'agent' : 'visitor');
        $bundle['ok'] = true;
        $bundle['agentTyping'] = self::isTyping($s['agent_typing_at'] ?? null);
        $bundle['visitorTyping'] = self::isTyping($s['visitor_typing_at'] ?? null);
        if ($viewer === 'agent') {
            $bundle['visitor'] = self::visitorProfile($s);
        }
        return [true, '', $bundle];
    }

    public static function visitorOwns(array $s, string $token, ?int $userId): bool
    {
        if (!empty($s['visitor_token']) && $token !== '' && hash_equals((string) $s['visitor_token'], $token)) {
            return true;
        }
        if ($userId !== null && !empty($s['visitor_id']) && (int) $s['visitor_id'] === $userId) {
            return true;
        }
        return Auth::isAdmin();
    }

    /** Typing indicator — an "at" timestamp that expires after a few seconds. */
    public static function typing(string $ref, string $who, array $actor = []): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'Chat not found.', null];
        }
        DB::update('chat_sessions', [$who === 'agent' ? 'agent_typing_at' : 'visitor_typing_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $s['id']]);
        return [true, '', null];
    }

    private static function isTyping(?string $at): bool
    {
        return $at !== null && $at !== '' && (time() - strtotime($at)) < 7;
    }

    /* -------------------------------------------------------------- routing */

    /**
     * Route a queued chat to the best available agent.
     * Returns the agent array, or null when nobody is free.
     */
    public static function autoAssign(int $sessionId): ?array
    {
        $s = DB::row('SELECT * FROM chat_sessions WHERE id = ?', [$sessionId]);
        if (!$s || (string) $s['status'] !== 'queued') {
            return null;
        }
        $dept = self::departmentById((int) ($s['department_id'] ?? 0));
        $routing = $dept['routing'] ?? (string) self::settings()['routing'];
        if ($routing === 'manual') {
            return null; // trust & safety is picked by hand
        }

        $candidates = self::availableAgents((int) ($s['department_id'] ?? 0));
        if (!$candidates) {
            return null;
        }
        if ($routing === 'round_robin') {
            usort($candidates, static function ($a, $b) {
                $x = self::lastAssigned((int) $a['id']);
                $y = self::lastAssigned((int) $b['id']);
                return $x <=> $y;
            });
        } else {
            usort($candidates, static fn($a, $b) => [$a['load'], self::lastAssigned((int) $a['id'])] <=> [$b['load'], self::lastAssigned((int) $b['id'])]);
        }
        $pick = $candidates[0];

        self::assign((string) $s['ref'], (int) $pick['id'], ['id' => 0, 'name' => 'Auto-routing'], 'auto-routed', true);
        return self::agent((int) $pick['id']);
    }

    private static function lastAssigned(int $agentId): int
    {
        $v = DB::value('SELECT last_assigned_at FROM chat_agents WHERE id = ?', [$agentId]);
        return $v ? (int) strtotime((string) $v) : 0;
    }

    /** Assign a chat to an agent (or return it to the queue with $agentId = null). */
    public static function assign(string $ref, ?int $agentId, array $actor, string $reason = '', bool $silent = false): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'Chat not found.', null];
        }
        $previous = !empty($s['agent_id']) ? self::agent((int) $s['agent_id']) : null;

        if (!$agentId) {
            DB::update('chat_sessions', [
                'agent_id' => null,
                'status'   => 'queued',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $s['id']]);
            $dept = self::departmentById((int) ($s['department_id'] ?? 0));
            self::event((int) $s['id'], 'unassigned', 'agent', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'System'),
                'Returned to the ' . (($dept['name'] ?? null) ?: 'support') . ' queue' . ($reason ? ' · ' . $reason : ''));
            Repo::flush();
            return [true, 'Returned to the queue.', null];
        }

        $agent = self::agent($agentId);
        if (!$agent || !$agent['active']) {
            return [false, 'That agent is not available.', null];
        }

        $now = date('Y-m-d H:i:s');
        $fields = [
            'agent_id'      => $agent['id'],
            'status'        => 'active',
            'agent_joined_at' => (string) $s['status'] === 'queued' ? $now : ($s['agent_joined_at'] ?: $now),
            'wait_seconds'  => (string) $s['status'] === 'queued' ? max(0, time() - strtotime((string) $s['created_at'])) : (int) $s['wait_seconds'],
            'updated_at'    => $now,
        ];
        if ($previous && $previous['id'] !== $agent['id']) {
            $fields['transferred_from'] = $previous['id'];
        }
        DB::update('chat_sessions', $fields, 'id = ?', [(int) $s['id']]);
        DB::update('chat_agents', ['last_assigned_at' => $now, 'last_seen_at' => $now], 'id = ?', [(int) $agent['id']]);

        $kind = $previous && $previous['id'] !== $agent['id'] ? 'transferred' : 'assigned';
        self::event((int) $s['id'], $kind, 'agent', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'System'),
            $kind === 'transferred'
                ? $previous['name'] . ' → ' . $agent['name'] . ($reason ? ' · ' . $reason : '')
                : $agent['name'] . ' joined the chat' . ($reason === 'auto-routed' ? ' (auto-routed)' : ''));

        if (!$silent || $previous === null) {
            if ($agent['userId']) {
                Repo::notify($agent['userId'], 'Chat ' . $s['ref'] . ' is yours',
                    ($s['visitor_name'] ?: 'A visitor') . ' · ' . mb_substr((string) $s['subject'], 0, 90), 'chat');
            }
        }

        Repo::flush();
        return [true, 'Assigned to ' . $agent['name'] . '.', ['agent' => $agent['name']]];
    }

    public static function claim(string $ref, array $agent): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'Chat not found.', null];
        }
        if (!empty($s['agent_id']) && (int) $s['agent_id'] !== (int) $agent['id']) {
            return [false, 'That chat is already with ' . (self::agent((int) $s['agent_id'])['name'] ?? 'another agent') . '.', null];
        }
        if ($agent['load'] >= $agent['max']) {
            return [false, 'You are at your chat limit (' . $agent['max'] . '). Close one or raise your limit.', null];
        }
        return self::assign($ref, (int) $agent['id'], ['id' => (int) $agent['id'], 'name' => $agent['name']], 'claimed the chat');
    }

    public static function transfer(string $ref, int $toAgentId, array $actor): array
    {
        $to = self::agent($toAgentId);
        if (!$to || !$to['active']) {
            return [false, 'Choose an active agent to transfer to.', null];
        }
        $s = self::session($ref);
        if (!$s) {
            return [false, 'Chat not found.', null];
        }
        if ((int) ($s['agent_id'] ?? 0) === $toAgentId) {
            return [false, $to['name'] . ' already has this chat.', null];
        }
        $result = self::assign($ref, $toAgentId, $actor, 'transferred by ' . ($actor['name'] ?? 'an agent'));
        if (!$result[0]) {
            return $result;
        }
        self::message((int) $s['id'], 'system',
            'You are now chatting with ' . $to['name'] . '.', null, 'Jollof Living');
        return [true, 'Chat transferred to ' . $to['name'] . '.', null];
    }

    public static function close(string $ref, string $by, string $note = '', array $actor = []): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'Chat not found.', null];
        }
        if ((string) $s['status'] === 'closed') {
            return [false, 'That chat is already closed.', null];
        }
        $now = date('Y-m-d H:i:s');
        DB::update('chat_sessions', [
            'status'     => 'closed',
            'closed_at'  => $now,
            'closed_by'  => mb_substr($by, 0, 140),
            'close_note' => mb_substr($note, 0, 255) ?: null,
            'updated_at' => $now,
        ], 'id = ?', [(int) $s['id']]);
        self::event((int) $s['id'], 'closed', $actor ? 'agent' : 'visitor', (int) ($actor['id'] ?? 0), $by,
            $note !== '' ? 'Closed · ' . $note : 'Chat closed');

        $cfg = self::settings();
        if ($cfg['transcript'] && !empty($s['visitor_email'])) {
            self::emailTranscript($s, $note);
        }
        self::message((int) $s['id'], 'system',
            'This chat has ended. Thank you for chatting with Jollof Living — you can start a new chat at any time.', null, 'Jollof Living');
        Repo::flush();
        return [true, 'Chat closed.', ['ref' => $s['ref']]];
    }

    public static function rate(string $ref, int $stars, string $comment, string $token = ''): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [false, 'Chat not found.', null];
        }
        if (!self::visitorOwns($s, $token, Auth::id())) {
            return [false, 'That chat is not yours.', 403];
        }
        if ($stars < 1 || $stars > 5) {
            return [false, 'Choose between one and five stars.', null];
        }
        DB::update('chat_sessions', [
            'rating'         => $stars,
            'rating_comment' => mb_substr(trim($comment), 0, 255) ?: null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $s['id']]);
        self::event((int) $s['id'], 'rated', 'visitor', null, (string) ($s['visitor_name'] ?? 'Visitor'),
            'Rated ' . $stars . '/5' . ($comment !== '' ? ' — ' . mb_substr($comment, 0, 120) : ''));
        if (!empty($s['agent_id'])) {
            $a = self::agent((int) $s['agent_id']);
            if ($a && $a['userId']) {
                Repo::notify($a['userId'], 'Chat ' . $s['ref'] . ' rated ' . $stars . '/5',
                    $comment !== '' ? mb_substr($comment, 0, 130) : 'Thanks for the feedback.', 'star');
            }
        }
        Repo::flush();
        return [true, 'Thank you — your feedback goes straight to the agent and their team.', ['rating' => $stars]];
    }

    /** Email the transcript when a chat closes (used as the "offline" follow-up too). */
    private static function emailTranscript(array $s, string $note = ''): void
    {
        $lines = self::messages((int) $s['id'], 0, 'visitor');
        $html = '<p>Hello ' . e((string) ($s['visitor_name'] ?: 'there')) . ',</p>'
            . '<p>Here is a copy of your Jollof Living chat <b>' . e((string) $s['ref']) . '</b>'
            . ($note !== '' ? ' (' . e($note) . ')' : '') . '.</p><div style="border-left:3px solid #c9a227;padding-left:12px">';
        foreach (array_slice($lines, 0, 80) as $m) {
            $who = $m['from'] === 'visitor' ? 'You' : ($m['from'] === 'agent' ? ($m['name'] ?: 'Agent') : 'Jollof Living');
            $html .= '<p><b>' . e($who) . '</b> <span style="color:#8d8574;font-size:12px">' . e($m['time']) . '</span><br>'
                . nl2br(e(mb_substr($m['body'], 0, 1500))) . '</p>';
        }
        $html .= '</div><p>Reply to this email if you need anything else — it opens a ticket with our team.</p>';
        Mailer::send((string) $s['visitor_email'], 'Your Jollof Living chat ' . $s['ref'], $html);
    }

    private static function notifyVisitor(array $s, string $agentName, string $body): void
    {
        $uid = (int) ($s['visitor_id'] ?? 0);
        if ($uid > 0) {
            Repo::notify($uid, 'New message from ' . $agentName, mb_substr($body, 0, 140), 'chat');
        }
    }

    /* ---------------------------------------------------------------- console */

    /**
     * Everything the agent console needs in one call.
     *
     * @param array $agent the chat_agents row (shaped)
     */
    public static function consoleState(array $agent, bool $isAdmin = false, int $limit = 40): array
    {
        $isSupervisor = $isAdmin || in_array($agent['role'], ['supervisor', 'admin'], true);
        $deptIds = $agent['queues'];

        $where = '';
        $args = [];
        if (!$isSupervisor && $deptIds) {
            $where = ' AND (s.department_id IN (' . implode(',', array_map('intval', $deptIds)) . ') OR s.department_id IS NULL)';
        }

        $waiting = DB::all(
            "SELECT s.*, d.name AS department_name FROM chat_sessions s
          LEFT JOIN chat_departments d ON d.id = s.department_id
              WHERE s.status = 'queued' $where
           ORDER BY " . self::rankCase('s.priority', ['urgent', 'high', 'normal']) . ", s.id LIMIT " . max(1, $limit),
            $args
        );

        $mine = DB::all(
            "SELECT s.*, d.name AS department_name FROM chat_sessions s
          LEFT JOIN chat_departments d ON d.id = s.department_id
              WHERE s.agent_id = ? AND s.status = 'active'
           ORDER BY s.last_message_at DESC, s.id DESC LIMIT 30",
            [(int) $agent['id']]
        );

        $team = [];
        if ($isSupervisor) {
            $team = DB::all(
                "SELECT s.*, d.name AS department_name, a.name AS agent_name FROM chat_sessions s
              LEFT JOIN chat_departments d ON d.id = s.department_id
              LEFT JOIN chat_agents a ON a.id = s.agent_id
                  WHERE s.status = 'active' AND (s.agent_id IS NULL OR s.agent_id <> ?)
               ORDER BY s.last_message_at DESC LIMIT 40",
                [(int) $agent['id']]
            );
        }

        $recent = DB::all(
            "SELECT s.*, d.name AS department_name FROM chat_sessions s
          LEFT JOIN chat_departments d ON d.id = s.department_id
              WHERE s.agent_id = ? AND s.status = 'closed'
           ORDER BY s.closed_at DESC LIMIT 15",
            [(int) $agent['id']]
        );

        return [
            'me'        => $agent,
            'waiting'   => array_map(static fn($s) => self::shapeSession($s), $waiting),
            'mine'      => array_map(static fn($s) => self::shapeSession($s), $mine),
            'team'      => array_map(static fn($s) => self::shapeSession($s), $team),
            'recent'    => array_map(static fn($s) => self::shapeSession($s), $recent),
            'agents'    => self::agents(true),
            'departments' => self::departments(),
            'canned'    => self::canned((int) $agent['id'], null),
            'metrics'   => self::metrics(),
            'limits'    => ['max' => $agent['max'], 'load' => count($mine)],
            'isSupervisor' => $isSupervisor,
        ];
    }

    public static function shapeSession(array $s): array
    {
        $agent = !empty($s['agent_id']) ? self::agent((int) $s['agent_id']) : null;
        $wait = (int) ($s['wait_seconds'] ?? 0);
        if ((string) $s['status'] === 'queued') {
            $secs = max(0, time() - strtotime((string) $s['created_at']));
            $wait = max($wait, $secs);
        }
        return [
            'ref'      => (string) $s['ref'],
            'status'   => (string) $s['status'],
            'subject'  => (string) ($s['subject'] ?? ''),
            'dept'     => (string) ($s['department_name'] ?? 'Support'),
            'deptId'   => (int) ($s['department_id'] ?? 0),
            'agent'    => (string) ($s['agent_name'] ?? ($agent['name'] ?? '')),
            'agentId'  => (int) ($s['agent_id'] ?? 0),
            'visitor'  => (string) ($s['visitor_name'] ?: 'Guest'),
            'email'    => (string) ($s['visitor_email'] ?? ''),
            'userId'   => (int) ($s['visitor_id'] ?? 0),
            'unread'   => (int) ($s['unread_agent'] ?? 0),
            'priority' => (string) ($s['priority'] ?? 'normal'),
            'wait'     => self::humanWait($wait),
            'waitSecs' => $wait,
            'rating'   => $s['rating'] !== null ? (int) $s['rating'] : null,
            'opened'   => (string) $s['created_at'],
            'openedAgo' => self::humanWait(max(1, time() - strtotime((string) $s['created_at']))),
            'last'     => (string) ($s['last_message_at'] ?? ''),
            'page'     => (string) ($s['page_url'] ?? ''),
            'source'   => (string) ($s['source'] ?? ''),
            'tags'     => (string) ($s['tags'] ?? ''),
        ];
    }

    /** The last few lines of a chat, for the list preview. */
    public static function preview(string $ref, int $limit = 1): array
    {
        $s = self::session($ref);
        if (!$s) {
            return [];
        }
        $rows = DB::all('SELECT * FROM chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [(int) $s['id']]);
        return array_reverse(array_map(static fn($m) => [
            'from' => (string) $m['sender'],
            'name' => (string) ($m['author_name'] ?? ''),
            'body' => mb_substr((string) $m['body'], 0, 180),
            'time' => date('H:i', strtotime((string) $m['created_at'])),
        ], $rows));
    }

    /** Full case file for the agent's right-hand panel. */
    public static function visitorProfile(array $s): array
    {
        $out = [
            'name'     => (string) ($s['visitor_name'] ?: 'Guest'),
            'email'    => (string) ($s['visitor_email'] ?? ''),
            'phone'    => (string) ($s['visitor_phone'] ?? ''),
            'signedIn' => !empty($s['visitor_id']),
            'page'     => (string) ($s['page_url'] ?? ''),
            'source'   => (string) ($s['source'] ?? 'widget'),
            'ip'       => (string) ($s['ip'] ?? ''),
            'agent'    => (string) ($s['user_agent'] ?? ''),
            'member'   => null,
            'bookings' => [],
            'disputes' => [],
            'history'  => [],
        ];
        $uid = (int) ($s['visitor_id'] ?? 0);
        if ($uid) {
            $u = Repo::userRow($uid);
            if ($u) {
                $out['member'] = [
                    'id'      => (int) $u['id'],
                    'name'    => (string) $u['name'],
                    'tier'    => ucfirst((string) $u['tier']),
                    'points'  => (int) $u['points'],
                    'status'  => (string) $u['status'],
                    'host'    => (int) $u['is_host'] === 1,
                    'city'    => (string) ($u['city'] ?? ''),
                    'since'   => date('M Y', strtotime((string) $u['created_at'])),
                ];
            }
            foreach (DB::all(
                'SELECT b.*, p.name AS property_name FROM bookings b LEFT JOIN properties p ON p.id = b.property_id
                  WHERE b.user_id = ? ORDER BY b.id DESC LIMIT 3',
                [$uid]
            ) as $b) {
                $out['bookings'][] = [
                    'ref'    => (string) $b['ref'],
                    'prop'   => (string) ($b['property_name'] ?? ''),
                    'dates'  => ((string) $b['checkin']) . ' → ' . ((string) $b['checkout']),
                    'status' => (string) $b['status'],
                    'total'  => money((int) $b['total']),
                ];
            }
            foreach (DB::all('SELECT ref, subject, status FROM disputes WHERE user_id = ? ORDER BY id DESC LIMIT 3', [$uid]) as $d) {
                $out['disputes'][] = ['ref' => (string) $d['ref'], 'subject' => (string) $d['subject'], 'status' => (string) $d['status']];
            }
        }
        foreach (DB::all(
            "SELECT ref, status, created_at, rating FROM chat_sessions
              WHERE (visitor_id = ? AND ? > 0) OR (visitor_email = ? AND ? <> '')
           ORDER BY id DESC LIMIT 5",
            [$uid, $uid, (string) $s['visitor_email'], (string) $s['visitor_email']]
        ) as $h) {
            $out['history'][] = [
                'ref'     => (string) $h['ref'],
                'status'  => (string) $h['status'],
                'when'    => date('M j', strtotime((string) $h['created_at'])),
                'rating'  => $h['rating'] !== null ? (int) $h['rating'] : null,
            ];
        }
        return $out;
    }

    /* ------------------------------------------------------------- analytics */

    public static function metrics(): array
    {
        $today = date('Y-m-d 00:00:00');
        $since = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $queued = (int) DB::value("SELECT COUNT(*) FROM chat_sessions WHERE status = 'queued'", [], 0);
        $active = (int) DB::value("SELECT COUNT(*) FROM chat_sessions WHERE status = 'active'", [], 0);
        $online = (int) DB::value("SELECT COUNT(*) FROM chat_agents WHERE is_active = 1 AND status IN ('online','away')", [], 0);
        $todayCount = (int) DB::value('SELECT COUNT(*) FROM chat_sessions WHERE created_at >= ?', [$today], 0);

        /* Response-time averages are computed in PHP from one row set, which
           keeps them identical on MySQL and the SQLite development driver. */
        $waits = [];
        $firsts = [];
        $durations = [];
        foreach (DB::all(
            'SELECT created_at, first_response_at, closed_at, wait_seconds, status
               FROM chat_sessions WHERE created_at > ? LIMIT 5000',
            [$since]
        ) as $r) {
            $created = strtotime((string) $r['created_at']) ?: 0;
            if ($created <= 0) {
                continue;
            }
            if ((int) ($r['wait_seconds'] ?? 0) > 0) {
                $waits[] = (int) $r['wait_seconds'];
            }
            if (!empty($r['first_response_at'])) {
                $firsts[] = max(0, (strtotime((string) $r['first_response_at']) ?: $created) - $created);
            }
            if (!empty($r['closed_at'])) {
                $durations[] = max(0, (strtotime((string) $r['closed_at']) ?: $created) - $created);
            }
        }
        $avgOf = static fn(array $list): int => $list ? (int) round(array_sum($list) / count($list)) : 0;
        $avgWait = $avgOf($waits);
        $avgFirst = $avgOf($firsts);
        $avgDuration = $avgOf($durations);
        $rating = (float) DB::value('SELECT COALESCE(AVG(rating),0) FROM chat_sessions WHERE rating IS NOT NULL', [], 0);
        $rated  = (int) DB::value('SELECT COUNT(*) FROM chat_sessions WHERE rating IS NOT NULL', [], 0);
        $closed = (int) DB::value("SELECT COUNT(*) FROM chat_sessions WHERE status = 'closed'", [], 0);
        $abandoned = (int) DB::value("SELECT COUNT(*) FROM chat_sessions WHERE status = 'abandoned'", [], 0);

        $byDept = DB::all(
            "SELECT d.name, COUNT(s.id) AS n
               FROM chat_departments d LEFT JOIN chat_sessions s ON s.department_id = d.id
              GROUP BY d.id, d.name ORDER BY n DESC"
        );

        return [
            'queued'     => $queued,
            'active'     => $active,
            'online'     => $online,
            'today'      => $todayCount,
            'closed'     => $closed,
            'abandoned'  => $abandoned,
            'avgWait'    => self::humanWait((int) round($avgWait)),
            'avgWaitSecs' => (int) round($avgWait),
            'avgFirst'   => self::humanWait((int) round($avgFirst)),
            'avgFirstSecs' => (int) round($avgFirst),
            'avgDuration' => self::humanWait((int) round($avgDuration)),
            'rating'     => round($rating, 1),
            'rated'      => $rated,
            'byDept'     => array_map(static fn($r) => ['name' => (string) $r['name'], 'n' => (int) $r['n']], $byDept),
            'live'       => array_map(static fn($s) => self::shapeSession($s), DB::all(
                "SELECT s.*, d.name AS department_name, a.name AS agent_name FROM chat_sessions s
              LEFT JOIN chat_departments d ON d.id = s.department_id
              LEFT JOIN chat_agents a ON a.id = s.agent_id
                  WHERE s.status IN ('queued','active')
               ORDER BY " . self::rankCase('s.status', ['queued', 'active']) . ", s.id DESC LIMIT 25"
            )),
        ];
    }

    public static function queuePosition(int $sessionId): int
    {
        $row = DB::row('SELECT id, created_at, priority FROM chat_sessions WHERE id = ?', [$sessionId]);
        if (!$row) {
            return 0;
        }
        $n = (int) DB::value(
            "SELECT COUNT(*) FROM chat_sessions WHERE status = 'queued' AND id < ? AND id <> ?",
            [(int) $row['id'], $sessionId], 0
        );
        return $n + 1;
    }

    /* ------------------------------------------------------------ canned text */

    public static function canned(?int $agentId = null, ?int $deptId = null): array
    {
        $args = [];
        $sql = "SELECT * FROM chat_canned WHERE is_active = 1 AND (scope = 'global'";
        if ($agentId) {
            $sql .= " OR (scope = 'personal' AND agent_id = ?)";
            $args[] = $agentId;
        }
        if ($deptId) {
            $sql .= " OR (scope = 'department' AND department_id = ?)";
            $args[] = $deptId;
        }
        $sql .= ') ORDER BY sort_order, id LIMIT 60';
        return array_map(static fn($c) => [
            'id'       => (int) $c['id'],
            'shortcut' => (string) $c['shortcut'],
            'title'    => (string) $c['title'],
            'body'     => (string) $c['body'],
            'scope'    => (string) $c['scope'],
            'deptId'   => (int) ($c['department_id'] ?? 0),
            'agentId'  => (int) ($c['agent_id'] ?? 0),
            'uses'     => (int) $c['uses'],
            'active'   => (int) $c['is_active'] === 1,
        ], DB::all($sql, $args));
    }

    public static function allCanned(): array
    {
        return array_map(static fn($c) => [
            'id'       => (int) $c['id'],
            'shortcut' => (string) $c['shortcut'],
            'title'    => (string) $c['title'],
            'body'     => (string) $c['body'],
            'scope'    => (string) $c['scope'],
            'deptId'   => (int) ($c['department_id'] ?? 0),
            'agentId'  => (int) ($c['agent_id'] ?? 0),
            'uses'     => (int) $c['uses'],
            'active'   => (int) $c['is_active'] === 1,
            'dept'     => (string) (DB::value('SELECT name FROM chat_departments WHERE id = ?', [(int) ($c['department_id'] ?? 0)]) ?: 'All queues'),
        ], DB::all('SELECT * FROM chat_canned ORDER BY sort_order, id'));
    }

    public static function saveCanned(array $in, array $actor = []): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        $body  = trim((string) ($in['body'] ?? ''));
        if ($title === '' || $body === '') {
            return [false, 'A canned reply needs a title and a message.', null];
        }
        $id = (int) ($in['id'] ?? 0);
        $shortcut = trim((string) ($in['shortcut'] ?? '')) ?: '/' . slugify($title);
        $fields = [
            'shortcut'      => mb_substr(ltrim($shortcut, '/'), 0, 38),
            'title'         => mb_substr($title, 0, 140),
            'body'          => mb_substr($body, 0, 4000),
            'scope'         => in_array((string) ($in['scope'] ?? ''), ['global', 'department', 'personal'], true) ? (string) $in['scope'] : 'global',
            'department_id' => (int) ($in['department'] ?? 0) ?: null,
            'agent_id'      => (int) ($in['agent'] ?? 0) ?: null,
            'sort_order'    => (int) ($in['sort'] ?? 0),
            'is_active'     => !array_key_exists('active', $in) || in_array((string) $in['active'], ['1', 'true', 'on'], true) ? 1 : 0,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            DB::update('chat_canned', $fields, 'id = ?', [$id]);
        } else {
            $id = DB::insert('chat_canned', $fields + ['created_at' => date('Y-m-d H:i:s')]);
        }
        audit((string) ($actor['name'] ?? 'admin'), ($id ? 'Updated' : 'Created') . ' canned reply ' . $title, 'info');
        Repo::flush();
        return [true, 'Canned reply saved.', ['id' => $id]];
    }

    public static function deleteCanned(int $id, array $actor = []): array
    {
        $c = DB::row('SELECT * FROM chat_canned WHERE id = ?', [$id]);
        if (!$c) {
            return [false, 'That canned reply no longer exists.', null];
        }
        DB::delete('chat_canned', 'id = ?', [$id]);
        audit((string) ($actor['name'] ?? 'admin'), 'Deleted canned reply ' . $c['title'], 'warn');
        Repo::flush();
        return [true, 'Canned reply deleted.', null];
    }

    /* ---------------------------------------------------------------- helpers */

    /** Sweep: abandon chats nobody answered and auto-close stale ones. */
    public static function sweep(): void
    {
        $cfg = self::settings();
        $cut = date('Y-m-d H:i:s', time() - $cfg['autoClose'] * 60);
        DB::run(
            "UPDATE chat_sessions SET status = 'abandoned', updated_at = ?
              WHERE status = 'queued' AND created_at < ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', time() - 3600 * 6)]
        );
        $stale = DB::all(
            "SELECT * FROM chat_sessions WHERE status = 'active' AND last_message_at IS NOT NULL AND last_message_at < ? LIMIT 20",
            [$cut]
        );
        foreach ($stale as $s) {
            self::close((string) $s['ref'], 'Auto-close', 'No activity for ' . $cfg['autoClose'] . ' minutes');
        }
    }

    /**
     * Portable ranking for "these values first" ordering.
     *
     * The codebase runs on MySQL in production and SQLite for local
     * development, and FIELD() only exists on one of them.
     */
    private static function rankCase(string $column, array $order): string
    {
        $sql = 'CASE ' . $column;
        foreach (array_values($order) as $i => $value) {
            $sql .= " WHEN '" . str_replace("'", '', (string) $value) . "' THEN $i";
        }
        return $sql . ' ELSE ' . count($order) . ' END';
    }

    public static function humanWait(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'now';
        }
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return (int) floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }
        $h = (int) floor($seconds / 3600);
        return $h . 'h ' . (int) floor(($seconds % 3600) / 60) . 'm';
    }

    private static function event(int $sessionId, string $kind, string $actorType, ?int $actorId, string $actorName, string $detail): int
    {
        if ($sessionId <= 0) {
            return 0;
        }
        return DB::insert('chat_events', [
            'session_id' => $sessionId,
            'kind'       => $kind,
            'actor_type' => $actorType,
            'actor_id'   => $actorId ?: null,
            'actor_name' => mb_substr($actorName, 0, 140),
            'detail'     => mb_substr($detail, 0, 255),
        ]);
    }

    /** Audit trail for a chat (assignments, transfers, closes). */
    public static function timeline(int $sessionId): array
    {
        return array_map(static fn($e) => [
            'kind'   => (string) $e['kind'],
            'who'    => (string) ($e['actor_name'] ?? 'System'),
            'detail' => (string) ($e['detail'] ?? ''),
            'when'   => date('H:i', strtotime((string) $e['created_at'])),
            'day'    => date('j M', strtotime((string) $e['created_at'])),
        ], DB::all('SELECT * FROM chat_events WHERE session_id = ? ORDER BY id', [$sessionId]));
    }
}
