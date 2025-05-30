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
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to send admin verification email
function sendAdminVerificationEmail($email, $first_name, $last_name, $token) {
    $mail = new PHPMailer(true);
    $verificationUrl = "http://localhost/evoting/admin_verify_token.php?token=$token";

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mark.anthony.mark233@gmail.com'; // Move to config later
        $mail->Password = 'dbqwfzasqmaitlty'; // Move to config later
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('mark.anthony.mark233@gmail.com', 'eVoting System');
        $mail->addAddress($email, "$first_name $last_name");

        $mail->isHTML(true);
        $mail->Subject = 'Admin Login Verification';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0a5f2d;'>Admin Login Verification</h2>
                <p>Hello $first_name,</p>
                <p>Click the button below to complete your admin login:</p>
                <p style='margin: 25px 0;'>
                    <a href='$verificationUrl' style='
                        display: inline-block;
                        padding: 12px 24px;
                        background-color: #0a5f2d;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Verify Admin Login</a>
                </p>
                <p>This link expires in 24 hours.</p>
                <hr style='margin: 20px 0; border-top: 1px solid #eee;'>
                <p style='font-size: 12px; color: #777;'>eVoting System | Cavite State University</p>
            </div>
        ";
        $mail->AltBody = "Verify your admin login: $verificationUrl";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Cookie-based login (optional, your existing code here if any)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.html?error=Invalid email format.&email=" . urlencode($email));
        exit;
    }

    // Validate password presence
    if (empty($password)) {
        header("Location: login.html?error=Password required.&email=" . urlencode($email));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Email not found
            header("Location: login.html?error=Email does not exist.&email=" . urlencode($email));
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            // Wrong password
            header("Location: login.html?error=Incorrect password.&email=" . urlencode($email));
            exit;
        }

        // Admin login flow requiring email verification
        if ($user['is_admin']) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $stmt = $pdo->prepare("INSERT INTO admin_login_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $token, $expires]);

            // Send verification email
            if (sendAdminVerificationEmail($user['email'], $user['first_name'], $user['last_name'], $token)) {
                $_SESSION['pending_admin_auth'] = $user['user_id']; // Track pending admin login
                header("Location: admin_login_pending.php");
                exit;
            } else {
                header("Location: login.html?error=Failed to send verification email.&email=" . urlencode($email));
                exit;
            }
        }

        // Regular user login (no email verification)
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = false;

        $_SESSION['position'] = $user['position'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['course'] = $user['course'] ?? '';
        $_SESSION['status'] = $user['status'] ?? '';
        $_SESSION['role'] = 'voter'; 

        // Remember me functionality
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
            $stmt->execute([$token, $user['user_id']]);

            setcookie('remember_me', $token, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        header("Location: voters_dashboard.php");
        exit;

    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header("Location: login.html?error=System error. Please try again.");
        exit;
    }

} else {
    // If not POST, redirect to login form
    header("Location: login.html");
    exit;
}
