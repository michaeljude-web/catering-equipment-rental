<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Summary Statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT cb.id) as total_bookings,
        SUM(cb.total_amount) as total_revenue,
        COUNT(DISTINCT CASE WHEN cb.status = 'Borrowed' THEN cb.id END) as active_bookings,
        COUNT(DISTINCT CASE WHEN cb.status = 'Returned' THEN cb.id END) as completed_bookings,
        COUNT(DISTINCT CASE WHEN cb.status = 'Overdue' THEN cb.id END) as overdue_bookings,
        COUNT(DISTINCT CASE WHEN cb.status = 'Cancelled' THEN cb.id END) as cancelled_bookings
    FROM customer_booking cb
    WHERE cb.created_at BETWEEN ? AND ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Popular Equipment Report
$popular_equipment_query = "
    SELECT 
        e.name,
        c.category_name,
        COUNT(bi.id) as times_booked,
        SUM(bi.quantity) as total_quantity_booked,
        SUM(bi.price * bi.quantity) as total_revenue
    FROM booking_items bi
    INNER JOIN equipments e ON bi.equipment_id = e.id
    INNER JOIN categories c ON e.category_id = c.id
    INNER JOIN customer_booking cb ON bi.booking_id = cb.id
    WHERE cb.created_at BETWEEN ? AND ?
    AND bi.equipment_id IS NOT NULL
    GROUP BY e.id, e.name, c.category_name
    ORDER BY times_booked DESC
    LIMIT 10
";
$stmt = $conn->prepare($popular_equipment_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$popular_equipment = $stmt->get_result();

// Popular Packages Report
$popular_packages_query = "
    SELECT 
        p.package_name,
        COUNT(bi.id) as times_booked,
        SUM(bi.quantity) as total_quantity_booked,
        SUM(bi.price * bi.quantity) as total_revenue
    FROM booking_items bi
    INNER JOIN packages p ON bi.package_id = p.id
    INNER JOIN customer_booking cb ON bi.booking_id = cb.id
    WHERE cb.created_at BETWEEN ? AND ?
    AND bi.package_id IS NOT NULL
    GROUP BY p.id, p.package_name
    ORDER BY times_booked DESC
    LIMIT 10
";
$stmt = $conn->prepare($popular_packages_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$popular_packages = $stmt->get_result();

// Revenue by Month
$monthly_revenue_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(id) as bookings,
        SUM(total_amount) as revenue
    FROM customer_booking
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
";
$stmt = $conn->prepare($monthly_revenue_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$monthly_revenue = $stmt->get_result();

// Recent Bookings
$recent_bookings_query = "
    SELECT 
        cb.id,
        cb.customer_name,
        cb.borrow_date,
        cb.return_date,
        cb.total_amount,
        cb.status,
        cb.created_at
    FROM customer_booking cb
    WHERE cb.created_at BETWEEN ? AND ?
    ORDER BY cb.created_at DESC
    LIMIT 15
";
$stmt = $conn->prepare($recent_bookings_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$recent_bookings = $stmt->get_result();

// Equipment Stock Status
$stock_status_query = "
    SELECT 
        e.name,
        c.category_name,
        e.quantity as total_quantity,
        e.stock as available_stock,
        (e.quantity - e.stock) as currently_borrowed
    FROM equipments e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.stock < 5 OR (e.quantity - e.stock) > 0
    ORDER BY e.stock ASC
";
$stock_status = $conn->query($stock_status_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - Catering System</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    .stat-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .stat-icon {
        font-size: 2rem;
        opacity: 0.7;
    }
    .table-responsive {
        max-height: 400px;
        overflow-y: auto;
    }
    @media print {
        .no-print { display: none; }
        .stat-card { page-break-inside: avoid; }
    }
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Reports & Analytics</h1>
            <button onclick="window.print()" class="btn btn-primary no-print">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <!-- Date Range Filter -->
        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card stat-card border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Revenue</h6>
                                <h3 class="mb-0">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                            </div>
                            <i class="fas fa-peso-sign stat-icon text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Bookings</h6>
                                <h3 class="mb-0"><?php echo $stats['total_bookings'] ?? 0; ?></h3>
                            </div>
                            <i class="fas fa-calendar-check stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Active Bookings</h6>
                                <h3 class="mb-0"><?php echo $stats['active_bookings'] ?? 0; ?></h3>
                            </div>
                            <i class="fas fa-clock stat-icon text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Completed</h6>
                                <h3 class="mb-0"><?php echo $stats['completed_bookings'] ?? 0; ?></h3>
                            </div>
                            <i class="fas fa-check-circle stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Overdue</h6>
                                <h3 class="mb-0"><?php echo $stats['overdue_bookings'] ?? 0; ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Cancelled</h6>
                                <h3 class="mb-0"><?php echo $stats['cancelled_bookings'] ?? 0; ?></h3>
                            </div>
                            <i class="fas fa-times-circle stat-icon text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Monthly Revenue -->
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Monthly Revenue</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Bookings</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $monthly_revenue->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                        <td><?php echo $row['bookings']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($row['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Equipment -->
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-star"></i> Popular Equipment</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Times Booked</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $popular_equipment->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['category_name']); ?></small>
                                        </td>
                                        <td><?php echo $row['times_booked']; ?>x</td>
                                        <td class="text-end">₱<?php echo number_format($row['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Packages -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-box"></i> Popular Packages</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Package Name</th>
                                <th>Times Booked</th>
                                <th>Total Quantity</th>
                                <th class="text-end">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $popular_packages->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                                <td><?php echo $row['times_booked']; ?>x</td>
                                <td><?php echo $row['total_quantity']; ?></td>
                                <td class="text-end">₱<?php echo number_format($row['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Equipment Stock Status -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-warehouse"></i> Equipment Stock Status (Low Stock / Currently Borrowed)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Total Quantity</th>
                                <th>Available</th>
                                <th>Borrowed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stock_status->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td><?php echo $row['total_quantity']; ?></td>
                                <td><?php echo $row['available_stock']; ?></td>
                                <td><?php echo $row['currently_borrowed']; ?></td>
                                <td>
                                    <?php if ($row['available_stock'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($row['available_stock'] < 5): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">In Use</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Bookings</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Booked On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_bookings->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['return_date'])); ?></td>
                                <td class="text-end">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $badge_class = [
                                        'Borrowed' => 'info',
                                        'Returned' => 'success',
                                        'Overdue' => 'warning',
                                        'Cancelled' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class[$row['status']] ?? 'secondary'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>

</body>
</html>