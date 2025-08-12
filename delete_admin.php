<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit;
}

// Validate POST request
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

    // Check if the user is an admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        header("Location: manage_admins.php?error=Invalid admin.");
        exit;
    }

    // Delete the admin
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    header("Location: manage_admins.php?success=Admin deleted.");
    exit;

} catch (PDOException $e) {
    error_log("Admin deletion error: " . $e->getMessage());
    header("Location: manage_admins.php?error=Failed to delete admin.");
    exit;
}
