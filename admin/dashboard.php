<?php
session_start();
include '../includes/db_connection.php';
include '../classes/AdminAuth.php';

define('ENC_KEY', 'YourSecretKey1234567890abcdef12');
define('ENC_METHOD', 'AES-256-CBC');

function dec($data) {
    if ($data === null || $data === '') return '';
    $decoded = base64_decode($data);
    if (strlen($decoded) < 16) return $data;
    $iv     = substr($decoded, 0, 16);
    $result = openssl_decrypt(substr($decoded, 16), ENC_METHOD, ENC_KEY, 0, $iv);
    return $result !== false ? $result : $data;
}

$auth = new AdminAuth($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$today           = date('Y-m-d');
$this_month_start = date('Y-m-01');
$this_month_end   = date('Y-m-t 23:59:59');

$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM equipments) as total_equipment,
        (SELECT COUNT(*) FROM packages) as total_packages,
        (SELECT COUNT(*) FROM customer_booking) as total_bookings,
        (SELECT COUNT(*) FROM customer_booking WHERE status = 'Borrowed') as active_bookings,
        (SELECT COUNT(*) FROM customer_booking WHERE status = 'Borrowed' AND return_date < NOW()) as overdue_bookings,
        (SELECT COALESCE(SUM(total_amount), 0) FROM customer_booking WHERE DATE(created_at) >= DATE_FORMAT(NOW(), '%Y-%m-01') AND DATE(created_at) <= LAST_DAY(NOW())) as monthly_revenue,
        (SELECT COALESCE(SUM(total_amount), 0) FROM customer_booking) as total_revenue
")->fetch_assoc();

$today_bookings_rows = $conn->query("
    SELECT cb.*, COUNT(bi.id) as item_count
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    WHERE DATE(cb.created_at) = CURDATE()
    GROUP BY cb.id ORDER BY cb.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
foreach ($today_bookings_rows as &$r) { $r['customer_name'] = dec($r['customer_name']); } unset($r);

$upcoming_returns_rows = $conn->query("
    SELECT cb.*, TIMESTAMPDIFF(DAY, NOW(), cb.return_date) as days_until_return,
           TIMESTAMPDIFF(HOUR, NOW(), cb.return_date) as hours_until_return
    FROM customer_booking cb
    WHERE cb.status = 'Borrowed' AND cb.return_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY cb.return_date ASC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
foreach ($upcoming_returns_rows as &$r) { $r['customer_name'] = dec($r['customer_name']); } unset($r);

$overdue_rows = $conn->query("
    SELECT cb.*, TIMESTAMPDIFF(DAY, cb.return_date, NOW()) as days_overdue,
           TIMESTAMPDIFF(HOUR, cb.return_date, NOW()) as hours_overdue,
           COALESCE(cb.fine_amount, 0) as fine_amount
    FROM customer_booking cb
    WHERE cb.status = 'Borrowed' AND cb.return_date < NOW()
    ORDER BY cb.return_date ASC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
foreach ($overdue_rows as &$r) {
    $r['customer_name'] = dec($r['customer_name']);
    $r['phone']         = dec($r['phone']);
} unset($r);

$low_stock = $conn->query("
    SELECT e.*, c.category_name, (e.quantity - e.stock) as borrowed_count
    FROM equipments e INNER JOIN categories c ON e.category_id = c.id
    WHERE e.stock < 5 ORDER BY e.stock ASC LIMIT 5
");

$recent_activity_rows = $conn->query("
    SELECT cb.id, cb.customer_name, cb.status, cb.total_amount, cb.created_at, COUNT(bi.id) as items_count
    FROM customer_booking cb
    LEFT JOIN booking_items bi ON cb.id = bi.booking_id
    GROUP BY cb.id ORDER BY cb.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
foreach ($recent_activity_rows as &$r) { $r['customer_name'] = dec($r['customer_name']); } unset($r);

$chart_result = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, SUM(total_amount) as revenue, COUNT(id) as bookings
    FROM customer_booking
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
");
$chart_data = [];
while ($row = $chart_result->fetch_assoc()) $chart_data[] = $row;

$stmt = $conn->prepare("
    SELECT e.name, e.photo, COUNT(bi.id) as times_booked, SUM(bi.quantity) as total_qty
    FROM booking_items bi
    INNER JOIN equipments e ON bi.equipment_id = e.id
    INNER JOIN customer_booking cb ON bi.booking_id = cb.id
    WHERE cb.created_at BETWEEN ? AND ? AND bi.equipment_id IS NOT NULL
    GROUP BY e.id ORDER BY times_booked DESC LIMIT 5
");
$stmt->bind_param("ss", $this_month_start, $this_month_end);
$stmt->execute();
$top_equipment_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_data = [];
$status_result = $conn->query("
    SELECT status, COUNT(*) as count, SUM(total_amount) as total
    FROM customer_booking WHERE DATE(created_at) >= DATE_FORMAT(NOW(), '%Y-%m-01')
    GROUP BY status
");
while ($row = $status_result->fetch_assoc()) $status_data[] = $row;

$category_data = [];
$cat_result = $conn->query("
    SELECT c.category_name, COUNT(bi.id) as bookings, SUM(bi.price * bi.quantity) as revenue
    FROM booking_items bi
    INNER JOIN equipments e ON bi.equipment_id = e.id
    INNER JOIN categories c ON e.category_id = c.id
    INNER JOIN customer_booking cb ON bi.booking_id = cb.id
    WHERE cb.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    GROUP BY c.id ORDER BY revenue DESC LIMIT 5
");
while ($row = $cat_result->fetch_assoc()) $category_data[] = $row;
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
    .stat-card { border-left:4px solid; transition:all .3s ease; height:100%; }
    .stat-card:hover { transform:translateY(-5px); box-shadow:0 8px 16px rgba(0,0,0,.15); }
    .stat-icon { font-size:2.5rem; opacity:.8; }
    .activity-item { border-left:3px solid #e9ecef; transition:border-color .2s; }
    .activity-item:hover { border-left-color:#0d6efd; background-color:#f8f9fa; }
    .chart-container { position:relative; height:300px; }
    .status-badge { font-size:.75rem; padding:.25rem .5rem; }
    .mini-card { border-radius:10px; padding:1rem; margin-bottom:.5rem; }
    .progress-thin { height:5px; }
    .table-sm td,.table-sm th { padding:.5rem; font-size:.875rem; }
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Dashboard</h1>
                <p class="text-muted mb-0">Welcome back! Here's what's happening today.</p>
            </div>
            <div class="text-end">
                <small class="text-muted d-block"><?= date('l, F d, Y') ?></small>
                <small class="text-muted"><?= date('h:i A') ?></small>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Revenue</h6>
                                <h3 class="mb-0">₱<?= number_format($stats['total_revenue'] ?? 0, 2) ?></h3>
                                <small class="text-success"><i class="fas fa-arrow-up"></i> All time</small>
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
                                <h3 class="mb-0">₱<?= number_format($stats['monthly_revenue'] ?? 0, 2) ?></h3>
                                <small class="text-muted"><i class="fas fa-calendar"></i> This month</small>
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
                                <h3 class="mb-0"><?= $stats['active_bookings'] ?? 0 ?></h3>
                                <small class="text-muted"><i class="fas fa-clock"></i> Currently borrowed</small>
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
                                <h3 class="mb-0"><?= $stats['overdue_bookings'] ?? 0 ?></h3>
                                <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Need attention</small>
                            </div>
                            <i class="fas fa-exclamation-circle stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-8 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line text-primary"></i> Revenue Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie text-success"></i> Booking Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="statusChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar text-info"></i> Category Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="categoryChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-star text-warning"></i> Top Equipment This Month</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="topEquipmentChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Today's Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($today_bookings_rows)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead><tr><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead>
                                <tbody>
                                    <?php foreach ($today_bookings_rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= $row['item_count'] ?> items</span></td>
                                        <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['status'] == 'Borrowed' ? 'info' : ($row['status'] == 'Returned' ? 'success' : ($row['status'] == 'Overdue' ? 'warning' : 'danger')) ?>">
                                                <?= $row['status'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('h:i A', strtotime($row['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center mb-0">No bookings today yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Upcoming Returns (Next 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcoming_returns_rows)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead><tr><th>Customer</th><th>Return Date & Time</th><th>Time Left</th><th>Amount</th></tr></thead>
                                <tbody>
                                    <?php foreach ($upcoming_returns_rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($row['return_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['days_until_return'] <= 1 ? 'warning' : 'info' ?>">
                                                <?= $row['days_until_return'] > 0 ? $row['days_until_return'] . ' day(s)' : $row['hours_until_return'] . ' hour(s)' ?>
                                            </span>
                                        </td>
                                        <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center mb-0">No upcoming returns in the next 7 days.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($overdue_rows)): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Overdue Bookings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead><tr><th>Customer</th><th>Phone</th><th>Should Return</th><th>Overdue</th><th>Fine</th><th>Amount</th></tr></thead>
                                <tbody>
                                    <?php foreach ($overdue_rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($row['return_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?= $row['days_overdue'] > 0 ? $row['days_overdue'] . ' day(s)' : $row['hours_overdue'] . ' hour(s)' ?>
                                            </span>
                                        </td>
                                        <td><strong class="text-danger">₱<?= number_format($row['fine_amount'], 2) ?></strong></td>
                                        <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 mb-4">
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
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($row['category_name']) ?></small>
                                </div>
                                <span class="badge bg-<?= $row['stock'] == 0 ? 'danger' : 'warning' ?>"><?= $row['stock'] ?> left</span>
                            </div>
                            <div class="progress progress-thin">
                                <div class="progress-bar bg-<?= $row['stock'] == 0 ? 'danger' : 'warning' ?>" style="width:<?= $row['quantity'] > 0 ? ($row['stock'] / $row['quantity']) * 100 : 0 ?>%"></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <p class="text-muted text-center small mb-0">All equipment stock levels are good!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h6>
                    </div>
                    <div class="card-body" style="max-height:400px;overflow-y:auto;">
                        <?php if (!empty($recent_activity_rows)): ?>
                        <?php foreach ($recent_activity_rows as $row): ?>
                        <div class="activity-item ps-3 pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars($row['customer_name']) ?></strong>
                                    <br><small class="text-muted"><?= $row['items_count'] ?> items - ₱<?= number_format($row['total_amount'], 2) ?></small>
                                    <br><small class="text-muted"><?= date('M d, h:i A', strtotime($row['created_at'])) ?></small>
                                </div>
                                <span class="badge status-badge bg-<?= $row['status'] == 'Borrowed' ? 'info' : ($row['status'] == 'Returned' ? 'success' : ($row['status'] == 'Overdue' ? 'warning' : 'danger')) ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($chart_data, 'month')) ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?= json_encode(array_column($chart_data, 'revenue')) ?>,
            borderColor: 'rgb(75,192,192)', backgroundColor: 'rgba(75,192,192,.1)', tension: .4, fill: true
        },{
            label: 'Bookings',
            data: <?= json_encode(array_column($chart_data, 'bookings')) ?>,
            borderColor: 'rgb(255,99,132)', backgroundColor: 'rgba(255,99,132,.1)', tension: .4, fill: true, yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
            y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'Revenue (₱)' } },
            y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Bookings' }, grid: { drawOnChartArea: false } }
        }
    }
});

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($status_data, 'status')) ?>,
        datasets: [{ data: <?= json_encode(array_column($status_data, 'count')) ?>, backgroundColor: ['rgba(13,110,253,.8)','rgba(25,135,84,.8)','rgba(255,193,7,.8)','rgba(220,53,69,.8)'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});

const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($category_data, 'category_name')) ?>,
        datasets: [{ label: 'Revenue (₱)', data: <?= json_encode(array_column($category_data, 'revenue')) ?>, backgroundColor: 'rgba(54,162,235,.8)', borderColor: 'rgba(54,162,235,1)', borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

const topEquipmentCtx = document.getElementById('topEquipmentChart').getContext('2d');
new Chart(topEquipmentCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($top_equipment_data, 'name')) ?>,
        datasets: [{ label: 'Times Booked', data: <?= json_encode(array_column($top_equipment_data, 'times_booked')) ?>, backgroundColor: ['rgba(255,206,86,.8)','rgba(255,159,64,.8)','rgba(153,102,255,.8)','rgba(75,192,192,.8)','rgba(255,99,132,.8)'], borderWidth: 1 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>
</body>
</html>