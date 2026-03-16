<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title></title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body>


<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <h1 class="h3 mb-3">Dashboard</h1>
        <p>Welcome to your admin dashboard!</p>
    </main>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>

</body>
</html>
<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title></title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body>


<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <h1 class="h3 mb-3">Booking</h1>

    </main>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>

</body>
</html>
