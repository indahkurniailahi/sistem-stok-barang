<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// ============================================
// HAPUS TRANSAKSI
// ============================================
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    // Cek kepemilikan transaksi
    $stmt = $conn->prepare("SELECT user_id, kode_barang, jenis, jumlah_box, jumlah_pcs FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaksi = $result->fetch_assoc();
    $stmt->close();
    
    if ($transaksi) {
        $user_id = $transaksi['user_id'];
        $current_user = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        
        // Cek apakah boleh hapus (admin atau pemilik transaksi)
        if ($role == 'admin' || $user_id == $current_user) {
            $kode_barang = $transaksi['kode_barang'];
            $jenis = $transaksi['jenis'];
            $jumlah_box = $transaksi['jumlah_box'];
            $jumlah_pcs = $transaksi['jumlah_pcs'];
            
            // Ambil isi_per_box
            $barang = $conn->query("SELECT isi_per_box FROM barang WHERE kode = '$kode_barang'")->fetch_assoc();
            $isi_per_box = $barang['isi_per_box'] ?? 30;
            
            // Reverse stok
            if ($jenis == 'masuk') {
                // Jika hapus transaksi masuk, kurangi stok
                $stmt = $conn->prepare("
                    UPDATE stocks 
                    SET total_box = total_box - ?,
                        total_pcs = total_pcs - ?
                    WHERE kode_barang = ?
                ");
                $stmt->bind_param("iis", $jumlah_box, $jumlah_pcs, $kode_barang);
            } else {
                // Jika hapus transaksi keluar, tambah stok
                $stmt = $conn->prepare("
                    UPDATE stocks 
                    SET total_box = total_box + ?,
                        total_pcs = total_pcs + ?
                    WHERE kode_barang = ?
                ");
                $stmt->bind_param("iis", $jumlah_box, $jumlah_pcs, $kode_barang);
            }
            $stmt->execute();
            $stmt->close();
            
            // Hapus transaksi
            $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            setFlash('Transaksi berhasil dihapus!', 'success');
        } else {
            setFlash('Anda tidak berhak menghapus transaksi ini!', 'error');
        }
    }
    
    redirect('?page=transaksi' . (isset($_GET['kode']) ? '&kode=' . $_GET['kode'] : ''));
}

// ============================================
// TAMBAH TRANSAKSI
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $kode_barang = $_POST['kode_barang'];
    $tanggal = $_POST['tanggal'];
    $jenis = $_POST['jenis'];
    $jumlah_box = intval($_POST['jumlah_box']);
    $jumlah_pcs = intval($_POST['jumlah_pcs']);
    $catatan = $_POST['catatan'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if ($jumlah_box == 0 && $jumlah_pcs == 0) {
        setFlash('Jumlah tidak boleh 0!', 'error');
    } else {
        // Ambil isi_per_box
        $barang = $conn->query("SELECT isi_per_box FROM barang WHERE kode = '$kode_barang'")->fetch_assoc();
        $isi_per_box = $barang['isi_per_box'] ?? 30;
        
        // Cek stok jika keluar
        if ($jenis == 'keluar') {
            $stok = $conn->query("SELECT total_box, total_pcs FROM stocks WHERE kode_barang = '$kode_barang'")->fetch_assoc();
            $stok_pcs = toPcs($stok['total_box'], $stok['total_pcs'], $isi_per_box);
            $keluar_pcs = toPcs($jumlah_box, $jumlah_pcs, $isi_per_box);
            
            if ($keluar_pcs > $stok_pcs) {
                setFlash('Stok tidak mencukupi!', 'error');
                redirect('?page=transaksi&kode=' . $kode_barang);
            }
        }
        
        // Insert transaksi
        $stmt = $conn->prepare("
            INSERT INTO transactions (user_id, kode_barang, tanggal, jenis, jumlah_box, jumlah_pcs, catatan) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssiis", $user_id, $kode_barang, $tanggal, $jenis, $jumlah_box, $jumlah_pcs, $catatan);
        $stmt->execute();
        $stmt->close();
        
        // Update stok
        if ($jenis == 'masuk') {
            $conn->query("
                INSERT INTO stocks (kode_barang, total_box, total_pcs) 
                VALUES ('$kode_barang', $jumlah_box, $jumlah_pcs)
                ON DUPLICATE KEY UPDATE 
                total_box = total_box + $jumlah_box,
                total_pcs = total_pcs + $jumlah_pcs
            ");
        } else {
            $conn->query("
                UPDATE stocks 
                SET total_box = total_box - $jumlah_box,
                    total_pcs = total_pcs - $jumlah_pcs
                WHERE kode_barang = '$kode_barang'
            ");
        }
        
        // Normalisasi stok
        $stok = $conn->query("SELECT total_box, total_pcs FROM stocks WHERE kode_barang = '$kode_barang'")->fetch_assoc();
        $total_pcs = toPcs($stok['total_box'], $stok['total_pcs'], $isi_per_box);
        $normal = toBoxPcs($total_pcs, $isi_per_box);
        $conn->query("UPDATE stocks SET total_box = {$normal['box']}, total_pcs = {$normal['pcs']} WHERE kode_barang = '$kode_barang'");
        
        setFlash('Transaksi berhasil disimpan!', 'success');
        redirect('?page=transaksi&kode=' . $kode_barang);
    }
}

// ============================================
// PENCARIAN BARANG
// ============================================
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_results = $conn->query("
        SELECT kode, nama, isi_per_box 
        FROM barang 
        WHERE kode LIKE '%$search%' OR nama LIKE '%$search%'
        ORDER BY 
            CASE 
                WHEN kode = '$search' THEN 0 
                WHEN kode LIKE '$search%' THEN 1 
                ELSE 2 
            END,
            nama
        LIMIT 10
    ");
}

// ============================================
// AMBIL DATA
// ============================================

// Kode barang yang dipilih (dari URL atau dari pencarian)
$selected_kode = $_GET['kode'] ?? '';

// Data barang yang dipilih
$selected_barang = null;
$stok = ['total_box' => 0, 'total_pcs' => 0];
$transaksi_list = [];

if ($selected_kode) {
    $selected_barang = $conn->query("SELECT * FROM barang WHERE kode = '$selected_kode'")->fetch_assoc();
    if ($selected_barang) {
        $stok = $conn->query("SELECT total_box, total_pcs FROM stocks WHERE kode_barang = '$selected_kode'")->fetch_assoc();
        if (!$stok) {
            $stok = ['total_box' => 0, 'total_pcs' => 0];
        }
        
        $transaksi_list = $conn->query("
            SELECT t.*, u.nama_lengkap, u.id as user_id 
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.kode_barang = '$selected_kode'
            ORDER BY t.tanggal DESC, t.created_at DESC
        ");
    } else {
        $selected_kode = '';
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-exchange-alt"></i>
        <h2>Transaksi Barang</h2>
    </div>
    
    <!-- Info Box -->
    <div style="background: #e0f2fe; border-left: 4px solid #0284c7; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <i class="fas fa-info-circle" style="color: #0284c7; font-size: 1.5rem;"></i>
            <div>
                <strong style="color: #0369a1;">Sistem Konversi Otomatis</strong>
                <p style="margin: 0; color: #075985; font-size: 0.9rem;">
                    1 Box = 30 PCS. Stok akan otomatis dikonversi (contoh: 32 PCS → 1 Box + 2 PCS)
                </p>
            </div>
        </div>
    </div>
    
    <!-- Pencarian Barang -->
    <div style="margin-bottom: 2rem;">
        <form method="GET" action="" id="searchForm">
            <input type="hidden" name="page" value="transaksi">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Cari Barang (Ketik kode atau nama):</label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       style="max-width: 400px;" 
                       placeholder="Contoh: BRG001 atau Pulpen"
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                       autocomplete="off"
                       id="searchInput">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Cari
                </button>
                <?php if ($selected_kode): ?>
                <a href="?page=transaksi" class="btn" style="background: #64748b; color: white;">
                    <i class="fas fa-times"></i> Reset
                </a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Hasil Pencarian -->
        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
            <div style="margin-top: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 400px;">
                <?php if ($search_results && $search_results->num_rows > 0): ?>
                    <?php while($b = $search_results->fetch_assoc()): ?>
                    <a href="?page=transaksi&kode=<?php echo $b['kode']; ?>" 
                       style="display: block; padding: 0.75rem 1rem; text-decoration: none; color: #1e293b; border-bottom: 1px solid #e2e8f0; hover:background: #f8fafc;">
                        <strong><?php echo $b['kode']; ?></strong> - <?php echo $b['nama']; ?>
                        <small style="color: #64748b; display: block;"><?php echo $b['isi_per_box']; ?> pcs/box</small>
                    </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="padding: 1rem; color: #64748b; text-align: center;">
                        <i class="fas fa-exclamation-circle"></i> Barang tidak ditemukan
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($selected_barang): ?>
    <!-- Info Stok -->
    <div style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 1.5rem; border-radius: 15px; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($selected_barang['nama']); ?></h3>
                <p style="margin: 0; opacity: 0.9;">Kode: <?php echo $selected_barang['kode']; ?></p>
                <p style="margin: 0.25rem 0 0 0; opacity: 0.9;">1 Box = <?php echo $selected_barang['isi_per_box']; ?> PCS</p>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold;"><?php echo $stok['total_box']; ?>/<?php echo $stok['total_pcs']; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Stok Saat Ini</div>
                <div style="font-size: 0.8rem; opacity: 0.7;"><?php echo toPcs($stok['total_box'], $stok['total_pcs'], $selected_barang['isi_per_box']); ?> pcs</div>
            </div>
        </div>
    </div>
    
    <!-- Form Transaksi -->
    <div style="background: #f8fafc; padding: 1.5rem; border-radius: 15px; margin-bottom: 2rem;">
        <h3 style="margin: 0 0 1rem 0;">Tambah Transaksi</h3>
        
        <form method="POST" action="">
            <input type="hidden" name="kode_barang" value="<?php echo $selected_kode; ?>">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div>
                    <label>Jenis Transaksi</label>
                    <select name="jenis" class="form-control" required>
                        <option value="masuk">Barang Masuk</option>
                        <option value="keluar">Barang Keluar</option>
                    </select>
                </div>
                
                <div>
                    <label>Jumlah Box</label>
                    <input type="number" name="jumlah_box" class="form-control" min="0" value="0" required>
                </div>
                
                <div>
                    <label>Jumlah PCS</label>
                    <input type="number" name="jumlah_pcs" class="form-control" min="0" value="0" required>
                </div>
                
                <div style="grid-column: span 2;">
                    <label>Catatan (opsional)</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Contoh: dari supplier A, untuk proyek B, dll">
                </div>
            </div>
            
            <button type="submit" name="simpan" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Transaksi
            </button>
        </form>
    </div>
    
    <!-- Riwayat Transaksi -->
    <h3 style="margin: 0 0 1rem 0;">Riwayat Transaksi</h3>
    
    <div class="table-container">
        <?php if ($transaksi_list && $transaksi_list->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>User</th>
                        <th>Jenis</th>
                        <th>Jumlah</th>
                        <th>Total PCS</th>
                        <th>Catatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = $transaksi_list->fetch_assoc()): 
                        $total_pcs = toPcs($t['jumlah_box'], $t['jumlah_pcs'], $selected_barang['isi_per_box']);
                        $boleh_hapus = ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $t['user_id']);
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($t['tanggal'])); ?></td>
                        <td><?php echo htmlspecialchars($t['nama_lengkap'] ?? 'System'); ?></td>
                        <td>
    <?php if($t['jenis'] == 'masuk'): ?>
        <span style="color: #10b981; font-weight: 600; font-size: 0.7rem;">
            <i class="fas fa-arrow-down" style="font-size: 0.6rem;"></i> MASUK
        </span>
    <?php else: ?>
        <span style="color: #ef4444; font-weight: 600; font-size: 0.7rem;">
            <i class="fas fa-arrow-up" style="font-size: 0.6rem;"></i> KELUAR
        </span>
    <?php endif; ?>
</td>
                        <td><?php echo $t['jumlah_box']; ?>/<?php echo $t['jumlah_pcs']; ?></td>
                        <td><?php echo number_format($total_pcs); ?> pcs</td>
                        <td><?php echo htmlspecialchars($t['catatan']); ?></td>
                        <td>
                            <?php if ($boleh_hapus): ?>
                            <a href="?page=transaksi&hapus=<?php echo $t['id']; ?>&kode=<?php echo $selected_kode; ?>" 
                               class="btn btn-danger btn-sm" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                               onclick="return confirm('Hapus transaksi ini?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php else: ?>
                                <span style="color: #94a3b8;" title="Tidak berhak menghapus">
                                    <i class="fas fa-ban"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h4>Belum Ada Transaksi</h4>
                <p>Belum ada transaksi untuk barang ini.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php elseif (!isset($_GET['search'])): ?>
    <!-- Tampilkan pesan jika belum pilih barang -->
    <div style="text-align: center; padding: 3rem; color: #6b7280; background: #f8fafc; border-radius: 15px;">
        <i class="fas fa-search" style="font-size: 4rem; margin-bottom: 1rem; color: #94a3b8;"></i>
        <h3>Cari Barang Terlebih Dahulu</h3>
        <p>Ketik kode atau nama barang di kolom pencarian di atas</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto submit search setelah berhenti mengetik (opsional)
let searchTimeout;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    if (query.length >= 2) {
        searchTimeout = setTimeout(() => {
            document.getElementById('searchForm').submit();
        }, 500);
    }
});
</script>