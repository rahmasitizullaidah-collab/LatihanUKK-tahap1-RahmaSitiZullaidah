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

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$pesan = "";
if (isset($_GET['status']) && $_GET['status'] == 'sukses') {
    $pesan = "<div class='alert-success'>Transaksi berhasil disimpan!</div>";
}

// Jika tombol "Proses Transaksi" ditekan
if (isset($_POST['simpan_transaksi'])) {
    $id_pelanggan      = mysqli_real_escape_string($koneksi, $_POST['id_pelanggan']);
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
            $id_p = mysqli_real_escape_string($koneksi, $arr_produk[$i]);
            $jml  = (int) $arr_jumlah[$i];

            if (empty($id_p) || $jml <= 0) continue;

            $query_p = mysqli_query($koneksi, "SELECT * FROM produk WHERE id_produk = '$id_p'");
            $data_produk = mysqli_fetch_assoc($query_p);

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
            mysqli_begin_transaction($koneksi);

            try {
                // 1. Simpan ke tabel penjualan utama
                $query_simpan = mysqli_query($koneksi, "INSERT INTO penjualan (id_pelanggan, tanggal_penjualan, total_harga) VALUES ('$id_pelanggan', '$tanggal_penjualan', '$total_seluruhnya')");

                if (!$query_simpan) {
                    throw new Exception("Gagal menyimpan data penjualan utama.");
                }

                $id_penjualan_baru = mysqli_insert_id($koneksi);

                // 2. Simpan rincian produk ke detail_penjualan dan kurangi stok
                foreach ($items_valid as $item) {
                    $id_p = $item['id_produk'];
                    $jml  = $item['jumlah'];
                    $sub  = $item['subtotal'];
                    $stok_baru = $item['stok_sekarang'] - $jml;

                    $q_detail = mysqli_query($koneksi, "INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah_produk, subtotal) VALUES ('$id_penjualan_baru', '$id_p', '$jml', '$sub')");
                    if (!$q_detail) throw new Exception("Gagal menyimpan detail penjualan.");

                    $q_update = mysqli_query($koneksi, "UPDATE produk SET stok = '$stok_baru' WHERE id_produk = '$id_p'");
                    if (!$q_update) throw new Exception("Gagal memperbarui stok produk.");
                }

                mysqli_commit($koneksi);
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=sukses");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($koneksi);
                $pesan = "<div class='alert-danger'>" . $e->getMessage() . "</div>";
            }
        }
    }
}

// Handler request AJAX untuk mengambil detail produk via modal di halaman yang sama
if (isset($_GET['ajax_detail']) && isset($_GET['id'])) {
    $id_penjualan = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    $q_penjualan = mysqli_query($koneksi, "SELECT p.*, pl.nama_pelanggan FROM penjualan p LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan WHERE p.id_penjualan = '$id_penjualan'");
    $data_jual = mysqli_fetch_assoc($q_penjualan);

    $q_detail = mysqli_query($koneksi, "SELECT dp.*, pr.nama_produk, pr.harga FROM detail_penjualan dp LEFT JOIN produk pr ON dp.id_produk = pr.id_produk WHERE dp.id_penjualan = '$id_penjualan'");

    if ($data_jual) {
        echo "<div style='margin-bottom: 12px; font-size: 13px; color: #444;'>";
        echo "<b>ID Transaksi:</b> #" . $data_jual['id_penjualan'] . "<br>";
        echo "<b>Pelanggan:</b> " . htmlspecialchars($data_jual['nama_pelanggan'] ?? 'Umum') . "<br>";
        echo "<b>Tanggal:</b> " . date('d/m/Y H:i', strtotime($data_jual['tanggal_penjualan'])) . "<br>";
        echo "</div>";

        echo "<table style='width:100%; border-collapse: collapse; font-size: 13px;'>";
        echo "<thead><tr style='background:#fcf4ec;'><th style='padding:8px; border-bottom:1px solid #ddd;'>Produk</th><th style='padding:8px; border-bottom:1px solid #ddd; text-align:center;'>Qty</th><th style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>Subtotal</th></tr></thead>";
        echo "<tbody>";
        
        while ($row = mysqli_fetch_assoc($q_detail)) {
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
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Kasir - Jus Segar</title>
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
            max-width: 1300px;
            margin: 30px auto;
            padding: 0 20px;
            display: flex;
            gap: 25px;
        }
        
        /* Form Section */
        .form-section {
            flex: 1.2;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            height: fit-content;
        }
        .form-section h3 { margin-top: 0; color: #222; border-bottom: 2px solid #fbe0c9; padding-bottom: 10px; margin-bottom: 20px;}
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px;}
        .input-group select, .input-group input {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; background: #fff;
        }
        .input-group select:focus, .input-group input:focus {
            outline: none; border-color: #cf663a;
        }

        .produk-item-row {
            display: flex; gap: 10px; margin-bottom: 10px; align-items: center;
            background: #faf8f5; padding: 10px; border-radius: 8px; border: 1px solid #eee;
        }
        .produk-item-row select { flex: 2; }
        .produk-item-row input { flex: 1; }
        
        .btn-hapus-row {
            background-color: #e74c3c; color: white; border: none; padding: 10px 12px;
            border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-hapus-row:hover { background-color: #c0392b; }

        .btn-tambah-row {
            background-color: #27ae60; color: white; border: none; padding: 8px 12px;
            border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; margin-bottom: 15px; display: inline-block;
        }

        /* Styling Total Belanja */
        .total-belanja-box {
            background-color: #fcf4ec;
            border: 1px solid #fbe0c9;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            font-size: 15px;
            color: #333;
        }
        .total-belanja-box span#grand-total {
            color: #cf663a;
            font-size: 18px;
        }

        .btn-submit {
            background-color: #cf663a; color: white; padding: 12px 15px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 15px; transition: background-color 0.3s;
        }
        .btn-submit:hover { background-color: #b3552d; }
        
        /* Table Section */
        .table-section {
            flex: 2.5;
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
        
        /* Tombol Aksi */
        .btn-detail {
            background-color: #3498db;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin-right: 4px;
            transition: background-color 0.3s;
        }
        .btn-detail:hover { background-color: #2980b9; }

        .btn-cetak-struk {
            background-color: #27ae60;
            color: white;
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn-cetak-struk:hover { background-color: #219653; }

        /* Badge Status */
        .badge-sukses {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eee; padding: 12px 10px; text-align: left; font-size: 13px;}
        th { background-color: #fcf4ec; color: #333; font-weight: 600; }
        tr:hover { background-color: #fafafa; }

        .alert-success { background-color: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}

        /* Modal Styling */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto; 
            padding: 20px;
            border-radius: 12px;
            width: 450px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover { color: #000; }

        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .modal-content { width: 90%; }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">🛒 Kasir & Transaksi Penjualan</div>
        <div class="nav-links">
            <a href="index.php">🏠 Beranda </a>
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
                        $query_pel = mysqli_query($koneksi, "SELECT * FROM pelanggan ORDER BY nama_pelanggan ASC");
                        while($pel = mysqli_fetch_assoc($query_pel)) {
                            echo "<option value='".$pel['id_pelanggan']."'>".htmlspecialchars($pel['nama_pelanggan'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Daftar Produk / Jus</label>
                    <div id="produk-container">
                        <div class="produk-item-row">
                            <select name="id_produk[]" class="select-produk" required>
                                <option value="" data-harga="0">-- Pilih Produk --</option>
                                <?php
                                $query_p = mysqli_query($koneksi, "SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk ASC");
                                while($p = mysqli_fetch_assoc($query_p)) {
                                    echo "<option value='".$p['id_produk']."' data-harga='".$p['harga']."'>".htmlspecialchars($p['nama_produk'])." (Stok: ".$p['stok']." | Rp ".number_format($p['harga'], 0, ',', '.').")</option>";
                                }
                                ?>
                            </select>
                            <input type="text" name="jumlah[]" class="input-jumlah" min="1" placeholder="Qty" required>
                        </div>
                    </div>
                    <button type="button" class="btn-tambah-row" onclick="tambahBaris()">+ Tambah Produk Lain</button>
                    
                    <!-- Kotak Total Belanja di bawah tombol tambah produk -->
                    <div class="total-belanja-box">
                        <span>Total Belanja:</span>
                        <span id="grand-total">Rp 0</span>
                    </div>
                </div>

                <button type="submit" name="simpan_transaksi" class="btn-submit">Proses & Simpan Transaksi</button>
            </form>
        </div>

        <!-- Tabel Riwayat -->
        <div class="table-section">
            <div class="table-header">
                <h3>Riwayat Penjualan</h3>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>ID</th>
                        <th>Tanggal/Waktu</th>
                        <th>Pelanggan</th>
                        <th>Total Barang</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_penjualan = "SELECT penjualan.*, pelanggan.nama_pelanggan, SUM(detail_penjualan.jumlah_produk) AS total_barang 
                                        FROM penjualan 
                                        JOIN pelanggan ON penjualan.id_pelanggan = pelanggan.id_pelanggan 
                                        LEFT JOIN detail_penjualan ON penjualan.id_penjualan = detail_penjualan.id_penjualan
                                        GROUP BY penjualan.id_penjualan 
                                        ORDER BY penjualan.id_penjualan DESC";
                    
                    $query_tampil = mysqli_query($koneksi, $sql_penjualan);
                    $no = 1;
                    
                    if(mysqli_num_rows($query_tampil) == 0){
                        echo "<tr><td colspan='8' style='text-align:center; padding: 20px; color: #888;'>Belum ada riwayat penjualan.</td></tr>";
                    }

                    while ($row = mysqli_fetch_assoc($query_tampil)) {
                    ?>
                    <tr>
                        <td><?php echo $no; ?></td>
                        <td>#<?php echo $row['id_penjualan']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_penjualan'])); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_pelanggan']); ?></td>
                        <td><?php echo $row['total_barang']; ?> Pcs</td> <!-- Tidak di-bold -->
                        <td>Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                        <td><span class="badge-sukses">Sukses</span></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-detail" onclick="bukaModal(<?php echo $row['id_penjualan']; ?>)">Detail</button>
                            <a href="#" onclick="window.open('invoice.php?id=<?php echo $row['id_penjualan']; ?>', 'StrukKasir', 'width=350,height=600,scrollbars=yes'); return false;" class="btn-cetak-struk">🖨 Invoice</a>
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
        // Fungsi untuk menghitung total belanja secara otomatis
        function hitungTotal() {
            let total = 0;
            const rows = document.querySelectorAll('.produk-item-row');
            
            rows.forEach(row => {
                const select = row.querySelector('.select-produk');
                const inputJumlah = row.querySelector('.input-jumlah');
                
                const selectedOption = select.options[select.selectedIndex];
                const harga = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
                const jumlah = parseInt(inputJumlah.value) || 0;
                
                total += harga * jumlah;
            });

            // Format ke Rupiah
            document.getElementById('grand-total').innerText = 'Rp ' + total.toLocaleString('id-ID');
        }

        // Event listener untuk memicu perhitungan saat pilihan produk atau jumlah qty berubah
        document.getElementById('produk-container').addEventListener('input', hitungTotal);
        document.getElementById('produk-container').addEventListener('change', hitungTotal);

        function tambahBaris() {
            const container = document.getElementById('produk-container');
            const barisPertama = container.querySelector('.produk-item-row');
            const barisBaru = barisPertama.cloneNode(true);
            
            barisBaru.querySelector('.select-produk').value = '';
            barisBaru.querySelector('.input-jumlah').value = '';
            
            if (!barisBaru.querySelector('.btn-hapus-row')) {
                const btnHapus = document.createElement('button');
                btnHapus.type = 'button';
                btnHapus.className = 'btn-hapus-row';
                btnHapus.innerHTML = '✕';
                btnHapus.onclick = function() {
                    barisBaru.remove();
                    hitungTotal(); // Hitung ulang total saat baris dihapus
                };
                barisBaru.appendChild(btnHapus);
            }
            
            container.appendChild(barisBaru);
            hitungTotal();
        }

        // Fungsi JavaScript untuk Modal Detail
        function bukaModal(idPenjualan) {
            const modal = document.getElementById('modalDetail');
            const isiModal = document.getElementById('isiModalDetail');
            modal.style.display = 'block';
            isiModal.innerHTML = '<p style="text-align:center; color:#666;">Memuat data...</p>';

            // Mengambil rincian data ke file ini sendiri via AJAX
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