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

// 1. PROSES TAMBAH PELANGGAN
if (isset($_POST['simpan_pelanggan'])) {
    $nama_pelanggan = mysqli_real_escape_string($koneksi, $_POST['nama_pelanggan']);
    $no_telp = mysqli_real_escape_string($koneksi, $_POST['no_telp']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);

    // Validasi tambahan: Pastikan nomor HP hanya angka dan panjangnya 10 sampai 12 digit
    if (!preg_match('/^[0-9]{10,12}$/', $no_telp)) {
        $pesan = "<div class='alert-danger'>Nomor HP harus berupa angka dan panjang antara 10 hingga 12 digit!</div>";
    } else {
        $query_simpan = mysqli_query($koneksi, "INSERT INTO pelanggan (nama_pelanggan, no_telp, alamat) VALUES ('$nama_pelanggan', '$no_telp', '$alamat')");
        
        if ($query_simpan) {
            $pesan = "<div class='alert-success'>Data pelanggan berhasil disimpan!</div>";
        } else {
            $pesan = "<div class='alert-danger'>Gagal menyimpan data pelanggan!</div>";
        }
    }
}

// 2. PROSES HAPUS PELANGGAN
if (isset($_GET['hapus'])) {
    $id_pelanggan = $_GET['hapus'];
    $query_hapus = mysqli_query($koneksi, "DELETE FROM pelanggan WHERE id_pelanggan = '$id_pelanggan'");
    if ($query_hapus) {
        header("Location: pelanggan_admin.php");
        exit;
    } else {
        $pesan = "<div class='alert-danger'>Gagal menghapus data pelanggan!</div>";
    }
}

// 3. FITUR PENCARIAN PINTAR (Prioritas nama pas di baris teratas)
$keyword = "";
if (isset($_GET['cari']) && trim($_GET['cari']) !== "") {
    $keyword = mysqli_real_escape_string($koneksi, trim($_GET['cari']));
    
    $sql_pelanggan = "SELECT * FROM pelanggan 
                      WHERE nama_pelanggan LIKE '%$keyword%' 
                         OR no_telp LIKE '%$keyword%' 
                         OR alamat LIKE '%$keyword%' 
                      ORDER BY 
                         CASE 
                            WHEN nama_pelanggan = '$keyword' THEN 1
                            WHEN nama_pelanggan LIKE '$keyword%' THEN 2
                            ELSE 3
                         END, 
                         id_pelanggan DESC";
} else {
    $sql_pelanggan = "SELECT * FROM pelanggan ORDER BY id_pelanggan DESC";
}

$query_tampil = mysqli_query($koneksi, $sql_pelanggan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelanggan (Admin) - Jus Segar</title>
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
        .input-group input, .input-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; background: #fff;
        }
        .input-group input:focus, .input-group textarea:focus {
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
        <div class="logo-title">Kelola Pelanggan (Admin Panel)</div>
        <div class="nav-links">
            <a href="index_admin.php">🏠 Beranda</a>
        </div>
    </header>

    <div class="container">
        
        <!-- Form Tambah Pelanggan -->
        <div class="form-section">
            <h3>Tambah Pelanggan Baru</h3>
            <?php echo $pesan; ?>
            <form action="" method="POST">
                <div class="input-group">
                    <label>Nama Pelanggan</label>
                    <input type="text" name="nama_pelanggan" required placeholder="">
                </div>

                <div class="input-group">
                    <label>Nomor HP </label>
                    <!-- Ditambahkan maxlength="12", inputmode="numeric", dan filter oninput -->
                    <input type="text" name="no_telp" required maxlength="12" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="">
                </div>

                <div class="input-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" rows="3" required placeholder=""></textarea>
                </div>

                <button type="submit" name="simpan_pelanggan" class="btn-submit">Simpan Data Pelanggan</button>
            </form>
        </div>

        <!-- Tabel Daftar Pelanggan dengan Pencarian -->
        <div class="table-section">
            <div class="table-header">
                <h3>Daftar Pelanggan Terdaftar</h3>
                
                <!-- Form Pencarian -->
                <form action="" method="GET" class="search-form">
                    <input type="text" name="cari" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Cari nama..">
                    <button type="submit">Cari</button>
                    <?php if(!empty($keyword)): ?>
                        <a href="pelanggan_admin.php" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Pelanggan</th>
                        <th>No HP / Telepon</th>
                        <th>Alamat</th>
                        <th style="text-align: center;" width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    if(mysqli_num_rows($query_tampil) == 0){
                        echo "<tr><td colspan='5' style='text-align:center; padding: 20px; color: #888;'>Tidak ada data pelanggan ditemukan.</td></tr>";
                    }

                    while ($row = mysqli_fetch_assoc($query_tampil)) {
                        $is_match = (!empty($keyword) && strtolower($row['nama_pelanggan']) == strtolower($keyword));
                    ?>
                    <tr class="<?php echo $is_match ? 'highlight' : ''; ?>">
                        <td><?php echo $no++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nama_pelanggan']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['no_telp']); ?></td>
                        <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                        <td style="text-align: center;">
                            <a href="edit_pelanggan.php?id=<?php echo $row['id_pelanggan']; ?>" class="btn-edit">Edit</a>
                            <a href="pelanggan_admin.php?hapus=<?php echo $row['id_pelanggan']; ?>" class="btn-hapus" onclick="return confirm('Yakin ingin menghapus pelanggan ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>