<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get position ID from POST
$positionId = $_POST['position_id'] ?? '';

if (empty($positionId)) {
    echo json_encode(['success' => false, 'message' => 'Position ID is required']);
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
    
    // First check if position exists
    $stmt = $pdo->prepare("SELECT id, position_name, created_by FROM positions WHERE id = :positionId");
    $stmt->execute([':positionId' => $positionId]);
    $position = $stmt->fetch();
    
    if (!$position) {
        echo json_encode(['success' => false, 'message' => 'Position not found']);
        exit();
    }
    
    // Check if position belongs to this admin
    if ($position['created_by'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this position']);
        exit();
    }
    
    // Check if position is being used by any candidates
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM election_candidates WHERE position_id = :positionId");
    $stmt->execute([':positionId' => $positionId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete position. It is being used by ' . $result['count'] . ' candidate(s).']);
        exit();
    }
    
    // Delete the position
    $stmt = $pdo->prepare("DELETE FROM positions WHERE id = :positionId");
    $stmt->execute([':positionId' => $positionId]);
    
    echo json_encode(['success' => true, 'message' => 'Position deleted successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>