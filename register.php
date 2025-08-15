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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic fields
    $first_name = trim(htmlspecialchars($_POST['first_name'] ?? ''));
    $last_name = trim(htmlspecialchars($_POST['last_name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Position
    $position = $_POST['position'] ?? '';
    $status = '';
    $department = '';
    $course = '';
    $is_coop_member = 0;

    // Process position logic
    if ($position === 'student') {
        $department = $_POST['studentDepartment'] ?? '';
        $course = $_POST['studentCourse'] ?? '';
        $final_position = 'student'; // no coop
    } elseif ($position === 'academic') {
        $department = $_POST['academicCollege'] ?? '';
        $course = $_POST['academicCourse'] ?? '';
        $status = $_POST['academicStatus'] ?? '';
        if ($status === 'regular' && isset($_POST['academicIsCoop'])) {
            $is_coop_member = 1;
        }
        $final_position = 'academic';
      } elseif ($position === 'non-academic') {
        $department = $_POST['nonAcademicDept'] ?? '';
        $status = $_POST['nonAcademicStatus'] ?? '';
        file_put_contents('debug.log', "nonAcademicStatus value: " . var_export($status, true) . "\n", FILE_APPEND);
    
        if ($status === 'regular' && isset($_POST['nonAcademicIsCoop'])) {
            $is_coop_member = 1;
        }
        $final_position = 'non-academic';
    }
    

    // Final DB value for position (e.g., "academic,coop")
    $position_db = $final_position;
    if (($final_position === 'academic' || $final_position === 'non-academic') && $is_coop_member === 1 && $status === 'regular') {
        $position_db .= ',coop';
    }

    // Validation
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($position)) $errors[] = "Position is required.";

    if ($position === 'student') {
        if (empty($department)) $errors[] = "Department (College) is required for students.";
        if (empty($course)) $errors[] = "Course is required for students.";
    } elseif ($position === 'academic') {
        if (empty($department)) $errors[] = "College is required for academic.";
        if (empty($status)) $errors[] = "Status is required for academic.";
    } elseif ($position === 'non-academic') {
        if (empty($department)) $errors[] = "Department is required for non-academic.";
        if (empty($status)) $errors[] = "Status is required for non-academic.";
    }

    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain uppercase, lowercase letters, and numbers.";
    }
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    // Check duplicate email in both users and pending_users
    // Check if email exists in 'users' table
    $user_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $user_check_stmt->execute([$email]);
    $user_count = $user_check_stmt->fetchColumn();

    // Check if email exists in pending_users from 'normal' registration
    $pending_normal_stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE email = ? AND source = 'normal'");
    $pending_normal_stmt->execute([$email]);
    $pending_normal_count = $pending_normal_stmt->fetchColumn();

    // Check if email exists in pending_users from 'csv' upload
    $pending_csv_stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_users WHERE email = ? AND source = 'csv'");
    $pending_csv_stmt->execute([$email]);
    $pending_csv_count = $pending_csv_stmt->fetchColumn();

    // Show specific error messages
    if ($user_count > 0 || $pending_normal_count > 0) {
        $errors[] = "Email already registered.";
    } elseif ($pending_csv_count > 0) {
        $errors[] = "You're not allowed to vote.";
    }


    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO pending_users 
                (first_name, last_name, email, position, department, course, status, password, token, expires_at, is_coop_member) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $position_db,
                $department,
                $course,
                $status,
                $hashed_password,
                $token,
                $expiresAt,
                $is_coop_member
            ]);

            $verificationUrl = "http://localhost/ebalota/verify_email.php?token=$token";

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'makimaki.maki123567@gmail.com';
            $mail->Password = 'neqlotimpppfzmwj';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota');
            $mail->addAddress($email, "$first_name $last_name");

            $mail->isHTML(true);
            $mail->Subject = 'Email Verification';
            $mail->Body = "
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
        }
    }
} else {
    header("Location: register.html");
    exit;
}
?>

<!-- HTML for feedback modal -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Registration Result</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 <?php if (!$success && empty($errors)) echo 'hidden'; ?>">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-10 relative text-center flex flex-col items-center">
      <button id="closeBtn" class="absolute top-4 right-5 text-gray-600 hover:text-black text-2xl font-bold">&times;</button>

      <?php if ($success): ?>
        <div class="text-green-500 text-7xl mb-4">&#10004;</div>
        <h2 class="text-3xl font-bold mb-2 text-green-700">Registration Successful!</h2>
        <p class="text-gray-700 mb-6">
          A verification email has been sent to <strong><?= htmlspecialchars($email) ?></strong>.<br>
          Please check your email to verify your account before logging in.
        </p>
      <?php else: ?>
        <div class="text-red-500 text-7xl mb-4">&#10060;</div>
        <h2 class="text-3xl font-bold mb-2 text-red-700">Registration Failed</h2>
        <ul class="text-red-600 list-disc list-inside mb-6 text-left max-w-sm">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <a href="register.html" class="inline-block mt-2 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold">Back to Register</a>
    </div>
  </div>

  <script>
    const modal = document.getElementById('modal');
    const closeBtn = document.getElementById('closeBtn');
    closeBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
      window.location.href = 'register.html';
    });
  </script>
</body>
</html>
