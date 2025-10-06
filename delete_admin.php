<?php
session_start();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['user_id'])) {
    header("Location: manage_admins.php?error=Invalid request.");
    exit;
}

$user_id = intval($_POST['user_id']);
$current_user_id = $_SESSION['user_id'];

if ($user_id === $current_user_id) {
    header("Location: manage_admins.php?error=You cannot delete your own account.");
    exit;
}

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

    // Verify user is an admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        header("Location: manage_admins.php?error=Invalid admin.");
        exit;
    }

    // Check for dependent records in positions table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE created_by = ?");
    $stmt->execute([$user_id]);
    $dependentCount = $stmt->fetchColumn();

    if ($dependentCount > 0) {
        // Option 1: Reassign to another admin (e.g., current user)
        $updateStmt = $pdo->prepare("UPDATE positions SET created_by = ? WHERE created_by = ?");
        $updateStmt->execute([$current_user_id, $user_id]);

        // Option 2: Delete dependent records (use with caution)
        // $deleteStmt = $pdo->prepare("DELETE FROM positions WHERE created_by = ?");
        // $deleteStmt->execute([$user_id]);
    }

    // Now delete the admin
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    header("Location: manage_admins.php?success=Admin deleted.");
    exit;

} catch (PDOException $e) {
    error_log("Admin deletion error: " . $e->getMessage());
    header("Location: manage_admins.php?error=Failed to delete admin. " . $e->getMessage());
    exit;
}