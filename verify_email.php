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
    die("Database connection failed.");
}

$token = $_GET['token'] ?? '';
if (!$token) {
    header("Location: login.html?error=" . urlencode("No token provided."));
    exit();
}

// Find pending user with valid token and not expired
$stmt = $pdo->prepare("SELECT * FROM pending_users WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: login.html?error=" . urlencode("Token is invalid or has expired."));
    exit();
}

$message = '';
$success = false;

try {
    $pdo->beginTransaction();

    $position       = $user['position'];
    $is_coop_member = $user['is_coop_member'];

    // Insert into users table
    $insertStmt = $pdo->prepare("
        INSERT INTO users 
            (first_name, last_name, email, position, is_coop_member, department, department1, course, status, password, is_verified, student_number, employee_number, force_password_change) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $user['first_name'],
        $user['last_name'],
        $user['email'],
        $position,
        $is_coop_member,
        $user['department'],
        $user['department1'],
        $user['course'],
        $user['status'],
        $user['password'],
        true,
        $user['student_number'],
        $user['employee_number'],
        0 // registration users: no forced password change
    ]);

    // Delete from pending_users table
    $deleteStmt = $pdo->prepare("DELETE FROM pending_users WHERE pending_id = ?");
    $deleteStmt->execute([$user['pending_id']]);

    $pdo->commit();
    $message = "Your email address has been verified. You may now log in to the system using your registered CvSU email and password.";
    $success = true;

} catch (Exception $e) {
    $pdo->rollBack();
    $message = "Failed to verify email: " . htmlspecialchars($e->getMessage());
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Email Verification - eBalota</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

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
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 25%, #bbf7d0 50%, #86efac 75%, #4ade80 100%);
      background-attachment: fixed;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background-image: 
        radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.2) 0%, transparent 25%),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.15) 0%, transparent 20%),
        radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 15%);
      pointer-events: none;
      z-index: -1;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-10 text-slate-900">

  <div class="bg-white/95 rounded-3xl shadow-2xl border border-emerald-900/10 max-w-md w-full px-8 py-10 relative">
    <div class="text-center">
      <?php if ($success): ?>
        <div class="mx-auto bg-emerald-100 w-20 h-20 rounded-full flex items-center justify-center mb-6">
          <i class="fas fa-check text-emerald-600 text-3xl"></i>
        </div>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">
          Email Verified
        </h1>
        <p class="text-gray-600 mb-6 text-sm md:text-base">
          <?= $message ?>
        </p>
        <a
          href="login.html"
          class="inline-flex items-center justify-center w-full bg-cvsu-primary text-white font-semibold py-2.5 rounded-full text-sm tracking-wide shadow-sm hover:bg-cvsu-secondary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cvsu-primary focus:ring-offset-emerald-900 transition"
        >
          <span>Go to Login</span>
          <i class="fas fa-arrow-right ml-2 text-xs"></i>
        </a>
      <?php else: ?>
        <div class="mx-auto bg-red-100 w-20 h-20 rounded-full flex items-center justify-center mb-6">
          <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
        </div>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">
          Verification Error
        </h1>
        <p class="text-gray-600 mb-6 text-sm md:text-base">
          <?= $message ?>
        </p>
        <a
          href="register.html"
          class="inline-flex items-center justify-center w-full bg-red-600 text-white font-semibold py-2.5 rounded-full text-sm tracking-wide shadow-sm hover:bg-red-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-600 focus:ring-offset-red-900 transition"
        >
          <span>Back to Register</span>
          <i class="fas fa-arrow-left ml-2 text-xs"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
