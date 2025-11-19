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
    die("Database connection failed: " . $e->getMessage());
}

// --- Scope from session (NEW model) ---
 $scope_category   = $_SESSION['scope_category']   ?? '';
 $assigned_scope   = strtoupper(trim($_SESSION['assigned_scope']   ?? ''));  // legacy, keep for fallback
 $assigned_scope_1 = trim($_SESSION['assigned_scope_1'] ?? '');
 $admin_status     = $_SESSION['admin_status']     ?? 'inactive';

// This dashboard is ONLY for Non-Academic-Employee admins (new scope model)
if ($scope_category !== 'Non-Academic-Employee') {
    // Legacy fallback: old NON-ACADEMIC global admin
    if ($assigned_scope !== 'NON-ACADEMIC') {
        header('Location: admin_dashboard_redirect.php');
        exit();
    }
}

if ($admin_status !== 'active') {
    header('Location: login.php?error=Your admin account is inactive.');
    exit();
}

// Resolve this admin's scope seat (admin_scopes) for Non-Academic-Employee
 $scopeId       = null;
 $myScopeDetails = [];
 $allowedDeptScopeNonAcad = [];  // department codes like ADMIN, HR, LIBRARY, NAEA, ...

if ($scope_category === 'Non-Academic-Employee') {
    $scopeStmt = $pdo->prepare("
        SELECT scope_id, scope_type, scope_details
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = 'Non-Academic-Employee'
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid' => $_SESSION['user_id'],
    ]);
    if ($scopeRow = $scopeStmt->fetch()) {
        $scopeId = (int)$scopeRow['scope_id'];
        if (!empty($scopeRow['scope_details'])) {
            $decoded = json_decode($scopeRow['scope_details'], true);
            if (is_array($decoded)) {
                $myScopeDetails = $decoded;
            }
        }
    }

    // Departments from scope_details (codes like ADMIN, LIBRARY, HR, NAEA, etc.)
    if (!empty($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
        $allowedDeptScopeNonAcad = array_values(array_filter(array_map('trim', $myScopeDetails['departments'])));
    }
    // departments_display = 'All' → walang restriction
    if (($myScopeDetails['departments_display'] ?? '') === 'All') {
        $allowedDeptScopeNonAcad = [];
    }
}

/* =============================
   BUILD SCOPE BADGE (NEW)
   ============================= */

if ($scope_category === 'Non-Academic-Employee') {

    if (!empty($allowedDeptScopeNonAcad)) {
        // Example: ADMIN, LIBRARY
        $scopeBadgeText = "Non-Academic Employee — " . implode(", ", $allowedDeptScopeNonAcad);
    } else {
        // Means ALL non-academic departments
        $scopeBadgeText = "Non-Academic Employee — All Departments";
    }

} else {
    // Legacy fallback
    $scopeBadgeText = "Non-Academic (Legacy Global Admin)";
}

// --- Get available years for dropdown ---
 $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE role = 'voter' AND position = 'non-academic' ORDER BY year DESC");
 $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
 $currentYear = date('Y');
 $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// --- Build scoped non-academic voters (master dataset) ---

 $scopedNonAcad = [];

// Base query: all non-academic voters
 $sql = "
    SELECT user_id, department, status, created_at
    FROM users
    WHERE role = 'voter'
      AND position = 'non-academic'
";
 $conditions = [];
 $params     = [];

// If new Non-Academic-Employee scope with specific departments → filter by department codes
if ($scope_category === 'Non-Academic-Employee' && !empty($allowedDeptScopeNonAcad)) {
    $placeholders = implode(',', array_fill(0, count($allowedDeptScopeNonAcad), '?'));
    $conditions[] = "department IN ($placeholders)";
    $params       = array_merge($params, $allowedDeptScopeNonAcad);
}

// Legacy NON-ACADEMIC global admin → no extra filter (can see all)
if ($conditions) {
    $sql .= ' AND ' . implode(' AND ', $conditions);
}

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $scopedNonAcad = $stmt->fetchAll();

// Total voters in this admin's scope
 $total_voters = count($scopedNonAcad);

// --- Fetch dashboard stats (scope-based, like faculty) ---

// Total Elections for this non-academic scope
if ($scopeId !== null) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_elections
        FROM elections
        WHERE election_scope_type = 'Non-Academic-Employee'
          AND owner_scope_id      = ?
    ");
    $stmt->execute([$scopeId]);
    $total_elections = (int)($stmt->fetch()['total_elections'] ?? 0);

    // Ongoing Elections for this non-academic scope
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS ongoing_elections
        FROM elections
        WHERE election_scope_type = 'Non-Academic-Employee'
          AND owner_scope_id      = ?
          AND status              = 'ongoing'
    ");
    $stmt->execute([$scopeId]);
    $ongoing_elections = (int)($stmt->fetch()['ongoing_elections'] ?? 0);
} else {
    // No scope row found — safest fallback
    $total_elections   = 0;
    $ongoing_elections = 0;
}

// --- Fetch elections for display ---
 $electionStmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
 $elections = $electionStmt->fetchAll();

// --- Fetch Non-Academic Analytics Data ---

// Get voters distribution by department (scoped)
 $votersByDepartment = [];
foreach ($scopedNonAcad as $u) {
    $dept = $u['department'] ?: 'Unspecified';
    if (!isset($votersByDepartment[$dept])) {
        $votersByDepartment[$dept] = 0;
    }
    $votersByDepartment[$dept]++;
}
 $votersByDepartment = array_map(
    fn($count, $name) => ['department' => $name, 'count' => $count],
    $votersByDepartment,
    array_keys($votersByDepartment)
);
usort($votersByDepartment, fn($a, $b) => $b['count'] <=> $a['count']);

// Status distribution (scoped)
 $statusCounts = [];
foreach ($scopedNonAcad as $u) {
    $st = $u['status'] ?? 'Unspecified';
    if ($st === '') $st = 'Unspecified';
    if (!isset($statusCounts[$st])) {
        $statusCounts[$st] = 0;
    }
    $statusCounts[$st]++;
}
 $byStatus = [];
foreach ($statusCounts as $status => $count) {
    $byStatus[] = ['status' => $status, 'count' => $count];
}
usort($byStatus, fn($a, $b) => $b['count'] <=> $a['count']);

// Define date ranges for current and previous month
 $currentMonthStart = date('Y-m-01');
 $currentMonthEnd = date('Y-m-t');
 $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
 $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

// Get new voters this month & last month within scopedNonAcad
 $newVoters       = 0;
 $lastMonthVoters = 0;

foreach ($scopedNonAcad as $u) {
    $created = substr($u['created_at'], 0, 10); // YYYY-MM-DD
    if ($created >= $currentMonthStart && $created <= $currentMonthEnd) {
        $newVoters++;
    }
    if ($created >= $lastMonthStart && $created <= $lastMonthEnd) {
        $lastMonthVoters++;
    }
}

// Calculate growth rate for summary card
if ($lastMonthVoters > 0) {
    $growthRate = round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1);
} else {
    $growthRate = 0;
}

// --- Fetch Voter Turnout Analytics Data (scope-based) ---
 $turnoutDataByYear = [];
 $turnoutYears      = [];

if ($scopeId !== null) {
    // Get all years that have elections for this non-academic scope
    $stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections 
        WHERE election_scope_type = 'Non-Academic-Employee'
          AND owner_scope_id      = ?
        ORDER BY year DESC
    ");
    $stmt->execute([$scopeId]);
    $turnoutYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($turnoutYears as $year) {
        // Distinct non-ac voters who voted in this scope's elections that year
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.voter_id) AS total_voted 
            FROM votes v 
            JOIN elections e ON v.election_id = e.election_id 
            WHERE e.election_scope_type = 'Non-Academic-Employee'
              AND e.owner_scope_id      = ?
              AND YEAR(e.start_datetime) = ?
        ");
        $stmt->execute([$scopeId, $year]);
        $totalVoted = (int)($stmt->fetch()['total_voted'] ?? 0);
        
        // Eligible voters as of Dec 31 that year (from your scoped non-ac dataset)
        $yearEnd = $year . '-12-31 23:59:59';
        $totalEligible = 0;
        foreach ($scopedNonAcad as $u) {
            if ($u['created_at'] <= $yearEnd) {
                $totalEligible++;
            }
        }
        
        $turnoutRate = ($totalEligible > 0)
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0;
        
        // Number of elections for this scope & year
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS election_count 
            FROM elections 
            WHERE election_scope_type = 'Non-Academic-Employee'
              AND owner_scope_id      = ?
              AND YEAR(start_datetime) = ?
        ");
        $stmt->execute([$scopeId, $year]);
        $electionCount = (int)($stmt->fetch()['election_count'] ?? 0);
        
        $turnoutDataByYear[$year] = [
            'year'           => (int)$year,
            'total_voted'    => $totalVoted,
            'total_eligible' => $totalEligible,
            'turnout_rate'   => $turnoutRate,
            'election_count' => $electionCount
        ];
    }
} else {
    // Legacy fallback: global non-academic elections by target_position
    $stmt = $pdo->query("
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections
        WHERE target_position = 'non-academic'
        ORDER BY year DESC
    ");
    $turnoutYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Ensure current year is ALWAYS included even if no elections exist
    $currentYear = (int)date('Y');
    if (!in_array($currentYear, $turnoutYears)) {
        $turnoutYears[] = $currentYear;
    }

    // Also include previous year for comparison
    $prevYear = $currentYear - 1;
    if (!in_array($prevYear, $turnoutYears)) {
        $turnoutYears[] = $prevYear;
    }

    sort($turnoutYears);


    foreach ($turnoutYears as $year) {
        // Legacy global non-acad
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.voter_id) AS total_voted
            FROM votes v
            JOIN elections e ON v.election_id = e.election_id
            WHERE e.target_position = 'non-academic'
              AND YEAR(e.start_datetime) = ?
        ");
        $stmt->execute([$year]);
        $totalVoted = (int)($stmt->fetch()['total_voted'] ?? 0);

        // Eligible voters as of Dec 31 that year (within scopedNonAcad)
        $yearEnd       = sprintf('%04d-12-31 23:59:59', $year);
        $totalEligible = 0;
        foreach ($scopedNonAcad as $u) {
            if ($u['created_at'] <= $yearEnd) {
                $totalEligible++;
            }
        }

        $turnoutRate = ($totalEligible > 0)
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0.0;

        // Number of elections in this scope & year
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS election_count
            FROM elections
            WHERE target_position = 'non-academic'
              AND YEAR(start_datetime) = ?
        ");
        $stmt->execute([$year]);
        $electionCount = (int)($stmt->fetch()['election_count'] ?? 0);

        $turnoutDataByYear[$year] = [
            'year'           => $year,
            'total_voted'    => $totalVoted,
            'total_eligible' => $totalEligible,
            'turnout_rate'   => $turnoutRate,
            'election_count' => $electionCount,
        ];
    }
}

// Add previous year if not exists (for comparison only)
 $prevYear = $selectedYear - 1;
if (!isset($turnoutDataByYear[$prevYear])) {
    $turnoutDataByYear[$prevYear] = [
        'year'           => $prevYear,
        'total_voted'    => 0,
        'total_eligible' => 0,
        'turnout_rate'   => 0,
        'election_count' => 0,
        'growth_rate'    => 0,
    ];
}

// Calculate year-over-year growth for turnout (YoY)
 $years = array_keys($turnoutDataByYear);
sort($years);
 $previousYearKey = null;
foreach ($years as $year) {
    if ($previousYearKey !== null) {
        $prevTurnout    = $turnoutDataByYear[$previousYearKey]['turnout_rate'];
        $currentTurnout = $turnoutDataByYear[$year]['turnout_rate'];
        $gr = ($prevTurnout > 0)
            ? round((($currentTurnout - $prevTurnout) / $prevTurnout) * 100, 1)
            : 0.0;
        $turnoutDataByYear[$year]['growth_rate'] = $gr;
    } else {
        $turnoutDataByYear[$year]['growth_rate'] = 0;
    }
    $previousYearKey = $year;
}

// --- Year range filtering for turnout analytics ---
// Builds a subset $turnoutRangeData used by the chart & table

 $allTurnoutYears = array_keys($turnoutDataByYear);
sort($allTurnoutYears);

 $defaultYear = (int)date('Y');
 $currentYear = (int)date('Y');
 $previousYear = $currentYear - 1;
 
 // ALWAYS default to previous/current-year range
 $fromYear = isset($_GET['from_year']) ? (int)$_GET['from_year'] : $previousYear;
 $toYear   = isset($_GET['to_year'])   ? (int)$_GET['to_year']   : $currentYear;
 
 // Clamp based on available years (but allow current year even if no data)
 $minYear = min(min($allTurnoutYears), $previousYear);
 $maxYear = max(max($allTurnoutYears), $currentYear);
 
 if ($fromYear < $minYear) $fromYear = $minYear;
 if ($toYear   > $maxYear) $toYear   = $maxYear;
 if ($toYear < $fromYear)  $toYear   = $fromYear; 

// Clamp to known bounds
if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear < $fromYear)  $toYear   = $fromYear;

// Build range [fromYear..toYear], fill missing years with 0
 $turnoutRangeData = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($turnoutDataByYear[$y])) {
        $turnoutRangeData[$y] = $turnoutDataByYear[$y];
    } else {
        $turnoutRangeData[$y] = [
            'year'           => $y,
            'total_voted'    => 0,
            'total_eligible' => 0,
            'turnout_rate'   => 0,
            'election_count' => 0,
            'growth_rate'    => 0,
        ];
    }
}

// Recompute growth_rate within the selected range
 $prevY = null;
foreach ($turnoutRangeData as $y => &$data) {
    if ($prevY === null) {
        $data['growth_rate'] = 0;
    } else {
        $prevRate = $turnoutRangeData[$prevY]['turnout_rate'] ?? 0;
        $data['growth_rate'] = $prevRate > 0
            ? round(($data['turnout_rate'] - $prevRate) / $prevRate * 100, 1)
            : 0;
    }
    $prevY = $y;
}
unset($data);

/* ==========================================================
DEPARTMENT TURNOUT DATA (SCOPE-BASED, LIKE FACULTY)
========================================================== */

 $departmentTurnoutData = [];

// Build voted set for this admin's non-ac scope & selected year
 $votedSet = [];
if (!empty($scopeId)) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT v.voter_id
        FROM votes v
        JOIN elections e ON v.election_id = e.election_id
        WHERE e.election_scope_type = 'Non-Academic-Employee'
          AND e.owner_scope_id      = ?
          AND YEAR(e.start_datetime) = ?
    ");
    $stmt->execute([$scopeId, $selectedYear]);
    $votedIds = array_column($stmt->fetchAll(), 'voter_id');
    $votedSet = array_flip($votedIds);
}

 $deptBuckets     = [];
 $yearEndSelected = sprintf('%04d-12-31 23:59:59', $selectedYear);

// Use ONLY scoped non-ac voters
foreach ($scopedNonAcad as $u) {
    if ($u['created_at'] > $yearEndSelected) {
        continue; // not yet "eligible" in that year
    }

    $dept = trim($u['department'] ?? '');
    if ($dept === '') $dept = 'UNSPECIFIED';

    if (!isset($deptBuckets[$dept])) {
        $deptBuckets[$dept] = [
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }

    $deptBuckets[$dept]['eligible_count']++;

    if (isset($votedSet[$u['user_id']])) {
        $deptBuckets[$dept]['voted_count']++;
    }
}

foreach ($deptBuckets as $dept => $c) {
    $rate = ($c['eligible_count'] > 0)
        ? round(($c['voted_count'] / $c['eligible_count']) * 100, 1)
        : 0.0;
    $departmentTurnoutData[] = [
        'department'     => $dept,
        'eligible_count' => (int)$c['eligible_count'],
        'voted_count'    => (int)$c['voted_count'],
        'turnout_rate'   => (float)$rate,
    ];
}

/* ==========================================================
STATUS TURNOUT DATA (SCOPE-BASED, LIKE FACULTY)
========================================================== */

 $statusTurnoutData = [];

// $votedSet already built above for this year & scope

 $statusBuckets = [];
foreach ($scopedNonAcad as $u) {
    if ($u['created_at'] > $yearEndSelected) {
        continue;
    }

    $statusName = trim($u['status'] ?? '');
    if ($statusName === '') $statusName = 'Unspecified';

    if (!isset($statusBuckets[$statusName])) {
        $statusBuckets[$statusName] = [
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }

    $statusBuckets[$statusName]['eligible_count']++;

    if (isset($votedSet[$u['user_id']])) {
        $statusBuckets[$statusName]['voted_count']++;
    }
}

foreach ($statusBuckets as $statusName => $c) {
    $rate = ($c['eligible_count'] > 0)
        ? round(($c['voted_count'] / $c['eligible_count']) * 100, 1)
        : 0.0;
    $statusTurnoutData[] = [
        'status'         => $statusName,
        'eligible_count' => (int)$c['eligible_count'],
        'voted_count'    => (int)$c['voted_count'],
        'turnout_rate'   => (float)$rate,
    ];
}

// Sort by eligible count DESC (like faculty)
usort($statusTurnoutData, function($a, $b) {
    return $b['eligible_count'] <=> $a['eligible_count'];
});

// Department mapping for full names
 $departmentMap = [
    'HR' => 'Human Resources',
    'ADMIN' => 'Administration',
    'FINANCE' => 'Finance',
    'IT' => 'Information Technology',
    'MAINTENANCE' => 'Maintenance',
    'SECURITY' => 'Security',
    'LIBRARY' => 'Library',
    'NAEA' => 'Non-Academic Employees Association',
    'NAES' => 'Non-Academic Employee Services',
    'NAEM' => 'Non-Academic Employee Management',
    'NAEH' => 'Non-Academic Employee Health',
    'NAEIT' => 'Non-Academic Employee IT'
];

// Function to get full department name
function getFullDepartmentName($abbr) {
    global $departmentMap;
    return $departmentMap[$abbr] ?? $abbr;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - Non-Academic Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
      --cvsu-accent: #2D5F3F;
      --cvsu-light-accent: #4A7C59;
    }
    ::-webkit-scrollbar {
      width: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: var(--cvsu-green-light);
      border-radius: 3px;
    }
    .analytics-card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .analytics-card:hover {
      transform: translateY(-2px);
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .chart-container {
      position: relative;
      height: 320px;
      width: 100%;
    }
    .chart-tooltip {
      position: absolute;
      background-color: white;
      color: #333;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 14px;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.2s;
      z-index: 10;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      border: 1px solid #e5e7eb;
      max-width: 220px;
      display: none;
      right: 10px;
      top: 10px;
      left: auto;
    }
    .chart-tooltip.show {
      opacity: 1;
      display: block;
    }
    .chart-tooltip .title {
      font-weight: bold;
      color: var(--cvsu-green-dark);
      margin-bottom: 4px;
      font-size: 16px;
    }
    .chart-tooltip .count {
      color: #4b5563;
      font-size: 14px;
    }
    .breakdown-section {
      display: none;
    }
    .breakdown-section.active {
      display: block;
    }
    .cvsu-gradient {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }
    .cvsu-card {
      background-color: white;
      border-left: 4px solid var(--cvsu-green);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      transition: all 0.3s;
    }
    .cvsu-card:hover {
      box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }
    /* === Scope Badge === */
    .scope-badge {
        display: inline-block;
        background-color: var(--cvsu-green);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 0;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">

<?php include 'sidebar.php'; ?>

<!-- Top Bar -->
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color: var(--cvsu-green-dark);">
  <div class="flex items-center space-x-4">
    <div class="flex flex-col">
        <span class="scope-badge"><?= htmlspecialchars($scopeBadgeText) ?></span>

        <h1 class="text-2xl font-bold text-white mt-1">
            NON-ACADEMIC ADMIN DASHBOARD
        </h1>
    </div>
  </div>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
  </div>
</header>

<!-- Main Content Area -->
<main class="flex-1 pt-20 px-8 ml-64">
  <!-- Statistics Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Population -->
    <div class="cvsu-card p-6 rounded-xl">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Voters</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-green-dark);"><?= number_format($total_voters) ?></p>
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
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-yellow);"><?= $total_elections ?></p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(255, 209, 102, 0.1);">
          <i class="fas fa-vote-yea text-2xl" style="color: var(--cvsu-yellow);"></i>
        </div>
      </div>
    </div>

    <!-- Ongoing Elections -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: #3B82F6;">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-blue-600"><?= $ongoing_elections ?></p>
        </div>
        <div class="p-3 rounded-full bg-blue-50">
          <i class="fas fa-clock text-2xl text-blue-600"></i>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <div>
          <h2 class="text-2xl font-bold text-white">Non-Academic Voters Analytics</h2>
        </div>
      </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="p-4 rounded-lg border" style="background-color: rgba(30, 111, 70, 0.05); border-color: var(--cvsu-green-light);">
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-green-light);">
            <i class="fas fa-user-plus text-white text-xl"></i>
          </div>
          <div>
            <p class="text-sm" style="color: var(--cvsu-green);">New This Month</p>
            <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);"><?= number_format($newVoters) ?></p>
          </div>
        </div>
      </div>
      
      <div class="p-4 rounded-lg border" style="background-color: rgba(59, 130, 246, 0.05); border-color: #3B82F6;">
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4 bg-blue-500">
            <i class="fas fa-building text-white text-xl"></i>
          </div>
          <div>
            <p class="text-sm text-blue-600">Departments</p>
            <p class="text-2xl font-bold text-blue-800"><?= count($votersByDepartment) ?></p>
          </div>
        </div>
      </div>
      
      <div class="p-4 rounded-lg border" style="background-color: rgba(139, 92, 246, 0.05); border-color: #8B5CF6;">
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4 bg-purple-500">
            <i class="fas fa-id-badge text-white text-xl"></i>
          </div>
          <div>
            <p class="text-sm text-purple-600">Status Types</p>
            <p class="text-2xl font-bold text-purple-800"><?= count($byStatus) ?></p>
          </div>
        </div>
      </div>
      
      <div class="p-4 rounded-lg border" style="background-color: rgba(245, 158, 11, 0.05); border-color: var(--cvsu-yellow);">
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-yellow);">
            <i class="fas fa-chart-line text-white text-xl"></i>
          </div>
          <div>
            <p class="text-sm" style="color: var(--cvsu-yellow);">Growth Rate</p>
            <p class="text-2xl font-bold" style="color: #D97706;">
              <?php
              // Calculate growth rate directly in the HTML to ensure correct display
              if ($lastMonthVoters > 0) {
                  $displayGrowthRate = round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1);
                  echo ($displayGrowthRate > 0 ? '+' : '') . $displayGrowthRate . '%';
              } else {
                  echo '0%';
              }
              ?>
            </p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Detailed Analytics -->
    <div id="analyticsDetails" class="border-t">
      <div class="p-6">
        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Department Distribution Chart -->
          <div class="p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.03);">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Voters by Department</h3>
            <div class="chart-container">
              <canvas id="departmentChart"></canvas>
              <div id="chartTooltip" class="chart-tooltip">
                <div class="title"></div>
                <div class="count"></div>
              </div>
            </div>
          </div>
          
          <!-- Status Distribution Chart -->
          <div class="p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.03);">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Voters by Status</h3>
            <div class="chart-container">
              <canvas id="statusChart"></canvas>
            </div>
          </div>
        </div>
        
        <!-- Detailed Breakdown Section -->
        <div class="mt-8">
          <!-- Breakdown Selector -->
          <div class="mb-6 flex items-center">
            <label for="breakdownType" class="mr-2 text-gray-700 font-medium">Breakdown by:</label>
            <select id="breakdownType" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <option value="department">Department</option>
              <option value="status">Status</option>
            </select>
          </div>
          
          <!-- Department Breakdown -->
          <div id="departmentBreakdown" class="breakdown-section active">
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($votersByDepartment as $department): 
                      $percentage = ($total_voters > 0) ? round(($department['count'] / $total_voters) * 100, 1) : 0;
                    ?>
                      <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-medium">
                          <?= htmlspecialchars(getFullDepartmentName($department['department'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= number_format($department['count']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                              <div class="h-2 rounded-full" style="width: <?= $percentage ?>%; background-color: var(--cvsu-green);"></div>
                            </div>
                            <span><?= $percentage ?>%</span>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <!-- Status Breakdown -->
          <div id="statusBreakdown" class="breakdown-section">
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($byStatus as $status): 
                      $percentage = ($total_voters > 0) ? round(($status['count'] / $total_voters) * 100, 1) : 0;
                    ?>
                      <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-medium"><?= htmlspecialchars($status['status']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= number_format($status['count']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                              <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <span><?= $percentage ?>%</span>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Voter Turnout Analytics Section -->
    <div class="border-t p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Voter Turnout Analytics</h3>
        
        <!-- Year Selector -->
        <div class="flex items-center">
        <label for="turnoutYearSelector" class="mr-2 text-gray-700 font-medium">Select Year:</label>
        <select id="turnoutYearSelector" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
            <?php foreach ($turnoutYears as $year): ?>
            <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
        </select>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(99, 102, 241, 0.05); border-color: #6366F1;">
        <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-indigo-500">
            <i class="fas fa-percentage text-white text-xl"></i>
            </div>
            <div>
            <p class="text-sm text-indigo-600"><?= $selectedYear ?> Turnout</p>
            <p class="text-2xl font-bold text-indigo-800">
                <?= isset($turnoutDataByYear[$selectedYear]) ? $turnoutDataByYear[$selectedYear]['turnout_rate'] : '0' ?>%
            </p>
            </div>
        </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(139, 92, 246, 0.05); border-color: #8B5CF6;">
        <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500">
            <i class="fas fa-percentage text-white text-xl"></i>
            </div>
            <div>
            <p class="text-sm text-purple-600"><?= $selectedYear - 1 ?> Turnout</p>
            <p class="text-2xl font-bold text-purple-800">
                <?= isset($turnoutDataByYear[$selectedYear - 1]) ? $turnoutDataByYear[$selectedYear - 1]['turnout_rate'] : '0' ?>%
            </p>
            </div>
        </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(16, 185, 129, 0.05); border-color: #10B981;">
        <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-green-500">
            <i class="fas fa-chart-line text-white text-xl"></i>
            </div>
            <div>
            <p class="text-sm text-green-600">Growth Rate</p>
            <p class="text-2xl font-bold text-green-800">
                <?php 
                $currentTurnout = isset($turnoutDataByYear[$selectedYear]) ? $turnoutDataByYear[$selectedYear]['turnout_rate'] : 0;
                $prevTurnout = isset($turnoutDataByYear[$selectedYear - 1]) ? $turnoutDataByYear[$selectedYear - 1]['turnout_rate'] : 0;
                
                if ($prevTurnout > 0) {
                    $growthRate = round((($currentTurnout - $prevTurnout) / $prevTurnout) * 100, 1);
                    echo ($growthRate > 0 ? '+' : '') . $growthRate . '%';
                } else {
                    echo '0%';
                }
                ?>
            </p>
            </div>
        </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(59, 130, 246, 0.05); border-color: #3B82F6;">
        <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-blue-500">
            <i class="fas fa-vote-yea text-white text-xl"></i>
            </div>
            <div>
            <p class="text-sm text-blue-600">Elections</p>
            <p class="text-2xl font-bold text-blue-800">
                <?= isset($turnoutDataByYear[$selectedYear]) ? $turnoutDataByYear[$selectedYear]['election_count'] : '0' ?>
            </p>
            </div>
        </div>
        </div>
    </div>
    
    <!-- Turnout Trend Chart (Full Width) -->
    <div class="p-4 rounded-lg mb-8" style="background-color: rgba(30, 111, 70, 0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Turnout Rate Trend</h3>
        <div class="chart-container" style="height: 400px;">
        <canvas id="turnoutTrendChart"></canvas>
        </div>
    </div>
    
    <!-- Yearly Turnout Table -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-8">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Elections</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Eligible Voters</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters Participated</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turnout Rate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Growth</th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($turnoutRangeData as $year => $data): 
                $isPositive = $data['growth_rate'] > 0;
                $trendIcon = $isPositive ? 'fa-arrow-up' : ($data['growth_rate'] < 0 ? 'fa-arrow-down' : 'fa-minus');
                $trendColor = $isPositive ? 'text-green-600' : ($data['growth_rate'] < 0 ? 'text-red-600' : 'text-gray-600');
            ?>
                <tr>
                <td class="px-6 py-4 whitespace-nowrap font-medium"><?= $year ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= $data['election_count'] ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['total_eligible']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['total_voted']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="<?= $data['turnout_rate'] >= 70 ? 'text-green-600' : ($data['turnout_rate'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['turnout_rate'] ?>%
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="<?= $isPositive ? 'text-green-600' : ($data['growth_rate'] < 0 ? 'text-red-600' : 'text-gray-600') ?>">
                    <?= $data['growth_rate'] > 0 ? '+' : '' ?><?= $data['growth_rate'] ?>%
                    </span>
                    <i class="fas <?= $trendIcon ?> <?= $trendColor ?> ml-1"></i>
                </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    
    <!-- Elections vs Turnout Rate Section -->
    <div class="p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Elections vs Turnout Rate</h3>
        
        <!-- Dropdown Options -->
        <div class="mb-4">
        <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
            <!-- Data Series Dropdown -->
            <div class="flex items-center">
            <label for="dataSeriesSelect" class="mr-3 text-sm font-medium text-gray-700">Data Series:</label>
            <select id="dataSeriesSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="elections">Elections vs Turnout</option>
                <option value="voters">Voters vs Turnout</option>
            </select>
            </div>
            
            <!-- Breakdown Dropdown -->
            <div class="flex items-center">
            <label for="breakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
            <select id="breakdownSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="year">Year</option>
                <option value="department">Department</option>
                <option value="status">Status</option>
            </select>
            </div>
        </div>
        </div>
        
        <div class="chart-container" style="height: 400px;">
        <canvas id="electionsVsTurnoutChart"></canvas>
        </div>
        
        <!-- Table container -->
        <div id="turnoutBreakdownTable" class="mt-6 overflow-x-auto"></div>
    </div>
    
    <!-- Year Range Selector -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-medium text-blue-800 mb-2">Turnout Analysis – Year Range</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="fromYear" class="block text-sm font-medium text-blue-800">From year</label>
            <select id="fromYear" name="from_year" class="mt-1 p-2 border rounded w-full">
              <?php 
              // Generate years from minYear to maxYear
              for ($y = $minYear; $y <= $maxYear; $y++): ?>
                <option value="<?= $y ?>" <?= ($y == $fromYear) ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div>
            <label for="toYear" class="block text-sm font-medium text-blue-800">To year</label>
            <select id="toYear" name="to_year" class="mt-1 p-2 border rounded w-full">
              <?php 
              // Generate years from minYear to maxYear
              for ($y = $minYear; $y <= $maxYear; $y++): ?>
                <option value="<?= $y ?>" <?= ($y == $toYear) ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <p class="text-xs text-blue-700 mt-2">
          Select a start and end year to compare turnout. Years with no elections in this range will appear with zero values.
        </p>
    </div>
    </div>
</main>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Breakdown type selector
    const breakdownType = document.getElementById('breakdownType');
    if (breakdownType) {
        breakdownType.addEventListener('change', function() {
            const selectedType = this.value;
            
            // Hide all breakdown sections
            document.querySelectorAll('.breakdown-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected breakdown section
            if (selectedType === 'department') {
                document.getElementById('departmentBreakdown').classList.add('active');
            } else if (selectedType === 'status') {
                document.getElementById('statusBreakdown').classList.add('active');
            }
        });
    }
    
    // Turnout year selector
    const turnoutYearSelector = document.getElementById('turnoutYearSelector');
    if (turnoutYearSelector) {
        turnoutYearSelector.addEventListener('change', function() {
            const selectedYear = this.value;
            // Reload page with selected year
            window.location.href = window.location.pathname + '?year=' + selectedYear;
        });
    }
    
    // Year range selectors for turnout analytics
    const fromYearSelect = document.getElementById('fromYear');
    const toYearSelect   = document.getElementById('toYear');

    function submitYearRange() {
        if (!fromYearSelect || !toYearSelect) return;
        const from = fromYearSelect.value;
        const to   = toYearSelect.value;

        const url = new URL(window.location.href);
        if (from) url.searchParams.set('from_year', from); else url.searchParams.delete('from_year');
        if (to)   url.searchParams.set('to_year', to);     else url.searchParams.delete('to_year');

        // Keep currently selected single year for summary cards
        const yearSelect = document.getElementById('turnoutYearSelector');
        if (yearSelect && yearSelect.value) {
          url.searchParams.set('year', yearSelect.value);
        }

        window.location.href = url.toString();
    }

    fromYearSelect?.addEventListener('change', submitYearRange);
    toYearSelect?.addEventListener('change', submitYearRange);
    
    let departmentChartInstance = null;
    let statusChartInstance = null;
    let turnoutTrendChartInstance = null;
    let electionsVsTurnoutChartInstance = null;
    
    // Initialize charts immediately when page loads
    initializeCharts();
    
    function initializeCharts() {
        // Department mapping for tooltips
        const departmentMap = {
            'HR': 'Human Resources',
            'ADMIN': 'Administration',
            'FINANCE': 'Finance',
            'IT': 'Information Technology',
            'MAINTENANCE': 'Maintenance',
            'SECURITY': 'Security',
            'LIBRARY': 'Library',
            'NAEA': 'Non-Academic Employees Association',
            'NAES': 'Non-Academic Employee Services',
            'NAEM': 'Non-Academic Employee Management',
            'NAEH': 'Non-Academic Employee Health',
            'NAEIT': 'Non-Academic Employee IT'
        };
        
        // Function to get full department name
        function getFullDepartmentName(abbr) {
            return departmentMap[abbr] || abbr;
        }
        
        // Department Distribution Chart (Doughnut)
        const departmentCtx = document.getElementById('departmentChart');
        const chartTooltip = document.getElementById('chartTooltip');
        const tooltipTitle = chartTooltip.querySelector('.title');
        const tooltipCount = chartTooltip.querySelector('.count');
        
        if (departmentCtx && !departmentChartInstance) {
            // Prepare data with abbreviations for labels
            const departmentLabels = <?= json_encode(array_column($votersByDepartment, 'department')) ?>;
            const departmentCounts = <?= json_encode(array_column($votersByDepartment, 'count')) ?>;
            
            // Colors for each department segment
            const colors = [
                '#1E6F46', '#37A66B', '#FFD166', '#EF4444', '#3B82F6',
                '#8B5CF6', '#EC4899', '#F59E0B', '#10B981', '#6B7280'
            ];
            
            departmentChartInstance = new Chart(departmentCtx, {
                type: 'doughnut',
                data: {
                    labels: departmentLabels,
                    datasets: [{
                        data: departmentCounts,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverBorderWidth: 4,
                        hoverBorderColor: '#fff',
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '50%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                font: {
                                    size: 12
                                },
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            enabled: false
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1000,
                        easing: 'easeOutQuart'
                    },
                    onHover: (event, activeElements) => {
                        event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                        
                        if (activeElements.length > 0) {
                            const dataIndex = activeElements[0].index;
                            const departmentAbbr = departmentLabels[dataIndex];
                            const fullName = getFullDepartmentName(departmentAbbr);
                            const count = departmentCounts[dataIndex];
                            const total = departmentCounts.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((count / total) * 100);
                            
                            tooltipTitle.innerHTML = `
                                <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                    <span style="display: inline-block; width: 12px; height: 12px; background-color: ${colors[dataIndex]}; border-radius: 50%; margin-right: 8px;"></span>
                                    ${fullName}
                                </div>
                            `;
                            tooltipCount.innerHTML = `
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span>Voters:</span>
                                    <span style="font-weight: bold;">${count}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Percentage:</span>
                                    <span style="font-weight: bold;">${percentage}%</span>
                                </div>
                            `;
                            
                            chartTooltip.style.right = '10px';
                            chartTooltip.style.top = '10px';
                            chartTooltip.style.left = 'auto';
                            chartTooltip.style.transform = 'none';
                            
                            chartTooltip.classList.add('show');
                        } else {
                            chartTooltip.classList.remove('show');
                        }
                    }
                }
            });
            
            departmentCtx.addEventListener('mouseleave', function() {
                chartTooltip.classList.remove('show');
            });
        }
        
        // Status Distribution Chart (Bar)
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx && !statusChartInstance) {
            const statusLabels = <?= json_encode(array_column($byStatus, 'status')) ?>;
            const statusCounts = <?= json_encode(array_column($byStatus, 'count')) ?>;
            
            statusChartInstance = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Voter Count',
                        data: statusCounts,
                        backgroundColor: '#1E6F46',
                        borderColor: '#154734',
                        borderWidth: 1,
                        borderRadius: 4,
                        barThickness: 'flex',
                        maxBarThickness: 60,
                        hoverBackgroundColor: '#37A66B',
                        hoverBorderColor: '#1E6F46'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            padding: 12,
                            cornerRadius: 4,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.raw / total) * 100);
                                    return [
                                        `Voters: ${context.raw}`,
                                        `Percentage: ${percentage}%`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            title: {
                                display: true,
                                text: 'Number of Voters',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 12
                                },
                                maxRotation: 0,
                                minRotation: 0
                            },
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Status',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    },
                    onHover: (event, activeElements) => {
                        event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                    }
                }
            });
        }
        
        // Turnout Trend Chart
        const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
        if (turnoutTrendCtx && !turnoutTrendChartInstance) {
            const turnoutYears = <?= json_encode(array_keys($turnoutRangeData)) ?>;
            const turnoutRates = <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>;
            
            turnoutTrendChartInstance = new Chart(turnoutTrendCtx, {
                type: 'line',
                data: {
                    labels: turnoutYears,
                    datasets: [{
                        label: 'Turnout Rate (%)',
                        data: turnoutRates,
                        borderColor: '#1E6F46',
                        backgroundColor: 'rgba(30, 111, 70, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#1E6F46',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 },
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return `Turnout: ${context.raw}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        
        // Initialize the Elections vs Turnout Chart with dropdown functionality
        const chartData = {
            'elections': {
                'year': {
                    labels: <?= json_encode(array_keys($turnoutRangeData)) ?>,
                    electionCounts: <?= json_encode(array_column($turnoutRangeData, 'election_count')) ?>,
                    turnoutRates: <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>
                }
            },
            'voters': {
                'year': {
                    labels: <?= json_encode(array_keys($turnoutRangeData)) ?>,
                    eligibleCounts: <?= json_encode(array_column($turnoutRangeData, 'total_eligible')) ?>,
                    turnoutRates: <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>
                },
                'department': <?= json_encode($departmentTurnoutData) ?>,
                'status': <?= json_encode($statusTurnoutData) ?>
            }
        };
        
        // Current chart state
        let currentDataSeries = 'elections';
        let currentBreakdown = 'year';
        
        // Function to update the Elections vs Turnout Chart
        function updateElectionsVsTurnoutChart() {
            const ctx = document.getElementById('electionsVsTurnoutChart');
            if (!ctx) return;
            
            // Destroy existing chart if it exists
            if (electionsVsTurnoutChartInstance) {
                electionsVsTurnoutChartInstance.destroy();
            }
            
            let data;
            let options;
            
            if (currentDataSeries === 'elections') {
                // Only breakdown by year is available for elections
                data = {
                    labels: chartData.elections.year.labels,
                    datasets: [
                        {
                            label: 'Number of Elections',
                            data: chartData.elections.year.electionCounts,
                            backgroundColor: '#1E6F46',
                            borderColor: '#154734',
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Turnout Rate (%)',
                            data: chartData.elections.year.turnoutRates,
                            backgroundColor: '#FFD166',
                            borderColor: '#F59E0B',
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y1'
                        }
                    ]
                };
                
                options = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 12 },
                                padding: 15
                            }
                        },
                        title: {
                            display: true,
                            text: `Elections vs Turnout Rate by Year`,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Number of Elections',
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
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            grid: { drawOnChartArea: false }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                };
            } else {
                // voters data series
                if (currentBreakdown === 'year') {
                    data = {
                        labels: chartData.voters.year.labels,
                        datasets: [
                            {
                                label: 'Eligible Voters',
                                data: chartData.voters.year.eligibleCounts,
                                backgroundColor: '#1E6F46',
                                borderColor: '#154734',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Turnout Rate (%)',
                                data: chartData.voters.year.turnoutRates,
                                backgroundColor: '#FFD166',
                                borderColor: '#F59E0B',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y1'
                            }
                        ]
                    };
                    
                    options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: { size: 12 },
                                    padding: 15
                                }
                            },
                            title: {
                                display: true,
                                text: `Eligible Voters vs Turnout Rate by Year`,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Number of Voters',
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
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: { drawOnChartArea: false }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    };
                } else if (currentBreakdown === 'department') {
                    const departments = chartData.voters.department.map(item => item.department);
                    const eligibleCounts = chartData.voters.department.map(item => item.eligible_count);
                    const turnoutRates = chartData.voters.department.map(item => item.turnout_rate);
                    
                    data = {
                        labels: departments,
                        datasets: [
                            {
                                label: 'Eligible Voters',
                                data: eligibleCounts,
                                backgroundColor: '#1E6F46',
                                borderColor: '#154734',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Turnout Rate (%)',
                                data: turnoutRates,
                                backgroundColor: '#FFD166',
                                borderColor: '#F59E0B',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y1'
                            }
                        ]
                    };
                    
                    options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: { size: 12 },
                                    padding: 15
                                }
                            },
                            title: {
                                display: true,
                                text: `Eligible Voters vs Turnout Rate by Department`,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Number of Voters',
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
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: { drawOnChartArea: false }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    };
                } else if (currentBreakdown === 'status') {
                    const statuses = chartData.voters.status.map(item => item.status);
                    const eligibleCounts = chartData.voters.status.map(item => item.eligible_count);
                    const turnoutRates = chartData.voters.status.map(item => item.turnout_rate);
                    
                    data = {
                        labels: statuses,
                        datasets: [
                            {
                                label: 'Eligible Voters',
                                data: eligibleCounts,
                                backgroundColor: '#1E6F46',
                                borderColor: '#154734',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Turnout Rate (%)',
                                data: turnoutRates,
                                backgroundColor: '#FFD166',
                                borderColor: '#F59E0B',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y1'
                            }
                        ]
                    };
                    
                    options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: { size: 12 },
                                    padding: 15
                                }
                            },
                            title: {
                                display: true,
                                text: `Eligible Voters vs Turnout Rate by Status`,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Number of Voters',
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
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: { drawOnChartArea: false }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    };
                }
            }
            
            electionsVsTurnoutChartInstance = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: options
            });
        }
        
        // Add this function after the updateElectionsVsTurnoutChart function
        function generateTurnoutBreakdownTable() {
            const tableContainer = document.getElementById('turnoutBreakdownTable');
            
            // Clear existing content
            tableContainer.innerHTML = '';
            
            let tableData;
            let tableHeaders;
            
            if (currentDataSeries === 'elections') {
                // Only breakdown by year is available for elections
                tableData = chartData.elections.year.labels.map((label, index) => ({
                    label: label,
                    electionCount: chartData.elections.year.electionCounts[index],
                    turnoutRate: chartData.elections.year.turnoutRates[index]
                }));
                
                tableHeaders = ['Year', 'Number of Elections', 'Turnout Rate'];
            } else {
                // voters data series
                if (currentBreakdown === 'year') {
                    tableData = chartData.voters.year.labels.map((label, index) => ({
                        label: label,
                        eligibleCount: chartData.voters.year.eligibleCounts[index],
                        turnoutRate: chartData.voters.year.turnoutRates[index]
                    }));
                    
                    tableHeaders = ['Year', 'Eligible Voters', 'Turnout Rate'];
                } else if (currentBreakdown === 'department') {
                    tableData = chartData.voters.department.map(item => ({
                        label: item.department,
                        eligibleCount: item.eligible_count,
                        votedCount: item.voted_count,
                        turnoutRate: item.turnout_rate
                    }));
                    
                    tableHeaders = ['Department', 'Eligible Voters', 'Voted', 'Turnout Rate'];
                } else if (currentBreakdown === 'status') {
                    tableData = chartData.voters.status.map(item => ({
                        label: item.status,
                        eligibleCount: item.eligible_count,
                        votedCount: item.voted_count,
                        turnoutRate: item.turnout_rate
                    }));
                    
                    tableHeaders = ['Status', 'Eligible Voters', 'Voted', 'Turnout Rate'];
                }
            }
            
            // Create table element
            const table = document.createElement('table');
            table.className = 'min-w-full divide-y divide-gray-200';
            
            // Create table header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            headerRow.className = 'bg-gray-50';
            
            tableHeaders.forEach(header => {
                const th = document.createElement('th');
                th.className = 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
                th.textContent = header;
                headerRow.appendChild(th);
            });
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create table body
            const tbody = document.createElement('tbody');
            tbody.className = 'bg-white divide-y divide-gray-200';
            
            tableData.forEach(row => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                
                // Add label cell
                const labelCell = document.createElement('td');
                labelCell.className = 'px-6 py-4 whitespace-nowrap font-medium text-gray-900';
                
                // If department, get full name
                if (currentDataSeries === 'voters' && currentBreakdown === 'department') {
                    const departmentMap = {
                        'HR': 'Human Resources',
                        'ADMIN': 'Administration',
                        'FINANCE': 'Finance',
                        'IT': 'Information Technology',
                        'MAINTENANCE': 'Maintenance',
                        'SECURITY': 'Security',
                        'LIBRARY': 'Library',
                        'NAEA': 'Non-Academic Employees Association',
                        'NAES': 'Non-Academic Employee Services',
                        'NAEM': 'Non-Academic Employee Management',
                        'NAEH': 'Non-Academic Employee Health',
                        'NAEIT': 'Non-Academic Employee IT'
                    };
                    labelCell.textContent = departmentMap[row.label] || row.label;
                } else {
                    labelCell.textContent = row.label;
                }
                
                tr.appendChild(labelCell);
                
                // Add eligible count or election count
                if (row.eligibleCount !== undefined) {
                    const eligibleCell = document.createElement('td');
                    eligibleCell.className = 'px-6 py-4 whitespace-nowrap text-gray-700';
                    eligibleCell.textContent = row.eligibleCount.toLocaleString();
                    tr.appendChild(eligibleCell);
                } else if (row.electionCount !== undefined) {
                    const electionCell = document.createElement('td');
                    electionCell.className = 'px-6 py-4 whitespace-nowrap text-gray-700';
                    electionCell.textContent = row.electionCount.toLocaleString();
                    tr.appendChild(electionCell);
                }
                
                // Add voted count if available
                if (row.votedCount !== undefined) {
                    const votedCell = document.createElement('td');
                    votedCell.className = 'px-6 py-4 whitespace-nowrap text-gray-700';
                    votedCell.textContent = row.votedCount.toLocaleString();
                    tr.appendChild(votedCell);
                }
                
                // Add turnout rate with progress bar
                const turnoutCell = document.createElement('td');
                turnoutCell.className = 'px-6 py-4 whitespace-nowrap';
                
                const turnoutContainer = document.createElement('div');
                turnoutContainer.className = 'flex items-center';
                
                const progressBar = document.createElement('div');
                progressBar.className = 'w-24 bg-gray-200 rounded-full h-2 mr-2';
                
                const progressFill = document.createElement('div');
                progressFill.className = 'bg-green-600 h-2 rounded-full';
                progressFill.style.width = `${row.turnoutRate}%`;
                
                progressBar.appendChild(progressFill);
                turnoutContainer.appendChild(progressBar);
                
                const turnoutText = document.createElement('span');
                turnoutText.className = 'text-gray-700';
                turnoutText.textContent = `${row.turnoutRate}%`;
                
                turnoutContainer.appendChild(turnoutText);
                turnoutCell.appendChild(turnoutContainer);
                
                tr.appendChild(turnoutCell);
                tbody.appendChild(tr);
            });
            
            table.appendChild(tbody);
            tableContainer.appendChild(table);
        }
        
        // Initialize the chart
        updateElectionsVsTurnoutChart();
        generateTurnoutBreakdownTable();
        
        // Add event listeners for the new dropdowns
        document.getElementById('dataSeriesSelect')?.addEventListener('change', function() {
            currentDataSeries = this.value;
            
            // If elections is selected, force breakdown to year and disable the breakdown dropdown
            if (currentDataSeries === 'elections') {
                document.getElementById('breakdownSelect').value = 'year';
                document.getElementById('breakdownSelect').disabled = true;
            } else {
                document.getElementById('breakdownSelect').disabled = false;
            }
            
            currentBreakdown = document.getElementById('breakdownSelect').value;
            updateElectionsVsTurnoutChart();
            generateTurnoutBreakdownTable();
        });
        
        document.getElementById('breakdownSelect')?.addEventListener('change', function() {
            currentBreakdown = this.value;
            updateElectionsVsTurnoutChart();
            generateTurnoutBreakdownTable();
        });
    }
});
</script>

</body>
</html>