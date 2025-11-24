<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/analytics_scopes.php';
require_once __DIR__ . '/includes/super_admin_helpers.php';
requireSuperAdmin();

// ===== DB Connection =====
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
    die("Database connection failed: " . $e->getMessage());
}

// ------------------------------------------------------------------
// Shared helper used by Scope Detail Panel
// ------------------------------------------------------------------
if (!function_exists('groupByField')) {
    function groupByField(array $rows, string $field): array {
        $agg = [];
        foreach ($rows as $r) {
            $key = $r[$field] ?? '';
            if ($key === null) {
                $key = '';
            }
            if (!isset($agg[$key])) {
                $agg[$key] = 0;
            }
            $agg[$key]++;
        }
        arsort($agg);
        return $agg;
    }
}

// ------------------------------------------------------
// 1. Global basic stats
// ------------------------------------------------------

// Total voters (all voter accounts)
 $stmt = $pdo->query("SELECT COUNT(*) AS total_voters FROM users WHERE role = 'voter'");
 $totalVoters = (int)($stmt->fetch()['total_voters'] ?? 0);

// Total elections (all scope types)
 $stmt = $pdo->query("SELECT COUNT(*) AS total_elections FROM elections");
 $totalElections = (int)($stmt->fetch()['total_elections'] ?? 0);

// Active admins (admin + super_admin)
 $stmt = $pdo->query("
    SELECT COUNT(*) AS active_admins
    FROM users
    WHERE role IN ('admin','super_admin')
      AND admin_status = 'active'
");
 $activeAdmins = (int)($stmt->fetch()['active_admins'] ?? 0);

// Elections per year (for simple chart)
 $stmt = $pdo->query("
    SELECT YEAR(start_datetime) AS year, COUNT(*) AS count
    FROM elections
    GROUP BY YEAR(start_datetime)
    ORDER BY YEAR(start_datetime)
");
 $electionsPerYear = $stmt->fetchAll();
 $yearsForChart    = array_column($electionsPerYear, 'year');
 $countsForChart   = array_map('intval', array_column($electionsPerYear, 'count'));

// ------------------------------------------------------
// 2. Global voters by category (using helpers)
// ------------------------------------------------------

// Academic-Student (college-based students)
 $acadStudentsGlobal = getScopedVoters(
    $pdo,
    SCOPE_ACAD_STUDENT,
    null,
    ['year_end' => null, 'include_flags' => false]
);
 $totalAcadStudents = count($acadStudentsGlobal);

// Non-Academic-Student (org-based student groups)
 $nonAcadStudentsGlobal = getScopedVoters(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    null,
    ['year_end' => null, 'include_flags' => false]
);
 $totalNonAcadStudents = count($nonAcadStudentsGlobal);

// Academic-Faculty
 $acadFacultyGlobal = getScopedVoters(
    $pdo,
    SCOPE_ACAD_FACULTY,
    null,
    ['year_end' => null, 'include_flags' => false]
);
 $totalAcadFaculty = count($acadFacultyGlobal);

// Non-Academic-Employee
 $nonAcadEmployeeGlobal = getScopedVoters(
    $pdo,
    SCOPE_NONACAD_EMPLOYEE,
    null,
    ['year_end' => null, 'include_flags' => false]
);
 $totalNonAcadEmployee = count($nonAcadEmployeeGlobal);

// COOP members (Others-COOP)
 $coopGlobal = getScopedVoters(
    $pdo,
    SCOPE_OTHERS_COOP,
    null,
    ['year_end' => null, 'include_flags' => true]
);
 $totalCoop = count($coopGlobal);

// Others-Default members
 $othersDefaultGlobal = getScopedVoters(
    $pdo,
    SCOPE_OTHERS_DEFAULT,
    null,
    ['year_end' => null, 'include_flags' => false]
);
 $totalOthersDefault = count($othersDefaultGlobal);

// ------------------------------------------------------
// Global Voter Breakdown (with segment filter)
// ------------------------------------------------------

// Category breakdown (base totals)
 $categoryLabels = [
    'Academic Students',
    'Non-Academic Students',
    'Academic Faculty',
    'Non-Academic Employees',
    'Others-Default',
    'COOP (MIGS)',
];
 $categoryCountsBase = [
    $totalAcadStudents,
    $totalNonAcadStudents,
    $totalAcadFaculty,
    $totalNonAcadEmployee,
    $totalOthersDefault,
    $totalCoop,
];

// Segment filter: all / students / faculty / employees
 $allowedSegments = ['all', 'students', 'faculty', 'employees'];
 $segment = $_GET['segment'] ?? 'all';
if (!in_array($segment, $allowedSegments, true)) {
    $segment = 'all';
}

// Apply segment to category counts (we keep labels fixed, just zero-out others)
 $categoryCountsSegmented = $categoryCountsBase;

switch ($segment) {
    case 'students':
        // Keep student-related categories, zero-out faculty & employees
        // Index: 0=AcadStud,1=NonAcadStud,2=Faculty,3=Employees,4=Others-Default,5=COOP
        $categoryCountsSegmented[2] = 0; // Academic Faculty
        $categoryCountsSegmented[3] = 0; // Non-Academic Employees
        break;

    case 'faculty':
        foreach ($categoryCountsSegmented as $i => $v) {
            $categoryCountsSegmented[$i] = ($i === 2) ? $v : 0;
        }
        break;

    case 'employees':
        foreach ($categoryCountsSegmented as $i => $v) {
            $categoryCountsSegmented[$i] = ($i === 3) ? $v : 0;
        }
        break;

    case 'all':
    default:
        // no change
        break;
}

// Global voters by position (student / academic / non-academic / etc.)
 $whereSegment = "role = 'voter'";
switch ($segment) {
    case 'students':
        $whereSegment .= " AND position = 'student'";
        break;
    case 'faculty':
        $whereSegment .= " AND position = 'academic'";
        break;
    case 'employees':
        $whereSegment .= " AND position = 'non-academic'";
        break;
    case 'all':
    default:
        // no extra condition
        break;
}

 $sqlPositions = "
    SELECT COALESCE(NULLIF(position,''),'Unspecified') AS position, COUNT(*) AS count
    FROM users
    WHERE $whereSegment
    GROUP BY COALESCE(NULLIF(position,''),'Unspecified')
    ORDER BY count DESC
";
 $stmt = $pdo->query($sqlPositions);
 $positionRows   = $stmt->fetchAll();
 $positionLabels = array_column($positionRows, 'position');
 $positionCounts = array_map('intval', array_column($positionRows, 'count'));

// Limit positions chart to top 10 for readability
 $positionLabelsLimited = array_slice($positionLabels, 0, 10);
 $positionCountsLimited = array_slice($positionCounts, 0, 10);

// ------------------------------------------------------
// 2b. Global turnout by year (all elections)
// ------------------------------------------------------
 $globalTurnoutByYear = getGlobalTurnoutByYear($pdo, null); // all years in DB
 $turnoutYears        = array_keys($globalTurnoutByYear);
 $turnoutRates        = array_column($globalTurnoutByYear, 'turnout_rate');

// Year range filtering for turnout analytics
 $allTurnoutYears = array_keys($globalTurnoutByYear);

// Always include 2024 and current year (2025) in the available years
 $currentYear = (int)date('Y');
 $year2024 = 2024;

if (!in_array($year2024, $allTurnoutYears)) {
    $allTurnoutYears[] = $year2024;
    sort($allTurnoutYears);
}

if (!in_array($currentYear, $allTurnoutYears)) {
    $allTurnoutYears[] = $currentYear;
    sort($allTurnoutYears);
}

 $minYear = !empty($allTurnoutYears) ? min($allTurnoutYears) : $year2024;
 $maxYear = !empty($allTurnoutYears) ? max($allTurnoutYears) : $currentYear;

// Set default year range to 2024-2025 if available
 $fromYear = isset($_GET['from_year']) && ctype_digit((string)$_GET['from_year'])
    ? (int)$_GET['from_year']
    : $year2024;

 $toYear = isset($_GET['to_year']) && ctype_digit((string)$_GET['to_year'])
    ? (int)$_GET['to_year']
    : $currentYear;

// Clamp to bounds
if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear > $maxYear) $toYear = $maxYear;
if ($toYear < $fromYear) $toYear = $fromYear;

// Build filtered turnout data for [fromYear..toYear]
 $filteredTurnoutByYear = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($globalTurnoutByYear[$y])) {
        // Ensure all required keys exist, set defaults if missing
        $filteredTurnoutByYear[$y] = array_merge([
            'year' => $y,
            'total_voted' => 0,
            'total_eligible' => 0,
            'turnout_rate' => 0.0,
            'election_count' => 0,
            'growth_rate' => 0.0,
        ], $globalTurnoutByYear[$y]);
    } else {
        $filteredTurnoutByYear[$y] = [
            'year' => $y,
            'total_voted' => 0,
            'total_eligible' => 0,
            'turnout_rate' => 0.0,
            'election_count' => 0,
            'growth_rate' => 0.0,
        ];
    }
}

// Update variables for chart and table
 $turnoutYears = array_keys($filteredTurnoutByYear);
 $turnoutRates = array_column($filteredTurnoutByYear, 'turnout_rate');

 $currentYearTurnout = $globalTurnoutByYear[$currentYear]['turnout_rate'] ?? 0.0;

// ------------------------------------------------------
// 3. Scope seat summaries (for "Scopes & Admin Overview")
// ------------------------------------------------------

// All scope seats from admin_scopes
 $scopeSeatsAll = getScopeSeats($pdo, null);

 $scopeSummaries = [];

foreach ($scopeSeatsAll as $seat) {
    $scopeType = $seat['scope_type'];
    $scopeId   = $seat['scope_id'];

    // Decide how to call scoped helpers per scope type
    $votersScopeId    = $scopeId;
    $electionsScopeId = $scopeId;

    // For COOP and CSG we treat them as *global* scopes (no per-owner filtering)
    if ($scopeType === SCOPE_OTHERS_COOP || $scopeType === SCOPE_SPECIAL_CSG) {
        $votersScopeId    = null;
        $electionsScopeId = null;
    }

    // Voters in this scope
    $scopedVoters = getScopedVoters($pdo, $scopeType, $votersScopeId, [
        'year_end'      => null,
        'include_flags' => false,
    ]);

    // Elections in this scope (by election_scope_type + owner_scope_id)
    $scopedElections = getScopedElections($pdo, $scopeType, $electionsScopeId, [
        'years_back' => 5,   // just to keep it bounded
    ]);

    // Last election (by start_datetime) if any
    $lastElectionDate = null;
    if (!empty($scopedElections)) {
        $first = $scopedElections[0];
        $lastElectionDate = $first['start_datetime'] ?? null;
    }

    $scopeSummaries[] = [
        'scope_id'         => $scopeId,
        'scope_type'       => $scopeType,
        'label'            => $seat['label'],
        'admin_name'       => $seat['admin_full_name'],
        'admin_email'      => $seat['admin_email'],
        'voters_count'     => count($scopedVoters),
        'elections_count'  => count($scopedElections),
        'last_election_at' => $lastElectionDate,
    ];
}

// Scope Type filter for Scopes & Admin Overview
 $scopeTypesAvailable = [];
foreach ($scopeSummaries as $s) {
    $scopeTypesAvailable[] = $s['scope_type'];
}
 $scopeTypesAvailable = array_values(array_unique($scopeTypesAvailable));
sort($scopeTypesAvailable);

 $scopeTypeFilter = isset($_GET['scope_type']) && $_GET['scope_type'] !== ''
    ? $_GET['scope_type']
    : 'all';

// Filtered list used in the Scopes & Admin Overview table
 $filteredScopeSummaries = array_values(array_filter(
    $scopeSummaries,
    function ($s) use ($scopeTypeFilter) {
        if ($scopeTypeFilter === 'all') {
            return true;
        }
        return $s['scope_type'] === $scopeTypeFilter;
    }
));

// ------------------------------------------------------
// 4. Scope Detail Panel (selection via ?scope_type&scope_id)
// ------------------------------------------------------

// Build detail view selection AFTER we already have $scopeSeatsAll
 $selectedScopeId   = isset($_GET['scope_id']) && ctype_digit((string)$_GET['scope_id'])
    ? (int)$_GET['scope_id']
    : null;
 $selectedScopeType = $_GET['scope_type'] ?? null;

 $selectedSeat                 = null;
 $selectedScopeVoters          = [];
 $selectedScopeElections       = [];
 $selectedScopeTurnout         = [];
 $selectedScopeLatestYear      = null;
 $selectedScopeLatestTurnout   = null;
 $selectedScopeLatestElection  = null;
 $selectedScopeBreakdown       = [];

if ($selectedScopeId && $selectedScopeType) {
    foreach ($scopeSeatsAll as $seat) {
        if ((int)$seat['scope_id'] === $selectedScopeId && $seat['scope_type'] === $selectedScopeType) {
            $selectedSeat = $seat;
            break;
        }
    }
}

if ($selectedSeat !== null) {
    // 1) VOTERS for this scope seat
    $selectedScopeVoters = getScopedVoters(
        $pdo,
        $selectedSeat['scope_type'],
        $selectedSeat['scope_id'],
        [
            'year_end'      => null,
            'include_flags' => false,
        ]
    );

    // 2) ELECTIONS for this scope seat (last 5 years)
    $selectedScopeElections = getScopedElections(
        $pdo,
        $selectedSeat['scope_type'],
        $selectedSeat['scope_id'],
        [
            'year_from' => date('Y') - 5,
            'year_to'   => date('Y'),
        ]
    );

    // 3) TURNOUT per year for this scope seat
    $selectedScopeTurnout = computeTurnoutByYear(
        $pdo,
        $selectedSeat['scope_type'],
        $selectedSeat['scope_id'],
        $selectedScopeVoters,
        [
            'year_from' => null,
            'year_to'   => null,
        ]
    );

    if (!empty($selectedScopeTurnout)) {
        $years = array_keys($selectedScopeTurnout);
        sort($years);
        $selectedScopeLatestYear    = end($years);
        $selectedScopeLatestTurnout = $selectedScopeTurnout[$selectedScopeLatestYear]['turnout_rate'] ?? 0;
    }

    if (!empty($selectedScopeElections)) {
        usort($selectedScopeElections, function ($a, $b) {
            return strcmp($b['start_datetime'], $a['start_datetime']);
        });
        $selectedScopeLatestElection = $selectedScopeElections[0];
    }

    // 4) SIMPLE BREAKDOWN – depende sa scope type kung ano gagamitin
    if (in_array($selectedSeat['scope_type'], [SCOPE_ACAD_STUDENT, SCOPE_ACAD_FACULTY, SCOPE_NONACAD_STUDENT, SCOPE_NONACAD_EMPLOYEE], true)) {
        $selectedScopeBreakdown = groupByField($selectedScopeVoters, 'department');
    } elseif (in_array($selectedSeat['scope_type'], [SCOPE_OTHERS_COOP, SCOPE_OTHERS_DEFAULT], true)) {
        $selectedScopeBreakdown = groupByField($selectedScopeVoters, 'department');
    } else {
        $selectedScopeBreakdown = groupByField($selectedScopeVoters, 'position');
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - Super Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      /* CVSU Colors */
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
      --cvsu-accent: #2D5F3F;
      --cvsu-light-accent: #4A7C59;
      
      /* Additional Colors for Better Visual Hierarchy */
      --cvsu-blue: #3B82F6;
      --cvsu-blue-light: #60A5FA;
      --cvsu-purple: #8B5CF6;
      --cvsu-purple-light: #A78BFA;
      --cvsu-red: #EF4444;
      --cvsu-orange: #F97316;
      --cvsu-teal: #14B8A6;
      --cvsu-indigo: #6366F1;
      --cvsu-pink: #EC4899;
      --cvsu-gray-light: #F3F4F6;
      --cvsu-gray: #9CA3AF;
      --cvsu-gray-dark: #4B5563;
    }
    
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb {
      background-color: var(--cvsu-green-light);
      border-radius: 3px;
    }
    
    .analytics-card { transition: transform .2s, box-shadow .2s; }
    .analytics-card:hover { transform: translateY(-2px); }
    
    .chart-container{position:relative;height:320px;width:100%;}
    
    .cvsu-gradient{
      background:linear-gradient(135deg,var(--cvsu-green-dark)0%,var(--cvsu-green)100%);
    }
    
    .cvsu-card{
      background-color:white;border-left:4px solid var(--cvsu-green);
      box-shadow:0 4px 6px rgba(0,0,0,.05);transition:all .3s;
    }
    
    .cvsu-card:hover{box-shadow:0 10px 15px rgba(0,0,0,.1);transform:translateY(-2px);}
    
    /* Color Classes */
    .bg-cvsu-green { background-color: var(--cvsu-green); }
    .bg-cvsu-green-dark { background-color: var(--cvsu-green-dark); }
    .bg-cvsu-green-light { background-color: var(--cvsu-green-light); }
    .bg-cvsu-yellow { background-color: var(--cvsu-yellow); }
    .bg-cvsu-blue { background-color: var(--cvsu-blue); }
    .bg-cvsu-blue-light { background-color: var(--cvsu-blue-light); }
    .bg-cvsu-purple { background-color: var(--cvsu-purple); }
    .bg-cvsu-purple-light { background-color: var(--cvsu-purple-light); }
    .bg-cvsu-red { background-color: var(--cvsu-red); }
    .bg-cvsu-orange { background-color: var(--cvsu-orange); }
    .bg-cvsu-teal { background-color: var(--cvsu-teal); }
    .bg-cvsu-indigo { background-color: var(--cvsu-indigo); }
    .bg-cvsu-pink { background-color: var(--cvsu-pink); }
    
    .text-cvsu-green { color: var(--cvsu-green); }
    .text-cvsu-green-dark { color: var(--cvsu-green-dark); }
    .text-cvsu-green-light { color: var(--cvsu-green-light); }
    .text-cvsu-yellow { color: var(--cvsu-yellow); }
    .text-cvsu-blue { color: var(--cvsu-blue); }
    .text-cvsu-blue-light { color: var(--cvsu-blue-light); }
    .text-cvsu-purple { color: var(--cvsu-purple); }
    .text-cvsu-purple-light { color: var(--cvsu-purple-light); }
    .text-cvsu-red { color: var(--cvsu-red); }
    .text-cvsu-orange { color: var(--cvsu-orange); }
    .text-cvsu-teal { color: var(--cvsu-teal); }
    .text-cvsu-indigo { color: var(--cvsu-indigo); }
    .text-cvsu-pink { color: var(--cvsu-pink); }
    
    .border-cvsu-green { border-color: var(--cvsu-green); }
    .border-cvsu-green-dark { border-color: var(--cvsu-green-dark); }
    .border-cvsu-green-light { border-color: var(--cvsu-green-light); }
    .border-cvsu-yellow { border-color: var(--cvsu-yellow); }
    .border-cvsu-blue { border-color: var(--cvsu-blue); }
    .border-cvsu-blue-light { border-color: var(--cvsu-blue-light); }
    .border-cvsu-purple { border-color: var(--cvsu-purple); }
    .border-cvsu-purple-light { border-color: var(--cvsu-purple-light); }
    .border-cvsu-red { border-color: var(--cvsu-red); }
    .border-cvsu-orange { border-color: var(--cvsu-orange); }
    .border-cvsu-teal { border-color: var(--cvsu-teal); }
    .border-cvsu-indigo { border-color: var(--cvsu-indigo); }
    .border-cvsu-pink { border-color: var(--cvsu-pink); }
    
    select:disabled {
      background-color: #f3f4f6;
      color: #9ca3af;
      cursor: not-allowed;
    }
    
    .label-disabled {
      color: #9ca3af;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">

<?php include 'super_admin_sidebar.php'; ?>

<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
  <h1 class="text-2xl font-bold text-white">
    SUPER ADMIN DASHBOARD
  </h1>
  <div class="text-white text-sm">
    Logged in as:
    <span class="font-semibold">
      <?= htmlspecialchars($_SESSION['email'] ?? ($_SESSION['username'] ?? '')) ?>
    </span>
  </div>
</header>

<main class="flex-1 pt-20 px-8 ml-64">

  <!-- Global summary cards (4 cards: voters, elections, admins, turnout) -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <!-- Total Voters -->
    <div class="cvsu-card p-6 rounded-xl">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Voters</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-green-dark);">
            <?= number_format($totalVoters) ?>
          </p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(30, 111, 70, 0.1);">
          <i class="fas fa-users text-2xl" style="color: var(--cvsu-green);"></i>
        </div>
      </div>
    </div>

    <!-- Total Elections -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-yellow);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-yellow);">
            <?= number_format($totalElections) ?>
          </p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(255, 209, 102, 0.1);">
          <i class="fas fa-vote-yea text-2xl" style="color: var(--cvsu-yellow);"></i>
        </div>
      </div>
    </div>

    <!-- Active Admins -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-blue);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Active Admins</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-blue);">
            <?= number_format($activeAdmins) ?>
          </p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(59, 130, 246, 0.1);">
          <i class="fas fa-user-shield text-2xl" style="color: var(--cvsu-blue);"></i>
        </div>
      </div>
    </div>

    <!-- Global Turnout (Current Year) -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-indigo);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">
            Global Turnout (<?= htmlspecialchars($currentYear) ?>)
          </h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-indigo);">
            <?= number_format($currentYearTurnout, 1) ?>%
          </p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(99, 102, 241, 0.1);">
          <i class="fas fa-chart-line text-2xl" style="color: var(--cvsu-indigo);"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Global Voter Breakdown (same pattern as admin dashboards: doughnut + bar) -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-white">Global Voter Breakdown</h2>
          <p class="text-xs text-white/80">
            All voters, broken down by category and position.
          </p>
        </div>

        <!-- Segment filter -->
        <form method="get" class="flex items-center gap-2 text-xs md:text-sm">
          <label for="segmentFilter" class="text-white/80 font-medium">
            Segment:
          </label>
          <select
            id="segmentFilter"
            name="segment"
            class="px-3 py-1.5 border border-white/40 rounded-lg bg-white/10 text-white text-xs md:text-sm focus:outline-none focus:ring-2 focus:ring-white"
            onchange="this.form.submit()"
          >
            <option value="all"      <?= $segment === 'all'      ? 'selected' : '' ?>>All voters</option>
            <option value="students" <?= $segment === 'students' ? 'selected' : '' ?>>Students only</option>
            <option value="faculty"  <?= $segment === 'faculty'  ? 'selected' : '' ?>>Faculty only</option>
            <option value="employees"<?= $segment === 'employees'? 'selected' : '' ?>>Employees only</option>
          </select>

          <!-- Preserve scope type filter so overview table state remains -->
          <input type="hidden" name="scope_type" value="<?= htmlspecialchars($scopeTypeFilter) ?>">
          <?php if ($selectedScopeId && $selectedScopeType): ?>
            <input type="hidden" name="scope_id" value="<?= (int)$selectedScopeId ?>">
            <input type="hidden" name="scope_type_detail" value="<?= htmlspecialchars($selectedScopeType) ?>">
          <?php endif; ?>
          
          <!-- Preserve year range filters -->
          <input type="hidden" name="from_year" value="<?= htmlspecialchars($fromYear) ?>">
          <input type="hidden" name="to_year" value="<?= htmlspecialchars($toYear) ?>">
        </form>
      </div>
    </div>

    <div class="p-6">
      <p class="text-[11px] text-gray-500 mb-2">
        Current segment:
        <span class="font-semibold">
          <?php
            switch ($segment) {
              case 'students':  echo 'Students only'; break;
              case 'faculty':   echo 'Faculty only';  break;
              case 'employees': echo 'Employees only'; break;
              default:          echo 'All voters';
            }
          ?>
        </span>
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Category doughnut -->
        <div class="p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Voters by Category</h3>
          <div class="chart-container" style="height: 260px;">
            <canvas id="globalCategoryChart"></canvas>
          </div>
        </div>

        <!-- Position bar chart -->
        <div class="p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Voters by Position (Top 10)</h3>
          <div class="chart-container" style="height: 260px;">
            <canvas id="globalPositionChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Simple tables under charts -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Category table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="px-4 py-2 bg-gray-50 border-b text-xs font-semibold text-gray-700">
            Categories
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-xs divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Category</th>
                  <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase">Voters</th>
                  <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase">Share</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <?php
                $totalAll = max(1, array_sum($categoryCountsSegmented));
                foreach ($categoryLabels as $idx => $label):
                  $cnt   = $categoryCountsSegmented[$idx];
                  $share = round($cnt / $totalAll * 100, 1);
                ?>
                  <tr>
                    <td class="px-4 py-2"><?= htmlspecialchars($label) ?></td>
                    <td class="px-4 py-2 text-right"><?= number_format($cnt) ?></td>
                    <td class="px-4 py-2 text-right"><?= $share ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Position table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="px-4 py-2 bg-gray-50 border-b text-xs font-semibold text-gray-700">
            Positions (Top 10)
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-xs divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Position</th>
                  <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase">Voters</th>
                  <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase">Share</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <?php
                $totPosAll = max(1, array_sum($positionCounts));
                foreach ($positionLabelsLimited as $idx => $label):
                  $cnt   = $positionCountsLimited[$idx] ?? 0;
                  $share = round($cnt / $totPosAll * 100, 1);
                ?>
                  <tr>
                    <td class="px-4 py-2"><?= htmlspecialchars($label) ?></td>
                    <td class="px-4 py-2 text-right"><?= number_format($cnt) ?></td>
                    <td class="px-4 py-2 text-right"><?= $share ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Global Elections per Year -->
  <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Global Elections per Year</h2>
    <div class="chart-container">
      <canvas id="globalElectionsChart"></canvas>
    </div>
  </div>

  <!-- Global Turnout Rate by Year -->
  <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Global Turnout Rate by Year</h2>
    
    <!-- Year Range Selector -->
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
      <h3 class="font-medium text-blue-800 mb-2">Turnout Analysis – Year Range</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label for="fromYear" class="block text-sm font-medium text-blue-800">From year</label>
          <select id="fromYear" name="from_year" class="mt-1 p-2 border rounded w-full" onchange="submitYearRange()">
            <?php foreach ($allTurnoutYears as $y): ?>
              <option value="<?= $y ?>" <?= ($y == $fromYear) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="toYear" class="block text-sm font-medium text-blue-800">To year</label>
          <select id="toYear" name="to_year" class="mt-1 p-2 border rounded w-full" onchange="submitYearRange()">
            <?php foreach ($allTurnoutYears as $y): ?>
              <option value="<?= $y ?>" <?= ($y == $toYear) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <p class="text-xs text-blue-700 mt-2">
        Select a start and end year to compare turnout. Years with no elections in this range will appear with zero values.
      </p>
    </div>
    
    <div class="chart-container">
      <canvas id="globalTurnoutChart"></canvas>
    </div>
    <p class="mt-3 text-xs text-gray-500">
      Turnout = distinct voters who participated in any election in that year ÷ all voters existing by December 31 of that year.
    </p>
    
    <!-- Turnout Table -->
    <div class="mt-6 bg-white border border-gray-200 rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Elections</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Eligible Students</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students Participated</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turnout Rate</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Growth</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php 
            // Calculate growth rates for the filtered range
            $prevYear = null;
            foreach ($filteredTurnoutByYear as $year => $data):
              $isPositive = ($data['growth_rate'] ?? 0) > 0;
              $trendIcon = $isPositive ? 'fa-arrow-up' : (($data['growth_rate'] ?? 0) < 0 ? 'fa-arrow-down' : 'fa-minus');
              $trendColor = $isPositive ? 'text-green-600' : (($data['growth_rate'] ?? 0) < 0 ? 'text-red-600' : 'text-gray-600');
            ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap font-medium"><?= $year ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= $data['election_count'] ?? 0 ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['total_eligible'] ?? 0) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['total_voted'] ?? 0) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['turnout_rate'] >= 70 ? 'text-green-600' : ($data['turnout_rate'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['turnout_rate'] ?? 0 ?>%
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $trendColor ?>"><?= ($data['growth_rate'] ?? 0) > 0 ? '+' : '' ?><?= $data['growth_rate'] ?? 0 ?>%</span>
                  <i class="fas <?= $trendIcon ?> <?= $trendColor ?> ml-1"></i>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Scopes & Admin Overview (no more "view scope dashboard" button) -->
  <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-3">
      <h2 class="text-2xl font-bold text-gray-800">Scopes &amp; Admin Overview</h2>
      <div class="flex items-center gap-2 text-sm">
        <span class="text-xs text-gray-500">
          Filter by scope type:
        </span>
        <form method="get" class="flex items-center gap-2 text-sm">
          <select
            id="scopeTypeFilterChart"
            name="scope_type"
            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            onchange="this.form.submit()"
          >
            <option value="all" <?= $scopeTypeFilter === 'all' ? 'selected' : '' ?>>All</option>
            <?php foreach ($scopeTypesAvailable as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $scopeTypeFilter === $type ? 'selected' : '' ?>>
                <?= htmlspecialchars($type) ?>
              </option>
            <?php endforeach; ?>
          </select>
          
          <!-- Preserve segment filter -->
          <input type="hidden" name="segment" value="<?= htmlspecialchars($segment) ?>">
          
          <!-- Preserve year range filters -->
          <input type="hidden" name="from_year" value="<?= htmlspecialchars($fromYear) ?>">
          <input type="hidden" name="to_year" value="<?= htmlspecialchars($toYear) ?>">
          
          <?php if ($selectedScopeId && $selectedScopeType): ?>
            <input type="hidden" name="scope_id" value="<?= (int)$selectedScopeId ?>">
            <input type="hidden" name="scope_type_detail" value="<?= htmlspecialchars($selectedScopeType) ?>">
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if (empty($filteredScopeSummaries)): ?>
      <p class="text-sm text-gray-600">
        No scope seats found for the selected filter.
      </p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Scope Type</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Scope Label</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Admin</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Voters</th>
              <th class="px-4 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Elections</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Last Election</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($filteredScopeSummaries as $s): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 whitespace-nowrap text-xs font-semibold">
                  <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                    <?= htmlspecialchars($s['scope_type']) ?>
                  </span>
                </td>
                <td class="px-4 py-2 whitespace-normal">
                  <div class="text-xs text-gray-900 font-medium">
                    <?= htmlspecialchars($s['label']) ?>
                  </div>
                  <div class="text-[11px] text-gray-500">
                    Scope ID: <?= (int)$s['scope_id'] ?>
                  </div>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-800">
                  <?= htmlspecialchars($s['admin_name']) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-xs text-blue-700">
                  <?php if (!empty($s['admin_email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($s['admin_email']) ?>" class="underline">
                      <?= htmlspecialchars($s['admin_email']) ?>
                    </a>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right text-xs text-gray-900">
                  <?= number_format($s['voters_count']) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right text-xs text-gray-900">
                  <?= number_format($s['elections_count']) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-700">
                  <?php if ($s['last_election_at']): ?>
                    <?= date('Y-m-d H:i', strtotime($s['last_election_at'])) ?>
                  <?php else: ?>
                    <span class="text-gray-400 italic">None</span>
                  <?php endif; ?>
                </td>
                <?php
                // Decide which admin dashboard file to use for "view as seat admin"
                $viewAsUrl = null;
                switch ($s['scope_type']) {
                    case SCOPE_ACAD_STUDENT:
                        $viewAsUrl = "admin_dashboard_college.php?scope_id=" . (int)$s['scope_id'];
                        break;
                    case SCOPE_ACAD_FACULTY:
                        $viewAsUrl = "admin_dashboard_faculty.php?scope_id=" . (int)$s['scope_id'];
                        break;
                    case SCOPE_NONACAD_STUDENT:
                        $viewAsUrl = "admin_dashboard_non_acad_students.php?scope_id=" . (int)$s['scope_id'];
                        break;
                    case SCOPE_NONACAD_EMPLOYEE:
                        $viewAsUrl = "admin_dashboard_nonacademic.php?scope_id=" . (int)$s['scope_id'];
                        break;
                    case SCOPE_OTHERS_COOP:
                        $viewAsUrl = "admin_dashboard_coop.php?scope_id=" . (int)$s['scope_id'];
                        break;
                    case SCOPE_OTHERS_DEFAULT:
                        $viewAsUrl = "admin_dashboard_default.php?scope_id=" . (int)$s['scope_id'];
                        break;
                    case SCOPE_SPECIAL_CSG:
                        $viewAsUrl = "admin_dashboard_csg.php?scope_id=" . (int)$s['scope_id'];
                        break;
                }
                ?>
                <td class="px-4 py-2 whitespace-nowrap text-xs">
                  <div class="flex flex-col gap-1">
                    <?php if ($viewAsUrl): ?>
                      <a href="<?= htmlspecialchars($viewAsUrl) ?>"
                         class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border border-gray-400 text-gray-700 hover:bg-gray-100">
                        View as seat admin
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Scope Details Panel (unchanged logic, just moved) -->
  <?php if ($selectedSeat !== null): ?>
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-bold text-gray-800">Scope Details</h2>
          <p class="text-sm text-gray-600">
            <?= htmlspecialchars($selectedSeat['label']) ?><br>
            <span class="text-xs text-gray-500">
              Admin: <?= htmlspecialchars($selectedSeat['admin_full_name']) ?>
              (<?= htmlspecialchars($selectedSeat['admin_email'] ?? '') ?>)
            </span>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <a href="super_admin_dashboard.php?segment=<?= htmlspecialchars($segment) ?>&scope_type=<?= htmlspecialchars($scopeTypeFilter) ?>&from_year=<?= htmlspecialchars($fromYear) ?>&to_year=<?= htmlspecialchars($toYear) ?>" class="text-xs px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">
            Clear selection
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(59, 130, 246, 0.05); border-color: var(--cvsu-blue);">
          <div class="text-xs" style="color: var(--cvsu-blue);">Voters in this scope</div>
          <div class="text-2xl font-bold" style="color: var(--cvsu-blue-dark);">
            <?= number_format(count($selectedScopeVoters)) ?>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(30, 111, 70, 0.05); border-color: var(--cvsu-green);">
          <div class="text-xs" style="color: var(--cvsu-green);">Elections in this scope</div>
          <div class="text-2xl font-bold" style="color: var(--cvsu-green-dark);">
            <?= number_format(count($selectedScopeElections)) ?>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(99, 102, 241, 0.05); border-color: var(--cvsu-indigo);">
          <div class="text-xs" style="color: var(--cvsu-indigo);">
            Latest turnout<?= $selectedScopeLatestYear ? ' ('.$selectedScopeLatestYear.')' : '' ?>
          </div>
          <div class="text-2xl font-bold" style="color: var(--cvsu-indigo);">
            <?= $selectedScopeLatestTurnout !== null ? $selectedScopeLatestTurnout.'%' : '0%' ?>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(255, 209, 102, 0.05); border-color: var(--cvsu-yellow);">
          <div class="text-xs" style="color: var(--cvsu-yellow);">Last election</div>
          <div class="text-sm" style="color: var(--cvsu-yellow-dark); font-weight: 600;">
            <?= $selectedScopeLatestElection ? htmlspecialchars($selectedScopeLatestElection['title']) : '—' ?>
          </div>
          <?php if ($selectedScopeLatestElection): ?>
            <div class="text-xs text-gray-600 mt-1">
              <?= date('M d, Y', strtotime($selectedScopeLatestElection['start_datetime'])) ?>
              – <?= date('M d, Y', strtotime($selectedScopeLatestElection['end_datetime'])) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($selectedScopeBreakdown)): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">
              Breakdown by
              <?php if (in_array($selectedSeat['scope_type'], [SCOPE_ACAD_STUDENT, SCOPE_ACAD_FACULTY, SCOPE_NONACAD_STUDENT, SCOPE_NONACAD_EMPLOYEE], true)): ?>
                Department / College
              <?php elseif (in_array($selectedSeat['scope_type'], [SCOPE_OTHERS_COOP, SCOPE_OTHERS_DEFAULT], true)): ?>
                Department
              <?php else: ?>
                Position
              <?php endif; ?>
            </h3>
            <span class="text-xs text-gray-500">
              Top <?= min(10, count($selectedScopeBreakdown)) ?> rows
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-4 py-2 text-left font-medium text-gray-600">Label</th>
                  <th class="px-4 py-2 text-left font-medium text-gray-600">Voters</th>
                  <th class="px-4 py-2 text-left font-medium text-gray-600">Share</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <?php
                $totalVotersScope = max(1, count($selectedScopeVoters));
                $i = 0;
                foreach ($selectedScopeBreakdown as $label => $cnt) {
                    if ($i++ >= 10) break;
                    $percent = round($cnt / $totalVotersScope * 100, 1);
                ?>
                <tr>
                  <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-800">
                    <?= htmlspecialchars($label === '' ? 'Unspecified' : $label) ?>
                  </td>
                  <td class="px-4 py-2 whitespace-nowrap text-gray-700">
                    <?= number_format($cnt) ?>
                  </td>
                  <td class="px-4 py-2 whitespace-nowrap text-gray-700">
                    <?= $percent ?>%
                  </td>
                </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php else: ?>
        <p class="text-sm text-gray-500">No detailed breakdown available for this scope.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Global elections per year
  const electionsCtx = document.getElementById('globalElectionsChart');
  if (electionsCtx) {
    const years  = <?= json_encode(array_map('intval', $yearsForChart)) ?>;
    const counts = <?= json_encode(array_map('intval', $countsForChart)) ?>;

    new Chart(electionsCtx, {
      type: 'line',
      data: {
        labels: years,
        datasets: [{
          label: 'Number of Elections',
          data: counts,
          borderColor: '#1E6F46',
          backgroundColor: 'rgba(30,111,70,0.1)',
          borderWidth: 3,
          pointBackgroundColor: '#1E6F46',
          pointRadius: 4,
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 14 },
            bodyFont: { size: 13 },
            padding: 10,
            callbacks: {
              label: (ctx) => `Elections: ${ctx.raw}`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
            title: { display: true, text: 'Number of Elections' }
          },
          x: {
            title: { display: true, text: 'Year' },
            grid: { display: false }
          }
        }
      }
    });
  }

  // Global turnout rate per year
  const turnoutCtx = document.getElementById('globalTurnoutChart');
  if (turnoutCtx) {
    const tYears  = <?= json_encode(array_map('intval', $turnoutYears)) ?>;
    const tRates  = <?= json_encode(array_map('floatval', $turnoutRates)) ?>;

    new Chart(turnoutCtx, {
      type: 'line',
      data: {
        labels: tYears,
        datasets: [{
          label: 'Turnout Rate (%)',
          data: tRates,
          borderColor: '#8B5CF6',
          backgroundColor: 'rgba(139,92,246,0.12)',
          borderWidth: 3,
          pointBackgroundColor: '#8B5CF6',
          pointRadius: 4,
          tension: 0.3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 14 },
            bodyFont: { size: 13 },
            padding: 10,
            callbacks: {
              label: (ctx) => `Turnout: ${ctx.raw}%`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: {
              callback: (v) => v + '%'
            },
            title: { display: true, text: 'Turnout Rate (%)' }
          },
          x: {
            title: { display: true, text: 'Year' },
            grid: { display: false }
          }
        }
      }
    });
  }

  // Global category breakdown chart (doughnut)
  const catCtx = document.getElementById('globalCategoryChart');
  if (catCtx) {
    const catLabels = <?= json_encode($categoryLabels) ?>;
    const catCounts = <?= json_encode($categoryCountsSegmented) ?>;
    new Chart(catCtx, {
      type: 'doughnut',
      data: {
        labels: catLabels,
        datasets: [{
          data: catCounts,
          backgroundColor: [
            '#1E6F46', // Academic Students
            '#37A66B', // Non-Academic Students
            '#FFD166', // Academic Faculty
            '#154734', // Non-Academic Employees
            '#2D5F3F', // Others-Default
            '#4A7C59'  // COOP (MIGS)
          ],
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
          legend: {
            position: 'right',
            labels: {
              boxWidth: 12,
              padding: 15,
              font: { size: 11 }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 13 },
            bodyFont: { size: 12 },
            padding: 10,
            callbacks: {
              label: (ctx) => {
                const label = ctx.label || '';
                const value = ctx.raw || 0;
                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((value / total) * 100);
                return `${label}: ${value.toLocaleString()} (${percentage}%)`;
              }
            }
          }
        }
      }
    });
  }

  // Global position breakdown chart
  const posCtx = document.getElementById('globalPositionChart');
  if (posCtx) {
    const posLabels = <?= json_encode($positionLabelsLimited) ?>;
    const posCounts = <?= json_encode($positionCountsLimited) ?>;
    new Chart(posCtx, {
      type: 'bar',
      data: {
        labels: posLabels,
        datasets: [{
          label: 'Voters',
          data: posCounts,
          backgroundColor: '#1E6F46',
          borderColor: '#154734',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 13 },
            bodyFont: { size: 12 },
            padding: 10,
            callbacks: {
              label: (ctx) => `Voters: ${ctx.raw.toLocaleString()}`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (v) => v.toLocaleString()
            },
            title: { display: true, text: 'Number of Voters' }
          },
          x: {
            ticks: { 
              maxRotation: 0, // Changed from 45 to 0 for horizontal labels
              minRotation: 0, // Changed from 45 to 0 for horizontal labels
              autoSkip: false,
              font: { size: 10 }
            },
            grid: { display: false }
          }
        }
      }
    });
  }
  
  // Function to handle year range submission
  function submitYearRange() {
    const fromYear = document.getElementById('fromYear').value;
    const toYear = document.getElementById('toYear').value;
    
    // Get current URL parameters
    const url = new URL(window.location.href);
    url.searchParams.set('from_year', fromYear);
    url.searchParams.set('to_year', toYear);
    
    // Navigate to the new URL
    window.location.href = url.toString();
  }
});
</script>

</body>
</html>