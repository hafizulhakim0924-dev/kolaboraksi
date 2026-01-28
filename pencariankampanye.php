<?php
header('Content-Type: text/html; charset=utf-8');

// Database configuration
$db_host = 'localhost';
$db_user = 'rank3598_apk';
$db_pass = 'Hakim123!';
$db_name = 'rank3598_apk';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset("utf8");

// Helper function for Indonesian currency format
function rupiah($number) {
    return "Rp " . number_format($number, 0, ',', '.');
}

// Helper function to calculate progress percentage
function calculateProgress($terkumpul, $target) {
    if ($target <= 0) return 0;
    return min(100, round(($terkumpul / $target) * 100, 1));
}

// Get search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build SQL query
if (!empty($search_query)) {
    $search_term = '%' . $conn->real_escape_string($search_query) . '%';
    $sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul 
            FROM campaigns 
            WHERE title LIKE ? OR organizer LIKE ?
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Show all campaigns if no search query
    $sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul 
            FROM campaigns 
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
}

$campaigns = array();
while ($row = $result->fetch_assoc()) {
    $row['progress'] = calculateProgress($row['donasi_terkumpul'], $row['target_terkumpul']);
    $row['donasi_formatted'] = rupiah($row['donasi_terkumpul']);
    $campaigns[] = $row;
}

$total_results = count($campaigns);
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Kampanye - KolaborAksi</title>
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
            background: linear-gradient(135deg, #E8F5F2 0%, #F0FFFE 25%, #FFFFFF 50%, #F0FFFE 75%, #E8F5F2 100%);
            background-attachment: fixed;
            color: #333;
            min-height: 100vh;
            padding-bottom: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 16px;
            box-shadow: 0 2px 8px rgba(23, 166, 151, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 20px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-button {
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #17a697;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .back-button:hover {
            background: rgba(23, 166, 151, 0.1);
        }

        .page-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F1F1F;
            flex: 1;
        }

        .search-section {
            margin-bottom: 24px;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-input {
            flex: 1;
            padding: 14px 16px;
            border: 2px solid #E0E0E0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: #17a697;
            box-shadow: 0 0 0 3px rgba(23, 166, 151, 0.1);
        }

        .search-button {
            padding: 14px 24px;
            background: #17a697;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-button:hover {
            background: #0f6f5f;
            transform: translateY(-2px);
        }

        .search-button:active {
            transform: translateY(0);
        }

        .results-info {
            background: rgba(255, 255, 255, 0.8);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .results-count {
            font-size: 14px;
            color: #666;
        }

        .results-count strong {
            color: #17a697;
            font-weight: 700;
        }

        .clear-search {
            background: none;
            border: none;
            color: #17a697;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .clear-search:hover {
            background: rgba(23, 166, 151, 0.1);
        }

        .campaigns-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .campaign-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #F0F0F0;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .campaign-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(23, 166, 151, 0.15);
        }

        .campaign-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .campaign-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .campaign-content {
            padding: 16px;
        }

        .campaign-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            color: #1F1F1F;
            min-height: 44px;
        }

        .campaign-organizer {
            font-size: 12px;
            color: #888;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .verified-badge {
            color: #17a697;
        }

        .progress-section {
            margin-bottom: 12px;
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
            background: linear-gradient(90deg, #17a697 0%, #20c997 100%);
            transition: width 0.5s ease;
            border-radius: 3px;
        }

        .campaign-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 11px;
            color: #888;
            margin-bottom: 2px;
        }

        .stat-value {
            font-size: 14px;
            font-weight: 700;
            color: #17a697;
        }

        .stat-progress {
            font-size: 12px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F1F1F;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 14px;
            color: #888;
            line-height: 1.6;
        }

        @media (min-width: 768px) {
            .campaigns-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .search-input {
                font-size: 15px;
            }
        }

        @media (min-width: 1024px) {
            .campaigns-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }

            .container {
                padding: 0 24px;
            }
        }

        .highlight {
            background: rgba(23, 166, 151, 0.2);
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <h1 class="page-title">🔍 Cari Kampanye</h1>
        </div>
    </div>

    <div class="container">
        <div class="search-section">
            <form method="GET" action="" class="search-form">
                <input 
                    type="text" 
                    name="q" 
                    class="search-input" 
                    placeholder="Cari kampanye atau penyelenggara..."
                    value="<?php echo htmlspecialchars($search_query); ?>"
                    autofocus
                >
                <button type="submit" class="search-button">
                    <i class="fas fa-search"></i>
                    <span>Cari</span>
                </button>
            </form>
        </div>

        <?php if (!empty($search_query) || $total_results > 0): ?>
        <div class="results-info">
            <div class="results-count">
                <?php if (!empty($search_query)): ?>
                    Ditemukan <strong><?php echo $total_results; ?></strong> kampanye untuk "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                <?php else: ?>
                    Menampilkan <strong><?php echo $total_results; ?></strong> kampanye
                <?php endif; ?>
            </div>
            <?php if (!empty($search_query)): ?>
            <a href="pencariankampanye.php" class="clear-search">
                <i class="fas fa-times"></i> Hapus
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($total_results > 0): ?>
        <div class="campaigns-grid">
            <?php foreach ($campaigns as $campaign): ?>
            <a href="kampanye-detail.php?id=<?php echo $campaign['id']; ?>" class="campaign-card">
                <div class="campaign-image">
                    <?php if (!empty($campaign['image'])): ?>
                        <img src="<?php echo htmlspecialchars($campaign['image']); ?>" alt="<?php echo htmlspecialchars($campaign['title']); ?>" onerror="this.parentElement.innerHTML='<?php echo $campaign['emoji'] ?: '💝'; ?>'">
                    <?php else: ?>
                        <?php echo $campaign['emoji'] ?: '💝'; ?>
                    <?php endif; ?>
                </div>
                <div class="campaign-content">
                    <h3 class="campaign-title"><?php echo htmlspecialchars($campaign['title']); ?></h3>
                    <p class="campaign-organizer">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($campaign['organizer']); ?>
                        <i class="fas fa-check-circle verified-badge"></i>
                    </p>
                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $campaign['progress']; ?>%;"></div>
                        </div>
                        <div class="campaign-stats">
                            <div class="stat-item">
                                <span class="stat-label">Terkumpul</span>
                                <span class="stat-value"><?php echo $campaign['donasi_formatted']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-progress"><?php echo $campaign['progress']; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <h2 class="empty-title">
                <?php if (!empty($search_query)): ?>
                    Tidak Ada Hasil
                <?php else: ?>
                    Belum Ada Kampanye
                <?php endif; ?>
            </h2>
            <p class="empty-text">
                <?php if (!empty($search_query)): ?>
                    Maaf, kami tidak menemukan kampanye yang cocok dengan pencarian "<strong><?php echo htmlspecialchars($search_query); ?></strong>".<br>
                    Coba kata kunci lain atau <a href="pencariankampanye.php" style="color: #17a697; font-weight: 600;">lihat semua kampanye</a>.
                <?php else: ?>
                    Belum ada kampanye yang tersedia saat ini.<br>
                    Silakan kembali lagi nanti.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto focus search input on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });

        // Highlight search terms in results
        function highlightSearchTerms() {
            const searchQuery = '<?php echo addslashes($search_query); ?>';
            if (!searchQuery) return;

            const titles = document.querySelectorAll('.campaign-title');
            const organizers = document.querySelectorAll('.campaign-organizer');

            function highlight(element, term) {
                const text = element.textContent;
                const regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                const highlightedText = text.replace(regex, '<span class="highlight">$1</span>');
                if (text !== highlightedText) {
                    element.innerHTML = highlightedText;
                }
            }

            titles.forEach(el => highlight(el, searchQuery));
        }

        // Run highlight after page loads
        if ('<?php echo $search_query; ?>') {
            window.addEventListener('load', highlightSearchTerms);
        }
    </script>
</body>
</html>