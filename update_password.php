<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

// Get the posted data
$data = json_decode(file_get_contents('php://input'), true);
$newPassword = $data['new_password'] ?? '';

if (empty($newPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'Password is required.']);
    exit();
}

// Database connection
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
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the password and set force_password_change to 0
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("UPDATE users SET password = :password, force_password_change = 0 WHERE user_id = :user_id");
$stmt->execute([
    ':password' => $hashedPassword,
    ':user_id' => $userId
]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update password.']);
}