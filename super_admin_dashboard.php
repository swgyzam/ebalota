<?php
session_start();
date_default_timezone_set('Asia/Manila');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Redirect if not logged in or not super admin
//if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
  //header('Location: login.php');
  //exit();
//}

// Fetch dashboard stats
$stmt = $pdo->query("SELECT COUNT(*) AS total_voters FROM users WHERE is_verified = 1 AND role = 'voter'");
$total_voters = $stmt->fetch()['total_voters'];

$stmt = $pdo->query("SELECT COUNT(*) AS total_admins FROM users WHERE role IN ('admin')");
$total_admins = $stmt->fetch()['total_admins'];

$stmt = $pdo->query("SELECT COUNT(*) AS total_elections FROM elections");
$total_elections = $stmt->fetch()['total_elections'];

$stmt = $pdo->query("SELECT COUNT(*) AS ongoing_elections FROM elections WHERE status = 'ongoing'");
$ongoing_elections = $stmt->fetch()['ongoing_elections'];

$now = date('Y-m-d H:i:s');
$pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
$pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
$pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - Super Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

  <div class="flex min-h-screen">

    <?php include 'super_admin_sidebar.php'; ?>

    <header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:rgb(25, 72, 49);"> 
      <div class="flex items-center space-x-4">
        <h1 class="text-xl font-bold text-white">SUPER ADMIN DASHBOARD</h1>
      </div>
      <div class="text-white">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A10.95 10.95 0 0112 15c2.485 0 4.779.91 6.879 2.404M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
    </header>

    <main class="flex-1 pt-20 px-8 ml-64">

      <!-- Dashboard Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        <!-- Total Population -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-[var(--cvsu-green)] hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Population</h2>
              <p class="text-2xl md:text-4xl font-bold text-[var(--cvsu-green-dark)] mt-2 md:mt-3"><?= $total_voters ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 md:h-12 md:w-12 text-[var(--cvsu-green-light)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M12 12a4 4 0 100-8 4 4 0 000 8zm6 8v-2a4 4 0 00-3-3.87M6 20v-2a4 4 0 013-3.87" />
            </svg>
          </div>
        </div>

        <!-- Total Admin Accounts -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-green-500 hover:shadow-2xl transition-shadow duration-300">
        <div class="flex items-center justify-between">
            <div>
            <h2 class="text-base md:text-lg font-semibold text-gray-700">Admin Accounts</h2>
            <p class="text-2xl md:text-4xl font-bold text-green-700 mt-2 md:mt-3"><?= $total_admins ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 md:h-12 md:w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 013-3.87M15 17v-2a4 4 0 00-3-3.87m0 0a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
        </div>
        </div>

        <!-- Total Elections -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-yellow-400 hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
              <p class="text-2xl md:text-4xl font-bold text-yellow-600 mt-2 md:mt-3"><?= $total_elections ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 md:h-12 md:w-12 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 6h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm5 6h4" />
            </svg>
          </div>
        </div>

        <!-- Ongoing Elections -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-blue-500 hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
              <p class="text-2xl md:text-4xl font-bold text-blue-600 mt-2 md:mt-3"><?= $ongoing_elections ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 md:h-12 md:w-12 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>

      </div>

      <!-- Charts -->
      <div class="flex flex-col lg:flex-row gap-10 mb-6">

        <!-- By Gender -->
        <div class="bg-white p-6 rounded-xl shadow-lg w-full lg:max-w-sm">
          <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold text-gray-700">By Gender</h2>
            <select class="border border-gray-300 rounded px-2 py-1 text-sm">
              <option>All Colleges</option>
              <option>CAS</option>
              <option>CCJ</option>
              <option>CED</option>
              <option>CEIT</option>
              <option>CON</option>
              <option>CEMDS</option>
              <option>CHTM</option>
              <option>CAFENR</option>
              <option>CSPEAR</option>
              <option>CVMBS</option>
            </select>
          </div>
          <canvas id="genderChart" height="180"></canvas>
        </div>

        <!-- Analytics -->
        <div class="bg-white p-6 rounded-xl shadow-lg flex-1">
          <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold text-gray-700">Previous Election Analytics Report</h2>
            <select class="border border-gray-300 rounded px-2 py-1 text-sm">
              <option value="2024">2024</option>
              <option value="2025">2025</option>
            </select>
          </div>
          <canvas id="analyticsChart" height="80"></canvas>
        </div>
      </div>

      <!-- Population per College -->
      <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg mb-8">
        <h2 class="text-base md:text-lg font-semibold text-gray-700 mb-4">Population of Voters per Colleges</h2>
        <canvas id="collegeChart" class="w-full h-64"></canvas>
      </div>

      <div class="p-5">
        <?php include 'footer.php'; ?>
      </div>

    </main>
  </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const collegeChart = new Chart(document.getElementById('collegeChart'), {
    type: 'bar',
    data: {
      labels: ['CAS', 'CCJ', 'CED', 'CEIT', 'CON', 'CEMDS', 'CTHM', 'CAFENR', 'CSPEAR', 'CVMBS'],
      datasets: [{
        data: [1517, 792, 770, 1213, 760, 1864, 819, 620, 397, 246],
        backgroundColor: ['#e62e00', '#003300','#0033cc', '#ff9933', '#b3b3b3', '#008000', '#ff6699', '#00b33c', '#993300', '#990099' ]
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });

  const analyticsChart = new Chart(document.getElementById('analyticsChart'), {
    type: 'line',
    data: {
      labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
      datasets: [{
        label: '',
        data: [100, 400, 300, 600, 500, 100, 20, 200, 400, 321, 202, 1000],
        borderColor: '#FFD166',
        fill: false
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });

  const genderChart = new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
      labels: ['Male', 'Female'],
      datasets: [{
        data: [5234, 6284],
        backgroundColor: ['#0066ff', '#ff6699']
      }]
    }
  });
</script>
</body>
</html>
