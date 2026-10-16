<?php
// Memulai sesi
session_start();

// Cek apakah tombol konfirmasi sudah ditekan melalui JavaScript
if (isset($_GET['aksi']) && $_GET['aksi'] == 'proses') {
    // Menghapus semua data sesi yang aktif
    session_unset();
    session_destroy();

    // Mengarahkan kembali ke halaman index
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout</title>
</head>
<body>

    <script>
        // Memunculkan kotak dialog konfirmasi
        let konfirmasi = confirm("Yakin Anda ingin logout?");
        
        if (konfirmasi) {
            // Jika klik OK, lanjutkan proses penghapusan sesi ke file ini lagi
            window.location.href = "logout_kasir.php?confirm=yes";
        } else {
            // Jika klik Batal, kembalikan ke halaman sebelumnya
            window.history.back();
        }
    </script>

</body>
</html>