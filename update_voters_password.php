<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired. Please login again.'
    ]);
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
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit();
}

// Simple activity logger
function logActivity(PDO $pdo, int $userId, string $action): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
        ]);
    } catch (PDOException $e) {
        // Huwag ipakita sa user; log lang sa server
        error_log('Activity log insert failed: ' . $e->getMessage());
    }
}

// Get JSON data from request
 $data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['new_password']) || empty($data['new_password'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password is required'
    ]);
    exit();
}

 $newPassword = $data['new_password'];
 $userId = $_SESSION['user_id'];

// Check password requirements
 $length = strlen($newPassword) >= 8;
 $uppercase = preg_match('/[A-Z]/', $newPassword);
 $number = preg_match('/[0-9]/', $newPassword);
 $special = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword);

// Calculate strength (0-4)
 $strength = 0;
if ($length) $strength++;
if ($uppercase) $strength++;
if ($number) $strength++;
if ($special) $strength++;

// Check minimum requirements
if (!$length) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password must be at least 8 characters long.'
    ]);
    exit();
}

if ($strength < 3) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password is not strong enough. Please include at least 2 of the following: uppercase letter, number, special character.'
    ]);
    exit();
}

// Hash the new password
 $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the password in the database
try {
    $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE user_id = ?");
    $stmt->execute([$hashedPassword, $userId]);
    
    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        logActivity($pdo, (int)$userId, 'Changed password while logged in');

        echo json_encode([
            'status' => 'success',
            'message' => 'Password updated successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update password. Please try again.'
        ]);
    }
} catch (\PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>