<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function baseMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'scottiesage071804@gmail.com';
    $mail->Password = 'sbtn ginp bvjd kzmd';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('scottiesage071804@gmail.com', 'Secure Ledger');

    return $mail;
}

function sendVerificationEmail(string $toEmail, string $verificationCode): array
{
    try {
        $mail = baseMailer();
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif;'>
                <h2>Verify Your Account</h2>
                <p>Your verification code is:</p>
                <h1 style='letter-spacing: 3px;'>{$verificationCode}</h1>
                <p>This code expires in <strong>10 minutes</strong>.</p>
                <p>If you did not request this, you can ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Your verification code is: {$verificationCode}\n\nThis code expires in 10 minutes.";
        $mail->send();

        return ['success' => true, 'message' => 'Verification email sent successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function sendPasswordResetEmail(string $toEmail, string $resetCode): array
{
    try {
        $mail = baseMailer();
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif;'>
                <h2>Reset Your Password</h2>
                <p>Your password reset code is:</p>
                <h1 style='letter-spacing: 3px;'>{$resetCode}</h1>
                <p>This code expires in <strong>10 minutes</strong>.</p>
                <p>If you did not request this, you can ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Your password reset code is: {$resetCode}\n\nThis code expires in 10 minutes.";
        $mail->send();

        return ['success' => true, 'message' => 'Password reset email sent successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}