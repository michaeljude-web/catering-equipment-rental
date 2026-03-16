<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

define('ENC_KEY', 'YourSecretKey1234567890abcdef12');
define('ENC_METHOD', 'AES-256-CBC');

function enc($data) {
    if ($data === null || $data === '') return '';
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, ENC_METHOD, ENC_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function dec($data) {
    if ($data === null || $data === '') return '';
    $decoded = base64_decode($data);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, ENC_METHOD, ENC_KEY, 0, $iv);
}

$limit = 9;
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $firstname      = trim($_POST['firstname']      ?? '');
    $lastname       = trim($_POST['lastname']       ?? '');
    $age            = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $address        = trim($_POST['address']        ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $username       = trim($_POST['username']       ?? '');
    $password       = $_POST['password']            ?? '';

    if (!empty($firstname) && !empty($lastname) && !empty($username) && !empty($password)) {
        $e_firstname = enc($firstname);
        $e_lastname  = enc($lastname);
        $e_address   = enc($address);
        $e_contact   = enc($contact_number);
        $e_username  = enc($username);
        $e_password  = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO staff_info (firstname, lastname, age, address, contact_number, username, password_hash) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssissss", $e_firstname, $e_lastname, $age, $e_address, $e_contact, $e_username, $e_password);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: staff.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $id             = intval($_POST['edit_id']);
    $firstname      = trim($_POST['firstname']      ?? '');
    $lastname       = trim($_POST['lastname']       ?? '');
    $age            = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $address        = trim($_POST['address']        ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $username       = trim($_POST['username']       ?? '');
    $password       = $_POST['password']            ?? '';

    if ($id > 0 && !empty($firstname) && !empty($lastname) && !empty($username)) {
        $e_firstname = enc($firstname);
        $e_lastname  = enc($lastname);
        $e_address   = enc($address);
        $e_contact   = enc($contact_number);
        $e_username  = enc($username);

        if (!empty($password)) {
            $e_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE staff_info SET firstname=?, lastname=?, age=?, address=?, contact_number=?, username=?, password_hash=? WHERE id=?");
            $stmt->bind_param("ssissssi", $e_firstname, $e_lastname, $age, $e_address, $e_contact, $e_username, $e_password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE staff_info SET firstname=?, lastname=?, age=?, address=?, contact_number=?, username=? WHERE id=?");
            $stmt->bind_param("sisssi", $e_firstname, $e_lastname, $age, $e_address, $e_contact, $e_username, $id);
        }
        $stmt->execute();
        $stmt->close();
    }
    header("Location: staff.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM staff_info WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: staff.php");
    exit();
}

$total       = (int)$conn->query("SELECT COUNT(*) FROM staff_info")->fetch_row()[0];
$total_pages = max(1, ceil($total / $limit));
$page        = min(max($page, 1), $total_pages);
$offset      = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT id, firstname, lastname, age, address, contact_number, username FROM staff_info ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$staff_list = array_map(function($s) {
    return [
        'id'             => $s['id'],
        'firstname'      => dec($s['firstname']),
        'lastname'       => dec($s['lastname']),
        'age'            => $s['age'],
        'address'        => dec($s['address']),
        'contact_number' => dec($s['contact_number']),
        'username'       => dec($s['username']),
    ];
}, $rows);
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
                        <th class="text-center" style="width:100px;">Action</th>
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
                                <button class="btn btn-sm btn-warning me-1"
                                    onclick="openEdit(
                                        <?= $staff['id'] ?>,
                                        '<?= htmlspecialchars($staff['firstname'],      ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($staff['lastname'],       ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($staff['age'] ?? '',      ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($staff['contact_number'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($staff['address'],        ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($staff['username'],       ENT_QUOTES) ?>'
                                    )">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="staff.php?delete=<?= $staff['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this staff member?');">
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

        <?php if ($total_pages > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <small class="text-muted">Page <?= $page ?> of <?= $total_pages ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="staff.php?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
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

<div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="firstname" id="e_firstname" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="lastname" id="e_lastname" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" id="e_age" class="form-control" min="1" max="120">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" id="e_contact" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="e_address" class="form-control" rows="2"></textarea>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="e_username" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
                        <input type="password" name="password" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit_staff" class="btn btn-warning">Update Staff</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
function openEdit(id, firstname, lastname, age, contact, address, username) {
    document.getElementById('edit_id').value      = id;
    document.getElementById('e_firstname').value  = firstname;
    document.getElementById('e_lastname').value   = lastname;
    document.getElementById('e_age').value        = age;
    document.getElementById('e_contact').value    = contact;
    document.getElementById('e_address').value    = address;
    document.getElementById('e_username').value   = username;
    new bootstrap.Modal(document.getElementById('editStaffModal')).show();
}
</script>
</body>
</html>