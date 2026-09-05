<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Mailer
 * Uses Gmail SMTP to send password reset emails.
 */
class Mailer
{
    /** Returns true on success. Never throws — failures are logged and swallowed. */
    public static function sendOtpEmail(string $toEmail, string $toName, string $otp): bool
    {
        // Check if SMTP is configured
        if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
            error_log('Mailer: SMTP credentials not configured.');
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            // Enable debug output
            $mail->SMTPDebug = 2; // Remove this after testing
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer: $str");
            };

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            // Sender and recipient
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            // Email content
            $mail->isHTML(true);
            $mail->Subject = APP_NAME . ' - Your Password Reset Code';
            $mail->Body = self::otpEmailBody($toName, $otp);
            $mail->AltBody = "Your " . APP_NAME . " password reset code is: {$otp}\n\n"
                . "This code expires in 10 minutes and can only be used once.\n\n"
                . APP_NAME . " - Admin Panel";

            $mail->send();
            error_log("Mailer: Email sent successfully to {$toEmail}");
            return true;
            
        } catch (PHPMailerException $e) {
            error_log('Mailer: failed to send OTP email: ' . $mail->ErrorInfo);
            error_log('PHPMailer Exception: ' . $e->getMessage());
            return false;
        }
    }

    private static function otpEmailBody(string $name, string $otp): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #EDF2EA; }
        .header h1 { color: #28392E; font-size: 24px; margin: 0; }
        .content { padding: 30px 0; }
        .content p { color: #24322A; font-size: 16px; line-height: 1.6; }
        .otp-code { font-size: 36px; font-weight: bold; color: #A9812D; letter-spacing: 8px; text-align: center; padding: 20px; background-color: #FBFCFA; border: 2px solid #EDF2EA; border-radius: 8px; margin: 20px 0; font-family: 'Courier New', monospace; }
        .footer { text-align: center; padding-top: 20px; border-top: 2px solid #EDF2EA; color: #55645B; font-size: 12px; }
        .warning { color: #7A2E2E; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 {$safeAppName}</h1>
        </div>
        <div class="content">
            <p>Hi <strong>{$safeName}</strong>,</p>
            <p>You requested to reset your password for your {$safeAppName} admin account.</p>
            <p>Your 6-digit verification code is:</p>
            <div class="otp-code">{$safeOtp}</div>
            <p>⏱️ This code expires in <strong>10 minutes</strong> and can only be used once.</p>
            <p class="warning">⚠️ If you didn't request this, please ignore this email. Your account is safe.</p>
        </div>
        <div class="footer">
            <p>© {$safeAppName} - Admin Panel</p>
            <p>This is an automated email. Please do not reply to this message.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}