<?php
session_start();
date_default_timezone_set('Asia/Manila');


if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['election_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    // Basic validation
    if (empty($title) || empty($start_date) || empty($end_date)) {
        die("Please fill in all required fields: Title, Start Date/Time, and End Date/Time.");
    }

    if (!strtotime($start_datetime) || !strtotime($end_datetime)) {
        die("Invalid date/time format.");
    }

    if (strtotime($end_datetime) <= strtotime($start_datetime)) {
        die("End Date/Time must be after Start Date/Time.");
    }


    $realtime_results = isset($_POST['realtime']) ? 1 : 0;

    // Get selected colleges array and convert to string
    $colleges = $_POST['colleges'] ?? [];
    $allowed_colleges = !empty($colleges) ? implode(',', $colleges) : 'All';

    $positions = $_POST['target_position'] ?? [];
    $target_positions = !empty($positions) ? implode(',', $positions) : 'All';
      
    
    $status_arr = $_POST['allowed_status'] ?? [];
    $allowed_status = !empty($status_arr) ? implode(',', $status_arr) : 'All';
    

    // Allowed courses
    $courses = $_POST['courses'] ?? [];
    $allowed_courses = !empty($courses) ? implode(',', $courses) : 'All';
    
    // Update SQL to include new columns
    $sql = "INSERT INTO elections 
(title, description, start_datetime, end_datetime, status, realtime_results, allowed_colleges, target_position, allowed_courses, allowed_status) 
VALUES 
(:title, :description, :start_datetime, :end_datetime, 'upcoming', :realtime_results, :allowed_colleges, :target_position, :allowed_courses, :allowed_status)";


    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':start_datetime' => $start_datetime,
        ':end_datetime' => $end_datetime,
        ':realtime_results' => $realtime_results,
        ':allowed_colleges' => $allowed_colleges,
        ':target_position' => $target_positions,
        ':allowed_courses' => $allowed_courses,
        ':allowed_status' => $allowed_status,
    ]);

    header('Location: manage_elections.php');
    exit();
} else {
    header('Location: manage_elections.php');
    exit();
}

?>