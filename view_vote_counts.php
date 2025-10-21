<?php 
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get election ID from URL
 $electionId = $_GET['id'] ?? 0;
if (!$electionId) {
    header('Location: admin_view_elections.php');
    exit();
}

// Fetch election details
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
 $stmt->execute([$electionId]);
 $election = $stmt->fetch();

if (!$election) {
    header('Location: admin_view_elections.php');
    exit();
}

// Determine if election is completed
 $now = new DateTime();
 $start = new DateTime($election['start_datetime']);
 $end = new DateTime($election['end_datetime']);
 $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

// ===== GET UNIQUE VOTERS WHO HAVE VOTED (not total votes) =====
 $sql = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE election_id = ?";
 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $totalVotesCast = $stmt->fetch()['total'];

// ===== GET ELIGIBLE VOTERS COUNT (Same logic as voters dashboard) =====
 $conditions = ["role = 'voter'"];
 $params = [];

if ($election['target_position'] === 'coop') {
    // For COOP elections - only users with both is_coop_member=1 AND migs_status=1
    $conditions[] = "is_coop_member = 1";
    $conditions[] = "migs_status = 1";
} else {
    // For other elections - apply position filter first
    if ($election['target_position'] !== 'All') {
        if ($election['target_position'] === 'faculty') {
            $conditions[] = "position = ?";
            $params[] = 'academic';
        } else {
            $conditions[] = "position = ?";
            $params[] = $election['target_position'];
        }
    }
    
    // Get allowed filters from election
    $allowed_colleges = array_filter(array_map('strtolower', array_map('trim', explode(',', $election['allowed_colleges'] ?? ''))));
    $allowed_courses = array_filter(array_map('strtolower', array_map('trim', explode(',', $election['allowed_courses'] ?? ''))));
    $allowed_status = array_filter(array_map('strtolower', array_map('trim', explode(',', $election['allowed_status'] ?? ''))));
    
    // Apply college filter if specified
    if (!empty($allowed_colleges) && !in_array('all', $allowed_colleges)) {
        $placeholders = implode(',', array_fill(0, count($allowed_colleges), '?'));
        $conditions[] = "LOWER(department) IN ($placeholders)";
        $params = array_merge($params, $allowed_colleges);
    }
    
    // Apply course filter if specified (mainly for students)
    if (!empty($allowed_courses) && !in_array('all', $allowed_courses)) {
        $placeholders = implode(',', array_fill(0, count($allowed_courses), '?'));
        $conditions[] = "LOWER(course) IN ($placeholders)";
        $params = array_merge($params, $allowed_courses);
    }
    
    // Apply status filter if specified (mainly for faculty and non-academic)
    if (!empty($allowed_status) && !in_array('all', $allowed_status)) {
        $placeholders = implode(',', array_fill(0, count($allowed_status), '?'));
        $conditions[] = "LOWER(status) IN ($placeholders)";
        $params = array_merge($params, $allowed_status);
    }
}

// Build and execute the query for eligible voters
 $sql = "SELECT COUNT(*) as total FROM users WHERE " . implode(' AND ', $conditions);
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $totalEligibleVoters = $stmt->fetch()['total'];

// Calculate turnout percentage
 $turnoutPercentage = ($totalEligibleVoters > 0) ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1) : 0;

// ===== GET DISTINCT POSITIONS FOR THIS ELECTION =====
 $positionSql = "SELECT DISTINCT position FROM election_candidates WHERE election_id = ? ORDER BY position";
 $stmt = $pdo->prepare($positionSql);
 $stmt->execute([$electionId]);
 $positions = $stmt->fetchAll();
 $positionOptions = array_column($positions, 'position');

// Add "All" option at the beginning
array_unshift($positionOptions, 'All');

// ===== GET CANDIDATES WITH VOTE COUNTS =====
 $sql = "
    SELECT 
        ec.id as election_candidate_id,
        c.id as candidate_id,
        CONCAT(c.first_name, ' ', c.last_name) as candidate_name,
        c.photo,
        ec.position as election_position,
        COUNT(v.vote_id) as vote_count
    FROM election_candidates ec
    JOIN candidates c ON ec.candidate_id = c.id
    LEFT JOIN votes v ON ec.election_id = v.election_id 
                   AND ec.candidate_id = v.candidate_id
    WHERE ec.election_id = ?
    GROUP BY ec.id, c.id, c.first_name, c.last_name, c.photo, ec.position
    ORDER BY ec.position, vote_count DESC
";

 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $candidatesWithVotes = $stmt->fetchAll();

// Group candidates by position
 $candidatesByPosition = [];
foreach ($candidatesWithVotes as $candidate) {
    $position = $candidate['election_position'];
    if (!isset($candidatesByPosition[$position])) {
        $candidatesByPosition[$position] = [];
    }
    $candidatesByPosition[$position][] = $candidate;
}

 $pageTitle = $status === 'completed' ? 'Election Results' : 'Vote Counts';

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($election['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .progress-bar {
      transition: width 1s ease-in-out;
    }
    .candidate-card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .candidate-card:hover {
      transform: translateY(-2px);
    }
    .candidate-card-highlight {
      border: 2px solid #FFD700;
      box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
      background-color: #FFFBEB;
    }
    .live-indicator {
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }
    .position-section {
      scroll-margin-top: 80px;
    }
    .rank-badge {
      width: 40px;
      height: 40px;
    }
    .rank-1 {
      background: linear-gradient(135deg, #FFD700, #FFA500);
      color: white;
    }
    .rank-2 {
      background: linear-gradient(135deg, #9e9e9e, #757575);
      color: white;
    }
    .rank-3 {
      background: linear-gradient(135deg, #9e9e9e, #757575);
      color: white;
    }
    .rank-other {
      background: linear-gradient(135deg, #9e9e9e, #757575);
      color: white;
    }
    .tie-indicator {
      background: linear-gradient(135deg, #FFD166, #FFA500);
      color: white;
    }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
  
  <main class="flex-1 p-6 md:p-8 md:ml-64">
    <div class="max-w-6xl mx-auto">
      <!-- Election Information Header -->
      <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
          <div class="flex-1">
            <div class="flex items-center mb-2">
              <h1 class="text-2xl font-bold text-[var(--cvsu-green-dark)] mr-3">
                <?= htmlspecialchars($pageTitle) ?>
              </h1>
              <?php if ($status === 'ongoing' && $election['realtime_results']): ?>
                <span class="live-indicator inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                  <i class="fas fa-circle mr-1"></i> LIVE
                </span>
              <?php endif; ?>
            </div>
            <p class="text-gray-600 text-lg">
              <?= htmlspecialchars($election['title']) ?>
            </p>
            <div class="mt-2 text-sm text-gray-500">
              <?= date("F j, Y, g:i A", strtotime($election['start_datetime'])) ?> - 
              <?= date("F j, Y, g:i A", strtotime($election['end_datetime'])) ?>
            </div>
          </div>
          
          <div class="mt-4 md:mt-0 md:ml-6">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                  <?= $status === 'completed' ? 'bg-green-100 text-green-800' : 
                     ($status === 'ongoing' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') ?>">
              <?php if ($status === 'completed'): ?>
                <i class="fas fa-check-circle mr-1"></i> Completed
              <?php elseif ($status === 'ongoing'): ?>
                <i class="fas fa-clock mr-1"></i> Ongoing
              <?php else: ?>
                <i class="fas fa-hourglass-start mr-1"></i> Upcoming
              <?php endif; ?>
            </span>
          </div>
        </div>
        
        <!-- Position Filter -->
        <div class="mt-4">
          <label for="positionFilter" class="block text-sm font-medium text-gray-700 mb-2">
            Filter by Position:
          </label>
          <select id="positionFilter" class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)]">
            <?php foreach ($positionOptions as $position): ?>
              <option value="<?= htmlspecialchars($position) ?>" <?= $position === 'All' ? 'selected' : '' ?>>
                <?= htmlspecialchars($position) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <!-- Vote Turnout Statistics and Candidates Section -->
      <div id="electionResults">
        <!-- Vote Turnout Statistics -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-users text-blue-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-blue-800">Eligible Voters</p>
                <p class="text-2xl font-bold text-blue-900"><?= number_format($totalEligibleVoters) ?></p>
              </div>
            </div>
          </div>
          
          <div class="bg-gradient-to-r from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-green-800">Votes Cast</p>
                <p class="text-2xl font-bold text-green-900"><?= number_format($totalVotesCast) ?></p>
              </div>
            </div>
          </div>
          
          <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-percentage text-purple-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-purple-800">Turnout Rate</p>
                <p class="text-2xl font-bold text-purple-900"><?= $turnoutPercentage ?>%</p>
              </div>
            </div>
          </div>
          
          <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-user-friends text-yellow-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-yellow-800">Candidates</p>
                <p class="text-2xl font-bold text-yellow-900"><?= count($candidatesWithVotes) ?></p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Candidates Vote Counts by Position -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Candidate Vote Counts by Position</h2>
          </div>
          
          <div class="p-6">
            <?php if (empty($candidatesByPosition)): ?>
              <div class="text-center py-8">
                <i class="fas fa-users text-gray-400 text-4xl mb-3"></i>
                <p class="text-gray-600">No candidates found for this election.</p>
              </div>
            <?php else: ?>
              <?php foreach ($candidatesByPosition as $position => $candidates): ?>
                <?php
                // Sort candidates by vote count for ranking
                usort($candidates, function($a, $b) {
                  return $b['vote_count'] - $a['vote_count'];
                });
                
                $totalVotesForPosition = array_sum(array_column($candidates, 'vote_count'));
                
                // Check if there's a tie for first place with votes > 0
                $isFirstPlaceTie = false;
                if (count($candidates) > 1 && $candidates[0]['vote_count'] > 0) {
                    $firstPlaceVotes = $candidates[0]['vote_count'];
                    for ($i = 1; $i < count($candidates); $i++) {
                        if ($candidates[$i]['vote_count'] == $firstPlaceVotes) {
                            $isFirstPlaceTie = true;
                            break;
                        } else {
                            break; // Since candidates are sorted by vote count
                        }
                    }
                }
                
                // Initialize tie detection variables
                $prevVoteCount = null;
                $prevRank = null;
                $isTie = false;
                ?>
                
                <!-- Position Header -->
                <div class="position-section mb-8 last:mb-0">
                  <div class="flex items-center mb-4 pb-2 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-[var(--cvsu-green-dark)]">
                      <?= htmlspecialchars($position) ?>
                    </h3>
                    <span class="ml-3 text-sm text-gray-500">
                      <?= count($candidates) ?> candidate<?= count($candidates) != 1 ? 's' : '' ?> • 
                      <?= number_format($totalVotesForPosition) ?> vote<?= $totalVotesForPosition != 1 ? 's' : '' ?>
                    </span>
                  </div>
                  
                  <!-- Candidates List for this Position (Full Width Cards) -->
                  <div class="space-y-4">
                    <?php foreach ($candidates as $index => $data): ?>
                      <?php
                      $candidateId = $data['candidate_id'];
                      $candidateName = $data['candidate_name'];
                      $candidatePhoto = $data['photo'];
                      $electionPosition = $data['election_position'];
                      $voteCount = $data['vote_count'];
                      $percentage = $totalVotesForPosition > 0 ? round(($voteCount / $totalVotesForPosition) * 100, 1) : 0;
                      
                      // Check for tie only if vote count > 0
                      if ($voteCount > 0 && $prevVoteCount === $voteCount) {
                          $isTie = true;
                          $rank = $prevRank;
                      } else {
                          $isTie = false;
                          $rank = $index + 1;
                          $prevRank = $rank;
                      }
                      
                      $prevVoteCount = $voteCount;
                      
                      // Determine if candidate card should be highlighted (rank 1 AND has votes > 0)
                      $isHighlighted = ($rank === 1 && $voteCount > 0);
                      ?>
                      
                      <div class="candidate-card <?= $isHighlighted ? 'candidate-card-highlight' : 'border border-gray-200' ?> bg-white rounded-lg shadow-sm p-4 hover:shadow-md" data-position="<?= htmlspecialchars($position) ?>">
                        <div class="flex items-center">
                          <!-- Rank Badge -->
                          <div class="flex-shrink-0 mr-4">
                            <div class="rank-badge rounded-full flex items-center justify-center font-bold text-lg 
                                <?= $rank === 1 ? ($isFirstPlaceTie ? 'tie-indicator' : 'rank-1') : 
                                   ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other')) ?>">
                              <?= $rank ?>
                            </div>
                            <?php if ($isTie && $voteCount > 0): ?>
                              <div class="text-xs text-center text-yellow-600 mt-1 font-bold">TIE</div>
                            <?php endif; ?>
                          </div>
                          
                          <!-- Candidate Photo -->
                          <div class="flex-shrink-0 mr-4">
                            <?php if (!empty($candidatePhoto)): ?>
                              <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                                   alt="<?= htmlspecialchars($candidateName) ?>" 
                                   class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-md">
                            <?php else: ?>
                              <div class="w-16 h-16 rounded-full bg-gray-200 border-2 border-white shadow-md flex items-center justify-center">
                                <span class="text-gray-500 text-sm font-medium">
                                  <?= substr($candidateName, 0, 1) ?>
                                </span>
                              </div>
                            <?php endif; ?>
                          </div>
                          
                          <!-- Candidate Details -->
                          <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                              <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                  <?= htmlspecialchars($candidateName) ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                  <?= htmlspecialchars($electionPosition) ?>
                                </p>
                              </div>
                              
                              <div class="mt-2 md:mt-0 text-right">
                                <p class="text-xl font-bold text-gray-900"><?= number_format($voteCount) ?></p>
                                <p class="text-sm text-gray-500">votes</p>
                              </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="mt-3">
                              <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span><?= $percentage ?>% of position votes</span>
                                <span><?= round(($voteCount / max($totalVotesForPosition, 1)) * 100, 1) ?>%</span>
                              </div>
                              <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="progress-bar bg-gradient-to-r from-[var(--cvsu-green)] to-[var(--cvsu-green-light)] h-3 rounded-full flex items-center justify-end pr-2" 
                                     style="width: <?= $percentage ?>%">
                                  <?php if ($percentage > 15): ?>
                                    <span class="text-xs text-white font-medium"><?= $percentage ?>%</span>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="mt-2 flex items-center justify-between text-sm">
                              <span class="text-gray-500">
                                <i class="fas fa-chart-line mr-1"></i>
                                Rank #<?= $rank ?> in <?= htmlspecialchars($position) ?>
                                <?php if ($isTie && $voteCount > 0): ?>
                                  <span class="text-yellow-600 font-medium">(TIE)</span>
                                <?php endif; ?>
                              </span>
                              <?php if ($status === 'completed' && $rank === 1 && $voteCount > 0): ?>
                                <?php if ($isFirstPlaceTie): ?>
                                  <span class="text-yellow-600 font-medium">
                                    <i class="fas fa-trophy mr-1"></i> TIE for <?= htmlspecialchars($position) ?>
                                  </span>
                                <?php else: ?>
                                  <span class="text-green-600 font-medium">
                                    <i class="fas fa-trophy mr-1"></i> Winner for <?= htmlspecialchars($position) ?>
                                  </span>
                                <?php endif; ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Back Button -->
      <div class="mt-6">
        <a href="admin_view_elections.php" 
           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          <i class="fas fa-arrow-left mr-2"></i>
          Back to Elections
        </a>
      </div>
    </div>
  </main>
</div>

<!-- Real-time Update Script (for ongoing elections with realtime_results enabled) -->
<?php if ($status === 'ongoing' && $election['realtime_results']): ?>
<script>
  function updateVoteCounts() {
    fetch('view_vote_counts.php?id=<?= $electionId ?>&ajax=1')
      .then(response => response.text())
      .then(html => {
        // Update the election results section
        document.getElementById('electionResults').innerHTML = html;
        
        // Reinitialize position filter
        const positionFilter = document.getElementById('positionFilter');
        if (positionFilter) {
          positionFilter.dispatchEvent(new Event('change'));
        }
        
        // Reinitialize progress bars animation
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
          const width = bar.style.width;
          bar.style.width = '0%';
          
          setTimeout(() => {
            bar.style.width = width;
          }, 100);
        });
      })
      .catch(error => console.error('Error updating vote counts:', error));
  }
  
  // Update every 30 seconds
  setInterval(updateVoteCounts, 30000);
</script>
<?php endif; ?>

<!-- Position Filter Script -->
<script>
  document.getElementById('positionFilter').addEventListener('change', function() {
    const selectedPosition = this.value;
    const positionSections = document.querySelectorAll('.position-section');
    
    positionSections.forEach(section => {
      const sectionPosition = section.querySelector('h3').textContent.trim();
      
      if (selectedPosition === 'All' || sectionPosition === selectedPosition) {
        section.style.display = 'block';
      } else {
        section.style.display = 'none';
      }
    });
  });
</script>

<!-- AJAX Handler for Real-time Updates -->
<?php if (isset($_GET['ajax']) && $_GET['ajax'] === '1'): ?>
  <?php
  // Recalculate unique voters count
  $sql = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE election_id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$electionId]);
  $totalVotesCast = $stmt->fetch()['total'];
  
  // Recalculate turnout percentage
  $turnoutPercentage = ($totalEligibleVoters > 0) ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1) : 0;
  
  // Re-run the candidate vote count query
  $sql = "
      SELECT 
          ec.id as election_candidate_id,
          c.id as candidate_id,
          CONCAT(c.first_name, ' ', c.last_name) as candidate_name,
          c.photo,
          ec.position as election_position,
          COUNT(v.vote_id) as vote_count
      FROM election_candidates ec
      JOIN candidates c ON ec.candidate_id = c.id
      LEFT JOIN votes v ON ec.election_id = v.election_id 
                     AND ec.candidate_id = v.candidate_id
      WHERE ec.election_id = ?
      GROUP BY ec.id, c.id, c.first_name, c.last_name, c.photo, ec.position
      ORDER BY ec.position, vote_count DESC
  ";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$electionId]);
  $candidatesWithVotes = $stmt->fetchAll();
  
  // Group by position
  $candidatesByPosition = [];
  foreach ($candidatesWithVotes as $candidate) {
      $position = $candidate['election_position'];
      if (!isset($candidatesByPosition[$position])) {
          $candidatesByPosition[$position] = [];
      }
      $candidatesByPosition[$position][] = $candidate;
  }
  
  // Return only the election results section for AJAX updates
  ob_start();
  ?>
  <!-- Vote Turnout Statistics -->
  <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-users text-blue-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-blue-800">Eligible Voters</p>
          <p class="text-2xl font-bold text-blue-900"><?= number_format($totalEligibleVoters) ?></p>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-r from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-check-circle text-green-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-green-800">Votes Cast</p>
          <p class="text-2xl font-bold text-green-900"><?= number_format($totalVotesCast) ?></p>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-percentage text-purple-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-purple-800">Turnout Rate</p>
          <p class="text-2xl font-bold text-purple-900"><?= $turnoutPercentage ?>%</p>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-user-friends text-yellow-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-yellow-800">Candidates</p>
          <p class="text-2xl font-bold text-yellow-900"><?= count($candidatesWithVotes) ?></p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Candidates Vote Counts by Position -->
  <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-800">Candidate Vote Counts by Position</h2>
    </div>
    
    <div class="p-6">
      <?php if (empty($candidatesByPosition)): ?>
        <div class="text-center py-8">
          <i class="fas fa-users text-gray-400 text-4xl mb-3"></i>
          <p class="text-gray-600">No candidates found for this election.</p>
        </div>
      <?php else: ?>
        <?php foreach ($candidatesByPosition as $position => $candidates): 
          usort($candidates, function($a, $b) {
            return $b['vote_count'] - $a['vote_count'];
          });
          
          $totalVotesForPosition = array_sum(array_column($candidates, 'vote_count'));
          
          // Check if there's a tie for first place with votes > 0
          $isFirstPlaceTie = false;
          if (count($candidates) > 1 && $candidates[0]['vote_count'] > 0) {
              $firstPlaceVotes = $candidates[0]['vote_count'];
              for ($i = 1; $i < count($candidates); $i++) {
                  if ($candidates[$i]['vote_count'] == $firstPlaceVotes) {
                      $isFirstPlaceTie = true;
                      break;
                  } else {
                      break; // Since candidates are sorted by vote count
                  }
              }
          }
          
          // Initialize tie detection variables
          $prevVoteCount = null;
          $prevRank = null;
          $isTie = false;
        ?>
          <div class="position-section mb-8 last:mb-0">
            <div class="flex items-center mb-4 pb-2 border-b border-gray-200">
              <h3 class="text-xl font-bold text-[var(--cvsu-green-dark)]">
                <?= htmlspecialchars($position) ?>
              </h3>
              <span class="ml-3 text-sm text-gray-500">
                <?= count($candidates) ?> candidate<?= count($candidates) != 1 ? 's' : '' ?> • 
                <?= number_format($totalVotesForPosition) ?> vote<?= $totalVotesForPosition != 1 ? 's' : '' ?>
              </span>
            </div>
            
            <!-- Candidates List for this Position (Full Width Cards) -->
            <div class="space-y-4">
              <?php foreach ($candidates as $index => $data): 
                $candidateName = $data['candidate_name'];
                $candidatePhoto = $data['photo'];
                $electionPosition = $data['election_position'];
                $voteCount = $data['vote_count'];
                $percentage = $totalVotesForPosition > 0 ? round(($voteCount / $totalVotesForPosition) * 100, 1) : 0;
                
                // Check for tie only if vote count > 0
                if ($voteCount > 0 && $prevVoteCount === $voteCount) {
                    $isTie = true;
                    $rank = $prevRank;
                } else {
                    $isTie = false;
                    $rank = $index + 1;
                    $prevRank = $rank;
                }
                
                $prevVoteCount = $voteCount;
                
                // Determine if candidate card should be highlighted (rank 1 AND has votes > 0)
                $isHighlighted = ($rank === 1 && $voteCount > 0);
              ?>
                <div class="candidate-card <?= $isHighlighted ? 'candidate-card-highlight' : 'border border-gray-200' ?> bg-white rounded-lg shadow-sm p-4 hover:shadow-md" data-position="<?= htmlspecialchars($position) ?>">
                  <div class="flex items-center">
                    <!-- Rank Badge -->
                    <div class="flex-shrink-0 mr-4">
                      <div class="rank-badge rounded-full flex items-center justify-center font-bold text-lg 
                          <?= $rank === 1 ? ($isFirstPlaceTie ? 'tie-indicator' : 'rank-1') : 
                             ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other')) ?>">
                        <?= $rank ?>
                      </div>
                      <?php if ($isTie && $voteCount > 0): ?>
                        <div class="text-xs text-center text-yellow-600 mt-1 font-bold">TIE</div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Candidate Photo -->
                    <div class="flex-shrink-0 mr-4">
                      <?php if (!empty($candidatePhoto)): ?>
                        <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                             alt="<?= htmlspecialchars($candidateName) ?>" 
                             class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-md">
                      <?php else: ?>
                        <div class="w-16 h-16 rounded-full bg-gray-200 border-2 border-white shadow-md flex items-center justify-center">
                          <span class="text-gray-500 text-sm font-medium">
                            <?= substr($candidateName, 0, 1) ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Candidate Details -->
                    <div class="flex-1">
                      <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                          <h3 class="text-lg font-semibold text-gray-900">
                            <?= htmlspecialchars($candidateName) ?>
                          </h3>
                          <p class="text-sm text-gray-600">
                            <?= htmlspecialchars($electionPosition) ?>
                          </p>
                        </div>
                        
                        <div class="mt-2 md:mt-0 text-right">
                          <p class="text-xl font-bold text-gray-900"><?= number_format($voteCount) ?></p>
                          <p class="text-sm text-gray-500">votes</p>
                        </div>
                      </div>
                      
                      <!-- Progress Bar -->
                      <div class="mt-3">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                          <span><?= $percentage ?>% of position votes</span>
                          <span><?= round(($voteCount / max($totalVotesForPosition, 1)) * 100, 1) ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                          <div class="progress-bar bg-gradient-to-r from-[var(--cvsu-green)] to-[var(--cvsu-green-light)] h-3 rounded-full flex items-center justify-end pr-2" 
                               style="width: <?= $percentage ?>%">
                            <?php if ($percentage > 15): ?>
                              <span class="text-xs text-white font-medium"><?= $percentage ?>%</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Additional Info -->
                      <div class="mt-2 flex items-center justify-between text-sm">
                        <span class="text-gray-500">
                          <i class="fas fa-chart-line mr-1"></i>
                          Rank #<?= $rank ?> in <?= htmlspecialchars($position) ?>
                          <?php if ($isTie && $voteCount > 0): ?>
                            <span class="text-yellow-600 font-medium">(TIE)</span>
                          <?php endif; ?>
                        </span>
                        <?php if ($status === 'completed' && $rank === 1 && $voteCount > 0): ?>
                          <?php if ($isFirstPlaceTie): ?>
                            <span class="text-yellow-600 font-medium">
                              <i class="fas fa-trophy mr-1"></i> TIE for <?= htmlspecialchars($position) ?>
                            </span>
                          <?php else: ?>
                            <span class="text-green-600 font-medium">
                              <i class="fas fa-trophy mr-1"></i> Winner for <?= htmlspecialchars($position) ?>
                            </span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php
  echo ob_get_clean();
  ?>
<?php endif; ?>

<script>
  // Animate progress bars on page load
  document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    setTimeout(() => {
      progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        setTimeout(() => {
          bar.style.width = width;
        }, 100);
      });
    }, 300);
    
    // Initialize position filter
    const positionFilter = document.getElementById('positionFilter');
    if (positionFilter) {
      positionFilter.dispatchEvent(new Event('change'));
    }
  });
</script>
</body>
</html>