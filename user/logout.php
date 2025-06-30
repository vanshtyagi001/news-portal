<?php
session_start();
$_SESSION = array(); // Unset all session values
session_destroy();
header("location: ../index.php"); // Redirect to homepage
exit;
?>