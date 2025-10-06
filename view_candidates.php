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
    die("Database connection failed: " . $e->getMessage());
}

// Redirect if not logged in or not a voter
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: login.html");
    exit();
}

// Get election ID from URL
 $election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
if ($election_id <= 0) {
    die("Invalid election ID");
}

// Fetch election details
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
 $stmt->execute([$election_id]);
 $election = $stmt->fetch();
if (!$election) {
    die("Election not found");
}

// Check if user has already voted in this election
 $stmt = $pdo->prepare("SELECT * FROM votes WHERE voter_id = ? AND election_id = ?");
 $stmt->execute([$_SESSION['user_id'], $election_id]);
if ($stmt->fetch()) {
    // Instead of showing an error, redirect to dashboard with a message
    header("Location: voters_dashboard.php?message=already_voted");
    exit();
}

// Get election status
 $now = date('Y-m-d H:i:s');
 $start = $election['start_datetime'];
 $end = $election['end_datetime'];
if ($now < $start) {
    die("This election has not started yet");
} elseif ($now > $end) {
    die("This election has already ended");
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
    
    // Reindex positions array
    $positions = array_values($positions);
    
} catch (\PDOException $e) {
    // If there's an error, set empty arrays
    $electionCandidates = [];
    $candidatesByPosition = [];
    $positions = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate that a candidate is selected for each position
    $selectedCandidates = $_POST['candidates'] ?? [];
    $validSelection = true;
    
    foreach ($positions as $position) {
        $positionId = $position['position_id'];
        if (!isset($selectedCandidates[$positionId]) || empty($selectedCandidates[$positionId])) {
            $validSelection = false;
            break;
        }
    }
    
    if ($validSelection) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Record the vote for each position
            foreach ($selectedCandidates as $positionId => $candidateId) {
                $stmt = $pdo->prepare("
                    INSERT INTO votes (voter_id, election_id, candidate_id, position_id, vote_datetime)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $election_id, $candidateId, $positionId]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to success page
            header("Location: vote_success.php?election_id=" . $election_id);
            exit();
        } catch (\PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            die("Error recording your vote: " . $e->getMessage());
        }
    } else {
        $error = "Please select a candidate for each position before submitting your vote.";
    }
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
    
    .candidate-card input[type="radio"] {
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
      align-items: center;
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
              <li>Select one candidate for each position</li>
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
            <p class="text-green-100 text-sm">Please select one candidate for each position</p>
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
                <div class="position-section">
                  <div class="position-header">
                    <h3 class="text-lg font-bold"><?= htmlspecialchars($position['position_name']) ?></h3>
                    <p class="text-sm text-green-100">Select one candidate</p>
                  </div>
                  
                  <?php if (empty($candidatesByPosition[$position['position_id']])): ?>
                    <div class="text-center py-4">
                      <p class="text-gray-600">No candidates available for this position.</p>
                    </div>
                  <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
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
                          
                          <!-- Radio Button at the Bottom -->
                          <div class="radio-container">
                            <input type="radio" 
                                   name="candidates[<?= htmlspecialchars($position['position_id']) ?>]" 
                                   value="<?= htmlspecialchars($candidate['candidate_id']) ?>" 
                                   id="candidate_<?= htmlspecialchars($candidate['candidate_id']) ?>"
                                   required>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
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
      
      <!-- Confirmation Modal -->
      <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
          <div class="text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirm Your Vote</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to submit your vote? This action cannot be undone.</p>
            
            <div class="flex space-x-3">
              <button type="button" id="cancelVote" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
                Cancel
              </button>
              <button type="button" id="confirmVote" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200">
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
    
    // Handle candidate selection
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
    
    // Confirmation modal handling
    const votingForm = document.getElementById('votingForm');
    const confirmModal = document.getElementById('confirmModal');
    const cancelVote = document.getElementById('cancelVote');
    const confirmVote = document.getElementById('confirmVote');
    
    votingForm.addEventListener('submit', function(e) {
      e.preventDefault();
      confirmModal.classList.remove('hidden');
    });
    
    cancelVote.addEventListener('click', function() {
      confirmModal.classList.add('hidden');
    });
    
    confirmVote.addEventListener('click', function() {
      votingForm.submit();
    });
  </script>
</body>
</html>