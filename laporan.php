<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// Fungsi bantu sudah ada di config.php

// ============================================
// FILTER
// ============================================
$kode_barang = $_GET['kode_barang'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';

// ============================================
// PENCARIAN BARANG (untuk dropdown manual)
// ============================================
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $search_results = $conn->query("
        SELECT kode, nama 
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

// Ambil daftar user untuk filter (admin only)
$user_list = [];
if (isAdmin()) {
    $user_list = $conn->query("SELECT id, nama_lengkap FROM users ORDER BY nama_lengkap");
}

// ============================================
// AMBIL DATA LAPORAN
// ============================================
$laporan = [];
$ringkasan = [
    'awal_box' => 0, 'awal_pcs' => 0,
    'masuk_box' => 0, 'masuk_pcs' => 0,
    'keluar_box' => 0, 'keluar_pcs' => 0,
    'akhir_box' => 0, 'akhir_pcs' => 0
];
$isi_per_box = 30;
$nama_barang = '';

if ($kode_barang) {
    // Ambil info barang
    $barang = $conn->query("SELECT nama, isi_per_box FROM barang WHERE kode = '$kode_barang'")->fetch_assoc();
    
    if ($barang) {
        $nama_barang = $barang['nama'] ?? '';
        $isi_per_box = $barang['isi_per_box'] ?? 30;
        
        // Hitung stok awal (sebelum start_date)
        $stok_awal = $conn->query("
            SELECT 
                COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah_box ELSE -jumlah_box END), 0) as total_box,
                COALESCE(SUM(CASE WHEN jenis = 'masuk' THEN jumlah_pcs ELSE -jumlah_pcs END), 0) as total_pcs
            FROM transactions 
            WHERE kode_barang = '$kode_barang' 
            AND tanggal < '$start_date'
        ")->fetch_assoc();
        
        $total_pcs_awal = toPcs($stok_awal['total_box'], $stok_awal['total_pcs'], $isi_per_box);
        $normal_awal = toBoxPcs($total_pcs_awal, $isi_per_box);
        $ringkasan['awal_box'] = $normal_awal['box'];
        $ringkasan['awal_pcs'] = $normal_awal['pcs'];
        
        // Query transaksi periode
        $query = "
            SELECT t.*, u.nama_lengkap 
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.kode_barang = '$kode_barang'
            AND t.tanggal BETWEEN '$start_date' AND '$end_date'
        ";
        
        if ($user_id) {
            $query .= " AND t.user_id = $user_id";
        }
        
        $query .= " ORDER BY t.tanggal ASC, t.created_at ASC";
        $laporan = $conn->query($query);
        
        // Hitung total masuk/keluar periode
        $total = $conn->query("
            SELECT 
                SUM(CASE WHEN jenis = 'masuk' THEN jumlah_box ELSE 0 END) as masuk_box,
                SUM(CASE WHEN jenis = 'masuk' THEN jumlah_pcs ELSE 0 END) as masuk_pcs,
                SUM(CASE WHEN jenis = 'keluar' THEN jumlah_box ELSE 0 END) as keluar_box,
                SUM(CASE WHEN jenis = 'keluar' THEN jumlah_pcs ELSE 0 END) as keluar_pcs
            FROM transactions 
            WHERE kode_barang = '$kode_barang'
            AND tanggal BETWEEN '$start_date' AND '$end_date'
        ")->fetch_assoc();
        
        $ringkasan['masuk_box'] = $total['masuk_box'] ?? 0;
        $ringkasan['masuk_pcs'] = $total['masuk_pcs'] ?? 0;
        $ringkasan['keluar_box'] = $total['keluar_box'] ?? 0;
        $ringkasan['keluar_pcs'] = $total['keluar_pcs'] ?? 0;
        
        // Hitung stok akhir
        $total_masuk_pcs = toPcs($ringkasan['masuk_box'], $ringkasan['masuk_pcs'], $isi_per_box);
        $total_keluar_pcs = toPcs($ringkasan['keluar_box'], $ringkasan['keluar_pcs'], $isi_per_box);
        $total_akhir_pcs = $total_pcs_awal + $total_masuk_pcs - $total_keluar_pcs;
        $normal_akhir = toBoxPcs($total_akhir_pcs, $isi_per_box);
        $ringkasan['akhir_box'] = $normal_akhir['box'];
        $ringkasan['akhir_pcs'] = $normal_akhir['pcs'];
    } else {
        $kode_barang = ''; // Reset jika barang tidak ditemukan
    }
}

$conn->close();
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-chart-bar"></i>
        <h2>Laporan Transaksi</h2>
    </div>
    
    <!-- Form Filter dengan Pencarian Manual -->
    <div style="background: #f8fafc; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
        <form method="GET" action="" id="searchForm">
            <input type="hidden" name="page" value="laporan">
            
            <!-- Pencarian Barang (Manual Input) -->
            <div style="margin-bottom: 0.8rem;">
                <label style="display: block; margin-bottom: 0.25rem; font-weight: 500; font-size: 0.75rem;">Cari Barang:</label>
                <div style="display: flex; gap: 0.3rem;">
                    <input type="text" 
                           id="searchInput"
                           name="search" 
                           class="form-control" 
                           style="flex: 1; font-size: 0.8rem; padding: 0.5rem;"
                           placeholder="Ketik kode atau nama barang..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ($kode_barang ? $kode_barang . ' - ' . $nama_barang : '')); ?>"
                           autocomplete="off">
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 0.8rem;">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($kode_barang): ?>
                    <a href="?page=laporan" class="btn" style="background: #64748b; color: white; padding: 0.5rem 0.8rem;">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Hasil Pencarian -->
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <div style="margin-top: 0.3rem; border: 1px solid #e2e8f0; border-radius: 6px; max-width: 100%; background: white;">
                        <?php if ($search_results && $search_results->num_rows > 0): ?>
                            <?php while($b = $search_results->fetch_assoc()): ?>
                            <a href="?page=laporan&kode_barang=<?php echo $b['kode']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?><?php echo $user_id ? '&user_id='.$user_id : ''; ?>" 
                               style="display: block; padding: 0.5rem 0.7rem; text-decoration: none; color: #1e293b; border-bottom: 1px solid #e2e8f0; hover:background: #f8fafc; font-size: 0.75rem;">
                                <strong><?php echo $b['kode']; ?></strong> - <?php echo $b['nama']; ?>
                            </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="padding: 0.5rem; color: #64748b; text-align: center; font-size: 0.75rem;">
                                <i class="fas fa-exclamation-circle"></i> Barang tidak ditemukan
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Filter Tanggal dan User -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem; margin-bottom: 0.5rem;">
                <div>
                    <label style="font-size: 0.7rem;">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control" style="padding: 0.4rem; font-size: 0.7rem;" value="<?php echo $start_date; ?>">
                </div>
                
                <div>
                    <label style="font-size: 0.7rem;">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control" style="padding: 0.4rem; font-size: 0.7rem;" value="<?php echo $end_date; ?>">
                </div>
                
                <?php if (isAdmin() && $user_list && $user_list->num_rows > 0): ?>
                <div>
                    <label style="font-size: 0.7rem;">Filter User</label>
                    <select name="user_id" class="form-control" style="padding: 0.4rem; font-size: 0.7rem;">
                        <option value="">Semua User</option>
                        <?php while($u = $user_list->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo $u['nama_lengkap']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 0.3rem; margin-top: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.7rem;">
                    <i class="fas fa-filter"></i> Tampilkan
                </button>
                
                <?php if ($kode_barang): ?>
                <button type="button" onclick="window.print()" class="btn" style="background: #64748b; color: white; padding: 0.4rem 0.8rem; font-size: 0.7rem;">
                    <i class="fas fa-print"></i> Cetak
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if ($kode_barang && $nama_barang): ?>
    <!-- Info Barang -->
    <div style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0 0 0.2rem 0; font-size: 0.95rem;"><?php echo htmlspecialchars($nama_barang); ?></h3>
                <p style="margin: 0; opacity: 0.8; font-size: 0.65rem;">Kode: <?php echo $kode_barang; ?> | 1 Box = <?php echo $isi_per_box; ?> PCS</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.7rem; opacity: 0.8;">Periode</div>
                <div style="font-weight: 600; font-size: 0.75rem;"><?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Ringkasan -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 1.5rem;">
        <div style="background: #dbeafe; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="color: #1e40af; font-size: 0.6rem;">Stok Awal</div>
            <div style="font-size: 1rem; font-weight: bold;"><?php echo $ringkasan['awal_box']; ?>/<?php echo $ringkasan['awal_pcs']; ?></div>
            <div style="color: #3b82f6; font-size: 0.55rem;"><?php echo number_format(toPcs($ringkasan['awal_box'], $ringkasan['awal_pcs'], $isi_per_box)); ?> pcs</div>
        </div>
        
        <div style="background: #dcfce7; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="color: #166534; font-size: 0.6rem;">Total Masuk</div>
            <div style="font-size: 1rem; font-weight: bold;"><?php echo $ringkasan['masuk_box']; ?>/<?php echo $ringkasan['masuk_pcs']; ?></div>
            <div style="color: #10b981; font-size: 0.55rem;"><?php echo number_format(toPcs($ringkasan['masuk_box'], $ringkasan['masuk_pcs'], $isi_per_box)); ?> pcs</div>
        </div>
        
        <div style="background: #fee2e2; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="color: #991b1b; font-size: 0.6rem;">Total Keluar</div>
            <div style="font-size: 1rem; font-weight: bold;"><?php echo $ringkasan['keluar_box']; ?>/<?php echo $ringkasan['keluar_pcs']; ?></div>
            <div style="color: #ef4444; font-size: 0.55rem;"><?php echo number_format(toPcs($ringkasan['keluar_box'], $ringkasan['keluar_pcs'], $isi_per_box)); ?> pcs</div>
        </div>
        
        <div style="background: #fef3c7; padding: 0.5rem; border-radius: 6px; text-align: center;">
            <div style="color: #92400e; font-size: 0.6rem;">Stok Akhir</div>
            <div style="font-size: 1rem; font-weight: bold;"><?php echo $ringkasan['akhir_box']; ?>/<?php echo $ringkasan['akhir_pcs']; ?></div>
            <div style="color: #f59e0b; font-size: 0.55rem;"><?php echo number_format(toPcs($ringkasan['akhir_box'], $ringkasan['akhir_pcs'], $isi_per_box)); ?> pcs</div>
        </div>
    </div>
    
    <!-- Detail Transaksi -->
    <h3 style="font-size: 0.9rem; margin: 0 0 0.5rem 0;">Detail Transaksi</h3>
    
    <div class="table-container">
        <?php if ($laporan && $laporan->num_rows > 0): ?>
            <table style="font-size: 0.7rem;">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>User</th>
                        <th>Jenis</th>
                        <th>Jumlah</th>
                        <th>Total PCS</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $running_box = $ringkasan['awal_box'];
                    $running_pcs = $ringkasan['awal_pcs'];
                    
                    while($t = $laporan->fetch_assoc()): 
                        // Update stok berjalan
                        if ($t['jenis'] == 'masuk') {
                            $running_box += $t['jumlah_box'];
                            $running_pcs += $t['jumlah_pcs'];
                        } else {
                            $running_box -= $t['jumlah_box'];
                            $running_pcs -= $t['jumlah_pcs'];
                        }
                        
                        // Normalisasi
                        $total_running = toPcs($running_box, $running_pcs, $isi_per_box);
                        $normal = toBoxPcs($total_running, $isi_per_box);
                        $running_box = $normal['box'];
                        $running_pcs = $normal['pcs'];
                        
                        $total_pcs = toPcs($t['jumlah_box'], $t['jumlah_pcs'], $isi_per_box);
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($t['tanggal'])); ?></td>
                        <td><?php echo htmlspecialchars($t['nama_lengkap'] ?? 'System'); ?></td>
                        <td>
                            <?php if($t['jenis'] == 'masuk'): ?>
                                <span style="color: #10b981; font-weight: 600;">
                                    <i class="fas fa-arrow-down" style="font-size: 0.6rem;"></i> MASUK
                                </span>
                            <?php else: ?>
                                <span style="color: #ef4444; font-weight: 600;">
                                    <i class="fas fa-arrow-up" style="font-size: 0.6rem;"></i> KELUAR
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $t['jumlah_box']; ?>/<?php echo $t['jumlah_pcs']; ?></td>
                        <td><?php echo number_format($total_pcs); ?> pcs</td>
                        <td><?php echo htmlspecialchars($t['catatan']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #6b7280; font-size: 0.8rem;">
                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                <p>Tidak ada transaksi pada periode ini.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php elseif (!isset($_GET['search']) && !$kode_barang): ?>
    <!-- Tampilan awal sebelum mencari -->
    <div style="text-align: center; padding: 3rem; color: #6b7280; background: #f8fafc; border-radius: 10px;">
        <i class="fas fa-search" style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #94a3b8;"></i>
        <h3 style="font-size: 1rem; margin-bottom: 0.3rem;">Cari Barang</h3>
        <p style="font-size: 0.75rem;">Ketik kode atau nama barang di kolom pencarian</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto search setelah berhenti mengetik
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