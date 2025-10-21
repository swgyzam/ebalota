<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is COOP admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SESSION['assigned_scope'] !== 'COOP') {
    header('Location: login.php');
    exit();
}

// DB Connection
 $host = 'localhost';
 $db   = 'evoting_system';
 $user = 'root';
 $pass = '';
 $charset = 'utf8mb4';
 $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
 $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

// Function to send email using PHPMailer
function sendMigsStatusEmail($userEmail, $userName, $newStatus) {
    $subject = "MIGS Status Update";
    $message = "Dear $userName,\n\n";
    $message .= "Your MIGS status has been " . ($newStatus ? "activated" : "deactivated") . ".\n\n";
    $message .= "This means you are " . ($newStatus ? "now eligible" : "no longer eligible") . " to vote in the COOP election.\n\n";
    $message .= "If you believe this is an error, please contact the administrator.\n\n";
    $message .= "Regards,\nCVSU eVoting System";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ebalota9@gmail.com';
        $mail->Password = 'qxdqbjttedtqkujz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('makimaki.maki123567@gmail.com', 'Ebalota');
        $mail->addAddress($userEmail, $userName);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "
            Hi $userName,<br><br>
            Your MIGS status has been " . ($newStatus ? "activated" : "deactivated") . ".<br><br>
            This means you are " . ($newStatus ? "now eligible" : "no longer eligible") . " to vote in the COOP election.<br><br>
            If you believe this is an error, please contact the administrator.<br><br>
            Regards,<br>CVSU eVoting System | Cavite State University
        ";
        $mail->AltBody = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $userEmail: " . $mail->ErrorInfo);
        return false;
    }
}

// Get user ID from URL
if (!isset($_GET['user_id'])) {
    $_SESSION['message'] = "User ID is required.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

 $userId = (int)$_GET['user_id'];

// Get user details
 $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_coop_member = 1");
 $stmt->execute([$userId]);
 $user = $stmt->fetch();

if (!$user) {
    $_SESSION['message'] = "User not found or not a COOP member.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

// Toggle MIGS status
 $newStatus = $user['migs_status'] ? 0 : 1;
 $stmt = $pdo->prepare("UPDATE users SET migs_status = ? WHERE user_id = ?");
 $stmt->execute([$newStatus, $userId]);

// Send email
 $emailSent = sendMigsStatusEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $newStatus);

// Set session message
 $_SESSION['message'] = "MIGS status has been " . ($newStatus ? "activated" : "deactivated") . " for " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ".";
 $_SESSION['message'] .= $emailSent ? " An email notification has been sent." : " However, the email notification failed to send.";
 $_SESSION['message_type'] = "success";

header('Location: admin_manage_users.php');
exit();
?>