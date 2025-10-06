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
// --- Build query conditions and columns based on admin's scope ---
 $conditions = ["role = 'voter'"];
 $params = [];
 $columns = [];
 $filterOptions = [];
// Super Admin sees all voters
if ($currentRole === 'super_admin') {
    $columns = ['Photo', 'Name', 'Position', 'Department', 'Course', 'Actions'];
    $filterOptions['positions'] = $pdo->query("SELECT DISTINCT position FROM users WHERE role = 'voter' ORDER BY position ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['departments'] = $pdo->query("SELECT DISTINCT department FROM users WHERE role = 'voter' AND department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['courses'] = $pdo->query("SELECT DISTINCT course FROM users WHERE role = 'voter' AND course IS NOT NULL AND course != '' ORDER BY course ASC")->fetchAll(PDO::FETCH_COLUMN);
} 
// College Admin (CEIT, CAS, etc.) - only students from their college
else if (in_array($assignedScope, ['CEIT', 'CAS', 'CEMDS', 'CCJ', 'CAFENR', 'CON', 'COED', 'CVM', 'GRADUATE SCHOOL'])) {
    $conditions[] = "position = 'student'";
    $conditions[] = "UPPER(TRIM(department)) = :scope";
    $params[':scope'] = $assignedScope;
    $columns = ['Photo', 'Name', 'Student Number', 'Course', 'Department', 'Actions'];
    $filterOptions['courses'] = $pdo->prepare("SELECT DISTINCT course FROM users WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = :scope AND course IS NOT NULL AND course != '' ORDER BY course ASC");
    $filterOptions['courses']->execute([':scope' => $assignedScope]);
    $filterOptions['courses'] = $filterOptions['courses']->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['departments'] = $pdo->prepare("SELECT DISTINCT department1 FROM users WHERE role = 'voter' AND position = 'student' AND UPPER(TRIM(department)) = :scope AND department1 IS NOT NULL AND department1 != '' ORDER BY department1 ASC");
    $filterOptions['departments']->execute([':scope' => $assignedScope]);
    $filterOptions['departments'] = $filterOptions['departments']->fetchAll(PDO::FETCH_COLUMN);
}
// Faculty Association Admin - academic faculty
else if ($assignedScope === 'FACULTY ASSOCIATION') {
    $conditions[] = "position = 'academic'";
    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'College', 'Department', 'Actions'];
    $filterOptions['statuses'] = $pdo->query("SELECT DISTINCT status FROM users WHERE role = 'voter' AND position = 'academic' ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['departments'] = $pdo->query("SELECT DISTINCT department FROM users WHERE role = 'voter' AND position = 'academic' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
}
// Non-Academic Admin - non-academic staff
else if ($assignedScope === 'NON-ACADEMIC') {
    $conditions[] = "position = 'non-academic'";
    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'Department', 'Actions'];
    $filterOptions['statuses'] = $pdo->query("SELECT DISTINCT status FROM users WHERE role = 'voter' AND position = 'non-academic' ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['departments'] = $pdo->query("SELECT DISTINCT department FROM users WHERE role = 'voter' AND position = 'non-academic' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
}
// COOP Admin - COOP members
else if ($assignedScope === 'COOP') {
    $conditions[] = "is_coop_member = 1";
    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'College/Department', 'MIGS Status', 'Actions'];
    $filterOptions['statuses'] = $pdo->query("SELECT DISTINCT status FROM users WHERE role = 'voter' AND is_coop_member = 1 ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['departments'] = $pdo->query("SELECT DISTINCT IFNULL(department, department1) as dept FROM users WHERE role = 'voter' AND is_coop_member = 1 AND (department IS NOT NULL OR department1 IS NOT NULL) ORDER BY dept ASC")->fetchAll(PDO::FETCH_COLUMN);
}
// CSG Admin - all students
else if ($assignedScope === 'CSG ADMIN') {
    $conditions[] = "position = 'student'";
    $columns = ['Photo', 'Name', 'Student Number', 'Course', 'College', 'Department', 'Actions'];
    $filterOptions['courses'] = $pdo->query("SELECT DISTINCT course FROM users WHERE role = 'voter' AND position = 'student' AND course IS NOT NULL AND course != '' ORDER BY course ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filterOptions['departments'] = $pdo->query("SELECT DISTINCT department FROM users WHERE role = 'voter' AND position = 'student' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
}
// Get current filters
 $filterStatus = $_GET['status'] ?? '';
 $filterDepartment = $_GET['department'] ?? '';
 $filterCourse = $_GET['course'] ?? '';
// Apply additional filters if set
if (!empty($filterStatus) && isset($filterOptions['statuses']) && in_array($filterStatus, $filterOptions['statuses'])) {
    $conditions[] = "status = :status";
    $params[':status'] = $filterStatus;
}
if (!empty($filterDepartment) && isset($filterOptions['departments']) && in_array($filterDepartment, $filterOptions['departments'])) {
    // For COOP admin, we need to check both department and department1 fields
    if ($assignedScope === 'COOP') {
        $conditions[] = "(department = :department OR department1 = :department)";
    } else {
        $conditions[] = "department = :department";
    }
    $params[':department'] = $filterDepartment;
}
if (!empty($filterCourse) && isset($filterOptions['courses']) && in_array($filterCourse, $filterOptions['courses'])) {
    $conditions[] = "course = :course";
    $params[':course'] = $filterCourse;
}
// Build the final query
 $sql = "SELECT * FROM users WHERE " . implode(' AND ', $conditions) . " ORDER BY user_id DESC";
// Execute the query
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
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
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <!-- Background Pattern -->
  <div class="fixed inset-0 opacity-5 pointer-events-none">
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"2\" fill=\"%23154734\"/></svg>'); background-size: 20px 20px;"></div>
  </div>
  
  <div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-users text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Manage Users</h1>
            <p class="text-green-100 mt-1">
              <?php 
              if ($currentRole === 'super_admin') {
                echo "All registered users in the system";
              } else if ($assignedScope === 'FACULTY ASSOCIATION') {
                echo "Faculty Association members";
              } else if ($assignedScope === 'NON-ACADEMIC') {
                echo "Non-Academic staff";
              } else if ($assignedScope === 'COOP') {
                echo "COOP members";
              } else if ($assignedScope === 'CSG ADMIN') {
                echo "All student voters";
              } else {
                // Check if it's a college admin
                if (in_array($assignedScope, ['CEIT', 'CAS', 'CEMDS', 'CCJ', 'CAFENR', 'CON', 'COED', 'CVM', 'GRADUATE SCHOOL'])) {
                    echo htmlspecialchars($assignedScope) . " students";
                } else {
                    echo "Users";
                }
              }
              ?>
            </p>
          </div>
        </div>
        <!-- In manage_users.php, find this section in the header -->
        <div class="flex items-center gap-4">
          <a href="admin_add_user.php" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">Add User</a>
          <!-- Add this new button -->
          <a href="admin_restrict_users.php" class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded font-semibold transition">Restrict Users</a>
        </div>
      </header>
      
      <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-6 p-4 rounded <?= $_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
          <?= htmlspecialchars($_SESSION['message']) ?>
      </div>
      <?php 
      unset($_SESSION['message']);
      unset($_SESSION['message_type']);
      endif; 
      ?>
      
      <!-- Filters -->
      <div class="mb-4 flex flex-wrap gap-4 items-center bg-white p-4 rounded shadow">
        <?php if (isset($filterOptions['positions'])): ?>
        <div>
          <label for="position" class="font-semibold">Filter by Position:</label>
          <select id="position" name="position" class="border rounded px-3 py-2" onchange="filter()">
            <option value="">All Positions</option>
            <?php foreach ($filterOptions['positions'] as $position): ?>
              <option value="<?= htmlspecialchars($position) ?>" <?= ($position == $filterPosition) ? 'selected' : '' ?>>
                <?= htmlspecialchars($position) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <?php if (isset($filterOptions['statuses'])): ?>
        <div>
          <label for="status" class="font-semibold">Filter by Status:</label>
          <select id="status" name="status" class="border rounded px-3 py-2" onchange="filter()">
            <option value="">All Statuses</option>
            <?php foreach ($filterOptions['statuses'] as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($status == $filterStatus) ? 'selected' : '' ?>>
                <?= htmlspecialchars($status) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <?php if (isset($filterOptions['departments'])): ?>
        <div>
          <label for="department" class="font-semibold">Filter by Department:</label>
          <select id="department" name="department" class="border rounded px-3 py-2" onchange="filter()">
            <option value="">All Departments</option>
            <?php foreach ($filterOptions['departments'] as $dept): ?>
              <option value="<?= htmlspecialchars($dept) ?>" <?= ($dept == $filterDepartment) ? 'selected' : '' ?>>
                <?= htmlspecialchars($dept) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <?php if (isset($filterOptions['courses'])): ?>
        <div>
          <label for="course" class="font-semibold">Filter by Course:</label>
          <select id="course" name="course" class="border rounded px-3 py-2" onchange="filter()">
            <option value="">All Courses</option>
            <?php foreach ($filterOptions['courses'] as $course): ?>
              <option value="<?= htmlspecialchars($course) ?>" <?= ($course == $filterCourse) ? 'selected' : '' ?>>
                <?= htmlspecialchars($course) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <div class="ml-auto">
          <a href="admin_manage_users.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded font-medium">
            <i class="fas fa-sync-alt mr-2"></i>Reset Filters
          </a>
        </div>
      </div>
      
      <!-- Users Table -->
      <div class="overflow-x-auto bg-white rounded shadow-lg">
        <table class="min-w-full table-auto">
          <thead class="bg-[var(--cvsu-green)] text-white">
            <tr>
              <?php foreach ($columns as $column): ?>
                <th class="py-3 px-6 text-left"><?= htmlspecialchars($column) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (count($users) > 0): ?>
              <?php foreach ($users as $user): ?>
                <tr class="border-b hover:bg-gray-100">
                  <td class="py-3 px-6">
                    <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                      <i class="fas fa-user text-gray-400"></i>
                    </div>
                  </td>
                  <td class="py-3 px-6 font-medium">
                    <?= htmlspecialchars($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']) ?>
                  </td>
                  
                  <?php if (in_array('Student Number', $columns)): ?>
                  <td class="py-3 px-6">
                    <?= !empty($user['student_number']) ? htmlspecialchars($user['student_number']) : '<span class="text-gray-400">Not set</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Employee Number', $columns)): ?>
                  <td class="py-3 px-6">
                    <?= !empty($user['employee_number']) ? htmlspecialchars($user['employee_number']) : '<span class="text-gray-400">Not set</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Status', $columns)): ?>
                  <td class="py-3 px-6">
                    <?php if (!empty($user['status'])): ?>
                      <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                        <?= htmlspecialchars($user['status']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">Not set</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Course', $columns)): ?>
                  <td class="py-3 px-6">
                    <?= !empty($user['course']) ? htmlspecialchars($user['course']) : '<span class="text-gray-400">Not set</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('College', $columns)): ?>
                  <td class="py-3 px-6">
                    <?= !empty($user['department']) ? htmlspecialchars($user['department']) : '<span class="text-gray-400">Not assigned</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Department', $columns)): ?>
                  <td class="py-3 px-6">
                    <?php 
                    // For non-academic, department is in the 'department' field
                    // For students and academic, department is in the 'department1' field
                    if ($assignedScope === 'NON-ACADEMIC') {
                        $deptValue = $user['department'];
                    } else {
                        $deptValue = $user['department1'];
                    }
                    ?>
                    <?= !empty($deptValue) ? htmlspecialchars($deptValue) : '<span class="text-gray-400">Not assigned</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('College/Department', $columns)): ?>
                  <td class="py-3 px-6">
                    <?php 
                    $collegeDept = '';
                    if (!empty($user['department'])) {
                        $collegeDept = htmlspecialchars($user['department']);
                    }
                    if (!empty($user['department1'])) {
                        if (!empty($collegeDept)) {
                            $collegeDept .= ' / ';
                        }
                        $collegeDept .= htmlspecialchars($user['department1']);
                    }
                    echo !empty($collegeDept) ? $collegeDept : '<span class="text-gray-400">Not assigned</span>';
                    ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('MIGS Status', $columns)): ?>
                  <td class="py-3 px-6">
                    <?php if ($user['migs_status'] == 1): ?>
                      <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">MIGS</span>
                    <?php else: ?>
                      <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">Non-MIGS</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Position', $columns)): ?>
                  <td class="py-3 px-6">
                    <?php if (!empty($user['position'])): ?>
                      <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                        <?= htmlspecialchars($user['position']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">Not assigned</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  
                  <td class="py-3 px-6 text-center space-x-2">
                    <button 
                      onclick='triggerEditUser(<?= $user["user_id"] ?>)'
                      class="text-blue-500 hover:text-blue-600 font-semibold">
                      <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                    <a href="admin_delete_users.php?user_id=<?= $user['user_id'] ?>" class="text-red-600 hover:text-red-700 font-semibold" onclick="return confirm('Are you sure you want to delete this user?');">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?= count($columns) ?>" class="text-center py-6 text-gray-500">
                  No users found. <a href="admin_add_user.php" class="text-green-600 hover:underline">Add your first user</a>
                </td>
              </tr>
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
function filter() {
  const position = document.getElementById('position') ? document.getElementById('position').value : '';
  const status = document.getElementById('status') ? document.getElementById('status').value : '';
  const department = document.getElementById('department') ? document.getElementById('department').value : '';
  const course = document.getElementById('course') ? document.getElementById('course').value : '';
  
  const url = new URL(window.location.href);
  
  if (position) {
    url.searchParams.set('position', position);
  } else {
    url.searchParams.delete('position');
  }
  
  if (status) {
    url.searchParams.set('status', status);
  } else {
    url.searchParams.delete('status');
  }
  
  if (department) {
    url.searchParams.set('department', department);
  } else {
    url.searchParams.delete('department');
  }
  
  if (course) {
    url.searchParams.set('course', course);
  } else {
    url.searchParams.delete('course');
  }
  
  window.location.href = url.toString();
}
</script>
</body>
</html>