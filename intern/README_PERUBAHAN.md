# Absensi Magang — Pembaruan

## Cara pasang
1. Timpa semua file proyek lama dengan isi zip ini (config/database.php: sesuaikan lagi user/password DB bila berbeda).
2. Buka aplikasi seperti biasa. **Database otomatis diperbarui** (kolom & tabel baru ditambahkan, data lama aman).
   Alternatif manual: jalankan `database/migration_v2.sql` di phpMyAdmin.
3. Login admin → **Pengaturan** → atur jam absensi per hari (kolom baru: *Batas Tepat Waktu*) lalu Simpan.
4. Anak magang login → klik **Daftarkan Wajah** (sekali) → bisa absen.
5. Kamera & GPS di browser hanya jalan lewat **HTTPS** atau `localhost`.
6. Pastikan folder `uploads/` bisa ditulis oleh server (foto bukti absensi).

## Aturan jam absensi
| Waktu | Absen Masuk | Absen Pulang |
|---|---|---|
| Sebelum *Buka Masuk* | Belum dibuka | Tidak bisa |
| *Buka Masuk* – *Batas Tepat Waktu* | Tepat Waktu | Tidak bisa |
| *Batas Tepat Waktu* – *Tutup Masuk* | **Terlambat** (menit dihitung) | Tidak bisa |
| Setelah *Tutup Masuk* | Ditutup → **Alpa** | Tidak bisa (belum waktunya) |
| *Buka Pulang* – *Tutup Pulang* | Tidak bisa | Bisa, **hanya jika sudah absen masuk** |
| Setelah *Tutup Pulang* | — | Ditutup |

Admin tidak bisa menyimpan jam yang membuat masuk & pulang tumpang tindih. Semua dicek di **server**
(jam server WIB), bukan jam HP pengguna.

## Scan wajah + lokasi
Satu permintaan absen harus lolos semuanya di server: jam → jarak GPS (Haversine) → kode verifikasi → kecocokan wajah.
Foto bukti diberi cap nama, waktu, koordinat, jarak. Admin melihatnya di *Data Absensi*; reset wajah di *Kelola User*.

## PENTING (keamanan)
- **Hapus `reset_admin.php` dari server** — file itu mengganti password admin menjadi `Admin12345!` untuk siapa pun yang membukanya.
- Ganti password admin setelah dipakai.

## Perubahan tampilan & perbaikan kedip (terbaru)
- Login & Daftar: layout rata tengah/simetris (logo bulat di atas, kartu kaca di tengah), responsif HP.
- Gambar diganti nama & dirapikan: `assets/img/logodiskominfo.png` (dipotong bulat, 640px, ringan) dan
  `assets/img/backgrounddiskominfo.jpg` (strip bawah bawaan dibuang, 2048px). File lama dihapus.
- Deteksi kedip (`assets/js/face-scan.js`): baseline mata tidak lagi meluruh saat mata menutup, deteksi tanpa jeda,
  wajah hilang sesaat saat mata tertutup dihitung sebagai kedipan, ambang relatif + absolut. Timeout 30 dtk.
- Bila browser menyimpan CSS lama, tekan Ctrl+F5.

## Scan wajah tanpa kedip (terbaru)
- Kedip dihapus. Pendaftaran wajah: lihat depan → putar ke kiri → putar ke kanan → depan lagi (4 sampel).
- Absen: pengguna diminta menoleh ke arah acak (kiri/kanan) sebagai pengecekan "orang hidup", lalu menghadap depan untuk dicocokkan.
- Arah dihitung dari posisi hidung terhadap tepi rahang (`_yawRatio` di face-scan.js); ambang di `_poseOf` (0.40 / 0.60) bisa disesuaikan.
- Server: batas konsistensi antar sampel pendaftaran dilonggarkan 0.55 → 0.70 karena wajah menoleh berbeda dari wajah depan.
- Pengguna yang sudah terdaftar tidak perlu daftar ulang.

## Perbaikan pose kiri/kanan (terbaru)
- Arah kepala kini dinilai RELATIF terhadap wajah depan pengguna (dikalibrasi di langkah "depan"), bukan angka tetap.
- Data wajah diambil pada frame yang sama saat pose terpenuhi (tidak ada pass kedua yang bisa gagal karena kepala bergerak).
- Sampel menoleh hanya disimpan bila masih mirip wajah depan; sampel depan selalu dikirim (2 + 2).
- Pesan error penyimpanan sekarang menampilkan alasan sebenarnya dari server.

## Perbaikan "Foto wajah tidak valid" (terbaru)
- `includes/face_photo.php`: validasi JPEG tidak lagi bergantung pada ekstensi GD. Jika GD PHP tidak aktif, foto JPEG yang sudah divalidasi
  disimpan apa adanya (tanpa cap teks). Jika GD aktif, foto tetap di-encode ulang + diberi cap.
- Pesan error kini menyebut alasan persis (mis. "folder uploads tidak bisa ditulis"). Bila muncul, beri izin tulis pada folder `uploads/`.
