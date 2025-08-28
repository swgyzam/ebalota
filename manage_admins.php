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
    die("DB Error: " . $e->getMessage());
}

// --- Auth check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Admins - Super Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
  </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">
  <div class="flex min-h-screen">
    <?php include 'super_admin_sidebar.php'; ?>

    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <h1 class="text-3xl font-extrabold">Manage Admins</h1>
        <div class="flex space-x-2">
          <button id="openModalBtn" onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">+ Add Admin</button>
        </div>
      </header>

      <!-- Filter by Scope -->
      <form method="GET" class="mb-4">
        <label for="scope" class="mr-2">Filter by Scope:</label>
        <select name="scope" id="scope" onchange="this.form.submit()" class="px-2 py-1 border border-gray-300 rounded">
          <option value="">All</option>
          <?php
            $scopes = ['CAFENR','CEIT','CAS','CVMBS','CED','CEMDS','CSPEAR','CCJ','CON','CTHM','COM','GS-OLC','FACULTY_ASSOCIATION','COOP','NON_ACADEMIC', 'CSG_ADMIN'];
            foreach ($scopes as $scope) {
              $selected = $filterScope === $scope ? 'selected' : '';
              echo "<option value=\"$scope\" $selected>$scope</option>";
            }
          ?>
        </select>
      </form>

      <!-- Admins Table -->
      <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full divide-y divide-gray-200">
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
                    <button 
                      onclick='triggerEditAdmin(<?= $admin["user_id"] ?>)'
                      class="px-3 py-1 text-sm rounded bg-yellow-500 text-white hover:bg-yellow-600 transition">Edit</button>
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
  </div>

<script>
let selectedAdmin = null;

function triggerEditAdmin(userId) {
  fetch('get_admin.php?user_id=' + userId)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        openUpdateModal(data.data);
      } else {
        alert("Admin not found.");
      }
    })
    .catch(() => alert("Fetch failed"));
}

function openUpdateModal(admin) {
  selectedAdmin = admin;

  document.getElementById('update_user_id').value = admin.user_id;
  document.getElementById('update_first_name').value = admin.first_name;
  document.getElementById('update_last_name').value = admin.last_name;
  document.getElementById('update_email').value = admin.email;

  const scopeSelect = document.getElementById('update_assigned_scope');
  const scopeValue = (admin.assigned_scope || '').trim();

  let matched = false;
  Array.from(scopeSelect.options).forEach(opt => {
    if (opt.value === scopeValue) {
      opt.selected = true;
      matched = true;
    } else {
      opt.selected = false;
    }
  });

  if (!matched && scopeValue) {
    const opt = document.createElement("option");
    opt.value = scopeValue;
    opt.textContent = scopeValue;
    opt.selected = true;
    scopeSelect.appendChild(opt);
  }

  document.getElementById('updateModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('update_first_name').focus(), 100);
}
</script>
</body>
</html>
