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
// Get counts for stats cards
$totalAdmins = count($admins);
$activeScopes = [];
foreach ($admins as $admin) {
    if (!empty($admin['assigned_scope'])) {
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
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fas fa-user-shield text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">System Access</p>
              <p class="text-2xl font-bold text-gray-900">Full</p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Filter Card -->
      <div class="bg-white rounded-xl shadow-md p-6 mb-8 card border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">Filter Admins</h2>
            <p class="text-gray-600 text-sm">Filter admins by assigned scope</p>
          </div>
          <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
              <label for="scope" class="block text-sm font-medium text-gray-700 mb-1">Scope</label>
              <div class="relative">
                <select name="scope" id="scope" onchange="this.form.submit()" 
                        class="appearance-none block w-full pl-3 pr-10 py-2.5 text-base border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-lg">
                  <option value="">All Scopes</option>
                  <optgroup label="Colleges">
                    <option value="CAFENR">CAFENR</option>
                    <option value="CEIT">CEIT</option>
                    <option value="CAS">CAS</option>
                    <option value="CVMBS">CVMBS</option>
                    <option value="CED">CED</option>
                    <option value="CEMDS">CEMDS</option>
                    <option value="CSPEAR">CSPEAR</option>
                    <option value="CCJ">CCJ</option>
                    <option value="CON">CON</option>
                    <option value="CTHM">CTHM</option>
                    <option value="COM">COM</option>
                    <option value="GS-OLC">GS-OLC</option>
                  </optgroup>
                  <optgroup label="Other Sectors">
                    <option value="Faculty Association">Faculty Association</option>
                    <option value="COOP">Cooperative</option>
                    <option value="Non-Academic">Non-Academic</option>
                  </optgroup>
                  <optgroup label="Special Scope">
                    <option value="CSG Admin">CSG Admin</option>
                  </optgroup>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
            </div>
            <button type="button" onclick="window.location.href='manage_admins.php'" 
                    class="self-end bg-gray-200 hover:bg-gray-300 px-4 py-2.5 rounded-lg font-medium transition flex items-center">
              <i class="fas fa-sync-alt mr-2"></i>Reset
            </button>
          </form>
        </div>
      </div>
      
      <!-- Admins Table -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden card border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="text-xl font-bold text-gray-800">Admin Accounts</h2>
          <div class="text-sm text-gray-500">
            Showing <?= count($admins) ?> admin accounts
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 table-hover">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Scope</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($admins)): ?>
              <tr>
                <td colspan="4" class="px-6 py-12 text-center">
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
                          <div class="text-sm text-gray-500">Administrator</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?= htmlspecialchars($admin['email']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if (!empty($admin['assigned_scope'])): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                          <?= htmlspecialchars($admin['assigned_scope']) ?>
                        </span>
                      <?php else: ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                          No Scope
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                      <button 
                        onclick='triggerEditAdmin(<?= $admin["user_id"] ?>)'
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
  
<script>
let selectedAdmin = null;
function triggerEditAdmin(userId) {
  // Show loading overlay
  document.getElementById('loadingOverlay').classList.remove('hidden');
  
  fetch('get_admin.php?user_id=' + userId)
    .then(res => res.json())
    .then(data => {
      // Hide loading overlay
      document.getElementById('loadingOverlay').classList.add('hidden');
      
      if (data.status === 'success') {
        openUpdateModal(data.data);
      } else {
        alert("Admin not found.");
      }
    })
    .catch(() => {
      // Hide loading overlay
      document.getElementById('loadingOverlay').classList.add('hidden');
      alert("Fetch failed");
    });
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
// Show loading overlay when submitting forms
document.addEventListener('DOMContentLoaded', function() {
  const deleteForms = document.querySelectorAll('.delete-form');
  deleteForms.forEach(form => {
    form.addEventListener('submit', function(event) {
      // Show confirmation dialog
      if (!confirm('Are you sure you want to delete this admin? This action cannot be undone.')) {
        event.preventDefault(); // Cancel form submission
        return;
      }
      
      // Show loading overlay if confirmed
      document.getElementById('loadingOverlay').classList.remove('hidden');
    });
  });
});
</script>
</body>
</html>