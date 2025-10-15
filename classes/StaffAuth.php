<?php
class StaffAuth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT id, firstname, lastname, username, password_hash FROM staff_info WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $staff = $result->fetch_assoc();
            
            if (password_verify($password, $staff['password_hash'])) {
                $_SESSION['staff_logged_in'] = true;
                $_SESSION['staff_id'] = $staff['id'];
                $_SESSION['staff_username'] = $staff['username'];
                $_SESSION['staff_firstname'] = $staff['firstname'];
                $_SESSION['staff_lastname'] = $staff['lastname'];
                $_SESSION['staff_fullname'] = $staff['firstname'] . ' ' . $staff['lastname'];
                
                return true;
            }
        }
        
        return false;
    }

    public function logout() {
        unset($_SESSION['staff_logged_in']);
        unset($_SESSION['staff_id']);
        unset($_SESSION['staff_username']);
        unset($_SESSION['staff_firstname']);
        unset($_SESSION['staff_lastname']);
        unset($_SESSION['staff_fullname']);
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true;
    }

    public function getStaffId() {
        return isset($_SESSION['staff_id']) ? $_SESSION['staff_id'] : null;
    }

    public function getStaffUsername() {
        return isset($_SESSION['staff_username']) ? $_SESSION['staff_username'] : null;
    }

    public function getStaffFullname() {
        return isset($_SESSION['staff_fullname']) ? $_SESSION['staff_fullname'] : null;
    }
}
?>