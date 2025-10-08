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

if ($_POST['action'] ?? null === 'process_checkout') {
    try {
        $selected_items = explode(',', $_POST['selected_items'] ?? '');
        $selected_items = array_filter($selected_items, 'is_numeric');
        
        if (empty($selected_items)) {
            throw new Exception('No items selected for checkout');
        }

        $customer_query = "SELECT full_name, email FROM customers WHERE id = ?";
        $customer_stmt = $conn->prepare($customer_query);
        $customer_stmt->execute([$customer_id]);
        $customer_result = $customer_stmt->get_result();
        $customer_info = $customer_result->fetch_assoc();

        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $cart_query = "
            SELECT c.id, c.equipment_id, c.quantity, c.price, c.total, e.name 
            FROM cart c 
            JOIN equipments e ON c.equipment_id = e.id 
            WHERE c.id IN ($placeholders) AND c.customer_id = ?
        ";
        
        $params = array_merge($selected_items, [$customer_id]);
        $cart_stmt = $conn->prepare($cart_query);
        $cart_stmt->execute($params);
        $cart_result = $cart_stmt->get_result();
        
        $cart_items = [];
        $total_payment = 0;
        while ($row = $cart_result->fetch_assoc()) {
            $cart_items[] = $row;
            $total_payment += $row['total'];
        }

        if (empty($cart_items)) {
            throw new Exception('Selected items not found');
        }

        $booking_ref = 'ECC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $valid_id_path = null;
        if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
            // Use relative path that works on any OS
            $upload_dir = __DIR__ . '/../uploads/valid_ids/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['valid_id']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $filename = $booking_ref . '_valid_id.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['valid_id']['tmp_name'], $upload_path)) {
                    // Store relative path in database
                    $valid_id_path = 'uploads/valid_ids/' . $filename;
                }
            }
        }

        $conn->begin_transaction();

        $booking_query = "
            INSERT INTO customer_booking (
                user_id, booking_ref, name, contact, full_address, 
                borrow_date, return_date, total_payment, valid_id_path, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ";
        
        $booking_stmt = $conn->prepare($booking_query);
        if (!$booking_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $customer_name = $_POST['customer_name'];
        $contact = $_POST['contact'];
        $full_address = $_POST['full_address'];
        $borrow_date = !empty($_POST['borrow_date']) ? $_POST['borrow_date'] : null;
        $return_date = $_POST['return_date'];
        
        $booking_params = [
            $customer_id,
            $booking_ref,
            $customer_name,
            $contact,
            $full_address,
            $borrow_date,
            $return_date,
            $total_payment,
            $valid_id_path
        ];
        
        if (!$booking_stmt->execute($booking_params)) {
            throw new Exception('Failed to create booking: ' . $booking_stmt->error);
        }

        $booking_id = $conn->insert_id;

        $create_booking_items_table = "
            CREATE TABLE IF NOT EXISTS booking_items (
                id INT(11) NOT NULL AUTO_INCREMENT,
                booking_id INT(11) NOT NULL,
                equipment_id INT(11) NOT NULL,
                quantity INT(11) NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                PRIMARY KEY (id),
                KEY fk_booking_items_booking (booking_id),
                KEY fk_booking_items_equipment (equipment_id),
                CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES customer_booking(id) ON DELETE CASCADE,
                CONSTRAINT fk_booking_items_equipment FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $conn->query($create_booking_items_table);

        $item_query = "
            INSERT INTO booking_items (booking_id, equipment_id, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ";
        $item_stmt = $conn->prepare($item_query);

        foreach ($cart_items as $item) {
            $item_params = [
                $booking_id,
                $item['equipment_id'],
                $item['quantity'],
                $item['price'],
                $item['total']
            ];
            
            if (!$item_stmt->execute($item_params)) {
                throw new Exception('Failed to save booking items');
            }
        }

        $delete_cart_query = "DELETE FROM cart WHERE id IN ($placeholders) AND customer_id = ?";
        $delete_stmt = $conn->prepare($delete_cart_query);
        $delete_stmt->execute($params);

        $conn->commit();

        $success_message = "Booking created successfully! Booking Reference: " . $booking_ref;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

if ($_POST['action'] ?? null === 'delete_item') {
    $cart_id = $_POST['cart_id'] ?? 0;
    
    $delete_query = "DELETE FROM cart WHERE id = ? AND customer_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->execute([$cart_id, $customer_id]);
    
    if ($stmt->affected_rows > 0) {
        header("Location: cart.php");
        exit();
    }
}

$query = "
    SELECT c.id AS cart_id, e.name, e.photo, c.quantity, c.price, c.total
    FROM cart c
    JOIN equipments e ON c.equipment_id = e.id
    WHERE c.customer_id = ?
    ORDER BY c.id DESC
";
$stmt = $conn->prepare($query);
$stmt->execute([$customer_id]);
$result = $stmt->get_result();

$cart_items = [];
$grand_total = 0;
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $grand_total += $row['total'];
}

$customer_query = "SELECT full_name, email FROM customers WHERE id = ?";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->execute([$customer_id]);
$customer_result = $customer_stmt->get_result();
$customer_info = $customer_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - El Cielo Catering</title>
    <meta name="description" content="Review your selected equipment rentals">
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/font/css/all.min.css">
    <style>
        .item-summary-row {
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
        }
        .item-summary-row:last-child {
            border-bottom: none;
        }
        .penalty-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body class="bg-light min-vh-100 d-flex flex-column">
    <header class="bg-white shadow-sm border-bottom">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container position-relative" style="min-height:56px;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-shopping-cart me-2 text-primary fs-4"></i>
                    <h1 class="mb-0 fs-5 fw-bold text-dark">My Cart</h1>
                </div>

                <div class="position-absolute top-50 start-50 translate-middle">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <?= count($cart_items) ?> item<?= count($cart_items) !== 1 ? 's' : '' ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <main class="container my-5 flex-grow-1">
        <?php if (empty($cart_items)): ?>
            <section class="text-center py-5">
                <div class="card border-0 shadow-sm mx-auto" style="max-width: 500px;">
                    <div class="card-body py-5">
                        <div class="mb-4">
                            <i class="fas fa-shopping-cart fa-4x text-muted"></i>
                        </div>
                        <h2 class="h4 mb-3 text-dark">Your cart is empty</h2>
                        <p class="text-muted mb-4">Browse our equipment selection and add items to your cart.</p>
                        <a href="dashboard.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-search me-2"></i>Browse Equipment
                        </a>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section>
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-bottom">
                                <h2 class="h5 mb-0 fw-semibold">
                                    <i class="fas fa-list me-2 text-primary"></i>Cart Items
                                </h2>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" class="border-0 ps-4" style="width: 50px;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                                        <label class="form-check-label" for="selectAll">
                                                            <span class="visually-hidden">Select all items</span>
                                                        </label>
                                                    </div>
                                                </th>
                                                <th scope="col" class="border-0">Equipment</th>
                                                <th scope="col" class="border-0 text-center">Quantity</th>
                                                <th scope="col" class="border-0 text-end">Unit Price</th>
                                                <th scope="col" class="border-0 text-end">Total</th>
                                                <th scope="col" class="border-0 text-center pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input item-checkbox" 
                                                               type="checkbox" 
                                                               id="item_<?= $item['cart_id'] ?>"
                                                               value="<?= $item['cart_id'] ?>"
                                                               data-name="<?= htmlspecialchars($item['name']) ?>"
                                                               data-quantity="<?= $item['quantity'] ?>"
                                                               data-unit-price="<?= $item['price'] ?>"
                                                               data-price="<?= $item['total'] ?>">
                                                        <label class="form-check-label" for="item_<?= $item['cart_id'] ?>">
                                                            <span class="visually-hidden">Select <?= htmlspecialchars($item['name']) ?></span>
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <?php if (!empty($item['photo'])): ?>
                                                                <img src="../uploads/<?= htmlspecialchars($item['photo']) ?>"
                                                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                                                     class="rounded shadow-sm"
                                                                     style="width: 80px; height: 80px; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                                     style="width: 80px; height: 80px;">
                                                                    <i class="fas fa-image text-muted fa-2x"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h3 class="h6 mb-1 fw-semibold text-dark">
                                                                <?= htmlspecialchars($item['name']) ?>
                                                            </h3>
                                                            <small class="text-muted">Equipment Rental</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                                                        <?= $item['quantity'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <span class="fw-semibold text-dark">
                                                        ₱<?= number_format($item['price'], 2) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <span class="fw-bold text-primary fs-6">
                                                        ₱<?= number_format($item['total'], 2) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center pe-4">
                                                    <button type="button" 
                                                            class="btn btn-outline-danger btn-sm delete-item" 
                                                            data-cart-id="<?= $item['cart_id'] ?>"
                                                            data-item-name="<?= htmlspecialchars($item['name']) ?>"
                                                            title="Remove item from cart">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top: 2rem;">
                            <div class="card-header bg-primary text-white">
                                <h2 class="h5 mb-0 fw-semibold">
                                    <i class="fas fa-calculator me-2"></i>Order Summary
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Selected items:</span>
                                    <span class="fw-semibold" id="selectedCount">0</span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Selected subtotal:</span>
                                    <span class="fw-semibold" id="selectedSubtotal">₱0.00</span>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <span class="fw-bold fs-5">Total Amount:</span>
                                    <span class="fw-bold fs-4 text-primary" id="selectedTotal">₱0.00</span>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-success btn-lg w-100" id="checkoutSelected" data-bs-toggle="modal" data-bs-target="#checkoutModal" disabled>
                                        <i class="fas fa-credit-card me-2"></i>Checkout Selected Items
                                    </button>
                                    
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-search me-2"></i>Browse Equipment
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="checkoutModalLabel">
                        <i class="fas fa-credit-card me-2"></i>Complete Your Booking
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="checkoutForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="process_checkout">
                        <input type="hidden" name="selected_items" id="modalSelectedItems" value="">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                       value="<?= htmlspecialchars($customer_info['full_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact" name="contact" required
                                       placeholder="09XXXXXXXXX">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_address" class="form-label">Complete Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="full_address" name="full_address" rows="3" required
                                      placeholder="House/Building No., Street, Barangay, City, Province"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="borrow_date" class="form-label">Borrow Date</label>
                                <input type="date" class="form-control" id="borrow_date" name="borrow_date" 
                                       min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="return_date" name="return_date" required
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="valid_id" class="form-label">Valid ID</label>
                            <input type="file" class="form-control" id="valid_id" name="valid_id" 
                                   accept=".jpg,.jpeg,.png,.pdf">
                            <div class="form-text">Upload a copy of your valid ID (JPG, PNG, or PDF format)</div>
                        </div>
                        
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body">
                                <h6 class="card-title fw-bold mb-3">
                                    <i class="fas fa-list-ul me-2"></i>Order Summary
                                </h6>
                                
                                <div id="modalItemsList" class="mb-3">
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold" id="modalSubtotal">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <strong>Total Amount:</strong>
                                    <strong class="text-primary fs-5" id="modalSelectedTotal">₱0.00</strong>
                                </div>
                                
                                <div class="penalty-warning p-3 rounded">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                                        <div>
                                            <strong class="d-block mb-1">Late Return Penalty</strong>
                                            <small class="text-dark">
                                                Failure to return equipment by <strong id="penaltyReturnDate" class="text-danger">the specified return date</strong> 
                                                will incur a penalty fee of <strong>₱100.00 per day</strong> for each day delayed.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>s -->
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Confirm Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="bg-white text-center py-4 shadow-sm mt-auto border-top">
        <div class="container">
            <p class="mb-0 text-muted">&copy; 2025 El Cielo Catering Services. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const selectedCountElement = document.getElementById('selectedCount');
            const selectedSubtotalElement = document.getElementById('selectedSubtotal');
            const selectedTotalElement = document.getElementById('selectedTotal');
            const checkoutSelectedBtn = document.getElementById('checkoutSelected');
            
            const modalSelectedItems = document.getElementById('modalSelectedItems');
            const modalItemsList = document.getElementById('modalItemsList');
            const modalSubtotal = document.getElementById('modalSubtotal');
            const modalSelectedTotal = document.getElementById('modalSelectedTotal');
            const borrowDateInput = document.getElementById('borrow_date');
            const returnDateInput = document.getElementById('return_date');
            const penaltyReturnDate = document.getElementById('penaltyReturnDate');
            
            selectAllCheckbox.addEventListener('change', function() {
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateOrderSummary();
            });
            
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                    updateOrderSummary();
                });
            });
            
            function updateSelectAllState() {
                const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
                selectAllCheckbox.checked = checkedBoxes.length === itemCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < itemCheckboxes.length;
            }
            
            function updateOrderSummary() {
                const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
                let selectedCount = checkedBoxes.length;
                let selectedSubtotal = 0;
                let selectedItems = [];
                let itemsListHTML = '';
                
                checkedBoxes.forEach(checkbox => {
                    const itemName = checkbox.dataset.name;
                    const quantity = parseInt(checkbox.dataset.quantity);
                    const unitPrice = parseFloat(checkbox.dataset.unitPrice);
                    const totalPrice = parseFloat(checkbox.dataset.price);
                    
                    selectedSubtotal += totalPrice;
                    selectedItems.push(checkbox.value);
                    
                    itemsListHTML += `
                        <div class="item-summary-row">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="flex-grow-1">
                                    <strong class="d-block">${itemName}</strong>
                                    <small class="text-muted">₱${unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} × ${quantity}</small>
                                </div>
                                <div class="text-end ms-3">
                                    <strong class="text-primary">₱${totalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                selectedCountElement.textContent = selectedCount;
                selectedSubtotalElement.textContent = '₱' + selectedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                selectedTotalElement.textContent = '₱' + selectedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                checkoutSelectedBtn.disabled = selectedCount === 0;
                
                if (selectedCount === 0) {
                    checkoutSelectedBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout Selected Items';
                    itemsListHTML = '<p class="text-muted text-center mb-0">No items selected</p>';
                } else {
                    checkoutSelectedBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Checkout ' + selectedCount + ' Item' + (selectedCount !== 1 ? 's' : '');
                }
                
                modalSelectedItems.value = selectedItems.join(',');
                modalItemsList.innerHTML = itemsListHTML;
                modalSubtotal.textContent = '₱' + selectedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                modalSelectedTotal.textContent = '₱' + selectedSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            returnDateInput.addEventListener('change', function() {
                if (this.value) {
                    const returnDate = new Date(this.value);
                    const formattedDate = returnDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    penaltyReturnDate.textContent = formattedDate;
                }
            });
            
            borrowDateInput.addEventListener('change', function() {
                const borrowDate = new Date(this.value);
                const minReturnDate = new Date(borrowDate);
                minReturnDate.setDate(minReturnDate.getDate() + 1);
                returnDateInput.min = minReturnDate.toISOString().split('T')[0];
                
                if (returnDateInput.value && new Date(returnDateInput.value) <= borrowDate) {
                    returnDateInput.value = '';
                }
            });
            
            document.getElementById('checkoutForm').addEventListener('submit', function(e) {
                const selectedItems = modalSelectedItems.value;
                const customerName = document.getElementById('customer_name').value.trim();
                const contact = document.getElementById('contact').value.trim();
                const fullAddress = document.getElementById('full_address').value.trim();
                const returnDate = document.getElementById('return_date').value;
                
                if (!selectedItems) {
                    e.preventDefault();
                    alert('Please select at least one item to checkout.');
                    return;
                }
                
                if (!customerName || !contact || !fullAddress || !returnDate) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return;
                }
                
                const contactPattern = /^09\d{9}$/;
                if (!contactPattern.test(contact)) {
                    e.preventDefault();
                    alert('Please enter a valid contact number (11 digits starting with 09).');
                    return;
                }
                
                const borrowDate = document.getElementById('borrow_date').value;
                if (borrowDate && returnDate && new Date(returnDate) <= new Date(borrowDate)) {
                    e.preventDefault();
                    alert('Return date must be after borrow date.');
                    return;
                }
            });

            document.querySelectorAll('.delete-item').forEach(button => {
                button.addEventListener('click', function() {
                    const cartId = this.dataset.cartId;
                    const itemName = this.dataset.itemName;
                    
                    if (confirm('Are you sure you want to remove "' + itemName + '" from your cart?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = '<input type="hidden" name="action" value="delete_item"><input type="hidden" name="cart_id" value="' + cartId + '">';
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            updateOrderSummary();
        });
    </script>
</body>
</html>