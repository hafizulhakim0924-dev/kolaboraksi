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
// Handle offline donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'offline_donation') {
    // ==== FORM: INPUT DONASI OFFLINE ====
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $donor_name = mysqli_real_escape_string($conn, trim($_POST['donor_name'] ?? ''));
    $donor_email = mysqli_real_escape_string($conn, trim($_POST['donor_email'] ?? ''));
    $donor_phone = mysqli_real_escape_string($conn, trim($_POST['donor_phone'] ?? ''));
    $amount = intval($_POST['amount'] ?? 0);
    $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1' ? 1 : 0;
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));

    // Validasi
    if ($campaign_id <= 0) {
        $_SESSION['error'] = "Silakan pilih kampanye.";
        header("Location: partner_dashboard.php");
        exit;
    }

    if (empty($donor_name)) {
        $_SESSION['error'] = "Nama donatur wajib diisi.";
        header("Location: partner_dashboard.php");
        exit;
    }

    // Email tidak wajib, tapi jika diisi harus valid
    if (!empty($donor_email) && !filter_var($donor_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Email tidak valid.";
        header("Location: partner_dashboard.php");
        exit;
    }
    
    // Jika email kosong, set ke NULL atau string kosong
    if (empty($donor_email)) {
        $donor_email = '';
    }

    if (empty($donor_phone)) {
        $_SESSION['error'] = "Nomor WhatsApp wajib diisi.";
        header("Location: partner_dashboard.php");
        exit;
    }

    // Format phone number
    if (substr($donor_phone, 0, 1) === '0') {
        $donor_phone = '62' . substr($donor_phone, 1);
    } elseif (substr($donor_phone, 0, 2) !== '62') {
        $donor_phone = '62' . $donor_phone;
    }

    if ($amount < 10000) {
        $_SESSION['error'] = "Minimal donasi adalah Rp 10.000.";
        header("Location: partner_dashboard.php");
        exit;
    }

    // Cek apakah kampanye ada
    // Karena dropdown hanya menampilkan kampanye milik partner, cukup cek apakah kampanye ada
    $check_stmt = $conn->prepare("SELECT id, organizer FROM campaigns WHERE id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $campaign_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if (!$check_result || $check_result->num_rows == 0) {
            $check_stmt->close();
            $_SESSION['error'] = "Kampanye tidak ditemukan.";
            header("Location: partner_dashboard.php");
            exit;
        }
        
        // Optional: Verifikasi organizer (tapi tidak wajib karena dropdown sudah filter)
        $campaign_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // Verifikasi organizer dengan case-insensitive dan trim whitespace
        if (strtolower(trim($campaign_data['organizer'])) !== strtolower(trim($organizer))) {
            // Jika tidak match, tetap izinkan karena mungkin ada perbedaan kecil (spasi, case)
            // Tapi log untuk debugging
            error_log("Organizer mismatch - Campaign: '" . $campaign_data['organizer'] . "', Partner: '" . $organizer . "'");
        }
    } else {
        // Fallback ke query biasa jika prepared statement gagal
        $check_campaign = mysqli_query($conn, "SELECT id FROM campaigns WHERE id = $campaign_id");
        if (!$check_campaign || mysqli_num_rows($check_campaign) == 0) {
            $_SESSION['error'] = "Kampanye tidak ditemukan.";
            header("Location: partner_dashboard.php");
            exit;
        }
    }

    // Insert donasi offline ke database
    $fee_total = 0; // Offline tidak ada fee
    $total_amount = $amount;
    $payment_method = 'OFFLINE';
    $payment_channel = 'Donasi Offline';
    $status = 'PAID'; // Langsung dibayar karena offline
    $merchant_ref = 'OFF' . time() . rand(1000, 9999);

    // Pastikan tabel donations ada
    $createDonationsTable = "CREATE TABLE IF NOT EXISTS `donations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `campaign_id` int(11) NOT NULL,
        `donor_name` varchar(255) NOT NULL,
        `donor_email` varchar(255) DEFAULT NULL,
        `donor_phone` varchar(50) NOT NULL,
        `amount` int(11) NOT NULL,
        `fee_total` int(11) DEFAULT 0,
        `total_amount` int(11) NOT NULL,
        `payment_method` varchar(50) DEFAULT NULL,
        `payment_channel` varchar(100) DEFAULT NULL,
        `tripay_reference` varchar(255) DEFAULT NULL,
        `tripay_merchant_ref` varchar(255) DEFAULT NULL,
        `status` enum('UNPAID','PAID','EXPIRED','FAILED') DEFAULT 'UNPAID',
        `payment_url` text DEFAULT NULL,
        `qr_url` text DEFAULT NULL,
        `is_anonymous` tinyint(1) DEFAULT 0,
        `message` text DEFAULT NULL,
        `expired_at` datetime DEFAULT NULL,
        `paid_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `campaign_id` (`campaign_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($conn, $createDonationsTable);

    // Pastikan kolom paid_at ada (untuk tabel yang sudah ada)
    $checkPaidAtColumn = mysqli_query($conn, "SHOW COLUMNS FROM donations LIKE 'paid_at'");
    if (!$checkPaidAtColumn || mysqli_num_rows($checkPaidAtColumn) == 0) {
        mysqli_query($conn, "ALTER TABLE donations ADD COLUMN `paid_at` datetime DEFAULT NULL AFTER `expired_at`");
    }

    // Pastikan kolom donor_email bisa NULL (untuk tabel yang sudah ada)
    $checkEmailColumn = mysqli_query($conn, "SHOW COLUMNS FROM donations WHERE Field = 'donor_email'");
    if ($checkEmailColumn && mysqli_num_rows($checkEmailColumn) > 0) {
        $emailColumn = mysqli_fetch_assoc($checkEmailColumn);
        if ($emailColumn['Null'] === 'NO') {
            mysqli_query($conn, "ALTER TABLE donations MODIFY COLUMN `donor_email` varchar(255) DEFAULT NULL");
        }
    }
    
    // Insert donasi
    // Jika email kosong, set ke string kosong (akan disimpan sebagai NULL di database jika kolom bisa NULL)
    $donor_email_final = empty($donor_email) ? '' : $donor_email;
    
    $stmt = $conn->prepare("INSERT INTO donations (campaign_id, donor_name, donor_email, donor_phone, amount, fee_total, total_amount, payment_method, payment_channel, tripay_reference, tripay_merchant_ref, status, payment_url, qr_url, is_anonymous, message, expired_at, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NULL, NULL, ?, ?, NULL, NOW())");
    
    if ($stmt) {
        $stmt->bind_param("isssiiisssisi", 
            $campaign_id,
            $donor_name,
            $donor_email_final,
            $donor_phone,
            $amount,
            $fee_total,
            $total_amount,
            $payment_method,
            $payment_channel,
            $merchant_ref,
            $status,
            $is_anonymous,
            $message
        );

        if ($stmt->execute()) {
            // Update donasi_terkumpul di tabel campaigns
            $update_campaign = mysqli_query($conn, "UPDATE campaigns SET donasi_terkumpul = donasi_terkumpul + $amount WHERE id = $campaign_id");
            
            if ($update_campaign) {
                $_SESSION['success'] = "Donasi offline berhasil ditambahkan! Donatur: " . htmlspecialchars($donor_name) . " - Jumlah: " . rupiah($amount);
            } else {
                $_SESSION['error'] = "Donasi berhasil disimpan, namun gagal update total kampanye: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "Gagal menyimpan donasi: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Gagal menyiapkan query: " . mysqli_error($conn);
    }

    header("Location: partner_dashboard.php");
    exit;
}

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

    // Pastikan kolom 'link' ada di tabel banners (untuk tabel yang sudah ada)
    $checkLinkColumn = mysqli_query($conn, "SHOW COLUMNS FROM banners LIKE 'link'");
    if (!$checkLinkColumn || mysqli_num_rows($checkLinkColumn) == 0) {
        mysqli_query($conn, "ALTER TABLE banners ADD COLUMN `link` varchar(255) DEFAULT NULL AFTER `image`");
    }

    // Hapus banner lama sebelum menyimpan yang baru (hanya 1 banner aktif)
    // Ambil path gambar banner lama untuk dihapus dari server
    $old_banners = mysqli_query($conn, "SELECT id, image FROM banners");
    $old_images = [];
    if ($old_banners) {
        while ($old_row = mysqli_fetch_assoc($old_banners)) {
            if (!empty($old_row['image']) && file_exists($old_row['image'])) {
                $old_images[] = $old_row['image'];
            }
        }
    }
    
    // Hapus semua banner lama dari database
    mysqli_query($conn, "DELETE FROM banners");
    
    // Hapus file gambar banner lama dari server (kecuali jika sama dengan yang baru)
    foreach ($old_images as $old_img) {
        if ($old_img != $banner_file && file_exists($old_img)) {
            @unlink($old_img);
        }
    }

    // Simpan banner baru dengan order = 1
    $order = 1;
    $stmtBanner = $conn->prepare("INSERT INTO banners (title, subtitle, image, link, `order`) VALUES (?, ?, ?, ?, ?)");
    if ($stmtBanner) {
        $stmtBanner->bind_param("ssssi", $title, $subtitle, $banner_file, $link, $order);
        if ($stmtBanner->execute()) {
            $_SESSION['success'] = "Banner beranda berhasil disimpan dan menggantikan banner lama.";
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
   AJAX: GET DONORS
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_donors') {
    header('Content-Type: application/json; charset=utf-8');
    
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    
    if ($campaign_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid campaign ID']);
        exit;
    }
    
    // Verifikasi kampanye milik partner ini
    $check_stmt = $conn->prepare("SELECT id, organizer FROM campaigns WHERE id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $campaign_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if (!$check_result || $check_result->num_rows == 0) {
            $check_stmt->close();
            echo json_encode(['success' => false, 'message' => 'Kampanye tidak ditemukan']);
            exit;
        }
        
        $campaign_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // Verifikasi organizer (case-insensitive)
        if (strtolower(trim($campaign_data['organizer'])) !== strtolower(trim($organizer))) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke kampanye ini']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    // Get donations
    $donors_stmt = $conn->prepare("SELECT id, donor_name, donor_email, donor_phone, amount, message, is_anonymous, status, created_at, paid_at FROM donations WHERE campaign_id = ? ORDER BY created_at DESC");
    
    if ($donors_stmt) {
        $donors_stmt->bind_param("i", $campaign_id);
        $donors_stmt->execute();
        $donors_result = $donors_stmt->get_result();
        
        $donors = [];
        $total_donors = 0;
        $total_amount = 0;
        $paid_amount = 0;
        
        while ($row = $donors_result->fetch_assoc()) {
            $donors[] = $row;
            $total_donors++;
            $total_amount += intval($row['amount']);
            if ($row['status'] === 'PAID') {
                $paid_amount += intval($row['amount']);
            }
        }
        
        $donors_stmt->close();
        
        echo json_encode([
            'success' => true,
            'donors' => $donors,
            'summary' => [
                'total_donors' => $total_donors,
                'total_amount' => $total_amount,
                'paid_amount' => $paid_amount
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data donatur']);
    }
    
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
try {
    $check_banner_table = mysqli_query($conn, "SHOW TABLES LIKE 'banners'");
    if ($check_banner_table && mysqli_num_rows($check_banner_table) > 0) {
        // Pastikan kolom 'link' ada
        $checkLinkColumn = mysqli_query($conn, "SHOW COLUMNS FROM banners LIKE 'link'");
        if (!$checkLinkColumn || mysqli_num_rows($checkLinkColumn) == 0) {
            mysqli_query($conn, "ALTER TABLE banners ADD COLUMN `link` varchar(255) DEFAULT NULL AFTER `image`");
        }
        
        $banner_res = mysqli_query($conn, "SELECT id, title, subtitle, image, COALESCE(link, '#') as link, `order` FROM banners ORDER BY created_at DESC, id DESC LIMIT 1");
        if ($banner_res && mysqli_num_rows($banner_res) > 0) {
            $current_banner = mysqli_fetch_assoc($banner_res);
        }
    }
} catch (Exception $e) {
    // Jika error, biarkan null
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

.btn-detail, .btn-edit, .btn-delete, .btn-donatur {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    flex: 1;
    text-align: center;
    min-width: 80px;
    border: none;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.btn-detail {
    background: #17a697;
    color: white;
}

.btn-donatur {
    background: #6c757d;
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

.btn-detail:hover, .btn-edit:hover, .btn-delete:hover, .btn-donatur:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

/* Modal Donatur */
.donor-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    overflow-y: auto;
    animation: fadeIn 0.3s ease-in-out;
}

.donor-modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.donor-modal-content {
    background: #fff;
    border-radius: 16px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease-in-out;
}

@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.donor-modal-header {
    background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
    color: white;
    padding: 20px 24px;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.donor-modal-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
}

.donor-modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.donor-modal-close:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

.donor-modal-body {
    padding: 24px;
}

.donors-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    font-size: 14px;
}

.donors-table thead {
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 5;
}

.donors-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.donors-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    color: #555;
}

.donors-table tbody tr {
    transition: all 0.2s;
}

.donors-table tbody tr:hover {
    background: #f8f9fa;
}

.donors-table tbody tr:last-child td {
    border-bottom: none;
}

.donor-name {
    font-weight: 600;
    color: #333;
}

.donor-amount {
    font-weight: 700;
    color: #17a697;
    font-size: 15px;
}

.donor-anonymous {
    color: #999;
    font-style: italic;
}

.donor-message {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    color: #666;
}

.donor-date {
    font-size: 12px;
    color: #999;
}

.donor-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.donor-status.paid {
    background: #d1fae5;
    color: #065f46;
}

.donor-status.unpaid {
    background: #fee2e2;
    color: #991b1b;
}

.donor-summary {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.donor-summary-item {
    text-align: center;
}

.donor-summary-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.donor-summary-value {
    font-size: 18px;
    font-weight: 700;
    color: #17a697;
}

.loading-donors {
    text-align: center;
    padding: 40px;
    color: #999;
}

.loading-donors .spinner {
    border: 3px solid #f0f0f0;
    border-top: 3px solid #17a697;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
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

@media (max-width: 768px) {
    .donors-table {
        font-size: 12px;
    }
    
    .donors-table th,
    .donors-table td {
        padding: 8px 6px;
    }
    
    .donor-message {
        max-width: 120px;
    }
    
    .donor-summary {
        flex-direction: column;
        text-align: center;
    }
    
    .donor-modal-content {
        margin: 10px;
        max-height: 95vh;
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
    <h2>Input Donasi Offline</h2>
    <p style="font-size: 13px; color: #555; margin-bottom: 15px;">
        Gunakan form ini untuk mencatat donasi yang diterima secara offline (tunai, transfer manual, dll). 
        Donasi akan langsung masuk ke database dan memperbarui total donasi kampanye.
    </p>

    <form method="POST">
        <input type="hidden" name="form_type" value="offline_donation">
        
        <label>Pilih Kampanye <span style="color: red;">*</span></label>
        <select name="campaign_id" required>
            <option value="">-- Pilih Kampanye --</option>
            <?php if(count($campaigns_list) > 0): ?>
                <?php foreach($campaigns_list as $camp): ?>
                    <option value="<?= $camp['id'] ?>"><?= htmlspecialchars($camp['title']) ?> (<?= rupiah($camp['donasi_terkumpul']) ?> / <?= rupiah($camp['target_terkumpul']) ?>)</option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="" disabled>Belum ada kampanye. Buat kampanye terlebih dahulu.</option>
            <?php endif; ?>
        </select>

        <label>Nama Donatur <span style="color: red;">*</span></label>
        <input type="text" name="donor_name" placeholder="Masukkan nama lengkap donatur" required>

        <label>Email Donatur (Opsional)</label>
        <input type="email" name="donor_email" placeholder="email@example.com">

        <label>Nomor WhatsApp <span style="color: red;">*</span></label>
        <input type="tel" name="donor_phone" placeholder="08xxxxxxxxxx" required>
        <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
            Format: 08xxxxxxxxxx (akan otomatis dikonversi ke format internasional)
        </small>

        <label style="margin-top: 12px;">
            <input type="checkbox" name="is_anonymous" value="1" style="width: auto; margin-right: 8px;">
            Sembunyikan nama donatur (Anonim)
        </label>

        <label style="margin-top: 12px;">Jumlah Donasi (Rp) <span style="color: red;">*</span></label>
        <input type="number" name="amount" min="10000" step="1000" placeholder="Minimal Rp 10.000" required>
        <small style="color: #666; font-size: 12px; display: block; margin-top: 4px;">
            Minimal donasi: Rp 10.000
        </small>

        <label style="margin-top: 12px;">Pesan & Doa (Opsional)</label>
        <textarea name="message" rows="3" placeholder="Pesan atau doa dari donatur..."></textarea>

        <button type="submit">Simpan Donasi Offline</button>
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
                <button type="button" class="btn-donatur" onclick="showDonors(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')">Lihat Donatur</button>
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

<!-- Modal Donatur -->
<div id="donorModal" class="donor-modal" onclick="closeDonorModal()">
    <div class="donor-modal-content" onclick="event.stopPropagation()">
        <div class="donor-modal-header">
            <h3 class="donor-modal-title" id="donorModalTitle">Daftar Donatur</h3>
            <button class="donor-modal-close" onclick="closeDonorModal()">&times;</button>
        </div>
        <div class="donor-modal-body" id="donorModalBody">
            <div class="loading-donors">
                <div class="spinner"></div>
                <p>Memuat data donatur...</p>
            </div>
        </div>
    </div>
</div>

<script>
function showDonors(campaignId, campaignTitle) {
    const modal = document.getElementById('donorModal');
    const modalBody = document.getElementById('donorModalBody');
    const modalTitle = document.getElementById('donorModalTitle');
    
    modalTitle.textContent = 'Daftar Donatur - ' + campaignTitle;
    modal.classList.add('show');
    modalBody.innerHTML = '<div class="loading-donors"><div class="spinner"></div><p>Memuat data donatur...</p></div>';
    
    // Fetch data donatur via AJAX
    fetch('?ajax=get_donors&campaign_id=' + campaignId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDonorsTable(data.donors, data.summary);
            } else {
                modalBody.innerHTML = '<div class="alert error">' + (data.message || 'Gagal memuat data donatur') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="alert error">Terjadi kesalahan saat memuat data donatur.</div>';
        });
}

function renderDonorsTable(donors, summary) {
    const modalBody = document.getElementById('donorModalBody');
    
    if (!donors || donors.length === 0) {
        modalBody.innerHTML = '<div class="empty-state"><p>Belum ada donatur untuk kampanye ini.</p></div>';
        return;
    }
    
    let html = '<div class="donor-summary">';
    html += '<div class="donor-summary-item"><div class="donor-summary-label">Total Donatur</div><div class="donor-summary-value">' + summary.total_donors + '</div></div>';
    html += '<div class="donor-summary-item"><div class="donor-summary-label">Total Donasi</div><div class="donor-summary-value">' + formatRupiah(summary.total_amount) + '</div></div>';
    html += '<div class="donor-summary-item"><div class="donor-summary-label">Donasi Terbayar</div><div class="donor-summary-value">' + formatRupiah(summary.paid_amount) + '</div></div>';
    html += '</div>';
    
    html += '<table class="donors-table">';
    html += '<thead><tr>';
    html += '<th style="width: 40px;">No</th>';
    html += '<th>Nama Donatur</th>';
    html += '<th>Email</th>';
    html += '<th>WhatsApp</th>';
    html += '<th style="text-align: right;">Jumlah Donasi</th>';
    html += '<th>Pesan & Doa</th>';
    html += '<th>Tanggal</th>';
    html += '<th>Status</th>';
    html += '</tr></thead>';
    html += '<tbody>';
    
    donors.forEach((donor, index) => {
        const donorName = donor.is_anonymous == 1 ? '<span class="donor-anonymous">Donatur Anonim</span>' : '<span class="donor-name">' + escapeHtml(donor.donor_name) + '</span>';
        const donorEmail = donor.donor_email ? escapeHtml(donor.donor_email) : '<span style="color: #999;">-</span>';
        // Format nomor WhatsApp: jika dimulai dengan 62, ubah ke 0
        let donorPhone = donor.donor_phone ? escapeHtml(donor.donor_phone) : '<span style="color: #999;">-</span>';
        if (donor.donor_phone && donor.donor_phone.startsWith('62')) {
            donorPhone = '0' + escapeHtml(donor.donor_phone.substring(2));
        }
        const donorMessage = donor.message ? '<span class="donor-message" title="' + escapeHtml(donor.message) + '">' + escapeHtml(donor.message) + '</span>' : '<span style="color: #999;">-</span>';
        const donorDate = formatDate(donor.created_at);
        const donorStatus = donor.status === 'PAID' ? '<span class="donor-status paid">Lunas</span>' : '<span class="donor-status unpaid">Belum Lunas</span>';
        const donorAmount = formatRupiah(donor.amount);
        
        html += '<tr>';
        html += '<td>' + (index + 1) + '</td>';
        html += '<td>' + donorName + '</td>';
        html += '<td>' + donorEmail + '</td>';
        html += '<td>' + donorPhone + '</td>';
        html += '<td style="text-align: right;"><span class="donor-amount">' + donorAmount + '</span></td>';
        html += '<td>' + donorMessage + '</td>';
        html += '<td><span class="donor-date">' + donorDate + '</span></td>';
        html += '<td>' + donorStatus + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    modalBody.innerHTML = html;
}

function closeDonorModal() {
    document.getElementById('donorModal').classList.remove('show');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatRupiah(amount) {
    return 'Rp ' + amount.toLocaleString('id-ID');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
}

// Close modal when clicking outside
document.getElementById('donorModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDonorModal();
    }
});
</script>

</body>
</html>
