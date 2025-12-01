<?php
session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

// === DB CONNECTION ===
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("DB connection failed in create_election.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// === BASIC REQUIRED FIELDS ===
if (
    empty($_POST['election_name']) ||
    empty($_POST['start_datetime']) ||
    empty($_POST['end_datetime']) ||
    empty($_POST['target_voter'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields.']);
    exit;
}

$title             = trim($_POST['election_name'] ?? '');
$description       = trim($_POST['description']    ?? '');
$start_datetime    = $_POST['start_datetime']      ?? '';
$end_datetime      = $_POST['end_datetime']        ?? '';
$target_voter      = $_POST['target_voter']        ?? '';
$assigned_admin_id = $_POST['assigned_admin_id']   ?? null;

// Validate admin
if (!$assigned_admin_id) {
    echo json_encode(['status' => 'error', 'message' => 'Please assign an election admin.']);
    exit;
}
$assigned_admin_id = (int)$assigned_admin_id;

// Extra sanity checks
if ($title === '' || $start_datetime === '' || $end_datetime === '' || $target_voter === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields.']);
    exit;
}

// === DATE VALIDATION (SERVER-SIDE) ===
$startTs = strtotime($start_datetime);
$endTs   = strtotime($end_datetime);
$nowTs   = time();

if ($startTs === false || $endTs === false) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date/time format.']);
    exit;
}

if ($startTs < $nowTs) {
    echo json_encode(['status' => 'error', 'message' => 'Start date/time must be today or in the future.']);
    exit;
}

if ($endTs <= $startTs) {
    echo json_encode(['status' => 'error', 'message' => 'End date must be after start date.']);
    exit;
}

// === FILE UPLOAD (LOGO) ===
$logoPath = null;
if (isset($_FILES['election_logo']) && $_FILES['election_logo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/uploads/logos/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create logo directory.']);
            exit;
        }
    }

    $fileName       = time() . '_' . basename($_FILES['election_logo']['name']);
    $targetFilePath = $uploadDir . $fileName;
    $fileType       = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowedTypes   = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileType, $allowedTypes, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Only JPG and PNG files are allowed.']);
        exit;
    }

    if ($_FILES['election_logo']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Logo must be less than 2MB.']);
        exit;
    }

    if (!move_uploaded_file($_FILES['election_logo']['tmp_name'], $targetFilePath)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload logo file.']);
        exit;
    }

    // Store relative path used by front-end
    $logoPath = 'uploads/logos/' . $fileName;
}

// === DEFAULT VALUES (match elections schema) ===
$allowed_colleges     = 'All';
$allowed_courses      = '';      // empty string = all courses for students
$allowed_status       = null;    // null / 'All'
$allowed_departments  = null;
$target_position      = 'All';   // enum: 'student','faculty','non-academic','coop','others','All'
$target_department    = 'All';
$realtime_results     = isset($_POST['realtime_results']) ? 1 : 0;

// === NEW: Resolve admin scope → election_scope_type + owner_scope_id ===
$election_scope_type = null;
$owner_scope_id      = null;

try {
    // 1) Get admin scope_category from users
    $adminStmt = $pdo->prepare("SELECT scope_category FROM users WHERE user_id = :uid");
    $adminStmt->execute([':uid' => $assigned_admin_id]);
    $adminRow = $adminStmt->fetch();

    if ($adminRow) {
        // e.g. 'Academic-Faculty', 'Others', etc.
        $election_scope_type = $adminRow['scope_category'];

        // 2) Get admin_scope row for this admin + category
        $scopeStmt = $pdo->prepare("
            SELECT scope_id, scope_type, scope_details
            FROM admin_scopes
            WHERE user_id = :uid
              AND scope_type = :stype
            LIMIT 1
        ");
        $scopeStmt->execute([
            ':uid'   => $assigned_admin_id,
            ':stype' => $election_scope_type
        ]);
        $scopeRow = $scopeStmt->fetch();
        if ($scopeRow) {
            $owner_scope_id = (int)$scopeRow['scope_id'];
        }
    }
} catch (PDOException $e) {
    error_log("Error resolving admin scope in create_election.php: " . $e->getMessage());
    // Do not hard-fail; election can still be created but without scope linking
}

// === MAP target_voter + form fields → DB columns ===
switch ($target_voter) {

    // 1) STUDENTS
    case 'student':
        $allowed_colleges = $_POST['allowed_colleges_student'] ?? 'all';
        if (strtolower($allowed_colleges) === 'all' || $allowed_colleges === '') {
            $allowed_colleges = 'All';
            $allowed_courses  = ''; // All courses
        } else {
            $rawCourses = $_POST['allowed_courses_student'] ?? [];
            if (!empty($rawCourses) && is_array($rawCourses)) {
                $clean = array_map('trim', $rawCourses);
                $clean = array_filter($clean, fn($c) => $c !== '');
                $allowed_courses = $clean ? implode(',', $clean) : '';
            } else {
                $allowed_courses = '';
            }
        }

        $target_position   = 'student';
        $target_department = 'Students';
        break;

    // 2) ACADEMIC (FACULTY)
    case 'academic':
        $allowed_colleges = $_POST['allowed_colleges_academic'] ?? 'all';
        if (strtolower($allowed_colleges) === 'all' || $allowed_colleges === '') {
            $allowed_colleges = 'All';
        }

        // Faculty: no course filter
        $allowed_courses = null;

        // Departments (from UI)
        $deptArr = $_POST['allowed_departments_faculty'] ?? [];
        if (!empty($deptArr) && is_array($deptArr)) {
            $clean = array_map('trim', $deptArr);
            $clean = array_filter($clean, fn($d) => $d !== '');
            $allowed_departments = $clean ? implode(',', $clean) : 'All';
        } else {
            $allowed_departments = 'All';
        }

        // Statuses (from UI)
        $rawStatus = $_POST['allowed_status_academic'] ?? [];
        if (!empty($rawStatus) && is_array($rawStatus)) {
            $clean = array_map('trim', $rawStatus);
            $clean = array_filter($clean, fn($s) => $s !== '');
            $allowed_status = $clean ? implode(',', $clean) : null;
        } else {
            $allowed_status = null; // all faculty statuses
        }

        $target_position   = 'faculty';
        $target_department = 'Faculty';
        break;

    // 3) NON-ACADEMIC EMPLOYEES
    case 'non_academic':
        $dept = $_POST['allowed_departments_nonacad'] ?? 'all';
        if (strtolower($dept) === 'all' || $dept === '') {
            $allowed_departments = 'All';
        } else {
            $allowed_departments = $dept; // single dept code
        }

        $rawStatus = $_POST['allowed_status_nonacad'] ?? [];
        if (!empty($rawStatus) && is_array($rawStatus)) {
            $clean = array_map('trim', $rawStatus);
            $clean = array_filter($clean, fn($s) => $s !== '');
            $allowed_status = $clean ? implode(',', $clean) : 'All';
        } else {
            $allowed_status = 'All';
        }

        $allowed_colleges   = 'All';
        $allowed_courses    = '';
        $target_position    = 'non-academic';
        $target_department  = 'Non-Academic';
        break;

    // 4) OTHERS (generic, no special filters – voters fully controlled by uploaded list)
    case 'others':
        // No restrictions stored; everything default / "All"
        $allowed_colleges    = 'All';
        $allowed_courses     = '';
        $allowed_status      = null;     // means no explicit status filter
        $allowed_departments = 'All';

        $target_position     = 'others';
        $target_department   = 'Others';

        // election_scope_type should already be 'Others' if admin is an Others admin;
        // do NOT invent Others-COOP / Others-Default anymore.
        if ($election_scope_type === null) {
            $election_scope_type = 'Others';
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid target voter.']);
        exit;
}

// === INSERT INTO elections ===
//
// Columns per schema:
//   title, description, start_datetime, end_datetime,
//   status, creation_stage, created_by, assigned_admin_id,
//   target_department, target_position,
//   election_scope_type, owner_scope_id,
//   realtime_results, allowed_colleges, allowed_courses,
//   allowed_status, allowed_departments, logo_path
//
$sql = "INSERT INTO elections (
            title,
            description,
            start_datetime,
            end_datetime,
            status,
            creation_stage,
            created_by,
            assigned_admin_id,
            target_department,
            target_position,
            election_scope_type,
            owner_scope_id,
            realtime_results,
            allowed_colleges,
            allowed_courses,
            allowed_status,
            allowed_departments,
            logo_path
        ) VALUES (
            :title,
            :description,
            :start_datetime,
            :end_datetime,
            :status,
            :creation_stage,
            :created_by,
            :assigned_admin_id,
            :target_department,
            :target_position,
            :election_scope_type,
            :owner_scope_id,
            :realtime_results,
            :allowed_colleges,
            :allowed_courses,
            :allowed_status,
            :allowed_departments,
            :logo_path
        )";

$stmt = $pdo->prepare($sql);

try {
    $stmt->execute([
        ':title'               => $title,
        ':description'         => $description,
        ':start_datetime'      => $start_datetime,
        ':end_datetime'        => $end_datetime,
        ':status'              => 'upcoming',
        ':creation_stage'      => 'pending_admin',
        ':created_by'          => $_SESSION['user_id'] ?? null,
        ':assigned_admin_id'   => $assigned_admin_id,
        ':target_department'   => $target_department,
        ':target_position'     => $target_position,
        ':election_scope_type' => $election_scope_type,
        ':owner_scope_id'      => $owner_scope_id,
        ':realtime_results'    => $realtime_results,
        ':allowed_colleges'    => $allowed_colleges,
        ':allowed_courses'     => $allowed_courses,
        ':allowed_status'      => $allowed_status,
        ':allowed_departments' => $allowed_departments,
        ':logo_path'           => $logoPath
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Election created successfully.']);
} catch (PDOException $e) {
    error_log("Error creating election: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error creating election. Please try again.']);
}
