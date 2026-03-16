<?php
class CustomerAuth {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT id, full_name, email, password FROM customers WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['customer_id'] = $user['id'];
                    $_SESSION['customer_name'] = $user['full_name'];
                    $_SESSION['customer_email'] = $user['email'];
                    return ['status' => 'success', 'message' => 'Login successful!'];
                } else {
                    return ['status' => 'error', 'message' => 'Invalid password. Please try again.'];
                }
            } else {
                return ['status' => 'error', 'message' => 'Email not registered. Please sign up first.'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Login failed. Please try again later.'];
        }
    }
    
    public function signup($full_name, $email, $password) {
        try {
            // Check if email already exists
            $checkStmt = $this->conn->prepare("SELECT id FROM customers WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows > 0) {
                return ['status' => 'error', 'message' => 'Email already exists. Please use a different email.'];
            }
            
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->conn->prepare("INSERT INTO customers (full_name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $full_name, $email, $hashedPassword);
            
            if ($stmt->execute()) {
                return ['status' => 'success', 'message' => 'Account created successfully! You can now login.'];
            } else {
                return ['status' => 'error', 'message' => 'Registration failed. Please try again.'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Registration failed. Please try again later.'];
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        return ['status' => 'success', 'message' => 'Logged out successfully!'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['customer_id']);
    }
    
    public function getCustomerId() {
        return $_SESSION['customer_id'] ?? null;
    }
    
    public function getCustomerName() {
        return $_SESSION['customer_name'] ?? null;
    }
    
    public function getCustomerEmail() {
        return $_SESSION['customer_email'] ?? null;
    }
}