<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- Validate election id ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['toast_message'] = "Invalid election ID.";
    $_SESSION['toast_type'] = "error";
    header("Location: admin_view_elections.php");
    exit();
}

$electionId = intval($_GET['id']);
$userId = $_SESSION['user_id'];

// --- Fetch election ---
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = :id");
$stmt->execute([':id' => $electionId]);
$election = $stmt->fetch();

if (!$election) {
    $_SESSION['toast_message'] = "Election not found.";
    $_SESSION['toast_type'] = "error";
    header("Location: admin_view_elections.php");
    exit();
}

// --- Check if already launched ---
if ($election['creation_stage'] === 'ready_for_voters') {
    $_SESSION['toast_message'] = "Election is already launched.";
    $_SESSION['toast_type'] = "error";
    header("Location: admin_view_elections.php");
    exit();
}

// --- Launch election ---
try {
    $update = $pdo->prepare("UPDATE elections 
                             SET creation_stage = 'ready_for_voters', status = 'upcoming' 
                             WHERE election_id = :id");
    $update->execute([':id' => $electionId]);

    $_SESSION['toast_message'] = "Election \"{$election['title']}\" successfully launched to voters!";
    $_SESSION['toast_type'] = "success";
} catch (Exception $e) {
    $_SESSION['toast_message'] = "Failed to launch election: " . $e->getMessage();
    $_SESSION['toast_type'] = "error";
}

header("Location: admin_view_elections.php");
exit();
