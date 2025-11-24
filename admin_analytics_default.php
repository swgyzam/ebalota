<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Shared scope / analytics helpers ---
require_once __DIR__ . '/includes/analytics_scopes.php';

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Fetch basic user info
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

$role = $userInfo['role'] ?? '';

if ($role !== 'admin' && $role !== 'super_admin') {
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   FIND THIS ADMIN'S OTHERS-DEFAULT SCOPE SEAT
   ========================================================== */

$mySeat   = null;
$odSeats  = getScopeSeats($pdo, SCOPE_OTHERS_DEFAULT);

foreach ($odSeats as $seat) {
    if ((int) $seat['admin_user_id'] === $userId) {
        $mySeat = $seat;
        break;
    }
}

if (!$mySeat) {
    // This admin has no Others-Default scope seat
    header('Location: admin_analytics.php');
    exit();
}

$scopeId   = (int) $mySeat['scope_id'];   // owner_scope_id for elections/voters
$scopeType = $mySeat['scope_type'];       // 'Others-Default'

/* ==========================================================
   ELECTION SELECTION & SCOPE GUARD
   ========================================================== */

$electionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($electionId <= 0) {
    header('Location: admin_analytics.php');
    exit();
}

// Ensure election belongs to this Others-Default seat
$scopedElections    = getScopedElections($pdo, SCOPE_OTHERS_DEFAULT, $scopeId);
$allowedElectionIds = array_map('intval', array_column($scopedElections, 'election_id'));

if (!in_array($electionId, $allowedElectionIds, true)) {
    $_SESSION['toast_message'] = 'You are not allowed to view analytics for this Others-Default election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

// Fetch full election row
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$electionId]);
$election = $stmt->fetch();

if (!$election) {
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   ELECTION STATUS
   ========================================================== */

$now   = new DateTime();
$start = new DateTime($election['start_datetime']);
$end   = new DateTime($election['end_datetime']);

if ($now < $start) {
    $status = 'upcoming';
} elseif ($now >= $start && $now <= $end) {
    $status = 'ongoing';
} else {
    $status = 'completed';
}

/* ==========================================================
   ELIGIBLE VOTERS (OTHERS-DEFAULT UNDER THIS SCOPE)
   ========================================================== */

$yearEnd = $election['end_datetime'] ?? null;

// All voters attached to this Others-Default scope seat
$scopedVoters = getScopedVoters(
    $pdo,
    SCOPE_OTHERS_DEFAULT,
    $scopeId,
    [
        'year_end'      => $yearEnd,
        'include_flags' => true,
    ]
);
// They are already: role='voter', is_other_member=1, owner_scope_id = $scopeId

$eligibleVotersForElection = [];
foreach ($scopedVoters as $v) {
    $eligibleVotersForElection[$v['user_id']] = $v;
}
$totalEligibleVoters = count($eligibleVotersForElection);

/* ==========================================================
   VOTES: DISTINCT VOTERS WHO VOTED (INTERSECT WITH ELIGIBLE SET)
   ========================================================== */

$stmt = $pdo->prepare("SELECT DISTINCT voter_id FROM votes WHERE election_id = ?");
$stmt->execute([$electionId]);
$votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$votedSet = array_flip($votedIds);

$totalVotesCast = 0;
foreach ($eligibleVotersForElection as $uid => $_row) {
    if (isset($votedSet[$uid])) {
        $totalVotesCast++;
    }
}

$turnoutPercentage = $totalEligibleVoters > 0
    ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1)
    : 0.0;

/* ==========================================================
   WINNERS BY POSITION + FILTER MAPS
   ========================================================== */

$sql = "
   SELECT 
       ec.position,
       c.id AS candidate_id,
       CONCAT(c.first_name, ' ', c.last_name) AS candidate_name,
       COUNT(v.vote_id) AS vote_count
   FROM election_candidates ec
   JOIN candidates c ON ec.candidate_id = c.id
   LEFT JOIN votes v 
          ON ec.election_id = v.election_id 
         AND ec.candidate_id = v.candidate_id
   WHERE ec.election_id = ?
   GROUP BY ec.position, c.id, c.first_name, c.last_name
   ORDER BY ec.position, vote_count DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$electionId]);
$allCandidates = $stmt->fetchAll();

// Group by position for winners
$winnersByPosition = [];
foreach ($allCandidates as $candidate) {
    $position = $candidate['position'];
    if (!isset($winnersByPosition[$position])) {
        $winnersByPosition[$position] = [];
    }
    $winnersByPosition[$position][] = $candidate;
}

// Determine winners (handle ties)
foreach ($winnersByPosition as $position => &$candidates) {
    if (empty($candidates)) continue;
    
    $maxVotes = $candidates[0]['vote_count'];
    $winners  = [];
    
    foreach ($candidates as $candidate) {
        if ($candidate['vote_count'] == $maxVotes && $maxVotes > 0) {
            $winners[] = $candidate;
        } else {
            break;
        }
    }
    $candidates = $winners;
}
unset($candidates);

// Build winner lookup + position list for filters
$winnerKeyMap   = [];  // "POSITION|CANDIDATE_ID" => true
$positionTieMap = [];  // "POSITION" => bool (tie?)
$allPositions   = [];

foreach ($winnersByPosition as $position => $winners) {
    if (!in_array($position, $allPositions, true)) {
        $allPositions[] = $position;
    }

    $isTie = count($winners) > 1;
    $positionTieMap[$position] = $isTie;

    foreach ($winners as $w) {
        $key = $position . '|' . (int) $w['candidate_id'];
        $winnerKeyMap[$key] = true;
    }
}
sort($allPositions);

/* ==========================================================
   TURNOUT BREAKDOWN (COLLEGE / STATUS)
   ========================================================== */

// raw buckets keyed by college||status
$rawBuckets = [];

foreach ($eligibleVotersForElection as $v) {
    $college   = $v['department'] ?: 'UNSPECIFIED'; // college/department code or name
    $statusStr = $v['status'] ?: 'Not Specified';
    $uid       = $v['user_id'];

    $key = $college . '||' . $statusStr;
    if (!isset($rawBuckets[$key])) {
        $rawBuckets[$key] = [
            'college'        => $college,
            'status'         => $statusStr,
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }

    $rawBuckets[$key]['eligible_count']++;

    if (isset($votedSet[$uid])) {
        $rawBuckets[$key]['voted_count']++;
    }
}

// convert to list with per-row turnout %
$turnoutRows = [];
foreach ($rawBuckets as $entry) {
    $pct = $entry['eligible_count'] > 0
        ? round(($entry['voted_count'] / $entry['eligible_count']) * 100, 1)
        : 0.0;
    $entry['turnout_percentage'] = $pct;
    $turnoutRows[] = $entry;
}

// Aggregated per college
$collegeMap = [];
foreach ($turnoutRows as $row) {
    $college = $row['college'];
    if (!isset($collegeMap[$college])) {
        $collegeMap[$college] = [
            'college'        => $college,
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }
    $collegeMap[$college]['eligible_count'] += $row['eligible_count'];
    $collegeMap[$college]['voted_count']    += $row['voted_count'];
}

$collegeData = [];
foreach ($collegeMap as $college => $agg) {
    $pct = $agg['eligible_count'] > 0
        ? round(($agg['voted_count'] / $agg['eligible_count']) * 100, 1)
        : 0.0;
    $agg['turnout_percentage'] = $pct;
    $collegeData[] = $agg;
}

// Aggregated per status
$statusMap = [];
foreach ($turnoutRows as $row) {
    $st = $row['status'];
    if (!isset($statusMap[$st])) {
        $statusMap[$st] = [
            'status'         => $st,
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }
    $statusMap[$st]['eligible_count'] += $row['eligible_count'];
    $statusMap[$st]['voted_count']    += $row['voted_count'];
}

$statusData = [];
foreach ($statusMap as $st => $agg) {
    $pct = $agg['eligible_count'] > 0
        ? round(($agg['voted_count'] / $agg['eligible_count']) * 100, 1)
        : 0.0;
    $agg['turnout_percentage'] = $pct;
    $statusData[] = $agg;
}

// Lists for dropdowns
$collegesList = array_values(array_unique(array_map(fn($r) => $r['college'], $collegeData)));
$statusesList = array_values(array_unique(array_map(fn($r) => $r['status'],  $statusData)));

/* ==========================================================
   OTHERS-DEFAULT TURNOUT BY YEAR (ALL ELECTIONS FOR THIS SEAT)
   ========================================================== */

// All Others-Default voters for this seat (all years)
$scopedAllVoters = getScopedVoters(
    $pdo,
    SCOPE_OTHERS_DEFAULT,
    $scopeId,
    [
        'year_end'      => null,
        'include_flags' => true,
    ]
);

// Turnout by year for this Others-Default seat
$turnoutDataByYear = computeTurnoutByYear(
    $pdo,
    SCOPE_OTHERS_DEFAULT,  // scope type (matches election_scope_type)
    $scopeId,              // this admin's seat
    $scopedAllVoters,
    [
        'year_from' => null,
        'year_to'   => null,
    ]
);

// Years present
$allTurnoutYears = array_keys($turnoutDataByYear);
sort($allTurnoutYears);

$defaultYear = (int) date('Y');
$minYear     = $allTurnoutYears ? min($allTurnoutYears) : $defaultYear;
$maxYear     = $allTurnoutYears ? max($allTurnoutYears) : $defaultYear;

// Year range (?from_year=YYYY&to_year=YYYY)
$fromYear = isset($_GET['from_year']) && ctype_digit((string) $_GET['from_year'])
    ? (int) $_GET['from_year']
    : $minYear;

$toYear = isset($_GET['to_year']) && ctype_digit((string) $_GET['to_year'])
    ? (int) $_GET['to_year']
    : $maxYear;

// Clamp
if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear   < $fromYear) $toYear  = $fromYear;

// Subset for [fromYear..toYear]
$turnoutRangeData = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($turnoutDataByYear[$y])) {
        $turnoutRangeData[$y] = $turnoutDataByYear[$y];
    } else {
        $turnoutRangeData[$y] = [
            'year'           => $y,
            'total_voted'    => 0,
            'total_eligible' => 0,
            'turnout_rate'   => 0.0,
            'election_count' => 0,
            'growth_rate'    => 0.0,
        ];
    }
}

// Recompute growth_rate within selected range
$prevY = null;
foreach ($turnoutRangeData as $y => &$row) {
    if ($prevY === null) {
        $row['growth_rate'] = 0.0;
    } else {
        $prevRate = $turnoutRangeData[$prevY]['turnout_rate'] ?? 0.0;
        $row['growth_rate'] = $prevRate > 0
            ? round(($row['turnout_rate'] - $prevRate) / $prevRate * 100, 1)
            : 0.0;
    }
    $prevY = $y;
}
unset($row);

// Years for focus-year dropdown
$turnoutYearsForSelect = array_keys($turnoutDataByYear);
sort($turnoutYearsForSelect);

// Focus year for summary cards (default: year of this election's start)
$ctxYear = isset($_GET['ctx_year']) && ctype_digit((string) $_GET['ctx_year'])
    ? (int) $_GET['ctx_year']
    : (int) date('Y', strtotime($election['start_datetime']));

$currentYearTurnout  = $turnoutDataByYear[$ctxYear]     ?? null;
$previousYearTurnout = $turnoutDataByYear[$ctxYear - 1] ?? null;

/* ==========================================================
   PER-ELECTION TURNOUT STATS FOR FOCUS YEAR (ctxYear)
   ========================================================== */

$ctxYearElections = getScopedElections(
    $pdo,
    SCOPE_OTHERS_DEFAULT,
    $scopeId,
    [
        'from_year' => $ctxYear,
        'to_year'   => $ctxYear,
    ]
);

$ctxElectionStats = [];
foreach ($ctxYearElections as $erow) {
    $eid    = (int) $erow['election_id'];
    $etitle = $erow['title'];
    $eend   = $erow['end_datetime'] ?: ($ctxYear . '-12-31 23:59:59');

    // Eligible: all Others-Default voters under this seat as of this election's end
    $seatVoters = getScopedVoters(
        $pdo,
        SCOPE_OTHERS_DEFAULT,
        $scopeId,
        [
            'year_end'      => $eend,
            'include_flags' => true,
        ]
    );

    $eligibleForElection = [];
    foreach ($seatVoters as $v) {
        $eligibleForElection[$v['user_id']] = true;
    }
    $totalEligible = count($eligibleForElection);

    // Voted: distinct voters who voted in this election (Others-Default only)
    $stmt = $pdo->prepare("
        SELECT DISTINCT v.voter_id
        FROM votes v
        JOIN users u ON u.user_id = v.voter_id
        WHERE v.election_id = :eid
          AND u.role            = 'voter'
          AND u.is_other_member = 1
          AND u.owner_scope_id  = :sid
    ");
    $stmt->execute([
        ':eid' => $eid,
        ':sid' => $scopeId,
    ]);
    $votedIdsElection = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $votedSetElection = array_flip($votedIdsElection);

    $totalVoted = 0;
    foreach ($eligibleForElection as $uid => $_) {
        if (isset($votedSetElection[$uid])) {
            $totalVoted++;
        }
    }

    $turnoutRate = $totalEligible > 0
        ? round(($totalVoted / $totalEligible) * 100, 1)
        : 0.0;

    $ctxElectionStats[] = [
        'election_id'    => $eid,
        'title'          => $etitle,
        'year'           => (int) date('Y', strtotime($erow['start_datetime'])),
        'total_eligible' => $totalEligible,
        'total_voted'    => $totalVoted,
        'turnout_rate'   => $turnoutRate,
        'status'         => $erow['status'],
    ];
}

$pageTitle = 'Others-Default Election Analytics';

include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($election['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .analytics-card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .analytics-card:hover {
      transform: translateY(-2px);
    }
    .winner-badge {
      background: linear-gradient(135deg, #FFD700, #FFA500);
      color: white;
    }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td {
      padding: 0.75rem;
      text-align: left;
    }
    .data-table th {
      background-color: #f3f4f6;
      font-weight: 600;
      color: #374151;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    .data-table td {
      border-bottom: 1px solid #e5e7eb;
    }
    .data-table tr:hover {
      background-color: #f9fafb;
    }
    .data-table .text-center { text-align: center; }
    .turnout-bar-container {
      width: 100%;
      height: 8px;
      background-color: #e5e7eb;
      border-radius: 4px;
      overflow: hidden;
    }
    .turnout-bar {
      height: 100%;
      border-radius: 4px;
    }
    .turnout-high   { background-color: #10b981; }
    .turnout-medium { background-color: #f59e0b; }
    .turnout-low    { background-color: #ef4444; }
    .table-container::-webkit-scrollbar { height: 8px; }
    .table-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    .table-container::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 4px;
    }
    .table-container::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    .loading-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s, visibility 0.3s;
    }
    .loading-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 5px solid #f3f3f3;
      border-top: 5px solid var(--cvsu-green);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .no-data-message {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      text-align: center;
      color: #6b7280;
    }
    .no-data-message i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: #d1d5db;
    }
    .no-data-message p {
      font-size: 1.125rem;
      font-weight: 500;
    }
    .chart-wrapper {
      position: relative;
      height: 100%;
      width: 100%;
    }
    .chart-no-data {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background-color: rgba(249, 250, 251, 0.9);
      z-index: 10;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">

  <main class="flex-1 p-6 md:p-8 md:ml-64">
    <div class="max-w-7xl mx-auto">

      <!-- Election Information Header -->
      <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl shadow-xl overflow-hidden mb-8 border border-gray-100">
        <div class="bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] p-6 relative">
          <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
          <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-5 rounded-full -ml-12 -mb-12"></div>

          <div class="flex flex-col md:flex-row md:items-center md:justify-between relative z-10">
            <div class="flex-1">
              <div class="flex items-center mb-3">
                <div class="bg-white bg-opacity-20 p-3 rounded-xl mr-4 shadow-md">
                  <i class="fas fa-chart-line text-white text-3xl"></i>
                </div>
                <div>
                  <h1 class="text-3xl font-bold text-white leading-tight">
                    <?= htmlspecialchars($pageTitle) ?>
                  </h1>
                  <p class="text-green-100 text-lg font-medium">
                    <?= htmlspecialchars($election['title']) ?>
                  </p>
                </div>
              </div>
            </div>

            <div class="mt-4 md:mt-0 flex items-center space-x-3">
              <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold shadow-md
                    <?= $status === 'completed' ? 'bg-green-500 text-white' : 
                       ($status === 'ongoing' ? 'bg-blue-500 text-white' : 'bg-yellow-600 text-white') ?>">
                <?php if ($status === 'completed'): ?>
                  <i class="fas fa-check-circle mr-2"></i> Completed
                <?php elseif ($status === 'ongoing'): ?>
                  <i class="fas fa-clock mr-2"></i> Ongoing
                <?php else: ?>
                  <i class="fas fa-hourglass-start mr-2"></i> Upcoming
                <?php endif; ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Top summary cards -->
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-green-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-users text-green-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Eligible Voters</p>
                  <p class="text-2xl font-bold text-gray-800"><?= number_format($totalEligibleVoters) ?></p>
                </div>
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-blue-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-check-circle text-blue-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Votes Cast</p>
                  <p class="text-2xl font-bold text-gray-800"><?= number_format($totalVotesCast) ?></p>
                </div>
              </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-purple-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-percentage text-purple-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Turnout Rate</p>
                  <p class="text-2xl font-bold text-gray-800"><?= $turnoutPercentage ?>%</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Winners / Candidates Section -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <h2 class="text-xl font-semibold text-gray-800">
              <i class="fas fa-trophy text-yellow-500 mr-2"></i>Candidate Summary
            </h2>

            <?php if (!empty($allCandidates)): ?>
              <div class="mt-3 md:mt-0 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-center">
                  <label for="candidateDisplayMode" class="mr-2 text-sm font-medium text-gray-700">
                    Show:
                  </label>
                  <select id="candidateDisplayMode"
                          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                    <option value="winners" selected>Winners only</option>
                    <option value="all">All candidates</option>
                  </select>
                </div>

                <div class="flex items-center">
                  <label for="candidatePositionFilter" class="mr-2 text-sm font-medium text-gray-700">
                    Position:
                  </label>
                  <select id="candidatePositionFilter"
                          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                    <option value="all">All positions</option>
                    <?php foreach ($allPositions as $pos): ?>
                      <option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="p-6">
          <?php if (empty($allCandidates)): ?>
            <div class="no-data-message">
              <i class="fas fa-users"></i>
              <p>No candidate data available</p>
            </div>
          <?php else: ?>
            <div id="candidateCardsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <?php foreach ($allCandidates as $cand): ?>
                <?php
                  $position      = $cand['position'];
                  $candidateId   = (int) $cand['candidate_id'];
                  $candidateName = $cand['candidate_name'];
                  $voteCount     = (int) $cand['vote_count'];
                  $winnerKey     = $position . '|' . $candidateId;
                  $isWinner      = !empty($winnerKeyMap[$winnerKey]);
                  $isTiePosition = !empty($positionTieMap[$position]);
                ?>
                <div class="candidate-summary-card analytics-card bg-gradient-to-br from-yellow-50 to-white rounded-xl border border-yellow-200 p-6 shadow-sm"
                     data-winner="<?= $isWinner ? '1' : '0' ?>"
                     data-position="<?= htmlspecialchars($position) ?>">
                  <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg mr-4
                                <?= $isWinner ? 'winner-badge' : 'bg-gray-200 text-gray-600' ?>">
                      <i class="fas <?= $isWinner ? 'fa-trophy' : 'fa-user' ?>"></i>
                    </div>
                    <div>
                      <h3 class="text-lg font-bold text-gray-800">
                        <?= htmlspecialchars($candidateName) ?>
                      </h3>
                      <p class="text-sm text-gray-600">
                        <?= htmlspecialchars($position) ?>
                      </p>
                    </div>
                  </div>

                  <div class="flex justify-between items-center">
                    <div>
                      <p class="text-2xl font-bold text-gray-800"><?= number_format($voteCount) ?></p>
                      <p class="text-sm text-gray-500">votes</p>
                    </div>

                    <?php if ($isWinner && $voteCount > 0): ?>
                      <?php if ($isTiePosition): ?>
                        <span class="text-xs font-bold bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">
                          TIE
                        </span>
                      <?php else: ?>
                        <span class="text-xs font-bold bg-green-100 text-green-800 px-2 py-1 rounded-full">
                          WINNER
                        </span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Voter Turnout Analytics Section -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-800">
            <i class="fas fa-chart-pie text-green-600 mr-2"></i>Voter Turnout Analytics
          </h2>
          <p class="text-sm text-gray-500 mt-1">
            Others-Default Election (breakdown by college/department and status)
          </p>
        </div>

        <div class="p-6">
          <?php if (empty($turnoutRows)): ?>
            <div class="no-data-message">
              <i class="fas fa-chart-bar"></i>
              <p>No voters and votes data available</p>
            </div>
          <?php else: ?>
            <!-- Filter Section -->
            <div class="mb-6">
              <div class="flex flex-wrap items-center justify-center gap-6 mb-4">
                <div class="flex items-center">
                  <label for="breakdownType" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
                  <select id="breakdownType"
                          class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="college">College/Department</option>
                    <option value="status">Status</option>
                  </select>
                </div>

                <div class="flex items-center">
                  <label id="filterLabel" for="filterSelect" class="mr-3 text-sm font-medium text-gray-700">Select College/Department:</label>
                  <select id="filterSelect"
                          class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="all">All Colleges/Departments</option>
                    <?php foreach ($collegesList as $college): ?>
                      <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Chart Section -->
            <div class="mb-12">
              <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Turnout Visualization</h3>
              <div class="bg-gray-50 p-6 rounded-xl shadow-sm">
                <div class="h-96">
                  <div class="chart-wrapper">
                    <canvas id="turnoutChart"></canvas>
                    <div id="chartNoData" class="chart-no-data" style="display: none;">
                      <i class="fas fa-chart-bar text-gray-400 text-4xl mb-3"></i>
                      <p class="text-gray-600 text-lg">No voters and votes data available</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detailed Breakdown Section -->
            <div class="mt-12">
              <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Detailed Breakdown</h3>
              <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto table-container">
                  <div id="tableContainer" class="w-full"></div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Others-Default Turnout Comparison (Year Range + Per-Election) -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mt-8">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
              <h2 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-chart-bar text-blue-600 mr-2"></i>
                Others-Default Turnout Comparison (All Elections)
              </h2>
              <p class="text-sm text-gray-500 mt-1">
                Compare <strong>turnout</strong> over time for all Others-Default elections under this admin.
              </p>
            </div>
            <div class="mt-3 md:mt-0 flex items-center space-x-3">
              <label for="ctxYearSelector" class="text-sm font-medium text-gray-700">Focus year:</label>
              <select id="ctxYearSelector"
                      class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                <?php foreach ($turnoutYearsForSelect as $y): ?>
                  <option value="<?= $y ?>" <?= $y == $ctxYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="p-6">
          <!-- Summary cards (focus year vs previous year) -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 rounded-lg border" style="background-color: rgba(99,102,241,0.05); border-color:#6366F1;">
              <div class="flex items-center">
                <div class="p-3 rounded-lg mr-4 bg-indigo-500">
                  <i class="fas fa-percentage text-white text-xl"></i>
                </div>
                <div>
                  <p class="text-sm text-indigo-600"><?= $ctxYear ?> Turnout</p>
                  <p class="text-2xl font-bold text-indigo-800">
                    <?= $currentYearTurnout['turnout_rate'] ?? 0 ?>%
                  </p>
                </div>
              </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color:#8B5CF6;">
              <div class="flex items-center">
                <div class="p-3 rounded-lg mr-4 bg-purple-500">
                  <i class="fas fa-percentage text-white text-xl"></i>
                </div>
                <div>
                  <p class="text-sm text-purple-600"><?= $ctxYear - 1 ?> Turnout</p>
                  <p class="text-2xl font-bold text-purple-800">
                    <?= $previousYearTurnout['turnout_rate'] ?? 0 ?>%
                  </p>
                </div>
              </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(16,185,129,0.05); border-color:#10B981;">
              <div class="flex items-center">
                <div class="p-3 rounded-lg mr-4 bg-green-500">
                  <i class="fas fa-chart-line text-white text-xl"></i>
                </div>
                <div>
                  <p class="text-sm text-green-600">Growth Rate</p>
                  <p class="text-2xl font-bold text-green-800">
                    <?php
                      $ct = $currentYearTurnout['turnout_rate']  ?? 0;
                      $pt = $previousYearTurnout['turnout_rate'] ?? 0;
                      echo $pt > 0
                        ? ((($ct - $pt) / $pt > 0 ? '+' : '') . round((($ct - $pt) / $pt) * 100, 1) . '%')
                        : '0%';
                    ?>
                  </p>
                </div>
              </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color:#3B82F6;">
              <div class="flex items-center">
                <div class="p-3 rounded-lg mr-4 bg-blue-500">
                  <i class="fas fa-vote-yea text-white text-xl"></i>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Elections (<?= $ctxYear ?>)</p>
                  <p class="text-2xl font-bold text-blue-800">
                    <?= $currentYearTurnout['election_count'] ?? 0 ?>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Data series select -->
          <div class="mb-4">
            <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-6">
              <div class="flex items-center">
                <label for="ctxDataSeriesSelect" class="mr-3 text-sm font-medium text-gray-700">Data Series:</label>
                <select id="ctxDataSeriesSelect"
                        class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                  <option value="elections">Elections vs Turnout</option>
                  <option value="voters">Voters vs Turnout</option>
                </select>
              </div>

              <div class="flex items-center">
                <label for="ctxBreakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
                <select id="ctxBreakdownSelect"
                        class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                  <option value="year">Year</option>
                  <option value="election">Election (current year)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Chart -->
          <div class="chart-container" style="height: 400px;">
            <canvas id="ctxElectionsVsTurnoutChart"></canvas>
          </div>

          <!-- Year range selector -->
          <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-medium text-blue-800 mb-2">Turnout Analysis – Year Range</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="ctxFromYear" class="block text-sm font-medium text-blue-800">From year</label>
                <select id="ctxFromYear" class="mt-1 p-2 border rounded w-full">
                  <?php foreach ($allTurnoutYears as $y): ?>
                    <option value="<?= $y ?>" <?= $y == $fromYear ? 'selected' : '' ?>><?= $y ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="ctxToYear" class="block text-sm font-medium text-blue-800">To year</label>
                <select id="ctxToYear" class="mt-1 p-2 border rounded w-full">
                  <?php foreach ($allTurnoutYears as $y): ?>
                    <option value="<?= $y ?>" <?= $y == $toYear ? 'selected' : '' ?>><?= $y ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <p class="text-xs text-blue-700 mt-2">
              Select a start and end year to compare Others-Default turnout. Years with no elections in this range will appear with zero values.
            </p>
          </div>

          <!-- Table container -->
          <div id="ctxTurnoutBreakdownTable" class="mt-6 overflow-x-auto"></div>
        </div>
      </div>

      <!-- Back Button -->
      <div class="mt-6">
        <a href="admin_analytics.php" 
           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          <i class="fas fa-arrow-left mr-2"></i>
          Back to Election Analytics
        </a>
      </div>

    </div>
  </main>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
  <div class="loading-spinner"></div>
</div>

<!-- Candidate Winners / All Toggle + Position Filter -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modeSelect     = document.getElementById('candidateDisplayMode');
  const positionSelect = document.getElementById('candidatePositionFilter');
  const cards          = document.querySelectorAll('.candidate-summary-card');

  if (!modeSelect || !positionSelect || cards.length === 0) return;

  function applyCandidateFilters() {
    const mode     = modeSelect.value;      // 'winners' | 'all'
    const position = positionSelect.value;  // 'all' | specific position

    cards.forEach(card => {
      const isWinner = card.getAttribute('data-winner') === '1';
      const cardPos  = card.getAttribute('data-position') || '';

      if (mode === 'winners' && !isWinner) {
        card.style.display = 'none';
        return;
      }

      if (position !== 'all' && cardPos !== position) {
        card.style.display = 'none';
        return;
      }

      card.style.display = 'block';
    });
  }

  modeSelect.addEventListener('change',     applyCandidateFilters);
  positionSelect.addEventListener('change', applyCandidateFilters);

  applyCandidateFilters();
});
</script>

<!-- Turnout Chart + Table (Others-Default, this election) -->
<script>
const collegeData    = <?= json_encode($collegeData) ?>;
const statusData     = <?= json_encode($statusData) ?>;

const collegesList   = <?= json_encode($collegesList) ?>;
const statusesList   = <?= json_encode($statusesList) ?>;

let turnoutChartInstance = null;

let currentState = {
  breakdownType: 'college', // 'college' | 'status'
  filterValue:   'all'      // selected college or status
};

document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  currentState.breakdownType = urlParams.get('breakdown') || 'college';
  currentState.filterValue   = urlParams.get('filter')    || 'all';

  document.getElementById('breakdownType').value = currentState.breakdownType;
  updateFilterDropdown();

  if (currentState.filterValue !== 'all') {
    document.getElementById('filterSelect').value = currentState.filterValue;
  }

  if (typeof Chart === 'undefined') {
    const script = document.createElement('script');
    script.src   = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload  = () => updateView(false);
    script.onerror = () => showChartNoDataMessage('Error loading chart library');
    document.head.appendChild(script);
  } else {
    updateView(false);
  }

  document.getElementById('breakdownType')?.addEventListener('change', function() {
    updateState({ breakdownType: this.value, filterValue: 'all' });
  });

  document.getElementById('filterSelect')?.addEventListener('change', function() {
    updateState({ filterValue: this.value });
  });

  window.addEventListener('popstate', function(event) {
    if (event.state) {
      currentState = event.state;
      document.getElementById('breakdownType').value = currentState.breakdownType;
      updateFilterDropdown();
      document.getElementById('filterSelect').value = currentState.filterValue;
      updateView(false);
    }
  });
});

// In the JavaScript section, find the updateFilterDropdown function and replace it with this:

function updateFilterDropdown() {
  const filterSelect = document.getElementById('filterSelect');
  const filterLabel  = document.getElementById('filterLabel');

  filterSelect.innerHTML = '';

  if (currentState.breakdownType === 'college') {
    filterLabel.textContent = 'Select College/Department:';
    filterSelect.innerHTML  = '<option value="all">All Colleges/Departments</option>';
    collegesList.forEach(col => {
      const opt = document.createElement('option');
      opt.value = col;
      opt.textContent = col;
      filterSelect.appendChild(opt);
    });
    currentState.filterValue = 'all';
  } else {
    // For status breakdown, we still want to filter by college/department
    filterLabel.textContent = 'Select College/Department:';
    filterSelect.innerHTML  = '<option value="all">All Colleges/Departments</option>';
    collegesList.forEach(col => {
      const opt = document.createElement('option');
      opt.value = col;
      opt.textContent = col;
      filterSelect.appendChild(opt);
    });
    currentState.filterValue = 'all';
  }
}

// Replace the getFilteredData function with this:

function getFilteredData() {
  let data;

  if (currentState.breakdownType === 'college') {
    data = collegeData;
    if (currentState.filterValue !== 'all') {
      data = data.filter(item => item.college === currentState.filterValue);
    }
  } else {
    // For status breakdown, we need to handle college filtering
    if (currentState.filterValue === 'all') {
      // Show all statuses across all colleges
      data = statusData;
    } else {
      // Show statuses for a specific college
      // We need to filter the original turnoutRows by college and then group by status
      const filteredRows = turnoutRows.filter(row => row.college === currentState.filterValue);
      
      // Group by status
      const statusMap = {};
      filteredRows.forEach(row => {
        const st = row.status;
        if (!statusMap[st]) {
          statusMap[st] = {
            status:         st,
            eligible_count: 0,
            voted_count:    0,
          };
        }
        statusMap[st].eligible_count += row.eligible_count;
        statusMap[st].voted_count    += row.voted_count;
      });
      
      // Convert to array and calculate turnout percentages
      data = [];
      for (const st in statusMap) {
        const agg = statusMap[st];
        const pct = agg.eligible_count > 0
          ? Math.round((agg.voted_count / agg.eligible_count) * 100 * 10) / 10
          : 0.0;
        agg.turnout_percentage = pct;
        data.push(agg);
      }
    }
  }

  return data;
}

// Add this line after the statusData declaration to make turnoutRows available in JavaScript:
const turnoutRows   = <?= json_encode($turnoutRows) ?>;

function updateState(newState) {
  showLoading();
  currentState = { ...currentState, ...newState };

  const url = new URL(window.location);
  url.searchParams.set('breakdown', currentState.breakdownType);
  url.searchParams.set('filter',    currentState.filterValue);
  window.history.pushState(currentState, '', url);

  updateView();
}

function updateView(showLoader = true) {
  const work = () => {
    const data = getFilteredData();
    updateChart(data);
    generateTable(data);
    hideLoading();
  };
  if (showLoader) setTimeout(work, 300); else work();
}

function updateChart(data) {
  const canvas   = document.getElementById('turnoutChart');
  const noDataEl = document.getElementById('chartNoData');

  if (!canvas) return;

  if (!data || data.length === 0) {
    canvas.style.display   = 'none';
    if (noDataEl) noDataEl.style.display = 'flex';
    return;
  }

  canvas.style.display   = 'block';
  if (noDataEl) noDataEl.style.display = 'none';

  const ctx = canvas.getContext('2d');

  const labels = data.map(item =>
    currentState.breakdownType === 'college'
      ? item.college
      : item.status
  );
  const eligibleData = data.map(item => parseInt(item.eligible_count) || 0);
  const votedData    = data.map(item => parseInt(item.voted_count)    || 0);

  if (turnoutChartInstance) {
    turnoutChartInstance.destroy();
  }

  const count = labels.length;
  let barThickness, categorySpacing, fontSize, maxBarThickness;

  if (count <= 5) {
    barThickness = 0.8; categorySpacing = 0.2; fontSize = 14; maxBarThickness = 80;
  } else if (count <= 10) {
    barThickness = 0.6; categorySpacing = 0.3; fontSize = 12; maxBarThickness = 60;
  } else if (count <= 20) {
    barThickness = 0.4; categorySpacing = 0.4; fontSize = 10; maxBarThickness = 40;
  } else {
    barThickness = 0.3; categorySpacing = 0.5; fontSize = 9;  maxBarThickness = 30;
  }

  turnoutChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Eligible Voters',
          data: eligibleData,
          backgroundColor: 'rgba(54, 162, 235, 0.7)',
          borderColor:     'rgba(54, 162, 235, 1)',
          borderWidth: 1,
          borderRadius: 4,
          barPercentage: barThickness,
          categoryPercentage: 1 - categorySpacing,
          maxBarThickness
        },
        {
          label: 'Voted',
          data: votedData,
          backgroundColor: 'rgba(75, 192, 192, 0.7)',
          borderColor:     'rgba(75, 192, 192, 1)',
          borderWidth: 1,
          borderRadius: 4,
          barPercentage: barThickness,
          categoryPercentage: 1 - categorySpacing,
          maxBarThickness
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: { font: { size: 14 }, padding: 20 }
        },
        title: {
          display: true,
          text: `Voter Turnout by ${currentState.breakdownType === 'college' ? 'College/Department' : 'Status'}`,
          font: { size: 18, weight: 'bold' },
          padding: { top: 10, bottom: 30 }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleFont: { size: 14 },
          bodyFont:  { size: 13 },
          padding: 12,
          cornerRadius: 4,
          callbacks: {
            title: (ctx) => ctx[0].label,
            label: (ctx) => {
              let label = ctx.dataset.label || '';
              if (label) label += ': ';
              if (ctx.parsed.y !== null) {
                label += new Intl.NumberFormat('en-US', {
                  style: 'decimal', maximumFractionDigits: 0
                }).format(ctx.parsed.y);
              }
              return label;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0, font: { size: 12 } },
          grid: { color: 'rgba(0, 0, 0, 0.1)' },
          title: {
            display: true,
            text: 'Number of Voters',
            font: { size: 14, weight: 'bold' }
          }
        },
        x: {
          ticks: {
            font: { size: fontSize },
            maxRotation: 0,
            minRotation: 0,
            autoSkip: true,
            maxTicksLimit: 20
          },
          grid: { display: false },
          title: {
            display: true,
            text: currentState.breakdownType === 'college' ? 'College/Department' : 'Status',
            font: { size: 14, weight: 'bold' }
          }
        }
      },
      animation: { duration: 1000, easing: 'easeOutQuart' },
      layout: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
    }
  });
}

function generateTable(data) {
  const tableContainer = document.getElementById('tableContainer');
  tableContainer.innerHTML = '';

  if (!data || data.length === 0) {
    const noDataDiv = document.createElement('div');
    noDataDiv.className = 'no-data-message';
    noDataDiv.innerHTML = `
      <i class="fas fa-table text-gray-400 text-4xl mb-3"></i>
      <p class="text-gray-600 text-lg">No voters and votes data available</p>
    `;
    tableContainer.appendChild(noDataDiv);
    return;
  }

  const table = document.createElement('table');
  table.className = 'data-table';

  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');

  if (currentState.breakdownType === 'college') {
    headerRow.innerHTML = `
      <th style="width: 40%">College/Department</th>
      <th style="width: 20%" class="text-center">Eligible</th>
      <th style="width: 20%" class="text-center">Voted</th>
      <th style="width: 20%" class="text-center">Turnout %</th>
    `;
  } else {
    headerRow.innerHTML = `
      <th style="width: 40%">Status</th>
      <th style="width: 20%" class="text-center">Eligible</th>
      <th style="width: 20%" class="text-center">Voted</th>
      <th style="width: 20%" class="text-center">Turnout %</th>
    `;
  }

  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');

  data.forEach(item => {
    const row = document.createElement('tr');
    if (currentState.breakdownType === 'college') {
      row.innerHTML = `
        <td style="width: 40%">${item.college}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.eligible_count)}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.voted_count)}</td>
        <td style="width: 20%" class="text-center">${createTurnoutBar(item.turnout_percentage)}</td>
      `;
    } else {
      row.innerHTML = `
        <td style="width: 40%">${item.status}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.eligible_count)}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.voted_count)}</td>
        <td style="width: 20%" class="text-center">${createTurnoutBar(item.turnout_percentage)}</td>
      `;
    }
    tbody.appendChild(row);
  });

  table.appendChild(tbody);
  tableContainer.appendChild(table);
}

function createTurnoutBar(percentage) {
  let barColor = 'turnout-low';
  if (percentage >= 70)      barColor = 'turnout-high';
  else if (percentage >= 40) barColor = 'turnout-medium';

  return `
    <div class="flex flex-col items-center">
      <div class="turnout-bar-container w-32">
        <div class="turnout-bar ${barColor}" style="width: ${percentage}%"></div>
      </div>
      <span class="text-sm font-bold text-gray-700 mt-1">${percentage}%</span>
    </div>
  `;
}

function numberFormat(num) {
  return new Intl.NumberFormat('en-US').format(num);
}

function showLoading() {
  document.getElementById('loadingOverlay')?.classList.add('active');
}

function hideLoading() {
  document.getElementById('loadingOverlay')?.classList.remove('active');
}

function showChartNoDataMessage(message = 'No data available for the selected filter') {
  const canvas   = document.getElementById('turnoutChart');
  const noDataEl = document.getElementById('chartNoData');

  if (canvas) canvas.style.display = 'none';
  if (noDataEl) {
    noDataEl.style.display = 'flex';
  }
}
</script>

<!-- Others-Default Elections/Voters vs Turnout (Year + Per-Election) -->
<script>
// PHP → JS data
const ctxTurnoutYears   = <?= json_encode(array_keys($turnoutRangeData)) ?>;
const ctxElectionCounts = <?= json_encode(array_column($turnoutRangeData, 'election_count')) ?>;
const ctxTotalEligible  = <?= json_encode(array_column($turnoutRangeData, 'total_eligible')) ?>;
const ctxTotalVoted     = <?= json_encode(array_column($turnoutRangeData, 'total_voted')) ?>;
const ctxTurnoutRates   = <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>;

// Per-election stats (focus year)
const ctxElectionStats = <?= json_encode($ctxElectionStats) ?>;

const ctxChartData = {
  elections: {
    year: {
      labels: ctxTurnoutYears,
      electionCounts: ctxElectionCounts,
      turnoutRates:   ctxTurnoutRates
    },
    election: {
      labels:        ctxElectionStats.map(e => e.title),
      electionCounts: ctxElectionStats.map(e => 1), // one bar per election
      turnoutRates:   ctxElectionStats.map(e => e.turnout_rate)
    }
  },
  voters: {
    year: {
      labels:         ctxTurnoutYears,
      eligibleCounts: ctxTotalEligible,
      turnoutRates:   ctxTurnoutRates
    },
    election: {
      labels:         ctxElectionStats.map(e => e.title),
      eligibleCounts: ctxElectionStats.map(e => e.total_eligible),
      turnoutRates:   ctxElectionStats.map(e => e.turnout_rate)
    }
  }
};

let ctxCurrentSeries    = 'elections'; // 'elections' | 'voters'
let ctxCurrentBreakdown = 'year';      // 'year' | 'election'
let ctxChartInstance    = null;

document.addEventListener('DOMContentLoaded', function () {
  const seriesSelect    = document.getElementById('ctxDataSeriesSelect');
  const breakdownSelect = document.getElementById('ctxBreakdownSelect');
  const fromYearSelect  = document.getElementById('ctxFromYear');
  const toYearSelect    = document.getElementById('ctxToYear');
  const ctxYearSelector = document.getElementById('ctxYearSelector');

  // Focus year change
  ctxYearSelector?.addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('ctx_year', this.value);
    window.location.href = url.toString();
  });

  // Year range change
  function updateYearRangeParams() {
    const url  = new URL(window.location.href);
    const from = fromYearSelect.value;
    const to   = toYearSelect.value;

    if (from) url.searchParams.set('from_year', from); else url.searchParams.delete('from_year');
    if (to)   url.searchParams.set('to_year',   to);   else url.searchParams.delete('to_year');

    window.location.href = url.toString();
  }
  fromYearSelect?.addEventListener('change', updateYearRangeParams);
  toYearSelect?.addEventListener('change',   updateYearRangeParams);

  seriesSelect?.addEventListener('change', function () {
    ctxCurrentSeries = this.value;
    renderCtxChartAndTable();
  });

  breakdownSelect?.addEventListener('change', function () {
    ctxCurrentBreakdown = this.value;
    renderCtxChartAndTable();
  });

  renderCtxChartAndTable();
});

function renderCtxChartAndTable() {
  const canvas = document.getElementById('ctxElectionsVsTurnoutChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  if (ctxChartInstance) ctxChartInstance.destroy();

  let labels   = [];
  let leftData = [];
  let rightData= [];
  let titleText;
  let leftLabel;

  if (ctxCurrentSeries === 'elections') {
    if (ctxCurrentBreakdown === 'year') {
      labels    = ctxChartData.elections.year.labels;
      leftData  = ctxChartData.elections.year.electionCounts;
      rightData = ctxChartData.elections.year.turnoutRates;
      leftLabel = 'Number of Elections';
      titleText = 'Elections vs Turnout Rate (By Year)';
    } else {
      labels    = ctxChartData.elections.election.labels;
      leftData  = ctxChartData.elections.election.electionCounts;
      rightData = ctxChartData.elections.election.turnoutRates;
      leftLabel = 'Elections';
      titleText = 'Elections vs Turnout Rate (By Election, current year)';
    }
  } else {
    if (ctxCurrentBreakdown === 'year') {
      labels    = ctxChartData.voters.year.labels;
      leftData  = ctxChartData.voters.year.eligibleCounts;
      rightData = ctxChartData.voters.year.turnoutRates;
      leftLabel = 'Eligible Voters';
      titleText = 'Voters vs Turnout Rate (By Year)';
    } else {
      labels    = ctxChartData.voters.election.labels;
      leftData  = ctxChartData.voters.election.eligibleCounts;
      rightData = ctxChartData.voters.election.turnoutRates;
      leftLabel = 'Eligible Voters (per election)';
      titleText = 'Voters vs Turnout Rate (By Election, current year)';
    }
  }

  ctxChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: leftLabel,
          data: leftData,
          backgroundColor: '#1E6F46',
          borderColor: '#154734',
          borderWidth: 1,
          borderRadius: 4,
          yAxisID: 'y'
        },
        {
          label: 'Turnout Rate (%)',
          data: rightData,
          backgroundColor: '#FFD166',
          borderColor: '#F59E0B',
          borderWidth: 1,
          borderRadius: 4,
          yAxisID: 'y1'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: { font: { size: 12 }, padding: 15 }
        },
        title: {
          display: true,
          text: titleText,
          font: { size: 16, weight: 'bold' },
          padding: { top: 10, bottom: 20 }
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleFont: { size: 14 },
          bodyFont:  { size: 13 },
          padding: 12,
          callbacks: {
            label: (context) => {
              const dsLabel = context.dataset.label || '';
              if (dsLabel.includes('Turnout')) {
                return `${dsLabel}: ${context.raw}%`;
              }
              return `${dsLabel}: ${context.raw.toLocaleString()}`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          position: 'left',
          title: {
            display: true,
            text: leftLabel,
            font: { size: 14, weight: 'bold' }
          }
        },
        y1: {
          beginAtZero: true,
          max: 100,
          position: 'right',
          title: {
            display: true,
            text: 'Turnout Rate (%)',
            font: { size: 14, weight: 'bold' }
          },
          ticks: { callback: v => v + '%' },
          grid: { drawOnChartArea: false }
        },
        x: { grid: { display: false } }
      }
    }
  });

  renderCtxYearTable();
}

function renderCtxYearTable() {
  const container = document.getElementById('ctxTurnoutBreakdownTable');
  if (!container) return;

  container.innerHTML = '';

  // If breakdown = election but no election stats, show message
  if (ctxCurrentBreakdown === 'election') {
    if (!ctxElectionStats || ctxElectionStats.length === 0) {
      container.innerHTML = `
        <div class="table-no-data">
          <i class="fas fa-table text-gray-400 text-4xl mb-3"></i>
          <p class="text-gray-600 text-lg">No elections found for this year.</p>
        </div>`;
      return;
    }

    const table = document.createElement('table');
    table.className = 'data-table';

    const thead = document.createElement('thead');
    thead.innerHTML = `
      <tr>
        <th>Election</th>
        <th class="text-center">Eligible Voters</th>
        <th class="text-center">Voters Participated</th>
        <th class="text-center">Turnout Rate</th>
        <th class="text-center">Status</th>
      </tr>`;
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    ctxElectionStats.forEach(row => {
      const rate = row.turnout_rate ?? 0;
      const cls  = rate >= 70 ? 'text-green-600'
                 : rate >= 40 ? 'text-yellow-600'
                              : 'text-red-600';
      const tr   = document.createElement('tr');
      tr.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap font-medium">${row.title}</td>
        <td class="px-6 py-4 whitespace-nowrap text-center">${(row.total_eligible ?? 0).toLocaleString()}</td>
        <td class="px-6 py-4 whitespace-nowrap text-center">${(row.total_voted ?? 0).toLocaleString()}</td>
        <td class="px-6 py-4 whitespace-nowrap text-center"><span class="${cls}">${rate}%</span></td>
        <td class="px-6 py-4 whitespace-nowrap text-center">${row.status || ''}</td>`;
      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    container.appendChild(table);
    return;
  }

  // Year breakdown table
  const labels    = ctxTurnoutYears;
  const counts    = ctxElectionCounts;
  const eligibles = ctxTotalEligible;
  const voted     = ctxTotalVoted;
  const rates     = ctxTurnoutRates;

  if (!labels || labels.length === 0) {
    container.innerHTML = `
      <div class="table-no-data">
        <i class="fas fa-table text-gray-400 text-4xl mb-3"></i>
        <p class="text-gray-600 text-lg">No Others-Default turnout data available.</p>
      </div>`;
    return;
  }

  const table = document.createElement('table');
  table.className = 'data-table';

  const thead = document.createElement('thead');
  thead.innerHTML = `
    <tr>
      <th>Year</th>
      <th class="text-center">Elections</th>
      <th class="text-center">Eligible Voters</th>
      <th class="text-center">Voters Participated</th>
      <th class="text-center">Turnout Rate</th>
    </tr>`;
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  labels.forEach((year, idx) => {
    const rate = rates[idx] ?? 0;
    const cls  = rate >= 70 ? 'text-green-600'
               : rate >= 40 ? 'text-yellow-600'
                            : 'text-red-600';
    const tr   = document.createElement('tr');
    tr.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap font-medium">${year}</td>
      <td class="px-6 py-4 whitespace-nowrap text-center">${(counts[idx] ?? 0).toLocaleString()}</td>
      <td class="px-6 py-4 whitespace-nowrap text-center">${(eligibles[idx] ?? 0).toLocaleString()}</td>
      <td class="px-6 py-4 whitespace-nowrap text-center">${(voted[idx] ?? 0).toLocaleString()}</td>
      <td class="px-6 py-4 whitespace-nowrap text-center"><span class="${cls}">${rate}%</span></td>`;
    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  container.appendChild(table);
}
</script>

</body>
</html>
