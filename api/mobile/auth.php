<?php
/**
 * Mobile auth — register, login, logout, upgrade to owner.
 * Wraps the same Auth class the website uses, then hands back a bearer
 * token because the app cannot rely on a session cookie.
 */
declare(strict_types=1);

require __DIR__ . '/_mobile.php';
mobile_post();

$action = input_str('action', 'login');
$device = input_str('device', 'Android device');

switch ($action) {
    case 'register': {
        $name  = input_str('name');
        $email = strtolower(input_str('email'));
        $pass  = (string) input('password', '');
        $phone = input_str('phone');
        $type  = input_str('account_type', 'customer');

        if ($name === '' || mb_strlen($name) < 2) {
            json_fail('Please tell us your name.');
        }
        if (!is_email($email)) {
            json_fail('That email address does not look right.');
        }
        if (mb_strlen($pass) < 8) {
            json_fail('Please choose a password of at least 8 characters.');
        }

        // The website maps "owner" to the host role; keep that identical.
        $role = ($type === 'owner' || $type === 'host') ? 'host' : 'guest';
        // Auth::register returns [ok, message, newUserId] — an id, not a row.
        [$ok, $msg, $newId] = Auth::register($name, $email, $pass, $phone, $role);
        if (!$ok || !$newId) {
            json_fail($msg ?: 'We could not create that account.');
        }
        $user = DB::row('SELECT * FROM users WHERE id = ?', [(int) $newId]);
        if (!$user) {
            json_fail('We could not create that account.');
        }

        $token = MobileAuth::issue((int) $user['id'], $device);
        mobile_ok([
            'token' => $token,
            'user'  => mobile_user_payload($user),
        ], $role === 'host'
            ? 'Welcome aboard — your owner dashboard is ready ✨'
            : 'Welcome to Jollof Living ✨');
        break;
    }

    case 'login': {
        $email = strtolower(input_str('email'));
        $pass  = (string) input('password', '');
        if ($email === '' || $pass === '') {
            json_fail('Please enter your email and password.');
        }
        [$ok, $msg, $user] = Auth::attempt($email, $pass);
        if (!$ok || !$user) {
            json_fail($msg ?: 'Those details do not match our records.', 401);
        }
        $token = MobileAuth::issue((int) $user['id'], $device);
        mobile_ok([
            'token' => $token,
            'user'  => mobile_user_payload($user),
        ], 'Welcome back.');
        break;
    }

    case 'logout': {
        MobileAuth::revoke(MobileAuth::bearer());
        mobile_ok([], 'Signed out.');
        break;
    }

    case 'me': {
        $u = mobile_user();
        mobile_ok(['user' => mobile_user_payload($u)]);
        break;
    }

    case 'upgrade': {
        // An existing customer becoming a property owner keeps their trips,
        // wishlist and points — exactly as the website's upgrade does.
        $u = mobile_user();
        Auth::provisionHost((int) $u['id']);
        $fresh = DB::row('SELECT * FROM users WHERE id = ?', [(int) $u['id']]);
        audit((string) $u['email'], 'Upgraded to owner from the Android app', 'info');
        mobile_ok(['user' => mobile_user_payload($fresh ?: $u)], 'Hosting enabled — welcome aboard ✨');
        break;
    }

    case 'push-token': {
        // Store the FCM registration id so the server can notify this device.
        $u = mobile_user();
        $push = input_str('push_token');
        DB::run(
            'UPDATE api_tokens SET push_token = ? WHERE token_hash = ? AND user_id = ?',
            [$push, hash('sha256', MobileAuth::bearer()), (int) $u['id']]
        );
        mobile_ok([], 'Notifications enabled.');
        break;
    }

    default:
        json_fail('Unknown action.');
}
