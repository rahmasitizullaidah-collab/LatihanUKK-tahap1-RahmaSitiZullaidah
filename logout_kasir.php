<?php
// Memulai sesi
session_start();

// Cek apakah parameter 'confirm' sudah diset dari JavaScript
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Menghapus semua data sesi yang aktif
    session_unset();
    session_destroy();

    // Mengarahkan kembali ke halaman login kasir setelah dikonfirmasi
    header("Location: login_kasir.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
</head>
<body>

    <script>
        // Menampilkan kotak dialog konfirmasi saat halaman ini dibuka
        let konfirmasi = confirm("Yakin Anda ingin logout?");
        
        if (konfirmasi) {
            // Jika pengguna klik "OK", arahkan ke halaman ini lagi dengan parameter konfirmasi=yes
            window.location.href = "logout_kasir.php?confirm=yes";
        } else {
            // Jika pengguna klik "Cancel", kembalikan ke halaman sebelumnya (atau dashboard kasir)
            window.history.back();
        }
    </script>

</body>
</html>