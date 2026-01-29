<?php
session_start();
include "db.php";

/* =========================
   CEK LOGIN PARTNER
========================= */
if (!isset($_SESSION['partner_id']) || !isset($_SESSION['partner_nama'])) {
    header("Location: partner_login.php");
    exit;
}

$partner_id   = $_SESSION['partner_id'];
$partner_nama = $_SESSION['partner_nama'];

/* =========================
   HANDLE SUBMIT FORM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'banner') {
    // ==== FORM: SIMPAN BANNER BERANDA ====
    $title    = mysqli_real_escape_string($conn, $_POST['banner_title'] ?? '');
    $subtitle = mysqli_real_escape_string($conn, $_POST['banner_subtitle'] ?? '');
    $link     = mysqli_real_escape_string($conn, $_POST['banner_link'] ?? '#');

    if (empty($title)) {
        $title = 'KolaborAksi';
    }

    if (!isset($_FILES['banner_image']) || $_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Silakan pilih gambar banner.";
        header("Location: partner_dashboard.php");
        exit;
    }

    // Validasi dan upload file banner
    $allowed_banner_ext = ['jpg','jpeg','png','gif','webp'];
    $banner_name = $_FILES['banner_image']['name'];
    $banner_tmp  = $_FILES['banner_image']['tmp_name'];
    $banner_ext  = strtolower(pathinfo($banner_name, PATHINFO_EXTENSION));

    if (!in_array($banner_ext, $allowed_banner_ext)) {
        $_SESSION['error'] = "Format banner tidak diizinkan. Gunakan JPG, JPEG, PNG, GIF, atau WEBP.";
        header("Location: partner_dashboard.php");
        return;
    }

    if (!is_dir('uploads/banners')) {
        mkdir('uploads/banners', 0755, true);
    }

    $banner_file = 'uploads/banners/' . time() . '_' . uniqid() . '.' . $banner_ext;
    if (!move_uploaded_file($banner_tmp, $banner_file)) {
        $_SESSION['error'] = "Gagal meng-upload file banner.";
        header("Location: partner_dashboard.php");
        return;
    }

    // Pastikan tabel banners ada
    $createBannerTable = "CREATE TABLE IF NOT EXISTS `banners` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `subtitle` varchar(255) DEFAULT NULL,
        `image` varchar(255) NOT NULL,
        `link` varchar(255) DEFAULT NULL,
        `order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_order` (`order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $createBannerTable);

    // Hitung urutan banner berikutnya
    $order = 1;
    $resOrder = mysqli_query($conn, "SELECT MAX(`order`) AS max_order FROM banners");
    if ($resOrder && $rowOrder = mysqli_fetch_assoc($resOrder)) {
        $order = (int)$rowOrder['max_order'] + 1;
    }

    $stmtBanner = $conn->prepare("INSERT INTO banners (title, subtitle, image, link, `order`) VALUES (?, ?, ?, ?, ?)");
    if ($stmtBanner) {
        $stmtBanner->bind_param("ssssi", $title, $subtitle, $banner_file, $link, $order);
        if ($stmtBanner->execute()) {
            $_SESSION['success'] = "Banner beranda berhasil disimpan.";
        } else {
            $_SESSION['error'] = "Gagal menyimpan banner: " . $stmtBanner->error;
        }
        $stmtBanner->close();
    } else {
        $_SESSION['error'] = "Gagal menyiapkan query banner: " . mysqli_error($conn);
    }

    header("Location: partner_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ==== FORM: BUAT KAMPANYE BARU ====
    $organizer        = mysqli_real_escape_string($conn, $partner_nama);
    $title            = mysqli_real_escape_string($conn, $_POST['title']);
    $emoji            = mysqli_real_escape_string($conn, $_POST['emoji'] ?? 'ðŸ“–');
    $description      = mysqli_real_escape_string($conn, $_POST['description']);
    $target_terkumpul = (int) $_POST['target_terkumpul'];
    $image_link       = mysqli_real_escape_string($conn, $_POST['image_link'] ?? '');
    $link             = mysqli_real_escape_string($conn, $_POST['link'] ?? '#');
    $type             = mysqli_real_escape_string($conn, $_POST['type'] ?? 'lainnya');

    /* ===== UPLOAD MULTIPLE MEDIA (IMAGES & VIDEOS) ===== */
    // Check if any file was uploaded
    $has_media = false;
    
    // Check for multiple media files
    if (isset($_FILES['media']) && !empty($_FILES['media'])) {
        // Handle both single file and multiple files
        if (isset($_FILES['media']['name']) && is_array($_FILES['media']['name'])) {
            // Multiple files
            $file_count = count($_FILES['media']['name']);
            if ($file_count > 0) {
                // Check if at least one file has no error
                for ($i = 0; $i < $file_count; $i++) {
                    if (isset($_FILES['media']['error'][$i]) && $_FILES['media']['error'][$i] === UPLOAD_ERR_OK) {
                        $has_media = true;
                        break;
                    }
                }
            }
        } elseif (isset($_FILES['media']['name']) && !empty($_FILES['media']['name'])) {
            // Single file (backward compatibility)
            if (isset($_FILES['media']['error']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
                $has_media = true;
            }
        }
    }
    
    // Check for single image (backward compatibility)
    if (!$has_media && isset($_FILES['image']) && isset($_FILES['image']['error']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $has_media = true;
    }
    
    if (!$has_media) {
        $_SESSION['error'] = "Minimal satu gambar atau video wajib diupload. Pastikan file yang dipilih valid dan tidak melebihi ukuran maksimal.";
        header("Location: partner_dashboard.php");
        exit;
    }

    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }

    $uploaded_media = [];
    $first_image_path = '';
    $upload_errors = [];

    // Handle multiple media files
    if (isset($_FILES['media']) && !empty($_FILES['media'])) {
        // Handle both array (multiple) and single file
        if (isset($_FILES['media']['name']) && is_array($_FILES['media']['name'])) {
            $file_count = count($_FILES['media']['name']);
        } elseif (isset($_FILES['media']['name']) && !empty($_FILES['media']['name'])) {
            // Single file - convert to array format for processing
            $file_count = 1;
            $single_file = $_FILES['media'];
            $_FILES['media'] = [
                'name' => [$single_file['name']],
                'type' => [$single_file['type']],
                'tmp_name' => [$single_file['tmp_name']],
                'error' => [$single_file['error']],
                'size' => [$single_file['size']]
            ];
        } else {
            $file_count = 0;
        }
        
        if ($file_count > 0) {
            for ($i = 0; $i < $file_count; $i++) {
                $file_error = $_FILES['media']['error'][$i];
                
                // Handle upload errors
                if ($file_error !== UPLOAD_ERR_OK) {
                    $error_message = '';
                    switch ($file_error) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = "File terlalu besar. Maksimal " . ini_get('upload_max_filesize');
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = "File hanya terupload sebagian";
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        continue; // Skip jika tidak ada file
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = "Folder temporary tidak ditemukan";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = "Gagal menulis file ke disk";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = "Upload dihentikan oleh extension PHP";
                        break;
                    default:
                        $error_message = "Error upload tidak diketahui (code: $file_error)";
                    }
                    if (!empty($error_message)) {
                        $upload_errors[] = $_FILES['media']['name'][$i] . ": " . $error_message;
                    }
                    continue;
                }

                $file_name = $_FILES['media']['name'][$i];
                $file_tmp = $_FILES['media']['tmp_name'][$i];
                $file_size = $_FILES['media']['size'][$i];
                
                // Check if file was actually uploaded
                if (!is_uploaded_file($file_tmp)) {
                    $upload_errors[] = $file_name . ": File tidak valid";
                    continue;
                }
                
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Determine media type
                $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowed_videos = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
                $media_type = null;
                
                if (in_array($ext, $allowed_images)) {
                    $media_type = 'image';
                    // Check image size (max 10MB per image)
                    if ($file_size > 10 * 1024 * 1024) {
                        $upload_errors[] = $file_name . ": Gambar terlalu besar. Maksimal 10MB";
                        continue;
                    }
                } elseif (in_array($ext, $allowed_videos)) {
                    $media_type = 'video';
                    // Check video size (max 100MB per video)
                    if ($file_size > 100 * 1024 * 1024) {
                        $upload_errors[] = $file_name . ": Video terlalu besar. Maksimal 100MB";
                        continue;
                    }
                    // Check if file is actually a video by checking MIME type (if available)
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_tmp);
                        finfo_close($finfo);
                        
                        $allowed_video_mimes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'application/octet-stream'];
                        if (!empty($mime_type) && !in_array($mime_type, $allowed_video_mimes) && strpos($mime_type, 'video/') !== 0) {
                            // Allow if it's a generic video type or if MIME check fails (might be valid video)
                            // Only reject if clearly not a video
                            if (strpos($mime_type, 'image/') === 0 || strpos($mime_type, 'text/') === 0) {
                                $upload_errors[] = $file_name . ": Format video tidak valid atau file rusak (MIME: $mime_type)";
                                continue;
                            }
                        }
                    }
                    // If finfo is not available, trust the file extension
                } else {
                    $upload_errors[] = $file_name . ": Format file tidak diizinkan. Gunakan gambar (JPG, PNG, GIF, WEBP) atau video (MP4, WEBM, OGG, MOV, AVI, MKV)";
                    continue;
                }

                $media_name = time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                $media_path = 'uploads/' . $media_name;

                if (move_uploaded_file($file_tmp, $media_path)) {
                    // Verify file exists and has content
                    if (file_exists($media_path) && filesize($media_path) > 0) {
                        $uploaded_media[] = [
                            'type' => $media_type,
                            'path' => $media_path,
                            'order' => count($uploaded_media)
                        ];
                        
                        // Set first image as main campaign image
                        if ($media_type === 'image' && empty($first_image_path)) {
                            $first_image_path = $media_path;
                        }
                    } else {
                        $upload_errors[] = $file_name . ": File gagal disimpan";
                        if (file_exists($media_path)) {
                            unlink($media_path);
                        }
                    }
                } else {
                    $upload_errors[] = $file_name . ": Gagal memindahkan file ke folder uploads";
                }
            } // End for loop
        } // End if file_count > 0
    } // End if isset $_FILES['media']

    // Handle single image (backward compatibility)
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $image_name = time() . '_' . uniqid() . '.' . $ext;
            $image_path = 'uploads/' . $image_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                if (empty($first_image_path)) {
                    $first_image_path = $image_path;
                }
                // Add to media array if not already added
                $found = false;
                foreach ($uploaded_media as $media) {
                    if ($media['path'] === $image_path) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $uploaded_media[] = [
                        'type' => 'image',
                        'path' => $image_path,
                        'order' => count($uploaded_media)
                    ];
                }
            }
        }
    }

    // Show errors if any, but continue if at least one file was uploaded
    if (!empty($upload_errors)) {
        $_SESSION['error'] = "Beberapa file gagal diupload:<br>" . implode("<br>", $upload_errors);
    }
    
    if (empty($uploaded_media)) {
        $error_msg = "Gagal upload media. ";
        if (!empty($upload_errors)) {
            $error_msg .= "Error: " . implode(", ", $upload_errors);
        } else {
            $error_msg .= "Pastikan file yang diupload valid dan tidak melebihi ukuran maksimal.";
        }
        $_SESSION['error'] = $error_msg;
        header("Location: partner_dashboard.php");
        exit;
    }

    // Use first image as main campaign image (for backward compatibility)
    // If no image found, use first media (could be video) or empty
    if (empty($first_image_path)) {
        foreach ($uploaded_media as $media) {
            if ($media['type'] === 'image') {
                $first_image_path = $media['path'];
                break;
            }
        }
        // If still no image, use first media item (video) or empty
        // Campaign will use emoji as fallback in display
        if (empty($first_image_path) && !empty($uploaded_media)) {
            // For campaigns with only videos, we can leave image empty
            // The frontend will use emoji as fallback
            $first_image_path = '';
        }
    }

    /* ===== INSERT DATABASE ===== */
    // Status default di-set 'approved' agar kampanye langsung tampil di halaman utama
    // Escape first_image_path if not empty
    $image_value = !empty($first_image_path) ? "'" . mysqli_real_escape_string($conn, $first_image_path) . "'" : "NULL";
    
    $sql = "INSERT INTO campaigns (
                title,
                emoji,
                image,
                organizer,
                image_link,
                progress,
                target_terkumpul,
                link,
                type,
                donasi_terkumpul,
                status
            ) VALUES (
                '$title',
                '$emoji',
                $image_value,
                '$organizer',
                '$image_link',
                0,
                '$target_terkumpul',
                '$link',
                '$type',
                0,
                'approved'
            )";

    if (mysqli_query($conn, $sql)) {
        $campaign_id = mysqli_insert_id($conn);
        
        // Insert media into campaign_media table
        foreach ($uploaded_media as $media) {
            $media_type = mysqli_real_escape_string($conn, $media['type']);
            $media_path = mysqli_real_escape_string($conn, $media['path']);
            $display_order = intval($media['order']);
            
            $media_sql = "INSERT INTO campaign_media (campaign_id, media_type, media_path, display_order) 
                         VALUES ($campaign_id, '$media_type', '$media_path', $display_order)";
            mysqli_query($conn, $media_sql);
        }
        
        $_SESSION['success'] = "Kampanye berhasil dibuat dengan " . count($uploaded_media) . " media! Menunggu persetujuan admin untuk ditampilkan.";
    } else {
        // Delete uploaded files on error
        foreach ($uploaded_media as $media) {
            if (file_exists($media['path'])) {
                unlink($media['path']);
            }
        }
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    }

    header("Location: partner_dashboard.php");
    exit;
}

/* =========================
   GET STATISTICS
========================= */
$organizer = mysqli_real_escape_string($conn, $partner_nama);

// Get total campaigns
$stats_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM campaigns WHERE organizer='$organizer'");
$stats_total = mysqli_fetch_assoc($stats_query)['total'];

// Get total donations
$stats_donasi = mysqli_query($conn, "SELECT SUM(donasi_terkumpul) as total FROM campaigns WHERE organizer='$organizer'");
$stats_donasi_row = mysqli_fetch_assoc($stats_donasi);
$total_donasi = $stats_donasi_row['total'] ?? 0;

// Get top campaign (most donations)
$top_campaign_query = mysqli_query($conn, "SELECT * FROM campaigns WHERE organizer='$organizer' ORDER BY donasi_terkumpul DESC LIMIT 1");
$top_campaign = mysqli_fetch_assoc($top_campaign_query);

// Get all campaigns
$campaigns_query = mysqli_query($conn, "SELECT * FROM campaigns WHERE organizer='$organizer' ORDER BY id DESC");
$campaigns_list = [];
while ($row = mysqli_fetch_assoc($campaigns_query)) {
    $campaigns_list[] = $row;
}

// Get current home banner (if banners table exists)
$current_banner = null;
$check_banner_table = mysqli_query($conn, "SHOW TABLES LIKE 'banners'");
if ($check_banner_table && mysqli_num_rows($check_banner_table) > 0) {
    $banner_res = mysqli_query($conn, "SELECT * FROM banners ORDER BY `order` ASC LIMIT 1");
    if ($banner_res && mysqli_num_rows($banner_res) > 0) {
        $current_banner = mysqli_fetch_assoc($banner_res);
    }
}

function rupiah($number) {
    return "Rp " . number_format($number, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Partner Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: #f5f5f5;
    padding: 16px;
    line-height: 1.6;
    color: #333;
}

.card {
    background: #fff;
    padding: 20px;
    border-radius: 16px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}

.card h2 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
}

.header-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.logout {
    background: #dc3545;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
}

.logout:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}

.success {
    background: #d1fae5;
    color: #065f46;
}

.error {
    background: #fee2e2;
    color: #7f1d1d;
    white-space: pre-line;
}

input, textarea, select {
    width: 100%;
    padding: 12px;
    margin-top: 8px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s;
}

input:focus, textarea:focus, select:focus {
    outline: none;
    border-color: #17a697;
    box-shadow: 0 0 0 3px rgba(23, 166, 151, 0.1);
}

button[type="submit"] {
    background: #17a697;
    color: #fff;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    margin-top: 15px;
    transition: all 0.3s;
}

button[type="submit"]:hover {
    background: #128c7f;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(23, 166, 151, 0.3);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px;
    border-radius: 12px;
    text-align: center;
}

.stat-card.primary {
    background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    margin: 8px 0 4px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
}

.campaigns-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.campaign-item {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e0e0e0;
    transition: all 0.3s;
}

.campaign-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.campaign-image {
    width: 100%;
    height: 180px;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 64px;
}

.campaign-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.campaign-info h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.campaign-stats {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 8px;
    font-size: 12px;
    color: #666;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: #e0e0e0;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 12px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #17a697 0%, #20c997 100%);
    transition: width 0.3s;
}

.campaign-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-detail, .btn-edit, .btn-delete {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    flex: 1;
    text-align: center;
    min-width: 80px;
}

.btn-detail {
    background: #17a697;
    color: white;
}

.btn-edit {
    background: #ffc107;
    color: #333;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-detail:hover, .btn-edit:hover, .btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

@media (min-width: 768px) {
    body {
        padding: 24px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .campaign-item {
        display: flex;
        gap: 16px;
    }
    
    .campaign-image {
        width: 200px;
        height: 120px;
        flex-shrink: 0;
        margin-bottom: 0;
    }
    
    .campaign-info {
        flex: 1;
    }
    
    .campaign-stats {
        flex-direction: row;
        gap: 16px;
    }
}
</style>
</head>
<body>

<div class="card">
    <div class="header-info">
        <h2>Dashboard Partner</h2>
        <div>
            <span>Selamat datang, <b><?= htmlspecialchars($partner_nama) ?></b></span>
            <a href="partner_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Banner Beranda -->
    <div class="card" style="margin-top: 10px; margin-bottom: 20px;">
        <h2>Banner Beranda</h2>
        <p style="font-size: 13px; color: #555; margin-bottom: 10px;">
            Banner ini akan tampil di bagian paling atas halaman utama, seperti contoh Rumah Zakat. 
            Gunakan gambar ukuran landscape (misal 1200x500) agar tampilan lebih rapi.
        </p>

        <?php if ($current_banner && !empty($current_banner['image'])): ?>
            <div style="margin-bottom: 12px;">
                <div style="border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 100%; max-height: 220px;">
                    <img src="<?= htmlspecialchars($current_banner['image']) ?>" alt="<?= htmlspecialchars($current_banner['title']) ?>" style="width:100%; height:220px; object-fit:cover;">
                </div>
                <div style="margin-top: 8px; font-size: 13px;">
                    <strong><?= htmlspecialchars($current_banner['title']) ?></strong><br>
                    <span style="color:#666;"><?= htmlspecialchars($current_banner['subtitle'] ?? '') ?></span>
                </div>
            </div>
        <?php else: ?>
            <p style="font-size: 13px; color:#888; margin-bottom:10px;">Belum ada banner. Silakan upload banner pertama Anda.</p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="margin-top: 10px;">
            <input type="hidden" name="form_type" value="banner">
            
            <label>Judul Banner</label>
            <input type="text" name="banner_title" placeholder="Contoh: #BikinBahagia - Ayo Berdonasi Sekarang" required>

            <label>Subjudul (opsional)</label>
            <input type="text" name="banner_subtitle" placeholder="Teks pendek tambahan di bawah judul">

            <label>Link ketika banner di-klik (opsional)</label>
            <input type="text" name="banner_link" placeholder="Contoh: https://kolaboraksi.app.rangkiangpedulinegeri.org">

            <label>Gambar Banner</label>
            <input type="file" name="banner_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required>
            <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
                Rekomendasi ukuran: 1200x500 atau proporsi 3:2 / 16:9. Format: JPG, PNG, GIF, atau WEBP.
            </small>

            <button type="submit" style="margin-top:12px;">Simpan Banner</button>
        </form>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-label">Total Kampanye</div>
            <div class="stat-value"><?= $stats_total ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Donasi</div>
            <div class="stat-value"><?= rupiah($total_donasi) ?></div>
        </div>
        <?php if($top_campaign): ?>
        <div class="stat-card">
            <div class="stat-label">Kampanye Teratas</div>
            <div class="stat-value" style="font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($top_campaign['title']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Donasi Teratas</div>
            <div class="stat-value"><?= rupiah($top_campaign['donasi_terkumpul']) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Buat Kampanye Baru</h2>

    <form method="POST" enctype="multipart/form-data">
        <label>Judul Kampanye</label>
        <input type="text" name="title" required>

        <label>Emoji (opsional)</label>
        <input type="text" name="emoji" placeholder="ðŸ“–">

        <label>Deskripsi</label>
        <textarea name="description" rows="4" required></textarea>

        <label>Target Donasi (Rp)</label>
        <input type="number" name="target_terkumpul" min="1000" required>

        <label>Upload Media (Gambar & Video) - Bisa Multiple</label>
        <input type="file" name="media[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo,video/x-matroska" multiple required>
        <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
            <strong>Format yang didukung:</strong><br>
            • Gambar: JPG, PNG, GIF, WEBP (maksimal 10MB per gambar)<br>
            • Video: MP4, WEBM, OGG, MOV, AVI, MKV (maksimal 100MB per video)<br>
            <strong>Catatan:</strong> Pastikan ukuran file tidak melebihi batas maksimal PHP (<?= ini_get('upload_max_filesize') ?>)
        </small>
        
        <label style="margin-top: 16px;">Atau Upload Satu Gambar (Lama)</label>
        <input type="file" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
            Opsi ini untuk kompatibilitas dengan versi lama. Lebih baik gunakan upload multiple di atas.
        </small>

        <label>Link Gambar Eksternal</label>
        <input type="text" name="image_link">

        <label>Link Tambahan</label>
        <input type="text" name="link">

<label>Tipe Kampanye</label>
<select name="type">
    <option value="pendidikan">Pendidikan</option>
    <option value="kesehatan">Kesehatan</option>
    <option value="bencana">Bencana</option>
    <option value="sosial">Sosial</option>
    <option value="masjid">🕌 Masjid</option>
    <option value="rumah_tahfiz">📖 Rumah Tahfiz</option>
    <option value="lainnya">Lainnya</option>
</select>

        <button type="submit">Buat Kampanye</button>
    </form>
</div>

<div class="card">
<h2>Kampanye Anda</h2>
<div class="campaigns-list">
<?php if(count($campaigns_list) > 0): ?>
    <?php foreach($campaigns_list as $c): 
        $target=(int)$c['target_terkumpul'];
        $terkumpul=(int)$c['donasi_terkumpul'];
        $progress=$target>0?min(100, round(($terkumpul/$target)*100)):0;
    ?>
    <div class="campaign-item">
        <div class="campaign-image">
            <?php if(!empty($c['image'])): ?>
                <img src="<?= htmlspecialchars($c['image']) ?>" alt="<?= htmlspecialchars($c['title']) ?>" onerror="this.parentElement.innerHTML='<?= htmlspecialchars($c['emoji'] ?? '💝') ?>'">
            <?php else: ?>
                <?= htmlspecialchars($c['emoji'] ?? '💝') ?>
            <?php endif; ?>
        </div>
        <div class="campaign-info">
            <h3><?= htmlspecialchars($c['title']) ?></h3>
            <div class="campaign-stats">
                <span>Terkumpul: <?= rupiah($terkumpul) ?></span>
                <span>Target: <?= rupiah($target) ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $progress ?>%"></div>
            </div>
            <div class="campaign-actions">
                <a href="kampanye-detail.php?id=<?= $c['id'] ?>" class="btn-detail">Detail</a>
                <a href="edit_campaign.php?id=<?= $c['id'] ?>" class="btn-edit">Edit</a>
                <a href="hapus_campaign.php?id=<?= $c['id'] ?>" class="btn-delete" onclick="return confirm('Hapus kampanye?')">Hapus</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty-state">
        <p>Belum ada kampanye</p>
    </div>
<?php endif; ?>
</div>
</div>

</body>
</html>
