<?php
// get_election.php
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
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.html');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: manage_elections.php');
    exit();
}

$election_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if (!$election) {
    die("Election not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Election</title>
</head>
<body>

<h2>Update Election #<?= htmlspecialchars($election['election_id']) ?></h2>

<form action="update_election.php" method="POST">
    <input type="hidden" name="election_id" value="<?= htmlspecialchars($election['election_id']) ?>">
    
    <label>Title:</label><br>
    <input type="text" name="title" value="<?= htmlspecialchars($election['title']) ?>" required><br><br>
    
    <label>Description:</label><br>
    <textarea name="description" required><?= htmlspecialchars($election['description']) ?></textarea><br><br>
    
    <label>Start Date and Time:</label><br>
    <input type="datetime-local" name="start_datetime" 
           value="<?= date('Y-m-d\TH:i', strtotime($election['start_datetime'])) ?>" required><br><br>
           
    <label>End Date and Time:</label><br>
    <input type="datetime-local" name="end_datetime" 
           value="<?= date('Y-m-d\TH:i', strtotime($election['end_datetime'])) ?>" required><br><br>
    
    <button type="submit">Update Election</button>
</form>

<br>
<a href="manage_elections.php">Back to Manage Elections</a>

</body>
</html>
