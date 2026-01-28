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

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query untuk mengambil semua donasi
$sql = "SELECT d.*, k.judul as kampanye_judul, k.foto as kampanye_foto, c.nama as csr_nama
        FROM transaksi_donasi d
        JOIN kampanye_donasi k ON d.kampanye_id = k.id
        JOIN csr_donasi c ON k.csr_id = c.id
        WHERE 1=1";

// Filter berdasarkan status
if ($filter == 'berhasil') {
    $sql .= " AND d.status_pembayaran = 'berhasil'";
} elseif ($filter == 'pending') {
    $sql .= " AND d.status_pembayaran = 'pending'";
}

// Search
if (!empty($search)) {
    $sql .= " AND (d.nama_donatur LIKE :search OR k.judul LIKE :search OR c.nama LIKE :search)";
}

$sql .= " ORDER BY d.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);

if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
}

$stmt->execute();
$all_donasi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik
$stmt_stats = $pdo->query("SELECT 
    COUNT(*) as total_transaksi,
    SUM(CASE WHEN status_pembayaran = 'berhasil' THEN nominal ELSE 0 END) as total_donasi,
    SUM(CASE WHEN status_pembayaran = 'berhasil' THEN 1 ELSE 0 END) as total_berhasil,
    SUM(CASE WHEN status_pembayaran = 'pending' THEN 1 ELSE 0 END) as total_pending,
    COUNT(DISTINCT nama_donatur) as total_donatur
    FROM transaksi_donasi");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivitas Donasi - AyoBerbagi</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: white;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-card i {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-card.blue { color: #667eea; }
        .stat-card.green { color: #4caf50; }
        .stat-card.orange { color: #ff9800; }
        .stat-card.purple { color: #9c27b0; }

        .stat-card .value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #888;
            font-weight: 500;
        }

        /* Search & Filter */
        .search-filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .search-box {
            margin-bottom: 15px;
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
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #E0E0E0;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            color: #333;
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

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 0;
        }

        .timeline-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            position: relative;
            transition: all 0.3s;
        }

        .timeline-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .timeline-content {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .timeline-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
        }

        .timeline-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .timeline-details {
            flex: 1;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .donatur-name {
            font-size: 16px;
            font-weight: 700;
            color: #1F1F1F;
            margin-bottom: 5px;
        }

        .donatur-name i {
            color: #00AEEF;
            font-size: 14px;
        }

        .donation-amount {
            font-size: 18px;
            font-weight: 700;
            color: #4caf50;
        }

        .kampanye-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .kampanye-title strong {
            color: #333;
        }

        .donation-message {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            font-style: italic;
            margin-top: 10px;
            border-left: 3px solid #00AEEF;
        }

        .timeline-meta {
            display: flex;
            gap: 20px;
            margin-top: 12px;
            font-size: 12px;
            color: #888;
        }

        .timeline-meta i {
            margin-right: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-berhasil {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-gagal {
            background: #f8d7da;
            color: #721c24;
        }

        .metode-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
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
            .timeline-content {
                flex-direction: column;
            }

            .timeline-image {
                width: 100%;
                height: 150px;
            }

            .timeline-header {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Aktivitas Donasi</h1>
            <p>Pantau semua aktivitas donasi dari seluruh kampanye</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <i class="fas fa-exchange-alt"></i>
                <div class="value"><?php echo number_format($stats['total_transaksi']); ?></div>
                <div class="label">Total Transaksi</div>
            </div>
            <div class="stat-card green">
                <i class="fas fa-money-bill-wave"></i>
                <div class="value">Rp <?php echo number_format($stats['total_donasi'], 0, ',', '.'); ?></div>
                <div class="label">Total Donasi</div>
            </div>
            <div class="stat-card purple">
                <i class="fas fa-users"></i>
                <div class="value"><?php echo number_format($stats['total_donatur']); ?></div>
                <div class="label">Total Donatur</div>
            </div>
            <div class="stat-card orange">
                <i class="fas fa-check-circle"></i>
                <div class="value"><?php echo number_format($stats['total_berhasil']); ?></div>
                <div class="label">Donasi Berhasil</div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="search-filter-container">
            <form method="GET" action="">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" placeholder="🔎 Cari berdasarkan nama donatur, kampanye, atau organisasi..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Semua (<?php echo $stats['total_transaksi']; ?>)
                    </a>
                    <a href="?filter=berhasil" class="filter-btn <?php echo $filter == 'berhasil' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Berhasil (<?php echo $stats['total_berhasil']; ?>)
                    </a>
                    <a href="?filter=pending" class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Pending (<?php echo $stats['total_pending']; ?>)
                    </a>
                </div>
            </form>
        </div>

        <!-- Timeline -->
        <div class="timeline">
            <?php if (count($all_donasi) > 0): ?>
                <?php foreach ($all_donasi as $donasi): 
                    $status_class = 'status-' . $donasi['status_pembayaran'];
                    $status_text = [
                        'berhasil' => '✅ Berhasil',
                        'pending' => '⏳ Pending',
                        'gagal' => '❌ Gagal'
                    ];
                    
                    $metode_text = [
                        'transfer_bank' => '🏦 Transfer Bank',
                        'e_wallet' => '💳 E-Wallet',
                        'qris' => '📱 QRIS'
                    ];
                ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-image">
                                <?php if ($donasi['kampanye_foto'] && file_exists($donasi['kampanye_foto'])): ?>
                                    <img src="<?php echo htmlspecialchars($donasi['kampanye_foto']); ?>" alt="Kampanye">
                                <?php else: ?>
                                    💝
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-details">
                                <div class="timeline-header">
                                    <div>
                                        <div class="donatur-name">
                                            <i class="fas fa-user-circle"></i>
                                            <?php echo htmlspecialchars($donasi['nama_donatur']); ?>
                                        </div>
                                        <div class="kampanye-title">
                                            berdonasi untuk <strong><?php echo htmlspecialchars($donasi['kampanye_judul']); ?></strong>
                                        </div>
                                        <div style="font-size: 12px; color: #888; margin-top: 5px;">
                                            <i class="fas fa-building"></i>
                                            <?php echo htmlspecialchars($donasi['csr_nama']); ?>
                                        </div>
                                    </div>
                                    <div class="donation-amount">
                                        Rp <?php echo number_format($donasi['nominal'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                                
                                <?php if ($donasi['pesan']): ?>
                                    <div class="donation-message">
                                        <i class="fas fa-quote-left"></i>
                                        <?php echo htmlspecialchars($donasi['pesan']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="timeline-meta">
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d M Y, H:i', strtotime($donasi['created_at'])); ?>
                                    </span>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text[$donasi['status_pembayaran']] ?? $donasi['status_pembayaran']; ?>
                                    </span>
                                    <span class="metode-badge">
                                        <?php echo $metode_text[$donasi['metode_pembayaran']] ?? $donasi['metode_pembayaran']; ?>
                                    </span>
                                    <?php if ($donasi['email_donatur']): ?>
                                        <span>
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($donasi['email_donatur']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($donasi['no_hp']): ?>
                                        <span>
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($donasi['no_hp']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Belum Ada Aktivitas</h3>
                    <p>Saat ini belum ada aktivitas donasi yang tercatat.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($all_donasi) >= 100): ?>
            <div style="background: white; padding: 15px; border-radius: 12px; text-align: center; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <i class="fas fa-info-circle" style="color: #00AEEF;"></i>
                Menampilkan 100 transaksi terakhir. Gunakan pencarian untuk menemukan transaksi spesifik.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>