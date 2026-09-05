<?php
$user = current_user();
$flashes = pull_flashes();
$title = $title ?? 'SIM ORMAWA & HMJ';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Sistem Informasi Manajemen ORMAWA dan HMJ berbasis web dengan arsitektur multi-tenant — Politeknik Negeri Lampung.">
    <title><?= e($title) ?> — SIM ORMAWA & HMJ</title>
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="public-body">

<div class="utility-bar">
    <div class="container utility-inner">
        <div class="utility-welcome"><span class="dot-live"></span> Portal Organisasi Mahasiswa &bull; Politeknik Negeri Lampung</div>
        <div class="utility-links">
            <span>Multi-Tenant</span>
            <span>Terintegrasi</span>
            <span>Aman</span>
        </div>
    </div>
</div>

<header class="main-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(route_url('home')) ?>">
            <img src="<?= e(asset('img/logo.svg')) ?>" alt="Logo SIM ORMAWA">
            <div><strong>SIM ORMAWA & HMJ</strong><small>Portal Manajemen Organisasi Mahasiswa</small></div>
        </a>
        <button class="nav-toggle" type="button" aria-label="Buka navigasi" data-nav-toggle>☰</button>
        <nav class="main-nav" data-main-nav>
            <a href="<?= e(route_url('home')) ?>#beranda">Beranda</a>
            <a href="<?= e(route_url('home')) ?>#struktur">Struktur</a>
            <a href="<?= e(route_url('home')) ?>#organisasi">Organisasi</a>
            <a href="<?= e(route_url('home')) ?>#jurusan">Jurusan &amp; Prodi</a>
            <a href="<?= e(route_url('home')) ?>#layanan">Layanan</a>
            <?php if ($user): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(route_url('dashboard')) ?>">Dashboard →</a>
            <?php else: ?>
                <a href="<?= e(route_url('register')) ?>">Ajukan Akun</a>
                <a class="btn btn-primary btn-sm" href="<?= e(route_url('login')) ?>">Portal Pengguna →</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php foreach ($flashes as $flash): ?>
<div class="toast toast-<?= e($flash['type']) ?>" data-toast><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<main><?= $content ?></main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <div class="brand footer-brand">
                <img src="<?= e(asset('img/logo.svg')) ?>" alt="Logo">
                <div><strong>SIM ORMAWA & HMJ</strong><small>Arsitektur Multi-Tenant</small></div>
            </div>
            <p>Platform terpusat untuk pengelolaan ORMAWA/UKM, HMJ, dan HIMA Politeknik Negeri Lampung dengan isolasi data setiap tenant.</p>
        </div>
        <div>
            <h4>Navigasi</h4>
            <a href="#struktur">Struktur Sistem</a>
            <a href="#organisasi">Direktori</a>
            <a href="#layanan">Layanan</a>
            <a href="#jurusan">Jurusan &amp; Prodi</a>
        </div>
        <div>
            <h4>Akses</h4>
            <a href="<?= e(route_url('login')) ?>">Masuk Portal</a>
            <a href="<?= e(route_url('register')) ?>">Ajukan Akun Tenant</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© <?= date('Y') ?> SIM ORMAWA &amp; HMJ &bull; Politeknik Negeri Lampung</span>
        <span>Dibangun untuk manajemen organisasi mahasiswa yang terstruktur.</span>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
