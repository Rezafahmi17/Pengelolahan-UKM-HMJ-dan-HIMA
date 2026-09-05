<?php

declare(strict_types=1);

function db(): PDO
{
    return Database::connection();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function envv(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) envv('APP_URL', 'http://127.0.0.1:8000'), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function route_url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}

function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Token CSRF tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function rupiah(float|int|string|null $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function indo_date(?string $value, string $format = 'd M Y'): string
{
    if (!$value) return '-';
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '-';
}

function status_badge(string $status): string
{
    $key = strtoupper($status);
    $map = [
        'ACTIVE' => 'success', 'AKTIF' => 'success', 'APPROVED' => 'success',
        'DISETUJUI' => 'success', 'DITERIMA' => 'success', 'SELESAI' => 'success',
        'BERJALAN' => 'info', 'PENDING' => 'warning', 'DIAJUKAN' => 'warning',
        'DRAFT' => 'muted', 'REVISI' => 'warning', 'SUSPENDED' => 'danger',
        'REJECTED' => 'danger', 'DITOLAK' => 'danger', 'NONAKTIF' => 'muted',
        'RUSAK_RINGAN' => 'warning', 'RUSAK_BERAT' => 'danger', 'HILANG' => 'danger',
        'BAIK' => 'success',
    ];
    $class = $map[$key] ?? 'muted';
    return '<span class="badge badge-' . $class . '">' . e(str_replace('_', ' ', $key)) . '</span>';
}

function request_int(string $key, ?int $default = null): ?int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($value === null || $value === '') return $default;
    return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
}

function clean_text(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function json_for_audit(mixed $value): ?string
{
    return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function audit_log(string $action, ?string $table = null, ?int $recordId = null, mixed $old = null, mixed $new = null): void
{
    try {
        $user = current_user();
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, tenant_id, aksi, nama_tabel, record_id, old_data, new_data, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $user['user_id'] ?? null,
            $user['tenant_id'] ?? null,
            $action,
            $table,
            $recordId,
            json_for_audit($old),
            json_for_audit($new),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable) {
        // Audit tidak boleh menggagalkan aksi utama.
    }
}

function upload_file(string $field, string $prefix = 'doc'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file gagal.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file maksimal 5 MB.');
    }

    $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('File hanya boleh PDF, JPG, atau PNG.');
    }

    $ext = match ($mime) {
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        default => 'bin',
    };
    $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetDir = dirname(__DIR__) . '/public/uploads';
    if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Tidak dapat menyimpan file upload.');
    }
    return 'uploads/' . $name;
}
