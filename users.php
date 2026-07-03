<?php
require_once 'config.php';

if (!isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();

// Add new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $nama_lengkap, $email, $role);
    
    if ($stmt->execute()) {
        setFlash('User berhasil ditambahkan!', 'success');
        redirect('?page=users');
    }
}

// Delete user
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id != $_SESSION['user_id']) { // Can't delete yourself
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        setFlash('User berhasil dihapus!', 'success');
        redirect('?page=users');
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$conn->close();
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-users"></i>
        <h2>Kelola User</h2>
    </div>
    
    <!-- Add User Form -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <i class="fas fa-user-plus"></i>
            <h3>Tambah User Baru</h3>
        </div>
        
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.8rem; margin-bottom: 0.8rem;">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="karyawan">Karyawan</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="add_user" class="btn btn-success">
                <i class="fas fa-save"></i> Simpan User
            </button>
        </form>
    </div>
    
    <!-- Users List -->
    <h3 style="font-size: 1rem; margin-bottom: 0.8rem;">Daftar User</h3>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Tanggal Daftar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <?php if (!$user['is_active']): ?>
                        <span style="color: #e53e3e; font-size: 0.65rem;">(non-aktif)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <!-- Role tanpa background, hanya teks berwarna -->
                        <?php if ($user['role'] == 'admin'): ?>
                            <span style="color: #3b82f6; font-weight: 600; font-size: 0.75rem;">
                                <i  style="font-size: 0.65rem;"></i> ADMIN
                            </span>
                        <?php else: ?>
                            <span style="color: #10b981; font-weight: 600; font-size: 0.75rem;">
                                <i  style="font-size: 0.65rem;"></i> KARYAWAN
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="?page=users&delete=<?php echo $user['id']; ?>" 
                           class="btn btn-danger btn-sm" 
                           style="padding: 0.2rem 0.5rem; font-size: 0.65rem;"
                           onclick="return confirmDelete('Hapus user <?php echo $user['username']; ?>?')">
                            <i class="fas fa-trash"></i> Hapus
                        </a>
                        <?php else: ?>
                        <span style="color: #94a3b8; font-size: 0.65rem;">
                            <i class="fas fa-user-check"></i> Anda
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>