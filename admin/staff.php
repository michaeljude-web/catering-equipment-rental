<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';
include '../classes/Staff.php';
include '../classes/Pagination.php';

$auth = new AdminAuth($conn);
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$staffObj = new Staff($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($firstname) && !empty($lastname) && !empty($username) && !empty($password)) {
        $staffObj->addStaff($firstname, $lastname, $age, $address, $contact_number, $username, $password);
    }
    header("Location: staff.php");
    exit();
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $staffObj->deleteStaff($delete_id);
    header("Location: staff.php");
    exit();
}

$limit = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

$total_staff = $staffObj->countStaff();
$pagination = new Pagination($total_staff, $page, $limit);

$staff_list = $staffObj->getStaff($limit, $pagination->getOffset());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Staff Management</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="flex-grow-1 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4">Staff List</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-plus"></i> New Staff
        </button>
    </div>
<hr>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Contact Number</th>
                        <th>Username</th>
                        <th>Address</th>
                        <th class="text-center" style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($staff_list)): ?>
                        <?php foreach ($staff_list as $staff): ?>
                            <tr>
                                <td><?= htmlspecialchars($staff['firstname'] . ' ' . $staff['lastname']) ?></td>
                                <td><?= $staff['age'] ? htmlspecialchars($staff['age']) : '-' ?></td>
                                <td><?= $staff['contact_number'] ? htmlspecialchars($staff['contact_number']) : '-' ?></td>
                                <td><?= htmlspecialchars($staff['username']) ?></td>
                                <td><?= $staff['address'] ? htmlspecialchars($staff['address']) : '-' ?></td>
                                <td class="text-center">
                                    <a href="staff.php?delete=<?= $staff['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this staff member?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No staff found.</td>
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

<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Staff</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div class="row">
              <div class="col-md-6 mb-3">
                  <label class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" name="firstname" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" name="lastname" class="form-control" required>
              </div>
          </div>
          <div class="row">
              <div class="col-md-6 mb-3">
                  <label class="form-label">Age</label>
                  <input type="number" name="age" class="form-control" min="1" max="120">
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Contact Number</label>
                  <input type="text" name="contact_number" class="form-control">
              </div>
          </div>
          <div class="mb-3">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"></textarea>
          </div>
          <hr>
          <div class="row">
              <div class="col-md-6 mb-3">
                  <label class="form-label">Username <span class="text-danger">*</span></label>
                  <input type="text" name="username" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                  <label class="form-label">Password <span class="text-danger">*</span></label>
                  <input type="password" name="password" class="form-control" required>
              </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_staff" class="btn btn-primary">Save Staff</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
</body>
</html>