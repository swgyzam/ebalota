<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

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
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$assigned_scope = trim($_POST['assigned_scope'] ?? '');
$password = $_POST['password'] ?? '';

if (!$first_name || !$last_name || !$email || !$assigned_scope || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}

// Check for existing email
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Email is already in use.']);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert admin
$stmt = $pdo->prepare("INSERT INTO users 
    (first_name, last_name, email, password, role, is_verified, is_admin, force_password_change, assigned_scope)
    VALUES (:first, :last, :email, :pw, 'admin', 1, 1, 1, :scope)");

try {
    $stmt->execute([
        ':first' => $first_name,
        ':last' => $last_name,
        ':email' => $email,
        ':pw' => $hashed_password,
        ':scope' => $assigned_scope
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create admin.']);
    exit;
}

// Email admin credentials
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username = 'mark.anthony.mark233@gmail.com';
    $mail->Password = 'flxoykqjycmgplrv';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mark.anthony.mark233@gmail.com', 'eBalota System');
    $mail->addAddress($email, "$first_name $last_name");
    $mail->isHTML(true);
    $mail->Subject = 'eBalota Admin Credentials';
    $mail->Body = "
        <h2>Welcome, $first_name!</h2>
        <p>You have been added as an Admin for <strong>$assigned_scope</strong>.</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Password:</strong> $password</p>
        <p>Please change your password upon first login.</p>
        <a href='http://localhost/ebalota/login.php' style='
            display: inline-block;
            background-color: #1E6F46;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        '>Login Now</a>
    ";

    $mail->AltBody = "Welcome $first_name! Email: $email | Password: $password";

    $mail->send();

    echo json_encode(['status' => 'success', 'message' => 'Admin created and credentials sent to email.']);
    exit;

} catch (Exception $e) {
    error_log("Email error: " . $mail->ErrorInfo);
    echo json_encode(['status' => 'error', 'message' => 'Admin created, but email sending failed.']);
    exit;
}
