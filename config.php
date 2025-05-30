<?php
// config.php
$host = 'localhost';
$dbname = 'evoting_system';  // pangalan ng database mo
$username = 'root';       // user ng MySQL mo
$password = '';           // password ng MySQL mo

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>



