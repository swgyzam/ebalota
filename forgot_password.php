<?php 
session_start();

date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
    $errorParam = urlencode('System error. Please try again later.');
    header('Location: forgot_password.html?error=' . $errorParam);
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        try {
            // Hanapin user sa database
            $stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // WALANG account na may ganyang email
                $errors[] = "The email you provided does not exist in the system.";
            } else {
                // MAY account → generate token + send email
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("
                    INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        token      = VALUES(token),
                        expires_at = VALUES(expires_at)
                ");
                $stmt->execute([$user['user_id'], $token, $expiresAt]);

                $resetUrl = "http://localhost/ebalota/reset_password.php?token=$token";

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'krpmab@gmail.com';
                $mail->Password   = 'ghdumnwrjbphujbs';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota');
                $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Reset your eBalota password';
                $mail->Body    = "
                    Hi {$user['first_name']},<br><br>
                    We received a request to reset your eBalota password. Click the button below to create a new password:<br><br>
                    <a href='$resetUrl' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #28a745;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Reset Password</a><br><br>
                    If you did not request this, you can safely ignore this email.<br><br>
                    This link will expire in 1 hour for your security.<br><br>
                    Regards,<br>
                    eBalota | Cavite State University
                ";
                $mail->AltBody = "We received a request to reset your eBalota password. Reset it by visiting: $resetUrl\n\nIf you did not request this, you can ignore this email. The link expires in 1 hour.";

                $mail->send();
                $success = true;
            }
        } catch (Exception $e) {
            error_log("Password reset mail error: " . $e->getMessage());
            $errors[] = "Failed to send reset email. Please try again later.";
        } catch (\PDOException $e) {
            error_log("DB error in forgot_password.php: " . $e->getMessage());
            $errors[] = "System error. Please try again later.";
        }
    }

    // Redirects
    if ($success) {
        // CLEAR message – hindi na conditional
        $msg = "A password reset link has been sent to your email. Please check your inbox and spam folder.";
        header('Location: forgot_password.html?success=' . urlencode($msg));
        exit;
    }

    if (!empty($errors)) {
        $errorMsg   = implode(' ', $errors);
        $errorParam = urlencode($errorMsg);
        header('Location: forgot_password.html?error=' . $errorParam);
        exit;
    }

    header('Location: forgot_password.html');
    exit;

} else {
    header("Location: forgot_password.html");
    exit;
}
