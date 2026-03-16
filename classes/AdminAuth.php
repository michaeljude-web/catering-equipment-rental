<?php
class AdminAuth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($username, $password) {

        $stmt = $this->conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {

            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin['password'])) {

                $_SESSION['admin'] = $admin['username'];
                return true;

            }
        }

        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION['admin']);
    }

    public function logout() {
        session_destroy();
        unset($_SESSION['admin']);
    }
}
