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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Filtering ---
$filterScope = isset($_GET['scope']) ? $_GET['scope'] : '';
$scopeQuery = '';
$params = [];

if (!empty($filterScope)) {
    $scopeQuery = " AND assigned_scope = :scope";
    $params[':scope'] = $filterScope;
}

// --- Fetch admins ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' $scopeQuery ORDER BY user_id DESC");
$stmt->execute($params);
$admins = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Admins - Super Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    @media (max-width: 767px) {
      .main-content { margin-left: 0 !important; padding-left: 0 !important; padding-right: 0 !important; }
      .fixed-header { left: 0 !important; width: 100vw !important; }
      .responsive-table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .responsive-table { min-width: 900px; width: 900px; display: block; }
    }
    @media (min-width: 768px) {
      .main-content { margin-left: 16rem !important; }
      .fixed-header { left: 16rem !important; width: calc(100vw - 16rem) !important; }
      .responsive-table-container { width: 100%; overflow-x: visible; }
      .responsive-table { min-width: 900px; width: 100%; display: table; }
    }
    /* Scrollbar styling */
    .responsive-table-container::-webkit-scrollbar { height: 8px; background: #e5e7eb; }
    .responsive-table-container::-webkit-scrollbar-thumb { background: #1E6F46; border-radius: 4px; }
    .responsive-table-container { scrollbar-color: #1E6F46 #e5e7eb; scrollbar-width: thin; }
  </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">

  <?php include 'super_admin_sidebar.php'; ?>

  <!-- Header -->
  <header class="fixed-header w-full fixed top-0 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:rgb(25,72,49);">
    <div class="flex items-center space-x-4">
      <!-- Hamburger -->
      <button id="sidebarToggle" class="md:hidden mr-2 p-2 rounded bg-[var(--cvsu-green-dark)] text-white shadow-lg focus:outline-none flex items-center justify-center">
        <svg id="sidebarToggleIcon" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path id="hamburgerIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
          <path id="closeIcon" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
      <h1 class="text-xl font-bold text-white">Manage Admins</h1>
    </div>
    <div class="flex space-x-2">
      <button id="openModalBtn" onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition text-xs">+ Add Admin</button>
    </div>
  </header>

  <main class="main-content flex-1 pt-20 px-2 md:px-8">
    <!-- Filter -->
    <form method="GET" class="mb-4">
      <label for="scope" class="mr-2">Filter by Scope:</label>
      <select name="scope" id="scope" onchange="this.form.submit()" class="px-2 py-1 border border-gray-300 rounded">
        <option value="">All</option>
        <?php
          $scopes = ['CAFENR','CEIT','CAS','CVMBS','CED','CEMDS','CSPEAR','CCJ','CON','CTHM','COM','GS-OLC','FACULTY_ASSOCIATION','COOP','NON_ACADEMIC'];
          foreach ($scopes as $scope) {
            $selected = $filterScope === $scope ? 'selected' : '';
            echo "<option value=\"$scope\" $selected>$scope</option>";
          }
        ?>
      </select>
    </form>

    <!-- Table -->
    <div class="responsive-table-container bg-white rounded shadow mt-4">
      <table class="responsive-table min-w-full divide-y divide-gray-200">
        <thead class="bg-[var(--cvsu-green-light)] text-white">
          <tr>
            <th class="px-6 py-3 text-left text-sm font-semibold">Full Name</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Assigned Scope</th>
            <th class="px-6 py-3 text-center text-sm font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($admins)): ?>
            <tr>
              <td colspan="4" class="px-6 py-4 text-center text-gray-500">No admin accounts found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($admins as $admin): ?>
              <tr>
                <td class="px-6 py-4"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($admin['email']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($admin['assigned_scope']) ?></td>
                <td class="px-6 py-4 text-center space-x-2">
                  <button onclick='triggerEditAdmin(<?= $admin["user_id"] ?>)' class="px-3 py-1 text-sm rounded bg-yellow-500 text-white hover:bg-yellow-600 transition">Edit</button>
                  <form action="delete_admin.php" method="POST" class="inline" onsubmit="return confirm('Delete this admin?')">
                    <input type="hidden" name="user_id" value="<?= $admin['user_id'] ?>">
                    <button type="submit" class="px-3 py-1 text-sm rounded bg-red-600 text-white hover:bg-red-700 transition">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php include 'admin_modal_create.php'; ?>
    <?php include 'admin_modal_update.php'; ?>
  </main>

  <?php include 'super_admin_sidebar.php'; ?>

<script>
function triggerEditAdmin(userId) {
  fetch('get_admin.php?user_id=' + userId)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        document.getElementById('update_user_id').value = data.data.user_id;
        document.getElementById('update_first_name').value = data.data.first_name;
        document.getElementById('update_last_name').value = data.data.last_name;
        document.getElementById('update_email').value = data.data.email;
        document.getElementById('updateModal').classList.remove('hidden');
      } else {
        alert("Admin not found.");
      }
    })
    .catch(() => alert("Fetch failed"));
}

// Sidebar toggle (same as Manage Users)
document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const hamburgerIcon = document.getElementById('hamburgerIcon');
  const closeIcon = document.getElementById('closeIcon');

  function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    setTimeout(() => overlay.classList.add('opacity-100'), 10);
    document.body.style.overflow = 'hidden';
    hamburgerIcon.classList.add('hidden');
    closeIcon.classList.remove('hidden');
  }

  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    overlay.classList.remove('opacity-100');
    document.body.style.overflow = '';
    hamburgerIcon.classList.remove('hidden');
    closeIcon.classList.add('hidden');
  }

  toggleBtn.addEventListener('click', function () {
    if (sidebar.classList.contains('-translate-x-full')) openSidebar();
    else closeSidebar();
  });

  overlay.addEventListener('click', closeSidebar);

  function handleResize() {
    if (window.innerWidth >= 768) {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.add('hidden');
      overlay.classList.remove('opacity-100');
      document.body.style.overflow = '';
      hamburgerIcon.classList.remove('hidden');
      closeIcon.classList.add('hidden');
    } else {
      sidebar.classList.add('-translate-x-full');
      hamburgerIcon.classList.remove('hidden');
      closeIcon.classList.add('hidden');
    }
  }

  window.addEventListener('resize', handleResize);
  handleResize();
});
</script>
</body>
</html>
