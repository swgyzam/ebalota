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
    $first_name = trim(htmlspecialchars($_POST['first_name'] ?? ''));
    $last_name = trim(htmlspecialchars($_POST['last_name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $position = $_POST['position'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (!in_array($position, ['student', 'faculty', 'coop'])) $errors[] = "Please select a valid position.";
    if (empty($department)) $errors[] = "Department is required.";
    if ($position === 'student' && empty($course)) $errors[] = "Course is required for students.";
    if (($position === 'faculty' || $position === 'coop') && empty($status)) $errors[] = "Status is required for faculty and coop.";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain uppercase, lowercase letters, and numbers.";
    }
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT email FROM pending_users WHERE email = ? UNION SELECT email FROM users WHERE email = ?");
        $stmt->execute([$email, $email]);
        if ($stmt->fetch()) {
            $errors[] = "This email is already registered.";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));

        try {
            $stmt = $pdo->prepare("INSERT INTO pending_users 
                (first_name, last_name, email, position, department, course, status, password, token, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $position,
                $department,
                $position === 'student' ? $course : null,
                ($position === 'faculty' || $position === 'coop') ? $status : null,
                $hashed_password,
                $token,
                $expiresAt
            ]);

            $verificationUrl = "http://localhost/evoting/verify_email.php?token=$token";

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mark.anthony.mark233@gmail.com';  // change to your email
            $mail->Password = 'dbqwfzasqmaitlty';                // change to your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('your-email@gmail.com', 'Evoting System');
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
                Regards,<br>Evoting System
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

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Registration Result</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

  <!-- Modal -->
  <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 <?php if (!$success && empty($errors)) echo 'hidden'; ?>">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-10 relative text-center flex flex-col items-center">

      <!-- Close Button -->
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

      <a href="register.html" class="mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded transition">
        Back to Registration
      </a>
    </div>
  </div>

  <script>
    document.getElementById('closeBtn').addEventListener('click', () => {
      document.getElementById('modal').classList.add('hidden');
    });

    document.getElementById('modal').addEventListener('click', (e) => {
      if (e.target === e.currentTarget) {
        document.getElementById('modal').classList.add('hidden');
      }
    });
  </script>

</body>
</html>
