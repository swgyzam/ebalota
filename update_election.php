<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check admin
//if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    //header('Location: login.html');
    //exit();
//}

// DB connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=evoting_system;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Validate required fields
$required = ['election_id', 'election_name', 'start_datetime', 'end_datetime', 'target_voter'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $_SESSION['error'] = "Missing required field: " . str_replace('_', ' ', $field);
        header('Location: manage_elections.php');
        exit();
    }
}

// Process data with proper null handling
$election_id = (int)$_POST['election_id'];
$title = trim($_POST['election_name']);
$description = trim($_POST['description'] ?? '');
$start_datetime = date('Y-m-d H:i:s', strtotime($_POST['start_datetime']));
$end_datetime = date('Y-m-d H:i:s', strtotime($_POST['end_datetime']));
$target_voter = $_POST['target_voter'];
$realtime_results = isset($_POST['realtime_results']) ? 1 : 0;

// Initialize fields according to schema defaults
$allowed_colleges = 'All';
$allowed_courses = '';
$allowed_status = null;
$allowed_departments = null;
$target_position = 'All';
$target_department = 'All';
$assigned_admin_id = !empty($_POST['assigned_admin_id']) ? (int)$_POST['assigned_admin_id'] : null;

// Process voter type
switch ($target_voter) {
    case 'student':
        $allowed_colleges = $_POST['allowed_colleges'] ?? 'All';
        $allowed_courses = !empty($_POST['allowed_courses_student']) 
            ? implode(',', array_map('trim', $_POST['allowed_courses_student']))
            : '';
        $target_position = 'student';
        $target_department = 'Students';
        break;

    case 'faculty':
        $allowed_colleges = $_POST['allowed_colleges_faculty'] ?? 'All';
        
        // Always NULL for faculty
        $allowed_courses = null;
        
        $allowed_status = !empty($_POST['allowed_status_faculty']) 
            ? implode(',', array_map('trim', $_POST['allowed_status_faculty']))
            : null;
        $target_position = 'faculty';
        $target_department = 'Faculty';
        break;

    case 'non_academic':
        $departments = $_POST['allowed_departments_nonacad'] ?? 'all';
        $allowed_departments = (strtolower($departments) === 'all') ? 'All' : $departments;

        if (!empty($_POST['allowed_status_nonacad'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_nonacad']);
            $allowed_status = implode(',', $allowed_status_arr);
        } else {
            $allowed_status = 'All';
        }

        $allowed_courses = 'All'; // not applicable
        $allowed_colleges = 'All'; // set this explicitly
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
        $_SESSION['error'] = 'Invalid voter type selected';
        header('Location: manage_elections.php');
        exit();
}

// Date validation
if (strtotime($start_datetime) >= strtotime($end_datetime)) {
    $_SESSION['error'] = 'End date must be after start date';
    header('Location: manage_elections.php');
    exit();
}

// ===== File Upload Handling for Logo Update =====
$logoPath = null;

if (isset($_FILES['update_logo']) && $_FILES['update_logo']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/logos/";

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES["update_logo"]["name"]);
    $targetFilePath = $targetDir . $fileName;

    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileType, $allowedTypes)) {
        $_SESSION['error'] = 'Only JPG and PNG files are allowed for logo.';
        header('Location: manage_elections.php');
        exit();
    }

    if ($_FILES['update_logo']['size'] > 2 * 1024 * 1024) {
        $_SESSION['error'] = 'Logo must be less than 2MB.';
        header('Location: manage_elections.php');
        exit();
    }

    if (move_uploaded_file($_FILES["update_logo"]["tmp_name"], $targetFilePath)) {
        $logoPath = $targetFilePath;
    } else {
        $_SESSION['error'] = 'Failed to upload the logo image.';
        header('Location: manage_elections.php');
        exit();
    }
}

// Fetch current logo path if no new upload
if ($logoPath === null) {
    $stmtLogo = $pdo->prepare("SELECT logo_path FROM elections WHERE election_id = :id");
    $stmtLogo->execute([':id' => $election_id]);
    $currentLogo = $stmtLogo->fetchColumn();
    $logoPath = $currentLogo ?: null;
}

// Update query matching exact schema
try {
    $sql = "UPDATE elections SET
    title = :title,
    description = :description,
    start_datetime = :start_datetime,
    end_datetime = :end_datetime,
    target_position = :target_position,
    target_department = :target_department,
    allowed_colleges = :allowed_colleges,
    allowed_courses = :allowed_courses,
    allowed_status = :allowed_status,
    allowed_departments = :allowed_departments,
    realtime_results = :realtime_results,
    logo_path = :logo_path,
    assigned_admin_id = :assigned_admin_id
    WHERE election_id = :election_id";

    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':title' => $title,
        ':description' => $description,
        ':start_datetime' => $start_datetime,
        ':end_datetime' => $end_datetime,
        ':target_position' => $target_position,
        ':target_department' => $target_department,
        ':allowed_colleges' => $allowed_colleges,
        ':allowed_courses' => $allowed_courses,
        ':allowed_status' => $allowed_status,
        ':allowed_departments' => $allowed_departments,
        ':realtime_results' => $realtime_results,
        ':logo_path' => $logoPath,
        ':election_id' => $election_id,
        ':assigned_admin_id' => $assigned_admin_id
    ];

    file_put_contents('update_debug.log', "Executing with params:\n".print_r($params, true)."\n", FILE_APPEND);
    
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = 'Election updated successfully!';
        file_put_contents('update_debug.log', "Update successful for election $election_id\n", FILE_APPEND);
    } else {
        // Verify if data was actually changed
        $check = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
        $check->execute([$election_id]);
        $current = $check->fetch();
        
        $changes = array_diff_assoc($params, [
            ':title' => $current['title'],
            ':description' => $current['description'],
            ':start_datetime' => $current['start_datetime'],
            ':end_datetime' => $current['end_datetime'],
            ':target_position' => $current['target_position'],
            ':target_department' => $current['target_department'],
            ':allowed_colleges' => $current['allowed_colleges'],
            ':allowed_courses' => $current['allowed_courses'],
            ':allowed_status' => $current['allowed_status'],
            ':allowed_departments' => $current['allowed_departments'],
            ':realtime_results' => $current['realtime_results'],
            ':logo_path' => $current['logo_path']
        ]);
        
        if (empty($changes)) {
            $_SESSION['info'] = 'No changes were made (data identical)';
        } else {
            $_SESSION['error'] = 'Update failed - no rows affected';
            file_put_contents('update_debug.log', 
                "Update failed but changes detected:\n".print_r($changes, true)."\n", 
                FILE_APPEND);
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    file_put_contents('update_debug.log', "Error: ".$e->getMessage()."\n", FILE_APPEND);
}

header('Location: manage_elections.php');
exit();
