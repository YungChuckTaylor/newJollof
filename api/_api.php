<?php
/**
 * Jollof Living — API bootstrap.
 * Every endpoint in this folder starts by requiring this file.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once JL_INC . '/view.php';

header('Cache-Control: no-store, max-age=0');

/** Reject anything that is not a same-origin POST carrying a valid token. */
function api_guard(): void
{
    require_post();
    require_csrf();
}

/** The signed-in user, or a 401 JSON envelope the front-end knows how to handle. */
function api_user(): array
{
    $u = Auth::user();
    if (!$u) {
        json_response([
            'ok'           => false,
            'requiresAuth' => true,
            'message'      => 'Please sign in to continue.',
        ], 401);
    }
    return $u;
}

/** Admin-only endpoints. */
function api_admin(): array
{
    $u = Auth::user();
    if (!$u || !Auth::isAdmin()) {
        json_response([
            'ok'           => false,
            'requiresAuth' => true,
            'message'      => 'Administrator sign-in required.',
        ], 403);
    }
    return $u;
}

/** Simple per-session throttle: max $max calls to $bucket every $seconds. */
function api_throttle(string $bucket, int $max, int $seconds): void
{
    $key = 'throttle_' . $bucket;
    $now = time();
    $hits = array_values(array_filter(
        (array) ($_SESSION[$key] ?? []),
        static fn($t) => ($now - (int) $t) < $seconds
    ));
    if (count($hits) >= $max) {
        json_fail('Too many requests — please wait a moment and try again.', 429);
    }
    $hits[] = $now;
    $_SESSION[$key] = $hits;
}

/** Success envelope that also ships the refreshed client state. */
function json_ok_state($data = [], string $message = ''): void
{
    json_response([
        'ok'      => true,
        'message' => $message,
        'data'    => $data,
        'state'   => View::state(),
    ]);
}
