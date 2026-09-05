<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Env.php';
Env::load($root . '/.env');
require_once $root . '/src/Database.php';
require_once $root . '/src/helpers.php';
require_once $root . '/src/Auth.php';

ini_set('display_errors', (string) (Env::get('APP_DEBUG', 'true') === 'true' ? '1' : '0'));
error_reporting(E_ALL);

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('simormawa_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
require_once $root . '/src/view.php';
require_once $root . '/src/Actions.php';
