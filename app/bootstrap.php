<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/app/helpers.php';
date_default_timezone_set(config('timezone'));

require APP_ROOT . '/app/db.php';
require APP_ROOT . '/app/auth.php';
require APP_ROOT . '/app/schema.php';
require APP_ROOT . '/app/repo.php';
require APP_ROOT . '/app/actions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_name('laundry_session');
    session_start();
}

Schema::ensure();
