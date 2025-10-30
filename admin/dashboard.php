<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

$auth = new AdminAuth($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get today's date
$today = date('Y-m-d');
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');

// Overall Statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM equipments) as total_equipment,
        (SELECT COUNT(*) FROM packages) as total_packages,
        (SELECT COUNT(*) FROM customer_booking) as total_bookings,
        (SELECT COUNT(*) FROM customer_booking WHERE status = 'Borrowed') as active_bookings,
        (SELECT COUNT(*) FROM customer_booking WHERE status = 'Overdue') as overdue_bookings,
        (SELECT SUM(total_amount) FROM customer_booking WHERE MONTH(created_at) = MONTH(CURRENT_DATE)) as monthly_revenue,
        (SELECT SUM(total_amount) FROM customer_booking) as total_revenue
";
$stats = $conn->query($stats_query)->fetch_assoc();

// Today's Bookings
$today_bookings_query = "
    SELECT 
        cb.*,
        COUNT(bi.id) as item_count
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    WHERE DATE(cb.created_at) = ?
    GROUP BY cb.id
    ORDER BY cb.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($today_bookings_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_bookings = $stmt->get_result();

// Upcoming Returns (Next 7 days)
$next_week = date('Y-m-d', strtotime('+7 days'));
$upcoming_returns_query = "
    SELECT 
        cb.*,
        DATEDIFF(cb.return_date, CURRENT_DATE) as days_until_return
    FROM customer_booking cb
    WHERE cb.status = 'Borrowed'
    AND cb.return_date BETWEEN CURRENT_DATE AND ?
    ORDER BY cb.return_date ASC
    LIMIT 5
";
$stmt = $conn->prepare($upcoming_returns_query);
$stmt->bind_param("s", $next_week);
$stmt->execute();
$upcoming_returns = $stmt->get_result();

// Overdue Bookings
$overdue_query = "
    SELECT 
        cb.*,
        DATEDIFF(CURRENT_DATE, cb.return_date) as days_overdue
    FROM customer_booking cb
    WHERE cb.status IN ('Borrowed', 'Overdue')
    AND cb.return_date < CURRENT_DATE
    ORDER BY cb.return_date ASC
    LIMIT 5
";
$overdue_bookings = $conn->query($overdue_query);

// Low Stock Equipment
$low_stock_query = "
    SELECT 
        e.*,
        c.category_name,
        (e.quantity - e.stock) as borrowed_count
    FROM equipments e
    INNER JOIN categories c ON e.category_id = c.id
    WHERE e.stock < 5
    ORDER BY e.stock ASC
    LIMIT 5
";
$low_stock = $conn->query($low_stock_query);

// Recent Activity (Last 10 bookings)
$recent_activity_query = "
    SELECT 
        cb.id,
        cb.customer_name,
        cb.status,
        cb.total_amount,
        cb.created_at,
        COUNT(bi.id) as items_count
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    GROUP BY cb.id
    ORDER BY cb.created_at DESC
    LIMIT 10
";
$recent_activity = $conn->query($recent_activity_query);

// Monthly Revenue Chart Data (Last 6 months)
$chart_query = "
    SELECT 
        DATE_FORMAT(created_at, '%b') as month,
        SUM(total_amount) as revenue,
        COUNT(id) as bookings
    FROM customer_booking
    WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
";
$chart_result = $conn->query($chart_query);
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_data[] = $row;
}

// Top Equipment this month
$top_equipment_query = "
    SELECT 
        e.name,
        COUNT(bi.id) as times_booked,
        SUM(bi.quantity) as total_qty
    FROM booking_items bi
    INNER JOIN equipments e ON bi.equipment_id = e.id
    INNER JOIN customer_booking cb ON bi.booking_id = cb.id
    WHERE cb.created_at BETWEEN ? AND ?
    AND bi.equipment_id IS NOT NULL
    GROUP BY e.id
    ORDER BY times_booked DESC
    LIMIT 5
";
$stmt = $conn->prepare($top_equipment_query);
$stmt->bind_param("ss", $this_month_start, $this_month_end);
$stmt->execute();
$top_equipment = $stmt->get_result();

// Booking Status Distribution
$status_distribution_query = "
    SELECT 
        status,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM customer_booking
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
    GROUP BY status
";
$status_distribution = $conn->query($status_distribution_query);
$status_data = [];
while ($row = $status_distribution->fetch_assoc()) {
    $status_data[] = $row;
}

// Category Performance
$category_performance_query = "
    SELECT 
        c.category_name,
        COUNT(bi.id) as bookings,
        SUM(bi.price * bi.quantity) as revenue
    FROM booking_items bi
    INNER JOIN equipments e ON bi.equipment_id = e.id
    INNER JOIN categories c ON e.category_id = c.id
    INNER JOIN customer_booking cb ON bi.booking_id = cb.id
    WHERE cb.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
    GROUP BY c.id
    ORDER BY revenue DESC
    LIMIT 5
";
$category_performance = $conn->query($category_performance_query);
$category_data = [];
while ($row = $category_performance->fetch_assoc()) {
    $category_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Catering System</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .stat-card {
        border-left: 4px solid;
        transition: all 0.3s ease;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }
    .stat-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }
    .activity-item {
        border-left: 3px solid #e9ecef;
        transition: border-color 0.2s;
    }
    .activity-item:hover {
        border-left-color: #0d6efd;
        background-color: #f8f9fa;
    }
    .chart-container {
        position: relative;
        height: 300px;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .mini-card {
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 0.5rem;
    }
    .progress-thin {
        height: 5px;
    }
    .table-sm td, .table-sm th {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <!-- Welcome Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Dashboard</h1>
                <p class="text-muted mb-0">Welcome back! Here's what's happening today.</p>
            </div>
            <div class="text-end">
                <small class="text-muted d-block"><?php echo date('l, F d, Y'); ?></small>
                <small class="text-muted"><?php echo date('h:i A'); ?></small>
            </div>
        </div>

        <!-- Main Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Revenue</h6>
                                <h3 class="mb-0">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up"></i> All time
                                </small>
                            </div>
                            <i class="fas fa-peso-sign stat-icon text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Monthly Revenue</h6>
                                <h3 class="mb-0">₱<?php echo number_format($stats['monthly_revenue'] ?? 0, 2); ?></h3>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> This month
                                </small>
                            </div>
                            <i class="fas fa-chart-line stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Active Bookings</h6>
                                <h3 class="mb-0"><?php echo $stats['active_bookings'] ?? 0; ?></h3>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Currently borrowed
                                </small>
                            </div>
                            <i class="fas fa-clipboard-list stat-icon text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Overdue</h6>
                                <h3 class="mb-0"><?php echo $stats['overdue_bookings'] ?? 0; ?></h3>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Need attention
                                </small>
                            </div>
                            <i class="fas fa-exclamation-circle stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Revenue Trend Chart -->
            <div class="col-lg-8 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line text-primary"></i> Revenue Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Status Pie Chart -->
            <div class="col-lg-4 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie text-success"></i> Booking Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Performance & Top Equipment -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar text-info"></i> Category Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-star text-warning"></i> Top Equipment This Month</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topEquipmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8 mb-4">
                <!-- Today's Bookings -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Today's Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($today_bookings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $today_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $row['item_count']; ?> items</span></td>
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
                        <?php else: ?>
                        <p class="text-muted text-center mb-0">No bookings today yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Returns -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Upcoming Returns (Next 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming_returns->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Return Date</th>
                                        <th>Days Left</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $upcoming_returns->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['return_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['days_until_return'] <= 1 ? 'warning' : 'info'; ?>">
                                                <?php echo $row['days_until_return']; ?> day(s)
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center mb-0">No upcoming returns in the next 7 days.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Overdue Bookings -->
                <?php if ($overdue_bookings->num_rows > 0): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Overdue Bookings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Should Return</th>
                                        <th>Days Overdue</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $overdue_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['return_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo $row['days_overdue']; ?> day(s)
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4 mb-4">
                <!-- Low Stock Alert -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-exclamation-circle"></i> Low Stock Alert</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($low_stock->num_rows > 0): ?>
                        <?php while ($row = $low_stock->fetch_assoc()): ?>
                        <div class="mini-card bg-light">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($row['category_name']); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $row['stock'] == 0 ? 'danger' : 'warning'; ?>">
                                    <?php echo $row['stock']; ?> left
                                </span>
                            </div>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-<?php echo $row['stock'] == 0 ? 'danger' : 'warning'; ?>" 
                                     style="width: <?php echo ($row['stock'] / $row['quantity']) * 100; ?>%"></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <p class="text-muted text-center small mb-0">All equipment stock levels are good!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h6>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($recent_activity->num_rows > 0): ?>
                        <?php while ($row = $recent_activity->fetch_assoc()): ?>
                        <div class="activity-item ps-3 pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($row['customer_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo $row['items_count']; ?> items - ₱<?php echo number_format($row['total_amount'], 2); ?></small>
                                    <br><small class="text-muted"><?php echo date('M d, h:i A', strtotime($row['created_at'])); ?></small>
                                </div>
                                <span class="badge status-badge bg-<?php 
                                    echo $row['status'] == 'Borrowed' ? 'info' : 
                                        ($row['status'] == 'Returned' ? 'success' : 
                                        ($row['status'] == 'Overdue' ? 'warning' : 'danger')); 
                                ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <p class="text-muted text-center small mb-0">No recent activity.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>

<script>
// Revenue Trend Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($chart_data, 'month')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($chart_data, 'revenue')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Bookings',
            data: <?php echo json_encode(array_column($chart_data, 'bookings')); ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                position: 'left'
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Booking Status Pie Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($status_data, 'status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($status_data, 'count')); ?>,
            backgroundColor: [
                'rgba(13, 110, 253, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Category Performance Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryChart = new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($category_data, 'category_name')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($category_data, 'revenue')); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});

// Top Equipment Chart
<?php
$top_equipment->data_seek(0);
$top_equipment_data = [];
while ($row = $top_equipment->fetch_assoc()) {
    $top_equipment_data[] = $row;
}
?>
const topEquipmentCtx = document.getElementById('topEquipmentChart').getContext('2d');
const topEquipmentChart = new Chart(topEquipmentCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($top_equipment_data, 'name')); ?>,
        datasets: [{
            label: 'Times Booked',
            data: <?php echo json_encode(array_column($top_equipment_data, 'times_booked')); ?>,
            backgroundColor: [
                'rgba(255, 206, 86, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 99, 132, 0.8)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>