<?php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Mailer
 * The only thing this app ever emails is the Admin's password-reset OTP.
 * Kept to one job on purpose — if SalonFlow ever needs more email types,
 * this is the place to add them, not a reason to redesign it.
 */
class Mailer
{
    /** Returns true on success. Never throws — failures are logged and swallowed. */
    public static function sendOtpEmail(string $toEmail, string $toName, string $otp): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = APP_NAME . ' - Your Password Reset Code';
            $mail->Body = self::otpEmailBody($toName, $otp);
            $mail->AltBody = "Your " . APP_NAME . " password reset code is: {$otp}\n\n"
                . "This code expires in 10 minutes and can only be used once. "
                . "If you didn't request this, you can safely ignore this email.";

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            // Never leak SMTP credentials or internal errors to the browser —
            // log server-side only, exactly like Database.php's connection failures.
            error_log('Mailer: failed to send OTP email: ' . $mail->ErrorInfo);
            return false;
        }
    }

    private static function otpEmailBody(string $name, string $otp): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hi {$safeName},</p>
            <p>Your {$safeAppName} password reset code is:</p>
            <p style="font-size:28px; font-weight:bold; letter-spacing:4px;">{$safeOtp}</p>
            <p>This code expires in 10 minutes and can only be used once.</p>
            <p>If you didn't request this, you can safely ignore this email.</p>
            HTML;
    }
}
