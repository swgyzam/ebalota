<?php
session_start();
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

header('Content-Type: application/json');

// DB connection
$host = 'localhost';
$db = 'evoting_system';
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
  echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
  exit;
}

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
  exit;
}

// Input
$user_id = $_POST['user_id'] ?? '';
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$assigned_scope = trim($_POST['assigned_scope'] ?? '');

if (!$user_id || !$first_name || !$last_name || !$assigned_scope) {
  echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
  exit;
}

// Get current email
$stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$email = $user['email'] ?? '';

if (!$email) {
  echo json_encode(['status' => 'error', 'message' => 'User not found.']);
  exit;
}

// Update admin (without changing email)
$stmt = $pdo->prepare("UPDATE users 
  SET first_name = :first, last_name = :last, assigned_scope = :scope 
  WHERE user_id = :id");

try {
  $stmt->execute([
    ':first' => $first_name,
    ':last' => $last_name,
    ':scope' => $assigned_scope,
    ':id' => $user_id
  ]);
} catch (PDOException $e) {
  echo json_encode(['status' => 'error', 'message' => 'Error updating admin.']);
  exit;
}

// Notify via email
$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'makimaki.maki123567@gmail.com';
  $mail->Password = 'neqlotimpppfzmwj';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  $mail->setFrom('mark.anthony.mark233@gmail.com', 'eBalota System');
  $mail->addAddress($email, "$first_name $last_name");

  $mail->isHTML(true);
  $mail->Subject = 'eBalota Admin Info Updated';
  $mail->Body = "
    <h2>Hello $first_name,</h2>
    <p>Your admin account information was updated by a super admin.</p>
    <p><strong>Updated Details:</strong></p>
    <ul>
      <li>First Name: $first_name</li>
      <li>Last Name: $last_name</li>
      <li>Assigned Scope: $assigned_scope</li>
    </ul>
    <p>If this wasn't you, please contact the system administrator.</p>
    <a href='http://localhost/ebalota/login.php' style='display:inline-block;padding:10px 20px;background:#1E6F46;color:white;text-decoration:none;border-radius:5px;'>Login to eBalota</a>
  ";

  $mail->AltBody = "Your admin info was updated. First Name: $first_name | Last Name: $last_name | Scope: $assigned_scope";

  $mail->send();
} catch (Exception $e) {
  error_log("Email notification failed: {$mail->ErrorInfo}");
}

echo json_encode(['status' => 'success', 'message' => 'Admin updated successfully.']);
exit;
