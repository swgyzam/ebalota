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

// Get user info including scope
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $userInfo = $stmt->fetch();

 $role = $userInfo['role'] ?? '';
 $scope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

// Verify this is the correct scope for this dashboard
if ($scope !== 'NON-ACADEMIC') {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// --- Get available years for dropdown ---
 $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE role = 'voter' AND position = 'non-academic' ORDER BY year DESC");
 $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
 $currentYear = date('Y');
 $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
 $previousYear = $selectedYear - 1;

// --- Fetch dashboard stats ---

// Total Non-Academic Voters
 $stmt = $pdo->query("SELECT COUNT(*) as total_voters 
                     FROM users 
                     WHERE role = 'voter' AND position = 'non-academic'");
 $total_voters = $stmt->fetch()['total_voters'];

// Total Elections (for non-academic admin)
 $stmt = $pdo->query("SELECT COUNT(*) as total_elections FROM elections");
 $total_elections = $stmt->fetch()['total_elections'];

// Ongoing Elections
 $stmt = $pdo->query("SELECT COUNT(*) as ongoing_elections 
                     FROM elections 
                     WHERE status = 'ongoing'");
 $ongoing_elections = $stmt->fetch()['ongoing_elections'];

// --- Fetch elections for display ---
 $electionStmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
 $elections = $electionStmt->fetchAll();

// --- Fetch Non-Academic Analytics Data ---

// Get voters distribution by department
 $stmt = $pdo->query("SELECT 
                        department,
                        COUNT(*) as count
                     FROM users 
                     WHERE role = 'voter' AND position = 'non-academic'
                     GROUP BY department
                     ORDER BY count DESC");
 $votersByDepartment = $stmt->fetchAll();

// Get status distribution
 $stmt = $pdo->query("SELECT 
                        status,
                        COUNT(*) as count
                     FROM users 
                     WHERE role = 'voter' AND position = 'non-academic'
                       AND status IS NOT NULL AND status != ''
                     GROUP BY status
                     ORDER BY count DESC");
 $byStatus = $stmt->fetchAll();

// Define date ranges for current and previous month
 $currentMonthStart = date('Y-m-01');
 $currentMonthEnd = date('Y-m-t');
 $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
 $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

// Get new voters this month
 $stmt = $pdo->prepare("SELECT COUNT(*) as new_voters 
                      FROM users 
                      WHERE role = 'voter' AND position = 'non-academic'
                        AND created_at BETWEEN ? AND ?");
 $stmt->execute([$currentMonthStart, $currentMonthEnd]);
 $newVoters = $stmt->fetch()['new_voters'];

// Get voters count for last month
 $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_voters 
                      FROM users 
                      WHERE role = 'voter' AND position = 'non-academic'
                        AND created_at BETWEEN ? AND ?");
 $stmt->execute([$lastMonthStart, $lastMonthEnd]);
 $result = $stmt->fetch();
 $lastMonthVoters = $result['last_month_voters'] ?? 0;

// Calculate growth rate for summary card
if ($lastMonthVoters > 0) {
    $growthRate = round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1);
} else {
    $growthRate = 0;
}

// --- Fetch Historical Growth Rate Data (Last 12 Months) ---
 $historicalData = [];
 $currentDate = new DateTime('first day of this month');

for ($i = 0; $i < 12; $i++) {
    // Clone the current date to avoid modifying it
    $monthDate = clone $currentDate;
    
    // Get the exact start and end dates for this month
    $monthStart = $monthDate->format('Y-m-01');
    $monthEnd = $monthDate->format('Y-m-t');
    $monthLabel = $monthDate->format('M Y');
    
    // Get voters count for this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as count 
                           FROM users 
                           WHERE role = 'voter' AND position = 'non-academic'
                             AND created_at BETWEEN ? AND ?");
    $stmt->execute([$monthStart, $monthEnd]);
    $currentCount = $stmt->fetch()['count'];
    
    // Get voters count for the previous month
    $prevMonthDate = clone $monthDate;
    $prevMonthDate->modify('-1 month');
    $prevMonthStart = $prevMonthDate->format('Y-m-01');
    $prevMonthEnd = $prevMonthDate->format('Y-m-t');
    $prevMonthLabel = $prevMonthDate->format('M Y');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count 
                           FROM users 
                           WHERE role = 'voter' AND position = 'non-academic'
                             AND created_at BETWEEN ? AND ?");
    $stmt->execute([$prevMonthStart, $prevMonthEnd]);
    $prevCount = $stmt->fetch()['count'];
    
    // Calculate growth rate
    if ($prevCount > 0) {
        $growthRateMonth = round((($currentCount - $prevCount) / $prevCount) * 100, 1);
    } else {
        $growthRateMonth = 0;
    }
    
    $historicalData[] = [
        'month' => $monthLabel,
        'current' => $currentCount,
        'previous' => $prevCount,
        'previous_month_label' => $prevMonthLabel,
        'growth' => $growthRateMonth
    ];
    
    // Move to previous month for next iteration
    $currentDate->modify('-1 month');
}

// Extract arrays for charts (already in correct order - most recent first)
 $monthLabels = array_column($historicalData, 'month');
 $voterCounts = array_column($historicalData, 'current');
 $historicalGrowth = array_column($historicalData, 'growth');

// For the table, reverse the order to show chronological (oldest first)
 $tableData = array_reverse($historicalData);

// --- Fetch Year-over-Year Cumulative Comparison Data ---
 $yearlyData = [];
 $cumulativeSelected = 0;
 $cumulativePrevious = 0;

// Check if previous year data exists in the database
 $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'voter' AND position = 'non-academic' AND YEAR(created_at) = ?");
 $stmt->execute([$previousYear]);
 $previousYearHasData = $stmt->fetch()['count'] > 0;

// Get monthly data for selected year and calculate cumulative
for ($month = 1; $month <= 12; $month++) {
    $monthStart = date("$selectedYear-m-01", mktime(0, 0, 0, $month, 1));
    $monthEnd = date("$selectedYear-m-t", mktime(0, 0, 0, $month, 1));
    $monthLabel = date('M', mktime(0, 0, 0, $month, 1));
    
    // Get voters count for selected year month
    $stmt = $pdo->prepare("SELECT COUNT(*) as count 
                           FROM users 
                           WHERE role = 'voter' AND position = 'non-academic'
                             AND created_at BETWEEN ? AND ?");
    $stmt->execute([$monthStart, $monthEnd]);
    $selectedYearCount = $stmt->fetch()['count'];
    
    // Get voters count for previous year same month
    $prevMonthStart = date("$previousYear-m-01", mktime(0, 0, 0, $month, 1));
    $prevMonthEnd = date("$previousYear-m-t", mktime(0, 0, 0, $month, 1));
    
    if ($previousYearHasData) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count 
                               FROM users 
                               WHERE role = 'voter' AND position = 'non-academic'
                                 AND created_at BETWEEN ? AND ?");
        $stmt->execute([$prevMonthStart, $prevMonthEnd]);
        $previousYearCount = $stmt->fetch()['count'];
    } else {
        // Set to 0 if no data exists for previous year
        $previousYearCount = 0;
    }
    
    // Calculate cumulative totals
    $cumulativeSelected += $selectedYearCount;
    $cumulativePrevious += $previousYearCount;
    
    // Calculate year-over-year growth for cumulative
    if ($cumulativePrevious > 0) {
        $yearOverYearGrowth = round((($cumulativeSelected - $cumulativePrevious) / $cumulativePrevious) * 100, 1);
    } else {
        // Set to 0 when previous year had 0 voters
        $yearOverYearGrowth = 0;
    }
    
    $yearlyData[] = [
        'month' => $monthLabel,
        'selected_year' => $cumulativeSelected,
        'previous_year' => $cumulativePrevious,
        'growth' => $yearOverYearGrowth,
        'new_selected' => $selectedYearCount,
        'new_previous' => $previousYearCount
    ];
}

// Calculate yearly totals
 $selectedYearTotal = $cumulativeSelected;
 $previousYearTotal = $cumulativePrevious;

// Calculate yearly growth rate
if ($previousYearTotal > 0) {
    $yearlyGrowthRate = round((($selectedYearTotal - $previousYearTotal) / $previousYearTotal) * 100, 1);
} else {
    // Set to 0 when previous year had 0 voters
    $yearlyGrowthRate = 0;
}

// --- Fetch Voter Turnout Analytics Data ---
 $turnoutDataByYear = [];

// Get all years that have non-academic elections
 $stmt = $pdo->query("SELECT DISTINCT YEAR(start_datetime) as year FROM elections WHERE target_position = 'non-academic' ORDER BY year DESC");
 $turnoutYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($turnoutYears as $year) {
    // Get distinct voters who voted in any non-academic elections in this year
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) as total_voted 
                           FROM votes v 
                           JOIN elections e ON v.election_id = e.election_id 
                           WHERE e.target_position = 'non-academic' 
                           AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$year]);
    $totalVoted = $stmt->fetch()['total_voted'];

    // Get total non-academic voters as of December 31 of this year
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                           FROM users 
                           WHERE role = 'voter' AND position = 'non-academic' 
                           AND created_at <= ?");
    $stmt->execute([$year . '-12-31 23:59:59']);
    $totalEligible = $stmt->fetch()['total_eligible'];

    // Calculate turnout rate
    $turnoutRate = ($totalEligible > 0) ? round(($totalVoted / $totalEligible) * 100, 1) : 0;

    // Also get the number of elections in this year
    $stmt = $pdo->prepare("SELECT COUNT(*) as election_count 
                           FROM elections 
                           WHERE YEAR(start_datetime) = ?");
    $stmt->execute([$year]);
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
sort($years); // sort in ascending order
 $previousYear = null;
foreach ($years as $year) {
    if ($previousYear !== null) {
        $prevTurnout = $turnoutDataByYear[$previousYear]['turnout_rate'];
        $currentTurnout = $turnoutDataByYear[$year]['turnout_rate'];
        
        if ($prevTurnout > 0) {
            $growthRate = round((($currentTurnout - $prevTurnout) / $prevTurnout) * 100, 1);
        } else {
            // Set to 0 when previous year had 0 turnout
            $growthRate = 0;
        }
        
        $turnoutDataByYear[$year]['growth_rate'] = $growthRate;
    } else {
        $turnoutDataByYear[$year]['growth_rate'] = 0;
    }
    $previousYear = $year;
}

// Set current and previous year turnout data for summary cards based on selected year
// Get selected year data
 $currentYearTurnout = isset($turnoutDataByYear[$selectedYear]) ? $turnoutDataByYear[$selectedYear] : null;

// Get previous year data (selected year - 1)
 $previousYearTurnout = isset($turnoutDataByYear[$selectedYear - 1]) ? $turnoutDataByYear[$selectedYear - 1] : null;

// --- Compute department turnout data for the selected year ---
 $departmentTurnoutData = [];
 $stmt = $pdo->prepare("
   SELECT 
       u.department,
       COUNT(DISTINCT u.user_id) as eligible_count,
       COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
   FROM users u
   LEFT JOIN (
       SELECT DISTINCT voter_id 
       FROM votes 
       WHERE election_id IN (
           SELECT election_id FROM elections 
           WHERE target_position = 'non-academic' 
           AND YEAR(start_datetime) = ?
       )
   ) v ON u.user_id = v.voter_id
   WHERE u.role = 'voter' AND u.position = 'non-academic'
   GROUP BY u.department
   ORDER BY u.department
");
 $stmt->execute([$selectedYear]);
 $deptResults = $stmt->fetchAll();

foreach ($deptResults as $row) {
    $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
    $departmentTurnoutData[] = [
        'department' => $row['department'],
        'eligible_count' => (int)$row['eligible_count'],
        'voted_count' => (int)$row['voted_count'],
        'turnout_rate' => (float)$turnoutRate
    ];
}

// --- Compute status turnout data for the selected year ---
 $statusTurnoutData = [];
 $stmt = $pdo->prepare("
   SELECT 
       u.status,
       COUNT(DISTINCT u.user_id) as eligible_count,
       COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
   FROM users u
   LEFT JOIN (
       SELECT DISTINCT voter_id 
       FROM votes 
       WHERE election_id IN (
           SELECT election_id FROM elections 
           WHERE target_position = 'non-academic' 
           AND YEAR(start_datetime) = ?
       )
   ) v ON u.user_id = v.voter_id
   WHERE u.role = 'voter' AND u.position = 'non-academic'
   GROUP BY u.status
   ORDER BY u.status
");
 $stmt->execute([$selectedYear]);
 $statusResults = $stmt->fetchAll();

foreach ($statusResults as $row) {
    $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
    $statusTurnoutData[] = [
        'status' => $row['status'],
        'eligible_count' => (int)$row['eligible_count'],
        'voted_count' => (int)$row['voted_count'],
        'turnout_rate' => (float)$turnoutRate
    ];
}

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
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">

<?php include 'sidebar.php'; ?>

<!-- Top Bar -->
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color: var(--cvsu-green-dark);">
  <div class="flex items-center space-x-4">
    <h1 class="text-2xl font-bold text-white">
      NON-ACADEMIC ADMIN DASHBOARD
    </h1>
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
    
    <!-- Growth Rate Analytics Section -->
    <div class="border-t p-6">
      <h3 class="text-xl font-semibold text-gray-800 mb-6">Voter Growth Analytics</h3>
      
      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Growth Rate Trend Chart -->
        <div class="p-4 rounded-lg" style="background-color: rgba(245, 158, 11, 0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Growth Rate Trend (Last 12 Months)</h3>
          <div class="chart-container">
            <canvas id="growthRateChart"></canvas>
          </div>
        </div>
        
        <!-- Voter Registration Trend -->
        <div class="p-4 rounded-lg" style="background-color: rgba(59, 130, 246, 0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Voter Registration Trend</h3>
          <div class="chart-container">
            <canvas id="registrationTrendChart"></canvas>
          </div>
        </div>
      </div>
      
      <!-- Detailed Growth Rate Breakdown -->
      <div class="mt-8">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">Monthly Growth Rate Breakdown</h4>
        
        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="p-4 rounded-lg border" style="background-color: rgba(59, 130, 246, 0.05); border-color: #3B82F6;">
            <div class="flex items-center">
              <div class="p-3 rounded-lg mr-4 bg-blue-500">
                <i class="fas fa-chart-line text-white text-xl"></i>
              </div>
              <div>
                <p class="text-sm text-blue-600">Average Growth</p>
                <p class="text-2xl font-bold text-blue-800">
                  <?= round(array_sum($historicalGrowth) / count($historicalGrowth), 1) ?>%
                </p>
              </div>
            </div>
          </div>
          
          <div class="p-4 rounded-lg border" style="background-color: rgba(16, 185, 129, 0.05); border-color: #10B981;">
            <div class="flex items-center">
              <div class="p-3 rounded-lg mr-4 bg-green-500">
                <i class="fas fa-arrow-up text-white text-xl"></i>
              </div>
              <div>
                <p class="text-sm text-green-600">Highest Growth</p>
                <p class="text-2xl font-bold text-green-800">
                  <?= max($historicalGrowth) ?>%
                </p>
              </div>
            </div>
          </div>
          
          <div class="p-4 rounded-lg border" style="background-color: rgba(239, 68, 68, 0.05); border-color: #EF4444;">
            <div class="flex items-center">
              <div class="p-3 rounded-lg mr-4 bg-red-500">
                <i class="fas fa-arrow-down text-white text-xl"></i>
              </div>
              <div>
                <p class="text-sm text-red-600">Lowest Growth</p>
                <p class="text-2xl font-bold text-red-800">
                  <?= min($historicalGrowth) ?>%
                </p>
              </div>
            </div>
          </div>
          
          <div class="p-4 rounded-lg border" style="background-color: rgba(139, 92, 246, 0.05); border-color: #8B5CF6;">
            <div class="flex items-center">
              <div class="p-3 rounded-lg mr-4 bg-purple-500">
                <i class="fas fa-calendar-check text-white text-xl"></i>
              </div>
              <div>
                <p class="text-sm text-purple-600">Positive Months</p>
                <p class="text-2xl font-bold text-purple-800">
                  <?= count(array_filter($historicalGrowth, function($rate) { return $rate > 0; })) ?>
                </p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Monthly Growth Rate Table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Voters</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Previous Month</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Growth Rate</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($tableData as $data): 
                  $isPositive = $data['growth'] > 0;
                  $trendIcon = $isPositive ? 'fa-arrow-up' : ($data['growth'] < 0 ? 'fa-arrow-down' : 'fa-minus');
                  $trendColor = $isPositive ? 'text-green-600' : ($data['growth'] < 0 ? 'text-red-600' : 'text-gray-600');
                ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium"><?= $data['month'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['current']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['previous']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="<?= $isPositive ? 'text-green-600' : ($data['growth'] < 0 ? 'text-red-600' : 'text-gray-600') ?>">
                        <?= $data['growth'] > 0 ? '+' : '' ?><?= $data['growth'] ?>%
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <i class="fas <?= $trendIcon ?> <?= $trendColor ?>"></i>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Trend Analysis -->
        <div class="mt-6 p-4 rounded-lg" style="background-color: rgba(30, 111, 70, 0.03);">
          <h5 class="font-semibold text-gray-800 mb-2">Growth Trend Analysis</h5>
          <p class="text-gray-600">
            <?php
            $positiveCount = count(array_filter($historicalGrowth, function($rate) { return $rate > 0; }));
            $negativeCount = count(array_filter($historicalGrowth, function($rate) { return $rate < 0; }));
            $avgGrowth = round(array_sum($historicalGrowth) / count($historicalGrowth), 1);
            
            if ($avgGrowth > 5) {
                echo "📈 Strong positive growth trend observed over the past year with an average growth rate of {$avgGrowth}%. ";
            } elseif ($avgGrowth > 0) {
                echo "📊 Moderate positive growth trend with an average growth rate of {$avgGrowth}%. ";
            } elseif ($avgGrowth < -5) {
                echo "📉 Significant decline in growth with an average rate of {$avgGrowth}%. ";
            } else {
                echo "➡️ Stable growth pattern with minimal fluctuations. ";
            }
            
            echo "Out of the last 12 months, {$positiveCount} showed positive growth while {$negativeCount} showed decline.";
            ?>
          </p>
        </div>
      </div>
    </div>
    
    <!-- Year-over-Year Comparison Section -->
    <div class="border-t p-6">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Year-over-Year Comparison</h3>
        
        <!-- Year Selector -->
        <div class="flex items-center">
          <label for="yearSelector" class="mr-2 text-gray-700 font-medium">Select Year:</label>
          <select id="yearSelector" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
            <?php foreach ($availableYears as $year): ?>
              <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <!-- Yearly Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 rounded-lg border" style="background-color: rgba(99, 102, 241, 0.05); border-color: #6366F1;">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4 bg-indigo-500">
                        <i class="fas fa-calendar-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-indigo-600"><?= $selectedYear ?> Total</p>
                        <p class="text-2xl font-bold text-indigo-800"><?= number_format($selectedYearTotal) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="p-4 rounded-lg border" style="background-color: rgba(139, 92, 246, 0.05); border-color: #8B5CF6;">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4 bg-purple-500">
                        <i class="fas fa-calendar text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-purple-600"><?= $selectedYear - 1 ?> Total</p>
                        <p class="text-2xl font-bold text-purple-800"><?= number_format($previousYearTotal) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="p-4 rounded-lg border" style="background-color: rgba(16, 185, 129, 0.05); border-color: #10B981;">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4 bg-green-500">
                        <i class="fas fa-percentage text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-green-600">Yearly Growth</p>
                        <p class="text-2xl font-bold text-green-800">
                            <?php 
                            if ($previousYearTotal > 0) {
                                echo ($yearlyGrowthRate > 0 ? '+' : '') . $yearlyGrowthRate . '%';
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
                        <i class="fas fa-chart-bar text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-blue-600">Avg Monthly</p>
                        <p class="text-2xl font-bold text-blue-800">
                            <?= round($selectedYearTotal / 12, 0) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
      
      <!-- Year-over-Year Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Year Comparison Chart -->
        <div class="p-4 rounded-lg" style="background-color: rgba(99, 102, 241, 0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Cumulative Voter Growth: <?= $selectedYear ?> vs <?= $selectedYear - 1 ?></h3>
          <div class="chart-container">
            <canvas id="yearComparisonChart"></canvas>
          </div>
        </div>
        
        <!-- Monthly Growth Rate Chart -->
        <div class="p-4 rounded-lg" style="background-color: rgba(245, 158, 11, 0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Growth Rate (<?= $selectedYear ?> vs <?= $selectedYear - 1 ?>)</h3>
          <div class="chart-container">
            <canvas id="monthlyGrowthChart"></canvas>
          </div>
        </div>
      </div>
      
      <!-- Year-over-Year Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $selectedYear ?> Cumulative</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= $selectedYear - 1 ?> Cumulative</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Growth Rate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($yearlyData as $data): 
                $isPositive = $data['growth'] > 0;
                $trendIcon = $isPositive ? 'fa-arrow-up' : ($data['growth'] < 0 ? 'fa-arrow-down' : 'fa-minus');
                $trendColor = $isPositive ? 'text-green-600' : ($data['growth'] < 0 ? 'text-red-600' : 'text-gray-600');
              ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap font-medium"><?= $data['month'] ?></td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?= number_format($data['selected_year']) ?>
                    <span class="text-xs text-gray-500">(+<?= number_format($data['new_selected']) ?>)</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?= number_format($data['previous_year']) ?>
                    <span class="text-xs text-gray-500">(+<?= number_format($data['new_previous']) ?>)</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="<?= $isPositive ? 'text-green-600' : ($data['growth'] < 0 ? 'text-red-600' : 'text-gray-600') ?>">
                      <?= $data['growth'] > 0 ? '+' : '' ?><?= $data['growth'] ?>%
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <i class="fas <?= $trendIcon ?> <?= $trendColor ?>"></i>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
            <?php foreach ($turnoutDataByYear as $year => $data): 
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
    
    // Year selector
    const yearSelector = document.getElementById('yearSelector');
    if (yearSelector) {
        yearSelector.addEventListener('change', function() {
            const selectedYear = this.value;
            // Reload page with selected year
            window.location.href = window.location.pathname + '?year=' + selectedYear;
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
    
    let departmentChartInstance = null;
    let statusChartInstance = null;
    let growthRateChartInstance = null;
    let registrationTrendChartInstance = null;
    let yearComparisonChartInstance = null;
    let monthlyGrowthChartInstance = null;
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
        
        // Growth Rate Trend Chart (Line)
        const growthRateCtx = document.getElementById('growthRateChart');
        if (growthRateCtx && !growthRateChartInstance) {
            const monthLabels = <?= json_encode($monthLabels) ?>;
            const growthRates = <?= json_encode($historicalGrowth) ?>;
            
            growthRateChartInstance = new Chart(growthRateCtx, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Growth Rate (%)',
                        data: growthRates,
                        borderColor: '#FFD166',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#FFD166',
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
                                    return `Growth: ${context.raw}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
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
        
        // Voter Registration Trend Chart (Bar)
        const registrationTrendCtx = document.getElementById('registrationTrendChart');
        if (registrationTrendCtx && !registrationTrendChartInstance) {
            const monthLabels = <?= json_encode($monthLabels) ?>;
            const voterCounts = <?= json_encode($voterCounts) ?>;
            
            registrationTrendChartInstance = new Chart(registrationTrendCtx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'New Voters',
                        data: voterCounts,
                        backgroundColor: '#3B82F6',
                        borderColor: '#1D4ED8',
                        borderWidth: 1,
                        borderRadius: 4,
                        barThickness: 'flex',
                        maxBarThickness: 40,
                        hoverBackgroundColor: '#2563EB',
                        hoverBorderColor: '#1D4ED8'
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
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 },
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return `New Voters: ${context.raw}`;
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
                                text: 'Number of New Voters',
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
                                maxRotation: 45,
                                minRotation: 45
                            },
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Month',
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
        
        // Year Comparison Chart (Line) - Cumulative
        const yearComparisonCtx = document.getElementById('yearComparisonChart');
        if (yearComparisonCtx && !yearComparisonChartInstance) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const selectedYearData = <?= json_encode(array_column($yearlyData, 'selected_year')) ?>;
            const previousYearData = <?= json_encode(array_column($yearlyData, 'previous_year')) ?>;
            
            yearComparisonChartInstance = new Chart(yearComparisonCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: '<?= $selectedYear ?>',
                            data: selectedYearData,
                            borderColor: '#1E6F46',
                            backgroundColor: 'rgba(30, 111, 70, 0.1)',
                            borderWidth: 3,
                            pointBackgroundColor: '#1E6F46',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            fill: false,
                            tension: 0.4
                        },
                        {
                            label: '<?= $selectedYear - 1 ?>',
                            data: previousYearData,
                            borderColor: '#37A66B',
                            backgroundColor: 'rgba(55, 166, 107, 0.1)',
                            borderWidth: 3,
                            pointBackgroundColor: '#37A66B',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
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
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 },
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.raw} cumulative voters`;
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
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            title: {
                                display: true,
                                text: 'Cumulative Number of Voters',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            title: {
                                display: true,
                                text: 'Month',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Monthly Growth Rate Chart (Bar) - Cumulative
        const monthlyGrowthCtx = document.getElementById('monthlyGrowthChart');
        if (monthlyGrowthCtx && !monthlyGrowthChartInstance) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const growthRates = <?= json_encode(array_column($yearlyData, 'growth')) ?>;
            
            monthlyGrowthChartInstance = new Chart(monthlyGrowthCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Growth Rate (%)',
                        data: growthRates,
                        backgroundColor: growthRates.map(rate => 
                            rate > 0 ? '#10B981' : (rate < 0 ? '#EF4444' : '#6B7280')
                        ),
                        borderColor: growthRates.map(rate => 
                            rate > 0 ? '#059669' : (rate < 0 ? '#DC2626' : '#4B5563')
                        ),
                        borderWidth: 1,
                        borderRadius: 4,
                        barThickness: 'flex',
                        maxBarThickness: 40,
                        hoverBackgroundColor: growthRates.map(rate => 
                            rate > 0 ? '#059669' : (rate < 0 ? '#DC2626' : '#4B5563')
                        )
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
                            titleFont: { size: 14 },
                            bodyFont: { size: 13 },
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return `Cumulative Growth: ${context.raw}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                },
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            title: {
                                display: true,
                                text: 'Cumulative Growth Rate (%)',
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
                                maxRotation: 45,
                                minRotation: 45
                            },
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Month',
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
            const turnoutYears = <?= json_encode(array_keys($turnoutDataByYear)) ?>;
            const turnoutRates = <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?>;
            
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
                    labels: <?= json_encode(array_keys($turnoutDataByYear)) ?>,
                    electionCounts: <?= json_encode(array_column($turnoutDataByYear, 'election_count')) ?>,
                    turnoutRates: <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?>
                }
            },
            'voters': {
                'year': {
                    labels: <?= json_encode(array_keys($turnoutDataByYear)) ?>,
                    eligibleCounts: <?= json_encode(array_column($turnoutDataByYear, 'total_eligible')) ?>,
                    turnoutRates: <?= json_encode(array_column($turnoutDataByYear, 'turnout_rate')) ?>
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