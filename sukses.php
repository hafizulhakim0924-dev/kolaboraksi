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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donasi Berhasil!</title>
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
        
        .success-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            padding: 50px 30px;
        }
        
        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }
        
        .success-icon i {
            font-size: 60px;
            color: white;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 32px;
        }
        
        .success-message {
            color: #666;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .donation-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-item label {
            color: #666;
            font-weight: 600;
        }
        
        .detail-item span {
            color: #333;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
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
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }
        
        .share-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px dashed #e9ecef;
        }
        
        .share-section h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .share-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .share-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            transition: transform 0.3s;
        }
        
        .share-btn:hover {
            transform: scale(1.1);
        }
        
        .share-btn.whatsapp { background: #25d366; }
        .share-btn.facebook { background: #3b5998; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.telegram { background: #0088cc; }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Terima Kasih! 🎉</h1>
        
        <div class="success-message">
            <p><strong>Donasi Anda telah berhasil!</strong></p>
            <p>Terima kasih atas kebaikan hati Anda. Kontribusi Anda akan sangat membantu dan membawa perubahan positif bagi mereka yang membutuhkan.</p>
        </div>
        
        <div class="donation-details">
            <div class="detail-item">
                <label>ID Donasi:</label>
                <span>#<?php echo str_pad($donasi['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="detail-item">
                <label>Kampanye:</label>
                <span><?php echo htmlspecialchars($donasi['kampanye_judul']); ?></span>
            </div>
            <div class="detail-item">
                <label>Jumlah Donasi:</label>
                <span style="color: #667eea; font-weight: 700; font-size: 18px;">
                    Rp <?php echo number_format($donasi['nominal'], 0, ',', '.'); ?>
                </span>
            </div>
            <div class="detail-item">
                <label>Status:</label>
                <span style="color: #4caf50; font-weight: 700;">
                    <i class="fas fa-check-circle"></i> Berhasil
                </span>
            </div>
        </div>
        
        <div class="buttons">
            <a href="dashboarduser.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Kembali ke Beranda
            </a>
            <a href="donasi.php?id=<?php echo $donasi['kampanye_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Lihat Kampanye
            </a>
        </div>
        
        <div class="share-section">
            <h3>Bagikan Kampanye Ini</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                Ajak teman dan keluarga untuk ikut berdonasi
            </p>
            <div class="share-buttons">
                <button class="share-btn whatsapp" onclick="shareWhatsApp()">
                    <i class="fab fa-whatsapp"></i>
                </button>
                <button class="share-btn facebook" onclick="shareFacebook()">
                    <i class="fab fa-facebook-f"></i>
                </button>
                <button class="share-btn twitter" onclick="shareTwitter()">
                    <i class="fab fa-twitter"></i>
                </button>
                <button class="share-btn telegram" onclick="shareTelegram()">
                    <i class="fab fa-telegram-plane"></i>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        const url = window.location.origin + '/donasi.php?id=<?php echo $donasi['kampanye_id']; ?>';
        const text = 'Saya baru saja berdonasi untuk "<?php echo addslashes($donasi['kampanye_judul']); ?>". Yuk ikut berdonasi juga!';
        
        function shareWhatsApp() {
            window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank');
        }
        
        function shareFacebook() {
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
        }
        
        function shareTwitter() {
            window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`, '_blank');
        }
        
        function shareTelegram() {
            window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`, '_blank');
        }
    </script>
</body>
</html>