<?php
/** Pendaftaran wajah (sekali). Reset hanya bisa oleh admin (Kelola User). */
require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/face_photo.php';

jsonRequireUser();
$userId = (int)$_SESSION['user_id'];

$cur = $pdo->prepare("SELECT face_descriptors FROM users WHERE id = ?");
$cur->execute([$userId]);
if ($cur->fetchColumn()) jsonOut(['ok' => false, 'message' => 'Wajah sudah terdaftar. Hubungi admin untuk mengatur ulang.'], 409);

$samples = json_decode($_POST['descriptors'] ?? '', true);
if (!is_array($samples) || count($samples) < 3 || count($samples) > 8) jsonOut(['ok' => false, 'message' => 'Minimal 3 sampel wajah dibutuhkan.'], 422);
foreach ($samples as $d) if (!validDescriptor($d)) jsonOut(['ok' => false, 'message' => 'Data wajah tidak valid.'], 422);

// Semua sampel harus orang yang sama (menolak sampel acak / bergantian orang)
for ($i = 0; $i < count($samples); $i++)
    for ($j = $i + 1; $j < count($samples); $j++)
        if (euclidean($samples[$i], $samples[$j]) > 0.70)
            jsonOut(['ok' => false, 'message' => 'Sampel wajah tidak konsisten. Ulangi dan pastikan hanya satu orang di depan kamera.'], 422);

$photo = saveFacePhoto($_POST['photo'] ?? '', 'wajah', 'enroll' . $userId, null);
if ($photo === null) jsonOut(['ok' => false, 'message' => 'Foto wajah gagal disimpan: ' . (facePhotoError() ?: 'tidak diketahui') . '.'], 422);

$pdo->prepare("UPDATE users SET face_descriptors = ?, face_registered_at = NOW(), face_photo = ? WHERE id = ? AND face_descriptors IS NULL")
    ->execute([json_encode(array_values($samples)), $photo, $userId]);

jsonOut(['ok' => true, 'message' => 'Wajah berhasil didaftarkan.']);
