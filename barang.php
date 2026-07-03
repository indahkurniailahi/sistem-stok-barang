<?php
require_once 'config.php';

if (!isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();
$success = '';
$error = '';

// ============================================
// TAMBAH BARANG BARU
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $kode = trim($_POST['kode']);
    $nama = trim($_POST['nama']);
    $isi_per_box = intval($_POST['isi_per_box'] ?? 30); // Ambil isi per box
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    // Validasi
    if (empty($kode) || empty($nama)) {
        $error = 'Kode dan Nama barang wajib diisi!';
    } elseif ($isi_per_box < 1) {
        $error = 'Isi per box harus minimal 1!';
    } else {
        // Cek apakah kode sudah ada
        $check = $conn->prepare("SELECT id FROM barang WHERE kode = ?");
        $check->bind_param("s", $kode);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "Kode barang '$kode' sudah terdaftar!";
        } else {
            // Insert barang baru dengan isi_per_box
            $stmt = $conn->prepare("INSERT INTO barang (kode, nama, isi_per_box, deskripsi) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $kode, $nama, $isi_per_box, $deskripsi);
            
            if ($stmt->execute()) {
                // Langsung buat stok awal
                $insert_stok = $conn->prepare("INSERT INTO stocks (kode_barang, total_box, total_pcs) VALUES (?, 0, 0)");
                $insert_stok->bind_param("s", $kode);
                $insert_stok->execute();
                $insert_stok->close();
                
                setFlash("Barang '$nama' berhasil ditambahkan! (1 Box = $isi_per_box PCS)", 'success');
                
                // Redirect untuk refresh
                redirect('?page=barang');
            } else {
                $error = "Gagal menambahkan barang: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================
// EDIT BARANG
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $kode = trim($_POST['kode']);
    $nama = trim($_POST['nama']);
    $isi_per_box = intval($_POST['isi_per_box'] ?? 30);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $kode_lama = $_POST['kode_lama'] ?? '';
    
    // Validasi
    if (empty($kode) || empty($nama)) {
        $error = 'Kode dan Nama barang wajib diisi!';
    } elseif ($isi_per_box < 1) {
        $error = 'Isi per box harus minimal 1!';
    } else {
        // Cek apakah kode sudah digunakan oleh barang lain
        $check = $conn->prepare("SELECT id FROM barang WHERE kode = ? AND id != ?");
        $check->bind_param("si", $kode, $id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "Kode barang '$kode' sudah digunakan oleh barang lain!";
        } else {
            // Update barang
            $stmt = $conn->prepare("UPDATE barang SET kode = ?, nama = ?, isi_per_box = ?, deskripsi = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssisi", $kode, $nama, $isi_per_box, $deskripsi, $id);
            
            if ($stmt->execute()) {
                // Update juga kode di stocks jika berubah
                if ($kode != $kode_lama) {
                    $conn->query("UPDATE stocks SET kode_barang = '$kode' WHERE kode_barang = '$kode_lama'");
                }
                
                setFlash("Barang '$nama' berhasil diperbarui! (1 Box = $isi_per_box PCS)", 'success');
                redirect('?page=barang');
            } else {
                $error = "Gagal memperbarui barang: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================
// HAPUS BARANG
// ============================================
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    // Ambil kode barang dulu
    $result = $conn->query("SELECT kode, nama FROM barang WHERE id = $id");
    $barang = $result->fetch_assoc();
    $kode = $barang['kode'] ?? '';
    $nama = $barang['nama'] ?? '';
    
    // Cek apakah barang ada di transaksi
    $check = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE kode_barang = ?");
    $check->bind_param("s", $kode);
    $check->execute();
    $result = $check->get_result();
    $count = $result->fetch_assoc()['total'];
    $check->close();
    
    if ($count > 0) {
        $error = "Barang '$nama' tidak bisa dihapus karena sudah ada dalam transaksi!";
    } else {
        // Hapus dari stocks dulu
        $conn->query("DELETE FROM stocks WHERE kode_barang = '$kode'");
        
        // Hapus dari barang
        $stmt = $conn->prepare("DELETE FROM barang WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            setFlash("Barang '$nama' berhasil dihapus!", 'success');
            redirect('?page=barang');
        } else {
            $error = "Gagal menghapus barang: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// AMBIL DATA UNTUK EDIT (jika ada parameter edit)
// ============================================
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM barang WHERE id = $id");
    $edit_data = $result->fetch_assoc();
}

// ============================================
// PENCARIAN
// ============================================
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM barang WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (kode LIKE ? OR nama LIKE ? OR deskripsi LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$barang = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang</title>
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 1.5rem;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .close-modal {
            float: right;
            font-size: 1.2rem;
            cursor: pointer;
            color: #64748b;
        }
        
        .close-modal:hover {
            color: #ef4444;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.3rem;
        }
        
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.65rem;
        }
        
        .search-box {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .total-info {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .kode-badge {
            background: #f1f5f9;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.7rem;
        }
        
        .info-box {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 0.5rem 0.8rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.7rem;
            color: #0369a1;
        }
        
        .info-box i {
            margin-right: 0.3rem;
        }
    </style>
</head>
<body>
    <!-- Modal Tambah/Edit Barang -->
<div id="barangModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1rem; margin: 0;" id="modalTitle">➕ TAMBAH BARANG BARU</h3>
            <span onclick="closeModal()" style="cursor: pointer; font-size: 1.2rem;">&times;</span>
        </div>
        
        <form method="POST" action="" id="barangForm">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="edit" id="isEdit" value="">
            
            <!-- Kode Barang -->
            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.8rem;">
                    <i class="fas fa-barcode"></i> Kode Barang <span style="color: red;">*</span>
                </label>
                <input type="text" id="kode" name="kode" class="form-control" required 
                       placeholder="Contoh: BRG001" maxlength="20">
                <small style="color: #64748b;">Kode unik, tidak boleh sama dengan barang lain</small>
            </div>
            
            <!-- Nama Barang -->
            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.8rem;">
                    <i class="fas fa-box"></i> Nama Barang <span style="color: red;">*</span>
                </label>
                <input type="text" id="nama" name="nama" class="form-control" required 
                       placeholder="Nama lengkap barang">
            </div>
            
            <!-- Isi per Box -->
            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.8rem;">
                    <i class="fas fa-cubes"></i> Isi per Box (PCS) <span style="color: red;">*</span>
                </label>
                <input type="number" id="isi_per_box" name="isi_per_box" class="form-control" required 
                       min="1" max="10000" value="30" placeholder="Contoh: 12, 24, 30, 50">
                <small style="color: #64748b;">Jumlah pcs dalam 1 box (contoh: 1 box = 30 pcs)</small>
            </div>
            
            <!-- Deskripsi -->
            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.8rem;">
                    <i class="fas fa-align-left"></i> Deskripsi (Opsional)
                </label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" 
                          rows="2" placeholder="Keterangan tambahan"></textarea>
            </div>
            
            <!-- Tombol Aksi -->
            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" onclick="closeModal()" class="btn" 
                        style="flex: 1; background: #64748b; color: white;">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" name="tambah" class="btn btn-success" style="flex: 2;">
                    <i class="fas fa-save"></i> Simpan Barang
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function openModal(type, id = null) {
    const modal = document.getElementById('barangModal');
    const modalTitle = document.getElementById('modalTitle');
    
    if (type === 'tambah') {
        modalTitle.innerHTML = '➕ TAMBAH BARANG BARU';
        document.getElementById('editId').value = '';
        document.getElementById('isEdit').value = '';
        document.getElementById('barangForm').reset();
        document.getElementById('kode').value = '';
        document.getElementById('nama').value = '';
        document.getElementById('isi_per_box').value = '30';
        document.getElementById('deskripsi').value = '';
    }
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('barangModal').style.display = 'none';
}

// Tutup modal jika klik di luar
window.onclick = function(event) {
    const modal = document.getElementById('barangModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

    <!-- Konten Utama -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-boxes"></i>
            <h2>Data Barang</h2>
        </div>
        
        <!-- Info Penting -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Info:</strong> Setiap barang memiliki "Isi per Box" yang menentukan konversi stok.
            Contoh: 1 Box = 30 PCS, maka 35 PCS akan otomatis jadi 1 Box + 5 PCS.
        </div>
        
        <!-- Pesan Status -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- TOMBOL TAMBAH BARANG YANG BESAR DAN JELAS -->
    <button onclick="openModal('tambah')" class="btn btn-success" style="padding: 0.6rem 1.2rem; font-size: 0.8rem;">
        <i class="fas fa-plus-circle"></i> TAMBAH BARANG BARU
    </button>
</div>
        
        
        <!-- Tabel Barang -->
        <!-- Tabel Data Barang dengan Scroll -->
<div style="margin-top: 1rem;">
    <!-- Header dengan tombol tambah dan info -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <div style="background: #1e293b; color: white; padding: 0.2rem 1rem; border-radius: 20px; font-size: 0.7rem; display: inline-block;">
                <i class="fas fa-box"></i> Total: <strong><?php echo count($barang); ?></strong> barang
            </div>
        </div>
        
        <!-- Search box kecil -->
        <div style="display: flex; gap: 0.2rem;">
            <input type="text" id="searchBarang" class="form-control" 
                   placeholder="Cari..." 
                   style="width: 150px; font-size: 0.65rem; padding: 0.25rem;">
            <button class="btn btn-primary" style="padding: 0.25rem 0.6rem; font-size: 0.65rem;" onclick="filterTable()">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    
    <!-- Container dengan scroll - UKURAN LEBIH KECIL -->
    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; scrollbar-width: thin;" id="tableContainer">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.65rem;">
            <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                <tr>
                    <th style="padding: 6px 4px; width: 80px;">Kode</th>
                    <th style="padding: 6px 4px;">Nama Barang</th>
                        
                    <th style="padding: 6px 4px; width: 80px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php if (empty($barang)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem; color: #64748b;">
                            <i class="fas fa-box-open" style="font-size: 1.5rem; margin-bottom: 0.3rem; display: block;"></i>
                            Belum ada data barang
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($barang as $item): ?>
                    <tr class="barang-row">
                        <td style="padding: 5px 4px; border-bottom: 1px solid #e2e8f0;">
                            <span style="background: #f1f5f9; padding: 2px 4px; border-radius: 3px; font-family: monospace; font-size: 0.6rem;">
                                <?php echo htmlspecialchars($item['kode']); ?>
                            </span>
                        </td>
                        <td style="padding: 5px 4px; border-bottom: 1px solid #e2e8f0;">
                            <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                                <?php echo htmlspecialchars($item['nama']); ?>
                            </div>
                            <div style="font-size: 0.55rem; color: #64748b;">
                                <?php echo $item['isi_per_box'] ?? 30; ?> pcs/box
                                <?php if (!empty($item['deskripsi'])): ?>
                                    | <?php echo htmlspecialchars($item['deskripsi']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td style="padding: 5px 4px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                            <div style="display: flex; gap: 0.2rem; justify-content: center;">
                                <a href="?page=barang&edit=<?php echo $item['id']; ?>" 
                                   class="btn btn-sm" 
                                   style="background: #3b82f6; color: white; padding: 2px 5px; font-size: 0.55rem; border-radius: 3px; text-decoration: none;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?page=transaksi&kode=<?php echo $item['kode']; ?>" 
                                   class="btn btn-sm" 
                                   style="background: #10b981; color: white; padding: 2px 5px; font-size: 0.55rem; border-radius: 3px; text-decoration: none;">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                                <a href="?page=barang&hapus=<?php echo $item['id']; ?>" 
                                   class="btn btn-sm" 
                                   style="background: #ef4444; color: white; padding: 2px 5px; font-size: 0.55rem; border-radius: 3px; text-decoration: none;"
                                   onclick="return confirm('Hapus?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    
    

<!-- Tambahkan CSS untuk scrollbar -->
<style>
/* Styling scrollbar */
#tableContainer::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#tableContainer::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

#tableContainer::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 10px;
}

#tableContainer::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

/* Sticky header */
#tableContainer thead th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    z-index: 10;
}

/* Hover effect */
.barang-row:hover {
    background: #f8fafc;
}

/* Responsive */
@media (max-width: 768px) {
    #tableContainer {
        max-height: 350px;
        font-size: 0.65rem;
    }
    
    #tableContainer td, 
    #tableContainer th {
        padding: 6px 4px;
    }
}
</style>

<script>
// Fitur pencarian real-time
function filterTable() {
    const searchInput = document.getElementById('searchBarang');
    const filter = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll('#tableBody .barang-row');
    let visible = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update jumlah yang terlihat
    document.getElementById('visibleCount').textContent = visible;
}

// Event listener untuk pencarian (otomatis saat mengetik)
document.getElementById('searchBarang').addEventListener('keyup', filterTable);

// Load jumlah awal
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('visibleCount').textContent = '<?php echo count($barang); ?>';
});
</script>
</body>
</html>