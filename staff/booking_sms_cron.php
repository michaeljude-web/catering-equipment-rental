<?php
/**
 * SMS Reminder Script - Automated Notifications
 * 
 * IMPORTANT: Timezone is set to Asia/Manila
 * Run via cron job every hour: 0 * * * * /usr/bin/php /path/to/send_sms_reminders.php ====================================================xuu
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// === SET TIMEZONE FIRST! ===
date_default_timezone_set('Asia/Manila');

include '../includes/db_connection.php';

// === iProgTech SMS Configuration ===
define('IPROG_API_TOKEN', 'f1c1f9b6a9b13be056773ac7d326cc2f91695e07');
define('SMS_API_URL', 'https://sms.iprogtech.com/api/v1/sms_messages');

// === Configuration ===
define('SEND_1DAY_REMINDER', true);   // 24 hours before
define('TEST_MODE', false);            // Set to TRUE to see detailed debug info

/**
 * Send SMS via iProgTech API
 */
function sendSMS($phoneNumber, $message) {
    $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    if (substr($phone, 0, 1) == '0') {
        $phone = '63' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) != '63') {
        $phone = '63' . $phone;
    }

    $postData = [
        'api_token' => IPROG_API_TOKEN,
        'message' => $message,
        'phone_number' => $phone
    ];

    $ch = curl_init(SMS_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => ($httpCode == 200 || $httpCode == 201),
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error,
        'phone' => $phone
    ];
}

/**
 * Check and send reminders
 */
function checkAndSendReminders($conn) {
    // Force timezone in MySQL query too
    $conn->query("SET time_zone = '+08:00'");
    
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $results = [
        'reminders_sent' => 0,
        'errors' => [],
        'details' => [],
        'debug' => []
    ];

    // Add debug info
    $results['debug'][] = "Current Server Time: " . $now->format('Y-m-d H:i:s');
    $results['debug'][] = "Timezone: " . date_default_timezone_get();

    // Get all active bookings
    $sql = "SELECT id, customer_name, phone, borrow_date, return_date, 
            status, sms_reminder_sent,
            TIMESTAMPDIFF(HOUR, NOW(), return_date) as hours_left,
            TIMESTAMPDIFF(MINUTE, NOW(), return_date) as minutes_left
            FROM customer_booking 
            WHERE (status = 'Approved' OR status = 'Borrowed')
            AND return_date > NOW()
            ORDER BY return_date ASC";

    $result = $conn->query($sql);

    if (!$result) {
        $results['errors'][] = "Query error: " . $conn->error;
        return $results;
    }

    $results['debug'][] = "Total active bookings found: " . $result->num_rows;

    while ($row = $result->fetch_assoc()) {
        $returnDate = new DateTime($row['return_date'], new DateTimeZone('Asia/Manila'));
        $borrowDate = new DateTime($row['borrow_date'], new DateTimeZone('Asia/Manila'));
        
        $hoursLeft = (int)$row['hours_left'];
        $minutesLeft = (int)$row['minutes_left'];
        
        $customerName = $row['customer_name'];
        $phone = $row['phone'];
        $bookingId = $row['id'];
        
        // Debug each booking
        $debugInfo = "Booking #{$bookingId} - {$customerName}: {$hoursLeft}h {$minutesLeft}m left | SMS Sent: " . ($row['sms_reminder_sent'] ? 'YES' : 'NO');
        $results['debug'][] = $debugInfo;
        
        // Skip if no phone
        if (empty($phone)) {
            $results['errors'][] = "No phone for booking #{$bookingId} ({$customerName})";
            continue;
        }

        // === 24-HOUR REMINDER ===
        // WIDER WINDOW: 20-28 hours (8 hour window para sure!)
        if (SEND_1DAY_REMINDER && $row['sms_reminder_sent'] == 0) {
            if ($hoursLeft <= 28 && $hoursLeft >= 20) {
                
                $message = "REMINDER: Equipment Return Due Soon!\n\n"
                         . "Hi {$customerName},\n"
                         . "Borrowed: " . $borrowDate->format('M d, Y g:i A') . "\n"
                         . "Return Date: " . $returnDate->format('M d, Y g:i A') . "\n\n"
                         . "You have approximately 1 day left to return your rented items.\n"
                         . "Late return fee: 100 pesos/hour.\n\n"
                         . "Thank you! From Elcielo";

                $results['debug'][] = "→ SENDING SMS to {$customerName} ({$phone})...";
                
                $smsResult = sendSMS($phone, $message);

                if ($smsResult['success']) {
                    // Update database
                    $update = $conn->prepare("UPDATE customer_booking SET sms_reminder_sent = 1 WHERE id = ?");
                    $update->bind_param("i", $bookingId);
                    
                    if ($update->execute()) {
                        $results['reminders_sent']++;
                        $results['details'][] = "✓ SMS sent to {$customerName} ({$smsResult['phone']}) - {$hoursLeft}h left";
                    } else {
                        $results['errors'][] = "SMS sent but DB update failed for {$customerName}: " . $conn->error;
                    }
                    $update->close();
                } else {
                    $results['errors'][] = "SMS FAILED for {$customerName} ({$phone}): HTTP {$smsResult['http_code']} - {$smsResult['error']} - {$smsResult['response']}";
                }
            }
        }
    }

    return $results;
}

// === DATABASE CHECK ===
function checkDatabaseSetup($conn) {
    $check = $conn->query("SHOW COLUMNS FROM customer_booking LIKE 'sms_reminder_sent'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE customer_booking ADD COLUMN sms_reminder_sent TINYINT(1) DEFAULT 0");
        return "✓ Added sms_reminder_sent column";
    }
    return "✓ Database OK";
}

// === Run Setup Check ===
$dbCheck = checkDatabaseSetup($conn);

// === Run the reminder checker ===
$results = checkAndSendReminders($conn);

// === Logging ===
$logMessage = "=================================================\n";
$logMessage .= date('Y-m-d H:i:s') . " - SMS Reminder Check\n";
$logMessage .= "=================================================\n";
$logMessage .= "Database: {$dbCheck}\n";
$logMessage .= "Reminders Sent: {$results['reminders_sent']}\n";
$logMessage .= "Errors: " . count($results['errors']) . "\n\n";

if (TEST_MODE && !empty($results['debug'])) {
    $logMessage .= "--- Debug Info ---\n";
    foreach ($results['debug'] as $debug) {
        $logMessage .= "  {$debug}\n";
    }
    $logMessage .= "\n";
}

if (!empty($results['details'])) {
    $logMessage .= "--- Success ---\n";
    foreach ($results['details'] as $detail) {
        $logMessage .= "  {$detail}\n";
    }
    $logMessage .= "\n";
}

if (!empty($results['errors'])) {
    $logMessage .= "--- Errors ---\n";
    foreach ($results['errors'] as $error) {
        $logMessage .= "  ✗ {$error}\n";
    }
    $logMessage .= "\n";
}

// Create logs directory
$logDir = '../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

file_put_contents($logDir . '/sms_notifications.log', $logMessage, FILE_APPEND);

// === Console Output ===
if (php_sapi_name() === 'cli') {
    echo $logMessage;
}

// === Browser Output ===
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html><html><head><title>SMS Reminder Test</title>";
    echo "<meta charset='UTF-8'>";
    echo "<style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { background: #252526; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .success { color: #4ec9b0; font-weight: bold; }
        .error { color: #f48771; font-weight: bold; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }
        h3 { color: #dcdcaa; margin-top: 30px; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #1e1e1e; }
        th { background: #569cd6; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #3e3e42; }
        tr:hover { background: #2d2d30; }
        .status-yes { color: #4ec9b0; font-weight: bold; }
        .status-no { color: #f48771; }
        .time-urgent { background: #5a1a1a; }
        .time-soon { background: #5a4a1a; }
        .time-ok { background: #1a3a1a; }
        .debug-box { background: #2d2d30; padding: 15px; border-left: 4px solid #569cd6; margin: 20px 0; }
    </style></head><body><div class='container'>";
    
    echo "<h2>🚀 SMS Reminder System - Test Results</h2>";
    
    // Show debug info if test mode
    if (TEST_MODE && !empty($results['debug'])) {
        echo "<div class='debug-box'>";
        echo "<h3 class='info'>🔍 Debug Information</h3>";
        echo "<pre>";
        foreach ($results['debug'] as $debug) {
            echo htmlspecialchars($debug) . "\n";
        }
        echo "</pre></div>";
    }
    
    echo "<pre>" . htmlspecialchars($logMessage) . "</pre>";
    
    // Show upcoming bookings with details
    $conn->query("SET time_zone = '+08:00'");
    $upcoming = $conn->query("SELECT id, customer_name, phone, return_date,
                             TIMESTAMPDIFF(HOUR, NOW(), return_date) as hours_left,
                             TIMESTAMPDIFF(MINUTE, NOW(), return_date) as minutes_left,
                             sms_reminder_sent
                             FROM customer_booking 
                             WHERE (status = 'Approved' OR status = 'Borrowed')
                             AND return_date > NOW()
                             ORDER BY return_date ASC
                             LIMIT 20");
    
    if ($upcoming && $upcoming->num_rows > 0) {
        echo "<h3 class='info'>📋 Upcoming Bookings (Next 20)</h3>";
        echo "<table>";
        echo "<tr>
                <th>ID</th><th>Customer</th><th>Phone</th><th>Return Date</th>
                <th>Time Left</th><th>SMS Sent?</th><th>Status</th>
              </tr>";
        
        while ($row = $upcoming->fetch_assoc()) {
            $hoursLeft = $row['hours_left'];
            $minutesLeft = $row['minutes_left'];
            
            $rowClass = '';
            $status = '';
            
            if ($hoursLeft <= 28 && $hoursLeft >= 20) {
                $rowClass = 'time-urgent';
                $status = '🎯 IN WINDOW (will send SMS)';
            } elseif ($hoursLeft < 20) {
                $rowClass = 'time-soon';
                $status = '⏰ Too close (window passed)';
            } else {
                $rowClass = 'time-ok';
                $status = '✓ Not yet time';
            }
            
            echo "<tr class='{$rowClass}'>";
            echo "<td><strong>#{$row['id']}</strong></td>";
            echo "<td>{$row['customer_name']}</td>";
            echo "<td>{$row['phone']}</td>";
            echo "<td>" . date('M d, Y h:i A', strtotime($row['return_date'])) . "</td>";
            echo "<td><strong>{$hoursLeft}h {$minutesLeft}m</strong></td>";
            echo "<td class='" . ($row['sms_reminder_sent'] ? 'status-yes' : 'status-no') . "'>" 
                 . ($row['sms_reminder_sent'] ? '✓ YES' : '✗ NO') . "</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='debug-box' style='margin-top: 30px;'>";
        echo "<h4>📌 Legend:</h4>";
        echo "<p><span class='time-urgent' style='padding: 5px 10px;'>█</span> <strong>RED</strong> = In sending window (20-28 hours before)</p>";
        echo "<p><span class='time-soon' style='padding: 5px 10px;'>█</span> <strong>YELLOW</strong> = Window already passed</p>";
        echo "<p><span class='time-ok' style='padding: 5px 10px;'>█</span> <strong>GREEN</strong> = Still too early</p>";
        echo "</div>";
        
    } else {
        echo "<p class='warning'>No upcoming bookings found.</p>";
    }
    
    echo "<div style='margin-top: 30px; padding: 20px; background: #2d2d30; border-radius: 5px;'>";
    echo "<h4 class='info'>💡 Quick Actions:</h4>";
    echo "<p>• Set <code>define('TEST_MODE', true);</code> to see detailed debug info</p>";
    echo "<p>• SMS sending window: <strong>20-28 hours before return</strong></p>";
    echo "<p>• Check logs at: <code>../logs/sms_notifications.log</code></p>";
    echo "<p>• Current server time: <strong>" . date('Y-m-d H:i:s') . " (Asia/Manila)</strong></p>";
    echo "</div>";
    
    echo "</div></body></html>";
}

$conn->close();
?>