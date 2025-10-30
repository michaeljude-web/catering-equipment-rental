<?php 
session_start();
include '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit();
}

if (empty($_POST['customer_name']) || empty($_POST['return_date'])) {
    $_SESSION['error_message'] = 'Please fill in all required fields.';
    header('Location: bookings.php');
    exit();
}

$customer_name = trim($_POST['customer_name']);
$email = !empty($_POST['email']) ? trim($_POST['email']) : null;
$phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
$address = !empty($_POST['address']) ? trim($_POST['address']) : null;
$borrow_date = date('Y-m-d H:i:s'); // Current datetime
$return_date = $_POST['return_date']; // datetime-local format

// Validate return date is in the future
$return_datetime = strtotime($return_date);
$current_datetime = time();

if ($return_datetime <= $current_datetime) {
    $_SESSION['error_message'] = 'Return date must be in the future.';
    header('Location: bookings.php');
    exit();
}

// Convert to proper datetime format for MySQL
$return_date_formatted = date('Y-m-d H:i:s', $return_datetime);

$equipment_ids = isset($_POST['equipment_id']) ? $_POST['equipment_id'] : [];
$equipment_quantities = isset($_POST['equipment_quantity']) ? $_POST['equipment_quantity'] : [];
$package_ids = isset($_POST['package_id']) ? $_POST['package_id'] : [];
$package_quantities = isset($_POST['package_quantity']) ? $_POST['package_quantity'] : [];

if (empty($equipment_ids) && empty($package_ids)) {
    $_SESSION['error_message'] = 'Please select at least one equipment or package.';
    header('Location: bookings.php');
    exit();
}

$conn->begin_transaction();

try {
    $total_amount = 0;
    $booking_items = [];
    
    // Process Equipment Items
    if (!empty($equipment_ids)) {
        foreach ($equipment_ids as $index => $equipment_id) {
            if (empty($equipment_id)) continue;
            
            $quantity = intval($equipment_quantities[$index]);
            
            if ($quantity <= 0) {
                throw new Exception("Invalid quantity for equipment.");
            }
            
            $stmt = $conn->prepare("SELECT name, price, stock FROM equipments WHERE id = ?");
            $stmt->bind_param("i", $equipment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Equipment not found.");
            }
            
            $equipment = $result->fetch_assoc();
            $stmt->close();
            
            if ($equipment['stock'] < $quantity) {
                throw new Exception("Insufficient stock for {$equipment['name']}. Available: {$equipment['stock']}, Requested: {$quantity}");
            }
            
            $item_price = $equipment['price'] * $quantity;
            $total_amount += $item_price;
            
            $booking_items[] = [
                'type' => 'equipment',
                'id' => $equipment_id,
                'quantity' => $quantity,
                'price' => $item_price,
                'stock_to_reduce' => $quantity
            ];
        }
    }
    
    // Process Package Items
    if (!empty($package_ids)) {
        foreach ($package_ids as $index => $package_id) {
            if (empty($package_id)) continue;
            
            $quantity = intval($package_quantities[$index]);
            
            if ($quantity <= 0) {
                throw new Exception("Invalid quantity for package.");
            }
            
            $stmt = $conn->prepare("SELECT package_name, price FROM packages WHERE id = ?");
            $stmt->bind_param("i", $package_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Package not found.");
            }
            
            $package = $result->fetch_assoc();
            $stmt->close();
            
            $stmt = $conn->prepare("
                SELECT pi.equipment_id, pi.quantity as pkg_quantity, e.name, e.stock 
                FROM package_items pi
                JOIN equipments e ON pi.equipment_id = e.id
                WHERE pi.package_id = ?
            ");
            $stmt->bind_param("i", $package_id);
            $stmt->execute();
            $pkg_items_result = $stmt->get_result();
            
            while ($pkg_item = $pkg_items_result->fetch_assoc()) {
                $required_stock = $pkg_item['pkg_quantity'] * $quantity;
                if ($pkg_item['stock'] < $required_stock) {
                    throw new Exception("Insufficient stock for {$pkg_item['name']} in package {$package['package_name']}. Available: {$pkg_item['stock']}, Required: {$required_stock}");
                }
            }
            $stmt->close();
            
            $item_price = $package['price'] * $quantity;
            $total_amount += $item_price;
            
            $booking_items[] = [
                'type' => 'package',
                'id' => $package_id,
                'quantity' => $quantity,
                'price' => $item_price
            ];
        }
    }
    
    // Insert into customer_booking table with datetime
    $stmt = $conn->prepare("INSERT INTO customer_booking (customer_name, email, phone, address, borrow_date, return_date, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Borrowed')");
    $stmt->bind_param("ssssssd", $customer_name, $email, $phone, $address, $borrow_date, $return_date_formatted, $total_amount);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create booking: " . $stmt->error);
    }
    
    $booking_id = $conn->insert_id;
    $stmt->close();
    
    // Insert booking items and update stock
    foreach ($booking_items as $item) {
        if ($item['type'] === 'equipment') {
            $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, equipment_id, package_id, quantity, price) VALUES (?, ?, NULL, ?, ?)");
            $stmt->bind_param("iiid", $booking_id, $item['id'], $item['quantity'], $item['price']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add equipment to booking: " . $stmt->error);
            }
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE equipments SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param("ii", $item['stock_to_reduce'], $item['id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update equipment stock: " . $stmt->error);
            }
            $stmt->close();
            
        } else if ($item['type'] === 'package') {
            $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, equipment_id, package_id, quantity, price) VALUES (?, NULL, ?, ?, ?)");
            $stmt->bind_param("iiid", $booking_id, $item['id'], $item['quantity'], $item['price']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add package to booking: " . $stmt->error);
            }
            $stmt->close();
            
            $stmt = $conn->prepare("SELECT equipment_id, quantity FROM package_items WHERE package_id = ?");
            $stmt->bind_param("i", $item['id']);
            $stmt->execute();
            $pkg_items = $stmt->get_result();
            
            while ($pkg_item = $pkg_items->fetch_assoc()) {
                $stock_to_reduce = $pkg_item['quantity'] * $item['quantity'];
                
                $update_stmt = $conn->prepare("UPDATE equipments SET stock = stock - ? WHERE id = ?");
                $update_stmt->bind_param("ii", $stock_to_reduce, $pkg_item['equipment_id']);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update package equipment stock: " . $update_stmt->error);
                }
                $update_stmt->close();
            }
            $stmt->close();
        }
    }
    
    $conn->commit();
    
    $_SESSION['success_message'] = "Booking recorded successfully! Total Amount: ₱" . number_format($total_amount, 2);
    header('Location: bookings.php');
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    
    $_SESSION['error_message'] = "Booking failed: " . $e->getMessage();
    header('Location: bookings.php');
    exit();
}

$conn->close();
?>