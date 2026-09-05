<?php $title='Masuk Portal'; ?>
<section class="auth-section">
    <div class="auth-visual"><div><span class="eyebrow">Portal Multi-Tenant</span><h1>Kelola organisasi Anda dengan data yang terisolasi dan terstruktur.</h1><p>Super Admin, ORMAWA, HMJ, dan HIMA memiliki ruang kerja serta hak akses masing-masing.</p></div></div>
    <div class="auth-card-wrap">
        <form class="auth-card" method="post" action="<?= e(route_url('login')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="login">
            <div class="auth-logo"><img src="<?= e(asset('img/logo.svg')) ?>" alt="Logo"><div><strong>SIM ORMAWA & HMJ</strong><small>Portal Pengguna</small></div></div>
            <h2>Selamat datang kembali</h2><p>Masukkan akun yang sudah disetujui.</p>
            <label>Email<input type="email" name="email" placeholder="nama@email.com" required autocomplete="email"></label>
            <label>Password<div class="password-field"><input type="password" name="password" placeholder="••••••••" required minlength="8" autocomplete="current-password" data-password><button type="button" data-toggle-password>Lihat</button></div></label>
            <button class="btn btn-primary btn-block" type="submit">Masuk ke Dashboard →</button>
            <div class="auth-divider"><span>atau</span></div>
            <a class="btn btn-light btn-block" href="<?= e(route_url('register')) ?>">Ajukan Akun Tenant</a>
            <small class="auth-help">Akun baru harus dikonfirmasi terlebih dahulu sesuai alur tenant.</small>
        </form>
    </div>
</section>
