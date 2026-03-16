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
    header("Location: login.php");
    exit();
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT DATE(borrow_date) as activity_date, COUNT(*) as total_borrowed,
           SUM(total_amount) as daily_revenue,
           GROUP_CONCAT(DISTINCT customer_name SEPARATOR ', ') as customers
    FROM customer_booking
    WHERE DATE(borrow_date) BETWEEN ? AND ?
    GROUP BY DATE(borrow_date) ORDER BY activity_date DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_borrowing_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($daily_borrowing_rows as &$r) {
    $names = array_map('dec', explode(', ', $r['customers']));
    $r['customers'] = implode(', ', $names);
}
unset($r);

$stmt = $conn->prepare("
    SELECT DATE(actual_return_date) as return_date, COUNT(*) as total_returned,
           SUM(total_amount) as rental_amount,
           COALESCE(SUM(fine_amount), 0) as total_fines,
           COALESCE(SUM(damage_fee), 0) as total_damages,
           GROUP_CONCAT(DISTINCT customer_name SEPARATOR ', ') as customers
    FROM customer_booking
    WHERE actual_return_date IS NOT NULL AND DATE(actual_return_date) BETWEEN ? AND ?
    GROUP BY DATE(actual_return_date) ORDER BY return_date DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_returns_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($daily_returns_rows as &$r) {
    $names = array_map('dec', explode(', ', $r['customers']));
    $r['customers'] = implode(', ', $names);
}
unset($r);

$stmt = $conn->prepare("
    SELECT cb.customer_name, cb.phone, cb.return_date, cb.actual_return_date,
           cb.total_amount, cb.fine_amount, cb.damage_fee, cb.damage_notes,
           TIMESTAMPDIFF(HOUR, cb.return_date, cb.actual_return_date) as hours_late
    FROM customer_booking cb
    WHERE (cb.fine_amount > 0 OR cb.damage_fee > 0)
    AND DATE(cb.actual_return_date) BETWEEN ? AND ?
    ORDER BY cb.actual_return_date DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$fines_damages_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($fines_damages_rows as &$r) {
    $r['customer_name'] = dec($r['customer_name']);
    $r['phone']         = dec($r['phone']);
}
unset($r);

$active_rentals_rows = $conn->query("
    SELECT cb.customer_name, cb.phone, cb.borrow_date, cb.return_date, cb.total_amount,
           CASE WHEN cb.return_date < NOW() THEN 'Overdue' ELSE 'Active' END as rental_status,
           TIMESTAMPDIFF(HOUR, NOW(), cb.return_date) as hours_remaining,
           COALESCE(cb.fine_amount, 0) as current_fine
    FROM customer_booking cb WHERE cb.status = 'Borrowed' ORDER BY cb.return_date ASC
")->fetch_all(MYSQLI_ASSOC);
foreach ($active_rentals_rows as &$r) {
    $r['customer_name'] = dec($r['customer_name']);
    $r['phone']         = dec($r['phone']);
}
unset($r);

$stmt = $conn->prepare("
    SELECT customer_name, COUNT(*) as total_bookings, SUM(total_amount) as total_spent,
           COALESCE(SUM(fine_amount), 0) as total_fines,
           COALESCE(SUM(damage_fee), 0) as total_damages,
           MAX(created_at) as last_booking
    FROM customer_booking
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY customer_name ORDER BY total_bookings DESC LIMIT 10
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$customer_activity_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($customer_activity_rows as &$r) {
    $r['customer_name'] = dec($r['customer_name']);
}
unset($r);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    .activity-date { font-weight:bold; color:#0d6efd; }
    .movement-card { border-left:3px solid; margin-bottom:10px; }
    .movement-borrow { border-left-color:#0d6efd; }
    .movement-return { border-left-color:#198754; }
    @media print {
        @page { margin:15mm; size:A4 portrait; }
        .no-print,.sidebar,nav,button,.btn,.card-header i { display:none !important; }
        body { background:white !important; color:black !important; font-size:11pt; font-family:Arial,sans-serif; margin:0; padding:0; line-height:1.4; }
        main { padding:0 !important; margin:0 !important; max-width:100% !important; }
        .print-company-header { display:block !important; text-align:center; border-bottom:3px solid black; padding-bottom:15px; margin-bottom:20px; }
        .print-company-header h1 { font-size:24pt; font-weight:bold; margin:0 0 5px 0; letter-spacing:1px; }
        .print-company-header .report-title { font-size:14pt; font-weight:bold; margin:10px 0 5px 0; }
        .print-company-header .report-info { font-size:10pt; margin:3px 0; }
        * { background:white !important; color:black !important; border-color:#333 !important; box-shadow:none !important; }
        table { border-collapse:collapse; width:100%; margin-bottom:20px; page-break-inside:auto; font-size:9pt; }
        th,td { border:1px solid #333 !important; padding:6px 8px !important; background:white !important; color:black !important; text-align:left; }
        th { font-weight:bold; background:#e8e8e8 !important; font-size:10pt; }
        tr { page-break-inside:avoid; page-break-after:auto; }
        .card { border:2px solid black !important; margin-bottom:20px; page-break-inside:avoid; background:white !important; }
        .card-header { background:#e8e8e8 !important; color:black !important; border-bottom:2px solid black !important; padding:10px !important; font-weight:bold; font-size:12pt; text-align:left; }
        .card-body { padding:15px !important; }
        .movement-card { border:1px solid #333 !important; margin-bottom:10px; padding:10px !important; page-break-inside:avoid; }
        .movement-card .activity-date { font-size:11pt; font-weight:bold; margin-bottom:5px; color:black !important; }
        h1,h2,h3,h4,h5,h6 { color:black !important; }
        h5 { font-size:12pt; margin:0; }
        .badge { border:1px solid black !important; padding:2px 6px; background:white !important; color:black !important; display:inline-block; }
        .row { display:block !important; margin:0 !important; }
        .col-lg-6 { width:100% !important; display:block !important; float:none !important; margin-bottom:20px !important; }
        small { font-size:8pt; }
        p { margin:3px 0; }
        div[style*="overflow-y"] { max-height:none !important; overflow:visible !important; }
        strong { font-weight:bold; }
        .print-footer { display:block !important; text-align:center; margin-top:30px; padding-top:10px; border-top:1px solid #333; font-size:9pt; }
    }
</style>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>
    <main class="flex-grow-1 p-4">
        <div class="print-company-header" style="display:none;">
            <h1>EL CIELO CATERING SERVICES</h1>
            <div class="report-title">DAILY MOVEMENT & OPERATIONS REPORT</div>
            <div class="report-info"><strong>Report Period:</strong> <?= date('F d, Y', strtotime($start_date)) ?> to <?= date('F d, Y', strtotime($end_date)) ?></div>
            <div class="report-info"><strong>Generated on:</strong> <?= date('F d, Y - h:i A') ?></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1 class="h3 mb-0"><i class="fas fa-chart-bar"></i> Reports & Daily Movement</h1>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
        </div>

        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><i class="fas fa-calendar"></i> Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fas fa-calendar"></i> End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-filter"></i> Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-arrow-down"></i> Daily Borrowing Activity</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height:400px;overflow-y:auto;">
                            <?php if (!empty($daily_borrowing_rows)): ?>
                            <?php foreach ($daily_borrowing_rows as $row): ?>
                            <div class="movement-card movement-borrow card mb-2">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div style="flex:1;">
                                            <div class="activity-date mb-1">
                                                <i class="fas fa-calendar-day"></i> <?= date('l, F d, Y', strtotime($row['activity_date'])) ?>
                                            </div>
                                            <p class="mb-1"><strong><?= $row['total_borrowed'] ?></strong> booking(s) borrowed</p>
                                            <p class="mb-0 text-muted small"><i class="fas fa-users"></i> <?= htmlspecialchars($row['customers']) ?></p>
                                        </div>
                                        <div class="text-end" style="min-width:100px;">
                                            <h5 class="text-primary mb-0">₱<?= number_format($row['daily_revenue'], 2) ?></h5>
                                            <small class="text-muted">revenue</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted text-center">No borrowing activity in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-arrow-up"></i> Daily Return Activity</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height:400px;overflow-y:auto;">
                            <?php if (!empty($daily_returns_rows)): ?>
                            <?php foreach ($daily_returns_rows as $row): ?>
                            <div class="movement-card movement-return card mb-2">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div style="flex:1;">
                                            <div class="activity-date mb-1" style="color:#198754;">
                                                <i class="fas fa-calendar-check"></i> <?= date('l, F d, Y', strtotime($row['return_date'])) ?>
                                            </div>
                                            <p class="mb-1"><strong><?= $row['total_returned'] ?></strong> booking(s) returned</p>
                                            <p class="mb-0 text-muted small"><i class="fas fa-users"></i> <?= htmlspecialchars($row['customers']) ?></p>
                                        </div>
                                        <div class="text-end" style="min-width:120px;">
                                            <h6 class="mb-0">₱<?= number_format($row['rental_amount'], 2) ?></h6>
                                            <?php if ($row['total_fines'] > 0): ?>
                                            <small class="text-warning d-block">+₱<?= number_format($row['total_fines'], 2) ?> fines</small>
                                            <?php endif; ?>
                                            <?php if ($row['total_damages'] > 0): ?>
                                            <small class="text-danger d-block">+₱<?= number_format($row['total_damages'], 2) ?> damages</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted text-center">No return activity in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($fines_damages_rows)): ?>
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Fines & Damages Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Customer</th><th>Phone</th><th>Should Return</th>
                                <th>Actually Returned</th><th>Hours Late</th>
                                <th>Rental</th><th>Fine</th><th>Damage</th><th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fines_damages_rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                <td><?= date('M d, h:i A', strtotime($row['return_date'])) ?></td>
                                <td><?= date('M d, h:i A', strtotime($row['actual_return_date'])) ?></td>
                                <td><span class="badge"><?= $row['hours_late'] ?>h</span></td>
                                <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                <td><strong>₱<?= number_format($row['fine_amount'], 2) ?></strong></td>
                                <td><strong>₱<?= number_format($row['damage_fee'], 2) ?></strong></td>
                                <td><?= htmlspecialchars($row['damage_notes'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($active_rentals_rows)): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Currently Active Rentals</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Customer</th><th>Phone</th><th>Borrowed</th>
                                <th>Should Return</th><th>Time Remaining</th><th>Amount</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_rentals_rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                <td><?= date('M d, h:i A', strtotime($row['borrow_date'])) ?></td>
                                <td><?= date('M d, h:i A', strtotime($row['return_date'])) ?></td>
                                <td>
                                    <?php
                                    $hours = $row['hours_remaining'];
                                    if ($hours < 0) echo '<span class="badge">OVERDUE</span>';
                                    elseif ($hours < 24) echo '<span class="badge">' . $hours . ' hours</span>';
                                    else echo '<span class="badge">' . floor($hours / 24) . ' day(s)</span>';
                                    ?>
                                </td>
                                <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                <td>
                                    <?php if ($row['rental_status'] == 'Overdue'): ?>
                                        <span class="badge">Overdue</span>
                                        <?php if ($row['current_fine'] > 0): ?>
                                        <br><small>Fine: ₱<?= number_format($row['current_fine'], 2) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge">Active</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-users"></i> Top Customers</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Customer Name</th><th>Total Bookings</th><th>Total Spent</th>
                                <th>Fines Paid</th><th>Damages</th><th>Last Booking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer_activity_rows as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                                <td><span class="badge"><?= $row['total_bookings'] ?>x</span></td>
                                <td>₱<?= number_format($row['total_spent'], 2) ?></td>
                                <td>₱<?= number_format($row['total_fines'], 2) ?></td>
                                <td>₱<?= number_format($row['total_damages'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($row['last_booking'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="print-footer" style="display:none;">
            <p>This is a system-generated report from El Cielo Catering Services</p>
            <p>Printed on: <?= date('F d, Y - h:i A') ?></p>
        </div>
    </main>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
</body>
</html>