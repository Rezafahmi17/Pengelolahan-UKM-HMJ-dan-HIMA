<?php

declare(strict_types=1);

function current_user(bool $refresh = false): ?array
{
    static $cache = null;
    static $loaded = false;

    if ($refresh) {
        $cache = null;
        $loaded = false;
    }
    if ($loaded) return $cache;
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) return null;

    try {
        $stmt = db()->prepare(
            'SELECT u.*, t.nama_tenant, t.kode_tenant, t.tenant_type, t.jurusan_id, t.prodi_id, t.parent_tenant_id, t.status AS tenant_status,
                    j.nama_jurusan, ps.nama_prodi
             FROM users u
             LEFT JOIN tenants t ON t.tenant_id = u.tenant_id
             LEFT JOIN jurusan j ON j.jurusan_id = t.jurusan_id
             LEFT JOIN program_studi ps ON ps.prodi_id = t.prodi_id
             WHERE u.user_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $id]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'ACTIVE') {
            unset($_SESSION['user_id']);
            return null;
        }
        if ($user['role'] !== 'SUPER_ADMIN' && $user['tenant_status'] !== 'ACTIVE') {
            unset($_SESSION['user_id']);
            return null;
        }
        $cache = $user;
        return $cache;
    } catch (Throwable) {
        return null;
    }
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    if ($user['role'] !== 'SUPER_ADMIN' && $user['tenant_id']) {
        $check = db()->prepare('SELECT status FROM tenants WHERE tenant_id = ? LIMIT 1');
        $check->execute([$user['tenant_id']]);
        if ($check->fetchColumn() !== 'ACTIVE') {
            return false;
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
    current_user(true);
    audit_log('LOGIN', 'users', (int) $user['user_id']);
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect_to(route_url('login'));
    }
    return $user;
}

function is_super_admin(?array $user = null): bool
{
    $user ??= current_user();
    return ($user['role'] ?? null) === 'SUPER_ADMIN';
}

function is_tenant_admin(?array $user = null): bool
{
    $user ??= current_user();
    return ($user['role'] ?? null) === 'ADMIN_TENANT';
}

function require_super_admin(): array
{
    $user = require_login();
    if (!is_super_admin($user)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    return $user;
}

function require_tenant_user(): array
{
    $user = require_login();
    if (!$user['tenant_id']) {
        http_response_code(403);
        exit('Halaman ini hanya untuk akun tenant.');
    }
    return $user;
}

function can_view_tenant(int $tenantId, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user) return false;
    if (is_super_admin($user)) {
        $stmt = db()->prepare("SELECT tenant_type FROM tenants WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $type = $stmt->fetchColumn();
        return in_array($type, ['ORMAWA', 'HMJ'], true);
    }
    if ((int) $user['tenant_id'] === $tenantId) return true;
    if (($user['tenant_type'] ?? null) === 'HMJ') {
        $stmt = db()->prepare("SELECT COUNT(*) FROM tenants WHERE tenant_id = ? AND tenant_type = 'HIMA' AND parent_tenant_id = ?");
        $stmt->execute([$tenantId, $user['tenant_id']]);
        return (bool) $stmt->fetchColumn();
    }
    return false;
}

function can_manage_tenant(int $tenantId, ?array $user = null): bool
{
    $user ??= current_user();
    return $user && !is_super_admin($user) && (int) ($user['tenant_id'] ?? 0) === $tenantId && is_tenant_admin($user);
}
