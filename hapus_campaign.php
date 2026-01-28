<?php
session_start();
include "db.php";

if (!isset($_SESSION['partner_id'])) {
    header("Location: partner_login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $partner_nama = $_SESSION['partner_nama'];
    
    // Get image filename before delete
    $q = mysqli_query($conn, "SELECT image FROM campaigns WHERE id=$id AND organizer='" . mysqli_real_escape_string($conn, $partner_nama) . "'");
    
    if ($row = mysqli_fetch_assoc($q)) {
        // Delete image file
        if (file_exists('uploads/' . $row['image'])) {
            unlink('uploads/' . $row['image']);
        }
        
        // Delete from database
        mysqli_query($conn, "DELETE FROM campaigns WHERE id=$id AND organizer='" . mysqli_real_escape_string($conn, $partner_nama) . "'");
        $_SESSION['success'] = "Kampanye berhasil dihapus";
    } else {
        $_SESSION['error'] = "Kampanye tidak ditemukan";
    }
}

header("Location: partner_dashboard.php");
exit;
?>