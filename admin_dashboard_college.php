<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- Auth check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Scope info from session — this dashboard is for Academic-Student (college student admins)
 $scope_category   = $_SESSION['scope_category']   ?? '';
 $assigned_scope   = $_SESSION['assigned_scope']   ?? ''; // college code (e.g., CEIT)
 $assigned_scope_1 = $_SESSION['assigned_scope_1'] ?? ''; // e.g., "Multiple: BSIT, BSCS"
 $scope_details    = $_SESSION['scope_details']    ?? [];
 $admin_status     = $_SESSION['admin_status']     ?? 'inactive';

// Validate: Academic-Student admins only (faculty admins use admin_dashboard_faculty.php)
if ($scope_category !== 'Academic-Student') {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

if ($admin_status !== 'active') {
    header('Location: login.php?error=Your admin account is inactive.');
    exit();
}

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

// --- COURSE MAP: canonical code => full name ---
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

// College full names
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

// --- Normalization helpers ----------------------------------------

function normalize_course_code(string $raw): string {
    $s = strtoupper(trim($raw));
    if ($s === '') return 'UNSPECIFIED';

    $s = preg_replace('/[.\-_,]/', ' ', $s);    // remove ., -, _, ,
    $s = preg_replace('/\s+/', ' ', $s);

    $replacements = [
        'BACHELOR OF SCIENCE IN ' => 'BS ',
        'BACHELOR OF SCIENCE '    => 'BS ',
        'BACHELOR OF '            => 'B ',
        'INFORMATION TECHNOLOGY'  => 'IT',
        'COMPUTER SCIENCE'        => 'CS',
        'COMPUTER ENGINEERING'    => 'CPE',
        'ELECTRONICS ENGINEERING' => 'ECE',
        'CIVIL ENGINEERING'       => 'CE',
        'MECHANICAL ENGINEERING'  => 'ME',
        'ELECTRICAL ENGINEERING'  => 'EE',
        'INDUSTRIAL ENGINEERING'  => 'IE',
        'AGRICULTURE'             => 'AGRI',
        'AGRIBUSINESS'            => 'AB',
        'ENVIRONMENTAL SCIENCE'   => 'ES',
        'FOOD TECHNOLOGY'         => 'FT',
        'FORESTRY'                => 'FOR',
        'AGRICULTURAL AND BIOSYSTEMS ENGINEERING' => 'ABE',
        'AGRICULTURAL ENTREPRENEURSHIP'           => 'AE',
        'LAND USE DESIGN AND MANAGEMENT'          => 'LDM',
        'BIOLOGY'                 => 'BIO',
        'CHEMISTRY'               => 'CHEM',
        'MATHEMATICS'             => 'MATH',
        'PHYSICS'                 => 'PHYSICS',
        'PSYCHOLOGY'              => 'PSYCH',
        'ENGLISH LANGUAGE STUDIES'=> 'ELS',
        'COMMUNICATION'           => 'COMM',
        'STATISTICS'              => 'STAT',
        'CRIMINOLOGY'             => 'CRIM',
        'NURSING'                 => 'N',
        'HOSPITALITY MANAGEMENT'  => 'HM',
        'TOURISM MANAGEMENT'      => 'TM',
        'LIBRARY AND INFORMATION SCIENCE' => 'LIS',
        'LIBRARY & INFORMATION SCIENCE'   => 'LIS',
        'EXERCISE AND SPORTS SCIENCES'    => 'ESS',
        'OFFICE ADMINISTRATION'   => 'OA',
        'ENTREPRENEURSHIP'        => 'ENT',
        'ECONOMICS'               => 'ECO',
        'ACCOUNTANCY'             => 'ACC',
        'SECONDARY EDUCATION'     => 'SED',
        'ELEMENTARY EDUCATION'    => 'EED',
        'PHYSICAL EDUCATION'      => 'PE',
        'TECHNOLOGY AND LIVELIHOOD EDUCATION' => 'TLE',
        'PRE VETERINARY'          => 'PV',
        'VETERINARY MEDICINE'     => 'DVM',
    ];
    foreach ($replacements as $from => $to) {
        $s = str_replace($from, $to, $s);
    }

    $s       = preg_replace('/\s+/', ' ', trim($s));
    $noSpace = str_replace(' ', '', $s);

    $patterns = [
        '/^BSIT$/'      => 'BSIT',
        '/^BSCS$/'      => 'BSCS',
        '/^BSCPE$/'     => 'BSCpE',
        '/^BSECE$/'     => 'BSECE',
        '/^BSCE$/'      => 'BSCE',
        '/^BSME$/'      => 'BSME',
        '/^BSEE$/'      => 'BSEE',
        '/^BSIE$/'      => 'BSIE',
        '/^BSAGRI$/'    => 'BSAgri',
        '/^BSAB$/'      => 'BSAB',
        '/^BSES$/'      => 'BSES',
        '/^BSFT$/'      => 'BSFT',
        '/^BSFOR$/'     => 'BSFor',
        '/^BSABE$/'     => 'BSABE',
        '/^BAE$/'       => 'BAE',
        '/^BSLDM$/'     => 'BSLDM',
        '/^BSBIO$/'     => 'BSBio',
        '/^BSCHEM$/'    => 'BSChem',
        '/^BSMATH$/'    => 'BSMath',
        '/^BSPHYSICS$/' => 'BSPhysics',
        '/^BSPSYCH$/'   => 'BSPsych',
        '/^BAELS$/'     => 'BAELS',
        '/^BACOMM$/'    => 'BAComm',
        '/^BSSTAT$/'    => 'BSStat',
        '/^DVM$/'       => 'DVM',
        '/^BSPV$/'      => 'BSPV',
        '/^BEED$/'      => 'BEEd',
        '/^BSED$/'      => 'BSEd',
        '/^BPE$/'       => 'BPE',
        '/^BTLE$/'      => 'BTLE',
        '/^BSBA$/'      => 'BSBA',
        '/^BSACC$/'     => 'BSAcc',
        '/^BSECO$/'     => 'BSEco',
        '/^BSENT$/'     => 'BSEnt',
        '/^BSOA$/'      => 'BSOA',
        '/^BSESS$/'     => 'BSESS',
        '/^BSCRIM$/'    => 'BSCrim',
        '/^BSN$/'       => 'BSN',
        '/^BSHM$/'      => 'BSHM',
        '/^BSTM$/'      => 'BSTM',
        '/^BLIS$/'      => 'BLIS',
        '/^PHD$/'       => 'PhD',
        '/^MS$/'        => 'MS',
        '/^MA$/'        => 'MA',
    ];
    foreach ($patterns as $regex => $code) {
        if (preg_match($regex, $noSpace)) {
            return $code;
        }
    }

    return $noSpace !== '' ? $noSpace : 'UNSPECIFIED';
}

function getCanonicalCourseDisplay(string $raw, array $courseMap): string {
    $code = normalize_course_code($raw);
    return $courseMap[$code] ?? $code;
}

// --- Scope: allowed courses only (Academic-Student) --------------------------

 $allowedCourseCodes = [];

// Academic-Student: COURSE scope for this college
if (!empty($assigned_scope_1) && $assigned_scope_1 !== 'All') {
    // Example: "Multiple: BSIT, BSCS"
    $rawCourseScope = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $assigned_scope_1);
    $allowedCourseCodes = array_filter(array_map('trim', explode(',', $rawCourseScope)));
} elseif (!empty($scope_details['courses']) && is_array($scope_details['courses'])) {
    // If scope_details has courses from admin_scopes, prefer that
    $allowedCourseCodes = $scope_details['courses']; // may be codes or full names
} else {
    // Empty = all courses in this college
    $allowedCourseCodes = [];
}

/**
 * Fetch ALL college students, then apply scope filters in PHP.
 */
function fetchScopedStudents(
    PDO $pdo,
    string $assigned_scope,
    array $allowedCourseCodes
): array {
    $sql = "SELECT user_id, department, department1, course, status, created_at
            FROM users
            WHERE role = 'voter'
              AND position = 'student'";

    $conditions = [];
    $params     = [];

    // College scope (department column = college code)
    if (!empty($assigned_scope)) {
        $conditions[] = "department = ?";
        $params[]     = $assigned_scope;
    }

    if ($conditions) {
        $sql .= ' AND ' . implode(' AND ', $conditions);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Academic-Student: filter by allowed course list, if any
    if (!empty($allowedCourseCodes)) {
        // Normalize allowed list to course codes
        $normalizedAllowedSet = [];
        foreach ($allowedCourseCodes as $c) {
            $normalizedAllowedSet[] = strtoupper(normalize_course_code($c));
        }
        $normalizedAllowedSet = array_unique($normalizedAllowedSet);

        $rows = array_filter($rows, function ($row) use ($normalizedAllowedSet) {
            $codeNorm = strtoupper(normalize_course_code($row['course'] ?? ''));
            return in_array($codeNorm, $normalizedAllowedSet, true);
        });
    }

    return array_values($rows);
}

// --- Master scoped dataset: ALL stats will be based on this --------

 $scopedStudents = fetchScopedStudents(
    $pdo,
    $assigned_scope,
    $allowedCourseCodes
);

 $userId       = $_SESSION['user_id'];
 $total_voters = count($scopedStudents);

// --- Election counts -----------------------------------------------

 $stmt = $pdo->prepare("SELECT COUNT(*) AS total_elections
                       FROM elections
                       WHERE assigned_admin_id = ?");
 $stmt->execute([$userId]);
 $total_elections = (int) $stmt->fetch()['total_elections'];

 $stmt = $pdo->prepare("SELECT COUNT(*) AS ongoing_elections
                       FROM elections
                       WHERE status = 'ongoing'
                         AND assigned_admin_id = ?");
 $stmt->execute([$userId]);
 $ongoing_elections = (int) $stmt->fetch()['ongoing_elections'];

// --- Date/time ranges ----------------------------------------------

 $currentYear       = (int) date('Y');
 $selectedYear      = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int) $_GET['year'] : $currentYear;
 $currentMonthStart = date('Y-m-01');
 $currentMonthEnd   = date('Y-m-t');
 $lastMonthStart    = date('Y-m-01', strtotime('-1 month'));
 $lastMonthEnd      = date('Y-m-t', strtotime('-1 month'));

// --- New / last month voters (scoped) ------------------------------

 $newVoters       = 0;
 $lastMonthVoters = 0;
foreach ($scopedStudents as $s) {
    $createdDate = substr($s['created_at'], 0, 10);
    if ($createdDate >= $currentMonthStart && $createdDate <= $currentMonthEnd) {
        $newVoters++;
    }
    if ($createdDate >= $lastMonthStart && $createdDate <= $lastMonthEnd) {
        $lastMonthVoters++;
    }
}

// --- Group by department -------------------------------------------

 $votersByDepartment = [];
foreach ($scopedStudents as $s) {
    $deptName = $s['department1'] ?: $s['department'] ?: 'Unspecified';
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

// --- Group by course -----------------------------------------------

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

// --- Status distribution -------------------------------------------

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

// --- Growth rate ---------------------------------------------------

 $growthRate = ($lastMonthVoters > 0)
    ? round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1)
    : 0.0;

// --- Turnout analytics (per year) ---------------------------------

 $stmt = $pdo->prepare("SELECT DISTINCT YEAR(start_datetime) AS year
                       FROM elections
                       WHERE assigned_admin_id = ?
                       ORDER BY year ASC");
 $stmt->execute([$userId]);
 $turnoutYears = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

 $turnoutDataByYear = [];

// Build full per-year metrics for all years that have elections
foreach ($turnoutYears as $year) {
    // total voted (distinct students who voted in this admin's elections this year)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) AS total_voted
                           FROM votes v
                           JOIN elections e ON v.election_id = e.election_id
                           WHERE e.assigned_admin_id = ?
                             AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$userId, $year]);
    $totalVoted = (int) ($stmt->fetch()['total_voted'] ?? 0);

    // total eligible in scopedStudents as of year end
    $yearEnd       = sprintf('%04d-12-31 23:59:59', $year);
    $totalEligible = 0;
    foreach ($scopedStudents as $s) {
        if ($s['created_at'] <= $yearEnd) {
            $totalEligible++;
        }
    }

    $turnoutRate = ($totalEligible > 0)
        ? round(($totalVoted / $totalEligible) * 100, 1)
        : 0.0;

    // election count for that year
    $stmt = $pdo->prepare("SELECT COUNT(*) AS election_count
                           FROM elections
                           WHERE assigned_admin_id = ?
                             AND YEAR(start_datetime) = ?");
    $stmt->execute([$userId, $year]);
    $electionCount = (int) ($stmt->fetch()['election_count'] ?? 0);

    $turnoutDataByYear[$year] = [
        'year'           => $year,
        'total_voted'    => $totalVoted,
        'total_eligible' => $totalEligible,
        'turnout_rate'   => $turnoutRate,
        'election_count' => $electionCount,
    ];
}

// === Year range filtering for turnout analytics (faculty-style) ===

// All years we have data for
 $allTurnoutYears = array_keys($turnoutDataByYear);
sort($allTurnoutYears);

// If walang data pa, gumamit ng current year as safe default
 $defaultYear = (int)date('Y');
 $minYear = $allTurnoutYears ? min($allTurnoutYears) : $defaultYear;
 $maxYear = $allTurnoutYears ? max($allTurnoutYears) : $defaultYear;

// Read range from query string (e.g., ?from_year=2021&to_year=2024)
 $fromYear = isset($_GET['from_year']) && ctype_digit($_GET['from_year'])
    ? (int)$_GET['from_year']
    : $minYear;
 $toYear   = isset($_GET['to_year']) && ctype_digit($_GET['to_year'])
    ? (int)$_GET['to_year']
    : $maxYear;

// Clamp to known bounds
if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear   < $fromYear) $toYear  = $fromYear;

// Build range [fromYear..toYear], ensuring missing years appear with zeros
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
        ];
    }
}

// Recompute growth_rate within the selected range
 $prevY = null;
foreach ($turnoutRangeData as $y => &$data) {
    if ($prevY === null) {
        $data['growth_rate'] = 0.0;
    } else {
        $prevRate = $turnoutRangeData[$prevY]['turnout_rate'] ?? 0;
        $data['growth_rate'] = $prevRate > 0
            ? round(($data['turnout_rate'] - $prevRate) / $prevRate * 100, 1)
            : 0.0;
    }
    $prevY = $y;
}
unset($data);

// Summary cards still use single selected year (via ?year=...)
 $currentYearTurnout  = $turnoutDataByYear[$selectedYear]     ?? null;
 $previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;

// --- Department turnout (selected year) ----------------------------

 $departmentTurnoutData = [];

 $stmt = $pdo->prepare("
    SELECT DISTINCT v.voter_id
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    WHERE e.assigned_admin_id = ?
      AND YEAR(e.start_datetime) = ?
");
 $stmt->execute([$userId, $selectedYear]);
 $votedIds = array_column($stmt->fetchAll(), 'voter_id');
 $votedSet = array_flip($votedIds);

 $deptBuckets     = [];
 $yearEndSelected = sprintf('%04d-12-31 23:59:59', $selectedYear);

foreach ($scopedStudents as $s) {
    if ($s['created_at'] > $yearEndSelected) continue;

    $dept = $s['department1'] ?: $s['department'] ?: 'Unspecified';
    if (!isset($deptBuckets[$dept])) {
        $deptBuckets[$dept] = ['eligible_count' => 0, 'voted_count' => 0];
    }
    $deptBuckets[$dept]['eligible_count']++;
    if (isset($votedSet[$s['user_id']])) {
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

// --- Course turnout (selected year) -------------------------------

 $courseTurnoutData = [];
 $courseBuckets     = [];

foreach ($scopedStudents as $s) {
    if ($s['created_at'] > $yearEndSelected) continue;

    $code = normalize_course_code($s['course'] ?? '');
    if ($code === '') $code = 'UNSPECIFIED';

    if (!isset($courseBuckets[$code])) {
        $courseBuckets[$code] = ['eligible_count' => 0, 'voted_count' => 0];
    }
    $courseBuckets[$code]['eligible_count']++;
    if (isset($votedSet[$s['user_id']])) {
        $courseBuckets[$code]['voted_count']++;
    }
}

foreach ($courseBuckets as $code => $c) {
    $rate = ($c['eligible_count'] > 0)
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

// --- Scope display ------------------------------------------------

function formatScopeForDisplay(string $category, string $scope, string $scope1, array $details): string {
    switch ($category) {
        case 'Academic-Student':
            if (empty($scope1) || $scope1 === 'All') return "$scope - All Courses";
            return "$scope - Courses: $scope1";
        case 'Academic-Faculty':
            if (empty($scope1) || $scope1 === 'All') return "$scope - All Departments";
            return "$scope - Departments: $scope1";
        default:
            return $scope;
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
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">

  <?php include 'sidebar.php'; ?>

  <header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
    <div class="flex items-center space-x-4">
      <div class="scope-badge"><?= htmlspecialchars($scopeDisplay) ?></div>
      <h1 class="text-2xl font-bold text-white">
        <?= htmlspecialchars($collegeFullName) ?> ADMIN DASHBOARD
      </h1>
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
                </select>
              </div>
              <div class="flex items-center">
                <label for="breakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
                <select id="breakdownSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                  <option value="year">Year</option>
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

  function getFullCourseNameJS(code) {
    return courseMapJS[code] || code;
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

  // Elections vs Turnout chart
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
    }
  };

  let currentDataSeries = 'elections';
  let currentBreakdown  = 'year';

  function renderElectionsVsTurnout() {
    const ctx = document.getElementById('electionsVsTurnoutChart');
    if (!ctx) return;
    if (electionsVsTurnoutChartInstance) electionsVsTurnoutChartInstance.destroy();

    const breakdownSelect = document.getElementById('breakdownSelect');
    let data, options;

    if (currentDataSeries === 'elections') {
      breakdownSelect.value    = 'year';
      breakdownSelect.disabled = true;
      currentBreakdown         = 'year';

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
            labels: { font: { size: 12 }, padding: 15 }
          },
          title: {
            display: true,
            text: 'Elections vs Turnout Rate by Year',
            font: { size: 16, weight: 'bold' },
            padding: { top: 10, bottom: 20 }
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
            ticks: { callback: v => v + '%' },
            grid: { drawOnChartArea: false }
          },
          x: { grid: { display: false } }
        }
      };

    } else {
      breakdownSelect.disabled = false;

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
              text: 'Eligible Students vs Turnout Rate by Year',
              font: { size: 16, weight: 'bold' },
              padding: { top: 10, bottom: 20 }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              position: 'left',
              title: {
                display: true,
                text: 'Number of Students',
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
        };

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
              text: 'Eligible Students vs Turnout Rate by Department',
              font: { size: 16, weight: 'bold' },
              padding: { top: 10, bottom: 20 }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              position: 'left',
              title: {
                display: true,
                text: 'Number of Students',
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
        };

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
              text: 'Eligible Students vs Turnout Rate by Course',
              font: { size: 16, weight: 'bold' },
              padding: { top: 10, bottom: 20 }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              position: 'left',
              title: {
                display: true,
                text: 'Number of Students',
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
        };
      }
    }

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
      headers = ['Year', 'Number of Elections', 'Turnout Rate'];
      rows    = chartData.elections.year.labels.map((label, i) => [
        label,
        chartData.elections.year.electionCounts[i].toLocaleString(),
        chartData.elections.year.turnoutRates[i] + '%'
      ]);
    } else {
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
      }
    }

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
    currentDataSeries = this.value;
    if (currentDataSeries === 'elections') {
      const breakdownSelect = document.getElementById('breakdownSelect');
      breakdownSelect.value    = 'year';
      breakdownSelect.disabled = true;
      currentBreakdown         = 'year';
    }
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