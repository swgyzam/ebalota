<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Auth check - only COOP admins can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SESSION['assigned_scope'] !== 'COOP') {
    header('Location: login.php');
    exit();
}

// Check if we have the CSV file path in session
if (!isset($_SESSION['csv_file_path'])) {
    die("No CSV file to process.");
}

 $csvFilePath = $_SESSION['csv_file_path'];
unset($_SESSION['csv_file_path']); // Clear the session

// Database connection
 $host = 'localhost';
 $db = 'evoting_system';
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

// Check if the file exists
if (!file_exists($csvFilePath)) {
    die("CSV file not found.");
}

// Open the CSV file
 $file = fopen($csvFilePath, 'r');
if (!$file) {
    die("Failed to open CSV file.");
}

// Skip header row
 $header = fgetcsv($file);
if (!$header) {
    die("Failed to read CSV header.");
}

// Initialize counters
 $totalRows = 0;
 $successCount = 0;
 $notFoundCount = 0;
 $notCoopCount = 0;
 $invalidActionCount = 0;
 $emailSent = 0;
 $emailFailed = 0;
 $errorMessages = []; // Store error messages for display

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

// Function to send email using PHPMailer
function sendMigsStatusEmail($userEmail, $userName, $newStatus) {
    $subject = "MIGS Status Update";
    $message = "Dear $userName,\n\n";
    $message .= "Your MIGS status has been " . ($newStatus ? "activated" : "deactivated") . ".\n\n";
    $message .= "This means you are " . ($newStatus ? "now eligible" : "no longer eligible") . " to vote in the COOP election.\n\n";
    $message .= "If you believe this is an error, please contact the administrator.\n\n";
    $message .= "Regards,\nCVSU eVoting System";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mark.anthony.mark233@gmail.com';
        $mail->Password = 'flxoykqjycmgplrv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('makimaki.maki123567@gmail.com', 'CVSU eVoting System');
        $mail->addAddress($userEmail, $userName);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "
            Hi $userName,<br><br>
            Your MIGS status has been " . ($newStatus ? "activated" : "deactivated") . ".<br><br>
            This means you are " . ($newStatus ? "now eligible" : "no longer eligible") . " to vote in the COOP election.<br><br>
            If you believe this is an error, please contact the administrator.<br><br>
            Regards,<br>CVSU eVoting System | Cavite State University
        ";
        $mail->AltBody = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to $userEmail: " . $mail->ErrorInfo);
        return false;
    }
}

// Read each row
while (($row = fgetcsv($file)) !== FALSE) {
    $totalRows++;
    
    // Check if we have the expected number of columns (employee_number, action)
    if (count($row) < 2) {
        $invalidActionCount++;
        $errorMessages[] = "Row $totalRows: Insufficient columns. Expected: employee_number, action";
        continue;
    }
    
    // Extract data
    $employeeNumber = trim($row[0] ?? '');
    $action = strtolower(trim($row[1] ?? ''));
    
    // Validate required fields
    if (empty($employeeNumber) || empty($action)) {
        $invalidActionCount++;
        $errorMessages[] = "Row $totalRows: Missing required fields (employee_number or action)";
        continue;
    }
    
    // Validate action
    if (!in_array($action, ['activate', 'deactivate'])) {
        $invalidActionCount++;
        $errorMessages[] = "Row $totalRows: Invalid action '$action'. Must be 'activate' or 'deactivate'";
        continue;
    }
    
    // Determine new status
    $newStatus = ($action === 'activate') ? 1 : 0;
    
    try {
        // Find user by employee number
        $stmt = $pdo->prepare("SELECT * FROM users WHERE employee_number = ?");
        $stmt->execute([$employeeNumber]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $notFoundCount++;
            $errorMessages[] = "Row $totalRows: Employee number '$employeeNumber' not found in the system";
            continue;
        }
        
        // Check if user is COOP member
        if (!$user['is_coop_member']) {
            $notCoopCount++;
            $errorMessages[] = "Row $totalRows: User with employee number '$employeeNumber' is not a COOP member";
            continue;
        }
        
        // Check if status is already the same
        if ($user['migs_status'] == $newStatus) {
            $errorMessages[] = "Row $totalRows: User with employee number '$employeeNumber' already has MIGS status " . ($newStatus ? 'activated' : 'deactivated');
            continue;
        }
        
        // Update MIGS status
        $stmt = $pdo->prepare("UPDATE users SET migs_status = ? WHERE user_id = ?");
        $stmt->execute([$newStatus, $user['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $successCount++;
            
            // Send email notification
            $emailSentStatus = sendMigsStatusEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], $newStatus);
            if ($emailSentStatus) {
                $emailSent++;
            } else {
                $emailFailed++;
                $errorMessages[] = "Row $totalRows: Failed to send email to {$user['email']}";
            }
        } else {
            $errorMessages[] = "Row $totalRows: Failed to update MIGS status for employee number '$employeeNumber'";
        }
    } catch (Exception $e) {
        $errorMessages[] = "Row $totalRows: Database error - " . $e->getMessage();
        error_log("Error updating MIGS status for employee number $employeeNumber: " . $e->getMessage());
    }
}

fclose($file);

// Delete the CSV file
unlink($csvFilePath);

// Store results in session
 $_SESSION['migs_processing_results'] = [
    'totalRows' => $totalRows,
    'successCount' => $successCount,
    'notFoundCount' => $notFoundCount,
    'notCoopCount' => $notCoopCount,
    'invalidActionCount' => $invalidActionCount,
    'emailSent' => $emailSent,
    'emailFailed' => $emailFailed,
    'errorMessages' => $errorMessages
];

// Redirect back to the admin_migs_status.php page
header('Location: admin_migs_status.php');
exit;
?>