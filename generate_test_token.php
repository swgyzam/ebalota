<?php
date_default_timezone_set('Asia/Manila');

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
    die("DB Connection Failed: " . $e->getMessage());
}

// Palitan ito ng valid admin user_id sa 'users' table mo
$admin_user_id = 20;

// Gumawa ng 64-character hex token
$token = bin2hex(random_bytes(32));

// Expiration time: 1 hour from now, naka-Asia/Manila timezone
$expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Insert token sa database
$stmt = $pdo->prepare("INSERT INTO admin_login_tokens (user_id, token, expires_at, is_used, created_at) VALUES (?, ?, ?, 0, NOW())");

try {
    $stmt->execute([$admin_user_id, $token, $expires_at]);
    echo "Token generated and inserted successfully!<br>";
    echo "Token: " . $token . "<br>";
    echo "Expires at: " . $expires_at . "<br>";
    echo "Use this URL to test: <br>";
    echo "http://localhost/evoting/admin_verify_token.php?token=" . $token;
} catch (PDOException $e) {
    die("Insert failed: " . $e->getMessage());
}
