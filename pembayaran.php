<?php
session_start();

// Koneksi database
$host = 'localhost';
$dbname = 'xreiins1_asrama';
$username = 'xreiins1_asrama';
$password = 'Hakim123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$message = '';
$donasi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil detail donasi
$stmt = $pdo->prepare("SELECT d.*, k.judul as kampanye_judul, k.id as kampanye_id 
                       FROM transaksi_donasi d 
                       JOIN kampanye_donasi k ON d.kampanye_id = k.id 
                       WHERE d.id = ?");
$stmt->execute([$donasi_id]);
$donasi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donasi) {
    header('Location: dashboarduser.php');
    exit;
}

// Proses upload bukti transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_bukti'])) {
    $bukti = '';
    
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['bukti_transfer']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'bukti_' . $donasi_id . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/bukti/' . $new_filename;
            
            if (!file_exists('uploads/bukti')) {
                mkdir('uploads/bukti', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $upload_path)) {
                // Update status donasi menjadi berhasil dan update terkumpul di kampanye
                $stmt = $pdo->prepare("UPDATE transaksi_donasi SET bukti_transfer = ?, status_pembayaran = 'berhasil' WHERE id = ?");
                $stmt->execute([$upload_path, $donasi_id]);
                
                // Update total terkumpul di kampanye
                $stmt = $pdo->prepare("UPDATE kampanye_donasi SET terkumpul = terkumpul + ? WHERE id = ?");
                $stmt->execute([$donasi['nominal'], $donasi['kampanye_id']]);
                
                header("Location: sukses.php?id=$donasi_id");
                exit;
            }
        }
    }
    
    $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Gagal upload bukti transfer!</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Donasi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .payment-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .payment-header p {
            opacity: 0.9;
        }
        
        .payment-content {
            padding: 30px;
        }
        
        .donation-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            padding-top: 15px;
        }
        
        .summary-item label {
            color: #666;
        }
        
        .summary-item span {
            font-weight: 600;
            color: #333;
        }
        
        .payment-methods {
            margin-bottom: 30px;
        }
        
        .payment-methods h3 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .method-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .method-card h4 {
            color: #667eea;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .method-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .account-number {
            background: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            color: #333;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .copy-btn:hover {
            background: #5568d3;
        }
        
        .upload-section {
            background: #fff3cd;
            border: 2px dashed #ffc107;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .upload-section h3 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .upload-section p {
            color: #856404;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: block;
            padding: 15px;
            background: white;
            border: 2px solid #ffc107;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #856404;
            font-weight: 600;
        }
        
        .file-input-label:hover {
            background: #ffc107;
            color: white;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .note {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
        
        .note p {
            color: #014361;
            font-size: 13px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h2><i class="fas fa-credit-card"></i> Pembayaran Donasi</h2>
            <p>Lakukan pembayaran untuk menyelesaikan donasi Anda</p>
        </div>
        
        <div class="payment-content">
            <?php echo $message; ?>
            
            <div class="donation-summary">
                <h3 style="margin-bottom: 15px; color: #333;">Ringkasan Donasi</h3>
                <div class="summary-item">
                    <label>Kampanye:</label>
                    <span><?php echo htmlspecialchars($donasi['kampanye_judul']); ?></span>
                </div>
                <div class="summary-item">
                    <label>Nama Donatur:</label>
                    <span><?php echo htmlspecialchars($donasi['nama_donatur']); ?></span>
                </div>
                <div class="summary-item">
                    <label>Metode Pembayaran:</label>
                    <span>
                        <?php
                        $metode = [
                            'transfer_bank' => 'Transfer Bank',
                            'e_wallet' => 'E-Wallet',
                            'qris' => 'QRIS'
                        ];
                        echo $metode[$donasi['metode_pembayaran']] ?? 'Transfer Bank';
                        ?>
                    </span>
                </div>
                <div class="summary-item">
                    <label>Total Donasi:</label>
                    <span>Rp <?php echo number_format($donasi['nominal'], 0, ',', '.'); ?></span>
                </div>
            </div>
            
            <div class="payment-methods">
                <h3><i class="fas fa-info-circle"></i> Informasi Pembayaran</h3>
                
                <?php if ($donasi['metode_pembayaran'] == 'transfer_bank'): ?>
                    <div class="method-card">
                        <h4><i class="fas fa-university"></i> Bank BCA</h4>
                        <p>Silakan transfer ke rekening berikut:</p>
                        <div class="account-number">
                            <span>1234567890 - a.n. Yayasan Peduli Sesama</span>
                            <button class="copy-btn" onclick="copyText('1234567890')">
                                <i class="fas fa-copy"></i> Salin
                            </button>
                        </div>
                    </div>
                    
                    <div class="method-card">
                        <h4><i class="fas fa-university"></i> Bank Mandiri</h4>
                        <p>Atau transfer ke rekening:</p>
                        <div class="account-number">
                            <span>9876543210 - a.n. Yayasan Peduli Sesama</span>
                            <button class="copy-btn" onclick="copyText('9876543210')">
                                <i class="fas fa-copy"></i> Salin
                            </button>
                        </div>
                    </div>
                <?php elseif ($donasi['metode_pembayaran'] == 'e_wallet'): ?>
                    <div class="method-card">
                        <h4><i class="fas fa-mobile-alt"></i> OVO / GoPay / Dana</h4>
                        <p>Transfer ke nomor:</p>
                        <div class="account-number">
                            <span>081234567890 - a.n. Yayasan Peduli Sesama</span>
                            <button class="copy-btn" onclick="copyText('081234567890')">
                                <i class="fas fa-copy"></i> Salin
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="method-card">
                        <h4><i class="fas fa-qrcode"></i> QRIS</h4>
                        <p>Scan QR Code berikut untuk pembayaran:</p>
                        <div style="text-align: center; margin-top: 15px;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=QRIS_PEDULI_SESAMA" alt="QR Code" style="max-width: 200px;">
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="upload-section">
                    <h3><i class="fas fa-upload"></i> Upload Bukti Transfer</h3>
                    <p>Setelah melakukan pembayaran, upload bukti transfer Anda (JPG, PNG, atau PDF)</p>
                    
                    <div class="file-input-wrapper">
                        <input type="file" name="bukti_transfer" id="bukti_transfer" accept="image/*,.pdf" required onchange="updateFileName()">
                        <label for="bukti_transfer" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i> <span id="file-name">Pilih File Bukti Transfer</span>
                        </label>
                    </div>
                    
                    <button type="submit" name="upload_bukti" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                    </button>
                </div>
            </form>
            
            <div class="note">
                <p><strong><i class="fas fa-exclamation-triangle"></i> Penting:</strong></p>
                <p>• Pastikan nominal transfer sesuai dengan jumlah donasi<br>
                • Upload bukti transfer yang jelas dan dapat dibaca<br>
                • Donasi akan diverifikasi dalam 1x24 jam<br>
                • Hubungi kami jika ada kendala pembayaran</p>
            </div>
        </div>
    </div>
    
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Nomor rekening berhasil disalin!');
            });
        }
        
        function updateFileName() {
            const input = document.getElementById('bukti_transfer');
            const label = document.getElementById('file-name');
            
            if (input.files && input.files[0]) {
                label.textContent = input.files[0].name;
            }
        }
    </script>
</body>
</html>