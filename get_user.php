<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Check if user_id is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit();
}

 $userId = (int)$_GET['user_id'];

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
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

// Fetch user data
 $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
 $stmt->execute([$userId]);
 $user = $stmt->fetch();

if ($user) {
    echo json_encode(['status' => 'success', 'data' => $user]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}
?>