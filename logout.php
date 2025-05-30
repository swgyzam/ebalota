<?php
session_start();

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
