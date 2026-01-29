<?php
// Disable error display for AJAX requests to prevent HTML in JSON response
if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
error_reporting(E_ALL);
ini_set('display_errors', 1);
}
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once 'tripay_config.php';

// AJAX: Get Payment Channels
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_channels') {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_URL => TRIPAY_API_URL . '/merchant/payment-channel',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . TRIPAY_API_KEY],
        CURLOPT_FAILONERROR => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT => 30
    ]);
        
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
    curl_close($curl);
        
        if ($response === false || !empty($curl_error)) {
            echo json_encode(['success' => false, 'message' => 'Gagal memuat metode pembayaran', 'error' => $curl_error]);
        } else {
            echo $response;
        }
    } catch (Exception $e) {
        error_log("Error in get_channels: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal memuat metode pembayaran']);
    }
    exit;
}

// AJAX: Check Payment Status
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_status') {
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT status FROM donations WHERE tripay_reference = ?");
    if ($stmt) {
        $stmt->bind_param("s", $_GET['reference']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode($result ? ['success' => true, 'status' => $result['status']] : ['success' => false]);
    } else {
        error_log("Error preparing check_status query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Gagal memeriksa status pembayaran']);
    }
    $conn->close();
    } catch (Exception $e) {
        error_log("Error in check_status: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal memeriksa status pembayaran']);
    }
    exit;
}

// AJAX: Process Donation
if (isset($_POST['ajax']) && $_POST['ajax'] === 'process_donation') {
    // Clear any previous output to prevent HTML in JSON
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    // Start output buffering to catch any errors
    ob_start();
    
    try {
    $amount = intval($_POST['amount']);
    if ($amount < 10000) {
        echo json_encode(['success' => false, 'message' => 'Minimal donasi Rp 10.000']);
            $output = ob_get_clean();
            echo $output;
        exit;
    }

    $phone = $_POST['donor_phone'];
    if (substr($phone, 0, 1) === '0') $phone = '62' . substr($phone, 1);
    elseif (substr($phone, 0, 2) !== '62') $phone = '62' . $phone;

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM campaigns WHERE id = ?");
    if (!$stmt) {
        error_log("Error preparing campaign query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Gagal memuat data kampanye']);
        $output = ob_get_clean();
        echo $output;
        exit;
    }
    $stmt->bind_param("i", $_POST['campaign_id']);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$campaign) {
        echo json_encode(['success' => false, 'message' => 'Kampanye tidak ditemukan']);
            $output = ob_get_clean();
            echo $output;
        exit;
    }

    $merchant_ref = 'DN' . time() . rand(1000, 9999);
        
        // Calculate signature correctly
        $signature_string = TRIPAY_MERCHANT_CODE . $merchant_ref . $amount;
        $signature = hash_hmac('sha256', $signature_string, TRIPAY_PRIVATE_KEY);
        
    $data = [
        'method' => $_POST['payment_method'],
        'merchant_ref' => $merchant_ref,
        'amount' => $amount,
        'customer_name' => trim($_POST['donor_name']),
        'customer_email' => trim($_POST['donor_email']),
        'customer_phone' => $phone,
        'order_items' => [['name' => 'Donasi: ' . substr($campaign['title'], 0, 50), 'price' => $amount, 'quantity' => 1]],
        'return_url' => SITE_URL . '/kampanye-detail.php?id=' . $_POST['campaign_id'],
            'callback_url' => CALLBACK_URL,
            'expired_time' => (int)(time() + 86400),
            'signature' => $signature
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_URL => TRIPAY_API_URL . '/transaction/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . TRIPAY_API_KEY, 'Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
    ]);
        
        $curl_response = curl_exec($curl);
        $curl_error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

        // Handle curl errors
        if ($curl_response === false || !empty($curl_error)) {
            $conn->close();
            error_log("Tripay CURL Error: " . $curl_error);
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal terhubung ke server pembayaran. Silakan coba lagi atau gunakan metode pembayaran lain.',
                'error' => $curl_error
            ]);
            $output = ob_get_clean();
            echo $output;
            exit;
        }

        // Handle HTTP errors
        if ($http_code !== 200) {
            $conn->close();
            error_log("Tripay HTTP Error: Code " . $http_code . " - Response: " . substr($curl_response, 0, 500));
            echo json_encode([
                'success' => false, 
                'message' => 'Server pembayaran mengembalikan error (HTTP ' . $http_code . '). Silakan coba lagi.',
                'http_code' => $http_code,
                'response' => substr($curl_response, 0, 200)
            ]);
            $output = ob_get_clean();
            echo $output;
            exit;
        }

        // Decode JSON response
        $response = json_decode($curl_response, true);
        
        // Handle JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $conn->close();
            error_log("Tripay JSON Error: " . json_last_error_msg() . " - Response: " . substr($curl_response, 0, 500));
            echo json_encode([
                'success' => false, 
                'message' => 'Respon dari server tidak valid. Silakan coba lagi.',
                'json_error' => json_last_error_msg()
            ]);
            $output = ob_get_clean();
            echo $output;
            exit;
        }

        // Validate response structure
        if (!$response || !is_array($response)) {
            $conn->close();
            error_log("Tripay Invalid Response: " . substr($curl_response, 0, 500));
            echo json_encode([
                'success' => false, 
                'message' => 'Respon dari server tidak valid. Silakan coba lagi.'
            ]);
            $output = ob_get_clean();
            echo $output;
            exit;
        }
    
        // Log response for debugging (remove sensitive data)
        if (isset($response['success']) && !$response['success']) {
            error_log("Tripay API Error: " . json_encode($response));
        }

        // Check if transaction creation was successful
        if (isset($response['success']) && $response['success'] === true && isset($response['data'])) {
        $tx = $response['data'];
            
            // Validate required transaction data
            if (!isset($tx['reference']) || !isset($tx['amount']) || !isset($tx['payment_name'])) {
                $conn->close();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Data transaksi tidak lengkap. Silakan coba lagi.'
                ]);
                $output = ob_get_clean();
                echo $output;
                exit;
            }
            
        $qr_url = $tx['qr_url'] ?? '';
            $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1' ? 1 : 0;
            $message_raw = isset($_POST['message']) ? $_POST['message'] : '';
            $message = !empty($message_raw) ? mysqli_real_escape_string($conn, $message_raw) : '';
            
            // Prepare SQL statement
            // Kolom: campaign_id, donor_name, donor_email, donor_phone, amount, fee_total, total_amount, payment_method, payment_channel, tripay_reference, tripay_merchant_ref, status, payment_url, qr_url, is_anonymous, message, expired_at
            // Total: 17 kolom, tapi status hardcoded 'UNPAID', jadi 16 placeholder
            $stmt = $conn->prepare("INSERT INTO donations (campaign_id, donor_name, donor_email, donor_phone, amount, fee_total, total_amount, payment_method, payment_channel, tripay_reference, tripay_merchant_ref, status, payment_url, qr_url, is_anonymous, message, expired_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UNPAID', ?, ?, ?, ?, FROM_UNIXTIME(?))");
        
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            
            $total_fee = isset($tx['total_fee']) ? intval($tx['total_fee']) : 0;
            $total_amount = isset($tx['amount']) ? intval($tx['amount']) : $amount;
            $checkout_url = $tx['checkout_url'] ?? '';
            $payment_name = $tx['payment_name'] ?? $_POST['payment_method'];
            $expired_time = isset($tx['expired_time']) ? intval($tx['expired_time']) : (time() + 86400);
            
            // Bind 16 parameters: i, s, s, s, i, i, i, s, s, s, s, s, s, s, i, s, i
        $stmt->bind_param(
 "isssiiissssssisi",
                $_POST['campaign_id'],     // 1. i - campaign_id
                $_POST['donor_name'],      // 2. s - donor_name
                $_POST['donor_email'],     // 3. s - donor_email
                $phone,                    // 4. s - donor_phone
                $amount,                   // 5. i - amount
                $total_fee,                // 6. i - fee_total
                $total_amount,             // 7. i - total_amount
                $_POST['payment_method'],  // 8. s - payment_method
                $payment_name,            // 9. s - payment_channel
                $tx['reference'],         // 10. s - tripay_reference
                $merchant_ref,             // 11. s - tripay_merchant_ref
                $checkout_url,             // 12. s - payment_url
                $qr_url,                   // 13. s - qr_url
                $is_anonymous,             // 14. i - is_anonymous
                $message,                  // 15. s - message
                $expired_time              // 16. i - expired_at (untuk FROM_UNIXTIME)
);

            if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        echo json_encode(['success' => true, 'data' => [
            'reference' => $tx['reference'],
            'payment_name' => $tx['payment_name'],
            'pay_code' => $tx['pay_code'] ?? null,
            'qr_url' => $tx['qr_url'] ?? null,
            'amount' => $tx['amount'],
                    'fee' => $total_fee,
                    'expired_time' => $expired_time,
            'instructions' => $tx['instructions'] ?? []
        ]]);
    } else {
                $stmt->close();
                $conn->close();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Gagal menyimpan data transaksi. Silakan coba lagi.',
                    'db_error' => $stmt->error
                ]);
            }
        } else {
            $conn->close();
            // Get error message from response
            $error_message = 'Gagal membuat transaksi';
            
            // Try to get error message from various possible locations
            if (isset($response['message'])) {
                $error_message = $response['message'];
            } elseif (isset($response['data']['message'])) {
                $error_message = $response['data']['message'];
            } elseif (isset($response['data']) && is_string($response['data'])) {
                $error_message = $response['data'];
            } elseif (isset($response['errors']) && is_array($response['errors'])) {
                // Handle validation errors
                $error_messages = [];
                foreach ($response['errors'] as $key => $value) {
                    if (is_array($value)) {
                        $error_messages[] = implode(', ', $value);
                    } else {
                        $error_messages[] = $value;
                    }
                }
                $error_message = implode('. ', $error_messages);
            }
            
            // Log full response for debugging
            error_log("Tripay Transaction Failed: " . json_encode($response));
            error_log("Request Data: " . json_encode($data));
            
            // Return error with more details (but hide sensitive info in production)
            $debug_info = [];
            if (isset($response['errors'])) {
                $debug_info['errors'] = $response['errors'];
            }
            
            echo json_encode([
                'success' => false, 
                'message' => $error_message ?: 'Gagal membuat transaksi. Silakan coba lagi atau hubungi admin.',
                'debug' => $debug_info
            ]);
        }
    
    } catch (mysqli_sql_exception $e) {
        // Catch database errors
        error_log("Database Error in process_donation: " . $e->getMessage() . " | Code: " . $e->getCode());
        if (isset($conn)) {
            $conn->close();
        }
        $error_msg = 'Terjadi kesalahan database. ';
        if (strpos($e->getMessage(), 'donations') !== false) {
            $error_msg .= 'Pastikan table donations sudah dibuat. ';
        }
        $error_msg .= 'Silakan coba lagi atau hubungi admin.';
        echo json_encode([
            'success' => false,
            'message' => $error_msg,
            'error' => $e->getMessage(),
            'error_type' => 'database'
        ]);
    } catch (Exception $e) {
        // Catch any unexpected errors
        error_log("Unexpected Error in process_donation: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . " | Trace: " . $e->getTraceAsString());
        if (isset($conn)) {
            $conn->close();
        }
        // Show more detailed error for debugging (you can hide this in production)
        $error_detail = $e->getMessage();
        if (strpos($error_detail, 'Call to undefined') !== false) {
            $error_detail = 'Fungsi tidak ditemukan: ' . $error_detail;
        } elseif (strpos($error_detail, 'Undefined variable') !== false) {
            $error_detail = 'Variabel tidak terdefinisi: ' . $error_detail;
        } elseif (strpos($error_detail, 'mysqli') !== false) {
            $error_detail = 'Database error: ' . $error_detail;
        }
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem: ' . $error_detail,
            'error' => $e->getMessage(),
            'error_type' => 'exception',
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);
    } catch (Error $e) {
        // Catch fatal errors
        error_log("Fatal Error in process_donation: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . " | Trace: " . $e->getTraceAsString());
        if (isset($conn)) {
            $conn->close();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan fatal: ' . $e->getMessage(),
            'error' => $e->getMessage(),
            'error_type' => 'fatal',
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);
    }
    
    // Clean any output buffer and send JSON
    $output = ob_get_clean();
    if (!empty($output) && json_decode($output) === null) {
        // If output is not JSON, it means there was an error (HTML output)
        error_log("Non-JSON output detected in process_donation: " . substr($output, 0, 500));
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.',
            'error' => 'Invalid response from server'
        ]);
    } else {
        echo $output;
    }
    exit;
}

// Load Campaign Data
$campaign_id = intval($_GET['id'] ?? 0);
if (!$campaign_id) { header('Location: index.php'); exit; }

$conn = getDBConnection();
$campaign_result = $conn->query("SELECT * FROM campaigns WHERE id = $campaign_id");
if (!$campaign_result) {
    error_log("Error querying campaign: " . $conn->error);
    header('Location: index.php');
    exit;
}
$campaign = $campaign_result->fetch_assoc();
if (!$campaign) { 
    header('Location: index.php'); 
    exit; 
}

// Pastikan tabel campaign_media ada
$create_media_table = "CREATE TABLE IF NOT EXISTS `campaign_media` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `campaign_id` int(11) NOT NULL,
    `media_type` enum('image','video') NOT NULL,
    `media_path` varchar(255) NOT NULL,
    `media_url` varchar(255) DEFAULT NULL,
    `display_order` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `campaign_id` (`campaign_id`),
    KEY `display_order` (`display_order`),
    CONSTRAINT `campaign_media_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$conn->query($create_media_table);

// Get campaign media (images and videos)
$campaign_media = [];
$media_stmt = $conn->prepare("SELECT * FROM campaign_media WHERE campaign_id = ? ORDER BY display_order ASC, id ASC");
if ($media_stmt) {
    $media_stmt->bind_param("i", $campaign_id);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();
    if ($media_result) {
        $campaign_media = $media_result->fetch_all(MYSQLI_ASSOC);
    }
    $media_stmt->close();
} else {
    error_log("Error preparing campaign_media query: " . $conn->error);
}

// Get recent donations
$recent_donations = [];
$stmt = $conn->prepare("SELECT donor_name, amount, message, is_anonymous, created_at FROM donations WHERE campaign_id = ? AND status = 'PAID' ORDER BY created_at DESC LIMIT 10");
if ($stmt) {
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $donations_result = $stmt->get_result();
    if ($donations_result) {
        $recent_donations = $donations_result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    error_log("Error preparing donations query: " . $conn->error);
}

// Get related campaigns from same organizer
$related_campaigns = [];
$stmt = $conn->prepare("SELECT id, title, emoji, image, target_terkumpul, donasi_terkumpul, organizer FROM campaigns WHERE organizer = ? AND id != ? ORDER BY created_at DESC LIMIT 3");
if ($stmt) {
    $stmt->bind_param("si", $campaign['organizer'], $campaign_id);
    $stmt->execute();
    $related_result = $stmt->get_result();
    if ($related_result) {
        $related_campaigns = $related_result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    error_log("Error preparing related campaigns query: " . $conn->error);
}
$conn->close();

$target = intval($campaign['target_terkumpul']);
$terkumpul = intval($campaign['donasi_terkumpul']);
$progress = $target > 0 ? min(100, round(($terkumpul / $target) * 100, 1)) : 0;

if (!function_exists('formatRupiah')) {
    function formatRupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
}

if (!function_exists('timeAgo')) {
    function timeAgo($dt) {
        $diff = time() - strtotime($dt);
        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return floor($diff/60) . ' menit lalu';
        if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
        if ($diff < 2592000) return floor($diff/86400) . ' hari lalu';
        return date('d M Y', strtotime($dt));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($campaign['title']) ?> | KolaborAksi</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:Poppins,sans-serif;background:#F8FAFB;color:#1a1a1a;line-height:1.6}
.container{max-width:900px;margin:0 auto;background:#fff;min-height:100vh}
.header{background:#fff;padding:20px 24px;border-bottom:1px solid #E8EBED;position:sticky;top:0;z-index:50;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.header-content{display:flex;align-items:center;gap:16px}
.back-btn{background:#F5F7F9;border:none;font-size:18px;color:#17a697;padding:10px;cursor:pointer;border-radius:10px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.back-btn:hover{background:#E8F5F2;transform:translateX(-2px)}
.header-title{font-size:17px;font-weight:600;flex:1}
.content{padding:24px 24px 100px;max-width:800px;margin:0 auto;width:100%}
.campaign-image-wrapper{width:100%;margin-bottom:32px;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);aspect-ratio:16/9;position:relative}
.campaign-image,.campaign-image-emoji{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.campaign-image{object-fit:cover}
.campaign-image-emoji{font-size:120px;background:linear-gradient(135deg,#667eea,#764ba2)}
.media-slider{width:100%;position:relative;border-radius:12px;overflow:hidden;aspect-ratio:16/9;background:#000}
.media-slide{display:none;width:100%;height:100%;object-fit:contain;background:#000}
.media-slide.active{display:block}
.media-slide img,.media-slide video{width:100%;height:100%;object-fit:contain}
.slider-controls{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:10}
.slider-btn{background:rgba(255,255,255,.8);border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;color:#17a697;transition:all .2s;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.slider-btn:hover{background:#fff;transform:scale(1.1)}
.slider-dots{position:absolute;bottom:60px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:10}
.slider-dot{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.5);cursor:pointer;transition:all .2s}
.slider-dot.active{background:#17a697;width:24px;border-radius:5px}
.media-counter{position:absolute;top:16px;right:16px;background:rgba(0,0,0,.6);color:#fff;padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;z-index:10}
.card{background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.05);margin-bottom:20px;border:1px solid #E8EBED}
.campaign-title{font-size:28px;font-weight:700;margin-bottom:24px;line-height:1.3}
.organizer{display:flex;align-items:center;gap:14px;margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid #F0F2F4}
.organizer-avatar{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#17a697,#139989);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:18px}
.organizer-info{flex:1}
.organizer-name{font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:6px;font-size:15px}
.organizer-badge{color:#17a697;font-size:14px}
.organizer-label{font-size:12px;color:#6B7280}
.progress-label{display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:12px}
.progress-percentage{color:#17a697;font-weight:700;font-size:14px}
.progress-bar{width:100%;height:10px;background:#F0F2F4;border-radius:10px;overflow:hidden;margin-bottom:20px}
.progress-fill{height:100%;background:linear-gradient(90deg,#17a697,#1bc9b5);width:0;border-radius:10px;transition:width 1.5s cubic-bezier(.4,0,.2,1);box-shadow:0 2px 8px rgba(23,166,151,.3)}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.stat{background:#F8FAFB;padding:20px 16px;border-radius:12px;text-align:center;border:1px solid #E8EBED;transition:all .2s}
.stat:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.stat.primary{background:linear-gradient(135deg,#E8F5F2,#D4EDE8);border-color:#B8E6DB}
.stat-label{font-size:11px;color:#6B7280;margin-bottom:8px;font-weight:500;text-transform:uppercase}
.stat-value{font-size:15px;font-weight:700;word-break:break-word}
.stat.primary .stat-value{color:#17a697}
.stat-icon{font-size:24px;margin-bottom:8px;display:block}
.section-title{font-size:17px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.section-title i{color:#17a697;font-size:20px}
.section-content{font-size:15px;line-height:1.7;color:#4B5563}
.share-buttons{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:20px}
.share-btn{padding:14px;border:1.5px solid #E8EBED;background:#fff;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;color:#4B5563;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.share-btn:hover{background:#F8FAFB;border-color:#17a697;color:#17a697;transform:translateY(-2px)}
.donate-button{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:900px;background:#fff;padding:16px 24px;border-top:1px solid #E8EBED;z-index:200;box-shadow:0 -4px 16px rgba(0,0,0,.06)}
.donate-btn{width:100%;background:linear-gradient(135deg,#17a697,#139989);color:#fff;border:none;padding:16px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 12px rgba(23,166,151,.25)}
.donate-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(23,166,151,.35)}
.modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background:rgba(0,0,0,.6)}
.modal.show{display:flex;align-items:center;justify-content:center}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-content{background:#fff;margin:20px;border-radius:16px;max-width:560px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-header{background:linear-gradient(135deg,#17a697,#139989);color:#fff;padding:24px 28px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center}
.modal-title{font-size:19px;font-weight:700}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:20px;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:10px;transition:all .2s}
.modal-close:hover{background:rgba(255,255,255,.25)}
.modal-body{padding:28px}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:10px}
.form-group label .required{color:#EF4444}
.form-group input,.form-group textarea{width:100%;padding:14px 16px;border:1.5px solid #E8EBED;border-radius:10px;font-size:14px;font-family:Poppins,sans-serif;transition:all .2s;background:#F8FAFB}
.form-group input:focus,.form-group textarea:focus{outline:none;border-color:#17a697;background:#fff;box-shadow:0 0 0 4px rgba(23,166,151,.08)}
.form-group textarea{resize:vertical;min-height:90px}
.quick-amount{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
.quick-amount-btn{padding:12px;border:1.5px solid #E8EBED;background:#fff;border-radius:10px;font-size:13px;font-weight:600;color:#4B5563;cursor:pointer;transition:all .2s}
.quick-amount-btn:hover{border-color:#17a697;background:#F0FAF8;color:#17a697}
.quick-amount-btn.active{background:#17a697;border-color:#17a697;color:#fff}
.checkbox-group{display:flex;align-items:center;gap:10px;margin-top:12px;padding:14px;background:#F8FAFB;border-radius:10px}
.checkbox-group input[type=checkbox]{width:20px;height:20px;accent-color:#17a697;cursor:pointer}
.checkbox-group label{margin:0!important;font-size:13px;font-weight:500;color:#4B5563;cursor:pointer}
.payment-methods{display:grid;gap:10px;margin-top:12px;max-height:320px;overflow-y:auto;padding-right:6px}
.payment-methods::-webkit-scrollbar{width:5px}
.payment-methods::-webkit-scrollbar-track{background:#F0F2F4;border-radius:10px}
.payment-methods::-webkit-scrollbar-thumb{background:#17a697;border-radius:10px}
.payment-group-title{font-size:11px;font-weight:700;color:#9CA3AF;margin:16px 0 10px;text-transform:uppercase}
.payment-method{border:1.5px solid #E8EBED;border-radius:10px;padding:14px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:12px;background:#fff}
.payment-method:hover{border-color:#17a697;background:#F8FAFB}
.payment-method.active{border-color:#17a697;background:#F0FAF8;box-shadow:0 2px 8px rgba(23,166,151,.15)}
.payment-method input[type=radio]{width:20px;height:20px;accent-color:#17a697}
.payment-method-info{flex:1}
.payment-method-name{font-size:14px;font-weight:600;margin-bottom:4px}
.payment-method-fee{font-size:11px;color:#6B7280}
.payment-method-logo{width:56px;height:28px;object-fit:contain}
.summary-box{background:#F8FAFB;padding:20px;border-radius:12px;margin:24px 0;border:1.5px solid #E8EBED}
.summary-row{display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;color:#4B5563}
.summary-row.total{padding-top:12px;border-top:1.5px solid #E8EBED;font-weight:700;font-size:17px;color:#17a697;margin-top:12px}
.submit-btn{width:100%;background:linear-gradient(135deg,#17a697,#139989);color:#fff;border:none;padding:16px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;transition:all .2s;box-shadow:0 4px 12px rgba(23,166,151,.25)}
.submit-btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 6px 20px rgba(23,166,151,.35)}
.submit-btn:disabled{background:#D1D5DB;cursor:not-allowed;box-shadow:none}
.donations-list{margin-top:20px}
.donation-item{display:flex;align-items:flex-start;gap:14px;padding:16px;background:#F8FAFB;border-radius:12px;margin-bottom:12px;border:1px solid #E8EBED;transition:all .2s}
.donation-item:hover{transform:translateX(4px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
.donation-avatar{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#17a697,#1bc9b5);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;box-shadow:0 2px 8px rgba(23,166,151,.2)}
.donation-info{flex:1;min-width:0}
.donation-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:6px}
.donation-name{font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px}
.donation-amount{font-weight:700;color:#17a697;font-size:15px;white-space:nowrap}
.donation-message{font-size:13px;color:#6B7280;line-height:1.5;margin-top:6px;font-style:italic}
.donation-time{font-size:11px;color:#9CA3AF;margin-top:4px;display:flex;align-items:center;gap:4px}
.empty-donations{text-align:center;padding:40px 20px;color:#9CA3AF}
.empty-donations i{font-size:48px;margin-bottom:16px;opacity:.5}
.payment-code-box{background:#F8FAFB;padding:20px;border-radius:12px;margin:20px 0;border:1.5px solid #E8EBED;text-align:center}
.payment-code-label{font-size:13px;color:#6B7280;margin-bottom:10px;font-weight:500}
.payment-code-value{font-size:24px;font-weight:700;color:#17a697;letter-spacing:2px;margin-bottom:12px}
.copy-code-btn{padding:10px 24px;background:#17a697;color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.copy-code-btn:hover{background:#139989;transform:translateY(-1px)}
.qr-code-box{margin:20px 0;text-align:center}
.qr-code-box img{max-width:250px;border:1.5px solid #E8EBED;border-radius:12px;padding:15px}
.countdown-box{background:#FEF3C7;padding:16px;border-radius:12px;margin-bottom:20px;border:1.5px solid #FDE68A;text-align:center}
.countdown-label{font-size:12px;color:#92400E;margin-bottom:8px;font-weight:500}
.countdown-timer{font-size:24px;font-weight:700;color:#92400E}
.instructions-box{text-align:left;margin-top:20px;padding:16px;background:#F8FAFB;border-radius:12px;border:1.5px solid #E8EBED}
.instructions-title{font-size:15px;font-weight:700;margin-bottom:12px}
.instructions-box ol{list-style:none;counter-reset:step;padding:0}
.instructions-box li{counter-increment:step;padding:10px 10px 10px 40px;margin-bottom:8px;background:#fff;border-radius:8px;font-size:12px;line-height:1.5;position:relative}
.instructions-box li::before{content:counter(step);position:absolute;left:10px;top:10px;width:24px;height:24px;background:#17a697;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px}
.loading{text-align:center;padding:40px}
.spinner{border:4px solid #F0F2F4;border-top:4px solid #17a697;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:0 auto 16px}
@keyframes spin{to{transform:rotate(360deg)}}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px}
.alert-error{background:#FEE2E2;color:#991B1B;border:1.5px solid #FECACA}
.related-campaigns{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:20px}
.related-card{background:#fff;border-radius:16px;overflow:hidden;border:1.5px solid #E8EBED;transition:all .3s;cursor:pointer;text-decoration:none;color:inherit;display:block;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.related-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.12);border-color:#17a697}
.related-image-wrapper{width:100%;aspect-ratio:16/9;overflow:hidden;background:#F8FAFB}
.related-image,.related-emoji{width:100%;height:100%;object-fit:cover}
.related-emoji{display:flex;align-items:center;justify-content:center;font-size:64px;background:linear-gradient(135deg,#667eea,#764ba2)}
.related-content{padding:16px}
.related-title{font-size:14px;font-weight:600;margin-bottom:12px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:#1F1F1F}
.related-progress{height:6px;background:#F0F2F4;border-radius:10px;overflow:hidden;margin-bottom:12px}
.related-progress-fill{height:100%;background:linear-gradient(90deg,#17a697,#1bc9b5);border-radius:10px;transition:width .5s ease}
.related-stats{display:flex;justify-content:space-between;align-items:center;font-size:12px}
.related-amount{font-weight:700;color:#17a697;font-size:13px}
.related-organizer{color:#6B7280;display:flex;align-items:center;gap:4px;font-size:11px}
@media(max-width:768px){
.content{padding:20px 16px 100px}
.campaign-title{font-size:24px}
.card{padding:24px 20px}
.stat{padding:16px 12px}
.stat-value{font-size:13px}
.stats{gap:10px}
.modal-content{margin:12px;max-height:95vh}
.modal-body{padding:24px 20px}
.quick-amount{grid-template-columns:repeat(2,1fr)}
.share-buttons{gap:10px}
.share-btn{font-size:12px;padding:12px}
.donate-button{padding:14px 16px;max-width:100%}
.donation-item{padding:14px}
.donation-avatar{width:40px;height:40px;font-size:14px}
.related-campaigns{grid-template-columns:1fr}
.slider-btn{width:36px;height:36px;font-size:16px}
.slider-dots{bottom:50px}
.media-counter{top:12px;right:12px;padding:4px 10px;font-size:11px}
}
@media(max-width:480px){
.header{padding:16px 20px}
.campaign-title{font-size:22px}
.stat-value{font-size:12px}
.stats{gap:8px}
.stat{padding:12px 8px}
.share-buttons{grid-template-columns:1fr;gap:8px}
}
</style>
</head>
<body>
<div class="container">
<div class="header">
<div class="header-content">
<button class="back-btn" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
<h1 class="header-title">Detail Kampanye</h1>
</div>
</div>
<div class="content">
<?php if(count($campaign_media) > 0): ?>
<div class="media-slider" id="mediaSlider">
<?php foreach($campaign_media as $index => $media): 
    $media_url = !empty($media['media_url']) ? $media['media_url'] : $media['media_path'];
    $is_active = $index === 0 ? 'active' : '';
?>
<div class="media-slide <?= $is_active ?>" data-index="<?= $index ?>">
<?php if($media['media_type'] === 'video'): ?>
<video controls playsinline>
<source src="<?= htmlspecialchars($media_url) ?>" type="video/mp4">
Browser Anda tidak mendukung video.
</video>
<?php else: ?>
<img src="<?= htmlspecialchars($media_url) ?>" alt="<?= htmlspecialchars($campaign['title']) ?>">
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php if(count($campaign_media) > 1): ?>
<div class="media-counter"><span id="currentSlide">1</span> / <?= count($campaign_media) ?></div>
<div class="slider-dots" id="sliderDots"></div>
<div class="slider-controls">
<button class="slider-btn" onclick="previousSlide()"><i class="fas fa-chevron-left"></i></button>
<button class="slider-btn" onclick="nextSlide()"><i class="fas fa-chevron-right"></i></button>
</div>
<?php endif; ?>
</div>
<?php elseif(!empty($campaign['image'])): ?>
<div class="campaign-image-wrapper">
<img src="<?= htmlspecialchars($campaign['image']) ?>" alt="<?= htmlspecialchars($campaign['title']) ?>" class="campaign-image" onerror="this.parentElement.innerHTML='<div class=campaign-image-emoji><?= htmlspecialchars($campaign['emoji']) ?></div>'">
</div>
<?php else: ?>
<div class="campaign-image-wrapper">
<div class="campaign-image-emoji"><?= htmlspecialchars($campaign['emoji']) ?></div>
</div>
<?php endif; ?>

<div class="card">
<h1 class="campaign-title"><?= htmlspecialchars($campaign['title']) ?></h1>
<div class="organizer">
<div class="organizer-avatar"><?= strtoupper(substr($campaign['organizer'],0,1)) ?></div>
<div class="organizer-info">
<div class="organizer-name"><?= htmlspecialchars($campaign['organizer']) ?> <i class="fas fa-check-circle organizer-badge"></i></div>
<div class="organizer-label">Penyelenggara Kampanye</div>
</div>
</div>
<div class="progress-label"><span>Progres Pengumpulan Dana</span><span class="progress-percentage" id="progressText">0%</span></div>
<div class="progress-bar"><div class="progress-fill" id="progressFill" data-progress="<?= $progress ?>"></div></div>
<div class="stats">
<div class="stat primary"><span class="stat-icon">💰</span><div class="stat-label">Terkumpul</div><div class="stat-value"><?= formatRupiah($terkumpul) ?></div></div>
<div class="stat"><span class="stat-icon">📊</span><div class="stat-label">Tersisa</div><div class="stat-value"><?= formatRupiah(max(0,$target-$terkumpul)) ?></div></div>
<div class="stat"><span class="stat-icon">🎯</span><div class="stat-label">Target</div><div class="stat-value"><?= formatRupiah($target) ?></div></div>
</div>
</div>

<div class="card">
<div class="section-title"><i class="fas fa-info-circle"></i> Tentang Kampanye</div>
<div class="section-content"><p><?= htmlspecialchars($campaign['title']) ?> adalah kampanye penggalangan dana dari masyarakat untuk tujuan kebaikan bersama.</p></div>
</div>

<div class="card">
<div class="section-title"><i class="fas fa-users"></i> Donatur Terbaru</div>
<div class="donations-list">
<?php if(count($recent_donations)>0): foreach($recent_donations as $d): $name=$d['is_anonymous']?'Anonim':$d['donor_name']; ?>
<div class="donation-item">
<div class="donation-avatar"><?= strtoupper(substr($name,0,1)) ?></div>
<div class="donation-info">
<div class="donation-header">
<div class="donation-name"><?= $d['is_anonymous']?'Donatur Anonim':htmlspecialchars($d['donor_name']) ?><?php if(!$d['is_anonymous']): ?> <i class="fas fa-check-circle" style="color:#17a697;font-size:12px"></i><?php endif; ?></div>
<div class="donation-amount"><?= formatRupiah($d['amount']) ?></div>
</div>
<?php if(!empty($d['message'])): ?><div class="donation-message">"<?= htmlspecialchars($d['message']) ?>"</div><?php endif; ?>
<div class="donation-time"><i class="far fa-clock" style="font-size:10px"></i> <?= timeAgo($d['created_at']) ?></div>
</div>
</div>
<?php endforeach; else: ?>
<div class="empty-donations"><i class="fas fa-heart"></i><p>Belum ada donatur. Jadilah yang pertama!</p></div>
<?php endif; ?>
</div>
</div>
<?php if(count($related_campaigns) > 0): ?>
<div class="card">
<div class="section-title"><i class="fas fa-th-large"></i> Kampanye Lain dari <?= htmlspecialchars($campaign['organizer']) ?></div>
<div class="related-campaigns">
<?php foreach($related_campaigns as $rc): 
$rc_target = intval($rc['target_terkumpul']);
$rc_terkumpul = intval($rc['donasi_terkumpul']);
$rc_progress = $rc_target > 0 ? min(100, round(($rc_terkumpul / $rc_target) * 100, 1)) : 0;
?>
<a href="kampanye-detail.php?id=<?= $rc['id'] ?>" class="related-card">
<div class="related-image-wrapper">
<?php if(!empty($rc['image'])): ?>
<img src="<?= htmlspecialchars($rc['image']) ?>" alt="<?= htmlspecialchars($rc['title']) ?>" class="related-image" onerror="this.parentElement.innerHTML='<div class=related-emoji><?= htmlspecialchars($rc['emoji']) ?></div>'">
<?php else: ?>
<div class="related-emoji"><?= htmlspecialchars($rc['emoji']) ?></div>
<?php endif; ?>
</div>
<div class="related-content">
<div class="related-title"><?= htmlspecialchars($rc['title']) ?></div>
<div class="related-progress">
<div class="related-progress-fill" style="width:<?= $rc_progress ?>%"></div>
</div>
<div class="related-stats">
<div class="related-amount"><?= formatRupiah($rc_terkumpul) ?></div>
<div class="related-organizer"><i class="fas fa-user" style="font-size:10px"></i> <?= htmlspecialchars($rc['organizer']) ?></div>
</div>
</div>
</a>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<div class="share-buttons">
<button class="share-btn" onclick="shareWhatsApp()"><i class="fab fa-whatsapp"></i> WhatsApp</button>
<button class="share-btn" onclick="shareFacebook()"><i class="fab fa-facebook"></i> Facebook</button>
<button class="share-btn" onclick="copyLink()"><i class="fas fa-link"></i> Salin Link</button>
</div>
</div>

<div class="donate-button">
<button class="donate-btn" onclick="openDonateModal()"><i class="fas fa-heart"></i> Donasi Sekarang</button>
</div>
</div>

<div id="donateModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<div class="modal-title"><i class="fas fa-heart"></i> Form Donasi</div>
<button class="modal-close" onclick="closeDonateModal()"><i class="fas fa-times"></i></button>
</div>
<div class="modal-body">
<form id="donationForm">
<input type="hidden" name="campaign_id" value="<?= $campaign_id ?>">
<div class="form-group">
<label>Nama Lengkap <span class="required">*</span></label>
<input type="text" name="donor_name" required placeholder="Masukkan nama lengkap">
</div>
<div class="form-group">
<label>Email <span class="required">*</span></label>
<input type="email" name="donor_email" required placeholder="email@example.com">
</div>
<div class="form-group">
<label>Nomor WhatsApp <span class="required">*</span></label>
<input type="tel" name="donor_phone" id="donor_phone" required placeholder="08xxxxxxxxxx">
</div>
<div class="checkbox-group">
<input type="checkbox" name="is_anonymous" id="is_anonymous" value="1">
<label for="is_anonymous">Sembunyikan nama saya (Anonim)</label>
</div>
<div class="form-group" style="margin-top:20px">
<label>Jumlah Donasi (Rp) <span class="required">*</span></label>
<input type="number" name="amount" id="amount" required min="10000" placeholder="Minimal Rp 10.000" oninput="updateSummary()">
<div class="quick-amount">
<button type="button" class="quick-amount-btn" onclick="setAmount(20000)">Rp 20K</button>
<button type="button" class="quick-amount-btn" onclick="setAmount(50000)">Rp 50K</button>
<button type="button" class="quick-amount-btn" onclick="setAmount(100000)">Rp 100K</button>
<button type="button" class="quick-amount-btn" onclick="setAmount(200000)">Rp 200K</button>
<button type="button" class="quick-amount-btn" onclick="setAmount(500000)">Rp 500K</button>
<button type="button" class="quick-amount-btn" onclick="setAmount(1000000)">Rp 1Jt</button>
</div>
</div>
<div class="form-group">
<label>Pesan & Doa (opsional)</label>
<textarea name="message" placeholder="Semoga bermanfaat..."></textarea>
</div>
<div class="form-group">
<label>Metode Pembayaran <span class="required">*</span></label>
<div id="paymentChannelsContainer">
<div class="loading"><div class="spinner"></div><p>Memuat metode pembayaran...</p></div>
</div>
</div>
<div class="summary-box">
<div class="summary-row"><span>Nominal Donasi:</span><span id="summaryAmount">Rp 0</span></div>
<div class="summary-row"><span>Biaya Admin:</span><span id="summaryFee">Rp 0</span></div>
<div class="summary-row total"><span>Total Pembayaran:</span><span id="summaryTotal">Rp 0</span></div>
</div>
<button type="submit" class="submit-btn" id="submitBtn"><i class="fas fa-paper-plane"></i> Lanjutkan Pembayaran</button>
</form>
</div>
</div>
</div>

<div id="paymentModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<div class="modal-title"><i class="fas fa-credit-card"></i> Instruksi Pembayaran</div>
<button class="modal-close" onclick="closePaymentModal()"><i class="fas fa-times"></i></button>
</div>
<div class="modal-body payment-modal-body" id="paymentModalBody"></div>
</div>
</div>

<script>
const CAMPAIGN_ID=<?= $campaign_id ?>;
let paymentChannels=[],currentTransaction=null;

function openDonateModal(){document.getElementById('donateModal').classList.add('show');loadPaymentChannels()}
function closeDonateModal(){document.getElementById('donateModal').classList.remove('show')}
function closePaymentModal(){document.getElementById('paymentModal').classList.remove('show');if(currentTransaction)location.reload()}

function loadPaymentChannels(){
const c=document.getElementById('paymentChannelsContainer');
fetch('?ajax=get_channels')
.then(r=>r.json())
.then(data=>{
if(data.success&&data.data){paymentChannels=data.data;renderPaymentChannels(paymentChannels)}
else c.innerHTML='<div class="alert alert-error">Gagal memuat metode pembayaran</div>'
})
.catch(e=>c.innerHTML='<div class="alert alert-error">Terjadi kesalahan</div>')
}

function renderPaymentChannels(channels){
const c=document.getElementById('paymentChannelsContainer');
const grouped={};
channels.forEach(ch=>{if(ch.active){if(!grouped[ch.group])grouped[ch.group]=[];grouped[ch.group].push(ch)}});
let html='<div class="payment-methods">';
for(const[group,chs]of Object.entries(grouped)){
html+=`<div class="payment-group-title">${group}</div>`;
chs.forEach(ch=>{
let fee=ch.total_fee.flat>0?`Biaya: Rp ${ch.total_fee.flat.toLocaleString('id-ID')}`:(ch.total_fee.percent>0?`Biaya: ${ch.total_fee.percent}%`:'');
html+=`<label class="payment-method" data-code="${ch.code}">
<input type="radio" name="payment_method" value="${ch.code}" data-fee-flat="${ch.total_fee.flat}" data-fee-percent="${ch.total_fee.percent}" onchange="updateSummary()" required>
<div class="payment-method-info"><div class="payment-method-name">${ch.name}</div><div class="payment-method-fee">${fee}</div></div>
${ch.icon_url?`<img src="${ch.icon_url}" class="payment-method-logo" alt="${ch.name}">`:''}
</label>`
})
}
html+='</div>';
c.innerHTML=html;
document.querySelectorAll('.payment-method').forEach(m=>{
m.addEventListener('click',function(){
document.querySelectorAll('.payment-method').forEach(x=>x.classList.remove('active'));
this.classList.add('active');
this.querySelector('input[type="radio"]').checked=true;
updateSummary()
})
})
}

function setAmount(amt){
document.getElementById('amount').value=amt;
document.querySelectorAll('.quick-amount-btn').forEach(b=>b.classList.remove('active'));
event.target.classList.add('active');
updateSummary()
}

function updateSummary(){
const amt=parseInt(document.getElementById('amount').value)||0;
const sel=document.querySelector('input[name="payment_method"]:checked');
let fee=0;
if(sel){
const flat=parseInt(sel.dataset.feeFlat)||0;
const pct=parseFloat(sel.dataset.feePercent)||0;
fee=flat+Math.ceil(amt*(pct/100))
}
document.getElementById('summaryAmount').textContent=formatRupiah(amt);
document.getElementById('summaryFee').textContent=formatRupiah(fee);
document.getElementById('summaryTotal').textContent=formatRupiah(amt+fee)
}

function formatRupiah(n){return 'Rp '+n.toLocaleString('id-ID')}

document.getElementById('donationForm').addEventListener('submit',function(e){
e.preventDefault();
const amt=parseInt(document.getElementById('amount').value)||0;
if(amt<10000){alert('Minimal donasi adalah Rp 10.000');return}
if(!document.querySelector('input[name="payment_method"]:checked')){alert('Silakan pilih metode pembayaran');return}
const btn=document.getElementById('submitBtn');
btn.disabled=true;
btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Memproses...';
const fd=new FormData(this);
fd.append('ajax','process_donation');
fetch('',{method:'POST',body:fd})
.then(r=>{
if(!r.ok){
throw new Error('HTTP error! status: '+r.status);
}
return r.json();
})
.then(data=>{
if(data&&data.success){
currentTransaction=data.data;
closeDonateModal();
showPaymentModal(data.data);
}else{
let errorMsg='Gagal membuat transaksi.';
if(data&&data.message){
errorMsg=data.message;
if(data.error){
errorMsg+='\n\nDetail: '+data.error;
}
if(data.error_type){
errorMsg+='\n\nTipe Error: '+data.error_type;
}
if(data.db_error){
errorMsg+='\n\nDatabase Error: '+data.db_error;
}
}else if(data&&data.debug&&data.debug.errors){
errorMsg='Error validasi:\n'+JSON.stringify(data.debug.errors);
}else{
errorMsg='Gagal membuat transaksi. Silakan coba lagi.';
}
console.error('Payment Error:',data);
alert(errorMsg);
btn.disabled=false;
btn.innerHTML='<i class="fas fa-paper-plane"></i> Lanjutkan Pembayaran';
}
})
.catch(e=>{
console.error('Error:',e);
let errorMsg='Terjadi kesalahan saat memproses pembayaran.';
if(e.message){
errorMsg+='\n\nDetail: '+e.message;
}
if(e.response){
errorMsg+='\n\nResponse: '+JSON.stringify(e.response);
}
alert(errorMsg+'\n\nSilakan coba lagi atau gunakan metode pembayaran lain.');
btn.disabled=false;
btn.innerHTML='<i class="fas fa-paper-plane"></i> Lanjutkan Pembayaran';
})
});

function showPaymentModal(tx){
const body=document.getElementById('paymentModalBody');
let html=`<div class="countdown-box"><div class="countdown-label">Selesaikan Pembayaran Sebelum:</div><div class="countdown-timer" id="countdownTimer"></div></div>
<h3 style="font-size:18px;margin-bottom:16px">${tx.payment_name||'Pembayaran'}</h3>
<div style="font-size:28px;font-weight:700;color:#17a697;margin-bottom:20px">${formatRupiah(tx.amount)}</div>`;
if(tx.pay_code)html+=`<div class="payment-code-box"><div class="payment-code-label">Kode Pembayaran / No. VA</div><div class="payment-code-value" id="payCode">${tx.pay_code}</div><button class="copy-code-btn" onclick="copyPayCode()"><i class="fas fa-copy"></i> Salin Kode</button></div>`;
if(tx.qr_url&&tx.qr_url.trim()!=='')html+=`<div class="qr-code-box"><img src="${tx.qr_url}" alt="QR Code" onerror="this.parentElement.innerHTML='<p style=color:#EF4444;padding:20px>Gagal memuat QR Code. Silakan refresh halaman atau gunakan metode pembayaran lain.</p>'"><p style="font-size:12px;color:#666;margin-top:10px">Scan QR Code untuk pembayaran</p></div>`;
if(tx.instructions&&Array.isArray(tx.instructions)&&tx.instructions.length>0){
html+='<div class="instructions-box"><div class="instructions-title">Cara Pembayaran:</div><ol>';
tx.instructions.forEach(ins=>{
if(ins&&ins.steps&&Array.isArray(ins.steps)){
ins.steps.forEach(step=>html+=`<li>${step}</li>`);
}
});
html+='</ol></div>'
}
body.innerHTML=html;
document.getElementById('paymentModal').classList.add('show');
if(tx.expired_time)startCountdown(tx.expired_time);
if(tx.reference)startPaymentStatusCheck(tx.reference);
}

function copyPayCode(){
const code=document.getElementById('payCode').textContent;
navigator.clipboard.writeText(code).then(()=>alert('Kode pembayaran berhasil disalin!'))
}

function startCountdown(exp){
const el=document.getElementById('countdownTimer');
function update(){
const now=Math.floor(Date.now()/1000);
const dist=exp-now;
if(dist<0){el.textContent='Kadaluarsa';return}
const h=Math.floor(dist/3600);
const m=Math.floor((dist%3600)/60);
const s=dist%60;
el.textContent=String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
setTimeout(update,1000)
}
update()
}

function startPaymentStatusCheck(ref){
const interval=setInterval(()=>{
fetch(`?ajax=check_status&reference=${ref}`)
.then(r=>r.json())
.then(data=>{
if(data.success&&data.status==='PAID'){
clearInterval(interval);
alert('Pembayaran berhasil! Terima kasih atas donasi Anda.');
closePaymentModal()
}
})
.catch(e=>console.error('Error:',e))
},10000)
}

let currentSlideIndex=0;
const totalSlides=<?= count($campaign_media) ?>;

function showSlide(index){
const slides=document.querySelectorAll('.media-slide');
const dots=document.querySelectorAll('.slider-dot');
if(slides.length===0)return;
currentSlideIndex=(index+slides.length)%slides.length;
slides.forEach((s,i)=>{
s.classList.toggle('active',i===currentSlideIndex);
});
dots.forEach((d,i)=>{
d.classList.toggle('active',i===currentSlideIndex);
});
const counter=document.getElementById('currentSlide');
if(counter)counter.textContent=currentSlideIndex+1;
}

function nextSlide(){
showSlide(currentSlideIndex+1);
}

function previousSlide(){
showSlide(currentSlideIndex-1);
}

document.addEventListener('DOMContentLoaded',function(){
const pf=document.getElementById('progressFill');
const pt=document.getElementById('progressText');
if(pf&&pt){
const target=parseFloat(pf.dataset.progress)||0;
setTimeout(function(){
pf.style.width=target+'%';
let start=null;
const step=timestamp=>{
if(!start)start=timestamp;
const progress=Math.min((timestamp-start)/2000,1);
const ease=1-Math.pow(1-progress,3);
const val=(target*ease).toFixed(1);
pt.textContent=val+'%';
if(progress<1)window.requestAnimationFrame(step);
else pt.textContent=target.toFixed(1)+'%'
};
window.requestAnimationFrame(step)
},500)
}

if(totalSlides>1){
const dotsContainer=document.getElementById('sliderDots');
if(dotsContainer){
for(let i=0;i<totalSlides;i++){
const dot=document.createElement('div');
dot.className='slider-dot'+(i===0?' active':'');
dot.onclick=()=>showSlide(i);
dotsContainer.appendChild(dot);
}
}

// Auto-slide disabled - user can navigate manually
// let autoSlideInterval=setInterval(nextSlide,5000);
// const slider=document.getElementById('mediaSlider');
// if(slider){
// slider.addEventListener('mouseenter',()=>clearInterval(autoSlideInterval));
// slider.addEventListener('mouseleave',()=>{
// autoSlideInterval=setInterval(nextSlide,5000);
// });
// }
}
});

function shareWhatsApp(){
const txt="Ayo bantu kampanye: <?= htmlspecialchars($campaign['title']) ?>\n"+window.location.href;
window.open("https://wa.me/?text="+encodeURIComponent(txt),'_blank')
}

function shareFacebook(){
window.open("https://www.facebook.com/sharer/sharer.php?u="+encodeURIComponent(window.location.href),'_blank')
}

function copyLink(){
navigator.clipboard.writeText(window.location.href)
.then(()=>showNotification('Berhasil disalin!'))
.catch(()=>showNotification('Gagal menyalin'))
}

function showNotification(msg){
const n=document.createElement('div');
n.textContent=msg;
n.style.cssText='position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#17a697;color:#fff;padding:12px 24px;border-radius:10px;font-size:13px;font-weight:600;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,.3)';
document.body.appendChild(n);
setTimeout(()=>n.remove(),2500)
}

document.getElementById('donor_phone').addEventListener('input',function(e){
let v=e.target.value.replace(/\D/g,'');
if(v.startsWith('0'))v='62'+v.substring(1);
else if(!v.startsWith('62'))v='62'+v;
e.target.value=v.substring(0,15)
});

window.onclick=function(e){
const dm=document.getElementById('donateModal');
const pm=document.getElementById('paymentModal');
if(e.target===dm)closeDonateModal();
if(e.target===pm)closePaymentModal()
}
</script>
</body>
</html>