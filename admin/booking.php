<?php error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Kunin lahat ng booking kasama items
$sql = "
    SELECT cb.*, 
           bi.equipment_id, bi.quantity, bi.unit_price, bi.total_price, 
           e.name AS equipment_name, e.photo
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    LEFT JOIN equipments e ON bi.equipment_id = e.id
";

if ($status_filter != 'all') {
    $sql .= " WHERE cb.status = '" . $conn->real_escape_string($status_filter) . "'";
}

$sql .= " ORDER BY cb.id DESC";

$result = $conn->query($sql);

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[$row['id']]['info'] = [
        'id' => $row['id'],
        'booking_ref' => $row['booking_ref'],
        'name' => $row['name'],
        'contact' => $row['contact'],
        'full_address' => $row['full_address'],
        'borrow_date' => $row['borrow_date'],
        'return_date' => $row['return_date'],
        'total_payment' => $row['total_payment'],
        'valid_id_path' => $row['valid_id_path'],
        'status' => $row['status']
    ];
    if ($row['equipment_name']) {
        $bookings[$row['id']]['items'][] = [
            'equipment_id' => $row['equipment_id'],
            'equipment_name' => $row['equipment_name'],
            'photo' => $row['photo'],
            'quantity' => $row['quantity'],
            'unit_price' => $row['unit_price'],
            'total_price' => $row['total_price']
        ];
    }
}

// Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = intval($_POST['booking_id']);
    $new_status = $_POST['status'];
    $old_status = $_POST['old_status'] ?? '';

    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status
        $stmt = $conn->prepare("UPDATE customer_booking SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $booking_id);
        $stmt->execute();
        
        // Kung mag-approve (Pending -> Approved)
        if ($old_status == 'Pending' && $new_status == 'Approved') {
            // Bawasan ang stock
            $items_sql = "SELECT equipment_id, quantity FROM booking_items WHERE booking_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $booking_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $update_stock = $conn->prepare("UPDATE equipments SET stock = stock - ? WHERE id = ?");
                $update_stock->bind_param("ii", $item['quantity'], $item['equipment_id']);
                $update_stock->execute();
            }
        }
        
        // Kung mag-cancel from Approved (Approved -> Canceled)
        if ($old_status == 'Approved' && $new_status == 'Canceled') {
            // Ibalik ang stock
            $items_sql = "SELECT equipment_id, quantity FROM booking_items WHERE booking_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $booking_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $update_stock = $conn->prepare("UPDATE equipments SET stock = stock + ? WHERE id = ?");
                $update_stock->bind_param("ii", $item['quantity'], $item['equipment_id']);
                $update_stock->execute();
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Booking status updated to " . $new_status;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating booking: " . $e->getMessage();
    }
    
    header("Location: booking.php" . ($status_filter != 'all' ? "?status=$status_filter" : ""));
    exit();
}

// Count bookings by status
$count_sql = "SELECT status, COUNT(*) as count FROM customer_booking GROUP BY status";
$count_result = $conn->query($count_sql);
$status_counts = ['Pending' => 0, 'Approved' => 0, 'Canceled' => 0];
while ($count_row = $count_result->fetch_assoc()) {
    $status_counts[$count_row['status']] = $count_row['count'];
}
$total_count = array_sum($status_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Management</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    body {
        background-color: #f5f6fa;
    }
    .filter-tabs {
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    .filter-btn {
        padding: 10px 20px;
        margin: 0 5px;
        border: 2px solid #e9ecef;
        background: white;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .filter-btn:hover {
        background: #f8f9fa;
    }
    .filter-btn.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    .filter-btn .badge {
        margin-left: 5px;
    }
    .card {
        border: none;
        border-radius: 8px;
    }
    .table thead {
        background-color: #f8f9fa;
    }
    .table thead th {
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 15px;
    }
    .table tbody tr {
        transition: all 0.2s;
    }
    .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .btn-view {
        padding: 6px 15px;
        font-size: 0.9rem;
    }
    .modal-header {
        background: #667eea;
        color: white;
    }
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    .info-group {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
    }
    .info-group h6 {
        color: #667eea;
        font-weight: 600;
        margin-bottom: 15px;
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 8px;
    }
    .info-item {
        display: flex;
        margin-bottom: 10px;
    }
    .info-label {
        font-weight: 600;
        min-width: 130px;
        color: #6c757d;
    }
    .info-value {
        color: #212529;
    }
    .valid-id-img {
        max-width: 100%;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }
    .item-card {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        display: flex;
        gap: 12px;
    }
    .item-photo {
        width: 70px;
        height: 70px;
        object-fit: cover;
        border-radius: 6px;
    }
    .alert-success, .alert-danger {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
</style>
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>

<main class="flex-grow-1 p-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <h1 class="h3 mb-4">Customer Bookings</h1>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?status=all" class="btn filter-btn <?= $status_filter == 'all' ? 'active' : '' ?>">
            <i class="fas fa-list me-1"></i> All
            <span class="badge bg-secondary"><?= $total_count ?></span>
        </a>
        <a href="?status=Pending" class="btn filter-btn <?= $status_filter == 'Pending' ? 'active' : '' ?>">
            <i class="fas fa-clock me-1"></i> Pending
            <span class="badge bg-warning"><?= $status_counts['Pending'] ?></span>
        </a>
        <a href="?status=Approved" class="btn filter-btn <?= $status_filter == 'Approved' ? 'active' : '' ?>">
            <i class="fas fa-check-circle me-1"></i> Approved
            <span class="badge bg-success"><?= $status_counts['Approved'] ?></span>
        </a>
        <a href="?status=Canceled" class="btn filter-btn <?= $status_filter == 'Canceled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle me-1"></i> Canceled
            <span class="badge bg-danger"><?= $status_counts['Canceled'] ?></span>
        </a>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Booking Ref</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Borrow Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No bookings found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $bookingId => $data): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($data['info']['booking_ref']) ?></strong></td>
                            <td><?= htmlspecialchars($data['info']['name']) ?></td>
                            <td><?= htmlspecialchars($data['info']['contact']) ?></td>
                            <td><?= date('M d, Y', strtotime($data['info']['borrow_date'])) ?></td>
                            <td><?= date('M d, Y', strtotime($data['info']['return_date'])) ?></td>
                            <td>
                                <span class="status-badge 
                                    <?php if($data['info']['status']=='Approved') echo 'bg-success'; 
                                          elseif($data['info']['status']=='Canceled') echo 'bg-danger'; 
                                          else echo 'bg-warning text-dark'; ?>">
                                    <?= htmlspecialchars($data['info']['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm btn-view" data-bs-toggle="modal" data-bs-target="#viewModal<?= $bookingId ?>">
                                    <i class="fa fa-eye me-1"></i> View
                                </button>
                            </td>
                        </tr>

                        <!-- Modal -->
                        <div class="modal fade" id="viewModal<?= $bookingId ?>" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-file-invoice me-2"></i>
                                            Booking Details - <?= htmlspecialchars($data['info']['booking_ref']) ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <!-- Customer Info -->
                                                <div class="info-group">
                                                    <h6><i class="fas fa-user-circle me-2"></i>Customer Information</h6>
                                                    <div class="info-item">
                                                        <span class="info-label">Name:</span>
                                                        <span class="info-value"><?= htmlspecialchars($data['info']['name']) ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Contact:</span>
                                                        <span class="info-value"><?= htmlspecialchars($data['info']['contact']) ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Address:</span>
                                                        <span class="info-value"><?= htmlspecialchars($data['info']['full_address']) ?></span>
                                                    </div>
                                                </div>

                                                <!-- Booking Info -->
                                                <div class="info-group">
                                                    <h6><i class="fas fa-calendar-alt me-2"></i>Booking Details</h6>
                                                    <div class="info-item">
                                                        <span class="info-label">Borrow Date:</span>
                                                        <span class="info-value"><?= date('F d, Y', strtotime($data['info']['borrow_date'])) ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Return Date:</span>
                                                        <span class="info-value"><?= date('F d, Y', strtotime($data['info']['return_date'])) ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Total Payment:</span>
                                                        <span class="info-value fw-bold text-primary">₱<?= number_format($data['info']['total_payment'], 2) ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <span class="info-label">Status:</span>
                                                        <span class="info-value">
                                                            <span class="status-badge 
                                                                <?php if($data['info']['status']=='Approved') echo 'bg-success'; 
                                                                      elseif($data['info']['status']=='Canceled') echo 'bg-danger'; 
                                                                      else echo 'bg-warning text-dark'; ?>">
                                                                <?= htmlspecialchars($data['info']['status']) ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Items -->
                                                <div class="info-group">
                                                    <h6><i class="fas fa-box me-2"></i>Booked Equipment</h6>
                                                    <?php if (isset($data['items'])): ?>
                                                        <?php foreach ($data['items'] as $item): ?>
                                                            <div class="item-card">
                                                                <img src="../uploads/<?= htmlspecialchars($item['photo']) ?>" class="item-photo" alt="Equipment">
                                                                <div class="flex-grow-1">
                                                                    <h6 class="mb-1"><?= htmlspecialchars($item['equipment_name']) ?></h6>
                                                                    <small class="text-muted">
                                                                        Qty: <?= htmlspecialchars($item['quantity']) ?> × 
                                                                        ₱<?= number_format($item['unit_price'], 2) ?> = 
                                                                        <strong class="text-primary">₱<?= number_format($item['total_price'], 2) ?></strong>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="info-group">
                                                    <h6><i class="fas fa-id-card me-2"></i>Valid ID</h6>
                                                    <?php 
                                                    $valid_id = $data['info']['valid_id_path'];
                                                    if (empty($valid_id)) {
                                                        echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No valid ID uploaded</div>';
                                                    } else {
                                                        if (strpos($valid_id, 'uploads/') !== false) {
                                                            $img_src = "../" . $valid_id;
                                                        } else {
                                                            $img_src = "../uploads/valid_ids/" . $valid_id;
                                                        }
                                                        $file_path = __DIR__ . '/../' . str_replace('../', '', $img_src);
                                                        if (file_exists($file_path)) {
                                                            echo '<img src="'. htmlspecialchars($img_src) .'" class="valid-id-img img-fluid rounded border" alt="Valid ID">';
                                                        } else {
                                                            echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>ID image not found</div>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <?php if ($data['info']['status'] == 'Pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="old_status" value="Pending">
                                                <input type="hidden" name="status" value="Approved">
                                                <button type="submit" name="update_status" value="1" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="old_status" value="Pending">
                                                <input type="hidden" name="status" value="Canceled">
                                                <button type="submit" name="update_status" value="1" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Cancel
                                                </button>
                                            </form>
                                        <?php elseif ($data['info']['status'] == 'Approved'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                                                <input type="hidden" name="old_status" value="Approved">
                                                <input type="hidden" name="status" value="Canceled">
                                                <!-- <button type="submit" name="update_status" value="1" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Cancel Booking
                                                </button> -->
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                This booking has been canceled
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 3000);
</script>
</body>
</html>