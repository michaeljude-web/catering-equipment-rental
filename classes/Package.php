<?php
class Package {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addPackage($package_name, $price, $equipment_ids = [], $quantities = []) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Insert package
            $stmt = $this->conn->prepare("INSERT INTO packages (package_name, price) VALUES (?, ?)");
            $stmt->bind_param("sd", $package_name, $price);
            $stmt->execute();
            $package_id = $this->conn->insert_id;
            $stmt->close();

            // Insert package items
            if (!empty($equipment_ids)) {
                $stmt_item = $this->conn->prepare("INSERT INTO package_items (package_id, equipment_id, quantity) VALUES (?, ?, ?)");
                
                foreach ($equipment_ids as $index => $equipment_id) {
                    if (!empty($equipment_id)) {
                        $quantity = isset($quantities[$index]) && $quantities[$index] > 0 ? intval($quantities[$index]) : 1;
                        $stmt_item->bind_param("iii", $package_id, $equipment_id, $quantity);
                        $stmt_item->execute();
                    }
                }
                $stmt_item->close();
            }

            $this->conn->commit();
            return $package_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    public function getPackages($limit, $offset) {
        $stmt = $this->conn->prepare("SELECT id, package_name, price, created_at FROM packages ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function countPackages() {
        $result = $this->conn->query("SELECT COUNT(*) as total FROM packages");
        $row = $result->fetch_assoc();
        return $row['total'];
    }

    public function deletePackage($id) {
        // ON DELETE CASCADE will automatically delete package_items
        $stmt = $this->conn->prepare("DELETE FROM packages WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getPackageById($id) {
        $stmt = $this->conn->prepare("SELECT id, package_name, price, created_at FROM packages WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    public function getPackageItems($package_id) {
        // FIXED: Changed e.equipment_name to e.name with alias
        $stmt = $this->conn->prepare("
            SELECT pi.id, pi.equipment_id, pi.quantity, e.name as equipment_name 
            FROM package_items pi 
            JOIN equipments e ON pi.equipment_id = e.id 
            WHERE pi.package_id = ?
            ORDER BY e.name ASC
        ");
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function updatePackage($id, $package_name, $price) {
        $stmt = $this->conn->prepare("UPDATE packages SET package_name = ?, price = ? WHERE id = ?");
        $stmt->bind_param("sdi", $package_name, $price, $id);
        
        return $stmt->execute();
    }

    public function deletePackageItem($item_id) {
        $stmt = $this->conn->prepare("DELETE FROM package_items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        return $stmt->execute();
    }

    public function addPackageItem($package_id, $equipment_id, $quantity) {
        $stmt = $this->conn->prepare("INSERT INTO package_items (package_id, equipment_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $package_id, $equipment_id, $quantity);
        
        return $stmt->execute();
    }
}
?>