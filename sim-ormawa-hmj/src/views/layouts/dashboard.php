<?php
$user = require_login();
$flashes = pull_flashes();
$title = $title ?? 'Dashboard';
$pageNow = $_GET['page'] ?? 'dashboard';
$tenantLabel = is_super_admin($user) ? 'Super Admin' : ($user['nama_tenant'] ?? 'Tenant');
$tenantType = is_super_admin($user) ? 'PUSAT' : ($user['tenant_type'] ?? '-');

$menu = [
    ['dashboard', 'Dashboard'],
];
if (is_super_admin($user)) {
    $menu[] = ['tenants', 'Tenant & Persetujuan'];
    $menu[] = ['programs', 'Monitoring Proja'];
    $menu[] = ['structure', 'Struktur Organisasi'];
    $menu[] = ['master', 'Jurusan & Prodi'];
} else {
    $menu[] = ['members', 'Anggota'];
    $menu[] = ['structure', 'Struktur'];
    $menu[] = ['programs', 'Program Kerja'];
    $menu[] = ['submissions', 'Pengajuan'];
    $menu[] = ['assets', 'Inventaris'];
    $menu[] = ['finance', 'Keuangan'];
    if (($user['tenant_type'] ?? null) === 'HMJ') $menu[] = ['tenants', 'HIMA & Persetujuan'];
    if (in_array(($user['tenant_type'] ?? ''), ['HMJ', 'HIMA'], true)) $menu[] = ['messages', 'Pesan HMJ–HIMA'];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> — SIM ORMAWA & HMJ</title>
    <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<div class="utility-bar app-utility">
    <div class="container utility-inner">
        <div><span class="dot-live"></span> <?= e($tenantLabel) ?> <span class="tenant-pill"><?= e($tenantType) ?></span></div>
        <div class="utility-links"><span><?= e($user['nama_lengkap']) ?></span><span><?= e($user['role']) ?></span></div>
    </div>
</div>
<header class="main-header app-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(route_url('dashboard')) ?>">
            <img src="<?= e(asset('img/logo.svg')) ?>" alt="Logo SIM ORMAWA">
            <div><strong>SIM ORMAWA & HMJ</strong><small><?= e($tenantLabel) ?></small></div>
        </a>
        <button class="nav-toggle" type="button" data-nav-toggle>☰</button>
        <nav class="main-nav dashboard-nav" data-main-nav>
            <?php foreach ($menu as [$key, $label]): ?>
                <a class="<?= $pageNow === $key ? 'active' : '' ?>" href="<?= e(route_url($key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-light btn-sm" href="<?= e(route_url('home')) ?>">Website</a>
            <a class="btn btn-danger-soft btn-sm" href="<?= e(route_url('logout')) ?>">Keluar</a>
        </nav>
    </div>
</header>

<?php foreach ($flashes as $flash): ?>
<div class="toast toast-<?= e($flash['type']) ?>" data-toast><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<main class="app-main">
    <div class="container">
        <?= $content ?>
    </div>
</main>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
