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

    // Start transaction
    $pdo->beginTransaction();

    // Temporarily disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Handle activity logs - reassign to current user
    $stmt = $pdo->prepare("UPDATE activity_logs SET user_id = ? WHERE user_id = ?");
    $stmt->execute([$current_user_id, $user_id]);
    
    // 2. Handle positions created by this admin
    $stmt = $pdo->prepare("UPDATE positions SET created_by = ? WHERE created_by = ?");
    $stmt->execute([$current_user_id, $user_id]);
    
    // 3. Handle elections created by this admin
    $stmt = $pdo->prepare("UPDATE elections SET created_by = ? WHERE created_by = ?");
    $stmt->execute([$current_user_id, $user_id]);
    
    // 4. Handle votes cast by this admin
    $stmt = $pdo->prepare("DELETE FROM votes WHERE voter_id = ?");
    $stmt->execute([$user_id]);
    
    // 5. Get all candidate IDs for this user
    $stmt = $pdo->prepare("SELECT id FROM candidates WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $candidate_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($candidate_ids)) {
        // 6. Handle election_candidates associations
        $placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM election_candidates WHERE candidate_id IN ($placeholders)");
        $stmt->execute($candidate_ids);
        
        // 7. Delete candidate records
        $stmt = $pdo->prepare("DELETE FROM candidates WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    // 8. Handle any other tables that might reference this user
    // Add additional table handling here if needed
    
    // Check for any other tables that might reference this user
    $tables = [
        'user_sessions', 
        'user_permissions', 
        'admin_scopes', 
        'admin_roles',
        'audit_logs',
        'notifications',
        'user_settings'
    ];
    
    foreach ($tables as $table) {
        try {
            // Check if table exists
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
            $stmt->execute([$db, $table]);
            if ($stmt->fetchColumn()) {
                // Try to delete records from this table
                $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
        } catch (PDOException $e) {
            // Table might not exist or no user_id column, continue to next table
            error_log("Error handling table $table: " . $e->getMessage());
        }
    }
    
    // 9. Finally, delete the admin
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Commit the transaction
    $pdo->commit();

    header("Location: manage_admins.php?success=Admin deleted successfully.");
    exit;

} catch (PDOException $e) {
    // Roll back the transaction in case of error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Make sure foreign key checks are re-enabled
    if (isset($pdo)) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    error_log("Admin deletion error: " . $e->getMessage());
    header("Location: manage_admins.php?error=Failed to delete admin. " . $e->getMessage());
    exit;
}
?>