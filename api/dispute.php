<?php
/**
 * Dispute resolution centre — raise, track and mediate cases.
 *
 * The help page talks to this endpoint for everything: a signed-in guest, a
 * signed-out visitor holding their private access token, the counterparty host
 * and the mediation team all read and write the same case file.
 *
 *      action=open        category, booking, subject, description, want, amount,
 *                         name, email, phone           → the new case + token
 *      action=view        ref, token?                    → case file + timeline
 *      action=post        ref, token?, body, kind, attachment, visibility
 *      action=withdraw    ref, token?
 *      action=rate        ref, token?, stars, comment
 *      action=queue       q?, status?, priority?         (staff)
 *      action=claim       ref                            (staff)
 *      action=assign      ref, mediator                  (staff)
 *      action=status      ref, status, note?, priority?  (staff)
 *      action=resolve     ref, outcome, note, refund?     (staff)
 *      action=categories  — the picker shown by the wizard
 */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';
require_once JL_INC . '/disputes.php';

api_guard();

/* The centre ships as a migration; until it has been run the endpoint answers
   with a friendly "finish setting up" envelope instead of a 500. */
if (!DB::tableExists('disputes')) {
    json_response([
        'ok'            => false,
        'needsMigration' => true,
        'message'       => 'The dispute centre needs its database tables — an administrator can finish that from install/migrate.php.',
    ], 503);
}

$action = input_str('action', '');
$admin  = Auth::isAdmin();

/**
 * Resolve the case and the caller's role in one step.
 * Returns [row, role] or ends the request with a JSON error.
 */
$authorize = static function (string $ref, bool $allowStaff = true) use ($admin): array {
    $d = DisputeService::find($ref);
    if (!$d) {
        json_fail('We could not find a dispute with that reference.', 404);
    }
    $user  = Auth::user();
    $uid   = $user ? (int) $user['id'] : null;
    $email = $user ? (string) $user['email'] : '';
    $role  = DisputeService::roleFor($d, $uid, input_str('token'), $email, $admin);
    if ($role === null) {
        json_fail('You do not have access to that case file.', 403);
    }
    if ($role === 'staff' && !$allowStaff) {
        json_fail('That action is reserved for the mediation team.', 403);
    }
    return [$d, $role];
};

/** Staff-only gate for the back-office actions. */
$requireStaff = static function () use ($admin): void {
    if (!$admin) {
        json_fail('Mediation actions are restricted to the support team.', 403, ['requiresStaff' => true]);
    }
};

/** The actor passed into the service: who is writing, in what role. */
$actor = static function (string $role, ?array $user, string $fallback = 'Guest'): array {
    return [
        'id'   => $user ? (int) $user['id'] : null,
        'role' => $role,
        'name' => $user ? (string) $user['name'] : $fallback,
    ];
};

switch ($action) {

    /* ------------------------------------------------------------- the wizard */

    case 'categories': {
        json_ok([
            'categories' => DisputeService::categories(),
            'wants'      => DisputeService::WANTS,
            'stats'      => DisputeService::stats(),
        ]);
    }

    /** The cases the signed-in member is party to — the "your cases" list. */
    case 'mine': {
        $user = api_user();
        json_ok([
            'cases' => DisputeService::forUser((int) $user['id'], 25),
            'stats' => DisputeService::stats(),
        ]);
    }

    case 'open': {
        api_throttle('dispute_open', 6, 3600);

        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::open([
            'booking'     => input_str('booking'),
            'category'    => input_str('category'),
            'subject'     => input_str('subject'),
            'description' => input_str('description'),
            'want'        => input_str('want'),
            'amount'      => input_int('amount'),
            'against'     => input_str('against'),
            'name'        => input_str('name'),
            'email'       => input_str('email'),
            'phone'       => input_str('phone'),
            'priority'    => input_str('priority'),
        ], $user ? (int) $user['id'] : null);

        if (!$ok) {
            json_fail($message);
        }
        json_ok_state($data, $message);
    }

    /* ----------------------------------------------------------- the case file */

    case 'view': {
        [$d, $role] = $authorize(input_str('ref'));
        $case = DisputeService::detail((string) $d['ref'], $role);
        if (!$case) {
            json_fail('We could not load that case file.', 404);
        }
        json_ok([
            'role'     => $role,
            'staff'    => $role === 'staff',
            'events'   => $case['events'],
            'case'     => DisputeService::summary($case, $role === 'staff') + [
                'events'    => $case['events'],
                'mediator'  => (string) ($case['mediator_name'] ?? ''),
                'claimant'  => (string) ($case['claimant_name'] ?? ($case['contact_name'] ?? 'Guest')),
                'claimantEmail' => (string) ($case['contact_email'] ?? ''),
                'slaDue'    => (string) ($case['sla_due_at'] ?? ''),
                'overdue'   => DisputeService::isOverdue($case),
            ],
        ]);
    }

    case 'post': {
        [$d, $role] = $authorize(input_str('ref'));
        if (($d['status'] ?? '') === 'withdrawn') {
            json_fail('That case was withdrawn — open a new dispute if something else has come up.');
        }
        $requested = input_str('visibility', 'all');
        if ($role !== 'staff' && $requested === 'staff') {
            json_fail('Only the mediation team can leave internal notes on a case.');
        }
        $visibility = $role === 'staff' && $requested === 'staff' ? 'staff' : 'all';
        api_throttle('dispute_post', 40, 600);

        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::post(
            (string) $d['ref'],
            input_str('body'),
            $actor($role, $user, (string) ($d['contact_name'] ?? 'Guest')),
            input_str('kind', 'message'),
            input_str('attachment'),
            $visibility
        );
        if (!$ok) {
            json_fail($message);
        }
        $case = DisputeService::detail((string) $d['ref'], $role);
        json_ok(['events' => $case['events'] ?? [], 'case' => DisputeService::summary($case, $role === 'staff')], $message);
    }

    case 'withdraw': {
        [$d, $role] = $authorize(input_str('ref'));
        /* Only the person who raised the case can take it back (D17) — the
           respondent withdrawing a case against them is not a thing. */
        if ($role !== 'guest') {
            json_fail($role === 'staff'
                ? 'Staff close a case with a decision rather than a withdrawal.'
                : 'Only the person who raised this case can withdraw it.', 403);
        }
        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::withdraw((string) $d['ref'], $actor($role, $user, (string) ($d['contact_name'] ?? 'Guest')), $d);
        if (!$ok) {
            json_fail($message);
        }
        json_ok_state($data, $message);
    }

    case 'rate': {
        [$d, $role] = $authorize(input_str('ref'));
        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::rate(
            (string) $d['ref'],
            input_int('stars'),
            input_str('comment'),
            $actor($role, $user, (string) ($d['contact_name'] ?? 'Guest'))
        );
        if (!$ok) {
            json_fail($message);
        }
        json_ok($data, $message);
    }

    /* --------------------------------------------------------------- the desk */

    case 'queue': {
        $requireStaff();
        json_ok([
            'cases'     => DisputeService::search([
                'q'        => input_str('q'),
                'status'   => input_str('status', 'open'),
                'priority' => input_str('priority'),
                'assigned' => input_int('assigned'),
            ]),
            'stats'     => DisputeService::stats(true),
            'mediators' => DisputeService::mediators(),
        ]);
    }

    case 'claim': {
        $requireStaff();
        [$d] = $authorize(input_str('ref'));
        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::assign((string) $d['ref'], (int) $user['id'], $actor('staff', $user, 'Mediator'));
        if (!$ok) {
            json_fail($message);
        }
        if ((string) $d['status'] === 'submitted') {
            DisputeService::setStatus((string) $d['ref'], 'under_review', $actor('staff', $user, 'Mediator'),
                'Claimed by ' . (string) $user['name']);
        }
        $case = DisputeService::detail((string) $d['ref'], 'staff');
        json_ok(['case' => DisputeService::summary($case, true)], $message);
    }

    case 'assign': {
        $requireStaff();
        [$d] = $authorize(input_str('ref'));
        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::assign((string) $d['ref'], input_int('mediator'), $actor('staff', $user, 'Mediator'));
        if (!$ok) {
            json_fail($message);
        }
        $case = DisputeService::detail((string) $d['ref'], 'staff');
        json_ok(['case' => DisputeService::summary($case, true)], $message);
    }

    case 'status': {
        $requireStaff();
        [$d] = $authorize(input_str('ref'));
        $status = input_str('status');
        if ($status === 'resolved') {
            json_fail('Use the decision form to resolve a case — it records the outcome and any refund.');
        }
        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::setStatus(
            (string) $d['ref'],
            $status,
            $actor('staff', $user, 'Mediator'),
            input_str('note'),
            input_str('priority')
        );
        if (!$ok) {
            json_fail($message);
        }
        $case = DisputeService::detail((string) $d['ref'], 'staff');
        json_ok(['case' => DisputeService::summary($case, true)], $message);
    }

    case 'resolve': {
        $requireStaff();
        [$d] = $authorize(input_str('ref'));
        $user = Auth::user();
        [$ok, $message, $data] = DisputeService::resolve((string) $d['ref'], [
            'outcome' => input_str('outcome'),
            'note'    => input_str('note'),
            'refund'  => input_int('refund'),
        ], $actor('staff', $user, 'Mediator'));
        if (!$ok) {
            json_fail($message);
        }
        $case = DisputeService::detail((string) $d['ref'], 'staff');
        json_ok(['case' => DisputeService::summary($case, true), 'decision' => $data], $message);
    }
}

json_fail('Unknown dispute action.');
