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
if ($scope !== 'CSG ADMIN') {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// --- Fetch dashboard stats ---

// Total Students (CSG scope includes all students)
 $stmt = $pdo->query("SELECT COUNT(*) as total_voters 
                     FROM users 
                     WHERE role = 'voter' AND position = 'student'");
 $total_voters = $stmt->fetch()['total_voters'];

// Total Elections (for CSG admin)
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

// --- Fetch CSG Analytics Data ---

// Get student distribution by college
 $stmt = $pdo->query("SELECT 
                        department as college,
                        COUNT(*) as count
                     FROM users 
                     WHERE role = 'voter' AND position = 'student'
                     GROUP BY department
                     ORDER BY count DESC");
 $studentsByCollege = $stmt->fetchAll();

// Get top courses
 $stmt = $pdo->query("SELECT 
                        course,
                        COUNT(*) as count
                     FROM users 
                     WHERE role = 'voter' AND position = 'student' 
                       AND course IS NOT NULL AND course != ''
                     GROUP BY course
                     ORDER BY count DESC
                     LIMIT 10");
 $topCourses = $stmt->fetchAll();

// Get new students this month
 $currentMonth = date('Y-m-01');
 $stmt = $pdo->prepare("SELECT COUNT(*) as new_students 
                       FROM users 
                       WHERE role = 'voter' AND position = 'student'
                         AND created_at >= ?");
 $stmt->execute([$currentMonth]);
 $newStudents = $stmt->fetch()['new_students'];

// Calculate growth rate
 $lastMonth = date('Y-m-01', strtotime('-1 month'));
 $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_students 
                       FROM users 
                       WHERE role = 'voter' AND position = 'student'
                         AND created_at >= ? AND created_at < ?");
 $stmt->execute([$lastMonth, $currentMonth]);
 $lastMonthStudents = $stmt->fetch()['last_month_students'];

 $growthRate = ($lastMonthStudents > 0) ? 
    round((($newStudents - $lastMonthStudents) / $lastMonthStudents) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - CSG Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
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
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">

<?php include 'sidebar.php'; ?>

<!-- Top Bar -->
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:rgb(25, 72, 49);"> 
  <div class="flex items-center space-x-4">
    <h1 class="text-2xl font-bold text-white">
      CSG ADMIN DASHBOARD
    </h1>
  </div>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A10.95 10.95 0 0112 15c2.485 0 4.779.91 6.879 2.404M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  </div>
</header>

<!-- Main Content Area -->
<main class="flex-1 pt-20 px-8 ml-64">
  <!-- Statistics Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Population -->
    <div class="bg-white p-6 rounded-xl shadow-lg border-l-8 border-[var(--cvsu-green)] hover:shadow-2xl transition-shadow duration-300">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Students</h2>
          <p class="text-2xl md:text-4xl font-bold text-[var(--cvsu-green-dark)] mt-2 md:mt-3"><?= number_format($total_voters) ?></p>
        </div>
      </div>
    </div>

    <!-- Total Elections -->
    <div class="bg-white p-6 rounded-xl shadow-lg border-l-8 border-yellow-400 hover:shadow-2xl transition-shadow duration-300">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-yellow-600 mt-2 md:mt-3"><?= $total_elections ?></p>
        </div>
      </div>
    </div>

    <!-- Ongoing Elections -->
    <div class="bg-white p-6 rounded-xl shadow-lg border-l-8 border-blue-500 hover:shadow-2xl transition-shadow duration-300">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-blue-600 mt-2 md:mt-3"><?= $ongoing_elections ?></p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] p-6">
      <div class="flex justify-between items-center">
        <div>
          <h2 class="text-2xl font-bold text-white">Student Population Analytics</h2>
          <p class="text-green-100">CSG ADMIN DASHBOARD</p>
        </div>
        <button id="toggleAnalytics" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg transition">
          <i class="fas fa-chevron-down mr-2"></i>Toggle Details
        </button>
      </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-green-50 p-4 rounded-lg border border-green-200">
        <div class="flex items-center">
          <div class="bg-green-100 p-3 rounded-lg mr-4">
            <i class="fas fa-users text-green-600 text-xl"></i>
          </div>
          <div>
            <p class="text-sm text-green-600">New This Month</p>
            <p class="text-2xl font-bold text-green-800"><?= number_format($newStudents) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <div class="flex items-center">
          <div class="bg-blue-100 p-3 rounded-lg mr-4">
            <i class="fas fa-university text-blue-600 text-xl"></i>
          </div>
          <div>
            <p class="text-sm text-blue-600">Colleges</p>
            <p class="text-2xl font-bold text-blue-800"><?= count($studentsByCollege) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
        <div class="flex items-center">
          <div class="bg-purple-100 p-3 rounded-lg mr-4">
            <i class="fas fa-book text-purple-600 text-xl"></i>
          </div>
          <div>
            <p class="text-sm text-purple-600">Courses</p>
            <p class="text-2xl font-bold text-purple-800"><?= count($topCourses) ?></p>
          </div>
        </div>
      </div>
      
      <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
        <div class="flex items-center">
          <div class="bg-yellow-100 p-3 rounded-lg mr-4">
            <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
          </div>
          <div>
            <p class="text-sm text-yellow-600">Growth Rate</p>
            <p class="text-2xl font-bold text-yellow-800">
              <?= $growthRate > 0 ? '+' : '' ?><?= $growthRate ?>%
            </p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Detailed Analytics (Hidden by default) -->
    <div id="analyticsDetails" class="hidden border-t">
      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- College Distribution Chart -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Students by College</h3>
            <div class="h-64">
              <canvas id="collegeChart"></canvas>
            </div>
          </div>
          
          <!-- Top Courses Chart -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Courses</h3>
            <div class="h-64">
              <canvas id="coursesChart"></canvas>
            </div>
          </div>
        </div>
        
        <!-- Detailed Table -->
        <div class="mt-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">College Breakdown</h3>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($studentsByCollege as $college): 
                  $percentage = ($total_voters > 0) ? round(($college['count'] / $total_voters) * 100, 1) : 0;
                ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium"><?= htmlspecialchars($college['college']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= number_format($college['count']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                          <div class="bg-green-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
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
  
  <!-- Recent Elections Section -->
  <div class="bg-white p-6 rounded-xl shadow-lg mt-6">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Recent Elections</h2>
    <?php if (!empty($elections)): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scope</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($elections as $election): ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($election['title']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($election['start_datetime'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($election['end_datetime'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($election['allowed_colleges']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="status-badge 
                    <?= $election['status'] === 'ongoing' ? 'bg-green-100 text-green-800' : 
                       ($election['status'] === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                    <?= ucfirst($election['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500">No elections found.</p>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle analytics details
    const toggleBtn = document.getElementById('toggleAnalytics');
    const detailsSection = document.getElementById('analyticsDetails');
    
    if (toggleBtn && detailsSection) {
        toggleBtn.addEventListener('click', function() {
            const isHidden = detailsSection.classList.contains('hidden');
            
            if (isHidden) {
                detailsSection.classList.remove('hidden');
                this.innerHTML = '<i class="fas fa-chevron-up mr-2"></i>Hide Details';
                
                // Initialize charts when first shown
                initializeCharts();
            } else {
                detailsSection.classList.add('hidden');
                this.innerHTML = '<i class="fas fa-chevron-down mr-2"></i>Toggle Details';
            }
        });
    }
    
    function initializeCharts() {
        // College Distribution Chart
        const collegeCtx = document.getElementById('collegeChart');
        if (collegeCtx) {
            new Chart(collegeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($studentsByCollege, 'college')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($studentsByCollege, 'count')) ?>,
                        backgroundColor: [
                            '#1E6F46', '#37A66B', '#FFD166', '#EF4444', '#3B82F6',
                            '#8B5CF6', '#EC4899', '#F59E0B', '#10B981', '#6B7280'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
        
        // Top Courses Chart
        const coursesCtx = document.getElementById('coursesChart');
        if (coursesCtx) {
            new Chart(coursesCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($topCourses, 'course')) ?>,
                    datasets: [{
                        label: 'Students',
                        data: <?= json_encode(array_column($topCourses, 'count')) ?>,
                        backgroundColor: '#1E6F46',
                        borderColor: '#154734',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
});
</script>

</body>
</html>