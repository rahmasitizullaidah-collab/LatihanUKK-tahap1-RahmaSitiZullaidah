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

// ==========================================
// MENGAMBIL DATA STATISTIK UTAMA UNTUK ADMIN
// ==========================================

// 1. Ambil Total Pelanggan
$total_pelanggan = 0;
$query_pelanggan = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM pelanggan");
if ($query_pelanggan) {
    $data_pelanggan = mysqli_fetch_assoc($query_pelanggan);
    $total_pelanggan = $data_pelanggan['total'];
}

// 2. Ambil Total Stok Produk
$total_produk = 0;
$query_produk = mysqli_query($koneksi, "SELECT SUM(stok) as total_stok FROM produk");
if ($query_produk) {
    $data_produk = mysqli_fetch_assoc($query_produk);
    $total_produk = $data_produk['total_stok'] ? $data_produk['total_stok'] : 0;
}

// 3. Ambil Total Transaksi & Pendapatan Keseluruhan
$jml_transaksi = 0;
$total_pendapatan = 0;

$query_penjualan = mysqli_query($koneksi, "SELECT COUNT(*) as jml, SUM(total_harga) as pendapatan FROM penjualan");
if ($query_penjualan) {
    $data_penjualan = mysqli_fetch_assoc($query_penjualan);
    $jml_transaksi = $data_penjualan['jml'] ? $data_penjualan['jml'] : 0;
    $total_pendapatan = $data_penjualan['pendapatan'] ? $data_penjualan['pendapatan'] : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jus Segar Dashboard - Administrator</title>
    <style>
        /* Pengaturan Dasar */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fcf4ec; 
            color: #333;
        }

        /* Bagian Header (Navigasi Atas) */
        header {
            background-color: #fbe0c9;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-bottom: 4px solid #2c3e50; /* Aksen admin */
        }
        .logo-title {
            font-size: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
            font-weight: 500;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            transition: color 0.3s;
        }
        .nav-links a:hover {
            color: #2c3e50;
        }

        /* Bagian Judul Utama (Hero Section) */
        .hero {
            text-align: center;
            margin: 40px 0;
        }
        .hero h1 {
            font-size: 42px;
            margin: 0 0 10px 0;
            color: #222;
        }
        .hero p {
            font-size: 16px;
            margin: 5px 0;
            color: #555;
        }

        /* Grid Utama (Kontainer Kartu) */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px 40px 20px;
        }
        
        /* Baris 1: Kartu Statistik */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .stat-card.purple { background-color: #e2d9f3; }
        .stat-card.yellow { background-color: #fcebc4; }
        .stat-card.orange { background-color: #fce2d1; }
        .stat-card.blue   { background-color: #dce5f9; }
        
        .stat-icon {
            font-size: 35px;
        }
        .stat-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }
        .stat-info p {
            margin: 0 0 5px 0;
            font-size: 12px;
            color: #666;
        }
        .stat-info h2 {
            margin: 0;
            font-size: 22px;
            color: #111;
        }

        /* Baris 2: Kartu Menu Aksi Admin (4 Kolom) */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .action-card {
            background-color: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .action-card.bg-purple { background-color: #e8e2f8; }
        .action-card.bg-yellow { background-color: #fef1d8; }
        .action-card.bg-orange { background-color: #feebe0; }
        .action-card.bg-blue   { background-color: #e8effb; }

        .action-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }
        .action-card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #222;
        }
        .action-card p {
            font-size: 13px;
            color: #555;
            margin-bottom: 25px;
            line-height: 1.4;
        }
        .btn-kelola {
            background-color: #2c3e50;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
            display: inline-block;
        }
        .btn-kelola:hover {
            background-color: #1a252f;
        }

        /* Katalog Produk Premium */
        .catalog-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            border: 1px solid #fce2d1;
        }
        .catalog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #fbe0c9;
            padding-bottom: 15px;
        }
        .catalog-header h2 {
            margin: 0;
            color: #222;
            font-size: 22px;
        }
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .product-card {
            background: #fffaf5;
            border: 1px solid #fce2d1;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(207, 102, 58, 0.1);
        }
        .product-img-container {
            width: 100%;
            height: 160px;
            background-color: #fbe0c9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .product-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-img-placeholder {
            font-size: 50px;
        }
        .product-body {
            padding: 15px;
            text-align: center;
        }
        .product-title {
            font-size: 16px;
            font-weight: bold;
            color: #222;
            margin: 0 0 8px 0;
        }
        .product-price {
            font-size: 15px;
            font-weight: bold;
            color: #cf663a;
            margin-bottom: 10px;
        }
        .product-stock {
            display: inline-block;
            background: #e8f8f5;
            color: #27ae60;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .product-stock.habis {
            background: #fdebd0;
            color: #e74c3c;
        }

        /* Responsif */
        @media (max-width: 900px) {
            .stats-grid, .action-grid, .catalog-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header>
        <div class="logo-title">
            Jus Segar - Panel Admin
        </div>
        <div class="nav-links">
            <a href="index_admin.php">Beranda</a>
            <a href="logout_admin.php" style="color: #e74c3c; font-weight: bold;">Logout Admin</a>
        </div>
    </header>

    <!-- JUDUL UTAMA -->
    <div class="hero">
        <h1>ADMINISTRATOR</h1>
        <p>Manajemen & Rekapitulasi Sistem Toko Jus Segar</p>
        <p>Selamat datang, <b><?php echo htmlspecialchars($_SESSION['nama_admin']); ?></b>! Panel kontrol utama aktif.</p>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="container">
        
        <!-- BARIS 1: STATISTIK KESELURUHAN -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h4>Total Pelanggan</h4>
                    <p>Semua Pelanggan</p>
                    <h2><?php echo $total_pelanggan; ?> Orang</h2>
                </div>
            </div>
            
            <div class="stat-card yellow">
                <div class="stat-icon">🧺</div>
                <div class="stat-info">
                    <h4>Stok Produk</h4>
                    <p>Total Stok Gudang</p>
                    <h2><?php echo $total_produk; ?> jus</h2>
                </div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-icon">🖨️</div>
                <div class="stat-info">
                    <h4>Total Transaksi</h4>
                    <p>Keseluruhan Transaksi</p>
                    <h2><?php echo $jml_transaksi; ?> Transaksi</h2>
                </div>
            </div>
            
            <div class="stat-card blue">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h4>Total Pendapatan</h4>
                    <p>Akumulasi Omzet</p>
                    <h2>Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?></h2>
                </div>
            </div>
        </div>

        <!-- BARIS 2: MENU AKSI ADMIN -->
        <div class="action-grid">
            <div class="action-card bg-purple">
                <div>
                    <div class="action-icon">🧍‍♀️</div>
                    <h3>Data Pelanggan</h3>
                    <p>Mencatat dan mengelola data dasar pelanggan langganan.</p>
                </div>
                <a href="pelanggan_admin.php" class="btn-kelola">Kelola Data</a>
            </div>

            <div class="action-card bg-yellow">
                <div>
                    <div class="action-icon">🍎</div>
                    <h3>Data Produk</h3>
                    <p>Ketersediaan Buah & Varian. Kelola stok buah untuk bahan baku dan menu jus.</p>
                </div>
                <a href="produk_admin.php" class="btn-kelola">Kelola Data</a>
            </div>

            <div class="action-card bg-orange">
                <div>
                    <div class="action-icon">🖨️</div>
                    <h3>Penjualan</h3>
                    <p>Mencatat transaksi penjualan baru dengan cepat.</p>
                </div>
                <a href="transaksi_admin.php" class="btn-kelola">Buka Kasir</a>
            </div>

            <div class="action-card bg-blue">
                <div>
                    <div class="action-icon">📊</div>
                    <h3>Laporan Penjualan</h3>
                    <p>Melihat detail lengkap dan rekap omzet per transaksi.</p>
                </div>
                <a href="detail_penjualan_admin.php" class="btn-kelola">Lihat Laporan</a>
            </div>
        </div>

        <!-- BARIS 3: KATALOG PRODUK PREMIUM -->
        <div class="catalog-section">
            <div class="catalog-header">
                <h2>✨ Katalog Varian Jus Premium</h2>
                <span style="font-size: 14px; color: #666;">Daftar menu dan ketersediaan stok segar hari ini</span>
            </div>

            <div class="catalog-grid">
                <?php
                // Mengambil data produk dari database
                $query_katalog = mysqli_query($koneksi, "SELECT * FROM produk ORDER BY id_produk ASC");
                
                if (mysqli_num_rows($query_katalog) > 0) {
                    while ($produk = mysqli_fetch_assoc($query_katalog)) {
                        // Cek apakah kolom gambar terisi
                        $ada_gambar = isset($produk['gambar']) && !empty($produk['gambar']);
                ?>
                <div class="product-card">
                    <div class="product-img-container">
                        <?php if ($ada_gambar): ?>
                            <!-- Diarahkan ke folder assets/img/ -->
                            <img src="assets/img/<?php echo htmlspecialchars($produk['gambar']); ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>">
                        <?php else: ?>
                            <div class="product-img-placeholder">🥤</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <h3 class="product-title"><?php echo htmlspecialchars($produk['nama_produk']); ?></h3>
                        <div class="product-price">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></div>
                        
                        <?php if ($produk['stok'] > 0): ?>
                            <span class="product-stock">Stok: <?php echo $produk['stok']; ?> Gelas</span>
                        <?php else: ?>
                            <span class="product-stock habis">Stok Habis</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    }
                } else {
                    echo "<div style='grid-column: span 4; text-align: center; color: #888; padding: 20px;'>Belum ada produk jus yang terdaftar di katalog.</div>";
                }
                ?>
            </div>
        </div>

    </div>

</body>
</html>