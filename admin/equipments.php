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
    $query = "SELECT e.id, e.name, e.photo, e.price, c.category_name AS category 
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

    $photoName = null;
    if(isset($_FILES['photo']) && $_FILES['photo']['name'] != '') {
        $photoName = time().'_'.basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/".$photoName);
    }

    $stmt = $conn->prepare("INSERT INTO equipments (name, category_id, price, photo, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sids", $name, $category_id, $price, $photoName);
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

    if(isset($_FILES['photo']) && $_FILES['photo']['name'] != '') {
        $photoName = time().'_'.basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/".$photoName);
        $stmt = $conn->prepare("UPDATE equipments SET name=?, category_id=?, price=?, photo=? WHERE id=?");
        $stmt->bind_param("sidsi", $name, $category_id, $price, $photoName, $id);
    } else {
        $stmt = $conn->prepare("UPDATE equipments SET name=?, category_id=?, price=? WHERE id=?");
        $stmt->bind_param("sidi", $name, $category_id, $price, $id);
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

$query = $conn->query("SELECT e.id, e.name, e.photo, e.price, c.category_name AS category 
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="equipmentTableBody">
                        <?php foreach($equipments as $eq): ?>
                        <tr>
                            <td><?= htmlspecialchars($eq['name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if(!empty($eq['photo'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($eq['photo']) ?>" width="50" class="rounded">
                                <?php else: ?>N/A<?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($eq['category'] ?? 'N/A') ?></td>
                            <td><?= number_format($eq['price'] ?? 0,2) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="openEditModal(
                                    '<?= $eq['id'] ?>',
                                    '<?= htmlspecialchars($eq['name'], ENT_QUOTES) ?>',
                                    '<?= $eq['category'] ?>',
                                    '<?= $eq['price'] ?>',
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
          <input type="text" name="name" placeholder="Name" class="form-control mb-2" required>
          <select name="category_id" class="form-select mb-2" required>
              <?php foreach($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= $cat['category_name'] ?></option>
              <?php endforeach; ?>
          </select>
          <input type="number" name="price" placeholder="Price" step="0.01" class="form-control mb-2" required>
          <input type="file" name="photo" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_equipment" class="btn btn-success">Add Equipment</button>
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
          <input type="text" name="name" id="edit_name" class="form-control mb-2" required>
          <select name="category_id" id="edit_category_id" class="form-select mb-2" required>
              <?php foreach($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= $cat['category_name'] ?></option>
              <?php endforeach; ?>
          </select>
          <input type="number" name="price" id="edit_price" class="form-control mb-2" step="0.01" required>
          <div id="edit_photo_preview" class="mb-2"></div>
          <input type="file" name="photo" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="submit" name="edit_equipment" class="btn btn-success">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script src="script.js"></script>

</body>
</html>