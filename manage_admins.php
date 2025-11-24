<?php
// manage_admins.php
session_start();
date_default_timezone_set('Asia/Manila');

include_once 'admin_functions.php'; // taxonomy & helpers

// --- CSRF token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 $PAGE_CSRF = $_SESSION['csrf_token'];

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
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    die('Database connection error.');
}

// --- Auth check ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// --- POST actions (AJAX from this page) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    $action   = $_POST['action'];
    $response = ['success' => false, 'message' => ''];

    try {
        // Toggle status
        if ($action === 'toggle_status') {
            $adminId   = (int)($_POST['admin_id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'inactive';

            $allowed = ['active', 'inactive', 'suspended'];
            if (!in_array($newStatus, $allowed, true)) {
                throw new Exception('Invalid status value');
            }

            $pdo->beginTransaction();
            
            // Updated SELECT to include admin_title
            $stmt = $pdo->prepare("
                SELECT user_id, role, admin_status, scope_category, assigned_scope, assigned_scope_1, admin_title 
                FROM users 
                WHERE user_id = ? AND role = 'admin' 
                FOR UPDATE
            ");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            if (!$admin) {
                throw new Exception('Admin not found');
            }

            // Enforce one ACTIVE admin per logical scope
            if ($newStatus === 'active') {
                $conditions = [];
                $params     = [];

                if (!empty($admin['scope_category'])) {
                    $conditions[]              = "scope_category = :scope_category";
                    $params[':scope_category'] = $admin['scope_category'];
                }
                if (!empty($admin['assigned_scope'])) {
                    $conditions[]              = "assigned_scope = :assigned_scope";
                    $params[':assigned_scope'] = $admin['assigned_scope'];
                }
                if (!empty($admin['assigned_scope_1'])) {
                    $conditions[]               = "assigned_scope_1 = :assigned_scope_1";
                    $params[':assigned_scope_1']= $admin['assigned_scope_1'];
                }

                /**
                 * SPECIAL CASE:
                 * For Non-Academic-Student and Others-Default,
                 * uniqueness should be per SEAT:
                 *   (scope_category, assigned_scope, assigned_scope_1, admin_title)
                 *
                 * This means:
                 *  - Music Org admin & Esports Org admin can both be active
                 *    (different admin_title → different seat)
                 *  - But two "Music Org" admins in the same scope → only 1 active.
                 */
                if (
                    in_array($admin['scope_category'], ['Non-Academic-Student', 'Others-Default'], true) &&
                    !empty($admin['admin_title'])
                ) {
                    $conditions[]              = "admin_title = :admin_title";
                    $params[':admin_title']    = $admin['admin_title'];
                }

                if ($conditions) {
                    $where = implode(' AND ', $conditions);
                    $sql   = "SELECT user_id, first_name, last_name, admin_title 
                              FROM users 
                              WHERE role = 'admin' AND admin_status = 'active' 
                                AND $where AND user_id != :cur";
                    $params[':cur'] = $adminId;

                    $check    = $pdo->prepare($sql);
                    $check->execute($params);
                    $existing = $check->fetch();

                    if ($existing) {
                        $pdo->rollBack();
                        throw new Exception(
                            "Cannot activate this admin. There is already an active admin ({$existing['admin_title']}: " .
                            "{$existing['first_name']} {$existing['last_name']}) with the same scope."
                        );
                    }
                }
            }

            $update = $pdo->prepare("UPDATE users SET admin_status = ? WHERE user_id = ?");
            $update->execute([$newStatus, $adminId]);

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Admin status updated successfully';
        }

        // Inherit scope seat from previous admin with the same scope
        elseif ($action === 'inherit_scope') {
            $targetAdminId = (int)($_POST['target_admin_id'] ?? 0);

            if ($targetAdminId <= 0) {
                throw new Exception('Invalid target admin.');
            }

            $pdo->beginTransaction();

            // Fetch target admin
            $stmt = $pdo->prepare("
                SELECT user_id, admin_status, scope_category, assigned_scope, assigned_scope_1, admin_title 
                FROM users 
                WHERE user_id = ? AND role = 'admin'
                FOR UPDATE
            ");
            $stmt->execute([$targetAdminId]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new Exception('Target admin not found.');
            }

            if ($target['admin_status'] !== 'inactive') {
                throw new Exception('Target admin must be inactive to inherit scope.');
            }
            if (empty($target['scope_category'])) {
                throw new Exception('Target admin has no scope category.');
            }

            $scopeCategory  = $target['scope_category'];
            $assignedScope  = $target['assigned_scope'];
            $assignedScope1 = $target['assigned_scope_1'];
            $adminTitle     = $target['admin_title'];

            // Source admin: same scope_category + assigned_scope + assigned_scope_1
            // For Non-Academic-Student / Others-Default, also require same admin_title.
            $sql = "
                SELECT user_id, admin_status, admin_title, first_name, last_name
                FROM users
                WHERE role = 'admin'
                  AND user_id != :target
                  AND scope_category = :scope_category
                  AND assigned_scope = :assigned_scope
                  AND assigned_scope_1 = :assigned_scope_1
                  AND admin_status = 'inactive'
            ";
            $params = [
                ':target'          => $targetAdminId,
                ':scope_category'  => $scopeCategory,
                ':assigned_scope'  => $assignedScope,
                ':assigned_scope_1'=> $assignedScope1,
            ];

            if (in_array($scopeCategory, ['Non-Academic-Student','Others-Default'], true) && !empty($adminTitle)) {
                $sql .= " AND admin_title = :admin_title";
                $params[':admin_title'] = $adminTitle;
            }

            // pick the most recent previous admin (highest user_id) as source
            $sql .= " ORDER BY user_id DESC LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $source = $stmt->fetch();

            if (!$source) {
                throw new Exception('No previous inactive admin found with the same scope to inherit from.');
            }

            $sourceAdminId = (int)$source['user_id'];

            // 1) Remove any existing scope rows for this scope_category under the target admin
            $stmt = $pdo->prepare("
                DELETE FROM admin_scopes 
                WHERE user_id = :target_id 
                  AND scope_type = :scope_type
            ");
            $stmt->execute([
                ':target_id'  => $targetAdminId,
                ':scope_type' => $scopeCategory,
            ]);

            // 2) Transfer scope seat: move admin_scopes rows from source to target
            $stmt = $pdo->prepare("
                UPDATE admin_scopes
                SET user_id = :target_id
                WHERE user_id = :source_id
                  AND scope_type = :scope_type
            ");
            $stmt->execute([
                ':target_id'  => $targetAdminId,
                ':source_id'  => $sourceAdminId,
                ':scope_type' => $scopeCategory,
            ]);

            // 3) Transfer candidate ownership to the new admin
            $stmt = $pdo->prepare("
                UPDATE candidates
                SET created_by = :target_id
                WHERE created_by = :source_id
            ");
            $stmt->execute([
                ':target_id' => $targetAdminId,
                ':source_id' => $sourceAdminId,
            ]);

            // 4) Transfer custom positions (so new admin sees old admin's custom positions)
            $stmt = $pdo->prepare("
                UPDATE positions
                SET created_by = :target_id
                WHERE created_by = :source_id
            ");
            $stmt->execute([
                ':target_id' => $targetAdminId,
                ':source_id' => $sourceAdminId,
            ]);

            // 5) Transfer disabled default positions (so default positions you disabled stay disabled)
            $stmt = $pdo->prepare("
                UPDATE disabled_default_positions
                SET admin_id = :target_id
                WHERE admin_id = :source_id
            ");
            $stmt->execute([
                ':target_id' => $targetAdminId,
                ':source_id' => $sourceAdminId,
            ]);

            // Activate target admin after inheriting
            $stmt = $pdo->prepare("UPDATE users SET admin_status = 'active' WHERE user_id = :target_id");
            $stmt->execute([':target_id' => $targetAdminId]);

            $pdo->commit();

            $response['success'] = true;
            $response['message'] = "Scope successfully inherited from {$source['admin_title']} ({$source['first_name']} {$source['last_name']}).";
        }

        else {
            throw new Exception('Unknown action');
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// --- Filtering ---
 $filterScope      = $_GET['scope']       ?? '';
 $filterCollege    = $_GET['college']     ?? '';
 $filterCourse     = $_GET['course']      ?? '';
 $filterDepartment = $_GET['department']  ?? '';
 $filterYear       = $_GET['year']        ?? '';
 $filterAdminTitle = $_GET['admin_title'] ?? '';

 $scopeQuery = '';
 $params     = [];

if (!empty($filterScope)) {
    $scopeQuery         .= " AND u.scope_category = :scope";
    $params[':scope']    = $filterScope;
}

if (!empty($filterCollege)) {
    $scopeQuery          .= " AND u.assigned_scope = :college";
    $params[':college']   = $filterCollege;
}

if (!empty($filterCourse) && $filterScope === 'Academic-Student') {
    $scopeQuery         .= " AND u.assigned_scope_1 LIKE :course";
    $params[':course']   = '%' . $filterCourse . '%';
}

if (!empty($filterDepartment)) {
    if ($filterScope === 'Academic-Faculty') {
        $scopeQuery         .= " AND u.assigned_scope_1 LIKE :department";
        $params[':department']= '%' . $filterDepartment . '%';
    } elseif ($filterScope === 'Non-Academic-Employee') {
        $scopeQuery         .= " AND (u.assigned_scope LIKE :department OR u.assigned_scope_1 LIKE :department)";
        $params[':department']= '%' . $filterDepartment . '%';
    }
}

if (!empty($filterYear)) {
    $scopeQuery        .= " AND u.academic_year = :year";
    $params[':year']    = $filterYear;
}

// Fetch admins for this scope/college/course/department/year
 $stmt = $pdo->prepare("
    SELECT u.*, asd.scope_details 
    FROM users u 
    LEFT JOIN admin_scopes asd ON u.user_id = asd.user_id 
    WHERE u.role = 'admin' $scopeQuery 
    ORDER BY FIELD(u.admin_status, 'active','inactive','suspended'), u.user_id DESC
");
 $stmt->execute($params);
 $adminsRaw = $stmt->fetchAll();

// Build Admin Title filter options only for Non-Academic-Student / Others-Default
 $adminFilterOptions = [];
if (in_array($filterScope, ['Non-Academic-Student', 'Others-Default'], true)) {
    foreach ($adminsRaw as $row) {
        $title = trim($row['admin_title'] ?? '');
        if ($title === '') continue;
        $adminFilterOptions[$title] = $title;
    }
}

// Apply Admin Title filter (seat-level) for those two categories
if (!empty($filterAdminTitle) && in_array($filterScope, ['Non-Academic-Student', 'Others-Default'], true)) {
    $admins = array_values(array_filter(
        $adminsRaw,
        fn($a) => trim($a['admin_title'] ?? '') === $filterAdminTitle
    ));
} else {
    $admins = $adminsRaw;
}

// Stats
 $totalAdmins  = count($admins);
 $activeAdmins = count(array_filter(
    $admins,
    fn($a) => ($a['admin_status'] ?? '') === 'active'
));

// Helper: scope summary for display
function summarizeAssignedScopeShort(array $admin): string {
    $category        = $admin['scope_category']   ?? '';
    $scopeDetailsJson= $admin['scope_details']    ?? '';
    $assignedScope   = $admin['assigned_scope']   ?? '';
    $assignedScope1  = $admin['assigned_scope_1'] ?? '';

    $details = [];
    if ($scopeDetailsJson) {
        $tmp = json_decode($scopeDetailsJson, true);
        if (is_array($tmp)) $details = $tmp;
    }

    // Academic - Student
    if ($category === 'Academic-Student') {
        $college     = $details['college'] ?? $assignedScope;
        $courseCodes = [];

        if (!empty($details['courses']) && is_array($details['courses'])) {
            $courseCodes = $details['courses'];
        } elseif ($assignedScope1 && strpos($assignedScope1, 'Multiple: ') === 0) {
            $courseCodes = array_map('trim', explode(',', substr($assignedScope1, 9)));
        } elseif (!empty($assignedScope1)) {
            $courseCodes = [$assignedScope1];
        }

        if (empty($courseCodes) && $college) {
            return $college . " - All courses";
        } elseif (count($courseCodes) === 1) {
            return ($college ? $college . " - " : "") . $courseCodes[0];
        } else {
            return ($college ? $college . " - " : "") . "Multiple courses (" . count($courseCodes) . ")";
        }
    }

    // Academic - Faculty
    if ($category === 'Academic-Faculty') {
        $college   = $details['college'] ?? $assignedScope;
        $deptCodes = [];

        if (!empty($details['departments']) && is_array($details['departments'])) {
            $deptCodes = $details['departments'];
        } elseif ($assignedScope1 && strpos($assignedScope1, 'Multiple: ') === 0) {
            $deptCodes = array_map('trim', explode(',', substr($assignedScope1, 9)));
        } elseif (!empty($assignedScope1)) {
            $deptCodes = [$assignedScope1];
        }

        if (empty($deptCodes)) {
            return $college . " - All departments";
        } elseif (count($deptCodes) === 1) {
            return ($college ? $college . " - " : "") . $deptCodes[0];
        } else {
            return ($college ? $college . " - " : "") . "Multiple departments (" . count($deptCodes) . ")";
        }
    }

    // Non-Academic-Employee
    if ($category === 'Non-Academic-Employee') {
        $deptCodes = [];

        if (!empty($details['departments']) && is_array($details['departments'])) {
            $deptCodes = $details['departments'];
        } elseif ($assignedScope1 && strpos($assignedScope1, 'Multiple: ') === 0) {
            $deptCodes = array_map('trim', explode(',', substr($assignedScope1, 9)));
        } elseif (!empty($assignedScope1) && $assignedScope1 !== 'Non-Academic') {
            $deptCodes = [$assignedScope1];
        } elseif (!empty($assignedScope) && $assignedScope !== 'Non-Academic') {
            $deptCodes = [$assignedScope];
        }

        if (empty($deptCodes)) {
            return "All non-academic departments";
        } elseif (count($deptCodes) === 1) {
            return $deptCodes[0];
        } else {
            return "Multiple departments (" . count($deptCodes) . ")";
        }
    }

    // Others & Non-Academic-Student
    if ($category === 'Others-COOP')          return 'COOP Admin';
    if ($category === 'Others-Default')       return 'Default Admin';
    if ($category === 'Non-Academic-Student') return 'All non-academic orgs';
    if ($category === 'Special-Scope')        return 'CSG Admin';

    return 'No specific scope assigned';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Admins - Super Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { --cvsu-green-dark:#154734; --cvsu-green:#1E6F46; --cvsu-green-light:#37A66B; --cvsu-yellow:#FFD166; }
    .gradient-bg { background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%); }
    .card { transition: all .3s ease; border-radius:.75rem; }
    .card:hover { transform: translateY(-4px); box-shadow:0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -2px rgba(0,0,0,.05); }
    .btn-primary { background-color: var(--cvsu-green); transition: all .3s ease; }
    .btn-primary:hover { background-color: var(--cvsu-green-dark); transform: translateY(-2px); box-shadow:0 4px 6px rgba(0,0,0,.1); }
    .btn-warning { background-color: var(--cvsu-yellow); transition: all .3s ease; }
    .btn-warning:hover { background-color:#e6b800; transform: translateY(-2px); box-shadow:0 4px 6px rgba(0,0,0,.1); }
    .btn-danger { background-color:#ef4444; transition: all .3s ease; }
    .btn-danger:hover { background-color:#dc2626; transform: translateY(-2px); box-shadow:0 4px 6px rgba(0,0,0,.1); }
    .btn-success { background-color:#10b981; transition: all .3s ease; }
    .btn-success:hover { background-color:#059669; transform: translateY(-2px); box-shadow:0 4px 6px rgba(0,0,0,.1); }
    .table-hover tbody tr:hover { background-color:#f3f4f6; }
    .status-badge { display:inline-flex; align-items:center; padding:.25rem .75rem; border-radius:9999px; font-size:.75rem; font-weight:600; }
    .status-active { background:#d1fae5; color:#065f46; }
    .status-inactive { background:#fee2e2; color:#991b1b; }
    .status-suspended { background:#fef3c7; color:#92400e; }
    .scope-badge { display:inline-block; padding:.25rem .5rem; border-radius:.375rem; font-size:.75rem; font-weight:500; margin-bottom:.25rem; }
    .scope-primary { background:#dbeafe; color:#1e40af; }
    .scope-secondary { background:#e9d5ff; color:#6b21a8; }
    .admin-table td { vertical-align: middle; }
    .admin-table .admin-info { display:flex; align-items:center; }
    .admin-table .admin-avatar { flex-shrink:0; }
    .admin-table .admin-details { margin-left:1rem; }
    .scope-cell .scope-secondary {
      display:inline-block; max-width:420px; white-space:nowrap;
      overflow:hidden; text-overflow:ellipsis; vertical-align:bottom;
    }
    #notificationModal {
      position:fixed; top:0; left:0; right:0; bottom:0;
      background-color:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center;
      z-index:9999; opacity:0; visibility:hidden; transition:opacity .3s, visibility .3s;
    }
    #notificationModal.show { opacity:1; visibility:visible; }
    .notification-content {
      background-color:white; border-radius:.5rem; box-shadow:0 10px 25px rgba(0,0,0,0.2);
      padding:1.5rem; max-width:90%; width:100%; max-width:500px; transform:translateY(20px); transition:transform .3s;
    }
    #notificationModal.show .notification-content { transform:translateY(0); }
    .notification-success { background-color:#d1fae5; color:#065f46; }
    .notification-error   { background-color:#fee2e2; color:#991b1b; }
    .notification-warning { background-color:#fef3c7; color:#92400e; }
    .notification-info    { background-color:#dbeafe; color:#1e40af; }
    @media (min-width:1024px){ .admin-container{ max-width:1400px; margin:0 auto; } }
    @media (max-width:1279px){ .stats-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:767px){
      .stats-grid{ grid-template-columns:1fr; }
      .filter-form{ flex-direction:column; }
      .admin-actions{ flex-direction:column; gap:.5rem; }
      .admin-actions button{ width:100%; }
    }
  </style>
</head>

<body class="bg-white font-sans text-gray-900">
  <div class="flex min-h-screen">
    <?php include 'super_admin_sidebar.php'; ?>
    <main class="flex-1 p-4 md:p-6 lg:p-8 ml-64 admin-container">
      <!-- Header -->
      <header class="gradient-bg text-white p-4 md:p-6 flex flex-col md:flex-row justify-between items-center shadow-xl rounded-xl mb-6 md:mb-8">
        <div class="flex items-center space-x-4 mb-4 md:mb-0">
          <div class="w-14 h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-user-shield text-2xl"></i>
          </div>
          <div>
            <h1 class="text-2xl md:text-3xl font-extrabold">Manage Admins</h1>
            <p class="text-green-100 mt-1">Administer admin accounts and permissions</p>
          </div>
        </div>
        <div class="flex items-center gap-4 w-full md:w-auto">
          <button id="openModalBtn"
                  onclick="document.getElementById('createModal').classList.remove('hidden')"
                  class="btn-warning text-white px-5 py-2.5 rounded-lg font-semibold transition flex items-center justify-center w-full md:w-auto">
            <i class="fas fa-plus-circle mr-2"></i>Add Admin
          </button>
        </div>
      </header>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6 md:mb-8 stats-grid">
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600"><i class="fas fa-users text-xl"></i></div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Admins</p>
              <p class="text-2xl font-bold text-gray-900"><?= (int)$totalAdmins ?></p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600"><i class="fas fa-user-check text-xl"></i></div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Active Admins</p>
              <p class="text-2xl font-bold text-gray-900"><?= (int)$activeAdmins ?></p>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600"><i class="fas fa-calendar-alt text-xl"></i></div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Current Calendar Year</p>
              <p class="text-2xl font-bold text-gray-900"><?= date('Y') ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Card -->
      <div class="bg-white rounded-xl shadow-md p-6 mb-6 md:mb-8 card border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">Filter Admins</h2>
            <p class="text-gray-600 text-sm">Filters will apply automatically as you select options</p>
          </div>

          <form method="GET" id="filterForm" class="flex flex-col sm:flex-row gap-3 filter-form">
            <!-- Scope Category -->
            <div class="relative">
              <label for="scope" class="block text-sm font-medium text-gray-700 mb-1">Scope Category</label>
              <div class="relative">
                <select name="scope" id="scope"
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Categories</option>
                  <?php foreach (getAllScopeCategories() as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $filterScope === $code ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>

            <!-- College -->
            <div id="collegeFilterContainer" class="relative <?= in_array($filterScope, ['Academic-Student','Academic-Faculty'], true) ? '' : 'hidden' ?>">
              <label for="college" class="block text-sm font-medium text-gray-700 mb-1">College</label>
              <div class="relative">
                <select name="college" id="college"
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Colleges</option>
                  <?php foreach (getColleges() as $code => $name): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $filterCollege === $code ? 'selected' : '' ?>>
                      <?= htmlspecialchars($code . ' - ' . $name) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>

            <!-- Course -->
            <div id="courseFilterContainer" class="relative <?= ($filterScope === 'Academic-Student' && !empty($filterCollege)) ? '' : 'hidden' ?>">
              <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
              <div class="relative">
                <select name="course" id="course"
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Courses</option>
                  <?php
                    if (!empty($filterCollege)) {
                      $courses = getCoursesByCollege($filterCollege);
                      foreach ($courses as $code => $name): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= $filterCourse === $code ? 'selected' : '' ?>>
                          <?= htmlspecialchars($code . ' - ' . $name) ?>
                        </option>
                      <?php endforeach;
                    }
                  ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>

            <!-- Department -->
            <div id="departmentFilterContainer" class="relative <?= (($filterScope === 'Academic-Faculty' && !empty($filterCollege)) || $filterScope === 'Non-Academic-Employee') ? '' : 'hidden' ?>">
              <label for="department" class="block text-sm font-medium text-gray-700 mb-1">
                <?= $filterScope === 'Non-Academic-Employee' ? 'Department (Non-Academic)' : 'Department' ?>
              </label>
              <div class="relative">
                <select name="department" id="department"
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Departments</option>
                  <?php
                  if ($filterScope === 'Non-Academic-Employee') {
                    $nonAcademicDepts = getNonAcademicDepartments();
                    foreach ($nonAcademicDepts as $code => $name): ?>
                      <option value="<?= htmlspecialchars($code) ?>" <?= $filterDepartment === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($code . ' - ' . $name) ?>
                      </option>
                    <?php endforeach;
                  } elseif ($filterScope === 'Academic-Faculty' && !empty($filterCollege)) {
                    $departments = getAcademicDepartments()[$filterCollege] ?? [];
                    foreach ($departments as $code => $name): ?>
                      <option value="<?= htmlspecialchars($code) ?>" <?= $filterDepartment === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($code . ' - ' . $name) ?>
                      </option>
                    <?php endforeach;
                  }
                  ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>

            <!-- Admin Title filter only for Non-Academic-Student & Others-Default -->
            <div id="adminFilterContainer" class="relative <?= in_array($filterScope, ['Non-Academic-Student','Others-Default'], true) ? '' : 'hidden' ?>">
              <label for="admin_title" class="block text-sm font-medium text-gray-700 mb-1">Admin Title</label>
              <div class="relative">
                <select name="admin_title" id="admin_title"
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Admin Titles</option>
                  <?php foreach ($adminFilterOptions as $title => $label): ?>
                    <option value="<?= htmlspecialchars($title) ?>" <?= $filterAdminTitle === $title ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>

            <div class="flex gap-2 self-end">
              <button type="button" onclick="window.location.href='manage_admins.php'"
                      class="bg-gray-200 hover:bg-gray-300 px-4 py-2.5 rounded-lg font-medium transition flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>Reset
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Admins Table -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden card border border-gray-100">
        <div class="px-4 md:px-6 py-4 border-b border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
          <h2 class="text-xl font-bold text-gray-800">Admin Accounts</h2>
          <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
            <div class="text-sm text-gray-500">Showing <?= (int)count($admins) ?> admin accounts</div>
            <div class="relative w-full sm:w-auto">
              <select id="tableYearFilter" name="year" onchange="filterByYear(this.value)"
                      class="appearance-none block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                <option value="">All Years</option>
                <?php foreach ($academicYears as $year): ?>
                  <option value="<?= htmlspecialchars($year) ?>" <?= $filterYear === $year ? 'selected' : '' ?>>
                    <?= htmlspecialchars($year) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                <i class="fas fa-chevron-down"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 table-hover admin-table">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Scope</th>
                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                <th class="px-4 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($admins)): ?>
              <tr>
                <td colspan="6" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                      <i class="fas fa-user-shield text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No admin accounts found</h3>
                    <p class="text-gray-500">Create your first admin account to get started</p>
                    <button onclick="document.getElementById('createModal').classList.remove('hidden')"
                            class="mt-4 bg-yellow-800 text-white px-4 py-2 rounded-lg font-semibold flex items-center shadow-md">
                      <i class="fas fa-plus-circle mr-2"></i>Add Admin
                    </button>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($admins as $admin): ?>
                <?php
                  $firstName   = htmlspecialchars($admin['first_name'] ?? '');
                  $lastName    = htmlspecialchars($admin['last_name']  ?? '');
                  $email       = htmlspecialchars($admin['email']      ?? '');
                  $adminTitle  = htmlspecialchars($admin['admin_title'] ?? 'Administrator');
                  $status      = htmlspecialchars($admin['admin_status'] ?? 'inactive');
                  $statusClass = 'status-inactive';
                  if ($status === 'active')        $statusClass = 'status-active';
                  elseif ($status === 'suspended') $statusClass = 'status-suspended';

                  $scopeLabel   = 'No Scope';
                  $scopeSummary = 'No specific scope assigned';
                  if (!empty($admin['scope_category'])) {
                      $scopeLabel   = htmlspecialchars(getScopeCategoryLabel($admin['scope_category']));
                      $scopeSummary = htmlspecialchars(summarizeAssignedScopeShort($admin));
                  } elseif (!empty($admin['assigned_scope'])) {
                      $scopeLabel = htmlspecialchars($admin['assigned_scope']);
                  }

                  // Determine if this row should show "Inherit Scope" instead of "Activate"
                  $showInherit = false;

                  if (!empty($filterScope) && $status === 'inactive' && !empty($admin['scope_category'])) {

                      // Special seat-based logic for Non-Academic-Student & Others-Default
                      if (in_array($admin['scope_category'], ['Non-Academic-Student','Others-Default'], true)) {

                          $seatCategory = $admin['scope_category']   ?? '';
                          $seatScope    = $admin['assigned_scope']   ?? '';
                          $seatScope1   = $admin['assigned_scope_1'] ?? '';
                          $seatTitle    = trim($admin['admin_title'] ?? '');

                          // Collect all admins that belong to the same seat
                          $seatAdmins = array_filter($admins, function ($other) use ($admin, $seatCategory, $seatScope, $seatScope1, $seatTitle) {
                              if (($other['scope_category']   ?? '') !== $seatCategory) return false;
                              if (($other['assigned_scope']    ?? '') !== $seatScope)   return false;
                              if (($other['assigned_scope_1']  ?? '') !== $seatScope1)  return false;
                              if (trim($other['admin_title']   ?? '') !== $seatTitle)   return false;
                              return true;
                          });

                          $hasActive        = false;
                          $hasOlderInactive = false;
                          $maxId            = 0;
                          $currentId        = (int)$admin['user_id'];

                          foreach ($seatAdmins as $sa) {
                              $uid    = (int)$sa['user_id'];
                              $status = $sa['admin_status'] ?? '';

                              if ($uid > $maxId) {
                                  $maxId = $uid; // track newest admin in this seat
                              }
                              if ($uid !== $currentId && $status === 'active') {
                                  $hasActive = true;
                              }
                              if ($uid < $currentId && $status === 'inactive') {
                                  $hasOlderInactive = true; // may isang mas luma na inactive sa seat
                              }
                          }

                          // Show Inherit only if:
                          //  - Walang active admin sa seat (vacant na yung upuan)
                          //  - Itong admin ang pinakabagong admin sa seat (max user_id)
                          //  - May at least isang mas luma na inactive admin sa seat (source to inherit from)
                          if (!$hasActive && $currentId === $maxId && $hasOlderInactive) {
                              $showInherit = true;
                          }

                      } else {
                          // Existing generic behavior for other scope categories
                          foreach ($admins as $other) {
                              if ($other['user_id'] == $admin['user_id']) continue;
                              if (
                                  ($other['scope_category']   ?? '') === ($admin['scope_category']   ?? '') &&
                                  ($other['assigned_scope']    ?? '') === ($admin['assigned_scope']    ?? '') &&
                                  ($other['assigned_scope_1']  ?? '') === ($admin['assigned_scope_1']  ?? '') &&
                                  ($other['admin_status']      ?? '') === 'inactive' &&
                                  (int)$other['user_id']       < (int)$admin['user_id']
                              ) {
                                  $showInherit = true;
                                  break;
                              }
                          }
                      }
                  }

                  $isAllCategories = empty($filterScope);
                ?>
                <tr>
                  <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                    <div class="admin-info">
                      <div class="admin-avatar">
                        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                          <span class="text-green-800 font-semibold">
                            <?= strtoupper(htmlspecialchars(substr($admin['first_name'],0,1) . substr($admin['last_name'],0,1))) ?>
                          </span>
                        </div>
                      </div>
                      <div class="admin-details">
                        <div class="text-sm font-medium text-gray-900"><?= $firstName . ' ' . $lastName ?></div>
                        <div class="text-sm text-gray-500"><?= $adminTitle ?></div>
                      </div>
                    </div>
                  </td>

                  <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900"><?= $email ?></div>
                  </td>

                  <td class="px-4 md:px-6 py-4 whitespace-nowrap scope-cell">
                    <?php if ($scopeLabel !== 'No Scope'): ?>
                      <span class="scope-badge scope-primary"><?= $scopeLabel ?></span>
                      <div class="mt-1">
                        <span class="scope-badge scope-secondary" title="<?= $scopeSummary ?>">
                          <?= $scopeSummary ?>
                        </span>
                      </div>
                    <?php else: ?>
                      <span class="scope-badge bg-gray-100 text-gray-800">No Scope</span>
                    <?php endif; ?>
                  </td>

                  <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                    <span class="status-badge <?= $statusClass ?>">
                      <i class="fas fa-circle mr-1 text-xs"></i><?= ucfirst($status) ?>
                    </span>
                  </td>

                  <td class="px-4 md:px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">
                      <?= htmlspecialchars($admin['academic_year'] ?? '') ?>
                    </div>
                  </td>

                  <td class="px-4 md:px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                    <div class="flex flex-col sm:flex-row gap-2 justify-center admin-actions">
                      <?php if ($isAllCategories): ?>
                        <!-- All Categories: ONLY Edit/Delete -->
                        <button onclick="handleEditClick(<?= (int)$admin['user_id'] ?>)"
                                class="btn-warning text-white px-3 py-1 rounded-lg inline-flex items-center justify-center" data-edit-btn>
                          <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                      <?php else: ?>
                        <!-- Specific scope filter: allow inherit / toggle -->
                        <?php if ($showInherit): ?>
                          <button
                            onclick="inheritScope(<?= (int)$admin['user_id'] ?>)"
                            class="btn-primary text-white px-3 py-1 rounded-lg inline-flex items-center justify-center">
                            <i class="fas fa-share-alt mr-1"></i>Inherit Scope
                          </button>
                        <?php else: ?>
                          <button
                            onclick="toggleAdminStatus(<?= (int)$admin['user_id'] ?>, '<?= $status === 'active' ? 'inactive' : 'active' ?>')"
                            class="<?= $status === 'active' ? 'btn-danger' : 'btn-success' ?> text-white px-3 py-1 rounded-lg inline-flex items-center justify-center">
                            <i class="fas fa-<?= $status === 'active' ? 'ban' : 'check' ?> mr-1"></i>
                            <?= $status === 'active' ? 'Deactivate' : 'Activate' ?>
                          </button>
                        <?php endif; ?>

                        <button onclick="handleEditClick(<?= (int)$admin['user_id'] ?>)"
                                class="btn-warning text-white px-3 py-1 rounded-lg inline-flex items-center justify-center" data-edit-btn>
                          <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                      <?php endif; ?>

                      <form action="delete_admin.php" method="POST" class="inline delete-form" id="delete-form-<?= (int)$admin['user_id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($PAGE_CSRF) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$admin['user_id'] ?>">
                        <button type="submit" class="btn-danger text-white px-3 py-1 rounded-lg inline-flex items-center justify-center">
                          <i class="fas fa-trash-alt mr-1"></i>Delete
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php include 'admin_modal_create.php'; ?>
      <?php include 'admin_modal_update.php'; ?>
    </main>
  </div>

  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl flex flex-col items-center">
      <div class="loading-spinner mb-4"></div>
      <p class="text-gray-700">Processing, please wait...</p>
    </div>
  </div>

  <!-- Notification Modal -->
  <div id="notificationModal" aria-live="assertive">
    <div class="notification-content">
      <div class="flex items-start">
        <div id="notificationIcon" class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3"></div>
        <div class="flex-1">
          <h3 id="notificationTitle" class="font-semibold text-gray-900"></h3>
          <p id="notificationMessage" class="text-sm text-gray-600 mt-1"></p>
        </div>
        <button onclick="hideNotification()" class="ml-4 text-gray-400 hover:text-gray-500 focus:outline-none">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="mt-3 flex justify-end">
        <button onclick="hideNotification()" class="text-sm font-medium text-green-600 hover:text-green-500 focus:outline-none">OK</button>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
      <h3 id="confirmationTitle" class="text-lg font-semibold text-gray-900 mb-2"></h3>
      <p id="confirmationMessage" class="text-gray-600 mb-6"></p>
      <div class="flex justify-end space-x-3">
        <button onclick="cancelConfirmation()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">Cancel</button>
        <button id="confirmButton" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Confirm</button>
      </div>
    </div>
  </div>

  <input type="hidden" id="pageCsrf" value="<?= htmlspecialchars($PAGE_CSRF) ?>">
  <script>
// === Global taxonomy bootstrap ===
window.collegesData             = <?php echo json_encode(getColleges()); ?>;
window.academicDepartmentsData  = <?php echo json_encode(getAcademicDepartments()); ?>;
window.nonAcademicDepartmentsData = <?php echo json_encode(getNonAcademicDepartments()); ?>;

// Build coursesByCollegeData
window.coursesByCollegeData = {};
<?php 
foreach (array_keys(getColleges()) as $college) {
    $courses = getCoursesByCollege($college);
    $arr = [];
    foreach ($courses as $code => $name) {
        $arr[] = ['code'=>$code, 'name'=>$name];
    }
    echo "window.coursesByCollegeData['$college'] = " . json_encode($arr) . ";\n";
}
?>

// === Notification helpers ===
function showNotification(title, message, type='info') {
  const modal    = document.getElementById('notificationModal');
  const icon     = document.getElementById('notificationIcon');
  const titleEl  = document.getElementById('notificationTitle');
  const messageEl= document.getElementById('notificationMessage');

  icon.className = 'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3 ';

  if (type === 'success') {
    icon.classList.add('notification-success');
    icon.innerHTML = '<i class="fas fa-check-circle"></i>';
  } else if (type === 'error') {
    icon.classList.add('notification-error');
    icon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
  } else if (type === 'warning') {
    icon.classList.add('notification-warning');
    icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
  } else {
    icon.classList.add('notification-info');
    icon.innerHTML = '<i class="fas fa-info-circle"></i>';
  }

  titleEl.textContent   = title;
  messageEl.textContent = message;

  modal.classList.add('show');
  setTimeout(hideNotification, 5000);
}

function hideNotification() {
  document.getElementById('notificationModal').classList.remove('show');
}

// === Confirmation modal helpers ===
let confirmationCallback = null;
function showConfirmation(title, message, callback) {
  document.getElementById('confirmationTitle').textContent  = title;
  document.getElementById('confirmationMessage').textContent= message;
  confirmationCallback = callback;
  document.getElementById('confirmationModal').classList.remove('hidden');
}
function cancelConfirmation(){
  document.getElementById('confirmationModal').classList.add('hidden');
  confirmationCallback = null;
}
document.getElementById('confirmButton').addEventListener('click', function(){
  if (confirmationCallback) confirmationCallback();
  cancelConfirmation();
});

// === Year filter ===
function filterByYear(year) {
  const url = new URL(window.location);
  if (year) url.searchParams.set('year', year);
  else      url.searchParams.delete('year');
  window.location.href = url.toString();
}

// === Filter autosubmit ===
function updateCollegeFilter(){
  const scope           = document.getElementById('scope');
  const collegeContainer= document.getElementById('collegeFilterContainer');
  const courseContainer = document.getElementById('courseFilterContainer');
  const deptContainer   = document.getElementById('departmentFilterContainer');
  const adminContainer  = document.getElementById('adminFilterContainer');
  const selected        = scope ? scope.value : '';

  // Admin Title filter only for Non-Academic-Student & Others-Default
  if (selected === 'Non-Academic-Student' || selected === 'Others-Default') {
    adminContainer.classList.remove('hidden');
  } else {
    adminContainer.classList.add('hidden');
  }

  if (['Academic-Student','Academic-Faculty'].includes(selected)) {
    collegeContainer.classList.remove('hidden');
  } else {
    collegeContainer.classList.add('hidden');
    courseContainer.classList.add('hidden');
    deptContainer.classList.add('hidden');
    const college = document.getElementById('college');
    if (college) college.value = '';
  }
}

function updateCourseDepartmentFilter(){
  const scope          = document.getElementById('scope')?.value || '';
  const college        = document.getElementById('college')?.value || '';
  const courseContainer= document.getElementById('courseFilterContainer');
  const deptContainer  = document.getElementById('departmentFilterContainer');

  if (scope === 'Academic-Student' && college) {
    courseContainer.classList.remove('hidden');
  } else {
    courseContainer.classList.add('hidden');
  }

  if ((scope === 'Academic-Faculty' && college) || scope === 'Non-Academic-Employee') {
    deptContainer.classList.remove('hidden');
  } else {
    deptContainer.classList.add('hidden');
  }
}

function submitFilterForm(){ document.getElementById('filterForm').submit(); }

document.addEventListener('DOMContentLoaded', () => {
  const scopeSel   = document.getElementById('scope');
  const collegeSel = document.getElementById('college');
  const courseSel  = document.getElementById('course');
  const deptSel    = document.getElementById('department');
  const adminSel   = document.getElementById('admin_title');

  if (scopeSel)   scopeSel.addEventListener('change', () => { updateCollegeFilter(); submitFilterForm(); });
  if (collegeSel) collegeSel.addEventListener('change', () => { updateCourseDepartmentFilter(); submitFilterForm(); });
  if (courseSel)  courseSel.addEventListener('change', submitFilterForm);
  if (deptSel)    deptSel.addEventListener('change', submitFilterForm);
  if (adminSel)   adminSel.addEventListener('change', submitFilterForm);

  updateCollegeFilter();
});

// === Toggle Admin Status ===
function toggleAdminStatus(adminId, newStatus){
  showConfirmation(
    'Confirm Action',
    `Are you sure you want to ${newStatus==='active'?'activate':'deactivate'} this admin?`,
    function(){
      const overlay = document.getElementById('loadingOverlay');
      overlay && overlay.classList.remove('hidden');

      fetch('manage_admins.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'toggle_status',
          admin_id: adminId,
          status: newStatus,
          csrf_token: document.getElementById('pageCsrf').value
        })
      })
      .then(r => r.json())
      .then(data => {
        overlay && overlay.classList.add('hidden');
        if (data.success) {
          showNotification('Success', data.message, 'success');
          setTimeout(() => window.location.reload(), 900);
        } else {
          showNotification('Error', data.message, 'error');
        }
      })
      .catch(err => {
        overlay && overlay.classList.add('hidden');
        showNotification('Request Failed', err.message, 'error');
      });
    }
  );
}

// === Inherit Scope ===
function inheritScope(targetAdminId) {
  showConfirmation(
    'Inherit Scope',
    'This will inherit the scope from the previous inactive admin with the same scope. Continue?',
    function() {
      const overlay = document.getElementById('loadingOverlay');
      overlay && overlay.classList.remove('hidden');

      fetch('manage_admins.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'inherit_scope',
          target_admin_id: targetAdminId,
          csrf_token: document.getElementById('pageCsrf').value
        })
      })
      .then(r => r.json())
      .then(data => {
        overlay && overlay.classList.add('hidden');
        if (data.success) {
          showNotification('Success', data.message, 'success');
          setTimeout(() => window.location.reload(), 900);
        } else {
          showNotification('Error', data.message, 'error');
        }
      })
      .catch(err => {
        overlay && overlay.classList.add('hidden');
        showNotification('Request Failed', err.message, 'error');
      });
    }
  );
}

// === Edit Modal glue ===
function triggerEditAdmin(userId){
  const overlay = document.getElementById('loadingOverlay');
  overlay && overlay.classList.remove('hidden');

  fetch('get_admin.php?user_id=' + encodeURIComponent(userId))
    .then(res => {
      if (!res.ok) throw new Error('Network response was not ok');
      return res.json();
    })
    .then(data => {
      overlay && overlay.classList.add('hidden');
      if (data.status === 'success') {
        openUpdateModal(data.data);
      } else {
        showNotification('Error', data.message || 'Admin not found', 'error');
      }
    })
    .catch(err => {
      overlay && overlay.classList.add('hidden');
      showNotification('Error', err.message, 'error');
    });
}

function handleEditClick(userId){
  triggerEditAdmin(userId);
}
  </script>
</body>
</html>