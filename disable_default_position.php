<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
  exit();
}

// Get position name from POST
$positionName = $_POST['position_name'] ?? '';
if (empty($positionName)) {
  echo json_encode(['success' => false, 'message' => 'Position name is required']);
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
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Create disabled_default_positions table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS disabled_default_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    position_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_admin_position (admin_id, position_name)
)");

// Insert the disabled position
try {
    $stmt = $pdo->prepare("INSERT INTO disabled_default_positions (admin_id, position_name) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $positionName]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'message' => 'Position is already disabled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}