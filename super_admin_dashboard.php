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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

 $userId = $_SESSION['user_id'];

// --- Get available years for dropdown ---
 $stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE role = 'voter' ORDER BY year DESC");
 $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
 $currentYear = date('Y');
 $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
 $previousYear = $selectedYear - 1;

// --- Fetch dashboard stats ---

// Total Students (across all colleges)
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_students
                     FROM users 
                     WHERE role = 'voter' AND position = 'student'");
 $stmt->execute();
 $total_students = $stmt->fetch()['total_students'];

// Total Academic Staff (across all colleges)
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_academic
                     FROM users 
                     WHERE role = 'voter' AND position = 'academic'");
 $stmt->execute();
 $total_academic = $stmt->fetch()['total_academic'];

// Total Non-Academic Staff
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_non_academic
                     FROM users 
                     WHERE role = 'voter' AND position = 'non-academic'");
 $stmt->execute();
 $total_non_academic = $stmt->fetch()['total_non_academic'];

// Total COOP Members
 $stmt = $pdo->prepare("SELECT COUNT(*) as total_coop
                     FROM users 
                     WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1");
 $stmt->execute();
 $total_coop = $stmt->fetch()['total_coop'];

// Total Voters (all categories)
 $total_voters = $total_students + $total_academic + $total_non_academic + $total_coop;

// Total Elections (global)
 $stmt = $pdo->query("SELECT COUNT(*) as total_elections FROM elections");
 $total_elections = $stmt->fetch()['total_elections'];

// Ongoing Elections
 $stmt = $pdo->query("SELECT COUNT(*) as ongoing_elections 
                     FROM elections 
                     WHERE status = 'ongoing'");
 $ongoing_elections = $stmt->fetch()['ongoing_elections'];

// --- Fetch distribution data by category ---
 $categoryDistribution = [];

// Students by College
 $stmt = $pdo->query("SELECT 
                    department as college_name,
                    COUNT(*) as count
                 FROM users 
                 WHERE role = 'voter' AND position = 'student'
                 GROUP BY college_name
                 ORDER BY count DESC");
 $studentsByCollege = $stmt->fetchAll();

foreach ($studentsByCollege as $college) {
    $categoryDistribution[] = [
        'category' => 'Students',
        'subcategory' => $college['college_name'],
        'count' => $college['count']
    ];
}

// Academic Staff by College
 $stmt = $pdo->query("SELECT 
                    department as college_name,
                    COUNT(*) as count
                 FROM users 
                     WHERE role = 'voter' AND position = 'academic'
                 GROUP BY college_name
                 ORDER BY count DESC");
 $academicByCollege = $stmt->fetchAll();

foreach ($academicByCollege as $college) {
    $categoryDistribution[] = [
        'category' => 'Academic Staff',
        'subcategory' => $college['college_name'],
        'count' => $college['count']
    ];
}

// Non-Academic by Department
 $stmt = $pdo->query("SELECT 
                    department,
                    COUNT(*) as count
                 FROM users 
                     WHERE role = 'voter' AND position = 'non-academic'
                 GROUP BY department
                 ORDER BY count DESC");
 $nonAcademicByDept = $stmt->fetchAll();

foreach ($nonAcademicByDept as $dept) {
    $categoryDistribution[] = [
        'category' => 'Non-Academic Staff',
        'subcategory' => $dept['department'],
        'count' => $dept['count']
    ];
}

// COOP by College
 $stmt = $pdo->query("SELECT 
                    department as college_name,
                    COUNT(*) as count
                 FROM users 
                     WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1
                 GROUP BY college_name
                 ORDER BY count DESC");
 $coopByCollege = $stmt->fetchAll();

foreach ($coopByCollege as $college) {
    $categoryDistribution[] = [
        'category' => 'COOP Members',
        'subcategory' => $college['college_name'],
        'count' => $college['count']
    ];
}

// --- Fetch Voter Turnout Analytics Data ---
 $turnoutDataByYear = [];

// Get all years that have elections
 $stmt = $pdo->query("SELECT DISTINCT YEAR(start_datetime) as year FROM elections ORDER BY year DESC");
 $turnoutYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($turnoutYears as $year) {
    // Student turnout
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) as total_voted 
                           FROM votes v 
                           JOIN elections e ON v.election_id = e.election_id 
                           JOIN users u ON v.voter_id = u.user_id
                           WHERE u.position = 'student' AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$year]);
    $studentsVoted = $stmt->fetch()['total_voted'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                           FROM users 
                           WHERE position = 'student' AND created_at <= ?");
    $stmt->execute([$year . '-12-31 23:59:59']);
    $studentsEligible = $stmt->fetch()['total_eligible'];
    
    $studentsTurnout = ($studentsEligible > 0) ? round(($studentsVoted / $studentsEligible) * 100, 1) : 0;
    
    // Academic staff turnout
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) as total_voted 
                           FROM votes v 
                           JOIN elections e ON v.election_id = e.election_id 
                           JOIN users u ON v.voter_id = u.user_id
                           WHERE u.position = 'academic' AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$year]);
    $academicVoted = $stmt->fetch()['total_voted'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                           FROM users 
                           WHERE position = 'academic' AND created_at <= ?");
    $stmt->execute([$year . '-12-31 23:59:59']);
    $academicEligible = $stmt->fetch()['total_eligible'];
    
    $academicTurnout = ($academicEligible > 0) ? round(($academicVoted / $academicEligible) * 100, 1) : 0;
    
    // Non-academic staff turnout
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) as total_voted 
                           FROM votes v 
                           JOIN elections e ON v.election_id = e.election_id 
                           JOIN users u ON v.voter_id = u.user_id
                           WHERE u.position = 'non-academic' AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$year]);
    $nonAcademicVoted = $stmt->fetch()['total_voted'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                           FROM users 
                           WHERE position = 'non-academic' AND created_at <= ?");
    $stmt->execute([$year . '-12-31 23:59:59']);
    $nonAcademicEligible = $stmt->fetch()['total_eligible'];
    
    $nonAcademicTurnout = ($nonAcademicEligible > 0) ? round(($nonAcademicVoted / $nonAcademicEligible) * 100, 1) : 0;
    
    // COOP turnout
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.voter_id) as total_voted 
                           FROM votes v 
                           JOIN elections e ON v.election_id = e.election_id 
                           JOIN users u ON v.voter_id = u.user_id
                           WHERE u.is_coop_member = 1 AND u.migs_status = 1 AND YEAR(e.start_datetime) = ?");
    $stmt->execute([$year]);
    $coopVoted = $stmt->fetch()['total_voted'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                           FROM users 
                           WHERE is_coop_member = 1 AND migs_status = 1 AND created_at <= ?");
    $stmt->execute([$year . '-12-31 23:59:59']);
    $coopEligible = $stmt->fetch()['total_eligible'];
    
    $coopTurnout = ($coopEligible > 0) ? round(($coopVoted / $coopEligible) * 100, 1) : 0;
    
    // Overall turnout
    $totalVoted = $studentsVoted + $academicVoted + $nonAcademicVoted + $coopVoted;
    $totalEligible = $studentsEligible + $academicEligible + $nonAcademicEligible + $coopEligible;
    $overallTurnout = ($totalEligible > 0) ? round(($totalVoted / $totalEligible) * 100, 1) : 0;
    
    // Election count
    $stmt = $pdo->prepare("SELECT COUNT(*) as election_count 
                           FROM elections 
                           WHERE YEAR(start_datetime) = ?");
    $stmt->execute([$year]);
    $electionCount = $stmt->fetch()['election_count'];
    
    $turnoutDataByYear[$year] = [
        'year' => $year,
        'students_turnout' => $studentsTurnout,
        'academic_turnout' => $academicTurnout,
        'non_academic_turnout' => $nonAcademicTurnout,
        'coop_turnout' => $coopTurnout,
        'overall_turnout' => $overallTurnout,
        'election_count' => $electionCount
    ];
}

// FIXED: Ensure selected year and previous year are in the data array
if (!isset($turnoutDataByYear[$selectedYear])) {
    $turnoutDataByYear[$selectedYear] = [
        'year' => $selectedYear,
        'students_turnout' => 0,
        'academic_turnout' => 0,
        'non_academic_turnout' => 0,
        'coop_turnout' => 0,
        'overall_turnout' => 0,
        'election_count' => 0
    ];
}

if (!isset($turnoutDataByYear[$previousYear])) {
    $turnoutDataByYear[$previousYear] = [
        'year' => $previousYear,
        'students_turnout' => 0,
        'academic_turnout' => 0,
        'non_academic_turnout' => 0,
        'coop_turnout' => 0,
        'overall_turnout' => 0,
        'election_count' => 0
    ];
}

// Calculate year-over-year growth for overall turnout
 $years = array_keys($turnoutDataByYear);
sort($years);
 $previousYearData = null;
foreach ($years as $year) {
    if ($previousYearData !== null) {
        $prevTurnout = $previousYearData['overall_turnout'];
        $currentTurnout = $turnoutDataByYear[$year]['overall_turnout'];
        $growthRate = ($prevTurnout > 0) ? round((($currentTurnout - $prevTurnout) / $prevTurnout) * 100, 1) : 0;
        $turnoutDataByYear[$year]['growth_rate'] = $growthRate;
    } else {
        $turnoutDataByYear[$year]['growth_rate'] = 0;
    }
    $previousYearData = $turnoutDataByYear[$year];
}

// Get current and previous year data for summary cards
 $currentYearTurnout = $turnoutDataByYear[$selectedYear] ?? null;
 $previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;
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
  <style>
    :root {
      /* CVSU Colors */
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
      --cvsu-accent: #2D5F3F;
      --cvsu-light-accent: #4A7C59;
      
      /* Additional Colors */
      --cvsu-blue: #3B82F6;
      --cvsu-purple: #8B5CF6;
      --cvsu-red: #EF4444;
      --cvsu-orange: #F97316;
      --cvsu-teal: #14B8A6;
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
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      overflow: auto;
    }
    .modal-content {
      background-color: white;
      margin: 10% auto;
      padding: 20px;
      border-radius: 10px;
      width: 80%;
      max-width: 800px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    .close:hover {
      color: black;
    }
    .summary-card {
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .summary-card-content {
      display: flex;
      align-items: center;
      flex-grow: 1;
    }
    .summary-card-details {
      flex-grow: 1;
    }
    .summary-card-icon {
      flex-shrink: 0;
    }
    .summary-card-footer {
      margin-top: 1rem;
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
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
  </div>
</header>
<main class="flex-1 pt-20 px-8 ml-64">

  <!-- Statistics Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Population -->
    <div class="cvsu-card p-6 rounded-xl">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Population</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-green-dark);"><?= number_format($total_voters) ?></p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(30, 111, 70, 0.1);">
          <i class="fas fa-users text-2xl" style="color: var(--cvsu-green);"></i>
        </div>
      </div>
      <div class="mt-4">
        <button onclick="showPopulationBreakdown()" class="text-sm font-medium" style="color: var(--cvsu-green);">
          View Breakdown <i class="fas fa-chevron-right ml-1"></i>
        </button>
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
      <div class="mt-4">
        <button onclick="showElectionBreakdown()" class="text-sm font-medium" style="color: var(--cvsu-yellow);">
          View Breakdown <i class="fas fa-chevron-right ml-1"></i>
        </button>
      </div>
    </div>
    
    <!-- Ongoing Elections -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-blue);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-blue-600"><?= $ongoing_elections ?></p>
        </div>
        <div class="p-3 rounded-full bg-blue-50">
          <i class="fas fa-clock text-2xl text-blue-600"></i>
        </div>
      </div>
      <div class="mt-4">
        <button onclick="showOngoingElectionBreakdown()" class="text-sm font-medium text-blue-600">
          View Details <i class="fas fa-chevron-right ml-1"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Category Distribution -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">Population Distribution by Category</h2>
      </div>
    </div>
    
    <div class="p-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Students -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color: var(--cvsu-green-light);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-green-light);">
              <i class="fas fa-user-graduate text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm" style="color: var(--cvsu-green);">Students</p>
              <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);"><?= number_format($total_students) ?></p>
            </div>
          </div>
        </div>

        <!-- Academic Staff -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color: #3B82F6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-blue-500">
              <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-blue-600">Academic Staff</p>
              <p class="text-2xl font-bold text-blue-800"><?= number_format($total_academic) ?></p>
            </div>
          </div>
        </div>

        <!-- Non-Academic Staff -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color: #8B5CF6;">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500">
              <i class="fas fa-user-tie text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-purple-600">Non-Academic Staff</p>
              <p class="text-2xl font-bold text-purple-800"><?= number_format($total_non_academic) ?></p>
            </div>
          </div>
        </div>

        <!-- COOP Members -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(245,158,11,0.05); border-color: var(--cvsu-yellow);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-yellow);">
              <i class="fas fa-handshake text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm" style="color: var(--cvsu-yellow);">COOP Members</p>
              <p class="text-2xl font-bold" style="color: #D97706;"><?= number_format($total_coop) ?></p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Distribution Chart -->
      <div class="p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Population Distribution</h3>
        <div class="chart-container">
          <canvas id="distributionChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Voter Turnout Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">Voter Turnout Analytics</h2>
        <div class="flex items-center">
          <label for="turnoutYearSelector" class="mr-2 text-white font-medium">Select Year:</label>
          <select id="turnoutYearSelector" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
            <?php foreach ($turnoutYears as $year): ?>
              <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
    
    <div class="border-t p-6">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <!-- Overall Turnout -->
        <div class="summary-card p-4 rounded-lg border" style="background-color: rgba(99,102,241,0.05); border-color:var(--cvsu-blue);">
          <div class="summary-card-content">
            <div class="summary-card-details">
              <p class="text-sm text-indigo-600">Overall Turnout</p>
              <p class="text-2xl font-bold text-indigo-800"><?= $currentYearTurnout['overall_turnout'] ?? 0 ?>%</p>
            </div>
            <div class="summary-card-icon">
              <div class="p-3 rounded-lg bg-indigo-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            </div>
          </div>
          <div class="summary-card-footer">
            <p class="text-xs text-gray-500">
              <?php if ($previousYearTurnout): ?>
                <?= ($currentYearTurnout['overall_turnout'] > $previousYearTurnout['overall_turnout']) ? '↑' : '↓' ?> 
                <?= abs(round(($currentYearTurnout['overall_turnout'] ?? 0) - ($previousYearTurnout['overall_turnout'] ?? 0), 1)) ?>% from last year
              <?php else: ?>
                No previous year data
              <?php endif; ?>
            </p>
          </div>
        </div>
        
        <!-- Students Turnout -->
        <div class="summary-card p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color:var(--cvsu-green);">
          <div class="summary-card-content">
            <div class="summary-card-details">
              <p class="text-sm" style="color: var(--cvsu-green);">Students Turnout</p>
              <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);"><?= $currentYearTurnout['students_turnout'] ?? 0 ?>%</p>
            </div>
            <div class="summary-card-icon">
              <div class="p-3 rounded-lg" style="background-color: var(--cvsu-green);"><i class="fas fa-user-graduate text-white text-xl"></i></div>
            </div>
          </div>
          <div class="summary-card-footer">
            <p class="text-xs text-gray-500">
              <?php if ($previousYearTurnout): ?>
                <?= ($currentYearTurnout['students_turnout'] > $previousYearTurnout['students_turnout']) ? '↑' : '↓' ?> 
                <?= abs(round(($currentYearTurnout['students_turnout'] ?? 0) - ($previousYearTurnout['students_turnout'] ?? 0), 1)) ?>% from last year
              <?php else: ?>
                No previous year data
              <?php endif; ?>
            </p>
          </div>
        </div>
        
        <!-- Academic Turnout -->
        <div class="summary-card p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color:#3B82F6;">
          <div class="summary-card-content">
            <div class="summary-card-details">
              <p class="text-sm text-blue-600">Academic Turnout</p>
              <p class="text-2xl font-bold text-blue-800"><?= $currentYearTurnout['academic_turnout'] ?? 0 ?>%</p>
            </div>
            <div class="summary-card-icon">
              <div class="p-3 rounded-lg bg-blue-500"><i class="fas fa-chalkboard-teacher text-white text-xl"></i></div>
            </div>
          </div>
          <div class="summary-card-footer">
            <p class="text-xs text-gray-500">
              <?php if ($previousYearTurnout): ?>
                <?= ($currentYearTurnout['academic_turnout'] > $previousYearTurnout['academic_turnout']) ? '↑' : '↓' ?> 
                <?= abs(round(($currentYearTurnout['academic_turnout'] ?? 0) - ($previousYearTurnout['academic_turnout'] ?? 0), 1)) ?>% from last year
              <?php else: ?>
                No previous year data
              <?php endif; ?>
            </p>
          </div>
        </div>
        
        <!-- Non-Academic Turnout -->
        <div class="summary-card p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color:#8B5CF6;">
          <div class="summary-card-content">
            <div class="summary-card-details">
              <p class="text-sm text-purple-600">Non-Academic Turnout</p>
              <p class="text-2xl font-bold text-purple-800"><?= $currentYearTurnout['non_academic_turnout'] ?? 0 ?>%</p>
            </div>
            <div class="summary-card-icon">
              <div class="p-3 rounded-lg bg-purple-500"><i class="fas fa-user-tie text-white text-xl"></i></div>
            </div>
          </div>
          <div class="summary-card-footer">
            <p class="text-xs text-gray-500">
              <?php if ($previousYearTurnout): ?>
                <?= ($currentYearTurnout['non_academic_turnout'] > $previousYearTurnout['non_academic_turnout']) ? '↑' : '↓' ?> 
                <?= abs(round(($currentYearTurnout['non_academic_turnout'] ?? 0) - ($previousYearTurnout['non_academic_turnout'] ?? 0), 1)) ?>% from last year
              <?php else: ?>
                No previous year data
              <?php endif; ?>
            </p>
          </div>
        </div>
        
        <!-- COOP Turnout -->
        <div class="summary-card p-4 rounded-lg border" style="background-color: rgba(245,158,11,0.05); border-color:var(--cvsu-yellow);">
          <div class="summary-card-content">
            <div class="summary-card-details">
              <p class="text-sm" style="color: var(--cvsu-yellow);">COOP Turnout</p>
              <p class="text-2xl font-bold" style="color: #D97706;"><?= $currentYearTurnout['coop_turnout'] ?? 0 ?>%</p>
            </div>
            <div class="summary-card-icon">
              <div class="p-3 rounded-lg" style="background-color: var(--cvsu-yellow);"><i class="fas fa-handshake text-white text-xl"></i></div>
            </div>
          </div>
          <div class="summary-card-footer">
            <p class="text-xs text-gray-500">
              <?php if ($previousYearTurnout): ?>
                <?= ($currentYearTurnout['coop_turnout'] > $previousYearTurnout['coop_turnout']) ? '↑' : '↓' ?> 
                <?= abs(round(($currentYearTurnout['coop_turnout'] ?? 0) - ($previousYearTurnout['coop_turnout'] ?? 0), 1)) ?>% from last year
              <?php else: ?>
                No previous year data
              <?php endif; ?>
            </p>
          </div>
        </div>
      </div>
      
      <!-- Turnout Trend Chart -->
      <div class="p-4 rounded-lg mb-8" style="background-color: rgba(30,111,70,0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Turnout Rate Trend</h3>
        <div class="chart-container" style="height: 400px;">
          <canvas id="turnoutTrendChart"></canvas>
        </div>
      </div>
      
      <!-- Year-over-Year Comparison Section -->
      <div class="mt-4 p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Year-over-Year Comparison</h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <!-- Overall Comparison -->
          <div class="text-center">
            <p class="text-sm text-gray-600">Overall Turnout</p>
            <div class="flex justify-center items-center mt-1">
              <span class="text-lg font-semibold"><?= $previousYearTurnout['overall_turnout'] ?? 0 ?>%</span>
              <i class="fas fa-arrow-right mx-2 text-gray-400"></i>
              <span class="text-lg font-semibold" style="color: var(--cvsu-green-dark);"><?= $currentYearTurnout['overall_turnout'] ?? 0 ?>%</span>
            </div>
            <p class="text-sm mt-1 <?= ($currentYearTurnout['overall_turnout'] ?? 0) > ($previousYearTurnout['overall_turnout'] ?? 0) ? 'text-green-600' : 'text-red-600' ?>">
              <?= ($currentYearTurnout['overall_turnout'] ?? 0) > ($previousYearTurnout['overall_turnout'] ?? 0) ? '+' : '' ?>
              <?= round(($currentYearTurnout['overall_turnout'] ?? 0) - ($previousYearTurnout['overall_turnout'] ?? 0), 1) ?>%
            </p>
          </div>
          
          <!-- Students Comparison -->
          <div class="text-center">
            <p class="text-sm text-gray-600">Students</p>
            <div class="flex justify-center items-center mt-1">
              <span class="text-lg font-semibold"><?= $previousYearTurnout['students_turnout'] ?? 0 ?>%</span>
              <i class="fas fa-arrow-right mx-2 text-gray-400"></i>
              <span class="text-lg font-semibold" style="color: var(--cvsu-green-dark);"><?= $currentYearTurnout['students_turnout'] ?? 0 ?>%</span>
            </div>
            <p class="text-sm mt-1 <?= ($currentYearTurnout['students_turnout'] ?? 0) > ($previousYearTurnout['students_turnout'] ?? 0) ? 'text-green-600' : 'text-red-600' ?>">
              <?= ($currentYearTurnout['students_turnout'] ?? 0) > ($previousYearTurnout['students_turnout'] ?? 0) ? '+' : '' ?>
              <?= round(($currentYearTurnout['students_turnout'] ?? 0) - ($previousYearTurnout['students_turnout'] ?? 0), 1) ?>%
            </p>
          </div>
          
          <!-- Academic Comparison -->
          <div class="text-center">
            <p class="text-sm text-gray-600">Academic Staff</p>
            <div class="flex justify-center items-center mt-1">
              <span class="text-lg font-semibold"><?= $previousYearTurnout['academic_turnout'] ?? 0 ?>%</span>
              <i class="fas fa-arrow-right mx-2 text-gray-400"></i>
              <span class="text-lg font-semibold" style="color: var(--cvsu-green-dark);"><?= $currentYearTurnout['academic_turnout'] ?? 0 ?>%</span>
            </div>
            <p class="text-sm mt-1 <?= ($currentYearTurnout['academic_turnout'] ?? 0) > ($previousYearTurnout['academic_turnout'] ?? 0) ? 'text-green-600' : 'text-red-600' ?>">
              <?= ($currentYearTurnout['academic_turnout'] ?? 0) > ($previousYearTurnout['academic_turnout'] ?? 0) ? '+' : '' ?>
              <?= round(($currentYearTurnout['academic_turnout'] ?? 0) - ($previousYearTurnout['academic_turnout'] ?? 0), 1) ?>%
            </p>
          </div>
          
          <!-- Non-Academic Comparison -->
          <div class="text-center">
            <p class="text-sm text-gray-600">Non-Academic Staff</p>
            <div class="flex justify-center items-center mt-1">
              <span class="text-lg font-semibold"><?= $previousYearTurnout['non_academic_turnout'] ?? 0 ?>%</span>
              <i class="fas fa-arrow-right mx-2 text-gray-400"></i>
              <span class="text-lg font-semibold" style="color: var(--cvsu-green-dark);"><?= $currentYearTurnout['non_academic_turnout'] ?? 0 ?>%</span>
            </div>
            <p class="text-sm mt-1 <?= ($currentYearTurnout['non_academic_turnout'] ?? 0) > ($previousYearTurnout['non_academic_turnout'] ?? 0) ? 'text-green-600' : 'text-red-600' ?>">
              <?= ($currentYearTurnout['non_academic_turnout'] ?? 0) > ($previousYearTurnout['non_academic_turnout'] ?? 0) ? '+' : '' ?>
              <?= round(($currentYearTurnout['non_academic_turnout'] ?? 0) - ($previousYearTurnout['non_academic_turnout'] ?? 0), 1) ?>%
            </p>
          </div>
          
          <!-- COOP Comparison -->
          <div class="text-center">
            <p class="text-sm text-gray-600">COOP Members</p>
            <div class="flex justify-center items-center mt-1">
              <span class="text-lg font-semibold"><?= $previousYearTurnout['coop_turnout'] ?? 0 ?>%</span>
              <i class="fas fa-arrow-right mx-2 text-gray-400"></i>
              <span class="text-lg font-semibold" style="color: var(--cvsu-green-dark);"><?= $currentYearTurnout['coop_turnout'] ?? 0 ?>%</span>
            </div>
            <p class="text-sm mt-1 <?= ($currentYearTurnout['coop_turnout'] ?? 0) > ($previousYearTurnout['coop_turnout'] ?? 0) ? 'text-green-600' : 'text-red-600' ?>">
              <?= ($currentYearTurnout['coop_turnout'] ?? 0) > ($previousYearTurnout['coop_turnout'] ?? 0) ? '+' : '' ?>
              <?= round(($currentYearTurnout['coop_turnout'] ?? 0) - ($previousYearTurnout['coop_turnout'] ?? 0), 1) ?>%
            </p>
          </div>
        </div>
      </div>
      
      <!-- Yearly Turnout Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mt-6">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Elections</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Overall Turnout</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students Turnout</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Academic Turnout</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Non-Academic Turnout</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">COOP Turnout</th>
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
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['overall_turnout'] >= 70 ? 'text-green-600' : ($data['overall_turnout'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['overall_turnout'] ?>%
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['students_turnout'] >= 70 ? 'text-green-600' : ($data['students_turnout'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['students_turnout'] ?>%
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['academic_turnout'] >= 70 ? 'text-green-600' : ($data['academic_turnout'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['academic_turnout'] ?>%
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['non_academic_turnout'] >= 70 ? 'text-green-600' : ($data['non_academic_turnout'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['non_academic_turnout'] ?>%
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['coop_turnout'] >= 70 ? 'text-green-600' : ($data['coop_turnout'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['coop_turnout'] ?>%
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
  </div>

</main>
</div>

<!-- Modals for Breakdowns -->
<!-- Population Breakdown Modal -->
<div id="populationModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('populationModal')">&times;</span>
    <h2 class="text-2xl font-bold mb-4" style="color: var(--cvsu-green-dark);">Population Breakdown</h2>
    <div class="grid grid-cols-2 gap-4">
      <div class="p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
        <h3 class="font-semibold mb-2" style="color: var(--cvsu-green);">Students</h3>
        <p class="text-2xl font-bold"><?= number_format($total_students) ?></p>
        <p class="text-sm text-gray-600"><?= round(($total_students / $total_voters) * 100, 1) ?>% of total</p>
      </div>
      <div class="p-4 rounded-lg bg-blue-50">
        <h3 class="font-semibold mb-2 text-blue-600">Academic Staff</h3>
        <p class="text-2xl font-bold text-blue-800"><?= number_format($total_academic) ?></p>
        <p class="text-sm text-gray-600"><?= round(($total_academic / $total_voters) * 100, 1) ?>% of total</p>
      </div>
      <div class="p-4 rounded-lg bg-purple-50">
        <h3 class="font-semibold mb-2 text-purple-600">Non-Academic Staff</h3>
        <p class="text-2xl font-bold text-purple-800"><?= number_format($total_non_academic) ?></p>
        <p class="text-sm text-gray-600"><?= round(($total_non_academic / $total_voters) * 100, 1) ?>% of total</p>
      </div>
      <div class="p-4 rounded-lg" style="background-color: rgba(245,158,11,0.05);">
        <h3 class="font-semibold mb-2" style="color: var(--cvsu-yellow);">COOP Members</h3>
        <p class="text-2xl font-bold" style="color: #D97706;"><?= number_format($total_coop) ?></p>
        <p class="text-sm text-gray-600"><?= round(($total_coop / $total_voters) * 100, 1) ?>% of total</p>
      </div>
    </div>
  </div>
</div>

<!-- Election Breakdown Modal -->
<div id="electionModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('electionModal')">&times;</span>
    <h2 class="text-2xl font-bold mb-4" style="color: var(--cvsu-yellow);">Election Breakdown</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Election ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php
          $stmt = $pdo->query("SELECT election_id, title, start_datetime, end_datetime, status FROM elections ORDER BY start_datetime DESC");
          $elections = $stmt->fetchAll();
          foreach ($elections as $election):
            $statusColor = $election['status'] == 'ongoing' ? 'text-green-600' : ($election['status'] == 'completed' ? 'text-blue-600' : 'text-gray-600');
          ?>
          <tr>
            <td class="px-6 py-4 whitespace-nowrap"><?= $election['election_id'] ?></td>
            <td class="px-6 py-4 whitespace-nowrap"><?= $election['title'] ?></td>
            <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y', strtotime($election['start_datetime'])) ?></td>
            <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y', strtotime($election['end_datetime'])) ?></td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="<?= $statusColor ?>"><?= ucfirst($election['status']) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Ongoing Election Breakdown Modal -->
<div id="ongoingModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('ongoingModal')">&times;</span>
    <h2 class="text-2xl font-bold mb-4 text-blue-600">Ongoing Elections Details</h2>
    <?php
    $stmt = $pdo->query("SELECT election_id, title, start_datetime, end_datetime FROM elections WHERE status = 'ongoing'");
    $ongoing = $stmt->fetchAll();
    if (count($ongoing) > 0):
      foreach ($ongoing as $election):
        $startDate = new DateTime($election['start_datetime']);
        $endDate = new DateTime($election['end_datetime']);
        $now = new DateTime();
        $interval = $now->diff($endDate);
        $daysLeft = $interval->days;
    ?>
    <div class="p-4 rounded-lg border border-blue-200 mb-4">
      <h3 class="text-lg font-semibold text-blue-800"><?= $election['title'] ?></h3>
      <p class="text-sm text-gray-600 mb-2">Election ID: <?= $election['election_id'] ?></p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-sm text-gray-500">Start Date</p>
          <p class="font-medium"><?= $startDate->format('M d, Y h:i A') ?></p>
        </div>
        <div>
          <p class="text-sm text-gray-500">End Date</p>
          <p class="font-medium"><?= $endDate->format('M d, Y h:i A') ?></p>
        </div>
      </div>
      <div class="mt-3">
        <p class="text-sm text-gray-500">Time Remaining</p>
        <p class="font-medium text-blue-600"><?= $daysLeft ?> day(s) left</p>
      </div>
    </div>
    <?php 
      endforeach;
    else:
    ?>
    <p class="text-gray-500">No ongoing elections at the moment.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Year selector
  document.getElementById('turnoutYearSelector')?.addEventListener('change', function() {
    window.location.href = window.location.pathname + '?year=' + this.value;
  });
  
  // Distribution Chart
  const distributionCtx = document.getElementById('distributionChart');
  if (distributionCtx) {
    const distributionData = <?= json_encode($categoryDistribution) ?>;
    
    // Group by category
    const categories = {};
    distributionData.forEach(item => {
      if (!categories[item.category]) {
        categories[item.category] = 0;
      }
      categories[item.category] += item.count;
    });
    
    new Chart(distributionCtx, {
      type: 'doughnut',
      data: {
        labels: Object.keys(categories),
        datasets: [{
          data: Object.values(categories),
          backgroundColor: [
            '#1E6F46', // Students - green
            '#3B82F6', // Academic - blue
            '#8B5CF6', // Non-Academic - purple
            '#F59E0B'  // COOP - yellow
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
  
  // Turnout Trend Chart
  const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
  if (turnoutTrendCtx) {
    // Get the sorted years and data
    const turnoutData = <?= json_encode($turnoutDataByYear) ?>;
    
    // Sort by year
    const sortedYears = Object.keys(turnoutData).sort();
    const overallTurnout = sortedYears.map(year => turnoutData[year].overall_turnout);
    const studentsTurnout = sortedYears.map(year => turnoutData[year].students_turnout);
    const academicTurnout = sortedYears.map(year => turnoutData[year].academic_turnout);
    const nonAcademicTurnout = sortedYears.map(year => turnoutData[year].non_academic_turnout);
    const coopTurnout = sortedYears.map(year => turnoutData[year].coop_turnout);
    
    new Chart(turnoutTrendCtx, {
      type: 'line',
      data: {
        labels: sortedYears,
        datasets: [
          {
            label: 'Overall Turnout',
            data: overallTurnout,
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
            label: 'Students',
            data: studentsTurnout,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#3B82F6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: false,
            tension: 0.4
          },
          {
            label: 'Academic Staff',
            data: academicTurnout,
            borderColor: '#8B5CF6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#8B5CF6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: false,
            tension: 0.4
          },
          {
            label: 'Non-Academic Staff',
            data: nonAcademicTurnout,
            borderColor: '#A78BFA',
            backgroundColor: 'rgba(167, 139, 250, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#A78BFA',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: false,
            tension: 0.4
          },
          {
            label: 'COOP Members',
            data: coopTurnout,
            borderColor: '#F59E0B',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#F59E0B',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
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
              font: { size: 12 },
              padding: 15
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleFont: { size: 14 },
            bodyFont: { size: 13 },
            padding: 12,
            callbacks: {
              label: function(context) {
                return `${context.dataset.label}: ${context.raw}%`;
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
});

// Modal functions
function showPopulationBreakdown() {
  document.getElementById('populationModal').style.display = 'block';
}

function showElectionBreakdown() {
  document.getElementById('electionModal').style.display = 'block';
}

function showOngoingElectionBreakdown() {
  document.getElementById('ongoingModal').style.display = 'block';
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modals = document.getElementsByClassName('modal');
  for (let i = 0; i < modals.length; i++) {
    if (event.target == modals[i]) {
      modals[i].style.display = 'none';
    }
  }
}
</script>

</body>
</html>