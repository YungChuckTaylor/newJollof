<?php
/**
 * Jollof Living — live chat agent console.
 *
 * The workspace support agents sign into: queues, transcripts, canned replies,
 * routing and (for supervisors) the agent roster. Administrators manage the
 * same records inside the back office; this page is the day-to-day desk.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require JL_INC . '/view.php';
require JL_INC . '/livechat.php';

Auth::requireLogin();

/* Server-side gate (T12): the console shell must never render for someone
   who is not desk staff — the SPA's client check is a courtesy, not a wall. */
$agent = LiveChat::agentForUser(Auth::id());
if ($agent === null && !Auth::isAdmin()) {
    http_response_code(403);
    View::header('404', ['title' => 'Agent console — access denied']);
    echo '<main style="max-width:620px;margin:16vh auto 20vh;padding:0 20px;text-align:center">'
       . '<h1>Live chat desk</h1>'
       . '<p>This workspace is for support agents. Your account is not on the roster yet.</p>'
       . '<p>If you believe you should be here, ask an administrator to add <b>'
       . e((string) (Auth::user()['email'] ?? '')) . '</b> under Admin → Live chat desk.</p>'
       . '<p><a class="btn btn-gold" href="' . e(url('')) . '">Back to Jollof Living</a></p></main>';
    View::footer();
    exit;
}

$extra = [
    'agentConsole' => [
        'ready'   => DB::tableExists('chat_sessions'),
        'agent'   => $agent,
        'isAdmin' => Auth::isAdmin(),
    ],
];

View::header('agent', [
    'title' => 'Live chat desk — ' . (string) Repo::setting('site_name', 'Jollof Living'),
    'desc'  => 'Work the live chat queues: reply to guests, claim and transfer chats, and keep the desk running.',
    'metaKey' => 'admin',
    'extra' => $extra,
]);
View::footer();
