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
    header('Location: admin_manage_candidates.php?error=' . urlencode('Invalid candidate ID.'));
    exit();
}

$candidate_id = (int)$_GET['id'];
$userId       = (int)$_SESSION['user_id'];

// DB Connection
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';
$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // --- Fetch admin scope info for seat-aware operations ---
    $adminStmt = $pdo->prepare("
        SELECT role, assigned_scope, scope_category
        FROM users
        WHERE user_id = :uid
    ");
    $adminStmt->execute([':uid' => $userId]);
    $adminRow      = $adminStmt->fetch();

    $scopeCategory = $adminRow['scope_category'] ?? '';
    $assignedScope = $adminRow['assigned_scope'] ?? '';

    // Resolve this admin's scope seat (admin_scopes)
    $myScopeId = null;
    if (!empty($scopeCategory)) {
        $scopeStmt = $pdo->prepare("
            SELECT scope_id, scope_type, scope_details
            FROM admin_scopes
            WHERE user_id   = :uid
              AND scope_type = :stype
            LIMIT 1
        ");
        $scopeStmt->execute([
            ':uid'   => $userId,
            ':stype' => $scopeCategory,
        ]);
        $scopeRow = $scopeStmt->fetch();

        if ($scopeRow) {
            $myScopeId = (int)$scopeRow['scope_id'];
        }
    }
    
    // Verify the candidate exists and belongs to this admin
    $stmt = $pdo->prepare("
        SELECT id, first_name, middle_name, last_name, photo, credentials
        FROM candidates
        WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$candidate_id, $userId]);
    $candidate = $stmt->fetch();
    
    if (!$candidate) {
        header('Location: admin_manage_candidates.php?error=' . urlencode('Candidate not found or not owned by you.'));
        exit();
    }

    // 🔹 CHECK: does this candidate already have votes?
    $voteStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM votes WHERE candidate_id = ?");
    $voteStmt->execute([$candidate_id]);
    $voteCount = (int)($voteStmt->fetch()['cnt'] ?? 0);

    if ($voteCount > 0) {
        // Bawal i-delete kung may boto na
        $msg = 'Cannot delete candidate: this candidate already has ' . $voteCount . ' vote' . ($voteCount === 1 ? '' : 's') . '.';
        header('Location: admin_manage_candidates.php?error=' . urlencode($msg));
        exit();
    }
    
    // Verify that the candidate is only in elections that this admin is allowed to manage
    $electionCheckStmt = $pdo->prepare("
        SELECT e.election_id, e.title, e.assigned_admin_id
        FROM elections e
        INNER JOIN election_candidates ec ON e.election_id = ec.election_id
        WHERE ec.candidate_id = :candidateId
    ");
    $electionCheckStmt->execute([':candidateId' => $candidate_id]);
    $candidateElections = $electionCheckStmt->fetchAll();
    
    // Check each election to ensure admin has permission
    foreach ($candidateElections as $election) {
        $hasPermission = false;
        
        // Check if election is directly assigned to admin
        if (!empty($election['assigned_admin_id']) && (int)$election['assigned_admin_id'] === $userId) {
            $hasPermission = true;
        }
        
        // Check if election is owned by admin's scope (if admin has a scope)
        if (!$hasPermission && $myScopeId !== null && !empty($scopeCategory)) {
            $scopeCheckStmt = $pdo->prepare("
                SELECT COUNT(*) AS count 
                FROM elections 
                WHERE election_id = :electionId 
                  AND owner_scope_id = :scopeId 
                  AND election_scope_type = :scopeCategory
            ");
            $scopeCheckStmt->execute([
                ':electionId'     => $election['election_id'],
                ':scopeId'        => $myScopeId,
                ':scopeCategory'  => $scopeCategory
            ]);
            $result = $scopeCheckStmt->fetch();
            
            if (!empty($result['count']) && (int)$result['count'] > 0) {
                $hasPermission = true;
            }
        }
        
        // If admin doesn't have permission for this election, block deletion
        if (!$hasPermission) {
            header('Location: admin_manage_candidates.php?error=' . urlencode('Cannot delete candidate: candidate is in an election you are not authorized to manage.'));
            exit();
        }
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1) Delete from election_candidates table
    $stmt = $pdo->prepare("DELETE FROM election_candidates WHERE candidate_id = ?");
    $stmt->execute([$candidate_id]);
    
    // 2) Delete from candidates table
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    
    // Delete files if they exist
    if (!empty($candidate['photo']) && file_exists($candidate['photo'])) {
        @unlink($candidate['photo']);
    }
    
    if (!empty($candidate['credentials']) && file_exists($candidate['credentials'])) {
        @unlink($candidate['credentials']);
    }
    
    // Commit transaction
    $pdo->commit();

    // --- Activity Log: Candidate deleted ---
    try {
        $adminId = $userId;

        // Build full name
        $fullName = trim(
            ($candidate['first_name'] ?? '') . ' ' .
            (($candidate['middle_name'] ?? '') ?: '') . ' ' .
            ($candidate['last_name'] ?? '')
        );
        if ($fullName === '') {
            $fullName = 'Unnamed Candidate';
        }

        // Build election list (titles + IDs)
        $electionParts = [];
        foreach ($candidateElections as $el) {
            $title = $el['title'] ?? '';
            $id    = $el['election_id'] ?? '';
            if ($title !== '') {
                $electionParts[] = $title . ' (ID: ' . $id . ')';
            } else {
                $electionParts[] = 'ID: ' . $id;
            }
        }
        $electionInfo = '';
        if (!empty($electionParts)) {
            $electionInfo = ' from elections: ' . implode(', ', $electionParts);
        }

        $actionText = 'Deleted candidate: ' . $fullName .
                      ' (ID: ' . $candidate_id . ')' . $electionInfo;

        if ($adminId > 0) {
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, timestamp)
                VALUES (:uid, :action, NOW())
            ");
            $logStmt->execute([
                ':uid'    => $adminId,
                ':action' => $actionText,
            ]);
        }
    } catch (PDOException $logEx) {
        // Silent log error
        error_log('Activity log error (delete_candidate.php): ' . $logEx->getMessage());
    }
    
    // Redirect with success message
    header('Location: admin_manage_candidates.php?success=' . urlencode('Candidate deleted successfully.'));
    exit();
    
} catch (PDOException $e) {
    // Rollback in case of error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Redirect with error message
    header('Location: admin_manage_candidates.php?error=' . urlencode('Error deleting candidate: ' . $e->getMessage()));
    exit();
}
?>
