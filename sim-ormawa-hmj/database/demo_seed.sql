USE sim_ormawa_multitenant;

-- ============================================================
-- DEMO LOGIN
-- Password seluruh akun demo: admin123
-- Jalankan file ini HANYA untuk demo/pengembangan lokal.
-- ============================================================
SET @demo_hash = '$2y$12$izRDk7pLZwcao.jC3G5wC.kgbOYPDKvAcNcmXZKs9UUOl7N0QsORG';

-- 1) Super Admin
INSERT INTO users (tenant_id,nama_lengkap,email,password_hash,role,status)
SELECT NULL,'Super Admin','admin@simormawa.test',@demo_hash,'SUPER_ADMIN','ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='admin@simormawa.test');

-- 2) Admin UKM Olahraga
INSERT INTO users (tenant_id,nama_lengkap,email,password_hash,role,status)
SELECT t.tenant_id,'Admin UKM Olahraga','olahraga@simormawa.test',@demo_hash,'ADMIN_TENANT','ACTIVE'
FROM tenants t
WHERE t.kode_tenant='UKM-OLAHRAGA'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email='olahraga@simormawa.test');

-- 3) Admin HMJ Teknologi Informasi
INSERT INTO users (tenant_id,nama_lengkap,email,password_hash,role,status)
SELECT t.tenant_id,'Admin HMJ Teknologi Informasi','hmjti@simormawa.test',@demo_hash,'ADMIN_TENANT','ACTIVE'
FROM tenants t
WHERE t.kode_tenant='HMJ-TI'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email='hmjti@simormawa.test');

-- 4) HIMA Manajemen Informatika aktif untuk demo HMJ <-> HIMA
INSERT INTO tenants
(kode_tenant,nama_tenant,tenant_type,jurusan_id,prodi_id,parent_tenant_id,status,approved_at)
SELECT 'HIMA-D3-MI','HIMA Manajemen Informatika','HIMA',ps.jurusan_id,ps.prodi_id,hmj.tenant_id,'ACTIVE',NOW()
FROM program_studi ps
JOIN tenants hmj ON hmj.tenant_type='HMJ' AND hmj.jurusan_id=ps.jurusan_id
WHERE ps.kode_prodi='D3-MI'
  AND NOT EXISTS (SELECT 1 FROM tenants x WHERE x.tenant_type='HIMA' AND x.prodi_id=ps.prodi_id);

INSERT INTO users (tenant_id,nama_lengkap,email,password_hash,role,status)
SELECT t.tenant_id,'Admin HIMA Manajemen Informatika','himami@simormawa.test',@demo_hash,'ADMIN_TENANT','ACTIVE'
FROM tenants t
WHERE t.kode_tenant='HIMA-D3-MI'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email='himami@simormawa.test');

-- 5) Satu HIMA pending agar alur konfirmasi HMJ dapat langsung diuji.
INSERT INTO tenants
(kode_tenant,nama_tenant,tenant_type,jurusan_id,prodi_id,parent_tenant_id,status)
SELECT 'HIMA-D4-TRPL','HIMA Teknologi Rekayasa Perangkat Lunak','HIMA',ps.jurusan_id,ps.prodi_id,hmj.tenant_id,'PENDING'
FROM program_studi ps
JOIN tenants hmj ON hmj.tenant_type='HMJ' AND hmj.jurusan_id=ps.jurusan_id
WHERE ps.kode_prodi='D4-TRPL'
  AND NOT EXISTS (SELECT 1 FROM tenants x WHERE x.tenant_type='HIMA' AND x.prodi_id=ps.prodi_id);

INSERT INTO users (tenant_id,nama_lengkap,email,password_hash,role,status)
SELECT t.tenant_id,'Calon Admin HIMA TRPL','himatrpl@simormawa.test',@demo_hash,'ADMIN_TENANT','PENDING'
FROM tenants t
WHERE t.kode_tenant='HIMA-D4-TRPL'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email='himatrpl@simormawa.test');

INSERT INTO tenant_approvals
(tenant_id,requested_by,reviewer_tenant_id,approval_target,status)
SELECT hima.tenant_id,u.user_id,hmj.tenant_id,'PARENT_HMJ','PENDING'
FROM tenants hima
JOIN users u ON u.tenant_id=hima.tenant_id AND u.email='himatrpl@simormawa.test'
JOIN tenants hmj ON hmj.tenant_id=hima.parent_tenant_id
WHERE hima.kode_tenant='HIMA-D4-TRPL'
  AND NOT EXISTS (
    SELECT 1 FROM tenant_approvals ta
    WHERE ta.tenant_id=hima.tenant_id AND ta.status='PENDING'
  );

-- Contoh anggota/proja/aset/keuangan hanya untuk HIMA MI.
SET @hima_mi = (SELECT tenant_id FROM tenants WHERE kode_tenant='HIMA-D3-MI' LIMIT 1);
SET @user_hima = (SELECT user_id FROM users WHERE email='himami@simormawa.test' LIMIT 1);
SET @prodi_mi = (SELECT prodi_id FROM program_studi WHERE kode_prodi='D3-MI' LIMIT 1);

INSERT INTO members (tenant_id,prodi_id,npm,nama_lengkap,jenis_kelamin,angkatan,email,status)
SELECT @hima_mi,@prodi_mi,'24781001','Andi Pratama','L',2024,'andi@example.test','AKTIF'
WHERE @hima_mi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM members WHERE tenant_id=@hima_mi AND npm='24781001');

INSERT INTO members (tenant_id,prodi_id,npm,nama_lengkap,jenis_kelamin,angkatan,email,status)
SELECT @hima_mi,@prodi_mi,'24781002','Siti Rahma','P',2024,'siti@example.test','AKTIF'
WHERE @hima_mi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM members WHERE tenant_id=@hima_mi AND npm='24781002');

INSERT INTO program_kerja (tenant_id,nama_proja,deskripsi,tujuan,tanggal_mulai,tanggal_selesai,lokasi,penanggung_jawab,estimasi_anggaran,status_proja,created_by)
SELECT @hima_mi,'Workshop Web Development','Pelatihan dasar pengembangan web untuk mahasiswa.','Meningkatkan keterampilan teknis anggota.',DATE_ADD(CURDATE(),INTERVAL 14 DAY),DATE_ADD(CURDATE(),INTERVAL 14 DAY),'Lab Komputer','Ketua Pelaksana',2500000,'DIAJUKAN',@user_hima
WHERE @hima_mi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM program_kerja WHERE tenant_id=@hima_mi AND nama_proja='Workshop Web Development');

INSERT INTO assets (tenant_id,kode_asset,nama_asset,kategori,jumlah,jumlah_baik,jumlah_rusak,kondisi,lokasi,nilai_asset)
SELECT @hima_mi,'AST-001','Proyektor','Elektronik',1,1,0,'BAIK','Sekretariat',4500000
WHERE @hima_mi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM assets WHERE tenant_id=@hima_mi AND kode_asset='AST-001');

INSERT INTO financial_transactions (tenant_id,jenis_transaksi,kategori,tanggal_transaksi,nominal,deskripsi,created_by)
SELECT @hima_mi,'PEMASUKAN','Kas Anggota',CURDATE(),3000000,'Kas awal periode',@user_hima
WHERE @hima_mi IS NOT NULL AND NOT EXISTS (SELECT 1 FROM financial_transactions WHERE tenant_id=@hima_mi AND kategori='Kas Anggota');
