<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get statistics
$stats = [];

// Total Bookings
$result = $conn->query("SELECT COUNT(*) as total FROM customer_booking");
$stats['total_bookings'] = $result->fetch_assoc()['total'];

// Total Customers
$result = $conn->query("SELECT COUNT(*) as total FROM customers");
$stats['total_customers'] = $result->fetch_assoc()['total'];

// Total Equipment
$result = $conn->query("SELECT COUNT(*) as total FROM equipments");
$stats['total_equipments'] = $result->fetch_assoc()['total'];

// Total Revenue
$result = $conn->query("SELECT SUM(total_payment) as total FROM customer_booking WHERE status = 'Approved'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Booking Status Count
$pending = $conn->query("SELECT COUNT(*) as total FROM customer_booking WHERE status = 'Pending'")->fetch_assoc()['total'];
$confirmed = $conn->query("SELECT COUNT(*) as total FROM customer_booking WHERE status = 'Confirm'")->fetch_assoc()['total'];
$approved = $conn->query("SELECT COUNT(*) as total FROM customer_booking WHERE status = 'Approved'")->fetch_assoc()['total'];
$canceled = $conn->query("SELECT COUNT(*) as total FROM customer_booking WHERE status = 'Canceled'")->fetch_assoc()['total'];

// Recent Bookings
$recent_bookings = $conn->query("
    SELECT cb.*, c.full_name, c.email 
    FROM customer_booking cb 
    JOIN customers c ON cb.user_id = c.id 
    ORDER BY cb.id DESC 
    LIMIT 8
");

// Top Equipment by Bookings
$top_equipment = $conn->query("
    SELECT e.name, e.photo, cat.category_name, COUNT(bi.id) as booking_count, SUM(bi.quantity) as total_rented
    FROM booking_items bi
    JOIN equipments e ON bi.equipment_id = e.id
    JOIN categories cat ON e.category_id = cat.id
    GROUP BY e.id
    ORDER BY booking_count DESC
    LIMIT 5
");

// Monthly Revenue (last 6 months)
$monthly_revenue = $conn->query("
    SELECT 
        DATE_FORMAT(borrow_date, '%b %Y') as month,
        SUM(total_payment) as revenue
    FROM customer_booking
    WHERE status = 'Approved'
    AND borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(borrow_date), MONTH(borrow_date)
    ORDER BY borrow_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    body {
        background: #f5f5f5;
    }
    .stat-card {
        border-radius: 10px;
        border: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .stat-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }
    .card-clean {
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .status-card {
        border-radius: 10px;
        border: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .status-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .badge-status {
        padding: 0.4rem 0.8rem;
        font-size: 0.875rem;
        border-radius: 5px;
        font-weight: 500;
    }
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
    .top-item {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 3px solid #0d6efd;
        transition: all 0.2s ease;
    }
    .top-item:hover {
        background: #e9ecef;
        transform: translateX(3px);
    }
    .revenue-item {
        padding: 0.75rem;
        border-left: 3px solid #198754;
        margin-bottom: 0.5rem;
        background: #f8f9fa;
        border-radius: 5px;
    }
    .card-header-simple {
        background: white;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem 1.25rem;
    }
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <div class="mb-4">
            <h1 class="h3 mb-1"><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h1>
            <!-- <p class="text-muted">Overview of your catering business</p> -->
        </div>
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <!-- Total Bookings -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 opacity-75">Total Bookings</p>
                                <h2 class="mb-0"><?php echo $stats['total_bookings']; ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Customers -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 opacity-75">Total Customers</p>
                                <h2 class="mb-0"><?php echo $stats['total_customers']; ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Equipment -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 opacity-75">Total Equipment</p>
                                <h2 class="mb-0"><?php echo $stats['total_equipments']; ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 opacity-75">Total Revenue</p>
                                <h2 class="mb-0">₱<?php echo number_format($stats['total_revenue'], 2); ?></h2>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-peso-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Status Overview -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-clock text-warning mb-2" style="font-size: 2.5rem;"></i>
                        <h3 class="mb-1"><?php echo $pending; ?></h3>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle text-info mb-2" style="font-size: 2.5rem;"></i>
                        <h3 class="mb-1"><?php echo $confirmed; ?></h3>
                        <p class="text-muted mb-0">Confirmed</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-thumbs-up text-success mb-2" style="font-size: 2.5rem;"></i>
                        <h3 class="mb-1"><?php echo $approved; ?></h3>
                        <p class="text-muted mb-0">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-times-circle text-danger mb-2" style="font-size: 2.5rem;"></i>
                        <h3 class="mb-1"><?php echo $canceled; ?></h3>
                        <p class="text-muted mb-0">Canceled</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Recent Bookings -->
            <div class="col-lg-8">
                <div class="card card-clean">
                    <div class="card-header-simple">
                        <h5 class="mb-0"><i class="fas fa-list text-primary me-2"></i>Recent Bookings</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking Ref</th>
                                        <th>Customer</th>
                                        <th>Event Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($recent_bookings->num_rows > 0): ?>
                                        <?php while($booking = $recent_bookings->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <small class="text-primary">
                                                        <i class="fas fa-hashtag"></i>
                                                        <?php echo substr($booking['booking_ref'], -6); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                                <td>
                                                    <i class="fas fa-calendar text-muted me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($booking['borrow_date'])); ?>
                                                </td>
                                                <td class="fw-bold">₱<?php echo number_format($booking['total_payment'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'Pending' => 'bg-warning text-dark',
                                                        'Confirm' => 'bg-info text-white',
                                                        'Approved' => 'bg-success text-white',
                                                        'Canceled' => 'bg-danger text-white'
                                                    ];
                                                    $class = $statusClass[$booking['status']] ?? 'bg-secondary text-white';
                                                    ?>
                                                    <span class="badge <?php echo $class; ?> badge-status">
                                                        <?php echo $booking['status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No bookings yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Top Equipment -->
                <div class="card card-clean mb-3">
                    <div class="card-header-simple">
                        <h6 class="mb-0"><i class="fas fa-star text-warning me-2"></i>Top Equipment</h6>
                    </div>
                    <div class="card-body">
                        <?php if($top_equipment->num_rows > 0): ?>
                            <?php $rank = 1; while($item = $top_equipment->fetch_assoc()): ?>
                                <div class="top-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2"><?php echo $rank++; ?></span>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-primary"><?php echo $item['booking_count']; ?></div>
                                            <small class="text-muted">bookings</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-muted mb-0">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="card card-clean">
                    <div class="card-header-simple">
                        <h6 class="mb-0"><i class="fas fa-chart-line text-success me-2"></i>Revenue (Last 6 Months)</h6>
                    </div>
                    <div class="card-body">
                        <?php if($monthly_revenue->num_rows > 0): ?>
                            <?php while($month = $monthly_revenue->fetch_assoc()): ?>
                                <div class="revenue-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                                            <strong><?php echo $month['month']; ?></strong>
                                        </div>
                                        <span class="text-success fw-bold">₱<?php echo number_format($month['revenue'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-muted mb-0">No revenue data</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>

</body>
</html>