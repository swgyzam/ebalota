<?php
session_start();

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Status mapping and validation - same as in CSV upload
$statusMapping = [
    'full-time'    => 'Regular',
    'part-time'    => 'Part-time',
    'contractual'  => 'Contractual',
    'regular'      => 'Regular',
    'full time'    => 'Regular',
    'part time'    => 'Part-time',
    'permanent'    => 'Regular',
    'temporary'    => 'Contractual',
    'probationary' => 'Contractual',
    'casual'       => 'Contractual',
];

// Allowed statuses
$allowedStatuses = ['Regular', 'Part-time', 'Contractual'];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- Basic fields ----------
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Format names: each word capitalized
    function formatName($name) {
        $name  = strtolower($name);
        $words = explode(' ', $name);
        $out   = [];

        foreach ($words as $word) {
            if ($word !== '') {
                $out[] = ucfirst($word);
            }
        }
        return implode(' ', $out);
    }

    $first_name = formatName($first_name);
    $last_name  = formatName($last_name);

    // ---------- Position logic ----------
    $position = $_POST['position'] ?? '';

    $student_number  = '';
    $employee_number = '';
    $year_level      = 0; // default, para hindi undefined sa non-student

    $department   = '';
    $department1  = null;
    $course       = null;
    $status       = '';
    $is_coop_member = 0;
    $final_position = '';

    if ($position === 'student') {
        // Basahin lahat ng student-specific fields
        $year_level      = (int)($_POST['year_level'] ?? 0);
        $department      = $_POST['studentDepartment']  ?? '';
        $department1     = $_POST['studentDepartment1'] ?? '';
        $course          = $_POST['studentCourse']      ?? '';
        $student_number  = trim($_POST['student_number'] ?? '');
        $final_position  = 'student';

    } elseif ($position === 'academic') {
        $department   = $_POST['academicCollege'] ?? '';
        $department1  = $_POST['academicDepartment'] ?? '';
        $raw_status   = $_POST['academicStatus'] ?? '';

        $status_lower = strtolower($raw_status);
        $status = $statusMapping[$status_lower] ?? $raw_status;

        if (isset($_POST['academicIsCoop'])) {
            $is_coop_member = 1;
        }
        $employee_number = trim($_POST['employee_number'] ?? '');
        $final_position  = 'academic';

    } elseif ($position === 'non-academic') {
        $department  = $_POST['nonAcademicDept'] ?? '';
        $raw_status  = $_POST['nonAcademicStatus'] ?? '';

        $status_lower = strtolower($raw_status);
        $status = $statusMapping[$status_lower] ?? $raw_status;

        if (isset($_POST['nonAcademicIsCoop'])) {
            $is_coop_member = 1;
        }
        $employee_number = trim($_POST['employee_number'] ?? '');
        $final_position  = 'non-academic';
    }

    $position_db = $final_position;

    // ---------- Validation ----------
    if ($first_name === '') $errors[] = "First name is required.";
    if ($last_name === '')  $errors[] = "Last name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if ($position === '')   $errors[] = "Position is required.";

    // Student / employee numbers
    if ($position === 'student' && $student_number === '') {
        $errors[] = "Student number is required for students.";
    }
    if (($position === 'academic' || $position === 'non-academic') && $employee_number === '') {
        $errors[] = "Employee number is required for academic and non-academic staff.";
    }

    // Position-specific validation
    if ($position === 'student') {
        if ($year_level < 1 || $year_level > 5) {
            $errors[] = "Year level is required.";
        }
        if ($department === '')  $errors[] = "College is required for students.";
        if ($department1 === '') $errors[] = "Department is required for students.";
        if ($course === '')      $errors[] = "Course is required for students.";

    } elseif ($position === 'academic') {
        if ($department === '')  $errors[] = "College is required for academic.";
        if ($department1 === '') $errors[] = "Department is required for academic.";
        if ($status === '')      $errors[] = "Status is required for academic.";
        elseif (!in_array($status, $allowedStatuses, true)) {
            $errors[] = "Status must be one of: " . implode(', ', $allowedStatuses);
        }

    } elseif ($position === 'non-academic') {
        if ($department === '')  $errors[] = "Department is required for non-academic.";
        if ($status === '')      $errors[] = "Status is required for non-academic.";
        elseif (!in_array($status, $allowedStatuses, true)) {
            $errors[] = "Status must be one of: " . implode(', ', $allowedStatuses);
        }
    }

    // Password rules (same idea as client-side)
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if (!preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain uppercase, lowercase letters, and numbers.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // ---------- Duplicate checks ----------
    // Email in users
    $user_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $user_check_stmt->execute([$email]);
    $user_count = (int)$user_check_stmt->fetchColumn();

    // Email in pending_users (normal)
    $pending_normal_stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE email = ? AND source = 'normal'");
    $pending_normal_stmt->execute([$email]);
    $pending_normal_count = (int)$pending_normal_stmt->fetchColumn();

    // Email in pending_users CSV not restricted
    $pending_csv_verify_stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE email = ? AND source = 'csv' AND is_restricted = 0");
    $pending_csv_verify_stmt->execute([$email]);
    $pending_csv_verify_count = (int)$pending_csv_verify_stmt->fetchColumn();

    // Email in pending_users CSV restricted
    $pending_csv_restrict_stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE email = ? AND source = 'csv' AND is_restricted = 1");
    $pending_csv_restrict_stmt->execute([$email]);
    $pending_csv_restrict_count = (int)$pending_csv_restrict_stmt->fetchColumn();

    if ($user_count > 0) {
        $errors[] = "Email already registered.";
    } elseif ($pending_normal_count > 0) {
        $errors[] = "Email already registered. Please check your email to verify your account.";
    } elseif ($pending_csv_verify_count > 0) {
        $errors[] = "This email was already uploaded by an administrator. Please check your email to verify your account and access the voting system.";
    } elseif ($pending_csv_restrict_count > 0) {
        $errors[] = "You're not allowed to vote.";
    }

    // Duplicate student / employee numbers in pending_users
    if ($position === 'student' && $student_number !== '') {
        $check_num = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE student_number = ?");
        $check_num->execute([$student_number]);
        if ($check_num->fetchColumn() > 0) {
            $errors[] = "Student number already registered.";
        }
    } elseif (($position === 'academic' || $position === 'non-academic') && $employee_number !== '') {
        $check_num = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE employee_number = ?");
        $check_num->execute([$employee_number]);
        if ($check_num->fetchColumn() > 0) {
            $errors[] = "Employee number already registered.";
        }
    }

    // --- STUDENT EXPIRY CALCULATION ---
    $accountExpiresAt = null;

    if ($position === 'student') {
        $remainingYears = null;

        if ($year_level >= 1 && $year_level <= 4) {
            $remainingYears = 5 - $year_level; // 1→4, 2→3, 3→2, 4→1
        } elseif ($year_level === 5) {
            $remainingYears = 1; // 5th year = 1 year palugit
        }

        if ($remainingYears !== null) {
            $accountExpiresAt = date('Y-m-d H:i:s', strtotime("+{$remainingYears} years"));
        }
    }

    // ---------- If no errors, insert + send email ----------
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO pending_users 
                    (
                        first_name, 
                        last_name, 
                        email, 
                        position, 
                        department, 
                        department1, 
                        course, 
                        status, 
                        password, 
                        token, 
                        expires_at,
                        account_expires_at,
                        year_level_at_registration,
                        is_coop_member, 
                        student_number, 
                        employee_number, 
                        source, 
                        is_restricted
                    ) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'normal', 0)
            ");

            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $position_db,
                $department,
                $department1,
                $course,
                $status,
                $hashed_password,
                $token,
                $expiresAt,            // verification expiry (1 day)
                $accountExpiresAt,     // our computed student expiry (4/3/2/1/1)
                $year_level,           // save year level
                $is_coop_member,
                $student_number,
                $employee_number
            ]);            

            $verificationUrl = "http://localhost/ebalota/verify_email.php?token=$token";

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'krpmab@gmail.com';
            $mail->Password   = 'ghdumnwrjbphujbs';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota');
            $mail->addAddress($email, "$first_name $last_name");
            
            $mail->isHTML(true);
            $mail->Subject = 'Email Verification';
            $mail->Body    = "
                Hi $first_name,<br><br>
                Please verify your email by clicking the button below:<br><br>
                <a href='$verificationUrl' style='
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #28a745;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                '>Verify Email</a><br><br>
                This link will expire in 24 hours.<br><br>
                Regards,<br>eBalota | Cavite State University
            ";
            $mail->AltBody = "Please verify your email by visiting: $verificationUrl";
            
            $mail->send();
            $success = true;

        } catch (Exception $e) {
            $errors[] = "Verification email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            $success = false;
        }
    }

    // ---------- Redirect back to register.html with query params ----------
    if ($success) {
        // Show success modal in register.html
        header('Location: register.html?success=true');
        exit;
    } else {
        // Concatenate all error messages into one string
        $errorMsg = implode(' ', $errors);
        $errorParam = urlencode($errorMsg);
        header('Location: register.html?error=' . $errorParam);
        exit;
    }

} else {
    // Direct access without POST
    header("Location: register.html");
    exit;
}
