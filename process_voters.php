<?php
session_start();
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Check if CSV file path is passed
if (!isset($_SESSION['csv_file_path'])) {
    die("CSV file not found. Please upload again.");
}

$csvFile = $_SESSION['csv_file_path'];

// Open the file
if (($handle = fopen($csvFile, 'r')) !== FALSE) {
    $rowCount = 0;
    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    // Skip header row
    fgetcsv($handle);

    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $rowCount++;  // Increment row count at the start

        // Skip empty or incomplete rows
        if (count($data) < 7) {
            $errorCount++;
            $errors[] = "Row {$rowCount}: Missing fields.";
            continue;
        }
    
        $first_name = trim($data[0]);
        $last_name = trim($data[1]);
        $email = trim($data[2]);
        $position = strtolower(trim($data[3])); // student/faculty/coop
        $department = trim($data[4]);
        $course = trim($data[5]);
        $status = strtolower(trim($data[6]));

        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($first_name) || empty($last_name)) {
            $errorCount++;
            $errors[] = "Row {$rowCount}: Invalid or missing fields.";
            continue;
        }

        // Map CSV status to DB status enum ('regular', 'lecturer') or NULL for students
        $db_status = null;
        if (in_array($position, ['academic', 'non-academic'])) {
            if (strpos(strtolower($status), 'contractual') !== false) {
                $db_status = 'contractual';
            } else {
                $db_status = 'regular';
            }
        }

        // Check for duplicate email in pending_users or users
        $check = $pdo->prepare("SELECT email FROM pending_users WHERE email = ? UNION SELECT email FROM users WHERE email = ?");
        $check->execute([$email, $email]);
        if ($check->fetch()) {
            $errorCount++;
            $errors[] = "Row {$rowCount}: Email already exists.";
            continue;
        }

        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
        $password = null;

        try {
            $stmt = $pdo->prepare("INSERT INTO pending_users (first_name, last_name, email, position, department, course, status, password, token, expires_at, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'csv')");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $position,
                $department,
                $course,
                $db_status,
                $password,
                $token,
                $expires_at
            ]);

            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Row {$rowCount}: " . $e->getMessage();
        }
    }

    fclose($handle);

    // Show import summary
    echo "<h2>Import Summary</h2>";
    echo "<p>Total rows processed: $rowCount</p>";
    echo "<p>Successful inserts: $successCount</p>";
    echo "<p>Errors: $errorCount</p>";

    if (!empty($errors)) {
        echo "<h3>Error Details:</h3><ul>";
        foreach ($errors as $err) {
            echo "<li>" . htmlspecialchars($err) . "</li>";
        }
        echo "</ul>";
    }

    // Clean up session
    unset($_SESSION['csv_file_path']);

} else {
    die("Unable to open CSV file.");
}
?>
