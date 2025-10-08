<?php
session_start();
include 'includes/db_connection.php';
include 'classes/CustomerAuth.php';
include 'classes/Category.php';

$auth = new CustomerAuth($conn);
$categoryObj = new Category($conn);

if ($auth->isLoggedIn()) {
    header("Location: user/dashboard.php");
    exit;
}

function getCategoriesWithPhotos($categoryObj, $conn) {
    $categories = [];
    $totalCategories = $categoryObj->countCategories();
    $allCategories = $categoryObj->getCategories($totalCategories, 0);
    
    foreach ($allCategories as $category) {
        $catId = $category['id'];
        $equipQuery = $conn->query("SELECT photo FROM equipments WHERE category_id = $catId AND photo IS NOT NULL ORDER BY RAND() LIMIT 1");
        $equip = $equipQuery->fetch_assoc();
        $category['photo'] = $equip ? $equip['photo'] : "default.png";
        $categories[] = $category;
    }
    return $categories;
}

function getEquipments($conn, $searchQuery = null) {
    $equipments = [];
    if ($searchQuery) {
        $q = "%" . $searchQuery . "%";
        $stmt = $conn->prepare("SELECT * FROM equipments WHERE name LIKE ? ORDER BY name ASC");
        $stmt->bind_param("s", $q);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $equipments[] = $row;
        }
    } else {
        $equipQuery = $conn->query("SELECT * FROM equipments ORDER BY name ASC");
        while ($row = $equipQuery->fetch_assoc()) {
            $equipments[] = $row;
        }
    }
    return $equipments;
}

$categories = getCategoriesWithPhotos($categoryObj, $conn);
$equipments = getEquipments($conn, $_GET['q'] ?? null);

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
                exit;
            }
            
            $result = $auth->login($email, $password);
            if ($result['status'] === 'success') {
                $result['redirect'] = 'user/dashboard.php';
            }
            echo json_encode($result);
            break;
            
        case 'signup':
            $full_name = $_POST['full_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($full_name) || empty($email) || empty($password)) {
                echo json_encode(['status' => 'error', 'message' => 'Full name, email, and password are required.']);
                exit;
            }
            
            if (strlen($password) < 6) {
                echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long.']);
                exit;
            }
            
            $result = $auth->signup($full_name, $email, $password);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/font/css/all.min.css">
    <link rel="stylesheet" href="user/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">

<header>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                <img src="assets/img/logos.png" alt="Logo" width="40" height="40" class="me-2 rounded-circle">
                <span>Catering Services</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav" aria-controls="navbarNav" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0"></ul>
                
                <form class="d-flex me-3" id="searchForm">
                    <input class="form-control me-2" type="search" placeholder="Search equipments..." 
                           aria-label="Search" id="equipment-search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fa fa-search"></i>
                    </button>
                </form>
                
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#loginModal">
                        Login
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#signupModal">
                        Sign Up
                    </button>
                </div>
            </div>
        </div>
    </nav>
</header>

<main class="container my-5">
    <section class="row mb-5">
        <div class="col-12 mb-4">
            <h1 class="fw-bold">Shop by Category</h1>
            <p class="text-muted">Explore our complete selection of equipment categories.</p>
        </div>
        <div class="row g-4 text-center">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card border-0 shadow-sm h-100" style="cursor: pointer;" onclick="showLoginPrompt()">
                    <img src="uploads/<?= htmlspecialchars($cat['photo']) ?>" alt="<?= htmlspecialchars($cat['category_name']) ?>" 
                         class="card-img-top p-3" style="height:120px;object-fit:contain;">
                    <div class="card-body p-2">
                        <hr>
                        <p class="card-text fw-semibold"><?= htmlspecialchars($cat['category_name']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="row mb-4">
        <div class="col-12 mb-4">
            <h1 class="fw-bold">Available Equipments</h1>
            <?php if (isset($_GET['q']) && !empty($_GET['q'])): ?>
            <p class="text-muted">Search results for: <strong>"<?= htmlspecialchars($_GET['q']) ?>"</strong></p>
            <?php endif; ?>
        </div>
        <div class="row g-4" id="available-equipments">
            <?php if (empty($equipments)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No equipments found</h5>
                <p class="text-muted">Try searching with different keywords.</p>
            </div>
            <?php else: ?>
            <?php foreach ($equipments as $equip): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card border-0 shadow-sm h-100 d-flex flex-column text-center" style="cursor: pointer;" onclick="showLoginPrompt()">
                    <img src="uploads/<?= htmlspecialchars($equip['photo']) ?>" alt="<?= htmlspecialchars($equip['name']) ?>" 
                         class="card-img-top p-3" style="height:150px; object-fit:contain;">
                    <div class="card-body d-flex flex-column p-2 mt-auto">
                        <p class="card-text fw-semibold mb-2"><?= htmlspecialchars($equip['name']) ?></p>
                        <p class="text-primary fw-bold mt-auto">₱<?= number_format($equip['price'], 2) ?></p>
                        <small class="text-muted">Stock: <?= $equip['stock'] ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer class="bg-white text-center py-4 shadow-sm mt-5">
    <p class="mb-0 text-muted">&copy; 2025 El Cielo. All rights reserved.</p>
</footer>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="loginForm">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sign-in-alt me-2"></i>Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="email" class="form-control mb-3" name="email" placeholder="Email Address" required>
                <input type="password" class="form-control mb-3" name="password" placeholder="Password" required>
                <div id="loginMessage"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="signupForm">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Create Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control mb-3" name="full_name" placeholder="Full Name" required>
                <input type="email" class="form-control mb-3" name="email" placeholder="Email Address" required>
                <input type="password" class="form-control mb-3" name="password" placeholder="Password (min. 6 characters)" required minlength="6">
                <div id="signupMessage"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="signupBtn">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="user/script.js"></script>

</body>
</html>