<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get position name from POST
$positionName = trim($_POST['position_name'] ?? '');

if (empty($positionName)) {
    echo json_encode(['success' => false, 'message' => 'Position name is required']);
    exit();
}

try {
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
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if position already exists for this admin
    $stmt = $pdo->prepare("SELECT id FROM positions WHERE position_name = :positionName AND created_by = :userId");
    $stmt->execute([':positionName' => $positionName, ':userId' => $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Position already exists']);
        exit();
    }
    
    // Insert new position
    $stmt = $pdo->prepare("INSERT INTO positions (position_name, created_by) VALUES (:positionName, :userId)");
    $stmt->execute([':positionName' => $positionName, ':userId' => $_SESSION['user_id']]);
    
    $positionId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'position_id' => $positionId,
        'message' => 'Position added successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>