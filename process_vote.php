<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to vote.'
    ]);
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
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit();
}

// Get JSON data from request
 $data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['election_id']) || !isset($data['votes']) || empty($data['votes'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid vote data'
    ]);
    exit();
}

 $election_id = (int)$data['election_id'];
 $voter_id = $_SESSION['user_id'];
 $votes = $data['votes'];

// Debug: Log the current session and request data
error_log("Session voter_id: " . $_SESSION['user_id']);
error_log("Request voter_id: $voter_id");
error_log("Election ID: $election_id");
error_log("Votes data: " . json_encode($votes));

// Verify that the voter exists in the database and is active
 $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_active = 1");
 $stmt->execute([$voter_id]);
 $voterData = $stmt->fetch();

if (!$voterData) {
    error_log("Voter with ID $voter_id does not exist or is not active");
    echo json_encode([
        'status' => 'error',
        'message' => 'Your account does not exist or is not active. Please contact the administrator.'
    ]);
    exit();
}

// Verify election exists and is active
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
 $stmt->execute([$election_id]);
 $election = $stmt->fetch();

if (!$election) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Election not found'
    ]);
    exit();
}

// Check if election is ongoing
 $now = date('Y-m-d H:i:s');
 $start = $election['start_datetime'];
 $end = $election['end_datetime'];

if ($now < $start) {
    echo json_encode([
        'status' => 'error',
        'message' => 'This election has not started yet'
    ]);
    exit();
} elseif ($now > $end) {
    echo json_encode([
        'status' => 'error',
        'message' => 'This election has already ended'
    ]);
    exit();
}

// If this is a COOP election, verify MIGS status
if ($election['target_position'] === 'coop') {
    $stmt = $pdo->prepare("SELECT migs_status FROM users WHERE user_id = ?");
    $stmt->execute([$voter_id]);
    $voterData = $stmt->fetch();
    
    if (($voterData['migs_status'] ?? 0) != 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Only COOP members with MIGS status can vote in this election'
        ]);
        exit();
    }
}

// Get position types for all positions in the vote
 $positionKeys = array_keys($votes);
 $positionTypes = [];

foreach ($positionKeys as $positionKey) {
    // Determine if this is a position_id or position_name
    $isPositionId = is_numeric($positionKey) && $positionKey > 0;
    
    if ($isPositionId) {
        $stmt = $pdo->prepare("
            SELECT allow_multiple, max_votes 
            FROM position_types 
            WHERE position_id = ? AND position_name = ''
        ");
        $stmt->execute([$positionKey]);
    } else {
        $stmt = $pdo->prepare("
            SELECT allow_multiple, max_votes 
            FROM position_types 
            WHERE position_id = 0 AND position_name = ?
        ");
        $stmt->execute([$positionKey]);
    }
    $positionType = $stmt->fetch();
    
    if (!$positionType) {
        // Default to single vote if not specified
        $positionType = [
            'allow_multiple' => false,
            'max_votes' => 1
        ];
    }
    
    $positionTypes[$positionKey] = [
        'allow_multiple' => (bool)$positionType['allow_multiple'],
        'max_votes' => (int)$positionType['max_votes'],
        'is_position_id' => $isPositionId
    ];
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Process each position
    foreach ($votes as $position_key => $candidateSelections) {
        // Get position type info
        $positionType = $positionTypes[$position_key];
        
        // Ensure candidateSelections is an array for consistency
        if (!is_array($candidateSelections)) {
            $candidateSelections = [$candidateSelections];
        }
        
        // Count existing votes for this position
        if ($positionType['is_position_id']) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM votes 
                WHERE election_id = ? AND voter_id = ? AND position_id = ?
            ");
            $stmt->execute([$election_id, $voter_id, $position_key]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM votes 
                WHERE election_id = ? AND voter_id = ? AND position_name = ?
            ");
            $stmt->execute([$election_id, $voter_id, $position_key]);
        }
        $existingVotes = $stmt->fetchColumn();
        
        // Check vote limit
        $newVotes = count($candidateSelections);
        if ($existingVotes + $newVotes > $positionType['max_votes']) {
            $pdo->rollBack();
            echo json_encode([
                'status' => 'error',
                'message' => "You can only vote for up to {$positionType['max_votes']} candidates in this position"
            ]);
            exit();
        }
        
        // Now process each candidate
        foreach ($candidateSelections as $candidate_id) {
            // Validate candidate
            if (!validateCandidate($pdo, $election_id, $position_key, $candidate_id, $positionType['is_position_id'])) {
                $pdo->rollBack();
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid candidate selection'
                ]);
                exit();
            }
            
            // Insert vote
            try {
                if ($positionType['is_position_id']) {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (election_id, candidate_id, voter_id, position_id, vote_type)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$election_id, $candidate_id, $voter_id, $position_key, $positionType['allow_multiple'] ? 'multi' : 'single']);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (election_id, candidate_id, voter_id, position_name, vote_type)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$election_id, $candidate_id, $voter_id, $position_key, $positionType['allow_multiple'] ? 'multi' : 'single']);
                }
                
                // Debug: Log successful vote insertion
                error_log("Successfully recorded vote for voter_id: $voter_id, candidate_id: $candidate_id, position: $position_key");
            } catch (\PDOException $e) {
                // Check if it's a duplicate entry error
                if ($e->errorInfo[1] == 1062) {
                    $pdo->rollBack();
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'You have already voted for this candidate'
                    ]);
                    exit();
                } else {
                    throw $e;
                }
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Debug: Log successful vote submission
    error_log("Successfully committed all votes for voter_id: $voter_id");
    
    // Log the activity
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, activity_type, activity_details, ip_address, created_at)
            VALUES (?, 'vote', ?, ?, ?, NOW())
        ");
        $activityDetails = json_encode([
            'election_id' => $election_id,
            'election_title' => $election['title']
        ]);
        $stmt->execute([$voter_id, 'cast_vote', $activityDetails, $_SERVER['REMOTE_ADDR']]);
        error_log("Activity logged successfully");
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Your vote has been recorded successfully.'
    ]);
    
} catch (\PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error recording your vote: ' . $e->getMessage()
    ]);
}

// Helper function to validate candidate
function validateCandidate($pdo, $election_id, $position_key, $candidate_id, $isPositionId) {
    if ($isPositionId) {
        $stmt = $pdo->prepare("
            SELECT ec.id 
            FROM election_candidates ec 
            WHERE ec.election_id = ? 
            AND ec.candidate_id = ? 
            AND ec.position_id = ?
        ");
        $stmt->execute([$election_id, $candidate_id, $position_key]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ec.id 
            FROM election_candidates ec 
            WHERE ec.election_id = ? 
            AND ec.candidate_id = ? 
            AND ec.position = ?
        ");
        $stmt->execute([$election_id, $candidate_id, $position_key]);
    }
    return $stmt->fetch() !== false;
}
?>