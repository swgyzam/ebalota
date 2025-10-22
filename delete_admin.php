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

    // Start transaction to ensure all operations succeed or none do
    $pdo->beginTransaction();

    // 1. Handle activity logs - either delete or reassign to current user
    $stmt = $pdo->prepare("UPDATE activity_logs SET user_id = ? WHERE user_id = ?");
    $stmt->execute([$current_user_id, $user_id]);
    
    // 2. Handle positions created by this admin
    $stmt = $pdo->prepare("UPDATE positions SET created_by = ? WHERE created_by = ?");
    $stmt->execute([$current_user_id, $user_id]);
    
    // 3. Handle any elections created by this admin (if applicable)
    $stmt = $pdo->prepare("UPDATE elections SET created_by = ? WHERE created_by = ?");
    $stmt->execute([$current_user_id, $user_id]);
    
    // 4. Handle any votes cast by this admin (if applicable)
    $stmt = $pdo->prepare("DELETE FROM votes WHERE voter_id = ?");
    $stmt->execute([$user_id]);
    
    // 5. Handle any candidate records associated with this admin
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 6. Handle any election_candidates associations
    $stmt = $pdo->prepare("DELETE FROM election_candidates WHERE candidate_id IN (SELECT id FROM candidates WHERE user_id = ?)");
    $stmt->execute([$user_id]);
    
    // Now delete the admin
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Commit the transaction
    $pdo->commit();

    header("Location: manage_admins.php?success=Admin deleted successfully.");
    exit;

} catch (PDOException $e) {
    // Roll back the transaction in case of error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Admin deletion error: " . $e->getMessage());
    header("Location: manage_admins.php?error=Failed to delete admin. " . $e->getMessage());
    exit;
}
?>