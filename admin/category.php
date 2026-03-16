<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';
include '../classes/Category.php';
include '../classes/Pagination.php';

$auth = new AdminAuth($conn);
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$categoryObj = new Category($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        $categoryObj->addCategory($category_name);
    }
    header("Location: category.php");
    exit();
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $categoryObj->deleteCategory($delete_id);
    header("Location: category.php");
    exit();
}

$limit = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

$total_categories = $categoryObj->countCategories();
$pagination = new Pagination($total_categories, $page, $limit);

$categories = $categoryObj->getCategories($limit, $pagination->getOffset());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Categories</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="flex-grow-1 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4">Category List</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus"></i> New Category
        </button>
    </div>
<hr>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Category Name</th>
                        <th class="text-center" style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['category_name']) ?></td>
                                <td class="text-center">
                                    <a href="category.php?delete=<?= $cat['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this category?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted">No categories found.</td>
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

<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label class="form-label">Category Name</label>
              <input type="text" name="category_name" class="form-control" required>
          </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_category" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
</body>
</html>
