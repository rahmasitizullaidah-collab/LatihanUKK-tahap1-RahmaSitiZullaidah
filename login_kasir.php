<?php
session_start();

// 1. Buat Koneksi ke Database
$host = "localhost";
$username = "root";       // Sesuaikan dengan user phpMyAdmin kamu
$password_md5 = "";           // Sesuaikan dengan password phpMyAdmin kamu
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
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);

    // Ubah inputan password menjadi MD5
    $password_md5 = md5($password);

    // Cek kecocokan data di tabel kasir (menggunakan password yang sudah di-MD5)
    $query = mysqli_query($koneksi, "SELECT * FROM kasir WHERE username='$username' AND password='$password_md5'");
    $cek = mysqli_num_rows($query);

    if ($cek > 0) {
        $data_kasir = mysqli_fetch_assoc($query);
        
        // Jika cocok, buat session dan arahkan ke dashboard
        $_SESSION['login_kasir'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['nama_lengkap'] = $data_kasir['nama_lengkap']; 
        
        // Arahkan ke halaman utama/dashboard kamu
        header("Location: index.php"); 
        exit;
    } else {
        $error = "Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir - Jus Segar</title>
    <style>
        /* Gaya Tampilan (CSS) */
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
        }
        .login-container h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 24px;
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
            border-color: #d2691e;
            background-color: #fff;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #cf663a; 
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
            background-color: #b3552d;
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
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo-icon">🍹</div>
    <h2>Login Kasir</h2>
    
    <?php if($error != ""): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="input-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Masukkan username" required autocomplete="off">
        </div>
        
        <div class="input-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Masukkan password" required>
        </div>
        
        <button type="submit" class="btn-login">Masuk</button>
    </form>
</div>

</body>
</html>