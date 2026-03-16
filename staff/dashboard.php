<?php
session_start();
include '../includes/db_connection.php';
include '../classes/StaffAuth.php';

// Default staff name
$staff_firstname = 'Staff';

// Get staff's first name if logged in
if (isset($_SESSION['staff_id'])) {
    $staff_id = $_SESSION['staff_id'];
    $query = "SELECT firstname FROM staff_info WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $stmt->bind_result($firstname);
    if ($stmt->fetch()) {
        $staff_firstname = $firstname;
    }
    $stmt->close();
}

// Get today's date
$today = date('Y-m-d');

// Today's Statistics
$today_stats_query = "
    SELECT 
        COUNT(*) as today_bookings,
        COALESCE(SUM(total_amount), 0) as today_revenue,
        COUNT(CASE WHEN status = 'Borrowed' THEN 1 END) as active_today
    FROM customer_booking
    WHERE DATE(created_at) = ?
";
$stmt = $conn->prepare($today_stats_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_stats = $stmt->get_result()->fetch_assoc();

// Active Bookings Count
$active_query = "SELECT COUNT(*) as active_count FROM customer_booking WHERE status = 'Borrowed'";
$active_count = $conn->query($active_query)->fetch_assoc()['active_count'];

// Overdue Bookings Count
$overdue_query = "
    SELECT COUNT(*) as overdue_count 
    FROM customer_booking 
    WHERE status IN ('Borrowed', 'Overdue') 
    AND return_date < CURRENT_DATE
";
$overdue_count = $conn->query($overdue_query)->fetch_assoc()['overdue_count'];

// Upcoming Returns Today
$returns_today_query = "
    SELECT 
        cb.*,
        COUNT(bi.id) as items_count
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    WHERE cb.status = 'Borrowed'
    AND cb.return_date = ?
    GROUP BY cb.id
    ORDER BY cb.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($returns_today_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$returns_today = $stmt->get_result();

// Recent Bookings (Last 5)
$recent_bookings_query = "
    SELECT 
        cb.*,
        COUNT(bi.id) as items_count
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    GROUP BY cb.id
    ORDER BY cb.created_at DESC
    LIMIT 5
";
$recent_bookings = $conn->query($recent_bookings_query);

// Low Stock Items (Stock < 5)
$low_stock_query = "
    SELECT 
        e.id,
        e.name,
        e.stock,
        e.quantity,
        c.category_name
    FROM equipments e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.stock < 5
    ORDER BY e.stock ASC
    LIMIT 5
";
$low_stock = $conn->query($low_stock_query);

// Overdue Items (For immediate attention)
$overdue_items_query = "
    SELECT 
        cb.*,
        DATEDIFF(CURRENT_DATE, cb.return_date) as days_overdue
    FROM customer_booking cb
    WHERE cb.status IN ('Borrowed', 'Overdue')
    AND cb.return_date < CURRENT_DATE
    ORDER BY cb.return_date ASC
    LIMIT 5
";
$overdue_items = $conn->query($overdue_items_query);

// Get current time for greeting
$current_hour = date('H');
if ($current_hour < 12) {
    $greeting = "Good Morning";
} elseif ($current_hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    .welcome-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        border-radius: 10px;
        transition: transform 0.2s, box-shadow 0.2s;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .stat-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }
    .quick-action-btn {
        border-radius: 10px;
        padding: 1.5rem;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .quick-action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        border-color: currentColor;
    }
    .task-card {
        border-left: 4px solid;
        margin-bottom: 0.5rem;
        transition: background-color 0.2s;
    }
    .task-card:hover {
        background-color: #f8f9fa;
    }
    .badge-pulse {
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
    <!-- Main content -->
    <main class="flex-fill">
        <div class="container-fluid p-4">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-5 fw-bold mb-2">
                            <?php echo $greeting; ?>, <?php echo htmlspecialchars($staff_firstname); ?>! 
                        </h1>
                        <p class="lead mb-0">
                            <!-- Here's what's happening with your catering business today. -->
                        </p>
                        <small class="opacity-75"><?php echo date('l, F d, Y - h:i A'); ?></small>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-user-tie" style="font-size: 5rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Today's Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm bg-primary bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75">Today's Bookings</h6>
                                    <h2 class="mb-0"><?php echo $today_stats['today_bookings']; ?></h2>
                                </div>
                                <i class="fas fa-calendar-plus stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm bg-success bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75">Today's Revenue</h6>
                                    <h2 class="mb-0">₱<?php echo number_format($today_stats['today_revenue'], 0); ?></h2>
                                </div>
                                <i class="fas fa-peso-sign stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm bg-info bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75">Active Bookings</h6>
                                    <h2 class="mb-0"><?php echo $active_count; ?></h2>
                                </div>
                                <i class="fas fa-clipboard-list stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card border-0 shadow-sm bg-warning bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75">Overdue Items</h6>
                                    <h2 class="mb-0">
                                        <?php echo $overdue_count; ?>
                                        <?php if ($overdue_count > 0): ?>
                                        <i class="fas fa-exclamation-triangle ms-2 badge-pulse"></i>
                                        <?php endif; ?>
                                    </h2>
                                </div>
                                <i class="fas fa-exclamation-circle stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0"><i class="fas fa-bolt text-warning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="create_booking.php" class="btn btn-primary w-100 quick-action-btn text-start">
                                <i class="fas fa-plus-circle fs-3 d-block mb-2"></i>
                                <strong>New Booking</strong>
                                <br><small>Create booking</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage_bookings.php" class="btn btn-info w-100 quick-action-btn text-start">
                                <i class="fas fa-tasks fs-3 d-block mb-2"></i>
                                <strong>Manage Bookings</strong>
                                <br><small>View all bookings</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="equipment.php" class="btn btn-success w-100 quick-action-btn text-start">
                                <i class="fas fa-box fs-3 d-block mb-2"></i>
                                <strong>Equipment</strong>
                                <br><small>Check inventory</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="packages.php" class="btn btn-secondary w-100 quick-action-btn text-start">
                                <i class="fas fa-cube fs-3 d-block mb-2"></i>
                                <strong>Packages</strong>
                                <br><small>View packages</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8 mb-4">
                    <!-- Returns Due Today -->
                    <?php if ($returns_today->num_rows > 0): ?>
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Returns Due Today</h5>
                        </div>
                        <div class="card-body">
                            <?php while ($row = $returns_today->fetch_assoc()): ?>
                            <div class="task-card card border-info p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($row['customer_name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-box"></i> <?php echo $row['items_count']; ?> items
                                            <span class="ms-2"><i class="fas fa-peso-sign"></i> <?php echo number_format($row['total_amount'], 2); ?></span>
                                        </small>
                                    </div>
                                    <a href="manage_bookings.php?booking_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Overdue Items -->
                    <?php if ($overdue_items->num_rows > 0): ?>
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Overdue Bookings - Need Immediate Attention!</h5>
                        </div>
                        <div class="card-body">
                            <?php while ($row = $overdue_items->fetch_assoc()): ?>
                            <div class="task-card card border-danger p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 text-danger">
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                            <span class="badge bg-danger ms-2"><?php echo $row['days_overdue']; ?> days overdue</span>
                                        </h6>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone'] ?? 'No phone'); ?>
                                        </small>
                                        <small class="text-muted">
                                            Should return: <?php echo date('M d, Y', strtotime($row['return_date'])); ?>
                                        </small>
                                    </div>
                                    <a href="manage_bookings.php?booking_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger">
                                        <i class="fas fa-phone"></i> Contact
                                    </a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Bookings -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Recent Bookings</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <!-- <th>ID</th> -->
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $recent_bookings->fetch_assoc()): ?>
                                        <tr>
                                            
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo $row['items_count']; ?></span></td>
                                            <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $row['status'] == 'Borrowed' ? 'info' : 
                                                        ($row['status'] == 'Returned' ? 'success' : 
                                                        ($row['status'] == 'Overdue' ? 'warning' : 'danger')); 
                                                ?>">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4 mb-4">
                    <!-- Low Stock Alert -->
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-warehouse"></i> Low Stock Alert</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($low_stock->num_rows > 0): ?>
                            <?php while ($row = $low_stock->fetch_assoc()): ?>
                            <div class="card mb-2 border-warning">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="d-block"><?php echo htmlspecialchars($row['name']); ?></strong>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['category_name']); ?></small>
                                        </div>
                                        <span class="badge bg-<?php echo $row['stock'] == 0 ? 'danger' : 'warning'; ?>">
                                            <?php echo $row['stock']; ?> / <?php echo $row['quantity']; ?>
                                        </span>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-<?php echo $row['stock'] == 0 ? 'danger' : 'warning'; ?>" 
                                             style="width: <?php echo ($row['stock'] / $row['quantity']) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <p class="text-muted text-center small mb-0">All equipment stock levels are good! ✓</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Tips -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Quick Tips</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success"></i>
                                <small class="ms-2">Always verify equipment condition before lending</small>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success"></i>
                                <small class="ms-2">Contact customers 1 day before return date</small>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success"></i>
                                <small class="ms-2">Update stock levels after each transaction</small>
                            </div>
                            <div class="mb-0">
                                <i class="fas fa-check-circle text-success"></i>
                                <small class="ms-2">Check for overdue items daily</small>
                            </div>
                        </div>
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