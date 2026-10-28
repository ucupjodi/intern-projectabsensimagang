<?php
require_once 'config/database.php';

$nama = trim($_POST['nama_lengkap'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($nama) || empty($username) || empty($password)) {
    header('Location: register.php?error=Semua field wajib diisi');
    exit;
}

if (strlen($password) < 6) {
    header('Location: register.php?error=Password minimal 6 karakter');
    exit;
}

// Cek username sudah ada
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    header('Location: register.php?error=Username sudah digunakan');
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, 'user')");
$stmt->execute([$username, $hash, $nama]);

header('Location: register.php?success=Pendaftaran berhasil! Silakan login.');
exit;