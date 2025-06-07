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
    die("Database connection failed.");
}

$token = $_GET['token'] ?? '';

if (!$token) {
    die("No token provided.");
}

// Find pending user with valid token and not expired
$stmt = $pdo->prepare("SELECT * FROM pending_users WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: login.html?error=" . urlencode("Token is invalid or has expired."));
    exit;
}

$message = '';
$success = false;

try {
    $pdo->beginTransaction();

    // Parse position and COOP membership safely
    $original_position = $user['position'];  // e.g., "non-academic,coop"
    $position_parts = explode(',', $original_position);

    $position = '';
    $is_coop_member = 0;

    foreach ($position_parts as $part) {
        $part = trim(strtolower($part));
        if (in_array($part, ['student', 'academic', 'non-academic',])) {
            $position = $part;
        } elseif ($part === 'coop') {
            $is_coop_member = 1;
        }
    }

    // Insert into users table (confirmed users)
    $insertStmt = $pdo->prepare("INSERT INTO users 
        (first_name, last_name, email, position, is_coop_member, department, course, status, password, is_verified) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $insertStmt->execute([
        $user['first_name'],
        $user['last_name'],
        $user['email'],
        $position,
        $is_coop_member,
        $user['department'],
        $user['course'],
        $user['status'],
        $user['password'],
        true
    ]);

    // Delete from pending_users table
    $deleteStmt = $pdo->prepare("DELETE FROM pending_users WHERE pending_id = ?");
    $deleteStmt->execute([$user['pending_id']]);

    $pdo->commit();

    $message = "Email verified successfully! You can now log in to your account.";
    $success = true;
} catch (Exception $e) {
    $pdo->rollBack();
    $message = "Failed to verify email: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Email Verification</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
  <div class="bg-white shadow-lg rounded-lg max-w-md w-full p-8 text-center">
    <?php if ($success): ?>
      <div class="text-green-600 text-9xl mb-6">&#10004;</div>
      <h2 class="text-3xl font-bold mb-4 text-green-700">Success!</h2>
      <p class="text-gray-700 mb-6"><?= $message ?></p>
      <a href="login.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
        Go to Login
      </a>
    <?php else: ?>
      <div class="text-red-600 text-9xl mb-6">&#10060;</div>
      <h2 class="text-3xl font-bold mb-4 text-red-700">Error</h2>
      <p class="text-gray-700 mb-6"><?= $message ?></p>
      <a href="register.html" class="inline-block bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
        Back to Register
      </a>
    <?php endif; ?>
  </div>
</body>
</html>
