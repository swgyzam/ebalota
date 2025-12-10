<?php
session_start();
date_default_timezone_set('Asia/Manila');

// DB connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=evoting_system;charset=utf8mb4",
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
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

$election_id    = (int)$_POST['election_id'];
$title          = trim($_POST['election_name']);
$description    = trim($_POST['description'] ?? '');
$start_datetime = date('Y-m-d H:i:s', strtotime($_POST['start_datetime']));
$end_datetime   = date('Y-m-d H:i:s', strtotime($_POST['end_datetime']));
$target_voter   = $_POST['target_voter'];
$realtime_results = isset($_POST['realtime_results']) ? 1 : 0;

$allowed_colleges    = 'All';
$allowed_courses     = '';
$allowed_status      = null;
$allowed_departments = null;
$target_position     = 'All';
$target_department   = 'All';
$assigned_admin_id   = !empty($_POST['assigned_admin_id']) ? (int)$_POST['assigned_admin_id'] : null;

$election_scope_type = null;
$owner_scope_id      = null;

try {
    if ($assigned_admin_id) {
        $adminStmt = $pdo->prepare("SELECT scope_category FROM users WHERE user_id = :uid");
        $adminStmt->execute([':uid' => $assigned_admin_id]);
        $adminRow = $adminStmt->fetch();

        if (!$adminRow) {
            $_SESSION['error'] = 'Selected admin not found.';
            header('Location: manage_elections.php');
            exit();
        }

        $adminScope = $adminRow['scope_category'] ?? '';

        // Allowed scopes per target voter (note: here target_voter uses 'faculty')
        $allowedScopesByTarget = [
            'student'      => ['Academic-Student', 'Special-Scope'],
            'faculty'      => ['Academic-Faculty'],
            'non_academic' => ['Non-Academic-Employee'],
            'others'       => ['Others'],
        ];

        if (isset($allowedScopesByTarget[$target_voter]) &&
            !in_array($adminScope, $allowedScopesByTarget[$target_voter], true)) {

            $_SESSION['error'] =
                "Selected admin has scope '{$adminScope}', which does not match this election target. ".
                "Please choose an admin with the correct scope.";
            header('Location: manage_elections.php');
            exit();
        }

        $election_scope_type = $adminScope;

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
    file_put_contents('update_debug.log', "Scope error: ".$e->getMessage()."\n", FILE_APPEND);
}

// Process voter type (align with create_election.php)
switch ($target_voter) {

    // STUDENT
    case 'student':
        $allowed_colleges = $_POST['allowed_colleges'] ?? 'all';
        if (strtolower($allowed_colleges) === 'all' || $allowed_colleges === '') {
            $allowed_colleges = 'All';
            $allowed_courses  = '';
        } else {
            $raw = $_POST['allowed_courses_student'] ?? [];
            if (!empty($raw) && is_array($raw)) {
                $clean = array_map('trim', $raw);
                $clean = array_filter($clean, fn($c) => $c !== '');
                $allowed_courses = $clean ? implode(',', $clean) : '';
            } else {
                $allowed_courses = '';
            }
        }

        $target_position   = 'student';
        $target_department = 'Students';
        break;

    // FACULTY
    case 'faculty':
        $allowed_colleges = $_POST['allowed_colleges_faculty'] ?? 'all';
        if (strtolower($allowed_colleges) === 'all' || $allowed_colleges === '') {
            $allowed_colleges = 'All';
        }

        $allowed_courses = null;

        // Departments from UI
        $deptArr = $_POST['allowed_departments_faculty'] ?? [];
        if (!empty($deptArr) && is_array($deptArr)) {
            $clean = array_map('trim', $deptArr);
            $clean = array_filter($clean, fn($d) => $d !== '');
            $allowed_departments = $clean ? implode(',', $clean) : 'All';
        } else {
            $allowed_departments = 'All';
        }

        // Status from UI
        $rawStatus = $_POST['allowed_status_faculty'] ?? [];
        if (!empty($rawStatus) && is_array($rawStatus)) {
            $clean = array_map('trim', $rawStatus);
            $clean = array_filter($clean, fn($s) => $s !== '');
            $allowed_status = $clean ? implode(',', $clean) : null;
        } else {
            $allowed_status = null;
        }

        $target_position   = 'faculty';
        $target_department = 'Faculty';
        break;

    // NON-ACADEMIC
    case 'non_academic':
        $departments = $_POST['allowed_departments_nonacad'] ?? 'all';
        $allowed_departments = (strtolower($departments) === 'all') ? 'All' : $departments;

        if (!empty($_POST['allowed_status_nonacad'])) {
            $allowed_status_arr = array_map('trim', $_POST['allowed_status_nonacad']);
            $allowed_status     = implode(',', $allowed_status_arr);
        } else {
            $allowed_status = 'All';
        }

        $allowed_courses   = '';
        $allowed_colleges  = 'All';
        $target_position   = 'non-academic';
        $target_department = 'Non-Academic';
        break;

    // OTHERS (generic, no MIGS logic; voters fully defined by uploads)
    case 'others':
        $allowed_colleges    = 'All';
        $allowed_courses     = '';
        $allowed_status      = null;       // no explicit filter
        $allowed_departments = 'All';

        $target_position     = 'others';
        $target_department   = 'Others';

        if ($election_scope_type === null) {
            $election_scope_type = 'Others';
        }
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

    $fileName       = time() . "_" . basename($_FILES["update_logo"]["name"]);
    $targetFilePath = $targetDir . $fileName;

    $fileType     = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
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
        title               = :title,
        description         = :description,
        start_datetime      = :start_datetime,
        end_datetime        = :end_datetime,
        target_position     = :target_position,
        target_department   = :target_department,
        election_scope_type = :election_scope_type,
        owner_scope_id      = :owner_scope_id,
        allowed_colleges    = :allowed_colleges,
        allowed_courses     = :allowed_courses,
        allowed_status      = :allowed_status,
        allowed_departments = :allowed_departments,
        realtime_results    = :realtime_results,
        logo_path           = :logo_path,
        assigned_admin_id   = :assigned_admin_id
        WHERE election_id   = :election_id";

    $stmt = $pdo->prepare($sql);

    $params = [
        ':title'               => $title,
        ':description'         => $description,
        ':start_datetime'      => $start_datetime,
        ':end_datetime'        => $end_datetime,
        ':target_position'     => $target_position,
        ':target_department'   => $target_department,
        ':election_scope_type' => $election_scope_type,
        ':owner_scope_id'      => $owner_scope_id,
        ':allowed_colleges'    => $allowed_colleges,
        ':allowed_courses'     => $allowed_courses,
        ':allowed_status'      => $allowed_status,
        ':allowed_departments' => $allowed_departments,
        ':realtime_results'    => $realtime_results,
        ':logo_path'           => $logoPath,
        ':assigned_admin_id'   => $assigned_admin_id,
        ':election_id'         => $election_id
    ];

    file_put_contents('update_debug.log', "Executing with params:\n".print_r($params, true)."\n", FILE_APPEND);

    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = 'Election updated successfully!';
        file_put_contents('update_debug.log', "Update successful for election $election_id\n", FILE_APPEND);
    } else {
        $_SESSION['info'] = 'No changes were made (data identical)';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    file_put_contents('update_debug.log', "Error: ".$e->getMessage()."\n", FILE_APPEND);
}

header('Location: manage_elections.php');
exit();
