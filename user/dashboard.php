<?php
session_start();
include '../includes/db_connection.php';
include '../classes/CustomerAuth.php';

$auth = new CustomerAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

class Category {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getCategoriesWithPhotos() {
        $categories = [];
        $catQuery = $this->conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
        
        while ($row = $catQuery->fetch_assoc()) {
            $catId = $row['id'];
            $equipQuery = $this->conn->query("SELECT photo FROM equipments WHERE category_id = $catId AND photo IS NOT NULL ORDER BY RAND() LIMIT 1");
            $equip = $equipQuery->fetch_assoc();
            $row['photo'] = $equip ? $equip['photo'] : "default.png";
            $categories[] = $row;
        }
        
        return $categories;
    }
    
    public function getCategoryName($id) {
        $stmt = $this->conn->prepare("SELECT category_name FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        $stmt->close();
        return $category ? $category['category_name'] : 'Unknown';
    }
}

class Equipment {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getEquipments($searchQuery = null, $categoryId = null) {
        $equipments = [];
        
        if ($searchQuery && $categoryId) {
            $q = "%" . $searchQuery . "%";
            $stmt = $this->conn->prepare("SELECT * FROM equipments WHERE name LIKE ? AND category_id = ? AND stock > 0 ORDER BY name ASC");
            $stmt->bind_param("si", $q, $categoryId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $equipments[] = $row;
            }
            $stmt->close();
        } elseif ($searchQuery) {
            $q = "%" . $searchQuery . "%";
            $stmt = $this->conn->prepare("SELECT * FROM equipments WHERE name LIKE ? AND stock > 0 ORDER BY name ASC");
            $stmt->bind_param("s", $q);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $equipments[] = $row;
            }
            $stmt->close();
        } elseif ($categoryId) {
            $stmt = $this->conn->prepare("SELECT * FROM equipments WHERE category_id = ? AND stock > 0 ORDER BY name ASC");
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $equipments[] = $row;
            }
            $stmt->close();
        } else {
            $equipQuery = $this->conn->query("SELECT * FROM equipments WHERE stock > 0 ORDER BY name ASC");
            while ($row = $equipQuery->fetch_assoc()) {
                $equipments[] = $row;
            }
        }
        
        return $equipments;
    }
}

class CustomerProfile {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function getProfile($customerId) {
        $stmt = $this->conn->prepare("SELECT full_name, email FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();
        return $profile;
    }
    
    public function updateProfile($customerId, $full_name, $email, $password = null) {
        try {
            $emailStmt = $this->conn->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $emailStmt->bind_param("si", $email, $customerId);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            
            if ($emailResult->num_rows > 0) {
                $emailStmt->close();
                return ['success' => false, 'message' => 'Email already exists for another user'];
            }
            $emailStmt->close();
            
            if ($password) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE customers SET full_name = ?, email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $email, $hashedPassword, $customerId);
            } else {
                $stmt = $this->conn->prepare("UPDATE customers SET full_name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $full_name, $email, $customerId);
            }
            
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Profile updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update profile'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

class Cart {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function addToCart($customerId, $equipmentId, $quantity) {
        $stmt = $this->conn->prepare("SELECT id, price FROM equipments WHERE id = ?");
        $stmt->bind_param("i", $equipmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $equipment = $result->fetch_assoc();
        $stmt->close();
        
        if (!$equipment) {
            return ['success' => false, 'message' => 'Equipment not found'];
        }
        
        $price = $equipment['price'];
        $total = $price * $quantity;
        
        $checkStmt = $this->conn->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND equipment_id = ?");
        $checkStmt->bind_param("ii", $customerId, $equipmentId);
        $checkStmt->execute();
        $existingCart = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        if ($existingCart) {
            $newQuantity = $existingCart['quantity'] + $quantity;
            $newTotal = $price * $newQuantity;
            $updateStmt = $this->conn->prepare("UPDATE cart SET quantity = ?, total = ? WHERE id = ?");
            $updateStmt->bind_param("idi", $newQuantity, $newTotal, $existingCart['id']);
            $result = $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertStmt = $this->conn->prepare("INSERT INTO cart (customer_id, equipment_id, quantity, price, total, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $insertStmt->bind_param("iiidd", $customerId, $equipmentId, $quantity, $price, $total);
            $result = $insertStmt->execute();
            $insertStmt->close();
        }
        
        if ($result) {
            return ['success' => true, 'message' => 'Added to cart successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to add to cart'];
        }
    }
    
    public function getCartCount($customerId) {
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity), 0) as count FROM cart WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['count'];
    }
}

$categoryObj = new Category($conn);
$equipmentObj = new Equipment($conn);
$profileObj = new CustomerProfile($conn);
$cartObj = new Cart($conn);

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_profile':
            $customerId = $auth->getCustomerId();
            $profile = $profileObj->getProfile($customerId);
            echo json_encode($profile ?: []);
            exit;
            
        case 'update_profile':
            $customerId = $auth->getCustomerId();
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($full_name) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Name and email are required']);
                exit;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                exit;
            }
            
            if ($password && strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
                exit;
            }
            
            $result = $profileObj->updateProfile($customerId, $full_name, $email, $password ?: null);
            echo json_encode($result);
            exit;
            
        case 'get_category':
            $categoryId = $_POST['id'] ?? 0;
            $categoryName = $categoryObj->getCategoryName($categoryId);
            echo json_encode($categoryName);
            exit;
            
        case 'add_to_cart':
            $customerId = $auth->getCustomerId();
            $equipmentId = $_POST['equipment_id'] ?? 0;
            $quantity = $_POST['quantity'] ?? 1;
            
            if (!$equipmentId || !$quantity) {
                echo json_encode(['success' => false, 'message' => 'Invalid equipment or quantity']);
                exit;
            }
            
            $result = $cartObj->addToCart($customerId, $equipmentId, $quantity);
            echo json_encode($result);
            exit;
            
        case 'get_cart_count':
            $customerId = $auth->getCustomerId();
            $count = $cartObj->getCartCount($customerId);
            echo json_encode($count);
            exit;
    }
}

$categories = $categoryObj->getCategoriesWithPhotos();
$selectedCategory = $_GET['category'] ?? null;
$searchQuery = $_GET['q'] ?? null;
$equipments = $equipmentObj->getEquipments($searchQuery, $selectedCategory);

$selectedCategoryName = '';
if ($selectedCategory) {
    $selectedCategoryName = $categoryObj->getCategoryName($selectedCategory);
}

if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - EquipRent</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/font/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">

<header>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
                <img src="../assets/img/logos.png" alt="Logo" width="40" height="40" class="me-2 rounded-circle">
                <span>Catering Services</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav" aria-controls="navbarNav" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li> -->
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="rentals.php">
                            <i class="fas fa-clipboard-list me-1"></i>
                        </a>
                    </li> -->
                </ul>
                
                <form class="d-flex me-3" id="searchForm">
                    <input class="form-control me-2" type="search" placeholder="Search equipments..." 
                           aria-label="Search" id="equipment-search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fa fa-search"></i>
                    </button>
                </form>
                
                <div class="d-flex align-items-center">
                    <a href="cart.php" class="btn btn-outline-primary position-relative me-3">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">0</span>
                    </a>
                    <a href="rentals.php" class="btn btn-outline-primary position-relative me-3">
    <i class="fas fa-clipboard-list me-1"></i>
 
</a>


                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?= htmlspecialchars($auth->getCustomerName()) ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="openProfileModal()"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<main class="container my-4">
    <section class="row mb-5">
        <div class="col-12 mb-4">
            <h1 class="fw-bold">Shop by Category</h1>
            <p class="text-muted">Explore our complete selection of equipment categories.</p>
        </div>
        <div class="row g-4 text-center">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="dashboard.php?category=<?= $cat['id'] ?>" class="text-decoration-none text-dark">
                    <div class="card border-0 shadow-sm h-100 <?= $selectedCategory == $cat['id'] ? 'border-primary border-3' : '' ?>">
                        <img src="../uploads/<?= htmlspecialchars($cat['photo']) ?>" alt="<?= htmlspecialchars($cat['category_name']) ?>" 
                             class="card-img-top p-3" style="height:120px;object-fit:contain;">
                        <div class="card-body p-2">
                            <hr>
                            <p class="card-text fw-semibold <?= $selectedCategory == $cat['id'] ? 'text-primary' : '' ?>">
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="row mb-4">
        <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-bold">Available Equipments</h1>
                <?php if ($selectedCategory && $selectedCategoryName): ?>
                <p class="text-muted">Showing equipments in: <strong><?= htmlspecialchars($selectedCategoryName) ?></strong></p>
                <?php elseif ($searchQuery): ?>
                <p class="text-muted">Search results for: <strong>"<?= htmlspecialchars($searchQuery) ?>"</strong></p>
                <?php endif; ?>
            </div>
            <?php if ($selectedCategory || $searchQuery): ?>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Clear Filter
                </a>
            </div>
            <?php endif; ?>
        </div>
        <div class="row g-4" id="available-equipments">
            <?php if (empty($equipments)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No equipments found</h5>
                <p class="text-muted">Try searching with different keywords or browse other categories.</p>
            </div>
            <?php else: ?>
            <?php foreach ($equipments as $equip): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card border-0 shadow-sm h-100 d-flex flex-column text-center" style="cursor: pointer;" onclick="openEquipmentModal(<?= $equip['id'] ?>, '<?= htmlspecialchars($equip['name']) ?>', '<?= htmlspecialchars($equip['photo']) ?>', <?= $equip['category_id'] ?>, <?= $equip['price'] ?>, <?= $equip['stock'] ?>)">
                    <img src="../uploads/<?= htmlspecialchars($equip['photo']) ?>" alt="<?= htmlspecialchars($equip['name']) ?>" 
                         class="card-img-top p-3" style="height:150px; object-fit:contain;">
                    <div class="card-body d-flex flex-column p-2 mt-auto">
                        <p class="card-text fw-semibold mb-2"><?= htmlspecialchars($equip['name']) ?></p>
                        <p class="text-primary fw-bold mt-auto">₱<?= number_format($equip['price'], 2) ?></p>
                        <small class="text-muted mb-2">Stock: <?= $equip['stock'] ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</main>

<footer class="bg-white text-center py-4 shadow-sm mt-5">
    <p class="mb-0 text-muted">&copy; 2025 EquipRent. All rights reserved.</p>
</footer>

<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="profileForm">
                <div class="modal-body">
                    <div id="profileMessage"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">
                                <i class="fas fa-user me-2"></i>Full Name
                            </label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email
                            </label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>New Password (Optional)
                            </label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password" minlength="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Confirm Password
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="updateProfileBtn">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="equipmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="equipmentModalLabel">Equipment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="equipmentModalBody">
                <div class="row">
                    <div class="col-md-6">
                        <img id="equipImage" src="" alt="" class="img-fluid rounded" style="max-height: 300px; object-fit: contain; width: 100%;">
                    </div>
                    <div class="col-md-6">
                        <h4 id="equipName" class="fw-bold mb-3"></h4>
                        <div class="mb-2">
                            <strong>Category:</strong> <span id="equipCategory"></span>
                        </div>
                        <div class="mb-2">
                            <strong>Price:</strong> <span id="equipPrice" class="text-primary fw-bold"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Stock Available:</strong> <span id="equipStock"></span>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label fw-bold">Quantity:</label>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="decreaseQuantity()">-</button>
                                <input type="number" class="form-control mx-2 text-center" id="quantity" value="1" min="1" style="width: 80px;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="alert alert-info d-flex justify-content-between align-items-center">
                                <strong>Total Amount:</strong>
                                <span id="totalAmount" class="fw-bold fs-5">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary me-2" id="addToCartBtn">
                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                </button>
                <!-- <button type="button" class="btn btn-success" id="rentNowBtn">
                    <i class="fas fa-calendar-check me-2"></i>Rent Now
                </button> -->
            </div>
        </div>
    </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
<script>
function openProfileModal() {
    $('#profileModal').modal('show');
    loadProfileData();
}

function loadProfileData() {
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'get_profile' },
        dataType: 'json',
        success: function(data) {
            $('#full_name').val(data.full_name || '');
            $('#email').val(data.email || '');
            $('#password').val('');
            $('#confirm_password').val('');
        },
        error: function() {
            showProfileMessage('Error loading profile data', 'danger');
        }
    });
}

function showProfileMessage(message, type = 'info') {
    $('#profileMessage').html(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
}

function openEquipmentModal(id, name, photo, categoryId, price, stock) {
    $('#equipName').text(name);
    $('#equipImage').attr('src', '../uploads/' + photo);
    $('#equipImage').attr('alt', name);
    $('#equipPrice').text('₱' + Number(price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#equipStock').text(stock);
    $('#quantity').attr('max', stock);
    $('#quantity').val(1);
    
    $('#equipmentModal').attr('data-price', price);
    
    updateTotal();
    
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'get_category', id: categoryId },
        dataType: 'json',
        success: function(data) {
            $('#equipCategory').text(data);
        }
    });
    
    $('#addToCartBtn').attr('data-equipment-id', id);
    $('#rentNowBtn').attr('data-equipment-id', id);
    
    $('#equipmentModal').modal('show');
}

function increaseQuantity() {
    let qty = parseInt($('#quantity').val());
    let max = parseInt($('#quantity').attr('max'));
    if (qty < max) {
        $('#quantity').val(qty + 1);
        updateTotal();
    }
}

function decreaseQuantity() {
    let qty = parseInt($('#quantity').val());
    if (qty > 1) {
        $('#quantity').val(qty - 1);
        updateTotal();
    }
}

function updateTotal() {
    let quantity = parseInt($('#quantity').val());
    let price = parseFloat($('#equipmentModal').attr('data-price'));
    let total = quantity * price;
    
    $('#totalAmount').text('₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
}

$(document).on('input', '#quantity', function() {
    let qty = parseInt($(this).val());
    let max = parseInt($(this).attr('max'));
    
    if (qty > max) {
        $(this).val(max);
    } else if (qty < 1 || isNaN(qty)) {
        $(this).val(1);
    }
    
    updateTotal();
});

$('#profileForm').submit(function(e) {
    e.preventDefault();
    
    let password = $('#password').val();
    let confirmPassword = $('#confirm_password').val();
    
    if (password && password !== confirmPassword) {
        showProfileMessage('Passwords do not match!', 'danger');
        return;
    }
    
    $('#updateProfileBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: $(this).serialize() + '&action=update_profile',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showProfileMessage('Profile updated successfully!', 'success');
                setTimeout(function() {
                    $('#profileModal').modal('hide');
                    location.reload();
                }, 2000);
            } else {
                showProfileMessage('Error: ' + response.message, 'danger');
            }
        },
        error: function() {
            showProfileMessage('Error updating profile', 'danger');
        },
        complete: function() {
            $('#updateProfileBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Changes');
        }
    });
});

$(document).ready(function() {
    updateCartCount();
}); 

$('#addToCartBtn').click(function() {
    let equipmentId = $(this).attr('data-equipment-id');
    let quantity = $('#quantity').val();
    addToCart(equipmentId, quantity);
});

$('#rentNowBtn').click(function() {
    let equipmentId = $(this).attr('data-equipment-id');
    let quantity = $('#quantity').val();
    rentNow(equipmentId, quantity);
});

function addToCart(equipmentId, quantity = 1) {
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'add_to_cart',
            equipment_id: equipmentId,
            quantity: quantity
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Added to cart successfully!');
                $('#equipmentModal').modal('hide');
                updateCartCount();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error adding to cart');
        }
    });
}

function rentNow(equipmentId, quantity = 1) {
    window.location.href = 'rental.php?equipment_id=' + equipmentId + '&quantity=' + quantity;
}

function updateCartCount() {
    $.ajax({
        url: '',
        method: 'POST',
        data: { action: 'get_cart_count' },
        dataType: 'json',
        success: function(data) {
            let count = parseInt(data) || 0;
            $('.badge.rounded-pill.bg-danger').text(count);
        }
    });
}

$('#searchForm').submit(function(e) {
    e.preventDefault();
    let searchQuery = $('#equipment-search').val().trim();
    if (searchQuery) {
        window.location.href = 'dashboard.php?q=' + encodeURIComponent(searchQuery);
    } else {
        window.location.href = 'dashboard.php';
    }
});
</script>

</body>
</html>