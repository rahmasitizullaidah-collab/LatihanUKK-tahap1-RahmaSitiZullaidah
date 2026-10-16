<?php
session_start();

// Cek apakah kasir atau admin sudah login
if (!isset($_SESSION['login_kasir']) || $_SESSION['login_kasir'] !== true) {
    header("Location: login_kasir.php");
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

// Jika tombol "Simpan Produk" ditekan
if (isset($_POST['simpan'])) {
    $nama_produk = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    // Membersihkan input harga agar aman masuk ke tipe data decimal
    $harga = preg_replace('/[^0-9.]/', '', $_POST['harga']);
    $stok = mysqli_real_escape_string($koneksi, $_POST['stok']);

    // Simpan ke database
    $query_simpan = mysqli_query($koneksi, "INSERT INTO produk (nama_produk, harga, stok) VALUES ('$nama_produk', '$harga', '$stok')");
    
    if ($query_simpan) {
        $pesan = "<div class='alert-success'>Berhasil menambahkan produk baru!</div>";
    } else {
        $pesan = "<div class='alert-danger'>Gagal menyimpan data produk!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Jus Segar</title>
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
        .nav-links a:hover { color: #cf663a; }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
            display: flex;
            gap: 25px;
        }
        
        /* Form Section */
        .form-section {
            flex: 1;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            height: fit-content;
        }
        .form-section h3 { margin-top: 0; color: #222; border-bottom: 2px solid #fbe0c9; padding-bottom: 10px; margin-bottom: 20px;}
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px;}
        .input-group input {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit;
        }
        .input-group input:focus {
            outline: none; border-color: #cf663a;
        }
        .btn-submit {
            background-color: #cf663a; color: white; padding: 12px 15px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 15px; transition: background-color 0.3s;
        }
        .btn-submit:hover { background-color: #b3552d; }
        
        /* Table Section */
        .table-section {
            flex: 2.2;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .table-section h3 { margin-top: 0; color: #222; border-bottom: 2px solid #fbe0c9; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eee; padding: 12px 15px; text-align: left; }
        th { background-color: #fcf4ec; color: #333; font-weight: 600; }
        tr:hover { background-color: #fafafa; }

        /* Alerts */
        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { flex-direction: column; }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">🥤 Kelola Data Produk</div>
        <div class="nav-links">
            <a href="index.php">🏠 Beranda</a>
        </div>
    </header>

    <div class="container">
        
        <!-- Bagian Kiri: Form Tambah Produk -->
        <div class="form-section">
            <h3>Tambah Produk</h3>
            <?php echo $pesan; ?>
            <form action="" method="POST">
                <div class="input-group">
                    <label>Nama Produk / Jus</label>
                    <input type="text" name="nama_produk" required placeholder="">
                </div>
                <div class="input-group">
                    <label>Harga (Rp)</label>
                    <input type="text" name="harga" required placeholder="">
                </div>
                <div class="input-group">
                    <label>Stok</label>
                    <input type="text" name="stok" required placeholder="">
                </div>
                <button type="submit" name="simpan" class="btn-submit">Simpan Data Produk</button>
            </form>
        </div>

        <!-- Bagian Kanan: Tabel Data Produk -->
        <div class="table-section">
            <h3>Daftar Produk Tersedia</h3>
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Mengambil data produk dari database, diurutkan dari ID terbesar (terbaru)
                    $query_tampil = mysqli_query($koneksi, "SELECT * FROM produk ORDER BY id_produk DESC");
                    $no = 1;
                    
                    // Cek jika belum ada data di database
                    if(mysqli_num_rows($query_tampil) == 0){
                        echo "<tr><td colspan='4' style='text-align:center; padding: 20px; color: #888;'>Belum ada data produk yang terdaftar.</td></tr>";
                    }

                    // Tampilkan data berulang
                    while ($data = mysqli_fetch_assoc($query_tampil)) {
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><strong><?php echo htmlspecialchars($data['nama_produk']); ?></strong></td>
                        <td>Rp <?php echo number_format($data['harga'], 0, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($data['stok']); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>