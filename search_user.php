<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the identifier from GET request
$identifier = $_GET['identifier'] ?? '';

if (empty($identifier)) {
    echo json_encode(['success' => false, 'message' => 'No identifier provided']);
    exit;
}

// Database connection
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
    
    // Debug: Log the identifier being searched
    error_log("Searching for user with identifier: " . $identifier);
    
    // FIXED: Removed middle_name from the query since it doesn't exist in users table
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, position, student_number, employee_number 
                            FROM users 
                            WHERE student_number = ? OR employee_number = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Debug: Log the found user
        error_log("User found: " . json_encode($user));
        
        echo json_encode([
            'success' => true, 
            'user' => $user,
            'identifier' => $identifier
        ]);
    } else {
        // Debug: Log that no user was found
        error_log("No user found with identifier: " . $identifier);
        
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (\PDOException $e) {
    // Debug: Log the error
    error_log("Database error: " . $e->getMessage());
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>