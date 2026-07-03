<?php
require_once 'config.php';

if (!isAdmin()) {
    redirect('index.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Validasi file
    if ($file['error'] != 0) {
        $error = 'Error upload file: ' . $file['error'];
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $error = 'File terlalu besar (maksimal 2MB)';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $error = 'Hanya file Excel (CSV, XLS, XLSX) yang didukung';
        } else {
            // Upload file
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'barang_' . date('Ymd_His') . '.' . $ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Proses file berdasarkan ekstensi
                $conn = getConnection();
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                if ($ext == 'csv') {
                    // Proses CSV
                    $handle = fopen($target_file, 'r');
                    $row = 1;
                    
                    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                        // Skip baris pertama jika header
                        if ($row == 1 && strtolower($data[0]) == 'kode') {
                            $row++;
                            continue;
                        }
                        
                        // Bersihkan data
                        $kode = trim($data[0] ?? '');
                        $nama = trim($data[1] ?? '');
                        $isi_per_box = isset($data[2]) ? intval(trim($data[2])) : 30;
                        
                        if (empty($kode) || empty($nama)) {
                            $errors[] = "Baris $row: Kode atau nama kosong";
                            $row++;
                            continue;
                        }
                        
                        if ($isi_per_box <= 0) $isi_per_box = 30;
                        
                        // Cek apakah barang sudah ada
                        $check = $conn->query("SELECT id FROM barang WHERE kode = '$kode'");
                        
                        if ($check->num_rows > 0) {
                            // Update
                            $conn->query("UPDATE barang SET 
                                nama = '$nama', 
                                isi_per_box = $isi_per_box,
                                updated_at = NOW() 
                                WHERE kode = '$kode'");
                            $updated++;
                        } else {
                            // Insert
                            $conn->query("INSERT INTO barang (kode, nama, isi_per_box) 
                                         VALUES ('$kode', '$nama', $isi_per_box)");
                            
                            // Buat stok awal
                            $conn->query("INSERT INTO stocks (kode_barang, total_box, total_pcs) 
                                         VALUES ('$kode', 0, 0)");
                            $imported++;
                        }
                        
                        $row++;
                    }
                    fclose($handle);
                    
                } else {
                    // Untuk XLS/XLSX, tampilkan petunjuk
                    $error = 'Untuk file Excel (.xls/.xlsx), gunakan format CSV saja. 
                             Silakan save as CSV dari Excel Anda.';
                }
                
                $conn->close();
                
                if (empty($error)) {
                    $success = "
                        <div class='upload-success'>
                            <h4><i class='fas fa-check-circle'></i> Upload Berhasil!</h4>
                            <p><strong>File:</strong> {$file['name']}</p>
                            <p><strong>Barang baru:</strong> $imported</p>
                            <p><strong>Barang diupdate:</strong> $updated</p>
                            " . (count($errors) > 0 ? "
                            <p><strong>Error:</strong> " . count($errors) . "</p>
                            <details>
                                <summary>Lihat detail error</summary>
                                <ul>
                                    <li>" . implode('</li><li>', $errors) . "</li>
                                </ul>
                            </details>" : "") . "
                        </div>
                    ";
                }
            } else {
                $error = 'Gagal mengupload file';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Excel Barang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #667eea;
        }
        
        .card-header i {
            font-size: 2rem;
            color: #667eea;
        }
        
        .card-header h2 {
            color: #2d3748;
            font-size: 1.8rem;
        }
        
        .upload-area {
            border: 3px dashed #cbd5e0;
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 2rem;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background: #f0f5ff;
        }
        
        .upload-area i {
            font-size: 4rem;
            color: #a0aec0;
            margin-bottom: 1rem;
        }
        
        .upload-area h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .upload-area p {
            color: #718096;
        }
        
        .file-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #ebf8ff;
            border-radius: 10px;
            color: #2c5282;
            font-weight: 500;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #718096;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
        }
        
        .hidden {
            display: none;
        }
        
        .upload-success {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .upload-success h4 {
            color: #22543d;
            margin-bottom: 0.5rem;
        }
        
        .upload-success p {
            color: #2f855a;
            margin: 0.25rem 0;
        }
        
        .alert-error {
            background: #fff5f5;
            border-left: 4px solid #fc8181;
            padding: 1rem;
            border-radius: 10px;
            color: #c53030;
            margin-bottom: 2rem;
        }
        
        .format-guide {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .format-guide h4 {
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .format-guide pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 10px;
            overflow-x: auto;
            font-family: monospace;
        }
        
        .format-note {
            margin-top: 1rem;
            color: #718096;
            font-size: 0.9rem;
        }
        
        .format-note i {
            color: #48bb78;
            margin-right: 0.5rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        @media (max-width: 640px) {
            .card {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-excel"></i>
                <h2>Upload Data Barang</h2>
            </div>
            
            <?php if ($success): ?>
                <?php echo $success; ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="upload-area" id="dropArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Upload File Excel/CSV</h3>
                <p>Klik atau drag & drop file di sini</p>
                <p><small>Format: Kode, Nama Barang, Isi per Box</small></p>
                <div id="fileInfo" class="file-info hidden">
                    <i class="fas fa-file"></i> <span id="fileName"></span>
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <input type="file" id="fileInput" name="excel_file" 
                       accept=".csv,.xls,.xlsx" class="hidden" required>
                
                <div class="action-buttons">
                    <button type="button" onclick="document.getElementById('fileInput').click()" 
                            class="btn btn-secondary">
                        <i class="fas fa-folder-open"></i> Pilih File
                    </button>
                    
                    <button type="submit" id="submitBtn" class="btn btn-primary hidden">
                        <i class="fas fa-upload"></i> Upload & Proses
                    </button>
                </div>
            </form>
            
            <div class="format-guide">
                <h4><i class="fas fa-info-circle"></i> Format File yang Benar</h4>
                
                <p><strong>Format CSV (paling mudah):</strong></p>
                <pre>
BRG001,Pulpen Standard,12
BRG002,Buku Tulis A4,24
BRG003,Pensil 2B,50
BRG004,Penghapus,100</pre>
                
                <p><strong>Keterangan:</strong></p>
                <ol style="margin-left: 1.5rem; color: #4a5568;">
                    <li><strong>Kode Barang</strong> - Harus unik</li>
                    <li><strong>Nama Barang</strong> - Nama lengkap barang</li>
                    <li><strong>Isi per Box</strong> - Jumlah pcs dalam 1 box (angka)</li>
                </ol>
                
                <div class="format-note">
                    <i class="fas fa-lightbulb"></i> Tips: Save file Excel Anda sebagai CSV (Comma delimited) untuk hasil terbaik
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="?page=barang" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Data Barang
                </a>
            </div>
        </div>
    </div>
    
    <script>
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');
        
        // Handle klik area upload
        dropArea.addEventListener('click', () => fileInput.click());
        
        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                
                // Validasi ekstensi
                const ext = file.name.split('.').pop().toLowerCase();
                if (!['csv', 'xls', 'xlsx'].includes(ext)) {
                    alert('Hanya file Excel/CSV yang diperbolehkan!');
                    this.value = '';
                    fileInfo.classList.add('hidden');
                    submitBtn.classList.add('hidden');
                    return;
                }
                
                // Validasi ukuran (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File terlalu besar! Maksimal 2MB');
                    this.value = '';
                    fileInfo.classList.add('hidden');
                    submitBtn.classList.add('hidden');
                    return;
                }
                
                // Tampilkan info file
                fileName.textContent = file.name + ' (' + formatBytes(file.size) + ')';
                fileInfo.classList.remove('hidden');
                submitBtn.classList.remove('hidden');
            }
        });
        
        // Drag & Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.style.borderColor = '#667eea';
                dropArea.style.background = '#f0f5ff';
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => {
                dropArea.style.borderColor = '#cbd5e0';
                dropArea.style.background = '#f8fafc';
            }, false);
        });
        
        dropArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        }, false);
        
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Silakan pilih file terlebih dahulu!');
                return;
            }
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>