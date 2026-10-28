<?php

require_once 'config/database.php';

$username = 'admin';
$passwordBaru = 'Admin12345!';

$hash = password_hash($passwordBaru, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users
    SET password = ?
    WHERE username = ? AND role = 'admin'
");

$stmt->execute([$hash, $username]);

if ($stmt->rowCount() > 0) {
    echo "Password admin berhasil direset.<br>";
    echo "Username: " . htmlspecialchars($username) . "<br>";
    echo "Password baru: " . htmlspecialchars($passwordBaru);
} else {
    echo "Admin tidak ditemukan.";
}