<?php
session_start();
date_default_timezone_set('Asia/Manila');

// === DB CONNECTION ===
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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ===== AUTH CHECK =====
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'voter') {
    header('Location: login.html');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Load some basic user info for header
$userStmt = $pdo->prepare("
    SELECT first_name, position, department, course, force_password_change
    FROM users
    WHERE user_id = :uid
    LIMIT 1
");
$userStmt->execute([':uid' => $user_id]);
$user = $userStmt->fetch();

if (!$user) {
    die('User not found.');
}

$first_name = trim($user['first_name'] ?? '');
$position   = trim($user['position'] ?? '');
$department = trim($user['department'] ?? '');
$course     = trim($user['course'] ?? '');
$force_password_flag = (int)($user['force_password_change'] ?? 0);
$position_label = $position !== '' ? ucfirst($position) : '';

// Load recent activity logs for this voter
$logs = [];
$logs_error = '';
try {
    $logStmt = $pdo->prepare("
        SELECT log_id, action, timestamp
        FROM activity_logs
        WHERE user_id = :uid
        ORDER BY timestamp DESC
        LIMIT 50
    ");
    $logStmt->execute([':uid' => $user_id]);
    $logs = $logStmt->fetchAll();
} catch (PDOException $e) {
    $logs_error = 'Unable to load activity logs at this time.';
}

// Include sidebar (with toggleSidebar exposed globally)
include 'voters_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eBalota - Activity Logs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Poppins', 'system-ui', 'sans-serif'],
          },
          colors: {
            cvsu: {
              dark: '#154734',
              primary: '#1E6F46',
              light: '#37A66B',
              yellow: '#FFD166',
            }
          }
        }
      }
    }
  </script>

  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }

    body {
      font-family: 'Poppins', system-ui, sans-serif;
    }

    .password-strength-bar {
      transition: width 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

  <!-- Change Password Modal (same as profile) -->
  <div id="forcePasswordChangeModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 relative">
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
        <p class="text-gray-600 mt-2 text-sm">For security reasons, please choose a strong password.</p>
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
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div class="mt-2">
                  <div class="flex items-center text-xs text-gray-500 mb-1">
                      <span id="passwordStrength" class="font-medium">Password strength:</span>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                          <div id="strengthBar" class="h-2 rounded-full password-strength-bar" style="width: 0%"></div>
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
          
          <div id="notificationContainer" class="space-y-3">
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
                              Password updated successfully! Redirecting...
                          </div>
                      </div>
                  </div>
              </div>
              
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

  <div class="flex">
    <!-- Main Content -->
    <main class="flex-1 px-4 py-6 md:px-8 md:py-8 md:ml-64">
      <div class="w-full max-w-[1600px] mx-auto px-2 md:px-6 space-y-6">
        <!-- Header card -->
        <header class="bg-[var(--cvsu-green-dark)] text-white px-4 py-4 md:px-6 md:py-5 flex justify-between items-center shadow-md rounded-3xl">
          <div class="flex items-center gap-3">
            <!-- Hamburger (mobile) -->
            <button class="md:hidden w-9 h-9 bg-white bg-opacity-15 rounded-full flex items-center justify-center hover:bg-opacity-25 focus:outline-none" onclick="toggleSidebar()">
              <i class="fas fa-bars text-sm"></i>
            </button>
            <div class="flex flex-col">
              <h1 class="text-xl md:text-2xl font-bold">Activity Logs</h1>
              <p class="text-green-100 mt-1 text-xs md:text-sm">
                Recent actions associated with your account.
              </p>
            </div>
          </div>

          <div class="flex items-center space-x-2 relative">
            <?php if ($department !== '' || $course !== ''): ?>
              <span class="text-green-100 text-xs md:text-sm hidden sm:block text-right">
                <?= htmlspecialchars($department ?: '') ?>
                <?= $department !== '' && $course !== '' ? ' • ' : '' ?>
                <?= htmlspecialchars($course ?: '') ?>
              </span>
            <?php endif; ?>

            <div class="relative">
              <!-- Avatar button -->
              <button
                id="userMenuButton"
                type="button"
                class="w-9 h-9 md:w-10 md:h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center hover:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[var(--cvsu-green-dark)] focus:ring-white"
              >
                <i class="fas fa-user text-white text-sm md:text-base"></i>
              </button>

              <!-- Dropdown -->
              <div
                id="userMenuDropdown"
                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 text-sm"
              >
                <button
                  type="button"
                  id="changePasswordMenu"
                  class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                >
                  <i class="fas fa-key text-gray-500 text-xs"></i>
                  <span>Change Password</span>
                </button>
                <a
                  href="voter_activity_logs.php"
                  class="block px-4 py-2 text-gray-700 bg-gray-50 flex items-center gap-2 cursor-default"
                >
                  <i class="fas fa-clock-rotate-left text-gray-500 text-xs"></i>
                  <span>Activity Logs</span>
                </a>
              </div>
            </div>
          </div>
        </header>

        <!-- Activity Logs Card -->
        <section class="bg-white rounded-3xl shadow-md p-6 md:p-8">
          <?php if ($logs_error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md text-sm text-red-700">
              <p><?= htmlspecialchars($logs_error) ?></p>
            </div>
          <?php elseif (empty($logs)): ?>
            <div class="text-center py-8">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-gray-600 text-sm md:text-base">
                No activity logs found for your account yet.
              </p>
            </div>
          <?php else: ?>
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-sm md:text-base font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list text-emerald-600"></i>
                Recent Activity
              </h2>
              <p class="text-xs text-gray-500">
                Showing latest <?= count($logs) ?> entries
              </p>
            </div>

            <!-- Timeline-style list -->
            <div class="space-y-4">
              <?php foreach ($logs as $log): ?>
                <?php
                  $ts   = $log['timestamp'] ?? '';
                  $when = $ts ? date('M d, Y h:i A', strtotime($ts)) : '';
                  $action = trim($log['action'] ?? '');
                ?>
                <div class="flex items-start gap-3 md:gap-4">
                  <div class="mt-1">
                    <div class="w-2 h-2 md:w-2.5 md:h-2.5 rounded-full bg-[var(--cvsu-green)]"></div>
                  </div>
                  <div class="flex-1">
                    <p class="text-xs md:text-sm text-gray-500"><?= htmlspecialchars($when) ?></p>
                    <p class="text-sm md:text-base text-gray-800 mt-0.5 break-words">
                      <?= nl2br(htmlspecialchars($action)) ?>
                    </p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>
    </main>
  </div>

  <script>
    // Sidebar toggle from header burger
    function toggleSidebar() {
      if (typeof window.toggleSidebar === 'function') {
        window.toggleSidebar();
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      const forcePasswordChange = <?= $force_password_flag ?>;
      if (forcePasswordChange === 1) {
        const modal = document.getElementById('forcePasswordChangeModal');
        if (modal) {
          modal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        }
      }

      // Profile dropdown
      const userMenuButton    = document.getElementById('userMenuButton');
      const userMenuDropdown  = document.getElementById('userMenuDropdown');
      const changePasswordMenu = document.getElementById('changePasswordMenu');

      if (userMenuButton && userMenuDropdown) {
        userMenuButton.addEventListener('click', (e) => {
          e.stopPropagation();
          userMenuDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
          if (!userMenuDropdown.classList.contains('hidden')) {
            if (!userMenuDropdown.contains(e.target) && e.target !== userMenuButton) {
              userMenuDropdown.classList.add('hidden');
            }
          }
        });
      }

      if (changePasswordMenu) {
        changePasswordMenu.addEventListener('click', () => {
          const modal = document.getElementById('forcePasswordChangeModal');
          if (!modal) return;
          modal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
          if (userMenuDropdown) userMenuDropdown.classList.add('hidden');
        });
      }

      // Password inputs & strength
      const passwordInput         = document.getElementById('newPassword');
      const confirmPasswordInput  = document.getElementById('confirmPassword');
      const togglePasswordBtn     = document.getElementById('togglePassword');
      const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

      if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', () => {
          const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          passwordInput.setAttribute('type', type);
        });
      }

      if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', () => {
          const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
          confirmPasswordInput.setAttribute('type', type);
        });
      }

      const strengthBar   = document.getElementById('strengthBar');
      const strengthText  = document.getElementById('passwordStrength');
      const lengthCheck   = document.getElementById('lengthCheck');
      const uppercaseCheck= document.getElementById('uppercaseCheck');
      const numberCheck   = document.getElementById('numberCheck');
      const specialCheck  = document.getElementById('specialCheck');

      function updateCheck(el, isValid) {
        const icon = el.querySelector('svg');
        if (isValid) {
          el.classList.remove('text-gray-500');
          el.classList.add('text-green-500');
          icon.classList.remove('text-gray-400');
          icon.classList.add('text-green-500');
        } else {
          el.classList.remove('text-green-500');
          el.classList.add('text-gray-500');
          icon.classList.remove('text-green-500');
          icon.classList.add('text-gray-400');
        }
      }

      function updatePasswordStrength() {
        if (!passwordInput) return;
        const val = passwordInput.value || '';

        const length   = val.length >= 8;
        const uppercase= /[A-Z]/.test(val);
        const number   = /[0-9]/.test(val);
        const special  = /[!@#$%^&*(),.?":{}|<>]/.test(val);

        updateCheck(lengthCheck, length);
        updateCheck(uppercaseCheck, uppercase);
        updateCheck(numberCheck, number);
        updateCheck(specialCheck, special);

        let strength = 0;
        if (length)    strength++;
        if (uppercase) strength++;
        if (number)    strength++;
        if (special)   strength++;

        const strengthPercentage = (strength / 4) * 100;
        strengthBar.style.width = strengthPercentage + '%';

        if (strength === 0) {
          strengthBar.className = 'h-2 rounded-full bg-red-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Weak';
          strengthText.className = 'font-medium text-red-500';
        } else if (strength === 1) {
          strengthBar.className = 'h-2 rounded-full bg-orange-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Weak';
          strengthText.className = 'font-medium text-orange-500';
        } else if (strength === 2) {
          strengthBar.className = 'h-2 rounded-full bg-yellow-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Medium';
          strengthText.className = 'font-medium text-yellow-500';
        } else if (strength === 3) {
          strengthBar.className = 'h-2 rounded-full bg-blue-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Strong';
          strengthText.className = 'font-medium text-blue-500';
        } else {
          strengthBar.className = 'h-2 rounded-full bg-green-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Strong';
          strengthText.className = 'font-medium text-green-500';
        }
      }

      function checkPasswordMatch() {
        if (!passwordInput || !confirmPasswordInput) return;
        const passwordVal = passwordInput.value;
        const confirmVal  = confirmPasswordInput.value;
        const matchError  = document.getElementById('matchError');

        if (confirmVal && passwordVal !== confirmVal) {
          matchError.classList.remove('hidden');
          confirmPasswordInput.classList.add('border-red-500');
        } else {
          matchError.classList.add('hidden');
          confirmPasswordInput.classList.remove('border-red-500');
        }
      }

      if (passwordInput) {
        passwordInput.addEventListener('input', () => {
          updatePasswordStrength();
          checkPasswordMatch();
        });
      }
      if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
      }

      const forcePasswordChangeForm = document.getElementById('forcePasswordChangeForm');
      const passwordError   = document.getElementById('passwordError');
      const passwordSuccess = document.getElementById('passwordSuccess');
      const passwordLoading = document.getElementById('passwordLoading');
      const passwordErrorText = document.getElementById('passwordErrorText');
      const submitBtn       = document.getElementById('submitBtn');
      const submitBtnText   = document.getElementById('submitBtnText');
      const submitLoader    = document.getElementById('submitLoader');

      if (forcePasswordChangeForm && passwordInput && confirmPasswordInput) {
        forcePasswordChangeForm.addEventListener('submit', function(e) {
          e.preventDefault();

          passwordError.classList.add('hidden');
          passwordSuccess.classList.add('hidden');
          passwordLoading.classList.remove('hidden');

          const newPassword     = passwordInput.value;
          const confirmPassword = confirmPasswordInput.value;

          const length   = newPassword.length >= 8;
          const uppercase= /[A-Z]/.test(newPassword);
          const number   = /[0-9]/.test(newPassword);
          const special  = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);

          let strength = 0;
          if (length)    strength++;
          if (uppercase) strength++;
          if (number)    strength++;
          if (special)   strength++;

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

          submitBtn.disabled = true;
          submitBtnText.textContent = 'Updating...';
          submitLoader.classList.remove('hidden');

          fetch('update_voters_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_password: newPassword })
          })
          .then(response => response.json())
          .then(data => {
            passwordLoading.classList.add('hidden');

            if (data.status === 'success') {
              passwordSuccess.classList.remove('hidden');
              submitBtn.disabled = false;
              submitBtnText.textContent = 'Update Password';
              submitLoader.classList.add('hidden');

              setTimeout(() => {
                const modal = document.getElementById('forcePasswordChangeModal');
                if (modal) modal.classList.add('hidden');
                document.body.style.overflow = '';

                const overlay = document.createElement('div');
                overlay.className = 'fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center';
                overlay.innerHTML = `
                  <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-[var(--cvsu-green)] mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg font-medium text-gray-700">Reloading activity logs...</p>
                  </div>
                `;
                document.body.appendChild(overlay);

                setTimeout(() => {
                  window.location.reload();
                }, 1500);
              }, 1500);

            } else {
              submitBtn.disabled = false;
              submitBtnText.textContent = 'Update Password';
              submitLoader.classList.add('hidden');

              passwordErrorText.textContent = data.message || "Failed to update password.";
              passwordError.classList.remove('hidden');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            passwordLoading.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtnText.textContent = 'Update Password';
            submitLoader.classList.add('hidden');
            passwordErrorText.textContent = "An error occurred. Please try again.";
            passwordError.classList.remove('hidden');
          });
        });
      }
    });

    function closePasswordModal() {
      const modal = document.getElementById('forcePasswordChangeModal');
      if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
      }
    }
  </script>
</body>
</html>
