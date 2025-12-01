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
    
    // Add party_list column to candidates table if it doesn't exist
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS party_list VARCHAR(100) DEFAULT NULL");
    
    // Create disabled_default_positions table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS disabled_default_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        position_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_admin_position (admin_id, position_name)
    )");
    
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
// Get election status
$now = date('Y-m-d H:i:s');
$start = $election['start_datetime'];
$end = $election['end_datetime'];
$status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');
// Check if user has voted in this election
$stmt = $pdo->prepare("SELECT * FROM votes WHERE voter_id = ? AND election_id = ?");
$stmt->execute([$_SESSION['user_id'], $election_id]);
$hasVoted = (bool)$stmt->fetch();
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
include 'voters_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eBalota - Election Candidates</title>
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
    
    .position-header {
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .position-header:hover {
      background-color: rgba(30, 111, 70, 0.1);
    }
    
    .candidate-card {
      transition: all 0.3s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    
    .candidate-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
    
    .candidate-card:hover .candidate-photo {
      transform: scale(1.05);
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <div class="flex">
    <!-- Main Content -->
    <main class="flex-1 p-4 md:p-8 md:ml-64 pb-24">
      <!-- Header -->
      <header class="bg-[var(--cvsu-green-dark)] text-white p-3 md:p-4 flex justify-between items-center shadow-lg rounded-xl mb-8">
        <div class="flex items-center">
          <button class="md:hidden text-white mr-4" onclick="toggleSidebar()">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <div>
            <h1 class="text-xl md:text-2xl font-bold">Election Candidates</h1>
            <p class="text-green-100 text-sm"><?= htmlspecialchars($election['title']) ?></p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <a href="voters_dashboard.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-1 px-3 rounded-lg flex items-center text-sm transition">
            <i class="fas fa-arrow-left mr-2"></i> Back
          </a>
        </div>
      </header>
      
      <!-- Election Details Card -->
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
                <div class="bg-gradient-to-r from-green-50 to-green-100 p-3 rounded-lg border border-green-100">
                  <h3 class="text-xs font-medium text-green-700 uppercase tracking-wider flex items-center">
                    <i class="fas fa-tasks text-green-600 mr-1 text-xs"></i> Status
                  </h3>
                  <p class="text-sm">
                    <?php if ($status === 'ongoing'): ?>
                      <span class="status-badge bg-green-100 text-green-800">
                        <i class="fas fa-play-circle mr-1 text-xs"></i> Ongoing
                      </span>
                    <?php elseif ($status === 'upcoming'): ?>
                      <span class="status-badge bg-yellow-100 text-yellow-800">
                        <i class="fas fa-clock mr-1 text-xs"></i> Upcoming
                      </span>
                    <?php else: ?>
                      <span class="status-badge bg-gray-100 text-gray-800">
                        <i class="fas fa-check-circle mr-1 text-xs"></i> Completed
                      </span>
                    <?php endif; ?>
                  </p>
                </div>
                <div class="bg-gradient-to-r from-amber-50 to-amber-100 p-3 rounded-lg border border-amber-100">
                  <h3 class="text-xs font-medium text-amber-700 uppercase tracking-wider flex items-center">
                    <i class="fas fa-bullseye text-amber-600 mr-1 text-xs"></i> Position
                  </h3>
                  <p class="text-sm font-medium text-amber-800"><?= htmlspecialchars($election['target_position']) ?></p>
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
      
      <!-- Positions and Candidates Section -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
        <div class="p-5 border-b bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] text-white">
          <h2 class="text-xl font-bold">Positions & Candidates</h2>
          <p class="text-green-100 text-sm">Click on a position to view the candidates</p>
        </div>
        
        <div class="divide-y">
          <?php if (empty($positions)): ?>
            <div class="p-8 text-center">
              <i class="fas fa-exclamation-circle text-4xl text-gray-400 mb-3"></i>
              <h3 class="text-lg font-semibold text-gray-700 mb-1">No Candidates Available</h3>
              <p class="text-gray-600 text-sm">There are no candidates set up for this election yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($positions as $position): ?>
              <div class="position-section">
                <div class="position-header p-4 flex justify-between items-center" onclick="togglePosition('position_<?= is_numeric($position['position_id']) ? $position['position_id'] : md5($position['position_id']) ?>')">
                  <div>
                    <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($position['position_name']) ?></h3>
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($position['position_description']) ?></p>
                  </div>
                  <div class="flex items-center">
                    <span class="mr-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 px-3 py-1 rounded-full text-xs font-medium">
                      <?= count($candidatesByPosition[$position['position_id']] ?? []) ?> candidates
                    </span>
                    <i id="icon_position_<?= is_numeric($position['position_id']) ? $position['position_id'] : md5($position['position_id']) ?>" class="fas fa-chevron-down text-gray-600 transition-transform text-sm"></i>
                  </div>
                </div>
                
                <div id="position_<?= is_numeric($position['position_id']) ? $position['position_id'] : md5($position['position_id']) ?>" class="hidden p-5 bg-gray-50">
                  <?php if (empty($candidatesByPosition[$position['position_id']])): ?>
                    <div class="text-center py-4">
                      <p class="text-gray-600 text-sm">No candidates available for this position.</p>
                    </div>
                  <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                      <?php foreach ($candidatesByPosition[$position['position_id']] as $candidate): ?>
                        <div class="candidate-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
                          <!-- Card Header with Photo -->
                          <div class="relative bg-gradient-to-r from-[var(--cvsu-green-light)] to-[var(--cvsu-green)] p-5">
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
                              <h4 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($candidate['candidate_name']) ?></h4>
                              <div class="inline-block px-3 py-1 bg-gray-100 rounded-full">
                                <p class="text-sm text-gray-600 font-medium <?= $candidate['partylist'] === 'IND' ? 'partylist-ind' : '' ?>">
                                  Partylist: <?= htmlspecialchars($candidate['partylist']) ?>
                                </p>
                              </div>
                            </div>
                            
                            <!-- Credentials Button -->
                            <div class="mt-auto">
                              <?php if (!empty($candidate['credentials_path'])): ?>
                                <a href="<?= htmlspecialchars($candidate['credentials_path']) ?>" target="_blank" 
                                   class="credential-btn block w-full bg-gradient-to-r from-[var(--cvsu-green)] to-[var(--cvsu-green-dark)] hover:from-[var(--cvsu-green-dark)] hover:to-[var(--cvsu-green)] text-white py-3 px-4 rounded-lg text-sm font-medium flex items-center justify-center shadow-md">
                                  <i class="fas fa-file-pdf mr-2"></i> View Credentials
                                </a>
                              <?php else: ?>
                                <button disabled class="block w-full bg-gray-200 text-gray-500 py-3 px-4 rounded-lg text-sm font-medium cursor-not-allowed flex items-center justify-center shadow-inner">
                                  <i class="fas fa-file-pdf mr-2"></i> No Credentials
                                </button>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
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
    
    // Toggle position section
    function togglePosition(positionId) {
      const positionContent = document.getElementById(positionId);
      const icon = document.getElementById('icon_' + positionId);
      
      positionContent.classList.toggle('hidden');
      
      if (positionContent.classList.contains('hidden')) {
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
      } else {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
      }
    }
    
    // Initialize page - open first position by default
    document.addEventListener('DOMContentLoaded', () => {
      const firstPosition = document.querySelector('.position-section');
      if (firstPosition) {
        const positionHeader = firstPosition.querySelector('.position-header');
        const onclickAttr = positionHeader.getAttribute('onclick');
        const match = onclickAttr.match(/position_([^\']+)/);
        if (match) {
          togglePosition('position_' + match[1]);
        }
      }
    });
  </script>
</body>
</html>