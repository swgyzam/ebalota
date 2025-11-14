<?php
session_start();
date_default_timezone_set('Asia/Manila');
include_once 'admin_functions.php'; // Include the helper functions (only once)

// --- DB Connection ---
 $host = 'localhost';
 $db = 'evoting_system';
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

// --- Handle Admin Status Changes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $adminId = $_POST['admin_id'] ?? 0;
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'toggle_status') {
            $newStatus = $_POST['status'] ?? 'inactive';
            
            // Get admin details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'admin'");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                throw new Exception('Admin not found');
            }
            
            // If activating, deactivate other admins with same scope
            if ($newStatus === 'active') {
                $scopeCondition = "";
                $params = [];
                
                if (!empty($admin['scope_category'])) {
                    $scopeCondition = " AND scope_category = :scope_category";
                    $params[':scope_category'] = $admin['scope_category'];
                    
                    if (!empty($admin['assigned_scope'])) {
                        $scopeCondition .= " AND assigned_scope = :assigned_scope";
                        $params[':assigned_scope'] = $admin['assigned_scope'];
                    }
                } else if (!empty($admin['assigned_scope'])) {
                    $scopeCondition = " AND assigned_scope = :assigned_scope";
                    $params[':assigned_scope'] = $admin['assigned_scope'];
                }
                
                if (!empty($scopeCondition)) {
                    $deactivateStmt = $pdo->prepare("UPDATE users SET admin_status = 'inactive' WHERE role = 'admin' AND admin_status = 'active' $scopeCondition");
                    $deactivateStmt->execute($params);
                }
            }
            
            // Update the admin status
            $updateStmt = $pdo->prepare("UPDATE users SET admin_status = ? WHERE user_id = ?");
            $updateStmt->execute([$newStatus, $adminId]);
            
            $response['success'] = true;
            $response['message'] = "Admin status updated successfully";
            
        } elseif ($_POST['action'] === 'advance_year') {
            $newYear = $_POST['academic_year'] ?? '';
            
            if (empty($newYear)) {
                throw new Exception('Academic year is required');
            }
            
            // Update all admins to new academic year
            $updateStmt = $pdo->prepare("UPDATE users SET academic_year = ? WHERE role = 'admin'");
            $updateStmt->execute([$newYear]);
            
            $response['success'] = true;
            $response['message'] = "Academic year advanced successfully";
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// --- Filtering ---
 $filterScope = isset($_GET['scope']) ? $_GET['scope'] : '';
 $filterCollege = isset($_GET['college']) ? $_GET['college'] : '';
 $filterCourse = isset($_GET['course']) ? $_GET['course'] : '';
 $filterDepartment = isset($_GET['department']) ? $_GET['department'] : '';

 $scopeQuery = '';
 $params = [];

if (!empty($filterScope)) {
    $scopeQuery .= " AND u.scope_category = :scope";
    $params[':scope'] = $filterScope;
}

if (!empty($filterCollege)) {
    $scopeQuery .= " AND u.assigned_scope = :college";
    $params[':college'] = $filterCollege;
}

if (!empty($filterCourse)) {
    $scopeQuery .= " AND u.assigned_scope_1 LIKE :course";
    $params[':course'] = '%' . $filterCourse . '%';
}

if (!empty($filterDepartment)) {
    $scopeQuery .= " AND u.assigned_scope_1 LIKE :department";
    $params[':department'] = '%' . $filterDepartment . '%';
}

// --- Fetch admins with scope details ---
 $stmt = $pdo->prepare("
    SELECT u.*, asd.scope_details 
    FROM users u 
    LEFT JOIN admin_scopes asd ON u.user_id = asd.user_id 
    WHERE u.role = 'admin' $scopeQuery 
    ORDER BY u.admin_status DESC, u.user_id DESC
");
 $stmt->execute($params);
 $admins = $stmt->fetchAll();

// Get counts for stats cards
 $totalAdmins = count($admins);
 $activeAdmins = count(array_filter($admins, function($admin) {
    return $admin['admin_status'] === 'active';
}));
 $activeScopes = [];
foreach ($admins as $admin) {
    if (!empty($admin['scope_category'])) {
        $activeScopes[] = $admin['scope_category'];
    } else if (!empty($admin['assigned_scope'])) {
        $activeScopes[] = $admin['assigned_scope'];
    }
}
 $activeScopes = count(array_unique($activeScopes));
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
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
    
    .btn-warning {
      background-color: var(--cvsu-yellow);
      transition: all 0.3s ease;
    }
    
    .btn-warning:hover {
      background-color: #e6b800;
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
    
    .btn-success {
      background-color: #10b981;
      transition: all 0.3s ease;
    }
    
    .btn-success:hover {
      background-color: #059669;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .table-hover tbody tr:hover {
      background-color: #f3f4f6;
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
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .status-active {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .status-inactive {
      background-color: #fee2e2;
      color: #991b1b;
    }
    
    .status-suspended {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .scope-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }
    
    .scope-primary {
      background-color: #dbeafe;
      color: #1e40af;
    }
    
    .scope-secondary {
      background-color: #e9d5ff;
      color: #6b21a8;
    }
  </style>
</head>
<body class="bg-white font-sans text-gray-900">
  <div class="flex min-h-screen">
    <?php include 'super_admin_sidebar.php'; ?>
    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-xl rounded-xl mb-8">
        <div class="flex items-center space-x-4">
          <div class="w-14 h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-user-shield text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Manage Admins</h1>
            <p class="text-green-100 mt-1">Administer admin accounts and permissions</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <button id="openModalBtn" onclick="document.getElementById('createModal').classList.remove('hidden')" 
                  class="btn-warning text-white px-5 py-2.5 rounded-lg font-semibold transition flex items-center">
            <i class="fas fa-plus-circle mr-2"></i>Add Admin
          </button>
        </div>
      </header>
      
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Admins</p>
              <p class="text-2xl font-bold text-gray-900"><?= $totalAdmins ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fas fa-user-check text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Active Admins</p>
              <p class="text-2xl font-bold text-gray-900"><?= $activeAdmins ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fas fa-sitemap text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Active Scopes</p>
              <p class="text-2xl font-bold text-gray-900"><?= $activeScopes ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6 card border border-gray-100">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Current Year</p>
              <p class="text-2xl font-bold text-gray-900"><?= date('Y') ?></p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Debug Section -->
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold text-yellow-800 mb-3">Debug: Admin Scope Data</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-yellow-200">
            <thead class="bg-yellow-100">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-yellow-800 uppercase">Admin</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-yellow-800 uppercase">scope_category</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-yellow-800 uppercase">assigned_scope</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-yellow-800 uppercase">assigned_scope_1</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-yellow-800 uppercase">scope_details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-yellow-200">
              <?php foreach ($admins as $admin): ?>
                <tr>
                  <td class="px-4 py-2 text-sm text-yellow-900"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></td>
                  <td class="px-4 py-2 text-sm text-yellow-900"><?= htmlspecialchars($admin['scope_category'] ?? 'NULL') ?></td>
                  <td class="px-4 py-2 text-sm text-yellow-900"><?= htmlspecialchars($admin['assigned_scope'] ?? 'NULL') ?></td>
                  <td class="px-4 py-2 text-sm text-yellow-900"><?= htmlspecialchars($admin['assigned_scope_1'] ?? 'NULL') ?></td>
                  <td class="px-4 py-2 text-sm text-yellow-900"><?= htmlspecialchars($admin['scope_details'] ?? 'NULL') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Filter Card -->
      <div class="bg-white rounded-xl shadow-md p-6 mb-8 card border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">Filter Admins</h2>
            <p class="text-gray-600 text-sm">Filter admins by assigned scope with detailed breakdown</p>
          </div>
          <form method="GET" id="filterForm" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
              <label for="scope" class="block text-sm font-medium text-gray-700 mb-1">Scope Category</label>
              <div class="relative">
                <select name="scope" id="scope" onchange="updateCollegeFilter()" 
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Categories</option>
                  <option value="Academic-Student" <?= $filterScope == 'Academic-Student' ? 'selected' : '' ?>>Academic - Student</option>
                  <option value="Non-Academic-Student" <?= $filterScope == 'Non-Academic-Student' ? 'selected' : '' ?>>Non-Academic - Student</option>
                  <option value="Academic-Faculty" <?= $filterScope == 'Academic-Faculty' ? 'selected' : '' ?>>Academic - Faculty</option>
                  <option value="Non-Academic-Employee" <?= $filterScope == 'Non-Academic-Employee' ? 'selected' : '' ?>>Non-Academic - Employee</option>
                  <option value="Others-Default" <?= $filterScope == 'Others-Default' ? 'selected' : '' ?>>Others - Default</option>
                  <option value="Others-COOP" <?= $filterScope == 'Others-COOP' ? 'selected' : '' ?>>Others - COOP</option>
                  <option value="Special-Scope" <?= $filterScope == 'Special-Scope' ? 'selected' : '' ?>>Special Scope - CSG Admin</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>
            
            <div id="collegeFilterContainer" class="relative <?= in_array($filterScope, ['Academic-Student', 'Academic-Faculty']) ? '' : 'hidden' ?>">
              <label for="college" class="block text-sm font-medium text-gray-700 mb-1">College</label>
              <div class="relative">
                <select name="college" id="college" onchange="updateCourseDepartmentFilter()" 
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Colleges</option>
                  <?php foreach (getColleges() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $filterCollege == $code ? 'selected' : '' ?>><?= $code ?> - <?= $name ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>
            
            <div id="courseFilterContainer" class="relative <?= ($filterScope == 'Academic-Student' && !empty($filterCollege)) ? '' : 'hidden' ?>">
              <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
              <div class="relative">
                <select name="course" id="course" 
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Courses</option>
                  <?php 
                  if (!empty($filterCollege)) {
                      $courses = getCoursesByCollege($filterCollege);
                      foreach ($courses as $code => $name): ?>
                        <option value="<?= $code ?>" <?= $filterCourse == $code ? 'selected' : '' ?>><?= $code ?> - <?= $name ?></option>
                      <?php endforeach;
                  } ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>
            
            <div id="departmentFilterContainer" class="relative <?= ($filterScope == 'Academic-Faculty' && !empty($filterCollege)) ? '' : 'hidden' ?>">
              <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <div class="relative">
                <select name="department" id="department" 
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Departments</option>
                  <?php 
                  if (!empty($filterCollege)) {
                      $departments = getAcademicDepartments()[$filterCollege] ?? [];
                      foreach ($departments as $dept): ?>
                        <option value="<?= $dept ?>" <?= $filterDepartment == $dept ? 'selected' : '' ?>><?= $dept ?></option>
                      <?php endforeach;
                  } ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>
            
            <div class="flex gap-2 self-end">
              <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg font-medium transition flex items-center">
                <i class="fas fa-filter mr-2"></i>Apply Filters
              </button>
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
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="text-xl font-bold text-gray-800">Admin Accounts</h2>
          <div class="flex items-center gap-4">
            <div class="text-sm text-gray-500">
              Showing <?= count($admins) ?> admin accounts
            </div>
            <button onclick="showAdvanceYearModal()" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center">
              <i class="fas fa-calendar-plus mr-2"></i>Advance Year
            </button>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 table-hover">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Scope</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                          <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                            <span class="text-green-800 font-semibold"><?= strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)) ?></span>
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></div>
                          <div class="text-sm text-gray-500"><?= htmlspecialchars($admin['admin_title'] ?? 'Administrator') ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?= htmlspecialchars($admin['email']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php 
                      $scopeLabel = '';
                      $details = [];
                      
                      if (!empty($admin['scope_category'])) {
                          $scopeLabel = getScopeCategoryLabel($admin['scope_category']);
                          
                          // Get scope details from JSON
                          $scopeDetails = [];
                          if (!empty($admin['scope_details'])) {
                              $scopeDetails = json_decode($admin['scope_details'], true);
                          }
                          
                          // Format scope details based on category
                          switch ($admin['scope_category']) {
                              case 'Academic-Student':
                                  if (!empty($admin['assigned_scope'])) {
                                      $details[] = htmlspecialchars($admin['assigned_scope']);
                                  }
                                  
                                  if (!empty($scopeDetails['courses_display'])) {
                                      if ($scopeDetails['courses_display'] === 'All') {
                                          $details[] = 'All Courses';
                                      } else if (strpos($scopeDetails['courses_display'], ',') !== false) {
                                          $details[] = 'Multiple Courses';
                                      } else {
                                          $details[] = 'Course: ' . htmlspecialchars($scopeDetails['courses_display']);
                                      }
                                  }
                                  break;
                                  
                              case 'Academic-Faculty':
                                  if (!empty($admin['assigned_scope'])) {
                                      $details[] = htmlspecialchars($admin['assigned_scope']);
                                  }
                                  
                                  if (!empty($scopeDetails['departments_display'])) {
                                      if ($scopeDetails['departments_display'] === 'All') {
                                          $details[] = 'All Departments';
                                      } else if (strpos($scopeDetails['departments_display'], ',') !== false) {
                                          $details[] = 'Multiple Departments';
                                      } else {
                                          $details[] = 'Department: ' . htmlspecialchars($scopeDetails['departments_display']);
                                      }
                                  }
                                  break;
                                  
                              case 'Non-Academic-Employee':
                                  if (!empty($scopeDetails['departments_display'])) {
                                      if ($scopeDetails['departments_display'] === 'All') {
                                          $details[] = 'All Non-Academic Departments';
                                      } else if (strpos($scopeDetails['departments_display'], ',') !== false) {
                                          $details[] = 'Multiple Departments';
                                      } else {
                                          $details[] = htmlspecialchars($scopeDetails['departments_display']);
                                      }
                                  }
                                  break;
                                  
                              default:
                                  if (!empty($admin['assigned_scope_1'])) {
                                      $details[] = htmlspecialchars($admin['assigned_scope_1']);
                                  }
                          }
                      } else if (!empty($admin['assigned_scope'])) {
                          $scopeLabel = htmlspecialchars($admin['assigned_scope']);
                      } else {
                          $scopeLabel = 'No Scope';
                      }
                      ?>
                      <?php if (!empty($scopeLabel) && $scopeLabel !== 'No Scope'): ?>
                        <span class="scope-badge scope-primary">
                          <?= $scopeLabel ?>
                        </span>
                        <?php if (!empty($details)): ?>
                          <div class="mt-1">
                            <?php foreach ($details as $detail): ?>
                              <span class="scope-badge scope-secondary"><?= $detail ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="scope-badge bg-gray-100 text-gray-800">
                          No Scope
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="status-badge status-<?= $admin['admin_status'] ?>">
                        <i class="fas fa-circle mr-1 text-xs"></i>
                        <?= ucfirst($admin['admin_status']) ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?= htmlspecialchars($admin['academic_year']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                      <button 
                        onclick="toggleAdminStatus(<?= $admin['user_id'] ?>, '<?= $admin['admin_status'] === 'active' ? 'inactive' : 'active' ?>')"
                        class="<?= $admin['admin_status'] === 'active' ? 'btn-danger' : 'btn-success' ?> text-white px-3 py-1 rounded-lg mr-2 inline-flex items-center">
                        <i class="fas fa-<?= $admin['admin_status'] === 'active' ? 'ban' : 'check' ?> mr-1"></i>
                        <?= $admin['admin_status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                      </button>
                      <button 
                        onclick="handleEditClick(<?= $admin['user_id'] ?>)"
                        class="btn-warning text-white px-3 py-1 rounded-lg mr-2 inline-flex items-center">
                        <i class="fas fa-edit mr-1"></i>Edit
                      </button>
                      <form action="delete_admin.php" method="POST" class="inline delete-form" id="delete-form-<?= $admin['user_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $admin['user_id'] ?>">
                        <button type="submit" class="btn-danger text-white px-3 py-1 rounded-lg inline-flex items-center">
                          <i class="fas fa-trash-alt mr-1"></i>Delete
                        </button>
                      </form>
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
  
  <!-- Advance Year Modal -->
  <div id="advanceYearModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
      <h3 class="text-lg font-bold text-gray-900 mb-4">Advance Academic Year</h3>
      <form id="advanceYearForm" onsubmit="advanceAcademicYear(event)">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">New Academic Year</label>
          <input type="text" id="newAcademicYear" class="w-full p-2 border rounded" placeholder="e.g., 2025-2026" required>
        </div>
        <div class="mb-4">
          <p class="text-sm text-gray-600">This will update the academic year for all admin accounts.</p>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="closeAdvanceYearModal()" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded font-medium">
            Cancel
          </button>
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium">
            Advance Year
          </button>
        </div>
      </form>
    </div>
  </div>
  
<script>
// Get data from PHP and convert to JavaScript objects
const colleges = <?php echo json_encode(getColleges()); ?>;
const academicDepartments = <?php echo json_encode(getAcademicDepartments()); ?>;
const nonAcademicDepartments = <?php echo json_encode(getNonAcademicDepartments()); ?>;

// Debug: Log the colleges object
console.log('Colleges object:', colleges);

// Process courses data to match expected format
const coursesByCollege = {};
<?php 
foreach (array_keys(getColleges()) as $college) {
    $courses = getCoursesByCollege($college);
    $coursesArray = [];
    foreach ($courses as $code => $name) {
        $coursesArray[] = ['code' => $code, 'name' => $name];
    }
    echo "coursesByCollege['$college'] = " . json_encode($coursesArray) . ";\n";
    echo "console.log('Added courses for $college:', " . json_encode($coursesArray) . ");\n";
}
?>

// Debug: Log the final coursesByCollege object
console.log('Final coursesByCollege object:', coursesByCollege);

// Multi-level filtering functions
function updateCollegeFilter() {
  const scopeSelect = document.getElementById('scope');
  const collegeContainer = document.getElementById('collegeFilterContainer');
  const courseContainer = document.getElementById('courseFilterContainer');
  const departmentContainer = document.getElementById('departmentFilterContainer');
  
  if (!scopeSelect) return;
  
  const selectedScope = scopeSelect.value;
  
  // Show/hide college filter
  if (['Academic-Student', 'Academic-Faculty'].includes(selectedScope)) {
    collegeContainer.classList.remove('hidden');
  } else {
    collegeContainer.classList.add('hidden');
    courseContainer.classList.add('hidden');
    departmentContainer.classList.add('hidden');
  }
  
  // Don't auto-submit on scope change, let user click Apply button
}

function updateCourseDepartmentFilter() {
  const collegeSelect = document.getElementById('college');
  const courseContainer = document.getElementById('courseFilterContainer');
  const departmentContainer = document.getElementById('departmentFilterContainer');
  const scopeSelect = document.getElementById('scope');
  
  if (!collegeSelect || !scopeSelect) return;
  
  const selectedCollege = collegeSelect.value;
  const selectedScope = scopeSelect.value;
  
  // Show/hide course filter for Academic-Student
  if (selectedScope === 'Academic-Student' && selectedCollege) {
    courseContainer.classList.remove('hidden');
  } else {
    courseContainer.classList.add('hidden');
  }
  
  // Show/hide department filter for Academic-Faculty
  if (selectedScope === 'Academic-Faculty' && selectedCollege) {
    departmentContainer.classList.remove('hidden');
  } else {
    departmentContainer.classList.add('hidden');
  }
  
  // Don't auto-submit on college change, let user click Apply button
}

// Admin status toggle function
function toggleAdminStatus(adminId, newStatus) {
  if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this admin?`)) {
    return;
  }
  
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.classList.remove('hidden');
  }
  
  fetch('manage_admins.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      action: 'toggle_status',
      admin_id: adminId,
      status: newStatus
    })
  })
  .then(response => response.json())
  .then(data => {
    if (loadingOverlay) {
      loadingOverlay.classList.add('hidden');
    }
    
    if (data.success) {
      alert(data.message);
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    if (loadingOverlay) {
      loadingOverlay.classList.add('hidden');
    }
    alert('Request failed: ' + error.message);
  });
}

// Academic year functions
function showAdvanceYearModal() {
  const modal = document.getElementById('advanceYearModal');
  const currentYear = new Date().getFullYear();
  const nextYear = currentYear + 1;
  document.getElementById('newAcademicYear').value = `${currentYear}-${nextYear}`;
  modal.classList.remove('hidden');
}

function closeAdvanceYearModal() {
  document.getElementById('advanceYearModal').classList.add('hidden');
}

function advanceAcademicYear(event) {
  event.preventDefault();
  
  const newYear = document.getElementById('newAcademicYear').value;
  
  if (!confirm(`Are you sure you want to advance the academic year to ${newYear} for all admins?`)) {
    return;
  }
  
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.classList.remove('hidden');
  }
  
  fetch('manage_admins.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      action: 'advance_year',
      academic_year: newYear
    })
  })
  .then(response => response.json())
  .then(data => {
    if (loadingOverlay) {
      loadingOverlay.classList.add('hidden');
    }
    
    if (data.success) {
      alert(data.message);
      window.location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    if (loadingOverlay) {
      loadingOverlay.classList.add('hidden');
    }
    alert('Request failed: ' + error.message);
  });
}

// Define the critical functions inline
function triggerEditAdmin(userId) {
  // Show loading overlay
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.classList.remove('hidden');
  }
  
  fetch('get_admin.php?user_id=' + userId)
    .then(res => {
      if (!res.ok) {
        throw new Error('Network response was not ok');
      }
      return res.json();
    })
    .then(data => {
      // Hide loading overlay
      if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
      }
      
      if (data.status === 'success') {
        if (typeof openUpdateModal === 'function') {
          openUpdateModal(data.data);
        } else {
          alert('Error: Update modal function not loaded. Please refresh the page.');
        }
      } else {
        alert("Error: " + (data.message || "Admin not found."));
      }
    })
    .catch(error => {
      // Hide loading overlay
      if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
      }
      alert("Fetch failed: " + error.message);
    });
}

function handleEditClick(userId) {
  // Check if the function exists
  if (typeof triggerEditAdmin === 'function') {
    triggerEditAdmin(userId);
  } else {
    alert('Error: Edit function not loaded. Please refresh the page.');
  }
}

// Add the missing openUpdateModal function
function openUpdateModal(admin) {
  try {
    // Check if modal exists
    const modal = document.getElementById('updateModal');
    if (!modal) {
      throw new Error('Update modal element not found');
    }
    
    // Check if required form elements exist
    const userIdField = document.getElementById('update_user_id');
    const titleField = document.getElementById('update_admin_title');
    const firstNameField = document.getElementById('update_first_name');
    const lastNameField = document.getElementById('update_last_name');
    const emailField = document.getElementById('update_email');
    
    if (!userIdField || !titleField || !firstNameField || !lastNameField || !emailField) {
      throw new Error('One or more form elements not found');
    }
    
    // Set basic admin information
    userIdField.value = admin.user_id;
    titleField.value = admin.admin_title || '';
    firstNameField.value = admin.first_name;
    lastNameField.value = admin.last_name;
    emailField.value = admin.email;
    
    // Set scope category
    const scopeCategory = document.getElementById('updateScopeCategoryModal');
    if (scopeCategory && admin.scope_category) {
      scopeCategory.value = admin.scope_category;
      
      // Trigger change to populate dynamic fields
      if (typeof updateScopeFieldsForEdit === 'function') {
        updateScopeFieldsForEdit();
        
        // After a short delay, set the specific scope values
        setTimeout(() => {
          if (typeof populateScopeDetailsForEdit === 'function') {
            populateScopeDetailsForEdit(admin);
          }
        }, 100);
      }
    }
    
    // Show modal
    modal.classList.remove('hidden');
    setTimeout(() => firstNameField.focus(), 100);
    
  } catch (error) {
    alert('Error opening modal: ' + error.message);
  }
}

// Add placeholder functions for the ones that might be missing
function updateScopeFieldsForEdit() {
  // This would normally populate the dynamic fields based on scope category
  // For now, we'll just log that it was called
  console.log('updateScopeFieldsForEdit called');
}

function populateScopeDetailsForEdit(admin) {
  // This would normally populate the specific scope details
  // For now, we'll just log that it was called
  console.log('populateScopeDetailsForEdit called with admin:', admin);
}

function closeUpdateModal() {
  const modal = document.getElementById('updateModal');
  if (modal) {
    modal.classList.add('hidden');
  }
}

// Delete form handlers
document.addEventListener('DOMContentLoaded', function() {
  const deleteForms = document.querySelectorAll('.delete-form');
  
  deleteForms.forEach(form => {
    form.addEventListener('submit', function(event) {
      if (!confirm('Are you sure you want to delete this admin? This action cannot be undone.')) {
        event.preventDefault();
        return;
      }
      document.getElementById('loadingOverlay').classList.remove('hidden');
    });
  });
});

// Make functions globally accessible
window.triggerEditAdmin = triggerEditAdmin;
window.openUpdateModal = openUpdateModal;
window.closeUpdateModal = closeUpdateModal;
window.updateScopeFieldsForEdit = updateScopeFieldsForEdit;
window.populateScopeDetailsForEdit = populateScopeDetailsForEdit;
</script>
<script>
// Define functions in global scope
function handleEditClick(userId) {
    // Check if the function exists
    if (typeof triggerEditAdmin === 'function') {
        triggerEditAdmin(userId);
    } else {
        alert('Error: Edit function not loaded. Please refresh the page.');
    }
}

function toggleAdminStatus(adminId, newStatus) {
    if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this admin?`)) {
        return;
    }
    
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('hidden');
    }
    
    fetch('manage_admins.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'toggle_status',
            admin_id: adminId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (loadingOverlay) {
            loadingOverlay.classList.add('hidden');
        }
        
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        if (loadingOverlay) {
            loadingOverlay.classList.add('hidden');
        }
        alert('Request failed: ' + error.message);
    });
}

// Make functions globally accessible
window.handleEditClick = handleEditClick;
window.toggleAdminStatus = toggleAdminStatus;
</script>
</body>
</html>