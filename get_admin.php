<?php
session_start();
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
  exit;
}

$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
  echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
  exit;
}

$stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, assigned_scope FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch();

if ($admin) {
  echo json_encode(['status' => 'success', 'data' => $admin]);
} else {
  echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
}
exit;
