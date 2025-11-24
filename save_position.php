<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get position data from POST
$positionName   = trim($_POST['position_name'] ?? '');
$allowMultiple  = isset($_POST['allow_multiple']) ? (int)$_POST['allow_multiple'] : 0;
$maxVotesRaw    = $_POST['max_votes'] ?? '1';
$maxVotes       = (int)$maxVotesRaw;

// Basic validation
if (empty($positionName)) {
    echo json_encode(['success' => false, 'message' => 'Position name is required']);
    exit();
}

// Normalise allow_multiple to 0 or 1
$allowMultiple = $allowMultiple === 1 ? 1 : 0;

// Normalise maxVotes
if ($allowMultiple === 0) {
    // Single-select → force 1
    $maxVotes = 1;
} else {
    // Multi-select: must be at least 1 (you can change to 2 if gusto mo)
    if ($maxVotes < 1) {
        echo json_encode(['success' => false, 'message' => 'Max votes must be at least 1.']);
        exit();
    }
}

try {
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
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if position already exists for this admin
    $stmt = $pdo->prepare("SELECT id FROM positions WHERE position_name = :positionName AND created_by = :userId");
    $stmt->execute([':positionName' => $positionName, ':userId' => $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Position already exists']);
        exit();
    }
    
    // Use a transaction so positions + position_types stay in sync
    $pdo->beginTransaction();

    // 1) Insert new position (per admin)
    $stmt = $pdo->prepare("
        INSERT INTO positions (position_name, created_by)
        VALUES (:positionName, :userId)
    ");
    $stmt->execute([
        ':positionName' => $positionName,
        ':userId'       => $_SESSION['user_id']
    ]);
    
    $positionId = (int)$pdo->lastInsertId();

    // 2) Insert into position_types
    // For custom positions:
    //   position_id   = positions.id
    //   position_name = ''   (ID-based; default positions use position_id = 0 + name)
    $stmt = $pdo->prepare("
        INSERT INTO position_types (position_id, position_name, allow_multiple, max_votes)
        VALUES (:positionId, '', :allowMultiple, :maxVotes)
    ");
    $stmt->execute([
        ':positionId'    => $positionId,
        ':allowMultiple' => $allowMultiple,
        ':maxVotes'      => $maxVotes
    ]);

    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success'     => true, 
        'position_id' => $positionId,
        'message'     => 'Position added successfully'
    ]);

    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>