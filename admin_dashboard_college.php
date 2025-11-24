<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/includes/analytics_scopes.php';

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
   die("Database connection error.");
}

// --- Auth check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    header('Location: login.php');
    exit();
}

// --- Scope info (session default) ---
$scope_category   = $_SESSION['scope_category']   ?? '';
$assigned_scope   = $_SESSION['assigned_scope']   ?? ''; // college code (e.g., CEIT)
$assigned_scope_1 = $_SESSION['assigned_scope_1'] ?? ''; // e.g., "Multiple: BSIT, BSCS"
$scope_details    = $_SESSION['scope_details']    ?? [];
$admin_status     = $_SESSION['admin_status']     ?? 'inactive';

// --- Super admin impersonation via ?scope_id= ---
$seat = null;
$impersonatedScopeId = getImpersonatedScopeId();

if ($impersonatedScopeId !== null) {
    // Fetch the seat from admin_scopes; verify it is Academic-Student
    $seat = fetchScopeSeatById($pdo, $impersonatedScopeId);

    if (!$seat || $seat['scope_type'] !== SCOPE_ACAD_STUDENT) {
        die('Invalid scope for this dashboard.');
    }

    // Override local scope variables based on seat
    $scope_category   = $seat['scope_type'];              // "Academic-Student"
    $assigned_scope   = $seat['assigned_scope'] ?? '';    // college code (CEIT, CAS, ...)
    $assigned_scope_1 = $seat['assigned_scope_1'] ?? '';  // e.g. "BSIT, BSCS"
    $scope_details    = $seat['scope_details_array'] ?? [];
    // Treat impersonated admin as active for view purposes
    $admin_status     = 'active';
}

// Now decide which admin_id to use for analytics (for legacy uses, if any)
$userId           = (int)$_SESSION['user_id'];
$analyticsAdminId = $userId;

// If super admin is impersonating a seat, use the seat admin's user_id
if ($seat !== null && isset($seat['user_id'])) {
    $analyticsAdminId = (int)$seat['user_id'];
}

// --- force_password_change flag for this admin (real logged-in user) ---
$stmtFP = $pdo->prepare("SELECT force_password_change FROM users WHERE user_id = :uid");
$stmtFP->execute([':uid' => $userId]);
$forceRow = $stmtFP->fetch();
$force_password_flag = (int)($forceRow['force_password_change'] ?? 0);

// Validate: Academic-Student admins only
if ($scope_category !== SCOPE_ACAD_STUDENT) {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

if ($admin_status !== 'active') {
    header('Location: login.php?error=Your admin account is inactive.');
    exit();
}

// ------------------------------------------------------------------
// 1. Resolve scope seat (scope_id) for this Academic-Student admin
// ------------------------------------------------------------------

$scopeSeatId = null;

if ($impersonatedScopeId !== null) {
    $scopeSeatId = (int)$impersonatedScopeId;
} else {
    // Find seat where this admin is the owner (scope_type = Academic-Student)
    $acadSeats = getScopeSeats($pdo, SCOPE_ACAD_STUDENT);
    foreach ($acadSeats as $sRow) {
        if ((int)$sRow['admin_user_id'] === $analyticsAdminId) {
            $scopeSeatId   = (int)$sRow['scope_id'];
            // If session assigned_scope is empty, sync from seat
            if (empty($assigned_scope) && !empty($sRow['assigned_scope'])) {
                $assigned_scope = $sRow['assigned_scope'];
            }
            break;
        }
    }
}

if ($scopeSeatId === null) {
    die('No Academic-Student scope seat found for this admin.');
}

// ------------------------------------------------------------------
// 2. College names (for header + titles)
// ------------------------------------------------------------------

$collegeFullNameMap = [
    'CAFENR'  => 'College of Agriculture, Food, Environment and Natural Resources',
    'CEIT'    => 'College of Engineering and Information Technology',
    'CAS'     => 'College of Arts and Sciences',
    'CVMBS'   => 'College of Veterinary Medicine and Biomedical Sciences',
    'CED'     => 'College of Education',
    'CEMDS'   => 'College of Economics, Management and Development Studies',
    'CSPEAR'  => 'College of Sports, Physical Education and Recreation',
    'CCJ'     => 'College of Criminal Justice',
    'CON'     => 'College of Nursing',
    'CTHM'    => 'College of Tourism and Hospitality Management',
    'COM'     => 'College of Medicine',
    'GS-OLC'  => 'Graduate School and Open Learning College',
];

$collegeFullName = $collegeFullNameMap[$assigned_scope] ?? $assigned_scope;

// ------------------------------------------------------------------
// 3. Course map (for pretty display in tables / charts only)
// ------------------------------------------------------------------

$courseMap = [
    'BSCS'  => 'Bachelor of Science in Computer Science',
    'BSIT'  => 'Bachelor of Science in Information Technology',
    'BSCpE' => 'Bachelor of Science in Computer Engineering',
    'BSECE' => 'Bachelor of Science in Electronics Engineering',
    'BSCE'  => 'Bachelor of Science in Civil Engineering',
    'BSME'  => 'Bachelor of Science in Mechanical Engineering',
    'BSEE'  => 'Bachelor of Science in Electrical Engineering',
    'BSIE'  => 'Bachelor of Science in Industrial Engineering',

    'BSAgri' => 'Bachelor of Science in Agriculture',
    'BSAB'   => 'Bachelor of Science in Agribusiness',
    'BSES'   => 'Bachelor of Science in Environmental Science',
    'BSFT'   => 'Bachelor of Science in Food Technology',
    'BSFor'  => 'Bachelor of Science in Forestry',
    'BSABE'  => 'Bachelor of Science in Agricultural and Biosystems Engineering',
    'BAE'    => 'Bachelor of Agricultural Entrepreneurship',
    'BSLDM'  => 'Bachelor of Science in Land Use Design and Management',

    'BSBio'     => 'Bachelor of Science in Biology',
    'BSChem'    => 'Bachelor of Science in Chemistry',
    'BSMath'    => 'Bachelor of Science in Mathematics',
    'BSPhysics' => 'Bachelor of Science in Physics',
    'BSPsych'   => 'Bachelor of Science in Psychology',
    'BAELS'     => 'Bachelor of Arts in English Language Studies',
    'BAComm'    => 'Bachelor of Arts in Communication',
    'BSStat'    => 'Bachelor of Science in Statistics',

    'DVM'  => 'Doctor of Veterinary Medicine',
    'BSPV' => 'Bachelor of Science in Biology (Pre-Veterinary)',

    'BEEd' => 'Bachelor of Elementary Education',
    'BSEd' => 'Bachelor of Secondary Education',
    'BPE'  => 'Bachelor of Physical Education',
    'BTLE' => 'Bachelor of Technology and Livelihood Education',

    'BSBA'  => 'Bachelor of Science in Business Administration',
    'BSAcc' => 'Bachelor of Science in Accountancy',
    'BSEco' => 'Bachelor of Science in Economics',
    'BSEnt' => 'Bachelor of Science in Entrepreneurship',
    'BSOA'  => 'Bachelor of Science in Office Administration',

    'BSESS' => 'Bachelor of Science in Exercise and Sports Sciences',
    'BSCrim'=> 'Bachelor of Science in Criminology',
    'BSN'   => 'Bachelor of Science in Nursing',
    'BSHM'  => 'Bachelor of Science in Hospitality Management',
    'BSTM'  => 'Bachelor of Science in Tourism Management',
    'BLIS'  => 'Bachelor of Library and Information Science',

    'PhD' => 'Doctor of Philosophy',
    'MS'  => 'Master of Science',
    'MA'  => 'Master of Arts',
];

// Simple course normalization/display helpers
function normalize_course_code(string $raw): string {
    $s = strtoupper(trim($raw));
    if ($s === '') return 'UNSPECIFIED';
    $s = preg_replace('/[.\-_,]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    $noSpace = str_replace(' ', '', $s);
    return $noSpace ?: 'UNSPECIFIED';
}
function getCanonicalCourseDisplay(string $raw, array $courseMap): string {
    $code = normalize_course_code($raw);
    return $courseMap[$code] ?? $code;
}

// ------------------------------------------------------------------
// 4. Scoped students via analytics_scopes (Academic-Student seat)
// ------------------------------------------------------------------

$scopedStudents = getScopedVoters(
    $pdo,
    SCOPE_ACAD_STUDENT,
    $scopeSeatId,
    [
        'year_end'      => null, // no cutoff; we'll filter per year where needed
        'include_flags' => true,
    ]
);
$total_voters = count($scopedStudents);

// ------------------------------------------------------------------
// 5. Total elections & ongoing elections (scope-based)
// ------------------------------------------------------------------

$scopedElectionsAll = getScopedElections(
    $pdo,
    SCOPE_ACAD_STUDENT,
    $scopeSeatId
);
$total_elections   = count($scopedElectionsAll);
$ongoing_elections = 0;
foreach ($scopedElectionsAll as $eRow) {
    if (($eRow['status'] ?? '') === 'ongoing') {
        $ongoing_elections++;
    }
}

// ------------------------------------------------------------------
// 6. Date/time ranges & selected year
// ------------------------------------------------------------------

$currentYear       = (int) date('Y');
$selectedYear      = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int) $_GET['year'] : $currentYear;
$currentMonthStart = date('Y-m-01');
$currentMonthEnd   = date('Y-m-t');
$lastMonthStart    = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd      = date('Y-m-t', strtotime('-1 month'));

// ------------------------------------------------------------------
// 7. New / last month voters (scopedStudents)
// ------------------------------------------------------------------

$newVoters       = 0;
$lastMonthVoters = 0;
foreach ($scopedStudents as $s) {
    $createdDate = substr($s['created_at'] ?? '', 0, 10);
    if ($createdDate >= $currentMonthStart && $createdDate <= $currentMonthEnd) {
        $newVoters++;
    }
    if ($createdDate >= $lastMonthStart && $createdDate <= $lastMonthEnd) {
        $lastMonthVoters++;
    }
}

// ------------------------------------------------------------------
// 8. Group by department & course (for student distribution charts)
// ------------------------------------------------------------------

$votersByDepartment = [];
foreach ($scopedStudents as $s) {
    $deptName = $s['department1'] ?? $s['department'] ?? 'Unspecified';
    if (!isset($votersByDepartment[$deptName])) {
        $votersByDepartment[$deptName] = 0;
    }
    $votersByDepartment[$deptName]++;
}
$votersByDepartment = array_map(
    fn($count, $name) => ['department_name' => $name, 'count' => $count],
    $votersByDepartment,
    array_keys($votersByDepartment)
);
usort($votersByDepartment, fn($a, $b) => $b['count'] <=> $a['count']);

$tempCourseCounts = [];
foreach ($scopedStudents as $s) {
    $code = normalize_course_code($s['course'] ?? '');
    if (!isset($tempCourseCounts[$code])) {
        $tempCourseCounts[$code] = 0;
    }
    $tempCourseCounts[$code]++;
}
$votersByCourse = [];
foreach ($tempCourseCounts as $code => $count) {
    $votersByCourse[] = ['course' => $code, 'count' => $count];
}
usort($votersByCourse, fn($a, $b) => $b['count'] <=> $a['count']);

// ------------------------------------------------------------------
// 9. Status distribution & growth rate
// ------------------------------------------------------------------

$statusCounts = [];
foreach ($scopedStudents as $s) {
    $st = $s['status'] ?? 'Unspecified';
    if ($st === '') $st = 'Unspecified';
    if (!isset($statusCounts[$st])) $statusCounts[$st] = 0;
    $statusCounts[$st]++;
}
$byStatus = [];
foreach ($statusCounts as $status => $count) {
    $byStatus[] = ['status' => $status, 'count' => $count];
}

$growthRate = ($lastMonthVoters > 0)
    ? round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1)
    : 0.0;

// ------------------------------------------------------------------
// 10. Turnout by year via analytics_scopes
// ------------------------------------------------------------------

// Full turnout map (no year_from/year_to filter yet)
$turnoutDataByYear = computeTurnoutByYear(
    $pdo,
    SCOPE_ACAD_STUDENT,
    $scopeSeatId,
    $scopedStudents,
    [
        'year_from' => null,
        'year_to'   => null,
    ]
);

// All years in data
$allTurnoutYears = array_keys($turnoutDataByYear);
sort($allTurnoutYears);

// Safe defaults if empty
$defaultYear = (int)date('Y');
$minYear = $allTurnoutYears ? min($allTurnoutYears) : $defaultYear;
$maxYear = $allTurnoutYears ? max($allTurnoutYears) : $defaultYear;

// Year range from query (?from_year=&to_year=)
$fromYear = isset($_GET['from_year']) && ctype_digit($_GET['from_year'])
    ? (int)$_GET['from_year']
    : $minYear;
$toYear = isset($_GET['to_year']) && ctype_digit($_GET['to_year'])
    ? (int)$_GET['to_year']
    : $maxYear;

if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear   < $fromYear) $toYear  = $fromYear;

// Build turnoutRangeData subset based on [fromYear..toYear]
$turnoutRangeData = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($turnoutDataByYear[$y])) {
        $turnoutRangeData[$y] = $turnoutDataByYear[$y];
    } else {
        // If computeTurnoutByYear didn't include this year, fill with zeros
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

// Summary cards: selectedYear vs previous year
$currentYearTurnout  = $turnoutDataByYear[$selectedYear]     ?? null;
$previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;

// ------------------------------------------------------------------
// 11. Department & course turnout (selected year, scoped)
// ------------------------------------------------------------------

$departmentTurnoutData = [];
$courseTurnoutData     = [];

// All voters who voted in ANY scoped election that year
$stmt = $pdo->prepare("
    SELECT DISTINCT v.voter_id
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    WHERE e.election_scope_type = :stype
      AND e.owner_scope_id      = :sid
      AND YEAR(e.start_datetime) = :year
");
$stmt->execute([
    ':stype' => SCOPE_ACAD_STUDENT,
    ':sid'   => $scopeSeatId,
    ':year'  => $selectedYear,
]);
$votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$votedSet = array_flip($votedIds);

$yearEndSelected = sprintf('%04d-12-31 23:59:59', $selectedYear);
$deptBuckets     = [];
$courseBuckets   = [];

foreach ($scopedStudents as $s) {
    $createdAt = $s['created_at'] ?? null;
    if ($createdAt && $createdAt > $yearEndSelected) {
        continue;
    }

    $dept = $s['department1'] ?? $s['department'] ?? 'Unspecified';
    $courseCode = normalize_course_code($s['course'] ?? '');

    if (!isset($deptBuckets[$dept])) {
        $deptBuckets[$dept] = ['eligible_count' => 0, 'voted_count' => 0];
    }
    if (!isset($courseBuckets[$courseCode])) {
        $courseBuckets[$courseCode] = ['eligible_count' => 0, 'voted_count' => 0];
    }

    $deptBuckets[$dept]['eligible_count']++;
    $courseBuckets[$courseCode]['eligible_count']++;

    if (isset($votedSet[$s['user_id']])) {
        $deptBuckets[$dept]['voted_count']++;
        $courseBuckets[$courseCode]['voted_count']++;
    }
}

foreach ($deptBuckets as $dept => $c) {
    $rate = $c['eligible_count'] > 0
        ? round(($c['voted_count'] / $c['eligible_count']) * 100, 1)
        : 0.0;
    $departmentTurnoutData[] = [
        'department'     => $dept,
        'eligible_count' => (int)$c['eligible_count'],
        'voted_count'    => (int)$c['voted_count'],
        'turnout_rate'   => (float)$rate,
    ];
}

foreach ($courseBuckets as $code => $c) {
    $rate = $c['eligible_count'] > 0
        ? round(($c['voted_count'] / $c['eligible_count']) * 100, 1)
        : 0.0;
    $courseTurnoutData[] = [
        'course'         => $code,
        'eligible_count' => (int)$c['eligible_count'],
        'voted_count'    => (int)$c['voted_count'],
        'turnout_rate'   => (float)$rate,
    ];
}
usort($courseTurnoutData, fn($a, $b) => $b['eligible_count'] <=> $a['eligible_count']);

// ------------------------------------------------------------------
// 12. Per-election stats + abstain (selected year) via analytics_scopes
// ------------------------------------------------------------------

$electionTurnoutStats = computePerElectionStatsWithAbstain(
    $pdo,
    SCOPE_ACAD_STUDENT,
    $scopeSeatId,
    $scopedStudents,
    $selectedYear
);

// ------------------------------------------------------------------
// 13. Abstain by year via analytics_scopes (for Abstained → Year)
// ------------------------------------------------------------------

// --- Abstain by year (aggregated) ----------------------------------

// 1) Kunin lahat ng abstain stats (walang year range filter muna)
$abstainAllYears = computeAbstainByYear(
    $pdo,
    SCOPE_ACAD_STUDENT,
    $scopeSeatId,          // or $scopeSeatId depending sa variable name mo
    $scopedStudents,
    [
        'year_from' => null,
        'year_to'   => null,
    ]
);

// 2) Gumawa ng abstainByYear EXACTLY over [fromYear..toYear] (same as turnoutRangeData)
$abstainByYear = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($abstainAllYears[$y])) {
        $abstainByYear[$y] = $abstainAllYears[$y];
    } else {
        // Stub year with zero values so the chart can still show it
        $abstainByYear[$y] = [
            'year'           => (int)$y,
            'abstain_count'  => 0,
            'total_eligible' => 0,
            'abstain_rate'   => 0.0,
        ];
    }
}

// 3) Flatten for JS
$abstainYears      = array_keys($abstainByYear);
sort($abstainYears);
$abstainCountsYear = [];
$abstainRatesYear  = [];
foreach ($abstainYears as $y) {
    $abstainCountsYear[] = (int)($abstainByYear[$y]['abstain_count']  ?? 0);
    $abstainRatesYear[]  = (float)($abstainByYear[$y]['abstain_rate'] ?? 0.0);
}

// ------------------------------------------------------------------
// 14. Scope display for header (CEIT : BSIT, BSCS)
// ------------------------------------------------------------------

function formatScopeForDisplay(string $category, string $scope, string $scope1, array $details): string {
    // Standard format target: "CEIT : BSIT, BSCS" or "CEIT : All Courses"
    $scopeCode = $scope ?: '';

    switch ($category) {
        case SCOPE_ACAD_STUDENT:
            if (empty($scope1) || strcasecmp($scope1, 'All') === 0) {
                return $scopeCode . ' : All Courses';
            }
            $clean = preg_replace('/^(Courses?:\s*)?/i', '', $scope1);
            $clean = preg_replace('/^Multiple:\s*/i', '', $clean);
            $clean = trim($clean);
            return $scopeCode . ' : ' . $clean;

        case SCOPE_ACAD_FACULTY:
            if (empty($scope1) || strcasecmp($scope1, 'All') === 0) {
                return $scopeCode . ' : All Departments';
            }
            $clean = preg_replace('/^(Departments?:\s*)?/i', '', $scope1);
            $clean = preg_replace('/^Multiple:\s*/i', '', $clean);
            $clean = trim($clean);
            return $scopeCode . ' : ' . $clean;

        default:
            return $scopeCode;
    }
}

$scopeDisplay = formatScopeForDisplay($scope_category, $assigned_scope, $assigned_scope_1, $scope_details);
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - <?= htmlspecialchars($collegeFullName) ?> Admin Dashboard</title>

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
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb {
      background-color: var(--cvsu-green-light);
      border-radius: 3px;
    }
    .chart-container { position:relative; height:320px; width:100%; }
    .chart-tooltip {
      position:absolute;
      background-color:white;
      color:#333;
      padding:12px 16px;
      border-radius:8px;
      font-size:14px;
      pointer-events:none;
      opacity:0;
      transition:opacity .2s;
      z-index:10;
      box-shadow:0 4px 12px rgba(0,0,0,.15);
      border:1px solid #e5e7eb;
      max-width:220px;
      display:none;
      right:10px;
      top:10px;
      left:auto;
    }
    .chart-tooltip.show { opacity:1; display:block; }
    .chart-tooltip .title { font-weight:bold; color:var(--cvsu-green-dark); margin-bottom:4px; font-size:16px; }
    .chart-tooltip .count { color:#4b5563; font-size:14px; }
    .breakdown-section { display:none; }
    .breakdown-section.active { display:block; }
    .cvsu-gradient {
      background:linear-gradient(135deg,var(--cvsu-green-dark)0%,var(--cvsu-green)100%);
    }
    .cvsu-card {
      background-color:white;
      border-left:4px solid var(--cvsu-green);
      box-shadow:0 4px 6px rgba(0,0,0,.05);
      transition:all .3s;
    }
    .cvsu-card:hover {
      box-shadow:0 10px 15px rgba(0,0,0,.1);
      transform:translateY(-2px);
    }
    .scope-badge {
      display:inline-block;
      background-color: var(--cvsu-green);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: bold;
      margin-bottom: 0;
    }
    /* Force password change modal utilities */
    .modal-backdrop {
      background-color: rgba(0, 0, 0, 0.7);
      z-index: 9999;
    }
    .password-strength-bar {
      transition: width 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<!-- Force Password Change Modal (same design as voter dashboard) -->
<div id="forcePasswordChangeModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative">
      <button onclick="closePasswordModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl">
          &times;
      </button>
      
      <div class="text-center mb-6">
        <div class="w-16 h-16 bg-[var(--cvsu-green-light)] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-[var(--cvsu-green-dark)]">Change Your Password</h2>
        <p class="text-gray-600 mt-2">For security reasons, you must change your password before continuing.</p>
      </div>
      
      <form id="forcePasswordChangeForm" class="space-y-5">
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <div class="relative">
                  <input type="password" id="newPassword" name="new_password" required 
                         class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                  <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div class="mt-2">
                  <div class="flex items-center text-xs text-gray-500 mb-1">
                      <span id="passwordStrength" class="font-medium">Password strength:</span>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                          <div id="strengthBar" class="h-2 rounded-full password-strength-bar" style="width: 0%"></div>
                      </div>
                  </div>
                  <ul class="text-xs text-gray-500 space-y-1">
                      <li id="lengthCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          At least 8 characters
                      </li>
                      <li id="uppercaseCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains uppercase letter
                      </li>
                      <li id="numberCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains number
                      </li>
                      <li id="specialCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains special character
                      </li>
                  </ul>
              </div>
          </div>
          
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <div class="relative">
                  <input type="password" id="confirmPassword" name="confirm_password" required 
                         class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                  <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268-2.943 9.542-7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div id="matchError" class="mt-1 text-xs text-red-500 hidden">Passwords do not match</div>
          </div>
          
          <div id="notificationContainer" class="space-y-3">
              <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-red-800">Error</h3>
                          <div class="mt-1 text-sm text-red-700" id="passwordErrorText"></div>
                      </div>
                  </div>
              </div>
              
              <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-green-800">Success</h3>
                          <div class="mt-1 text-sm text-green-700">
                              Password updated successfully! Redirecting to dashboard...
                          </div>
                      </div>
                  </div>
              </div>
              
              <div id="passwordLoading" class="hidden bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="animate-spin h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-blue-800">Processing</h3>
                          <div class="mt-1 text-sm text-blue-700">
                              Updating your password...
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="flex justify-center pt-4">
              <button type="submit" id="submitBtn" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-8 py-3 rounded-lg font-medium flex items-center justify-center min-w-[180px] transition-all duration-200 transform hover:scale-105">
                  <span id="submitBtnText">Update Password</span>
                  <svg id="submitLoader" class="ml-2 h-5 w-5 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291
A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </button>
          </div>
      </form>
  </div>
</div>

<div class="flex min-h-screen">


  <?php
  // If super admin is impersonating a scope seat, use super admin sidebar
  if (function_exists('isSuperAdmin') && isSuperAdmin() && getImpersonatedScopeId() !== null) {
      include 'super_admin_sidebar.php';
  } else {
      include 'sidebar.php';
  }
  ?>

  <header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
    <!-- Left side: title + scope line (+ optional View As) -->
    <div class="flex flex-col">
      <h1 class="text-2xl font-bold text-white">
        <?= htmlspecialchars(strtoupper($collegeFullName)) ?> ADMIN DASHBOARD
      </h1>

      <div class="flex items-center space-x-3 mt-1">
        <!-- Scope identifier: e.g. CEIT : BSIT, BSCS -->
        <span class="scope-badge">
          <?= htmlspecialchars($scopeDisplay) ?>
        </span>

        <!-- View As indicator when super admin is impersonating -->
        <?php if (function_exists('isSuperAdmin') && isSuperAdmin() && getImpersonatedScopeId() !== null): ?>
          <span class="inline-flex items-center max-w-fit px-3 py-0.5 rounded-full text-xs font-semibold bg-yellow-300 text-gray-900 shadow-sm">
            View As College Admin
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right side: icon -->
    <div class="text-white">
      <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2z"/>
      </svg>
    </div>
  </header>

  <main class="flex-1 pt-20 px-8 ml-64">

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- Total Students -->
      <div class="cvsu-card p-6 rounded-xl">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Students</h2>
            <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-green-dark);">
              <?= number_format($total_voters) ?>
            </p>
          </div>
          <div class="p-3 rounded-full" style="background-color: rgba(30, 111, 70, 0.1);">
            <i class="fas fa-user-graduate text-2xl" style="color: var(--cvsu-green);"></i>
          </div>
        </div>
      </div>

      <!-- Total Elections -->
      <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-yellow);">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
            <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-yellow);">
              <?= $total_elections ?>
            </p>
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
            <p class="text-2xl md:text-4xl font-bold text-blue-600">
              <?= $ongoing_elections ?>
            </p>
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
        <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($collegeFullName) ?> Student Analytics</h2>
      </div>

      <!-- Small summary cards -->
      <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- New this month -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color: var(--cvsu-green-light);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-green-light);">
              <i class="fas fa-user-plus text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm" style="color: var(--cvsu-green);">New This Month</p>
              <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);">
                <?= number_format($newVoters) ?>
              </p>
            </div>
          </div>
        </div>

        <!-- Departments -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color: #3B82F6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-blue-500">
              <i class="fas fa-building-columns text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-blue-600">Departments</p>
              <p class="text-2xl font-bold text-blue-800"><?= count($votersByDepartment) ?></p>
            </div>
          </div>
        </div>

        <!-- Courses -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color: #8B5CF6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500">
              <i class="fas fa-graduation-cap text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-purple-600">Courses</p>
              <p class="text-2xl font-bold text-purple-800"><?= count($votersByCourse) ?></p>
            </div>
          </div>
        </div>

        <!-- Growth Rate -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(245,158,11,0.05); border-color: var(--cvsu-yellow);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-yellow);">
              <i class="fas fa-chart-line text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm" style="color: var(--cvsu-yellow);">Growth Rate</p>
              <p class="text-2xl font-bold" style="color: #D97706;">
                <?php
                  if ($lastMonthVoters > 0) {
                      echo ($growthRate > 0 ? '+' : '') . $growthRate . '%';
                  } else {
                      echo '0%';
                  }
                ?>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Detailed analytics (charts + breakdown tables) -->
      <div id="analyticsDetails" class="border-t">
        <div class="p-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Department chart -->
            <div class="p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.03);">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Students by Department</h3>
              <div class="chart-container">
                <canvas id="departmentChart"></canvas>
                <div id="chartTooltip" class="chart-tooltip">
                  <div class="title"></div>
                  <div class="count"></div>
                </div>
              </div>
            </div>

            <!-- Course chart -->
            <div class="p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.03);">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Students by Course</h3>
              <div class="chart-container">
                <canvas id="courseChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Breakdown toggle + tables -->
          <div class="mt-8">
            <div class="mb-6 flex items-center">
              <label for="breakdownType" class="mr-2 text-gray-700 font-medium">Breakdown by:</label>
              <select id="breakdownType" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                <option value="department">Department</option>
                <option value="course">Course</option>
              </select>
            </div>

            <!-- Department breakdown -->
            <div id="departmentBreakdown" class="breakdown-section active">
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <?php foreach ($votersByDepartment as $department):
                        $percentage = ($total_voters > 0)
                          ? round(($department['count'] / $total_voters) * 100, 1)
                          : 0;
                      ?>
                      <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-medium">
                          <?= htmlspecialchars($department['department_name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <?= number_format($department['count']) ?>
                        </td>
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

            <!-- Course breakdown -->
            <div id="courseBreakdown" class="breakdown-section">
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <?php foreach ($votersByCourse as $course):
                        $percentage = ($total_voters > 0)
                          ? round(($course['count'] / $total_voters) * 100, 1)
                          : 0;
                        $displayName = getCanonicalCourseDisplay($course['course'], $courseMap);
                      ?>
                      <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-medium">
                          <?= htmlspecialchars($displayName) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <?= number_format($course['count']) ?>
                        </td>
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

          </div> <!-- /Breakdown -->
        </div>
      </div>

      <!-- Voter Turnout Analytics -->
      <div class="border-t p-6">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-semibold text-gray-800">Voter Turnout Analytics</h3>
          <div class="flex items-center">
            <label for="turnoutYearSelector" class="mr-2 text-gray-700 font-medium">Select Year:</label>
            <select id="turnoutYearSelector" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <?php foreach (array_keys($turnoutDataByYear) as $year): ?>
                <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Turnout summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="p-4 rounded-lg border" style="background-color: rgba(99,102,241,0.05); border-color:#6366F1;">
            <div class="flex items-center">
              <div class="p-3 rounded-lg mr-4 bg-indigo-500">
                <i class="fas fa-percentage text-white text-xl"></i>
              </div>
              <div>
                <p class="text-sm text-indigo-600"><?= $selectedYear ?> Turnout</p>
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
                <p class="text-sm text-purple-600"><?= $selectedYear - 1 ?> Turnout</p>
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
                <p class="text-sm text-blue-600">Elections</p>
                <p class="text-2xl font-bold text-blue-800">
                  <?= $currentYearTurnout['election_count'] ?? 0 ?>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Turnout trend line -->
        <div class="p-4 rounded-lg mb-8" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Turnout Rate Trend</h3>
          <div class="chart-container" style="height: 400px;">
            <canvas id="turnoutTrendChart"></canvas>
          </div>
        </div>

        <!-- Yearly turnout table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-8">
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
                <?php foreach ($turnoutRangeData as $year => $data):
                  $isPositive = ($data['growth_rate'] ?? 0) > 0;
                  $trendIcon  = $isPositive ? 'fa-arrow-up' : (($data['growth_rate'] ?? 0) < 0 ? 'fa-arrow-down' : 'fa-minus');
                  $trendColor = $isPositive ? 'text-green-600' : (($data['growth_rate'] ?? 0) < 0 ? 'text-red-600' : 'text-gray-600');
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
                    <span class="<?= $trendColor ?>">
                      <?= ($data['growth_rate'] ?? 0) > 0 ? '+' : '' ?><?= $data['growth_rate'] ?? 0 ?>%
                    </span>
                    <i class="fas <?= $trendIcon ?> <?= $trendColor ?> ml-1"></i>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Elections vs Turnout chart -->
        <div class="p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Elections vs Turnout Rate</h3>
          <div class="mb-4">
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
              <div class="flex items-center">
                <label for="dataSeriesSelect" class="mr-3 text-sm font-medium text-gray-700">Data Series:</label>
                <select id="dataSeriesSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                  <option value="elections">Elections vs Turnout</option>
                  <option value="voters">Students vs Turnout</option>
                  <option value="abstained">Abstained</option>
                </select>
              </div>
              <div class="flex items-center">
                <label for="breakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
                <select id="breakdownSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                  <option value="year">Year</option>
                  <option value="election">Election (Current Year)</option>
                  <option value="department">Department</option>
                  <option value="course">Course</option>
                </select>
              </div>
            </div>
          </div>
          <div class="chart-container" style="height: 400px;">
            <canvas id="electionsVsTurnoutChart"></canvas>
          </div>
          <div id="turnoutBreakdownTable" class="mt-6 overflow-x-auto"></div>
          
          <!-- Year Range Selector -->
          <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-medium text-blue-800 mb-2">Turnout Analysis – Year Range</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="fromYear" class="block text-sm font-medium text-blue-800">From year</label>
                <select id="fromYear" name="from_year" class="mt-1 p-2 border rounded w-full">
                  <?php foreach ($allTurnoutYears as $y): ?>
                    <option value="<?= $y ?>" <?= ($y == $fromYear) ? 'selected' : '' ?>><?= $y ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="toYear" class="block text-sm font-medium text-blue-800">To year</label>
                <select id="toYear" name="to_year" class="mt-1 p-2 border rounded w-full">
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
        </div>

      </div> <!-- /Turnout section -->
    </div> <!-- /Analytics section -->

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // ===== Force password change (same design as voter modal) =====
  const forcePasswordChange = <?= $force_password_flag ?>;
  
  if (forcePasswordChange === 1) {
    const modal = document.getElementById('forcePasswordChangeModal');
    if (modal) {
      modal.classList.remove('hidden');
      document.body.style.pointerEvents = 'none';
      modal.style.pointerEvents = 'auto';
    }
  }

  const togglePassword = document.getElementById('togglePassword');
  const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
  const passwordInput = document.getElementById('newPassword');
  const confirmPasswordInput = document.getElementById('confirmPassword');

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
    });
  }

  if (toggleConfirmPassword && confirmPasswordInput) {
    toggleConfirmPassword.addEventListener('click', function() {
      const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      confirmPasswordInput.setAttribute('type', type);
    });
  }

  function updateCheck(id, isValid) {
    const element = document.getElementById(id);
    if (!element) return;
    const icon = element.querySelector('svg');
    
    if (isValid) {
      element.classList.remove('text-gray-500');
      element.classList.add('text-green-500');
      if (icon) {
        icon.classList.remove('text-gray-400');
        icon.classList.add('text-green-500');
      }
    } else {
      element.classList.remove('text-green-500');
      element.classList.add('text-gray-500');
      if (icon) {
        icon.classList.remove('text-green-500');
        icon.classList.add('text-gray-400');
      }
    }
  }

  function checkPasswordMatch() {
    if (!passwordInput || !confirmPasswordInput) return;
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    const matchError = document.getElementById('matchError');
    
    if (!matchError) return;
    
    if (confirmPassword && password !== confirmPassword) {
      matchError.classList.remove('hidden');
      confirmPasswordInput.classList.add('border-red-500');
    } else {
      matchError.classList.add('hidden');
      confirmPasswordInput.classList.remove('border-red-500');
    }
  }

  if (passwordInput) {
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      const strengthBar = document.getElementById('strengthBar');
      const strengthText = document.getElementById('passwordStrength');
      
      const length = password.length >= 8;
      const uppercase = /[A-Z]/.test(password);
      const number = /[0-9]/.test(password);
      const special = /[!@#$%^&*(),.?":{}|<>]/.test(password);
      
      updateCheck('lengthCheck', length);
      updateCheck('uppercaseCheck', uppercase);
      updateCheck('numberCheck', number);
      updateCheck('specialCheck', special);
      
      let strength = 0;
      if (length) strength++;
      if (uppercase) strength++;
      if (number) strength++;
      if (special) strength++;
      
      if (strengthBar && strengthText) {
        const strengthPercentage = (strength / 4) * 100;
        strengthBar.style.width = strengthPercentage + '%';
        
        if (strength === 0) {
          strengthBar.className = 'h-2 rounded-full bg-red-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Weak';
          strengthText.className = 'font-medium text-red-500';
        } else if (strength === 1) {
          strengthBar.className = 'h-2 rounded-full bg-orange-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Weak';
          strengthText.className = 'font-medium text-orange-500';
        } else if (strength === 2) {
          strengthBar.className = 'h-2 rounded-full bg-yellow-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Medium';
          strengthText.className = 'font-medium text-yellow-500';
        } else if (strength === 3) {
          strengthBar.className = 'h-2 rounded-full bg-blue-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Strong';
          strengthText.className = 'font-medium text-blue-500';
        } else {
          strengthBar.className = 'h-2 rounded-full bg-green-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Strong';
          strengthText.className = 'font-medium text-green-500';
        }
      }
      
      checkPasswordMatch();
    });
  }

  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
  }

  const forcePasswordChangeForm = document.getElementById('forcePasswordChangeForm');
  const passwordError = document.getElementById('passwordError');
  const passwordSuccess = document.getElementById('passwordSuccess');
  const passwordLoading = document.getElementById('passwordLoading');
  const passwordErrorText = document.getElementById('passwordErrorText');
  const submitBtn = document.getElementById('submitBtn');
  const submitBtnText = document.getElementById('submitBtnText');
  const submitLoader = document.getElementById('submitLoader');

  if (forcePasswordChangeForm && passwordInput && confirmPasswordInput) {
    forcePasswordChangeForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      if (passwordError) passwordError.classList.add('hidden');
      if (passwordSuccess) passwordSuccess.classList.add('hidden');
      if (passwordLoading) passwordLoading.classList.remove('hidden');
      
      const newPassword = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      
      const length = newPassword.length >= 8;
      const uppercase = /[A-Z]/.test(newPassword);
      const number = /[0-9]/.test(newPassword);
      const special = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
      
      let strength = 0;
      if (length) strength++;
      if (uppercase) strength++;
      if (number) strength++;
      if (special) strength++;
      
      if (!length) {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "Password must be at least 8 characters long.";
        if (passwordError) passwordError.classList.remove('hidden');
        return;
      }
      
      if (strength < 3) {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "Password is not strong enough. Please include at least 2 of the following: uppercase letter, number, special character.";
        if (passwordError) passwordError.classList.remove('hidden');
        return;
      }
      
      if (newPassword !== confirmPassword) {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "Passwords do not match.";
        if (passwordError) passwordError.classList.remove('hidden');
        return;
      }
      
      if (submitBtn) submitBtn.disabled = true;
      if (submitBtnText) submitBtnText.textContent = 'Updating...';
      if (submitLoader) submitLoader.classList.remove('hidden');
      
      fetch('update_admin_password.php', {   // admin endpoint for college admin
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ new_password: newPassword })
      })
      .then(response => response.json())
      .then(data => {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        
        if (data.status === 'success') {
          if (passwordSuccess) passwordSuccess.classList.remove('hidden');
          if (submitBtn) submitBtn.disabled = false;
          if (submitBtnText) submitBtnText.textContent = 'Update Password';
          if (submitLoader) submitLoader.classList.add('hidden');
          
          setTimeout(() => {
            const modal = document.getElementById('forcePasswordChangeModal');
            if (modal) modal.classList.add('hidden');
            document.body.style.pointerEvents = 'auto';
            
            const redirectOverlay = document.createElement('div');
            redirectOverlay.className = 'fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center';
            redirectOverlay.innerHTML = `
              <div class="text-center">
                <svg class="animate-spin h-12 w-12 text-[var(--cvsu-green)] mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-lg font-medium text-gray-700">Redirecting to dashboard...</p>
              </div>
            `;
            document.body.appendChild(redirectOverlay);
            
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          }, 2000);
        } else {
          if (submitBtn) submitBtn.disabled = false;
          if (submitBtnText) submitBtnText.textContent = 'Update Password';
          if (submitLoader) submitLoader.classList.add('hidden');
          
          if (passwordErrorText) passwordErrorText.textContent = data.message || "Failed to update password.";
          if (passwordError) passwordError.classList.remove('hidden');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = false;
        if (submitBtnText) submitBtnText.textContent = 'Update Password';
        if (submitLoader) submitLoader.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "An error occurred. Please try again.";
        if (passwordError) passwordError.classList.remove('hidden');
      });
    });
  }


  // Toggle breakdown table (department / course)
  const breakdownType = document.getElementById('breakdownType');
  breakdownType?.addEventListener('change', function() {
    document.querySelectorAll('.breakdown-section').forEach(s => s.classList.remove('active'));
    document.getElementById(this.value + 'Breakdown')?.classList.add('active');
  });

  // Year selector for turnout
  const turnoutYearSelector = document.getElementById('turnoutYearSelector');
  turnoutYearSelector?.addEventListener('change', function() {
    const url = new URL(window.location.href);
    url.searchParams.set('year', this.value);
    window.location.href = url.toString();
  });

  let departmentChartInstance = null;
  let courseChartInstance = null;
  let turnoutTrendChartInstance = null;
  let electionsVsTurnoutChartInstance = null;

  // Data from PHP
  const departmentLabels = <?= json_encode(array_column($votersByDepartment, 'department_name')) ?>;
  const departmentCounts = <?= json_encode(array_column($votersByDepartment, 'count')) ?>;
  const courseLabelsRaw  = <?= json_encode(array_column($votersByCourse, 'course')) ?>;
  const courseCounts     = <?= json_encode(array_column($votersByCourse, 'count')) ?>;
  const turnoutYearsJS   = <?= json_encode(array_keys($turnoutRangeData)) ?>;
  const turnoutRatesJS   = <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>;
  const departmentTurnoutData = <?= json_encode($departmentTurnoutData) ?>;
  const courseTurnoutData     = <?= json_encode($courseTurnoutData) ?>;
  const courseMapJS      = <?= json_encode($courseMap) ?>;
  const abstainYearsJS   = <?= json_encode($abstainYears) ?>;
  const abstainCountsYearJS = <?= json_encode($abstainCountsYear) ?>;
  const abstainRatesYearJS  = <?= json_encode($abstainRatesYear) ?>;

  function getFullCourseNameJS(code) {
    return courseMapJS[code] || code;
  }

  function closePasswordModal() {
    const forcePasswordChange = <?= $force_password_flag ?>;
    // Do not allow closing if still forced
    if (forcePasswordChange === 1) return;
    const modal = document.getElementById('forcePasswordChangeModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.pointerEvents = 'auto';
  }

  function getAbbreviatedName(fullName) {
    if (!fullName) return '';
    const excludeWords = ['and', 'of', 'the', 'in', 'for', 'at', 'by'];

    if (fullName.length <= 6 && fullName.toUpperCase() === fullName) {
      return fullName;
    }
    if (fullName.startsWith('College of ')) {
      const rest  = fullName.substring(11).trim();
      const words = rest.split(' ');
      return words.filter(w => !excludeWords.includes(w.toLowerCase()))
                  .map(w => w[0]).join('').toUpperCase();
    }
    if (fullName.startsWith('Department of ')) {
      const rest  = fullName.substring(13).trim();
      const words = rest.split(' ');
      return 'D' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                        .map(w => w[0]).join('').toUpperCase();
    }
    if (fullName.startsWith('Bachelor of Science in ')) {
      const rest  = fullName.substring(22).trim();
      const words = rest.split(' ');
      return 'BS' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                         .map(w => w[0]).join('').toUpperCase();
    }
    if (fullName.startsWith('Bachelor of Arts in ')) {
      const rest  = fullName.substring(18).trim();
      const words = rest.split(' ');
      return 'BA' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                         .map(w => w[0]).join('').toUpperCase();
    }
    if (fullName.startsWith('Bachelor of ')) {
      const rest  = fullName.substring(13).trim();
      const words = rest.split(' ');
      return 'B' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                        .map(w => w[0]).join('').toUpperCase();
    }
    if (fullName.startsWith('Master of ')) {
      const rest  = fullName.substring(11).trim();
      const words = rest.split(' ');
      return 'M' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                        .map(w => w[0]).join('').toUpperCase();
    }
    if (fullName.startsWith('Doctor of ')) {
      const rest  = fullName.substring(11).trim();
      const words = rest.split(' ');
      return 'D' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                        .map(w => w[0]).join('').toUpperCase();
    }
    return fullName;
  }

  // Department doughnut
  const departmentCtx   = document.getElementById('departmentChart');
  const chartTooltip    = document.getElementById('chartTooltip');
  const tooltipTitleEl  = chartTooltip?.querySelector('.title');
  const tooltipCountEl  = chartTooltip?.querySelector('.count');

  if (departmentCtx && !departmentChartInstance) {
    const baseColors = [
      '#1E6F46','#37A66B','#FFD166','#154734','#2D5F3F','#4A7C59',
      '#5A8F6A','#6A9F7A','#7AA78A','#8ABF9A','#9BD7AA','#B0E0B6',
      '#C2EAC2','#D4F3CE','#E5FBDC'
    ];
    const datasetColors = departmentLabels.map((_, i) => baseColors[i % baseColors.length]);

    departmentChartInstance = new Chart(departmentCtx, {
      type: 'doughnut',
      data: {
        labels: departmentLabels.map(label => getAbbreviatedName(label)),
        datasets: [{
          data: departmentCounts,
          backgroundColor: datasetColors,
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
              font: { size: 12 },
              padding: 15,
              usePointStyle: true,
              pointStyle: 'circle'
            }
          },
          tooltip: { enabled: false }
        },
        animation: {
          animateRotate: true,
          animateScale: true,
          duration: 1000,
          easing: 'easeOutQuart'
        },
        onHover: (event, active) => {
          event.native.target.style.cursor = active.length ? 'pointer' : 'default';
          if (!tooltipTitleEl || !tooltipCountEl) return;
          if (active.length) {
            const i     = active[0].index;
            const full  = departmentLabels[i];
            const count = departmentCounts[i];
            const total = departmentCounts.reduce((a, b) => a + b, 0);
            const pct   = total > 0 ? Math.round((count / total) * 100) : 0;
            const color = datasetColors[i];

            tooltipTitleEl.innerHTML = `
              <div style="display:flex;align-items:center;margin-bottom:8px;">
                <span style="display:inline-block;width:12px;height:12px;background-color:${color};border-radius:50%;margin-right:8px;"></span>
                ${full}
              </div>
            `;
            tooltipCountEl.innerHTML = `
              <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span>Students:</span><span style="font-weight:bold;">${count}</span>
              </div>
              <div style="display:flex;justify-content:space-between;">
                <span>Percentage:</span><span style="font-weight:bold;">${pct}%</span>
              </div>
            `;
            chartTooltip.classList.add('show');
          } else {
            chartTooltip.classList.remove('show');
          }
        }
      }
    });

    departmentCtx.addEventListener('mouseleave', () => chartTooltip?.classList.remove('show'));
  }

  // Course bar chart
  const courseCtx = document.getElementById('courseChart');
  if (courseCtx && !courseChartInstance) {
    courseChartInstance = new Chart(courseCtx, {
      type: 'bar',
      data: {
        labels: courseLabelsRaw.map(code => getAbbreviatedName(code)),
        datasets: [{
          label: 'Student Count',
          data: courseCounts,
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
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 14 },
            bodyFont:  { size: 13 },
            padding: 12,
            displayColors: false,
            callbacks: {
              title: (items) => {
                const idx  = items[0].dataIndex;
                const code = courseLabelsRaw[idx];
                return getFullCourseNameJS(code);
              },
              label: (ctx) => {
                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                const pct   = total > 0 ? Math.round((ctx.raw / total) * 100) : 0;
                return [`Students: ${ctx.raw}`, `Percentage: ${pct}%`];
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 12 } },
            grid: { color: 'rgba(0,0,0,0.1)' },
            title: {
              display: true,
              text: 'Number of Students',
              font: { size: 14, weight: 'bold' }
            }
          },
          x: {
            ticks: {
              font: { size: 12 },
              maxRotation: 0,
              minRotation: 0,
              callback: (val, index) => getAbbreviatedName(courseLabelsRaw[index])
            },
            grid: { display: false },
            title: {
              display: true,
              text: 'Course',
              font: { size: 14, weight: 'bold' }
            }
          }
        },
        animation: { duration: 1000, easing: 'easeOutQuart' },
        onHover: (e, a) => e.native.target.style.cursor = a.length ? 'pointer' : 'default'
      }
    });
  }

  // Turnout trend line
  const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
  if (turnoutTrendCtx && !turnoutTrendChartInstance) {
    turnoutTrendChartInstance = new Chart(turnoutTrendCtx, {
      type: 'line',
      data: {
        labels: turnoutYearsJS,
        datasets: [{
          label: 'Turnout Rate (%)',
          data: turnoutRatesJS,
          borderColor: '#1E6F46',
          backgroundColor: 'rgba(30,111,70,0.1)',
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
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 14 },
            bodyFont:  { size: 13 },
            padding: 12,
            callbacks: {
              label: c => `Turnout: ${c.raw}%`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { callback: v => v + '%' },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // === Elections vs Turnout / Students vs Turnout / Abstained ===

  const chartData = {
    elections: {
      year: {
        labels: <?= json_encode(array_keys($turnoutRangeData)) ?>,
        electionCounts: <?= json_encode(array_column($turnoutRangeData, 'election_count')) ?>,
        turnoutRates:   <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>
      }
    },
    voters: {
      year: {
        labels: <?= json_encode(array_keys($turnoutRangeData)) ?>,
        eligibleCounts: <?= json_encode(array_column($turnoutRangeData, 'total_eligible')) ?>,
        turnoutRates:   <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>
      },
      department: departmentTurnoutData,
      course:     courseTurnoutData
    },
    abstained: {
      year: {
        labels: abstainYearsJS,
        abstainCounts: abstainCountsYearJS,
        abstainRates:  abstainRatesYearJS
      }
    }
  };

  // Per-election stats for selected year (current year context)
  const electionTurnoutStats = <?= json_encode($electionTurnoutStats) ?>;
  const selectedYearJS       = <?= (int)$selectedYear ?>;

  let currentDataSeries = 'elections'; // 'elections' | 'voters' | 'abstained'
  let currentBreakdown  = 'year';      // 'year' | 'election' | 'department' | 'course'

  function resetBreakdownOptions() {
    const select = document.getElementById('breakdownSelect');
    if (!select) return;

    // Clear options
    select.innerHTML = '';

    let options = [];
    if (currentDataSeries === 'elections') {
      options = [
        { value: 'year',     label: 'Year' },
        { value: 'election', label: 'Election (Current Year)' }
      ];
    } else if (currentDataSeries === 'voters') {
      options = [
        { value: 'year',       label: 'Year' },
        { value: 'election',   label: 'Election (Current Year)' },
        { value: 'department', label: 'Department' },
        { value: 'course',     label: 'Course' }
      ];
    } else if (currentDataSeries === 'abstained') {
      options = [
        { value: 'year',     label: 'Year' },
        { value: 'election', label: 'Election (Current Year)' }
      ];
    }

    options.forEach(opt => {
      const o = document.createElement('option');
      o.value = opt.value;
      o.textContent = opt.label;
      select.appendChild(o);
    });

    const validValues = options.map(o => o.value);
    if (!validValues.includes(currentBreakdown)) {
      currentBreakdown = options[0].value;
    }
    select.value = currentBreakdown;
  }

  function renderElectionsVsTurnout() {
    const ctx = document.getElementById('electionsVsTurnoutChart');
    if (!ctx) return;
    if (electionsVsTurnoutChartInstance) electionsVsTurnoutChartInstance.destroy();

    resetBreakdownOptions();

    let data, options;
    let titleText = '';

    if (currentDataSeries === 'elections') {
      if (currentBreakdown === 'year') {
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
        titleText = 'Elections vs Turnout Rate by Year';
      } else if (currentBreakdown === 'election') {
        const labels = electionTurnoutStats.map(e => e.title);
        const voted  = electionTurnoutStats.map(e => e.total_voted);
        const rates  = electionTurnoutStats.map(e => e.turnout_rate);

        data = {
          labels,
          datasets: [
            {
              label: 'Students Participated',
              data: voted,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: rates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Elections vs Turnout Rate by Election (${selectedYearJS})`;
      }
    } else if (currentDataSeries === 'voters') {
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.voters.year.labels,
          datasets: [
            {
              label: 'Eligible Students',
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
        titleText = 'Eligible Students vs Turnout Rate by Year';
      } else if (currentBreakdown === 'department') {
        const labels   = chartData.voters.department.map(x => getAbbreviatedName(x.department));
        const eligible = chartData.voters.department.map(x => x.eligible_count);
        const tr       = chartData.voters.department.map(x => x.turnout_rate);

        data = {
          labels,
          datasets: [
            {
              label: 'Eligible Students',
              data: eligible,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: tr,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = 'Eligible Students vs Turnout Rate by Department';
      } else if (currentBreakdown === 'course') {
        const labels   = chartData.voters.course.map(x => getAbbreviatedName(x.course));
        const eligible = chartData.voters.course.map(x => x.eligible_count);
        const tr       = chartData.voters.course.map(x => x.turnout_rate);

        data = {
          labels,
          datasets: [
            {
              label: 'Eligible Students',
              data: eligible,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: tr,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = 'Eligible Students vs Turnout Rate by Course';
      } else if (currentBreakdown === 'election') {
        const labels   = electionTurnoutStats.map(e => e.title);
        const eligible = electionTurnoutStats.map(e => e.total_eligible);
        const tr       = electionTurnoutStats.map(e => e.turnout_rate);

        data = {
          labels,
          datasets: [
            {
              label: 'Eligible Students',
              data: eligible,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: tr,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Eligible Students vs Turnout Rate by Election (${selectedYearJS})`;
      }
    } else if (currentDataSeries === 'abstained') {
      if (currentBreakdown === 'year') {
        const labels = chartData.abstained.year.labels;
        const abst   = chartData.abstained.year.abstainCounts;
        const rates  = chartData.abstained.year.abstainRates;

        data = {
          labels,
          datasets: [
            {
              label: 'Abstained Students',
              data: abst,
              backgroundColor: '#EF4444',
              borderColor: '#B91C1C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Abstain Rate (%)',
              data: rates,
              backgroundColor: '#F97316',
              borderColor: '#C2410C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = 'Abstained Students by Year';
      } else if (currentBreakdown === 'election') {
        const withAbstain = electionTurnoutStats.filter(e => (e.abstain_count || 0) > 0);
        const labels = withAbstain.map(e => e.title);
        const abst   = withAbstain.map(e => e.abstain_count);
        const rates  = withAbstain.map(e => e.abstain_rate);

        data = {
          labels,
          datasets: [
            {
              label: 'Abstained Students',
              data: abst,
              backgroundColor: '#EF4444',
              borderColor: '#B91C1C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Abstain Rate (%)',
              data: rates,
              backgroundColor: '#F97316',
              borderColor: '#C2410C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Abstained Students by Election (${selectedYearJS})`;
      }
    }

    options = {
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
              if (dsLabel.includes('%')) {
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
            text:
              currentDataSeries === 'elections'
                ? (currentBreakdown === 'year' ? 'Number of Elections' : 'Students Participated')
                : (currentDataSeries === 'abstained' ? 'Abstained Students' : 'Number of Students'),
            font: { size: 14, weight: 'bold' }
          }
        },
        y1: {
          beginAtZero: true,
          max: 100,
          position: 'right',
          title: {
            display: true,
            text: currentDataSeries === 'abstained' ? 'Abstain Rate (%)' : 'Turnout Rate (%)',
            font: { size: 14, weight: 'bold' }
          },
          ticks: { callback: v => v + '%' },
          grid: { drawOnChartArea: false }
        },
        x: { grid: { display: false } }
      }
    };

    electionsVsTurnoutChartInstance = new Chart(ctx, {
      type: 'bar',
      data,
      options
    });

    buildTurnoutBreakdownTable();
  }

  function buildTurnoutBreakdownTable() {
    const container = document.getElementById('turnoutBreakdownTable');
    if (!container) return;
    container.innerHTML = '';

    let headers = [];
    let rows    = [];

    if (currentDataSeries === 'elections') {
      if (currentBreakdown === 'year') {
        headers = ['Year', 'Number of Elections', 'Turnout Rate'];
        rows    = chartData.elections.year.labels.map((label, i) => [
          label,
          chartData.elections.year.electionCounts[i].toLocaleString(),
          chartData.elections.year.turnoutRates[i] + '%'
        ]);
      } else if (currentBreakdown === 'election') {
        headers = ['Election', 'Students Participated', 'Eligible Students', 'Turnout Rate'];
        rows    = electionTurnoutStats.map(e => [
          e.title,
          (e.total_voted    || 0).toLocaleString(),
          (e.total_eligible || 0).toLocaleString(),
          (e.turnout_rate   || 0) + '%'
        ]);
      }
    } else if (currentDataSeries === 'voters') {
      if (currentBreakdown === 'year') {
        headers = ['Year', 'Eligible Students', 'Turnout Rate'];
        rows    = chartData.voters.year.labels.map((label, i) => [
          label,
          chartData.voters.year.eligibleCounts[i].toLocaleString(),
          chartData.voters.year.turnoutRates[i] + '%'
        ]);
      } else if (currentBreakdown === 'department') {
        headers = ['Department', 'Eligible Students', 'Voted', 'Turnout Rate'];
        rows    = chartData.voters.department.map(row => [
          row.department,
          row.eligible_count.toLocaleString(),
          row.voted_count.toLocaleString(),
          row.turnout_rate + '%'
        ]);
      } else if (currentBreakdown === 'course') {
        headers = ['Course', 'Eligible Students', 'Voted', 'Turnout Rate'];
        rows    = chartData.voters.course.map(row => [
          getFullCourseNameJS(row.course),
          row.eligible_count.toLocaleString(),
          row.voted_count.toLocaleString(),
          row.turnout_rate + '%'
        ]);
      } else if (currentBreakdown === 'election') {
        headers = ['Election', 'Eligible Students', 'Students Participated', 'Turnout Rate'];
        rows    = electionTurnoutStats.map(e => [
          e.title,
          (e.total_eligible || 0).toLocaleString(),
          (e.total_voted    || 0).toLocaleString(),
          (e.turnout_rate   || 0) + '%'
        ]);
      }
    } else if (currentDataSeries === 'abstained') {
      if (currentBreakdown === 'year') {
        headers = ['Year', 'Abstained Students', 'Abstain Rate'];
        rows    = chartData.abstained.year.labels.map((label, i) => [
          label,
          (chartData.abstained.year.abstainCounts[i] || 0).toLocaleString(),
          (chartData.abstained.year.abstainRates[i]  || 0) + '%'
        ]);
      } else if (currentBreakdown === 'election') {
        headers = ['Election', 'Abstained Students', 'Abstain Rate', 'Eligible Students'];

        const withAbstain = electionTurnoutStats.filter(e => (e.abstain_count || 0) > 0);

        if (withAbstain.length === 0) {
          const msg = document.createElement('div');
          msg.className = 'text-center text-gray-600 text-sm py-4';
          msg.textContent = `No abstained voters found for ${selectedYearJS}.`;
          container.appendChild(msg);
          return;
        }

        rows = withAbstain.map(e => [
          e.title,
          (e.abstain_count || 0).toLocaleString(),
          (e.abstain_rate  || 0) + '%',
          (e.total_eligible || 0).toLocaleString()
        ]);
      }
    }

    if (!headers.length) return;

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200';

    const thead = document.createElement('thead');
    const trHead = document.createElement('tr');
    trHead.className = 'bg-gray-50';

    headers.forEach(h => {
      const th = document.createElement('th');
      th.className = 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
      th.textContent = h;
      trHead.appendChild(th);
    });
    thead.appendChild(trHead);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    tbody.className = 'bg-white divide-y divide-gray-200';

    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-gray-50';
      r.forEach((cell, idx) => {
        const td = document.createElement('td');
        td.className = 'px-6 py-4 whitespace-nowrap ' + (idx === 0 ? 'font-medium text-gray-900' : 'text-gray-700');
        td.textContent = cell;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    container.appendChild(table);
  }

  // Init Elections vs Turnout
  renderElectionsVsTurnout();

  document.getElementById('dataSeriesSelect')?.addEventListener('change', function() {
    currentDataSeries = this.value; // 'elections' | 'voters' | 'abstained'
    renderElectionsVsTurnout();
  });

  document.getElementById('breakdownSelect')?.addEventListener('change', function() {
    currentBreakdown = this.value;
    renderElectionsVsTurnout();
  });

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

    window.location.href = url.toString();
  }

  fromYearSelect?.addEventListener('change', submitYearRange);
  toYearSelect?.addEventListener('change', submitYearRange);
});
</script>
</body>
</html>
