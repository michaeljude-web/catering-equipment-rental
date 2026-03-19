<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connection.php';
require_once '../classes/AdminAuth.php';
require_once '../classes/Pagination.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    $package_name = trim($_POST['package_name']);
    $price = trim($_POST['price']);
    $equipment_ids = isset($_POST['equipment_ids']) ? $_POST['equipment_ids'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];

    if (!empty($package_name) && !empty($price)) {
        $conn->begin_transaction();
        try {
            foreach ($equipment_ids as $index => $eq_id) {
                if (!empty($eq_id)) {
                    $qty = intval($quantities[$index]);
                    if ($qty > 0) {
                        $checkStock = $conn->query("SELECT stock FROM equipments WHERE id = $eq_id");
                        $stockData = $checkStock->fetch_assoc();
                        if ($stockData['stock'] < $qty) {
                            throw new Exception("Insufficient stock for equipment ID $eq_id. Available: {$stockData['stock']}, Required: $qty");
                        }
                    }
                }
            }

            $stmt = $conn->prepare("INSERT INTO packages (package_name, price, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("sd", $package_name, $price);
            $stmt->execute();
            $package_id = $conn->insert_id;
            
            if (!empty($equipment_ids)) {
                foreach ($equipment_ids as $index => $eq_id) {
                    if (!empty($eq_id)) {
                        $qty = intval($quantities[$index]);
                        if ($qty > 0) {
                            $itemStmt = $conn->prepare("INSERT INTO package_items (package_id, equipment_id, quantity) VALUES (?, ?, ?)");
                            $itemStmt->bind_param("iii", $package_id, $eq_id, $qty);
                            $itemStmt->execute();

                            $conn->query("UPDATE equipments SET stock = stock - $qty WHERE id = $eq_id");
                        }
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Package created successfully and stock updated!";
            header("Location: packages.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            header("Location: packages.php");
            exit();
        }
    }
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    $conn->begin_transaction();
    try {
        $itemsQuery = $conn->query("SELECT equipment_id, quantity FROM package_items WHERE package_id = $delete_id");
        while ($item = $itemsQuery->fetch_assoc()) {
            $conn->query("UPDATE equipments SET stock = stock + {$item['quantity']} WHERE id = {$item['equipment_id']}");
        }
        
        $conn->query("DELETE FROM package_items WHERE package_id=$delete_id");
        $conn->query("DELETE FROM packages WHERE id=$delete_id");
        
        $conn->commit();
        $_SESSION['success_message'] = "Package deleted and stock restored!";
        header("Location: packages.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting package: " . $e->getMessage();
        header("Location: packages.php");
        exit();
    }
}

$limit = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

$totalResult = $conn->query("SELECT COUNT(*) as total FROM packages");
$total = $totalResult->fetch_assoc()['total'];

$pagination = new Pagination($total, $page, $limit);
$offset = $pagination->getOffset();

$packagesQuery = $conn->query("SELECT * FROM packages ORDER BY id DESC LIMIT $limit OFFSET $offset");

$packages = [];
if ($packagesQuery) {
    while ($row = $packagesQuery->fetch_assoc()) {
        $packages[] = $row;
    }
}

$equipments = $conn->query("SELECT id, name as equipment_name, stock FROM equipments ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Packages</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
.equipment-photo {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}
.equipment-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
}
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="flex-grow-1 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4">Package List</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
            <i class="fas fa-plus"></i> New Package
        </button>
    </div>
    <hr>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Package Name</th>
                        <th>Price</th>
                        <th class="text-center" style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($packages)): ?>
                        <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><?= htmlspecialchars($pkg['package_name']) ?></td>
                                <td>₱<?= number_format($pkg['price'], 2) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewPackageModal<?= $pkg['id'] ?>">
                                        <i class="fas fa-eye"></i> 
                                    </button>
                                    <a href="packages.php?delete=<?= $pkg['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this package? Stock will be restored.');">
                                        <i class="fas fa-trash"></i> 
                                    </a>
                                </td>
                            </tr>

                            <div class="modal fade" id="viewPackageModal<?= $pkg['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title">
                                                <i class="fas fa-box"></i> Package Details
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6 class="text-muted mb-1">Package Name</h6>
                                                            <h4 class="mb-0"><?= htmlspecialchars($pkg['package_name']) ?></h4>
                                                        </div>
                                                        <div class="col-md-6 text-end">
                                                            <h6 class="text-muted mb-1">Package Price</h6>
                                                            <h4 class="mb-0 text-success">₱<?= number_format($pkg['price'], 2) ?></h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>
                                            <h5 class="mb-3"><i class="fas fa-list"></i> Included Items</h5>
                                            
                                            <?php 
                                            $itemsQuery = $conn->query("
                                                SELECT pi.*, e.name as equipment_name, e.photo, c.category_name 
                                                FROM package_items pi
                                                LEFT JOIN equipments e ON pi.equipment_id = e.id
                                                LEFT JOIN categories c ON e.category_id = c.id
                                                WHERE pi.package_id = " . $pkg['id']
                                            );
                                            
                                            $items = [];
                                            if ($itemsQuery) {
                                                while ($item = $itemsQuery->fetch_assoc()) {
                                                    $items[] = $item;
                                                }
                                            }
                                            
                                            if (!empty($items)): ?>
                                                <?php foreach ($items as $item): ?>
                                                    <div class="equipment-card">
                                                        <div class="row align-items-center">
                                                            <div class="col-auto">
                                                                <?php if(!empty($item['photo'])): ?>
                                                                    <img src="../uploads/<?= htmlspecialchars($item['photo']) ?>" 
                                                                         alt="<?= htmlspecialchars($item['equipment_name'] ?? 'Equipment') ?>" 
                                                                         class="equipment-photo">
                                                                <?php else: ?>
                                                                    <div class="equipment-photo bg-secondary d-flex align-items-center justify-content-center">
                                                                        <i class="fas fa-image fa-2x text-white"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col">
                                                                <h5 class="mb-1">
                                                                    <?= htmlspecialchars($item['equipment_name'] ?? 'N/A') ?>
                                                                    <span class="badge bg-primary"><?= $item['quantity'] ?>x</span>
                                                                </h5>
                                                                <p class="mb-0 text-muted">
                                                                    <i class="fas fa-tag"></i> 
                                                                    <strong>Category:</strong> <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> No items in this package.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No packages found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination->totalPages() > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <small class="text-muted">
                Page <?= $pagination->currentPage() ?> of <?= $pagination->totalPages() ?>
            </small>
            <?= $pagination->render('pagination-sm mb-0') ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal fade" id="addPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="packageForm">
            <div class="modal-header">
                <h5 class="modal-title">Add New Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Package Name <span class="text-danger">*</span></label>
                        <input type="text" name="package_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>
                </div>

                <hr>
                <h6 class="mb-3">Package Items</h6>
                <div id="itemsContainer">
                    <div class="row mb-2 item-row">
                        <div class="col-md-7">
                            <label class="form-label small">Equipment</label>
                            <select name="equipment_ids[]" class="form-select equipment-select" onchange="updateStockInfo(this)">
                                <option value="">Select Equipment</option>
                                <?php 
                                $equipments->data_seek(0);
                                while ($eq = $equipments->fetch_assoc()): 
                                    $disabled = $eq['stock'] == 0 ? 'disabled' : '';
                                    $stockText = $eq['stock'] == 0 ? '[OUT OF STOCK]' : "[Stock: {$eq['stock']}]";
                                ?>
                                    <option value="<?= $eq['id'] ?>" data-stock="<?= $eq['stock'] ?>" <?= $disabled ?>>
                                        <?= htmlspecialchars($eq['equipment_name']) ?> <?= $stockText ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted stock-info"></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Quantity</label>
                            <input type="number" name="quantities[]" class="form-control form-control-sm quantity-input" value="1" min="1" disabled>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm w-100 remove-item" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-success add-item-btn" data-container="itemsContainer">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_package" class="btn btn-primary">Save Package</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
function updateStockInfo(selectElement) {
    const row = selectElement.closest('.item-row');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const stock = selectedOption.dataset.stock || 0;
    const stockInfo = row.querySelector('.stock-info');
    const qtyInput = row.querySelector('.quantity-input');
    
    if (selectElement.value) {
        stockInfo.textContent = `Available stock: ${stock}`;
        qtyInput.disabled = false;
        qtyInput.max = stock;
        qtyInput.value = Math.min(1, stock);
    } else {
        stockInfo.textContent = '';
        qtyInput.disabled = true;
        qtyInput.value = 1;
    }
}

document.querySelectorAll('.add-item-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const containerId = this.getAttribute('data-container');
        const container = document.getElementById(containerId);
        const firstRow = container.querySelector('.item-row');
        const newRow = firstRow.cloneNode(true);
        
        newRow.querySelector('.equipment-select').value = '';
        newRow.querySelector('.stock-info').textContent = '';
        newRow.querySelector('.quantity-input').value = 1;
        newRow.querySelector('.quantity-input').disabled = true;
        newRow.querySelector('.remove-item').disabled = false;
        
        container.appendChild(newRow);
        updateRemoveButtons(container);
    });
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
        const itemRow = e.target.closest('.item-row');
        const container = itemRow.parentElement;
        itemRow.remove();
        updateRemoveButtons(container);
    }
});

function updateRemoveButtons(container) {
    const rows = container.querySelectorAll('.item-row');
    rows.forEach((row, index) => {
        const btn = row.querySelector('.remove-item');
        btn.disabled = (rows.length === 1);
    });
}

document.getElementById('packageForm').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.item-row');
    let valid = true;
    
    rows.forEach(row => {
        const select = row.querySelector('.equipment-select');
        const qtyInput = row.querySelector('.quantity-input');
        
        if (select.value) {
            const maxStock = parseInt(qtyInput.max);
            const qty = parseInt(qtyInput.value);
            
            if (qty > maxStock) {
                e.preventDefault();
                alert(`Quantity exceeds available stock! Maximum: ${maxStock}`);
                valid = false;
                return false;
            }
        }
    });
    
    return valid;
});
</script>

</body>
</html>