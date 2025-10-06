<?php
session_start();
require_once 'config.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Check if candidate ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_manage_candidates.php?error=Invalid candidate ID');
    exit();
}

$candidate_id = $_GET['id'];
$userId = $_SESSION['user_id'];

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
    
    // Verify the candidate exists and belongs to this admin
    $stmt = $pdo->prepare("SELECT id, photo, credentials FROM candidates WHERE id = ? AND created_by = ?");
    $stmt->execute([$candidate_id, $userId]);
    $candidate = $stmt->fetch();
    
    if (!$candidate) {
        header('Location: admin_manage_candidates.php?error=Candidate not found');
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete from election_candidates table
    $stmt = $pdo->prepare("DELETE FROM election_candidates WHERE candidate_id = ?");
    $stmt->execute([$candidate_id]);
    
    // Delete from candidates table
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    
    // Delete files if they exist
    if (!empty($candidate['photo']) && file_exists($candidate['photo'])) {
        unlink($candidate['photo']);
    }
    
    if (!empty($candidate['credentials']) && file_exists($candidate['credentials'])) {
        unlink($candidate['credentials']);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Redirect with success message
    header('Location: admin_manage_candidates.php?success=Candidate deleted successfully');
    exit();
    
} catch (PDOException $e) {
    // Rollback in case of error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Redirect with error message
    header('Location: admin_manage_candidates.php?error=Error deleting candidate: ' . urlencode($e->getMessage()));
    exit();
}
?>