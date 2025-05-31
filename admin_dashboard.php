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

$timeout_duration = 3600; // 1hr

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Session expired. Please login again.');
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();

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
    /* Custom green shades inspired by CvSU branding */
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    /* Scrollbar for sidebar */
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

    <?php include 'sidebar.php'; ?>

    <!-- Main content -->
    <main class="flex-1 ml-64 p-12">
      <h1 class="text-4xl font-extrabold mb-12 text-[var(--cvsu-green-dark)] tracking-tight">Admin Dashboard Overview</h1>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
        <!-- Total Voters -->
        <div class="bg-white p-8 rounded-xl shadow-lg border-l-8 border-[var(--cvsu-green)] hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-700">Total Verified Voters</h2>
              <p class="text-4xl font-bold text-[var(--cvsu-green-dark)] mt-3"><?= $total_voters ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-[var(--cvsu-green-light)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c.45 0 .85-.3.97-.73L14 7m-4 4h4m-4 0v4m4-4v4m-4-4H8m0 0l-2 4m10-4l2 4" />
            </svg>
          </div>
        </div>

        <!-- Total Elections -->
        <div class="bg-white p-8 rounded-xl shadow-lg border-l-8 border-yellow-400 hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-700">Total Elections</h2>
              <p class="text-4xl font-bold text-yellow-600 mt-3"><?= $total_elections ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>

        <!-- Ongoing Elections -->
        <div class="bg-white p-8 rounded-xl shadow-lg border-l-8 border-blue-500 hover:shadow-2xl transition-shadow duration-300">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-700">Ongoing Elections</h2>
              <p class="text-4xl font-bold text-blue-600 mt-3"><?= $ongoing_elections ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
            </svg>
          </div>
        </div>
      </div>

      <?php include 'footer.php'; ?>

    </main>

  </div>
</body>
</html>
