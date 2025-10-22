<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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
} catch (\PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header('Location: login.php');
    exit();
}

// Check if user_id is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    $_SESSION['message'] = "Invalid user ID.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

$user_id = $_GET['user_id'];
$currentRole = $_SESSION['role'];
$assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// Verify the admin has permission to delete this user
// First, get the user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id AND role = 'voter'");
$stmt->execute([':user_id' => $user_id]);
$userToDelete = $stmt->fetch();

if (!$userToDelete) {
    $_SESSION['message'] = "User not found or cannot be deleted.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

// Check if the current admin has permission to delete this user based on their scope
$hasPermission = false;

// Super admin can delete any user
if ($currentRole === 'super_admin') {
    $hasPermission = true;
}
// College Admin can only delete students from their college
else if (in_array($assignedScope, ['CEIT', 'CAS', 'CEMDS', 'CCJ', 'CAFENR', 'CON', 'COED', 'CVM', 'GRADUATE SCHOOL'])) {
    if ($userToDelete['position'] === 'student' && 
        strtoupper(trim($userToDelete['department'])) === $assignedScope) {
        $hasPermission = true;
    }
}
// Faculty Association Admin can only delete academic faculty
else if ($assignedScope === 'FACULTY ASSOCIATION') {
    if ($userToDelete['position'] === 'academic') {
        $hasPermission = true;
    }
}
// Non-Academic Admin can only delete non-academic staff
else if ($assignedScope === 'NON-ACADEMIC') {
    if ($userToDelete['position'] === 'non-academic') {
        $hasPermission = true;
    }
}
// COOP Admin can only delete COOP members
else if ($assignedScope === 'COOP') {
    if ($userToDelete['is_coop_member'] == 1) {
        $hasPermission = true;
    }
}
// CSG Admin can only delete students
else if ($assignedScope === 'CSG ADMIN') {
    if ($userToDelete['position'] === 'student') {
        $hasPermission = true;
    }
}

if (!$hasPermission) {
    $_SESSION['message'] = "You don't have permission to delete this user.";
    $_SESSION['message_type'] = "error";
    header('Location: admin_manage_users.php');
    exit();
}

// Log the deletion action
$adminId = $_SESSION['user_id'];
$action = "Deleted user: " . $userToDelete['first_name'] . " " . $userToDelete['last_name'] . " (ID: " . $userToDelete['user_id'] . ")";
$logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, timestamp) VALUES (:user_id, :action, NOW())");
$logStmt->execute([':user_id' => $adminId, ':action' => $action]);

// Delete the user
try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete related records first (if any)
    // For example, delete votes cast by this user
    $stmt = $pdo->prepare("DELETE FROM votes WHERE voter_id = :voter_id");
    $stmt->execute([':voter_id' => $user_id]);
    
    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['message'] = "User deleted successfully.";
    $_SESSION['message_type'] = "success";
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['message'] = "Error deleting user: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Redirect back to the manage users page
header('Location: admin_manage_users.php');
exit();
?>