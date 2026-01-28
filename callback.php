<?php
require_once 'tripay_config.php';

// Get callback signature from header
$callbackSignature = isset($_SERVER['HTTP_X_CALLBACK_SIGNATURE']) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';

// Get raw input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log raw callback for debugging
file_put_contents(
    'callback_log.txt', 
    date('Y-m-d H:i:s') . "\n" . 
    "Signature: " . $callbackSignature . "\n" . 
    "Data: " . $json . "\n\n",
    FILE_APPEND
);

// Validate signature
if (empty($callbackSignature) || empty($json)) {
    http_response_code(400);
    exit('Invalid request');
}

$signature = hash_hmac('sha256', $json, TRIPAY_PRIVATE_KEY);

if ($signature !== $callbackSignature) {
    http_response_code(403);
    exit('Invalid signature');
}

// Process callback
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid JSON data');
}

$conn = getDBConnection();

// Extract data
$tripay_reference = $data['reference'] ?? '';
$merchant_ref = $data['merchant_ref'] ?? '';
$status = $data['status'] ?? '';
$payment_method = $data['payment_method'] ?? '';
$payment_name = $data['payment_name'] ?? '';
$paid_amount = isset($data['amount_received']) ? intval($data['amount_received']) : 0;

// Log to payment_logs table
$log_sql = "INSERT INTO payment_logs (tripay_reference, event_type, status, payload) VALUES (?, ?, ?, ?)";
$log_stmt = $conn->prepare($log_sql);
$event_type = 'payment_status';
$log_stmt->bind_param("ssss", $tripay_reference, $event_type, $status, $json);
$log_stmt->execute();
$log_stmt->close();

// Get donation record
$sql = "SELECT * FROM donations WHERE tripay_reference = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tripay_reference);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    http_response_code(404);
    exit('Donation not found');
}

$donation = $result->fetch_assoc();
$donation_id = $donation['id'];
$campaign_id = $donation['campaign_id'];
$stmt->close();

// Update donation status based on Tripay status
$new_status = '';
$paid_at = null;

switch ($status) {
    case 'PAID':
        $new_status = 'PAID';
        $paid_at = date('Y-m-d H:i:s');
        
        // Update campaign's collected amount
        $update_campaign_sql = "UPDATE campaigns 
                               SET donasi_terkumpul = donasi_terkumpul + ? 
                               WHERE id = ?";
        $update_stmt = $conn->prepare($update_campaign_sql);
        $donation_amount = intval($donation['amount']);
        $update_stmt->bind_param("ii", $donation_amount, $campaign_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        break;
        
    case 'EXPIRED':
        $new_status = 'EXPIRED';
        break;
        
    case 'FAILED':
        $new_status = 'FAILED';
        break;
        
    case 'UNPAID':
    default:
        $new_status = 'UNPAID';
        break;
}

// Update donation record
if (!empty($new_status)) {
    if ($paid_at) {
        $update_sql = "UPDATE donations 
                      SET status = ?, 
                          paid_at = ? 
                      WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $new_status, $paid_at, $donation_id);
    } else {
        $update_sql = "UPDATE donations 
                      SET status = ? 
                      WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $donation_id);
    }
    
    $update_stmt->execute();
    $update_stmt->close();
}

$conn->close();

// Send success response to Tripay
http_response_code(200);
echo json_encode(['success' => true]);

// Optional: Send notification to donor (you can implement email/WhatsApp notification here)
if ($new_status === 'PAID') {
    // Send thank you email or WhatsApp message
    // sendThankYouNotification($donation['donor_email'], $donation['donor_phone'], $donation);
}

exit;
?>