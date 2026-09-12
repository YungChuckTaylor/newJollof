<?php
/** Standalone AI concierge page — logs to the concierge thread and answers. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
api_throttle('concierge', 40, 300);

$text = input_str('text');
if ($text === '') json_fail('Ask me anything about staying in Lagos or Abuja.');

$uid = Auth::id();
if ($uid) {
    Repo::ensureConversations($uid);
    $conv = DB::row("SELECT * FROM conversations WHERE user_id = ? AND ckey = 'concierge' LIMIT 1", [$uid]);
    if ($conv) {
        DB::insert('messages', ['conversation_id' => (int) $conv['id'], 'sender' => 'me', 'body' => $text, 'time_label' => date('H:i'), 'read_flag' => 1]);
    }
}

$reply = Concierge::reply($text, $uid);

if ($uid && !empty($conv)) {
    DB::insert('messages', ['conversation_id' => (int) $conv['id'], 'sender' => 'bot', 'body' => $reply, 'time_label' => date('H:i'), 'read_flag' => 1]);
    DB::update('conversations', ['last_message_at' => date('Y-m-d H:i:s'), 'preview' => mb_substr(strip_tags($reply), 0, 120)], 'id = ?', [(int) $conv['id']]);
}

json_ok(['reply' => $reply]);
