<?php
session_start();
include '../includes/db_connection.php';
include '../classes/StaffAuth.php';

$staffAuth = new StaffAuth($conn);
if (!$staffAuth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$staff_firstname = 'Staff';
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

// Update overdue fines before displaying
$conn->query("UPDATE customer_booking 
              SET fine_amount = TIMESTAMPDIFF(HOUR, return_date, NOW()) * 100 
              WHERE status = 'Borrowed' 
              AND NOW() > return_date 
              AND TIMESTAMPDIFF(HOUR, return_date, NOW()) > 0");

// Fetch only ACTIVE bookings (exclude 'Returned' and 'Overdue')
$bookings_query = "SELECT id, customer_name, email, phone, address, borrow_date, return_date, 
                   actual_return_date, total_amount, fine_amount, damage_fee, damage_notes,
                   status, created_at,
                   CASE 
                       WHEN status = 'Borrowed' AND NOW() > return_date THEN 'Overdue'
                       ELSE status
                   END as display_status
                   FROM customer_booking 
                   WHERE status = 'Borrowed'
                   ORDER BY 
                       CASE WHEN NOW() > return_date THEN 0 ELSE 1 END,
                       return_date ASC";
$bookings_result = $conn->query($bookings_query);
$bookings = [];
if ($bookings_result) {
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Fetch equipments
$equipments = [];
$equip_query = "SELECT e.id, e.name, e.price, e.category_id, e.stock, e.quantity, c.category_name 
                FROM equipments e 
                JOIN categories c ON e.category_id = c.id 
                ORDER BY c.category_name, e.name";
$equip_result = $conn->query($equip_query);
if ($equip_result) {
    while ($row = $equip_result->fetch_assoc()) {
        $equipments[] = $row;
    }
}

// Get packages
$packages = [];
$pkg_query = "SELECT p.id, p.package_name, p.price,
              GROUP_CONCAT(CONCAT(e.name, ' (', pi.quantity, ')') SEPARATOR ', ') as items
              FROM packages p
              LEFT JOIN package_items pi ON p.id = pi.package_id
              LEFT JOIN equipments e ON pi.equipment_id = e.id
              GROUP BY p.id, p.package_name, p.price
              ORDER BY p.package_name";
$pkg_result = $conn->query($pkg_query);
if ($pkg_result) {
    while ($row = $pkg_result->fetch_assoc()) {
        $packages[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bookings - Staff Dashboard</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/font/css/all.min.css">
<style>
    .equipment-item, .package-item {
        border: 1px solid #dee2e6;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 5px;
        background: #f8f9fa;
    }
    #bookingTypeButtons .btn {
        min-width: 150px;
        color: white;
        border: none;
    }
    #equipmentBtn { background-color: #0d6efd; }
    #equipmentBtn:hover { background-color: #0b5ed7; }
    #packageBtn { background-color: #198754; }
    #packageBtn:hover { background-color: #157347; }
    #mixedBtn { background-color: #6f42c1; }
    #mixedBtn:hover { background-color: #5a32a3; }
  
    .status-badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-Borrowed { background-color: #0d6efd; color: white; }
    .status-Overdue { background-color: #dc3545; color: white; }
    
    .booking-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .booking-row:hover { background-color: #f8f9fa; }
    
    .timer-badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .timer-upcoming { background-color: #0dcaf0; color: #000; }
    .timer-soon { background-color: #ffc107; color: #000; }
    .timer-pay { background-color: #dc3545; color: white; font-weight: 700; }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
    <main class="flex-fill">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Active Bookings</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                    <i class="fas fa-plus"></i> New Booking
                </button>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Borrow Date</th>
                                    <th>Return Date & Time</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Time / Payment Due</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>No active bookings. All clear!</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr class="booking-row" onclick="viewBooking(<?php echo $booking['id']; ?>)" 
                                            data-return-date="<?php echo $booking['return_date']; ?>" 
                                            data-status="<?php echo $booking['status']; ?>"
                                            data-display-status="<?php echo $booking['display_status']; ?>">
                                            <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($booking['borrow_date'])); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($booking['return_date'])); ?></td>
                                            <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $booking['display_status']; ?>">
                                                    <?php echo $booking['display_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="timer-badge" id="timer-<?php echo $booking['id']; ?>">
                                                    <i class="fas fa-clock"></i> Calculating...
                                                </span>
                                            </td>
                                            <td onclick="event.stopPropagation();">
                                                <button class="btn btn-sm btn-info" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($booking['display_status'] === 'Borrowed'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="openReturnModal(<?php echo $booking['id']; ?>, false)">
                                                        <i class="fas fa-undo"></i> Return
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-warning" onclick="openReturnModal(<?php echo $booking['id']; ?>, true)">
                                                        <i class="fas fa-money-bill-wave"></i> Settle
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Booking Modal -->
<div class="modal fade" id="addBookingModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> New Customer Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="process_booking.php" method="POST" id="bookingForm">
                    <h5 class="mb-3"><i class="fas fa-user"></i> Customer Information</h5>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="col-md-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address">
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4"><i class="fas fa-calendar"></i> Return Date & Time</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="return_date" class="form-label">Return Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="return_date" name="return_date" required>
                            <small class="text-muted">Borrow date will be set automatically</small>
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4"><i class="fas fa-box"></i> Select Booking Type</h5>
                    <div class="mb-3" id="bookingTypeButtons">
                        <button type="button" class="btn" id="equipmentBtn" onclick="showBookingType('equipment')">
                            <i class="fas fa-tools"></i> Equipment Only
                        </button>
                        <button type="button" class="btn" id="packageBtn" onclick="showBookingType('package')">
                            <i class="fas fa-box-open"></i> Package Only
                        </button>
                        <button type="button" class="btn" id="mixedBtn" onclick="showBookingType('mixed')">
                            <i class="fas fa-layer-group"></i> Package + Equipment
                        </button>
                    </div>

                    <div id="equipmentSection" style="display: none;">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-tools"></i> Equipment Selection</h6>
                            </div>
                            <div class="card-body">
                                <div id="equipmentList"></div>
                                <button type="button" class="btn btn-sm btn-success" onclick="addEquipment()">
                                    <i class="fas fa-plus"></i> Add Equipment
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="packageSection" style="display: none;">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-box-open"></i> Package Selection</h6>
                            </div>
                            <div class="card-body">
                                <div id="packageList"></div>
                                <button type="button" class="btn btn-sm btn-success" onclick="addPackage()">
                                    <i class="fas fa-plus"></i> Add Package
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h5 class="mb-0"><strong>Total Amount: <span class="text-primary" id="totalAmount">₱0.00</span></strong></h5>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="bookingForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Booking
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Return/Settle Payment Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="returnModalHeader">
                <h5 class="modal-title" id="returnModalTitle"><i class="fas fa-undo"></i> Return Equipment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_return.php" method="POST" id="returnForm">
                <div class="modal-body">
                    <input type="hidden" name="booking_id" id="return_booking_id">
                    
                    <div class="alert alert-info" id="returnAlert">
                        <i class="fas fa-info-circle"></i> Mark this booking as returned
                    </div>

                    <div id="fineAlert" class="alert alert-warning" style="display: none;">
                        <h6 class="mb-2"><i class="fas fa-exclamation-triangle"></i> <strong>Overdue Charges</strong></h6>
                        <p class="mb-1">Rental: <span id="rentalAmount">₱0.00</span></p>
                        <p class="mb-1 text-danger">Overdue Fine: <span id="fineAmount">₱0.00</span></p>
                        <hr>
                        <p class="mb-0"><strong>Subtotal: <span id="subtotalAmount">₱0.00</span></strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Any Damages?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="has_damage" id="no_damage" value="0" checked onchange="toggleDamageFields()">
                            <label class="form-check-label" for="no_damage">
                                No Damage
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="has_damage" id="has_damage" value="1" onchange="toggleDamageFields()">
                            <label class="form-check-label" for="has_damage">
                                Yes, Has Damage
                            </label>
                        </div>
                    </div>

                    <div id="damageFields" style="display: none;">
                        <div class="mb-3">
                            <label for="damage_fee" class="form-label">Damage Fee (₱)</label>
                            <input type="number" class="form-control" name="damage_fee" id="damage_fee" min="0" step="0.01" value="0" onchange="updateTotalPayment()">
                        </div>
                        <div class="mb-3">
                            <label for="damage_notes" class="form-label">Damage Description</label>
                            <textarea class="form-control" name="damage_notes" id="damage_notes" rows="3" placeholder="Describe the damage..."></textarea>
                        </div>
                    </div>

                    <div class="alert alert-success" id="totalPaymentAlert" style="display: none;">
                        <h5 class="mb-0"><strong>TOTAL TO COLLECT: <span class="text-success" id="totalPayment">₱0.00</span></strong></h5>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" id="returnSubmitBtn">
                        <i class="fas fa-check"></i> Process Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Booking Modal -->
<div class="modal fade" id="viewBookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Booking Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookingDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const equipments = <?php echo json_encode($equipments); ?>;
const packages = <?php echo json_encode($packages); ?>;

let equipmentCounter = 0;
let packageCounter = 0;
let currentBookingType = null;
let currentBookingData = null;

document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('return_date').min = now.toISOString().slice(0,16);
});

function showBookingType(type) {
    currentBookingType = type;
    document.getElementById('equipmentSection').style.display = 'none';
    document.getElementById('packageSection').style.display = 'none';
    document.getElementById('equipmentBtn').classList.remove('active');
    document.getElementById('packageBtn').classList.remove('active');
    document.getElementById('mixedBtn').classList.remove('active');
    
    if (type === 'equipment') {
        document.getElementById('equipmentSection').style.display = 'block';
        document.getElementById('equipmentBtn').classList.add('active');
        document.getElementById('packageList').innerHTML = '';
        packageCounter = 0;
        if (equipmentCounter === 0) addEquipment();
    } else if (type === 'package') {
        document.getElementById('packageSection').style.display = 'block';
        document.getElementById('packageBtn').classList.add('active');
        document.getElementById('equipmentList').innerHTML = '';
        equipmentCounter = 0;
        if (packageCounter === 0) addPackage();
    } else if (type === 'mixed') {
        document.getElementById('equipmentSection').style.display = 'block';
        document.getElementById('packageSection').style.display = 'block';
        document.getElementById('mixedBtn').classList.add('active');
        if (packageCounter === 0) addPackage();
        if (equipmentCounter === 0) addEquipment();
    }
    calculateTotal();
}

function addEquipment() {
    equipmentCounter++;
    const equipmentList = document.getElementById('equipmentList');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'equipment-item';
    itemDiv.id = `equipment-${equipmentCounter}`;
    
    let optionsHTML = '<option value="">-- Select Equipment --</option>';
    let currentCategory = '';
    equipments.forEach(eq => {
        if (eq.category_name !== currentCategory) {
            if (currentCategory !== '') optionsHTML += '</optgroup>';
            optionsHTML += `<optgroup label="${eq.category_name}">`;
            currentCategory = eq.category_name;
        }
        const disabled = eq.stock === 0 ? 'disabled' : '';
        const stockInfo = eq.stock === 0 ? ' [OUT OF STOCK]' : ` [Stock: ${eq.stock}]`;
        optionsHTML += `<option value="${eq.id}" data-price="${eq.price}" data-stock="${eq.stock}" ${disabled}>${eq.name} - ₱${parseFloat(eq.price).toFixed(2)}${stockInfo}</option>`;
    });
    if (currentCategory !== '') optionsHTML += '</optgroup>';
    
    itemDiv.innerHTML = `
        <div class="row">
            <div class="col-md-7">
                <label class="form-label">Equipment</label>
                <select class="form-select equipment-select" name="equipment_id[]" onchange="calculateTotal()" required>
                    ${optionsHTML}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantity</label>
                <input type="number" class="form-control equipment-quantity" name="equipment_quantity[]" value="1" min="1" onchange="calculateTotal()" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeEquipment(${equipmentCounter})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    equipmentList.appendChild(itemDiv);
}

function removeEquipment(id) {
    document.getElementById(`equipment-${id}`)?.remove();
    calculateTotal();
}

function addPackage() {
    packageCounter++;
    const packageList = document.getElementById('packageList');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'package-item';
    itemDiv.id = `package-${packageCounter}`;
    
    let optionsHTML = '<option value="">-- Select Package --</option>';
    packages.forEach(pkg => {
        optionsHTML += `<option value="${pkg.id}" data-price="${pkg.price}">${pkg.package_name} - ₱${parseFloat(pkg.price).toFixed(2)}</option>`;
    });
    
    itemDiv.innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <label class="form-label">Package</label>
                <select class="form-select package-select" name="package_id[]" onchange="calculateTotal()" required>
                    ${optionsHTML}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantity</label>
                <input type="number" class="form-control package-quantity" name="package_quantity[]" value="1" min="1" onchange="calculateTotal()" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removePackage(${packageCounter})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    packageList.appendChild(itemDiv);
}

function removePackage(id) {
    document.getElementById(`package-${id}`)?.remove();
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.equipment-select').forEach((select, index) => {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption?.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const quantity = parseInt(document.querySelectorAll('.equipment-quantity')[index].value);
            total += price * quantity;
        }
    });
    document.querySelectorAll('.package-select').forEach((select, index) => {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption?.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const quantity = parseInt(document.querySelectorAll('.package-quantity')[index].value);
            total += price * quantity;
        }
    });
    document.getElementById('totalAmount').textContent = '₱' + total.toFixed(2);
}

function toggleDamageFields() {
    const hasDamage = document.getElementById('has_damage').checked;
    document.getElementById('damageFields').style.display = hasDamage ? 'block' : 'none';
    if (!hasDamage) {
        document.getElementById('damage_fee').value = '0';
        document.getElementById('damage_notes').value = '';
    }
    updateTotalPayment();
}

function updateTotalPayment() {
    if (!currentBookingData) return;
    
    const rental = parseFloat(currentBookingData.total_amount);
    const fine = parseFloat(currentBookingData.fine_amount || 0);
    const damage = parseFloat(document.getElementById('damage_fee').value || 0);
    const total = rental + fine + damage;
    
    document.getElementById('totalPayment').textContent = '₱' + total.toFixed(2);
}

function openReturnModal(bookingId, isOverdue) {
    document.getElementById('return_booking_id').value = bookingId;
    
    // Reset form
    document.getElementById('no_damage').checked = true;
    document.getElementById('damage_fee').value = '0';
    document.getElementById('damage_notes').value = '';
    document.getElementById('damageFields').style.display = 'none';
    
    // Update modal appearance based on overdue status
    const modalHeader = document.getElementById('returnModalHeader');
    const modalTitle = document.getElementById('returnModalTitle');
    const returnAlert = document.getElementById('returnAlert');
    const submitBtn = document.getElementById('returnSubmitBtn');
    
    if (isOverdue) {
        modalHeader.className = 'modal-header bg-warning text-dark';
        modalTitle.innerHTML = '<i class="fas fa-money-bill-wave"></i> Settle Overdue Payment';
        returnAlert.className = 'alert alert-warning';
        returnAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>This booking is overdue.</strong> Please collect payment before processing return.';
        submitBtn.className = 'btn btn-warning';
        submitBtn.innerHTML = '<i class="fas fa-money-bill-wave"></i> Collect Payment & Return';
    } else {
        modalHeader.className = 'modal-header bg-success text-white';
        modalTitle.innerHTML = '<i class="fas fa-undo"></i> Return Equipment';
        returnAlert.className = 'alert alert-info';
        returnAlert.innerHTML = '<i class="fas fa-info-circle"></i> Mark this booking as returned';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Process Return';
    }
    
    // Fetch booking details
    fetch(`get_booking_details.php?id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentBookingData = data.booking;
                
                if (data.booking.fine_amount > 0) {
                    document.getElementById('fineAlert').style.display = 'block';
                    document.getElementById('rentalAmount').textContent = '₱' + parseFloat(data.booking.total_amount).toFixed(2);
                    document.getElementById('fineAmount').textContent = '₱' + parseFloat(data.booking.fine_amount).toFixed(2);
                    const subtotal = parseFloat(data.booking.total_amount) + parseFloat(data.booking.fine_amount);
                    document.getElementById('subtotalAmount').textContent = '₱' + subtotal.toFixed(2);
                    document.getElementById('totalPaymentAlert').style.display = 'block';
                    document.getElementById('totalPayment').textContent = '₱' + subtotal.toFixed(2);
                } else {
                    document.getElementById('fineAlert').style.display = 'none';
                    document.getElementById('totalPaymentAlert').style.display = 'none';
                }
            }
        });
    
    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    modal.show();
}

function viewBooking(bookingId) {
    const modal = new bootstrap.Modal(document.getElementById('viewBookingModal'));
    modal.show();
    fetch(`get_booking_details.php?id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) displayBookingDetails(data.booking, data.items);
            else document.getElementById('bookingDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading booking details</div>';
        })
        .catch(error => {
            document.getElementById('bookingDetailsContent').innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        });
}

function displayBookingDetails(booking, items) {
    let itemsHTML = '';
    items.forEach(item => {
        itemsHTML += `<tr><td>${item.item_name}</td><td>${item.type}</td><td>${item.quantity}</td><td>₱${parseFloat(item.price).toFixed(2)}</td></tr>`;
    });
    
    const now = new Date();
    const returnDate = new Date(booking.return_date);
    const displayStatus = now > returnDate ? 'Overdue' : booking.status;
    
    const grandTotal = parseFloat(booking.total_amount) + parseFloat(booking.fine_amount || 0) + parseFloat(booking.damage_fee || 0);
    
    let damageInfo = '';
    if (booking.damage_notes) {
        damageInfo = `<div class="alert alert-warning mt-3">
            <h6><i class="fas fa-exclamation-triangle"></i> Damage Report</h6>
            <p class="mb-0">${booking.damage_notes}</p>
            <strong>Damage Fee: ₱${parseFloat(booking.damage_fee).toFixed(2)}</strong>
        </div>`;
    }
    
    const html = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-user"></i> Customer Information</h6>
                <table class="table table-sm">
                    <tr><th>Name:</th><td>${booking.customer_name}</td></tr>
                    <tr><th>Email:</th><td>${booking.email || 'N/A'}</td></tr>
                    <tr><th>Phone:</th><td>${booking.phone || 'N/A'}</td></tr>
                    <tr><th>Address:</th><td>${booking.address || 'N/A'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-calendar"></i> Booking Information</h6>
                <table class="table table-sm">
                    <tr><th>Borrow Date:</th><td>${new Date(booking.borrow_date).toLocaleString()}</td></tr>
                    <tr><th>Return Date:</th><td>${new Date(booking.return_date).toLocaleString()}</td></tr>
                    <tr><th>Status:</th><td><span class="status-badge status-${displayStatus}">${displayStatus}</span></td></tr>
                    <tr><th>Created:</th><td>${new Date(booking.created_at).toLocaleString()}</td></tr>
                </table>
            </div>
        </div>
        
        <h6 class="mt-4"><i class="fas fa-list"></i> Booked Items</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr><th>Item Name</th><th>Type</th><th>Quantity</th><th>Price</th></tr>
                </thead>
                <tbody>${itemsHTML}</tbody>
            </table>
        </div>
        
        ${damageInfo}
        
        <div class="alert alert-${booking.fine_amount > 0 ? 'warning' : 'success'}">
            <h5 class="mb-2"><strong>Payment Breakdown:</strong></h5>
            <p class="mb-1">Rental: ₱${parseFloat(booking.total_amount).toFixed(2)}</p>
            ${booking.fine_amount > 0 ? `<p class="mb-1 text-danger">Overdue Fine: ₱${parseFloat(booking.fine_amount).toFixed(2)}</p>` : ''}
            ${booking.damage_fee > 0 ? `<p class="mb-1 text-warning">Damage Fee: ₱${parseFloat(booking.damage_fee).toFixed(2)}</p>` : ''}
            <hr>
            <h5 class="mb-0"><strong>TOTAL: ₱${grandTotal.toFixed(2)}</strong></h5>
        </div>
    `;
    document.getElementById('bookingDetailsContent').innerHTML = html;
}

// Timer functionality - shows countdown or payment due
function updateTimers() {
    const rows = document.querySelectorAll('.booking-row');
    const now = new Date();
    
    rows.forEach(row => {
        const returnDate = new Date(row.dataset.returnDate);
        const displayStatus = row.dataset.displayStatus;
        const bookingId = row.getAttribute('onclick').match(/\d+/)[0];
        const timerElement = document.getElementById(`timer-${bookingId}`);
        
        if (!timerElement) return;
        
        const timeDiff = returnDate - now;
        
        if (timeDiff < 0) {
            // OVERDUE - Show payment amount
            const totalSeconds = Math.floor(Math.abs(timeDiff) / 1000);
            const hoursOverdue = Math.ceil(totalSeconds / 3600); // Round up to next hour
            const paymentDue = hoursOverdue * 100;
            
            timerElement.className = 'timer-badge timer-pay';
            timerElement.innerHTML = `<i class="fas fa-money-bill-wave"></i> Pay: ₱${paymentDue.toLocaleString()}`;
            return;
        }
        
        // NOT YET OVERDUE - Show countdown
        const totalSeconds = Math.floor(timeDiff / 1000);
        const days = Math.floor(totalSeconds / (60 * 60 * 24));
        const hours = Math.floor((totalSeconds % (60 * 60 * 24)) / (60 * 60));
        const minutes = Math.floor((totalSeconds % (60 * 60)) / 60);
        const seconds = totalSeconds % 60;
        
        if (days === 0 && hours < 24) {
            timerElement.className = 'timer-badge timer-soon';
            timerElement.innerHTML = `<i class="fas fa-hourglass-half"></i> ${hours}h ${minutes}m ${seconds}s`;
        } else if (days < 3) {
            timerElement.className = 'timer-badge timer-soon';
            timerElement.innerHTML = `<i class="fas fa-clock"></i> ${days}d ${hours}h ${minutes}m`;
        } else {
            timerElement.className = 'timer-badge timer-upcoming';
            timerElement.innerHTML = `<i class="fas fa-calendar-check"></i> ${days}d ${hours}h`;
        }
    });
}

document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (!currentBookingType) {
        e.preventDefault();
        alert('Please select a booking type');
        return false;
    }
    
    const returnDate = new Date(document.getElementById('return_date').value);
    const now = new Date();
    if (returnDate < now) {
        e.preventDefault();
        alert('Return date must be in the future');
        return false;
    }
});

document.getElementById('addBookingModal').addEventListener('hidden.bs.modal', function () {
    currentBookingType = null;
    equipmentCounter = 0;
    packageCounter = 0;
    document.getElementById('equipmentSection').style.display = 'none';
    document.getElementById('packageSection').style.display = 'none';
    document.getElementById('equipmentList').innerHTML = '';
    document.getElementById('packageList').innerHTML = '';
    document.getElementById('totalAmount').textContent = '₱0.00';
    document.getElementById('bookingForm').reset();
});

updateTimers();
setInterval(updateTimers, 1000);
</script>
</body>
</html>