<?php 
session_start();
include '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: booking.php');
    exit();
}

$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$has_damage = isset($_POST['has_damage']) ? intval($_POST['has_damage']) : 0;
$damage_fee = isset($_POST['damage_fee']) ? floatval($_POST['damage_fee']) : 0;
$damage_notes = isset($_POST['damage_notes']) ? trim($_POST['damage_notes']) : null;

if ($booking_id <= 0) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: booking.php');
    exit();
}

$conn->begin_transaction();

try {
    // Get booking details
    $stmt = $conn->prepare("SELECT status, return_date, total_amount FROM customer_booking WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Booking not found.");
    }
    
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    if ($booking['status'] === 'Returned') {
        throw new Exception("This booking has already been returned.");
    }
    
    // Calculate overdue fine
    $fine_amount = 0;
    $now = new DateTime();
    $return_date = new DateTime($booking['return_date']);
    
    if ($now > $return_date) {
        $interval = $return_date->diff($now);
        $total_hours = ($interval->days * 24) + $interval->h;
        if ($interval->i > 0) {
            $total_hours++; // Round up if there are minutes
        }
        $fine_amount = $total_hours * 100; // ₱100 per hour
    }
    
    // STEP 1: Get all booking items and RESTORE stock first (return everything)
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
    
    // STEP 2: Process damaged equipment (if any)
    $damaged_items_json = null;
    
    if ($has_damage === 1) {
        $damaged_equipment_ids = isset($_POST['damaged_equipment_id']) ? $_POST['damaged_equipment_id'] : [];
        $damaged_quantities = isset($_POST['damaged_quantity']) ? $_POST['damaged_quantity'] : [];
        
        // Validate arrays
        if (count($damaged_equipment_ids) !== count($damaged_quantities)) {
            throw new Exception('Invalid damaged equipment data');
        }
        
        $damaged_items = [];
        
        // Process each damaged equipment
        for ($i = 0; $i < count($damaged_equipment_ids); $i++) {
            $equipment_id = intval($damaged_equipment_ids[$i]);
            $damaged_qty = intval($damaged_quantities[$i]);
            
            if ($equipment_id > 0 && $damaged_qty > 0) {
                // Get equipment name for logging
                $name_stmt = $conn->prepare("SELECT name FROM equipments WHERE id = ?");
                $name_stmt->bind_param("i", $equipment_id);
                $name_stmt->execute();
                $name_result = $name_stmt->get_result();
                
                if ($name_result->num_rows === 0) {
                    throw new Exception("Equipment ID $equipment_id not found");
                }
                
                $eq_data = $name_result->fetch_assoc();
                $eq_name = $eq_data['name'];
                $name_stmt->close();
                
                // Deduct damaged quantity from both quantity AND stock
                $deduct_stmt = $conn->prepare("
                    UPDATE equipments 
                    SET quantity = quantity - ?,
                        stock = stock - ?
                    WHERE id = ? 
                    AND quantity >= ? 
                    AND stock >= ?
                ");
                $deduct_stmt->bind_param("iiiii", $damaged_qty, $damaged_qty, $equipment_id, $damaged_qty, $damaged_qty);
                
                if (!$deduct_stmt->execute()) {
                    throw new Exception("Failed to deduct damaged equipment: $eq_name");
                }
                
                // Check if deduction was successful
                if ($deduct_stmt->affected_rows === 0) {
                    $deduct_stmt->close();
                    throw new Exception("Insufficient quantity or stock for equipment: $eq_name. Cannot deduct $damaged_qty units.");
                }
                
                $deduct_stmt->close();
                
                // Store damaged item info
                $damaged_items[] = [
                    'equipment_id' => $equipment_id,
                    'equipment_name' => $eq_name,
                    'quantity' => $damaged_qty
                ];
            }
        }
        
        // Convert damaged items to JSON for storage
        if (!empty($damaged_items)) {
            $damaged_items_json = json_encode($damaged_items);
        }
    }
    
    // STEP 3: Update booking status to Returned
    $actual_return_date = date('Y-m-d H:i:s');
    
    $update_stmt = $conn->prepare("
        UPDATE customer_booking 
        SET status = 'Returned', 
            actual_return_date = ?,
            fine_amount = ?,
            damage_fee = ?,
            damage_notes = ?,
            damaged_items = ?
        WHERE id = ?
    ");
    $update_stmt->bind_param("sddssi", $actual_return_date, $fine_amount, $damage_fee, $damage_notes, $damaged_items_json, $booking_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update booking status.");
    }
    $update_stmt->close();
    
    $conn->commit();
    
    // Success message
    $total_payment = floatval($booking['total_amount']) + $fine_amount + $damage_fee;
    $_SESSION['success_message'] = "Equipment returned successfully! Total collected: ₱" . number_format($total_payment, 2);
    
    if ($has_damage === 1 && !empty($damaged_items)) {
        $_SESSION['success_message'] .= " (Damaged equipment deducted from inventory)";
    }
    
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