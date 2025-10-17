<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Redirect if not logged in or not a voter
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: login.html");
    exit();
}

// Get election ID from URL
 $election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
if ($election_id <= 0) {
    header("Location: voters_dashboard.php?error=invalid_election");
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
    header("Location: voters_dashboard.php?error=database_connection");
    exit();
}

// Fetch election details
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
 $stmt->execute([$election_id]);
 $election = $stmt->fetch();

if (!$election) {
    header("Location: voters_dashboard.php?error=election_not_found");
    exit();
}

// Get voter details
 $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $voter = $stmt->fetch();

// Get the votes this user just cast in this election
 $stmt = $pdo->prepare("
    SELECT v.position_name, v.position_id, c.first_name, c.middle_name, c.last_name, v.vote_datetime
    FROM votes v
    JOIN candidates c ON v.candidate_id = c.id
    WHERE v.election_id = ? AND v.voter_id = ?
    ORDER BY v.vote_datetime DESC
");
 $stmt->execute([$election_id, $_SESSION['user_id']]);
 $voteDetails = $stmt->fetchAll();

// Get all position names for custom positions
 $customPositionIds = [];
foreach ($voteDetails as $vote) {
    if ($vote['position_name'] === null && $vote['position_id'] !== null) {
        $customPositionIds[] = $vote['position_id'];
    }
}

 $positionNames = [];
if (!empty($customPositionIds)) {
    $placeholders = implode(',', array_fill(0, count($customPositionIds), '?'));
    $stmt = $pdo->prepare("SELECT id, position_name FROM positions WHERE id IN ($placeholders)");
    $stmt->execute($customPositionIds);
    $positions = $stmt->fetchAll();
    foreach ($positions as $position) {
        $positionNames[$position['id']] = $position['position_name'];
    }
}

// Group votes by position and format candidate names
 $votesByPosition = [];
foreach ($voteDetails as $vote) {
    // Determine position name
    if ($vote['position_name'] !== null) {
        $positionName = $vote['position_name'];
    } elseif (isset($positionNames[$vote['position_id']])) {
        $positionName = $positionNames[$vote['position_id']];
    } else {
        $positionName = "Position {$vote['position_id']}";
    }
    
    // Format candidate name with middle initial
    $candidateName = $vote['first_name'];
    if (!empty($vote['middle_name'])) {
        // Get the first character of middle name and add a period
        $candidateName .= ' ' . strtoupper(substr(trim($vote['middle_name']), 0, 1)) . '.';
    }
    $candidateName .= ' ' . $vote['last_name'];
    
    if (!isset($votesByPosition[$positionName])) {
        $votesByPosition[$positionName] = [];
    }
    $votesByPosition[$positionName][] = $candidateName;
}

include 'voters_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Vote Success - eBalota</title>
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
    
    .success-card {
      background: linear-gradient(135deg, #1E6F46 0%, #154734 100%);
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
    
    .vote-item {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s ease;
    }
    
    .vote-item:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-2px);
    }
    
    .pulse {
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0% {
        transform: scale(1);
        opacity: 1;
      }
      50% {
        transform: scale(1.05);
        opacity: 0.8;
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }
    
    .confetti {
      position: fixed;
      width: 10px;
      height: 10px;
      background: #FFD166;
      animation: confetti-fall 3s linear infinite;
    }
    
    @keyframes confetti-fall {
      to {
        transform: translateY(100vh) rotate(360deg);
      }
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
            <h1 class="text-xl md:text-2xl font-bold">Vote Success</h1>
            <p class="text-green-100 text-sm">Your vote has been recorded</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <a href="voters_dashboard.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-1 px-3 rounded-lg flex items-center text-sm transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
          </a>
        </div>
      </header>
      
      <!-- Success Card -->
      <div class="max-w-4xl mx-auto">
        <div class="success-card text-white p-8 md:p-12 mb-8">
          <div class="text-center mb-8">
            <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-6 pulse">
              <i class="fas fa-check-circle text-5xl text-white"></i>
            </div>
            <h2 class="text-3xl md:text-4xl font-bold mb-4">Thank You for Voting!</h2>
            <p class="text-xl text-green-100 mb-2">Your vote has been successfully recorded</p>
            <p class="text-green-100">Election: <?= htmlspecialchars($election['title']) ?></p>
          </div>
          
          <!-- Voter Info -->
          <div class="bg-white bg-opacity-10 rounded-xl p-6 mb-8">
            <div class="flex items-center justify-center mb-4">
              <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-user text-2xl text-white"></i>
              </div>
              <div>
                <h3 class="text-xl font-semibold"><?= htmlspecialchars($voter['first_name'] . ' ' . $voter['last_name']) ?></h3>
                <p class="text-green-100">Voter ID: <?= htmlspecialchars($_SESSION['user_id']) ?></p>
              </div>
            </div>
          </div>
          
          <!-- Vote Summary -->
          <div class="mb-8">
            <h3 class="text-2xl font-bold mb-6 text-center">Your Vote Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <?php foreach ($votesByPosition as $position => $candidates): ?>
                <div class="vote-item rounded-xl p-6">
                  <h4 class="text-lg font-semibold mb-3"><?= htmlspecialchars($position) ?></h4>
                  <?php foreach ($candidates as $candidate): ?>
                    <div class="flex items-center mb-2">
                      <i class="fas fa-check-circle text-green-300 mr-2"></i>
                      <span><?= htmlspecialchars($candidate) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          
          <!-- Important Notice -->
          <div class="bg-yellow-400 bg-opacity-20 border border-yellow-400 border-opacity-30 rounded-xl p-6 mb-8">
            <div class="flex items-start">
              <i class="fas fa-info-circle text-yellow-300 mt-1 mr-3 text-xl"></i>
              <div>
                <h4 class="text-lg font-semibold mb-2">Important Notice</h4>
                <ul class="text-sm text-green-100 space-y-1">
                  <li>• Your vote is final and cannot be changed</li>
                  <li>• You have already voted in this election</li>
                  <li>• Keep this page as your receipt</li>
                  <li>• Election results will be announced after voting period ends</li>
                </ul>
              </div>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="window.print()" class="bg-white text-[var(--cvsu-green-dark)] hover:bg-gray-100 font-bold py-3 px-6 rounded-lg flex items-center justify-center transition">
              <i class="fas fa-print mr-2"></i> Print Receipt
            </button>
            <a href="voters_dashboard.php" class="bg-[var(--cvsu-green-light)] hover:bg-[var(--cvsu-green-dark)] text-white font-bold py-3 px-6 rounded-lg flex items-center justify-center transition">
              <i class="fas fa-home mr-2"></i> Go to Dashboard
            </a>
          </div>
        </div>
        
        <!-- Additional Info -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-xl font-bold text-[var(--cvsu-green-dark)] mb-4">Election Information</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="font-semibold text-gray-700 mb-2">Election Period</h4>
              <p class="text-sm text-gray-600">
                <i class="fas fa-calendar-alt mr-2"></i>
                <?= date("M d, Y h:i A", strtotime($election['start_datetime'])) ?> - 
                <?= date("M d, Y h:i A", strtotime($election['end_datetime'])) ?>
              </p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="font-semibold text-gray-700 mb-2">Target Participants</h4>
              <p class="text-sm text-gray-600">
                <i class="fas fa-users mr-2"></i>
                <?= htmlspecialchars($election['target_department']) ?>
              </p>
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
    
    // Create confetti effect
    function createConfetti() {
      const colors = ['#FFD166', '#1E6F46', '#154734', '#37A66B', '#FFFFFF'];
      const confettiCount = 50;
      
      for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.animationDelay = Math.random() * 3 + 's';
        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
        document.body.appendChild(confetti);
        
        // Remove confetti after animation
        setTimeout(() => {
          confetti.remove();
        }, 5000);
      }
    }
    
    // Create confetti on page load
    window.addEventListener('load', createConfetti);
    
    // Create more confetti when clicking the success card
    document.querySelector('.success-card').addEventListener('click', createConfetti);
  </script>
</body>
</html>