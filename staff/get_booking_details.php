<?php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID not provided']);
    exit();
}

$booking_id = intval($_GET['id']);

try {
    // Get booking details with computed fine
    $stmt = $conn->prepare("
        SELECT 
            id, customer_name, email, phone, address, 
            borrow_date, return_date, actual_return_date,
            total_amount, fine_amount, damage_fee, damage_notes,
            status, created_at,
            CASE 
                WHEN status = 'Borrowed' AND NOW() > return_date THEN TIMESTAMPDIFF(HOUR, return_date, NOW()) * 100
                ELSE fine_amount
            END as calculated_fine
        FROM customer_booking 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }
    
    $booking = $result->fetch_assoc();
    $booking['fine_amount'] = $booking['calculated_fine']; // Use real-time calculated fine
    unset($booking['calculated_fine']); // Remove temporary field
    $stmt->close();
    
    // Get booking items
    $items = [];
    $stmt = $conn->prepare("
        SELECT 
            bi.id,
            bi.quantity,
            bi.price,
            CASE 
                WHEN bi.equipment_id IS NOT NULL THEN e.name
                WHEN bi.package_id IS NOT NULL THEN p.package_name
            END as item_name,
            CASE 
                WHEN bi.equipment_id IS NOT NULL THEN 'Equipment'
                WHEN bi.package_id IS NOT NULL THEN 'Package'
            END as type
        FROM booking_items bi
        LEFT JOIN equipments e ON bi.equipment_id = e.id
        LEFT JOIN packages p ON bi.package_id = p.id
        WHERE bi.booking_id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'booking' => $booking,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>