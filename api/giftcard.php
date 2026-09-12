<?php
/** Buy or redeem a gift card. */
declare(strict_types=1);
require_once __DIR__ . '/_api.php';

api_guard();
$action = input_str('action', 'purchase');

if ($action === 'purchase') {
    $user = api_user();
    $amount = input_int('amount');
    $allowed = [50000, 100000, 250000, 500000, 1000000];
    if (!in_array($amount, $allowed, true)) {
        json_fail('Please choose one of the available gift card amounts.');
    }

    $name  = input_str('name');
    $email = strtolower(input_str('email'));
    if ($name === '')      json_fail('Who is this gift for?');
    if (!is_email($email)) json_fail('Please enter a valid recipient email address.');

    // JL-GIFT-XXXXXXXX — unambiguous characters only
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = 'JL-GIFT-';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (DB::value('SELECT 1 FROM gift_cards WHERE code = ?', [$code]));

    $message = mb_substr(input_str('message'), 0, 500);

    DB::insert('gift_cards', [
        'code'      => $code,
        'amount'    => $amount,
        'balance'   => $amount,
        'purchaser' => (string) $user['email'],
        'recipient' => $email,
        'message'   => $message ?: null,
        'status'    => 'active',
    ]);

    Mailer::send($email, 'You have a Jollof Living gift card',
        '<h2 style="margin:0 0 12px;font-size:20px">A gift from ' . e((string) $user['name']) . '</h2>'
        . ($message ? '<p style="font-style:italic">“' . nl2br(e($message)) . '”</p>' : '')
        . '<p>Your gift card is worth <b>' . e(money($amount)) . '</b>.</p>'
        . '<p style="font-size:22px;letter-spacing:.2em;font-weight:700">' . e($code) . '</p>'
        . '<p>Enter it at checkout on any residence. The balance never expires.</p>');

    Repo::notify((int) $user['id'], 'Gift card sent ✨',
        money($amount) . ' gift card sent to ' . $email . ' (' . $code . ').', 'gift');
    audit((string) $user['email'], 'Gift card purchased: ' . $code . ' · ' . money($amount), 'ok');

    json_ok(['code' => $code, 'amount' => $amount],
        'Gift card sent to ' . $email . ' — code ' . $code . ' ✨');
}

if ($action === 'check') {
    $code = strtoupper(input_str('code'));
    $gc = DB::row('SELECT * FROM gift_cards WHERE code = ?', [$code]);
    if (!$gc || $gc['status'] !== 'active' || (int) $gc['balance'] <= 0) {
        json_fail('That gift card code is not valid or has been fully redeemed.');
    }
    json_ok(['code' => $gc['code'], 'balance' => (int) $gc['balance']],
        money((int) $gc['balance']) . ' available on this card');
}

json_fail('Unknown gift card action.');
