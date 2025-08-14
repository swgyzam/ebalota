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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = "No account found with that email address.";
            } else {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)");
                $stmt->execute([$user['user_id'], $token, $expiresAt]);

                $resetUrl = "http://localhost/ebalota/reset_password.php?token=$token";

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'makimaki.maki123567@gmail.com';
                $mail->Password = 'neqlotimpppfzmwj';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('mark.anthony.mark233@gmail.com', 'Evoting System');
                $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "
                    Hi {$user['first_name']},<br><br>
                    We received a request to reset your password. Click the button below to reset it:<br><br>
                    <a href='$resetUrl' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #dc3545;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Reset Password</a><br><br>
                    If you did not request this, please ignore this email.<br><br>
                    This link will expire in 1 hour.<br><br>
                    Regards,<br>Evoting System
                ";
                $mail->AltBody = "Reset your password by visiting: $resetUrl";

                $mail->send();
                $success = true;
            }
        } catch (Exception $e) {
            error_log("Password reset mail error: " . $mail->ErrorInfo);
            $errors[] = "Failed to send reset email. Please try again later.";
        } catch (PDOException $e) {
            error_log("DB error: " . $e->getMessage());
            $errors[] = "System error. Please try again later.";
        }
    }
} else {
    header("Location: forgot_password.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Forgot Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6 relative">

  <!-- Overlay Modal -->
  <?php if ($success || !empty($errors)): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50">
      <div class="bg-white shadow-xl rounded-xl p-8 w-full max-w-md text-center">
        <?php if ($success): ?>
          <div class="text-green-500 text-7xl mb-4">&#10004;</div>
          <h2 class="text-2xl font-bold text-green-700 mb-4">Reset Email Sent!</h2>
          <p class="text-gray-700 mb-6">
            If the email you provided is registered, a password reset link has been sent.<br>
            Please check your inbox and spam folder.
          </p>
          <a href="forgot_password.html" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition">Back to Forgot Password</a>
        <?php else: ?>
          <div class="text-red-500 text-7xl mb-4">&#10060;</div>
          <h2 class="text-2xl font-bold text-red-700 mb-4">Something Went Wrong</h2>
          <ul class="text-red-600 list-disc list-inside text-left mb-6">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="forgot_password.html" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition">Back to Forgot Password</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</body>
</html>
