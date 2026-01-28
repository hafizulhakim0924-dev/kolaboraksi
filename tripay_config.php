<?php
// Tripay Configuration
define('TRIPAY_API_KEY', 'Hfdqxnb7S2wPkU9AwghJkBoP7BwUmeZ5emhGC0rQ');
define('TRIPAY_PRIVATE_KEY', 'peyOY-QK9Bw-dTcOF-ISsZV-kHZvx');
define('TRIPAY_MERCHANT_CODE', 'T47104');
define('TRIPAY_MODE', 'production'); // Ubah ke 'production' saat live

// API Endpoints
if (TRIPAY_MODE === 'production') {
    define('TRIPAY_API_URL', 'https://tripay.co.id/api');
} else {
    define('TRIPAY_API_URL', 'https://tripay.co.id/api-sandbox');
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'rank3598_apk');
define('DB_PASS', 'Hakim123!');
define('DB_NAME', 'rank3598_apk');

// Site URL - Sesuaikan dengan domain Anda
define('SITE_URL', 'https://kolaboraksi.app.rangkiangpedulinegeri.org'); // Domain production

// Callback URL untuk Tripay
define('CALLBACK_URL', SITE_URL . '/callback.php');

// Return URL setelah pembayaran
define('RETURN_URL', SITE_URL . '/payment-result.php');

// Helper function untuk koneksi database
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // For AJAX requests, return JSON error instead of die()
        if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        die('Connection failed: ' . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Helper function untuk format rupiah
function formatRupiah($number) {
    return "Rp " . number_format($number, 0, ',', '.');
}
?>