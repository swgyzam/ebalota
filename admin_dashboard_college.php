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

// --- Auth check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

 $userId = $_SESSION['user_id'];

// Get user info including scope
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = ?");
 $stmt->execute([$userId]);
 $userInfo = $stmt->fetch();

 $role = $userInfo['role'] ?? '';
 $scope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

// Valid college scopes
 $validCollegeScopes = ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'];

// Verify this is the correct scope for this dashboard
if (!in_array($scope, $validCollegeScopes)) {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// --- Get available years for dropdown ---
 $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE role = 'voter' AND position = 'student' ORDER BY year DESC");
 $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
 $currentYear = date('Y');
 $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
 $previousYear = $selectedYear - 1;

// --- Fetch dashboard stats ---

// Total Students in College (unchanged - still based on scope)
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters 
                     FROM users 
                     WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ?");
 $stmt->execute([$scope]);
 $total_voters = $stmt->fetch()['total_voters'];

// Total Elections (now based on assigned_admin_id)
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_elections 
                     FROM elections 
                     WHERE assigned_admin_id = ?");
 $stmt->execute([$userId]);
 $total_elections = $stmt->fetch()['total_elections'];

// Ongoing Elections (now based on assigned_admin_id)
 $stmt = $pdo->prepare("SELECT COUNT(*) as ongoing_elections 
                     FROM elections 
                     WHERE status = 'ongoing' AND assigned_admin_id = ?");
 $stmt->execute([$userId]);
 $ongoing_elections = $stmt->fetch()['ongoing_elections'];

// --- Fetch elections for display (now based on assigned_admin_id) ---
 $electionStmt = $pdo->prepare("SELECT * FROM elections 
                               WHERE assigned_admin_id = ?
                               ORDER BY start_datetime DESC");
 $electionStmt->execute([$userId]);
 $elections = $electionStmt->fetchAll();

// --- Fetch College Analytics Data ---

// Get voters distribution by department (within the college) - unchanged
 $stmt = $pdo->prepare("SELECT 
                        COALESCE(NULLIF(department1, ''), department) as department_name,
                        COUNT(*) as count
                     FROM users 
                     WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ?
                     GROUP BY department_name
                     ORDER BY count DESC");
 $stmt->execute([$scope]);
 $votersByDepartment = $stmt->fetchAll();

/* ==========================================================
   COURSE MAPS + NORMALIZATION HELPERS (unchanged)
   ========================================================== */

// Course mapping for full names (display)
 $courseMap = [
    // CEIT Courses
    'BSCS'  => 'Bachelor of Science in Computer Science',
    'BSIT'  => 'Bachelor of Science in Information Technology',
    'BSCpE' => 'Bachelor of Science in Computer Engineering',
    'BSECE' => 'Bachelor of Science in Electronics Engineering',
    'BSCE'  => 'Bachelor of Science in Civil Engineering',
    'BSME'  => 'Bachelor of Science in Mechanical Engineering',
    'BSEE'  => 'Bachelor of Science in Electrical Engineering',
    'BSIE'  => 'Bachelor of Science in Industrial Engineering',
    
    // CAFENR Courses
    'BSAgri' => 'Bachelor of Science in Agriculture',
    'BSAB'   => 'Bachelor of Science in Agribusiness',
    'BSES'   => 'Bachelor of Science in Environmental Science',
    'BSFT'   => 'Bachelor of Science in Food Technology',
    'BSFor'  => 'Bachelor of Science in Forestry',
    'BSABE'  => 'Bachelor of Science in Agricultural and Biosystems Engineering',
    'BAE'    => 'Bachelor of Agricultural Entrepreneurship',
    'BSLDM'  => 'Bachelor of Science in Land Use Design and Management',
    
    // CAS Courses
    'BSBio'    => 'Bachelor of Science in Biology',
    'BSChem'   => 'Bachelor of Science in Chemistry',
    'BSMath'   => 'Bachelor of Science in Mathematics',
    'BSPhysics'=> 'Bachelor of Science in Physics',
    'BSPsych'  => 'Bachelor of Science in Psychology',
    'BAELS'    => 'Bachelor of Arts in English Language Studies',
    'BAComm'   => 'Bachelor of Arts in Communication',
    'BSStat'   => 'Bachelor of Science in Statistics',
    
    // CVMBS Courses
    'DVM'  => 'Doctor of Veterinary Medicine',
    'BSPV' => 'Bachelor of Science in Biology (Pre-Veterinary)',
    
    // CED Courses
    'BEEd' => 'Bachelor of Elementary Education',
    'BSEd' => 'Bachelor of Secondary Education',
    'BPE'  => 'Bachelor of Physical Education',
    'BTLE' => 'Bachelor of Technology and Livelihood Education',
    
    // CEMDS Courses
    'BSBA'  => 'Bachelor of Science in Business Administration',
    'BSAcc' => 'Bachelor of Science in Accountancy',
    'BSEco' => 'Bachelor of Science in Economics',
    'BSEnt' => 'Bachelor of Science in Entrepreneurship',
    'BSOA'  => 'Bachelor of Science in Office Administration',
    
    // CSPEAR Courses
    'BSESS' => 'Bachelor of Science in Exercise and Sports Sciences',
    
    // CCJ Courses
    'BSCrim' => 'Bachelor of Science in Criminology',
    
    // CON Courses
    'BSN' => 'Bachelor of Science in Nursing',
    
    // CTHM Courses
    'BSHM' => 'Bachelor of Science in Hospitality Management',
    'BSTM' => 'Bachelor of Science in Tourism Management',
    
    // COM Courses
    'BLIS' => 'Bachelor of Library and Information Science',
    
    // GS-OLC Courses
    'PhD' => 'Doctor of Philosophy',
    'MS'  => 'Master of Science',
    'MA'  => 'Master of Arts',
];

// Function to get full course name (existing)
function getFullCourseName($abbr) {
    global $courseMap;
    
    // If it's already a full name, return as is
    if (is_string($abbr) && strlen($abbr) > 10 && strpos($abbr, 'Bachelor') !== false) {
        return $abbr;
    }
    return $courseMap[$abbr] ?? $abbr;
}

// --- COURSE NORMALIZATION HELPERS (unchanged) ---

/**
 * Normalize any free-text course label to a canonical course code (e.g., BSIT, BSCS, BSCpE).
 * Collapses variants like "BS Information Technology", "Bachelor of Science in Information Technology" -> "BSIT".
 */
function normalize_course_code(string $raw): string {
    $s = strtoupper(trim($raw));

    if ($s === '') return 'UNSPECIFIED';

    // Remove punctuation and compress whitespace
    $s = preg_replace('/[.\-_,]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    // Standardize long phrases to compact tokens
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
        'SECONDARY EDUCATION'     => 'SEd',
        'ELEMENTARY EDUCATION'    => 'EEd',
        'PHYSICAL EDUCATION'      => 'PE',
        'TECHNOLOGY AND LIVELIHOOD EDUCATION' => 'TLE',
        'PRE VETERINARY'          => 'PV',
        'VETERINARY MEDICINE'     => 'DVM',
    ];
    foreach ($replacements as $from => $to) {
        $s = str_replace($from, $to, $s);
    }

    // Remove extra spaces again
    $s = preg_replace('/\s+/', ' ', trim($s));

    // Canonical patterns -> final codes
    $patterns = [
        '/^BS ?IT$/'     => 'BSIT',
        '/^BS ?CS$/'     => 'BSCS',
        '/^BS ?CPE$/'    => 'BSCpE',
        '/^BS ?ECE$/'    => 'BSECE',
        '/^BS ?CE$/'     => 'BSCE',
        '/^BS ?ME$/'     => 'BSME',
        '/^BS ?EE$/'     => 'BSEE',
        '/^BS ?IE$/'     => 'BSIE',

        '/^BS ?AGRI$/'   => 'BSAgri',
        '/^BS ?AB$/'     => 'BSAB',
        '/^BS ?ES$/'     => 'BSES',
        '/^BS ?FT$/'     => 'BSFT',
        '/^BS ?FOR$/'    => 'BSFor',
        '/^BS ?ABE$/'    => 'BSABE',
        '/^B ?AE$/'      => 'BAE',
        '/^BS ?LDM$/'    => 'BSLDM',

        '/^BS ?BIO$/'    => 'BSBio',
        '/^BS ?CHEM$/'   => 'BSChem',
        '/^BS ?MATH$/'   => 'BSMath',
        '/^BS ?PHYSICS$/' => 'BSPhysics',
        '/^BS ?PSYCH$/'  => 'BSPsych',
        '/^BA ?ELS$/'    => 'BAELS',
        '/^BA ?COMM$/'   => 'BAComm',
        '/^BS ?STAT$/'   => 'BSStat',

        '/^DVM$/'        => 'DVM',
        '/^BS ?PV$/'     => 'BSPV',

        '/^BE?ED$/'      => 'BEEd',
        '/^BS?ED$/'      => 'BSEd',
        '/^B ?PE$/'      => 'BPE',
        '/^B ?TLE$/'     => 'BTLE',

        '/^BS ?BA$/'     => 'BSBA',
        '/^BS ?ACC$/'    => 'BSAcc',
        '/^BS ?ECO$/'    => 'BSEco',
        '/^BS ?ENT$/'    => 'BSEnt',
        '/^BS ?OA$/'     => 'BSOA',

        '/^BS ?ESS$/'    => 'BSESS',
        '/^BS ?CRIM$/'   => 'BSCrim',
        '/^BS ?N$/'      => 'BSN',
        '/^BS ?HM$/'     => 'BSHM',
        '/^BS ?TM$/'     => 'BSTM',
        '/^B ?LIS$/'     => 'BLIS',

        // Graduate
        '/^PHD$/'        => 'PhD',
        '/^MS$/'         => 'MS',
        '/^MA$/'         => 'MA',
    ];
    foreach ($patterns as $regex => $code) {
        if (preg_match($regex, $s)) return $code;
    }

    // Fallback: if it already looks like a short code use that, else return cleaned original
    $noSpace = str_replace(' ', '', $s);
    if (preg_match('/^(BS|BA|BEEd|BSEd|BPE|BTLE)[A-Z]{0,6}$/', $noSpace)) {
        return $noSpace;
    }
    return $s;
}

/**
 * Canonical display full name based on $courseMap, using normalized code.
 */
function getCanonicalCourseDisplay(string $raw): string {
    $code = normalize_course_code($raw);
    return getFullCourseName($code);
}

// Get voters distribution by course (within the college) - unchanged
 $stmt = $pdo->prepare("
    SELECT course
    FROM users 
    WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ?
      AND course IS NOT NULL AND course <> ''
");
 $stmt->execute([$scope]);

 $tempCounts = [];
while ($row = $stmt->fetch()) {
    $code = normalize_course_code($row['course']);
    if (!isset($tempCounts[$code])) $tempCounts[$code] = 0;
    $tempCounts[$code]++;
}
 $votersByCourse = [];
foreach ($tempCounts as $code => $count) {
    $votersByCourse[] = [
        'course' => $code, // canonical code
        'count'  => $count,
    ];
}
// Sort by count DESC
usort($votersByCourse, function($a, $b) { return $b['count'] <=> $a['count']; });

// Get status distribution - unchanged
 $stmt = $pdo->prepare("SELECT 
                        status,
                        COUNT(*) as count
                     FROM users 
                     WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ?
                       AND status IS NOT NULL AND status != ''
                     GROUP BY status
                     ORDER BY count DESC");
 $stmt->execute([$scope]);
 $byStatus = $stmt->fetchAll();

// Define date ranges for current and previous month - unchanged
 $currentMonthStart = date('Y-m-01');
 $currentMonthEnd = date('Y-m-t');
 $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
 $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

// Get new voters this month - unchanged
 $stmt = $pdo->prepare("SELECT COUNT(*) as new_voters 
                      FROM users 
                      WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ?
                        AND created_at BETWEEN ? AND ?");
 $stmt->execute([$scope, $currentMonthStart, $currentMonthEnd]);
 $newVoters = $stmt->fetch()['new_voters'];

// Get voters count for last month - unchanged
 $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_voters 
                      FROM users 
                      WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ?
                        AND created_at BETWEEN ? AND ?");
 $stmt->execute([$scope, $lastMonthStart, $lastMonthEnd]);
 $result = $stmt->fetch();
 $lastMonthVoters = $result['last_month_voters'] ?? 0;

// Calculate growth rate for summary card - unchanged
if ($lastMonthVoters > 0) {
    $growthRate = round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1);
} else {
    $growthRate = 0;
}

// --- Fetch Voter Turnout Analytics Data (UPDATED) ---
 $turnoutDataByYear = [];

// Get all years that have elections assigned to this admin
 $stmt = $pdo->prepare("SELECT DISTINCT YEAR(start_datetime) as year FROM elections 
                        WHERE assigned_admin_id = ?
                        ORDER BY year DESC");
 $stmt->execute([$userId]);
 $turnoutYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($turnoutYears as $year) {
    // Get distinct voters who voted in elections assigned to this admin
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) as total_voted 
                           FROM votes v 
                           JOIN elections e ON v.election_id = e.election_id 
                           WHERE e.assigned_admin_id = ? AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$userId, $year]);
    $totalVoted = $stmt->fetch()['total_voted'];

    // Get total students in this college as of December 31 of this year
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                           FROM users 
                           WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = ? 
                           AND created_at <= ?");
    $stmt->execute([$scope, $year . '-12-31 23:59:59']);
    $totalEligible = $stmt->fetch()['total_eligible'];

    // Calculate turnout rate
    $turnoutRate = ($totalEligible > 0) ? round(($totalVoted / $totalEligible) * 100, 1) : 0;

    // Also get the number of elections assigned to this admin in this year
    $stmt = $pdo->prepare("SELECT COUNT(*) as election_count 
                           FROM elections 
                           WHERE assigned_admin_id = ? AND YEAR(start_datetime) = ?");
    $stmt->execute([$userId, $year]);
    $electionCount = $stmt->fetch()['election_count'];

    $turnoutDataByYear[$year] = [
        'year' => $year,
        'total_voted' => $totalVoted,
        'total_eligible' => $totalEligible,
        'turnout_rate' => $turnoutRate,
        'election_count' => $electionCount
    ];
}

// Add previous year if not exists (for comparison only)
 $prevYear = $selectedYear - 1;
if (!isset($turnoutDataByYear[$prevYear])) {
    $turnoutDataByYear[$prevYear] = [
        'year' => $prevYear,
        'total_voted' => 0,
        'total_eligible' => 0,
        'turnout_rate' => 0,
        'election_count' => 0,
        'growth_rate' => 0
    ];
}

// Calculate year-over-year growth for turnout
 $years = array_keys($turnoutDataByYear);
sort($years);
 $previousYear = null;
foreach ($years as $year) {
    if ($previousYear !== null) {
        $prevTurnout = $turnoutDataByYear[$previousYear]['turnout_rate'];
        $currentTurnout = $turnoutDataByYear[$year]['turnout_rate'];
        $growthRate = ($prevTurnout > 0) ? round((($currentTurnout - $prevTurnout) / $prevTurnout) * 100, 1) : 0;
        $turnoutDataByYear[$year]['growth_rate'] = $growthRate;
    } else {
        $turnoutDataByYear[$year]['growth_rate'] = 0;
    }
    $previousYear = $year;
}

// --- Department turnout data (UPDATED) ---
 $departmentTurnoutData = [];
 $stmt = $pdo->prepare("
    SELECT 
        COALESCE(NULLIF(u.department1, ''), u.department) as department_name,
        COUNT(DISTINCT u.user_id) as eligible_count,
        COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
    FROM users u
    LEFT JOIN (
        SELECT DISTINCT voter_id 
        FROM votes 
        WHERE election_id IN (
            SELECT election_id FROM elections 
            WHERE assigned_admin_id = ? AND YEAR(start_datetime) = ?
        )
    ) v ON u.user_id = v.voter_id
    WHERE u.role = 'voter' AND u.position = 'student' AND UPPER(TRIM(u.department)) = ?
    GROUP BY department_name
    ORDER BY department_name
");
 $stmt->execute([$userId, $selectedYear, $scope]);
 $deptResults = $stmt->fetchAll();

foreach ($deptResults as $row) {
    $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
    $departmentTurnoutData[] = [
        'department' => $row['department_name'],
        'eligible_count' => (int)$row['eligible_count'],
        'voted_count' => (int)$row['voted_count'],
        'turnout_rate' => (float)$turnoutRate
    ];
}

/* ==========================================================
   COURSE TURNOUT DATA (UPDATED)
   ========================================================== */
 $courseTurnoutData = [];
// Pull set of voted user_ids for the selected year & admin
 $stmt = $pdo->prepare("
    SELECT DISTINCT v.voter_id
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    WHERE e.assigned_admin_id = ? AND YEAR(e.start_datetime) = ?
");
 $stmt->execute([$userId, $selectedYear]);
 $votedIds = array_column($stmt->fetchAll(), 'voter_id');
 $votedSet = array_flip($votedIds);

// Fetch all eligible users for the scope (with their raw course)
 $stmt = $pdo->prepare("
    SELECT u.user_id, u.course
    FROM users u
    WHERE u.role = 'voter' AND u.position = 'student' AND UPPER(TRIM(u.department)) = ?
");
 $stmt->execute([$scope]);

 $byCourse = [];
while ($u = $stmt->fetch()) {
    $code = normalize_course_code($u['course'] ?? '');
    if ($code === '') $code = 'UNSPECIFIED';
    if (!isset($byCourse[$code])) {
        $byCourse[$code] = ['eligible_count' => 0, 'voted_count' => 0];
    }
    $byCourse[$code]['eligible_count']++;
    if (isset($votedSet[$u['user_id']])) {
        $byCourse[$code]['voted_count']++;
    }
}

foreach ($byCourse as $code => $counts) {
    $rate = ($counts['eligible_count'] > 0)
        ? round(($counts['voted_count'] / $counts['eligible_count']) * 100, 1)
        : 0.0;
    $courseTurnoutData[] = [
        'course' => $code,
        'eligible_count' => (int)$counts['eligible_count'],
        'voted_count' => (int)$counts['voted_count'],
        'turnout_rate' => (float)$rate,
    ];
}
usort($courseTurnoutData, fn($a, $b) => $b['eligible_count'] <=> $a['eligible_count']);

/* ==========================================================
   REMAINDER OF FILE — HTML RENDER (unchanged)
   ========================================================== */

 $collegeFullNameMap = [
    'CAFENR' => 'College of Agriculture, Food, Environment and Natural Resources',
    'CEIT' => 'College of Engineering and Information Technology',
    'CAS' => 'College of Arts and Sciences',
    'CVMBS' => 'College of Veterinary Medicine and Biomedical Sciences',
    'CED' => 'College of Education',
    'CEMDS' => 'College of Economics, Management and Development Studies',
    'CSPEAR' => 'College of Sports, Physical Education and Recreation',
    'CCJ' => 'College of Criminal Justice',
    'CON' => 'College of Nursing',
    'CTHM' => 'College of Tourism and Hospitality Management',
    'COM' => 'College of Medicine',
    'GS-OLC' => 'Graduate School and Open Learning College',
];
 $collegeFullName = $collegeFullNameMap[$scope] ?? $scope;
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
    .analytics-card { transition: transform .2s, box-shadow .2s; }
    .analytics-card:hover { transform: translateY(-2px); }
    .status-badge {
      display:inline-flex;align-items:center;padding:.25rem .75rem;
      border-radius:9999px;font-size:.75rem;font-weight:600;
    }
    .chart-container{position:relative;height:320px;width:100%;}
    .chart-tooltip{position:absolute;background-color:white;color:#333;padding:12px 16px;
      border-radius:8px;font-size:14px;pointer-events:none;opacity:0;transition:opacity .2s;
      z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;
      max-width:220px;display:none;right:10px;top:10px;left:auto;}
    .chart-tooltip.show{opacity:1;display:block;}
    .chart-tooltip .title{font-weight:bold;color:var(--cvsu-green-dark);margin-bottom:4px;font-size:16px;}
    .chart-tooltip .count{color:#4b5563;font-size:14px;}
    .breakdown-section{display:none;}
    .breakdown-section.active{display:block;}
    .cvsu-gradient{
      background:linear-gradient(135deg,var(--cvsu-green-dark)0%,var(--cvsu-green)100%);
    }
    .cvsu-card{
      background-color:white;border-left:4px solid var(--cvsu-green);
      box-shadow:0 4px 6px rgba(0,0,0,.05);transition:all .3s;
    }
    .cvsu-card:hover{box-shadow:0 10px 15px rgba(0,0,0,.1);transform:translateY(-2px);}
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">
<?php include 'sidebar.php'; ?>
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
  <h1 class="text-2xl font-bold text-white">
    <?= htmlspecialchars($collegeFullName) ?> ADMIN DASHBOARD
  </h1>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
  </div>
</header>
<main class="flex-1 pt-20 px-8 ml-64">



  <!-- Statistics Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Students -->
    <div class="cvsu-card p-6 rounded-xl">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Students</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-green-dark);"><?= number_format($total_voters) ?></p>
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

  <?php
  // Department mapping for full names (optional pretty names for abbreviations found in department1)
  $departmentMap = [
      // CEIT
      'Department of Civil Engineering' => 'DCE',
      'Department of Computer and Electronics Engineering' => 'DCEE',
      'Department of Industrial Engineering and Technology' => 'DIET',
      'Department of Mechanical and Electronics Engineering' => 'DMEE',
      'Department of Information Technology' => 'DIT',
      // CAFENR
      'Department of Animal Science' => 'DAS',
      'Department of Crop Science' => 'DCS',
      'Department of Food Science and Technology' => 'DFST',
      'Department of Forestry and Environmental Science' => 'DFES',
      'Department of Agricultural Economics and Development' => 'DAED',
      // CAS
      'Department of Biological Sciences' => 'DBS',
      'Department of Physical Sciences' => 'DPS',
      'Department of Languages and Mass Communication' => 'DLMC',
      'Department of Social Sciences' => 'DSS',
      'Department of Mathematics and Statistics' => 'DMS',
      // CCJ
      'Department of Criminal Justice' => 'DCJ',
      // CEMDS
      'Department of Economics' => 'DE',
      'Department of Business and Management' => 'DBM',
      'Department of Development Studies' => 'DDS',
      // CED
      'Department of Science Education' => 'DSE',
      'Department of Technology and Livelihood Education' => 'DTLE',
      'Department of Curriculum and Instruction' => 'DCI',
      'Department of Human Kinetics' => 'DHK',
      // CON
      'Department of Nursing' => 'DN',
      // COM
      'Department of Basic Medical Sciences' => 'DBMS',
      'Department of Clinical Sciences' => 'DCS',
      // CSPEAR
      'Department of Physical Education and Recreation' => 'DPER',
      // CVMBS
      'Department of Veterinary Medicine' => 'DVM',
      'Department of Biomedical Sciences' => 'DBS',
      // GS-OLC
      'Department of Various Graduate Programs' => 'DVGP',
  ];
  function getFullDepartmentName($abbr) {
      global $departmentMap;
      // if $abbr is the full name already, return it; else map reverse
      $rev = array_flip($departmentMap);
      return $rev[$abbr] ?? $abbr;
  }
  ?>

  <!-- Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($collegeFullName) ?> Student Analytics</h2>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color: var(--cvsu-green-light);">
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
                    $displayGrowthRate = round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1);
                    echo ($displayGrowthRate > 0 ? '+' : '') . $displayGrowthRate . '%';
                } else { echo '0%'; }
              ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Analytics -->
    <div id="analyticsDetails" class="border-t">
      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Department Distribution Chart -->
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

          <!-- Course Distribution Chart -->
          <div class="p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.03);">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Students by Course</h3>
            <div class="chart-container">
              <canvas id="courseChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Detailed Breakdown Section -->
        <div class="mt-8">
          <div class="mb-6 flex items-center">
            <label for="breakdownType" class="mr-2 text-gray-700 font-medium">Breakdown by:</label>
            <select id="breakdownType" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <option value="department">Department</option>
              <option value="course">Course</option>
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
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($votersByDepartment as $department):
                      $percentage = ($total_voters > 0) ? round(($department['count'] / $total_voters) * 100, 1) : 0;
                    ?>
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap font-medium">
                        <?= htmlspecialchars(getFullDepartmentName($department['department_name'])) ?>
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

          <!-- Course Breakdown -->
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
                      $percentage = ($total_voters > 0) ? round(($course['count'] / $total_voters) * 100, 1) : 0;
                    ?>
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap font-medium">
                        <?= htmlspecialchars(getCanonicalCourseDisplay($course['course'])) ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap"><?= number_format($course['count']) ?></td>
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
        </div> <!-- /Detailed Breakdown -->
      </div>
    </div>

    <!-- Voter Turnout Analytics Section -->
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

      <?php
      $currentYearTurnout = $turnoutDataByYear[$selectedYear] ?? null;
      $previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;
      ?>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(99,102,241,0.05); border-color:#6366F1;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-indigo-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-indigo-600"><?= $selectedYear ?> Turnout</p>
              <p class="text-2xl font-bold text-indigo-800"><?= $currentYearTurnout['turnout_rate'] ?? 0 ?>%</p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color:#8B5CF6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-purple-600"><?= $selectedYear - 1 ?> Turnout</p>
              <p class="text-2xl font-bold text-purple-800"><?= $previousYearTurnout['turnout_rate'] ?? 0 ?>%</p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(16,185,129,0.05); border-color:#10B981;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-green-500"><i class="fas fa-chart-line text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-green-600">Growth Rate</p>
              <p class="text-2xl font-bold text-green-800">
                <?php
                $ct = $currentYearTurnout['turnout_rate'] ?? 0;
                $pt = $previousYearTurnout['turnout_rate'] ?? 0;
                echo $pt > 0 ? ((($ct-$pt)/$pt>0?'+':'').round((($ct-$pt)/$pt)*100,1).'%') : '0%';
                ?>
              </p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color:#3B82F6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-blue-500"><i class="fas fa-vote-yea text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-blue-600">Elections</p>
              <p class="text-2xl font-bold text-blue-800"><?= $currentYearTurnout['election_count'] ?? 0 ?></p>
            </div>
          </div>
        </div>
      </div>

      <div class="p-4 rounded-lg mb-8" style="background-color: rgba(30,111,70,0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Turnout Rate Trend</h3>
        <div class="chart-container" style="height: 400px;">
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
              <?php foreach ($turnoutDataByYear as $year => $data):
                $isPositive = ($data['growth_rate'] ?? 0) > 0;
                $trendIcon = $isPositive ? 'fa-arrow-up' : (($data['growth_rate'] ?? 0) < 0 ? 'fa-arrow-down' : 'fa-minus');
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

      <!-- Elections vs Turnout Rate -->
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
      </div>
    </div>
  </div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Toggle breakdown
  const breakdownType = document.getElementById('breakdownType');
  breakdownType?.addEventListener('change', function() {
    document.querySelectorAll('.breakdown-section').forEach(s => s.classList.remove('active'));
    document.getElementById(this.value + 'Breakdown')?.classList.add('active');
  });

  // Year selectors
  document.getElementById('turnoutYearSelector')?.addEventListener('change', function() {
    window.location.href = window.location.pathname + '?year=' + this.value;
  });

  let departmentChartInstance = null;
  let courseChartInstance = null;
  let turnoutTrendChartInstance = null;
  let electionsVsTurnoutChartInstance = null;

  // Data from PHP
  const departmentLabels = <?= json_encode(array_column($votersByDepartment, 'department_name')) ?>;
  const departmentCounts = <?= json_encode(array_column($votersByDepartment, 'count')) ?>;
  const courseLabelsRaw = <?= json_encode(array_column($votersByCourse, 'course')) ?>; // canonical codes
  const courseCounts = <?= json_encode(array_column($votersByCourse, 'count')) ?>;
  const turnoutYears = <?= json_encode(array_keys($turnoutDataByYear)) ?>;
  const turnoutRates = <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?>;
  const departmentTurnoutData = <?= json_encode($departmentTurnoutData) ?>;
  const courseTurnoutData = <?= json_encode($courseTurnoutData) ?>;

  // JS Course map (no duplicates)
  const courseMap = {
    BSCS:'Bachelor of Science in Computer Science',
    BSIT:'Bachelor of Science in Information Technology',
    BSCpE:'Bachelor of Science in Computer Engineering',
    BSECE:'Bachelor of Science in Electronics Engineering',
    BSCE:'Bachelor of Science in Civil Engineering',
    BSME:'Bachelor of Science in Mechanical Engineering',
    BSEE:'Bachelor of Science in Electrical Engineering',
    BSIE:'Bachelor of Science in Industrial Engineering',
    BSAgri:'Bachelor of Science in Agriculture',
    BSAB:'Bachelor of Science in Agribusiness',
    BSES:'Bachelor of Science in Environmental Science',
    BSFT:'Bachelor of Science in Food Technology',
    BSFor:'Bachelor of Science in Forestry',
    BSABE:'Bachelor of Science in Agricultural and Biosystems Engineering',
    BAE:'Bachelor of Agricultural Entrepreneurship',
    BSLDM:'Bachelor of Science in Land Use Design and Management',
    BSBio:'Bachelor of Science in Biology',
    BSChem:'Bachelor of Science in Chemistry',
    BSMath:'Bachelor of Science in Mathematics',
    BSPhysics:'Bachelor of Science in Physics',
    BSPsych:'Bachelor of Science in Psychology',
    BAELS:'Bachelor of Arts in English Language Studies',
    BAComm:'Bachelor of Arts in Communication',
    BSStat:'Bachelor of Science in Statistics',
    DVM:'Doctor of Veterinary Medicine',
    BSPV:'Bachelor of Science in Biology (Pre-Veterinary)',
    BEEd:'Bachelor of Elementary Education',
    BSEd:'Bachelor of Secondary Education',
    BPE:'Bachelor of Physical Education',
    BTLE:'Bachelor of Technology and Livelihood Education',
    BSBA:'Bachelor of Science in Business Administration',
    BSAcc:'Bachelor of Science in Accountancy',
    BSEco:'Bachelor of Science in Economics',
    BSEnt:'Bachelor of Science in Entrepreneurship',
    BSOA:'Bachelor of Science in Office Administration',
    BSESS:'Bachelor of Science in Exercise and Sports Sciences',
    BSCrim:'Bachelor of Science in Criminology',
    BSN:'Bachelor of Science in Nursing',
    BSHM:'Bachelor of Science in Hospitality Management',
    BSTM:'Bachelor of Science in Tourism Management',
    BLIS:'Bachelor of Library and Information Science',
    PhD:'Doctor of Philosophy',
    MS:'Master of Science',
    MA:'Master of Arts',
    UNSPECIFIED:'Unspecified'
  };
  
  // Department abbreviation mapping
  const departmentAbbrevMap = {
    'College of Agriculture, Food, Environment and Natural Resources': 'CAFENR',
    'College of Engineering and Information Technology': 'CEIT',
    'College of Arts and Sciences': 'CAS',
    'College of Veterinary Medicine and Biomedical Sciences': 'CVMBS',
    'College of Education': 'CED',
    'College of Economics, Management and Development Studies': 'CEMDS',
    'College of Sports, Physical Education and Recreation': 'CSPEAR',
    'College of Criminal Justice': 'CCJ',
    'College of Nursing': 'CON',
    'College of Tourism and Hospitality Management': 'CTHM',
    'College of Medicine': 'COM',
    'Graduate School and Open Learning College': 'GS-OLC'
  };
  
  // Department/Course abbreviation map
  const deptCourseAbbrevMap = {
    'Department of Civil Engineering': 'DCE',
    'Department of Computer and Electronics Engineering': 'DCEE',
    'Department of Industrial Engineering and Technology': 'DIET',
    'Department of Mechanical and Electronics Engineering': 'DMEE',
    'Department of Information Technology': 'DIT',
    'Department of Animal Science': 'DAS',
    'Department of Crop Science': 'DCS',
    'Department of Food Science and Technology': 'DFST',
    'Department of Forestry and Environmental Science': 'DFES',
    'Department of Agricultural Economics and Development': 'DAED',
    'Department of Biological Sciences': 'DBS',
    'Department of Physical Sciences': 'DPS',
    'Department of Languages and Mass Communication': 'DLMC',
    'Department of Social Sciences': 'DSS',
    'Department of Mathematics and Statistics': 'DMS',
    'Department of Criminal Justice': 'DCJ',
    'Department of Economics': 'DE',
    'Department of Business and Management': 'DBM',
    'Department of Development Studies': 'DDS',
    'Department of Science Education': 'DSE',
    'Department of Technology and Livelihood Education': 'DTLE',
    'Department of Curriculum and Instruction': 'DCI',
    'Department of Human Kinetics': 'DHK',
    'Department of Nursing': 'DN',
    'Department of Basic Medical Sciences': 'DBMS',
    'Department of Clinical Sciences': 'DCS',
    'Department of Physical Education and Recreation': 'DPER',
    'Department of Veterinary Medicine': 'DVM',
    'Department of Biomedical Sciences': 'DBS',
    'Department of Various Graduate Programs': 'DVGP',
    // Courses
    'Bachelor of Science in Computer Science': 'BSCS',
    'Bachelor of Science in Information Technology': 'BSIT',
    'Bachelor of Science in Computer Engineering': 'BSCpE',
    'Bachelor of Science in Electronics Engineering': 'BSECE',
    'Bachelor of Science in Civil Engineering': 'BSCE',
    'Bachelor of Science in Mechanical Engineering': 'BSME',
    'Bachelor of Science in Electrical Engineering': 'BSEE',
    'Bachelor of Science in Industrial Engineering': 'BSIE',
    'Bachelor of Science in Agriculture': 'BSAgri',
    'Bachelor of Science in Agribusiness': 'BSAB',
    'Bachelor of Science in Environmental Science': 'BSES',
    'Bachelor of Science in Food Technology': 'BSFT',
    'Bachelor of Science in Forestry': 'BSFor',
    'Bachelor of Science in Agricultural and Biosystems Engineering': 'BSABE',
    'Bachelor of Agricultural Entrepreneurship': 'BAE',
    'Bachelor of Science in Land Use Design and Management': 'BSLDM',
    'Bachelor of Science in Biology': 'BSBio',
    'Bachelor of Science in Chemistry': 'BSChem',
    'Bachelor of Science in Mathematics': 'BSMath',
    'Bachelor of Science in Physics': 'BSPhysics',
    'Bachelor of Science in Psychology': 'BSPsych',
    'Bachelor of Arts in English Language Studies': 'BAELS',
    'Bachelor of Arts in Communication': 'BAComm',
    'Bachelor of Science in Statistics': 'BSStat',
    'Doctor of Veterinary Medicine': 'DVM',
    'Bachelor of Science in Biology (Pre-Veterinary)': 'BSPV',
    'Bachelor of Elementary Education': 'BEEd',
    'Bachelor of Secondary Education': 'BSEd',
    'Bachelor of Physical Education': 'BPE',
    'Bachelor of Technology and Livelihood Education': 'BTLE',
    'Bachelor of Science in Business Administration': 'BSBA',
    'Bachelor of Science in Accountancy': 'BSAcc',
    'Bachelor of Science in Economics': 'BSEco',
    'Bachelor of Science in Entrepreneurship': 'BSEnt',
    'Bachelor of Science in Office Administration': 'BSOA',
    'Bachelor of Science in Exercise and Sports Sciences': 'BSESS',
    'Bachelor of Science in Criminology': 'BSCrim',
    'Bachelor of Science in Nursing': 'BSN',
    'Bachelor of Science in Hospitality Management': 'BSHM',
    'Bachelor of Science in Tourism Management': 'BSTM',
    'Bachelor of Library and Information Science': 'BLIS',
    'Doctor of Philosophy': 'PhD',
    'Master of Science': 'MS',
    'Master of Arts': 'MA'
  };

  function getFullCourseNameJS(code){ return courseMap[code] || code; }
  
  function getAbbreviatedName(fullName) {
    // First check if it's a college name
    if (departmentAbbrevMap[fullName]) {
      return departmentAbbrevMap[fullName];
    }
    
    // Then check if it's a department/course name
    if (deptCourseAbbrevMap[fullName]) {
      return deptCourseAbbrevMap[fullName];
    }
    
    // Words to exclude from abbreviations
    const excludeWords = ['and', 'of', 'the', 'in', 'for', 'at', 'by'];
    
    // For colleges that start with "College of " but not in the map
    if (fullName.startsWith("College of ")) {
      // Try to match by key in the map
      for (let [name, abbrev] of Object.entries(departmentAbbrevMap)) {
        if (fullName === name) {
          return abbrev;
        }
      }
      // If not found, generate abbreviation from the name
      // Remove "College of " and take first letters of remaining words, excluding common words
      let rest = fullName.substring(13).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return abbr;
    }
    
    // For departments that start with "Department of "
    if (fullName.startsWith("Department of ")) {
      let rest = fullName.substring(13).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "D" + abbr;
    }
    
    // For courses that start with "Bachelor of Science in "
    if (fullName.startsWith("Bachelor of Science in ")) {
      let rest = fullName.substring(22).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "BS" + abbr;
    }
    
    // For courses that start with "Bachelor of Arts in "
    if (fullName.startsWith("Bachelor of Arts in ")) {
      let rest = fullName.substring(18).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "BA" + abbr;
    }
    
    // For courses that start with "Bachelor of "
    if (fullName.startsWith("Bachelor of ")) {
      let rest = fullName.substring(13).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "B" + abbr;
    }
    
    // For "Doctor of" and "Master of"
    if (fullName.startsWith("Doctor of ")) {
      let rest = fullName.substring(11).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "D" + abbr;
    }
    
    if (fullName.startsWith("Master of ")) {
      let rest = fullName.substring(11).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "M" + abbr;
    }
    
    // Handle special cases not covered above
    if (fullName === "General") {
      return "GEN";
    }
    
    // Default: return the full name
    return fullName;
  }

  // Department -> pretty name helper
  const departmentPrettyMap = {}; // optional (kept minimal)
  function getFullDepartmentNameJS(label){ return departmentPrettyMap[label] || label; }

  /* Department Doughnut */
  const departmentCtx = document.getElementById('departmentChart');
  const chartTooltip = document.getElementById('chartTooltip');
  const tooltipTitle = chartTooltip?.querySelector('.title');
  const tooltipCount = chartTooltip?.querySelector('.count');

  if (departmentCtx && !departmentChartInstance) {
    const colors = ['#1E6F46','#37A66B','#FFD166','#154734','#2D5F3F','#4A7C59','#5A8F6A','#6A9F7A','#7AAFAA','#8ABFBA'];
    departmentChartInstance = new Chart(departmentCtx, {
      type: 'doughnut',
      data: { 
        labels: departmentLabels.map(label => getAbbreviatedName(label)), 
        datasets: [{ 
          data: departmentCounts, 
          backgroundColor: colors, 
          borderWidth:2, 
          borderColor:'#fff', 
          hoverBorderWidth:4, 
          hoverBorderColor:'#fff', 
          hoverOffset:10 
        }]
      },
      options: {
        responsive:true, 
        maintainAspectRatio:false, 
        cutout:'50%',
        plugins:{ 
          legend:{ 
            position:'right', 
            labels:{ 
              font:{size:12}, 
              padding:15, 
              usePointStyle:true, 
              pointStyle:'circle'
            }
          }, 
          tooltip:{enabled:false}
        },
        animation:{ 
          animateRotate:true, 
          animateScale:true, 
          duration:1000, 
          easing:'easeOutQuart' 
        },
        onHover:(event,active)=>{
          event.native.target.style.cursor = active.length ? 'pointer':'default';
          if(!tooltipTitle||!tooltipCount) return;
          if(active.length){
            const i = active[0].index;
            const fullName = departmentLabels[i];
            const abbrev = getAbbreviatedName(fullName);
            const count = departmentCounts[i];
            const total = departmentCounts.reduce((a,b)=>a+b,0);
            const pct = Math.round((count/total)*100);
            tooltipTitle.innerHTML = `<div style="display:flex;align-items:center;margin-bottom:8px;"><span style="display:inline-block;width:12px;height:12px;background-color:${colors[i]};border-radius:50%;margin-right:8px;"></span>${fullName}</div>`;
            tooltipCount.innerHTML = `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>Students:</span><span style="font-weight:bold;">${count}</span></div><div style="display:flex;justify-content:space-between;"><span>Percentage:</span><span style="font-weight:bold;">${pct}%</span></div>`;
            chartTooltip.classList.add('show');
          } else {
            chartTooltip.classList.remove('show');
          }
        }
      }
    });
    departmentCtx.addEventListener('mouseleave', ()=> chartTooltip?.classList.remove('show'));
  }

  /* Course Bar */
  const courseCtx = document.getElementById('courseChart');
  if (courseCtx && !courseChartInstance) {
    courseChartInstance = new Chart(courseCtx, {
      type:'bar',
      data:{ 
        labels: courseLabelsRaw.map(label => getAbbreviatedName(label)), 
        datasets:[{ 
          label:'Student Count', 
          data: courseCounts, 
          backgroundColor:'#1E6F46', 
          borderColor:'#154734', 
          borderWidth:1, 
          borderRadius:4, 
          barThickness:'flex', 
          maxBarThickness:60, 
          hoverBackgroundColor:'#37A66B', 
          hoverBorderColor:'#1E6F46' 
        }]
      },
      options:{
        responsive:true, 
        maintainAspectRatio:false,
        plugins:{
          legend:{display:false},
          tooltip:{
            backgroundColor:'rgba(0,0,0,0.8)', 
            titleFont:{size:14}, 
            bodyFont:{size:13}, 
            padding:12, 
            displayColors:false,
            callbacks:{
              title:(ctx)=> {
                // Get the index and then the full name from courseLabelsRaw
                const index = ctx[0].dataIndex;
                const fullName = courseLabelsRaw[index];
                return getFullCourseNameJS(fullName);
              },
              label:(ctx)=> {
                const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                const pct = Math.round((ctx.raw/total)*100);
                return [`Students: ${ctx.raw}`, `Percentage: ${pct}%`];
              }
            }
          }
        },
        scales:{
          y:{ 
            beginAtZero:true, 
            ticks:{precision:0, font:{size:12}}, 
            grid:{color:'rgba(0,0,0,0.1)'}, 
            title:{display:true, text:'Number of Students', font:{size:14, weight:'bold'}} 
          },
          x:{ 
            ticks:{ 
              font:{size:12}, 
              maxRotation:0, 
              minRotation:0,
              callback:(val, index) => {
                // Return abbreviated name for x-axis labels
                return getAbbreviatedName(courseLabelsRaw[index]);
              }
            }, 
            grid:{display:false}, 
            title:{display:true, text:'Course', font:{size:14, weight:'bold'}} 
          }
        },
        animation:{ duration:1000, easing:'easeOutQuart' },
        onHover:(e,a)=> e.native.target.style.cursor = a.length ? 'pointer':'default'
      }
    });
  }

  /* Turnout Trend */
  const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
  if (turnoutTrendCtx && !turnoutTrendChartInstance) {
    turnoutTrendChartInstance = new Chart(turnoutTrendCtx, {
      type:'line',
      data:{ labels: turnoutYears, datasets:[{ label:'Turnout Rate (%)', data:turnoutRates, borderColor:'#1E6F46', backgroundColor:'rgba(30,111,70,0.1)', borderWidth:3, pointBackgroundColor:'#1E6F46', pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:5, pointHoverRadius:7, fill:true, tension:0.4 }]},
      options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'rgba(0,0,0,0.8)', titleFont:{size:14}, bodyFont:{size:13}, padding:12, callbacks:{ label:(c)=> `Turnout: ${c.raw}%` }}},
        scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback:(v)=> v+'%' }, grid:{ color:'rgba(0,0,0,0.05)' }}, x:{ grid:{display:false}}}
      }
    });
  }

  /* Elections vs Turnout */
  const chartData = {
    elections:{ year:{
      labels: <?= json_encode(array_keys($turnoutDataByYear)) ?>,
      electionCounts: <?= json_encode(array_column($turnoutDataByYear, 'election_count')) ?>,
      turnoutRates: <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?>
    }},
    voters:{
      year:{ labels: <?= json_encode(array_keys($turnoutDataByYear)) ?>, eligibleCounts: <?= json_encode(array_column($turnoutDataByYear, 'total_eligible')) ?>, turnoutRates: <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?> },
      department: <?= json_encode($departmentTurnoutData) ?>,
      course: <?= json_encode($courseTurnoutData) ?>
    }
  };
  let currentDataSeries = 'elections';
  let currentBreakdown = 'year';

  function renderElectionsVsTurnout() {
    const ctx = document.getElementById('electionsVsTurnoutChart');
    if (!ctx) return;
    if (electionsVsTurnoutChartInstance) electionsVsTurnoutChartInstance.destroy();

    let data, options;
    if (currentDataSeries === 'elections') {
      data = {
        labels: chartData.elections.year.labels,
        datasets:[
          { label:'Number of Elections', data: chartData.elections.year.electionCounts, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
          { label:'Turnout Rate (%)', data: chartData.elections.year.turnoutRates, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
        ]
      };
      options = {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Elections vs Turnout Rate by Year', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
        scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Elections', font:{size:14, weight:'bold'}}},
                 y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                 x:{ grid:{display:false}}}
      };
      document.getElementById('breakdownSelect').value = 'year';
      document.getElementById('breakdownSelect').disabled = true;
    } else {
      document.getElementById('breakdownSelect').disabled = false;
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.voters.year.labels,
          datasets:[
            { label:'Eligible Students', data: chartData.voters.year.eligibleCounts, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: chartData.voters.year.turnoutRates, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Eligible Students vs Turnout Rate by Year', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Students', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      } else if (currentBreakdown === 'department') {
        const labels = chartData.voters.department.map(x=>getAbbreviatedName(x.department));
        const eligible = chartData.voters.department.map(x=>x.eligible_count);
        const tr = chartData.voters.department.map(x=>x.turnout_rate);
        data = {
          labels,
          datasets:[
            { label:'Eligible Students', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Eligible Students vs Turnout Rate by Department', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Students', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      } else if (currentBreakdown === 'course') {
        const labels = chartData.voters.course.map(x=>getAbbreviatedName(x.course));
        const eligible = chartData.voters.course.map(x=>x.eligible_count);
        const tr = chartData.voters.course.map(x=>x.turnout_rate);
        data = {
          labels,
          datasets:[
            { label:'Eligible Students', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Eligible Students vs Turnout Rate by Course', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Students', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      }
    }

    electionsVsTurnoutChartInstance = new Chart(ctx, { type:'bar', data, options });
    buildTurnoutBreakdownTable();
  }

  function buildTurnoutBreakdownTable() {
    const container = document.getElementById('turnoutBreakdownTable');
    container.innerHTML = '';
    let headers = [], rows = [];

    if (currentDataSeries === 'elections') {
      headers = ['Year','Number of Elections','Turnout Rate'];
      rows = chartData.elections.year.labels.map((label,i)=> [label, chartData.elections.year.electionCounts[i].toLocaleString(), chartData.elections.year.turnoutRates[i] + '%']);
    } else {
      if (currentBreakdown === 'year') {
        headers = ['Year','Eligible Students','Turnout Rate'];
        rows = chartData.voters.year.labels.map((label,i)=> [label, chartData.voters.year.eligibleCounts[i].toLocaleString(), chartData.voters.year.turnoutRates[i] + '%']);
      } else if (currentBreakdown === 'department') {
        headers = ['Department','Eligible Students','Voted','Turnout Rate'];
        rows = chartData.voters.department.map(row => [row.department, row.eligible_count.toLocaleString(), row.voted_count.toLocaleString(), row.turnout_rate + '%']);
      } else if (currentBreakdown === 'course') {
        headers = ['Course','Eligible Students','Voted','Turnout Rate'];
        rows = chartData.voters.course.map(row => [row.course, row.eligible_count.toLocaleString(), row.voted_count.toLocaleString(), row.turnout_rate + '%']);
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
        td.className = 'px-6 py-4 whitespace-nowrap ' + (idx===0?'font-medium text-gray-900':'text-gray-700');
        td.textContent = cell;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }

  // Init
  renderElectionsVsTurnout();
  document.getElementById('dataSeriesSelect')?.addEventListener('change', function(){
    currentDataSeries = this.value;
    if (currentDataSeries === 'elections'){
      document.getElementById('breakdownSelect').value = 'year';
      document.getElementById('breakdownSelect').disabled = true;
      currentBreakdown = 'year';
    } else {
      document.getElementById('breakdownSelect').disabled = false;
    }
    renderElectionsVsTurnout();
  });
  document.getElementById('breakdownSelect')?.addEventListener('change', function(){
    currentBreakdown = this.value;
    renderElectionsVsTurnout();
  });
});
</script>
</body>
</html>