<?php
//session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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

// --- Auth check ---BYPASS for now ---------------------------
//if (!isset($_SESSION['user_id'])) {
    //header('Location: login.php');
    //exit();
//}

//$userId = $_SESSION['user_id'];

// --- Fetch user info including scope ---
$stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = :userId");
$stmt->execute([':userId' => $userId]);
$user = $stmt->fetch();

//if (!$user) {
    // User not found in DB, force logout
    //session_destroy();
    //header('Location: login.php');
    //exit();
//}

$role = $user['role'];
$scope = $user['assigned_scope'] ?? null;

// --- Fetch dashboard stats ---

// Total Voters
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_voters 
                           FROM users 
                           WHERE is_verified = 1 
                             AND is_admin = 0 
                             AND assigned_scope = :scope");
    $stmt->execute([':scope' => $scope]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) AS total_voters 
                         FROM users 
                         WHERE is_verified = 1 
                           AND is_admin = 0");
}
$total_voters = $stmt->fetch()['total_voters'];

// Total Elections
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_elections 
                           FROM elections 
                           WHERE assigned_admin_id = :adminId");
    $stmt->execute([':adminId' => $userId]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) AS total_elections FROM elections");
}
$total_elections = $stmt->fetch()['total_elections'];

// Ongoing Elections
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS ongoing_elections 
                           FROM elections 
                           WHERE assigned_admin_id = :adminId 
                             AND status = 'ongoing'");
    $stmt->execute([':adminId' => $userId]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) AS ongoing_elections 
                         FROM elections WHERE status = 'ongoing'");
}
$ongoing_elections = $stmt->fetch()['ongoing_elections'];

// --- Election status auto-update (only for super_admin) ---
if ($role === 'super_admin') {
    $now = date('Y-m-d H:i:s');
    $pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
    $pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
    $pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");
}

// --- Fetch elections for display ---
if ($role === 'admin') {
    $electionStmt = $pdo->prepare("SELECT * FROM elections 
                                   WHERE assigned_admin_id = :adminId
                                   ORDER BY start_datetime DESC");
    $electionStmt->execute([':adminId' => $userId]);
    $elections = $electionStmt->fetchAll();
} else {
    $electionStmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
    $elections = $electionStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - Admin Dashboard</title>
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
    @media (max-width: 767px) {
      .main-content {
        margin-left: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      .fixed-header {
        left: 0 !important;
        width: 100vw !important;
      }
    }
    @media (min-width: 768px) {
      .main-content {
        margin-left: 16rem !important;
      }
      .fixed-header {
        left: 16rem !important;
        width: calc(100vw - 16rem) !important;
      }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<!-- Sidebar drawer and overlay -->
<?php include 'sidebar.php'; ?>

<!-- Responsive header: hamburger menu inside header -->
<header class="fixed-header w-full fixed top-0 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:rgb(25, 72, 49);">
  <div class="flex items-center space-x-4">
    <!-- Hamburger button (always shown on mobile, hidden on desktop) -->
    <button 
      id="sidebarToggle" 
      class="md:hidden mr-2 p-2 rounded bg-[var(--cvsu-green-dark)] text-white shadow-lg focus:outline-none flex items-center justify-center"
      aria-label="Open sidebar"
      type="button"
    >
      <svg id="sidebarToggleIcon" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <!-- Hamburger bars (default, will swap with X via JS) -->
        <path id="hamburgerIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
        <!-- X icon (hidden by default, shown when sidebar is open) -->
        <path id="closeIcon" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
    <h1 class="text-lg md:text-2xl font-bold text-white">
      <?php echo htmlspecialchars($scope) . " ADMIN DASHBOARD"; ?>
    </h1>
  </div>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A10.95 10.95 0 0112 15c2.485 0 4.779.91 6.879 2.404M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  </div>
</header>

<!-- Main Content Area -->
<main class="main-content flex-1 pt-20 px-1 md:px-8">
  <div class="grid grid-cols-1 lg:grid-cols-1 gap-3 mb-6">
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
  
  <!-- Population per College -->
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg mb-8">
    <h2 class="text-base md:text-lg font-semibold text-gray-700 mb-4">Population of Voters per Colleges</h2>
    <canvas id="collegeChart" class="w-full h-64"></canvas>
  </div>
  
  <!-- Recent Elections Section -->
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg mb-8">
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
                <td class="px-2 py-1 text-xs font-semibold rounded-full 
                  <?= $election['status'] === 'ongoing' ? 'bg-green-100 text-green-800' : 
                     ($election['status'] === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                  <?= ucfirst($election['status']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500">No elections found for your assignment.</p>
    <?php endif; ?>
  </div>
  <div class="p-5">
    <?php include 'footer.php'; ?>
  </div>
</main>

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
  options: { plugins: { legend: { display: false } } }
});

// Sidebar toggle JS for hamburger/X icon
document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const navLinks = sidebar ? sidebar.querySelectorAll('a') : [];
  const hamburgerIcon = document.getElementById('hamburgerIcon');
  const closeIcon = document.getElementById('closeIcon');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('-translate-x-full');
    if (overlay) {
      overlay.classList.remove('hidden');
      setTimeout(() => overlay.classList.add('opacity-100'), 10);
    }
    document.body.style.overflow = 'hidden';
    if (hamburgerIcon) hamburgerIcon.classList.add('hidden');
    if (closeIcon) closeIcon.classList.remove('hidden');
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('-translate-x-full');
    if (overlay) {
      overlay.classList.add('hidden');
      overlay.classList.remove('opacity-100');
    }
    document.body.style.overflow = '';
    if (hamburgerIcon) hamburgerIcon.classList.remove('hidden');
    if (closeIcon) closeIcon.classList.add('hidden');
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      if (!sidebar) return;
      if (sidebar.classList.contains('-translate-x-full')) openSidebar();
      else closeSidebar();
    });
  }

  if (overlay) overlay.addEventListener('click', closeSidebar);

  navLinks.forEach(link => {
    link.addEventListener('click', function () {
      if (window.innerWidth < 768) closeSidebar();
    });
  });

  function handleResize() {
    if (!sidebar) return;
    if (window.innerWidth >= 768) {
      sidebar.classList.remove('-translate-x-full');
      if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('opacity-100');
      }
      document.body.style.overflow = '';
      if (hamburgerIcon) hamburgerIcon.classList.remove('hidden');
      if (closeIcon) closeIcon.classList.add('hidden');
    } else {
      sidebar.classList.add('-translate-x-full');
      if (hamburgerIcon) hamburgerIcon.classList.remove('hidden');
      if (closeIcon) closeIcon.classList.add('hidden');
    }
  }

  window.addEventListener('resize', handleResize);
  handleResize();
});
</script>
</body>
</html>