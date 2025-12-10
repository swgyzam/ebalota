<?php
session_start();

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Simple activity logger
function logActivity(PDO $pdo, int $userId, string $action): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
        ]);
    } catch (PDOException $e) {
        error_log('Activity log insert failed: ' . $e->getMessage());
    }
}

/* ==========================================================
   SHOW RESET FORM (GET)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    $tokenValid = (bool)$row;

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <link rel="icon" href="assets/img/weblogo.png" type="image/png">
      <title>eBalota - Reset Password</title>

      <!-- Tailwind -->
      <script src="https://cdn.tailwindcss.com"></script>

      <!-- Fonts & Icons -->
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
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
                  primary: '#0a5f2d',
                  secondary: '#1e8449',
                  light: '#e8f5e9',
                }
              }
            }
          }
        }
      </script>

      <style>
        body {
          font-family: 'Poppins', sans-serif;
        }

        .nav-link {
          position: relative;
        }

        .nav-link:after {
          content: '';
          position: absolute;
          width: 0;
          height: 2px;
          bottom: -2px;
          left: 0;
          background-color: rgb(6, 81, 16);
          transition: width 0.3s ease;
        }

        .nav-link:hover:after {
          width: 100%;
        }

        .page-background {
          background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
        }

        .form-container {
          opacity: 0;
          transform: translateY(20px);
          animation: fadeInUp 0.6s ease-out forwards;
          background: rgba(255, 255, 255, 0.95);
          backdrop-filter: blur(10px);
          -webkit-backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.4);
          box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
        }

        @keyframes fadeInUp {
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .form-input {
          transition: all 0.3s ease;
        }

        .form-input:focus {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(10, 95, 45, 0.15);
        }

        .btn-primary {
          transition: all 0.3s ease;
        }

        .btn-primary:hover {
          transform: translateY(-2px);
          box-shadow: 0 7px 14px rgba(10, 95, 45, 0.2);
        }

        .password-strength-bar {
          transition: width 0.3s ease;
        }
      </style>
    </head>
    <body class="min-h-screen flex flex-col page-background text-slate-900">

      <!-- NAVIGATION -->
      <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-emerald-900/10 text-black">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center">
          <div class="flex-shrink-0">
            <a href="index.html" class="block">
              <img 
                src="assets/img/ebalota_logo.png" 
                alt="eBalota logo" 
                class="w-32 sm:w-40 md:w-52 lg:w-56 xl:w-60 h-auto"
              >
            </a>
          </div>
          <div class="hidden md:flex space-x-8 items-center text-sm font-medium">
            <a href="register.html" class="nav-link">REGISTER</a>
            <a href="login.html" class="nav-link">LOGIN</a>
          </div>
          <div class="md:hidden"></div>
        </div>
      </nav>

      <main class="flex-grow flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
          <div class="form-container rounded-3xl px-7 py-8 sm:px-8 sm:py-9">

            <?php if ($tokenValid): ?>
              <!-- Valid token: show reset form -->
              <div class="text-center mb-6">
                <div class="mx-auto bg-emerald-100 w-16 h-16 rounded-full flex items-center justify-center mb-3">
                  <i class="fas fa-lock text-cvsu-primary text-2xl"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">
                  Reset <span class="text-cvsu-primary">Password</span>
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                  Create a new password for your account.
                </p>
              </div>

              <form method="POST" action="reset_password.php" class="space-y-4" id="resetForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />

                <!-- New password -->
                <div class="space-y-1">
                  <label for="password" class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    New Password
                  </label>
                  <div class="relative">
                    <input
                      id="password"
                      name="password"
                      type="password"
                      required
                      minlength="8"
                      pattern="(?=.*[A-Z])(?=.*\d).{8,}"
                      title="Password must be at least 8 characters long, include at least one uppercase letter and one number."
                      class="form-input w-full rounded-xl border border-gray-300 bg-slate-50 px-3 py-2.5 pr-9 text-sm placeholder-gray-400 focus:outline-none focus:border-cvsu-primary focus:ring-2 focus:ring-cvsu-primary/40"
                      placeholder="Enter new password"
                    >
                    <span class="absolute right-3 top-2.5 text-gray-400 cursor-pointer toggle-password" data-target="password">
                      <i class="fas fa-eye"></i>
                    </span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500">
                    Must be at least 8 characters with at least 1 uppercase letter and 1 number.
                  </p>

                  <!-- Password strength indicator -->
                  <div class="mt-2">
                    <div class="flex items-center text-xs text-gray-500 mb-1">
                      <span id="passwordStrengthReset" class="font-medium">Password strength:</span>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                        <div id="strengthBarReset" class="h-2 rounded-full password-strength-bar" style="width: 0%"></div>
                      </div>
                    </div>
                    <ul class="text-xs text-gray-500 space-y-1">
                      <li id="lengthCheckReset" class="flex items-center">
                        <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        At least 8 characters
                      </li>
                      <li id="uppercaseCheckReset" class="flex items-center">
                        <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Contains uppercase letter
                      </li>
                      <li id="numberCheckReset" class="flex items-center">
                        <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Contains number
                      </li>
                      <li id="specialCheckReset" class="flex items-center">
                        <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Contains special character
                      </li>
                    </ul>
                  </div>
                </div>

                <!-- Confirm password -->
                <div class="space-y-1">
                  <label for="confirm_password" class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    Confirm Password
                  </label>
                  <div class="relative">
                    <input
                      id="confirm_password"
                      name="confirm_password"
                      type="password"
                      required
                      minlength="8"
                      class="form-input w-full rounded-xl border border-gray-300 bg-slate-50 px-3 py-2.5 pr-9 text-sm placeholder-gray-400 focus:outline-none focus:border-cvsu-primary focus:ring-2 focus:ring-cvsu-primary/40"
                      placeholder="Re-enter new password"
                    >
                    <span class="absolute right-3 top-2.5 text-gray-400 cursor-pointer toggle-password" data-target="confirm_password">
                      <i class="fas fa-eye"></i>
                    </span>
                  </div>
                </div>

                <!-- Client-side error placeholder -->
                <div id="formError" class="hidden text-xs bg-red-50 border border-red-400 text-red-700 px-3 py-2 rounded-xl"></div>

                <button
                  type="submit"
                  class="btn-primary mt-1 w-full inline-flex items-center justify-center gap-2 bg-cvsu-primary text-white font-semibold py-2.5 rounded-full text-sm tracking-wide shadow-sm hover:bg-cvsu-secondary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cvsu-primary focus:ring-offset-emerald-900 transition"
                >
                  <span>Reset Password</span>
                  <i class="fas fa-arrow-right text-xs"></i>
                </button>

                <div class="text-center text-sm mt-3 text-gray-600">
                  Remembered your password?
                  <a href="login.html" class="text-cvsu-primary font-semibold hover:underline">
                    Back to Login
                  </a>
                </div>
              </form>

            <?php else: ?>
              <!-- Invalid or expired token -->
              <div class="text-center mb-6">
                <div class="mx-auto bg-red-100 w-16 h-16 rounded-full flex items-center justify-center mb-3">
                  <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">
                  Invalid <span class="text-red-600">Link</span>
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                  This password reset link is invalid or has expired.
                </p>
              </div>
              <a
                href="forgot_password.html"
                class="btn-primary w-full inline-flex items-center justify-center gap-2 bg-cvsu-primary text-white font-semibold py-2.5 rounded-full text-sm tracking-wide shadow-sm hover:bg-cvsu-secondary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cvsu-primary focus:ring-offset-emerald-900 transition"
              >
                <i class="fas fa-envelope-open-text text-xs"></i>
                <span>Request a New Reset Link</span>
              </a>
              <div class="text-center text-sm mt-3 text-gray-600">
                Back to
                <a href="login.html" class="text-cvsu-primary font-semibold hover:underline">
                  Login
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>

      <footer class="bg-emerald-950 text-emerald-100 text-center text-xs py-4">
        <p>© eBalota. All rights reserved.</p>
      </footer>

      <?php if ($tokenValid): ?>
      <script>
        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(icon => {
          icon.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const iconTag = this.querySelector('i');
            if (input.type === 'password') {
              input.type = 'text';
              iconTag.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
              input.type = 'password';
              iconTag.classList.replace('fa-eye-slash', 'fa-eye');
            }
          });
        });

        const pwInput   = document.getElementById('password');
        const cpwInput  = document.getElementById('confirm_password');
        const form      = document.getElementById('resetForm');
        const errDiv    = document.getElementById('formError');

        const strengthBar   = document.getElementById('strengthBarReset');
        const strengthText  = document.getElementById('passwordStrengthReset');
        const lengthCheck   = document.getElementById('lengthCheckReset');
        const uppercaseCheck= document.getElementById('uppercaseCheckReset');
        const numberCheck   = document.getElementById('numberCheckReset');
        const specialCheck  = document.getElementById('specialCheckReset');

        function updateCheckElement(el, isValid) {
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
          const val = pwInput.value || '';

          const length    = val.length >= 8;
          const uppercase = /[A-Z]/.test(val);
          const number    = /[0-9]/.test(val);
          const special   = /[!@#$%^&*(),.?":{}|<>]/.test(val);

          updateCheckElement(lengthCheck, length);
          updateCheckElement(uppercaseCheck, uppercase);
          updateCheckElement(numberCheck, number);
          updateCheckElement(specialCheck, special);

          let strength = 0;
          if (length)    strength++;
          if (uppercase) strength++;
          if (number)    strength++;
          if (special)   strength++;

          const percent = (strength / 4) * 100;
          strengthBar.style.width = percent + '%';

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

        function validateMatch() {
          if (pwInput.value !== cpwInput.value) {
            cpwInput.setCustomValidity("Passwords don't match");
          } else {
            cpwInput.setCustomValidity('');
          }
        }

        pwInput.addEventListener('input', () => {
          updatePasswordStrength();
          validateMatch();
        });
        cpwInput.addEventListener('input', validateMatch);

        form.addEventListener('submit', function(e) {
          errDiv.classList.add('hidden');
          errDiv.textContent = '';

          validateMatch();

          if (pwInput.value !== cpwInput.value) {
            e.preventDefault();
            errDiv.textContent = 'Passwords do not match.';
            errDiv.classList.remove('hidden');
          }
        });
      </script>
      <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

/* ==========================================================
   PROCESS RESET (POST)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token           = $_POST['token'] ?? '';
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        die("Invalid request.");
    }

    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/\d/', $password)
    ) {
        die("Password must be at least 8 characters, with 1 uppercase letter and 1 number.");
    }

    if ($password !== $confirmPassword) {
        die("Passwords do not match.");
    }

    $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        $msg = urlencode('Invalid or expired password reset link. Please request a new one.');
        header('Location: forgot_password.html?error=' . $msg);
        exit;
    }

    $userId        = (int)$row['user_id'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Get user role (for role-specific logging)
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $roleStmt->execute([$userId]);
    $roleRow = $roleStmt->fetch();
    $userRole = $roleRow['role'] ?? null;

    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$password_hash, $userId]);

        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);

        // Role-aware activity log
        $actionText = in_array($userRole, ['admin','super_admin'], true)
            ? 'Admin password reset via email reset link'
            : 'User password reset via email reset link';

        logActivity($pdo, $userId, $actionText);

        // Success page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1.0" />
          <link rel="icon" href="assets/img/weblogo.png" type="image/png">
          <title>eBalota - Password Reset Successful</title>

          <script src="https://cdn.tailwindcss.com"></script>
          <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
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
                      primary: '#0a5f2d',
                      secondary: '#1e8449',
                      light: '#e8f5e9',
                    }
                  }
                }
              }
            }
          </script>

          <style>
            body { font-family: 'Poppins', sans-serif; }
            .page-background {
              background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            }
            .form-container {
              background: rgba(255, 255, 255, 0.95);
              backdrop-filter: blur(10px);
              -webkit-backdrop-filter: blur(10px);
              border: 1px solid rgba(255, 255, 255, 0.4);
              box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            }
          </style>
        </head>
        <body class="min-h-screen flex flex-col page-background text-slate-900">

          <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-emerald-900/10 text-black">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center">
              <div class="flex-shrink-0">
                <a href="index.html" class="block">
                  <img 
                    src="assets/img/ebalota_logo.png" 
                    alt="eBalota logo" 
                    class="w-32 sm:w-40 md:w-52 lg:w-56 xl:w-60 h-auto"
                  >
                </a>
              </div>
              <div class="hidden md:flex space-x-8 items-center text-sm font-medium">
                <a href="login.html" class="nav-link">LOGIN</a>
              </div>
              <div class="md:hidden"></div>
            </div>
          </nav>

          <main class="flex-grow flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md">
              <div class="form-container rounded-3xl px-7 py-8 sm:px-8 sm:py-9 text-center">
                <div class="mx-auto bg-emerald-100 w-16 h-16 rounded-full flex items-center justify-center mb-4">
                  <i class="fas fa-check text-cvsu-primary text-2xl"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                  Password <span class="text-cvsu-primary">Reset</span> Successful!
                </h2>
                <p class="text-sm text-gray-600 mb-6">
                  Your password has been updated. You can now log in with your new password.
                </p>
                <a
                  href="login.html"
                  class="inline-flex items-center justify-center gap-2 bg-cvsu-primary text-white font-semibold px-8 py-2.5 rounded-full text-sm tracking-wide shadow-sm hover:bg-cvsu-secondary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cvsu-primary transition"
                >
                  <span>Go to Login</span>
                  <i class="fas fa-arrow-right text-xs"></i>
                </a>
              </div>
            </div>
          </main>

          <footer class="bg-emerald-950 text-emerald-100 text-center text-xs py-4">
            <p>© eBalota. All rights reserved.</p>
          </footer>
        </body>
        </html>
        <?php
        exit;

    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        die("System error. Please try again later.");
    }
} else {
    header("Location: login.html");
    exit;
}
