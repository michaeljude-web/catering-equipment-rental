<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../includes/db_connection.php';
include '../classes/CustomerAuth.php';

$auth = new CustomerAuth($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

$customer_query = "SELECT full_name, email FROM customers WHERE id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->execute([$customer_id]);
$customer_result = $customer_stmt->get_result();
$customer_info = $customer_result->fetch_assoc();

$bookings_query = "
    SELECT 
        cb.id,
        cb.booking_ref,
        cb.name,
        cb.contact,
        cb.full_address,
        cb.borrow_date,
        cb.return_date,
        cb.total_payment,
        cb.status,
        cb.valid_id_path
    FROM customer_booking cb
    WHERE cb.user_id = ?
    ORDER BY cb.id DESC
";
$bookings_stmt = $conn->prepare($bookings_query);
$bookings_stmt->execute([$customer_id]);
$bookings_result = $bookings_stmt->get_result();

$bookings = [];
while ($row = $bookings_result->fetch_assoc()) {
    $items_query = "
        SELECT 
            bi.*,
            e.name as equipment_name,
            e.photo
        FROM booking_items bi
        JOIN equipments e ON bi.equipment_id = e.id
        WHERE bi.booking_id = ?
    ";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->execute([$row['id']]);
    $items_result = $items_stmt->get_result();
    
    $row['items'] = [];
    while ($item = $items_result->fetch_assoc()) {
        $row['items'][] = $item;
    }
    
    $bookings[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - El Cielo Catering</title>
    <meta name="description" content="View your equipment rental bookings">
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/font/css/all.min.css">
    <style>
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .booking-card {
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        .booking-card:hover {
            background-color: #f8f9fa;
            border-left-color: #0d6efd;
            transform: translateX(2px);
        }
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        .detail-value {
            color: #212529;
            font-weight: 500;
        }
        .booking-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .info-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light min-vh-100 d-flex flex-column">
    <header class="bg-white shadow-sm border-bottom">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container position-relative" style="min-height:56px;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-calendar-check me-2 text-primary fs-4"></i>
                    <h1 class="mb-0 fs-5 fw-bold text-dark">My Bookings</h1>
                </div>

                <div class="position-absolute top-50 start-50 translate-middle">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- <a href="cart.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-shopping-cart me-1"></i>Cart
                    </a> -->
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5 flex-grow-1">
        <?php if (empty($bookings)): ?>
            <section class="text-center py-5">
                <div class="card border-0 shadow-sm mx-auto" style="max-width: 500px;">
                    <div class="card-body py-5">
                        <div class="mb-4">
                            <i class="fas fa-calendar-times fa-4x text-muted"></i>
                        </div>
                        <h2 class="h4 mb-3 text-dark">No bookings yet</h2>
                        <p class="text-muted mb-4">Start browsing equipment and make your first booking.</p>
                        <a href="dashboard.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-search me-2"></i>Browse Equipment
                        </a>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section>
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-bottom py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h2 class="h5 mb-0 fw-semibold">
                                        <i class="fas fa-list me-2 text-primary"></i>All Bookings
                                    </h2>
                                    <small class="text-muted">Click on a booking to view details</small>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($bookings as $index => $booking): ?>
                                <div class="booking-card border-bottom p-4 <?= $index === 0 ? 'border-top-0' : '' ?>" 
                                     data-bs-toggle="modal" 
                                     data-bs-target="#bookingModal<?= $booking['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h3 class="h6 mb-1 fw-bold text-primary">
                                                <i class="fas fa-ticket-alt me-1"></i>
                                                <?= htmlspecialchars($booking['booking_ref']) ?>
                                            </h3>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= htmlspecialchars($booking['name']) ?>
                                            </small>
                                        </div>
                                        <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                            <?= htmlspecialchars($booking['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="text-primary me-2">
                                                    <i class="fas fa-calendar-day"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Borrow Date</small>
                                                    <small class="fw-semibold">
                                                        <?= $booking['borrow_date'] ? date('M d, Y', strtotime($booking['borrow_date'])) : 'Not set' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="text-primary me-2">
                                                    <i class="fas fa-calendar-check"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Return Date</small>
                                                    <small class="fw-semibold">
                                                        <?= date('M d, Y', strtotime($booking['return_date'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="text-primary me-2">
                                                    <i class="fas fa-boxes"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Items</small>
                                                    <small class="fw-semibold">
                                                        <?= count($booking['items']) ?> equipment<?= count($booking['items']) !== 1 ? 's' : '' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                                        <span class="text-muted">
                                            <i class="fas fa-money-bill-wave me-1"></i>Total Payment
                                        </span>
                                        <span class="fw-bold text-primary fs-5">
                                            ₱<?= number_format($booking['total_payment'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="modal fade" id="bookingModal<?= $booking['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header booking-card-header text-white">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-info-circle me-2"></i>Booking Details
                                                </h5>
                                                <!-- <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button> -->
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-4 p-3 border rounded" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-muted d-block mb-1">Booking Reference</small>
                                                            <h3 class="h5 mb-0 fw-bold text-dark">
                                                                <i class="fas fa-ticket-alt me-2"></i>
                                                                <?= htmlspecialchars($booking['booking_ref']) ?>
                                                            </h3>
                                                        </div>
                                                        <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                                            <?= htmlspecialchars($booking['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="info-section">
                                                    <h4 class="h6 fw-bold mb-3">
                                                        <i class="fas fa-user me-2 text-primary"></i>Customer Information
                                                    </h4>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="detail-label">
                                                                <i class="fas fa-id-badge me-1"></i>Full Name
                                                            </div>
                                                            <div class="detail-value"><?= htmlspecialchars($booking['name']) ?></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="detail-label">
                                                                <i class="fas fa-phone me-1"></i>Contact Number
                                                            </div>
                                                            <div class="detail-value"><?= htmlspecialchars($booking['contact']) ?></div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="detail-label">
                                                                <i class="fas fa-map-marker-alt me-1"></i>Complete Address
                                                            </div>
                                                            <div class="detail-value"><?= htmlspecialchars($booking['full_address']) ?></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="info-section">
                                                    <h4 class="h6 fw-bold mb-3">
                                                        <i class="fas fa-calendar-alt me-2 text-primary"></i>Rental Period
                                                    </h4>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="detail-label">
                                                                <i class="fas fa-calendar-day me-1"></i>Borrow Date
                                                            </div>
                                                            <div class="detail-value">
                                                                <?= $booking['borrow_date'] ? date('F d, Y', strtotime($booking['borrow_date'])) : 'Not set' ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="detail-label">
                                                                <i class="fas fa-calendar-check me-1"></i>Return Date
                                                            </div>
                                                            <div class="detail-value">
                                                                <?= date('F d, Y', strtotime($booking['return_date'])) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="info-section">
                                                    <h4 class="h6 fw-bold mb-3">
                                                        <i class="fas fa-box me-2 text-primary"></i>Booked Equipment
                                                    </h4>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Equipment</th>
                                                                    <th class="text-center">Qty</th>
                                                                    <th class="text-end">Unit Price</th>
                                                                    <th class="text-end">Total</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($booking['items'] as $item): ?>
                                                                <tr>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <?php if (!empty($item['photo'])): ?>
                                                                            <img src="../uploads/<?= htmlspecialchars($item['photo']) ?>"
                                                                                 alt="<?= htmlspecialchars($item['equipment_name']) ?>"
                                                                                 class="rounded me-2"
                                                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                                                            <?php else: ?>
                                                                            <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center"
                                                                                 style="width: 40px; height: 40px;">
                                                                                <i class="fas fa-image text-muted"></i>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                            <span class="fw-semibold">
                                                                                <?= htmlspecialchars($item['equipment_name']) ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span class="badge bg-light text-dark border px-2">
                                                                            <?= $item['quantity'] ?>
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-end">₱<?= number_format($item['unit_price'], 2) ?></td>
                                                                    <td class="text-end fw-semibold text-primary">₱<?= number_format($item['total_price'], 2) ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                            <tfoot class="table-light">
                                                                <tr>
                                                                    <td colspan="3" class="text-end fw-bold">Total Payment:</td>
                                                                    <td class="text-end fw-bold text-primary fs-5">
                                                                        ₱<?= number_format($booking['total_payment'], 2) ?>
                                                                    </td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                </div>

                                                <?php if (!empty($booking['valid_id_path'])): ?>
                                                <div class="info-section">
                                                    <h4 class="h6 fw-bold mb-3">
                                                        <i class="fas fa-id-card me-2 text-primary"></i>Valid ID
                                                    </h4>
                                                    <a href="../<?= htmlspecialchars($booking['valid_id_path']) ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-file me-2"></i>View Uploaded ID
                                                    </a>
                                                </div>
                                                <?php endif; ?>

                                                <?php if ($booking['status'] === 'Approved' || $booking['status'] === 'Pending'): ?>
                                                <div class="alert alert-warning border-0 mb-0">
                                                    <div class="d-flex align-items-start">
                                                        <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                                                        <div>
                                                            <strong class="d-block mb-1">Late Return Penalty</strong>
                                                            <small>
                                                                Failure to return equipment by <strong><?= date('F d, Y', strtotime($booking['return_date'])) ?></strong> 
                                                                will incur a penalty fee
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i>Close
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="bg-white text-center py-4 shadow-sm mt-auto border-top">
        <div class="container">
            <p class="mb-0 text-muted">&copy; 2025 El Cielo Catering Services. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>