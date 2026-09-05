<?php

declare(strict_types=1);

function handle_post_action(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    verify_csrf();
    $action = clean_text('action');

    try {
        switch ($action) {
            case 'login':
                $email = strtolower(clean_text('email'));
                $password = (string) ($_POST['password'] ?? '');
                if ($email === '' || $password === '') throw new RuntimeException('Email dan password wajib diisi.');
                if (!attempt_login($email, $password)) throw new RuntimeException('Email/password salah atau akun belum aktif.');
                flash('success', 'Berhasil masuk ke portal.');
                redirect_to(route_url('dashboard'));

            case 'register_account':
                register_account_action();
                break;

            case 'approve_tenant':
            case 'reject_tenant':
                tenant_approval_action($action === 'approve_tenant');
                break;

            case 'save_member':
                save_member_action();
                break;
            case 'delete_member':
                delete_owned_row('members', 'member_id', 'members', route_url('members'));
                break;

            case 'save_period':
                save_period_action();
                break;
            case 'save_position':
                save_position_action();
                break;
            case 'save_structure':
                save_structure_action();
                break;
            case 'delete_structure':
                delete_owned_row('organization_structures', 'structure_id', 'structure', route_url('structure'));
                break;

            case 'save_program':
                save_program_action();
                break;
            case 'delete_program':
                delete_owned_row('program_kerja', 'proja_id', 'program_kerja', route_url('programs'));
                break;
            case 'submit_program':
                submit_program_action();
                break;
            case 'review_program':
                review_program_action();
                break;
            case 'change_program_status':
                change_program_status_action();
                break;

            case 'save_proposal':
                save_proposal_action();
                break;
            case 'save_need':
                save_need_action();
                break;
            case 'save_finance_request':
                save_finance_request_action();
                break;
            case 'review_submission':
                review_submission_action();
                break;

            case 'save_asset':
                save_asset_action();
                break;
            case 'delete_asset':
                delete_owned_row('assets', 'asset_id', 'assets', route_url('assets'));
                break;

            case 'save_finance':
                save_finance_action();
                break;
            case 'delete_finance':
                delete_owned_row('financial_transactions', 'transaction_id', 'financial_transactions', route_url('finance'));
                break;

            case 'send_message':
                send_message_action();
                break;

            default:
                throw new RuntimeException('Aksi tidak dikenali.');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        $back = $_SERVER['HTTP_REFERER'] ?? route_url('dashboard');
        redirect_to($back);
    }
}

function register_account_action(): never
{
    $type = strtoupper(clean_text('tenant_type'));
    $name = clean_text('nama_lengkap');
    $email = strtolower(clean_text('email'));
    $password = (string) ($_POST['password'] ?? '');

    if (!in_array($type, ['ORMAWA', 'HMJ', 'HIMA'], true)) throw new RuntimeException('Jenis tenant tidak valid.');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Nama dan email valid wajib diisi.');
    if (strlen($password) < 8) throw new RuntimeException('Password minimal 8 karakter.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $checkEmail->execute([$email]);
        if ((int) $checkEmail->fetchColumn() > 0) throw new RuntimeException('Email sudah terdaftar.');

        $tenantId = null;
        $approvalTarget = 'SUPER_ADMIN';
        $reviewerTenantId = null;

        if (in_array($type, ['ORMAWA', 'HMJ'], true)) {
            $tenantId = request_int($type === 'ORMAWA' ? 'tenant_id_ormawa' : 'tenant_id_hmj');
            if (!$tenantId) throw new RuntimeException('Pilih organisasi yang ingin diajukan.');
            $stmt = $pdo->prepare('SELECT * FROM tenants WHERE tenant_id = ? AND tenant_type = ? AND status = \'ACTIVE\' LIMIT 1');
            $stmt->execute([$tenantId, $type]);
            $tenant = $stmt->fetch();
            if (!$tenant) throw new RuntimeException('Tenant tidak ditemukan atau belum aktif.');

            $adminCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role = 'ADMIN_TENANT' AND status IN ('PENDING','ACTIVE')");
            $adminCheck->execute([$tenantId]);
            if ((int) $adminCheck->fetchColumn() > 0) throw new RuntimeException('Organisasi ini sudah memiliki admin atau sedang menunggu persetujuan admin.');
        } else {
            $prodiId = request_int('prodi_id');
            if (!$prodiId) throw new RuntimeException('Program studi HIMA wajib dipilih.');
            $prodi = $pdo->prepare('SELECT ps.*, j.kode_jurusan FROM program_studi ps JOIN jurusan j ON j.jurusan_id = ps.jurusan_id WHERE ps.prodi_id = ? AND ps.status = \'AKTIF\' LIMIT 1');
            $prodi->execute([$prodiId]);
            $prodiRow = $prodi->fetch();
            if (!$prodiRow) throw new RuntimeException('Program studi tidak ditemukan.');

            $exists = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE tenant_type = 'HIMA' AND prodi_id = ? AND status IN ('PENDING','ACTIVE')");
            $exists->execute([$prodiId]);
            if ((int) $exists->fetchColumn() > 0) throw new RuntimeException('HIMA untuk program studi ini sudah terdaftar atau sedang menunggu persetujuan.');

            $hmj = $pdo->prepare("SELECT * FROM tenants WHERE tenant_type = 'HMJ' AND jurusan_id = ? AND status = 'ACTIVE' LIMIT 1");
            $hmj->execute([$prodiRow['jurusan_id']]);
            $hmjRow = $hmj->fetch();
            if (!$hmjRow) throw new RuntimeException('HMJ induk untuk jurusan ini belum aktif.');

            $kodeTenant = 'HIMA-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $prodiRow['kode_prodi']));
            $namaTenant = 'HIMA ' . $prodiRow['nama_prodi'];
            $insertTenant = $pdo->prepare("INSERT INTO tenants (kode_tenant, nama_tenant, tenant_type, jurusan_id, prodi_id, parent_tenant_id, status) VALUES (?,?, 'HIMA', ?, ?, ?, 'PENDING')");
            $insertTenant->execute([$kodeTenant, $namaTenant, $prodiRow['jurusan_id'], $prodiId, $hmjRow['tenant_id']]);
            $tenantId = (int) $pdo->lastInsertId();
            $approvalTarget = 'PARENT_HMJ';
            $reviewerTenantId = (int) $hmjRow['tenant_id'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insertUser = $pdo->prepare("INSERT INTO users (tenant_id, nama_lengkap, email, password_hash, role, status) VALUES (?,?,?,?, 'ADMIN_TENANT', 'PENDING')");
        $insertUser->execute([$tenantId, $name, $email, $hash]);
        $userId = (int) $pdo->lastInsertId();

        $insertApproval = $pdo->prepare("INSERT INTO tenant_approvals (tenant_id, requested_by, reviewer_tenant_id, approval_target, status) VALUES (?,?,?,?, 'PENDING')");
        $insertApproval->execute([$tenantId, $userId, $reviewerTenantId, $approvalTarget]);

        if ($approvalTarget === 'SUPER_ADMIN') {
            $superUsers = $pdo->query("SELECT user_id FROM users WHERE role='SUPER_ADMIN' AND status='ACTIVE'")->fetchAll();
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, judul, pesan, tipe) VALUES (?, 'Pengajuan akun tenant', ?, 'TENANT_APPROVAL')");
            foreach ($superUsers as $su) $notify->execute([$su['user_id'], 'Pengajuan admin tenant baru dari ' . $name . '.']);
        } else {
            $pdo->prepare("INSERT INTO notifications (tenant_id, judul, pesan, tipe) VALUES (?, 'Pengajuan HIMA baru', ?, 'TENANT_APPROVAL')")
                ->execute([$reviewerTenantId, 'HIMA baru menunggu konfirmasi HMJ.']);
        }

        $pdo->commit();
        flash('success', 'Pengajuan berhasil dikirim. Akun dapat digunakan setelah disetujui.');
        redirect_to(route_url('login'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function tenant_approval_action(bool $approve): never
{
    $user = require_login();
    $approvalId = request_int('approval_id');
    if (!$approvalId) throw new RuntimeException('ID persetujuan tidak valid.');

    $stmt = db()->prepare('SELECT ta.*, t.tenant_type, t.status AS tenant_status, t.parent_tenant_id, t.nama_tenant FROM tenant_approvals ta JOIN tenants t ON t.tenant_id = ta.tenant_id WHERE ta.tenant_approval_id = ? AND ta.status = \'PENDING\' LIMIT 1');
    $stmt->execute([$approvalId]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Pengajuan tidak ditemukan atau sudah diproses.');

    if ($row['approval_target'] === 'SUPER_ADMIN') {
        if (!is_super_admin($user)) throw new RuntimeException('Hanya Super Admin yang dapat memproses pengajuan ini.');
    } else {
        if (($user['tenant_type'] ?? '') !== 'HMJ' || (int) $user['tenant_id'] !== (int) $row['reviewer_tenant_id']) {
            throw new RuntimeException('Pengajuan HIMA hanya dapat diproses oleh HMJ induknya.');
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $newStatus = $approve ? 'APPROVED' : 'REJECTED';
        $pdo->prepare('UPDATE tenant_approvals SET status=?, reviewer_user_id=?, reviewed_at=NOW(), catatan=? WHERE tenant_approval_id=?')
            ->execute([$newStatus, $user['user_id'], clean_text('catatan'), $approvalId]);

        if ($approve) {
            if ($row['tenant_status'] === 'PENDING') {
                $pdo->prepare("UPDATE tenants SET status='ACTIVE', approved_at=NOW() WHERE tenant_id=?")->execute([$row['tenant_id']]);
            }
            if ($row['requested_by']) {
                $pdo->prepare("UPDATE users SET status='ACTIVE' WHERE user_id=?")->execute([$row['requested_by']]);
            }
        } else {
            if ($row['tenant_status'] === 'PENDING') {
                $pdo->prepare("UPDATE tenants SET status='REJECTED' WHERE tenant_id=?")->execute([$row['tenant_id']]);
            }
            if ($row['requested_by']) {
                $pdo->prepare("UPDATE users SET status='REJECTED' WHERE user_id=?")->execute([$row['requested_by']]);
            }
        }

        $pdo->prepare('INSERT INTO approval_history (tenant_id, reference_type, reference_id, status_sebelum, status_sesudah, reviewed_by, catatan) VALUES (?,\'TENANT\',?,\'PENDING\',?,?,?)')
            ->execute([$row['tenant_id'], $approvalId, $newStatus, $user['user_id'], clean_text('catatan')]);

        if ($row['requested_by']) {
            $pdo->prepare("INSERT INTO notifications (user_id, tenant_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'TENANT_APPROVAL')")
                ->execute([$row['requested_by'], $row['tenant_id'], $approve ? 'Pengajuan disetujui' : 'Pengajuan ditolak', $approve ? 'Akun tenant Anda sudah aktif.' : 'Pengajuan akun tenant Anda ditolak.']);
        }

        $pdo->commit();
        audit_log($approve ? 'APPROVE' : 'REJECT', 'tenant_approvals', $approvalId, ['status' => 'PENDING'], ['status' => $newStatus]);
        flash('success', $approve ? 'Pengajuan berhasil disetujui.' : 'Pengajuan berhasil ditolak.');
        redirect_to(route_url('tenants'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function save_member_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengelola anggota.');
    $id = request_int('member_id');
    $npm = clean_text('npm');
    $nama = clean_text('nama_lengkap');
    if ($nama === '') throw new RuntimeException('Nama anggota wajib diisi.');

    $data = [
        $user['tenant_id'], request_int('prodi_id'), $npm !== '' ? $npm : null, $nama,
        clean_text('jenis_kelamin') ?: null, clean_text('angkatan') ?: null,
        clean_text('email') ?: null, clean_text('no_hp') ?: null, clean_text('status', 'AKTIF')
    ];

    if ($id) {
        $check = db()->prepare('SELECT * FROM members WHERE member_id=? AND tenant_id=?');
        $check->execute([$id, $user['tenant_id']]);
        $old = $check->fetch();
        if (!$old) throw new RuntimeException('Anggota tidak ditemukan.');
        $stmt = db()->prepare('UPDATE members SET prodi_id=?, npm=?, nama_lengkap=?, jenis_kelamin=?, angkatan=?, email=?, no_hp=?, status=? WHERE member_id=? AND tenant_id=?');
        $stmt->execute([$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$id,$user['tenant_id']]);
        audit_log('UPDATE', 'members', $id, $old, ['nama_lengkap'=>$nama]);
        flash('success', 'Data anggota berhasil diperbarui.');
    } else {
        $stmt = db()->prepare('INSERT INTO members (tenant_id, prodi_id, npm, nama_lengkap, jenis_kelamin, angkatan, email, no_hp, status) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute($data);
        $id = (int) db()->lastInsertId();
        audit_log('INSERT', 'members', $id, null, ['nama_lengkap'=>$nama]);
        flash('success', 'Anggota berhasil ditambahkan.');
    }
    redirect_to(route_url('members'));
}

function save_period_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengubah struktur.');
    $name = clean_text('nama_periode');
    $start = clean_text('tanggal_mulai');
    $end = clean_text('tanggal_selesai');
    if (!$name || !$start || !$end) throw new RuntimeException('Data periode belum lengkap.');
    db()->prepare('INSERT INTO organization_periods (tenant_id,nama_periode,tanggal_mulai,tanggal_selesai,status) VALUES (?,?,?,?,?)')
        ->execute([$user['tenant_id'],$name,$start,$end,clean_text('status','AKTIF')]);
    flash('success', 'Periode kepengurusan ditambahkan.');
    redirect_to(route_url('structure'));
}

function save_position_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengubah struktur.');
    $name = clean_text('nama_jabatan');
    if (!$name) throw new RuntimeException('Nama jabatan wajib diisi.');
    db()->prepare('INSERT INTO positions (tenant_id,nama_jabatan,urutan) VALUES (?,?,?)')
        ->execute([$user['tenant_id'],$name,request_int('urutan',0)]);
    flash('success', 'Jabatan ditambahkan.');
    redirect_to(route_url('structure'));
}

function save_structure_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengubah struktur.');
    $periodId = request_int('period_id');
    $memberId = request_int('member_id');
    $positionId = request_int('position_id');
    if (!$periodId || !$memberId || !$positionId) throw new RuntimeException('Periode, anggota, dan jabatan wajib dipilih.');
    $sql = 'INSERT INTO organization_structures (tenant_id, period_id, member_id, position_id, mulai_jabatan, selesai_jabatan)
            SELECT ?, p.period_id, m.member_id, pos.position_id, ?, ?
            FROM organization_periods p JOIN members m ON m.tenant_id=p.tenant_id JOIN positions pos ON pos.tenant_id=p.tenant_id
            WHERE p.period_id=? AND m.member_id=? AND pos.position_id=? AND p.tenant_id=? LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([$user['tenant_id'], clean_text('mulai_jabatan') ?: null, clean_text('selesai_jabatan') ?: null, $periodId, $memberId, $positionId, $user['tenant_id']]);
    if ($stmt->rowCount() === 0) throw new RuntimeException('Data struktur tidak sesuai tenant.');
    flash('success', 'Pengurus berhasil dimasukkan ke struktur.');
    redirect_to(route_url('structure'));
}

function save_program_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengelola program kerja.');
    $id = request_int('proja_id');
    $name = clean_text('nama_proja');
    if (!$name) throw new RuntimeException('Nama program kerja wajib diisi.');
    $values = [
        $name, clean_text('deskripsi') ?: null, clean_text('tujuan') ?: null,
        clean_text('tanggal_mulai') ?: null, clean_text('tanggal_selesai') ?: null,
        clean_text('lokasi') ?: null, clean_text('penanggung_jawab') ?: null,
        (float) ($_POST['estimasi_anggaran'] ?? 0)
    ];
    if ($id) {
        $stmt = db()->prepare("UPDATE program_kerja SET nama_proja=?, deskripsi=?, tujuan=?, tanggal_mulai=?, tanggal_selesai=?, lokasi=?, penanggung_jawab=?, estimasi_anggaran=? WHERE proja_id=? AND tenant_id=? AND status_proja IN ('DRAFT','REVISI')");
        $stmt->execute([...$values,$id,$user['tenant_id']]);
        if ($stmt->rowCount() === 0) throw new RuntimeException('Program tidak dapat diedit pada status saat ini.');
        flash('success', 'Program kerja diperbarui.');
    } else {
        $stmt = db()->prepare("INSERT INTO program_kerja (tenant_id,nama_proja,deskripsi,tujuan,tanggal_mulai,tanggal_selesai,lokasi,penanggung_jawab,estimasi_anggaran,status_proja,created_by) VALUES (?,?,?,?,?,?,?,?,?,'DRAFT',?)");
        $stmt->execute([$user['tenant_id'],...$values,$user['user_id']]);
        flash('success', 'Program kerja berhasil ditambahkan.');
    }
    redirect_to(route_url('programs'));
}

function submit_program_action(): never
{
    $user = require_tenant_user();
    $id = request_int('proja_id');
    if (!$id) throw new RuntimeException('Program kerja tidak valid.');
    $stmt = db()->prepare("UPDATE program_kerja SET status_proja='DIAJUKAN' WHERE proja_id=? AND tenant_id=? AND status_proja IN ('DRAFT','REVISI')");
    $stmt->execute([$id,$user['tenant_id']]);
    if ($stmt->rowCount() === 0) throw new RuntimeException('Program tidak dapat diajukan.');

    if (($user['tenant_type'] ?? '') === 'HIMA') {
        db()->prepare("INSERT INTO notifications (tenant_id, judul, pesan, tipe, reference_id) VALUES (?, 'Program kerja HIMA diajukan', 'Ada program kerja HIMA yang menunggu peninjauan.', 'PROJA', ?)")
            ->execute([$user['parent_tenant_id'],$id]);
    } else {
        $supers = db()->query("SELECT user_id FROM users WHERE role='SUPER_ADMIN' AND status='ACTIVE'")->fetchAll();
        $n = db()->prepare("INSERT INTO notifications (user_id, judul, pesan, tipe, reference_id) VALUES (?, 'Program kerja diajukan', ?, 'PROJA', ?)");
        foreach ($supers as $su) $n->execute([$su['user_id'], ($user['nama_tenant'] ?? 'Tenant') . ' mengajukan program kerja.', $id]);
    }
    flash('success', 'Program kerja berhasil diajukan.');
    redirect_to(route_url('programs'));
}

function review_program_action(): never
{
    $user = require_login();
    $id = request_int('proja_id');
    $decision = strtoupper(clean_text('decision'));
    if (!$id || !in_array($decision,['DISETUJUI','DITOLAK','REVISI'],true)) throw new RuntimeException('Keputusan tidak valid.');

    $stmt = db()->prepare('SELECT pk.*, t.tenant_type, t.parent_tenant_id FROM program_kerja pk JOIN tenants t ON t.tenant_id=pk.tenant_id WHERE pk.proja_id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || $row['status_proja'] !== 'DIAJUKAN') throw new RuntimeException('Program tidak tersedia untuk ditinjau.');

    $allowed = false;
    if (is_super_admin($user) && in_array($row['tenant_type'],['ORMAWA','HMJ'],true)) $allowed = true;
    if (($user['tenant_type'] ?? '') === 'HMJ' && $row['tenant_type']==='HIMA' && (int)$row['parent_tenant_id']===(int)$user['tenant_id']) $allowed = true;
    if (!$allowed) throw new RuntimeException('Anda tidak berwenang meninjau program ini.');

    db()->prepare('UPDATE program_kerja SET status_proja=?, reviewed_by_user_id=?, reviewed_at=NOW(), catatan_review=? WHERE proja_id=?')
        ->execute([$decision,$user['user_id'],clean_text('catatan_review') ?: null,$id]);
    db()->prepare("INSERT INTO approval_history (tenant_id,reference_type,reference_id,status_sebelum,status_sesudah,reviewed_by,catatan) VALUES (?,'PROJA',?,'DIAJUKAN',?,?,?)")
        ->execute([$row['tenant_id'],$id,$decision,$user['user_id'],clean_text('catatan_review') ?: null]);
    db()->prepare("INSERT INTO notifications (tenant_id, judul, pesan, tipe, reference_id) VALUES (?, 'Status program kerja berubah', ?, 'PROJA', ?)")
        ->execute([$row['tenant_id'],'Program kerja Anda: ' . $decision,$id]);
    audit_log($decision==='DISETUJUI'?'APPROVE':'REJECT','program_kerja',$id,['status'=>'DIAJUKAN'],['status'=>$decision]);
    flash('success', 'Program kerja berhasil ditinjau.');
    redirect_to(route_url('programs'));
}

function change_program_status_action(): never
{
    $user = require_tenant_user();
    $id = request_int('proja_id');
    $status = strtoupper(clean_text('status_proja'));
    if (!$id || !in_array($status,['BERJALAN','SELESAI'],true)) throw new RuntimeException('Status tidak valid.');
    $allowedFrom = $status === 'BERJALAN' ? "'DISETUJUI'" : "'BERJALAN','DISETUJUI'";
    $stmt = db()->prepare("UPDATE program_kerja SET status_proja=? WHERE proja_id=? AND tenant_id=? AND status_proja IN ($allowedFrom)");
    $stmt->execute([$status,$id,$user['tenant_id']]);
    if ($stmt->rowCount()===0) throw new RuntimeException('Status program tidak dapat diubah.');
    flash('success','Status program kerja diperbarui.');
    redirect_to(route_url('programs'));
}

function save_proposal_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengajukan proposal.');
    $projaId = request_int('proja_id');
    ensure_owned_program($projaId, (int)$user['tenant_id']);
    $file = upload_file('file_proposal','proposal');
    db()->prepare("INSERT INTO program_proposals (tenant_id,proja_id,nomor_proposal,judul_proposal,file_path,status,submitted_by,tanggal_pengajuan) VALUES (?,?,?,?,?,'DIAJUKAN',?,NOW())")
        ->execute([$user['tenant_id'],$projaId,clean_text('nomor_proposal') ?: null,clean_text('judul_proposal'),$file,$user['user_id']]);
    flash('success','Proposal program kerja berhasil diajukan.');
    redirect_to(route_url('submissions'));
}

function save_need_action(): never
{
    $user = require_tenant_user();
    if (!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat membuat pengajuan kebutuhan.');
    $projaId = request_int('proja_id');
    ensure_owned_program($projaId,(int)$user['tenant_id']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO program_needs (tenant_id,proja_id,judul_pengajuan,keterangan,total_estimasi,status,submitted_by,submitted_at) VALUES (?,?,?,?,?,'DIAJUKAN',?,NOW())")
            ->execute([$user['tenant_id'],$projaId,clean_text('judul_pengajuan'),clean_text('keterangan') ?: null,(float)($_POST['total_estimasi']??0),$user['user_id']]);
        $needId=(int)$pdo->lastInsertId();
        $itemName=clean_text('nama_kebutuhan');
        if($itemName!==''){
            $pdo->prepare('INSERT INTO program_need_items (need_id,nama_kebutuhan,jumlah,satuan,harga_estimasi,keterangan) VALUES (?,?,?,?,?,?)')
                ->execute([$needId,$itemName,(float)($_POST['jumlah']??1),clean_text('satuan') ?: null,(float)($_POST['harga_estimasi']??0),null]);
        }
        $pdo->commit();
        flash('success','Pengajuan kebutuhan berhasil dikirim.');
        redirect_to(route_url('submissions'));
    } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}

function save_finance_request_action(): never
{
    $user=require_tenant_user();
    if(!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat membuat pengajuan keuangan.');
    $projaId=request_int('proja_id'); ensure_owned_program($projaId,(int)$user['tenant_id']);
    $file=upload_file('file_pendukung','pengajuan-dana');
    db()->prepare("INSERT INTO program_finance_requests (tenant_id,proja_id,jenis_pengajuan,nominal,keterangan,file_pendukung,status,submitted_by,submitted_at) VALUES (?,?,?,?,?,?,'DIAJUKAN',?,NOW())")
      ->execute([$user['tenant_id'],$projaId,clean_text('jenis_pengajuan','DANA'),(float)($_POST['nominal']??0),clean_text('keterangan') ?: null,$file,$user['user_id']]);
    flash('success','Pengajuan keuangan berhasil dikirim.'); redirect_to(route_url('submissions'));
}

function review_submission_action(): never
{
    $user=require_login();
    $kind=clean_text('kind'); $id=request_int('id'); $decision=strtoupper(clean_text('decision'));
    if(!$id || !in_array($kind,['proposal','need','finance'],true) || !in_array($decision,['DITERIMA','DITOLAK','REVISI'],true)) throw new RuntimeException('Data review tidak valid.');
    $map=[
      'proposal'=>['program_proposals','proposal_id'],
      'need'=>['program_needs','need_id'],
      'finance'=>['program_finance_requests','finance_request_id']
    ];
    [$table,$pk]=$map[$kind];
    $stmt=db()->prepare("SELECT x.*, t.tenant_type,t.parent_tenant_id FROM {$table} x JOIN tenants t ON t.tenant_id=x.tenant_id WHERE x.{$pk}=? LIMIT 1");
    $stmt->execute([$id]); $row=$stmt->fetch();
    if(!$row || $row['status']!=='DIAJUKAN') throw new RuntimeException('Pengajuan tidak tersedia untuk direview.');
    $allowed=(is_super_admin($user) && in_array($row['tenant_type'],['ORMAWA','HMJ'],true)) || (($user['tenant_type']??'')==='HMJ' && $row['tenant_type']==='HIMA' && (int)$row['parent_tenant_id']===(int)$user['tenant_id']);
    if(!$allowed) throw new RuntimeException('Anda tidak berwenang meninjau pengajuan ini.');
    $cat=clean_text('catatan_review') ?: null;
    if($table==='program_proposals') db()->prepare("UPDATE {$table} SET status=?,reviewed_by=?,reviewed_at=NOW(),catatan=? WHERE {$pk}=?")->execute([$decision,$user['user_id'],$cat,$id]);
    else db()->prepare("UPDATE {$table} SET status=?,reviewed_by=?,reviewed_at=NOW(),catatan_review=? WHERE {$pk}=?")->execute([$decision,$user['user_id'],$cat,$id]);
    flash('success','Pengajuan berhasil ditinjau.'); redirect_to(route_url('submissions'));
}

function save_asset_action(): never
{
    $user=require_tenant_user(); if(!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengelola inventaris.');
    $id=request_int('asset_id'); $kode=clean_text('kode_asset'); $nama=clean_text('nama_asset');
    if(!$kode||!$nama) throw new RuntimeException('Kode dan nama aset wajib diisi.');
    $vals=[$kode,$nama,clean_text('kategori') ?: null,(int)($_POST['jumlah']??1),(int)($_POST['jumlah_baik']??0),(int)($_POST['jumlah_rusak']??0),clean_text('kondisi','BAIK'),clean_text('lokasi') ?: null,clean_text('tanggal_perolehan') ?: null,clean_text('sumber_perolehan') ?: null,(float)($_POST['nilai_asset']??0),clean_text('keterangan') ?: null];
    if($id){
      $stmt=db()->prepare('UPDATE assets SET kode_asset=?,nama_asset=?,kategori=?,jumlah=?,jumlah_baik=?,jumlah_rusak=?,kondisi=?,lokasi=?,tanggal_perolehan=?,sumber_perolehan=?,nilai_asset=?,keterangan=? WHERE asset_id=? AND tenant_id=?');
      $stmt->execute([...$vals,$id,$user['tenant_id']]); flash('success','Inventaris diperbarui.');
    } else {
      $stmt=db()->prepare('INSERT INTO assets (tenant_id,kode_asset,nama_asset,kategori,jumlah,jumlah_baik,jumlah_rusak,kondisi,lokasi,tanggal_perolehan,sumber_perolehan,nilai_asset,keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
      $stmt->execute([$user['tenant_id'],...$vals]); flash('success','Inventaris ditambahkan.');
    }
    redirect_to(route_url('assets'));
}

function save_finance_action(): never
{
    $user=require_tenant_user(); if(!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat mengelola keuangan.');
    $id=request_int('transaction_id'); $type=clean_text('jenis_transaksi'); $date=clean_text('tanggal_transaksi'); $amount=(float)($_POST['nominal']??0);
    if(!in_array($type,['PEMASUKAN','PENGELUARAN'],true)||!$date||$amount<=0) throw new RuntimeException('Data transaksi belum valid.');
    $file=upload_file('bukti','bukti-transaksi');
    if($id){
      $old=db()->prepare('SELECT bukti_path FROM financial_transactions WHERE transaction_id=? AND tenant_id=?'); $old->execute([$id,$user['tenant_id']]); $oldPath=$old->fetchColumn();
      $file=$file ?: ($oldPath ?: null);
      db()->prepare('UPDATE financial_transactions SET proja_id=?,jenis_transaksi=?,kategori=?,tanggal_transaksi=?,nominal=?,deskripsi=?,bukti_path=? WHERE transaction_id=? AND tenant_id=?')
        ->execute([request_int('proja_id'),$type,clean_text('kategori') ?: null,$date,$amount,clean_text('deskripsi') ?: null,$file,$id,$user['tenant_id']]);
      flash('success','Transaksi diperbarui.');
    } else {
      db()->prepare('INSERT INTO financial_transactions (tenant_id,proja_id,jenis_transaksi,kategori,tanggal_transaksi,nominal,deskripsi,bukti_path,created_by) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$user['tenant_id'],request_int('proja_id'),$type,clean_text('kategori') ?: null,$date,$amount,clean_text('deskripsi') ?: null,$file,$user['user_id']]);
      flash('success','Transaksi keuangan ditambahkan.');
    }
    redirect_to(route_url('finance'));
}

function send_message_action(): never
{
    $user=require_tenant_user();
    if(!in_array(($user['tenant_type']??''),['HMJ','HIMA'],true)) throw new RuntimeException('Fitur pesan hanya untuk HMJ dan HIMA.');
    $receiver=request_int('receiver_tenant_id'); if(!$receiver) throw new RuntimeException('Penerima wajib dipilih.');
    $message=clean_text('message'); if(!$message) throw new RuntimeException('Pesan tidak boleh kosong.');
    db()->prepare('INSERT INTO tenant_messages (sender_tenant_id,receiver_tenant_id,sender_user_id,subject,message) VALUES (?,?,?,?,?)')
      ->execute([$user['tenant_id'],$receiver,$user['user_id'],clean_text('subject') ?: null,$message]);
    db()->prepare("INSERT INTO notifications (tenant_id,judul,pesan,tipe,reference_id) VALUES (?,'Pesan baru',?,'PESAN',NULL)")
      ->execute([$receiver,'Pesan baru dari ' . $user['nama_tenant']]);
    flash('success','Pesan berhasil dikirim.'); redirect_to(route_url('messages'));
}

function ensure_owned_program(?int $projaId, int $tenantId): array
{
    if(!$projaId) throw new RuntimeException('Program kerja wajib dipilih.');
    $stmt=db()->prepare('SELECT * FROM program_kerja WHERE proja_id=? AND tenant_id=? LIMIT 1'); $stmt->execute([$projaId,$tenantId]); $row=$stmt->fetch();
    if(!$row) throw new RuntimeException('Program kerja tidak ditemukan pada tenant Anda.'); return $row;
}

function delete_owned_row(string $table,string $pk,string $auditTable,string $redirect): never
{
    $user=require_tenant_user(); if(!is_tenant_admin($user)) throw new RuntimeException('Hanya admin tenant yang dapat menghapus data.');
    $id=request_int($pk); if(!$id) throw new RuntimeException('ID data tidak valid.');
    $allowed=['members','organization_structures','program_kerja','assets','financial_transactions'];
    if(!in_array($table,$allowed,true)) throw new RuntimeException('Tabel tidak diizinkan.');
    $stmt=db()->prepare("DELETE FROM {$table} WHERE {$pk}=? AND tenant_id=?"); $stmt->execute([$id,$user['tenant_id']]);
    if($stmt->rowCount()===0) throw new RuntimeException('Data tidak ditemukan atau bukan milik tenant Anda.');
    audit_log('DELETE',$auditTable,$id); flash('success','Data berhasil dihapus.'); redirect_to($redirect);
}
