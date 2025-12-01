<?php
session_start();
header('Content-Type: application/json');

// Set timezone explicitly
date_default_timezone_set('Asia/Manila');

// Debug timezone
error_log("Current timezone: " . date_default_timezone_get());
error_log("Server timezone: " . ini_get('date.timezone'));

// Check if user is logged in and voter
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'You must be logged in to vote.'
    ]);
    exit();
}

// Database connection
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("DB connect error: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed'
    ]);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Basic validation: election_id + votes key must exist
if (!isset($data['election_id']) || !isset($data['votes'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid vote data'
    ]);
    exit();
}

$election_id = (int)$data['election_id'];
$voter_id    = (int)$_SESSION['user_id'];
$votes       = $data['votes'];

// Ensure votes is array (may be empty for full abstain)
if (!is_array($votes)) {
    $votes = [];
}

// Debug: log incoming data
error_log("Session voter_id: " . $_SESSION['user_id']);
error_log("Request voter_id: $voter_id");
error_log("Election ID: $election_id");
error_log("Votes data: " . json_encode($votes));

// Verify that the voter exists and is active
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_active = 1");
$stmt->execute([$voter_id]);
$voterData = $stmt->fetch();

if (!$voterData) {
    error_log("Voter with ID $voter_id does not exist or is not active");
    echo json_encode([
        'status'  => 'error',
        'message' => 'Your account does not exist or is not active. Please contact the administrator.'
    ]);
    exit();
}

// Verify election exists
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if (!$election) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Election not found'
    ]);
    exit();
}

// Check election time window using SQL NOW()
$stmt = $pdo->prepare("
    SELECT NOW() as now,
           start_datetime,
           end_datetime,
           (NOW() < start_datetime) AS not_started,
           (NOW() > end_datetime)   AS ended
    FROM elections
    WHERE election_id = ?
");
$stmt->execute([$election_id]);
$electionStatus = $stmt->fetch();

// Debug: SQL time comparison
error_log("=== SQL DATETIME DEBUG ===");
error_log("Current time (SQL): " . $electionStatus['now']);
error_log("Start time (SQL): " . $electionStatus['start_datetime']);
error_log("End  time (SQL): " . $electionStatus['end_datetime']);
error_log("Not started: " . ($electionStatus['not_started'] ? 'TRUE' : 'FALSE'));
error_log("Ended: "       . ($electionStatus['ended']      ? 'TRUE' : 'FALSE'));
error_log("=== END SQL DATETIME DEBUG ===");

if ($electionStatus['not_started']) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'This election has not started yet',
    ]);
    exit();
}
if ($electionStatus['ended']) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'This election has already ended',
    ]);
    exit();
}

// Backend guard: block double-voting per election
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM votes WHERE election_id = ? AND voter_id = ?");
$stmt->execute([$election_id, $voter_id]);
$alreadyVotedCount = (int)($stmt->fetch()['cnt'] ?? 0);

if ($alreadyVotedCount > 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'You have already voted in this election.'
    ]);
    exit();
}

// COOP election MIGS check
if ($election['target_position'] === 'coop') {
    $stmt = $pdo->prepare("SELECT migs_status FROM users WHERE user_id = ?");
    $stmt->execute([$voter_id]);
    $voterData = $stmt->fetch();
    
    if (($voterData['migs_status'] ?? 0) != 1) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Only COOP members with MIGS status can vote in this election.'
        ]);
        exit();
    }
}

// Get all positions in this election (for per-position abstain)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(ec.position_id, 0) AS position_id,
        COALESCE(p.position_name, ec.position) AS position_name
    FROM election_candidates ec
    LEFT JOIN positions p ON ec.position_id = p.id
    WHERE ec.election_id = ?
    GROUP BY COALESCE(ec.position_id, 0), COALESCE(p.position_name, ec.position)
");
$stmt->execute([$election_id]);
$allPositions = $stmt->fetchAll();

// Build a normalized list of position keys
$allPositionEntries = []; // [ ['key' => '...', 'position_id' => ..., 'position_name' => ..., 'is_position_id' => bool], ... ]
foreach ($allPositions as $pos) {
    $pid   = (int)$pos['position_id']; // 0 if default position (no ID)
    $pname = $pos['position_name'];

    if ($pid > 0) {
        $key = (string)$pid;
        $allPositionEntries[] = [
            'key'           => $key,
            'position_id'   => $pid,
            'position_name' => $pname,
            'is_position_id'=> true,
        ];
    } else {
        // default position keyed by name
        $key = $pname;
        $allPositionEntries[] = [
            'key'           => $key,
            'position_id'   => 0,
            'position_name' => $pname,
            'is_position_id'=> false,
        ];
    }
}

// Build position types only for positions that actually have selections
$positionKeys  = array_keys($votes);
$positionTypes = [];

if (!empty($positionKeys)) {
    foreach ($positionKeys as $positionKey) {
        $isPositionId = is_numeric($positionKey) && (int)$positionKey > 0;
        
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
                'allow_multiple' => 0,
                'max_votes'      => 1,
            ];
        }
        
        $positionTypes[$positionKey] = [
            'allow_multiple' => (bool)$positionType['allow_multiple'],
            'max_votes'      => (int)$positionType['max_votes'],
            'is_position_id' => $isPositionId,
        ];
    }
}

try {
    $pdo->beginTransaction();

    // Track which positions actually received candidate selections
    $positionsWithSelections = [];

    // 1) Insert normal candidate votes
    foreach ($votes as $position_key => $candidateSelections) {
        $positionsWithSelections[] = $position_key;

        // Ensure position type info exists (fallback single)
        $positionType = $positionTypes[$position_key] ?? [
            'allow_multiple' => false,
            'max_votes'      => 1,
            'is_position_id' => is_numeric($position_key) && (int)$position_key > 0,
        ];

        if (!is_array($candidateSelections)) {
            $candidateSelections = [$candidateSelections];
        }

        // Enforce max_votes (no existing votes due to election-level check)
        $newVotes = count($candidateSelections);
        if ($newVotes > $positionType['max_votes']) {
            $pdo->rollBack();
            echo json_encode([
                'status'  => 'error',
                'message' => "You can only vote for up to {$positionType['max_votes']} candidates in this position."
            ]);
            exit();
        }

        // Insert each selected candidate
        foreach ($candidateSelections as $candidate_id) {
            // Validate candidate belongs to this election + position
            if (!validateCandidate($pdo, $election_id, $position_key, $candidate_id, $positionType['is_position_id'])) {
                $pdo->rollBack();
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Invalid candidate selection.'
                ]);
                exit();
            }

            // Insert vote (is_abstain = 0 by default)
            try {
                if ($positionType['is_position_id']) {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (election_id, candidate_id, voter_id, position_id, vote_type, is_abstain)
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([
                        $election_id,
                        (int)$candidate_id,
                        $voter_id,
                        (int)$position_key,
                        $positionType['allow_multiple'] ? 'multi' : 'single',
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (election_id, candidate_id, voter_id, position_name, vote_type, is_abstain)
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([
                        $election_id,
                        (int)$candidate_id,
                        $voter_id,
                        $position_key,
                        $positionType['allow_multiple'] ? 'multi' : 'single',
                    ]);
                }

                error_log("Recorded vote: voter_id=$voter_id, candidate_id=$candidate_id, position=$position_key");
            } catch (\PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $pdo->rollBack();
                    echo json_encode([
                        'status'  => 'error',
                        'message' => 'You have already voted for this candidate.'
                    ]);
                    exit();
                } else {
                    throw $e;
                }
            }
        }
    }

    // 2) Insert abstain rows for any positions with no candidate selected
    $selectedPositionKeySet = array_flip($positionsWithSelections);

    foreach ($allPositionEntries as $pos) {
        $key           = $pos['key'];
        $pid           = $pos['position_id'];
        $pname         = $pos['position_name'];
        $isPositionId  = $pos['is_position_id'];

        if (isset($selectedPositionKeySet[$key])) {
            // This position already has candidate votes
            continue;
        }

        // Insert abstain vote for this position (candidate_id = NULL)
        if ($isPositionId) {
            $stmt = $pdo->prepare("
                INSERT INTO votes (election_id, voter_id, position_id, position_name, vote_type, is_abstain)
                VALUES (?, ?, ?, ?, 'single', 1)
            ");
            $stmt->execute([
                $election_id,
                $voter_id,
                $pid,
                $pname,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO votes (election_id, voter_id, position_name, vote_type, is_abstain)
                VALUES (?, ?, ?, 'single', 1)
            ");
            $stmt->execute([
                $election_id,
                $voter_id,
                $pname,
            ]);
        }

        error_log("Recorded abstain: voter_id=$voter_id, position_key=$key");
    }

    $pdo->commit();
    error_log("Successfully committed all votes for voter_id: $voter_id");

    // Log the activity (abstain or normal both count as "vote" activity")
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");

        $actionText = 'Cast vote in election ID ' . $election_id;
        if (!empty($election['title'])) {
            $actionText .= ' (' . $election['title'] . ')';
        }

        $stmt->execute([
            ':uid'    => $voter_id,
            ':action' => $actionText,
        ]);
    } catch (\Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Your vote has been recorded successfully.'
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in process_vote: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error recording your vote: ' . $e->getMessage()
    ]);
}

// Helper function to validate candidate for this election/position
function validateCandidate(PDO $pdo, int $election_id, $position_key, int $candidate_id, bool $isPositionId): bool
{
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
