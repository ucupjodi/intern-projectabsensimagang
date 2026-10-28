<?php
/**
 * Simpan foto hasil scan wajah (data-URL JPEG) dengan aman:
 *  - dibatasi ukuran & tipe, di-encode ulang oleh GD (membuang payload tersembunyi)
 *  - diberi cap: nama, waktu, koordinat (bukti absensi)
 * Mengembalikan path relatif terhadap root aplikasi, atau null bila gagal.
 */
function ensureUploadDir(string $dir): bool {
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    // folder upload tidak boleh menjalankan script apa pun
    $ht = dirname(__DIR__) . '/uploads/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, uploadsHtaccess());
    return true;
}

function uploadsHtaccess(): string {
    return "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi)$\">\n  Require all denied\n</FilesMatch>\n"
         . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\n  php_flag engine off\n</IfModule>\n";
}

/** Alasan kegagalan terakhir saveFacePhoto (untuk ditampilkan ke pengguna / admin) */
function facePhotoError(?string $set = null): string {
    static $err = '';
    if ($set !== null) $err = $set;
    return $err;
}

function saveFacePhoto(string $dataUrl, string $subdir, string $prefix, ?string $stamp = null, bool $forceNoGd = false): ?string {
    facePhotoError('');
    $prefixStr = 'data:image/jpeg;base64,';
    if (strncmp($dataUrl, $prefixStr, strlen($prefixStr)) !== 0) { facePhotoError('format foto bukan JPEG data-URL'); return null; }
    $bin = base64_decode(preg_replace('/\s+/', '', substr($dataUrl, strlen($prefixStr))), true);
    if ($bin === false) { facePhotoError('data foto rusak (base64)'); return null; }
    if (strlen($bin) < 1000 || strlen($bin) > 2 * 1024 * 1024) { facePhotoError('ukuran foto tidak wajar (' . strlen($bin) . ' byte)'); return null; }

    // Validasi JPEG asli tanpa GD: tanda awal FFD8 + getimagesizefromstring
    $info = @getimagesizefromstring($bin);
    if (substr($bin, 0, 2) !== "\xFF\xD8" || !$info || $info[2] !== IMAGETYPE_JPEG) { facePhotoError('file bukan gambar JPEG yang valid'); return null; }
    [$w, $h] = $info;
    if ($w < 120 || $h < 120 || $w > 1600 || $h > 1600) { facePhotoError("dimensi foto tidak valid ({$w}x{$h})"); return null; }

    $sub  = $subdir . '/' . date('Ym');
    $dir  = dirname(__DIR__) . '/uploads/' . $sub;
    if (!ensureUploadDir($dir)) { facePhotoError('folder uploads tidak bisa dibuat / ditulis — beri izin tulis pada folder uploads'); return null; }
    if (!is_writable($dir))     { facePhotoError('folder uploads tidak bisa ditulis — beri izin tulis pada folder uploads'); return null; }
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $path = $dir . '/' . $name;

    if (!$forceNoGd && function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        // Encode ulang lewat GD (membuang payload tersembunyi) + cap bukti
        $img = @imagecreatefromstring($bin);
        if ($img) {
            if ($stamp) {
                $bar = 22;
                $black = imagecolorallocatealpha($img, 0, 0, 0, 50);
                imagefilledrectangle($img, 0, $h - $bar, $w, $h, $black);
                $white = imagecolorallocate($img, 255, 255, 255);
                $txt = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $stamp) ?: $stamp) : $stamp;
                imagestring($img, 2, 6, $h - $bar + 4, substr($txt, 0, (int)floor(($w - 12) / 7)), $white);
            }
            $ok = @imagejpeg($img, $path, 82);
            imagedestroy($img);
            if ($ok) return 'uploads/' . $sub . '/' . $name;
            facePhotoError('gagal menulis file foto ke folder uploads');
            return null;
        }
    }
    // Cadangan bila ekstensi GD PHP tidak aktif: simpan JPEG yang sudah divalidasi apa adanya (tanpa cap)
    if (@file_put_contents($path, $bin) === false) { facePhotoError('gagal menulis file foto ke folder uploads'); return null; }
    return 'uploads/' . $sub . '/' . $name;
}
