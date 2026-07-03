<?php
require_once 'config.php';

$query = $_GET['q'] ?? '';
$results = [];

if (!empty($query)) {
    $conn = getConnection();
    $search_term = "%$query%";
    
    // Debug log
    error_log("Search query: $query");
    
    $stmt = $conn->prepare("
        SELECT kode, nama 
        FROM barang 
        WHERE kode LIKE ? OR nama LIKE ?
        ORDER BY 
            CASE WHEN kode LIKE ? THEN 0 ELSE 1 END,
            nama
        LIMIT 10
    ");
    
    if ($stmt) {
        $stmt->bind_param("sss", $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        $stmt->close();
    } else {
        error_log("Prepare failed: " . $conn->error);
    }
    
    $conn->close();
}

// Set header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode($results);
exit();
?>