<?php
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($auth->login($username, $password)) {
        header('Location: dashboard.php');
        exit();
    } else {
        $message = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Login</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
   <link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">

   <form method="post" action="" class="bg-white p-4 rounded shadow" style="width: 320px;">
      <h2 class="h4 text-center mb-3">
         <i class="fas fa-user-shield me-2"></i> Admin Login
      </h2>

      <?php if($message): ?>
         <div class="alert alert-danger text-center py-2">
            <?= htmlspecialchars($message) ?>
         </div>
      <?php endif; ?>

      <div class="mb-3">
         <input type="text" name="username" placeholder="Username" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
         <input type="password" name="password" placeholder="Password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">
         <i class="fas fa-sign-in-alt me-1"></i> Login
      </button>
   </form>

   <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
   <script src="../assets/font/js/all.min.js"></script>
</body>
</html>
