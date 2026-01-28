<?php
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

// Ambil semua kampanye yang berlangsung
$stmt = $pdo->prepare("SELECT k.*, c.nama as nama_csr 
                       FROM kampanye_donasi k 
                       JOIN csr_donasi c ON k.csr_id = c.id 
                       WHERE k.waktu_berakhir >= CURDATE() 
                       ORDER BY k.created_at DESC");
$stmt->execute();
$all_kampanye = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelajah Kampanye - AyoBerbagi</title>
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
            background-color: #F7F9FA;
            color: #333;
            padding-bottom: 70px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header h1 {
            font-size: 24px;
            color: #1F1F1F;
            font-weight: 700;
        }

        .search-box {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: #00AEEF;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .filter-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .filter-tabs::-webkit-scrollbar-thumb {
            background: #00AEEF;
            border-radius: 2px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #E0E0E0;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .filter-btn:hover {
            border-color: #00AEEF;
            color: #00AEEF;
        }

        .filter-btn.active {
            background: #00AEEF;
            color: white;
            border-color: #00AEEF;
        }

        .kampanye-count {
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            font-size: 14px;
            color: #666;
        }

        .kampanye-count strong {
            color: #00AEEF;
        }

        .kampanye-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .kampanye-card {
            display: flex;
            gap: 16px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid #F0F0F0;
            padding: 16px;
        }

        .kampanye-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .kampanye-image {
            width: 160px;
            height: 120px;
            flex-shrink: 0;
            border-radius: 8px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .kampanye-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .kampanye-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kampanye-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1F1F1F;
            line-height: 1.4;
        }

        .kampanye-organizer {
            font-size: 12px;
            color: #888;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .verified-badge {
            color: #00AEEF;
            font-size: 12px;
        }

        .kampanye-description {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #F0F0F0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: #00AEEF;
            transition: width 0.3s;
            border-radius: 3px;
        }

        .kampanye-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        .stat-raised {
            font-weight: 700;
            color: #1F1F1F;
        }

        .stat-days {
            color: #888;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .status-terbaru {
            background: #E3F2FD;
            color: #1976D2;
        }

        .status-event {
            background: #F3E5F5;
            color: #7B1FA2;
        }

        .status-favorit {
            background: #FFF3E0;
            color: #F57C00;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        .empty-state p {
            color: #666;
        }

        @media (max-width: 768px) {
            .kampanye-image {
                width: 120px;
                height: 100px;
            }

            .kampanye-title {
                font-size: 14px;
            }

            .kampanye-card {
                gap: 12px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboarduser.php" style="color: #00AEEF; text-decoration: none;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1>🔍 Jelajah Kampanye</h1>
        </div>

        <div class="search-box">
            <input type="text" class="search-input" placeholder="🔎 Cari kampanye berdasarkan judul atau organisasi..." id="searchInput">
        </div>

        <div class="filter-tabs">
            <button class="filter-btn active" onclick="filterKampanye('all')">
                <i class="fas fa-list"></i> Semua (<?php echo count($all_kampanye); ?>)
            </button>
            <button class="filter-btn" onclick="filterKampanye('terbaru')">
                🔥 Terbaru (<?php echo count(array_filter($all_kampanye, function($k) { return $k['status'] == 'terbaru'; })); ?>)
            </button>
            <button class="filter-btn" onclick="filterKampanye('event')">
                🎉 Event (<?php echo count(array_filter($all_kampanye, function($k) { return $k['status'] == 'event'; })); ?>)
            </button>
            <button class="filter-btn" onclick="filterKampanye('favorit')">
                ⭐ Favorit (<?php echo count(array_filter($all_kampanye, function($k) { return $k['status'] == 'favorit'; })); ?>)
            </button>
        </div>

        <div class="kampanye-count">
            Menampilkan <strong id="countText"><?php echo count($all_kampanye); ?></strong> kampanye yang sedang berlangsung
        </div>

        <div class="kampanye-list" id="kampanyeList">
            <?php if (count($all_kampanye) > 0): ?>
                <?php foreach ($all_kampanye as $kampanye): 
                    $progress = ($kampanye['target_nominal'] > 0) ? ($kampanye['terkumpul'] / $kampanye['target_nominal'] * 100) : 0;
                    $hari_tersisa = max(0, ceil((strtotime($kampanye['waktu_berakhir']) - time()) / 86400));
                    
                    $status_class = 'status-' . $kampanye['status'];
                    $status_text = [
                        'terbaru' => '🔥 Terbaru',
                        'event' => '🎉 Event',
                        'favorit' => '⭐ Favorit'
                    ];
                ?>
                    <div class="kampanye-card" 
                         data-title="<?php echo strtolower(htmlspecialchars($kampanye['judul'])); ?>" 
                         data-organizer="<?php echo strtolower(htmlspecialchars($kampanye['nama_csr'])); ?>"
                         data-status="<?php echo $kampanye['status']; ?>"
                         onclick="window.location.href='donasi.php?id=<?php echo $kampanye['id']; ?>'">
                        <div class="kampanye-image">
                            <?php if ($kampanye['foto'] && file_exists($kampanye['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($kampanye['foto']); ?>" alt="<?php echo htmlspecialchars($kampanye['judul']); ?>">
                            <?php else: ?>
                                💝
                            <?php endif; ?>
                        </div>
                        <div class="kampanye-content">
                            <div>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text[$kampanye['status']] ?? $kampanye['status']; ?>
                                </span>
                                <h3 class="kampanye-title"><?php echo htmlspecialchars($kampanye['judul']); ?></h3>
                                <p class="kampanye-organizer">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($kampanye['nama_csr']); ?>
                                    <i class="fas fa-check-circle verified-badge"></i>
                                </p>
                                <p class="kampanye-description">
                                    <?php echo htmlspecialchars($kampanye['cerita']); ?>
                                </p>
                            </div>
                            <div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min($progress, 100); ?>%;"></div>
                                </div>
                                <div class="kampanye-stats">
                                    <span class="stat-raised">Rp <?php echo number_format($kampanye['terkumpul'], 0, ',', '.'); ?> dari Rp <?php echo number_format($kampanye['target_nominal'], 0, ',', '.'); ?></span>
                                    <span class="stat-days"><?php echo $hari_tersisa; ?> hari lagi</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Belum Ada Kampanye</h3>
                    <p>Saat ini belum ada kampanye yang tersedia.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const kampanyeCards = document.querySelectorAll('.kampanye-card');
        let currentFilter = 'all';

        searchInput.addEventListener('input', function() {
            updateDisplay();
        });

        function filterKampanye(status) {
            currentFilter = status;
            
            // Update active button
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            updateDisplay();
        }

        function updateDisplay() {
            const searchTerm = searchInput.value.toLowerCase();
            let visibleCount = 0;

            kampanyeCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const organizer = card.getAttribute('data-organizer');
                const status = card.getAttribute('data-status');

                const matchesSearch = title.includes(searchTerm) || organizer.includes(searchTerm);
                const matchesFilter = currentFilter === 'all' || status === currentFilter;

                if (matchesSearch && matchesFilter) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Update count text
            document.getElementById('countText').textContent = visibleCount;
        }
    </script>
</body>
</html>