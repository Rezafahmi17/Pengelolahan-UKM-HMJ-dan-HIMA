

DROP DATABASE IF EXISTS sim_ormawa_multitenant;
CREATE DATABASE sim_ormawa_multitenant
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sim_ormawa_multitenant;

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ============================================================
-- 1. MASTER JURUSAN
-- ============================================================
CREATE TABLE jurusan (
    jurusan_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_jurusan    VARCHAR(20) NOT NULL UNIQUE,
    nama_jurusan    VARCHAR(150) NOT NULL UNIQUE,
    status          ENUM('AKTIF','NONAKTIF') NOT NULL DEFAULT 'AKTIF',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. MASTER PROGRAM STUDI
-- ============================================================
CREATE TABLE program_studi (
    prodi_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jurusan_id      BIGINT UNSIGNED NOT NULL,
    kode_prodi      VARCHAR(30) NOT NULL UNIQUE,
    jenjang         ENUM('D3','D4') NOT NULL,
    nama_prodi      VARCHAR(180) NOT NULL,
    status          ENUM('AKTIF','NONAKTIF') NOT NULL DEFAULT 'AKTIF',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_prodi_jurusan
      FOREIGN KEY (jurusan_id) REFERENCES jurusan(jurusan_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_prodi_jurusan_nama
      UNIQUE (jurusan_id, nama_prodi)
) ENGINE=InnoDB;

-- ============================================================
-- 3. TENANT
--    ORMAWA dan HMJ sejajar.
--    HIMA berada di bawah HMJ melalui parent_tenant_id.
-- ============================================================
CREATE TABLE tenants (
    tenant_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_tenant         VARCHAR(40) NOT NULL UNIQUE,
    nama_tenant         VARCHAR(180) NOT NULL,
    tenant_type         ENUM('ORMAWA','HMJ','HIMA') NOT NULL,

    jurusan_id          BIGINT UNSIGNED NULL,
    prodi_id            BIGINT UNSIGNED NULL,
    parent_tenant_id    BIGINT UNSIGNED NULL,

    status              ENUM('PENDING','ACTIVE','REJECTED','SUSPENDED')
                        NOT NULL DEFAULT 'PENDING',

    logo_path           VARCHAR(255) NULL,
    deskripsi           TEXT NULL,

    approved_at         DATETIME NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

    -- Menjamin hanya ada satu HMJ per jurusan.
    hmj_jurusan_key BIGINT UNSIGNED
      GENERATED ALWAYS AS (
        CASE WHEN tenant_type = 'HMJ' THEN jurusan_id ELSE NULL END
      ) STORED,

    -- Menjamin hanya ada satu HIMA per program studi.
    hima_prodi_key BIGINT UNSIGNED
      GENERATED ALWAYS AS (
        CASE WHEN tenant_type = 'HIMA' THEN prodi_id ELSE NULL END
      ) STORED,

    CONSTRAINT fk_tenant_jurusan
      FOREIGN KEY (jurusan_id) REFERENCES jurusan(jurusan_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_tenant_prodi
      FOREIGN KEY (prodi_id) REFERENCES program_studi(prodi_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_tenant_parent
      FOREIGN KEY (parent_tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_hmj_per_jurusan UNIQUE (hmj_jurusan_key),
    CONSTRAINT uq_hima_per_prodi UNIQUE (hima_prodi_key),

    CONSTRAINT chk_tenant_structure CHECK (
        (tenant_type = 'ORMAWA'
            AND jurusan_id IS NULL
            AND prodi_id IS NULL
            AND parent_tenant_id IS NULL)
        OR
        (tenant_type = 'HMJ'
            AND jurusan_id IS NOT NULL
            AND prodi_id IS NULL
            AND parent_tenant_id IS NULL)
        OR
        (tenant_type = 'HIMA'
            AND jurusan_id IS NOT NULL
            AND prodi_id IS NOT NULL
            AND parent_tenant_id IS NOT NULL)
    )
) ENGINE=InnoDB;

CREATE INDEX idx_tenants_type_status
    ON tenants(tenant_type, status);

CREATE INDEX idx_tenants_parent
    ON tenants(parent_tenant_id);

-- ============================================================
-- 4. USERS / AKUN
--    SUPER_ADMIN -> tenant_id harus NULL.
--    ADMIN_TENANT/PENGURUS -> tenant_id wajib terisi.
-- ============================================================
CREATE TABLE users (
    user_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NULL,
    nama_lengkap    VARCHAR(150) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('SUPER_ADMIN','ADMIN_TENANT','PENGURUS')
                    NOT NULL,
    status          ENUM('PENDING','ACTIVE','REJECTED','SUSPENDED')
                    NOT NULL DEFAULT 'PENDING',
    last_login_at   DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_user_tenant_role CHECK (
        (role = 'SUPER_ADMIN' AND tenant_id IS NULL)
        OR
        (role IN ('ADMIN_TENANT','PENGURUS') AND tenant_id IS NOT NULL)
    )
) ENGINE=InnoDB;

CREATE INDEX idx_users_tenant ON users(tenant_id);
CREATE INDEX idx_users_role_status ON users(role, status);

-- ============================================================
-- 5. PERSETUJUAN / KONFIRMASI TENANT
--    ORMAWA/HMJ -> Super Admin
--    HIMA       -> HMJ induk
-- ============================================================
CREATE TABLE tenant_approvals (
    tenant_approval_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    requested_by        BIGINT UNSIGNED NULL,
    reviewer_user_id    BIGINT UNSIGNED NULL,
    reviewer_tenant_id  BIGINT UNSIGNED NULL,

    approval_target     ENUM('SUPER_ADMIN','PARENT_HMJ') NOT NULL,
    status              ENUM('PENDING','APPROVED','REJECTED')
                        NOT NULL DEFAULT 'PENDING',
    catatan             TEXT NULL,
    requested_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at         DATETIME NULL,

    CONSTRAINT fk_tenant_approval_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_tenant_approval_requester
      FOREIGN KEY (requested_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_tenant_approval_reviewer
      FOREIGN KEY (reviewer_user_id) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_tenant_approval_reviewer_tenant
      FOREIGN KEY (reviewer_tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_tenant_approval_status
    ON tenant_approvals(status, approval_target);

-- ============================================================
-- 6. DATA ANGGOTA
-- ============================================================
CREATE TABLE members (
    member_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    prodi_id        BIGINT UNSIGNED NULL,
    npm             VARCHAR(30) NULL,
    nama_lengkap    VARCHAR(150) NOT NULL,
    jenis_kelamin   ENUM('L','P') NULL,
    angkatan        YEAR NULL,
    email           VARCHAR(190) NULL,
    no_hp           VARCHAR(30) NULL,
    status          ENUM('AKTIF','NONAKTIF','ALUMNI')
                    NOT NULL DEFAULT 'AKTIF',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_member_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_member_prodi
      FOREIGN KEY (prodi_id) REFERENCES program_studi(prodi_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT uq_member_tenant_npm UNIQUE (tenant_id, npm)
) ENGINE=InnoDB;

CREATE INDEX idx_members_tenant_status
    ON members(tenant_id, status);

-- ============================================================
-- 7. PERIODE KEPENGURUSAN
-- ============================================================
CREATE TABLE organization_periods (
    period_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    nama_periode    VARCHAR(100) NOT NULL,
    tanggal_mulai   DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status          ENUM('AKTIF','SELESAI') NOT NULL DEFAULT 'AKTIF',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_period_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_period_tenant_name
      UNIQUE (tenant_id, nama_periode)
) ENGINE=InnoDB;

-- ============================================================
-- 8. JABATAN
-- ============================================================
CREATE TABLE positions (
    position_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    nama_jabatan    VARCHAR(100) NOT NULL,
    urutan          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_position_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_position_tenant
      UNIQUE (tenant_id, nama_jabatan)
) ENGINE=InnoDB;

-- ============================================================
-- 9. DATA STRUKTUR ORGANISASI
--    Struktur ORMAWA/HMJ dapat dilihat Super Admin.
--    Struktur HIMA dapat dilihat HMJ induknya.
-- ============================================================
CREATE TABLE organization_structures (
    structure_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    period_id       BIGINT UNSIGNED NOT NULL,
    member_id       BIGINT UNSIGNED NOT NULL,
    position_id     BIGINT UNSIGNED NOT NULL,
    mulai_jabatan   DATE NULL,
    selesai_jabatan DATE NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_structure_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_structure_period
      FOREIGN KEY (period_id) REFERENCES organization_periods(period_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_structure_member
      FOREIGN KEY (member_id) REFERENCES members(member_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_structure_position
      FOREIGN KEY (position_id) REFERENCES positions(position_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_structure_member_period
      UNIQUE (tenant_id, period_id, member_id, position_id)
) ENGINE=InnoDB;

CREATE INDEX idx_structure_tenant
    ON organization_structures(tenant_id, period_id);

-- ============================================================
-- 10. DATA PROGRAM KERJA / PROJA
--
-- Untuk ORMAWA & HMJ:
--   dapat dimonitor Super Admin.
--
-- Untuk HIMA:
--   diajukan ke HMJ induk dan dapat di-ACC oleh HMJ tersebut.
-- ============================================================
CREATE TABLE program_kerja (
    proja_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    nama_proja          VARCHAR(180) NOT NULL,
    deskripsi           TEXT NULL,
    tujuan              TEXT NULL,
    tanggal_mulai       DATE NULL,
    tanggal_selesai     DATE NULL,
    lokasi              VARCHAR(180) NULL,
    penanggung_jawab    VARCHAR(150) NULL,
    estimasi_anggaran   DECIMAL(15,2) NOT NULL DEFAULT 0,

    status_proja        ENUM(
                            'DRAFT',
                            'DIAJUKAN',
                            'REVISI',
                            'DISETUJUI',
                            'DITOLAK',
                            'BERJALAN',
                            'SELESAI'
                        ) NOT NULL DEFAULT 'DRAFT',

    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at         DATETIME NULL,
    catatan_review      TEXT NULL,

    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_proja_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_proja_reviewer
      FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_proja_creator
      FOREIGN KEY (created_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_proja_tenant_status
    ON program_kerja(tenant_id, status_proja);

-- ============================================================
-- 11. PENGAJUAN / PEMASUKAN PROPOSAL PROJA
-- ============================================================
CREATE TABLE program_proposals (
    proposal_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    proja_id            BIGINT UNSIGNED NOT NULL,
    nomor_proposal      VARCHAR(100) NULL,
    judul_proposal      VARCHAR(200) NOT NULL,
    file_path           VARCHAR(255) NULL,
    tanggal_pengajuan   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status              ENUM('DRAFT','DIAJUKAN','REVISI','DITERIMA','DITOLAK')
                        NOT NULL DEFAULT 'DRAFT',
    catatan             TEXT NULL,
    submitted_by        BIGINT UNSIGNED NULL,
    reviewed_by         BIGINT UNSIGNED NULL,
    reviewed_at         DATETIME NULL,

    CONSTRAINT fk_proposal_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_proposal_proja
      FOREIGN KEY (proja_id) REFERENCES program_kerja(proja_id)
      ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_proposal_submitter
      FOREIGN KEY (submitted_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_proposal_reviewer
      FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_proposal_tenant_status
    ON program_proposals(tenant_id, status);

-- ============================================================
-- 12. PENGAJUAN DATA KEBUTUHAN
-- ============================================================
CREATE TABLE program_needs (
    need_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    proja_id            BIGINT UNSIGNED NOT NULL,
    judul_pengajuan     VARCHAR(180) NOT NULL,
    keterangan          TEXT NULL,
    total_estimasi      DECIMAL(15,2) NOT NULL DEFAULT 0,
    status              ENUM('DRAFT','DIAJUKAN','REVISI','DITERIMA','DITOLAK')
                        NOT NULL DEFAULT 'DRAFT',
    submitted_by        BIGINT UNSIGNED NULL,
    reviewed_by         BIGINT UNSIGNED NULL,
    submitted_at        DATETIME NULL,
    reviewed_at         DATETIME NULL,
    catatan_review      TEXT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_need_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_need_proja
      FOREIGN KEY (proja_id) REFERENCES program_kerja(proja_id)
      ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_need_submitter
      FOREIGN KEY (submitted_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_need_reviewer
      FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 13. DETAIL KEBUTUHAN PROGRAM KERJA
-- ============================================================
CREATE TABLE program_need_items (
    need_item_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    need_id         BIGINT UNSIGNED NOT NULL,
    nama_kebutuhan  VARCHAR(180) NOT NULL,
    jumlah          DECIMAL(12,2) NOT NULL DEFAULT 1,
    satuan          VARCHAR(50) NULL,
    harga_estimasi  DECIMAL(15,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(15,2)
                    GENERATED ALWAYS AS (jumlah * harga_estimasi) STORED,
    keterangan      TEXT NULL,

    CONSTRAINT fk_need_item_need
      FOREIGN KEY (need_id) REFERENCES program_needs(need_id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 14. PENGAJUAN KEUANGAN PROGRAM KERJA
-- ============================================================
CREATE TABLE program_finance_requests (
    finance_request_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    proja_id            BIGINT UNSIGNED NOT NULL,
    jenis_pengajuan     ENUM('DANA','REIMBURSE','LAINNYA')
                        NOT NULL DEFAULT 'DANA',
    nominal             DECIMAL(15,2) NOT NULL,
    keterangan          TEXT NULL,
    file_pendukung      VARCHAR(255) NULL,
    status              ENUM('DRAFT','DIAJUKAN','REVISI','DITERIMA','DITOLAK')
                        NOT NULL DEFAULT 'DRAFT',
    submitted_by        BIGINT UNSIGNED NULL,
    reviewed_by         BIGINT UNSIGNED NULL,
    submitted_at        DATETIME NULL,
    reviewed_at         DATETIME NULL,
    catatan_review      TEXT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_fin_req_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_fin_req_proja
      FOREIGN KEY (proja_id) REFERENCES program_kerja(proja_id)
      ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_fin_req_submitter
      FOREIGN KEY (submitted_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_fin_req_reviewer
      FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT chk_fin_req_nominal CHECK (nominal >= 0)
) ENGINE=InnoDB;

-- ============================================================
-- 15. DATA ALAT / INVENTARIS YANG DIMILIKI TENANT
-- ============================================================
CREATE TABLE assets (
    asset_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    kode_asset          VARCHAR(60) NOT NULL,
    nama_asset          VARCHAR(180) NOT NULL,
    kategori            VARCHAR(100) NULL,
    jumlah              INT UNSIGNED NOT NULL DEFAULT 1,
    jumlah_baik         INT UNSIGNED NOT NULL DEFAULT 0,
    jumlah_rusak        INT UNSIGNED NOT NULL DEFAULT 0,
    kondisi             ENUM('BAIK','RUSAK_RINGAN','RUSAK_BERAT','HILANG')
                        NOT NULL DEFAULT 'BAIK',
    lokasi              VARCHAR(180) NULL,
    tanggal_perolehan   DATE NULL,
    sumber_perolehan    VARCHAR(180) NULL,
    nilai_asset         DECIMAL(15,2) NOT NULL DEFAULT 0,
    keterangan          TEXT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_asset_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_asset_tenant_code
      UNIQUE (tenant_id, kode_asset),

    CONSTRAINT chk_asset_jumlah CHECK (
        jumlah_baik + jumlah_rusak <= jumlah
    )
) ENGINE=InnoDB;

CREATE INDEX idx_asset_tenant
    ON assets(tenant_id, kondisi);

-- ============================================================
-- 16. DATA PEMASUKAN DAN PENGELUARAN
-- ============================================================
CREATE TABLE financial_transactions (
    transaction_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id          BIGINT UNSIGNED NOT NULL,
    proja_id           BIGINT UNSIGNED NULL,
    jenis_transaksi    ENUM('PEMASUKAN','PENGELUARAN') NOT NULL,
    kategori           VARCHAR(120) NULL,
    tanggal_transaksi  DATE NOT NULL,
    nominal            DECIMAL(15,2) NOT NULL,
    deskripsi          TEXT NULL,
    bukti_path         VARCHAR(255) NULL,
    created_by         BIGINT UNSIGNED NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_transaction_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_transaction_proja
      FOREIGN KEY (proja_id) REFERENCES program_kerja(proja_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_transaction_creator
      FOREIGN KEY (created_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT chk_transaction_nominal CHECK (nominal > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_finance_tenant_date
    ON financial_transactions(tenant_id, tanggal_transaksi);

-- ============================================================
-- 17. KOMUNIKASI ANTAR TENANT
--     HANYA HIMA <-> HMJ INDUK.
--     ORMAWA tidak dapat chat ke ORMAWA lain.
--     HMJ tidak dapat chat ke HMJ lain.
--     HIMA tidak dapat chat ke HIMA lain.
-- ============================================================
CREATE TABLE tenant_messages (
    message_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_tenant_id    BIGINT UNSIGNED NOT NULL,
    receiver_tenant_id  BIGINT UNSIGNED NOT NULL,
    sender_user_id      BIGINT UNSIGNED NOT NULL,
    subject             VARCHAR(180) NULL,
    message             TEXT NOT NULL,
    is_read             BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at             DATETIME NULL,

    CONSTRAINT fk_message_sender_tenant
      FOREIGN KEY (sender_tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_message_receiver_tenant
      FOREIGN KEY (receiver_tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_message_sender_user
      FOREIGN KEY (sender_user_id) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_message_different_tenant
      CHECK (sender_tenant_id <> receiver_tenant_id)
) ENGINE=InnoDB;

CREATE INDEX idx_message_receiver
    ON tenant_messages(receiver_tenant_id, is_read, sent_at);

-- ============================================================
-- 18. NOTIFIKASI
-- ============================================================
CREATE TABLE notifications (
    notification_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id          BIGINT UNSIGNED NULL,
    user_id            BIGINT UNSIGNED NULL,
    judul              VARCHAR(180) NOT NULL,
    pesan              TEXT NOT NULL,
    tipe               ENUM(
                           'TENANT_APPROVAL',
                           'PROJA',
                           'PROPOSAL',
                           'KEBUTUHAN',
                           'KEUANGAN',
                           'PESAN',
                           'SISTEM'
                       ) NOT NULL DEFAULT 'SISTEM',
    reference_id       BIGINT UNSIGNED NULL,
    is_read            BOOLEAN NOT NULL DEFAULT FALSE,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at            DATETIME NULL,

    CONSTRAINT fk_notification_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_notification_user
      FOREIGN KEY (user_id) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 19. RIWAYAT PERSETUJUAN
-- ============================================================
CREATE TABLE approval_history (
    approval_history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NULL,
    reference_type      ENUM(
                            'TENANT',
                            'PROJA',
                            'PROPOSAL',
                            'KEBUTUHAN',
                            'KEUANGAN'
                        ) NOT NULL,
    reference_id        BIGINT UNSIGNED NOT NULL,
    status_sebelum      VARCHAR(50) NULL,
    status_sesudah      VARCHAR(50) NOT NULL,
    reviewed_by         BIGINT UNSIGNED NULL,
    catatan             TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_approval_history_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_approval_history_user
      FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_approval_history_reference
    ON approval_history(reference_type, reference_id);

-- ============================================================
-- 20. AUDIT LOG
-- ============================================================
CREATE TABLE audit_logs (
    audit_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    tenant_id       BIGINT UNSIGNED NULL,
    aksi            ENUM('INSERT','UPDATE','DELETE','LOGIN','APPROVE','REJECT')
                    NOT NULL,
    nama_tabel      VARCHAR(100) NULL,
    record_id       BIGINT UNSIGNED NULL,
    old_data        JSON NULL,
    new_data        JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
      FOREIGN KEY (user_id) REFERENCES users(user_id)
      ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_audit_tenant
      FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_audit_tenant_date
    ON audit_logs(tenant_id, created_at);

-- ============================================================
-- TRIGGER 1:
-- VALIDASI HIRARKI TENANT SAAT INSERT
-- ============================================================
DELIMITER $$

CREATE TRIGGER trg_tenants_validate_bi
BEFORE INSERT ON tenants
FOR EACH ROW
BEGIN
    DECLARE v_parent_type VARCHAR(20) DEFAULT NULL;
    DECLARE v_parent_jurusan BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_prodi_jurusan BIGINT UNSIGNED DEFAULT NULL;

    IF NEW.tenant_type = 'ORMAWA' THEN
        IF NEW.jurusan_id IS NOT NULL
           OR NEW.prodi_id IS NOT NULL
           OR NEW.parent_tenant_id IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Tenant ORMAWA tidak boleh memiliki jurusan, prodi, atau parent tenant.';
        END IF;
    END IF;

    IF NEW.tenant_type = 'HMJ' THEN
        IF NEW.jurusan_id IS NULL
           OR NEW.prodi_id IS NOT NULL
           OR NEW.parent_tenant_id IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Tenant HMJ wajib memiliki jurusan dan tidak boleh memiliki prodi/parent tenant.';
        END IF;
    END IF;

    IF NEW.tenant_type = 'HIMA' THEN
        IF NEW.jurusan_id IS NULL
           OR NEW.prodi_id IS NULL
           OR NEW.parent_tenant_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Tenant HIMA wajib memiliki jurusan, prodi, dan parent HMJ.';
        END IF;

        SELECT tenant_type, jurusan_id
          INTO v_parent_type, v_parent_jurusan
        FROM tenants
        WHERE tenant_id = NEW.parent_tenant_id
        LIMIT 1;

        IF v_parent_type IS NULL OR v_parent_type <> 'HMJ' THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'parent_tenant_id HIMA harus menunjuk tenant bertipe HMJ.';
        END IF;

        SELECT jurusan_id
          INTO v_prodi_jurusan
        FROM program_studi
        WHERE prodi_id = NEW.prodi_id
        LIMIT 1;

        IF v_prodi_jurusan IS NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'Program studi HIMA tidak ditemukan.';
        END IF;

        IF v_prodi_jurusan <> NEW.jurusan_id THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Program studi HIMA harus berada pada jurusan yang sama.';
        END IF;

        IF v_parent_jurusan <> NEW.jurusan_id THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'HIMA hanya boleh berada di bawah HMJ pada jurusan yang sama.';
        END IF;
    END IF;
END$$

-- ============================================================
-- TRIGGER 2:
-- VALIDASI HIRARKI TENANT SAAT UPDATE
-- ============================================================
CREATE TRIGGER trg_tenants_validate_bu
BEFORE UPDATE ON tenants
FOR EACH ROW
BEGIN
    DECLARE v_parent_type VARCHAR(20) DEFAULT NULL;
    DECLARE v_parent_jurusan BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_prodi_jurusan BIGINT UNSIGNED DEFAULT NULL;

    IF NEW.tenant_type = 'ORMAWA' THEN
        IF NEW.jurusan_id IS NOT NULL
           OR NEW.prodi_id IS NOT NULL
           OR NEW.parent_tenant_id IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Tenant ORMAWA tidak boleh memiliki jurusan, prodi, atau parent tenant.';
        END IF;
    END IF;

    IF NEW.tenant_type = 'HMJ' THEN
        IF NEW.jurusan_id IS NULL
           OR NEW.prodi_id IS NOT NULL
           OR NEW.parent_tenant_id IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Tenant HMJ wajib memiliki jurusan dan tidak boleh memiliki prodi/parent tenant.';
        END IF;
    END IF;

    IF NEW.tenant_type = 'HIMA' THEN
        SELECT tenant_type, jurusan_id
          INTO v_parent_type, v_parent_jurusan
        FROM tenants
        WHERE tenant_id = NEW.parent_tenant_id
        LIMIT 1;

        SELECT jurusan_id
          INTO v_prodi_jurusan
        FROM program_studi
        WHERE prodi_id = NEW.prodi_id
        LIMIT 1;

        IF v_parent_type IS NULL OR v_parent_type <> 'HMJ' THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'parent_tenant_id HIMA harus menunjuk tenant bertipe HMJ.';
        END IF;

        IF v_prodi_jurusan <> NEW.jurusan_id
           OR v_parent_jurusan <> NEW.jurusan_id THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT =
              'Jurusan HIMA, program studi, dan HMJ induk harus sama.';
        END IF;
    END IF;
END$$

-- ============================================================
-- TRIGGER 3:
-- VALIDASI KOMUNIKASI: HANYA HIMA <-> HMJ INDUK
-- ============================================================
CREATE TRIGGER trg_tenant_messages_validate_bi
BEFORE INSERT ON tenant_messages
FOR EACH ROW
BEGIN
    DECLARE v_sender_type VARCHAR(20);
    DECLARE v_receiver_type VARCHAR(20);
    DECLARE v_sender_parent BIGINT UNSIGNED;
    DECLARE v_receiver_parent BIGINT UNSIGNED;
    DECLARE v_sender_user_tenant BIGINT UNSIGNED;

    SELECT tenant_type, parent_tenant_id
      INTO v_sender_type, v_sender_parent
    FROM tenants
    WHERE tenant_id = NEW.sender_tenant_id
      AND status = 'ACTIVE'
    LIMIT 1;

    SELECT tenant_type, parent_tenant_id
      INTO v_receiver_type, v_receiver_parent
    FROM tenants
    WHERE tenant_id = NEW.receiver_tenant_id
      AND status = 'ACTIVE'
    LIMIT 1;

    SELECT tenant_id
      INTO v_sender_user_tenant
    FROM users
    WHERE user_id = NEW.sender_user_id
      AND status = 'ACTIVE'
    LIMIT 1;

    IF v_sender_user_tenant IS NULL
       OR v_sender_user_tenant <> NEW.sender_tenant_id THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT =
          'User pengirim bukan anggota tenant pengirim.';
    END IF;

    IF NOT (
        (v_sender_type = 'HIMA'
         AND v_receiver_type = 'HMJ'
         AND v_sender_parent = NEW.receiver_tenant_id)
        OR
        (v_sender_type = 'HMJ'
         AND v_receiver_type = 'HIMA'
         AND v_receiver_parent = NEW.sender_tenant_id)
    ) THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT =
          'Komunikasi antar tenant hanya diizinkan antara HIMA dan HMJ induknya.';
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- VIEW 1: HIRARKI TENANT
-- ============================================================
CREATE OR REPLACE VIEW v_tenant_hierarchy AS
SELECT
    t.tenant_id,
    t.kode_tenant,
    t.nama_tenant,
    t.tenant_type,
    t.status,
    j.nama_jurusan,
    ps.jenjang,
    ps.nama_prodi,
    p.tenant_id AS parent_hmj_id,
    p.nama_tenant AS parent_hmj
FROM tenants t
LEFT JOIN jurusan j
    ON j.jurusan_id = t.jurusan_id
LEFT JOIN program_studi ps
    ON ps.prodi_id = t.prodi_id
LEFT JOIN tenants p
    ON p.tenant_id = t.parent_tenant_id;

-- ============================================================
-- VIEW 2: RINGKASAN KEUANGAN PER TENANT
-- ============================================================
CREATE OR REPLACE VIEW v_financial_summary AS
SELECT
    t.tenant_id,
    t.nama_tenant,
    t.tenant_type,
    COALESCE(SUM(
        CASE
            WHEN ft.jenis_transaksi = 'PEMASUKAN'
            THEN ft.nominal
            ELSE 0
        END
    ), 0) AS total_pemasukan,
    COALESCE(SUM(
        CASE
            WHEN ft.jenis_transaksi = 'PENGELUARAN'
            THEN ft.nominal
            ELSE 0
        END
    ), 0) AS total_pengeluaran,
    COALESCE(SUM(
        CASE
            WHEN ft.jenis_transaksi = 'PEMASUKAN'
            THEN ft.nominal
            ELSE -ft.nominal
        END
    ), 0) AS saldo
FROM tenants t
LEFT JOIN financial_transactions ft
    ON ft.tenant_id = t.tenant_id
GROUP BY
    t.tenant_id,
    t.nama_tenant,
    t.tenant_type;

-- ============================================================
-- VIEW 3: MONITORING PROGRAM KERJA ORMAWA & HMJ OLEH SUPER ADMIN
-- ============================================================
CREATE OR REPLACE VIEW v_superadmin_monitoring_proja AS
SELECT
    pk.proja_id,
    pk.tenant_id,
    t.nama_tenant,
    t.tenant_type,
    j.nama_jurusan,
    pk.nama_proja,
    pk.status_proja,
    pk.tanggal_mulai,
    pk.tanggal_selesai,
    pk.estimasi_anggaran,
    pk.created_at
FROM program_kerja pk
JOIN tenants t
    ON t.tenant_id = pk.tenant_id
LEFT JOIN jurusan j
    ON j.jurusan_id = t.jurusan_id
WHERE t.tenant_type IN ('ORMAWA','HMJ');

-- ============================================================
-- VIEW 4: PROJA HIMA YANG DAPAT DILIHAT / DI-ACC HMJ
-- ============================================================
CREATE OR REPLACE VIEW v_hima_proja_for_hmj AS
SELECT
    pk.proja_id,
    pk.tenant_id AS hima_tenant_id,
    h.nama_tenant AS nama_hima,
    h.parent_tenant_id AS hmj_tenant_id,
    hmj.nama_tenant AS nama_hmj,
    j.nama_jurusan,
    ps.nama_prodi,
    pk.nama_proja,
    pk.status_proja,
    pk.estimasi_anggaran,
    pk.created_at
FROM program_kerja pk
JOIN tenants h
    ON h.tenant_id = pk.tenant_id
JOIN tenants hmj
    ON hmj.tenant_id = h.parent_tenant_id
JOIN jurusan j
    ON j.jurusan_id = h.jurusan_id
JOIN program_studi ps
    ON ps.prodi_id = h.prodi_id
WHERE h.tenant_type = 'HIMA'
  AND hmj.tenant_type = 'HMJ';

-- ============================================================
-- SEED MASTER JURUSAN
-- ============================================================
INSERT INTO jurusan (kode_jurusan, nama_jurusan) VALUES
('BTP',  'Jurusan Budidaya Tanaman Pangan'),
('BTPK', 'Jurusan Budidaya Tanaman Perkebunan'),
('TP',   'Jurusan Teknologi Pertanian'),
('PTK',  'Jurusan Peternakan'),
('EB',   'Jurusan Ekonomi dan Bisnis'),
('T',    'Jurusan Teknik'),
('PK',   'Jurusan Perikanan dan Kelautan'),
('TI',   'Jurusan Teknologi Informasi');

-- ============================================================
-- SEED MASTER PROGRAM STUDI
-- 31 program studi sesuai data yang diberikan.
-- ============================================================

-- 1. Jurusan Budidaya Tanaman Pangan
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-PTP', 'D3', 'Produksi Tanaman Pangan'
FROM jurusan WHERE kode_jurusan = 'BTP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-HORT', 'D3', 'Hortikultura'
FROM jurusan WHERE kode_jurusan = 'BTP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TPTH', 'D4', 'Teknologi Produksi Tanaman Hortikultura'
FROM jurusan WHERE kode_jurusan = 'BTP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TPB', 'D4', 'Teknologi Perbenihan'
FROM jurusan WHERE kode_jurusan = 'BTP';

-- 2. Jurusan Budidaya Tanaman Perkebunan
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-PTPK', 'D3', 'Produksi Tanaman Perkebunan'
FROM jurusan WHERE kode_jurusan = 'BTPK';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-PMIP', 'D4', 'Produksi dan Manajemen Industri Perkebunan'
FROM jurusan WHERE kode_jurusan = 'BTPK';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-PPK', 'D4', 'Pengelolaan Perkebunan Kopi'
FROM jurusan WHERE kode_jurusan = 'BTPK';

-- 3. Jurusan Teknologi Pertanian
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-TP', 'D3', 'Teknologi Pangan'
FROM jurusan WHERE kode_jurusan = 'TP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-TSLL', 'D3', 'Teknik Sumberdaya Lahan dan Lingkungan'
FROM jurusan WHERE kode_jurusan = 'TP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-MP', 'D3', 'Mekanisasi Pertanian'
FROM jurusan WHERE kode_jurusan = 'TP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-PA', 'D4', 'Pengelolaan Agroindustri'
FROM jurusan WHERE kode_jurusan = 'TP';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TRKI', 'D4', 'Teknologi Rekayasa Kimia Industri'
FROM jurusan WHERE kode_jurusan = 'TP';

-- 4. Jurusan Peternakan
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-PT', 'D3', 'Produksi Ternak'
FROM jurusan WHERE kode_jurusan = 'PTK';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TPT', 'D4', 'Teknologi Produksi Ternak'
FROM jurusan WHERE kode_jurusan = 'PTK';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TPTN', 'D4', 'Teknologi Pakan Ternak'
FROM jurusan WHERE kode_jurusan = 'PTK';

-- 5. Jurusan Ekonomi dan Bisnis
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-PH', 'D3', 'Pengelolaan Hotel / Perhotelan'
FROM jurusan WHERE kode_jurusan = 'EB';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-PW', 'D3', 'Perjalanan Wisata'
FROM jurusan WHERE kode_jurusan = 'EB';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-AP', 'D4', 'Agribisnis Pangan'
FROM jurusan WHERE kode_jurusan = 'EB';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-PAgr', 'D4', 'Pengelolaan Agribisnis'
FROM jurusan WHERE kode_jurusan = 'EB';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-AKP', 'D4', 'Akuntansi Perpajakan'
FROM jurusan WHERE kode_jurusan = 'EB';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-ABD', 'D4', 'Akuntansi Bisnis Digital'
FROM jurusan WHERE kode_jurusan = 'EB';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-MIP', 'D4', 'Manajemen Industri Pariwisata'
FROM jurusan WHERE kode_jurusan = 'EB';

-- 6. Jurusan Perikanan dan Kelautan
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-BP', 'D3', 'Budidaya Perikanan'
FROM jurusan WHERE kode_jurusan = 'PK';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-PTG', 'D3', 'Perikanan Tangkap'
FROM jurusan WHERE kode_jurusan = 'PK';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TPI', 'D4', 'Teknologi Pembenihan Ikan'
FROM jurusan WHERE kode_jurusan = 'PK';

-- 7. Jurusan Teknik
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TRKJJ', 'D4', 'Teknologi Rekayasa Konstruksi Jalan dan Jembatan'
FROM jurusan WHERE kode_jurusan = 'T';

-- 8. Jurusan Teknologi Informasi
INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D3-MI', 'D3', 'Manajemen Informatika'
FROM jurusan WHERE kode_jurusan = 'TI';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TRPL', 'D4', 'Teknologi Rekayasa Perangkat Lunak'
FROM jurusan WHERE kode_jurusan = 'TI';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TRI', 'D4', 'Teknologi Rekayasa Internet'
FROM jurusan WHERE kode_jurusan = 'TI';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-TRE', 'D4', 'Teknologi Rekayasa Elektronika'
FROM jurusan WHERE kode_jurusan = 'TI';

INSERT INTO program_studi
(jurusan_id, kode_prodi, jenjang, nama_prodi)
SELECT jurusan_id, 'D4-SDT', 'D4', 'Sains Data Terapan'
FROM jurusan WHERE kode_jurusan = 'TI';

-- ============================================================
-- SEED TENANT ORMAWA / UKM
-- Data yang diberikan berjumlah 9 ORMAWA.
-- Diset ACTIVE sebagai data awal.
-- Untuk tenant baru dari aplikasi gunakan status PENDING.
-- ============================================================
INSERT INTO tenants
(kode_tenant, nama_tenant, tenant_type, status, approved_at)
VALUES
('UKM-OLAHRAGA', 'UKM Olahraga',     'ORMAWA', 'ACTIVE', NOW()),
('UKM-SENI',     'UKM Seni',         'ORMAWA', 'ACTIVE', NOW()),
('UKM-KOPMA',    'UKM Kopma',        'ORMAWA', 'ACTIVE', NOW()),
('UKM-SUKMA',    'UKM Sukma',        'ORMAWA', 'ACTIVE', NOW()),
('UKM-EC',       'UKM EC',           'ORMAWA', 'ACTIVE', NOW()),
('UKM-ALBANA',   'UKM Albana',       'ORMAWA', 'ACTIVE', NOW()),
('UKM-POLTAPALA','UKM Poltapala',    'ORMAWA', 'ACTIVE', NOW()),
('UKM-DAVING',   'UKM Daving Club',  'ORMAWA', 'ACTIVE', NOW()),
('UKM-GARDA',    'UKM Garda',        'ORMAWA', 'ACTIVE', NOW());

-- ============================================================
-- SEED 8 TENANT HMJ
-- ORMAWA dan HMJ berada pada level yang sama.
-- ============================================================
INSERT INTO tenants
(kode_tenant, nama_tenant, tenant_type, jurusan_id, status, approved_at)
SELECT
    CONCAT('HMJ-', kode_jurusan),
    CONCAT('HMJ ', REPLACE(nama_jurusan, 'Jurusan ', '')),
    'HMJ',
    jurusan_id,
    'ACTIVE',
    NOW()
FROM jurusan;

