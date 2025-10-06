<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_manage_users.php');
    exit();
}

// Get form data
 $userId = (int)$_POST['user_id'];
 $firstName = trim($_POST['first_name'] ?? '');
 $lastName = trim($_POST['last_name'] ?? '');
 $email = trim($_POST['email'] ?? '');
 $position = trim($_POST['position'] ?? '');
 $department = trim($_POST['department'] ?? '');
 $course = trim($_POST['course'] ?? '');

// Validate required fields
if (empty($userId) || empty($firstName) || empty($lastName) || empty($email)) {
    $_SESSION['message'] = 'Please fill in all required fields.';
    $_SESSION['message_type'] = 'error';
    header('Location: admin_manage_users.php');
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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $_SESSION['message'] = 'Database connection failed: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: admin_manage_users.php');
    exit();
}

try {
    // Update user data
    $stmt = $pdo->prepare("UPDATE users SET 
                          first_name = ?, 
                          last_name = ?, 
                          email = ?, 
                          position = ?, 
                          department = ?, 
                          course = ? 
                          WHERE user_id = ?");
    $stmt->execute([$firstName, $lastName, $email, $position, $department, $course, $userId]);
    
    $_SESSION['message'] = 'User updated successfully.';
    $_SESSION['message_type'] = 'success';
    header('Location: admin_manage_users.php');
    exit();
    
} catch (\PDOException $e) {
    $_SESSION['message'] = 'Error updating user: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header('Location: admin_manage_users.php');
    exit();
}
?>