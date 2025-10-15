<?php
session_start();
include '../includes/db_connection.php';
include '../classes/StaffAuth.php';

$auth = new StaffAuth($conn);
$auth->logout();

header("Location: login.php");
exit();
?>