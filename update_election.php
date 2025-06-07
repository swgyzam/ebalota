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


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['election_id'];
    $name = $_POST['election_name'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $status = $_POST['status'];

    $target_position = isset($_POST['target_position']) ? json_encode($_POST['target_position']) : json_encode([]);
    $allowed_colleges = isset($_POST['allowed_colleges']) ? json_encode($_POST['allowed_colleges']) : json_encode([]);
    $allowed_courses = isset($_POST['allowed_courses']) ? json_encode($_POST['allowed_courses']) : json_encode([]);

    $stmt = $pdo->prepare("UPDATE elections SET election_name = ?, start_date = ?, end_date = ?, status = ?, target_position = ?, allowed_colleges = ?, allowed_courses = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $name, $start, $end, $status, $target_position, $allowed_colleges, $allowed_courses, $id);

    if ($stmt->execute()) {
        header("Location: manage_elections.php?success=1");
        exit();
    } else {
        echo "Update failed: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
