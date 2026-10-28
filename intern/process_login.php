<?php
require_once 'config/database.php';
session_start();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: index.php?error=Username dan password wajib diisi');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama'] = $user['nama_lengkap'];
    $_SESSION['role'] = $user['role'];

    header('Location: ' . ($user['role'] === 'admin' ? 'admin/index.php' : 'user/index.php'));
} else {
    header('Location: index.php?error=Username atau password salah');
}
exit;