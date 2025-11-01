<?php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json');

$booking_item_id = isset($_GET['booking_item_id']) ? intval($_GET['booking_item_id']) : 0;

if ($booking_item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking item ID']);
    exit();
}

$sql = "SELECT bi.package_id, bi.quantity as package_qty
        FROM booking_items bi
        WHERE bi.id = ? AND bi.package_id IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Package not found']);
    exit();
}

$row = $result->fetch_assoc();
$package_id = $row['package_id'];
$package_qty = $row['package_qty'];
$stmt->close();

$sql = "SELECT pi.quantity as item_qty, e.name as equipment_name
        FROM package_items pi
        JOIN equipments e ON pi.equipment_id = e.id
        WHERE pi.package_id = ?
        ORDER BY e.name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $total_qty = $row['item_qty'] * $package_qty;
    $items[] = [
        'equipment_name' => $row['equipment_name'],
        'quantity' => $total_qty
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'items' => $items
]);
?>