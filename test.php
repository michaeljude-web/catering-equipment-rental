<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing PHP setup...<br>";

// Test 1: Basic PHP
echo "1. PHP is working ✓<br>";

// Test 2: Session
session_start();
echo "2. Session started ✓<br>";

// Test 3: Database connection
try {
    include 'includes/db_connection.php';
    echo "3. Database connection file loaded ✓<br>";
    
    if (isset($conn) && $conn->ping()) {
        echo "4. Database connection active ✓<br>";
    } else {
        echo "4. Database connection failed ✗<br>";
    }
} catch (Exception $e) {
    echo "3. Database connection error: " . $e->getMessage() . " ✗<br>";
}

// Test 4: Classes
try {
    include 'classes/CustomerAuth.php';
    echo "5. CustomerAuth class loaded ✓<br>";
} catch (Exception $e) {
    echo "5. CustomerAuth class error: " . $e->getMessage() . " ✗<br>";
}

try {
    include 'classes/Category.php';
    echo "6. Category class loaded ✓<br>";
} catch (Exception $e) {
    echo "6. Category class error: " . $e->getMessage() . " ✗<br>";
}

// Test 5: AJAX POST
if ($_POST['action'] ?? false) {
    echo "7. AJAX POST received ✓<br>";
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'AJAX test successful']);
    exit;
}

echo "<br><button onclick=\"testAjax()\">Test AJAX</button>";
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function testAjax() {
    $.post('test.php', {action: 'test'})
    .done(function(response) {
        console.log('AJAX Success:', response);
        alert('AJAX Test: ' + response.message);
    })
    .fail(function(xhr, status, error) {
        console.log('AJAX Error:', status, error, xhr.responseText);
        alert('AJAX Error: ' + status);
    });
}
</script>