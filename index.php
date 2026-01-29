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

// Get all data from database
function getAllCampaignData($conn) {
    $data = array();

    // Get latest campaigns - hanya yang approved atau NULL/kosong (untuk backward compatibility)
   $sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul FROM campaigns WHERE (status = 'approved' OR status IS NULL OR status = '') ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($sql);
    $data['latest_campaigns'] = array();
    while ($row = $result->fetch_assoc()) {
        // Hitung progress
        $row['progress'] = calculateProgress($row['donasi_terkumpul'], $row['target_terkumpul']);
        $row['donasi_formatted'] = rupiah($row['donasi_terkumpul']);
        $data['latest_campaigns'][] = $row;
    }

    // Get event recommendations - hanya yang approved
   $sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul FROM campaigns WHERE (status = 'approved' OR status IS NULL OR status = '') ORDER BY RAND() LIMIT 10";
    $result = $conn->query($sql);
    $data['event_recommendations'] = array();
    while ($row = $result->fetch_assoc()) {
        $row['progress'] = calculateProgress($row['donasi_terkumpul'], $row['target_terkumpul']);
        $row['donasi_formatted'] = rupiah($row['donasi_terkumpul']);
        $data['event_recommendations'][] = $row;
    }
// Get Masjid & Rumah Tahfiz - hanya yang approved
$sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul FROM campaigns WHERE type IN ('masjid', 'rumah_tahfiz') AND (status = 'approved' OR status IS NULL OR status = '') ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($sql);
$data['masjid_tahfiz'] = array();
while ($row = $result->fetch_assoc()) {
    $row['progress'] = calculateProgress($row['donasi_terkumpul'], $row['target_terkumpul']);
    $row['donasi_formatted'] = rupiah($row['donasi_terkumpul']);
    $data['masjid_tahfiz'][] = $row;
}
    // Get favorite categories - hanya yang approved
$sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul FROM campaigns WHERE (status = 'approved' OR status IS NULL OR status = '') ORDER BY CAST(donasi_terkumpul AS UNSIGNED) DESC LIMIT 10";
    $result = $conn->query($sql);
    $data['favorite_categories'] = array();
    while ($row = $result->fetch_assoc()) {
        $row['progress'] = calculateProgress($row['donasi_terkumpul'], $row['target_terkumpul']);
        $row['donasi_formatted'] = rupiah($row['donasi_terkumpul']);
        $data['favorite_categories'][] = $row;
    }

    // Get recommendations (mix of all types, random) - hanya yang approved
    $sql = "SELECT id, title, emoji, image, organizer, target_terkumpul, donasi_terkumpul FROM campaigns WHERE (status = 'approved' OR status IS NULL OR status = '') ORDER BY RAND() LIMIT 10";
    $result = $conn->query($sql);
    $data['recommendations'] = array();
    while ($row = $result->fetch_assoc()) {
        $row['progress'] = calculateProgress($row['donasi_terkumpul'], $row['target_terkumpul']);
        $row['donasi_formatted'] = rupiah($row['donasi_terkumpul']);
        $data['recommendations'][] = $row;
    }

    // Get donor prayers
    $data['donor_prayers'] = array(
        array(
            'donor_name' => 'Ahmad Rizki',
            'donor_image' => null,
            'prayer_text' => 'Semoga amal ibadah kita diterima oleh Allah SWT, dan semoga mereka yang menerima bantuan mendapat keberkahan.'
        ),
        array(
            'donor_name' => 'Siti Nurhaliza',
            'donor_image' => null,
            'prayer_text' => 'Doa tulus dari hati untuk semua yang memberikan sedekah. Semoga Allah memberikan pahala yang berlipat ganda.'
        ),
        array(
            'donor_name' => 'Budi Santoso',
            'donor_image' => null,
            'prayer_text' => 'Bersama kita bisa lebih kuat. Semoga setiap donasi membawa kebaikan untuk kita semua.'
        ),
        array(
            'donor_name' => 'Rini Wijaya',
            'donor_image' => null,
            'prayer_text' => 'Terima kasih atas kesempatan berbagi. Semoga Allah membimbing semua langkah kita menuju kebaikan.'
        )
    );

    // Banners untuk halaman utama (jika tabel banners tersedia)
    $data['banners'] = [];
    try {
        // Cek apakah tabel banners ada dengan cara yang lebih sederhana
        $tableCheck = $conn->query("SELECT 1 FROM banners LIMIT 1");
        if ($tableCheck !== false) {
            // Tabel ada, ambil banner aktif (urutkan berdasarkan order, ambil yang pertama)
            $bannerQuery = "SELECT id, title, subtitle, image, link, `order` FROM banners WHERE (image IS NOT NULL AND image != '') ORDER BY `order` ASC, id ASC LIMIT 1";
            $bannerRes = $conn->query($bannerQuery);
            if ($bannerRes && $bannerRes->num_rows > 0) {
                while ($row = $bannerRes->fetch_assoc()) {
                    // Pastikan image tidak kosong
                    if (!empty($row['image']) && trim($row['image']) != '') {
                        $data['banners'][] = $row;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Jika error, biarkan array kosong dan akan pakai fallback campaign
        // Tidak perlu log error untuk menghindari spam log
    } catch (Error $e) {
        // Handle PHP 7+ Error class
    }

    // Categories removed - replaced with menu items

    return $data;
}

$data = getAllCampaignData($conn);
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="KolaborAksi - Platform donasi online untuk membantu sesama. Galang dana, donasi, dan berbuat baik bersama jutaan orang Indonesia.">
    <title>KolaborAksi - Platform Donasi Online Indonesia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #E8F5F2 0%, #F0FFFE 25%, #FFFFFF 50%, #F0FFFE 75%, #E8F5F2 100%);
            background-attachment: fixed;
            color: #333;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: transparent;
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .content-wrapper {
            flex: 1;
            width: 100%;
            padding-bottom: 80px;
            overflow-y: auto;
        }

        .tab-content {
            display: none;
            width: 100%;
            animation: fadeIn 0.3s ease-in-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* Banner Section */
        .banner-section {
            position: relative;
            overflow: hidden;
            background: transparent;
            padding: 12px;
            padding-bottom: 0;
            margin-bottom: 12px;
            border-radius: 0;
            width: 100%;
        }

        .banner-image-link {
            display: block;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .banner-image-link:active {
            transform: scale(0.98);
        }

        .banner-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .banner-image-placeholder {
            width: 100%;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
            font-size: 64px;
            color: white;
        }

        @media (min-width: 768px) {
            .banner-image, .banner-image-placeholder {
                height: 220px;
            }
        }
/* Category Frame Container */
.category-frame-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: white;
    z-index: 999;
    display: flex;
    flex-direction: column;
}

.category-frame-header {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: white;
    border-bottom: 1px solid #F0F0F0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
}

.back-button:active {
    background: rgba(23, 166, 151, 0.1);
}

.category-frame-title {
    font-size: 16px;
    font-weight: 600;
    color: #1F1F1F;
    flex: 1;
}

.category-frame {
    flex: 1;
    width: 100%;
    border: none;
    background: white;
}
        /* Menu Section - Mobile First */
        .menu-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 12px;
            margin: 8px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 12px rgba(23, 166, 151, 0.06);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .menu-item {
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
            padding: 8px 4px;
            border-radius: 12px;
            background: white;
            border: 1px solid #F0F0F0;
        }

        .menu-item:active {
            transform: scale(0.96);
            background: #F8F8F8;
        }

        .menu-item:hover {
            box-shadow: 0 2px 8px rgba(23, 166, 151, 0.12);
        }

        .menu-icon {
            font-size: 24px;
            margin-bottom: 4px;
            line-height: 1;
        }

        .menu-name {
            font-size: 10px;
            font-weight: 600;
            color: #333;
            line-height: 1.2;
        }

        @media (min-width: 480px) {
            .menu-grid {
                gap: 10px;
            }
            .menu-item {
                padding: 10px 6px;
            }
            .menu-icon {
                font-size: 28px;
                margin-bottom: 6px;
            }
            .menu-name {
                font-size: 11px;
            }
        }

        .zakat-calculator-card {
            background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
            margin: 8px;
            padding: 18px 16px;
            border-radius: 18px;
            box-shadow: 0 4px 16px rgba(23, 166, 151, 0.15);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .zakat-calculator-card:active {
            transform: scale(0.98);
        }

        .zakat-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .zakat-icon {
            font-size: 28px;
        }

        .zakat-card-content h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .zakat-card-content p {
            font-size: 11px;
            opacity: 0.9;
        }

        .zakat-types {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }

        .zakat-type-item {
            background: rgba(255, 255, 255, 0.15);
            padding: 6px;
            border-radius: 6px;
            text-align: center;
            font-size: 10px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            margin: 12px 8px;
            padding: 18px 16px;
            border-radius: 18px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 12px rgba(23, 166, 151, 0.06);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F1F1F;
        }

        .campaign-slider {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: none;
            margin: 0 -16px;
            padding-left: 16px;
            padding-right: 16px;
            -webkit-overflow-scrolling: touch;
        }

        .campaign-slider::-webkit-scrollbar {
            display: none;
        }

        .campaign-card-small {
            min-width: 168px;
            max-width: 168px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #E8EBED;
            text-decoration: none;
            color: inherit;
            display: block;
            flex-shrink: 0;
        }

        .campaign-card-small:active {
            transform: scale(0.97);
        }

        .campaign-image-small {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            overflow: hidden;
        }

        .campaign-image-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .campaign-content-small {
            padding: 12px;
        }

        .campaign-title-small {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            color: #1F1F1F;
            min-height: 36px;
        }

        .campaign-organizer-small {
            font-size: 10px;
            color: #6B7280;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .progress-bar-small {
            width: 100%;
            height: 4px;
            background: #F0F2F4;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #17a697 0%, #1bc9b5 100%);
            transition: width 0.5s ease;
            border-radius: 10px;
        }

        .campaign-stats-small {
            font-size: 11px;
        }

        .stat-label-small {
            color: #6B7280;
            font-weight: 500;
            display: block;
            margin-bottom: 3px;
            font-size: 10px;
        }

        .stat-raised-small {
            font-weight: 700;
            color: #17a697;
            font-size: 13px;
        }

        .rekomendasi-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rekomendasi-card {
            display: flex;
            gap: 12px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #E8EBED;
            padding: 12px;
            text-decoration: none;
            color: inherit;
        }

        .rekomendasi-card:active {
            transform: scale(0.98);
        }

        .rekomendasi-image {
            width: 140px;
            aspect-ratio: 16 / 9;
            flex-shrink: 0;
            border-radius: 12px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            overflow: hidden;
        }

        .rekomendasi-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .rekomendasi-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .rekomendasi-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            color: #1F1F1F;
        }

        .rekomendasi-organizer {
            font-size: 11px;
            color: #6B7280;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .verified-badge {
            color: #17a697;
            font-size: 10px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #F0F2F4;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .rekomendasi-stats {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-label {
            font-size: 10px;
            color: #6B7280;
            font-weight: 500;
        }

        .stat-raised {
            font-weight: 700;
            color: #17a697;
            font-size: 13px;
        }

        .stat-days {
            color: #6B7280;
            font-size: 10px;
        }

        .donor-prayers-section {
            background: linear-gradient(135deg, #FFFFFF 0%, #F5F5F5 100%);
            margin: 8px;
            padding: 20px 16px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(23, 166, 151, 0.1);
            box-shadow: 0 4px 16px rgba(23, 166, 151, 0.05);
        }

        .donor-prayers-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .donor-prayers-title-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .dove-image {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .donor-prayers-title {
            font-size: 20px;
            font-weight: 700;
            color: #1F1F1F;
            margin: 0;
        }

        .donor-prayers-subtitle {
            font-size: 12px;
            color: #888;
            margin: 6px 0 0 0;
        }

        .donor-prayers-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .donor-prayer-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #F0F0F0;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .donor-prayer-card:active {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 166, 151, 0.15);
            border-color: #17a697;
        }

        .donor-prayer-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #F0F0F0;
        }

        .donor-prayer-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .donor-prayer-info {
            flex: 1;
        }

        .donor-prayer-name {
            font-size: 13px;
            font-weight: 700;
            color: #1F1F1F;
            margin-bottom: 2px;
        }

        .donor-prayer-badge {
            display: inline-block;
            background: rgba(23, 166, 151, 0.1);
            color: #17a697;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 20px;
        }

        .donor-prayer-icon {
            font-size: 20px;
            margin-bottom: 6px;
            opacity: 0.8;
        }

        .donor-prayer-text {
            font-size: 12px;
            color: #555;
            line-height: 1.5;
            margin: 0;
            flex: 1;
            font-style: italic;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .donor-prayer-footer {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #F5F5F5;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .donor-prayer-footer-icon {
            color: #17a697;
            font-size: 12px;
        }

        .donor-prayer-footer-text {
            font-size: 10px;
            color: #888;
        }

        .about-section {
            background: rgba(255, 255, 255, 0.8);
            margin: 8px;
            padding: 20px 16px;
            border-radius: 16px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 16px rgba(23, 166, 151, 0.08);
        }

        .about-logo {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .about-title {
            font-size: 20px;
            font-weight: 700;
            color: #1F1F1F;
            margin-bottom: 10px;
        }

        .about-content {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .about-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .stat-item {
            text-align: center;
            padding: 12px 8px;
            background: rgba(23, 166, 151, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(23, 166, 151, 0.2);
        }

        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #17a697;
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 10px;
            color: #888;
            font-weight: 500;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 -2px 8px rgba(23, 166, 151, 0.1);
            display: flex;
            justify-content: space-around;
            padding: 4px 0;
            z-index: 1000;
            border-top: 1px solid rgba(23, 166, 151, 0.1);
        }

        .nav-item {
            text-align: center;
            color: #888;
            font-size: 9px;
            flex: 1;
            padding: 6px 4px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            background: none;
            border: none;
            font-family: 'Poppins', sans-serif;
        }

        .nav-item.active {
            color: #17a697;
        }

        .nav-icon {
            font-size: 18px;
            margin-bottom: 2px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 24px;
            border-radius: 16px;
            text-align: center;
            max-width: 300px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-icon {
            font-size: 56px;
            margin-bottom: 12px;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1F1F1F;
        }

        .modal-text {
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .modal-btn {
            background: #17a697;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .zakat-modal-content {
            max-width: 480px;
            width: 95%;
            max-height: 85vh;
            overflow-y: auto;
            text-align: left;
        }

        .zakat-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .zakat-tabs::-webkit-scrollbar {
            display: none;
        }

        .zakat-tab {
            padding: 6px 12px;
            background: #F0F0F0;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .zakat-tab.active {
            background: #17a697;
            color: white;
        }

        .zakat-calculator-content {
            display: none;
        }

        .zakat-calculator-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #333;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #DDD;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: #17a697;
        }

        .calculate-btn {
            width: 100%;
            padding: 11px;
            background: #17a697;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .calculate-btn:active {
            background: #0f6f5f;
        }

        .result-box {
            display: none;
            margin-top: 16px;
            padding: 16px;
            background: #E8F5F2;
            border-radius: 10px;
            border: 2px solid #17a697;
        }

        .result-box.show {
            display: block;
        }

        .result-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .result-value {
            font-size: 20px;
            font-weight: 700;
            color: #17a697;
        }

        .info-text {
            font-size: 10px;
            color: #888;
            margin-top: 10px;
            line-height: 1.4;
        }

        /* Tablet */
        @media (min-width: 768px) {
            .section-title {
                font-size: 18px;
            }

            .campaign-card-small {
                min-width: 160px;
                max-width: 180px;
            }

            .donor-prayers-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Desktop */
        @media (min-width: 1024px) {
            .section {
                margin: 8px 20px;
            }

            .menu-section {
                margin: 8px 20px;
            }

            .zakat-calculator-card {
                margin: 8px 20px;
            }

            .donor-prayers-section {
                margin: 8px 20px;
            }

            .about-section {
                margin: 8px 20px;
            }

            .donor-prayers-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content-wrapper">
            <!-- HOME TAB -->
            <div id="tab-beranda" class="tab-content active">
                
                <!-- Banner Section -->
                <?php 
                // Prioritaskan banner dari tabel banners, jika tidak ada gunakan campaign terbaru
                $banner = null;
                $bannerImage = '';
                $bannerTitle = 'KolaborAksi';
                $bannerSubtitle = '';
                $bannerLink = '#';
                $bannerEmoji = '💝';
                
                // Cek banner dari tabel banners terlebih dahulu
                if (!empty($data['banners']) && is_array($data['banners']) && count($data['banners']) > 0) {
                    $banner = $data['banners'][0];
                    $bannerImage = !empty($banner['image']) ? trim($banner['image']) : '';
                    $bannerTitle = !empty($banner['title']) ? trim($banner['title']) : 'KolaborAksi';
                    $bannerSubtitle = !empty($banner['subtitle']) ? trim($banner['subtitle']) : '';
                    $bannerLink = !empty($banner['link']) ? trim($banner['link']) : '#';
                } 
                // Fallback ke campaign terbaru jika tidak ada banner
                elseif (!empty($data['latest_campaigns']) && is_array($data['latest_campaigns']) && count($data['latest_campaigns']) > 0) {
                    $banner = $data['latest_campaigns'][0];
                    $bannerImage = !empty($banner['image']) ? trim($banner['image']) : '';
                    $bannerTitle = !empty($banner['title']) ? trim($banner['title']) : 'KolaborAksi';
                    $bannerLink = isset($banner['id']) ? 'kampanye-detail.php?id=' . intval($banner['id']) : '#';
                    $bannerEmoji = !empty($banner['emoji']) ? trim($banner['emoji']) : '💝';
                }
                
                // Tampilkan banner jika ada data
                if ($banner):
                ?>
                <div class="banner-section">
                    <a href="<?= htmlspecialchars($bannerLink) ?>" class="banner-image-link" target="_self">
                        <?php if (!empty($bannerImage)): ?>
                            <img src="<?= htmlspecialchars($bannerImage) ?>" alt="<?= htmlspecialchars($bannerTitle) ?>" class="banner-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="banner-image-placeholder" style="display: none;">
                                <?= htmlspecialchars($bannerEmoji) ?>
                            </div>
                        <?php else: ?>
                            <div class="banner-image-placeholder">
                                <?= htmlspecialchars($bannerEmoji) ?>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Zakat Calculator Card -->
                <div class="zakat-calculator-card" onclick="openZakatCalculator()">
                    <div class="zakat-card-header">
                        <div class="zakat-icon">🕌</div>
                        <div class="zakat-card-content">
                            <h3>Kalkulator Zakat</h3>
                            <p>Hitung zakat dengan mudah</p>
                        </div>
                    </div>
                    <div class="zakat-types">
                        <div class="zakat-type-item">Mal</div>
                        <div class="zakat-type-item">Fitrah</div>
                        <div class="zakat-type-item">Emas</div>
                    </div>
                </div>

                <!-- Menu Section -->
                <div class="menu-section">
                    <div class="menu-grid">
                        <a href="#latestCampaigns" class="menu-item" onclick="event.preventDefault(); document.getElementById('latestCampaigns').scrollIntoView({behavior: 'smooth', block: 'start'});">
                            <div class="menu-icon">🔥</div>
                            <div class="menu-name">Kampanye Terbaru</div>
                        </a>
                        <a href="#favoritCategories" class="menu-item" onclick="event.preventDefault(); document.getElementById('favoritCategories').scrollIntoView({behavior: 'smooth', block: 'start'});">
                            <div class="menu-icon">⭐</div>
                            <div class="menu-name">Kampanye Populer</div>
                        </a>
                        <a href="#eventRecommendations" class="menu-item" onclick="event.preventDefault(); document.getElementById('eventRecommendations').scrollIntoView({behavior: 'smooth', block: 'start'});">
                            <div class="menu-icon">🎉</div>
                            <div class="menu-name">Event</div>
                        </a>
                        <a href="kampanye_cepat.php" class="menu-item">
                            <div class="menu-icon">⚡</div>
                            <div class="menu-name">Kampanye Cepat</div>
                        </a>
                    </div>
                </div>

                <!-- Latest Campaigns -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">🔥 Terbaru</h2>
                    </div>
                    <div class="campaign-slider" id="latestCampaigns"></div>
                </div>

                <!-- Event Recommendations -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">🎉 Event</h2>
                    </div>
                    <div class="campaign-slider" id="eventRecommendations"></div>
                </div>
<!-- Masjid & Rumah Tahfiz -->
<div class="section">
    <div class="section-header">
        <h2 class="section-title">🕌 Masjid & Rumah Tahfiz</h2>
    </div>
    <div class="campaign-slider" id="masjidTahfiz"></div>
</div>
                <!-- Favorite Categories -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">⭐ Favorit</h2>
                    </div>
                    <div class="campaign-slider" id="favoritCategories"></div>
                </div>

                <!-- Recommendations -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">💡 Rekomendasi</h2>
                    </div>
                    <div class="rekomendasi-list" id="recommendationsList"></div>
                </div>

                <!-- Donor Prayers Section -->
                <div class="donor-prayers-section">
                    <div class="donor-prayers-header">
                        <div class="donor-prayers-title-container">
                            <div class="dove-image">🕊️</div>
                            <h2 class="donor-prayers-title">Doa Donatur</h2>
                        </div>
                        <p class="donor-prayers-subtitle">Doa dari para dermawan</p>
                    </div>
                    <div class="donor-prayers-container" id="donorPrayersContainer"></div>
                </div>

                <!-- About Section -->
                <div class="about-section">
                    <div class="about-logo">💝</div>
                    <h2 class="about-title">KolaborAksi</h2>
                    <p class="about-content">
                        Platform donasi online terpercaya yang menghubungkan donatur dengan penerima manfaat. Transparansi penuh dalam setiap kampanye.
                    </p>
                    <div class="about-stats">
                        <div class="stat-item">
                            <div class="stat-number">5M+</div>
                            <div class="stat-label">Donatur</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">50K+</div>
                            <div class="stat-label">Kampanye</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">2T+</div>
                            <div class="stat-label">Terkumpul</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXPLORE TAB -->
<!-- EXPLORE TAB -->
<div id="tab-jelajah" class="tab-content">
    <iframe 
        id="searchFrame" 
        src="" 
        style="width: 100%; height: calc(100vh - 80px); border: none; background: white;"
        frameborder="0"
        title="Pencarian Kampanye">
    </iframe>
</div>

        <!-- Zakat Calculator Modal -->
        <div id="zakatModal" class="modal" onclick="closeZakatModal()">
            <div class="modal-content zakat-modal-content" onclick="event.stopPropagation()">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 class="modal-title" style="margin: 0;">🕌 Zakat</h3>
                    <button onclick="closeZakatModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
                </div>

                <div class="zakat-tabs">
                    <button class="zakat-tab active" onclick="switchZakatTab('mal')">Mal</button>
                    <button class="zakat-tab" onclick="switchZakatTab('fitrah')">Fitrah</button>
                    <button class="zakat-tab" onclick="switchZakatTab('emas')">Emas</button>
                    <button class="zakat-tab" onclick="switchZakatTab('perak')">Perak</button>
                    <button class="zakat-tab" onclick="switchZakatTab('penghasilan')">Penghasilan</button>
                </div>

                <div id="calc-mal" class="zakat-calculator-content active">
                    <div class="form-group">
                        <label class="form-label">Total Harta (Rp)</label>
                        <input type="number" id="input-mal" class="form-input" placeholder="100000000">
                    </div>
                    <button class="calculate-btn" onclick="calculateZakatMal()">Hitung</button>
                    <div id="result-mal" class="result-box">
                        <div class="result-label">Zakat Mal:</div>
                        <div class="result-value" id="value-mal">Rp 0</div>
                        <p class="info-text">Nisab: 85 gram emas. Rate: 2.5%</p>
                    </div>
                </div>

                <div id="calc-fitrah" class="zakat-calculator-content">
                    <div class="form-group">
                        <label class="form-label">Jumlah Jiwa</label>
                        <input type="number" id="input-fitrah-jiwa" class="form-input" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Beras/Liter (Rp)</label>
                        <input type="number" id="input-fitrah-harga" class="form-input" placeholder="15000">
                    </div>
                    <button class="calculate-btn" onclick="calculateZakatFitrah()">Hitung</button>
                    <div id="result-fitrah" class="result-box">
                        <div class="result-label">Zakat Fitrah:</div>
                        <div class="result-value" id="value-fitrah">Rp 0</div>
                        <p class="info-text">3.5 liter beras per jiwa</p>
                    </div>
                </div>

                <div id="calc-emas" class="zakat-calculator-content">
                    <div class="form-group">
                        <label class="form-label">Jumlah Emas (gram)</label>
                        <input type="number" id="input-emas-jumlah" class="form-input" placeholder="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga/Gram (Rp)</label>
                        <input type="number" id="input-emas-harga" class="form-input" placeholder="1000000">
                    </div>
                    <button class="calculate-btn" onclick="calculateZakatEmas()">Hitung</button>
                    <div id="result-emas" class="result-box">
                        <div class="result-label">Zakat Emas:</div>
                        <div class="result-value" id="value-emas">Rp 0</div>
                        <p class="info-text">Nisab: 85 gram. Rate: 2.5%</p>
                    </div>
                </div>

                <div id="calc-perak" class="zakat-calculator-content">
                    <div class="form-group">
                        <label class="form-label">Jumlah Perak (gram)</label>
                        <input type="number" id="input-perak-jumlah" class="form-input" placeholder="600">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga/Gram (Rp)</label>
                        <input type="number" id="input-perak-harga" class="form-input" placeholder="15000">
                    </div>
                    <button class="calculate-btn" onclick="calculateZakatPerak()">Hitung</button>
                    <div id="result-perak" class="result-box">
                        <div class="result-label">Zakat Perak:</div>
                        <div class="result-value" id="value-perak">Rp 0</div>
                        <p class="info-text">Nisab: 595 gram. Rate: 2.5%</p>
                    </div>
                </div>

                <div id="calc-penghasilan" class="zakat-calculator-content">
                    <div class="form-group">
                        <label class="form-label">Penghasilan/Bulan (Rp)</label>
                        <input type="number" id="input-penghasilan" class="form-input" placeholder="10000000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Beras/Kg (Rp)</label>
                        <input type="number" id="input-penghasilan-beras" class="form-input" placeholder="12000">
                    </div>
                    <button class="calculate-btn" onclick="calculateZakatPenghasilan()">Hitung</button>
                    <div id="result-penghasilan" class="result-box">
                        <div class="result-label">Zakat Penghasilan:</div>
                        <div class="result-value" id="value-penghasilan">Rp 0</div>
                        <p class="info-text">Nisab: 520 kg beras. Rate: 2.5%</p>
                    </div>
                </div>
            </div>
        </div>
<!-- Category Frame Container -->
<div id="categoryFrameContainer" class="category-frame-container" style="display: none;">
    <div class="category-frame-header">
        <button class="back-button" onclick="closeCategoryFrame()">
            <i class="fas fa-arrow-left"></i> Kembali
        </button>
        <span class="category-frame-title" id="categoryFrameTitle"></span>
    </div>
    <iframe id="categoryFrame" class="category-frame"></iframe>
</div>
        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <button class="nav-item active" onclick="switchTab('beranda')">
                <div class="nav-icon"><i class="fas fa-home"></i></div>
                <div>Beranda</div>
            </button>
            <button class="nav-item" onclick="switchTab('jelajah')">
                <div class="nav-icon"><i class="fas fa-search"></i></div>
                <div>Jelajah</div>
            </button>
            <button class="nav-item" onclick="window.location.href='partner_dashboard.php'">
                <div class="nav-icon"><i class="fas fa-user-shield"></i></div>
                <div>Admin</div>
            </button>
        </nav>
    </div>

    <script>
        const phpData = <?php echo json_encode($data); ?>;

        // ==================== DATA LOADING ====================
        function loadPageData() {
            renderCampaigns(phpData.latest_campaigns, 'latestCampaigns');
            renderCampaigns(phpData.event_recommendations, 'eventRecommendations');
            renderCampaigns(phpData.masjid_tahfiz, 'masjidTahfiz');
            renderCampaigns(phpData.favorite_categories, 'favoritCategories');
            renderRecommendations(phpData.recommendations);
            renderDonorPrayers(phpData.donor_prayers);
        }

        // ==================== RENDER FUNCTIONS ====================
// Tempatkan kode ini di bagian JavaScript Anda (ganti fungsi renderCategories yang ada)

function renderCategories(categories) {
    const grid = document.getElementById('categoryGrid');
    
    // Debug: cek apakah element ada
    if (!grid) {
        console.error('❌ Element categoryGrid tidak ditemukan!');
        return;
    }
    
    // Debug: cek apakah categories ada
    if (!categories || categories.length === 0) {
        console.error('❌ Data categories kosong atau undefined!');
        grid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; color:#999;">Tidak ada kategori</p>';
        return;
    }
    
    console.log('✅ Rendering', categories.length, 'kategori');
    
    grid.innerHTML = '';
    
    categories.forEach((cat, index) => {
        console.log(`Kategori ${index + 1}:`, cat.name, cat.emoji);
        
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'category-item';
        
        // Perbaikan: Tambahkan error handling
        link.onclick = (e) => {
            e.preventDefault();
            console.log('Klik kategori:', cat.name);
            
            // Cek apakah link valid
            if (!cat.link || cat.link === '' || cat.link === '#') {
                alert('Link kategori belum tersedia');
                return;
            }
            
            openCategoryModal(cat);
        };
        
        link.innerHTML = `
            <div class="category-icon">${cat.emoji || '📱'}</div>
            <div class="category-name">${cat.name}</div>
        `;
        
        grid.appendChild(link);
    });
    
    console.log('✅ Kategori berhasil di-render');
}

function openCategoryModal(category) {
    // Aktifkan tab Jelajah
    switchTab('jelajah');

    const searchFrame = document.getElementById('searchFrame');

    if (!searchFrame) {
        console.error('Iframe jelajah tidak ditemukan');
        return;
    }

    // Set judul halaman (opsional)
    document.title = 'Kategori - ' + category.name;

    // Load halaman kategori ke iframe
    searchFrame.src = category.link;

    console.log('Kategori dibuka di iframe:', category.link);
}


// Fungsi debug untuk memeriksa data
function debugCheckData() {
    console.log('=== DEBUG INFO ===');
    console.log('phpData:', phpData);
    console.log('==================');
}

// Panggil saat halaman load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ KolaborAksi loaded');
    
    // Debug: cek data
    debugCheckData();
    
    // Load data
    loadPageData();
});

        function renderCampaigns(campaigns, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    campaigns.forEach(campaign => {
        const card = document.createElement('a');
        card.href = 'kampanye-detail.php?id=' + campaign.id;
        card.className = 'campaign-card-small';
        
        // Gunakan image path dari database langsung
        const imageHtml = campaign.image 
            ? `<img src="${campaign.image}" alt="${campaign.title}" onerror="this.parentElement.innerHTML='${campaign.emoji || '💝'}'">` 
            : (campaign.emoji || '💝');
        
        card.innerHTML = `
            <div class="campaign-image-small">
                ${imageHtml}
            </div>
            <div class="campaign-content-small">
                <h3 class="campaign-title-small">${campaign.title}</h3>
                <p class="campaign-organizer-small">
                    <i class="fas fa-user-circle"></i>
                    ${campaign.organizer}
                </p>
                <div class="progress-bar-small">
                    <div class="progress-fill" style="width: ${campaign.progress}%;"></div>
                </div>
                <div class="campaign-stats-small">
                    <span class="stat-label-small">Terkumpul</span>
                    <div class="stat-raised-small">${campaign.donasi_formatted}</div>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

function renderRecommendations(recommendations) {
    const list = document.getElementById('recommendationsList');
    list.innerHTML = '';
    
    recommendations.forEach(rec => {
        const card = document.createElement('a');
        card.href = 'kampanye-detail.php?id=' + rec.id;
        card.className = 'rekomendasi-card';
        
        // Gunakan image path dari database langsung
        const imageHtml = rec.image 
            ? `<img src="${rec.image}" alt="${rec.title}" onerror="this.parentElement.innerHTML='${rec.emoji || '💝'}'">` 
            : (rec.emoji || '💝');
        
        card.innerHTML = `
            <div class="rekomendasi-image">
                ${imageHtml}
            </div>
            <div class="rekomendasi-content">
                <div>
                    <h3 class="rekomendasi-title">${rec.title}</h3>
                    <p class="rekomendasi-organizer">
                        <i class="fas fa-user-circle"></i>
                        ${rec.organizer}
                        <i class="fas fa-check-circle verified-badge"></i>
                    </p>
                </div>
                <div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${rec.progress}%;"></div>
                    </div>
                    <div class="rekomendasi-stats">
                        <span class="stat-label">Terkumpul</span>
                        <span class="stat-raised">${rec.donasi_formatted}</span>
                        <span class="stat-days">30 Hari lagi</span>
                    </div>
                </div>
            </div>
        `;
        list.appendChild(card);
    });
}
        function renderDonorPrayers(prayers) {
            const container = document.getElementById('donorPrayersContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            prayers.forEach(prayer => {
                const card = document.createElement('div');
                card.className = 'donor-prayer-card';
                const initials = prayer.donor_name.split(' ').map(n => n[0]).join('').toUpperCase();
                
                card.innerHTML = `
                    <div class="donor-prayer-header">
                        <div class="donor-prayer-avatar">
                            ${prayer.donor_image ? `<img src="${prayer.donor_image}">` : initials}
                        </div>
                        <div class="donor-prayer-info">
                            <div class="donor-prayer-name">${prayer.donor_name}</div>
                            <div class="donor-prayer-badge">💝 Dermawan</div>
                        </div>
                    </div>
                    <div class="donor-prayer-icon">🙏</div>
                    <p class="donor-prayer-text">"${prayer.prayer_text}"</p>
                    <div class="donor-prayer-footer">
                        <span class="donor-prayer-footer-icon">✨</span>
                        <span class="donor-prayer-footer-text">Doa tulus</span>
                    </div>
                `;
                
                container.appendChild(card);
            });
        }

        // ==================== TAB SWITCHING ====================
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    
    document.getElementById('tab-' + tabName).classList.add('active');
    event.currentTarget.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Load pencariankampanye.php when Jelajah tab is clicked
    if (tabName === 'jelajah') {
        const searchFrame = document.getElementById('searchFrame');
        // Cek apakah src kosong atau hanya berisi URL halaman saat ini tanpa file
        if (!searchFrame.src || searchFrame.src === window.location.href || searchFrame.src === '' || searchFrame.src === 'about:blank') {
            searchFrame.src = 'pencariankampanye.php';
            console.log('Loading search frame:', searchFrame.src);
        }
    }
}

function openCategoryModal(category) {
    // Cek apakah link eksternal (dimulai dengan http/https)
    const isExternal = category.link.startsWith('http://') || category.link.startsWith('https://');
    
    // Jika link BUKAN eksternal (internal file), buka di iframe
    if (!isExternal) {
        document.getElementById('categoryFrameContainer').style.display = 'flex';
        document.getElementById('categoryFrameTitle').textContent = category.name;
        document.getElementById('categoryFrame').src = category.link;

 
    } else {
        // Jika link eksternal (Google, dll), buka di tab baru
        window.open(category.link, '_blank');
    }
}

function closeCategoryFrame() {
    // Sembunyikan container iframe
    document.getElementById('categoryFrameContainer').style.display = 'none';
    document.getElementById('categoryFrame').src = '';
    
    // Tampilkan kembali content wrapper dan bottom nav
    document.querySelector('.content-wrapper').style.display = 'block';
    document.querySelector('.bottom-nav').style.display = 'flex';
}

        function openZakatCalculator() {
            document.getElementById('zakatModal').style.display = 'block';
        }

        function closeZakatModal() {
            document.getElementById('zakatModal').style.display = 'none';
        }

        function switchZakatTab(type) {
            document.querySelectorAll('.zakat-tab').forEach(tab => tab.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.querySelectorAll('.zakat-calculator-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById('calc-' + type).classList.add('active');
        }

        // ==================== ZAKAT CALCULATOR ====================
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        function calculateZakatMal() {
            const harta = parseFloat(document.getElementById('input-mal').value) || 0;
            const nisab = 85 * 1000000;
            
            if (harta >= nisab) {
                const zakat = harta * 0.025;
                document.getElementById('value-mal').textContent = formatRupiah(zakat);
                document.getElementById('result-mal').classList.add('show');
            } else {
                alert('Belum mencapai nisab (85 gram emas)');
                document.getElementById('result-mal').classList.remove('show');
            }
        }

        function calculateZakatFitrah() {
            const jiwa = parseInt(document.getElementById('input-fitrah-jiwa').value) || 1;
            const hargaBeras = parseFloat(document.getElementById('input-fitrah-harga').value) || 0;
            const zakat = jiwa * 3.5 * hargaBeras;
            
            document.getElementById('value-fitrah').textContent = formatRupiah(zakat);
            document.getElementById('result-fitrah').classList.add('show');
        }

        function calculateZakatEmas() {
            const jumlahEmas = parseFloat(document.getElementById('input-emas-jumlah').value) || 0;
            const hargaEmas = parseFloat(document.getElementById('input-emas-harga').value) || 0;
            const nisab = 85;
            
            if (jumlahEmas >= nisab) {
                const totalNilai = jumlahEmas * hargaEmas;
                const zakat = totalNilai * 0.025;
                document.getElementById('value-emas').textContent = formatRupiah(zakat);
                document.getElementById('result-emas').classList.add('show');
            } else {
                alert('Belum mencapai nisab (85 gram)');
                document.getElementById('result-emas').classList.remove('show');
            }
        }

        function calculateZakatPerak() {
            const jumlahPerak = parseFloat(document.getElementById('input-perak-jumlah').value) || 0;
            const hargaPerak = parseFloat(document.getElementById('input-perak-harga').value) || 0;
            const nisab = 595;
            
            if (jumlahPerak >= nisab) {
                const totalNilai = jumlahPerak * hargaPerak;
                const zakat = totalNilai * 0.025;
                document.getElementById('value-perak').textContent = formatRupiah(zakat);
                document.getElementById('result-perak').classList.add('show');
            } else {
                alert('Belum mencapai nisab (595 gram)');
                document.getElementById('result-perak').classList.remove('show');
            }
        }

        function calculateZakatPenghasilan() {
            const penghasilan = parseFloat(document.getElementById('input-penghasilan').value) || 0;
            const hargaBeras = parseFloat(document.getElementById('input-penghasilan-beras').value) || 0;
            const nisab = 520 * hargaBeras;
            
            if (penghasilan >= nisab) {
                const zakat = penghasilan * 0.025;
                document.getElementById('value-penghasilan').textContent = formatRupiah(zakat);
                document.getElementById('result-penghasilan').classList.add('show');
            } else {
                alert('Belum mencapai nisab');
                document.getElementById('result-penghasilan').classList.remove('show');
            }
        }

        // ==================== EVENT LISTENERS ====================
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeZakatModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ KolaborAksi loaded');
            loadPageData();
        });
    </script>
</body>
</html>