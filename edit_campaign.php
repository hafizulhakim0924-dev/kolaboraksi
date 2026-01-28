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
   GET CAMPAIGN DATA
========================= */
$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($campaign_id <= 0) {
    $_SESSION['error'] = "ID kampanye tidak valid";
    header("Location: partner_dashboard.php");
    exit;
}

// Get campaign data and verify ownership
$organizer = mysqli_real_escape_string($conn, $partner_nama);
$campaign_query = mysqli_query($conn, "SELECT * FROM campaigns WHERE id = $campaign_id AND organizer = '$organizer'");
$campaign = mysqli_fetch_assoc($campaign_query);

if (!$campaign) {
    $_SESSION['error'] = "Kampanye tidak ditemukan atau Anda tidak memiliki akses untuk mengedit kampanye ini";
    header("Location: partner_dashboard.php");
    exit;
}

// Get existing media
$media_query = mysqli_query($conn, "SELECT * FROM campaign_media WHERE campaign_id = $campaign_id ORDER BY display_order ASC");
$existing_media = [];
while ($row = mysqli_fetch_assoc($media_query)) {
    $existing_media[] = $row;
}

/* =========================
   HANDLE UPDATE FORM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_campaign'])) {
    
    $title            = mysqli_real_escape_string($conn, $_POST['title']);
    $emoji            = mysqli_real_escape_string($conn, $_POST['emoji'] ?? '💝');
    $description      = mysqli_real_escape_string($conn, $_POST['description']);
    $target_terkumpul = (int) $_POST['target_terkumpul'];
    $image_link       = mysqli_real_escape_string($conn, $_POST['image_link'] ?? '');
    $link             = mysqli_real_escape_string($conn, $_POST['link'] ?? '#');
    $type             = mysqli_real_escape_string($conn, $_POST['type'] ?? 'lainnya');
    
    // Handle media deletion
    if (isset($_POST['delete_media']) && is_array($_POST['delete_media'])) {
        foreach ($_POST['delete_media'] as $media_id) {
            $media_id = intval($media_id);
            // Get media path before deletion
            $media_info = mysqli_query($conn, "SELECT media_path FROM campaign_media WHERE id = $media_id AND campaign_id = $campaign_id");
            if ($media_row = mysqli_fetch_assoc($media_info)) {
                // Delete file from server
                if (file_exists($media_row['media_path'])) {
                    unlink($media_row['media_path']);
                }
            }
            // Delete from database
            mysqli_query($conn, "DELETE FROM campaign_media WHERE id = $media_id AND campaign_id = $campaign_id");
        }
    }
    
    // Handle media reordering
    if (isset($_POST['media_order']) && is_array($_POST['media_order'])) {
        foreach ($_POST['media_order'] as $order => $media_id) {
            $media_id = intval($media_id);
            $order = intval($order);
            mysqli_query($conn, "UPDATE campaign_media SET display_order = $order WHERE id = $media_id AND campaign_id = $campaign_id");
        }
    }
    
    /* ===== UPLOAD NEW MEDIA (IMAGES & VIDEOS) ===== */
    $uploaded_media = [];
    $first_image_path = '';
    $upload_errors = [];
    
    // Check if new media is being uploaded
    $has_new_media = false;
    if (isset($_FILES['media']) && !empty($_FILES['media'])) {
        if (isset($_FILES['media']['name']) && is_array($_FILES['media']['name'])) {
            foreach ($_FILES['media']['error'] as $error) {
                if ($error === UPLOAD_ERR_OK) {
                    $has_new_media = true;
                    break;
                }
            }
        } elseif (isset($_FILES['media']['name']) && !empty($_FILES['media']['name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $has_new_media = true;
        }
    }
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $has_new_media = true;
    }
    
    if ($has_new_media) {
        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true);
        }
        
        // Handle multiple media files
        if (isset($_FILES['media']) && !empty($_FILES['media'])) {
            if (isset($_FILES['media']['name']) && is_array($_FILES['media']['name'])) {
                $file_count = count($_FILES['media']['name']);
            } elseif (isset($_FILES['media']['name']) && !empty($_FILES['media']['name'])) {
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
                    
                    if ($file_error !== UPLOAD_ERR_OK) {
                        if ($file_error === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        $upload_errors[] = $_FILES['media']['name'][$i] . ": Error upload (code: $file_error)";
                        continue;
                    }
                    
                    $file_name = $_FILES['media']['name'][$i];
                    $file_tmp = $_FILES['media']['tmp_name'][$i];
                    $file_size = $_FILES['media']['size'][$i];
                    
                    if (!is_uploaded_file($file_tmp)) {
                        $upload_errors[] = $file_name . ": File tidak valid";
                        continue;
                    }
                    
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $allowed_videos = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
                    $media_type = null;
                    
                    if (in_array($ext, $allowed_images)) {
                        $media_type = 'image';
                        if ($file_size > 10 * 1024 * 1024) {
                            $upload_errors[] = $file_name . ": Gambar terlalu besar. Maksimal 10MB";
                            continue;
                        }
                    } elseif (in_array($ext, $allowed_videos)) {
                        $media_type = 'video';
                        if ($file_size > 100 * 1024 * 1024) {
                            $upload_errors[] = $file_name . ": Video terlalu besar. Maksimal 100MB";
                            continue;
                        }
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime_type = finfo_file($finfo, $file_tmp);
                            finfo_close($finfo);
                            
                            $allowed_video_mimes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'application/octet-stream'];
                            if (!empty($mime_type) && !in_array($mime_type, $allowed_video_mimes) && strpos($mime_type, 'video/') !== 0) {
                                if (strpos($mime_type, 'image/') === 0 || strpos($mime_type, 'text/') === 0) {
                                    $upload_errors[] = $file_name . ": Format video tidak valid atau file rusak (MIME: $mime_type)";
                                    continue;
                                }
                            }
                        }
                    } else {
                        $upload_errors[] = $file_name . ": Format file tidak diizinkan";
                        continue;
                    }
                    
                    $media_name = time() . '_' . uniqid() . '_' . $i . '.' . $ext;
                    $media_path = 'uploads/' . $media_name;
                    
                    if (move_uploaded_file($file_tmp, $media_path)) {
                        if (file_exists($media_path) && filesize($media_path) > 0) {
                            $uploaded_media[] = [
                                'type' => $media_type,
                                'path' => $media_path,
                                'order' => count($existing_media) + count($uploaded_media)
                            ];
                            
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
                        $upload_errors[] = $file_name . ": Gagal memindahkan file";
                    }
                }
            }
        }
        
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
                            'order' => count($existing_media) + count($uploaded_media)
                        ];
                    }
                }
            }
        }
        
        // Insert new media into database
        foreach ($uploaded_media as $media) {
            $media_type = mysqli_real_escape_string($conn, $media['type']);
            $media_path = mysqli_real_escape_string($conn, $media['path']);
            $display_order = intval($media['order']);
            
            $media_sql = "INSERT INTO campaign_media (campaign_id, media_type, media_path, display_order) 
                         VALUES ($campaign_id, '$media_type', '$media_path', $display_order)";
            mysqli_query($conn, $media_sql);
        }
    }
    
    // Update main campaign image if new image uploaded
    if (!empty($first_image_path)) {
        $image_value = "'" . mysqli_real_escape_string($conn, $first_image_path) . "'";
    } else {
        // Keep existing image if no new image uploaded
        $image_value = !empty($campaign['image']) ? "'" . mysqli_real_escape_string($conn, $campaign['image']) . "'" : "NULL";
    }
    
    // Update campaign data
    // Check if description column exists, if not, don't include it
    $sql = "UPDATE campaigns SET
                title = '$title',
                emoji = '$emoji',
                image = $image_value,
                image_link = '$image_link',
                target_terkumpul = $target_terkumpul,
                link = '$link',
                type = '$type'";
    
    // Try to add description if column exists (will be handled gracefully if it doesn't)
    $check_desc = mysqli_query($conn, "SHOW COLUMNS FROM campaigns LIKE 'description'");
    if (mysqli_num_rows($check_desc) > 0) {
        $sql .= ", description = '$description'";
    }
    
    $sql .= " WHERE id = $campaign_id AND organizer = '$organizer'";
    
    if (mysqli_query($conn, $sql)) {
        if (!empty($upload_errors)) {
            $_SESSION['success'] = "Kampanye berhasil diperbarui, namun beberapa file gagal diupload:<br>" . implode("<br>", $upload_errors);
        } else {
            $_SESSION['success'] = "Kampanye berhasil diperbarui!";
        }
    } else {
        // Delete uploaded files on error
        foreach ($uploaded_media as $media) {
            if (file_exists($media['path'])) {
                unlink($media['path']);
            }
        }
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    }
    
    header("Location: edit_campaign.php?id=" . $campaign_id);
    exit;
}

// Get updated campaign data
$campaign_query = mysqli_query($conn, "SELECT * FROM campaigns WHERE id = $campaign_id AND organizer = '$organizer'");
$campaign = mysqli_fetch_assoc($campaign_query);

// Get updated media
$media_query = mysqli_query($conn, "SELECT * FROM campaign_media WHERE campaign_id = $campaign_id ORDER BY display_order ASC");
$existing_media = [];
while ($row = mysqli_fetch_assoc($media_query)) {
    $existing_media[] = $row;
}

function rupiah($number) {
    return "Rp " . number_format($number, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit Kampanye</title>
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
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.card h2 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
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

label {
    display: block;
    margin-top: 16px;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
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

.btn-back {
    display: inline-block;
    background: #6c757d;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.btn-back:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.media-preview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.media-item {
    position: relative;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    background: #f8f9fa;
}

.media-item img, .media-item video {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}

.media-item .media-controls {
    padding: 8px;
    background: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.media-item .media-type {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
}

.media-item input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.media-item label {
    margin: 0;
    font-size: 12px;
    cursor: pointer;
}

small {
    color: #666;
    font-size: 12px;
    display: block;
    margin-top: 4px;
}
</style>
</head>
<body>

<a href="partner_dashboard.php" class="btn-back">← Kembali ke Dashboard</a>

<div class="card">
    <h2>Edit Kampanye</h2>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="update_campaign" value="1">
        
        <label>Judul Kampanye</label>
        <input type="text" name="title" value="<?= htmlspecialchars($campaign['title']) ?>" required>

        <label>Emoji (opsional)</label>
        <input type="text" name="emoji" value="<?= htmlspecialchars($campaign['emoji'] ?? '💝') ?>" placeholder="💝">

        <label>Deskripsi</label>
        <textarea name="description" rows="4"><?= htmlspecialchars($campaign['description'] ?? '') ?></textarea>
        <small>Jika kolom description tidak ada di database, field ini akan diabaikan</small>

        <label>Target Donasi (Rp)</label>
        <input type="number" name="target_terkumpul" value="<?= $campaign['target_terkumpul'] ?>" min="1000" required>

        <label>Media yang Sudah Ada</label>
        <?php if (count($existing_media) > 0): ?>
            <div class="media-preview">
                <?php foreach ($existing_media as $media): ?>
                    <div class="media-item">
                        <?php if ($media['media_type'] === 'image'): ?>
                            <img src="<?= htmlspecialchars($media['media_path']) ?>" alt="Media" onerror="this.parentElement.innerHTML='<div style=\'padding:20px;text-align:center;\'>Gambar tidak ditemukan</div>'">
                        <?php else: ?>
                            <video src="<?= htmlspecialchars($media['media_path']) ?>" controls style="width:100%;height:150px;"></video>
                        <?php endif; ?>
                        <div class="media-controls">
                            <span class="media-type"><?= $media['media_type'] ?></span>
                            <label>
                                <input type="checkbox" name="delete_media[]" value="<?= $media['id'] ?>">
                                Hapus
                            </label>
                        </div>
                        <input type="hidden" name="media_order[]" value="<?= $media['id'] ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <small>Centang "Hapus" untuk menghapus media yang tidak diinginkan</small>
        <?php else: ?>
            <p style="color: #999; font-size: 14px;">Belum ada media</p>
        <?php endif; ?>

        <label style="margin-top: 20px;">Tambah Media Baru (Gambar & Video) - Bisa Multiple</label>
        <input type="file" name="media[]" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo,video/x-matroska" multiple>
        <small>
            <strong>Format yang didukung:</strong><br>
            • Gambar: JPG, PNG, GIF, WEBP (maksimal 10MB per gambar)<br>
            • Video: MP4, WEBM, OGG, MOV, AVI, MKV (maksimal 100MB per video)
        </small>
        
        <label style="margin-top: 16px;">Atau Upload Satu Gambar (Lama)</label>
        <input type="file" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        <small>Opsi ini untuk kompatibilitas dengan versi lama</small>

        <label>Link Gambar Eksternal</label>
        <input type="text" name="image_link" value="<?= htmlspecialchars($campaign['image_link'] ?? '') ?>">

        <label>Link Tambahan</label>
        <input type="text" name="link" value="<?= htmlspecialchars($campaign['link'] ?? '#') ?>">

        <label>Tipe Kampanye</label>
        <select name="type">
            <option value="pendidikan" <?= ($campaign['type'] ?? '') === 'pendidikan' ? 'selected' : '' ?>>Pendidikan</option>
            <option value="kesehatan" <?= ($campaign['type'] ?? '') === 'kesehatan' ? 'selected' : '' ?>>Kesehatan</option>
            <option value="bencana" <?= ($campaign['type'] ?? '') === 'bencana' ? 'selected' : '' ?>>Bencana</option>
            <option value="sosial" <?= ($campaign['type'] ?? '') === 'sosial' ? 'selected' : '' ?>>Sosial</option>
            <option value="masjid" <?= ($campaign['type'] ?? '') === 'masjid' ? 'selected' : '' ?>>🕌 Masjid</option>
            <option value="rumah_tahfiz" <?= ($campaign['type'] ?? '') === 'rumah_tahfiz' ? 'selected' : '' ?>>📖 Rumah Tahfiz</option>
            <option value="lainnya" <?= ($campaign['type'] ?? '') === 'lainnya' || empty($campaign['type']) ? 'selected' : '' ?>>Lainnya</option>
        </select>

        <button type="submit">Perbarui Kampanye</button>
    </form>
</div>

</body>
</html>

