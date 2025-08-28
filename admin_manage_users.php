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

// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header('Location: login.php');
    exit();
}

$currentRole  = $_SESSION['role'];
$assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// --- Scope Filter Function ---
function getScopeFilter($role, $scope) {
    $filter = ["sql" => "", "params" => []];

    if ($role === 'super_admin') {
        // super admin sees all users
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
            // assume college scope (CEIT, CAS, CEMDS, CCJ, CAFENR, etc.)
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users - Admin Panel</title>
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
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <h1 class="text-3xl font-extrabold">Manage Users</h1>
      </header>

      <!-- Users Table -->
      <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-[var(--cvsu-green-light)] text-white">
            <tr>
              <th class="px-6 py-3 text-left text-sm font-semibold">Full Name</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Position</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Department</th>
              <?php if ($hasStudent): ?>
                <th class="px-6 py-3 text-left text-sm font-semibold">Course</th>
              <?php endif; ?>
              <th class="px-6 py-3 text-center text-sm font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="<?= $hasStudent ? 6 : 5 ?>" class="px-6 py-4 text-center text-gray-500">No users found for your scope.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['email']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['position'] ?? '-') ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['department'] ?? '-') ?></td>

                  <?php if ($hasStudent): ?>
                    <td class="px-6 py-4">
                      <?php if (strtolower($user['position']) === 'student'): ?>
                        <?= htmlspecialchars($user['course'] ?? '-') ?>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>

                  <td class="px-6 py-4 text-center space-x-2">
                    <button 
                      onclick='triggerEditUser(<?= $user["user_id"] ?>)'
                      class="px-3 py-1 text-sm rounded bg-yellow-500 text-white hover:bg-yellow-600 transition">Edit</button>
                    <form action="delete_user.php" method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                      <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                      <button type="submit" class="px-3 py-1 text-sm rounded bg-red-600 text-white hover:bg-red-700 transition">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php 
      if (file_exists('user_modal_update.php')) {
          include 'user_modal_update.php'; 
      }
      ?>
    </main>
  </div>

<script>
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
  document.getElementById('update_first_name').value = user.first_name;
  document.getElementById('update_last_name').value = user.last_name;
  document.getElementById('update_email').value = user.email;
  document.getElementById('update_department').value = user.department ?? '';
  document.getElementById('update_course').value = user.course ?? '';
  document.getElementById('update_position').value = user.position ?? '';

  document.getElementById('updateModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('update_first_name').focus(), 100);
}
</script>
</body>
</html>
