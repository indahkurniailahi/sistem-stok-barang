<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();

// Fungsi deteksi mobile (untuk penyesuaian kecil)
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['android', 'iphone', 'ipad', 'mobile', 'blackberry', 'windows phone'];
    
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return true;
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>🏪 Sistem Stok Barang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
            font-size: 13px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header Minimal (Hanya Nama User) */
        .mini-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 18px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .mini-header .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .mini-header .logo i {
            font-size: 1.1rem;
        }
        
        .mini-header .logo span {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .mini-header .user {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
        }
        
        .mini-header .user i {
            font-size: 0.9rem;
        }
        
        .mini-header .logout-link {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            font-size: 0.7rem;
        }
        
        /* Main Content - Dengan padding bottom untuk bottom nav */
        .main-content {
            flex: 1;
            padding: 16px;
            padding-bottom: 70px; /* Ruang untuk bottom nav */
            background: #f8fafc;
        }
        
        /* Bottom Navigation - Untuk SEMUA device */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 8px 12px;
            display: flex;
            justify-content: space-around;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 1000;
        }
        
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.6rem;
            gap: 2px;
            padding: 4px 0;
            flex: 1;
            max-width: 80px;
        }
        
        .bottom-nav .nav-item i {
            font-size: 1.2rem;
        }
        
        .bottom-nav .nav-item.active {
            color: #1e293b;
            font-weight: 600;
        }
        
        .bottom-nav .nav-item.active i {
            color: #1e293b;
        }
        
        /* Untuk desktop, bottom nav tetap di bawah tapi lebih kecil */
        @media (min-width: 768px) {
            .container {
                max-width: 1200px;
                margin: 20px auto;
                min-height: auto;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            
            .main-content {
                padding-bottom: 70px; /* Tetap ada bottom nav */
            }
            
            .bottom-nav {
                max-width: 1200px;
                left: 50%;
                transform: translateX(-50%);
                border-radius: 20px 20px 0 0;
                border: 1px solid #e2e8f0;
                border-bottom: none;
                box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
            }
            
            .bottom-nav .nav-item {
                font-size: 0.65rem;
            }
            
            .bottom-nav .nav-item i {
                font-size: 1.1rem;
            }
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #1e293b;
        }
        
        .card-header i {
            font-size: 1rem;
            color: #1e293b;
        }
        
        .card-header h2 {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 4px;
            color: #0f172a;
            font-weight: 500;
            font-size: 0.7rem;
        }
        
        .form-control {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 0.75rem;
        }
        
        /* Buttons */
        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
        }
        
        .stat-info h3 {
            font-size: 1rem;
            color: #0f172a;
            margin-bottom: 1px;
        }
        
        .stat-info p {
            color: #64748b;
            font-size: 0.6rem;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
            font-size: 0.7rem;
        }
        
        th {
            background: #f8fafc;
            padding: 6px 8px;
            font-weight: 600;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.65rem;
        }
        
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* Alert */
        .alert {
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        /* Mobile Specific */
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .mini-header .user span {
                display: none; /* Sembunyikan nama di HP */
            }
            
            .bottom-nav .nav-item {
                font-size: 0.55rem;
            }
            
            .bottom-nav .nav-item i {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mini Header (Hanya penting saja) -->
        <div class="mini-header">
            <div class="logo">
                <i class="fas fa-boxes"></i>
                <span>Stok Barang</span>
            </div>
            <div class="user">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($user['nama_lengkap']); ?></span>
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php
            $flash = getFlash();
            if ($flash):
            ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <?php
            $page = $_GET['page'] ?? 'dashboard';
            
            switch ($page) {
                case 'mobile':
                case 'dashboard':
                    include 'dashboard.php';
                    break;
                case 'transaksi_list': // TAMBAHKAN INI
                include 'transaksi_list.php';
                break;
                 case 'profile':
                include 'profile.php';
                break;
                case 'users':
                    if (isAdmin()) include 'users.php';
                    else echo '<div class="alert alert-error">Akses ditolak!</div>';
                    break;
                case 'barang':
                    if (isAdmin()) include 'barang.php';
                    else echo '<div class="alert alert-error">Akses ditolak!</div>';
                    break;
                case 'upload_simple':
                    if (isAdmin()) include 'upload_simple.php';
                    else echo '<div class="alert alert-error">Akses ditolak!</div>';
                    break;
                case 'transaksi':
                    include 'transaksi.php';
                    break;
                case 'laporan':
                    include 'laporan.php';
                    break;
                default:
                    include 'dashboard.php';
            }
            ?>
        </div>
        
        <!-- Bottom Navigation (Untuk SEMUA Device) -->
        <div class="bottom-nav">
    <a href="?page=dashboard" class="nav-item <?php echo ($_GET['page'] ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    
    <?php if (isAdmin()): ?>
    <a href="?page=barang" class="nav-item <?php echo ($_GET['page'] ?? '') == 'barang' ? 'active' : ''; ?>">
        <i class="fas fa-box"></i>
        <span>Barang</span>
    </a>
    
    <!-- TAMBAHKAN MENU UPLOAD UNTUK ADMIN -->
    <a href="?page=upload_simple" class="nav-item <?php echo ($_GET['page'] ?? '') == 'upload_simple' ? 'active' : ''; ?>">
        <i class="fas fa-upload"></i>
        <span>Upload</span>
    </a>
    <?php endif; ?>
    
    <a href="?page=transaksi" class="nav-item <?php echo ($_GET['page'] ?? '') == 'transaksi' ? 'active' : ''; ?>">
        <i class="fas fa-exchange-alt"></i>
        <span>Transaksi</span>
    </a>
    
    <a href="?page=laporan" class="nav-item <?php echo ($_GET['page'] ?? '') == 'laporan' ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i>
        <span>Laporan</span>
    </a>
    
    <!-- Menu Users untuk admin -->
    <a href="?page=users" class="nav-item <?php echo ($_GET['page'] ?? '') == 'users' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>User</span>
    </a>
    
    <!-- Menu Profile untuk semua user (admin & karyawan) -->
    <a href="?page=profile" class="nav-item <?php echo ($_GET['page'] ?? '') == 'profile' ? 'active' : ''; ?>">
        <i class="fas fa-user-cog"></i>
        <span>Profile</span>
    </a>
</div>
    </div>
    
    <script>
    function confirmDelete(message = 'Apakah Anda yakin?') {
        return confirm(message);
    }
    
    // Auto hide alert
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 3000);
        });
    });
    </script>
</body>
</html>