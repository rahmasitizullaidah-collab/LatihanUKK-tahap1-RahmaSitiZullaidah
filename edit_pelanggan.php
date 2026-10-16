<?php
session_start();

// Cek apakah admin sudah login
if (!isset($_SESSION['login_admin']) || $_SESSION['login_admin'] !== true) {
    header("Location: login_admin.php");
    exit;
}

// Koneksi ke Database
$host = "localhost";
$user = "root";       
$pass = "";           
$db   = "db_kasir"; 

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$pesan = "";

// 1. Ambil ID Pelanggan dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: pelanggan_admin.php");
    exit;
}

$id_pelanggan = mysqli_real_escape_string($koneksi, $_GET['id']);

// 2. Ambil data pelanggan yang akan diedit dari database
$query_pilih = mysqli_query($koneksi, "SELECT * FROM pelanggan WHERE id_pelanggan = '$id_pelanggan'");
$data = mysqli_fetch_assoc($query_pilih);

if (!$data) {
    echo "<script>alert('Data pelanggan tidak ditemukan!'); window.location='pelanggan_admin.php';</script>";
    exit;
}

// 3. Proses Update Data ketika Tombol Simpan Perubahan ditekan
if (isset($_POST['update_pelanggan'])) {
    $nama_pelanggan = mysqli_real_escape_string($koneksi, $_POST['nama_pelanggan']);
    $no_telp = mysqli_real_escape_string($koneksi, $_POST['no_telp']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);

    $query_update = mysqli_query($koneksi, "UPDATE pelanggan SET nama_pelanggan = '$nama_pelanggan', no_telp = '$no_telp', alamat = '$alamat' WHERE id_pelanggan = '$id_pelanggan'");

    if ($query_update) {
        echo "<script>alert('Data pelanggan berhasil diperbarui!'); window.location='pelanggan_admin.php';</script>";
        exit;
    } else {
        $pesan = "<div class='alert-danger'>Gagal memperbarui data pelanggan!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pelanggan - Jus Segar</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fcf4ec;
            color: #333;
        }
        header {
            background-color: #fbe0c9;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-bottom: 4px solid #2c3e50;
        }
        .logo-title {
            font-size: 20px;
            font-weight: bold;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            margin-left: 20px;
            transition: color 0.3s;
        }
        .nav-links a:hover { color: #2c3e50; }
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Form Card Section */
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .form-card h3 { 
            margin-top: 0; 
            color: #222; 
            border-bottom: 2px solid #fbe0c9; 
            padding-bottom: 12px; 
            margin-bottom: 25px;
        }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px;}
        .input-group input, .input-group textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; background: #fff; font-size: 14px;
        }
        .input-group input:focus, .input-group textarea:focus {
            outline: none; border-color: #2c3e50;
        }
        .btn-container {
            display: flex;
            gap: 10px;
        }
        .btn-submit {
            background-color: #2c3e50; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; flex: 1; transition: background-color 0.3s;
        }
        .btn-submit:hover { background-color: #1a252f; }
        
        .btn-cancel {
            background-color: #bdc3c7; color: #333; padding: 12px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; text-align: center; flex: 1; transition: background-color 0.3s;
        }
        .btn-cancel:hover { background-color: #95a5a6; }

        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;}
    </style>
</head>
<body>

    <header>
        <div class="logo-title">✏️ Edit Data Pelanggan</div>
        <div class="nav-links">
            <a href="pelanggan_admin.php">⬅ Kembali</a>
        </div>
    </header>

    <div class="container">
        <div class="form-card">
            <h3>Form Perbarui Informasi Pelanggan</h3>
            <?php echo $pesan; ?>
            
            <form action="" method="POST">
                <div class="input-group">
                    <label>Nama Pelanggan</label>
                    <input type="text" name="nama_pelanggan" value="<?php echo htmlspecialchars($data['nama_pelanggan']); ?>" required autocomplete="off">
                </div>

                <div class="input-group">
                    <label>Nomor HP / Telepon</label>
                    <input type="text" name="no_telp" value="<?php echo htmlspecialchars($data['no_telp']); ?>" required autocomplete="off">
                </div>

                <div class="input-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" rows="3" required><?php echo htmlspecialchars($data['alamat']); ?></textarea>
                </div>

                <div class="btn-container">
                    <a href="pelanggan_admin.php" class="btn-cancel">Batal</a>
                    <button type="submit" name="update_pelanggan" class="btn-submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>