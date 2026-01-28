<?php
session_start();
session_destroy();
header("Location: partner_login.php");
exit;
?>

