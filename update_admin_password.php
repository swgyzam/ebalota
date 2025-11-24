<?php
session_start();
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$newPassword = $input['new_password'] ?? '';

if (strlen($newPassword) < 8) {
    echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters."]);
    exit;
}

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users 
    SET password = :pwd, force_password_change = 0 
    WHERE user_id = :uid
");

$ok = $stmt->execute([
    ":pwd" => $hashed,
    ":uid" => $userId
]);

echo json_encode([
    "status"  => $ok ? "success" : "error",
    "message" => $ok ? "Updated" : "Update failed"
]);
