<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// ============================================
// FILTER DAN PENCARIAN
// ============================================
$search = $_GET['search'] ?? '';
$filter_jenis = $_GET['jenis'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? '';

// ============================================
// AMBIL SEMUA TRANSAKSI (URUT BERDASARKAN WAKTU)
// ============================================
$query = "
    SELECT t.*, 
           u.nama_lengkap, 
           b.nama as nama_barang,
           b.isi_per_box
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN barang b ON t.kode_barang = b.kode
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (b.nama LIKE ? OR b.kode LIKE ? OR t.catatan LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($filter_jenis)) {
    $query .= " AND t.jenis = ?";
    $params[] = $filter_jenis;
    $types .= "s";
}

if (!empty($filter_tanggal)) {
    $query .= " AND DATE(t.tanggal) = ?";
    $params[] = $filter_tanggal;
    $types .= "s";
}

// URUTKAN BERDASARKAN TANGGAL ASC (DARI LAMA KE BARU) UNTUK MENGHITUNG STOK BERJALAN
$query .= " ORDER BY t.kode_barang ASC, t.tanggal ASC, t.created_at ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$all_transactions = $result->fetch_all(MYSQLI_ASSOC);

// ============================================
// HITUNG STOK BERJALAN (RUNNING BALANCE)
// ============================================
$running_stok = []; // Menyimpan stok sementara per barang
$transactions_with_balance = [];

foreach ($all_transactions as $t) {
    $kode = $t['kode_barang'];
    $isi_per_box = $t['isi_per_box'] ?? 30;
    
    // Inisialisasi stok awal jika belum ada
    if (!isset($running_stok[$kode])) {
        // Ambil stok awal sebelum transaksi pertama
        $stok_awal = $conn->query("
            SELECT 
                COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah_box ELSE -jumlah_box END), 0) as total_box,
                COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah_pcs ELSE -jumlah_pcs END), 0) as total_pcs
            FROM transactions 
            WHERE kode_barang = '$kode' 
            AND (tanggal < '{$t['tanggal']}' OR (tanggal = '{$t['tanggal']}' AND created_at < '{$t['created_at']}'))
        ")->fetch_assoc();
        
        $total_pcs_awal = ($stok_awal['total_box'] * $isi_per_box) + $stok_awal['total_pcs'];
        $running_stok[$kode] = $total_pcs_awal;
    }
    
    // Hitung perubahan stok dari transaksi ini
    $perubahan_pcs = ($t['jumlah_box'] * $isi_per_box) + $t['jumlah_pcs'];
    if ($t['jenis'] == 'masuk') {
        $running_stok[$kode] += $perubahan_pcs;
    } else {
        $running_stok[$kode] -= $perubahan_pcs;
    }
    
    // Simpan transaksi dengan stok setelah transaksi
    $t['stok_setelah_pcs'] = $running_stok[$kode];
    
    // Konversi ke box/pcs untuk tampilan
    $box_setelah = floor($running_stok[$kode] / $isi_per_box);
    $pcs_setelah = $running_stok[$kode] % $isi_per_box;
    $t['stok_setelah_box'] = $box_setelah;
    $t['stok_setelah_pcs'] = $pcs_setelah;
    
    $transactions_with_balance[] = $t;
}

// Balik urutan untuk ditampilkan (dari terbaru ke terlama)
$transactions_with_balance = array_reverse($transactions_with_balance);

// Hitung total untuk ringkasan
$total_transaksi = count($transactions_with_balance);
$total_masuk = 0;
$total_keluar = 0;

foreach ($transactions_with_balance as $t) {
    if ($t['jenis'] == 'masuk') {
        $total_masuk++;
    } else {
        $total_keluar++;
    }
}

$conn->close();
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-history"></i>
        <h2>Semua Transaksi</h2>
        <a href="?page=dashboard" class="btn" style="margin-left: auto; background: #64748b; color: white; padding: 0.3rem 0.6rem;">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
    
    <!-- Ringkasan -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 1.5rem;">
        <div style="background: #f8fafc; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="font-size: 0.6rem; color: #64748b;">Total Transaksi</div>
            <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $total_transaksi; ?></div>
        </div>
        <div style="background: #dbeafe; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="font-size: 0.6rem; color: #1e40af;">Transaksi Masuk</div>
            <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $total_masuk; ?></div>
        </div>
        <div style="background: #fee2e2; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="font-size: 0.6rem; color: #991b1b;">Transaksi Keluar</div>
            <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $total_keluar; ?></div>
        </div>
    </div>
    
    <!-- Filter dan Pencarian -->
    <div style="background: #f8fafc; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="transaksi_list">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.5rem;">
                <div>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari barang, kode, atau catatan..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="font-size: 0.7rem; padding: 0.4rem;">
                </div>
                
                <div>
                    <select name="jenis" class="form-control" style="font-size: 0.7rem; padding: 0.4rem;">
                        <option value="">Semua Jenis</option>
                        <option value="masuk" <?php echo $filter_jenis == 'masuk' ? 'selected' : ''; ?>>Masuk</option>
                        <option value="keluar" <?php echo $filter_jenis == 'keluar' ? 'selected' : ''; ?>>Keluar</option>
                    </select>
                </div>
                
                <div>
                    <input type="date" name="tanggal" class="form-control" 
                           value="<?php echo $filter_tanggal; ?>"
                           style="font-size: 0.7rem; padding: 0.4rem;">
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
            
            <?php if ($search || $filter_jenis || $filter_tanggal): ?>
            <div style="margin-top: 0.5rem;">
                <a href="?page=transaksi_list" class="btn btn-sm" style="background: #64748b; color: white; padding: 0.2rem 0.5rem;">
                    <i class="fas fa-times"></i> Reset Filter
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Daftar Transaksi dengan Stok Berjalan -->
    <div class="table-container">
    <?php if (empty($transactions_with_balance)): ?>
        <div style="text-align: center; padding: 2rem; color: #64748b;">
            <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
            <p style="font-size: 0.9rem;">Belum ada transaksi</p>
            <a href="?page=transaksi" class="btn btn-primary" style="margin-top: 0.5rem;">
                <i class="fas fa-plus-circle"></i> Transaksi Baru
            </a>
        </div>
    <?php else: ?>
        <table style="font-size: 0.7rem;">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Barang</th>
                    <th>User</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Stok Setelah</th>
                    <th>Total (PCS)</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($transactions_with_balance as $t): 
                    $total_pcs_transaksi = ($t['jumlah_box'] * ($t['isi_per_box'] ?? 30)) + $t['jumlah_pcs'];
                    $total_pcs_setelah = ($t['stok_setelah_box'] * ($t['isi_per_box'] ?? 30)) + $t['stok_setelah_pcs'];
                    $isi_per_box = $t['isi_per_box'] ?? 30;
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($t['tanggal'])); ?></td>
                    <td><?php echo date('H:i', strtotime($t['created_at'])); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($t['nama_barang'] ?? '-'); ?></strong>
                        <br>
                        <small style="color: #6b7280;"><?php echo htmlspecialchars($t['kode_barang']); ?></small>
                        <br>
                        <small style="color: #94a3b8;">(1 box = <?php echo $isi_per_box; ?> pcs)</small>
                    </td>
                    <td><?php echo htmlspecialchars($t['nama_lengkap'] ?? 'System'); ?></td>
                    <td>
                        <?php if ($t['jenis'] == 'masuk'): ?>
                            <span style="color: #10b981; font-weight: 600;">
                                <i class="fas fa-arrow-down"></i> MASUK
                            </span>
                        <?php else: ?>
                            <span style="color: #ef4444; font-weight: 600;">
                                <i class="fas fa-arrow-up"></i> KELUAR
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo $t['jumlah_box']; ?>/<?php echo $t['jumlah_pcs']; ?></strong>
                        <br>
                        <small style="color: #64748b;"><?php echo number_format($total_pcs_transaksi); ?> pcs</small>
                    </td>
                    <td>
                        <strong><?php echo $t['stok_setelah_box']; ?>/<?php echo $t['stok_setelah_pcs']; ?></strong>
                    </td>
                    <td>
                        <!-- TOTAL PCS AKHIR (dari stok setelah) -->
                        <span style="padding: 2px 8px; border-radius: 12px; font-weight: 600;">
                            <?php echo number_format($total_pcs_setelah); ?>
                        </span>
                        
                        
                    </td>
                    <td><?php echo htmlspecialchars($t['catatan']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Info tambahan -->
        <div style="margin-top: 1rem; font-size: 0.65rem; color: #64748b; display: flex; justify-content: space-between; align-items: center;">
            <span>Menampilkan <?php echo count($transactions_with_balance); ?> transaksi</span>
            
        </div>
    <?php endif; ?>
</div>