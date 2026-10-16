<?php
// Koneksi ke Database
$host = "localhost";
$user = "root";       
$pass = "";           
$db   = "db_kasir"; 

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil ID dari URL
$id_penjualan = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data penjualan dan pelanggan (termasuk metode_pembayaran)
$query_penjualan = mysqli_query($koneksi, "SELECT penjualan.*, pelanggan.nama_pelanggan 
                                           FROM penjualan 
                                           JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan 
                                           WHERE penjualan.id_penjualan = '$id_penjualan'");
$data_penjualan = mysqli_fetch_assoc($query_penjualan);

if (!$data_penjualan) {
    die("<h3 style='text-align:center; margin-top:50px;'>Data transaksi tidak ditemukan!</h3>");
}

// Ambil detail produk yang dibeli
$query_detail = mysqli_query($koneksi, "SELECT detail_penjualan.*, produk.nama_produk, produk.harga 
                                        FROM detail_penjualan 
                                        JOIN produk ON detail_penjualan.id_produk = produk.id_produk 
                                        WHERE detail_penjualan.id_penjualan = '$id_penjualan'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran #<?php echo $data_penjualan['id_penjualan']; ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace; /* Gaya font struk kasir */
            background-color: #eee;
            margin: 0;
            padding: 20px;
        }
        .struk-container {
            max-width: 320px;
            background: #fff;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .dashed-line {
            border-top: 1px dashed #333;
            margin: 10px 0;
        }
        table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }
        th, td {
            padding: 4px 0;
        }
        /* Tombol Navigasi Bawah Struk */
        .btn-container {
            text-align: center;
            margin-top: 20px;
        }
        .btn {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            margin: 0 3px;
        }
        .btn:hover { background-color: #1a252f; }
        .btn-print { background-color: #27ae60; }
        .btn-print:hover { background-color: #219653; }

        /* Sembunyikan tombol saat dicetak fisik */
        @media print {
            body { background: none; padding: 0; }
            .struk-container { box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="struk-container">
        <!-- Header Struk -->
        <div class="text-center">
            <h3 style="margin: 0 0 5px 0;">JUS SEGAR</h3>
            <p style="margin: 0; font-size: 12px;">Jl. Tulip's Raya No. 11</p>
        </div>
        
        <div class="dashed-line"></div>
        
        <div style="font-size: 13px;">
            <div>No Transaksi : #<?php echo $data_penjualan['id_penjualan']; ?></div>
            <div>Tanggal      : <?php echo date('d/m/Y H:i', strtotime($data_penjualan['tanggal_penjualan'])); ?></div>
            <div>Pelanggan    : <?php echo htmlspecialchars($data_penjualan['nama_pelanggan']); ?></div>
        </div>

        <div class="dashed-line"></div>

        <!-- Tabel Item -->
        <table>
            <thead>
                <tr>
                    <th align="left">Item</th>
                    <th align="center">Qty</th>
                    <th align="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row_d = mysqli_fetch_assoc($query_detail)) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row_d['nama_produk']); ?></td>
                    <td align="center"><?php echo $row_d['jumlah_produk']; ?></td>
                    <td align="right">Rp <?php echo number_format($row_d['subtotal'], 0, ',', '.'); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <!-- Total -->
        <div style="font-size: 14px; font-weight: bold;" class="text-right">
            Total: Rp <?php echo number_format($data_penjualan['total_harga'], 0, ',', '.'); ?>
        </div>

        <div class="dashed-line"></div>

        <div class="text-center" style="font-size: 12px; margin-top: 10px;">
            Terima Kasih Atas Kunjungan Anda!
        </div>

        <!-- Tombol Aksi (Tidak ikut tercetak) -->
        <div class="no-print btn-container">
            <button onclick="window.print()" class="btn btn-print">🖨 Cetak</button>
            <button onclick="window.close()" class="btn">✖ Tutup</button>
        </div>
    </div>

</body>
</html>