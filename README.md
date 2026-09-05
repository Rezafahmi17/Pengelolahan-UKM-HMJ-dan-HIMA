# SIM ORMAWA & HMJ — Multi-Tenant

Website **Sistem Informasi Manajemen ORMAWA dan HMJ Berbasis Web dengan Arsitektur Multi-Tenant** yang terhubung langsung ke skema MySQL yang diberikan.

## Struktur organisasi yang diterapkan

```text
SUPER ADMIN
├── ORMAWA / UKM      ← sejajar dengan HMJ
└── HMJ               ← satu HMJ per jurusan
    └── HIMA / Prodi  ← berada di bawah HMJ
```

Aturan isolasi:
- ORMAWA tidak dapat membaca data ORMAWA lain.
- HMJ tidak dapat membaca data HMJ lain.
- HIMA tidak dapat membaca data HIMA lain.
- HIMA hanya dapat berkomunikasi dengan HMJ induknya.
- HMJ dapat melihat struktur, proja/pengajuan, dan akun HIMA di bawahnya sesuai fungsi aplikasi.
- Super Admin memonitor ORMAWA/HMJ dan memproses persetujuan akun ORMAWA/HMJ.

## Teknologi

- PHP 8.2+ (native, PDO)
- MySQL 8.0+
- HTML5 + CSS3 + JavaScript murni
- Session authentication + password hashing
- CSRF protection
- Responsive layout
- Tidak membutuhkan Composer / npm

## Modul yang sudah tersedia

- Landing page seperti referensi: utility bar hijau, navbar putih, tombol portal kuning, hero besar, direktori organisasi.
- Login dan pengajuan akun tenant.
- Dashboard Super Admin / ORMAWA / HMJ / HIMA.
- Persetujuan akun ORMAWA/HMJ oleh Super Admin.
- Persetujuan tenant HIMA oleh HMJ induk.
- Data anggota.
- Periode, jabatan, dan struktur organisasi.
- Program kerja + submit/review/status berjalan/selesai.
- Proposal proja.
- Pengajuan kebutuhan.
- Pengajuan keuangan program kerja.
- Inventaris / alat.
- Pemasukan dan pengeluaran.
- Pesan HMJ ↔ HIMA.
- Master 8 jurusan dan 31 program studi.
- Audit log dan notifikasi menggunakan tabel pada database.

---

# Cara menjalankan di VS Code

## 1. Pastikan aplikasi tersedia

- PHP 8.2 atau lebih baru
- MySQL 8.0 atau lebih baru
- VS Code

Cek dari terminal:

```bash
php -v
```

## 2. Import database

Import file:

```text
database/schema.sql
```

Ke MySQL menggunakan MySQL Workbench, phpMyAdmin, DBeaver, atau terminal MySQL.

**Perhatian:** file schema memiliki `DROP DATABASE IF EXISTS sim_ormawa_multitenant;`. Jangan jalankan pada database produksi yang sudah berisi data tanpa backup.

## 3. Buat file `.env`

Salin:

```text
.env.example
```

menjadi:

```text
.env
```

Contoh untuk XAMPP/MySQL lokal:

```env
APP_NAME="SIM ORMAWA & HMJ"
APP_URL="http://127.0.0.1:8000"
APP_ENV="local"
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sim_ormawa_multitenant
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan username/password MySQL Anda.

## 4. Buat akun Super Admin

Pilihan paling aman:

```bash
php tools/create_admin.php
```

Default yang dibuat:

```text
Email    : admin@simormawa.test
Password : admin123
```

Atau tentukan sendiri:

```bash
php tools/create_admin.php admin@email.com PasswordKuat123 "Nama Admin"
```

## 5. Opsional: isi data demo

Jika ingin langsung mencoba HMJ, HIMA, approval, proja, inventaris, dan keuangan, import:

```text
database/demo_seed.sql
```

Akun demo (`password: admin123`):

```text
Super Admin : admin@simormawa.test
UKM Olahraga: olahraga@simormawa.test
HMJ TI      : hmjti@simormawa.test
HIMA MI     : himami@simormawa.test
```

`himatrpl@simormawa.test` sengaja dibuat **PENDING** supaya alur persetujuan HIMA oleh HMJ TI bisa diuji.

## 6. Jalankan website

Dari folder project:

```bash
php -S 127.0.0.1:8000 -t public
```

Kemudian buka browser:

```text
http://127.0.0.1:8000
```

---

# Cara menjalankan memakai XAMPP

1. Copy folder project ke `C:\xampp\htdocs\sim-ormawa-hmj`.
2. Jalankan Apache dan MySQL.
3. Import `database/schema.sql` lewat phpMyAdmin.
4. Buat `.env` dengan koneksi MySQL XAMPP.
5. Buka terminal di folder project dan jalankan `php tools/create_admin.php`.
6. Karena front controller berada di folder `public`, cara termudah tetap:

```bash
php -S 127.0.0.1:8000 -t public
```

---

# Isolasi tenant di backend

MySQL tidak memiliki Row Level Security seperti PostgreSQL. Karena itu aplikasi **tidak mengambil `tenant_id` dari form pengguna untuk query data organisasi**. `tenant_id` diambil dari user yang sedang login/session.

Contoh konsep query:

```sql
SELECT *
FROM program_kerja
WHERE tenant_id = :tenant_id_dari_session;
```

Untuk HMJ yang membaca data HIMA, aplikasi hanya mengizinkan HIMA dengan:

```sql
parent_tenant_id = :tenant_id_hmj_login
```

Database juga sudah memiliki trigger komunikasi yang hanya mengizinkan:

```text
HIMA ↔ HMJ induk
```

---

# Struktur folder

```text
sim-ormawa-hmj/
├── .env.example
├── README.md
├── database/
│   ├── schema.sql
│   └── demo_seed.sql
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/app.css
│   │   ├── js/app.js
│   │   └── img/
│   └── uploads/
├── src/
│   ├── Auth.php
│   ├── Database.php
│   ├── Env.php
│   ├── Actions.php
│   ├── helpers.php
│   ├── bootstrap.php
│   ├── view.php
│   └── views/
└── tools/
    └── create_admin.php
```
