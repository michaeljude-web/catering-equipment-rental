<?php
session_start();
include '../includes/db_connection.php';
include '../classes/StaffAuth.php';

$staff_firstname = 'Staff';

if (isset($_SESSION['staff_id'])) {
    $staff_id = $_SESSION['staff_id'];
    $query = "SELECT firstname FROM staff_info WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->bind_result($firstname);
    if ($stmt->fetch()) {
        $staff_firstname = $firstname;
    }
    $stmt->close();
}

$view_type = isset($_GET['view']) ? $_GET['view'] : 'equipment';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Monitoring - Staff Dashboard</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    .inventory-card {
        transition: transform 0.2s;
        height: 100%;
    }
    .inventory-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .equipment-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background-color: #f8f9fa;
    }
    .equipment-image-wrapper {
        position: relative;
        overflow: hidden;
        background-color: #e9ecef;
        height: 200px;
    }
    .stock-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 0.85rem;
        padding: 5px 10px;
        z-index: 10;
    }
    .low-stock {
        background-color: #dc3545;
        color: white;
    }
    .out-of-stock {
        background-color: #6c757d;
        color: white;
    }
    .in-stock {
        background-color: #28a745;
        color: white;
    }
    .nav-tabs .nav-link.active {
        font-weight: bold;
    }
    .item-thumb {
        width: 40px;
        height: 40px;
        object-fit: cover;
        background-color: #f8f9fa;
    }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<!-- Main content -->
<main class="flex-fill p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-boxes me-2"></i>Inventory Monitoring</h2>
        </div>

        <!-- View Type Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo $view_type === 'equipment' ? 'active' : ''; ?>" 
                   href="?view=equipment">
                    <i class="fas fa-tools me-2"></i>Equipment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $view_type === 'packages' ? 'active' : ''; ?>" 
                   href="?view=packages">
                    <i class="fas fa-box-open me-2"></i>Packages
                </a>
            </li>
        </ul>

        <!-- Filters Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_type); ?>">
                    
                    <?php if ($view_type === 'equipment'): ?>
                    <!-- Equipment Filters -->
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php 
                            $cat_query = "SELECT id, category_name FROM categories ORDER BY category_name";
                            $cat_result = $conn->query($cat_query);
                            if ($cat_result) {
                                while($cat = $cat_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Stock Status</label>
                        <select name="stock" class="form-select">
                            <option value="">All Stock</option>
                            <option value="available" <?php echo $stock_filter === 'available' ? 'selected' : ''; ?>>Available (In Stock)</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock (≤5)</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="col-md-6"></div>
                    <?php endif; ?>

                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <a href="?view=<?php echo $view_type; ?>" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($view_type === 'equipment'): ?>
        <!-- Equipment Inventory -->
        <div class="row">
            <?php
            // Build equipment query with filters
            $sql = "SELECT e.id, e.name, e.photo, e.price, e.stock, e.quantity, c.category_name 
                    FROM equipments e 
                    LEFT JOIN categories c ON e.category_id = c.id 
                    WHERE 1=1";
            
            $params = array();
            $types = "";
            
            if ($category_filter > 0) {
                $sql .= " AND e.category_id = ?";
                $params[] = $category_filter;
                $types .= "i";
            }
            
            if ($stock_filter === 'available') {
                $sql .= " AND e.stock > 0";
            } elseif ($stock_filter === 'low') {
                $sql .= " AND e.stock > 0 AND e.stock <= 5";
            } elseif ($stock_filter === 'out') {
                $sql .= " AND e.stock = 0";
            }
            
            if ($search !== '') {
                $sql .= " AND e.name LIKE ?";
                $params[] = "%" . $search . "%";
                $types .= "s";
            }
            
            $sql .= " ORDER BY e.name ASC";
            
            $stmt = $conn->prepare($sql);
            if ($types !== "") {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0):
                while($row = $result->fetch_assoc()):
                    // Determine stock status
                    $stock_class = 'in-stock';
                    $stock_text = 'In Stock';
                    if ($row['stock'] == 0) {
                        $stock_class = 'out-of-stock';
                        $stock_text = 'Out of Stock';
                    } elseif ($row['stock'] <= 5) {
                        $stock_class = 'low-stock';
                        $stock_text = 'Low Stock';
                    }
                    
                    // Get photo path - check if file exists
                    $photo_path = '../assets/images/no-image.png';
                    if (!empty($row['photo'])) {
                        $upload_path = '../uploads/' . $row['photo'];
                        if (file_exists($upload_path)) {
                            $photo_path = $upload_path;
                        }
                    }
            ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card inventory-card">
                    <div class="equipment-image-wrapper">
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                             class="equipment-image" 
                             alt="<?php echo htmlspecialchars($row['name']); ?>">
                        <span class="badge stock-badge <?php echo $stock_class; ?>">
                            <?php echo $stock_text; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                        <p class="card-text mb-2">
                            <small class="text-muted">
                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($row['category_name']); ?>
                            </small>
                        </p>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><strong>Price:</strong> ₱<?php echo number_format($row['price'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong>Available:</strong> <?php echo $row['stock']; ?></span>
                            <span><strong>Total:</strong> <?php echo $row['quantity']; ?></span>
                        </div>
                        <div class="progress mt-2" style="height: 8px;">
                            <?php 
                            $percentage = $row['quantity'] > 0 ? ($row['stock'] / $row['quantity']) * 100 : 0;
                            $progress_class = $percentage == 0 ? 'bg-danger' : ($percentage <= 20 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="progress-bar <?php echo $progress_class; ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%"
                                 aria-valuenow="<?php echo $percentage; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
                endwhile;
                $stmt->close();
            else:
                $stmt->close();
            ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>No equipment found matching your criteria.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Packages Inventory -->
        <div class="row">
            <?php
            // Build packages query with filters
            $sql = "SELECT p.id, p.package_name, p.price, p.created_at 
                    FROM packages p 
                    WHERE 1=1";
            
            if ($search !== '') {
                $sql .= " AND p.package_name LIKE ?";
            }
            
            $sql .= " ORDER BY p.package_name ASC";
            
            $stmt = $conn->prepare($sql);
            if ($search !== '') {
                $search_param = "%" . $search . "%";
                $stmt->bind_param("s", $search_param);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0):
                while($package = $result->fetch_assoc()):
                    // Get package items with stock info
                    $items_sql = "SELECT e.name, e.photo, pi.quantity, e.stock 
                                  FROM package_items pi 
                                  JOIN equipments e ON pi.equipment_id = e.id 
                                  WHERE pi.package_id = ?";
                    $items_stmt = $conn->prepare($items_sql);
                    $items_stmt->bind_param("i", $package['id']);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();
                    
                    // Check if all items are available
                    $all_available = true;
                    $items_array = array();
                    while($item = $items_result->fetch_assoc()) {
                        $items_array[] = $item;
                        if ($item['stock'] < $item['quantity']) {
                            $all_available = false;
                        }
                    }
                    $items_stmt->close();
                    
                    $stock_class = $all_available ? 'in-stock' : 'out-of-stock';
                    $stock_text = $all_available ? 'Available' : 'Unavailable';
                    $item_count = count($items_array);
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card inventory-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($package['package_name']); ?></h5>
                        <span class="badge <?php echo $stock_class; ?>">
                            <?php echo $stock_text; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h4 class="text-primary">₱<?php echo number_format($package['price'], 2); ?></h4>
                            <small class="text-muted">
                                <i class="fas fa-box me-1"></i><?php echo $item_count; ?> items included
                            </small>
                        </div>
                        
                        <h6 class="border-bottom pb-2 mb-3">Package Items:</h6>
                        <div class="list-group list-group-flush">
                            <?php 
                            foreach($items_array as $item): 
                                $item_available = $item['stock'] >= $item['quantity'];
                                
                                // Get photo path
                                $item_photo = '../assets/images/no-image.png';
                                if (!empty($item['photo'])) {
                                    $upload_path = '../uploads/' . $item['photo'];
                                    if (file_exists($upload_path)) {
                                        $item_photo = $upload_path;
                                    }
                                }
                            ?>
                            <div class="list-group-item px-0 py-2">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($item_photo); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="rounded me-2 item-thumb">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <small class="text-muted">
                                            Qty: <?php echo $item['quantity']; ?> | 
                                            Stock: <span class="<?php echo $item_available ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $item['stock']; ?>
                                            </span>
                                        </small>
                                    </div>
                                    <?php if (!$item_available): ?>
                                    <i class="fas fa-exclamation-triangle text-warning" 
                                       title="Insufficient stock"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
                endwhile;
                $stmt->close();
            else:
                $stmt->close();
            ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>No packages found matching your criteria.
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
</body>
</html>