<?php
class StaffAuth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function dec($data) {
        if ($data === null || $data === '') return '';
        $decoded   = base64_decode($data);
        $iv        = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', ENC_KEY, 0, $iv);
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT id, firstname, lastname, username, password_hash FROM staff_info WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $staff = $result->fetch_assoc();
            if (password_verify($password, $staff['password_hash'])) {
                $firstname = $this->dec($staff['firstname']);
                $lastname  = $this->dec($staff['lastname']);

                $_SESSION['staff_logged_in'] = true;
                $_SESSION['staff_id']        = $staff['id'];
                $_SESSION['staff_username']  = $staff['username'];
                $_SESSION['staff_firstname'] = $firstname;
                $_SESSION['staff_lastname']  = $lastname;
                $_SESSION['staff_fullname']  = $firstname . ' ' . $lastname;
                return true;
            }
        }
        return false;
    }

    public function logout() {
        unset($_SESSION['staff_logged_in'], $_SESSION['staff_id'], $_SESSION['staff_username'],
              $_SESSION['staff_firstname'], $_SESSION['staff_lastname'], $_SESSION['staff_fullname']);
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true;
    }

    public function getStaffId() {
        return $_SESSION['staff_id'] ?? null;
    }

    public function getStaffUsername() {
        return $_SESSION['staff_username'] ?? null;
    }

    public function getStaffFullname() {
        return $_SESSION['staff_fullname'] ?? null;
    }
}
?>