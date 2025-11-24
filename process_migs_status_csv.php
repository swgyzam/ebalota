<?php
session_start();
date_default_timezone_set('Asia/Manila');

// ==============================
// AUTH CHECK – COOP ADMIN ONLY
// ==============================
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$scopeCategory = $_SESSION['scope_category'] ?? '';
$assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// New model: scope_category = 'Others-COOP'
// Legacy fallback: assigned_scope = 'COOP'
if ($scopeCategory !== 'Others-COOP' && $assignedScope !== 'COOP') {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// ==============================
// CSV FILE FROM SESSION
// ==============================
if (!isset($_SESSION['csv_file_path'])) {
    die("No CSV file to process.");
}

$csvFilePath = $_SESSION['csv_file_path'];
unset($_SESSION['csv_file_path']); // Clear after use

// ==============================
// DB CONNECTION
// ==============================
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

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

// ==============================
// CHECK FILE
// ==============================
if (!file_exists($csvFilePath)) {
    die("CSV file not found.");
}

$file = fopen($csvFilePath, 'r');
if (!$file) {
    die("Failed to open CSV file.");
}

// Skip header row
$header = fgetcsv($file);
if (!$header) {
    die("Failed to read CSV header.");
}

// ==============================
// COUNTERS & RESULT STRUCTURE
// ==============================
$totalRows          = 0;
$successCount       = 0;
$notFoundCount      = 0;
$notCoopCount       = 0;
$invalidActionCount = 0;
$emailSent          = 0;
$emailFailed        = 0;
$errorMessages      = [];

// ==============================
// PHPMailer SETUP
// ==============================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

/**
 * Send MIGS status update email via PHPMailer.
 *
 * @param string $userEmail
 * @param string $userName
 * @param int    $newStatus 1 = activated, 0 = deactivated
 * @return bool
 */
function sendMigsStatusEmail(string $userEmail, string $userName, int $newStatus): bool
{
    $subject = "MIGS Status Update";
    $statusWord = $newStatus ? "activated" : "deactivated";

    $plainText = "Dear {$userName},\n\n"
        . "Your MIGS status has been {$statusWord}.\n\n"
        . "This means you are " . ($newStatus ? "now eligible" : "no longer eligible") . " to vote in the COOP election.\n\n"
        . "If you believe this is an error, please contact the administrator.\n\n"
        . "Regards,\nCVSU eVoting System";

    $htmlBody = "
        Hi {$userName},<br><br>
        Your MIGS status has been <strong>{$statusWord}</strong>.<br><br>
        This means you are " . ($newStatus ? "<strong>now eligible</strong>" : "<strong>no longer eligible</strong>") . " to vote in the COOP election.<br><br>
        If you believe this is an error, please contact the administrator.<br><br>
        Regards,<br>
        <strong>CVSU eVoting System | Cavite State University</strong>
    ";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';      // your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username = 'krpmab@gmail.com';
        $mail->Password = 'ghdumnwrjbphujbs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@example.com', 'CVSU eVoting System');
        $mail->addAddress($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to {$userEmail}: " . $e->getMessage());
        return false;
    }
}

// ==============================
// PROCESS CSV ROWS (email,action)
// ==============================
$rowNumber = 1; // including header row in count context

while (($row = fgetcsv($file)) !== false) {
    $rowNumber++;
    $totalRows++;

    // Expect at least 2 columns: email, action
    if (count($row) < 2) {
        $invalidActionCount++;
        $errorMessages[] = "Row {$rowNumber}: Insufficient columns. Expected: email, action";
        continue;
    }

    $email  = trim($row[0] ?? '');
    $action = strtolower(trim($row[1] ?? ''));

    // Validate required fields
    if ($email === '' || $action === '') {
        $invalidActionCount++;
        $errorMessages[] = "Row {$rowNumber}: Missing required fields (email or action)";
        continue;
    }

    // Validate action
    if (!in_array($action, ['activate', 'deactivate'], true)) {
        $invalidActionCount++;
        $errorMessages[] = "Row {$rowNumber}: Invalid action '{$action}'. Must be 'activate' or 'deactivate'";
        continue;
    }

    // Determine new status
    $newStatus = ($action === 'activate') ? 1 : 0;

    try {
        // Look up user by email
        $stmt = $pdo->prepare("
            SELECT user_id, email, first_name, last_name, is_coop_member, migs_status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $notFoundCount++;
            $errorMessages[] = "Row {$rowNumber}: Email '{$email}' not found in the system";
            continue;
        }

        // Check if user is COOP member
        if (empty($user['is_coop_member'])) {
            $notCoopCount++;
            $errorMessages[] = "Row {$rowNumber}: User with email '{$email}' is not a COOP member (is_coop_member != 1)";
            continue;
        }

        // Check if status is already the same
        if ((int)$user['migs_status'] === $newStatus) {
            // Already in desired status – we can log but count as "no change"
            $errorMessages[] = "Row {$rowNumber}: User with email '{$email}' already has MIGS status " . ($newStatus ? 'activated' : 'deactivated');
            continue;
        }

        // Update MIGS status
        $stmt = $pdo->prepare("UPDATE users SET migs_status = ? WHERE user_id = ?");
        $stmt->execute([$newStatus, $user['user_id']]);

        if ($stmt->rowCount() > 0) {
            $successCount++;

            // Send email notification
            $name     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $name     = $name !== '' ? $name : $email;
            $sentOkay = sendMigsStatusEmail($user['email'], $name, $newStatus);

            if ($sentOkay) {
                $emailSent++;
            } else {
                $emailFailed++;
                $errorMessages[] = "Row {$rowNumber}: Failed to send email to {$user['email']}";
            }
        } else {
            $errorMessages[] = "Row {$rowNumber}: Failed to update MIGS status for email '{$email}'";
        }
    } catch (Exception $e) {
        $errorMessages[] = "Row {$rowNumber}: Database/Email error - " . $e->getMessage();
        error_log("Error updating MIGS status for email {$email}: " . $e->getMessage());
    }
}

fclose($file);

// Delete the CSV file (optional but recommended)
if (file_exists($csvFilePath)) {
    @unlink($csvFilePath);
}

// ==============================
// STORE RESULTS IN SESSION
// ==============================
$_SESSION['migs_processing_results'] = [
    'totalRows'          => $totalRows,
    'successCount'       => $successCount,
    'notFoundCount'      => $notFoundCount,
    'notCoopCount'       => $notCoopCount,
    'invalidActionCount' => $invalidActionCount,
    'emailSent'          => $emailSent,
    'emailFailed'        => $emailFailed,
    'errorMessages'      => $errorMessages,
];

// Redirect back to MIGS page
header('Location: admin_migs_status.php');
exit;
