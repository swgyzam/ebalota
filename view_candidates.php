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
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    header("Location: error.php?message=database_connection_failed");
    exit();
}

// Redirect if not logged in or not a voter
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: login.html");
    exit();
}

// Get election ID from URL
 $election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
if ($election_id <= 0) {
    header("Location: error.php?message=invalid_election_id");
    exit();
}

// Fetch election details
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
 $stmt->execute([$election_id]);
 $election = $stmt->fetch();
if (!$election) {
    header("Location: error.php?message=election_not_found");
    exit();
}

// Check if user has already voted in this election
 $stmt = $pdo->prepare("SELECT * FROM votes WHERE voter_id = ? AND election_id = ?");
 $stmt->execute([$_SESSION['user_id'], $election_id]);
if ($stmt->fetch()) {
    header("Location: voters_dashboard.php?message=already_voted");
    exit();
}

// FIXED: Use DateTime objects for proper comparison
 $now = new DateTime();
 $start = new DateTime($election['start_datetime']);
 $end = new DateTime($election['end_datetime']);

// DEBUG: Output datetime values
error_log("Current time: " . $now->format('Y-m-d H:i:s'));
error_log("Start time: " . $start->format('Y-m-d H:i:s'));
error_log("End time: " . $end->format('Y-m-d H:i:s'));
error_log("Now < Start: " . ($now < $start ? 'true' : 'false'));
error_log("Now > End: " . ($now > $end ? 'true' : 'false'));

if ($now < $start) {
    header("Location: error.php?message=election_not_started");
    exit();
} elseif ($now > $end) {
    header("Location: error.php?message=election_ended");
    exit();
}

// Check if this is a COOP election and verify MIGS status
if ($election['target_position'] === 'coop') {
    $stmt = $pdo->prepare("SELECT migs_status FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $voterData = $stmt->fetch();
    
    if (($voterData['migs_status'] ?? 0) != 1) {
        header("Location: error.php?message=not_migs_member");
        exit();
    }
}

// FIXED: Use DateTime objects for time remaining calculation
 $interval = $now->diff($end);

if ($interval->days > 0) {
    $timeLeft = $interval->days . " day" . ($interval->days > 1 ? "s" : "") . " left";
} elseif ($interval->h > 0) {
    $timeLeft = $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " left";
} elseif ($interval->i > 0) {
    $timeLeft = $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " left";
} else {
    $timeLeft = "Less than a minute left";
}

// Get candidates for this election through election_candidates table
try {
    $stmt = $pdo->prepare("
        SELECT 
            ec.id as ec_id,
            ec.election_id,
            ec.candidate_id,
            ec.position,
            ec.position_id,
            c.id,
            c.first_name,
            c.last_name,
            c.middle_name,
            c.photo,
            c.credentials,
            c.party_list,
            p.id as p_id,
            p.position_name
        FROM election_candidates ec
        JOIN candidates c ON ec.candidate_id = c.id
        LEFT JOIN positions p ON ec.position_id = p.id
        WHERE ec.election_id = ?
        ORDER BY p.position_name, ec.position, c.last_name, c.first_name
    ");
    $stmt->execute([$election_id]);
    $electionCandidates = $stmt->fetchAll();
    
    // Group candidates by position
    $candidatesByPosition = [];
    $positions = [];
    
    foreach ($electionCandidates as $ec) {
        // Determine position key and name
        if (!empty($ec['position_id']) && !empty($ec['position_name'])) {
            // Use position from positions table
            $positionKey = $ec['position_id'];
            $positionName = $ec['position_name'];
            $positionDescription = '';
        } else {
            // Use position from election_candidates table
            $positionKey = $ec['position'];
            $positionName = $ec['position'];
            $positionDescription = '';
        }
        
        // Add position to positions array if not already there
        if (!isset($positions[$positionKey])) {
            $positions[$positionKey] = [
                'position_id' => $positionKey,
                'position_name' => $positionName,
                'position_description' => $positionDescription
            ];
        }
        
        // Add candidate to position group
        if (!isset($candidatesByPosition[$positionKey])) {
            $candidatesByPosition[$positionKey] = [];
        }
        
        // Create full name
        $fullName = trim($ec['first_name'] . ' ' . $ec['middle_name'] . ' ' . $ec['last_name']);
        $fullName = preg_replace('/\s+/', ' ', $fullName); // Remove extra spaces
        
        // Determine partylist - use "IND" if empty
        $partylist = !empty($ec['party_list']) ? $ec['party_list'] : 'IND';
        
        // Add candidate details
        $candidatesByPosition[$positionKey][] = [
            'id' => $ec['id'],
            'candidate_id' => $ec['candidate_id'],
            'candidate_name' => $fullName,
            'photo_path' => $ec['photo'],
            'platform' => $ec['credentials'], // Using credentials as platform
            'partylist' => $partylist, // Use determined partylist
            'credentials_path' => $ec['credentials'] // Add credentials path
        ];
    }
    
    // Get position types for all positions
    $positionIds = array_keys($positions);
    $placeholders = implode(',', array_fill(0, count($positionIds), '?'));
    
    $stmt = $pdo->prepare("
        SELECT pt.position_id, pt.position_name, pt.allow_multiple, pt.max_votes
        FROM position_types pt
        WHERE (pt.position_id IN ($placeholders) AND pt.position_name = '')
           OR (pt.position_id = 0 AND pt.position_name IN ($placeholders))
    ");
    $stmt->execute(array_merge($positionIds, $positionIds));
    $positionTypes = [];
    
    while ($row = $stmt->fetch()) {
        if ($row['position_id'] > 0) {
            $positionTypes[$row['position_id']] = [
                'allow_multiple' => (bool)$row['allow_multiple'],
                'max_votes' => (int)$row['max_votes']
            ];
        } else {
            $positionTypes[$row['position_name']] = [
                'allow_multiple' => (bool)$row['allow_multiple'],
                'max_votes' => (int)$row['max_votes']
            ];
        }
    }
    
    // Add position type info to positions array
    foreach ($positions as $key => $position) {
        if (isset($positionTypes[$key])) {
            $positions[$key]['allow_multiple'] = $positionTypes[$key]['allow_multiple'];
            $positions[$key]['max_votes'] = $positionTypes[$key]['max_votes'];
        } else {
            // Default to single vote if not specified
            $positions[$key]['allow_multiple'] = false;
            $positions[$key]['max_votes'] = 1;
        }
    }
    
    // Reindex positions array
    $positions = array_values($positions);
    
} catch (\PDOException $e) {
    // If there's an error, set empty arrays
    $electionCandidates = [];
    $candidatesByPosition = [];
    $positions = [];
}

include 'voters_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eBalota - Cast Your Vote</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    
    .mobile-sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease-in-out;
    }
    
    .mobile-sidebar.open {
      transform: translateX(0);
    }
    
    .candidate-card {
      transition: all 0.3s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
      border: 2px solid transparent;
      cursor: pointer;
    }
    
    .candidate-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .candidate-card.selected {
      border-color: var(--cvsu-green);
      background-color: rgba(30, 111, 70, 0.05);
    }
    
    .candidate-card.selected .card-header {
      background: linear-gradient(to right, var(--cvsu-green-light), var(--cvsu-green));
    }
    
    .candidate-card.selected .candidate-name {
      color: var(--cvsu-green-dark);
    }
    
    .candidate-card.selected .partylist-badge {
      border: 2px solid var(--cvsu-green);
    }
    
    .candidate-card.selected .radio-container {
      background-color: rgba(30, 111, 70, 0.1);
      border-top-color: var(--cvsu-green);
    }
    
    .candidate-card input[type="radio"], 
    .candidate-card input[type="checkbox"] {
      margin: 0 auto;
      display: block;
      transform: scale(1.5); /* Make radio buttons larger for better visibility */
    }
    
    .logo-container {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 0.5rem;
    }
    
    .status-badge {
      display: inline-flex;
      items-center: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-weight: 600;
      font-size: 0.75rem;
    }
    
    .partylist-ind {
      background-color: #f3f4f6;
      color: #6b7280;
      font-style: italic;
    }
    
    .credential-btn {
      transition: all 0.2s ease;
    }
    
    .credential-btn:hover {
      transform: scale(1.03);
    }
    
    .candidate-photo {
      transition: all 0.3s ease;
    }
    
    .card-header {
      background-color: #f9fafb;
      transition: background 0.3s ease;
    }
    
    .candidate-name {
      transition: color 0.3s ease;
    }
    
    .partylist-badge {
      transition: all 0.3s ease;
    }
    
    .position-section {
      margin-bottom: 2rem;
    }
    
    .position-header {
      background-color: var(--cvsu-green);
      color: white;
      padding: 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
    }
    
    .error-message {
      background-color: #fee;
      color: #c33;
      padding: 0.75rem;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
    }
    
    .submit-btn {
      background: linear-gradient(to right, var(--cvsu-green), var(--cvsu-green-dark));
      transition: all 0.3s ease;
    }
    
    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    .vote-instruction {
      background-color: #f0f9ff;
      border-left: 4px solid #0ea5e9;
      padding: 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
    }
    
    .radio-container {
      display: flex;
      justify-content: center;
      padding: 10px 0;
      background-color: rgba(0,0,0,0.02);
      border-top: 1px solid rgba(0,0,0,0.05);
      transition: all 0.3s ease;
    }
    
    .notification-banner {
      transition: all 0.3s ease;
    }
    
    .selection-counter {
      text-align: center;
      margin-top: 0.5rem;
      font-weight: 600;
      color: var(--cvsu-green);
    }
    
    /* Modal Styles */
    .modal {
      transition: opacity 0.3s ease;
    }
    
    .modal-content {
      animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
      from {
        transform: translateY(-50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
    
    .modal-backdrop {
      backdrop-filter: blur(5px);
    }
    
    .position-item {
      transition: all 0.2s ease;
    }
    
    .position-item:hover {
      transform: translateX(5px);
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <div class="flex">
    <!-- Main Content -->
    <main class="flex-1 p-4 md:p-8 md:ml-64">
      <!-- Header -->
      <header class="bg-[var(--cvsu-green-dark)] text-white p-3 md:p-4 flex justify-between items-center shadow-lg rounded-xl mb-8">
        <div class="flex items-center">
          <button class="md:hidden text-white mr-4" onclick="toggleSidebar()">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <div>
            <h1 class="text-xl md:text-2xl font-bold">Cast Your Vote</h1>
            <p class="text-green-100 text-sm"><?= htmlspecialchars($election['title']) ?></p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <a href="voters_dashboard.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-1 px-3 rounded-lg flex items-center text-sm transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
          </a>
        </div>
      </header>
      
      <!-- COOP Election Notification -->
      <?php if ($election['target_position'] === 'coop'): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 notification-banner">
          <div class="flex items-center">
            <i class="fas fa-info-circle text-green-500 mr-2"></i>
            <p class="text-green-700">This is a COOP election. Only members with MIGS status can vote.</p>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- Time Remaining Notification -->
      <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 notification-banner">
        <div class="flex items-center">
          <i class="fas fa-clock text-yellow-500 mr-2"></i>
          <p class="text-yellow-700">Election ends in: <?= $timeLeft ?></p>
        </div>
      </div>
      
      <!-- Error Message -->
      <?php if (isset($error)): ?>
        <div class="error-message mb-6">
          <i class="fas fa-exclamation-circle mr-2"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      
      <!-- Voting Instructions -->
      <div class="vote-instruction mb-6">
        <div class="flex items-start">
          <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
          <div>
            <h3 class="font-semibold text-blue-800 mb-1">How to Vote</h3>
            <ol class="list-decimal list-inside text-blue-700 text-sm space-y-1">
              <li>Select one candidate for each position (or up to the allowed number for multi-candidate positions)</li>
              <li>Review your selections before submitting</li>
              <li>Click "Submit Your Vote" to finalize your vote</li>
            </ol>
          </div>
        </div>
      </div>
      
      <!-- Election Details -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8 border border-gray-100">
        <div class="p-6">
          <div class="flex flex-col md:flex-row gap-6">
            <!-- Logo Section -->
            <div class="logo-container md:w-1/4 flex justify-center items-center">
              <?php if (!empty($election['logo_path'])): ?>
                <div class="relative w-60 h-60 rounded-full overflow-hidden border-4 border-white shadow-xl">
                  <img src="<?= htmlspecialchars($election['logo_path']) ?>" alt="Election Logo" class="w-full h-full object-cover">
                </div>
              <?php else: ?>
                <div class="w-40 h-40 rounded-full bg-gradient-to-br from-[var(--cvsu-green-light)] to-[var(--cvsu-green-dark)] flex items-center justify-center shadow-xl">
                  <i class="fas fa-vote-yea text-4xl text-white"></i>
                </div>
              <?php endif; ?>
            </div>
            
            <!-- Details Section -->
            <div class="md:w-3/4">
              <h2 class="text-2xl font-bold text-[var(--cvsu-green-dark)] mb-3"><?= htmlspecialchars($election['title']) ?></h2>
              
              <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center">
                  <i class="fas fa-info-circle text-[var(--cvsu-green)] mr-1 text-xs"></i> Description
                </h3>
                <p class="text-gray-600 text-sm bg-gray-50 p-3 rounded-lg"><?= nl2br(htmlspecialchars($election['description'])) ?></p>
              </div>
              
              <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-3 rounded-lg border border-blue-100">
                  <h3 class="text-xs font-medium text-blue-700 uppercase tracking-wider flex items-center">
                    <i class="fas fa-calendar-alt text-blue-600 mr-1 text-xs"></i> Start
                  </h3>
                  <p class="text-sm font-medium text-blue-800"><?= date("M d, Y h:i A", strtotime($election['start_datetime'])) ?></p>
                </div>
                <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-3 rounded-lg border border-purple-100">
                  <h3 class="text-xs font-medium text-purple-700 uppercase tracking-wider flex items-center">
                    <i class="fas fa-calendar-check text-purple-600 mr-1 text-xs"></i> End
                  </h3>
                  <p class="text-sm font-medium text-purple-800"><?= date("M d, Y h:i A", strtotime($election['end_datetime'])) ?></p>
                </div>
              </div>
              
              <div class="flex flex-wrap gap-2 mt-3">
                <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                  <i class="fas fa-users mr-1 text-xs"></i> <?= htmlspecialchars($election['target_department']) ?>
                </span>
                <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                  <i class="fas fa-user-check mr-1 text-xs"></i> <?= htmlspecialchars($election['allowed_status']) ?>
                </span>
                <?php if ($election['target_position'] === 'coop'): ?>
                  <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                    <i class="fas fa-users-cog mr-1 text-xs"></i> COOP Election (MIGS Members Only)
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Voting Form -->
      <form method="post" id="votingForm">
        <!-- Positions and Candidates Section -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 mb-8">
          <div class="p-5 border-b bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] text-white">
            <h2 class="text-xl font-bold">Select Your Candidates</h2>
            <p class="text-green-100 text-sm">Please select candidates for each position</p>
          </div>
          
          <div class="p-5">
            <?php if (empty($positions)): ?>
              <div class="text-center py-8">
                <i class="fas fa-exclamation-circle text-4xl text-gray-400 mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-1">No Candidates Available</h3>
                <p class="text-gray-600">There are no candidates set up for this election yet.</p>
              </div>
            <?php else: ?>
              <?php foreach ($positions as $position): ?>
                <div class="position-section" data-position-id="<?= htmlspecialchars($position['position_id']) ?>" data-position-name="<?= htmlspecialchars($position['position_name']) ?>">
                  <div class="position-header">
                    <h3 class="text-lg font-bold"><?= htmlspecialchars($position['position_name']) ?></h3>
                    <p class="text-sm text-green-100">
                      <?php if ($position['allow_multiple']): ?>
                        Select up to <?= $position['max_votes'] ?> candidates
                      <?php else: ?>
                        Select one candidate
                      <?php endif; ?>
                    </p>
                  </div>
                  
                  <?php if (empty($candidatesByPosition[$position['position_id']])): ?>
                    <div class="text-center py-4">
                      <p class="text-gray-600">No candidates available for this position.</p>
                    </div>
                  <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" role="radiogroup" aria-labelledby="position-<?= htmlspecialchars($position['position_id']) ?>">
                      <h3 id="position-<?= htmlspecialchars($position['position_id']) ?>" class="sr-only"><?= htmlspecialchars($position['position_name']) ?></h3>
                      <?php foreach ($candidatesByPosition[$position['position_id']] as $candidate): ?>
                        <div class="candidate-card">
                          <label for="candidate_<?= htmlspecialchars($candidate['candidate_id']) ?>" class="card-content block">
                            <!-- Card Header with Photo -->
                            <div class="card-header relative p-5">
                              <!-- Candidate Photo -->
                              <div class="flex justify-center">
                                <?php if (!empty($candidate['photo_path'])): ?>
                                  <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-white shadow-lg candidate-photo">
                                    <img src="<?= htmlspecialchars($candidate['photo_path']) ?>" alt="Candidate Photo" class="w-full h-full object-cover">
                                  </div>
                                <?php else: ?>
                                  <div class="w-28 h-28 rounded-full bg-white flex items-center justify-center shadow-lg candidate-photo">
                                    <i class="fas fa-user text-5xl text-[var(--cvsu-green-dark)]"></i>
                                  </div>
                                <?php endif; ?>
                              </div>
                            </div>
                            
                            <!-- Card Body -->
                            <div class="p-5 flex flex-col flex-grow">
                              <div class="text-center mb-4">
                                <h4 class="candidate-name text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($candidate['candidate_name']) ?></h4>
                                <div class="partylist-badge inline-block px-3 py-1 bg-gray-100 rounded-full">
                                  <p class="text-sm text-gray-600 font-medium <?= $candidate['partylist'] === 'IND' ? 'partylist-ind' : '' ?>">
                                    Partylist: <?= htmlspecialchars($candidate['partylist']) ?>
                                  </p>
                                </div>
                              </div>
                              
                              <!-- Credentials Button -->
                              <div class="mt-auto">
                                <?php if (!empty($candidate['credentials_path'])): ?>
                                  <a href="<?= htmlspecialchars($candidate['credentials_path']) ?>" target="_blank" 
                                     class="credential-btn block w-full bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded-lg text-sm font-medium flex items-center justify-center">
                                    <i class="fas fa-file-pdf mr-2"></i> View Credentials
                                  </a>
                                <?php else: ?>
                                  <button disabled class="block w-full bg-gray-100 text-gray-500 py-2 px-4 rounded-lg text-sm font-medium cursor-not-allowed flex items-center justify-center">
                                    <i class="fas fa-file-pdf mr-2"></i> No Credentials
                                  </button>
                                <?php endif; ?>
                              </div>
                            </div>
                          </label>
                          
                          <!-- Radio/Checkbox Button at the Bottom -->
                          <div class="radio-container">
                            <?php if ($position['allow_multiple']): ?>
                              <input type="checkbox" 
                                     name="candidates[<?= htmlspecialchars($position['position_id']) ?>][]" 
                                     value="<?= htmlspecialchars($candidate['candidate_id']) ?>" 
                                     id="candidate_<?= htmlspecialchars($candidate['candidate_id']) ?>"
                                     data-position="<?= htmlspecialchars($position['position_id']) ?>"
                                     data-max-votes="<?= $position['max_votes'] ?>"
                                     aria-label="Vote for <?= htmlspecialchars($candidate['candidate_name']) ?>">
                            <?php else: ?>
                              <input type="radio" 
                                     name="candidates[<?= htmlspecialchars($position['position_id']) ?>]" 
                                     value="<?= htmlspecialchars($candidate['candidate_id']) ?>" 
                                     id="candidate_<?= htmlspecialchars($candidate['candidate_id']) ?>"
                                     required
                                     aria-label="Vote for <?= htmlspecialchars($candidate['candidate_name']) ?>">
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    
                    <!-- Selection counter for multi-candidate positions -->
                    <?php if ($position['allow_multiple']): ?>
                      <div class="selection-counter" id="counter-<?= htmlspecialchars($position['position_id']) ?>">
                        0 / <?= $position['max_votes'] ?> selected
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Submit Button -->
        <div class="flex justify-center mb-8">
          <button type="submit" class="submit-btn text-white py-3 px-8 rounded-lg text-lg font-bold flex items-center shadow-lg">
            <i class="fas fa-vote-yea mr-3"></i> Submit Your Vote
          </button>
        </div>
      </form>
      
      <!-- Skipped Positions Modal -->
      <div id="skippedPositionsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal">
        <div class="bg-white rounded-2xl p-8 max-w-lg w-full mx-4 shadow-2xl modal-content">
          <div class="text-center">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Incomplete Votes</h3>
            <p class="text-gray-600 mb-6">You haven't voted for the following positions. If this is intentional, please proceed.</p>
            
            <ul id="skippedPositionsList" class="mb-6 text-left max-h-60 overflow-y-auto">
              <!-- Skipped positions will be added here dynamically -->
            </ul>
            
            <div class="flex space-x-3">
              <button type="button" id="cancelFromSkipped" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
                Cancel
              </button>
              <button type="button" id="submitFromSkipped" class="flex-1 px-6 py-3 bg-yellow-600 text-white rounded-xl hover:bg-yellow-700 transition-all duration-200">
                Submit Vote
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Confirmation Modal -->
      <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden modal">
        <div class="bg-white rounded-2xl p-8 max-w-2xl w-full mx-4 shadow-2xl modal-content max-h-[80vh] overflow-y-auto">
          <div class="text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirm Your Vote</h3>
            <p class="text-gray-600 mb-6">Please review your selections before submitting. This action cannot be undone.</p>
            
            <ul id="confirmationList" class="mb-6 text-left max-h-60 overflow-y-auto">
              <!-- All positions will be added here dynamically -->
            </ul>
            
            <div class="flex space-x-3">
              <button type="button" id="cancelFromConfirmation" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
                Cancel
              </button>
              <button type="button" id="confirmFromConfirmation" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200">
                Confirm Vote
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <?php include 'footer.php'; ?>
    </main>
  </div>
  
  <script>
    // Toggle mobile sidebar
    function toggleSidebar() {
      const sidebar = document.getElementById('votersSidebar');
      sidebar.classList.toggle('open');
    }
    
    // Handle candidate selection for radio buttons
    document.querySelectorAll('.candidate-card input[type="radio"]').forEach(radio => {
      radio.addEventListener('change', function() {
        // Remove selected class from all cards in the same position
        const positionId = this.name.match(/\[(.*?)\]/)[1];
        document.querySelectorAll(`input[name="candidates[${positionId}]"]`).forEach(r => {
          r.closest('.candidate-card').classList.remove('selected');
        });
        
        // Add selected class to the chosen card
        if (this.checked) {
          this.closest('.candidate-card').classList.add('selected');
        }
      });
    });
    
    // Handle candidate selection for checkboxes
    document.querySelectorAll('.candidate-card input[type="checkbox"]').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const positionId = this.dataset.position;
        const maxVotes = parseInt(this.dataset.maxVotes);
        const checkboxes = document.querySelectorAll(`input[type="checkbox"][data-position="${positionId}"]`);
        const checkedCount = document.querySelectorAll(`input[type="checkbox"][data-position="${positionId}"]:checked`).length;
        
        // Update selection counter
        const counter = document.getElementById(`counter-${positionId}`);
        if (counter) {
          counter.textContent = `${checkedCount} / ${maxVotes} selected`;
        }
        
        // Prevent selecting more than allowed
        if (checkedCount > maxVotes) {
          this.checked = false;
          alert(`You can only select up to ${maxVotes} candidates for this position`);
          
          // Update counter again
          const newCheckedCount = document.querySelectorAll(`input[type="checkbox"][data-position="${positionId}"]:checked`).length;
          if (counter) {
            counter.textContent = `${newCheckedCount} / ${maxVotes} selected`;
          }
        }
        
        // Toggle selected class
        if (this.checked) {
          this.closest('.candidate-card').classList.add('selected');
        } else {
          this.closest('.candidate-card').classList.remove('selected');
        }
      });
    });
    
    // Function to check for skipped positions
    function checkSkippedPositions() {
      const allPositions = [];
      const votedPositions = [];
      const skippedPositions = [];
      
      // Get all positions from the DOM
      document.querySelectorAll('.position-section').forEach(section => {
        const positionHeader = section.querySelector('.position-header h3');
        const positionName = positionHeader.textContent.trim();
        const positionId = section.dataset.positionId;
        
        allPositions.push({
          id: positionId,
          name: positionName
        });
        
        // Check if position has any votes
        const hasVote = section.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked');
        if (hasVote) {
          votedPositions.push({
            id: positionId,
            name: positionName
          });
        } else {
          skippedPositions.push({
            id: positionId,
            name: positionName
          });
        }
      });
      
      return {
        all: allPositions,
        voted: votedPositions,
        skipped: skippedPositions
      };
    }
    
    // Function to show the skipped positions modal
    function showSkippedPositionsModal(skippedPositions) {
      const modal = document.getElementById('skippedPositionsModal');
      const skippedList = document.getElementById('skippedPositionsList');
      
      // Clear previous list
      skippedList.innerHTML = '';
      
      // Add skipped positions to list
      skippedPositions.forEach(position => {
        const li = document.createElement('li');
        li.className = 'py-2 px-4 bg-gray-50 rounded-lg mb-2 position-item';
        li.innerHTML = `
          <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-yellow-500 mr-3"></i>
            <span class="font-medium">${position.name}</span>
          </div>
        `;
        skippedList.appendChild(li);
      });
      
      // Show modal
      modal.classList.remove('hidden');
    }
    
    // Function to show the confirmation modal
    function showConfirmationModal(allPositions, votedPositions) {
      const modal = document.getElementById('confirmationModal');
      const confirmationList = document.getElementById('confirmationList');
      
      // Clear previous list
      confirmationList.innerHTML = '';
      
      // Add all positions to list
      allPositions.forEach(position => {
        const li = document.createElement('li');
        li.className = 'py-3 px-4 bg-gray-50 rounded-lg mb-3 position-item';
        
        const isVoted = votedPositions.some(vp => vp.id === position.id);
        if (isVoted) {
          // Get all selected candidates for this position (handle both radio and checkbox)
          const selectedCandidates = document.querySelectorAll(`input[name^="candidates[${position.id}]"]:checked`);
          
          let candidateNames = [];
          selectedCandidates.forEach(candidate => {
            const candidateCard = candidate.closest('.candidate-card');
            if (candidateCard) {
              const nameElement = candidateCard.querySelector('.candidate-name');
              if (nameElement) {
                candidateNames.push(nameElement.textContent.trim());
              }
            }
          });
          
          // If we have candidate names, display them
          if (candidateNames.length > 0) {
            // Join the names with commas
            const namesString = candidateNames.join(', ');
            li.innerHTML = `
              <div class="flex items-center justify-between">
                <div>
                  <span class="font-medium text-gray-800">${position.name}</span>
                  <div class="text-sm text-gray-600 mt-1">
                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                    ${namesString}
                  </div>
                </div>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                  Voted
                </span>
              </div>
            `;
          } else {
            // Fallback if no candidate names found
            li.innerHTML = `
              <div class="flex items-center justify-between">
                <div>
                  <span class="font-medium text-gray-800">${position.name}</span>
                  <div class="text-sm text-gray-600 mt-1">
                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                    Candidate selected
                  </div>
                </div>
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                  Voted
                </span>
              </div>
            `;
          }
        } else {
          li.innerHTML = `
            <div class="flex items-center justify-between">
              <div>
                <span class="font-medium text-gray-800">${position.name}</span>
                <div class="text-sm text-gray-600 mt-1">
                  <i class="fas fa-times-circle text-red-500 mr-1"></i>
                  No vote
                </div>
              </div>
              <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                Skipped
              </span>
            </div>
          `;
        }
        
        confirmationList.appendChild(li);
      });
      
      // Show modal
      modal.classList.remove('hidden');
    }
    
    // Update the form submission handler
    document.getElementById('votingForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Check for skipped positions
      const positions = checkSkippedPositions();
      
      if (positions.skipped.length > 0) {
        // Show skipped positions modal
        showSkippedPositionsModal(positions.skipped);
      } else {
        // Show confirmation modal directly
        showConfirmationModal(positions.all, positions.voted);
      }
    });
    
    // Add event listeners for modal buttons
    document.getElementById('cancelFromSkipped').addEventListener('click', function() {
      document.getElementById('skippedPositionsModal').classList.add('hidden');
    });
    
    document.getElementById('submitFromSkipped').addEventListener('click', function() {
      document.getElementById('skippedPositionsModal').classList.add('hidden');
      
      const positions = checkSkippedPositions();
      showConfirmationModal(positions.all, positions.voted);
    });
    
    document.getElementById('cancelFromConfirmation').addEventListener('click', function() {
      document.getElementById('confirmationModal').classList.add('hidden');
    });
    
    document.getElementById('confirmFromConfirmation').addEventListener('click', function() {
      // Submit the form
      const formData = new FormData(document.getElementById('votingForm'));
      const votes = {};
      
      // Convert FormData to votes object
      for (let [key, value] of formData.entries()) {
        if (key.startsWith('candidates[')) {
          const positionMatch = key.match(/candidates\[(.*?)\]/);
          if (positionMatch) {
            const positionId = positionMatch[1];
            
            if (!votes[positionId]) {
              votes[positionId] = [];
            }
            
            votes[positionId].push(value);
          }
        }
      }
      
      // Convert single selections to single values
      Object.keys(votes).forEach(positionId => {
        if (votes[positionId].length === 1) {
          votes[positionId] = votes[positionId][0];
        }
      });
      
      // Show loading state
      const submitBtn = document.querySelector('.submit-btn');
      const originalBtnText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
      
      // Send vote data to server
      fetch('process_vote.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          election_id: <?= $election_id ?>,
          votes: votes
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          // Redirect to success page
          window.location.href = 'vote_success.php?election_id=<?= $election_id ?>';
        } else {
          // Show error message
          alert(data.message || 'Error submitting your vote');
          // Reset button state
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
          document.getElementById('confirmationModal').classList.add('hidden');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting your vote. Please try again.');
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        document.getElementById('confirmationModal').classList.add('hidden');
      });
    });
  </script>
</body>
</html>