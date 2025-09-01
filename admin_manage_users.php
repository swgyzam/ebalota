<?php
session_start(); // <- enable sessions to avoid undefined $_SESSION warnings
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
    die("DB Error: " . $e->getMessage());
}

// --- Auth Check (adjust to your flow) ---
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
//     header('Location: login.php');
//     exit();
// }

// Safe defaults to prevent notices during testing
$currentRole   = $_SESSION['role'] ?? 'super_admin';
$assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// --- Scope Filter Function ---
function getScopeFilter($role, $scope) {
    $filter = ["sql" => "", "params" => []];

    if ($role === 'super_admin') {
        // sees all voters
        return $filter;
    }

    switch ($scope) {
        case 'FACULTY_ASSOCIATION':
            $filter["sql"] = " AND position = 'academic'";
            break;
        case 'NON_ACADEMIC':
            $filter["sql"] = " AND position = 'non-academic'";
            break;
        case 'COOP':
            $filter["sql"] = " AND is_coop_member = 1";
            break;
        case 'CSG_ADMIN':
            $filter["sql"] = " AND position = 'student'";
            break;
        default:
            $filter["sql"] = " AND position = 'student' AND UPPER(TRIM(department)) = :scope";
            $filter["params"][":scope"] = strtoupper(trim($scope));
            break;
    }
    return $filter;
}

// --- Fetch Users ---
$filter = getScopeFilter($currentRole, $assignedScope);
$sql = "SELECT * FROM users WHERE role = 'voter' " . $filter["sql"] . " ORDER BY user_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filter["params"]);
$users = $stmt->fetchAll();

// --- Check if Course column is needed ---
$hasStudent = false;
foreach ($users as $u) {
  if (strtolower($u['position']) === 'student') {
    $hasStudent = true;
    break;
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <!-- IMPORTANT for mobile responsiveness -->
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Users - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    ::-webkit-scrollbar{ width:6px; }
    ::-webkit-scrollbar-thumb{ background-color:#37A66B; border-radius:3px; }
  </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">
  <div class="flex min-h-screen">

    <?php include 'sidebar.php'; ?>

    <!-- Fixed Header (same pattern as other page) -->
    <header class="w-full fixed top-0 md:left-64 h-16 shadow z-10 flex items-center justify-between px-6 transition-all duration-300 bg-[var(--cvsu-green-dark)]">
      <div class="flex items-center gap-4">
        <!-- Hamburger (mobile only) -->
        <button id="menuToggle" class="md:hidden text-white focus:outline-none" aria-label="Open menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-2xl font-bold text-white">Manage Users</h1>
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-1 pt-20 px-4 md:px-8 md:ml-64 transition-all duration-300">
      <!-- Users Table -->
      <div class="mt-2 overflow-x-auto bg-white rounded-xl shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-[var(--cvsu-green-light)] text-white">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left font-semibold">Full Name</th>
              <th class="px-4 sm:px-6 py-3 text-left font-semibold">Email</th>
              <th class="px-4 sm:px-6 py-3 text-left font-semibold">Position</th>
              <th class="px-4 sm:px-6 py-3 text-left font-semibold">Department</th>
              <?php if ($hasStudent): ?>
                <th class="px-4 sm:px-6 py-3 text-left font-semibold">Course</th>
              <?php endif; ?>
              <th class="px-4 sm:px-6 py-3 text-center font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="<?= $hasStudent ? 6 : 5 ?>" class="px-4 sm:px-6 py-6 text-center text-gray-500">
                  No users found for your scope.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-4 sm:px-6 py-3"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                  <td class="px-4 sm:px-6 py-3 break-all"><?= htmlspecialchars($user['email']) ?></td>
                  <td class="px-4 sm:px-6 py-3"><?= htmlspecialchars($user['position'] ?? '-') ?></td>
                  <td class="px-4 sm:px-6 py-3"><?= htmlspecialchars($user['department'] ?? '-') ?></td>

                  <?php if ($hasStudent): ?>
                    <td class="px-4 sm:px-6 py-3">
                      <?php if (strtolower($user['position']) === 'student'): ?>
                        <?= htmlspecialchars($user['course'] ?? '-') ?>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>

                  <td class="px-4 sm:px-6 py-3 text-center space-x-2">
                    <button 
                      onclick='triggerEditUser(<?= (int)$user["user_id"] ?>)'
                      class="px-3 py-1 rounded bg-yellow-500 text-white hover:bg-yellow-600 transition">Edit</button>
                    <form action="delete_user.php" method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                      <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                      <button type="submit" class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700 transition">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (file_exists('user_modal_update.php')) include 'user_modal_update.php'; ?>
    </main>
  </div>

  <script>
    // Elements from sidebar.php (don't duplicate the overlay element)
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const menuToggle = document.getElementById("menuToggle");

    if (menuToggle && sidebar && overlay) {
      menuToggle.addEventListener("click", () => {
        sidebar.classList.remove("-translate-x-full");
        overlay.classList.remove("hidden");
      });

      overlay.addEventListener("click", () => {
        sidebar.classList.add("-translate-x-full");
        overlay.classList.add("hidden");
      });

      // Optional: close with ESC
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          sidebar.classList.add("-translate-x-full");
          overlay.classList.add("hidden");
        }
      });
    }

    // Modal helpers
    let selectedUser = null;

    function triggerEditUser(userId) {
      fetch('get_user.php?user_id=' + userId)
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            openUpdateModal(data.data);
          } else {
            alert("User not found.");
          }
        })
        .catch(() => alert("Fetch failed"));
    }

    function openUpdateModal(user) {
      selectedUser = user;

      document.getElementById('update_user_id').value = user.user_id;
      document.getElementById('update_first_name').value = user.first_name ?? '';
      document.getElementById('update_last_name').value = user.last_name ?? '';
      document.getElementById('update_email').value = user.email ?? '';
      document.getElementById('update_department').value = user.department ?? '';
      document.getElementById('update_course').value = user.course ?? '';
      document.getElementById('update_position').value = user.position ?? '';

      document.getElementById('updateModal').classList.remove('hidden');
      setTimeout(() => document.getElementById('update_first_name').focus(), 100);
    }
  </script>
</body>
</html>
