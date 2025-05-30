<?php
session_start();

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
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Show reset form for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        die("Invalid or expired token.");
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <title>Reset Password</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <style>
        .eye-btn {
          cursor: pointer;
          position: absolute;
          right: 12px;
          top: 38px;
          font-size: 18px;
          color: #006400; /* CvSU green */
          user-select: none;
        }
      </style>
    </head>
    <body class="flex items-center justify-center min-h-screen bg-gray-100 p-6">
      <form method="POST" action="reset_password.php" class="bg-white p-8 rounded shadow-md w-full max-w-sm relative">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />

        <div class="mb-6 relative">
          <label for="password" class="block mb-2 font-semibold text-gray-700">New Password</label>
          <input id="password" name="password" type="password" required minlength="8"
            pattern="(?=.*[A-Z])(?=.*\d).{8,}"
            title="Password must be at least 8 characters long, include at least one uppercase letter and one number."
            class="w-full border border-gray-300 rounded px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-green-700" />
          <span class="eye-btn" onclick="togglePassword('password', this)" aria-label="Toggle password visibility" role="button">👁️</span>
          <p class="mt-1 text-sm text-gray-500">Must be 8+ characters, with at least 1 uppercase letter and 1 number.</p>
        </div>

        <div class="mb-6 relative">
          <label for="confirm_password" class="block mb-2 font-semibold text-gray-700">Confirm Password</label>
          <input id="confirm_password" name="confirm_password" type="password" required minlength="8"
            class="w-full border border-gray-300 rounded px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-green-700" />
          <span class="eye-btn" onclick="togglePassword('confirm_password', this)" aria-label="Toggle confirm password visibility" role="button">👁️</span>
        </div>

        <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-3 rounded transition">
          Reset Password
        </button>
      </form>

      <script>
        function togglePassword(fieldId, btn) {
          const input = document.getElementById(fieldId);
          if (input.type === "password") {
            input.type = "text";
            btn.textContent = "👁️"; // keep same icon
          } else {
            input.type = "password";
            btn.textContent = "👁️";
          }
        }

        document.querySelector('form').addEventListener('submit', function(e) {
          const pw = document.getElementById('password').value;
          const cpw = document.getElementById('confirm_password').value;
          if(pw !== cpw) {
            e.preventDefault();
            alert('Passwords do not match.');
          }
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}

// Process POST request for resetting password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        die("Invalid request.");
    }
    if (strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/\d/', $password)) {
        die("Password must be at least 8 characters, with 1 uppercase letter and 1 number.");
    }
    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        die("Invalid or expired token.");
    }

    $user_id = $row['user_id'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$password_hash, $user_id]);

        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);

        // Show success modal HTML + JS
        ?>
        <!DOCTYPE html>
        <html lang="en" class="h-full bg-gray-100">
        <head>
          <meta charset="UTF-8" />
          <title>Password Reset Successful</title>
          <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="h-full flex items-center justify-center">

          <!-- Modal Overlay -->
          <div id="modalOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <!-- Modal content -->
            <div class="bg-white rounded-lg shadow-lg max-w-sm w-full p-6 text-center relative">
              <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-4 h-16 w-16 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              <h2 class="text-2xl font-semibold mb-2 text-green-700">Password Reset Successful!</h2>
              <p class="mb-6 text-gray-700">You can now log in with your new password.</p>
              <a href="login.html" class="inline-block bg-green-700 hover:bg-green-800 text-white font-semibold px-6 py-2 rounded transition">Go to Login</a>
            </div>
          </div>

          <script>
            // Close modal on clicking outside modal content
            const overlay = document.getElementById('modalOverlay');
            overlay.addEventListener('click', function(e) {
              if (e.target === overlay) {
                window.location.href = 'login.html';
              }
            });
          </script>
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
