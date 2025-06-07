<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.html');
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

// Check required fields
if (
    empty($_POST['election_name']) || 
    empty($_POST['start_date']) || 
    empty($_POST['end_date']) || 
    empty($_POST['target_voter'])
) {
    die("Please fill all required fields.");
}

$title = trim($_POST['election_name']);
$description = trim($_POST['description'] ?? '');
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];

// Convert to datetime format with times (set start = 00:00:00, end = 23:59:59)
$start_datetime = date('Y-m-d H:i:s', strtotime($start_date . ' 00:00:00'));
$end_datetime = date('Y-m-d H:i:s', strtotime($end_date . ' 23:59:59'));

$target_voter = $_POST['target_voter'];

// Default values for allowed filters
$allowed_colleges = 'All';
$allowed_courses = 'All';
$allowed_status = 'All';
$target_position = 'All';

switch ($target_voter) {
    case 'student':
        $allowed_colleges = $_POST['allowed_colleges_student'] ?? 'all';
        if (strtolower($allowed_colleges) === 'all') {
            $allowed_colleges = 'All';
            $allowed_courses = 'All';
        } else {
            if (!empty($_POST['allowed_courses_student'])) {
                $allowed_courses_arr = array_map('trim', $_POST['allowed_courses_student']);
                $allowed_courses = implode(',', $allowed_courses_arr);
            } else {
                $allowed_courses = 'All';
            }
        }
        $target_position = 'student';
        $allowed_status = 'All'; // not applicable
        break;

    case 'academic':
        $allowed_colleges = $_POST['allowed_colleges_academic'] ?? 'all';
        if (strtolower($allowed_colleges) === 'all') {
            $allowed_colleges = 'All';
            $allowed_courses = 'All';
        } else {
            if (!empty($_POST['allowed_courses_academic'])) {
                $allowed_courses_arr = array_map('trim', $_POST['allowed_courses_academic']);
                $allowed_courses = implode(',', $allowed_courses_arr);
            } else {
                $allowed_courses = 'All';
            }
        }
        if (!empty($_POST['allowed_status_academic'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_academic']);
            $allowed_status = implode(',', $allowed_status_arr);
        } else {
            $allowed_status = 'All';
        }
        $target_position = 'faculty';
        break;

    case 'non_academic':
        // Use separate variable for departments, but save to allowed_colleges to reuse existing column
        $departments = $_POST['allowed_departments_nonacad'] ?? 'all';
        $allowed_colleges = (strtolower($departments) === 'all') ? 'All' : $departments;

        if (!empty($_POST['allowed_status_nonacad'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_nonacad']);
            $allowed_status = implode(',', $allowed_status_arr);
        } else {
            $allowed_status = 'All';
        }

        $allowed_courses = 'All'; // not applicable
        $target_position = 'non-academic';
        break;

        case 'coop':
            // COOP doesn't need colleges or courses
            $allowed_colleges = 'All';
            $allowed_courses = 'All';
        
            // Filter allowed_status_coop to only include 'MIGS'
            if (!empty($_POST['allowed_status_coop'])) {
                $allowed_status_arr = array_map('trim', $_POST['allowed_status_coop']);
                // Keep only 'MIGS' status (case-insensitive)
                $allowed_status_arr = array_filter($allowed_status_arr, function($status) {
                    return strtoupper($status) === 'MIGS';
                });
        
                if (count($allowed_status_arr) === 0) {
                    // If none selected, default to 'MIGS'
                    $allowed_status = 'MIGS';
                } else {
                    $allowed_status = implode(',', $allowed_status_arr);
                }
            } else {
                // Default to 'MIGS' if nothing selected
                $allowed_status = 'MIGS';
            }
        
            $target_position = 'coop';
            break;
    default:
        die("Invalid target voter.");
}


// For real-time results, assume it's off by default
$realtime_results = 0;
if (isset($_POST['realtime_results']) && $_POST['realtime_results'] === 'on') {
    $realtime_results = 1;
}


// Insert into elections table
$sql = "INSERT INTO elections 
(title, description, start_datetime, end_datetime, 
 allowed_colleges, allowed_courses, allowed_status, 
 target_position, realtime_results, status)
VALUES 
(:title, :description, :start_datetime, :end_datetime, 
 :allowed_colleges, :allowed_courses, :allowed_status, 
 :target_position, :realtime_results, :status)";
;

$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':start_datetime' => $start_datetime,
        ':end_datetime' => $end_datetime,
        ':allowed_colleges' => $allowed_colleges,
        ':allowed_courses' => $allowed_courses,
        ':allowed_status' => $allowed_status,
        ':target_position' => $target_position,
        ':realtime_results' => $realtime_results,
        ':status' => 'upcoming'
    ]);
    
    // Redirect back with success message
    header("Location: manage_elections.php?msg=Election created successfully");
    exit();
} catch (PDOException $e) {
    die("Error creating election: " . $e->getMessage());
}
