<?php
/**
 * Jollof Living — Dispute Resolution Centre.
 *
 * Backs the dispute panel on help.php and the mediation queue in the back
 * office. Everything lives in MySQL: a dispute is a row, its story is the
 * dispute_events timeline, and a resolution actually touches the booking
 * (escrow + payments) so the outcome is more than a message on a screen.
 *
 * Tables: disputes, dispute_events, dispute_categories
 *   (created by install/migrate.php and install/schema/*.sql)
 */
declare(strict_types=1);

// Defence in depth: these files are libraries, never entry points.
// .htaccess blocks the folder as well, but a mis-configured host must not leak them.
if (!defined('JL_ROOT')) {
    http_response_code(404);
    exit;
}

final class DisputeService
{
    /* ------------------------------------------------------------ vocabulary */

    /** status => [human label, pill level, short hint] */
    public const STATUSES = [
        'submitted'   => ['Submitted',        'info', 'Received — waiting for a mediator to pick it up'],
        'under_review' => ['Under review',    'gold', 'A mediator is reviewing the facts'],
        'evidence'    => ['Evidence needed',  'warn', 'Both sides are sharing photos, receipts or messages'],
        'mediation'   => ['In mediation',     'gold', 'A mediator is negotiating an outcome'],
        'escalated'   => ['Escalated',        'warn', 'With the senior trust & safety team'],
        'resolved'    => ['Resolved',         'ok',   'A decision has been issued'],
        'rejected'    => ['Closed · no action', 'bad', 'Reviewed and declined with reasons'],
        'withdrawn'   => ['Withdrawn',        'info', 'Closed by the person who raised it'],
    ];

    /** outcome key => human label */
    public const OUTCOMES = [
        'full_refund'  => 'Full refund',
        'partial_refund' => 'Partial refund',
        'rebooking'    => 'Rebooking / credit',
        'compensation' => 'Goodwill compensation',
        'repair'       => 'Host to fix / replace',
        'no_action'    => 'No action — evidence does not support it',
        'warning'      => 'Warning issued to the other party',
        'split'        => 'Split decision',
    ];

    /** what the person raising it wants */
    public const WANTS = [
        'refund'     => 'A refund',
        'partial'    => 'A partial refund',
        'rebooking'  => 'To be rebooked elsewhere',
        'repair'     => 'The issue put right',
        'apology'    => 'An apology and a warning',
        'other'      => 'Something else',
    ];

    /** statuses that mean the case is still live */
    public const OPEN_STATUSES = ['submitted', 'under_review', 'evidence', 'mediation', 'escalated'];

    /** allowed moves: from-status => [to-status, …] for staff */
    private const TRANSITIONS = [
        'submitted'    => ['under_review', 'evidence', 'mediation', 'escalated', 'rejected'],
        'under_review' => ['evidence', 'mediation', 'escalated', 'resolved', 'rejected'],
        'evidence'     => ['under_review', 'mediation', 'escalated', 'resolved', 'rejected'],
        'mediation'    => ['evidence', 'escalated', 'resolved', 'rejected'],
        'escalated'    => ['mediation', 'resolved', 'rejected'],
        'resolved'     => ['under_review'],
        'rejected'     => ['under_review'],
        'withdrawn'    => ['under_review'],
    ];

    /* ------------------------------------------------------------ categories */

    /** The seed list, also used by the installer when the table is empty. */
    public static function defaultCategories(): array
    {
        return [
            ['slug' => 'booking-issue',    'name' => 'Booking or date problem',     'description' => 'Wrong dates, cancelled stay, host could not accommodate you.', 'sla_hours' => 24, 'icon' => 'calendar', 'sort_order' => 0, 'is_active' => 1],
            ['slug' => 'refund-request',   'name' => 'Refund request',              'description' => 'You cancelled, or the stay did not match the listing.',        'sla_hours' => 48, 'icon' => 'wallet',   'sort_order' => 1, 'is_active' => 1],
            ['slug' => 'property-quality', 'name' => 'Property not as described',   'description' => 'Condition, cleanliness, amenities or photos differ.',          'sla_hours' => 48, 'icon' => 'home',     'sort_order' => 2, 'is_active' => 1],
            ['slug' => 'payment-billing',  'name' => 'Payment, invoice or billing', 'description' => 'Charged twice, unexpected fees, tax or invoice queries.',      'sla_hours' => 24, 'icon' => 'wallet',   'sort_order' => 3, 'is_active' => 1],
            ['slug' => 'host-conduct',     'name' => 'Host conduct',                'description' => 'Communication, access, privacy or policy breaches.',           'sla_hours' => 24, 'icon' => 'building', 'sort_order' => 4, 'is_active' => 1],
            ['slug' => 'guest-conduct',    'name' => 'Guest conduct (hosts)',       'description' => 'Property damage, rule breaches or unpaid balances.',           'sla_hours' => 48, 'icon' => 'users',    'sort_order' => 5, 'is_active' => 1],
            ['slug' => 'safety-security',  'name' => 'Safety or security',          'description' => 'A safety incident, lock or security concern.',                 'sla_hours' => 4,  'icon' => 'shield',   'sort_order' => 6, 'is_active' => 1],
            ['slug' => 'other',            'name' => 'Something else',              'description' => 'Anything that does not fit the categories above.',             'sla_hours' => 72, 'icon' => 'scale',    'sort_order' => 7, 'is_active' => 1],
        ];
    }

    /** Categories for the picker, straight from the database. */
    public static function categories(bool $activeOnly = true): array
    {
        try {
            $rows = DB::all(
                'SELECT * FROM dispute_categories ' . ($activeOnly ? 'WHERE is_active = 1 ' : '') . 'ORDER BY sort_order, id'
            );
        } catch (Throwable $e) {
            return self::defaultCategories();
        }
        if (!$rows) {
            return self::defaultCategories();
        }
        return array_map(static fn($c) => [
            'id'          => (int) $c['id'],
            'slug'        => (string) $c['slug'],
            'name'        => (string) $c['name'],
            'description' => (string) ($c['description'] ?? ''),
            'sla'         => (int) $c['sla_hours'],
            'ico'         => (string) ($c['icon'] ?? 'scale'),
        ], $rows);
    }

    private static function category(int $id): ?array
    {
        foreach (self::categories(false) as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        return null;
    }

    /* ---------------------------------------------------------------- raises */

    /**
     * Open a dispute. Returns [ok, message, data].
     *
     * $in keys: booking (id or ref), category (id or slug), subject, description,
     *           want, amount, against, name, email, phone, priority
     */
    public static function open(array $in, ?int $userId): array
    {
        $user = $userId ? Repo::userRow($userId) : null;

        $name  = trim((string) ($in['name'] ?? '')) ?: (string) ($user['name'] ?? '');
        $email = strtolower(trim((string) ($in['email'] ?? ''))) ?: strtolower((string) ($user['email'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? '')) ?: (string) ($user['phone'] ?? '');

        $subject = trim((string) ($in['subject'] ?? ''));
        $body    = trim((string) ($in['description'] ?? ''));

        if ($subject === '') {
            return [false, 'Please give your dispute a short title.', null];
        }
        if (mb_strlen($body) < 20) {
            return [false, 'Please describe what happened (at least 20 characters) so a mediator can act on it.', null];
        }
        if ($userId === null && !is_email($email)) {
            return [false, 'Enter the email address we should reply to.', null];
        }

        /* ---- booking link: only a booking the person actually holds ---- */
        $booking = null;
        $bookingKey = $in['booking'] ?? '';
        if ($bookingKey !== '' && $bookingKey !== null && $bookingKey !== 0) {
            if (is_numeric($bookingKey)) {
                $booking = DB::row(
                    'SELECT b.*, p.host_id, p.name AS property_name FROM bookings b
                       JOIN properties p ON p.id = b.property_id WHERE b.id = ?',
                    [(int) $bookingKey]
                );
            } else {
                $booking = DB::row(
                    'SELECT b.*, p.host_id, p.name AS property_name FROM bookings b
                       JOIN properties p ON p.id = b.property_id WHERE b.ref = ?',
                    [(string) $bookingKey]
                );
            }
            if (!$booking) {
                return [false, 'We could not find that reservation.', null];
            }
            if ($userId !== null && (int) $booking['user_id'] !== $userId) {
                return [false, 'That reservation is not on your account.', null];
            }
            if ($userId === null && strtolower((string) $booking['guest_email']) !== $email) {
                return [false, 'That reservation is registered to a different email address.', null];
            }
        }

        /* ---- category ---- */
        $catId = 0;
        $catKey = $in['category'] ?? '';
        foreach (self::categories(false) as $c) {
            if ((string) $c['id'] === (string) $catKey || $c['slug'] === (string) $catKey) {
                $catId = (int) $c['id'];
                break;
            }
        }
        if (!$catId) {
            $catId = (int) (self::categories()[0]['id'] ?? 0);
        }
        $cat = self::category($catId);
        $sla = (int) ($cat['sla'] ?? 48);

        $against = (string) ($in['against'] ?? 'host');
        if (!in_array($against, ['host', 'platform', 'guest'], true)) {
            $against = 'host';
        }
        $want = (string) ($in['want'] ?? 'refund');
        if (!isset(self::WANTS[$want])) {
            $want = 'refund';
        }

        $ref = self::nextRef();
        $token = bin2hex(random_bytes(20));
        $now = date('Y-m-d H:i:s');

        $id = DB::insert('disputes', [
            'ref'              => $ref,
            'user_id'          => $userId,
            'booking_id'       => $booking ? (int) $booking['id'] : null,
            'booking_ref'      => $booking ? (string) $booking['ref'] : null,
            'property_id'      => $booking ? (int) $booking['property_id'] : null,
            'category_id'      => $catId ?: null,
            'counterparty_id'  => $booking && (int) ($booking['host_id'] ?? 0) > 0 ? (int) $booking['host_id'] : null,
            'against'          => $against,
            'subject'          => mb_substr($subject, 0, 200),
            'description'      => mb_substr($body, 0, 8000),
            'desired_outcome'  => $want,
            'amount_claimed'   => max(0, (int) ($in['amount'] ?? 0)),
            'currency'         => active_currency(),
            'status'           => 'submitted',
            'priority'         => (string) ($cat['slug'] ?? '') === 'safety-security' ? 'urgent' : 'normal',
            'evidence_deadline'=> date('Y-m-d H:i:s', strtotime($now) + 48 * 3600),
            'sla_due_at'       => date('Y-m-d H:i:s', strtotime($now) + $sla * 3600),
            'contact_name'     => mb_substr($name, 0, 120),
            'contact_email'    => mb_substr($email, 0, 190),
            'contact_phone'    => mb_substr($phone, 0, 40) ?: null,
            'access_token'     => $token,
            'ip'               => client_ip(),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        self::event($id, 'opened', 'guest', $userId, $name,
            'Dispute raised' . ($booking ? ' against reservation ' . $booking['ref'] : '')
            . ' · wants: ' . (self::WANTS[$want] ?? 'a refund'),
            '', '', 'all');

        /* The timeline starts with the story itself so a mediator reads it in context. */
        self::event($id, 'message', 'guest', $userId, $name, mb_substr($body, 0, 4000), '', '', 'all');

        if ($booking) {
            DB::insert('booking_events', [
                'booking_id' => (int) $booking['id'],
                'event'      => 'dispute_opened',
                'detail'     => $ref . ' — ' . mb_substr($subject, 0, 120),
            ]);
        }

        Repo::notify(
            $userId ?: 0,
            'Dispute ' . $ref . ' opened',
            'A mediator will review "' . mb_substr($subject, 0, 80) . '" within ' . $sla . ' hours.',
            'scale'
        );
        self::emailStakeholders($id, 'We have your dispute ' . $ref,
            '<p>Hello ' . e($name) . ',</p><p>We have logged dispute <b>' . e($ref) . '</b> about “'
            . e(mb_substr($subject, 0, 120)) . '”.</p><p>A mediator reviews it within '
            . (int) $sla . ' hours; you can follow the case, add evidence and reply at any time from the help centre using your reference.</p>');

        audit($email ?: 'guest', 'Opened dispute ' . $ref, 'warn');

        return [true, 'Dispute ' . $ref . ' opened — a mediator will be in touch within ' . $sla . ' hours.', [
            'ref'   => $ref,
            'token' => $token,
            'sla'   => $sla,
        ]];
    }

    /* ------------------------------------------------------------------ read */

    public static function find(string $ref): ?array
    {
        $d = DB::row('SELECT * FROM disputes WHERE ref = ?', [trim($ref)]);
        return $d ?: null;
    }

    /** May this viewer see the case? Returns a role: guest|host|staff|null. */
    public static function roleFor(array $d, ?int $userId, string $token = '', string $email = '', bool $isStaff = false): ?string
    {
        if ($isStaff) {
            return 'staff';
        }
        if ($userId !== null) {
            if ((int) $d['user_id'] === $userId) {
                return 'guest';
            }
            if ($d['counterparty_id'] !== null && (int) $d['counterparty_id'] === $userId) {
                return 'host';
            }
        }
        if ($token !== '' && hash_equals((string) $d['access_token'], $token)) {
            return 'guest';
        }
        if ($email !== '' && strtolower((string) $d['contact_email']) === strtolower($email)) {
            return 'guest';
        }
        return null;
    }

    /** Full case file: header, timeline and the resolution. */
    public static function detail(string $ref, string $role): ?array
    {
        $d = self::find($ref);
        if (!$d) {
            return null;
        }
        // Names the case file shows: the mediator on the case and the claimant.
        if (!empty($d['assigned_to'])) {
            $d['mediator_name'] = (string) (DB::value('SELECT name FROM users WHERE id = ?', [(int) $d['assigned_to']]) ?: '');
        }
        if (!empty($d['user_id'])) {
            $d['claimant_name'] = (string) (DB::value('SELECT name FROM users WHERE id = ?', [(int) $d['user_id']]) ?: '');
        }
        $hidden = $role !== 'staff';
        $events = DB::all(
            'SELECT * FROM dispute_events WHERE dispute_id = ? '
            . ($hidden ? "AND visibility <> 'staff' " : '')
            . 'ORDER BY id',
            [(int) $d['id']]
        );
        $d['events'] = array_map(static fn($e) => [
            'id'         => (int) $e['id'],
            'kind'       => (string) $e['kind'],
            'role'       => (string) $e['actor_role'],
            'name'       => (string) ($e['actor_name'] ?? ucfirst((string) $e['actor_role'])),
            'body'       => (string) ($e['body'] ?? ''),
            'attachment' => (string) ($e['attachment'] ?? ''),
            'created'    => (string) $e['created_at'],
            'label'      => self::ago((string) $e['created_at']),
        ], $events);
        return $d;
    }

    /** Client-shaped summary for lists and cards. */
    public static function summary(array $d, bool $staff = false): array
    {
        $status = (string) $d['status'];
        $cat = null;
        foreach (self::categories(false) as $c) {
            if ((int) $c['id'] === (int) $d['category_id']) {
                $cat = $c;
                break;
            }
        }
        $events = isset($d['events']) && is_array($d['events']) ? $d['events'] : [];
        $messages = 0;
        foreach ($events as $e) {
            if (($e['kind'] ?? '') === 'message') {
                $messages++;
            }
        }
        return [
            'ref'        => (string) $d['ref'],
            'subject'    => (string) $d['subject'],
            'status'     => $status,
            'label'      => self::STATUSES[$status][0] ?? ucfirst($status),
            'level'      => self::STATUSES[$status][1] ?? 'info',
            'hint'       => self::STATUSES[$status][2] ?? '',
            'category'   => $cat['name'] ?? 'General',
            'catSlug'    => $cat['slug'] ?? 'other',
            'want'       => self::WANTS[(string) $d['desired_outcome']] ?? 'A refund',
            'amount'     => (int) $d['amount_claimed'],
            'currency'   => (string) $d['currency'],
            'booking'    => (string) ($d['booking_ref'] ?? ''),
            'property'   => (string) ($d['property_name'] ?? ''),
            'against'    => (string) $d['against'],
            'priority'   => (string) $d['priority'],
            'mediator'   => (string) ($d['mediator_name'] ?? ''),
            'outcome'    => self::OUTCOMES[(string) ($d['resolution_outcome'] ?? '')] ?? '',
            'outcomeKey' => (string) ($d['resolution_outcome'] ?? ''),
            'note'       => (string) ($d['resolution_note'] ?? ''),
            'refund'     => (int) $d['refund_amount'],
            'slaDue'     => self::dueLabel((string) ($d['sla_due_at'] ?? '')),
            'overdue'    => self::isOverdue($d),
            'opened'     => (string) $d['created_at'],
            'openedAgo'  => self::ago((string) $d['created_at']),
            'updatedAgo' => self::ago((string) ($d['updated_at'] ?? $d['created_at'])),
            'resolved'   => $d['resolved_at'] ? (string) $d['resolved_at'] : '',
            'satisfaction' => $d['satisfaction'] !== null ? (int) $d['satisfaction'] : null,
            'messages'   => $messages,
            'canMessage' => in_array($status, self::OPEN_STATUSES, true),
            'staff'      => $staff,
        ];
    }

    /** Cases raised by a member, newest first. */
    public static function forUser(int $userId, int $limit = 25): array
    {
        $rows = DB::all(
            'SELECT d.*, p.name AS property_name, m.name AS mediator_name
               FROM disputes d
          LEFT JOIN properties p ON p.id = d.property_id
          LEFT JOIN users m ON m.id = d.assigned_to
              WHERE d.user_id = ? OR d.counterparty_id = ?
           ORDER BY d.id DESC LIMIT ' . max(1, min(200, $limit)),
            [$userId, $userId]
        );
        return array_map(static fn($d) => self::summary($d), $rows);
    }

    /**
     * Mediation queue for the back office.
     *
     * @param array $f status ('' = open only, 'all' = everything), q, priority, assigned
     */
    public static function search(array $f = []): array
    {
        $where = [];
        $args = [];
        $status = (string) ($f['status'] ?? 'open');
        if ($status === 'open') {
            $where[] = "d.status IN ('" . implode("','", self::OPEN_STATUSES) . "')";
        } elseif ($status !== '' && $status !== 'all' && isset(self::STATUSES[$status])) {
            $where[] = 'd.status = ?';
            $args[] = $status;
        }
        if (!empty($f['q'])) {
            $where[] = '(d.ref LIKE ? OR d.subject LIKE ? OR d.contact_email LIKE ? OR d.booking_ref LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if (!empty($f['priority'])) {
            $where[] = 'd.priority = ?';
            $args[] = (string) $f['priority'];
        }
        if (!empty($f['assigned'])) {
            $where[] = 'd.assigned_to = ?';
            $args[] = (int) $f['assigned'];
        }
        $sql = 'SELECT d.*, p.name AS property_name, m.name AS mediator_name, u.name AS claimant_name, u.email AS claimant_email
                  FROM disputes d
             LEFT JOIN properties p ON p.id = d.property_id
             LEFT JOIN users m ON m.id = d.assigned_to
             LEFT JOIN users u ON u.id = d.user_id '
            . ($where ? 'WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . self::priorityCase('d.priority') . ', d.id DESC LIMIT 300';
        $rows = DB::all($sql, $args);
        return array_map(static fn($d) => self::summary($d, true) + [
            'claimant' => (string) ($d['claimant_name'] ?: ($d['contact_name'] ?? 'Guest')),
            'email'    => (string) ($d['claimant_email'] ?: ($d['contact_email'] ?? '')),
            'assignedTo' => (int) ($d['assigned_to'] ?? 0),
        ], $rows);
    }

    /* ------------------------------------------------------------- mutations */

    /**
     * Add a message, evidence or note to the timeline.
     *
     * @param array $actor ['id'=>int|null,'role'=>string,'name'=>string]
     */
    public static function post(string $ref, string $body, array $actor, string $kind = 'message', string $attachment = '', string $visibility = 'all'): array
    {
        $d = self::find($ref);
        if (!$d) {
            return [false, 'Dispute not found.', null];
        }
        $body = trim($body);
        /* Evidence links are exactly that — http(s). Anything else (a
           javascript: URI, a data: blob) is dropped to "no attachment"
           rather than stored to be rendered as a link later (D10). */
        $attachment = trim($attachment);
        if ($attachment !== '' && !preg_match('~^https?://~i', $attachment)) {
            $attachment = '';
        }
        if ($body === '' && $attachment === '') {
            return [false, 'Write a message or attach a link first.', null];
        }
        if (mb_strlen($body) > 6000) {
            return [false, 'That message is too long — please keep it under 6,000 characters.', null];
        }
        if (!in_array($kind, ['message', 'evidence', 'note', 'offer'], true)) {
            $kind = 'message';
        }
        if (!in_array($visibility, ['all', 'staff'], true)) {
            $visibility = 'all';
        }

        $role = (string) ($actor['role'] ?? 'guest');
        // Staff-only visibility is a staff-only privilege; anyone else posting
        // it lands as a normal message on the public timeline.
        if ($visibility === 'staff' && $role !== 'staff') {
            $visibility = 'all';
        }
        $id = self::event(
            (int) $d['id'], $kind, $role,
            isset($actor['id']) ? (int) $actor['id'] : null,
            (string) ($actor['name'] ?? ucfirst($role)),
            mb_substr($body, 0, 6000),
            mb_substr(trim($attachment), 0, 255),
            '',
            $visibility
        );

        $fields = ['updated_at' => date('Y-m-d H:i:s')];
        // The first staff reply stops the response clock and moves the case on.
        if ($role === 'staff' && empty($d['first_response_at'])) {
            $fields['first_response_at'] = date('Y-m-d H:i:s');
            if ((string) $d['status'] === 'submitted') {
                $fields['status'] = 'under_review';
            }
        }
        DB::update('disputes', $fields, 'id = ?', [(int) $d['id']]);

        if ($role === 'guest') {
            self::notifyStaff($d, 'Guest replied on ' . $d['ref'], mb_substr($body, 0, 120));
            self::emailMediator($d, 'New message on dispute ' . $d['ref'], $body);
        } elseif ($visibility !== 'staff') {
            /* An internal note is silent for everyone but staff: no claimant
               inbox entry, no email (D16). */
            self::notifyClaimant($d, $kind === 'evidence' ? 'Evidence added to ' . $d['ref'] : 'New message on ' . $d['ref'],
                mb_substr($body, 0, 160), 'scale');
            self::emailClaimant($d, 'Update on your dispute ' . $d['ref'], '<p>' . nl2br(e(mb_substr($body, 0, 1200))) . '</p>');
        }

        Repo::flush();
        return [true, $kind === 'evidence' ? 'Evidence added to the case file.' : 'Message added.', ['id' => $id]];
    }

    /** Move a case to another status (staff). */
    public static function setStatus(string $ref, string $status, array $actor, string $note = '', string $priority = ''): array
    {
        $d = self::find($ref);
        if (!$d) {
            return [false, 'Dispute not found.', null];
        }
        if (!isset(self::STATUSES[$status])) {
            return [false, 'Unknown status.', null];
        }
        $from = (string) $d['status'];
        if ($from === $status) {
            return [false, 'The case is already ' . strtolower(self::STATUSES[$status][0]) . '.', null];
        }
        if (!in_array($status, self::TRANSITIONS[$from] ?? [], true)) {
            return [false, 'A dispute cannot move from ' . (self::STATUSES[$from][0] ?? $from) . ' to ' . self::STATUSES[$status][0] . '.', null];
        }
        if ($status === 'rejected' && trim($note) === '') {
            return [false, 'Explain the decision — the reason is sent to both sides.', null];
        }

        $fields = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($priority !== '' && in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $fields['priority'] = $priority;
        }
        $terminal = in_array($status, ['resolved', 'rejected'], true);
        if ($terminal) {
            $fields['resolved_at'] = date('Y-m-d H:i:s');
        }
        if ($from === 'resolved' || $from === 'rejected') {
            $fields['resolved_at'] = null; // reopened
        }
        DB::update('disputes', $fields, 'id = ?', [(int) $d['id']]);

        self::event((int) $d['id'], 'status', 'mediator', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'Mediator'),
            'Status: ' . (self::STATUSES[$from][0] ?? $from) . ' → ' . self::STATUSES[$status][0]
            . ($note !== '' ? ' · ' . $note : ''), '', '', 'all');

        self::setWatchingMediator($d, $actor);
        self::notifyClaimant($d, 'Dispute ' . $d['ref'] . ' · ' . self::STATUSES[$status][0],
            $note !== '' ? mb_substr($note, 0, 150) : (self::STATUSES[$status][2] ?? ''));
        self::emailClaimant($d, 'Dispute ' . $d['ref'] . ' — ' . self::STATUSES[$status][0],
            '<p>Your dispute is now <b>' . e(self::STATUSES[$status][0]) . '</b>.</p>'
            . ($note !== '' ? '<p>' . nl2br(e($note)) . '</p>' : '')
            . '<p>Follow every step in the help centre → Dispute resolution.</p>');
        audit((string) ($actor['name'] ?? 'mediator'), 'Dispute ' . $ref . ' → ' . $status, $status === 'rejected' ? 'warn' : 'ok');
        Repo::flush();

        return [true, 'Case moved to ' . self::STATUSES[$status][0] . '.', ['status' => $status]];
    }

    /** Assign (or clear) the mediator. */
    public static function assign(string $ref, ?int $mediatorId, array $actor): array
    {
        $d = self::find($ref);
        if (!$d) {
            return [false, 'Dispute not found.', null];
        }
        if ($mediatorId === null || $mediatorId === 0) {
            DB::update('disputes', ['assigned_to' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $d['id']]);
            self::event((int) $d['id'], 'status', 'mediator', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'Mediator'), 'Unassigned from the mediation queue', '', '', 'staff');
            Repo::flush();
            return [true, 'Case returned to the queue.', null];
        }
        $mediator = Repo::userRow($mediatorId);
        if (!$mediator) {
            return [false, 'That mediator no longer exists.', null];
        }
        $fields = ['assigned_to' => $mediatorId, 'updated_at' => date('Y-m-d H:i:s')];
        if ((string) $d['status'] === 'submitted') {
            $fields['status'] = 'under_review';
        }
        DB::update('disputes', $fields, 'id = ?', [(int) $d['id']]);

        self::event((int) $d['id'], 'status', 'mediator', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'Mediator'),
            'Assigned to ' . $mediator['name'], '', '', 'all');
        Repo::notify($mediatorId, 'Dispute ' . $d['ref'] . ' assigned to you',
            mb_substr((string) $d['subject'], 0, 100) . ' · SLA ' . self::dueLabel((string) ($d['sla_due_at'] ?? '')), 'scale');
        self::notifyClaimant($d, 'A mediator has your case',
            'Dispute ' . $d['ref'] . ' is now with ' . $mediator['name'] . '.');
        Repo::flush();

        return [true, 'Assigned to ' . $mediator['name'] . '.', ['mediator' => $mediator['name']]];
    }

    /**
     * Issue the decision. A refund decision also touches the booking and the
     * payment rows, which is the part the dispute centre previously only pretended to do.
     *
     * $in: outcome, note, refund, priority, notify
     */
    public static function resolve(string $ref, array $in, array $actor): array
    {
        $d = self::find($ref);
        if (!$d) {
            return [false, 'Dispute not found.', null];
        }
        $outcome = (string) ($in['outcome'] ?? 'no_action');
        if (!isset(self::OUTCOMES[$outcome])) {
            return [false, 'Choose an outcome for the case.', null];
        }
        $note = trim((string) ($in['note'] ?? ''));
        if ($note === '') {
            return [false, 'Write the decision — it is sent to both sides.', null];
        }
        /* A decision is for live cases; closed ones are reopened first (D18),
           which keeps the audit trail honest about how many times money moved. */
        if (!in_array((string) $d['status'], self::OPEN_STATUSES, true)) {
            return [false, 'That case is not open — move it back to Under review first.', null];
        }
        $refund = max(0, (int) ($in['refund'] ?? 0));
        /* "No action" cannot quietly also be a payout (D34). */
        if ($outcome === 'no_action') {
            $refund = 0;
        }
        $booking = $d['booking_id'] ? BookingService::find((string) $d['booking_ref']) : null;
        if ($refund > 0 && !$booking) {
            return [false, 'A refund needs a reservation attached to the case — there is nothing to refund without one (D31).', null];
        }
        if ($refund > 0 && $booking && $refund > (int) $booking['total']) {
            return [false, 'The refund cannot exceed the reservation total of ' . money((int) $booking['total']) . '.', null];
        }

        $status = $outcome === 'no_action' ? 'rejected' : 'resolved';
        $now = date('Y-m-d H:i:s');
        DB::update('disputes', [
            'status'             => $status,
            'resolution_outcome' => $outcome,
            'resolution_note'    => mb_substr($note, 0, 4000),
            'refund_amount'      => $refund,
            'resolved_at'        => $now,
            'updated_at'         => $now,
            'assigned_to'        => $d['assigned_to'] ?: ($actor['id'] ?? null),
        ], 'id = ?', [(int) $d['id']]);

        self::event((int) $d['id'], 'resolution', 'mediator', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'Mediator'),
            'Decision: ' . self::OUTCOMES[$outcome] . ($refund > 0 ? ' · ' . money($refund) : '') . "\n" . $note, '', '', 'all');

        /* --- make the outcome real on the reservation --- */
        if ($booking) {
            $full = $refund > 0 && $refund >= (int) $booking['total'];
            if ($refund > 0) {
                // Post balanced double-entry ledger refund from escrow to refund_out
                $refundFen = $refund * 100;
                Ledger::postTransaction([
                    ['account' => Ledger::ACCT_ESCROW,     'direction' => 'debit',  'amount_fen' => $refundFen],
                    ['account' => Ledger::ACCT_REFUND_OUT, 'direction' => 'credit', 'amount_fen' => $refundFen],
                ], 'dispute_refund', 'REF-' . $d['ref'], (int) $booking['id'], (int) ($booking['user_id'] ?? 0), 'Dispute resolution refund (' . self::OUTCOMES[$outcome] . ')');

                // A real refund row against the reservation, not just a note.
                DB::insert('payments', [
                    'booking_id' => (int) $booking['id'],
                    'user_id'    => $booking['user_id'] ? (int) $booking['user_id'] : null,
                    'reference'  => 'REF-' . $d['ref'],
                    'gateway'    => 'dispute',
                    'amount'     => $refund,
                    'currency'   => (string) ($booking['currency'] ?? 'NGN'),
                    'status'     => 'refunded',
                    'payload'    => json_encode([
                        'dispute' => $d['ref'],
                        'outcome' => $outcome,
                        'method'  => (string) ($booking['pay_method'] ?? 'escrow'),
                    ]),
                ]);
                DB::update('bookings', [
                    'escrow_status' => $full ? 'refunded' : 'held',
                    'status'        => $full ? 'cancelled' : $booking['status'],
                    'updated_at'    => $now,
                ], 'id = ?', [(int) $booking['id']]);
                if ($full) {
                    DB::run("UPDATE payments SET status = 'refunded' WHERE booking_id = ? AND status <> 'refunded'", [(int) $booking['id']]);
                }
            }
            DB::insert('booking_events', [
                'booking_id' => (int) $booking['id'],
                'event'      => 'dispute_' . $status,
                'detail'     => $d['ref'] . ' — ' . self::OUTCOMES[$outcome] . ($refund > 0 ? ' · ' . money($refund) : ''),
            ]);
            // Fraud/trust signal for the risk dashboard when we refund a host's stay.
            if ($refund > 0 && $d['counterparty_id']) {
                DB::insert('fraud_flags', [
                    'code'    => 'DIS-' . strtoupper(substr(md5($d['ref']), 0, 4)),
                    'subject' => 'Dispute payout · ' . ($booking['property_name'] ?? $d['booking_ref']),
                    'reason'  => self::OUTCOMES[$outcome] . ' — ' . $d['ref'],
                    'score'   => $full ? 70 : 45,
                    'level'   => $full ? 'warn' : 'info',
                    'status'  => 'open',
                ]);
            }
        }

        self::setWatchingMediator($d, $actor);
        self::notifyClaimant($d, 'Dispute ' . $d['ref'] . ' — ' . self::OUTCOMES[$outcome],
            mb_substr($note, 0, 150));
        self::emailClaimant($d, 'Decision on your dispute ' . $d['ref'],
            '<p>The mediation team has issued a decision on your dispute.</p>'
            . '<p><b>' . e(self::OUTCOMES[$outcome]) . '</b>' . ($refund > 0 ? ' — ' . e(money($refund)) : '') . '</p>'
            . '<p>' . nl2br(e($note)) . '</p>'
            . '<p>Please rate how we handled it from the help centre — it is how we improve.</p>');
        if ($d['counterparty_id']) {
            Repo::notify((int) $d['counterparty_id'], 'Dispute outcome · ' . $d['ref'],
                self::OUTCOMES[$outcome] . ' — ' . mb_substr($note, 0, 110), 'scale');
        }
        // Any other staff member who touched the case should know it closed.
        self::notifyStaff($d, 'Dispute ' . $d['ref'] . ' closed', self::OUTCOMES[$outcome]);

        audit((string) ($actor['name'] ?? 'mediator'), 'Resolved dispute ' . $ref . ' — ' . $outcome, 'ok');
        Repo::flush();

        return [true, 'Decision recorded and both sides notified.', ['outcome' => $outcome, 'refund' => $refund]];
    }

    /** The claimant closes their own case. */
    public static function withdraw(string $ref, array $actor, array $d): array
    {
        if (in_array((string) $d['status'], ['resolved', 'rejected', 'withdrawn'], true)) {
            return [false, 'That dispute is already closed.', null];
        }
        DB::update('disputes', [
            'status'     => 'withdrawn',
            'resolved_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $d['id']]);
        self::event((int) $d['id'], 'withdrawn', 'guest', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'Guest'),
            'Withdrawn by the guest', '', '', 'all');
        self::notifyStaff($d, 'Dispute ' . $ref . ' withdrawn', 'The guest closed the case.');
        Repo::flush();
        return [true, 'Dispute withdrawn — you can open a new one at any time.', null];
    }

    /** Post-resolution satisfaction score (1–5). */
    public static function rate(string $ref, int $stars, string $comment, array $actor): array
    {
        if ($stars < 1 || $stars > 5) {
            return [false, 'Choose between one and five stars.', null];
        }
        $d = self::find($ref);
        if (!$d) {
            return [false, 'Dispute not found.', null];
        }
        if (!in_array((string) $d['status'], ['resolved', 'rejected'], true)) {
            return [false, 'You can rate a case once it has been decided.', null];
        }
        DB::update('disputes', [
            'satisfaction'      => $stars,
            'satisfaction_note' => mb_substr(trim($comment), 0, 255),
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $d['id']]);
        self::event((int) $d['id'], 'note', 'guest', (int) ($actor['id'] ?? 0), (string) ($actor['name'] ?? 'Guest'),
            'Rated the resolution ' . $stars . '/5' . ($comment !== '' ? ' — ' . $comment : ''), '', '', 'staff');
        Repo::flush();
        return [true, 'Thank you — your rating helps us improve.', ['rating' => $stars]];
    }

    /* ------------------------------------------------------------------ stats */

    /**
     * Live numbers for the help page and the back office.
     *
     * $private=false (the default — the endpoint has no way to know who is
     * asking) keeps the recent-case ticker to reference, status and age:
     * no subjects, amounts, booking refs or names (D32). The staff queue
     * view reads its cases via search(), which is gated separately.
     */
    public static function stats(bool $private = false): array
    {
        try {
            $open = (int) DB::value(
                "SELECT COUNT(*) FROM disputes WHERE status IN ('" . implode("','", self::OPEN_STATUSES) . "')", [], 0
            );
            $total   = (int) DB::value('SELECT COUNT(*) FROM disputes', [], 0);
            $resolved = (int) DB::value("SELECT COUNT(*) FROM disputes WHERE status = 'resolved'", [], 0);
            /* Decision times are averaged in PHP so the figure is identical on
               MySQL and the SQLite development driver (no TIMESTAMPDIFF). */
            $hours = [];
            $onTime = 0;
            foreach (DB::all('SELECT created_at, resolved_at, sla_due_at FROM disputes WHERE resolved_at IS NOT NULL LIMIT 5000') as $r) {
                $started = strtotime((string) $r['created_at']) ?: 0;
                $ended = strtotime((string) $r['resolved_at']) ?: 0;
                if ($started > 0 && $ended > 0) {
                    $hours[] = max(0, ($ended - $started) / 3600);
                }
                if (empty($r['sla_due_at']) || (string) $r['resolved_at'] <= (string) $r['sla_due_at']) {
                    $onTime++;
                }
            }
            $decided = count($hours);
            $avgHours = $decided > 0 ? array_sum($hours) / $decided : 0.0;
            $satisfaction = (float) DB::value('SELECT COALESCE(AVG(satisfaction),0) FROM disputes WHERE satisfaction IS NOT NULL', [], 0);
            $overdue = (int) DB::value(
                "SELECT COUNT(*) FROM disputes WHERE status IN ('" . implode("','", self::OPEN_STATUSES) . "')
                   AND sla_due_at IS NOT NULL AND sla_due_at < ?", [date('Y-m-d H:i:s')], 0
            );
            $byCategory = DB::all(
                'SELECT c.name, COUNT(d.id) AS n
                   FROM dispute_categories c LEFT JOIN disputes d ON d.category_id = c.id
                  GROUP BY c.id, c.name ORDER BY n DESC, c.sort_order'
            );
            $recent = DB::all(
                "SELECT d.*, p.name AS property_name, m.name AS mediator_name
                   FROM disputes d
              LEFT JOIN properties p ON p.id = d.property_id
              LEFT JOIN users m ON m.id = d.assigned_to
                  WHERE d.status IN ('" . implode("','", self::OPEN_STATUSES) . "')
               ORDER BY " . self::priorityCase('d.priority') . ", d.id DESC LIMIT 5"
            );
        } catch (Throwable $e) {
            return ['open' => 0, 'total' => 0, 'resolved' => 0, 'avgHours' => 0, 'onTime' => 0,
                    'decided' => 0, 'satisfaction' => 0, 'overdue' => 0, 'byCategory' => [], 'recent' => [], 'ready' => false];
        }

        return [
            'open'         => $open,
            'total'        => $total,
            'resolved'     => $resolved,
            'decided'      => $decided,
            'avgHours'     => round($avgHours, 1),
            'onTime'       => $decided ? (int) round($onTime / $decided * 100) : 100,
            'satisfaction' => round($satisfaction, 1),
            'overdue'      => $overdue,
            'byCategory'   => array_map(static fn($c) => ['name' => (string) $c['name'], 'n' => (int) $c['n']], $byCategory),
            'recent'       => $private
                ? array_map(static fn($d) => self::summary($d, true), $recent)
                : array_map(static fn($d) => [
                    'ref'       => (string) $d['ref'],
                    'status'    => (string) $d['status'],
                    'label'     => self::STATUSES[(string) $d['status']][0] ?? ucfirst((string) $d['status']),
                    'level'     => self::STATUSES[(string) $d['status']][1] ?? 'info',
                    'openedAgo' => self::ago((string) $d['created_at']),
                ], $recent),
            'ready'        => true,
        ];
    }

    /** Mediators available for assignment (admins + staff roles). */
    public static function mediators(): array
    {
        return array_map(static fn($u) => [
            'id'   => (int) $u['id'],
            'name' => (string) $u['name'],
            'email' => (string) $u['email'],
        ], DB::all("SELECT id, name, email FROM users WHERE role IN ('admin','corporate') ORDER BY name"));
    }

    /** Agents/admins who can work the live chat queues (used by the chat admin tab). */
    public static function mediatorsAndAgents(): array
    {
        return self::mediators();
    }

    /* ------------------------------------------------------------------ helpers */

    /**
     * Portable "most urgent first" ordering.
     *
     * MySQL's FIELD() has no SQLite equivalent, and this runs on both, so the
     * ranking is expressed as a CASE expression.
     */
    private static function priorityCase(string $column): string
    {
        return 'CASE ' . $column . " WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END";
    }

    private static function nextRef(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $ref = 'D-' . date('Y') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            if (!DB::value('SELECT 1 FROM disputes WHERE ref = ?', [$ref])) {
                return $ref;
            }
        }
        return 'D-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }

    private static function event(int $disputeId, string $kind, string $role, ?int $actorId, string $actorName,
                                  string $body, string $attachment, string $meta, string $visibility): int
    {
        return DB::insert('dispute_events', [
            'dispute_id' => $disputeId,
            'actor_id'   => $actorId ?: null,
            'actor_role' => in_array($role, ['guest', 'host', 'mediator', 'system'], true) ? $role : 'system',
            'actor_name' => mb_substr($actorName, 0, 140),
            'kind'       => $kind,
            'body'       => $body,
            'attachment' => $attachment ?: null,
            'meta'       => $meta ?: null,
            'visibility' => $visibility,
        ]);
    }

    private static function setWatchingMediator(array $d, array $actor): void
    {
        if (!empty($d['assigned_to']) || empty($actor['id'])) {
            return;
        }
        DB::update('disputes', ['assigned_to' => (int) $actor['id']], 'id = ?', [(int) $d['id']]);
    }

    private static function notifyClaimant(array $d, string $title, string $body): void
    {
        $uid = (int) ($d['user_id'] ?? 0);
        if ($uid > 0) {
            Repo::notify($uid, $title, $body, 'scale');
        }
    }

    private static function notifyStaff(array $d, string $title, string $body): void
    {
        $staff = (int) ($d['assigned_to'] ?? 0);
        if ($staff > 0) {
            Repo::notify($staff, $title, $body, 'scale');
            return;
        }
        // No mediator yet — tell every administrator so nothing sits unseen.
        foreach (DB::all("SELECT id FROM users WHERE role = 'admin' LIMIT 10") as $a) {
            Repo::notify((int) $a['id'], $title, $body, 'scale');
        }
    }

    private static function emailClaimant(array $d, string $subject, string $html): void
    {
        $to = trim((string) ($d['contact_email'] ?? ''));
        if ($to === '') {
            return;
        }
        Mailer::send($to, $subject, $html . self::footer($d));
    }

    private static function emailMediator(array $d, string $subject, string $body): void
    {
        $uid = (int) ($d['assigned_to'] ?? 0);
        if ($uid <= 0) {
            return;
        }
        $u = Repo::userRow($uid);
        if ($u && is_email((string) $u['email'])) {
            Mailer::send((string) $u['email'], $subject,
                '<p>' . nl2br(e(mb_substr($body, 0, 1200))) . '</p>'
                . '<p><a href="' . e(url('admin.php?tab=disputes&ref=' . urlencode((string) $d['ref']))) . '">Open the case in the back office</a></p>');
        }
    }

    private static function emailStakeholders(int $id, string $subject, string $html): void
    {
        $d = DB::row('SELECT * FROM disputes WHERE id = ?', [$id]);
        if ($d) {
            self::emailClaimant($d, $subject, $html);
        }
    }

    private static function footer(array $d): string
    {
        return '<p style="color:#7d7768;font-size:12.5px">Reference <b>' . e((string) $d['ref']) . '</b> · '
            . 'follow the case any time at <a href="' . e(url('help.php?dispute=' . urlencode((string) $d['ref']))) . '">'
            . e(url('help.php?dispute=' . urlencode((string) $d['ref']))) . '</a></p>';
    }

    public static function isOverdue(array $d): bool
    {
        if (!in_array((string) $d['status'], self::OPEN_STATUSES, true)) {
            return false;
        }
        return !empty($d['sla_due_at']) && strtotime((string) $d['sla_due_at']) < time();
    }

    private static function dueLabel(string $when): string
    {
        if ($when === '') {
            return '';
        }
        $ts = strtotime($when);
        if ($ts === false) {
            return '';
        }
        $diff = $ts - time();
        if ($diff <= 0) {
            return 'SLA overdue by ' . self::span(abs($diff));
        }
        return self::span($diff) . ' to respond';
    }

    private static function ago(string $when): string
    {
        $ts = strtotime($when);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        return self::span($diff) . ' ago';
    }

    private static function span(int $seconds): string
    {
        if ($seconds < 3600) {
            return max(1, (int) round($seconds / 60)) . ' min';
        }
        if ($seconds < 86400) {
            return max(1, (int) round($seconds / 3600)) . 'h';
        }
        return max(1, (int) round($seconds / 86400)) . 'd';
    }
}
