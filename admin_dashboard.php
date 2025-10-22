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

// --- Auth check ---BYPASS for now ---------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// --- Fetch user info including scope ---
// Change to:
$stmt = $pdo->prepare("SELECT role, assigned_scope, force_password_change FROM users WHERE user_id = :userId");

$stmt->execute([':userId' => $userId]);
$user = $stmt->fetch();
$forcePasswordChange = $user['force_password_change'] ?? 0;

if (!$user) {
    // User not found in DB, force logout
    session_destroy();
    header('Location: login.php');
    exit();
}

$role = $user['role'];
$scope = $user['assigned_scope'] ?? null;

// --- Fetch dashboard stats ---

// Total Voters
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_voters 
                           FROM users 
                           WHERE is_verified = 1 
                             AND is_admin = 0 
                             AND assigned_scope = :scope");
    $stmt->execute([':scope' => $scope]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) AS total_voters 
                         FROM users 
                         WHERE is_verified = 1 
                           AND is_admin = 0");
}
$total_voters = $stmt->fetch()['total_voters'];

// Total Elections
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_elections 
                           FROM elections 
                           WHERE assigned_admin_id = :adminId");
    $stmt->execute([':adminId' => $userId]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) AS total_elections FROM elections");
}
$total_elections = $stmt->fetch()['total_elections'];

// Ongoing Elections
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS ongoing_elections 
                           FROM elections 
                           WHERE assigned_admin_id = :adminId 
                             AND status = 'ongoing'");
    $stmt->execute([':adminId' => $userId]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) AS ongoing_elections 
                         FROM elections WHERE status = 'ongoing'");
}
$ongoing_elections = $stmt->fetch()['ongoing_elections'];

// --- Election status auto-update (only for super_admin) ---
if ($role === 'super_admin') {
    $now = date('Y-m-d H:i:s');
    $pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
    $pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
    $pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");
}

// --- Fetch elections for display ---
if ($role === 'admin') {
    $electionStmt = $pdo->prepare("SELECT * FROM elections 
                                   WHERE assigned_admin_id = :adminId
                                   ORDER BY start_datetime DESC");
    $electionStmt->execute([':adminId' => $userId]);
    $elections = $electionStmt->fetchAll();
} else {
    $electionStmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
    $elections = $electionStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    ::-webkit-scrollbar {
      width: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: var(--cvsu-green-light);
      border-radius: 3px;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900-keft font-sans">

<div class="flex min-h-screen">

<?php include 'sidebar.php'; ?>

<!-- Top Bar -->
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:rgb(25, 72, 49);"> 
  <div class="flex items-center space-x-4">
    <h1 class="text-2xl font-bold">
      <?php echo htmlspecialchars($scope) . " ADMIN DASHBOARD"; ?>
    </h1>
  </div>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A10.95 10.95 0 0112 15c2.485 0 4.779.91 6.879 2.404M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  </div>
</header>



<!-- Main Content Area -->
<main class="flex-1 pt-20 px-8 ml-64">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- LEFT COLUMN: Statistics Cards -->
    <div class="space-y-6">
      <!-- Total Population -->
      <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-[var(--cvsu-green)] hover:shadow-2xl transition-shadow duration-300">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Population</h2>
            <p class="text-2xl md:text-4xl font-bold text-[var(--cvsu-green-dark)] mt-2 md:mt-3"><?= $total_voters ?></p>
          </div>
        </div>
      </div>

      <!-- Total Elections -->
      <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-yellow-400 hover:shadow-2xl transition-shadow duration-300">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
            <p class="text-2xl md:text-4xl font-bold text-yellow-600 mt-2 md:mt-3"><?= $total_elections ?></p>
          </div>
        </div>
      </div>

      <!-- Ongoing Elections -->
      <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border-l-8 border-blue-500 hover:shadow-2xl transition-shadow duration-300">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
            <p class="text-2xl md:text-4xl font-bold text-blue-600 mt-2 md:mt-3"><?= $ongoing_elections ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: Bar Chart -->
    <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
      <h2 class="text-base md:text-lg font-semibold text-gray-700 mb-4">Population of Voters per Colleges</h2>
      <canvas id="collegeChart" class="w-full h-64"></canvas>
    </div>
  </div>
  
  <!-- Recent Elections Section -->
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg mt-6">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Recent Elections</h2>
    <?php if (!empty($elections)): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scope</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($elections as $election): ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($election['title']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($election['start_datetime'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($election['end_datetime'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($election['allowed_colleges']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full 
                    <?= $election['status'] === 'ongoing' ? 'bg-green-100 text-green-800' : 
                       ($election['status'] === 'upcoming' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                    <?= ucfirst($election['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500">No elections found for your assignment.</p>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Force Password Change Modal -->
<div id="forcePasswordChangeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative">
        <!-- Close button (X) at top right -->
        <button onclick="closePasswordModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl">
            &times;
        </button>
        
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-[var(--cvsu-green-light)] rounded-full flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-[var(--cvsu-green-dark)]">Change Your Password</h2>
            <p class="text-gray-600 mt-2">For security reasons, you must change your password before continuing.</p>
        </div>
        
        <form id="forcePasswordChangeForm" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <div class="relative">
                    <input type="password" id="newPassword" name="new_password" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <div class="mt-2">
                    <div class="flex items-center text-xs text-gray-500 mb-1">
                        <span id="passwordStrength" class="font-medium">Password strength:</span>
                        <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                            <div id="strengthBar" class="h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    <ul class="text-xs text-gray-500 space-y-1">
                        <li id="lengthCheck" class="flex items-center">
                            <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            At least 8 characters
                        </li>
                        <li id="uppercaseCheck" class="flex items-center">
                            <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Contains uppercase letter
                        </li>
                        <li id="numberCheck" class="flex items-center">
                            <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Contains number
                        </li>
                        <li id="specialCheck" class="flex items-center">
                            <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Contains special character
                        </li>
                    </ul>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <div class="relative">
                    <input type="password" id="confirmPassword" name="confirm_password" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                    <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <div id="matchError" class="mt-1 text-xs text-red-500 hidden">Passwords do not match</div>
            </div>
            
            <!-- Notification Container -->
            <div id="notificationContainer" class="space-y-3">
                <!-- Error Notification -->
                <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Error</h3>
                            <div class="mt-1 text-sm text-red-700" id="passwordErrorText"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Success Notification -->
                <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">Success</h3>
                            <div class="mt-1 text-sm text-green-700">
                                Password updated successfully! Redirecting to dashboard...
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Loading Notification -->
                <div id="passwordLoading" class="hidden bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="animate-spin h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Processing</h3>
                            <div class="mt-1 text-sm text-blue-700">
                                Updating your password...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Button Container -->
            <div class="flex justify-center pt-4">
                <button type="submit" id="submitBtn" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-8 py-3 rounded-lg font-medium flex items-center justify-center min-w-[180px] transition-all duration-200 transform hover:scale-105">
                    <span id="submitBtnText">Update Password</span>
                    <svg id="submitLoader" class="ml-2 h-5 w-5 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const collegeChart = new Chart(document.getElementById('collegeChart'), {
  type: 'bar',
  data: {
    labels: ['CAS', 'CCJ', 'CED', 'CEIT', 'CON', 'CEMDS', 'CTHM', 'CAFENR', 'CSPEAR', 'CVMBS'],
    datasets: [{
      data: [1517, 792, 770, 1213, 760, 1864, 819, 620, 397, 246],
      backgroundColor: ['#e62e00', '#003300','#0033cc', '#ff9933', '#b3b3b3', '#008000', '#ff6699', '#00b33c', '#993300', '#990099' ]
    }]
  },
  options: { plugins: { legend: { display: false } } }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if password change is required
    const forcePasswordChange = <?= $forcePasswordChange ?>;
    
    if (forcePasswordChange) {
        document.getElementById('forcePasswordChangeModal').classList.remove('hidden');
        // Prevent interaction with the rest of the page
        document.body.style.pointerEvents = 'none';
        document.getElementById('forcePasswordChangeModal').style.pointerEvents = 'auto';
    }
    
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.querySelector('svg').innerHTML = type === 'password' 
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    });
    
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        this.querySelector('svg').innerHTML = type === 'password' 
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    });
    
    // Password strength validation
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('passwordStrength');
        
        // Check password requirements
        const length = password.length >= 8;
        const uppercase = /[A-Z]/.test(password);
        const number = /[0-9]/.test(password);
        const special = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        // Update check marks
        updateCheck('lengthCheck', length);
        updateCheck('uppercaseCheck', uppercase);
        updateCheck('numberCheck', number);
        updateCheck('specialCheck', special);
        
        // Calculate strength
        let strength = 0;
        if (length) strength++;
        if (uppercase) strength++;
        if (number) strength++;
        if (special) strength++;
        
        // Update strength bar
        const strengthPercentage = (strength / 4) * 100;
        strengthBar.style.width = strengthPercentage + '%';
        
        if (strength === 0) {
            strengthBar.className = 'h-2 rounded-full bg-red-500 transition-all duration-300';
            strengthText.textContent = 'Password strength: Very Weak';
            strengthText.className = 'font-medium text-red-500';
        } else if (strength === 1) {
            strengthBar.className = 'h-2 rounded-full bg-orange-500 transition-all duration-300';
            strengthText.textContent = 'Password strength: Weak';
            strengthText.className = 'font-medium text-orange-500';
        } else if (strength === 2) {
            strengthBar.className = 'h-2 rounded-full bg-yellow-500 transition-all duration-300';
            strengthText.textContent = 'Password strength: Medium';
            strengthText.className = 'font-medium text-yellow-500';
        } else if (strength === 3) {
            strengthBar.className = 'h-2 rounded-full bg-blue-500 transition-all duration-300';
            strengthText.textContent = 'Password strength: Strong';
            strengthText.className = 'font-medium text-blue-500';
        } else {
            strengthBar.className = 'h-2 rounded-full bg-green-500 transition-all duration-300';
            strengthText.textContent = 'Password strength: Very Strong';
            strengthText.className = 'font-medium text-green-500';
        }
        
        // Check password match
        checkPasswordMatch();
    });
    
    // Confirm password validation
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    
    function updateCheck(id, isValid) {
        const element = document.getElementById(id);
        const icon = element.querySelector('svg');
        
        if (isValid) {
            element.classList.remove('text-gray-500');
            element.classList.add('text-green-500');
            icon.classList.remove('text-gray-400');
            icon.classList.add('text-green-500');
        } else {
            element.classList.remove('text-green-500');
            element.classList.add('text-gray-500');
            icon.classList.remove('text-green-500');
            icon.classList.add('text-gray-400');
        }
    }
    
    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const matchError = document.getElementById('matchError');
        
        if (confirmPassword && password !== confirmPassword) {
            matchError.classList.remove('hidden');
            confirmPasswordInput.classList.add('border-red-500');
        } else {
            matchError.classList.add('hidden');
            confirmPasswordInput.classList.remove('border-red-500');
        }
    }
    
    // Handle password change form submission
    const forcePasswordChangeForm = document.getElementById('forcePasswordChangeForm');
    const passwordError = document.getElementById('passwordError');
    const passwordSuccess = document.getElementById('passwordSuccess');
    const passwordLoading = document.getElementById('passwordLoading');
    const passwordErrorText = document.getElementById('passwordErrorText');
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnText = document.getElementById('submitBtnText');
    const submitLoader = document.getElementById('submitLoader');
    
    if (forcePasswordChangeForm) {
        forcePasswordChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Hide all notifications
            passwordError.classList.add('hidden');
            passwordSuccess.classList.add('hidden');
            passwordLoading.classList.remove('hidden');
            
            const newPassword = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Check password requirements
            const length = newPassword.length >= 8;
            const uppercase = /[A-Z]/.test(newPassword);
            const number = /[0-9]/.test(newPassword);
            const special = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
            
            // Calculate strength (0-4)
            let strength = 0;
            if (length) strength++;
            if (uppercase) strength++;
            if (number) strength++;
            if (special) strength++;
            
            // Check minimum requirements
            if (!length) {
                passwordLoading.classList.add('hidden');
                passwordErrorText.textContent = "Password must be at least 8 characters long.";
                passwordError.classList.remove('hidden');
                return;
            }
            
            if (strength < 3) {
                passwordLoading.classList.add('hidden');
                passwordErrorText.textContent = "Password is not strong enough. Please include at least 2 of the following: uppercase letter, number, special character.";
                passwordError.classList.remove('hidden');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                passwordLoading.classList.add('hidden');
                passwordErrorText.textContent = "Passwords do not match.";
                passwordError.classList.remove('hidden');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtnText.textContent = 'Updating...';
            submitLoader.classList.remove('hidden');
            
            // Submit the form
            fetch('update_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading notification
                passwordLoading.classList.add('hidden');
                
                if (data.status === 'success') {
                    // Show success notification
                    passwordSuccess.classList.remove('hidden');
                    
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtnText.textContent = 'Update Password';
                    submitLoader.classList.add('hidden');
                    
                    // Hide the modal after delay and redirect
                    setTimeout(() => {
                        document.getElementById('forcePasswordChangeModal').classList.add('hidden');
                        document.body.style.pointerEvents = 'auto';
                        
                        // Show a brief loading overlay during redirect
                        const redirectOverlay = document.createElement('div');
                        redirectOverlay.className = 'fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center';
                        redirectOverlay.innerHTML = `
                            <div class="text-center">
                                <svg class="animate-spin h-12 w-12 text-[var(--cvsu-green)] mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-lg font-medium text-gray-700">Redirecting to dashboard...</p>
                            </div>
                        `;
                        document.body.appendChild(redirectOverlay);
                        
                        // Reload the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }, 2000);
                } else {
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtnText.textContent = 'Update Password';
                    submitLoader.classList.add('hidden');
                    
                    // Show error
                    passwordErrorText.textContent = data.message || "Failed to update password.";
                    passwordError.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Hide loading notification
                passwordLoading.classList.add('hidden');
                
                // Reset button state
                submitBtn.disabled = false;
                submitBtnText.textContent = 'Update Password';
                submitLoader.classList.add('hidden');
                
                // Show error
                passwordErrorText.textContent = "An error occurred. Please try again.";
                passwordError.classList.remove('hidden');
            });
        });
    }
});

function closePasswordModal() {
    // Prevent closing if password change is required
    const forcePasswordChange = <?= $forcePasswordChange ?>;
    if (forcePasswordChange) {
        return;
    }
    document.getElementById('forcePasswordChangeModal').classList.add('hidden');
    document.body.style.pointerEvents = 'auto';
}
</script>

</body>
</html>
