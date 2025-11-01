<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
 $host = 'localhost';
 $db = 'evoting_system';
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

// Valid scopes - now includes FACULTY ASSOCIATION
 $validScopes = ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC', 'FACULTY ASSOCIATION'];

// Verify this is the correct scope for this dashboard
if (!in_array($scope, $validScopes)) {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// Check if this is Faculty Association Admin
 $isFacultyAdmin = ($scope === 'FACULTY ASSOCIATION');

// --- Get available years for dropdown ---
 $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE role = 'voter' AND position = 'academic' ORDER BY year DESC");
 $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
 $currentYear = date('Y');
 $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
 $previousYear = $selectedYear - 1;

// --- Fetch dashboard stats ---

// Total Voters (all colleges if Faculty Association Admin)
if ($isFacultyAdmin) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters
    FROM users
    WHERE role = 'voter' AND position = 'academic'");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters
    FROM users
    WHERE role = 'voter' AND position = 'academic' AND UPPER(TRIM(department)) = ?");
    $stmt->execute([$scope]);
}
 $total_voters = $stmt->fetch()['total_voters'];

// Total Elections (UPDATED - using assigned_admin_id)
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_elections
FROM elections
WHERE assigned_admin_id = ?");
 $stmt->execute([$userId]);
 $total_elections = $stmt->fetch()['total_elections'];

// Ongoing Elections (UPDATED - using assigned_admin_id)
 $stmt = $pdo->prepare("SELECT COUNT(*) as ongoing_elections
FROM elections
WHERE status = 'ongoing' AND assigned_admin_id = ?");
 $stmt->execute([$userId]);
 $ongoing_elections = $stmt->fetch()['ongoing_elections'];

// --- Fetch elections for display (UPDATED - using assigned_admin_id) ---
 $electionStmt = $pdo->prepare("SELECT * FROM elections
WHERE assigned_admin_id = ?
ORDER BY start_datetime DESC");
 $electionStmt->execute([$userId]);
 $elections = $electionStmt->fetchAll();

// --- Get all colleges for dropdown ---
 $allColleges = [];
if ($isFacultyAdmin) {
    $stmt = $pdo->query("SELECT DISTINCT department as college_name FROM users WHERE role = 'voter' AND position = 'academic' ORDER BY college_name");
    $allColleges = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $allColleges = [$scope];
}

// Department abbreviation mapping
 $departmentAbbrevMap = [
    'College of Agriculture, Food, Environment and Natural Resources' => 'CAFENR',
    'College of Engineering and Information Technology' => 'CEIT',
    'College of Arts and Sciences' => 'CAS',
    'College of Veterinary Medicine and Biomedical Sciences' => 'CVMBS',
    'College of Education' => 'CED',
    'College of Economics, Management and Development Studies' => 'CEMDS',
    'College of Sports, Physical Education and Recreation' => 'CSPEAR',
    'College of Criminal Justice' => 'CCJ',
    'College of Nursing' => 'CON',
    'College of Tourism and Hospitality Management' => 'CTHM',
    'College of Medicine' => 'COM',
    'Graduate School and Open Learning College' => 'GS-OLC'
];

// College full name mapping
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

// Department/Course abbreviation mapping
 $deptCourseAbbrevMap = [
    'Department of Animal Science' => 'DAS',
    'Department of Crop Science' => 'DCS',
    'Department of Food Science and Technology' => 'DFST',
    'Department of Forestry and Environmental Science' => 'DFES',
    'Department of Agricultural Economics and Development' => 'DAED',
    'Department of Biological Sciences' => 'DBS',
    'Department of Physical Sciences' => 'DPS',
    'Department of Languages and Mass Communication' => 'DLMC',
    'Department of Social Sciences' => 'DSS',
    'Department of Mathematics and Statistics' => 'DMS',
    'Department of Criminal Justice' => 'DCJ',
    'Department of Economics' => 'DEC',
    'Department of Business and Management' => 'DBM',
    'Department of Development Studies' => 'DDS',
    'Department of Science Education' => 'DSE',
    'Department of Technology and Livelihood Education' => 'DTLE',
    'Department of Curriculum and Instruction' => 'DCI',
    'Department of Human Kinetics' => 'DHK',
    'Department of Civil Engineering' => 'DCE',
    'Department of Computer and Electronics Engineering' => 'DCEE',
    'Department of Industrial Engineering and Technology' => 'DIET',
    'Department of Mechanical and Electronics Engineering' => 'DMEE',
    'Department of Information Technology' => 'DIT',
    'Department of Nursing' => 'DN',
    'Department of Basic Medical Sciences' => 'DBMS',
    'Department of Clinical Sciences' => 'DCS',
    'Department of Physical Education and Recreation' => 'DPER',
    'Department of Veterinary Medicine' => 'DVM',
    'Department of Biomedical Sciences' => 'DBS',
    'Department of Various Graduate Programs' => 'DVGP',
    'General' => 'GEN'
];

// =========================
// ANALYTICS DATA
// =========================

// Get voters distribution by college (for Faculty Association Admin) or by department (for college admin)
if ($isFacultyAdmin) {
    $stmt = $pdo->prepare("SELECT
    department as college_name,
    COUNT(*) as count
    FROM users
    WHERE role = 'voter' AND position = 'academic'
    GROUP BY college_name
    ORDER BY count DESC");
    $stmt->execute();
    $votersByCollege = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT
    COALESCE(NULLIF(department1, ''), department) as department_name,
    COUNT(*) as count
    FROM users
    WHERE role = 'voter' AND position = 'academic' AND UPPER(TRIM(department)) = ?
    GROUP BY department_name
    ORDER BY count DESC");
    $stmt->execute([$scope]);
    $votersByDepartment = $stmt->fetchAll();
}

// =========================
// BAR DATA (RIGHT OF DOUGHNUT)
// Unified shapes for JS:
// - $collegeDepartmentBar: { college_name, department_name, count }
// - $collegeCourseBar    : { college_name, course, count }
// =========================
if ($isFacultyAdmin) {
    $stmt = $pdo->query("SELECT department AS college_name, COALESCE(NULLIF(department1,''),'General') AS department_name, COUNT(*) AS count
                       FROM users WHERE role='voter' AND position='academic'
                       GROUP BY college_name, department_name
                       ORDER BY college_name, count DESC");
    $collegeDepartmentBar = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(department1,''),department) AS department_name, COUNT(*) AS count
                         FROM users WHERE role='voter' AND position='academic' AND UPPER(TRIM(department))=?
                         GROUP BY department_name ORDER BY count DESC");
    $stmt->execute([$scope]);
    $rows = $stmt->fetchAll();
    // inject college_name to unify shape
    $collegeDepartmentBar = array_map(function($r) use ($scope) {
        return [
            'college_name' => $scope,
            'department_name' => $r['department_name'],
            'count' => (int)$r['count']
        ];
    }, $rows);
}

if ($isFacultyAdmin) {
    $stmt = $pdo->query("SELECT department AS college_name, status
                       FROM users
                       WHERE role='voter' AND position='academic' AND status IS NOT NULL AND status<>''");
    $rows = $stmt->fetchAll();
    $agg = [];
    foreach ($rows as $r) {
        $statusName = $r['status'];
        $key  = $r['college_name'].'|'.$statusName;
        if (!isset($agg[$key])) $agg[$key] = ['college_name'=>$r['college_name'],'status'=>$statusName,'count'=>0];
        $agg[$key]['count']++;
    }
    $collegeStatusBar = array_values($agg);
} else {
    $stmt = $pdo->prepare("SELECT status
                         FROM users
                         WHERE role='voter' AND position='academic'
                           AND UPPER(TRIM(department))=? AND status IS NOT NULL AND status<>''");
    $stmt->execute([$scope]);
    $rows = $stmt->fetchAll();
    $agg = [];
    foreach ($rows as $r) {
        $statusName = $r['status'];
        if (!isset($agg[$statusName])) $agg[$statusName] = ['college_name'=>$scope,'status'=>$statusName,'count'=>0];
        $agg[$statusName]['count']++;
    }
    $collegeStatusBar = array_values($agg);
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
    
    // Get total voters as of December 31 of this year
    if ($isFacultyAdmin) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                               FROM users 
                               WHERE role = 'voter' AND position = 'academic' 
                               AND created_at <= ?");
        $stmt->execute([$year . '-12-31 23:59:59']);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                               FROM users 
                               WHERE role = 'voter' AND position = 'academic' AND UPPER(TRIM(department)) = ? 
                               AND created_at <= ?");
        $stmt->execute([$scope, $year . '-12-31 23:59:59']);
    }
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

// --- College Turnout Data (for Faculty Association Admin) ---
 $collegeTurnoutData = [];
if ($isFacultyAdmin) {
    $stmt = $pdo->prepare("
        SELECT
        u.department as college_name,
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
        WHERE u.role = 'voter' AND u.position = 'academic'
        GROUP BY college_name
        ORDER BY college_name
    ");
    $stmt->execute([$userId, $selectedYear]);
    $collegeResults = $stmt->fetchAll();
    
    foreach ($collegeResults as $row) {
        $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
        $collegeTurnoutData[] = [
            'college' => $row['college_name'],
            'eligible_count' => (int)$row['eligible_count'],
            'voted_count' => (int)$row['voted_count'],
            'turnout_rate' => (float)$turnoutRate
        ];
    }
}

// --- Department Turnout Data (for Faculty Association Admin) ---
 $departmentTurnoutData = [];
if ($isFacultyAdmin) {
    $stmt = $pdo->prepare("
        SELECT
        u.department as college_name,
        COALESCE(NULLIF(u.department1, ''), 'General') as department_name,
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
        WHERE u.role = 'voter' AND u.position = 'academic'
        GROUP BY college_name, department_name
        ORDER BY college_name, department_name
    ");
    $stmt->execute([$userId, $selectedYear]);
    $deptResults = $stmt->fetchAll();
    
    foreach ($deptResults as $row) {
        $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
        $departmentTurnoutData[] = [
            'college' => $row['college_name'],
            'department' => $row['department_name'],
            'eligible_count' => (int)$row['eligible_count'],
            'voted_count' => (int)$row['voted_count'],
            'turnout_rate' => (float)$turnoutRate
        ];
    }
}

/* ==========================================================
STATUS TURNOUT DATA (UPDATED)
========================================================== */
 $statusTurnoutData = [];

// First, get all distinct voters who voted in any elections assigned to this admin in this year
 $stmt = $pdo->prepare("
   SELECT DISTINCT v.voter_id
   FROM votes v
   JOIN elections e ON v.election_id = e.election_id
   WHERE e.assigned_admin_id = ? AND YEAR(e.start_datetime) = ?
");
 $stmt->execute([$userId, $selectedYear]);
 $votedIds = array_column($stmt->fetchAll(), 'voter_id');
 $votedSet = array_flip($votedIds);

// Get all status data first without grouping
 $stmt = $pdo->prepare("
   SELECT
   u.user_id,
   u.department as college_name,
   COALESCE(NULLIF(u.department1, ''), 'General') as department_name,
   u.status
   FROM users u
   WHERE u.role = 'voter' AND u.position = 'academic'
   AND u.status IS NOT NULL AND u.status <> ''");
 $stmt->execute();
 $rows = $stmt->fetchAll();

// Group by status names
 $statusGroups = [];
foreach ($rows as $row) {
    $college = $row['college_name'] ?? 'UNKNOWN';
    $department = $row['department_name'];
    $statusName = $row['status'];
    
    $key = $college . '|' . $department . '|' . $statusName;
    
    if (!isset($statusGroups[$key])) {
        $statusGroups[$key] = [
            'college_name' => $college,
            'department_name' => $department,
            'status' => $statusName,
            'eligible_count' => 0,
            'voted_count' => 0
        ];
    }
    $statusGroups[$key]['eligible_count']++;
    if (isset($votedSet[$row['user_id']])) {
        $statusGroups[$key]['voted_count']++;
    }
}

// Convert to array and calculate turnout rates
foreach ($statusGroups as $key => $data) {
    $turnoutRate = ($data['eligible_count'] > 0)
        ? round(($data['voted_count'] / $data['eligible_count']) * 100, 1)
        : 0.0;
    
    $statusTurnoutData[] = [
        'college_name' => $data['college_name'],
        'department_name' => $data['department_name'],
        'status' => $data['status'],
        'eligible_count' => (int)$data['eligible_count'],
        'voted_count' => (int)$data['voted_count'],
        'turnout_rate' => (float)$turnoutRate,
    ];
}

// Sort by eligible count DESC
usort($statusTurnoutData, function($a, $b) {
    return $b['eligible_count'] <=> $a['eligible_count'];
});

// Set page title based on scope
if ($isFacultyAdmin) {
    $pageTitle = "FACULTY ASSOCIATION ADMIN DASHBOARD";
    $pageSubtitle = "Faculty Association - All Colleges";
    $collegeFullName = "All Colleges";
} else {
    $pageTitle = htmlspecialchars($collegeFullNameMap[$scope] ?? $scope) . " ADMIN DASHBOARD";
    $pageSubtitle = htmlspecialchars($collegeFullNameMap[$scope] ?? $scope);
    $collegeFullName = $collegeFullNameMap[$scope] ?? $scope;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - <?= $pageTitle ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    
    /* Color Classes for Different Elements */
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
    
    /* Disabled select styling */
    select:disabled {
      background-color: #f3f4f6;
      color: #9ca3af;
      cursor: not-allowed;
    }
    
    /* Label for disabled select */
    .label-disabled {
      color: #9ca3af;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">
<?php include 'sidebar.php'; ?>
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
  <h1 class="text-2xl font-bold text-white">
    <?= $pageTitle ?>
  </h1>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
  </div>
</header>
<main class="flex-1 pt-20 px-8 ml-64">

  <!-- Statistics Cards (MOVED TO TOP) -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Voters -->
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
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-blue);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-blue-600"><?= $ongoing_elections ?></p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(59, 130, 246, 0.1);">
          <i class="fas fa-clock text-2xl text-blue-600"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Detailed Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">Faculty Association Admin Analytics</h2>
        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
          <div class="flex items-center">
            <label for="detailedBreakdownSelect" class="mr-3 text-sm font-medium text-white">Breakdown by:</label>
            <select id="detailedBreakdownSelect" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <option value="all">All Colleges</option>
              <option value="department">College and Department</option>
              <option value="status">College and Status</option>
            </select>
          </div>
          <div id="detailedCollegeSelector" class="flex items-center">
            <label id="detailedCollegeLabel" for="detailedCollegeSelect" class="mr-3 text-sm font-medium text-white">Select College:</label>
            <select id="detailedCollegeSelect" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <?php foreach ($allColleges as $college): ?>
                <option value="<?= $college ?>" <?= $college == $scope ? 'selected' : '' ?>><?= $college ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>
    
    <div class="p-6">
      <!-- Summary Cards -->
      <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color: var(--cvsu-green-light);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-green-light);">
              <i class="fas fa-user-plus text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm" style="color: var(--cvsu-green);">New This Month</p>
              <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);">
                <?php
                // Calculate new voters this month
                $currentMonthStart = date('Y-m-01');
                $currentMonthEnd = date('Y-m-t');
                
                if ($isFacultyAdmin) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as new_voters 
                                          FROM users 
                                          WHERE role = 'voter' AND position = 'academic'
                                            AND created_at BETWEEN ? AND ?");
                    $stmt->execute([$currentMonthStart, $currentMonthEnd]);
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as new_voters 
                                          FROM users 
                                          WHERE role = 'voter' AND position = 'academic' AND UPPER(TRIM(department)) = ?
                                            AND created_at BETWEEN ? AND ?");
                    $stmt->execute([$scope, $currentMonthStart, $currentMonthEnd]);
                }
                $newVoters = $stmt->fetch()['new_voters'];
                echo number_format($newVoters);
                ?>
              </p>
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
              <p class="text-2xl font-bold text-blue-800"><?= count($isFacultyAdmin ? $votersByCollege : $votersByDepartment) ?></p>
            </div>
          </div>
        </div>

        <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color: #8B5CF6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500">
              <i class="fas fa-id-badge text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-purple-600">Status Types</p>
              <p class="text-2xl font-bold text-purple-800"><?= count($collegeStatusBar) ?></p>
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
                // Calculate growth rate
                $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
                $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
                
                if ($isFacultyAdmin) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_voters 
                                          FROM users 
                                          WHERE role = 'voter' AND position = 'academic'
                                            AND created_at BETWEEN ? AND ?");
                    $stmt->execute([$lastMonthStart, $lastMonthEnd]);
                    $lastMonthVoters = $stmt->fetch()['last_month_voters'];
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_voters 
                                          FROM users 
                                          WHERE role = 'voter' AND position = 'academic' AND UPPER(TRIM(department)) = ?
                                            AND created_at BETWEEN ? AND ?");
                    $stmt->execute([$scope, $lastMonthStart, $lastMonthEnd]);
                    $lastMonthVoters = $stmt->fetch()['last_month_voters'];
                }
                
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
      
      <!-- Charts -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Donut Chart -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <?php if ($isFacultyAdmin): ?>Voters by College<?php else: ?>Voters by Department<?php endif; ?>
          </h3>
          <div class="chart-container">
            <canvas id="donutChart"></canvas>
          </div>
        </div>
        
        <!-- Bar Chart -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05);">
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="detailedTableBody">
              <!-- Table content will be populated by JavaScript -->
            </tbody>
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
      
      <?php
      $currentYearTurnout = $turnoutDataByYear[$selectedYear] ?? null;
      $previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;
      ?>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(99,102,241,0.05); border-color:var(--cvsu-indigo);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-indigo-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-indigo-600"><?= $selectedYear ?> Turnout</p>
              <p class="text-2xl font-bold text-indigo-800"><?= $currentYearTurnout['turnout_rate'] ?? 0 ?>%</p>
            </div>
          </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color:var(--cvsu-purple);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-purple-600"><?= $selectedYear - 1 ?> Turnout</p>
              <p class="text-2xl font-bold text-purple-800"><?= $previousYearTurnout['turnout_rate'] ?? 0 ?>%</p>
            </div>
          </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(16,185,129,0.05); border-color:var(--cvsu-teal);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-teal-500"><i class="fas fa-chart-line text-white text-xl"></i></div>
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
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color:var(--cvsu-blue);">
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Eligible Voters</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters Participated</th>
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
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Elections vs Turnout Rate</h3>
          <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-6">
            <div class="flex items-center">
              <label for="dataSeriesSelect" class="mr-3 text-sm font-medium text-gray-700">Data Series:</label>
              <select id="dataSeriesSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <option value="elections">Elections vs Turnout</option>
                <option value="voters">Voters vs Turnout</option>
              </select>
            </div>
            <div class="flex items-center">
              <label for="breakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
              <select id="breakdownSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <option value="year">Year</option>
                <?php if ($isFacultyAdmin): ?>
                <option value="college">College</option>
                <option value="department">Department</option>
                <?php endif; ?>
                <option value="status">Status</option>
              </select>
            </div>
            <div id="turnoutCollegeSelector" class="flex items-center" style="display: none;">
              <label for="turnoutCollegeSelect" class="mr-3 text-sm font-medium text-gray-700">Select College:</label>
              <select id="turnoutCollegeSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <!-- Options will be populated by JavaScript -->
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

</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Year selectors
  document.getElementById('turnoutYearSelector')?.addEventListener('change', function() {
    window.location.href = window.location.pathname + '?year=' + this.value;
  });
  
  let turnoutTrendChartInstance = null;
  let electionsVsTurnoutChartInstance = null;
  
  // Data from PHP
  const turnoutYears = <?= json_encode(array_keys($turnoutDataByYear)) ?>;
  const turnoutRates = <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?>;
  const statusTurnoutData = <?= json_encode($statusTurnoutData) ?>;
  
  // College and department data for filtering
  const collegeDepartmentData = <?= json_encode($collegeDepartmentBar) ?>;
  const collegeStatusData = <?= json_encode($collegeStatusBar) ?>;
  
  // Department abbreviation map
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
  
  // College name map
  const collegeFullNameMap = {
    'CAFENR': 'College of Agriculture, Food, Environment and Natural Resources',
    'CEIT': 'College of Engineering and Information Technology',
    'CAS': 'College of Arts and Sciences',
    'CVMBS': 'College of Veterinary Medicine and Biomedical Sciences',
    'CED': 'College of Education',
    'CEMDS': 'College of Economics, Management and Development Studies',
    'CSPEAR': 'College of Sports, Physical Education and Recreation',
    'CCJ': 'College of Criminal Justice',
    'CON': 'College of Nursing',
    'CTHM': 'College of Tourism and Hospitality Management',
    'COM': 'College of Medicine',
    'GS-OLC': 'Graduate School and Open Learning College',
  };
  
  // Department/Status abbreviation map
  const deptStatusAbbrevMap = {
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
    'Department of Economics': 'DEC',
    'Department of Business and Management': 'DBM',
    'Department of Development Studies': 'DDS',
    'Department of Science Education': 'DSE',
    'Department of Technology and Livelihood Education': 'DTLE',
    'Department of Curriculum and Instruction': 'DCI',
    'Department of Human Kinetics': 'DHK',
    'Department of Civil Engineering': 'DCE',
    'Department of Computer and Electronics Engineering': 'DCEE',
    'Department of Industrial Engineering and Technology': 'DIET',
    'Department of Mechanical and Electronics Engineering': 'DMEE',
    'Department of Information Technology': 'DIT',
    'Department of Nursing': 'DN',
    'Department of Basic Medical Sciences': 'DBMS',
    'Department of Clinical Sciences': 'DCS',
    'Department of Physical Education and Recreation': 'DPER',
    'Department of Veterinary Medicine': 'DVM',
    'Department of Biomedical Sciences': 'DBS',
    'Department of Various Graduate Programs': 'DVGP',
    'Full-time': 'FT',
    'Part-time': 'PT',
    'Regular': 'REG',
    'Probationary': 'PROB',
    'Contractual': 'CON',
    'General': 'GEN'
  };
  
  function getFullCollegeNameJS(code){ 
    return collegeFullNameMap[code] || code; 
  }
  
  function getDepartmentAbbrevJS(name){ 
    // First check if it's a college name
    if (departmentAbbrevMap[name]) {
      return departmentAbbrevMap[name];
    }
    
    // Then check if it's a department/status name
    if (deptStatusAbbrevMap[name]) {
      return deptStatusAbbrevMap[name];
    }
    
    // Words to exclude from abbreviations
    const excludeWords = ['and', 'of', 'the', 'in', 'for', 'at', 'by'];
    
    // For colleges that start with "College of " but not in the map
    if (name.startsWith("College of ")) {
      // Try to match by key in the map
      for (let [fullName, abbrev] of Object.entries(departmentAbbrevMap)) {
        if (name === fullName) {
          return abbrev;
        }
      }
      // If not found, generate abbreviation from the name
      // Remove "College of " and take first letters of remaining words, excluding common words
      let rest = name.substring(13).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return abbr;
    }
    
    // For departments that start with "Department of "
    if (name.startsWith("Department of ")) {
      let rest = name.substring(13).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return "D" + abbr;
    }
    
    // Handle special cases not covered above
    if (name === "General" || name === "Full-time" || name === "Part-time" || 
        name === "Regular" || name === "Probationary" || name === "Contractual") {
      return deptStatusAbbrevMap[name] || name;
    }
    
    // Default: return the full name
    return name;
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
      <?php if ($isFacultyAdmin): ?>
      college: <?= json_encode($collegeTurnoutData) ?>,
      department: <?= json_encode($departmentTurnoutData) ?>,
      <?php endif; ?>
      status: <?= json_encode($statusTurnoutData) ?>
    }
  };
  let currentDataSeries = 'elections';
  let currentBreakdown = 'year';
  
  // Get DOM elements
  const dataSeriesSelect = document.getElementById('dataSeriesSelect');
  const breakdownSelect = document.getElementById('breakdownSelect');
  const turnoutCollegeSelector = document.getElementById('turnoutCollegeSelector');
  const turnoutCollegeSelect = document.getElementById('turnoutCollegeSelect');
  
  // Function to update college selector options
  function updateCollegeSelectorOptions(breakdownType) {
    const select = turnoutCollegeSelect;
    select.innerHTML = '';
    
    if (breakdownType === 'college') {
      // Add "All Colleges" option first
      const allOption = document.createElement('option');
      allOption.value = 'all';
      allOption.textContent = 'All Colleges';
      select.appendChild(allOption);
      
      // Add individual colleges
      <?php if ($isFacultyAdmin): ?>
      const colleges = <?= json_encode($allColleges) ?>;
      colleges.forEach(college => {
        const option = document.createElement('option');
        option.value = college;
        option.textContent = college;
        select.appendChild(option);
      });
      <?php else: ?>
      const option = document.createElement('option');
      option.value = '<?= $scope ?>';
      option.textContent = '<?= $scope ?>';
      select.appendChild(option);
      <?php endif; ?>
    } else {
      // For department and status, only show specific colleges
      <?php if ($isFacultyAdmin): ?>
      const colleges = <?= json_encode($allColleges) ?>;
      colleges.forEach(college => {
        const option = document.createElement('option');
        option.value = college;
        option.textContent = college;
        select.appendChild(option);
      });
      <?php else: ?>
      const option = document.createElement('option');
      option.value = '<?= $scope ?>';
      option.textContent = '<?= $scope ?>';
      select.appendChild(option);
      <?php endif; ?>
    }
  }
  
  // Function to show/hide college selector based on breakdown
  function updateCollegeSelectorVisibility() {
    const breakdownValue = breakdownSelect.value;
    
    if (breakdownValue === 'college' || breakdownValue === 'department' || breakdownValue === 'status') {
      turnoutCollegeSelector.style.display = 'flex';
      updateCollegeSelectorOptions(breakdownValue);
    } else {
      turnoutCollegeSelector.style.display = 'none';
    }
  }
  
  // Function to handle data series change
  function handleDataSeriesChange() {
    currentDataSeries = dataSeriesSelect.value;
    
    if (currentDataSeries === 'elections') {
      // For elections, set breakdown to year and disable the breakdown select
      breakdownSelect.value = 'year';
      breakdownSelect.disabled = true;
      currentBreakdown = 'year';
      // Hide the college selector
      turnoutCollegeSelector.style.display = 'none';
    } else {
      // For voters, enable the breakdown select and set to year
      breakdownSelect.disabled = false;
      breakdownSelect.value = 'year';
      currentBreakdown = 'year';
      // Hide the college selector for year
      turnoutCollegeSelector.style.display = 'none';
    }
    
    renderElectionsVsTurnout();
  }
  
  // Function to handle breakdown change
  function handleBreakdownChange() {
    currentBreakdown = breakdownSelect.value;
    
    // Update college selector visibility
    updateCollegeSelectorVisibility();
    
    renderElectionsVsTurnout();
  }
  
  // Function to render the elections vs turnout chart
  function renderElectionsVsTurnout() {
    const ctx = document.getElementById('electionsVsTurnoutChart');
    if (!ctx) return;
    if (electionsVsTurnoutChartInstance) electionsVsTurnoutChartInstance.destroy();
    
    let data, options;
    const selectedCollege = turnoutCollegeSelect ? turnoutCollegeSelect.value : 'CEIT';
    
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
    } else {
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.voters.year.labels,
          datasets:[
            { label:'Eligible Voters', data: chartData.voters.year.eligibleCounts, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: chartData.voters.year.turnoutRates, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Eligible Voters vs Turnout Rate by Year', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      } 
      <?php if ($isFacultyAdmin): ?>
      else if (currentBreakdown === 'college') {
        let filteredData, labels, eligible, tr;
        
        if (selectedCollege === 'all') {
          // Show all colleges
          filteredData = chartData.voters.college;
          labels = filteredData.map(item => getDepartmentAbbrevJS(collegeFullNameMap[item.college] || item.college));
          eligible = filteredData.map(item => item.eligible_count);
          tr = filteredData.map(item => item.turnout_rate);
        } else {
          // Show specific college
          filteredData = chartData.voters.college.filter(item => item.college === selectedCollege);
          labels = [getDepartmentAbbrevJS(collegeFullNameMap[selectedCollege] || selectedCollege)];
          eligible = filteredData.map(item => item.eligible_count);
          tr = filteredData.map(item => item.turnout_rate);
        }
        
        data = {
          labels,
          datasets:[
            { label:'Eligible Voters', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:`Eligible Voters vs Turnout Rate by College (${selectedCollege === 'all' ? 'All Colleges' : selectedCollege})`, font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      } else if (currentBreakdown === 'department') {
        // Filter data by selected college
        const filteredData = chartData.voters.department.filter(item => item.college === selectedCollege);
        const labels = filteredData.map(item => getDepartmentAbbrevJS(item.department));
        const eligible = filteredData.map(item => item.eligible_count);
        const tr = filteredData.map(item => item.turnout_rate);
        
        data = {
          labels,
          datasets:[
            { label:'Eligible Voters', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:`Eligible Voters vs Turnout Rate by Department (${selectedCollege})`, font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      }
      <?php endif; ?>
      else if (currentBreakdown === 'status') {
        // Filter data by selected college
        const filteredData = chartData.voters.status.filter(item => item.college_name === selectedCollege);
        // Use full status names instead of abbreviations
        const labels = filteredData.map(item => item.status);
        const eligible = filteredData.map(item => item.eligible_count);
        const tr = filteredData.map(item => item.turnout_rate);
        
        data = {
          labels,
          datasets:[
            { label:'Eligible Voters', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:`Eligible Voters vs Turnout Rate by Status (${selectedCollege})`, font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
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
    const selectedCollege = turnoutCollegeSelect ? turnoutCollegeSelect.value : 'CEIT';
    
    if (currentDataSeries === 'elections') {
      headers = ['Year','Number of Elections','Turnout Rate'];
      rows = chartData.elections.year.labels.map((label,i)=> [label, chartData.elections.year.electionCounts[i].toLocaleString(), chartData.elections.year.turnoutRates[i] + '%']);
    } else {
      if (currentBreakdown === 'year') {
        headers = ['Year','Eligible Voters','Voted','Turnout Rate'];
        // Calculate voted count from eligible count and turnout rate
        rows = chartData.voters.year.labels.map((label,i)=> {
          const eligible = chartData.voters.year.eligibleCounts[i];
          const turnoutRate = chartData.voters.year.turnoutRates[i];
          const voted = Math.round(eligible * turnoutRate / 100);
          return [label, eligible.toLocaleString(), voted.toLocaleString(), turnoutRate + '%'];
        });
      } 
      <?php if ($isFacultyAdmin): ?>
      else if (currentBreakdown === 'college') {
        if (selectedCollege === 'all') {
          headers = ['College','Eligible Voters','Voted','Turnout Rate'];
          rows = chartData.voters.college.map(row => [
            collegeFullNameMap[row.college] || row.college, 
            row.eligible_count.toLocaleString(), 
            row.voted_count.toLocaleString(), 
            row.turnout_rate + '%'
          ]);
        } else {
          headers = ['College','Eligible Voters','Voted','Turnout Rate'];
          const filteredData = chartData.voters.college.filter(item => item.college === selectedCollege);
          rows = filteredData.map(row => [
            collegeFullNameMap[row.college] || row.college, 
            row.eligible_count.toLocaleString(), 
            row.voted_count.toLocaleString(), 
            row.turnout_rate + '%'
          ]);
        }
      } else if (currentBreakdown === 'department') {
        // Filter data by selected college
        const filteredData = chartData.voters.department.filter(item => item.college === selectedCollege);
        headers = ['Department','Eligible Voters','Voted','Turnout Rate'];
        rows = filteredData.map(row => [
            row.department, 
            row.eligible_count.toLocaleString(), 
            row.voted_count.toLocaleString(), 
            row.turnout_rate + '%'
        ]);
      }
      <?php endif; ?>
      else if (currentBreakdown === 'status') {
        // Filter data by selected college
        const filteredData = chartData.voters.status.filter(item => item.college_name === selectedCollege);
        headers = ['Status','Eligible Voters','Voted','Turnout Rate'];
        rows = filteredData.map(row => [
            row.status, 
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
        td.className = 'px-6 py-4 whitespace-nowrap ' + (idx===0?'font-medium text-gray-900':'text-gray-700');
        td.textContent = cell;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }
  
  // Add event listeners
  dataSeriesSelect.addEventListener('change', handleDataSeriesChange);
  breakdownSelect.addEventListener('change', handleBreakdownChange);
  turnoutCollegeSelect?.addEventListener('change', renderElectionsVsTurnout);
  
  // Initialize the page with the correct state
  handleDataSeriesChange();

  // === NEW: Detailed Analytics Section ===
  
  // Data for donut chart
  const donutData = {
    labels: <?= json_encode($isFacultyAdmin ? array_column($votersByCollege, 'college_name') : array_column($votersByDepartment, 'department_name')) ?>,
    counts: <?= json_encode($isFacultyAdmin ? array_column($votersByCollege, 'count') : array_column($votersByDepartment, 'count')) ?>
  };

  // Data for bar chart
  const barData = {
    department: <?= json_encode($collegeDepartmentBar) ?>,
    status: <?= json_encode($collegeStatusBar) ?>
  };

  // Create donut chart
  const donutCtx = document.getElementById('donutChart');
  if (donutCtx) {
    const donutChart = new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: donutData.labels.map(label => getDepartmentAbbrevJS(label)),
        datasets: [{
          data: donutData.counts,
          backgroundColor: [
            '#1E6F46', '#37A66B', '#FFD166', '#154734', '#2D5F3F', 
            '#4A7C59', '#5A8F6A', '#6A9F7A', '#7AAFAA', '#8ABFBA'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              font: { size: 12 },
              padding: 15
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.raw || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((value / total) * 100);
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        },
        cutout: '50%'
      }
    });
  }

  // Create bar chart
  const barCtx = document.getElementById('barChart');
  if (barCtx) {
    let barChart;

    // Function to get bar chart data based on selected college and breakdown type
    function getBarChartData(breakdownType, college) {
      let data, labels;
      
      if (breakdownType === 'all') {
        // For "all" breakdown, use the donut data (all colleges)
        data = donutData.labels.map((label, index) => ({
          college_name: label,
          count: donutData.counts[index]
        }));
        labels = data.map(item => getDepartmentAbbrevJS(item.college_name));
      } else {
        // Filter the data by college
        data = barData[breakdownType].filter(item => item.college_name === college);
        
        if (breakdownType === 'status') {
          // For status breakdown, use full status names
          labels = data.map(item => item.status);
        } else if (breakdownType === 'department') {
          // For department breakdown, use abbreviations
          labels = data.map(item => getDepartmentAbbrevJS(item.department_name));
        }
      }
      
      const counts = data.map(item => item.count);
      
      return { labels, counts };
    }

    // Initial bar chart
    const initialCollege = document.getElementById('detailedCollegeSelect').value;
    const initialBreakdown = 'all';
    const initialBarData = getBarChartData(initialBreakdown, initialCollege);

    barChart = new Chart(barCtx, {
      type: 'bar',
      data: {
        labels: initialBarData.labels,
        datasets: [{
          label: 'Number of Voters',
          data: initialBarData.counts,
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
          legend: {
            display: false
          },
          title: {
            display: true,
            text: initialBreakdown === 'all' ? 'All Colleges' : (initialBreakdown === 'department' ? 'Departments' : 'Status'),
            font: {
              size: 16,
              weight: 'bold'
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Number of Voters'
            }
          },
          x: {
            title: {
              display: true,
              text: initialBreakdown === 'all' ? 'College' : (initialBreakdown === 'department' ? 'Department' : 'Status')
            },
            ticks: {
              maxRotation: 0,
              autoSkip: false
            }
          }
        }
      }
    });

    // Event listeners for detailed analytics
    const detailedBreakdownSelect = document.getElementById('detailedBreakdownSelect');
    const detailedCollegeSelector = document.getElementById('detailedCollegeSelector');
    const detailedCollegeSelect = document.getElementById('detailedCollegeSelect');
    const detailedCollegeLabel = document.getElementById('detailedCollegeLabel');

    // Function to handle breakdown change
    function handleDetailedBreakdownChange() {
      const value = detailedBreakdownSelect.value;
      
      // Enable/disable the college selector based on breakdown type
      if (value === 'department' || value === 'status') {
        detailedCollegeSelect.disabled = false;
        detailedCollegeLabel.classList.remove('label-disabled');
      } else {
        detailedCollegeSelect.disabled = true;
        detailedCollegeLabel.classList.add('label-disabled');
      }
      
      updateDetailedAnalytics();
    }

    // Show/hide college selector based on breakdown selection
    detailedBreakdownSelect.addEventListener('change', handleDetailedBreakdownChange);

    // Update chart when college selection changes
    detailedCollegeSelect.addEventListener('change', function() {
      updateDetailedAnalytics();
    });

    function updateDetailedAnalytics() {
      const breakdownType = detailedBreakdownSelect.value;
      const college = detailedCollegeSelect.value;
      
      // Update bar chart
      const barData = getBarChartData(breakdownType, college);
      
      barChart.data.labels = barData.labels;
      barChart.data.datasets[0].data = barData.counts;
      barChart.options.plugins.title.text = breakdownType === 'all' ? 'All Colleges' : (breakdownType === 'department' ? 'Departments' : 'Status');
      barChart.options.scales.x.title.text = breakdownType === 'all' ? 'College' : (breakdownType === 'department' ? 'Department' : 'Status');
      barChart.update();
      
      // Update detailed table
      updateDetailedTable(breakdownType, college);
    }

    function updateDetailedTable(breakdownType, college) {
      const tableHeader = document.getElementById('detailedTableHeader');
      const tableBody = document.getElementById('detailedTableBody');
      
      // Clear existing content
      tableHeader.innerHTML = '';
      tableBody.innerHTML = '';
      
      // Set headers based on breakdown type
      if (breakdownType === 'all') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        
        // Add all colleges data
        <?php if ($isFacultyAdmin): ?>
        const allCollegesData = <?= json_encode($votersByCollege) ?>;
        allCollegesData.forEach(item => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-gray-50';
          row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${collegeFullNameMap[item.college_name] || item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(row);
        });
        <?php else: ?>
        const allDepartmentsData = <?= json_encode($votersByDepartment) ?>;
        allDepartmentsData.forEach(item => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-gray-50';
          row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${item.department_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(row);
        });
        <?php endif; ?>
      } else if (breakdownType === 'department') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        
        // Add department data
        const departmentData = barData.department.filter(item => item.college_name === college);
        departmentData.forEach(item => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-gray-50';
          row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${collegeFullNameMap[item.college_name] || item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.department_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(row);
        });
      } else if (breakdownType === 'status') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        
        // Add status data - using full status names
        const statusData = barData.status.filter(item => item.college_name === college);
        statusData.forEach(item => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-gray-50';
          row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${collegeFullNameMap[item.college_name] || item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.status}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(row);
        });
      }
    }

    // Initialize with default values
    // This is the key fix - call handleDetailedBreakdownChange immediately to set initial state
    handleDetailedBreakdownChange();
    
    // If the user is a college admin, disable the college selector
    <?php if (!$isFacultyAdmin): ?>
      detailedCollegeSelect.disabled = true;
      detailedCollegeLabel.classList.add('label-disabled');
    <?php endif; ?>
  }
});
</script>

</body>
</html>