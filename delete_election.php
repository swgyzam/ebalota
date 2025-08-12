<?php
session_start();
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
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check user session and admin status
//if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    //header('Location: login.html');
    //exit();
//}

// Check if election ID is sent via POST
if (!isset($_POST['election_id']) || empty($_POST['election_id'])) {
    $_SESSION['error_message'] = "Invalid request: missing election ID.";
    header('Location: manage_elections.php');
    exit;
}

$election_id = $_POST['election_id'];

if (!is_numeric($election_id)) {
    $_SESSION['error_message'] = "Invalid election ID.";
    header('Location: manage_elections.php');
    exit;
}

// Prepare and execute DELETE using PDO
$sql = "DELETE FROM elections WHERE election_id = :election_id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':election_id', (int)$election_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $_SESSION['success_message'] = "Election deleted successfully.";
} else {
    $_SESSION['error_message'] = "No election found with ID: " . htmlspecialchars($election_id);
}

// Redirect back to manage elections page
header('Location: manage_elections.php');
exit;
?>
