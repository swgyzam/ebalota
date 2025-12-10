<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- DB Connection ---
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
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

// --- Check if logged in and super admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$currentUserId = (int) $_SESSION['user_id'];

// --- Handle Activate/Deactivate/Delete ---
if (isset($_GET['action'], $_GET['id'])) {
    $userId = (int) $_GET['id'];
    $action = $_GET['action'];

    // Verify user exists and is not current user and not admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $userId !== $currentUserId && empty($user['is_admin'])) {
        if ($action === 'toggle') {
            // Always toggle is_verified now
            $stmt = $pdo->prepare("UPDATE users SET is_verified = NOT is_verified WHERE user_id = ?");
            $stmt->execute([$userId]);
            $_SESSION['message'] = "User status updated successfully";
            header('Location: manage_users.php');
            exit();
        } elseif ($action === 'delete') {
            try {
                $pdo->beginTransaction();

                // Debug: Log the user ID being deleted
                error_log("Attempting to delete user ID: " . $userId);

                // Delete votes
                $stmt = $pdo->prepare("DELETE FROM votes WHERE voter_id = ?");
                $stmt->execute([$userId]);
                $votesDeleted = $stmt->rowCount();
                error_log("Deleted $votesDeleted vote records");

                // Delete from activity_logs (FK constraint to users)
                try {
                    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $activityDeleted = $stmt->rowCount();
                    error_log("Deleted $activityDeleted activity_log records");
                } catch (Exception $e) {
                    error_log("No activity_logs table or error: " . $e->getMessage());
                }

                // Example: Delete from user_sessions table if it exists
                try {
                    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $sessionsDeleted = $stmt->rowCount();
                    error_log("Deleted $sessionsDeleted session records");
                } catch (Exception $e) {
                    // Table might not exist, continue
                    error_log("No user_sessions table or error: " . $e->getMessage());
                }

                // Example: Delete from user_logs table if it exists
                try {
                    $stmt = $pdo->prepare("DELETE FROM user_logs WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $logsDeleted = $stmt->rowCount();
                    error_log("Deleted $logsDeleted log records");
                } catch (Exception $e) {
                    // Table might not exist, continue
                    error_log("No user_logs table or error: " . $e->getMessage());
                }

                // Finally, delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userDeleted = $stmt->rowCount();
                error_log("Deleted $userDeleted user record");

                if ($userDeleted === 0) {
                    throw new Exception("User not found or already deleted");
                }

                $pdo->commit();

                $_SESSION['message'] = "User and all related records deleted permanently";
                header('Location: manage_users.php');
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Delete user error: ' . $e->getMessage());
                $_SESSION['error'] = "Delete failed: " . $e->getMessage();
                header('Location: manage_users.php');
                exit();
            }
        }
    } else {
        $_SESSION['error'] = "Cannot delete this user";
        header('Location: manage_users.php');
        exit();
    }
}

// --- Filtering ---
$filterPosition = isset($_GET['position']) ? $_GET['position'] : '';
$filterQuery    = '';
$params         = [];

if (!empty($filterPosition)) {
    if ($filterPosition === 'others') {
        // New: filter by Others members (custom uploaded voters)
        $filterQuery = " AND is_other_member = 1";
    } else {
        $filterQuery         = " AND position = :position";
        $params[':position'] = $filterPosition;
    }
}

// --- Pagination Setup ---
$perPage = 15;
$page    = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset  = ($page - 1) * $perPage;

// --- Count total users (non-admins) ---
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'voter' $filterQuery");
$totalStmt->execute($params);
$totalUsers = (int) $totalStmt->fetchColumn();
$totalPages = $totalUsers > 0 ? (int) ceil($totalUsers / $perPage) : 1;

// --- Fetch users page ---
$stmt = $pdo->prepare("
    SELECT * 
    FROM users 
    WHERE role = 'voter' 
    $filterQuery 
    ORDER BY user_id DESC 
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }

    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }

    .card {
      transition: all 0.3s ease;
      border-radius: 0.75rem;
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
                  0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .btn-primary {
      background-color: var(--cvsu-green);
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      background-color: var(--cvsu-green-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-danger {
      background-color: #ef4444;
      transition: all 0.3s ease;
    }

    .btn-danger:hover {
      background-color: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-warning {
      background-color: #f59e0b;
      transition: all 0.3s ease;
    }

    .btn-warning:hover {
      background-color: #d97706;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .loading-spinner {
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top: 3px solid white;
      width: 20px;
      height: 20px;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .table-hover tbody tr:hover {
      background-color: #f3f4f6;
    }
  </style>
</head>
<body class="bg-white font-sans text-gray-900">
  <div class="flex min-h-screen">
    <?php include 'super_admin_sidebar.php'; ?>
    <?php
    $role = $_SESSION['role'] ?? '';
    if ($role === 'super_admin') {
        include 'super_admin_change_password_modal.php';
    } else {
        include 'admin_change_password_modal.php';
    }
    ?>

    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-xl rounded-xl mb-8">
        <div class="flex items-center space-x-4">
          <div class="w-14 h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-users text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Manage Users</h1>
            <p class="text-green-100 mt-1">Administer all registered users in the system</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="restrict_users.php"
             class="bg-red-600 hover:bg-red-500 px-5 py-2.5 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-user-slash mr-2"></i>Restrict Users
          </a>
        </div>
      </header>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Users</p>
              <p class="text-2xl font-bold text-gray-900"><?= $totalUsers ?></p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fas fa-check-circle text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Verified</p>
              <p class="text-2xl font-bold text-gray-900">
                <?php 
                $verifiedStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_verified = 1");
                $verifiedStmt->execute();
                echo (int) $verifiedStmt->fetchColumn();
                ?>
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <i class="fas fa-user-clock text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Pending</p>
              <p class="text-2xl font-bold text-gray-900">
                <?php 
                $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'voter' AND is_verified = 0");
                $pendingStmt->execute();
                echo (int) $pendingStmt->fetchColumn();
                ?>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Card -->
      <div class="bg-white rounded-xl shadow-md p-6 mb-8 card border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">Filter Users</h2>
            <p class="text-gray-600 text-sm">Filter users by position or status</p>
          </div>
          <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
              <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position</label>
              <div class="relative">
                <select name="position" id="position"
                        onchange="this.form.submit()"
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Positions</option>
                  <option value="student"      <?= $filterPosition === 'student'      ? 'selected' : '' ?>>Student</option>
                  <option value="academic"     <?= $filterPosition === 'academic'     ? 'selected' : '' ?>>Faculty</option>
                  <option value="non-academic" <?= $filterPosition === 'non-academic' ? 'selected' : '' ?>>Non-Academic</option>
                  <option value="others"       <?= $filterPosition === 'others'       ? 'selected' : '' ?>>Others</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>
            <button type="button"
                    onclick="window.location.href='manage_users.php'"
                    class="self-end bg-gray-200 hover:bg-gray-300 px-4 py-2.5 rounded-lg font-medium transition flex items-center">
              <i class="fas fa-sync-alt mr-2"></i>Reset
            </button>
          </form>
        </div>
      </div>

      <!-- Alert Messages -->
      <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?= htmlspecialchars($_SESSION['message']) ?></span>
        </div>
        <?php unset($_SESSION['message']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
          <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error']) ?></span>
        </div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <!-- Users Table -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden card border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="text-xl font-bold text-gray-800">User List</h2>
          <div class="text-sm text-gray-500">
            Showing <?= count($users) ?> of <?= $totalUsers ?> users
          </div>
        </div>

        <!-- fixed height, scroll sa loob -->
        <div class="overflow-x-auto" style="max-height: 420px; overflow-y: auto;">
          <table class="min-w-full divide-y divide-gray-200 table-hover text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verified</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered At</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php if (empty($users)) : ?>
                <tr>
                  <td colspan="7" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                      <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-users text-gray-400 text-2xl"></i>
                      </div>
                      <h3 class="text-lg font-medium text-gray-900 mb-1">No users found</h3>
                      <p class="text-gray-500">Try adjusting your filter settings</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td class="px-4 py-2 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                          <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-600 font-semibold">
                              <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                            </span>
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 capitalize">
                        <?= htmlspecialchars($user['position']) ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if (!empty($user['is_verified'])): ?>
                        <span class="status-badge bg-green-100 text-green-800">
                          <i class="fas fa-check-circle mr-1"></i>Verified
                        </span>
                      <?php else: ?>
                        <span class="status-badge bg-red-100 text-red-800">
                          <i class="fas fa-times-circle mr-1"></i>Not Verified
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <?= htmlspecialchars(date('M d, Y h:i A', strtotime($user['created_at']))) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                      <a href="?action=toggle&id=<?= (int) $user['user_id'] ?><?= !empty($filterPosition) ? '&position=' . urlencode($filterPosition) : '' ?>"
                         class="btn-warning text-white px-2.5 py-1 rounded-md mr-2 inline-flex items-center text-xs">
                        <i class="fas fa-sync-alt mr-1"></i>
                        <?= !empty($user['is_verified']) ? 'Deactivate' : 'Activate' ?>
                      </a>
                      <a href="?action=delete&id=<?= (int) $user['user_id'] ?>"
                         class="btn-danger text-white px-2.5 py-1 rounded-md inline-flex items-center delete-btn text-xs"
                         data-user-name="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                        <i class="fas fa-trash-alt mr-1"></i>Delete
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="bg-white px-6 py-4 border-t border-gray-200 flex items-center justify-between">
          <div class="text-sm text-gray-700">
            <?php
              $from = $totalUsers > 0 ? max(1, ($page - 1) * $perPage + 1) : 0;
              $to   = $totalUsers > 0 ? min($page * $perPage, $totalUsers) : 0;
            ?>
            Showing <span class="font-medium"><?= $from ?></span> to
            <span class="font-medium"><?= $to ?></span> of
            <span class="font-medium"><?= $totalUsers ?></span> results
          </div>
          <div class="flex space-x-2">
            <?php if ($page > 1): ?>
              <a href="?page=<?= $page - 1 ?>&position=<?= urlencode($filterPosition) ?>"
                 class="px-3 py-1 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300 flex items-center">
                <i class="fas fa-chevron-left mr-1"></i> Previous
              </a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage   = min($totalPages, $page + 2);

            if ($startPage > 1) {
                echo '<a href="?page=1&position=' . urlencode($filterPosition) . '" class="px-3 py-1 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">1</a>';
                if ($startPage > 2) {
                    echo '<span class="px-2 text-gray-500">...</span>';
                }
            }

            for ($p = $startPage; $p <= $endPage; $p++): ?>
              <a href="?page=<?= $p ?>&position=<?= urlencode($filterPosition) ?>"
                 class="px-3 py-1 rounded-md <?= $p === $page ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                <?= $p ?>
              </a>
            <?php endfor;

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    echo '<span class="px-2 text-gray-500">...</span>';
                }
                echo '<a href="?page=' . $totalPages . '&position=' . urlencode($filterPosition) .
                     '" class="px-3 py-1 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">' . $totalPages . '</a>';
            }
            ?>

            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page + 1 ?>&position=<?= urlencode($filterPosition) ?>"
                 class="px-3 py-1 rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300 flex items-center">
                Next <i class="fas fa-chevron-right ml-1"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl flex flex-col items-center">
      <div class="loading-spinner mb-4"></div>
      <p class="text-gray-700">Processing, please wait...</p>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
      <div class="flex items-center mb-4">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
          <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900">Confirm Permanent Delete</h3>
      </div>
      <p class="text-gray-700 mb-6">
        Are you sure you want to permanently delete <strong id="deleteUserName"></strong>?
        This will delete the user and ALL their related records including votes.
        This action cannot be undone.
      </p>
      <div class="flex justify-end space-x-3">
        <button id="cancelDelete"
                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">
          Cancel
        </button>
        <button id="confirmDelete"
                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
          Delete Permanently
        </button>
      </div>
    </div>
  </div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const deleteModal     = document.getElementById('deleteModal');
    const deleteUserName  = document.getElementById('deleteUserName');
    const cancelDelete    = document.getElementById('cancelDelete');
    const confirmDelete   = document.getElementById('confirmDelete');
    const loadingOverlay  = document.getElementById('loadingOverlay');
    let deleteUrl = '';

    // Handle delete button clicks
    document.querySelectorAll('.delete-btn').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        const userName = this.getAttribute('data-user-name');
        deleteUrl = this.getAttribute('href');
        deleteUserName.textContent = userName;
        deleteModal.classList.remove('hidden');
      });
    });

    // Cancel delete
    cancelDelete.addEventListener('click', function() {
      deleteModal.classList.add('hidden');
    });

    // Confirm delete - use traditional navigation
    confirmDelete.addEventListener('click', function() {
      deleteModal.classList.add('hidden');
      loadingOverlay.classList.remove('hidden');
      window.location.href = deleteUrl;
    });

    // Close modal when clicking outside
    deleteModal.addEventListener('click', function(e) {
      if (e.target === deleteModal) {
        deleteModal.classList.add('hidden');
      }
    });
  });
</script>
</body>
</html>
