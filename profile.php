<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$conn = getConnection();
$user = getCurrentUser();

$success = '';
$error = '';

// Proses ubah password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ubah_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    // Validasi
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $error = 'Semua field harus diisi!';
    } elseif ($password_baru !== $konfirmasi_password) {
        $error = 'Password baru dan konfirmasi tidak cocok!';
    } elseif (strlen($password_baru) < 6) {
        $error = 'Password baru minimal 6 karakter!';
    } else {
        // Cek password lama
        if (password_verify($password_lama, $user['password'])) {
            // Update password
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $password_hash, $user['id']);
            
            if ($stmt->execute()) {
                $success = 'Password berhasil diubah!';
            } else {
                $error = 'Gagal mengubah password: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Password lama salah!';
        }
    }
}

$conn->close();
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-user-circle"></i>
        <h2>Profile Saya</h2>
    </div>
    
    <!-- Info User -->
    <div style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <h3 style="margin: 0 0 0.2rem 0;"><?php echo htmlspecialchars($user['nama_lengkap']); ?></h3>
                <p style="margin: 0; font-size: 0.7rem; opacity: 0.8;">@<?php echo htmlspecialchars($user['username']); ?> | <?php echo ucfirst($user['role']); ?></p>
                <p style="margin: 0.2rem 0 0 0; font-size: 0.65rem; opacity: 0.7;"><?php echo htmlspecialchars($user['email'] ?? 'Email tidak tersedia'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Form Ubah Password -->
    <div style="background: #f8fafc; padding: 1.2rem; border-radius: 10px;">
        <h3 style="font-size: 0.9rem; margin: 0 0 1rem 0;">
            <i class="fas fa-lock"></i> Ubah Password
        </h3>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 1rem;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" onsubmit="return validatePassword()">
            <!-- Password Lama -->
            <div class="form-group">
                <label style="font-size: 0.75rem;">
                    <i class="fas fa-key"></i> Password Lama
                </label>
                <div style="position: relative;">
                    <input type="password" id="password_lama" name="password_lama" class="form-control" required 
                           style="padding-right: 30px; font-size: 0.8rem;">
                    <span onclick="togglePassword('password_lama')" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                        <i class="fas fa-eye" id="toggle_password_lama"></i>
                    </span>
                </div>
            </div>
            
            <!-- Password Baru -->
            <div class="form-group">
                <label style="font-size: 0.75rem;">
                    <i class="fas fa-lock"></i> Password Baru
                </label>
                <div style="position: relative;">
                    <input type="password" id="password_baru" name="password_baru" class="form-control" required 
                           minlength="6" style="padding-right: 30px; font-size: 0.8rem;">
                    <span onclick="togglePassword('password_baru')" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                        <i class="fas fa-eye" id="toggle_password_baru"></i>
                    </span>
                </div>
                <div style="margin-top: 0.2rem; font-size: 0.6rem; color: #64748b;">
                    <i class="fas fa-info-circle"></i> Minimal 6 karakter
                </div>
            </div>
            
            <!-- Konfirmasi Password -->
            <div class="form-group">
                <label style="font-size: 0.75rem;">
                    <i class="fas fa-check-circle"></i> Konfirmasi Password Baru
                </label>
                <div style="position: relative;">
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" required 
                           style="padding-right: 30px; font-size: 0.8rem;">
                    <span onclick="togglePassword('konfirmasi_password')" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                        <i class="fas fa-eye" id="toggle_konfirmasi_password"></i>
                    </span>
                </div>
                <div id="password_match" style="font-size: 0.6rem; margin-top: 0.2rem;"></div>
            </div>
            
            <!-- Tombol Submit -->
            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="submit" name="ubah_password" class="btn btn-primary" style="flex: 1; padding: 0.5rem;">
                    <i class="fas fa-save"></i> Ubah Password
                </button>
                <a href="?page=dashboard" class="btn" style="background: #64748b; color: white; padding: 0.5rem 1rem;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
    
    <!-- Info Tambahan -->
    <div style="margin-top: 1rem; font-size: 0.65rem; color: #64748b; text-align: center;">
        <i class="fas fa-shield-alt"></i> Jaga kerahasiaan password Anda
    </div>
</div>

<!-- Script untuk toggle password dan validasi -->
<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById('toggle_' + fieldId);
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function validatePassword() {
    const passwordBaru = document.getElementById('password_baru').value;
    const konfirmasi = document.getElementById('konfirmasi_password').value;
    const matchDiv = document.getElementById('password_match');
    
    if (passwordBaru !== konfirmasi) {
        matchDiv.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times"></i> Password tidak cocok!</span>';
        return false;
    } else if (passwordBaru.length > 0) {
        matchDiv.innerHTML = '<span style="color: #10b981;"><i class="fas fa-check"></i> Password cocok</span>';
    }
    
    return true;
}

// Real-time validation
document.getElementById('konfirmasi_password').addEventListener('keyup', validatePassword);
document.getElementById('password_baru').addEventListener('keyup', validatePassword);
</script>

<style>
/* Animasi untuk alert */
.alert {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>