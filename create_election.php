<?php
session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json'); // So JS receives JSON response

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
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
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// ===== Validation =====
if (
    empty($_POST['election_name']) ||
    empty($_POST['start_datetime']) ||
    empty($_POST['end_datetime']) ||
    empty($_POST['target_voter'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields.']);
    exit();
}

$title = trim($_POST['election_name']);
$description = trim($_POST['description'] ?? '');
$election_name   = $_POST['election_name'] ?? '';
$start_datetime  = $_POST['start_datetime'] ?? '';
$end_datetime    = $_POST['end_datetime'] ?? '';
$target_voter    = $_POST['target_voter'] ?? '';

if (empty($election_name) || empty($start_datetime) || empty($end_datetime) || empty($target_voter)) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields.']);
    exit;
}

$now = date('Y-m-d\TH:i');
if ($start_datetime < $now) {
    echo json_encode(['status' => 'error', 'message' => 'Start date/time must be today or in the future.']);
    exit;
}

if (strtotime($end_datetime) <= strtotime($start_datetime)) {
    echo json_encode(['status' => 'error', 'message' => 'End date must be after start date.']);
    exit;
}

// ==== File Upload Handling for Logo ====
$logoPath = null;
if (isset($_FILES['election_logo']) && $_FILES['election_logo']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/logos/";

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES["election_logo"]["name"]);
    $targetFilePath = $targetDir . $fileName;

    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Only JPG and PNG files are allowed.']);
        exit();
    }

    if ($_FILES['election_logo']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Logo must be less than 2MB.']);
        exit();
    }

    if (move_uploaded_file($_FILES["election_logo"]["tmp_name"], $targetFilePath)) {
        $logoPath = $targetFilePath;
    }
}
// ==== End File Upload ====

// Defaults
$allowed_colleges = 'All';
$allowed_courses = 'All';
$allowed_status = 'All';
$target_position = 'All';

// Target voter logic
switch ($target_voter) {
    case 'student':
        $allowed_colleges = $_POST['allowed_colleges_student'] ?? 'All';
        if (strtolower($allowed_colleges) === 'all') {
            $allowed_colleges = 'All';
            $allowed_courses = 'All';
        } else {
            if (!empty($_POST['allowed_courses_student'])) {
                $allowed_courses_arr = array_map('trim', $_POST['allowed_courses_student']);
                $allowed_courses = implode(',', $allowed_courses_arr);
            }
        }
        $target_position = 'student';
        break;

    case 'academic':
        $allowed_colleges = $_POST['allowed_colleges_academic'] ?? 'All';
        if (!empty($_POST['allowed_courses_academic'])) {
            $allowed_courses_arr = array_map('trim', $_POST['allowed_courses_academic']);
            $allowed_courses = implode(',', $allowed_courses_arr);
        }
        if (!empty($_POST['allowed_status_academic'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_academic']);
            $allowed_status = implode(',', $allowed_status_arr);
        }
        $target_position = 'faculty';
        break;

    case 'non_academic':
        $departments = $_POST['allowed_departments_nonacad'] ?? 'All';
        $allowed_colleges = $departments;
        if (!empty($_POST['allowed_status_nonacad'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_nonacad']);
            $allowed_status = implode(',', $allowed_status_arr);
        }
        $target_position = 'non-academic';
        break;

    case 'coop':
        if (!empty($_POST['allowed_status_coop'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_coop']);
            $allowed_status_arr = array_filter($allowed_status_arr, fn($s) => strtoupper($s) === 'MIGS');
            $allowed_status = count($allowed_status_arr) === 0 ? 'MIGS' : implode(',', $allowed_status_arr);
        } else {
            $allowed_status = 'MIGS';
        }
        $target_position = 'coop';
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid target voter.']);
        exit();
}

$realtime_results = isset($_POST['realtime_results']) ? 1 : 0;

// Insert
$sql = "INSERT INTO elections 
(title, description, start_datetime, end_datetime, 
 allowed_colleges, allowed_courses, allowed_status, 
 target_position, realtime_results, status, creation_stage, logo_path)
VALUES 
(:title, :description, :start_datetime, :end_datetime, 
 :allowed_colleges, :allowed_courses, :allowed_status, 
 :target_position, :realtime_results, :status, :creation_stage, :logo_path)";

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
        ':status' => 'upcoming',
        ':creation_stage' => 'pending_admin',
        ':logo_path' => $logoPath
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Election created successfully.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error creating election: ' . $e->getMessage()]);
}
