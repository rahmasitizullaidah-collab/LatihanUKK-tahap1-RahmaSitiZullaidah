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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penjualan & Invoice - Jus Segar</title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Table Section */
        .table-section {
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
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .table-header h3 { margin: 0; color: #222; }

        /* Tombol Cetak Invoice */
        .btn-invoice {
            background-color: #27ae60;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: background-color 0.3s;
        }
        .btn-invoice:hover { background-color: #219653; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eee; padding: 12px 15px; text-align: left; font-size: 14px;}
        th { background-color: #fcf4ec; color: #333; font-weight: 600; }
        tr:hover { background-color: #fafafa; }

        /* Header Invoice khusus cetak (tersembunyi di web) */
        .invoice-print-header {
            display: none;
        }

        /* Pengaturan Cetak (Print Invoice Keseluruhan) */
        @media print {
            body { background: white; color: #000; }
            header, .nav-links, .btn-invoice {
                display: none !important;
            }
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            .table-section {
                box-shadow: none;
                padding: 0;
            }
            .table-header {
                border-bottom: 2px solid #000;
            }
            /* Munculkan kop surat / judul invoice saat diprint */
            .invoice-print-header {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
            table {
                border: 1px solid #000;
            }
            th, td {
                border: 1px solid #000;
            }
            th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 0 10px; }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">📋 Rincian Detail Penjualan</div>
        <div class="nav-links">
            <a href="transaksi.php">⬅ Kembali ke Transaksi</a>
            <a href="index.php">🏠 Beranda</a>
        </div>
    </header>

    <div class="container">
        
        <div class="table-section">
            <!-- Header khusus saat mode cetak invoice -->
            <div class="invoice-print-header">
                <h2>INVOICE PENJUALAN - TOKO JUS SEGAR</h2>
                <p style="margin: 2px 0; font-size: 13px;">Laporan Rekapitulasi Seluruh Transaksi Pelanggan</p>
                <p style="margin: 2px 0; font-size: 12px; color: #555;">Dicetak pada: <?php echo date('d/m/Y H:i'); ?></p>
                <hr style="border: 1px solid #000; margin-top: 15px;">
            </div>

            <div class="table-header">
                <h3>Daftar Seluruh Detail Produk Terjual</h3>
                <button onclick="window.print()" class="btn-invoice">🖨 Invoice </button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>ID Detail</th>
                        <th>ID Penjualan</th>
                        <th>Tanggal & Jam</th>
                        <th>Nama Pelanggan</th>
                        <th>Nama Produk / Jus</th>
                        <th>Jumlah Produk</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Mengambil data detail_penjualan, tanggal dari tabel penjualan, serta produk dan pelanggan
                    $sql_detail = "SELECT detail_penjualan.*, produk.nama_produk, pelanggan.nama_pelanggan, penjualan.tanggal_penjualan  
                                   FROM detail_penjualan 
                                   JOIN produk ON detail_penjualan.id_produk = produk.id_produk 
                                   JOIN penjualan ON detail_penjualan.id_penjualan = penjualan.id_penjualan
                                   JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan
                                   ORDER BY detail_penjualan.id_detail ASC";
                    
                    $query_tampil = mysqli_query($koneksi, $sql_detail);
                    $no = 1;
                    
                    if(mysqli_num_rows($query_tampil) == 0){
                        echo "<tr><td colspan='8' style='text-align:center; padding: 20px; color: #888;'>Belum ada data detail penjualan.</td></tr>";
                    }

                    while ($row = mysqli_fetch_assoc($query_tampil)) {
                    ?>
                    <tr>
                        <td><?php echo $no; ?></td>
                        <td>#<?php echo $row['id_detail']; ?></td>
                        <td>#<?php echo $row['id_penjualan']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_penjualan'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nama_pelanggan']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                        <td><?php echo $row['jumlah_produk']; ?> Gelas</td>
                        <td><strong>Rp <?php echo number_format($row['subtotal'], 0, ',', '.'); ?></strong></td>
                    </tr>
                    <?php 
                        $no++;
                    } 
                    ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>