<?php
session_start();
date_default_timezone_set('Asia/Jakarta'); 

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

// Menggunakan objek MySQLi agar mendukung prepared statements
$koneksi = new mysqli($host, $user, $pass, $db);
if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$pesan = "";
$script_alert = ""; // Variabel untuk menampung script pop-up alert

if (isset($_GET['status_notif'])) {
    if ($_GET['status_notif'] == 'sukses') {
        $pesan = "<div class='alert-success'>Transaksi berhasil disimpan!</div>";
        $script_alert = "alert('Transaksi berhasil disimpan!');";
    } elseif ($_GET['status_notif'] == 'diedit') {
        $pesan = "<div class='alert-success'>Data transaksi berhasil diperbarui!</div>";
        $script_alert = "alert('Data transaksi berhasil diperbarui!');";
    } elseif ($_GET['status_notif'] == 'dihapus') {
        $pesan = "<div class='alert-danger'>Status riwayat transaksi diubah menjadi sudah dihapus!</div>";
        $script_alert = "alert('Status riwayat transaksi berhasil diubah menjadi sudah dihapus!');";
    } elseif ($_GET['status_notif'] == 'batal') {
        $pesan = "<div class='alert-success'>Status transaksi berhasil dipulihkan menjadi sukses!</div>";
        $script_alert = "alert('Status transaksi berhasil dipulihkan menjadi sukses!');";
    }
}

// Fitur Ubah Status Menjadi 'dihapus' saat tombol Hapus diklik
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus_transaksi') {
    $id_penjualan_del = intval($_GET['id']);
    
    $stmt = $koneksi->prepare("UPDATE penjualan SET status = 'dihapus' WHERE id_penjualan = ?");
    $stmt->bind_param("i", $id_penjualan_del);
    if ($stmt->execute()) {
        header("Location: transaksi_admin.php?status_notif=dihapus");
        exit();
    } else {
        $pesan = "<div class='alert-danger'>Gagal memperbarui status transaksi.</div>";
    }
    $stmt->close();
}

// Fitur Batalkan Hapus (Mengembalikan Status Menjadi 'sukses' saat tombol Batal diklik)
if (isset($_GET['aksi']) && $_GET['aksi'] == 'batal_hapus') {
    $id_penjualan_batal = intval($_GET['id']);
    
    $stmt = $koneksi->prepare("UPDATE penjualan SET status = 'sukses' WHERE id_penjualan = ?");
    $stmt->bind_param("i", $id_penjualan_batal);
    if ($stmt->execute()) {
        header("Location: transaksi_admin.php?status_notif=batal");
        exit();
    } else {
        $pesan = "<div class='alert-danger'>Gagal memulihkan status transaksi.</div>";
    }
    $stmt->close();
}

// Jika tombol "Proses Transaksi" ditekan
if (isset($_POST['simpan_transaksi'])) {
    $id_pelanggan      = intval($_POST['id_pelanggan']);
    $tanggal_penjualan = date('Y-m-d H:i:s'); 
    
    $arr_produk = $_POST['id_produk'] ?? [];
    $arr_jumlah = $_POST['jumlah'] ?? [];

    if (empty($id_pelanggan)) {
        $pesan = "<div class='alert-danger'>Silakan pilih pelanggan terlebih dahulu!</div>";
    } elseif (empty($arr_produk)) {
        $pesan = "<div class='alert-danger'>Minimal harus ada 1 produk yang dipilih!</div>";
    } else {
        $stok_cukup = true;
        $error_msg = "";
        $items_valid = [];
        $total_seluruhnya = 0;

        for ($i = 0; $i < count($arr_produk); $i++) {
            $id_p = intval($arr_produk[$i]);
            $jml  = (int) $arr_jumlah[$i];

            if ($id_p <= 0 || $jml <= 0) continue;

            $stmt_p = $koneksi->prepare("SELECT * FROM produk WHERE id_produk = ?");
            $stmt_p->bind_param("i", $id_p);
            $stmt_p->execute();
            $result_p = $stmt_p->get_result();
            $data_produk = $result_p->fetch_assoc();
            $stmt_p->close();

            if ($data_produk) {
                $stok_sekarang = $data_produk['stok'];
                $harga = $data_produk['harga'];

                if ($jml > $stok_sekarang) {
                    $stok_cukup = false;
                    $error_msg = "Stok produk <b>" . htmlspecialchars($data_produk['nama_produk']) . "</b> tidak mencukupi! Sisa stok: " . $stok_sekarang;
                    break;
                }

                $subtotal = $harga * $jml;
                $total_seluruhnya += $subtotal;

                $items_valid[] = [
                    'id_produk' => $id_p,
                    'jumlah' => $jml,
                    'subtotal' => $subtotal,
                    'stok_sekarang' => $stok_sekarang
                ];
            }
        }

        if (!$stok_cukup) {
            $pesan = "<div class='alert-danger'>$error_msg</div>";
        } elseif (empty($items_valid)) {
            $pesan = "<div class='alert-danger'>Tidak ada produk valid yang dipilih!</div>";
        } else {
            $koneksi->begin_transaction();

            try {
                $stmt_jual = $koneksi->prepare("INSERT INTO penjualan (id_pelanggan, tanggal_penjualan, total_harga, status) VALUES (?, ?, ?, 'sukses')");
                $stmt_jual->bind_param("isd", $id_pelanggan, $tanggal_penjualan, $total_seluruhnya);
                
                if (!$stmt_jual->execute()) {
                    throw new Exception("Gagal menyimpan data penjualan utama.");
                }

                $id_penjualan_baru = $stmt_jual->insert_id;
                $stmt_jual->close();

                foreach ($items_valid as $item) {
                    $id_p = $item['id_produk'];
                    $jml  = $item['jumlah'];
                    $sub  = $item['subtotal'];
                    $stok_baru = $item['stok_sekarang'] - $jml;

                    $stmt_detail = $koneksi->prepare("INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah_produk, subtotal) VALUES (?, ?, ?, ?)");
                    $stmt_detail->bind_param("iiid", $id_penjualan_baru, $id_p, $jml, $sub);
                    if (!$stmt_detail->execute()) {
                        throw new Exception("Gagal menyimpan detail penjualan.");
                    }
                    $stmt_detail->close();

                    $stmt_upd = $koneksi->prepare("UPDATE produk SET stok = ? WHERE id_produk = ?");
                    $stmt_upd->bind_param("ii", $stok_baru, $id_p);
                    if (!$stmt_upd->execute()) {
                        throw new Exception("Gagal memperbarui stok produk.");
                    }
                    $stmt_upd->close();
                }

                $koneksi->commit();
                header("Location: " . $_SERVER['PHP_SELF'] . "?status_notif=sukses");
                exit();

            } catch (Exception $e) {
                $koneksi->rollback();
                $pesan = "<div class='alert-danger'>" . $e->getMessage() . "</div>";
            }
        }
    }
}

// Handler request AJAX untuk mengambil detail produk via modal
if (isset($_GET['ajax_detail']) && isset($_GET['id'])) {
    $id_penjualan = intval($_GET['id']);
    
    $stmt_jual = $koneksi->prepare("SELECT p.*, pl.nama_pelanggan FROM penjualan p LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan WHERE p.id_penjualan = ?");
    $stmt_jual->bind_param("i", $id_penjualan);
    $stmt_jual->execute();
    $data_jual = $stmt_jual->get_result()->fetch_assoc();
    $stmt_jual->close();

    $stmt_det = $koneksi->prepare("SELECT dp.*, pr.nama_produk, pr.harga FROM detail_penjualan dp LEFT JOIN produk pr ON dp.id_produk = pr.id_produk WHERE dp.id_penjualan = ?");
    $stmt_det->bind_param("i", $id_penjualan);
    $stmt_det->execute();
    $q_detail = $stmt_det->get_result();

    if ($data_jual) {
        echo "<div style='margin-bottom: 12px; font-size: 13px; color: #444;'>";
        echo "<b>ID Transaksi:</b> #" . $data_jual['id_penjualan'] . "<br>";
        echo "<b>Pelanggan:</b> " . htmlspecialchars($data_jual['nama_pelanggan'] ?? 'Umum') . "<br>";
        echo "<b>Tanggal:</b> " . date('d/m/Y H:i', strtotime($data_jual['tanggal_penjualan'])) . "<br>";
        echo "</div>";

        echo "<table style='width:100%; border-collapse: collapse; font-size: 13px;'>";
        echo "<thead><tr style='background:#fcf4ec;'><th style='padding:8px; border-bottom:1px solid #ddd;'>Produk</th><th style='padding:8px; border-bottom:1px solid #ddd; text-align:center;'>Qty</th><th style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>Subtotal</th></tr></thead>";
        echo "<tbody>";
        
        while ($row = $q_detail->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding:8px; border-bottom:1px solid #eee;'>" . htmlspecialchars($row['nama_produk']) . "</td>";
            echo "<td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>" . $row['jumlah_produk'] . "</td>";
            echo "<td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>Rp " . number_format($row['subtotal'], 0, ',', '.') . "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "<tfoot>";
        echo "<tr><td colspan='2' style='padding:10px; font-weight:bold; text-align:right;'>Total Keseluruhan:</td><td style='padding:10px; font-weight:bold; text-align:right; color:#cf663a;'>Rp " . number_format($data_jual['total_harga'], 0, ',', '.') . "</td></tr>";
        echo "</tfoot>";
        echo "</table>";
    } else {
        echo "<p style='color:red; text-align:center;'>Data transaksi tidak ditemukan.</p>";
    }
    $stmt_det->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir & Transaksi (Admin Panel)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #fcf4ec; color: #333; }
        header { background-color: #fbe0c9; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-bottom: 3px solid #2c3e50; }
        .logo-title { font-size: 20px; font-weight: bold; color: #222; }
        .nav-links a { text-decoration: none; color: #333; font-weight: 500; margin-left: 20px; transition: color 0.3s; }
        .nav-links a:hover { color: #cf663a; }
        .container { max-width: 1350px; margin: 30px auto; padding: 0 20px; display: flex; gap: 25px; }
        .form-section { flex: 1.2; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); height: fit-content; }
        .form-section h3 { margin-top: 0; color: #222; border-bottom: 2px solid #fbe0c9; padding-bottom: 10px; margin-bottom: 20px; font-size: 18px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px;}
        .input-group select, .input-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; background: #fff; }
        .input-group select:focus, .input-group input:focus { outline: none; border-color: #cf663a; }
        .produk-item-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #faf8f5; padding: 10px; border-radius: 6px; border: 1px solid #eee; }
        .produk-item-row select { flex: 2; }
        .produk-item-row input { flex: 1; }
        .btn-hapus-row { background-color: #e74c3c; color: white; border: none; padding: 10px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; }
        .btn-tambah-row { background-color: #27ae60; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; margin-bottom: 15px; display: inline-block; }
        .total-box { background: #fcf4ec; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border: 1px dashed #cf663a; font-weight: bold; color: #333; }
        .total-box span { color: #cf663a; font-size: 16px; }
        .btn-submit { background-color: #2c3e50; color: white; padding: 12px 15px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; font-size: 15px; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #1a252f; }
        .table-section { flex: 2.8; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .table-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #fbe0c9; padding-bottom: 10px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .table-header h3 { margin: 0; color: #222; font-size: 18px; }
        .search-form { display: flex; gap: 8px; align-items: center; }
        .search-form input { padding: 7px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; width: 200px; }
        .search-form input:focus { outline: none; border-color: #cf663a; }
        .search-form button { background: #2c3e50; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; }
        .search-form button:hover { background: #1a252f; }
        .btn-reset { background-color: #cbd5e1; color: #1e293b; padding: 7px 14px; border-radius: 6px; font-size: 13px; text-decoration: none; font-weight: 500; display: inline-block; }
        .btn-reset:hover { background-color: #94a3b8; }
        .btn-action-text { padding: 5px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; text-decoration: none; display: inline-block; margin-right: 3px; border: none; cursor: pointer; vertical-align: middle; }
        
        /* Tombol Invoice tetap Hijau */
        .btn-invoice { background-color: #10b981; color: white; }
        .btn-invoice:hover { background-color: #059669; }

        /* Tombol Detail disamakan dengan warna Simpan Data Transaksi (#2c3e50) */
        .btn-detail-text { background-color: #2c3e50; color: white; }
        .btn-detail-text:hover { background-color: #1a252f; }

        .btn-icon { width: 28px; height: 28px; padding: 0; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; margin-right: 3px; transition: opacity 0.2s, transform 0.1s; text-decoration: none; vertical-align: middle; }
        .btn-icon:hover { opacity: 0.85; transform: scale(1.05); }
        .btn-icon svg { width: 14px; height: 14px; fill: currentColor; }
        .btn-edit       { background-color: #0ea5e9; color: white; }
        .btn-hapus       { background-color: #ef4444; color: white; }
        .btn-batal-hapus { background-color: #f59e0b; color: white; }
        .badge-sukses { background-color: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-dihapus { background-color: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #f2f2f2; padding: 14px 10px; text-align: left; font-size: 13px;}
        th { background-color: #fff; color: #333; font-weight: 600; border-bottom: 2px solid #eee; }
        tr:hover { background-color: #fafafa; }
        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 14px;}
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 12px; width: 450px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .close-modal { color: #aaa; float: right; font-size: 24px; font-weight: bold; cursor: pointer; }
        .close-modal:hover { color: #000; }
        @media (max-width: 768px) { .container { flex-direction: column; } .modal-content { width: 90%; } }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">Kasir & Transaksi (Admin Panel)</div>
        <div class="nav-links">
            <a href="detail_penjualan_admin.php">📋 Lihat Laporan</a>
            <a href="index_admin.php">🏠 Beranda</a>
        </div>
    </header>

    <div class="container">
        
        <!-- Form Input Transaksi -->
        <div class="form-section">
            <h3>Input Transaksi Baru</h3>
            <?php echo $pesan; ?>
            <form action="" method="POST">
                <div class="input-group">
                    <label>Pilih Pelanggan</label>
                    <select name="id_pelanggan" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php
                        $query_pel = $koneksi->query("SELECT * FROM pelanggan ORDER BY nama_pelanggan ASC");
                        while($pel = $query_pel->fetch_assoc()) {
                            echo "<option value='".$pel['id_pelanggan']."'>".htmlspecialchars($pel['nama_pelanggan'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Daftar Produk / Jus</label>
                    <div id="produk-container">
                        <div class="produk-item-row">
                            <select name="id_produk[]" class="pilih-produk" onchange="hitungTotal()" required>
                                <option value="" data-harga="0">-- Pilih Produk --</option>
                                <?php
                                $query_p = $koneksi->query("SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk ASC");
                                while($p = $query_p->fetch_assoc()) {
                                    echo "<option value='".$p['id_produk']."' data-harga='".$p['harga']."'>".htmlspecialchars($p['nama_produk'])." (Stok: ".$p['stok']." | Rp ".number_format($p['harga'], 0, ',', '.').")</option>";
                                }
                                ?>
                            </select>
                            <input type="text" name="jumlah[]" class="input-qty" min="1" placeholder="Qty" oninput="hitungTotal()" required>
                        </div>
                    </div>
                    <button type="button" class="btn-tambah-row" onclick="tambahBaris()">+ Tambah Produk Lain</button>
                </div>

                <!-- Kotak Total Belanja Real-Time -->
                <div class="total-box">
                    Total Belanja: <span id="display-total">Rp 0</span>
                </div>

                <button type="submit" name="simpan_transaksi" class="btn-submit">Simpan Data Transaksi</button>
            </form>
        </div>

        <!-- Tabel Riwayat Penjualan -->
        <div class="table-section">
            <div class="table-header">
                <h3>Riwayat Penjualan</h3>
                <form action="" method="GET" class="search-form">
                    <?php $keyword = $_GET['cari'] ?? ''; ?>
                    <input type="text" name="cari" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Cari nama pelanggan...">
                    <button type="submit">Cari</button>
                    <?php if(!empty($keyword)): ?>
                        <a href="transaksi_admin.php" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>ID Transaksi</th>
                        <th>Tanggal & Waktu</th>
                        <th style="text-align: center;">Total Barang</th>
                        <th>Pelanggan</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_penjualan = "SELECT penjualan.*, pelanggan.nama_pelanggan, 
                                        COALESCE(SUM(detail_penjualan.jumlah_produk), 0) AS total_barang  
                                        FROM penjualan  
                                        LEFT JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan
                                        LEFT JOIN detail_penjualan ON penjualan.id_penjualan = detail_penjualan.id_penjualan";
                    
                    if($keyword != '') {
                        $sql_penjualan .= " WHERE pelanggan.nama_pelanggan LIKE ?";
                        $sql_penjualan .= " GROUP BY penjualan.id_penjualan ORDER BY penjualan.id_penjualan DESC";
                        
                        $stmt_tampil = $koneksi->prepare($sql_penjualan);
                        $like_keyword = "%" . $keyword . "%";
                        $stmt_tampil->bind_param("s", $like_keyword);
                        $stmt_tampil->execute();
                        $query_tampil = $stmt_tampil->get_result();
                    } else {
                        $sql_penjualan .= " GROUP BY penjualan.id_penjualan ORDER BY penjualan.id_penjualan DESC";
                        $query_tampil = $koneksi->query($sql_penjualan);
                    }

                    $no = 1;
                    
                    if($query_tampil->num_rows == 0){
                        echo "<tr><td colspan='8' style='text-align:center; padding: 20px; color: #888;'>Belum ada riwayat penjualan.</td></tr>";
                    }

                    while ($row = $query_tampil->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><?php echo $no; ?></td>
                        <td>#<?php echo $row['id_penjualan']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_penjualan'])); ?></td>
                        <td style="text-align: center; font-weight: 600;"><?php echo $row['total_barang']; ?></td>
                        <td>
                            <?php 
                            if (!empty($row['nama_pelanggan'])) {
                                echo "<strong>" . htmlspecialchars($row['nama_pelanggan']) . "</strong>";
                            } else {
                                echo "<span style='color: #888; font-size: 12px;'>Pelanggan tidak ditemukan</span>";
                            }
                            ?>
                        </td>
                        <td>Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                        <td>
                            <?php if ($row['status'] == 'dihapus'): ?>
                                <span class="badge-dihapus">sudah dihapus</span>
                            <?php else: ?>
                                <span class="badge-sukses">Sukses</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="#" onclick="window.open('invoice.php?id=<?php echo $row['id_penjualan']; ?>', 'StrukKasir', 'width=350,height=600,scrollbars=yes'); return false;" class="btn-action-text btn-invoice" title="Cetak Invoice">Invoice</a>
                            
                            <button type="button" class="btn-action-text btn-detail-text" onclick="bukaModal(<?php echo $row['id_penjualan']; ?>)" title="Lihat Detail">Detail</button>

                            <a href="edit_transaksi.php?id=<?php echo $row['id_penjualan']; ?>" class="btn-icon btn-edit" title="Edit Transaksi">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </a>

                            <?php if ($row['status'] == 'dihapus'): ?>
                                <a href="transaksi_admin.php?aksi=batal_hapus&id=<?php echo $row['id_penjualan']; ?>" class="btn-icon btn-batal-hapus" title="Pulihkan Transaksi">
                                    <svg viewBox="0 0 24 24"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
                                </a>
                            <?php else: ?>
                                <a href="transaksi_admin.php?aksi=hapus_transaksi&id=<?php echo $row['id_penjualan']; ?>" class="btn-icon btn-hapus" onclick="return confirm('Yakin ingin mengubah status transaksi ini menjadi sudah dihapus?')" title="Hapus Transaksi">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        $no++;
                    } 
                    ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal Detail Transaksi -->
    <div id="modalDetail" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="tutupModal()">&times;</span>
            <h3 style="margin-top:0; border-bottom: 2px solid #fbe0c9; padding-bottom: 8px;">Rincian Produk Dibeli</h3>
            <div id="isiModalDetail">
                <p style="text-align:center; color:#666;">Memuat data...</p>
            </div>
        </div>
    </div>

    <script>
        // Jalankan pop-up alert otomatis jika ada trigger dari PHP
        <?php echo $script_alert; ?>

        // Fungsi Menghitung Total Belanja Secara Otomatis
        function hitungTotal() {
            let totalKeseluruhan = 0;
            const rows = document.querySelectorAll('.produk-item-row');

            rows.forEach(row => {
                const selectProduk = row.querySelector('.pilih-produk');
                const inputQty = row.querySelector('.input-qty');

                const selectedOption = selectProduk.options[selectProduk.selectedIndex];
                const harga = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
                const qty = parseFloat(inputQty.value) || 0;

                totalKeseluruhan += harga * qty;
            });

            // Format ke Rupiah
            document.getElementById('display-total').innerText = 'Rp ' + totalKeseluruhan.toLocaleString('id-ID');
        }

        function tambahBaris() {
            const container = document.getElementById('produk-container');
            const barisPertama = container.querySelector('.produk-item-row');
            const barisBaru = barisPertama.cloneNode(true);
            
            barisBaru.querySelector('select').value = '';
            barisBaru.querySelector('input').value = '';
            
            if (!barisBaru.querySelector('.btn-hapus-row')) {
                const btnHapus = document.createElement('button');
                btnHapus.type = 'button';
                btnHapus.className = 'btn-hapus-row';
                btnHapus.innerHTML = '✕';
                btnHapus.onclick = function() {
                    barisBaru.remove();
                    hitungTotal(); 
                };
                barisBaru.appendChild(btnHapus);
            }
            
            container.appendChild(barisBaru);
        }

        function bukaModal(idPenjualan) {
            const modal = document.getElementById('modalDetail');
            const isiModal = document.getElementById('isiModalDetail');
            modal.style.display = 'block';
            isiModal.innerHTML = '<p style="text-align:center; color:#666;">Memuat data...</p>';

            fetch('<?php echo basename(__FILE__); ?>?ajax_detail=1&id=' + idPenjualan)
                .then(response => response.text())
                .then(data => {
                    isiModal.innerHTML = data;
                })
                .catch(error => {
                    isiModal.innerHTML = '<p style="text-align:center; color:red;">Gagal memuat rincian produk.</p>';
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