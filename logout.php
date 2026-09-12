<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    audit((string) (Auth::user()['email'] ?? 'user'), 'Signed out', 'info');
}
Auth::logout();
redirect('');
