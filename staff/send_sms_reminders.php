<?php
/**
 * SMS Reminder Script - SUPER DEBUG VERSION
 * This will show you EXACTLY why SMS is not sending ------------------auto reload for sending sms
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

include '../includes/db_connection.php';

// === iProgTech SMS Configuration ===
define('IPROG_API_TOKEN', 'f1c1f9b6a9b13be056773ac7d326cc2f91695e07');
define('SMS_API_URL', 'https://sms.iprogtech.com/api/v1/sms_messages');

// === TESTING CONFIGURATION ===
define('FORCE_SEND_MODE', false);  // Set TRUE to send SMS regardless of time window
define('SHOW_ALL_BOOKINGS', true); // Show even completed bookings

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
 * Check and send reminders with DETAILED DEBUG
 */
function checkAndSendReminders($conn) {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $results = [
        'reminders_sent' => 0,
        'errors' => [],
        'details' => [],
        'debug' => []
    ];

    // Current time info
    $results['debug'][] = "╔════════════════════════════════════════════════════════╗";
    $results['debug'][] = "║  CURRENT TIME: " . $now->format('Y-m-d H:i:s') . " (Asia/Manila)  ║";
    $results['debug'][] = "╚════════════════════════════════════════════════════════╝";
    $results['debug'][] = "";

    // Get ALL bookings for debugging
    $statusCondition = SHOW_ALL_BOOKINGS ? "" : "AND (status = 'Approved' OR status = 'Borrowed')";
    
    $sql = "SELECT id, customer_name, phone, borrow_date, return_date, 
            status, sms_reminder_sent,
            NOW() as current_db_time,
            TIMESTAMPDIFF(SECOND, NOW(), return_date) as seconds_left
            FROM customer_booking 
            WHERE 1=1 $statusCondition
            ORDER BY return_date ASC
            LIMIT 20";

    $result = $conn->query($sql);

    if (!$result) {
        $results['errors'][] = "❌ Query error: " . $conn->error;
        return $results;
    }

    $results['debug'][] = "📊 Total bookings found: " . $result->num_rows;
    $results['debug'][] = "";

    $counter = 0;
    while ($row = $result->fetch_assoc()) {
        $counter++;
        
        $returnDate = new DateTime($row['return_date'], new DateTimeZone('Asia/Manila'));
        $borrowDate = new DateTime($row['borrow_date'], new DateTimeZone('Asia/Manila'));
        $dbTime = new DateTime($row['current_db_time'], new DateTimeZone('Asia/Manila'));
        
        $secondsLeft = (int)$row['seconds_left'];
        $hoursLeft = floor($secondsLeft / 3600);
        $minutesLeft = floor(($secondsLeft % 3600) / 60);
        
        $customerName = $row['customer_name'];
        $phone = $row['phone'];
        $bookingId = $row['id'];
        $smsSent = $row['sms_reminder_sent'];
        
        // === DETAILED DEBUG FOR EACH BOOKING ===
        $results['debug'][] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $results['debug'][] = "📋 BOOKING #{$bookingId} - {$customerName}";
        $results['debug'][] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $results['debug'][] = "📱 Phone: " . ($phone ?: '❌ NO PHONE');
        $results['debug'][] = "📅 Return Date: " . $returnDate->format('Y-m-d H:i:s');
        $results['debug'][] = "🕐 Database Time: " . $dbTime->format('Y-m-d H:i:s');
        $results['debug'][] = "⏱️  Time Left: {$hoursLeft}h {$minutesLeft}m ({$secondsLeft} seconds)";
        $results['debug'][] = "📊 Status: {$row['status']}";
        $results['debug'][] = "💬 SMS Already Sent: " . ($smsSent ? '✅ YES' : '❌ NO');
        
        // Check each condition
        $results['debug'][] = "";
        $results['debug'][] = "🔍 CONDITION CHECKS:";
        
        // Check 1: Phone exists
        $hasPhone = !empty($phone);
        $results['debug'][] = "  1️⃣  Has phone? " . ($hasPhone ? '✅ YES' : '❌ NO');
        
        // Check 2: SMS not sent yet
        $notSentYet = ($smsSent == 0);
        $results['debug'][] = "  2️⃣  SMS not sent yet? " . ($notSentYet ? '✅ YES' : '❌ NO (already sent)');
        
        // Check 3: Time window (24 hours = 86400 seconds)
        // Using 20-28 hours window
        $inWindow = ($secondsLeft <= 100800 && $secondsLeft >= 72000); // 28h to 20h
        $results['debug'][] = "  3️⃣  In 20-28 hour window? " . ($inWindow ? '✅ YES' : '❌ NO');
        $results['debug'][] = "      (Window: 72000-100800 seconds, Current: {$secondsLeft} seconds)";
        
        // Check 4: Not overdue
        $notOverdue = ($secondsLeft > 0);
        $results['debug'][] = "  4️⃣  Not overdue? " . ($notOverdue ? '✅ YES' : '❌ NO (already passed)');
        
        // Final decision
        $shouldSend = $hasPhone && $notSentYet && ($inWindow || FORCE_SEND_MODE) && $notOverdue;
        
        $results['debug'][] = "";
        $results['debug'][] = "🎯 DECISION: " . ($shouldSend ? '✅ WILL SEND SMS' : '❌ SKIP (conditions not met)');
        
        if (FORCE_SEND_MODE && $hasPhone && $notSentYet && $notOverdue) {
            $results['debug'][] = "⚠️  FORCE MODE ENABLED - Sending regardless of time window!";
            $shouldSend = true;
        }
        
        // === SEND SMS IF CONDITIONS MET ===
        if ($shouldSend) {
            $message = "REMINDER: Equipment Return Due Soon!\n\n"
          . "Hello {$customerName},\n\n"
          . "Borrowed on: " . $borrowDate->format('M d, Y g:i A') . "\n"
          . "Return by: " . $returnDate->format('M d, Y g:i A') . "\n\n"
          . "Please note that you have approximately 1 day left to return your rented items / packages.\n"
          . "Late returns will incur a fee of ₱100 per hour.\n\n"
          . "Thank you for your cooperation.\n"
          . "- Elcielo Equipment Rental";


            $results['debug'][] = "";
            $results['debug'][] = "📤 Sending SMS to {$phone}...";
            
            $smsResult = sendSMS($phone, $message);

            if ($smsResult['success']) {
                $results['debug'][] = "✅ SMS API Success! HTTP {$smsResult['http_code']}";
                $results['debug'][] = "📱 Sent to: {$smsResult['phone']}";
                
                // Update database
                $update = $conn->prepare("UPDATE customer_booking SET sms_reminder_sent = 1 WHERE id = ?");
                $update->bind_param("i", $bookingId);
                
                if ($update->execute()) {
                    $results['reminders_sent']++;
                    $results['details'][] = "✅ SMS sent to {$customerName} ({$smsResult['phone']})";
                    $results['debug'][] = "✅ Database updated: sms_reminder_sent = 1";
                } else {
                    $results['errors'][] = "⚠️  SMS sent but DB update failed: " . $conn->error;
                    $results['debug'][] = "❌ Database update failed: " . $conn->error;
                }
                $update->close();
            } else {
                $error_msg = "SMS API Failed - HTTP {$smsResult['http_code']} - {$smsResult['error']} - {$smsResult['response']}";
                $results['errors'][] = $error_msg;
                $results['debug'][] = "❌ " . $error_msg;
            }
        }
        
        $results['debug'][] = "";
    }

    return $results;
}

// === Check if database column exists ===
$checkCol = $conn->query("SHOW COLUMNS FROM customer_booking LIKE 'sms_reminder_sent'");
if ($checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE customer_booking ADD COLUMN sms_reminder_sent TINYINT(1) DEFAULT 0");
}

// === Run the checker ===
$results = checkAndSendReminders($conn);

// === Browser Output with Color Coding ===
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html><html><head><title>SMS Debug</title>";
    echo "<meta charset='UTF-8'>";
    echo "<style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Consolas', 'Monaco', monospace; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            line-height: 1.6;
        }
        .container { 
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(0,0,0,0.8); 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        h1 { 
            color: #4ecca3; 
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .subtitle {
            color: #a8dadc;
            font-size: 1.1em;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4ecca3;
        }
        .debug-section {
            background: #1a1a2e;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #4ecca3;
            white-space: pre-wrap;
            font-size: 0.95em;
        }
        .success { color: #4ecca3; font-weight: bold; }
        .error { color: #ee6055; font-weight: bold; }
        .warning { color: #ffd97d; font-weight: bold; }
        .info { color: #aaf683; }
        .highlight { background: rgba(78, 204, 163, 0.2); padding: 2px 5px; border-radius: 3px; }
        .summary {
            background: linear-gradient(135deg, #4ecca3 0%, #00d2ff 100%);
            color: #000;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
            font-size: 1.2em;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 10px 30px rgba(78, 204, 163, 0.3);
        }
        .config-box {
            background: #16213e;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px solid #ffd97d;
        }
        .config-box h3 {
            color: #ffd97d;
            margin-bottom: 15px;
        }
        .config-item {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 5px;
        }
    </style></head><body><div class='container'>";
    
    echo "<h1>🔬 SMS Reminder System - Super Debug Mode</h1>";
    echo "<div class='subtitle'>Complete diagnostic information for troubleshooting</div>";
    
    // Configuration display
    echo "<div class='config-box'>";
    echo "<h3>⚙️ Current Configuration</h3>";
    echo "<div class='config-item'>🔧 FORCE_SEND_MODE: <span class='highlight'>" . (FORCE_SEND_MODE ? 'TRUE (sends regardless of time)' : 'FALSE (normal mode)') . "</span></div>";
    echo "<div class='config-item'>📋 SHOW_ALL_BOOKINGS: <span class='highlight'>" . (SHOW_ALL_BOOKINGS ? 'TRUE (shows all)' : 'FALSE (active only)') . "</span></div>";
    echo "<div class='config-item'>⏰ Time Window: <span class='highlight'>20-28 hours before return date</span></div>";
    echo "<div class='config-item'>🌐 Timezone: <span class='highlight'>Asia/Manila (" . date('Y-m-d H:i:s') . ")</span></div>";
    echo "</div>";
    
    // Summary
    echo "<div class='summary'>";
    echo "📊 RESULTS: {$results['reminders_sent']} SMS Sent | " . count($results['errors']) . " Errors";
    echo "</div>";
    
    // Debug information
    if (!empty($results['debug'])) {
        echo "<div class='debug-section'>";
        foreach ($results['debug'] as $line) {
            $line = htmlspecialchars($line);
            
            // Color code specific patterns
            $line = preg_replace('/✅/', '<span class="success">✅</span>', $line);
            $line = preg_replace('/❌/', '<span class="error">❌</span>', $line);
            $line = preg_replace('/⚠️/', '<span class="warning">⚠️</span>', $line);
            $line = preg_replace('/🎯 DECISION: ✅/', '<span class="success">🎯 DECISION: ✅', $line);
            $line = preg_replace('/🎯 DECISION: ❌/', '<span class="error">🎯 DECISION: ❌', $line);
            
            echo $line . "\n";
        }
        echo "</div>";
    }
    
    // Errors
    if (!empty($results['errors'])) {
        echo "<div class='debug-section' style='border-left-color: #ee6055;'>";
        echo "<h3 class='error'>❌ ERRORS FOUND:</h3>\n\n";
        foreach ($results['errors'] as $error) {
            echo "<span class='error'>" . htmlspecialchars($error) . "</span>\n";
        }
        echo "</div>";
    }
    
    // Success details
    if (!empty($results['details'])) {
        echo "<div class='debug-section' style='border-left-color: #4ecca3;'>";
        echo "<h3 class='success'>✅ SUCCESSFUL SENDS:</h3>\n\n";
        foreach ($results['details'] as $detail) {
            echo "<span class='success'>" . htmlspecialchars($detail) . "</span>\n";
        }
        echo "</div>";
    }
    
    // Instructions
    echo "<div class='config-box'>";
    echo "<h3>💡 Troubleshooting Guide</h3>";
    echo "<div class='config-item'><strong>If NO bookings appear:</strong> Check if you have active bookings with return dates in the future</div>";
    echo "<div class='config-item'><strong>If conditions fail:</strong> Look at the 🔍 CONDITION CHECKS section above to see which test failed</div>";
    echo "<div class='config-item'><strong>To force send SMS:</strong> Set <code>FORCE_SEND_MODE = true</code> at line 16</div>";
    echo "<div class='config-item'><strong>To test timing:</strong> Create a booking with return date exactly 24 hours from now</div>";
    echo "</div>";
    
    echo "</div></body></html>";
}

$conn->close();
?>