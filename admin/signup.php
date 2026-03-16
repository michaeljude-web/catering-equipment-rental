<?php
include '../includes/db_connection.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if(!empty($username) && !empty($password)){

        // hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO admin (username,password) VALUES (?,?)");
        $stmt->bind_param("ss",$username,$hash);

        if($stmt->execute()){
            $message = "Admin account created successfully!";
        }else{
            $message = "Username already exists!";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Admin</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
</head>

<body class="bg-light d-flex align-items-center justify-content-center vh-100">

<form method="post" class="bg-white p-4 rounded shadow" style="width:320px;">

<h4 class="text-center mb-3">Create Admin</h4>

<?php if($message): ?>
<div class="alert alert-info text-center">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="mb-3">
<input type="text" name="username" class="form-control" placeholder="Username" required>
</div>

<div class="mb-3">
<input type="password" name="password" class="form-control" placeholder="Password" required>
</div>

<button type="submit" class="btn btn-success w-100">
Create Account
</button>

</form>

</body>
</html>