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

// 1. PROSES TAMBAH PRODUK
if (isset($_POST['simpan'])) {
    $nama_produk = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $harga = preg_replace('/[^0-9.]/', '', $_POST['harga']); // Membersihkan format harga desimal
    $stok = mysqli_real_escape_string($koneksi, $_POST['stok']);

    $query_simpan = mysqli_query($koneksi, "INSERT INTO produk (nama_produk, harga, stok) VALUES ('$nama_produk', '$harga', '$stok')");
    
    if ($query_simpan) {
        $pesan = "<div class='alert-success'>Berhasil menambahkan produk baru!</div>";
    } else {
        $pesan = "<div class='alert-danger'>Gagal menyimpan data produk!</div>";
    }
}

// 2. PROSES HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $id_produk = $_GET['hapus'];
    $query_hapus = mysqli_query($koneksi, "DELETE FROM produk WHERE id_produk = '$id_produk'");
    if ($query_hapus) {
        header("Location: produk_admin.php");
        exit;
    } else {
        $pesan = "<div class='alert-danger'>Gagal menghapus data produk!</div>";
    }
}

// 3. FITUR PENCARIAN PINTAR (Prioritas nama pas di baris teratas)
$keyword = "";
if (isset($_GET['cari']) && trim($_GET['cari']) !== "") {
    $keyword = mysqli_real_escape_string($koneksi, trim($_GET['cari']));
    
    $sql_produk = "SELECT * FROM produk 
                   WHERE nama_produk LIKE '%$keyword%' 
                      OR harga LIKE '%$keyword%' 
                      OR stok LIKE '%$keyword%' 
                   ORDER BY 
                     CASE 
                         WHEN nama_produk = '$keyword' THEN 1
                         WHEN nama_produk LIKE '$keyword%' THEN 2
                         ELSE 3
                     END, 
                     id_produk DESC";
} else {
    $sql_produk = "SELECT * FROM produk ORDER BY id_produk DESC";
}

$query_tampil = mysqli_query($koneksi, $sql_produk);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk (Admin) - Jus Segar</title>
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
            max-width: 1200px;
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
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; background: #fff;
        }
        .input-group input:focus {
            outline: none; border-color: #2c3e50;
        }
        .btn-submit {
            background-color: #2c3e50; color: white; padding: 12px 15px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 15px; transition: background-color 0.3s;
        }
        .btn-submit:hover { background-color: #1a252f; }
        
        /* Table Section */
        .table-section {
            flex: 2.2;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #fbe0c9;
            padding-bottom: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h3 { margin: 0; color: #222; }

        /* Form Pencarian */
        .search-form {
            display: flex;
            gap: 8px;
        }
        .search-form input {
            padding: 7px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            width: 180px;
        }
        .search-form button {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 7px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .search-form button:hover { background-color: #1a252f; }
        .btn-reset {
            background-color: #bdc3c7 !important;
            color: #333 !important;
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eee; padding: 12px 15px; text-align: left; font-size: 14px;}
        th { background-color: #fcf4ec; color: #333; font-weight: 600; }
        tr:hover { background-color: #fafafa; }

        .highlight {
            background-color: #fef9e7 !important;
        }

        /* Tombol Aksi Tabel (Edit & Hapus) */
        .btn-edit {
            background-color: #f39c12;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-right: 4px;
        }
        .btn-edit:hover { background-color: #d68910; }
        
        .btn-hapus {
            background-color: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .btn-hapus:hover { background-color: #c0392b; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}

        @media (max-width: 768px) {
            .container { flex-direction: column; }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">👑 Kelola Produk (Admin Panel)</div>
        <div class="nav-links">
           <a href="index_admin.php">🏠 Beranda</a>
        </div>
    </header>

    <div class="container">
        
        <!-- Bagian Kiri: Form Tambah Produk -->
        <div class="form-section">
            <h3>Tambah Produk Baru</h3>
            <?php echo $pesan; ?>
            <form action="" method="POST">
                <div class="input-group">
                    <label>Nama Produk / Jus</label>
                    <input type="text" name="nama_produk" required placeholder autocomplete="off">
                </div>
                <div class="input-group">
                    <label>Harga (Rp)</label>
                    <input type="text" name="harga" required placeholder autocomplete="off">
                </div>
                <div class="input-group">
                    <label>Stok Jus</label>
                    <input type="text" name="stok" required placeholder autocomplete="off">
                </div>
                <button type="submit" name="simpan" class="btn-submit">Simpan Data Produk</button>
            </form>
        </div>

        <!-- Bagian Kanan: Tabel Data Produk dengan Pencarian -->
        <div class="table-section">
            <div class="table-header">
                <h3>Daftar Produk Tersedia</h3>
                
                <!-- Form Pencarian -->
                <form action="" method="GET" class="search-form">
                    <input type="text" name="cari" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Cari nama produk..">
                    <button type="submit">Cari</button>
                    <?php if(!empty($keyword)): ?>
                        <a href="produk_admin.php" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th style="text-align: center;" width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    if(mysqli_num_rows($query_tampil) == 0){
                        echo "<tr><td colspan='5' style='text-align:center; padding: 20px; color: #888;'>Tidak ada data produk ditemukan.</td></tr>";
                    }

                    while ($row = mysqli_fetch_assoc($query_tampil)) {
                        $is_match = (!empty($keyword) && strtolower($row['nama_produk']) == strtolower($keyword));
                    ?>
                    <tr class="<?php echo $is_match ? 'highlight' : ''; ?>">
                        <td><?php echo $no++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nama_produk']); ?></strong></td>
                        <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($row['stok']); ?> Jus</td>
                        <td style="text-align: center;">
                            <a href="edit_produk.php?id=<?php echo $row['id_produk']; ?>" class="btn-edit">Edit</a>
                            <a href="produk_admin.php?hapus=<?php echo $row['id_produk']; ?>" class="btn-hapus" onclick="return confirm('Yakin ingin menghapus produk ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>