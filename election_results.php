<?php
session_start();
date_default_timezone_set('Asia/Manila');

/* ==========================================================
   AUTH CHECK (VOTER ONLY)
   ========================================================== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header('Location: login.php');
    exit();
}

$voterId = (int)$_SESSION['user_id'];

/* ==========================================================
   DB CONNECTION
   ========================================================== */
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
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
    die("A system error occurred. Please try again later.");
}

/* ==========================================================
   SHARED HELPERS
   ========================================================== */
require_once __DIR__ . '/includes/election_scope_helpers.php';
require_once __DIR__ . '/includes/analytics_scopes.php';

/* ==========================================================
   GET ELECTION ID (supports ?id= or ?election_id=)
   ========================================================== */
$electionId = 0;
if (isset($_GET['id'])) {
    $electionId = (int)$_GET['id'];
} elseif (isset($_GET['election_id'])) {
    $electionId = (int)$_GET['election_id'];
}

/* ==========================================================
   IF NO / INVALID ID → FRIENDLY "NOT FOUND"
   ========================================================== */
if ($electionId <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Election Results - eBalota</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <style>
        :root {
          --cvsu-green-dark: #154734;
          --cvsu-green: #1E6F46;
          --cvsu-green-light: #37A66B;
          --cvsu-yellow: #FFD166;
        }
      </style>
    </head>
    <body class="bg-gray-50 flex min-h-screen items-center justify-center px-4">
      <div class="max-w-md w-full bg-white shadow-xl rounded-2xl p-6 text-center border border-gray-100">
        <div class="mb-4">
          <div class="mx-auto w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 11-12.728 0 9 9 0 0112.728 0zM12 8v4m0 4h.01" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-gray-800 mb-1">
            Election Not Found
          </h1>
          <p class="text-sm text-gray-600">
            The election link appears to be incomplete or invalid. Please return to your dashboard and open the results again.
          </p>
        </div>
        <a href="voters_dashboard.php"
           class="inline-flex items-center mt-4 px-4 py-2 rounded-lg bg-[#1E6F46] text-white text-sm font-medium hover:bg-[#154734]">
          ← Back to Dashboard
        </a>
      </div>
    </body>
    </html>
    <?php
    exit();
}

/* ==========================================================
   LOAD ELECTION
   ========================================================== */
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$electionId]);
$election = $stmt->fetch();

if (!$election) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Election Results - eBalota</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <style>
        :root {
          --cvsu-green-dark: #154734;
          --cvsu-green: #1E6F46;
          --cvsu-green-light: #37A66B;
          --cvsu-yellow: #FFD166;
        }
      </style>
    </head>
    <body class="bg-gray-50 flex min-h-screen items-center justify-center px-4">
      <div class="max-w-md w-full bg-white shadow-xl rounded-2xl p-6 text-center border border-gray-100">
        <div class="mb-4">
          <div class="mx-auto w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 11-12.728 0 9 9 0 0112.728 0zM12 8v4m0 4h.01" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-gray-800 mb-1">
            Election Not Found
          </h1>
          <p class="text-sm text-gray-600">
            We could not find the election you are trying to view. It may have been removed or is not yet available.
          </p>
        </div>
        <a href="voters_dashboard.php"
           class="inline-flex items-center mt-4 px-4 py-2 rounded-lg bg-[#1E6F46] text-white text-sm font-medium hover:bg-[#154734]">
          ← Back to Dashboard
        </a>
      </div>
    </body>
    </html>
    <?php
    exit();
}

/* ==========================================================
   CHECK IF RESULTS ARE RELEASED
   ========================================================== */
if (empty($election['results_released'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Election Results - <?= htmlspecialchars($election['title'] ?? 'Election') ?></title>
      <script src="https://cdn.tailwindcss.com"></script>
      <style>
        :root {
          --cvsu-green-dark: #154734;
          --cvsu-green: #1E6F46;
          --cvsu-green-light: #37A66B;
          --cvsu-yellow: #FFD166;
        }
      </style>
    </head>
    <body class="bg-gray-50 flex min-h-screen items-center justify-center px-4">
      <div class="max-w-md w-full bg-white shadow-xl rounded-2xl p-6 text-center border border-gray-100">
        <div class="mb-4">
          <div class="mx-auto w-16 h-16 rounded-full bg-yellow-50 flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12A9 9 0 113 12a9 9 0 0118 0z" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-gray-800 mb-1">
            Results Not Yet Released
          </h1>
          <p class="text-sm text-gray-600">
            The official results for 
            <span class="font-semibold"><?= htmlspecialchars($election['title'] ?? 'this election') ?></span>
            have not yet been released by the Election Administrator.
          </p>
          <p class="text-sm text-gray-600 mt-2">
            Please check back later. Once canvassing is complete and the results are verified,
            they will be published here.
          </p>
        </div>
        <a href="voters_dashboard.php"
           class="inline-flex items-center mt-4 px-4 py-2 rounded-lg bg-[#1E6F46] text-white text-sm font-medium hover:bg-[#154734]">
          ← Back to Dashboard
        </a>
      </div>
    </body>
    </html>
    <?php
    exit();
}

/* ==========================================================
   STATUS & TURNOUT STATS
   ========================================================== */
$now   = new DateTime();
$start = new DateTime($election['start_datetime']);
$end   = new DateTime($election['end_datetime']);
$status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

$sql = "SELECT COUNT(DISTINCT voter_id) AS total FROM votes WHERE election_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$electionId]);
$totalVotesCast = (int)($stmt->fetch()['total'] ?? 0);

// Generic eligible voters logic (simplified version of view_vote_counts generic branch)
$conditions = ["role = 'voter'"];
$params     = [];

$targetPos = strtolower($election['target_position'] ?? 'all');

if ($targetPos === 'coop') {
    $conditions[] = "is_coop_member = 1";
    $conditions[] = "migs_status = 1";
} else {
    if ($targetPos !== 'all') {
        if ($targetPos === 'faculty') {
            $conditions[] = "position = ?";
            $params[]     = 'academic';
        } elseif ($targetPos === 'non-academic') {
            $conditions[] = "position = ?";
            $params[]     = 'non-academic';
        } elseif ($targetPos === 'others') {
            $conditions[] = "(position = 'academic' OR position = 'non-academic')";
        } else {
            $conditions[] = "position = ?";
            $params[]     = $election['target_position'];
        }
    }
}

$allowed_colleges    = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_colleges']    ?? ''))));
$allowed_courses     = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_courses']     ?? ''))));
$allowed_status      = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_status']      ?? ''))));
$allowed_departments = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_departments'] ?? ''))));

if (!empty($allowed_colleges) && !in_array('ALL', $allowed_colleges, true)) {
    $placeholders = implode(',', array_fill(0, count($allowed_colleges), '?'));
    $conditions[] = "UPPER(department) IN ($placeholders)";
    $params       = array_merge($params, $allowed_colleges);
}

if (!empty($allowed_departments) && !in_array('ALL', $allowed_departments, true)) {
    $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
    $conditions[] = "UPPER(department) IN ($placeholders)";
    $params       = array_merge($params, $allowed_departments);
}

if (!empty($allowed_courses) && !in_array('ALL', $allowed_courses, true)) {
    $fullNames   = mapCourseCodesToFullNames($allowed_courses);
    $course_list = [];
    foreach ($fullNames as $name) {
        $course_list[] = strtolower($name);
    }
    if (!empty($course_list)) {
        $placeholders = implode(',', array_fill(0, count($course_list), '?'));
        $conditions[] = "LOWER(course) IN ($placeholders)";
        $params       = array_merge($params, $course_list);
    }
}

if (!empty($allowed_status) && !in_array('ALL', $allowed_status, true)) {
    $placeholders = implode(',', array_fill(0, count($allowed_status), '?'));
    $conditions[] = "UPPER(status) IN ($placeholders)";
    $params       = array_merge($params, $allowed_status);
}

$sql = "SELECT COUNT(*) as total FROM users WHERE " . implode(' AND ', $conditions);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$totalEligibleVoters = (int)($stmt->fetch()['total'] ?? 0);

$turnoutPercentage = ($totalEligibleVoters > 0)
    ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1)
    : 0.0;

/* ==========================================================
   FETCH CANDIDATES & VOTES
   ========================================================== */
$sql = "
    SELECT 
        ec.id AS election_candidate_id,
        c.id AS candidate_id,
        CONCAT(c.first_name, ' ', c.last_name) AS candidate_name,
        c.photo,
        ec.position AS election_position,
        ec.position_id,
        COUNT(v.vote_id) AS vote_count
    FROM election_candidates ec
    JOIN candidates c ON ec.candidate_id = c.id
    LEFT JOIN votes v ON ec.election_id = v.election_id 
                   AND ec.candidate_id = v.candidate_id
    WHERE ec.election_id = ?
    GROUP BY ec.id, c.id, c.first_name, c.last_name, c.photo, ec.position, ec.position_id
    ORDER BY ec.position, vote_count DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$electionId]);
$candidatesWithVotes = $stmt->fetchAll();

/* ==========================================================
   GROUP BY POSITION & WINNER LOGIC
   ========================================================== */
$candidatesByPosition = [];
$positionKeys         = [];

foreach ($candidatesWithVotes as $candidate) {
    $displayPosition = $candidate['election_position'];
    $positionId      = (int)($candidate['position_id'] ?? 0);

    if ($positionId > 0) {
        $key = (string)$positionId;
    } else {
        $key = $displayPosition;
    }

    $candidate['position_key'] = $key;

    if (!isset($candidatesByPosition[$displayPosition])) {
        $candidatesByPosition[$displayPosition] = [];
    }
    $candidatesByPosition[$displayPosition][] = $candidate;

    $positionKeys[$key] = true;
}

/* ==========================================================
   LOAD position_types (max_votes) FOR WINNERS
   ========================================================== */
$positionTypes = [];

foreach (array_keys($positionKeys) as $key) {
    $isPositionId = is_numeric($key) && (int)$key > 0;

    if ($isPositionId) {
        $stmt = $pdo->prepare("
            SELECT allow_multiple, max_votes
            FROM position_types
            WHERE position_id = ? AND position_name = ''
        ");
        $stmt->execute([(int)$key]);
    } else {
        $stmt = $pdo->prepare("
            SELECT allow_multiple, max_votes
            FROM position_types
            WHERE position_id = 0 AND position_name = ?
        ");
        $stmt->execute([$key]);
    }

    $row = $stmt->fetch();

    if (!$row) {
        $row = [
            'allow_multiple' => 0,
            'max_votes'      => 1,
        ];
    }

    $positionTypes[$key] = [
        'allow_multiple' => (bool)$row['allow_multiple'],
        'max_votes'      => (int)$row['max_votes'],
    ];
}

/* ==========================================================
   INCLUDE SIDEBAR (same as voters_dashboard)
   ========================================================== */
include 'voters_sidebar.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Election Results - <?= htmlspecialchars($election['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .winner-card {
      border: 2px solid var(--cvsu-green-light);
      box-shadow: 0 4px 15px rgba(30, 111, 70, 0.35); /* green glow */
      background-color: #ECFDF5; /* light green tint */
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">
  <!-- Main Content -->
  <main class="flex-1 px-4 py-6 md:px-8 md:py-8 md:ml-64 pb-16">
    <div class="w-full max-w-6xl mx-auto space-y-6">

    <header class="bg-[var(--cvsu-green-dark)] text-white p-4 md:p-6 shadow-md rounded-lg">
      <div class="flex flex-col gap-2">
        
        <!-- Row 1: burger + title (left) AND badge (right) -->
        <div class="flex items-start justify-between gap-3">
          <!-- Left cluster: burger + labels -->
          <div class="flex items-start">
            <button class="sm:hidden text-white mr-3 mt-0.5" onclick="toggleSidebar()">
              <i class="fas fa-bars text-xl"></i>
            </button>
            <div>
              <p class="text-[11px] uppercase tracking-widest text-green-100 leading-tight">
                Election Results
              </p>
              <h1 class="text-xl sm:text-2xl md:text-3xl font-bold leading-tight">
                <?= htmlspecialchars($election['title']) ?>
              </h1>
            </div>
          </div>

          <!-- Right: Results Released badge -->
          <div class="flex items-start justify-end">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-semibold bg-green-100 text-[var(--cvsu-green-dark)] whitespace-nowrap">
              <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-2"></span>
              Results Released
            </span>
          </div>
        </div>

        <!-- Row 2: Released on... (under the title) -->
        <p class="text-green-100 text-xs sm:text-sm mt-1 ml-7 sm:ml-0">
          Released on:
          <span class="font-semibold">
            <?= !empty($election['results_released_at'])
                  ? date("F j, Y g:i A", strtotime($election['results_released_at']))
                  : 'N/A' ?>
          </span>
        </p>

      </div>
    </header>

      <!-- Summary Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-4 flex items-center">
          <div class="flex-shrink-0 mr-3">
            <i class="fas fa-users text-blue-600 text-xl"></i>
          </div>
          <div>
            <p class="text-xs font-medium text-blue-700">Eligible Voters</p>
            <p class="text-xl font-bold text-blue-900"><?= number_format($totalEligibleVoters) ?></p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-green-100 p-4 flex items-center">
          <div class="flex-shrink-0 mr-3">
            <i class="fas fa-check-circle text-green-600 text-xl"></i>
          </div>
          <div>
            <p class="text-xs font-medium text-green-700">Votes Cast</p>
            <p class="text-xl font-bold text-green-900"><?= number_format($totalVotesCast) ?></p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-purple-100 p-4 flex items-center">
          <div class="flex-shrink-0 mr-3">
            <i class="fas fa-percentage text-purple-600 text-xl"></i>
          </div>
          <div>
            <p class="text-xs font-medium text-purple-700">Turnout</p>
            <p class="text-xl font-bold text-purple-900"><?= $turnoutPercentage ?>%</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-yellow-100 p-4 flex items-center">
          <div class="flex-shrink-0 mr-3">
            <i class="fas fa-user-friends text-yellow-600 text-xl"></i>
          </div>
          <div>
            <p class="text-xs font-medium text-yellow-700">Total Candidates</p>
            <p class="text-xl font-bold text-yellow-900"><?= count($candidatesWithVotes) ?></p>
          </div>
        </div>
      </div>

      <!-- Results per Position -->
      <div class="space-y-6">
        <?php if (empty($candidatesByPosition)): ?>
          <div class="bg-white rounded-xl shadow p-4 sm:p-6 text-center">
            <p class="text-gray-600 text-sm">
              No candidates found for this election.
            </p>
          </div>
        <?php else: ?>
          <?php foreach ($candidatesByPosition as $positionName => $candidates): ?>
            <?php
              usort($candidates, function($a, $b) {
                  return $b['vote_count'] <=> $a['vote_count'];
              });

              $totalVotesForPosition = array_sum(array_column($candidates, 'vote_count'));

              $firstCandidate = $candidates[0] ?? null;
              $positionKey    = $firstCandidate['position_key'] ?? $positionName;
              $typeInfo       = $positionTypes[$positionKey] ?? ['allow_multiple' => false, 'max_votes' => 1];

              $maxVotesAllowed = $typeInfo['max_votes'] > 0 ? $typeInfo['max_votes'] : 1;

              $nonZero = array_values(array_filter($candidates, function($c) {
                  return (int)$c['vote_count'] > 0;
              }));

              $winnerVoteThreshold = null;

              if (!empty($nonZero)) {
                  if (count($nonZero) <= $maxVotesAllowed) {
                      $winnerVoteThreshold = (int)end($nonZero)['vote_count'];
                  } else {
                      $winnerVoteThreshold = (int)$nonZero[$maxVotesAllowed - 1]['vote_count'];
                  }
              }
            ?>

            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5">
              <!-- Position Header -->
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4 pb-3 border-b border-gray-100">
                <div>
                  <h2 class="text-lg sm:text-xl font-bold text-[var(--cvsu-green-dark)]">
                    <?= htmlspecialchars($positionName) ?>
                  </h2>
                  <p class="text-xs sm:text-sm text-gray-500 mt-1">
                    <?= count($candidates) ?> candidate<?= count($candidates) != 1 ? 's' : '' ?> • 
                    <?= number_format($totalVotesForPosition) ?> total vote<?= $totalVotesForPosition != 1 ? 's' : '' ?>
                  </p>
                  <?php if ($maxVotesAllowed > 1): ?>
                    <p class="text-xs text-[var(--cvsu-green)] mt-1">
                      Top <?= $maxVotesAllowed ?> candidate<?= $maxVotesAllowed > 1 ? 's' : '' ?> (plus any ties) are considered winners for this position.
                    </p>
                  <?php else: ?>
                    <p class="text-xs text-gray-500 mt-1">
                      Highest-vote candidate is considered the winner for this position.
                    </p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Candidate List -->
              <div class="space-y-3">
              <?php foreach ($candidates as $index => $data): ?>
                <?php
                  $candidateName  = $data['candidate_name'];
                  $candidatePhoto = $data['photo'];
                  $votes          = (int)$data['vote_count'];

                  $percentage = $totalVotesForPosition > 0
                      ? round(($votes / $totalVotesForPosition) * 100, 1)
                      : 0;

                  $isWinner = ($winnerVoteThreshold !== null && $votes >= $winnerVoteThreshold && $votes > 0);
                  $rank     = $index + 1;
                ?>

                <article class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 sm:p-4 rounded-lg border transition-all
                              <?= $isWinner ? 'winner-card' : 'border-gray-200 bg-white' ?>">

                  <!-- LEFT: rank + avatar + name + tags -->
                  <div class="flex items-center gap-3 w-full sm:w-auto">
                    <!-- rank circle -->
                    <div class="flex-shrink-0">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                  <?= $isWinner ? 'bg-[var(--cvsu-green)] text-white' : 'bg-gray-300 text-gray-800' ?>">
                        <?= $rank ?>
                      </div>
                    </div>

                    <!-- avatar -->
                    <div class="flex-shrink-0">
                      <?php if (!empty($candidatePhoto)): ?>
                        <img src="<?= htmlspecialchars($candidatePhoto) ?>"
                            alt="<?= htmlspecialchars($candidateName) ?>"
                            class="w-12 h-12 sm:w-14 sm:h-14 rounded-full object-cover border-2 border-white shadow">
                      <?php else: ?>
                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-gray-200 border-2 border-white shadow flex items-center justify-center">
                          <span class="text-gray-600 font-semibold text-lg">
                            <?= strtoupper(substr($candidateName, 0, 1)) ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- name + rank pill + winner pill -->
                    <div class="flex flex-col">
                      <h3 class="text-sm sm:text-base font-semibold text-gray-900">
                        <?= htmlspecialchars($candidateName) ?>
                      </h3>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                    <?= $isWinner ? 'bg-[var(--cvsu-green)] text-white' : 'bg-gray-100 text-gray-600' ?>">
                          #<?= $rank ?> in <?= htmlspecialchars($positionName) ?>
                        </span>
                        <?php if ($isWinner): ?>
                          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold 
                                      bg-[var(--cvsu-green)] text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Winner
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <!-- RIGHT: votes + percentage + bar -->
                  <div class="flex-1 w-full">
                    <div class="flex items-center justify-between text-xs sm:text-sm mb-1">
                      <span class="text-gray-600">
                        <?= number_format($votes) ?> vote<?= $votes != 1 ? 's' : '' ?>
                      </span>
                      <span class="font-semibold text-gray-800">
                        <?= $percentage ?>%
                      </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                      <div class="h-2.5 rounded-full 
                                  <?= $isWinner 
                                      ? 'bg-gradient-to-r from-[var(--cvsu-green)] to-[var(--cvsu-green-light)]' 
                                      : 'bg-gradient-to-r from-gray-400 to-gray-300' ?>"
                          style="width: <?= $percentage ?>%"></div>
                    </div>
                  </div>

                </article>
              <?php endforeach; ?>
              </div>
            </section>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Back Button -->
      <div class="mt-6 flex justify-center">
        <a href="voters_dashboard.php" 
           class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
          ← Back to Dashboard
        </a>
      </div>

    </div>
  </main>
</div>

<script>
  // Same behavior as in voters_dashboard.php
  function toggleSidebar() {
    const btn = document.getElementById('sidebarToggle');
    if (btn) {
      btn.click();
    }
  }
</script>
</body>
</html>
