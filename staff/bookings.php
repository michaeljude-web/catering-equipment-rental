<?php
session_start();
include '../includes/db_connection.php';
include '../classes/StaffAuth.php';

// Create StaffAuth instance and check if logged in
$staffAuth = new StaffAuth($conn);
if (!$staffAuth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

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

// Fetch all bookings
$bookings_query = "SELECT id, customer_name, email, phone, address, borrow_date, return_date, 
                   total_amount, status, created_at 
                   FROM customer_booking 
                   ORDER BY created_at DESC";
$bookings_result = $conn->query($bookings_query);
$bookings = [];
if ($bookings_result) {
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Fetch categories and equipments for booking modal
$equipments = [];
$equip_query = "SELECT e.id, e.name, e.price, e.stock, e.category_id, c.category_name 
                FROM equipments e 
                JOIN categories c ON e.category_id = c.id 
                WHERE e.stock > 0 
                ORDER BY c.category_name, e.name";
$equip_result = $conn->query($equip_query);
if ($equip_result) {
    while ($row = $equip_result->fetch_assoc()) {
        $equipments[] = $row;
    }
}

// Get packages with their items
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
    .remove-btn {
        cursor: pointer;
    }
    #bookingTypeButtons .btn {
        min-width: 150px;
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-Borrowed { background-color: #0d6efd; color: white; }
    .status-Returned { background-color: #198754; color: white; }
    .status-Overdue { background-color: #dc3545; color: white; }
    .status-Cancelled { background-color: #6c757d; color: white; }
    .booking-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .booking-row:hover {
        background-color: #f8f9fa;
    }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
    <!-- Main content -->
    <main class="flex-fill">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-calendar-check"></i> Customer Bookings</h1>
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

            <!-- Bookings Table -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer Name</th>
                                    <th>Borrow Date</th>
                                    <th>Return Date</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>No bookings found. Create your first booking!</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr class="booking-row" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                            <td><?php echo $booking['id']; ?></td>
                                            <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['borrow_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['return_date'])); ?></td>
                                            <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                    <?php echo $booking['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); viewBooking(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
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
<div class="modal fade" id="addBookingModal" tabindex="-1" aria-labelledby="addBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addBookingModalLabel">
                    <i class="fas fa-calendar-plus"></i> New Customer Booking
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="process_booking.php" method="POST" id="bookingForm">
                    <!-- Customer Information -->
                    <h5 class="mb-3"><i class="fas fa-user"></i> Customer Information</h5>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        <div class="col-md-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="customer@example.com">
                        </div>
                        <div class="col-md-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="+63 XXX XXX XXXX">
                        </div>
                        <div class="col-md-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address" placeholder="Complete Address">
                        </div>
                    </div>

                    <!-- Booking Dates -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-calendar"></i> Booking Dates</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="borrow_date" class="form-label">Borrow Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="borrow_date" name="borrow_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="return_date" name="return_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- Booking Type Selection -->
                    <h5 class="mb-3 mt-4"><i class="fas fa-box"></i> Select Booking Type</h5>
                    <div class="mb-3" id="bookingTypeButtons">
                        <button type="button" class="btn btn-outline-primary" id="equipmentBtn" onclick="showBookingType('equipment')">
                            <i class="fas fa-tools"></i> Equipment
                        </button>
                        <button type="button" class="btn btn-outline-success" id="packageBtn" onclick="showBookingType('package')">
                            <i class="fas fa-box-open"></i> Package
                        </button>
                    </div>

                    <!-- Equipment Selection -->
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

                    <!-- Package Selection -->
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

                    <!-- Total Amount Display -->
                    <div class="alert alert-info">
                        <h5 class="mb-0"><strong>Total Amount: <span class="text-primary" id="totalAmount">₱0.00</span></strong></h5>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-warning" onclick="resetModalForm()">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button type="submit" form="bookingForm" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Booking
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Booking Details Modal -->
<div class="modal fade" id="viewBookingModal" tabindex="-1" aria-labelledby="viewBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewBookingModalLabel">
                    <i class="fas fa-info-circle"></i> Booking Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="bookingDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/font/js/all.min.js"></script>
<script>
const equipments = <?php echo json_encode($equipments); ?>;
const packages = <?php echo json_encode($packages); ?>;

let equipmentCounter = 0;
let packageCounter = 0;
let currentBookingType = null;

function showBookingType(type) {
    currentBookingType = type;
    if (type === 'equipment') {
        document.getElementById('equipmentSection').style.display = 'block';
        document.getElementById('packageSection').style.display = 'none';
        document.getElementById('equipmentBtn').classList.add('active');
        document.getElementById('packageBtn').classList.remove('active');
        document.getElementById('packageList').innerHTML = '';
        packageCounter = 0;
        if (equipmentCounter === 0) addEquipment();
    } else {
        document.getElementById('equipmentSection').style.display = 'none';
        document.getElementById('packageSection').style.display = 'block';
        document.getElementById('packageBtn').classList.add('active');
        document.getElementById('equipmentBtn').classList.remove('active');
        document.getElementById('equipmentList').innerHTML = '';
        equipmentCounter = 0;
        if (packageCounter === 0) addPackage();
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
        optionsHTML += `<option value="${eq.id}" data-price="${eq.price}" data-stock="${eq.stock}">${eq.name} - ₱${parseFloat(eq.price).toFixed(2)} (Stock: ${eq.stock})</option>`;
    });
    if (currentCategory !== '') optionsHTML += '</optgroup>';
    
    itemDiv.innerHTML = `<div class="row"><div class="col-md-8"><label class="form-label">Equipment</label><select class="form-select equipment-select" name="equipment_id[]" onchange="calculateTotal()" required>${optionsHTML}</select></div><div class="col-md-2"><label class="form-label">Quantity</label><input type="number" class="form-control equipment-quantity" name="equipment_quantity[]" value="1" min="1" onchange="validateStock(this); calculateTotal()" required></div><div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-danger btn-sm w-100 remove-btn" onclick="removeEquipment(${equipmentCounter})"><i class="fas fa-trash"></i></button></div></div>`;
    equipmentList.appendChild(itemDiv);
}

function removeEquipment(id) {
    const element = document.getElementById(`equipment-${id}`);
    if (element) {
        element.remove();
        calculateTotal();
    }
}

function addPackage() {
    packageCounter++;
    const packageList = document.getElementById('packageList');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'package-item';
    itemDiv.id = `package-${packageCounter}`;
    
    let optionsHTML = '<option value="">-- Select Package --</option>';
    packages.forEach(pkg => {
        const items = pkg.items ? pkg.items : 'No items';
        optionsHTML += `<option value="${pkg.id}" data-price="${pkg.price}" data-items="${items}">${pkg.package_name} - ₱${parseFloat(pkg.price).toFixed(2)}</option>`;
    });
    
    itemDiv.innerHTML = `<div class="row"><div class="col-md-8"><label class="form-label">Package</label><select class="form-select package-select" name="package_id[]" onchange="updatePackageDetails(this); calculateTotal()" required>${optionsHTML}</select><small class="text-muted package-details-${packageCounter}"></small></div><div class="col-md-2"><label class="form-label">Quantity</label><input type="number" class="form-control package-quantity" name="package_quantity[]" value="1" min="1" onchange="calculateTotal()" required></div><div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-danger btn-sm w-100 remove-btn" onclick="removePackage(${packageCounter})"><i class="fas fa-trash"></i></button></div></div>`;
    packageList.appendChild(itemDiv);
}

function removePackage(id) {
    const element = document.getElementById(`package-${id}`);
    if (element) {
        element.remove();
        calculateTotal();
    }
}

function updatePackageDetails(select) {
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.value) {
        const items = selectedOption.dataset.items;
        const packageId = select.closest('.package-item').id.split('-')[1];
        const detailsElement = document.querySelector(`.package-details-${packageId}`);
        if (detailsElement) detailsElement.textContent = `Includes: ${items}`;
    }
}

function validateStock(input) {
    const row = input.closest('.equipment-item');
    const select = row.querySelector('.equipment-select');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.value) {
        const maxStock = parseInt(selectedOption.dataset.stock);
        const quantity = parseInt(input.value);
        if (quantity > maxStock) {
            alert(`Maximum available stock is ${maxStock}`);
            input.value = maxStock;
        }
    }
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.equipment-select').forEach((select, index) => {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const quantity = parseInt(document.querySelectorAll('.equipment-quantity')[index].value);
            total += price * quantity;
        }
    });
    document.querySelectorAll('.package-select').forEach((select, index) => {
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const quantity = parseInt(document.querySelectorAll('.package-quantity')[index].value);
            total += price * quantity;
        }
    });
    document.getElementById('totalAmount').textContent = '₱' + total.toFixed(2);
}

function resetModalForm() {
    currentBookingType = null;
    equipmentCounter = 0;
    packageCounter = 0;
    document.getElementById('equipmentSection').style.display = 'none';
    document.getElementById('packageSection').style.display = 'none';
    document.getElementById('equipmentBtn').classList.remove('active');
    document.getElementById('packageBtn').classList.remove('active');
    document.getElementById('equipmentList').innerHTML = '';
    document.getElementById('packageList').innerHTML = '';
    document.getElementById('totalAmount').textContent = '₱0.00';
    document.getElementById('bookingForm').reset();
}

document.getElementById('borrow_date').addEventListener('change', function() {
    const returnDateInput = document.getElementById('return_date');
    returnDateInput.min = this.value;
    if (returnDateInput.value && new Date(returnDateInput.value) < new Date(this.value)) {
        returnDateInput.value = this.value;
    }
});

document.getElementById('bookingForm').addEventListener('submit', function(e) {
    if (!currentBookingType) {
        e.preventDefault();
        alert('Please select a booking type (Equipment or Package)');
        return false;
    }
    const borrowDate = new Date(document.getElementById('borrow_date').value);
    const returnDate = new Date(document.getElementById('return_date').value);
    if (returnDate < borrowDate) {
        e.preventDefault();
        alert('Return date must be on or after the borrow date');
        return false;
    }
});

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
    const html = `<div class="row"><div class="col-md-6"><h6><i class="fas fa-user"></i> Customer Information</h6><table class="table table-sm"><tr><th>Name:</th><td>${booking.customer_name}</td></tr><tr><th>Email:</th><td>${booking.email || 'N/A'}</td></tr><tr><th>Phone:</th><td>${booking.phone || 'N/A'}</td></tr><tr><th>Address:</th><td>${booking.address || 'N/A'}</td></tr></table></div><div class="col-md-6"><h6><i class="fas fa-calendar"></i> Booking Information</h6><table class="table table-sm"><tr><th>Booking ID:</th><td>#${booking.id}</td></tr><tr><th>Borrow Date:</th><td>${new Date(booking.borrow_date).toLocaleDateString()}</td></tr><tr><th>Return Date:</th><td>${new Date(booking.return_date).toLocaleDateString()}</td></tr><tr><th>Status:</th><td><span class="status-badge status-${booking.status}">${booking.status}</span></td></tr><tr><th>Created:</th><td>${new Date(booking.created_at).toLocaleString()}</td></tr></table></div></div><h6 class="mt-4"><i class="fas fa-list"></i> Booked Items</h6><div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light"><tr><th>Item Name</th><th>Type</th><th>Quantity</th><th>Price</th></tr></thead><tbody>${itemsHTML}</tbody></table></div><div class="alert alert-success"><h5 class="mb-0"><strong>Total Amount: ₱${parseFloat(booking.total_amount).toFixed(2)}</strong></h5></div>`;
    document.getElementById('bookingDetailsContent').innerHTML = html;
}

document.getElementById('addBookingModal').addEventListener('hidden.bs.modal', function () {
    resetModalForm();
});
</script>
</body>
</html>