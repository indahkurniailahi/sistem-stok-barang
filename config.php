<?php
// ============================================
// CONFIG.PHP - PERBAIKAN FINAL
// ============================================

// Start output buffering
if (!ob_get_level()) {
    ob_start();
}

// Pastikan session dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistem_stok');

// Create connection
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Check login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

// Redirect
function redirect($url) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (headers_sent()) {
        echo "<script>window.location.href = '$url';</script>";
        exit();
    } else {
        header("Location: $url");
        exit();
    }
}

// Check role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Flash message
function setFlash($message, $type = 'success') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Fungsi bantu untuk konversi
function toPcs($box, $pcs, $isi_per_box) {
    return ($box * $isi_per_box) + $pcs;
}

function toBoxPcs($total_pcs, $isi_per_box) {
    $box = floor($total_pcs / $isi_per_box);
    $pcs = $total_pcs % $isi_per_box;
    return ['box' => $box, 'pcs' => $pcs];
}
?>