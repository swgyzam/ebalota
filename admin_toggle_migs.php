<?php
session_start();
date_default_timezone_set('Asia/Manila');

// ==============================
// AUTH CHECK – COOP ADMIN ONLY
// ==============================
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$scopeCategory = $_SESSION['scope_category'] ?? '';
$assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// Bagong modelo: COOP admin = scope_category = 'Others-COOP'
// Legacy fallback: assigned_scope = 'COOP'
if ($scopeCategory !== 'Others-COOP' && $assignedScope !== 'COOP') {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// ==============================
// DB CONNECTION
// ==============================
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// ==============================
// PHPMailer SETUP
// ==============================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

/**
 * Send a MIGS status update email via PHPMailer.
 *
 * @param string $userEmail
 * @param string $userName
 * @param int    $newStatus 1 = activated, 0 = deactivated
 * @return bool
 */
function sendMigsStatusEmail(string $userEmail, string $userName, int $newStatus): bool
{
    $subject = "MIGS Status Update";
    $statusWord = $newStatus ? "activated" : "deactivated";

    $plainText = "Dear {$userName},\n\n"
        . "Your MIGS status has been {$statusWord}.\n\n"
        . "This means you are " . ($newStatus ? "now eligible" : "no longer eligible") . " to vote in the COOP election.\n\n"
        . "If you believe this is an error, please contact the administrator.\n\n"
        . "Regards,\nCVSU eVoting System";

    $htmlBody = "
        Hi {$userName},<br><br>
        Your MIGS status has been <strong>{$statusWord}</strong>.<br><br>
        This means you are " . ($newStatus ? "<strong>now eligible</strong>" : "<strong>no longer eligible</strong>") . " to vote in the COOP election.<br><br>
        If you believe this is an error, please contact the administrator.<br><br>
        Regards,<br>
        <strong>CVSU eVoting System | Cavite State University</strong>
    ";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        $mail->Username = 'krpmab@gmail.com';
        $mail->Password = 'ghdumnwrjbphujbs';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // TODO: palitan sa tamang from email/name mo
        $mail->setFrom('no-reply@example.com', 'CVSU eVoting System');
        $mail->addAddress($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to {$userEmail}: " . $e->getMessage());
        return false;
    }
}

// ==============================
// GET TARGET USER
// ==============================
if (!isset($_GET['user_id'])) {
    $_SESSION['message']      = "User ID is required.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

$userId = (int) $_GET['user_id'];

// Get user details – must be a COOP member
$stmt = $pdo->prepare("
    SELECT user_id, email, first_name, last_name, is_coop_member, migs_status
    FROM users
    WHERE user_id = ?
      AND is_coop_member = 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['message']      = "User not found or not a COOP member.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

// ==============================
// TOGGLE MIGS STATUS
// ==============================
$newStatus = $user['migs_status'] ? 0 : 1;

$stmt = $pdo->prepare("UPDATE users SET migs_status = ? WHERE user_id = ?");
$stmt->execute([$newStatus, $userId]);

// ==============================
// SEND EMAIL
// ==============================
$fullName  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if ($fullName === '') {
    $fullName = $user['email'];
}

$emailSent = sendMigsStatusEmail($user['email'], $fullName, $newStatus);

// ==============================
// SESSION MESSAGE
// ==============================
$statusText = $newStatus ? "activated" : "deactivated";

$_SESSION['message']  = "MIGS status has been {$statusText} for " . htmlspecialchars($fullName) . ".";
$_SESSION['message'] .= $emailSent
    ? " An email notification has been sent."
    : " However, the email notification failed to send.";
$_SESSION['message_type'] = "success";

header('Location: admin_manage_users.php');
exit();
