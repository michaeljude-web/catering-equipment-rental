<?php
session_start();
include '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit();
}

if (empty($_POST['booking_id'])) {
    $_SESSION['error_message'] = 'Booking ID is required.';
    header('Location: bookings.php');
    exit();
}

$booking_id = intval($_POST['booking_id']);
$has_damage = isset($_POST['has_damage']) ? intval($_POST['has_damage']) : 0;
$damage_fee = $has_damage ? floatval($_POST['damage_fee']) : 0;
$damage_notes = $has_damage && !empty($_POST['damage_notes']) ? trim($_POST['damage_notes']) : null;

$conn->begin_transaction();

try {
    // Get booking details
    $stmt = $conn->prepare("SELECT id, status, return_date FROM customer_booking WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Booking not found.");
    }
    
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    if ($booking['status'] === 'Returned' || $booking['status'] === 'Overdue') {
        throw new Exception("This booking has already been processed.");
    }
    
    // Calculate fine and determine final status
    $return_date = new DateTime($booking['return_date']);
    $now = new DateTime();
    $fine_amount = 0;
    $is_overdue = false;
    
    if ($now > $return_date) {
        // OVERDUE - calculate fine
        $interval = $return_date->diff($now);
        $hours_overdue = ($interval->days * 24) + $interval->h;
        if ($interval->i > 0) $hours_overdue++; // Round up if there are minutes
        
        $fine_amount = $hours_overdue * 100; // 100 per hour
        $is_overdue = true;
    }
    
    // Determine final status: If overdue when returned, status stays 'Overdue'
    // If returned on time, status becomes 'Returned'
    $final_status = $is_overdue ? 'Overdue' : 'Returned';
    
    // Get all equipment from this booking to restore stock
    $stmt = $conn->prepare("
        SELECT bi.equipment_id, bi.package_id, bi.quantity
        FROM booking_items bi
        WHERE bi.booking_id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking_items = $stmt->get_result();
    
    // Restore stock for equipment
    while ($item = $booking_items->fetch_assoc()) {
        if ($item['equipment_id']) {
            // Direct equipment
            $update_stmt = $conn->prepare("UPDATE equipments SET stock = stock + ? WHERE id = ?");
            $update_stmt->bind_param("ii", $item['quantity'], $item['equipment_id']);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to restore equipment stock.");
            }
            $update_stmt->close();
            
        } else if ($item['package_id']) {
            // Package - restore all equipment in package
            $pkg_stmt = $conn->prepare("SELECT equipment_id, quantity FROM package_items WHERE package_id = ?");
            $pkg_stmt->bind_param("i", $item['package_id']);
            $pkg_stmt->execute();
            $pkg_items = $pkg_stmt->get_result();
            
            while ($pkg_item = $pkg_items->fetch_assoc()) {
                $stock_to_restore = $pkg_item['quantity'] * $item['quantity'];
                
                $update_stmt = $conn->prepare("UPDATE equipments SET stock = stock + ? WHERE id = ?");
                $update_stmt->bind_param("ii", $stock_to_restore, $pkg_item['equipment_id']);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to restore package equipment stock.");
                }
                $update_stmt->close();
            }
            $pkg_stmt->close();
        }
    }
    $stmt->close();
    
    // Update booking - status will be 'Returned' if on-time, 'Overdue' if late
    $actual_return_date = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE customer_booking 
        SET status = ?, 
            actual_return_date = ?,
            fine_amount = ?,
            damage_fee = ?,
            damage_notes = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssddsi", $final_status, $actual_return_date, $fine_amount, $damage_fee, $damage_notes, $booking_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update booking status: " . $stmt->error);
    }
    $stmt->close();
    
    $conn->commit();
    
    // Success message - don't show booking ID
    if ($final_status === 'Overdue') {
        $message = "Overdue payment settled! Equipment returned with late charges.";
        if ($fine_amount > 0) {
            $message .= " Fine: ₱" . number_format($fine_amount, 2) . ".";
        }
    } else {
        $message = "Equipment returned successfully on time!";
    }
    
    if ($damage_fee > 0) {
        $message .= " Damage fee: ₱" . number_format($damage_fee, 2) . ".";
    }
    
    $_SESSION['success_message'] = $message;
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