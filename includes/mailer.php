<?php
// includes/mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

function send_otp_email(string $to_email, string $otp_code): bool {
    $mail = new PHPMailer(true);
    
    // For local testing/debugging: log the OTP so you can find it without an email
    error_log("DEBUG MFA OTP for {$to_email} is: {$otp_code}");

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? 'REPLACE_WITH_MAILTRAP_USER'; 
        $mail->Password   = $_ENV['SMTP_PASS'] ?? 'REPLACE_WITH_MAILTRAP_PASS'; 
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 2525;

        // Recipients
        $mail->setFrom('noreply@gebeya.com', 'Gebeya Security');
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Gebeya Login Verification Code';
        
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #1d4ed8;'>Gebeya Login Verification</h2>
                <p>You are attempting to log in to your account.</p>
                <p>Your one-time verification code is:</p>
                <div style='background-color: #f3f4f6; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                    <h1 style='color: #2563eb; letter-spacing: 8px; margin: 0;'>{$otp_code}</h1>
                </div>
                <p style='color: #ef4444; font-size: 14px;'>This code will expire in 5 minutes. Do not share this code with anyone.</p>
            </div>
        ";
        
        $mail->AltBody = "Your Gebeya login verification code is: {$otp_code}. It expires in 5 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function send_reset_email(string $to_email, string $reset_link): bool {
    $mail = new PHPMailer(true);
    
    // For local testing/debugging: log the link
    error_log("DEBUG RESET LINK for {$to_email} is: {$reset_link}");

    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'] ?? 'REPLACE_WITH_MAILTRAP_USER'; 
        $mail->Password   = $_ENV['SMTP_PASS'] ?? 'REPLACE_WITH_MAILTRAP_PASS'; 
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 2525;

        $mail->setFrom('noreply@gebeya.com', 'Gebeya Security');
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Gebeya Password';
        
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #1d4ed8;'>Password Reset Request</h2>
                <p>We received a request to reset your Gebeya password.</p>
                <p>Click the button below to set a new password. This link will expire in 15 minutes.</p>
                <div style='margin: 20px 0;'>
                    <a href='{$reset_link}' style='background-color: #2563eb; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Reset Password</a>
                </div>
                <p style='color: #64748b; font-size: 13px;'>If you did not request this, please ignore this email.</p>
            </div>
        ";
        
        $mail->AltBody = "Reset your password by clicking here: {$reset_link} . This link expires in 15 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Reset email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
