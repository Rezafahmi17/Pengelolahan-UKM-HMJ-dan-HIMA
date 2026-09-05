<?php
// Jalankan: php tools/create_admin.php
require_once dirname(__DIR__) . '/src/bootstrap.php';

$email = $argv[1] ?? 'admin@simormawa.test';
$password = $argv[2] ?? 'admin123';
$name = $argv[3] ?? 'Super Admin';

try {
    $check = db()->prepare('SELECT user_id FROM users WHERE email=? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        echo "Akun {$email} sudah ada.\n";
        exit(0);
    }

    $stmt = db()->prepare("INSERT INTO users (tenant_id,nama_lengkap,email,password_hash,role,status) VALUES (NULL,?,?,?,'SUPER_ADMIN','ACTIVE')");
    $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    echo "Super Admin berhasil dibuat.\nEmail    : {$email}\nPassword : {$password}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal: {$e->getMessage()}\n");
    exit(1);
}
