<?php
// Start session with secure settings
session_start();

date_default_timezone_set('Asia/Manila');

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
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Database connection failed.");
}

if (!isset($_GET['token'])) {
    header("Location: login.html?error=" . urlencode("Invalid link."));
    exit;
}

 $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
 $stmt = $pdo->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.role, 
           u.is_verified, u.assigned_scope
    FROM admin_login_tokens alt
    JOIN users u ON alt.user_id = u.user_id
    WHERE alt.token = ? AND alt.expires_at > NOW()
");
 $stmt->execute([$token]);
 $user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    header("Location: login.html?error=" . urlencode("Token is invalid or has expired."));
    exit;
}

// Delete the token
 $stmt = $pdo->prepare("DELETE FROM admin_login_tokens WHERE token = ?");
 $stmt->execute([$token]);

// Set all required session variables
 $_SESSION['user_id'] = $user['user_id'];
 $_SESSION['first_name'] = $user['first_name'];
 $_SESSION['last_name'] = $user['last_name'];
 $_SESSION['email'] = $user['email'];
 $_SESSION['role'] = 'admin';
 $_SESSION['is_verified'] = (bool)$user['is_verified']; // Ensure boolean value
 $_SESSION['CREATED'] = time();
 $_SESSION['LAST_ACTIVITY'] = time();

// ✅ Important: set assigned_scope for admins
 $_SESSION['assigned_scope'] = $user['assigned_scope'] ?? '';

// If user isn't verified in DB, update them
if (!$user['is_verified']) {
    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?")
       ->execute([$user['user_id']]);
    $_SESSION['is_verified'] = true;
}

// Debug output (remove after testing)
 $debugInfo = "Role: " . $_SESSION['role'] . ", Scope: " . $_SESSION['assigned_scope'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Admin Verified</title>
<style>
  body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f5f5;
  }

  .modal {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
  }

  .modal-content {
    background-color: #fff;
    padding: 40px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    animation: popUp 0.3s ease;
  }

  @keyframes popUp {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }

  .check-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background-color: #0a5f2d;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .check-icon svg {
    width: 40px;
    height: 40px;
    fill: white;
  }

  h2 {
    color: #0a5f2d;
    margin-bottom: 10px;
  }

  p {
    font-size: 16px;
    color: #333;
  }

  .debug-info {
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    margin: 15px 0;
    font-family: monospace;
    font-size: 14px;
    text-align: left;
    color: #666;
  }

  button {
    background-color: #0a5f2d;
    border: none;
    color: white;
    padding: 12px 28px;
    margin-top: 30px;
    cursor: pointer;
    font-weight: bold;
    border-radius: 6px;
    font-size: 16px;
  }

  button:hover {
    background-color: #08491c;
  }

  .countdown {
    margin-top: 15px;
    font-size: 14px;
    color: #666;
  }
</style>
</head>
<body>

<div class="modal">
  <div class="modal-content">
    <div class="check-icon">
      <svg viewBox="0 0 24 24">
        <path d="M9 16.2l-4.2-4.2-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
      </svg>
    </div>
    <h2>Welcome Admin!</h2>
    <p>Your email has been successfully verified. You will be redirected to the admin dashboard.</p>
    
    <!-- Debug info (remove after testing) -->
    <div class="debug-info">
      Debug: <?php echo htmlspecialchars($debugInfo); ?>
    </div>
    
    <button onclick="window.location.href='admin_dashboard_redirect.php'">Go to Dashboard</button>
    
    <div class="countdown" id="countdown">
      Redirecting in <span id="timer">5</span> seconds...
    </div>
  </div>
</div>

<script>
  // Countdown timer
  let seconds = 5;
  const timerElement = document.getElementById('timer');
  
  const countdown = setInterval(() => {
    seconds--;
    timerElement.textContent = seconds;
    
    if (seconds <= 0) {
      clearInterval(countdown);
      window.location.href = 'admin_dashboard_redirect.php';
    }
  }, 1000);
</script>

</body>
</html>