<?php
// Database configuration
$DB_HOST = 'localhost';
$DB_USER = 'rank3598_apk';
$DB_PASS = 'Hakim123!';
$DB_NAME = 'rank3598_apk';

// Create connection
$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set character encoding UTF-8
mysqli_set_charset($conn, "utf8");
?>
