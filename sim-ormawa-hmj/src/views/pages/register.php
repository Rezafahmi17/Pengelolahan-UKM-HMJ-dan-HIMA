<?php
$title='Ajukan Akun Tenant';
$ormawa=$hmj=$prodi=[];
try{
 $ormawa=db()->query("SELECT t.tenant_id,t.nama_tenant FROM tenants t WHERE t.tenant_type='ORMAWA' AND t.status='ACTIVE' AND NOT EXISTS(SELECT 1 FROM users u WHERE u.tenant_id=t.tenant_id AND u.role='ADMIN_TENANT' AND u.status IN('PENDING','ACTIVE')) ORDER BY t.nama_tenant")->fetchAll();
 $hmj=db()->query("SELECT t.tenant_id,t.nama_tenant,j.nama_jurusan FROM tenants t JOIN jurusan j ON j.jurusan_id=t.jurusan_id WHERE t.tenant_type='HMJ' AND t.status='ACTIVE' AND NOT EXISTS(SELECT 1 FROM users u WHERE u.tenant_id=t.tenant_id AND u.role='ADMIN_TENANT' AND u.status IN('PENDING','ACTIVE')) ORDER BY j.jurusan_id")->fetchAll();
 $prodi=db()->query("SELECT ps.prodi_id,ps.jenjang,ps.nama_prodi,j.nama_jurusan FROM program_studi ps JOIN jurusan j ON j.jurusan_id=ps.jurusan_id WHERE ps.status='AKTIF' AND NOT EXISTS(SELECT 1 FROM tenants t WHERE t.tenant_type='HIMA' AND t.prodi_id=ps.prodi_id AND t.status IN('PENDING','ACTIVE')) ORDER BY j.jurusan_id,ps.nama_prodi")->fetchAll();
}catch(Throwable){}
?>
<section class="auth-section register-section">
    <div class="auth-visual register-visual"><div><span class="eyebrow">Alur Persetujuan</span><h1>Ajukan akun tenant sesuai organisasi Anda.</h1><div class="approval-flow"><div><b>ORMAWA / HMJ</b><span>Pengajuan → Super Admin → Aktif</span></div><div><b>HIMA</b><span>Pengajuan → HMJ Induk → Aktif</span></div></div></div></div>
    <div class="auth-card-wrap">
        <form class="auth-card wide" method="post" action="<?= e(route_url('register')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="register_account">
            <div class="auth-logo"><img src="<?= e(asset('img/logo.svg')) ?>" alt="Logo"><div><strong>Pengajuan Akun</strong><small>SIM ORMAWA & HMJ</small></div></div>
            <h2>Daftarkan admin tenant</h2><p>Pilih jenis organisasi lalu isi data penanggung jawab akun.</p>
            <label>Jenis Tenant<select name="tenant_type" required data-tenant-type><option value="">Pilih jenis tenant</option><option value="ORMAWA">ORMAWA / UKM</option><option value="HMJ">HMJ</option><option value="HIMA">HIMA / Prodi</option></select></label>
            <div data-tenant-select="ORMAWA" class="conditional-field hidden"><label>Pilih ORMAWA<select name="tenant_id_ormawa" data-disable-when-hidden><option value="">Pilih ORMAWA</option><?php foreach($ormawa as $r):?><option value="<?= (int)$r['tenant_id'] ?>"><?= e($r['nama_tenant']) ?></option><?php endforeach;?></select></label></div>
            <div data-tenant-select="HMJ" class="conditional-field hidden"><label>Pilih HMJ<select name="tenant_id_hmj" data-disable-when-hidden><option value="">Pilih HMJ</option><?php foreach($hmj as $r):?><option value="<?= (int)$r['tenant_id'] ?>"><?= e($r['nama_tenant']) ?></option><?php endforeach;?></select></label></div>
            <div data-tenant-select="HIMA" class="conditional-field hidden"><label>Program Studi<select name="prodi_id" data-disable-when-hidden><option value="">Pilih program studi</option><?php $g='';foreach($prodi as $r): if($g!==$r['nama_jurusan']){if($g!=='')echo '</optgroup>'; $g=$r['nama_jurusan']; echo '<optgroup label="'.e($g).'">';}?><option value="<?= (int)$r['prodi_id'] ?>"><?= e($r['jenjang'].' '.$r['nama_prodi']) ?></option><?php endforeach;if($g!=='')echo '</optgroup>';?></select></label></div>
            <div class="form-grid two"><label>Nama Lengkap<input type="text" name="nama_lengkap" required placeholder="Nama admin tenant"></label><label>Email<input type="email" name="email" required placeholder="admin@organisasi.id"></label></div>
            <label>Password<input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"></label>
            <div class="notice"><b>Catatan:</b> ORMAWA/HMJ menunggu konfirmasi Super Admin. HIMA menunggu konfirmasi HMJ pada jurusannya.</div>
            <button class="btn btn-primary btn-block" type="submit">Kirim Pengajuan →</button>
            <a class="text-link" href="<?= e(route_url('login')) ?>">Sudah punya akun? Masuk di sini</a>
        </form>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const type=document.querySelector('[data-tenant-type]');
 const apply=()=>{
   document.querySelectorAll('[data-tenant-select]').forEach(box=>{
     const show=box.dataset.tenantSelect===type.value;
     box.classList.toggle('hidden',!show);
     box.querySelectorAll('select,input').forEach(el=>el.disabled=!show);
   });
 };
 type?.addEventListener('change',apply); apply();
});
</script>
