<?php
session_start();
include "db.php";

if (!isset($_SESSION['partner_id'])) {
    header("Location: partner_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $organizer = mysqli_real_escape_string($conn, $_POST['organizer']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $emoji = mysqli_real_escape_string($conn, $_POST['emoji'] ?? '📖');
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $target_terkumpul = mysqli_real_escape_string($conn, $_POST['target_terkumpul']);
    $image_link = mysqli_real_escape_string($conn, $_POST['image_link'] ?? '');
    $link = mysqli_real_escape_string($conn, $_POST['link'] ?? '#');
    $type = mysqli_real_escape_string($conn, $_POST['type'] ?? 'lainnya');
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($filetype, $allowed)) {
            $image_name = time() . '_' . uniqid() . '.' . $filetype;
            $upload_path = 'uploads/' . $image_name;
            
            // Create uploads directory if not exists
            if (!file_exists('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // SIMPAN PATH LENGKAP ke database
                $image_path = $upload_path; // uploads/filename.jpg
            } else {
                $_SESSION['error'] = "Gagal upload gambar";
                header("Location: partner_dashboard.php");
                exit;
            }
        } else {
            $_SESSION['error'] = "Format file tidak diizinkan. Gunakan: " . implode(', ', $allowed);
            header("Location: partner_dashboard.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Gambar harus diupload";
        header("Location: partner_dashboard.php");
        exit;
    }
    
    // Insert to database - status 'pending' untuk menunggu persetujuan admin
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
        '$image_path', 
        '$organizer', 
        '$image_link', 
        0, 
        '$target_terkumpul', 
        '$link', 
        '$type', 
        '0',
        'pending'
    )";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Kampanye berhasil dibuat! Menunggu persetujuan admin untuk ditampilkan.";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
        // Delete uploaded image if database insert fails
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    header("Location: partner_dashboard.php");
    exit;
}
?>