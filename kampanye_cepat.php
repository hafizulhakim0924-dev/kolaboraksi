<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
include "db.php";

// Helper function
function rupiah($number) {
    return "Rp " . number_format($number, 0, ',', '.');
}

function calculateProgress($terkumpul, $target) {
    if ($target <= 0) return 0;
    return min(100, round(($terkumpul / $target) * 100, 1));
}

// Get campaign ID from URL or session
$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['quick_campaign_id']) ? $_SESSION['quick_campaign_id'] : 0);

// Get all campaigns for selection - hanya yang approved
$campaigns_list = [];
$result = mysqli_query($conn, "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul FROM campaigns WHERE (status = 'approved' OR status IS NULL OR status = '') ORDER BY created_at DESC LIMIT 20");
while ($row = mysqli_fetch_assoc($result)) {
    $campaigns_list[] = $row;
}

// If campaign ID is set, get campaign details
$campaign = null;
$recent_donations = [];
$user_target = 0;

if ($campaign_id > 0) {
    $_SESSION['quick_campaign_id'] = $campaign_id;
    $result = mysqli_query($conn, "SELECT * FROM campaigns WHERE id = $campaign_id");
    $campaign = mysqli_fetch_assoc($result);
    
    if ($campaign) {
        // Get recent donations
        $donations_result = mysqli_query($conn, "SELECT donor_name, amount, message, is_anonymous, created_at FROM donations WHERE campaign_id = $campaign_id AND status = 'PAID' ORDER BY created_at DESC LIMIT 20");
        while ($row = mysqli_fetch_assoc($donations_result)) {
            $recent_donations[] = $row;
        }
        
        // Get user target from session
        $user_target = isset($_SESSION['quick_target_' . $campaign_id]) ? intval($_SESSION['quick_target_' . $campaign_id]) : intval($campaign['target_terkumpul']);
    }
}

// Handle target update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_target']) && $campaign_id > 0) {
    $new_target = intval($_POST['target']);
    if ($new_target > 0) {
        $_SESSION['quick_target_' . $campaign_id] = $new_target;
        $user_target = $new_target;
        header("Location: kampanye_cepat.php?id=$campaign_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kampanye Cepat - KolaborAksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Campaign Selection */
        .selection-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .selection-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
            text-align: center;
        }

        .selection-subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }

        .campaigns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .campaign-select-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .campaign-select-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border-color: #667eea;
        }

        .campaign-select-emoji {
            font-size: 48px;
            text-align: center;
            margin-bottom: 10px;
        }

        .campaign-select-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            text-align: center;
        }

        .campaign-select-organizer {
            font-size: 12px;
            color: #666;
            text-align: center;
        }

        /* Quick Campaign Display */
        .quick-campaign {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .campaign-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .campaign-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .campaign-emoji {
            font-size: 80px;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .campaign-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .campaign-organizer {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .progress-section {
            padding: 40px 30px;
            background: #f8f9fa;
        }

        .target-control {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .target-control-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .target-control-title i {
            color: #ffc107;
        }

        .target-input-group {
            display: flex;
            gap: 10px;
        }

        .target-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
        }

        .target-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .target-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .target-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .progress-container {
            position: relative;
            margin-bottom: 30px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 18px;
        }

        .progress-bar-wrapper {
            position: relative;
            height: 50px;
            background: #e0e0e0;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            border-radius: 25px;
            position: relative;
            transition: width 0.5s ease;
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }

        .progress-bar-fill::after {
            content: '⚡';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            animation: flash 1s infinite;
        }

        @keyframes flash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .progress-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }

        .donations-feed {
            padding: 30px;
            background: white;
        }

        .donations-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .donations-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .donation-item {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            animation: slideIn 0.5s ease;
            border-left: 4px solid #667eea;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .donation-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .donation-amount {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }

        .donation-message {
            font-style: italic;
            color: #666;
            margin-bottom: 8px;
            padding: 10px;
            background: rgba(255,255,255,0.5);
            border-radius: 8px;
        }

        .donation-time {
            font-size: 12px;
            color: #999;
        }

        .empty-donations {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-donations i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .campaigns-grid {
                grid-template-columns: 1fr;
            }

            .progress-stats {
                grid-template-columns: 1fr;
            }

            .target-input-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>

        <?php if (!$campaign): ?>
            <!-- Campaign Selection -->
            <div class="selection-card">
                <h1 class="selection-title">⚡ Kampanye Cepat</h1>
                <p class="selection-subtitle">Pilih kampanye untuk tampilan real-time</p>
                
                <div class="campaigns-grid">
                    <?php foreach ($campaigns_list as $c): ?>
                        <div class="campaign-select-card" onclick="window.location.href='kampanye_cepat.php?id=<?= $c['id'] ?>'">
                            <div class="campaign-select-emoji"><?= htmlspecialchars($c['emoji'] ?? '💝') ?></div>
                            <div class="campaign-select-title"><?= htmlspecialchars($c['title']) ?></div>
                            <div class="campaign-select-organizer"><?= htmlspecialchars($c['organizer']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: 
            $target = $user_target > 0 ? $user_target : intval($campaign['target_terkumpul']);
            $terkumpul = intval($campaign['donasi_terkumpul']);
            $progress = calculateProgress($terkumpul, $target);
            $sisa = max(0, $target - $terkumpul);
        ?>
            <!-- Quick Campaign Display -->
            <div class="quick-campaign">
                <div class="campaign-header">
                    <div class="campaign-emoji"><?= htmlspecialchars($campaign['emoji'] ?? '💝') ?></div>
                    <h1 class="campaign-title"><?= htmlspecialchars($campaign['title']) ?></h1>
                    <div class="campaign-organizer">by <?= htmlspecialchars($campaign['organizer']) ?></div>
                </div>

                <div class="progress-section">
                    <div class="target-control">
                        <div class="target-control-title">
                            <i class="fas fa-bolt"></i>
                            Set Target Kampanye
                        </div>
                        <form method="POST" class="target-input-group">
                            <input type="number" name="target" class="target-input" value="<?= $target ?>" min="1000" step="1000" required>
                            <button type="submit" name="set_target" class="target-btn">Set Target ⚡</button>
                        </form>
                    </div>

                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Progres</span>
                            <span id="progressPercent"><?= number_format($progress, 1) ?>%</span>
                        </div>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar-fill" id="progressBar" style="width: <?= $progress ?>%"></div>
                        </div>
                    </div>

                    <div class="progress-stats">
                        <div class="stat-card">
                            <div class="stat-icon">💰</div>
                            <div class="stat-label">Terkumpul</div>
                            <div class="stat-value" id="statTerkumpul"><?= rupiah($terkumpul) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">🎯</div>
                            <div class="stat-label">Target</div>
                            <div class="stat-value"><?= rupiah($target) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📊</div>
                            <div class="stat-label">Tersisa</div>
                            <div class="stat-value" id="statTersisa"><?= rupiah($sisa) ?></div>
                        </div>
                    </div>
                </div>

                <div class="donations-feed">
                    <h2 class="donations-title">
                        <i class="fas fa-heart"></i>
                        Donasi Terbaru
                    </h2>
                    <div class="donations-list" id="donationsList">
                        <?php if (count($recent_donations) > 0): ?>
                            <?php foreach ($recent_donations as $donation): 
                                $name = ($donation['is_anonymous'] == 1) ? 'Anonim' : htmlspecialchars($donation['donor_name']);
                            ?>
                                <div class="donation-item">
                                    <div class="donation-header">
                                        <div class="donation-name"><?= $name ?></div>
                                        <div class="donation-amount"><?= rupiah($donation['amount']) ?></div>
                                    </div>
                                    <?php if (!empty($donation['message'])): ?>
                                        <div class="donation-message">"<?= htmlspecialchars($donation['message']) ?>"</div>
                                    <?php endif; ?>
                                    <div class="donation-time">
                                        <i class="far fa-clock"></i> <?= date('d M Y, H:i', strtotime($donation['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-donations">
                                <i class="fas fa-inbox"></i>
                                <p>Belum ada donasi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($campaign): ?>
    <script>
        // Silent polling - refresh data secara realtime tanpa terlihat
        let lastDonationCount = <?= count($recent_donations) ?>;
        let lastDonationTime = '<?= count($recent_donations) > 0 ? $recent_donations[0]['created_at'] : '' ?>';
        
        function formatRupiah(number) {
            return 'Rp ' + number.toLocaleString('id-ID');
        }
        
        function formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            
            const day = String(date.getDate()).padStart(2, '0');
            const month = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][date.getMonth()];
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day} ${month} ${year}, ${hours}:${minutes}`;
        }
        
        function updateDonationsList(donations) {
            const donationsList = document.getElementById('donationsList');
            if (donations.length === 0) {
                donationsList.innerHTML = '<div class="empty-donations"><i class="fas fa-inbox"></i><p>Belum ada donasi</p></div>';
                return;
            }
            
            let html = '';
            donations.forEach(donation => {
                const name = donation.is_anonymous == 1 ? 'Anonim' : (donation.donor_name || 'Anonim');
                const message = donation.message ? `<div class="donation-message">"${donation.message}"</div>` : '';
                
                html += `
                    <div class="donation-item">
                        <div class="donation-header">
                            <div class="donation-name">${name}</div>
                            <div class="donation-amount">${formatRupiah(parseInt(donation.amount))}</div>
                        </div>
                        ${message}
                        <div class="donation-time">
                            <i class="far fa-clock"></i> ${formatTime(donation.created_at)}
                        </div>
                    </div>
                `;
            });
            
            // Cek apakah ada donasi baru
            if (donations.length > lastDonationCount || (donations.length > 0 && donations[0].created_at !== lastDonationTime)) {
                // Ada donasi baru, update dengan animasi
                donationsList.innerHTML = html;
                lastDonationCount = donations.length;
                lastDonationTime = donations.length > 0 ? donations[0].created_at : '';
            } else {
                // Tidak ada perubahan, update tanpa animasi
                donationsList.innerHTML = html;
            }
        }
        
        function pollCampaignData() {
            fetch('api.php?action=get_quick_campaign_data&id=<?= $campaign_id ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const terkumpul = parseInt(data.terkumpul);
                        const target = parseInt(data.target);
                        const progress = target > 0 ? Math.min(100, (terkumpul / target * 100)) : 0;
                        
                        // Update progress bar
                        const progressBar = document.getElementById('progressBar');
                        const progressPercent = document.getElementById('progressPercent');
                        const statTerkumpul = document.getElementById('statTerkumpul');
                        const statTersisa = document.getElementById('statTersisa');
                        
                        if (progressBar) {
                            progressBar.style.width = progress + '%';
                        }
                        if (progressPercent) {
                            progressPercent.textContent = progress.toFixed(1) + '%';
                        }
                        if (statTerkumpul) {
                            statTerkumpul.textContent = formatRupiah(terkumpul);
                        }
                        if (statTersisa) {
                            statTersisa.textContent = formatRupiah(Math.max(0, target - terkumpul));
                        }
                        
                        // Update donations list
                        if (data.donations) {
                            updateDonationsList(data.donations);
                        }
                    }
                })
                .catch(err => {
                    // Silent error handling - tidak tampilkan error ke user
                });
        }
        
        // Start polling setiap 2 detik (silent, tidak terlihat)
        setInterval(pollCampaignData, 2000);
        
        // Poll immediately on page load
        setTimeout(pollCampaignData, 500);
    </script>
    <?php endif; ?>
</body>
</html>
