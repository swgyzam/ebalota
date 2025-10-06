<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Auth check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header('Location: login.php');
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
    die("Database connection failed: " . $e->getMessage());
}

// Check if pending_id is provided
if (!isset($_GET['pending_id'])) {
    header('Location: manage_restricted_users.php');
    exit();
}

$pending_id = $_GET['pending_id'];

// Delete the restricted user
$stmt = $pdo->prepare("DELETE FROM pending_users WHERE pending_id = ? AND source = 'csv'");
$stmt->execute([$pending_id]);

// Redirect back to manage restricted users page
header('Location: admin_restrict_users.php');
exit();
?>