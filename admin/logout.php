<?php
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);
$auth->logout();

header('Location: login.php');
exit();
