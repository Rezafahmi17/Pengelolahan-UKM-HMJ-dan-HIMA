<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$page = $_GET['page'] ?? 'home';

if ($page === 'logout') {
    logout_user();
    redirect_to(route_url('home'));
}

handle_post_action();

try {
    switch ($page) {
        case 'home':
            render('home', [], 'public');
            break;
        case 'login':
            if (current_user()) redirect_to(route_url('dashboard'));
            render('login', [], 'public');
            break;
        case 'register':
            if (current_user()) redirect_to(route_url('dashboard'));
            render('register', [], 'public');
            break;
        case 'dashboard':
            render('dashboard');
            break;
        case 'tenants':
            render('tenants');
            break;
        case 'members':
            render('members');
            break;
        case 'structure':
            render('structure');
            break;
        case 'programs':
            render('programs');
            break;
        case 'submissions':
            render('submissions');
            break;
        case 'assets':
            render('assets');
            break;
        case 'finance':
            render('finance');
            break;
        case 'messages':
            render('messages');
            break;
        case 'master':
            render('master');
            break;
        default:
            http_response_code(404);
            echo '<!doctype html><html><body style="font-family:system-ui;padding:40px"><h1>404</h1><p>Halaman tidak ditemukan.</p><a href="index.php">Kembali ke beranda</a></body></html>';
    }
} catch (PDOException $e) {
    http_response_code(500);
    $debug = envv('APP_DEBUG', 'true') === 'true';
    $msg = $debug ? e($e->getMessage()) : 'Terjadi masalah koneksi database.';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database belum terhubung</title><link rel="stylesheet" href="assets/css/app.css"></head><body><main class="setup-error"><div class="setup-card"><img src="assets/img/logo.svg" alt="Logo"><h1>Database belum terhubung</h1><p>Pastikan MySQL aktif, database <b>sim_ormawa_multitenant</b> sudah di-import, dan file <code>.env</code> sudah benar.</p><pre>' . $msg . '</pre><a class="btn btn-primary" href="index.php">Coba lagi</a></div></main></body></html>';
}
