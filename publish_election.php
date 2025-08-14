<?php
session_start();
date_default_timezone_set('Asia/Manila');

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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['election_id'])) {
    $electionId = $_POST['election_id'];
    
    try {
        // 1. Get election details
        $stmt = $pdo->prepare("SELECT target_position, allowed_colleges, title, start_datetime, end_datetime FROM elections WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception("Election not found");
        }

        // 2. Update election status - using your actual enum values
        $updateStmt = $pdo->prepare("UPDATE elections 
                                    SET creation_stage = 'ready_for_voters',  // Using your existing enum value
                                        status = CASE 
                                            WHEN start_datetime > NOW() THEN 'upcoming'
                                            WHEN end_datetime < NOW() THEN 'completed'
                                            ELSE 'ongoing'
                                        END
                                    WHERE election_id = ?");
        
        if (!$updateStmt->execute([$electionId])) {
            throw new Exception("Failed to update election status");
        }

        // 3. Find and assign admin
        $scopeType = $election['target_position'];
        $scopeValue = ($scopeType === 'student' || $scopeType === 'faculty') 
                    ? $election['allowed_colleges'] 
                    : NULL;

        $adminStmt = $pdo->prepare("SELECT user_id FROM admin_scopes 
                                   WHERE scope_type = ? 
                                   AND (scope_value = ? OR scope_value IS NULL)
                                   LIMIT 1");
        $adminStmt->execute([$scopeType, $scopeValue]);
        $adminId = $adminStmt->fetchColumn();

        if ($adminId) {
            $pdo->prepare("UPDATE elections SET assigned_admin_id = ? WHERE election_id = ?")
               ->execute([$adminId, $electionId]);
        }

        header("Location: manage_elections.php?msg=Election+published+to+admins");
        exit();
        
    } catch (PDOException $e) {
        error_log("Publish Error: " . $e->getMessage());
        header("Location: manage_elections.php?error=Publish+failed&election_id=".$electionId);
        exit();
    }
}

header("Location: manage_elections.php");
exit();
?>