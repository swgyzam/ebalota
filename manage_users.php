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

// --- Session timeout 1 hour ---
$timeout_duration = 3600;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Session expired. Please login again.');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// --- Check if logged in and admin ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit();
}

$currentUserId = $_SESSION['user_id'];

// --- Handle Activate/Deactivate/Delete ---
if (isset($_GET['action'], $_GET['id'])) {
    $userId = (int)$_GET['id'];
    $action = $_GET['action'];

    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $userId !== $currentUserId && !$user['is_admin']) {
        if ($action === 'toggle') {
            if (isset($_GET['position']) && $_GET['position'] === 'coop') {
                $stmt = $pdo->prepare("UPDATE users SET migs_status = NOT migs_status WHERE user_id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_verified = NOT is_verified WHERE user_id = ?");
            }
            $stmt->execute([$userId]);
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
    }

    header('Location: manage_users.php');
    exit();
}

// --- Filtering ---
$filterPosition = isset($_GET['position']) ? $_GET['position'] : '';
$filterQuery = '';
$params = [];

if (!empty($filterPosition)) {
    if ($filterPosition === 'coop') {
        $filterQuery = " AND is_coop_member = 1";
    } else {
        $filterQuery = " AND position = :position";
        $params[':position'] = $filterPosition;
    }
}

$isCoopFilter = ($filterPosition === 'coop');

// --- Pagination Setup ---
$perPage = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// --- Count total users (non-admins) ---
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 0 $filterQuery");
$totalStmt->execute($params);
$totalUsers = $totalStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// --- Fetch users page ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE is_admin = 0 $filterQuery ORDER BY user_id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
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
  </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">

  <div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-8 ml-64">
      <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <h1 class="text-3xl font-extrabold">Manage Users</h1>
        <a href="admin_dashboard.php" class="px-4 py-2 bg-[var(--cvsu-green)] text-white rounded hover:bg-[var(--cvsu-green-light)] transition">Back to Dashboard</a>
      </header>

      <form method="GET" class="mb-4">
        <label for="position" class="mr-2">Filter by Position:</label>
        <select name="position" id="position" onchange="this.form.submit()" class="px-2 py-1 border border-gray-300 rounded">
          <option value="">All</option>
          <option value="student" <?= $filterPosition === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="academic" <?= $filterPosition === 'academic' ? 'selected' : '' ?>>Faculty</option>
          <option value="non-academic" <?= $filterPosition === 'non-academic' ? 'selected' : '' ?>>Non-Academic</option>
          <option value="coop" <?= $filterPosition === 'coop' ? 'selected' : '' ?>>COOP</option>
        </select>
      </form>

      <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-[var(--cvsu-green-light)] text-white">
            <tr>
              <th class="px-6 py-3 text-left text-sm font-semibold">ID</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Name</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Position</th>
              <?php if ($isCoopFilter): ?>
                <th class="px-6 py-3 text-left text-sm font-semibold">MIGS Status</th>
              <?php endif; ?>
              <th class="px-6 py-3 text-left text-sm font-semibold">Verified</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Registered At</th>
              <th class="px-6 py-3 text-center text-sm font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($users)) : ?>
              <tr>
                <td colspan="<?= $isCoopFilter ? '8' : '7' ?>" class="px-6 py-4 text-center text-gray-500">No users found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['user_id']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                  <td class="px-6 py-4"><?= htmlspecialchars($user['email']) ?></td>
                  <td class="px-6 py-4 capitalize"><?= htmlspecialchars($user['position']) ?></td>
                  <?php if ($isCoopFilter): ?>
                    <td class="px-6 py-4">
                      <?= $user['migs_status'] ? '<span class="text-green-600 font-semibold">MIGS</span>' : '<span class="text-gray-600 font-semibold">Not MIGS</span>' ?>
                    </td>
                  <?php endif; ?>
                  <td class="px-6 py-4">
                    <?php if ($user['is_verified']): ?>
                      <span class="text-green-600 font-semibold">Verified</span>
                    <?php else: ?>
                      <span class="text-red-600 font-semibold">Not Verified</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-6 py-4"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($user['created_at']))) ?></td>
                  <td class="px-6 py-4 text-center space-x-2">
                    <a href="?action=toggle&id=<?= $user['user_id'] ?><?= !empty($filterPosition) ? '&position=' . urlencode($filterPosition) : '' ?>"
                      onclick="return confirm('Are you sure you want to toggle <?= $filterPosition === 'coop' ? 'MIGS status' : 'verification status' ?> for this user?')"
                      class="px-3 py-1 text-sm rounded bg-yellow-400 text-white hover:bg-yellow-500 transition">
                      <?= ($filterPosition === 'coop' && $user['migs_status']) 
                            ? 'Deactivate MIGS' 
                            : ($filterPosition === 'coop' 
                                ? 'Activate MIGS' 
                                : ($user['is_verified'] ? 'Deactivate' : 'Activate')) ?>
                    </a>
                    <a href="?action=delete&id=<?= $user['user_id'] ?>"
                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"
                       class="px-3 py-1 text-sm rounded bg-red-600 text-white hover:bg-red-700 transition">
                      Delete
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-6 flex justify-center space-x-2">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>&position=<?= urlencode($filterPosition) ?>" class="px-3 py-1 bg-gray-300 rounded hover:bg-gray-400">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?page=<?= $p ?>&position=<?= urlencode($filterPosition) ?>"
             class="px-3 py-1 rounded <?= $p === $page ? 'bg-[var(--cvsu-green-light)] text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>&position=<?= urlencode($filterPosition) ?>" class="px-3 py-1 bg-gray-300 rounded hover:bg-gray-400">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </main>
  </div>

</body>
</html>
