<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

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

// ENDPOINT AJAX UNTUK MODAL NOTA DETAIL TRANSAKSI
if (isset($_GET['ajax_detail']) && isset($_GET['id'])) {
    $id_penjualan = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Ambil info utama transaksi & pelanggan
    $q_transaksi = mysqli_query($koneksi, "SELECT penjualan.*, pelanggan.nama_pelanggan FROM penjualan JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan WHERE penjualan.id_penjualan = '$id_penjualan'");
    $d_transaksi = mysqli_fetch_assoc($q_transaksi);

    if ($d_transaksi) {
        echo '<div style="text-align: center; border-bottom: 2px dashed #cbd5e1; padding-bottom: 12px; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0; color: #2c3e50; font-size: 16px;">TOKO JUS SEGAR</h4>';
        echo '<p style="margin: 3px 0 0 0; font-size: 12px; color: #64748b;">Nota Rincian Belanja Pelanggan</p>';
        echo '</div>';

        echo '<div style="font-size: 13px; margin-bottom: 15px; background: #f8fafc; padding: 10px; border-radius: 8px;">';
        echo '<div style="display: flex; justify-content: space-between; margin-bottom: 4px;"><span>ID Transaksi:</span> <strong>#' . $d_transaksi['id_penjualan'] . '</strong></div>';
        echo '<div style="display: flex; justify-content: space-between; margin-bottom: 4px;"><span>Nama Pelanggan:</span> <strong>' . htmlspecialchars($d_transaksi['nama_pelanggan']) . '</strong></div>';
        echo '<div style="display: flex; justify-content: space-between;"><span>Tanggal:</span> <strong>' . date('d/m/Y H:i', strtotime($d_transaksi['tanggal_penjualan'])) . '</strong></div>';
        echo '</div>';
        
        echo '<table style="width:100%; border-collapse:collapse; font-size: 13px;">';
        echo '<tr style="border-bottom: 2px solid #e2e8f0; color: #475569;"><th style="padding: 8px 4px; text-align: left;">Nama Produk / Jus</th><th style="padding: 8px 4px; text-align: center;">Qty</th><th style="padding: 8px 4px; text-align: right;">Subtotal</th></tr>';
        
        $q_item = mysqli_query($koneksi, "SELECT detail_penjualan.*, produk.nama_produk FROM detail_penjualan JOIN produk ON detail_penjualan.id_produk = produk.id_produk WHERE detail_penjualan.id_penjualan = '$id_penjualan'");
        $grand_total = 0;
        while($item = mysqli_fetch_assoc($q_item)){
            $grand_total += $item['subtotal'];
            echo '<tr style="border-bottom: 1px solid #f1f5f9;">';
            echo '<td style="padding: 8px 4px;">' . htmlspecialchars($item['nama_produk']) . '</td>';
            echo '<td style="padding: 8px 4px; text-align: center;">' . $item['jumlah_produk'] . ' Gelas</td>';
            echo '<td style="padding: 8px 4px; text-align: right;">Rp ' . number_format($item['subtotal'], 0, ',', '.') . '</td>';
            echo '</tr>';
        }
        
        echo '<tr>';
        echo '<td colspan="2" style="padding: 12px 4px; text-align: right; font-weight: bold; font-size: 14px;">Total Keseluruhan:</td>';
        echo '<td style="padding: 12px 4px; text-align: right; font-weight: bold; font-size: 14px; color: #d97706;">Rp ' . number_format($grand_total, 0, ',', '.') . '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<div style="text-align: center; margin-top: 20px;">';
        echo '<button onclick="tutupModal()" style="background-color: #2c3e50; color: white; border: none; padding: 7px 20px; border-radius: 6px; cursor: pointer; font-size: 13px;">Tutup</button>';
        echo '</div>';
    } else {
        echo "<p style='text-align:center; color:red;'>Data transaksi tidak ditemukan.</p>";
    }
    exit;
}

// PROSES HAPUS DETAIL PENJUALAN
if (isset($_GET['hapus'])) {
    $id_detail = $_GET['hapus'];

    $q_detail = mysqli_query($koneksi, "SELECT * FROM detail_penjualan WHERE id_detail = '$id_detail'");
    $d_detail = mysqli_fetch_assoc($q_detail);

    if ($d_detail) {
        $id_produk = $d_detail['id_produk'];
        $jumlah_beli = $d_detail['jumlah_produk'];
        $id_penjualan = $d_detail['id_penjualan'];

        mysqli_query($koneksi, "UPDATE produk SET stok = stok + $jumlah_beli WHERE id_produk = '$id_produk'");
        mysqli_query($koneksi, "DELETE FROM detail_penjualan WHERE id_detail = '$id_detail'");

        $q_cek_sisa = mysqli_query($koneksi, "SELECT * FROM detail_penjualan WHERE id_penjualan = '$id_penjualan'");
        if (mysqli_num_rows($q_cek_sisa) == 0) {
            mysqli_query($koneksi, "DELETE FROM penjualan WHERE id_penjualan = '$id_penjualan'");
        }

        header("Location: detail_penjualan_admin.php");
        exit;
    }
}

// FITUR PENCARIAN PINTAR
$keyword = "";
if (isset($_GET['cari']) && trim($_GET['cari']) !== "") {
    $keyword = mysqli_real_escape_string($koneksi, trim($_GET['cari']));
    
    $sql_detail = "SELECT detail_penjualan.*, produk.nama_produk, pelanggan.nama_pelanggan, penjualan.tanggal_penjualan 
                   FROM detail_penjualan 
                   JOIN produk ON detail_penjualan.id_produk = produk.id_produk 
                   JOIN penjualan ON detail_penjualan.id_penjualan = penjualan.id_penjualan
                   JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan
                   WHERE pelanggan.nama_pelanggan LIKE '%$keyword%' 
                      OR produk.nama_produk LIKE '%$keyword%' 
                      OR penjualan.tanggal_penjualan LIKE '%$keyword%'
                      OR detail_penjualan.id_penjualan LIKE '%$keyword%'
                   ORDER BY detail_penjualan.id_detail DESC";
} else {
    $sql_detail = "SELECT detail_penjualan.*, produk.nama_produk, pelanggan.nama_pelanggan, penjualan.tanggal_penjualan 
                   FROM detail_penjualan 
                   JOIN produk ON detail_penjualan.id_produk = produk.id_produk 
                   JOIN penjualan ON detail_penjualan.id_penjualan = penjualan.id_penjualan
                   JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan
                   ORDER BY detail_penjualan.id_detail DESC";
}

$query_tampil = mysqli_query($koneksi, $sql_detail);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penjualan & Invoice (Admin) - Jus Segar</title>
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
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Card Total Omzet */
        .omzet-card {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .omzet-card h4 { margin: 0; font-size: 14px; font-weight: 400; color: #cbd5e1; text-transform: uppercase; letter-spacing: 1px;}
        .omzet-card h2 { margin: 5px 0 0 0; font-size: 28px; font-weight: 700; color: #fbe0c9; }
        .omzet-icon { font-size: 35px; background: rgba(255,255,255,0.1); padding: 10px 15px; border-radius: 10px; }

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
            width: 200px;
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

        /* Container Tombol Aksi */
        .action-container {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: center;
        }

        /* Tombol Aksi Teks (Detail Modal) */
        .btn-action-text {
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            border: none;
            cursor: pointer;
            text-align: center;
        }
        .btn-detail-text { background-color: #3b82f6; color: white; }
        .btn-detail-text:hover { background-color: #2563eb; }

        /* Tombol Aksi Berbasis Ikon (Edit & Hapus) */
        .btn-icon {
            width: 26px;
            height: 26px;
            padding: 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.2s, transform 0.1s;
            text-decoration: none;
        }
        .btn-icon:hover { opacity: 0.85; transform: scale(1.05); }
        .btn-icon svg { width: 13px; height: 13px; fill: currentColor; }

        .btn-edit  { background-color: #0ea5e9; color: white; }
        .btn-hapus { background-color: #ef4444; color: white; }

        .btn-cetak-laporan {
            background-color: #2c3e50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: background-color 0.3s;
        }
        .btn-cetak-laporan:hover { background-color: #1a252f; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eee; padding: 12px 15px; text-align: left; font-size: 14px;}
        th { background-color: #fcf4ec; color: #333; font-weight: 600; }
        tr:hover { background-color: #fafafa; }

        .highlight { background-color: #fef9e7 !important; }

        /* Style Modal Nota Mengambang */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
        }
        .modal-content {
            background-color: #fff;
            margin: 8% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            position: relative;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .close-modal {
            color: #aaa;
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover { color: #000; }

        /* Header Invoice khusus cetak */
        .invoice-print-header { display: none; }

        @media print {
            body { background: white; color: #000; }
            header, .nav-links, .btn-cetak-laporan, .search-form, th:last-child, td:last-child, .omzet-card {
                display: none !important;
            }
            .container { max-width: 100%; margin: 0; padding: 0; }
            .table-section { box-shadow: none; padding: 0; }
            .table-header { border-bottom: 2px solid #000; display: none; }
            
            .invoice-print-header {
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
            .print-omzet-box {
                border: 2px solid #000;
                padding: 10px;
                margin: 15px 0;
                text-align: left;
                font-size: 15px;
                font-weight: bold;
                background-color: #f9f9f9 !important;
                -webkit-print-color-adjust: exact;
            }

            table { border: 1px solid #000; margin-top: 10px; }
            th, td { border: 1px solid #000; }
            th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
        }

        @media (max-width: 768px) {
            .container { padding: 0 10px; }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">📋 Rincian Detail Penjualan (Admin)</div>
        <div class="nav-links">
            <a href="transaksi_admin.php">⬅ Kembali ke Transaksi</a>
            <a href="index_admin.php">🏠 Beranda </a>
        </div>
    </header>

    <div class="container">
        
        <?php
        $sql_omzet = str_replace("SELECT detail_penjualan.*, produk.nama_produk, pelanggan.nama_pelanggan, penjualan.tanggal_penjualan", "SELECT SUM(detail_penjualan.subtotal) as total_omzet", $sql_detail);
        $query_omzet = mysqli_query($koneksi, $sql_omzet);
        $data_omzet = mysqli_fetch_assoc($query_omzet);
        $total_omzet = $data_omzet['total_omzet'] ?? 0;
        ?>

        <!-- Kotak Total Omzet -->
        <div class="omzet-card">
            <div>
                <h4>Total Omzet Penjualan <?php echo !empty($keyword) ? "(Hasil Pencarian)" : "(Keseluruhan)"; ?></h4>
                <h2>Rp <?php echo number_format($total_omzet, 0, ',', '.'); ?></h2>
            </div>
            <div class="omzet-icon">💰</div>
        </div>
        
        <div class="table-section">
            <div class="invoice-print-header">
                <h2>INVOICE PENJUALAN - TOKO JUS SEGAR</h2>
                <p style="margin: 2px 0; font-size: 13px;">Laporan Rekapitulasi Rincian Penjualan (Admin)</p>
                <p style="margin: 2px 0; font-size: 12px; color: #555;">Dicetak pada: <?php echo date('d/m/Y H:i'); ?></p>
                
                <div class="print-omzet-box">
                    TOTAL OMZET PENJUALAN: Rp <?php echo number_format($total_omzet, 0, ',', '.'); ?>
                </div>
                <hr style="border: 1px solid #000; margin-top: 10px;">
            </div>

            <div class="table-header">
                <h3>Daftar Seluruh Detail Produk Terjual</h3>
                
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <form action="" method="GET" class="search-form">
                        <input type="text" name="cari" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Cari pelanggan / produk...">
                        <button type="submit">Cari</button>
                        <?php if(!empty($keyword)): ?>
                            <a href="detail_penjualan_admin.php" class="btn-reset">Reset</a>
                        <?php endif; ?>
                    </form>

                    <button onclick="window.print()" class="btn-cetak-laporan">🖨 Cetak Laporan</button>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>ID Detail</th>
                        <th>ID Transaksi</th>
                        <th>Tanggal & Jam</th>
                        <th>Nama Pelanggan</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th style="text-align: center;" width="14%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    if(mysqli_num_rows($query_tampil) == 0){
                        echo "<tr><td colspan='8' style='text-align:center; padding: 20px; color: #888;'>Belum ada data detail penjualan ditemukan.</td></tr>";
                    }

                    while ($row = mysqli_fetch_assoc($query_tampil)) {
                        $is_match = (!empty($keyword) && (strtolower($row['nama_pelanggan']) == strtolower($keyword) || strtolower($row['nama_produk']) == strtolower($keyword)));
                    ?>
                    <tr class="<?php echo $is_match ? 'highlight' : ''; ?>">
                        <td><?php echo $no++; ?></td>
                        <td>#<?php echo $row['id_detail']; ?></td>
                        <td>#<?php echo $row['id_penjualan']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_penjualan'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nama_pelanggan']); ?></strong></td>
                        <td><?php echo $row['jumlah_produk']; ?> Gelas</td>
                        <td><strong>Rp <?php echo number_format($row['subtotal'], 0, ',', '.'); ?></strong></td>
                        <td style="text-align: center;">
                            <div class="action-container">
                                <!-- Tombol Detail (Memicu Modal Nota Berdasarkan ID Transaksi) -->
                                <button onclick="bukaModal(<?php echo $row['id_penjualan']; ?>)" class="btn-action-text btn-detail-text" title="Lihat Nota Transaksi">Detail</button>
                                
                                <!-- Tombol Edit (Ikon SVG) -->
                                <a href="edit_transaksi.php?id=<?php echo $row['id_penjualan']; ?>" class="btn-icon btn-edit" title="Edit Transaksi">
                                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>

                                <!-- Tombol Hapus (Ikon SVG) -->
                                <a href="detail_penjualan_admin.php?hapus=<?php echo $row['id_detail']; ?>" class="btn-icon btn-hapus" onclick="return confirm('Yakin ingin menghapus item detail ini? Stok produk akan dikembalikan.')" title="Hapus Item">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal Mengambang Gaya Nota -->
    <div id="modalDetail" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="tutupModal()">&times;</span>
            <div id="isiModalDetail">
                <!-- Konten nota rincian produk dimuat via AJAX -->
            </div>
        </div>
    </div>

    <script>
        function bukaModal(idPenjualan) {
            const modal = document.getElementById('modalDetail');
            const isiModal = document.getElementById('isiModalDetail');
            modal.style.display = 'block';
            isiModal.innerHTML = '<p style="text-align:center; color:#666; padding: 20px;">Memuat nota transaksi...</p>';

            fetch('<?php echo basename(__FILE__); ?>?ajax_detail=1&id=' + idPenjualan)
                .then(response => response.text())
                .then(data => {
                    isiModal.innerHTML = data;
                })
                .catch(error => {
                    isiModal.innerHTML = '<p style="text-align:center; color:red; padding: 20px;">Gagal memuat rincian nota.</p>';
                });
        }

        function tutupModal() {
            document.getElementById('modalDetail').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalDetail');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

</body>
</html>