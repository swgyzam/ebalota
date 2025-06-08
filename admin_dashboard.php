<?php
session_start();
date_default_timezone_set('Asia/Manila');

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

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit();
}

// Fetch dashboard stats
$stmt = $pdo->query("SELECT COUNT(*) AS total_voters FROM users WHERE is_verified = 1 AND is_admin = 0");
$total_voters = $stmt->fetch()['total_voters'];

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
  <title>eBalota - admin dashboard</title>
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
<body class="bg-gray-50 text-gray-900-keft font-sans">

  <div class="flex min-h-screen">

    <?php include 'sidebar.php'; ?>

      <!-- Top Bar -->
<header class="w-full fixed top-0 left-64 h-16 bg-white shadow z-10 flex items-center justify-between px-6">
  <div class="flex items-center space-x-4">
    <button class="text-gray-700">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>
    <h1 class="text-xl font-bold">Dashboard</h1>
  </div>
  <div class="text-gray-700">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A10.95 10.95 0 0112 15c2.485 0 4.779.91 6.879 2.404M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
  </div>
</header>

<!-- Main Content Area -->
<main class="flex-1 pt-20 px-8 ml-64">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 md:gap-8">
        <!-- Total Voters -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-[var(--cvsu-green)] hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Verified Voters</h2>
              <p class="text-2xl md:text-4xl font-bold text-[var(--cvsu-green-dark)] mt-2 md:mt-3"><?= $total_voters ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 md:h-12 md:w-12 text-[var(--cvsu-green-light)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c.45 0 .85-.3.97-.73L14 7m-4 4h4m-4 0v4m4-4v4m-4-4H8m0 0l-2 4m10-4l2 4" />
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
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
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
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
            </svg>
          </div>
        </div>
      </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Bar Chart: Population of Voters per College -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
      <h2 class="font-semibold text-gray-700 mb-4">Population of Voters per Colleges</h2>
      <canvas id="collegeChart" height="200"></canvas>
    </div>

    <!-- Line Chart: Previous Election Analytics -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
      <div class="flex justify-between items-center mb-4">
        <h2 class="font-semibold text-gray-700">Previous Election Analytics Report</h2>
        <select class="border border-gray-300 rounded px-2 py-1 text-sm">
          <option value="2024">2024</option>
          <option value="2025">2025</option>
        </select>
      </div>
      <canvas id="analyticsChart" height="200"></canvas>
    </div>
  </div>

  <!-- Donut Chart: By Gender -->
  <div class="bg-white p-6 rounded-xl shadow-lg max-w-md">
    <div class="flex justify-between items-center mb-4">
      <h2 class="font-semibold text-gray-700">By Gender</h2>
      <select class="border border-gray-300 rounded px-2 py-1 text-sm">
        <option>All Colleges</option>
        <option>CIT</option>
        <option>CSPEAR</option>
        <!-- add more -->
      </select>
    </div>
    <canvas id="genderChart" height="200"></canvas>
  </div>
</main>


      <?php include 'footer.php'; ?>

    </main>
  </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const collegeChart = new Chart(document.getElementById('collegeChart'), {
    type: 'bar',
    data: {
      labels: ['CIT', 'CAS', 'CSPEAR', 'CON', 'COED'],
      datasets: [{
        label: 'Voters',
        data: [1000, 800, 900, 600, 750],
        backgroundColor: '#1E6F46'
      }]
    }
  });

  const analyticsChart = new Chart(document.getElementById('analyticsChart'), {
    type: 'line',
    data: {
      labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
      datasets: [{
        label: 'Votes',
        data: [100, 400, 300, 600, 500],
        borderColor: '#FFD166',
        fill: false
      }]
    }
  });

  const genderChart = new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
      labels: ['Male', 'Female'],
      datasets: [{
        data: [5234, 6284],
        backgroundColor: ['#37A66B', '#FFD166']
      }]
    }
  });
</script>
</html>