<?php
$title='Dashboard'; $u=current_user(); $cards=[]; $recent=[]; $pending=[];
if(is_super_admin($u)){
 $cards=[
  ['Tenant Aktif',(int)db()->query("SELECT COUNT(*) FROM tenants WHERE status='ACTIVE'")->fetchColumn(),'ORMAWA, HMJ, dan HIMA'],
  ['Menunggu Persetujuan',(int)db()->query("SELECT COUNT(*) FROM tenant_approvals WHERE status='PENDING' AND approval_target='SUPER_ADMIN'")->fetchColumn(),'Akun ORMAWA/HMJ'],
  ['Program Kerja',(int)db()->query("SELECT COUNT(*) FROM program_kerja pk JOIN tenants t ON t.tenant_id=pk.tenant_id WHERE t.tenant_type IN('ORMAWA','HMJ')")->fetchColumn(),'Monitoring ORMAWA & HMJ'],
  ['Jurusan / Prodi','8 / 31','Master akademik']
 ];
 $recent=db()->query("SELECT pk.nama_proja,pk.status_proja,t.nama_tenant,pk.created_at FROM program_kerja pk JOIN tenants t ON t.tenant_id=pk.tenant_id WHERE t.tenant_type IN('ORMAWA','HMJ') ORDER BY pk.created_at DESC LIMIT 6")->fetchAll();
 $pending=db()->query("SELECT ta.tenant_approval_id,t.nama_tenant,u.nama_lengkap,ta.requested_at FROM tenant_approvals ta JOIN tenants t ON t.tenant_id=ta.tenant_id LEFT JOIN users u ON u.user_id=ta.requested_by WHERE ta.status='PENDING' AND ta.approval_target='SUPER_ADMIN' ORDER BY ta.requested_at DESC LIMIT 5")->fetchAll();
}else{
 $tid=(int)$u['tenant_id'];
 $member=(int)(db()->prepare('SELECT COUNT(*) FROM members WHERE tenant_id=? AND status=\'AKTIF\'')->execute([$tid]) ?: 0);
 $s=db()->prepare("SELECT (SELECT COUNT(*) FROM members WHERE tenant_id=? AND status='AKTIF') members,(SELECT COUNT(*) FROM program_kerja WHERE tenant_id=?) proja,(SELECT COUNT(*) FROM assets WHERE tenant_id=?) assets,(SELECT COALESCE(SUM(CASE WHEN jenis_transaksi='PEMASUKAN' THEN nominal ELSE -nominal END),0) FROM financial_transactions WHERE tenant_id=?) saldo"); $s->execute([$tid,$tid,$tid,$tid]); $x=$s->fetch();
 $cards=[['Anggota Aktif',(int)$x['members'],'Data anggota tenant'],['Program Kerja',(int)$x['proja'],'Seluruh status'],['Inventaris',(int)$x['assets'],'Item tercatat'],['Saldo',rupiah($x['saldo']),'Pemasukan - pengeluaran']];
 $st=db()->prepare('SELECT nama_proja,status_proja,created_at FROM program_kerja WHERE tenant_id=? ORDER BY created_at DESC LIMIT 6');$st->execute([$tid]);$recent=$st->fetchAll();
 if(($u['tenant_type']??'')==='HMJ'){ $p=db()->prepare("SELECT ta.tenant_approval_id,t.nama_tenant,u.nama_lengkap,ta.requested_at FROM tenant_approvals ta JOIN tenants t ON t.tenant_id=ta.tenant_id LEFT JOIN users u ON u.user_id=ta.requested_by WHERE ta.status='PENDING' AND ta.approval_target='PARENT_HMJ' AND ta.reviewer_tenant_id=? ORDER BY ta.requested_at DESC LIMIT 5");$p->execute([$tid]);$pending=$p->fetchAll(); }
}
?>
<section class="dashboard-hero">
 <div><span class="eyebrow dark">Dashboard <?= e(is_super_admin($u)?'Super Admin':$u['tenant_type']) ?></span><h1>Selamat datang, <?= e($u['nama_lengkap']) ?></h1><p><?= is_super_admin($u)?'Pantau tenant ORMAWA dan HMJ, persetujuan akun, struktur, serta program kerja dari satu halaman.':'Kelola data organisasi '.$u['nama_tenant'].' dengan ruang kerja tenant yang terisolasi.' ?></p></div>
 <div class="dashboard-date"><small>Hari ini</small><strong><?= date('d') ?></strong><span><?= date('M Y') ?></span></div>
</section>
<div class="metric-grid"><?php foreach($cards as $c):?><article class="metric-card"><span><?= e($c[2]) ?></span><strong><?= is_numeric($c[1])?number_format((float)$c[1],0,',','.'):e($c[1]) ?></strong><h3><?= e($c[0]) ?></h3></article><?php endforeach;?></div>
<div class="dashboard-grid">
 <section class="panel"><div class="panel-head"><div><h2>Aktivitas Program Kerja</h2><p>Data terbaru yang relevan dengan akun Anda.</p></div><a class="text-link" href="<?= e(route_url('programs')) ?>">Lihat semua →</a></div>
  <div class="table-wrap"><table><thead><tr><th>Program</th><th>Tenant</th><th>Status</th><th>Tanggal</th></tr></thead><tbody><?php if(!$recent):?><tr><td colspan="4" class="empty-cell">Belum ada data program kerja.</td></tr><?php endif;?><?php foreach($recent as $r):?><tr><td><b><?= e($r['nama_proja']) ?></b></td><td><?= e($r['nama_tenant']??$u['nama_tenant']??'-') ?></td><td><?= status_badge($r['status_proja']) ?></td><td><?= e(indo_date($r['created_at'])) ?></td></tr><?php endforeach;?></tbody></table></div>
 </section>
 <aside class="panel"><div class="panel-head"><div><h2>Menunggu Persetujuan</h2><p><?= is_super_admin($u)?'Akun ORMAWA/HMJ':'HIMA di bawah HMJ Anda' ?></p></div></div>
  <div class="approval-list"><?php if(!$pending):?><div class="empty-state compact">Tidak ada pengajuan baru.</div><?php endif;?><?php foreach($pending as $p):?><a href="<?= e(route_url('tenants')) ?>"><span class="avatar-sm">A</span><div><b><?= e($p['nama_tenant']) ?></b><small><?= e($p['nama_lengkap']??'Pemohon') ?> • <?= e(indo_date($p['requested_at'])) ?></small></div><span>→</span></a><?php endforeach;?></div>
 </aside>
</div>
