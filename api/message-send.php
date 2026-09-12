<?php
/** Send a message in a conversation thread. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$user = api_user();
$uid = (int) $user['id'];
api_throttle('message', 40, 300);

$convId = input_int('conversation');
$text = input_str('text');

if ($text === '') json_fail('Type a message first.');
if (mb_strlen($text) > 4000) json_fail('That message is too long.');

$conv = DB::row('SELECT * FROM conversations WHERE id = ? AND user_id = ?', [$convId, $uid]);
if (!$conv) json_fail('Conversation not found.', 404);

$now = date('Y-m-d H:i:s');
// everything the guest already received counts as read the moment they reply
DB::run("UPDATE messages SET read_flag = 1 WHERE conversation_id = ? AND sender <> 'me'", [$convId]);

DB::insert('messages', [
    'conversation_id' => $convId,
    'sender'          => 'me',
    'body'            => $text,
    'time_label'      => date('H:i', strtotime($now)),
    'read_flag'       => 1,
    'created_at'      => $now,
]);
DB::update('conversations', ['last_message_at' => $now, 'preview' => mb_substr($text, 0, 120)], 'id = ?', [$convId]);

$data = ['time' => date('H:i', strtotime($now))];

/* concierge threads answer immediately; host threads are delivered and await a reply */
if (($conv['kind'] ?? '') === 'concierge') {
    $reply = Concierge::reply($text, $uid);
    DB::insert('messages', [
        'conversation_id' => $convId,
        'sender'          => 'bot',
        'body'            => $reply,
        'time_label'      => date('H:i'),
        'read_flag'       => 1,
    ]);
    DB::update('conversations', ['preview' => mb_substr(strip_tags($reply), 0, 120)], 'id = ?', [$convId]);
    $data['reply'] = $reply;
    $data['replyTime'] = date('H:i');
} else {
    Mailer::hostMessage($conv, $user, $text);
}

json_ok_state($data, '');
