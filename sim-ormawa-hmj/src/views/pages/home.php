<?php
$title = 'Beranda';
$stats = ['ormawa'=>9,'hmj'=>8,'hima'=>0,'prodi'=>31];
$ormawa=[]; $hmj=[]; $jurusan=[];
try {
    $stats['ormawa']=(int)db()->query("SELECT COUNT(*) FROM tenants WHERE tenant_type='ORMAWA' AND status='ACTIVE'")->fetchColumn();
    $stats['hmj']=(int)db()->query("SELECT COUNT(*) FROM tenants WHERE tenant_type='HMJ' AND status='ACTIVE'")->fetchColumn();
    $stats['hima']=(int)db()->query("SELECT COUNT(*) FROM tenants WHERE tenant_type='HIMA' AND status='ACTIVE'")->fetchColumn();
    $stats['prodi']=(int)db()->query("SELECT COUNT(*) FROM program_studi WHERE status='AKTIF'")->fetchColumn();
    $ormawa=db()->query("SELECT tenant_id,nama_tenant FROM tenants WHERE tenant_type='ORMAWA' AND status='ACTIVE' ORDER BY nama_tenant")->fetchAll();
    $hmj=db()->query("SELECT t.tenant_id,t.nama_tenant,j.nama_jurusan FROM tenants t JOIN jurusan j ON j.jurusan_id=t.jurusan_id WHERE t.tenant_type='HMJ' AND t.status='ACTIVE' ORDER BY j.jurusan_id")->fetchAll();
    $jurusan=db()->query("SELECT j.jurusan_id,j.nama_jurusan,COUNT(ps.prodi_id) AS jumlah_prodi FROM jurusan j LEFT JOIN program_studi ps ON ps.jurusan_id=j.jurusan_id GROUP BY j.jurusan_id,j.nama_jurusan ORDER BY j.jurusan_id")->fetchAll();
} catch (Throwable) {
    $ormawa=array_map(fn($n)=>['nama_tenant'=>$n],['UKM Olahraga','UKM Seni','UKM Kopma','UKM Sukma','UKM EC','UKM Albana','UKM Poltapala','UKM Daving Club','UKM Garda']);
}
?>

<!-- ═══════════════════════════════════ HERO ═══════════════════════════════════ -->
<section class="hero" id="beranda">
    <div class="hero-bg"></div>
    <!-- Floating particles -->
    <div class="hero-particles" aria-hidden="true">
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
    </div>
    <div class="container hero-inner">
        <div class="hero-copy">
            <span class="eyebrow">✦ Terstruktur &bull; Cepat &bull; Terintegrasi</span>
            <h1>SISTEM INFORMASI<br>MANAJEMEN<br><span>ORMAWA &amp; HMJ</span></h1>
            <p>Kelola anggota, struktur organisasi, program kerja, proposal, kebutuhan, inventaris, keuangan, serta komunikasi HMJ–HIMA dalam satu portal multi-tenant.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(route_url('login')) ?>">Masuk Portal →</a>
                <a class="btn btn-ghost btn-lg" href="<?= e(route_url('register')) ?>">Ajukan Akun Tenant</a>
            </div>
        </div>
        <div class="hero-panel">
            <div class="hero-panel-head">Struktur Sistem</div>
            <div class="hierarchy-mini">
                <div class="node node-main">SUPER ADMIN</div>
                <div class="tree-split"><span></span><span></span></div>
                <div class="node-row"><div class="node">ORMAWA / UKM</div><div class="node">HMJ</div></div>
                <div class="tree-down"></div>
                <div class="node node-child">HIMA / PRODI</div>
            </div>
            <p class="hero-panel-note">ORMAWA dan HMJ sejajar. HIMA berada di bawah HMJ sesuai jurusan dan program studi.</p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ STATS ════════════════════════════════════ -->
<section class="stats-strip">
    <div class="container stats-grid">
        <div class="stat-card">
            <div>
                <strong><?= $stats['ormawa'] ?></strong>
                <span>ORMAWA / UKM</span>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <strong><?= $stats['hmj'] ?></strong>
                <span>HMJ Jurusan</span>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <strong><?= $stats['hima'] ?></strong>
                <span>HIMA Aktif</span>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <strong><?= $stats['prodi'] ?></strong>
                <span>Program Studi</span>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ ARSITEKTUR ═══════════════════════════════ -->
<section class="section" id="struktur">
    <div class="container">
        <div class="section-heading reveal">
            <span class="eyebrow dark">Arsitektur Multi-Tenant</span>
            <h2>Satu database, data setiap organisasi tetap terisolasi</h2>
            <p>Setiap data operasional menggunakan <code>tenant_id</code>. Tenant tidak dapat membaca data tenant lain, kecuali relasi HMJ dengan HIMA di bawahnya yang memang diizinkan.</p>
        </div>
        <div class="feature-grid three">
            <article class="feature-card">
                <div class="feature-icon">UKM</div>
                <h3>ORMAWA / UKM</h3>
                <p>Tenant mandiri yang sejajar dengan HMJ. Data anggota, program kerja, alat, dan keuangan hanya milik UKM tersebut.</p>
                <span class="feature-tag">Level 1</span>
            </article>
            <article class="feature-card">
                <div class="feature-icon">HMJ</div>
                <h3>HMJ</h3>
                <p>Satu tenant HMJ untuk setiap jurusan. HMJ dapat mengelola data sendiri sekaligus meninjau HIMA yang berada di bawah jurusannya.</p>
                <span class="feature-tag">Level 1</span>
            </article>
            <article class="feature-card">
                <div class="feature-icon">HIMA</div>
                <h3>HIMA / Prodi</h3>
                <p>Tenant HIMA terikat ke satu prodi dan satu HMJ induk. HIMA hanya dapat berkomunikasi dengan HMJ induknya.</p>
                <span class="feature-tag">Level 2</span>
            </article>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ LAYANAN ══════════════════════════════════ -->
<section class="section section-soft" id="layanan">
    <div class="container">
        <div class="section-heading left reveal">
            <span class="eyebrow dark">Layanan Utama</span>
            <h2>Manajemen organisasi dalam satu portal</h2>
        </div>
        <div class="service-grid">
            <?php foreach ([
                ['Anggota & Struktur','Kelola anggota, jabatan, periode, serta struktur kepengurusan secara lengkap.','👥'],
                ['Program Kerja','Rencanakan, ajukan, pantau, dan review program kerja organisasi.','📋'],
                ['Proposal & Kebutuhan','Dokumentasi proposal, kebutuhan kegiatan, dan pengajuan keuangan.','📄'],
                ['Inventaris','Catat alat, kondisi, jumlah, lokasi, dan nilai inventaris organisasi.','📦'],
                ['Keuangan','Pemasukan, pengeluaran, saldo, bukti transaksi, dan keterkaitan dengan proja.','💰'],
                ['Pesan HMJ–HIMA','Komunikasi terkontrol hanya antara HIMA dengan HMJ induknya.','💬'],
            ] as $i=>$item): ?>
                <div class="service-card">
                    <span class="service-num">0<?= $i+1 ?></span>
                    <h3><?= e($item[0]) ?></h3>
                    <p><?= e($item[1]) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ DIREKTORI ════════════════════════════════ -->
<section class="section" id="organisasi">
    <div class="container">
        <div class="section-heading left reveal">
            <span class="eyebrow dark">Direktori Organisasi</span>
            <h2>ORMAWA dan HMJ aktif</h2>
        </div>
        <div class="directory-columns">
            <div class="directory-card">
                <div class="directory-head">
                    <h3>ORMAWA / UKM</h3>
                    <span><?= count($ormawa) ?> organisasi</span>
                </div>
                <div class="directory-list">
                    <?php foreach($ormawa as $row): ?>
                    <div><span class="avatar-sm">U</span><b><?= e($row['nama_tenant']) ?></b><small>Tenant mandiri</small></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="directory-card">
                <div class="directory-head">
                    <h3>HMJ</h3>
                    <span><?= count($hmj) ?: 8 ?> jurusan</span>
                </div>
                <div class="directory-list">
                    <?php foreach($hmj as $row): ?>
                    <div><span class="avatar-sm">H</span><b><?= e($row['nama_tenant']) ?></b><small><?= e($row['nama_jurusan'] ?? 'Jurusan') ?></small></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ JURUSAN ══════════════════════════════════ -->
<section class="section section-green" id="jurusan">
    <div class="container">
        <div class="section-heading light reveal">
            <span class="eyebrow">Master Akademik</span>
            <h2>8 Jurusan dan 31 Program Studi</h2>
            <p>Program studi menjadi dasar pembentukan tenant HIMA dan secara otomatis dihubungkan ke HMJ pada jurusan yang sama.</p>
        </div>
        <div class="jurusan-grid">
            <?php foreach($jurusan as $i=>$row): ?>
            <div class="jurusan-card">
                <span><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span>
                <h3><?= e(str_replace('Jurusan ','',$row['nama_jurusan'])) ?></h3>
                <p><?= (int)$row['jumlah_prodi'] ?> program studi</p>
            </div>
            <?php endforeach; ?>
            <?php if(!$jurusan): foreach(['Budidaya Tanaman Pangan','Budidaya Tanaman Perkebunan','Teknologi Pertanian','Peternakan','Ekonomi dan Bisnis','Teknik','Perikanan dan Kelautan','Teknologi Informasi'] as $i=>$n): ?>
            <div class="jurusan-card">
                <span><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span>
                <h3><?= e($n) ?></h3>
                <p>Program studi terintegrasi</p>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>
