<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Cek login admin
if (!isset($_SESSION['login_admin']) || $_SESSION['login_admin'] !== true) {
    header("Location: login_admin.php");
    exit;
}

$host = "localhost";
$user = "root";        
$pass = "";            
$db   = "db_kasir"; 

$koneksi = mysqli_connect($host,$user, $pass,$db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Ambil ID Transaksi dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: transaksi_admin.php");
    exit;
}

$id_penjualan = mysqli_real_escape_string($koneksi,$_GET['id']);

// Ambil data transaksi utama beserta nama dan alamat pelanggan
$q_transaksi = mysqli_query($koneksi, "SELECT penjualan.*, pelanggan.nama_pelanggan, pelanggan.alamat FROM penjualan JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan WHERE penjualan.id_penjualan = '$id_penjualan'");
$data_transaksi = mysqli_fetch_assoc($q_transaksi);

if (!$data_transaksi) {
    echo "Data transaksi tidak ditemukan!";
    exit;
}

// PROSES SIMPAN PERUBAHAN
if (isset($_POST['update_transaksi'])) {
    
    // 2. PROSES ITEM LAMA (Update jumlah atau Hapus jika ditandai)
    $item_dihapus =$_POST['hapus_detail'] ?? []; 

    $q_old_details = mysqli_query($koneksi, "SELECT * FROM detail_penjualan WHERE id_penjualan = '$id_penjualan'");
    while ($old_d = mysqli_fetch_assoc($q_old_details)) {
        $id_detail =$old_d['id_detail'];
        $id_prod =$old_d['id_produk'];
        $jumlah_lama =$old_d['jumlah_produk'];

        // Jika item ini dihapus
        if (in_array($id_detail,$item_dihapus)) {
            mysqli_query($koneksi, "UPDATE produk SET stok = stok + $jumlah_lama WHERE id_produk = '$id_prod'");
            mysqli_query($koneksi, "DELETE FROM detail_penjualan WHERE id_detail = '$id_detail'");
        } 
        // Jika di-update jumlahnya
        elseif (isset($_POST['jumlah'][$id_detail])) {$jumlah_baru = intval($_POST['jumlah'][$id_detail]);

            if ($jumlah_baru > 0) {$selisih = $jumlah_baru -$jumlah_lama;

                if ($selisih != 0) {
                    mysqli_query($koneksi, "UPDATE produk SET stok = stok - $selisih WHERE id_produk = '$id_prod'");
                }

                $q_harga = mysqli_query($koneksi, "SELECT harga FROM produk WHERE id_produk = '$id_prod'");
                $d_h = mysqli_fetch_assoc($q_harga);$subtotal_baru = $jumlah_baru * $d_h['harga'];

                mysqli_query($koneksi, "UPDATE detail_penjualan SET jumlah_produk = '$jumlah_baru', subtotal = '$subtotal_baru' WHERE id_detail = '$id_detail'");
            }
        }
    }

    // 3. PROSES TAMBAH PRODUK BARU
    $arr_produk_baru =$_POST['id_produk_baru'] ?? [];
    $arr_jumlah_baru =$_POST['jumlah_baru'] ?? [];

    for ($i = 0; $i < count($arr_produk_baru); $i++) {$id_p_baru = mysqli_real_escape_string($koneksi,$arr_produk_baru[$i]);$jml_b     = intval($arr_jumlah_baru[$i]);

        if (!empty($id_p_baru) &&$jml_b > 0) {
            $q_p_info = mysqli_query($koneksi, "SELECT harga, stok FROM produk WHERE id_produk = '$id_p_baru'");
            $d_p_info = mysqli_fetch_assoc($q_p_info);

            if ($d_p_info) {$harga_p = $d_p_info['harga'];$subtotal_p = $harga_p * $jml_b;

                mysqli_query($koneksi, "INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah_produk, subtotal) VALUES ('$id_penjualan', '$id_p_baru', '$jml_b', '$subtotal_p')");
                mysqli_query($koneksi, "UPDATE produk SET stok = stok - $jml_b WHERE id_produk = '$id_p_baru'");
            }
        }
    }

    // 4. Hitung ulang total harga keseluruhan transaksi
    $q_sum = mysqli_query($koneksi, "SELECT SUM(subtotal) as total_keseluruhan FROM detail_penjualan WHERE id_penjualan = '$id_penjualan'");
    $d_sum = mysqli_fetch_assoc($q_sum);
    $total_baru_transaksi =$d_sum['total_keseluruhan'] ?? 0;

    mysqli_query($koneksi, "UPDATE penjualan SET total_harga = '$total_baru_transaksi' WHERE id_penjualan = '$id_penjualan'");

    header("Location: transaksi_admin.php?status_notif=diedit");
    exit;
}

$q_detail_transaksi = mysqli_query($koneksi, "SELECT detail_penjualan.*, produk.nama_produk FROM detail_penjualan JOIN produk ON detail_penjualan.id_produk = produk.id_produk WHERE detail_penjualan.id_penjualan = '$id_penjualan'");
$q_produk_opsi = mysqli_query($koneksi, "SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaksi #<?php echo $id_penjualan; ?> - Admin</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #fcf4ec; margin: 0; padding: 0; color: #333; }
        header { background-color: #fbe0c9; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #2c3e50; }
        .logo-title { font-size: 20px; font-weight: bold; }
        .nav-links a { text-decoration: none; color: #333; font-weight: 500; margin-left: 20px; }
        .container { max-width: 850px; margin: 30px auto; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .info-teks { background: #faf8f5; padding: 10px 15px; border: 1px solid #eee; border-radius: 6px; font-size: 14px; color: #555; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; font-size: 14px; vertical-align: middle; }
        th { background-color: #fcf4ec; }
        .btn-hapus-row { background-color: #e74c3c; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .btn-hapus-row:hover { background-color: #c0392b; }
        .btn-tambah-row { background-color: #27ae60; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; margin-top: 10px; display: inline-block; }
        .btn-tambah-row:hover { background-color: #219653; }
        .produk-baru-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #faf8f5; padding: 10px; border-radius: 6px; border: 1px solid #eee; }
        .produk-baru-row select { flex: 2; }
        .produk-baru-row input { flex: 1; }
        .btn-submit { background-color: #2c3e50; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; width: 100%; margin-top: 20px; }
        .btn-submit:hover { background-color: #1a252f; }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">✏️ Edit Transaksi #<?php echo $id_penjualan; ?></div>
        <div class="nav-links">
            <a href="transaksi_admin.php">⬅ Kembali ke Transaksi</a>
        </div>
    </header>

    <div class="container">
        <form action="" method="POST">
            <!-- Nama Pelanggan (Teks Biasa) -->
            <div class="form-group">
                <label>Nama Pelanggan</label>
                <div class="info-teks"><strong><?php echo htmlspecialchars($data_transaksi['nama_pelanggan']); ?></strong></div>
            </div>

            <!-- Alamat Pelanggan -->
            <div class="form-group">
                <label>Alamat Pelanggan</label>
                <div class="info-teks"><?php echo !empty($data_transaksi['alamat']) ? htmlspecialchars($data_transaksi['alamat']) : '<span style="color:#888; font-style:italic;">Tidak ada alamat</span>'; ?></div>
            </div>

            <div class="form-group">
                <label>Daftar Produk yang Dibeli:</label>
                <table>
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th width="120px">Jumlah (Qty)</th>
                            <th>Subtotal</th>
                            <th width="80px" style="text-align: center;">Batal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ada_item = false;
                        while($dtl = mysqli_fetch_assoc($q_detail_transaksi)):$ada_item = true;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dtl['nama_produk']); ?></td>
                            <td>
                                <input type="text" name="jumlah[<?php echo $dtl['id_detail']; ?>]" value="<?php echo $dtl['jumlah_produk']; ?>" min="1" required>
                            </td>
                            <td>Rp <?php echo number_format($dtl['subtotal'], 0, ',', '.'); ?></td>
                            <td style="text-align: center;">
                                <input type="checkbox" name="hapus_detail[]" value="<?php echo $dtl['id_detail']; ?>" id="del_<?php echo $dtl['id_detail']; ?>" style="display:none;">
                                <button type="button" class="btn-hapus-row" onclick="hapusBarisLama(this, <?php echo $dtl['id_detail']; ?>)">✕</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(!$ada_item): ?>
                            <tr><td colspan="4" style="text-align:center; color:#888;">Semua produk pada transaksi ini dihapus. Silakan tambah produk baru di bawah.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-group">
                <label>Tambah Jenis Jus Lain (Opsional):</label>
                <div id="container-produk-baru"></div>
                <button type="button" class="btn-tambah-row" onclick="tambahBarisBaru()">+ Tambah Jus Lain</button>
            </div>

            <button type="submit" name="update_transaksi" class="btn-submit">Simpan Perubahan Transaksi</button>
        </form>
    </div>

    <script>
        function hapusBarisLama(button, idDetail) {
            if (confirm('Yakin ingin membatalkan/menghapus produk ini dari transaksi?')) {
                const checkbox = document.getElementById('del_' + idDetail);
                checkbox.checked = true;
                const row = button.closest('tr');
                row.style.display = 'none';
            }
        }

        function tambahBarisBaru() {
            const container = document.getElementById('container-produk-baru');
            const divRow = document.createElement('div');
            divRow.className = 'produk-baru-row';
            
            divRow.innerHTML = `
                <select name="id_produk_baru[]" required>
                    <option value="">-- Pilih Jus Lain --</option>
                    <?php 
                    mysqli_data_seek($q_produk_opsi, 0);
                    while($prod = mysqli_fetch_assoc($q_produk_opsi)) {
                        echo "<option value='".$prod['id_produk']."'>".htmlspecialchars($prod['nama_produk'])." (Stok: ".$prod['stok']." | Rp ".number_format($prod['harga'], 0, ',', '.').")</option>";
                    }
                    ?>
                </select>
                <input type="text" name="jumlah_baru[]" min="1" placeholder="Qty" required>
                <button type="button" class="btn-hapus-row" onclick="this.parentElement.remove()">✕</button>
            `;
            
            container.appendChild(divRow);
        }
    </script>
</body>
</html>