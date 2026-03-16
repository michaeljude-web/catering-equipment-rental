<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === iProgTech SMS Configuration ===
$url = 'https://sms.iprogtech.com/api/v1/sms_messages';
$api_token = 'f1c1f9b6a9b13be056773ac7d326cc2f91695e07'; // iProgTech API token mo
$sender_name = 'MaMa'; // optional kung supported ng account mo
$phone_number = '09933920678'; // ← ilagay dito number mo para matest
$message = "✅ Test SMS from EquipRent System.\nThis is a test message via iProgTech API.";

// === Format Phone Number (PH format 63...) ===
$phone = preg_replace('/[^0-9]/', '', $phone_number);
if (substr($phone, 0, 1) == '0') {
    $phone = '63' . substr($phone, 1);
} elseif (substr($phone, 0, 2) != '63') {
    $phone = '63' . $phone;
}

// === Build Request Data ===
$data = [
    'api_token' => $api_token,
    'message' => $message,
    'phone_number' => $phone
];

// === Send SMS Request ===
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// === Output Result ===
echo "<pre>";
echo "📨 iProgTech SMS Test Result\n";
echo "----------------------------\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n$response\n";
echo "</pre>";
?>
