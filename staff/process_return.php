<?php 
session_start();
include '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit();
}

$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$has_damage = isset($_POST['has_damage']) ? intval($_POST['has_damage']) : 0;
$damage_fee = isset($_POST['damage_fee']) ? floatval($_POST['damage_fee']) : 0;
$damage_notes = isset($_POST['damage_notes']) ? trim($_POST['damage_notes']) : null;

if ($booking_id <= 0) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: bookings.php');
    exit();
}

$conn->begin_transaction();

try {
    // Get booking details
    $stmt = $conn->prepare("SELECT status FROM customer_booking WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Booking not found.");
    }
    
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    if ($booking['status'] !== 'Borrowed') {
        throw new Exception("This booking has already been returned.");
    }
    
    // Get all booking items (equipment and packages)
    $stmt = $conn->prepare("
        SELECT equipment_id, package_id, quantity 
        FROM booking_items 
        WHERE booking_id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    // Restore stock for each item
    while ($item = $items_result->fetch_assoc()) {
        if ($item['equipment_id']) {
            // Direct equipment - restore stock
            $update_stmt = $conn->prepare("UPDATE equipments SET stock = stock + ? WHERE id = ?");
            $update_stmt->bind_param("ii", $item['quantity'], $item['equipment_id']);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to restore equipment stock.");
            }
            $update_stmt->close();
            
        } else if ($item['package_id']) {
            // Package - restore stock for all items in the package
            $pkg_stmt = $conn->prepare("
                SELECT equipment_id, quantity 
                FROM package_items 
                WHERE package_id = ?
            ");
            $pkg_stmt->bind_param("i", $item['package_id']);
            $pkg_stmt->execute();
            $pkg_items = $pkg_stmt->get_result();
            
            while ($pkg_item = $pkg_items->fetch_assoc()) {
                $stock_to_restore = $pkg_item['quantity'] * $item['quantity'];
                
                $restore_stmt = $conn->prepare("UPDATE equipments SET stock = stock + ? WHERE id = ?");
                $restore_stmt->bind_param("ii", $stock_to_restore, $pkg_item['equipment_id']);
                
                if (!$restore_stmt->execute()) {
                    throw new Exception("Failed to restore package equipment stock.");
                }
                $restore_stmt->close();
            }
            $pkg_stmt->close();
        }
    }
    $stmt->close();
    
    // Update booking status and damage info
    $actual_return_date = date('Y-m-d H:i:s');
    
    if ($has_damage && $damage_fee > 0) {
        $update_stmt = $conn->prepare("
            UPDATE customer_booking 
            SET status = 'Returned', 
                actual_return_date = ?,
                damage_fee = ?,
                damage_notes = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("sdsi", $actual_return_date, $damage_fee, $damage_notes, $booking_id);
    } else {
        $update_stmt = $conn->prepare("
            UPDATE customer_booking 
            SET status = 'Returned', 
                actual_return_date = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("si", $actual_return_date, $booking_id);
    }
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update booking status.");
    }
    $update_stmt->close();
    
    $conn->commit();
    
    $_SESSION['success_message'] = "Equipment returned successfully! Stock has been restored.";
    header('Location: bookings.php');
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    
    $_SESSION['error_message'] = "Return failed: " . $e->getMessage();
    header('Location: bookings.php');
    exit();
}

$conn->close();
?>