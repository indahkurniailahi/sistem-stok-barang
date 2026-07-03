<?php
require_once 'config.php';

$conn = getConnection();

echo "<h3>🔍 CEK DATA BARANG</h3>";

// Cek total barang
$result = $conn->query("SELECT COUNT(*) as total FROM barang");
$total = $result->fetch_assoc()['total'];
echo "Total barang di database: <strong>$total</strong><br><br>";

// Tampilkan semua barang
$result = $conn->query("SELECT * FROM barang ORDER BY id DESC LIMIT 20");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Kode</th><th>Nama</th><th>Deskripsi</th><th>Isi per Box</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['kode'] . "</td>";
        echo "<td>" . $row['nama'] . "</td>";
        echo "<td>" . $row['deskripsi'] . "</td>";
        echo "<td>" . ($row['isi_per_box'] ?? '30') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Tabel barang KOSONG!";
}

$conn->close();
?>