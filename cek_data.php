<?php
$conn = new mysqli('localhost', 'root', '', 'sistem_stok');
echo "<h3>🔄 Cek Database barang</h3>";

// Cek total data
$result = $conn->query("SELECT COUNT(*) as total FROM barang");
$row = $result->fetch_assoc();
echo "Total data di tabel barang: <strong>" . $row['total'] . "</strong><br><br>";

// Tampilkan semua data
$result = $conn->query("SELECT * FROM barang ORDER BY id DESC LIMIT 10");
if ($result->num_rows > 0) {
    echo "<h4>📋 10 Data Terbaru:</h4>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Stok</th><th>Harga</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['kode'] . "</td>";
        echo "<td>" . $row['nama'] . "</td>";
        echo "<td>" . $row['kategori'] . "</td>";
        echo "<td>" . $row['stok'] . "</td>";
        echo "<td>Rp " . number_format($row['harga']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Tabel barang KOSONG!<br>";
    echo "Upload CSV tidak menyimpan data ke database.";
}

$conn->close();
?>