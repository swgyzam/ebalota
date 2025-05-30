<?php
// resend_verification.php

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = new PDO("mysql:host=localhost;dbname=evoting_system;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$email = $_POST['email'] ?? '';
$success = false;
$error = '';

if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $pdo->prepare("SELECT * FROM pending_users WHERE email = ? AND expires_at > NOW()");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate new token and update
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));

        $stmt = $pdo->prepare("UPDATE pending_users SET token = ?, expires_at = ? WHERE pending_id = ?");
        $stmt->execute([$token, $expiresAt, $user['pending_id']]);

        $verificationUrl = "http://localhost/evoting/verify_email.php?token=$token";

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'your@gmail.com';
            $mail->Password = 'your-app-password';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('your@gmail.com', 'Evoting System');
            $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
            $mail->isHTML(true);
            $mail->Subject = 'Resend Verification Email';
            $mail->Body = "Hello {$user['first_name']}, <br><br>Click the link to verify your account:<br><a href='$verificationUrl'>$verificationUrl</a>";

            $mail->send();
            $success = true;
        } catch (Exception $e) {
            $error = "Email sending failed: " . $mail->ErrorInfo;
        }
    } else {
        $error = "No pending registration found or token already expired.";
    }
} else {
    $error = "Please enter a valid email address.";
}

// Output response (you can adjust this depending on frontend)
echo json_encode(['success' => $success, 'error' => $error]);
