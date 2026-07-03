<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$conn = getConnection();

// Fungsi bantu
if (!function_exists('toPcs')) {
    function toPcs($box, $pcs, $isi_per_box = 30) {
        return ($box * $isi_per_box) + $pcs;
    }
}

// Ambil data
$total_barang = $conn->query("SELECT COUNT(*) as total FROM barang")->fetch_assoc()['total'] ?? 0;
$total_stok = $conn->query("SELECT SUM(total_box * 30 + total_pcs) as total FROM stocks")->fetch_assoc()['total'] ?? 0;
$total_transaksi = $conn->query("SELECT COUNT(*) as total FROM transactions WHERE DATE(tanggal) = CURDATE()")->fetch_assoc()['total'] ?? 0;

// Transaksi terbaru
$transaksi = $conn->query("
    SELECT t.*, b.nama as nama_barang 
    FROM transactions t
    JOIN barang b ON t.kode_barang = b.kode
    ORDER BY t.created_at DESC 
    LIMIT 5
");

$conn->close();
?>

<style>
    /* Mobile specific - ukuran lebih kecil */
    .mobile-view {
        font-size: 13px;
    }
    
    .mobile-view .header-mobile {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: white;
        padding: 16px;
        border-radius: 0 0 20px 20px;
    }
    
    .mobile-view .greeting {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .mobile-view .greeting h1 {
        font-size: 1.3rem;
        font-weight: 600;
    }
    
    .mobile-view .badge {
        background: rgba(255,255,255,0.2);
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.7rem;
    }
    
    .mobile-view .user-card {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .mobile-view .avatar {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 600;
    }
    
    .mobile-view .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 20px;
        margin-top: -10px;
    }
    
    .mobile-view .stat-card {
        background: white;
        border-radius: 12px;
        padding: 12px 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        text-align: center;
    }
    
    .mobile-view .stat-icon {
        width: 32px;
        height: 32px;
        background: #65494d;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 6px;
        font-size: 0.9rem;
    }
    
    .mobile-view .stat-number {
        font-size: 1.2rem;
        font-weight: 700;
    }
    
    .mobile-view .stat-label {
        font-size: 0.65rem;
        color: #64748b;
    }
    
    .mobile-view .quick-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        padding: 0 16px 16px;
    }
    
    .mobile-view .action-btn {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 4px;
        text-align: center;
        text-decoration: none;
        color: #1e293b;
        font-size: 0.7rem;
    }
    
    .mobile-view .action-btn i {
        font-size: 1rem;
        display: block;
        margin-bottom: 2px;
    }
    
    .mobile-view .section {
        padding: 16px;
    }
    
    .mobile-view .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .mobile-view .section-header h2 {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .mobile-view .section-header a {
        font-size: 0.75rem;
    }
    
    .mobile-view .transaction-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .mobile-view .transaction-item {
        background: white;
        border-radius: 10px;
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #f1f5f9;
    }
    
    .mobile-view .transaction-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    
    .mobile-view .transaction-icon.masuk {
        background: #dcfce7;
        color: #16a34a;
    }
    
    .mobile-view .transaction-icon.keluar {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .mobile-view .transaction-details {
        flex: 1;
    }
    
    .mobile-view .transaction-details h4 {
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .mobile-view .transaction-details p {
        font-size: 0.65rem;
        color: #64748b;
    }
    
    .mobile-view .transaction-amount {
        text-align: right;
    }
    
    .mobile-view .amount-main {
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .mobile-view .amount-sub {
        font-size: 0.6rem;
        color: #64748b;
    }
    
    .mobile-view .bottom-nav {
        background: white;
        border-top: 1px solid #e2e8f0;
        padding: 6px 12px;
        display: flex;
        justify-content: space-around;
        position: sticky;
        bottom: 0;
        margin-top: 16px;
    }
    
    .mobile-view .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: #94a3b8;
        font-size: 0.6rem;
        gap: 2px;
    }
    
    .mobile-view .nav-item i {
        font-size: 1rem;
    }
    
    .mobile-view .nav-item.active {
        color: #1e293b;
    }
</style>

<div class="mobile-view">
    <div class="header-mobile">
        <div class="greeting">
            <h1>Stok Mobile</h1>
            <span class="badge"><?php echo ucfirst($user['role']); ?></span>
        </div>
        
        <div class="user-card">
            <div class="avatar">
                <?php echo strtoupper(substr($user['nama_lengkap'] ?? $user['username'], 0, 2)); ?>
            </div>
            <div>
                <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($user['nama_lengkap'] ?? $user['username']); ?></div>
                <div style="font-size: 0.65rem; opacity: 0.8;"><?php echo date('d M Y'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-number"><?php echo $total_barang; ?></div>
            <div class="stat-label">Barang</div>
        </div>
        <!-- <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-cubes"></i></div>
            <div class="stat-number"><?php echo number_format($total_stok); ?></div>
            <div class="stat-label">Total Stok</div>
        </div> -->
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-number"><?php echo $total_transaksi; ?></div>
            <div class="stat-label">Transaksi</div>
        </div>
    </div>
    
    <div class="quick-actions">
        <a href="?page=transaksi" class="action-btn">
            <i class="fas fa-plus-circle"></i> Transaksi
        </a>
        <a href="?page=barang" class="action-btn">
            <i class="fas fa-box"></i> Barang
        </a>
        <a href="?page=laporan" class="action-btn">
            <i class="fas fa-chart-bar"></i> Laporan
        </a>
    </div>
    
    <div class="section">
        <div class="section-header">
            <h2>Transaksi Terbaru</h2>
            <a href="?page=transaksi">Lihat semua →</a>
        </div>
        
        <div class="transaction-list">
            <?php if ($transaksi && $transaksi->num_rows > 0): ?>
                <?php while($t = $transaksi->fetch_assoc()): ?>
                <div class="transaction-item">
                    <div class="transaction-icon" style="background: none; color: <?php echo $t['jenis'] == 'masuk' ? '#10b981' : '#ef4444'; ?>;">
    <i class="fas fa-<?php echo $t['jenis'] == 'masuk' ? 'arrow-down' : 'arrow-up'; ?>"></i>
</div>
                    <div class="transaction-details">
                        <h4><?php echo htmlspecialchars($t['nama_barang']); ?></h4>
                        <p><?php echo date('d/m H:i', strtotime($t['created_at'])); ?></p>
                    </div>
                    <div class="transaction-amount">
                        <div class="amount-main"><?php echo $t['jumlah_box']; ?>/<?php echo $t['jumlah_pcs']; ?></div>
                        <div class="amount-sub"><?php echo toPcs($t['jumlah_box'], $t['jumlah_pcs'], 30); ?> pcs</div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 0.8rem;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 5px;"></i>
                    <p>Belum ada transaksi</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bottom-nav">
        <a href="?page=mobile" class="nav-item active">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="?page=transaksi" class="nav-item">
            <i class="fas fa-exchange-alt"></i>
            <span>Transaksi</span>
        </a>
        <a href="?page=barang" class="nav-item">
            <i class="fas fa-box"></i>
            <span>Barang</span>
        </a>
        <a href="?page=laporan" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Laporan</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
    </div>
</div>