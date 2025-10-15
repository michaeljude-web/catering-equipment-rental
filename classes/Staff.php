<?php
class Staff {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addStaff($firstname, $lastname, $age, $address, $contact_number, $username, $password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("INSERT INTO staff_info (firstname, lastname, age, address, contact_number, username, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissss", $firstname, $lastname, $age, $address, $contact_number, $username, $password_hash);
        
        return $stmt->execute();
    }

    public function getStaff($limit, $offset) {
        $stmt = $this->conn->prepare("SELECT id, firstname, lastname, age, address, contact_number, username, created_at FROM staff_info ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function countStaff() {
        $result = $this->conn->query("SELECT COUNT(*) as total FROM staff_info");
        $row = $result->fetch_assoc();
        return $row['total'];
    }

    public function deleteStaff($id) {
        $stmt = $this->conn->prepare("DELETE FROM staff_info WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getStaffById($id) {
        $stmt = $this->conn->prepare("SELECT id, firstname, lastname, age, address, contact_number, username, created_at FROM staff_info WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    public function updateStaff($id, $firstname, $lastname, $age, $address, $contact_number, $username) {
        $stmt = $this->conn->prepare("UPDATE staff_info SET firstname = ?, lastname = ?, age = ?, address = ?, contact_number = ?, username = ? WHERE id = ?");
        $stmt->bind_param("ssisssi", $firstname, $lastname, $age, $address, $contact_number, $username, $id);
        
        return $stmt->execute();
    }

    public function usernameExists($username, $exclude_id = null) {
        if ($exclude_id) {
            $stmt = $this->conn->prepare("SELECT id FROM staff_info WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $exclude_id);
        } else {
            $stmt = $this->conn->prepare("SELECT id FROM staff_info WHERE username = ?");
            $stmt->bind_param("s", $username);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
}
?>