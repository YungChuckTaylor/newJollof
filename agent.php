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

$extra = [
    'agentConsole' => [
        'ready'   => DB::tableExists('chat_sessions'),
        'agent'   => Auth::id() ? LiveChat::agentForUser(Auth::id()) : null,
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
