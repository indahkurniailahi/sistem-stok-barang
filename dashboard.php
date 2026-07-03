<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();

// ============================================
// AMBIL DATA UNTUK DASHBOARD
// ============================================

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $result->fetch_assoc()['total'] ?? 0;

// Total barang
$result = $conn->query("SELECT COUNT(*) as total FROM barang");
$total_barang = $result->fetch_assoc()['total'] ?? 0;

// Total transaksi hari ini
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as total FROM transactions WHERE DATE(tanggal) = '$today'");
$transaksi_hari_ini = $result->fetch_assoc()['total'] ?? 0;

// Statistik hari ini (masuk vs keluar)
$jenis_transaksi = $conn->query("
    SELECT 
        SUM(CASE WHEN jenis = 'masuk' THEN 1 ELSE 0 END) as masuk,
        SUM(CASE WHEN jenis = 'keluar' THEN 1 ELSE 0 END) as keluar
    FROM transactions 
    WHERE DATE(tanggal) = '$today'
")->fetch_assoc();

// Transaksi terbaru (hanya 5 untuk dashboard)
$recent_transactions = $conn->query("
    SELECT t.*, 
           u.nama_lengkap, 
           b.nama as nama_barang
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN barang b ON t.kode_barang = b.kode
    ORDER BY t.created_at DESC 
    LIMIT 5
");

$conn->close();
?>

<div class="card">
    <!-- Header Dashboard -->
    <div class="card-header">
        <i class="fas fa-tachometer-alt"></i>
        <h2>Dashboard</h2>
    </div>
    
    <!-- Welcome Message -->
    <div style="background: linear-gradient(135deg, #4955a1 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-hand-wave" style="font-size: 1.2rem;"></i>
            <div>
                <strong style="font-size: 0.9rem;">Halo, <?php echo htmlspecialchars($_SESSION['nama'] ?? 'User'); ?>!</strong>
                <p style="margin: 0; font-size: 0.7rem; opacity: 0.9;"><?php echo date('l, d F Y'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Statistik Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
            <div class="stat-icon" style="background: #3b82f6;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($total_users); ?></h3>
                <p>Total User</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($total_barang); ?></h3>
                <p>Total Barang</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #24204f;">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($transaksi_hari_ini); ?></h3>
                <p>Transaksi Hari Ini</p>
            </div>
        </div>
    </div>
    
    <!-- Tombol Aksi Cepat -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin: 1.5rem 0;">
        <a href="?page=transaksi" class="btn btn-primary" style="justify-content: center; padding: 0.5rem;">
            <i class="fas fa-plus-circle"></i> <span style="font-size: 0.7rem;">Transaksi Baru</span>
        </a>
        <a href="?page=barang" class="btn" style="background: #64748b; color: white; justify-content: center; padding: 0.5rem;">
            <i class="fas fa-box"></i> <span style="font-size: 0.7rem;">Data Barang</span>
        </a>
        <a href="?page=laporan" class="btn" style="background: #2b0d71; color: white; justify-content: center; padding: 0.5rem;">
            <i class="fas fa-chart-bar"></i> <span style="font-size: 0.7rem;">Laporan</span>
        </a>
    </div>
    
    <!-- Statistik Hari Ini -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 1.5rem;">
        <div style="background: #dbeafe; padding: 0.75rem; border-radius: 8px; text-align: center;">
            <i class="fas fa-arrow-down" style="color: #10b981; font-size: 1rem;"></i>
            <div style="font-size: 1.2rem; font-weight: bold; margin: 0.2rem 0;"><?php echo number_format($jenis_transaksi['masuk'] ?? 0); ?></div>
            <div style="font-size: 0.6rem; color: #4b5563;">Barang Masuk Hari Ini</div>
        </div>
        <div style="background: #fee2e2; padding: 0.75rem; border-radius: 8px; text-align: center;">
            <i class="fas fa-arrow-up" style="color: #ef4444; font-size: 1rem;"></i>
            <div style="font-size: 1.2rem; font-weight: bold; margin: 0.2rem 0;"><?php echo number_format($jenis_transaksi['keluar'] ?? 0); ?></div>
            <div style="font-size: 0.6rem; color: #4b5563;">Barang Keluar Hari Ini</div>
        </div>
    </div>
    
    <!-- Transaksi Terbaru dengan Link "Lihat Semua" -->
    <div style="margin-top: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <h3 style="font-size: 0.9rem; font-weight: 600;">Transaksi Terbaru</h3>
            <a href="?page=transaksi_list" style="font-size: 0.7rem; color: #3b82f6; text-decoration: none;">
                Lihat semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="table-container">
            <?php if ($recent_transactions && $recent_transactions->num_rows > 0): ?>
                <table style="font-size: 0.65rem;">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Barang</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($trans = $recent_transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($trans['created_at'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($trans['nama_barang'] ?? '-'); ?>
                                <br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($trans['kode_barang']); ?></small>
                            </td>
                            <td>
                                <?php if ($trans['jenis'] == 'masuk'): ?>
                                    <span style="color: #10b981; font-weight: 600; font-size: 0.6rem;">
                                        <i class="fas fa-arrow-down"></i> MASUK
                                    </span>
                                <?php else: ?>
                                    <span style="color: #ef4444; font-weight: 600; font-size: 0.6rem;">
                                        <i class="fas fa-arrow-up"></i> KELUAR
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $trans['jumlah_box']; ?>/<?php echo $trans['jumlah_pcs']; ?>
                                <br>
                                <small style="color: #6b7280;"><?php echo ($trans['jumlah_box'] * 30) + $trans['jumlah_pcs']; ?> pcs</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 1.5rem; color: #6b7280; font-size: 0.7rem;">
                    <i class="fas fa-inbox" style="font-size: 1.5rem; margin-bottom: 0.3rem;"></i>
                    <p>Belum ada transaksi</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>