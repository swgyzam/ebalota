<?php
session_start();
$logoutUserId = $_SESSION['user_id'] ?? null;

// Connect to database (PDO)
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
    error_log("DB connection failed during logout: " . $e->getMessage());
}

// Activity logger
function logActivity(PDO $pdo, int $userId, string $action): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':action' => $action
        ]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

// Log logout action (ONLY if user was logged in)
if (!empty($logoutUserId)) {
    logActivity($pdo, (int)$logoutUserId, "User logged out");
}

// Destroy all session data
$_SESSION = [];
session_unset();
session_destroy();

// Remove Remember Me cookie if set
if (isset($_COOKIE['rememberme_token'])) {
    // Expire the cookie
    setcookie('rememberme_token', '', time() - 3600, '/');

    // Optional: remove the token from the database
    // Assuming you have a function or DB connection to remove it
    include 'db.php';
    
    $token = $_COOKIE['rememberme_token'];
    $stmt = $conn->prepare("UPDATE users SET rememberme_token = NULL WHERE rememberme_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to login page
header("Location: login.html");
exit;
?>
