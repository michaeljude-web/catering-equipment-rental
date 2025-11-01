<?php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json');

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

$equipment = [];

$sql_equipment = "SELECT bi.equipment_id, bi.quantity, e.name as equipment_name
                  FROM booking_items bi
                  JOIN equipments e ON bi.equipment_id = e.id
                  WHERE bi.booking_id = ? AND bi.equipment_id IS NOT NULL";

$stmt = $conn->prepare($sql_equipment);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $equipment[] = [
        'equipment_id' => $row['equipment_id'],
        'equipment_name' => $row['equipment_name'],
        'quantity' => $row['quantity']
    ];
}
$stmt->close();

$sql_packages = "SELECT bi.package_id, bi.quantity as package_qty, 
                        pi.equipment_id, pi.quantity as item_qty, 
                        e.name as equipment_name
                 FROM booking_items bi
                 JOIN package_items pi ON bi.package_id = pi.package_id
                 JOIN equipments e ON pi.equipment_id = e.id
                 WHERE bi.booking_id = ? AND bi.package_id IS NOT NULL";

$stmt = $conn->prepare($sql_packages);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $total_qty = $row['package_qty'] * $row['item_qty'];
    
    $found = false;
    foreach ($equipment as &$eq) {
        if ($eq['equipment_id'] == $row['equipment_id']) {
            $eq['quantity'] += $total_qty;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $equipment[] = [
            'equipment_id' => $row['equipment_id'],
            'equipment_name' => $row['equipment_name'],
            'quantity' => $total_qty
        ];
    }
}
$stmt->close();

echo json_encode([
    'success' => true,
    'equipment' => $equipment
]);
?>