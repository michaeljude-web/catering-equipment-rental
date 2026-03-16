<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';
include '../classes/Pagination.php';

$auth = new AdminAuth($conn);
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

if(isset($_GET['ajax_search'])) {
    $search = $_GET['search'] ?? '';
    
    $whereClause = '';
    $params = [];
    $types = '';
    
    if(!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $whereClause = "WHERE e.name LIKE ? OR c.category_name LIKE ?";
        $params = [$searchTerm, $searchTerm];
        $types = 'ss';
    }
    
    $countQuery = "SELECT COUNT(*) as total FROM equipments e LEFT JOIN categories c ON e.category_id = c.id $whereClause";
    $query = "SELECT e.id, e.name, e.photo, e.price, e.quantity, e.stock, c.category_name AS category 
              FROM equipments e 
              LEFT JOIN categories c ON e.category_id = c.id 
              $whereClause
              ORDER BY e.id DESC 
              LIMIT ? OFFSET ?";
    
    if(!empty($search)) {
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalResult = $countStmt->get_result()->fetch_assoc();
        $total = $totalResult['total'];
        
        $pagination = new Pagination($total, $page, $limit);
        $offset = $pagination->getOffset();
        
        $stmt = $conn->prepare($query);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $totalResult = $conn->query($countQuery)->fetch_assoc();
        $total = $totalResult['total'];
        
        $pagination = new Pagination($total, $page, $limit);
        $offset = $pagination->getOffset();
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $equipments = [];
    while($row = $result->fetch_assoc()) {
        $equipments[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $equipments,
        'pagination' => $pagination->render('pagination-sm mb-0', '?page='),
        'totalPages' => $pagination->totalPages(),
        'currentPage' => $pagination->currentPage(),
        'total' => $total
    ]);
    exit();
}

if(isset($_POST['add_equipment'])) {
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];

    $photoName = null;
    if(isset($_FILES['photo']) && $_FILES['photo']['name'] != '') {
        $photoName = time().'_'.basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/".$photoName);
    }

    $stmt = $conn->prepare("INSERT INTO equipments (name, category_id, price, quantity, stock, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sidiis", $name, $category_id, $price, $quantity, $quantity, $photoName);
    $stmt->execute();
    $stmt->close();

    header("Location: equipments.php");
    exit();
}

if(isset($_POST['edit_equipment'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    
    $currentStmt = $conn->prepare("SELECT quantity, stock FROM equipments WHERE id=?");
    $currentStmt->bind_param("i", $id);
    $currentStmt->execute();
    $currentData = $currentStmt->get_result()->fetch_assoc();
    $currentQuantity = $currentData['quantity'];
    $currentStock = $currentData['stock'];
    $currentStmt->close();
    
    $quantityDiff = $quantity - $currentQuantity;
    $newStock = $currentStock + $quantityDiff;
    
    $newStock = max(0, $newStock);

    if(isset($_FILES['photo']) && $_FILES['photo']['name'] != '') {
        $photoName = time().'_'.basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/".$photoName);
        $stmt = $conn->prepare("UPDATE equipments SET name=?, category_id=?, price=?, quantity=?, stock=?, photo=? WHERE id=?");
        $stmt->bind_param("sidiisi", $name, $category_id, $price, $quantity, $newStock, $photoName, $id);
    } else {
        $stmt = $conn->prepare("UPDATE equipments SET name=?, category_id=?, price=?, quantity=?, stock=? WHERE id=?");
        $stmt->bind_param("sidiii", $name, $category_id, $price, $quantity, $newStock, $id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: equipments.php");
    exit();
}

if(isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    $result = $conn->query("SELECT photo FROM equipments WHERE id=$id");
    if($row = $result->fetch_assoc()) {
        if(!empty($row['photo']) && file_exists("../uploads/".$row['photo'])) {
            unlink("../uploads/".$row['photo']);
        }
    }

    $conn->query("DELETE FROM equipments WHERE id=$id");

    header("Location: equipments.php");
    exit();
}

$totalResult = $conn->query("SELECT COUNT(*) as total FROM equipments");
$total = $totalResult->fetch_assoc()['total'];

$pagination = new Pagination($total, $page, $limit);
$offset = $pagination->getOffset();

$query = $conn->query("SELECT e.id, e.name, e.photo, e.price, e.quantity, e.stock, c.category_name AS category 
                       FROM equipments e 
                       LEFT JOIN categories c ON e.category_id = c.id 
                       ORDER BY e.id DESC 
                       LIMIT $limit OFFSET $offset");

$equipments = [];
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $equipments[] = $row;
    }
} else {
    die("Query failed: " . $conn->error);
}

$catQuery = $conn->query("SELECT * FROM categories");
$categories = [];
if($catQuery){
    while($cat = $catQuery->fetch_assoc()){
        $categories[] = $cat;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Equipments</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
.search-container {
    position: relative;
}
.search-loading {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
}
.badge-stock {
    font-size: 0.85rem;
}
.stock-low {
    background-color: #dc3545;
}
.stock-medium {
    background-color: #ffc107;
}
.stock-high {
    background-color: #28a745;
}
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="flex-grow-1 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">Equipments</h1>
    </div>

    <div class="row mb-3">
        <div class="col-md-8">
            <div class="search-container">
                <input type="text" 
                       id="searchInput" 
                       class="form-control form-control-lg" 
                       placeholder="Search equipment by name or category..." 
                       autocomplete="off">
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                <i class="fas fa-plus"></i> Add Equipment
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Photo</th>
                            <th>Category</th>
                            <th>Price</th>
                            <!-- <th>Quantity</th> -->
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="equipmentTableBody">
                        <?php foreach($equipments as $eq): 
                            $stockPercentage = $eq['quantity'] > 0 ? ($eq['stock'] / $eq['quantity']) * 100 : 0;
                            $stockClass = $stockPercentage <= 30 ? 'stock-low' : ($stockPercentage <= 60 ? 'stock-medium' : 'stock-high');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($eq['name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if(!empty($eq['photo'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($eq['photo']) ?>" width="50" class="rounded">
                                <?php else: ?>N/A<?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($eq['category'] ?? 'N/A') ?></td>
                            <td>₱<?= number_format($eq['price'] ?? 0,2) ?></td>
                           
                            <td>
                                <span class="badge badge-stock <?= $stockClass ?>">
                                    <?= $eq['stock'] ?? 0 ?> / <?= $eq['quantity'] ?? 0 ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="openEditModal(
                                    '<?= $eq['id'] ?>',
                                    '<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>',
                                    '<?= $eq['category'] ?>',
                                    '<?= $eq['price'] ?>',
                                    '<?= $eq['quantity'] ?>',
                                    '<?= $eq['stock'] ?>',
                                    '<?= $eq['photo'] ?>'
                                )"><i class="fas fa-edit"></i></button>

                                <a href="?delete_id=<?= $eq['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this equipment?');">
                                   <i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted">
                <span id="totalInfo">Showing <?= count($equipments) ?> of <?= $total ?> equipments</span>
            </div>
            <div id="paginationContainer">
                <?= $pagination->render() ?>
            </div>
        </div>
    </div>

    <div id="noResults" class="text-center mt-4" style="display: none;">
        <div class="alert alert-info">
            <i class="fas fa-search"></i> No equipment found matching your search.
        </div>
    </div>

</main>

<div class="modal fade" id="addEquipmentModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Equipment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" required>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select" required>
                  <option value="">Select Category</option>
                  <?php foreach($categories as $cat): ?>
                      <option value="<?= $cat['id'] ?>"><?= $cat['category_name'] ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Price</label>
              <input type="number" name="price" step="0.01" class="form-control" required>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Quantity (Total Items)</label>
              <input type="number" name="quantity" min="0" class="form-control" required>
              <small class="text-muted">Total number of items. Stock will be set to this initially.</small>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Photo (Optional)</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_equipment" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Equipment
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editEquipmentModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Equipment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          
          <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="category_id" id="edit_category_id" class="form-select" required>
                  <?php foreach($categories as $cat): ?>
                      <option value="<?= $cat['id'] ?>"><?= $cat['category_name'] ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Price</label>
              <input type="number" name="price" id="edit_price" class="form-control" step="0.01" required>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Quantity (Total Items)</label>
              <input type="number" name="quantity" id="edit_quantity" min="0" class="form-control" required>
              <small class="text-muted">Current Stock: <strong id="edit_stock_display">0</strong></small>
          </div>
          
          <div id="edit_photo_preview" class="mb-2"></div>
          <div class="mb-3">
              <label class="form-label">Photo (Optional)</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="edit_equipment" class="btn btn-success">
            <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
function openEditModal(id, name, category, price, quantity, stock, photo) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_quantity').value = quantity;
    document.getElementById('edit_stock_display').textContent = stock;

    const categorySelect = document.getElementById('edit_category_id');
    for(let i = 0; i < categorySelect.options.length; i++) {
        if(categorySelect.options[i].text === category) {
            categorySelect.selectedIndex = i;
            break;
        }
    }

    const photoPreview = document.getElementById('edit_photo_preview');
    if(photo) {
        photoPreview.innerHTML = `<img src="../uploads/${photo}" width="100" class="rounded mb-2">`;
    } else {
        photoPreview.innerHTML = '';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
    modal.show();
}

let searchTimeout;
const searchInput = document.getElementById('searchInput');
const searchLoading = document.querySelector('.search-loading');
const equipmentTableBody = document.getElementById('equipmentTableBody');
const paginationContainer = document.getElementById('paginationContainer');
const totalInfo = document.getElementById('totalInfo');
const noResults = document.getElementById('noResults');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchLoading.style.display = 'block';
    
    searchTimeout = setTimeout(() => {
        const searchValue = this.value.trim();
        performSearch(searchValue);
    }, 500);
});

function performSearch(searchTerm) {
    fetch(`equipments.php?ajax_search=1&search=${encodeURIComponent(searchTerm)}&page=1`)
        .then(response => response.json())
        .then(data => {
            searchLoading.style.display = 'none';
            
            if(data.data.length === 0) {
                equipmentTableBody.innerHTML = '';
                noResults.style.display = 'block';
                paginationContainer.innerHTML = '';
                totalInfo.textContent = 'No equipments found';
            } else {
                noResults.style.display = 'none';
                
                equipmentTableBody.innerHTML = data.data.map(eq => {
                    const stockPercentage = eq.quantity > 0 ? (eq.stock / eq.quantity) * 100 : 0;
                    const stockClass = stockPercentage <= 30 ? 'stock-low' : (stockPercentage <= 60 ? 'stock-medium' : 'stock-high');
                    
                    return `
                        <tr>
                            <td>${escapeHtml(eq.name || 'N/A')}</td>
                            <td>${eq.photo ? `<img src="../uploads/${escapeHtml(eq.photo)}" width="50" class="rounded">` : 'N/A'}</td>
                            <td>${escapeHtml(eq.category || 'N/A')}</td>
                            <td>₱${parseFloat(eq.price || 0).toFixed(2)}</td>
                            <td><span class="badge bg-secondary">${eq.quantity || 0}</span></td>
                            <td>
                                <span class="badge badge-stock ${stockClass}">
                                    ${eq.stock || 0} / ${eq.quantity || 0}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="openEditModal('${eq.id}','${escapeHtml(eq.name)}','${escapeHtml(eq.category)}','${eq.price}','${eq.quantity}','${eq.stock}','${eq.photo || ''}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete_id=${eq.id}" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
                
                paginationContainer.innerHTML = data.pagination;
                totalInfo.textContent = `Showing ${data.data.length} of ${data.total} equipments`;
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchLoading.style.display = 'none';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>