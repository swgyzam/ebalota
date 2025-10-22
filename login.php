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

// Function to send super admin verification email
function sendSuperAdminVerificationEmail($email, $first_name, $last_name, $token) {
    error_log("[EMAIL DEBUG] sendSuperAdminVerificationEmail() CALLED for $email at " . date('Y-m-d H:i:s'));
    $mail = new PHPMailer(true);
    $verificationUrl = "http://localhost/ebalota/super_admin_verify.php?token=$token";

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ebalota9@gmail.com';
        $mail->Password = 'qxdqbjttedtqkujz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('makimaki.maki1234567@gmail.com', 'eBalota System');
        $mail->addAddress($email, "$first_name $last_name");

        $mail->isHTML(true);
        $mail->Subject = 'Super Admin Login Verification';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #aa1e1e;'>Super Admin Login Verification</h2>
                <p>Hello $first_name,</p>
                <p>To complete your <strong>Super Admin</strong> login, please click the link below:</p>
                <p>
                    <a href='$verificationUrl' style='
                        display: inline-block;
                        padding: 12px 24px;
                        background-color: #aa1e1e;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Verify Super Admin Login</a>
                </p>
                <p>This link will expire in 1 hour.</p>
            </div>
        ";
        $mail->AltBody = "Verify your Super Admin login: $verificationUrl";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send admin verification email
function sendAdminVerificationEmail($email, $first_name, $last_name, $token) {
    error_log("[EMAIL DEBUG] sendSuperAdminVerificationEmail() CALLED for $email at " . date('Y-m-d H:i:s'));
    $mail = new PHPMailer(true);
    $verificationUrl = "http://localhost/ebalota/admin_verify_token.php?token=$token";

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ebalota9@gmail.com';
        $mail->Password = 'qxdqbjttedtqkujz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('makimaki.maki1234567@gmail.com', 'eBalota');
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
                <p>This link expires in 1 hour.</p>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Basic validations
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.html?error=Invalid email format.&email=" . urlencode($email));
        exit;
    }

    if (empty($password)) {
        header("Location: login.html?error=Password required.&email=" . urlencode($email));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            header("Location: login.html?error=Email does not exist.&email=" . urlencode($email));
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            header("Location: login.html?error=Incorrect password.&email=" . urlencode($email));
            exit;
        }

        // Admin/super_admin require email verification
        if (in_array($user['role'], ['super_admin', 'admin'])) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("INSERT INTO admin_login_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $token, $expires]);

            $success = false;
            if ($user['role'] === 'super_admin') {
                $success = sendSuperAdminVerificationEmail($user['email'], $user['first_name'], $user['last_name'], $token);
            } elseif ($user['role'] === 'admin') {
                $success = sendAdminVerificationEmail($user['email'], $user['first_name'], $user['last_name'], $token);
            }
            if ($success) {
                $_SESSION['pending_admin_auth'] = $user['user_id'];
                $_SESSION['pending_auth_role'] = $user['role']; 
                header("Location: admin_login_pending.php");
                exit;            
            } else {
                header("Location: login.html?error=Failed to send verification email.&email=" . urlencode($email));
                exit;
            }
        }

        // Normal user login
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role']  = $user['role'];

        $_SESSION['position'] = $user['position'];
        $_SESSION['is_coop_member'] = (bool)$user['is_coop_member'];
        $_SESSION['department'] = $user['department'] ?? '';
        $_SESSION['course'] = $user['course'] ?? '';
        $_SESSION['status'] = $user['status'] ?? '';

        if ($user['role'] === 'admin') {
            $_SESSION['assigned_scope'] = $user['assigned_scope'] ?? '';
        }        

        // Remember me
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

        // Redirect by role
        switch ($_SESSION['role']) {
            case 'super_admin':
                header("Location: super_admin/dashboard.php");
                break;
            case 'admin':
                header("Location: admin_dashboard.php");
                break;
            default:
                header("Location: voters_dashboard.php");
        }
        exit;

    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header("Location: login.html?error=System error. Please try again.");
        exit;
    }

} else {
    header("Location: login.html");
    exit;
}
