<?php
session_start();

// 1. Buat Koneksi ke Database
$host = "localhost";
$username = "root";       // Sesuaikan dengan user phpMyAdmin kamu
$password_md5 = "";       // Sesuaikan dengan password phpMyAdmin kamu
$db   = "db_kasir"; // Ganti dengan nama database yang kamu buat

$koneksi = mysqli_connect($host, $username, $password_md5, $db);

// Cek koneksi
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$error = "";

// Cek apakah form telah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Tangkap inputan dan amankan dari SQL Injection dasar
    $user_input = mysqli_real_escape_string($koneksi, $_POST['username']);
    $pass_input = mysqli_real_escape_string($koneksi, $_POST['password']);

    // Ubah inputan password menjadi MD5
    $password_md5 = md5($pass_input);

    // Cek kecocokan data di tabel admin
    $query = mysqli_query($koneksi, "SELECT * FROM admin WHERE username='$user_input' AND password='$password_md5'");
    $cek = mysqli_num_rows($query);

    if ($cek > 0) {
        $data_admin = mysqli_fetch_assoc($query);
        
        // Jika cocok, buat session khusus admin
        $_SESSION['login_admin'] = true;
        $_SESSION['username_admin'] = $user_input;
        $_SESSION['nama_admin'] = $data_admin['nama_lengkap'] ?? 'Administrator'; 
        
        // Arahkan ke halaman dashboard khusus admin (index_admin.php)
        header("Location: index_admin.php"); 
        exit;
    } else {
        $error = "Username atau Password Admin salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Jus Segar</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #fcefe3;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            width: 350px;
            text-align: center;
            border-top: 5px solid #2c3e50;
        }
        .login-container h2 {
            color: #333;
            margin-bottom: 5px;
            font-size: 24px;
        }
        .badge-admin {
            display: inline-block;
            background-color: #2c3e50;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 12px;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 15px;
            background-color: #f9f9f9;
        }
        .input-group input:focus {
            outline: none;
            border-color: #2c3e50;
            background-color: #fff;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #1a252f;
        }
        .error-message {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .logo-icon {
            font-size: 50px;
            margin-bottom: 5px;
        }
        .back-link {
            display: block;
            margin-top: 15px;
            font-size: 13px;
            color: #7f8c8d;
            text-decoration: none;
        }
        .back-link:hover {
            color: #cf663a;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>Login Administrator</h2>
    <span class="badge-admin">AKSES KONTROL UTAMA</span>
    
    <?php if($error != ""): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="input-group">
            <label for="username">Username Admin</label>
            <input type="text" id="username" name="username" placeholder="Masukkan username admin" required autocomplete="off">
        </div>
        
        <div class="input-group">
            <label for="password">Password Admin</label>
            <input type="password" id="password" name="password" placeholder="Masukkan password admin" required>
        </div>
        
        <button type="submit" class="btn-login">Masuk Sebagai Admin</button>
    </form>

    <a href="login_kasir.php" class="back-link">← Kembali ke Login Kasir</a>
</div>

</body>
</html>