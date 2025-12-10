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
    die("Database connection failed: " . $e->getMessage());
}

// --- Auth check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
    header('Location: login.php');
    exit();
}

$userId        = (int)$_SESSION['user_id'];
$scopeCategory = $_SESSION['scope_category'] ?? '';
$adminStatus   = $_SESSION['admin_status']   ?? 'inactive';

// --- Force password change flag ---
$stmtFP = $pdo->prepare("SELECT force_password_change FROM users WHERE user_id = :uid");
$stmtFP->execute([':uid' => $userId]);
$forceRow = $stmtFP->fetch();
$force_password_flag = (int)($forceRow['force_password_change'] ?? 0);

// --- Super admin impersonation via ?scope_id= ---
$impersonatedScopeId = getImpersonatedScopeId();
$seat                 = null;

if ($impersonatedScopeId !== null) {
    $seat = fetchScopeSeatById($pdo, $impersonatedScopeId);

    if (!$seat || $seat['scope_type'] !== SCOPE_NONACAD_STUDENT) {
        die('Invalid scope for this Non-Academic-Student dashboard.');
    }

    $scopeCategory = $seat['scope_type']; // "Non-Academic-Student"
    $adminStatus   = 'active';
}

// Dashboard is ONLY for Non-Academic-Student admins
if ($scopeCategory !== SCOPE_NONACAD_STUDENT) {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

if ($adminStatus !== 'active') {
    header('Location: login.php?error=Your admin account is inactive.');
    exit();
}

// --- Resolve Non-Academic-Student scope seat (admin_scopes) ---
$scopeId = null;

if ($impersonatedScopeId !== null) {
    $scopeId = (int)$impersonatedScopeId;
} else {
    $scopeStmt = $pdo->prepare("
        SELECT scope_id
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid'   => $userId,
        ':stype' => SCOPE_NONACAD_STUDENT,
    ]);
    if ($row = $scopeStmt->fetch()) {
        $scopeId = (int)$row['scope_id'];
    }
}

if ($scopeId === null) {
    die('No Non-Academic-Student scope seat found for this admin.');
}

// --- Get available years for dropdown (based on elections for this scope) ---
$stmt = $pdo->prepare("
    SELECT DISTINCT YEAR(start_datetime) as year
    FROM elections
    WHERE election_scope_type = 'Non-Academic-Student'
      AND owner_scope_id      = ?
    ORDER BY year DESC
");
$stmt->execute([$scopeId]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

$currentYear  = (int)date('Y');
$selectedYear = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$previousYear = $selectedYear - 1;

// =========================
// SCOPED VOTERS & ELECTIONS
// =========================

// All Non-Academic-Student voters for this scope
$scopedNAS = getScopedVoters(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    $scopeId,
    [
        'year_end'      => null,
        'include_flags' => true,
    ]
);

$total_voters = count($scopedNAS);

// Elections for this Non-Academic-Student scope
$scopedElectionsAll = getScopedElections(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    $scopeId
);

$total_elections   = count($scopedElectionsAll);
$ongoing_elections = 0;
foreach ($scopedElectionsAll as $erow) {
    if (($erow['status'] ?? '') === 'ongoing') {
        $ongoing_elections++;
    }
}

$elections = $scopedElectionsAll;

// =========================
// BASIC DATES & NEW THIS MONTH
// =========================

$currentMonthStart = date('Y-m-01');
$currentMonthEnd   = date('Y-m-t 23:59:59');

$newVoters = 0;

foreach ($scopedNAS as $v) {
    $created = $v['created_at'] ?? null;
    if (!$created) continue;

    if ($created >= $currentMonthStart && $created <= $currentMonthEnd) {
        $newVoters++;
    }
}

// =========================
// DISTRIBUTIONS: COLLEGE, DEPARTMENT, COURSE
// =========================

$votersByCollege = []; // department (college field) => count
foreach ($scopedNAS as $v) {
    $college = $v['department'] ?: 'Unspecified';
    if (!isset($votersByCollege[$college])) {
        $votersByCollege[$college] = 0;
    }
    $votersByCollege[$college]++;
}

$votersByCollegeArr = [];
foreach ($votersByCollege as $name => $cnt) {
    $votersByCollegeArr[] = [
        'college_name' => $name,
        'count'        => (int)$cnt,
    ];
}
usort($votersByCollegeArr, fn($a, $b) => $b['count'] <=> $a['count']);

// Department bar: college + department1
$collegeDepartmentBar = [];
$deptAgg              = [];

foreach ($scopedNAS as $v) {
    $college    = $v['department']  ?: 'Unspecified';
    $department = $v['department1'] ?: 'General';
    $key        = $college . '|' . $department;

    if (!isset($deptAgg[$key])) {
        $deptAgg[$key] = [
            'college_name'   => $college,
            'department_name'=> $department,
            'count'          => 0,
        ];
    }
    $deptAgg[$key]['count']++;
}
$collegeDepartmentBar = array_values($deptAgg);

// Course bar: college + course
$collegeCourseBar = [];
$courseAgg        = [];

foreach ($scopedNAS as $v) {
    $college = $v['department'] ?: 'Unspecified';
    $course  = $v['course']     ?? '';
    if ($course === '') continue;
    $key = $college . '|' . $course;
    if (!isset($courseAgg[$key])) {
        $courseAgg[$key] = [
            'college_name' => $college,
            'course'       => $course,
            'count'        => 0,
        ];
    }
    $courseAgg[$key]['count']++;
}
$collegeCourseBar = array_values($courseAgg);

// --- Totals for summary cards (Non-Academic Students analytics) ---
$totalColleges    = count($votersByCollegeArr);
$totalDepartments = count(array_unique(array_map(
    fn($row) => $row['department_name'] ?? '',
    $collegeDepartmentBar
)));
$totalCourses     = count($collegeCourseBar);

// =========================
// TURNOUT BY YEAR (analytics_scopes)
// =========================

$turnoutDataByYear = computeTurnoutByYear(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    $scopeId,
    $scopedNAS,
    [
        'year_from' => null,
        'year_to'   => null,
    ]
);

// Ensure at least current + previous year exist
if (empty($turnoutDataByYear)) {
    $turnoutDataByYear[$currentYear] = [
        'year'           => $currentYear,
        'total_voted'    => 0,
        'total_eligible' => 0,
        'turnout_rate'   => 0.0,
        'election_count' => 0,
        'growth_rate'    => 0.0,
    ];
}

if (!isset($turnoutDataByYear[$selectedYear])) {
    $turnoutDataByYear[$selectedYear] = [
        'year'           => $selectedYear,
        'total_voted'    => 0,
        'total_eligible' => 0,
        'turnout_rate'   => 0.0,
        'election_count' => 0,
        'growth_rate'    => 0.0,
    ];
}
if (!isset($turnoutDataByYear[$previousYear])) {
    $turnoutDataByYear[$previousYear] = [
        'year'           => $previousYear,
        'total_voted'    => 0,
        'total_eligible' => 0,
        'turnout_rate'   => 0.0,
        'election_count' => 0,
        'growth_rate'    => 0.0,
    ];
}

ksort($turnoutDataByYear);

// Year range filtering
$allTurnoutYears = array_keys($turnoutDataByYear);
$defaultYear     = (int)date('Y');
$minYear         = $allTurnoutYears ? min($allTurnoutYears) : $defaultYear;
$maxYear         = $allTurnoutYears ? max($allTurnoutYears) : $defaultYear;

$fromYear = isset($_GET['from_year']) && ctype_digit($_GET['from_year'])
    ? (int)$_GET['from_year']
    : $minYear;

$toYear   = isset($_GET['to_year']) && ctype_digit($_GET['to_year'])
    ? (int)$_GET['to_year']
    : $maxYear;

if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear   < $fromYear) $toYear  = $fromYear;

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
        $currRate = $row['turnout_rate']         ?? 0.0;
        $row['growth_rate'] = $prevRate > 0
            ? round((($currRate - $prevRate) / $prevRate) * 100, 1)
            : 0.0;
    }
    $prevY = $y;
}
unset($row);

$turnoutYears = array_keys($turnoutDataByYear);
sort($turnoutYears);

$currentYearTurnout  = $turnoutDataByYear[$selectedYear]     ?? null;
$previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;

// =========================
// TURNOUT BY COLLEGE & DEPARTMENT (selected year)
// =========================

// Distinct voters who voted in this scope's elections for selectedYear
$stmt = $pdo->prepare("
    SELECT DISTINCT v.voter_id
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    WHERE e.election_scope_type = 'Non-Academic-Student'
      AND e.owner_scope_id      = :sid
      AND YEAR(e.start_datetime) = :year
");
$stmt->execute([
    ':sid'  => $scopeId,
    ':year' => $selectedYear,
]);
$votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$votedSet = array_flip($votedIds);

$yearEndSelected = sprintf('%04d-12-31 23:59:59', $selectedYear);

// College turnout
$collegeTurnoutData = [];
$collegeBuckets     = [];

foreach ($scopedNAS as $v) {
    $createdAt = $v['created_at'] ?? null;
    if ($createdAt && $createdAt > $yearEndSelected) continue;

    $college = $v['department'] ?: 'Unspecified';
    if (!isset($collegeBuckets[$college])) {
        $collegeBuckets[$college] = [
            'college'        => $college,
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }
    $collegeBuckets[$college]['eligible_count']++;
    if (isset($votedSet[$v['user_id']])) {
        $collegeBuckets[$college]['voted_count']++;
    }
}

foreach ($collegeBuckets as $college => $bucket) {
    $rate = $bucket['eligible_count'] > 0
        ? round($bucket['voted_count'] / $bucket['eligible_count'] * 100, 1)
        : 0.0;
    $collegeTurnoutData[] = [
        'college'        => $college,
        'eligible_count' => (int)$bucket['eligible_count'],
        'voted_count'    => (int)$bucket['voted_count'],
        'turnout_rate'   => (float)$rate,
    ];
}

// Department turnout
$departmentTurnoutData = [];
$deptBuckets           = [];

foreach ($scopedNAS as $v) {
    $createdAt = $v['created_at'] ?? null;
    if ($createdAt && $createdAt > $yearEndSelected) continue;

    $college    = $v['department']  ?: 'Unspecified';
    $department = $v['department1'] ?: 'General';
    $key        = $college . '|' . $department;

    if (!isset($deptBuckets[$key])) {
        $deptBuckets[$key] = [
            'college'        => $college,
            'department'     => $department,
            'eligible_count' => 0,
            'voted_count'    => 0,
        ];
    }
    $deptBuckets[$key]['eligible_count']++;
    if (isset($votedSet[$v['user_id']])) {
        $deptBuckets[$key]['voted_count']++;
    }
}

foreach ($deptBuckets as $key => $bucket) {
    $rate = $bucket['eligible_count'] > 0
        ? round($bucket['voted_count'] / $bucket['eligible_count'] * 100, 1)
        : 0.0;
    $departmentTurnoutData[] = [
        'college'        => $bucket['college'],
        'department'     => $bucket['department'],
        'eligible_count' => (int)$bucket['eligible_count'],
        'voted_count'    => (int)$bucket['voted_count'],
        'turnout_rate'   => (float)$rate,
    ];
}

/* ==========================================================
COURSE TURNOUT DATA
========================================================== */

$courseTurnoutData = [];

// First, get all distinct voters who voted in any Non-Academic-Student elections this year
$stmt = $pdo->prepare("
    SELECT DISTINCT v.voter_id
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    WHERE e.election_scope_type = 'Non-Academic-Student'
      AND e.owner_scope_id      = ?
      AND YEAR(e.start_datetime) = ?
");
$stmt->execute([$scopeId, $selectedYear]);
$votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$votedSet = array_flip($votedIds);

// Group course turnout data from scopedNAS
$courseGroups = [];

foreach ($scopedNAS as $v) {
    $createdAt = $v['created_at'] ?? null;
    if ($createdAt && $createdAt > $yearEndSelected) continue;

    $college    = $v['department']  ?: 'Unspecified';
    $department = $v['department1'] ?: 'General';
    $course     = $v['course']      ?? '';
    if ($course === '') continue;
    $key = $college . '|' . $department . '|' . $course;

    if (!isset($courseGroups[$key])) {
        $courseGroups[$key] = [
            'college_name'    => $college,
            'department_name' => $department,
            'course'          => $course,
            'eligible_count'  => 0,
            'voted_count'     => 0,
        ];
    }
    $courseGroups[$key]['eligible_count']++;
    if (isset($votedSet[$v['user_id']])) {
        $courseGroups[$key]['voted_count']++;
    }
}

foreach ($courseGroups as $c) {
    $rate = $c['eligible_count'] > 0
        ? round($c['voted_count'] / $c['eligible_count'] * 100, 1)
        : 0.0;
    $courseTurnoutData[] = [
        'college_name'    => $c['college_name'],
        'department_name' => $c['department_name'],
        'course'          => $c['course'],
        'eligible_count'  => (int)$c['eligible_count'],
        'voted_count'     => (int)$c['voted_count'],
        'turnout_rate'    => (float)$rate,
    ];
}
usort($courseTurnoutData, fn($a, $b) => $b['eligible_count'] <=> $a['eligible_count']);

// =========================
// PER-ELECTION STATS + ABSTAIN
// =========================

$electionTurnoutStats = computePerElectionStatsWithAbstain(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    $scopeId,
    $scopedNAS,
    $selectedYear
);

// Abstain by year
$abstainAllYears = computeAbstainByYear(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    $scopeId,
    $scopedNAS,
    [
        'year_from' => null,
        'year_to'   => null,
    ]
);

// Align abstainByYear with [fromYear..toYear]
$abstainByYear = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($abstainAllYears[$y])) {
        $abstainByYear[$y] = $abstainAllYears[$y];
    } else {
        $abstainByYear[$y] = [
            'year'           => (int)$y,
            'abstain_count'  => 0,
            'total_eligible' => 0,
            'abstain_rate'   => 0.0,
        ];
    }
}

$abstainYears      = array_keys($abstainByYear);
sort($abstainYears);
$abstainCountsYear = [];
$abstainRatesYear  = [];
foreach ($abstainYears as $y) {
    $abstainCountsYear[] = (int)($abstainByYear[$y]['abstain_count']  ?? 0);
    $abstainRatesYear[]  = (float)($abstainByYear[$y]['abstain_rate'] ?? 0.0);
}

// =========================
// MAPPINGS & HEADER
// =========================

$departmentAbbrevMap = [
    'College of Agriculture, Food, Environment and Natural Resources' => 'CAFENR',
    'College of Engineering and Information Technology'               => 'CEIT',
    'College of Arts and Sciences'                                   => 'CAS',
    'College of Veterinary Medicine and Biomedical Sciences'         => 'CVMBS',
    'College of Education'                                           => 'CED',
    'College of Economics, Management and Development Studies'       => 'CEMDS',
    'College of Sports, Physical Education and Recreation'           => 'CSPEAR',
    'College of Criminal Justice'                                    => 'CCJ',
    'College of Nursing'                                             => 'CON',
    'College of Tourism and Hospitality Management'                  => 'CTHM',
    'College of Medicine'                                            => 'COM',
    'Graduate School and Open Learning College'                      => 'GS-OLC',
];

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

$deptCourseAbbrevMap = [
    'Department of Information Technology' => 'DIT',
    'Department of Engineering'            => 'DE',
    'Department of Computer Science'       => 'DCS',
    'BS in Information Technology'         => 'BSIT',
    'BS in Computer Science'               => 'BSCS',
    'BS in Engineering'                    => 'BSE',
    'BS in Agriculture'                    => 'BSA',
    'BS in Arts'                           => 'BSA',
    'BS in Education'                      => 'BSED',
    'BS in Nursing'                        => 'BSN',
    'BS in Tourism'                        => 'BST',
    'BS in Criminal Justice'               => 'BSCRIM',
    'BS in Veterinary Medicine'            => 'BSVM',
    'BS in Economics'                      => 'BSECON',
    'BS in Management'                     => 'BSM',
    'BS in Physical Education'             => 'BSPE',
    'BS in Medicine'                       => 'BSMED',
    'BS in Development Studies'            => 'BSDS',
    'BS in Sports Science'                 => 'BSSS',
    'BS in Open Learning'                  => 'BSOL',
    'General'                              => 'GEN',
];

// Page title & scope display
$pageTitle      = "NON-ACADEMIC STUDENT ADMIN DASHBOARD";
$pageSubtitle   = "Non-Academic Student Admin – Uploaded Students in Your Scope";
$collegeFullName = "Non-Academic Student Groups";

$scopeDisplay  = "Non-Academic Students – Organization";
$isImpersonate = function_exists('isSuperAdmin') && isSuperAdmin() && getImpersonatedScopeId() !== null;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - <?= htmlspecialchars($pageTitle) ?></title>
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
      --cvsu-blue: #3B82F6;
      --cvsu-purple: #8B5CF6;
      --cvsu-red: #EF4444;
      --cvsu-orange: #F97316;
      --cvsu-teal: #14B8A6;
      --cvsu-indigo: #6366F1;
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
    .chart-container { position:relative; height:320px; width:100%; }
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
    select:disabled {
      background-color:#f3f4f6;
      color:#9ca3af;
      cursor:not-allowed;
    }
    .label-disabled {
      color:#9ca3af;
    }
    .modal-backdrop {
      background-color: rgba(0,0,0,0.7);
      z-index: 9999;
    }
    .password-strength-bar {
      transition: width 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<!-- Force Password Change Modal -->
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
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268-2.943 9.542-7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
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
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </button>
          </div>
      </form>
  </div>
</div>

<div class="flex min-h-screen">
<?php
  if (function_exists('isSuperAdmin') && isSuperAdmin() && getImpersonatedScopeId() !== null) {
      include 'super_admin_sidebar.php';
  } else {
      include 'sidebar.php';
  }
?>
<?php include 'admin_change_password_modal.php'; ?>

<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
  <div class="flex flex-col">
    <h1 class="text-2xl font-bold text-white">
      <?= htmlspecialchars($pageTitle) ?>
    </h1>
    <div class="flex items-center space-x-3 mt-1">
      <span class="scope-badge"><?= htmlspecialchars($scopeDisplay) ?></span>
      <?php if ($isImpersonate): ?>
        <span class="inline-flex items-center max-w-fit px-3 py-0.5 rounded-full text-xs font-semibold bg-yellow-300 text-gray-900 shadow-sm">
          View As Non-Academic Student Admin
        </span>
      <?php endif; ?>
    </div>
  </div>
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
    <div class="cvsu-card p-6 rounded-xl">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Students</h2>
          <p class="text-2xl md:text-4xl font-bold text-[var(--cvsu-green-dark)]">
            <?= number_format($total_voters) ?>
          </p>
        </div>
        <div class="p-3 rounded-full" style="background-color:rgba(30,111,70,0.1);">
          <i class="fas fa-user-graduate text-2xl" style="color:var(--cvsu-green);"></i>
        </div>
      </div>
    </div>

    <div class="cvsu-card p-6 rounded-xl" style="border-left-color:var(--cvsu-yellow);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-[var(--cvsu-yellow)]">
            <?= $total_elections ?>
          </p>
        </div>
        <div class="p-3 rounded-full" style="background-color:rgba(255,209,102,0.1);">
          <i class="fas fa-vote-yea text-2xl" style="color:var(--cvsu-yellow);"></i>
        </div>
      </div>
    </div>

    <div class="cvsu-card p-6 rounded-xl" style="border-left-color:var(--cvsu-blue);">
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

  <!-- Detailed Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">Non-Academic Student Analytics</h2>
        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
          <div class="flex items-center">
            <label for="detailedBreakdownSelect" class="mr-3 text-sm font-medium text-white">Breakdown by:</label>
            <select id="detailedBreakdownSelect" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <option value="all">All Colleges</option>
              <option value="department">College and Department</option>
              <option value="course">College and Courses</option>
            </select>
          </div>
          <div id="detailedCollegeSelector" class="flex items-center">
            <label id="detailedCollegeLabel" for="detailedCollegeSelect" class="mr-3 text-sm font-medium text-white">Select College:</label>
            <select id="detailedCollegeSelect" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <?php foreach (array_column($votersByCollegeArr, 'college_name') as $college): ?>
                <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="p-6">
      <!-- Small summary cards -->
      <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color:rgba(30,111,70,0.05);border-color:var(--cvsu-green-light);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-[var(--cvsu-green-light)]">
              <i class="fas fa-user-plus text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-[var(--cvsu-green)]">New This Month</p>
              <p class="text-2xl font-bold text-[var(--cvsu-green-dark)]">
                <?= number_format($newVoters) ?>
              </p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color:rgba(59,130,246,0.05);border-color:#3B82F6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-blue-500">
              <i class="fas fa-building-columns text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-blue-600">Colleges</p>
              <p class="text-2xl font-bold text-blue-800">
                <?= number_format($totalColleges) ?>
              </p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color:rgba(139,92,246,0.05);border-color:#8B5CF6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500">
              <i class="fas fa-graduation-cap text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-purple-600">Departments</p>
              <p class="text-2xl font-bold text-purple-800">
                <?= number_format($totalDepartments) ?>
              </p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color:rgba(245,158,11,0.05);border-color:var(--cvsu-yellow);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-[var(--cvsu-yellow)]">
              <i class="fas fa-book text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm font-semibold" style="color:#B45309;">Courses</p>
              <p class="text-2xl font-bold" style="color:#92400E;">
                <?= number_format($totalCourses) ?>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Donut Chart -->
        <div class="p-4 rounded-lg border" style="background-color:rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Voters by College</h3>
          <div class="chart-container">
            <canvas id="donutChart"></canvas>
          </div>
        </div>

        <!-- Bar Chart -->
        <div class="p-4 rounded-lg border" style="background-color:rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Breakdown Details</h3>
          <div class="chart-container">
            <canvas id="barChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Detailed Breakdown Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200" id="detailedBreakdownTable">
            <thead class="bg-gray-50">
              <tr id="detailedTableHeader">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="detailedTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Voter Turnout Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">Voter Turnout Analytics</h2>
      </div>
    </div>

    <div class="border-t p-6">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Voter Turnout Analytics</h3>
        <div class="flex items-center">
          <label for="turnoutYearSelector" class="mr-2 text-gray-700 font-medium">Select Year:</label>
          <select id="turnoutYearSelector" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
            <?php foreach ($turnoutYears as $year): ?>
              <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color:rgba(99,102,241,0.05);border-color:var(--cvsu-indigo);">
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

        <div class="p-4 rounded-lg border" style="background-color:rgba(139,92,246,0.05);border-color:var(--cvsu-purple);">
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

        <div class="p-4 rounded-lg border" style="background-color:rgba(16,185,129,0.05);border-color:var(--cvsu-teal);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-teal-500">
              <i class="fas fa-chart-line text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-teal-600">Growth Rate</p>
              <p class="text-2xl font-bold text-teal-800">
                <?php
                $ct = $currentYearTurnout['turnout_rate'] ?? 0;
                $pt = $previousYearTurnout['turnout_rate'] ?? 0;
                echo $pt > 0 ? ((($ct-$pt)/$pt>0?'+':'').round((($ct-$pt)/$pt)*100,1).'%') : '0%';
                ?>
              </p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color:rgba(59,130,246,0.05);border-color:var(--cvsu-blue);">
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

      <div class="p-4 rounded-lg mb-8" style="background-color:rgba(30,111,70,0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Turnout Rate Trend</h3>
        <div class="chart-container" style="height:400px;">
          <canvas id="turnoutTrendChart"></canvas>
        </div>
      </div>

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
                  <span class="<?= $trendColor ?>"><?= ($data['growth_rate'] ?? 0) > 0 ? '+' : '' ?><?= $data['growth_rate'] ?? 0 ?>%</span>
                  <i class="fas <?= $trendIcon ?> <?= $trendColor ?> ml-1"></i>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

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

      <!-- Elections vs Turnout Rate -->
      <div class="p-4 rounded-lg mt-6" style="background-color:rgba(30,111,70,0.05);">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Elections vs Turnout / Students / Abstained</h3>
          <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-6">
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
                <option value="college">College</option>
                <option value="department">Department</option>
                <option value="course">Course</option>
              </select>
            </div>
            <div id="turnoutCollegeSelector" class="flex items-center" style="display:none;">
              <label for="turnoutCollegeSelect" class="mr-3 text-sm font-medium text-gray-700">Select College:</label>
              <select id="turnoutCollegeSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm"></select>
            </div>
          </div>
        </div>
        <div class="chart-container" style="height:400px;">
          <canvas id="electionsVsTurnoutChart"></canvas>
        </div>
        <div id="turnoutBreakdownTable" class="mt-6 overflow-x-auto"></div>
      </div>
    </div>
  </div>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // ===== Force password change =====
  const forcePasswordChange = <?= $force_password_flag ?>;
  const modal = document.getElementById('forcePasswordChangeModal');
  const passwordInput = document.getElementById('newPassword');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const togglePassword = document.getElementById('togglePassword');
  const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
  const forcePasswordChangeForm = document.getElementById('forcePasswordChangeForm');
  const passwordError = document.getElementById('passwordError');
  const passwordSuccess = document.getElementById('passwordSuccess');
  const passwordLoading = document.getElementById('passwordLoading');
  const passwordErrorText = document.getElementById('passwordErrorText');
  const submitBtn = document.getElementById('submitBtn');
  const submitBtnText = document.getElementById('submitBtnText');
  const submitLoader = document.getElementById('submitLoader');

  if (forcePasswordChange === 1 && modal) {
    modal.classList.remove('hidden');
    document.body.style.pointerEvents = 'none';
    modal.style.pointerEvents = 'auto';
  }

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

      fetch('update_admin_password.php', {
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

  // Turnout year selector
  const turnoutYearSelector = document.getElementById('turnoutYearSelector');
  if (turnoutYearSelector) {
    turnoutYearSelector.addEventListener('change', function() {
      const url = new URL(window.location.href);
      url.searchParams.set('year', this.value);
      window.location.href = url.toString();
    });
  }

  let turnoutTrendChartInstance = null;
  let electionsVsTurnoutChartInstance = null;

  // Data from PHP
  const turnoutYears = <?= json_encode(array_keys($turnoutRangeData)) ?>;
  const turnoutRates = <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>;

  const courseTurnoutData     = <?= json_encode($courseTurnoutData) ?>;
  const collegeDepartmentData = <?= json_encode($collegeDepartmentBar) ?>;
  const collegeCourseData     = <?= json_encode($collegeCourseBar) ?>;

  const abstainYearsJS      = <?= json_encode($abstainYears) ?>;
  const abstainCountsYearJS = <?= json_encode($abstainCountsYear) ?>;
  const abstainRatesYearJS  = <?= json_encode($abstainRatesYear) ?>;
  const electionTurnoutStats = <?= json_encode($electionTurnoutStats) ?>;
  const selectedYearJS      = <?= (int)$selectedYear ?>;

  const departmentAbbrevMap = <?= json_encode($departmentAbbrevMap) ?>;
  const collegeFullNameMap = <?= json_encode($collegeFullNameMap) ?>;
  const deptCourseAbbrevMap = <?= json_encode($deptCourseAbbrevMap) ?>;

  function getFullCollegeNameJS(code) {
    return collegeFullNameMap[code] || code;
  }

  function getDepartmentAbbrevJS(name) {
    if (departmentAbbrevMap[name]) return departmentAbbrevMap[name];
    if (deptCourseAbbrevMap[name]) return deptCourseAbbrevMap[name];

    const excludeWords = ['and','of','the','in','for','at','by'];

    if (name.startsWith('College of ')) {
      for (let [fullName, abbrev] of Object.entries(departmentAbbrevMap)) {
        if (name === fullName) return abbrev;
      }
      const rest  = name.substring(13).trim();
      const words = rest.split(' ');
      return words.filter(w => !excludeWords.includes(w.toLowerCase()))
                  .map(w => w[0]).join('').toUpperCase();
    }

    if (name.startsWith('Department of ')) {
      const rest  = name.substring(13).trim();
      const words = rest.split(' ');
      return 'D' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                        .map(w => w[0]).join('').toUpperCase();
    }

    if (name.startsWith('BS in ')) {
      const rest  = name.substring(6).trim();
      const words = rest.split(' ');
      return 'BS' + words.filter(w => !excludeWords.includes(w.toLowerCase()))
                         .map(w => w[0]).join('').toUpperCase();
    }

    if (name === 'General') return 'GEN';
    return name;
  }

  // Turnout trend chart
  const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
  if (turnoutTrendCtx && !turnoutTrendChartInstance) {
    turnoutTrendChartInstance = new Chart(turnoutTrendCtx, {
      type: 'line',
      data: {
        labels: turnoutYears,
        datasets: [{
          label: 'Turnout Rate (%)',
          data: turnoutRates,
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

  // Elections vs Turnout / Students vs Turnout / Abstained
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
      college: <?= json_encode($collegeTurnoutData) ?>,
      department: <?= json_encode($departmentTurnoutData) ?>,
      course: <?= json_encode($courseTurnoutData) ?>
    },
    abstained: {
      year: {
        labels: abstainYearsJS,
        abstainCounts: abstainCountsYearJS,
        abstainRates:  abstainRatesYearJS
      }
    }
  };

  let currentDataSeries = 'elections';
  let currentBreakdown  = 'year';

  const dataSeriesSelect = document.getElementById('dataSeriesSelect');
  const breakdownSelect  = document.getElementById('breakdownSelect');
  const turnoutCollegeSelector = document.getElementById('turnoutCollegeSelector');
  const turnoutCollegeSelect   = document.getElementById('turnoutCollegeSelect');

  function resetBreakdownOptions() {
    breakdownSelect.innerHTML = '';
    let opts = [];

    if (currentDataSeries === 'elections') {
      opts = [
        { value: 'year',     label: 'Year' },
        { value: 'election', label: 'Election (Current Year)' }
      ];
    } else if (currentDataSeries === 'voters') {
      opts = [
        { value: 'year',      label: 'Year' },
        { value: 'election',  label: 'Election (Current Year)' },
        { value: 'college',   label: 'College' },
        { value: 'department',label: 'Department' },
        { value: 'course',    label: 'Course' }
      ];
    } else {
      opts = [
        { value: 'year',     label: 'Year' },
        { value: 'election', label: 'Election (Current Year)' }
      ];
    }

    opts.forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.label;
      breakdownSelect.appendChild(opt);
    });

    const valid = opts.map(o => o.value);
    if (!valid.includes(currentBreakdown)) currentBreakdown = opts[0].value;
    breakdownSelect.value = currentBreakdown;

    updateCollegeSelectorVisibility();
  }

  function updateCollegeSelectorOptions(breakdownType) {
    if (!turnoutCollegeSelect) return;

    // remember current selection (if any)
    const previous = turnoutCollegeSelect.value;

    turnoutCollegeSelect.innerHTML = '';

    const colleges = <?= json_encode(array_column($votersByCollegeArr, 'college_name')) ?>;

    if (breakdownType === 'college') {
      const allOpt = document.createElement('option');
      allOpt.value = 'all';
      allOpt.textContent = 'All Colleges';
      turnoutCollegeSelect.appendChild(allOpt);
    }

    colleges.forEach(college => {
      const opt = document.createElement('option');
      opt.value = college;
      opt.textContent = college;
      turnoutCollegeSelect.appendChild(opt);
    });

    // try to restore previous selection if it still exists
    if (previous) {
      const hasPrev = Array.from(turnoutCollegeSelect.options).some(o => o.value === previous);
      if (hasPrev) {
        turnoutCollegeSelect.value = previous;
      }
    }
  }


  function updateCollegeSelectorVisibility() {
    if (!turnoutCollegeSelector || !breakdownSelect) return;
    const b = breakdownSelect.value;
    if (['college','department','course'].includes(b)) {
      turnoutCollegeSelector.style.display = 'flex';
      updateCollegeSelectorOptions(b);
    } else {
      turnoutCollegeSelector.style.display = 'none';
    }
  }

  function handleDataSeriesChange() {
    currentDataSeries = dataSeriesSelect.value;
    currentBreakdown  = 'year';
    renderElectionsVsTurnout();
  }

  function handleBreakdownChange() {
    currentBreakdown = breakdownSelect.value;
    resetBreakdownOptions();         
    renderElectionsVsTurnout();
  }

  function renderElectionsVsTurnout() {
    const canvas = document.getElementById('electionsVsTurnoutChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    if (electionsVsTurnoutChartInstance) {
      electionsVsTurnoutChartInstance.destroy();
    }

    resetBreakdownOptions();

    let data = null;
    let leftLabel = '';
    let titleText = '';

    const fallbackCollege = <?= json_encode($votersByCollegeArr[0]['college_name'] ?? null) ?>;
    const selectedCollege = turnoutCollegeSelect && turnoutCollegeSelect.value
      ? turnoutCollegeSelect.value
      : (fallbackCollege || 'all');

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
        leftLabel = 'Number of Elections';
        titleText = 'Elections vs Turnout Rate (By Year)';
      } else {
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
        leftLabel = 'Students Participated';
        titleText = `Elections vs Turnout Rate (By Election, ${selectedYearJS})`;
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
        leftLabel = 'Number of Students';
        titleText = 'Eligible Students vs Turnout Rate (By Year)';
      } else if (currentBreakdown === 'election') {
        const labels   = electionTurnoutStats.map(e => e.title);
        const eligible = electionTurnoutStats.map(e => e.total_eligible);
        const rates    = electionTurnoutStats.map(e => e.turnout_rate);
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
              data: rates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        leftLabel = 'Number of Students';
        titleText = `Eligible Students vs Turnout Rate (By Election, ${selectedYearJS})`;
      } else if (currentBreakdown === 'college') {
        let labels = [];
        let eligible = [];
        let tr = [];

        if (selectedCollege === 'all') {
          const colleges = chartData.voters.college;
          labels   = colleges.map(r => getDepartmentAbbrevJS(r.college));
          eligible = colleges.map(r => r.eligible_count);
          tr       = colleges.map(r => r.turnout_rate);
          titleText = 'Eligible Students vs Turnout Rate (All Colleges)';
        } else {
          const filtered = chartData.voters.college.filter(r => r.college === selectedCollege);
          labels   = filtered.map(r => getDepartmentAbbrevJS(r.college));
          eligible = filtered.map(r => r.eligible_count);
          tr       = filtered.map(r => r.turnout_rate);
          titleText = `Eligible Students vs Turnout Rate (College: ${selectedCollege})`;
        }

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
        leftLabel = 'Number of Students';
      } else if (currentBreakdown === 'department') {
        const filtered = chartData.voters.department.filter(r => r.college === selectedCollege);
        const labels   = filtered.map(r => getDepartmentAbbrevJS(r.department));
        const eligible = filtered.map(r => r.eligible_count);
        const tr       = filtered.map(r => r.turnout_rate);
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
        leftLabel = 'Number of Students';
        titleText = `Eligible Students vs Turnout Rate (Departments in ${selectedCollege})`;
      } else if (currentBreakdown === 'course') {
        const filtered = chartData.voters.course.filter(r => r.college_name === selectedCollege);
        const labels   = filtered.map(r => getDepartmentAbbrevJS(r.course));
        const eligible = filtered.map(r => r.eligible_count);
        const tr       = filtered.map(r => r.turnout_rate);
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
        leftLabel = 'Number of Students';
        titleText = `Eligible Students vs Turnout Rate (Courses in ${selectedCollege})`;
      }
    } else {
      // Abstained
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.abstained.year.labels,
          datasets: [
            {
              label: 'Abstained Students',
              data: chartData.abstained.year.abstainCounts,
              backgroundColor: '#EF4444',
              borderColor: '#B91C1C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Abstain Rate (%)',
              data: chartData.abstained.year.abstainRates,
              backgroundColor: '#F97316',
              borderColor: '#C2410C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        leftLabel = 'Abstained Students';
        titleText = 'Abstained Students (By Year)';
      } else {
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
        leftLabel = 'Abstained Students';
        titleText = `Abstained Students (By Election, ${selectedYearJS})`;
      }
    }

    electionsVsTurnoutChartInstance = new Chart(ctx, {
      type: 'bar',
      data,
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
              label: ctx => {
                const label = ctx.dataset.label || '';
                if (label.includes('Rate')) {
                  return `${label}: ${ctx.raw}%`;
                }
                return `${label}: ${ctx.raw.toLocaleString()}`;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            position: 'left',
            title: { display: true, text: leftLabel, font: { size: 14, weight: 'bold' } }
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
      }
    });

    buildTurnoutBreakdownTable();
  }

  function buildTurnoutBreakdownTable() {
    const container = document.getElementById('turnoutBreakdownTable');
    container.innerHTML = '';
    let headers = [];
    let rows    = [];

    const fallbackCollege = <?= json_encode($votersByCollegeArr[0]['college_name'] ?? null) ?>;
    const selectedCollege = turnoutCollegeSelect && turnoutCollegeSelect.value
      ? turnoutCollegeSelect.value
      : (fallbackCollege || 'all');

    if (currentDataSeries === 'elections') {
      if (currentBreakdown === 'year') {
        headers = ['Year','Number of Elections','Turnout Rate'];
        rows = chartData.elections.year.labels.map((label,i)=>[
          label,
          chartData.elections.year.electionCounts[i].toLocaleString(),
          chartData.elections.year.turnoutRates[i] + '%'
        ]);
      } else {
        headers = ['Election','Students Participated','Eligible Students','Turnout Rate'];
        rows = electionTurnoutStats.map(e => [
          e.title,
          (e.total_voted    || 0).toLocaleString(),
          (e.total_eligible || 0).toLocaleString(),
          (e.turnout_rate   || 0) + '%'
        ]);
      }
    } else if (currentDataSeries === 'voters') {
      if (currentBreakdown === 'year') {
        headers = ['Year','Eligible Students','Turnout Rate'];
        rows = chartData.voters.year.labels.map((label,i)=>[
          label,
          chartData.voters.year.eligibleCounts[i].toLocaleString(),
          chartData.voters.year.turnoutRates[i] + '%'
        ]);
      } else if (currentBreakdown === 'college') {
        headers = ['College','Eligible Students','Voted','Turnout Rate'];
        let filtered = chartData.voters.college;
        if (selectedCollege !== 'all') {
          filtered = filtered.filter(r => r.college === selectedCollege);
        }
        rows = filtered.map(row => [
          row.college,
          row.eligible_count.toLocaleString(),
          row.voted_count.toLocaleString(),
          row.turnout_rate + '%'
        ]);
      } else if (currentBreakdown === 'department') {
        headers = ['Department','Eligible Students','Voted','Turnout Rate'];
        const filtered = chartData.voters.department.filter(r => r.college === selectedCollege);
        rows = filtered.map(row => [
          row.department,
          row.eligible_count.toLocaleString(),
          row.voted_count.toLocaleString(),
          row.turnout_rate + '%'
        ]);
      } else if (currentBreakdown === 'course') {
        headers = ['Course','Eligible Students','Voted','Turnout Rate'];
        const filtered = chartData.voters.course.filter(r => r.college_name === selectedCollege);
        rows = filtered.map(row => [
          row.course,
          row.eligible_count.toLocaleString(),
          row.voted_count.toLocaleString(),
          row.turnout_rate + '%'
        ]);
      } else {
        headers = ['Election','Eligible Students','Students Participated','Turnout Rate'];
        rows = electionTurnoutStats.map(e => [
          e.title,
          (e.total_eligible || 0).toLocaleString(),
          (e.total_voted    || 0).toLocaleString(),
          (e.turnout_rate   || 0) + '%'
        ]);
      }
    } else {
      // abstained
      if (currentBreakdown === 'year') {
        headers = ['Year','Abstained Students','Abstain Rate'];
        rows = chartData.abstained.year.labels.map((label,i)=>[
          label,
          (chartData.abstained.year.abstainCounts[i] || 0).toLocaleString(),
          (chartData.abstained.year.abstainRates[i]  || 0) + '%'
        ]);
      } else {
        headers = ['Election','Abstained Students','Abstain Rate','Eligible Students'];
        const withAbstain = electionTurnoutStats.filter(e => (e.abstain_count || 0) > 0);
        if (withAbstain.length === 0) {
          const msg = document.createElement('div');
          msg.className = 'text-center text-gray-600 text-sm py-4';
          msg.textContent = `No abstained students found for ${selectedYearJS}.`;
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

  if (dataSeriesSelect) dataSeriesSelect.addEventListener('change', handleDataSeriesChange);
  if (breakdownSelect)  breakdownSelect.addEventListener('change', handleBreakdownChange);
  if (turnoutCollegeSelect) turnoutCollegeSelect.addEventListener('change', renderElectionsVsTurnout);

  handleDataSeriesChange();

  // === Detailed Analytics (donut + bar + table) ===
  const donutData = {
    labels: <?= json_encode(array_column($votersByCollegeArr, 'college_name')) ?>,
    counts: <?= json_encode(array_column($votersByCollegeArr, 'count')) ?>
  };

  const barData = {
    department: <?= json_encode($collegeDepartmentBar) ?>,
    course:     <?= json_encode($collegeCourseBar) ?>
  };

  const donutCtx = document.getElementById('donutChart');
  if (donutCtx) {
    new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: donutData.labels.map(label => getDepartmentAbbrevJS(label)),
        datasets: [{
          data: donutData.counts,
          backgroundColor: [
            '#1E6F46','#37A66B','#FFD166','#154734','#2D5F3F',
            '#4A7C59','#5A8F6A','#6A9F7A','#7AAFAA','#8ABFBA'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '50%',
        plugins: {
          legend: {
            position: 'right',
            labels: { font: { size: 12 }, padding: 15 }
          },
          tooltip: {
            callbacks: {
              label: ctx => {
                const label = ctx.label || '';
                const val   = ctx.raw || 0;
                const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                const pct   = total > 0 ? Math.round((val/total)*100) : 0;
                return `${label}: ${val} (${pct}%)`;
              }
            }
          }
        }
      }
    });
  }

  const barCtx = document.getElementById('barChart');
  if (barCtx) {
    let barChart;

    function getBarChartDataDetailed(breakdownType, college) {
      let data = [];
      let labels = [];

      if (breakdownType === 'all') {
        data = donutData.labels.map((label, i) => ({
          college_name: label,
          count: donutData.counts[i]
        }));
        labels = data.map(d => getDepartmentAbbrevJS(d.college_name));
      } else {
        data = barData[breakdownType].filter(item => item.college_name === college);
        if (breakdownType === 'department') {
          labels = data.map(d => getDepartmentAbbrevJS(d.department_name));
        } else {
          labels = data.map(d => getDepartmentAbbrevJS(d.course));
        }
      }

      const counts = data.map(d => d.count);
      return { labels, counts };
    }

    const detailedBreakdownSelect = document.getElementById('detailedBreakdownSelect');
    const detailedCollegeSelect   = document.getElementById('detailedCollegeSelect');
    const detailedCollegeLabel    = document.getElementById('detailedCollegeLabel');

    const initCollege   = detailedCollegeSelect.value;
    const initBreakdown = 'all';
    const initData      = getBarChartDataDetailed(initBreakdown, initCollege);

    barChart = new Chart(barCtx, {
      type: 'bar',
      data: {
        labels: initData.labels,
        datasets: [{
          label: 'Number of Voters',
          data: initData.counts,
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
          title: {
            display: true,
            text: 'All Colleges',
            font: { size: 16, weight: 'bold' }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: { display: true, text: 'Number of Voters' }
          },
          x: {
            title: { display: true, text: 'College' },
            ticks: { maxRotation: 0, autoSkip: false }
          }
        }
      }
    });

    function updateDetailedAnalytics() {
      const breakdownType = detailedBreakdownSelect.value;
      const college       = detailedCollegeSelect.value;
      const d             = getBarChartDataDetailed(breakdownType, college);

      barChart.data.labels = d.labels;
      barChart.data.datasets[0].data = d.counts;

      barChart.options.plugins.title.text =
        breakdownType === 'all' ? 'All Colleges' :
        (breakdownType === 'department' ? 'Departments' : 'Courses');

      barChart.options.scales.x.title.text =
        breakdownType === 'all' ? 'College' :
        (breakdownType === 'department' ? 'Department' : 'Course');

      barChart.update();
      updateDetailedTable(breakdownType, college);
    }

    function handleDetailedBreakdownChange() {
      const value = detailedBreakdownSelect.value;
      if (value === 'department' || value === 'course') {
        detailedCollegeSelect.disabled = false;
        detailedCollegeLabel.classList.remove('label-disabled');
      } else {
        detailedCollegeSelect.disabled = true;
        detailedCollegeLabel.classList.add('label-disabled');
      }
      updateDetailedAnalytics();
    }

    function updateDetailedTable(breakdownType, college) {
      const tableHeader = document.getElementById('detailedTableHeader');
      const tableBody   = document.getElementById('detailedTableBody');
      tableHeader.innerHTML = '';
      tableBody.innerHTML   = '';

      if (breakdownType === 'all') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        const allData = <?= json_encode($votersByCollegeArr) ?>;
        allData.forEach(item => {
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-gray-50';
          tr.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(tr);
        });
      } else if (breakdownType === 'department') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        const deptData = barData.department.filter(item => item.college_name === college);
        deptData.forEach(item => {
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-gray-50';
          tr.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.department_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(tr);
        });
      } else if (breakdownType === 'course') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        const courseData = barData.course.filter(item => item.college_name === college);
        courseData.forEach(item => {
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-gray-50';
          tr.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.course}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(tr);
        });
      }
    }

    detailedBreakdownSelect.addEventListener('change', handleDetailedBreakdownChange);
    detailedCollegeSelect.addEventListener('change', updateDetailedAnalytics);

    handleDetailedBreakdownChange();
  }

  // Year range selectors
  const fromYearSelect = document.getElementById('fromYear');
  const toYearSelect   = document.getElementById('toYear');

  function submitYearRange() {
    if (!fromYearSelect || !toYearSelect) return;
    const from = fromYearSelect.value;
    const to   = toYearSelect.value;
    const url  = new URL(window.location.href);
    if (from) url.searchParams.set('from_year', from); else url.searchParams.delete('from_year');
    if (to)   url.searchParams.set('to_year', to);     else url.searchParams.delete('to_year');

    const yearSelect = document.getElementById('turnoutYearSelector');
    if (yearSelect && yearSelect.value) {
      url.searchParams.set('year', yearSelect.value);
    }

    window.location.href = url.toString();
  }

  if (fromYearSelect) fromYearSelect.addEventListener('change', submitYearRange);
  if (toYearSelect)   toYearSelect.addEventListener('change', submitYearRange);
});

// global close for modal
function closePasswordModal() {
  const forcePasswordChange = <?= $force_password_flag ?>;
  if (forcePasswordChange === 1) return;
  const modal = document.getElementById('forcePasswordChangeModal');
  if (modal) modal.classList.add('hidden');
  document.body.style.pointerEvents = 'auto';
}
</script>
</body>
</html>
