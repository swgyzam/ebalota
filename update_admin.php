<?php
// Start output buffering immediately
ob_start();

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';
require 'admin_functions.php'; // Include helper functions

// Function to send JSON response and exit
function sendJsonResponse($status, $message, $data = []) {
    $response = array_merge(['status' => $status, 'message' => $message], $data);
    echo json_encode($response);
    exit;
}

// Database configuration
 $host = 'localhost';
 $db = 'evoting_system';
 $user = 'root';
 $pass = '';
 $charset = 'utf8mb4';

 $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
 $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    sendJsonResponse('error', 'Database connection failed.');
}

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    error_log("Unauthorized access attempt by user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    sendJsonResponse('error', 'Unauthorized access.');
}

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed for user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    sendJsonResponse('error', 'Security validation failed. Please try again.');
}

// Get and sanitize form data
 $user_id = intval($_POST['user_id'] ?? 0);
 $admin_title = htmlspecialchars(trim($_POST['admin_title'] ?? ''), ENT_QUOTES, 'UTF-8');
 $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
 $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
 $email = trim($_POST['email'] ?? '');
 $scope_category = trim($_POST['scope_category'] ?? '');

// Validate required fields
if (!$user_id || !$admin_title || !$first_name || !$last_name || !$email || !$scope_category) {
    sendJsonResponse('error', 'All fields are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid email format.');
}

// Check if user exists and get current data
 $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
 $stmt->execute([$user_id]);
 $user = $stmt->fetch();

if (!$user) {
    sendJsonResponse('error', 'Admin not found.');
}

 $old_email = $user['email'];
 $email_changed = ($old_email !== $email);

// Check for existing email (excluding current user)
if ($email_changed) {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        sendJsonResponse('error', 'Email is already in use by another admin.');
    }
}

// Get current scope details
 $stmt = $pdo->prepare("SELECT * FROM admin_scopes WHERE user_id = ?");
 $stmt->execute([$user_id]);
 $scopeData = $stmt->fetch();

if ($scopeData) {
    $current_scope_details = json_decode($scopeData['scope_details'], true);
} else {
    $current_scope_details = [];
}

// Process scope details based on category
 $scope_details = [];
 $assigned_scope = '';
 $assigned_scope_1 = '';

switch ($scope_category) {
    case 'Academic-Student':
        $college = $_POST['college'] ?? '';
        $courses = $_POST['courses'] ?? [];
        
        if (empty($college)) {
            sendJsonResponse('error', 'College selection is required for Academic-Student admins.');
        }
        
        // Check if all courses are selected
        if (isset($_POST['select_all_courses']) && $_POST['select_all_courses'] === 'true') {
            $courses_display = 'All';
            $assigned_scope_1 = 'All';
        } else if (!empty($courses)) {
            // If multiple courses are selected, store them as comma-separated list
            if (count($courses) > 1) {
                $courses_display = implode(', ', $courses);
                $assigned_scope_1 = 'Multiple: ' . implode(', ', $courses);
            } else {
                $courses_display = $courses[0];
                $assigned_scope_1 = $courses[0];
            }
        } else {
            $courses_display = '';
            $assigned_scope_1 = '';
        }
        
        $scope_details = [
            'college' => $college,
            'courses' => $courses,
            'courses_display' => $courses_display
        ];
        $assigned_scope = $college;
        break;
        
    case 'Academic-Faculty':
        $college = $_POST['college'] ?? '';
        $departments = $_POST['departments'] ?? [];
        
        if (empty($college)) {
            sendJsonResponse('error', 'College selection is required for Academic-Faculty admins.');
        }
        
        // Check if all departments are selected
        if (isset($_POST['select_all_departments']) && $_POST['select_all_departments'] === 'true') {
            $departments_display = 'All';
            $assigned_scope_1 = 'All';
        } else if (!empty($departments)) {
            // If multiple departments are selected, store them as comma-separated list
            if (count($departments) > 1) {
                $departments_display = implode(', ', $departments);
                $assigned_scope_1 = 'Multiple: ' . implode(', ', $departments);
            } else {
                $departments_display = $departments[0];
                $assigned_scope_1 = $departments[0];
            }
        } else {
            $departments_display = '';
            $assigned_scope_1 = '';
        }
        
        $scope_details = [
            'college' => $college,
            'departments' => $departments,
            'departments_display' => $departments_display
        ];
        $assigned_scope = $college;
        break;
        
    case 'Non-Academic-Employee':
        $departments = $_POST['departments'] ?? [];
        
        if (empty($departments)) {
            sendJsonResponse('error', 'Department selection is required for Non-Academic-Employee admins.');
        }
        
        // Check if all non-academic departments are selected
        if (isset($_POST['select_all_non_academic_depts']) && $_POST['select_all_non_academic_depts'] === 'true') {
            $departments_display = 'All';
            $assigned_scope_1 = 'All';
        } else if (!empty($departments)) {
            // If multiple departments are selected, store them as comma-separated list
            if (count($departments) > 1) {
                $departments_display = implode(', ', $departments);
                $assigned_scope_1 = 'Multiple: ' . implode(', ', $departments);
            } else {
                $departments_display = $departments[0];
                $assigned_scope_1 = $departments[0];
            }
        } else {
            $departments_display = '';
            $assigned_scope_1 = '';
        }
        
        $scope_details = [
            'departments' => $departments,
            'departments_display' => $departments_display
        ];
        $assigned_scope = $departments[0] ?? 'Non-Academic';
        break;
        
    case 'Others-COOP':
        $scope_details = ['type' => 'coop'];
        $assigned_scope = 'COOP';
        $assigned_scope_1 = 'COOP Admin';
        break;
        
    case 'Others-Default':
        $scope_details = ['type' => 'default'];
        $assigned_scope = 'Default';
        $assigned_scope_1 = 'Default Admin';
        break;
        
    case 'Special-Scope':
        $scope_details = ['type' => 'csg'];
        $assigned_scope = 'CSG Admin';
        $assigned_scope_1 = 'CSG Admin';
        break;
        
    default:
        sendJsonResponse('error', 'Invalid scope category.');
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update admin in users table - UPDATED to include scope_category
    $stmt = $pdo->prepare("UPDATE users SET 
        admin_title = :admin_title, 
        first_name = :first_name, 
        last_name = :last_name, 
        email = :email,
        assigned_scope = :assigned_scope,
        assigned_scope_1 = :assigned_scope_1,
        scope_category = :scope_category
        WHERE user_id = :user_id");
    
    $stmt->execute([
        ':admin_title' => $admin_title,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':assigned_scope' => $assigned_scope,
        ':assigned_scope_1' => $assigned_scope_1,
        ':scope_category' => $scope_category,
        ':user_id' => $user_id
    ]);
    
    // Update or insert admin scope details
    if ($scopeData) {
        // Update existing scope details
        $stmt = $pdo->prepare("UPDATE admin_scopes SET 
            scope_type = :scope_type, 
            scope_details = :scope_details 
            WHERE user_id = :user_id");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':scope_type' => $scope_category,
            ':scope_details' => json_encode($scope_details)
        ]);
    } else {
        // Insert new scope details
        $stmt = $pdo->prepare("INSERT INTO admin_scopes 
            (user_id, scope_type, scope_details)
            VALUES (:user_id, :scope_type, :scope_details)");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':scope_type' => $scope_category,
            ':scope_details' => json_encode($scope_details)
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log successful admin update
    error_log("Admin updated successfully: User ID $user_id, Email: $email");

    // Send email notification if email was changed
    if ($email_changed) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'krpmab@gmail.com';
            $mail->Password   = 'ghdumnwrjbphujbs';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('mark.anthony.mark233@gmail.com', 'eBalota System');
            $mail->addAddress($email, "$first_name $last_name");
            $mail->isHTML(true);
            $mail->Subject = 'Your eBalota Admin Account Has Been Updated';
            
            // Get scope description for email
            $scope_description = formatScopeDetails($scope_category, json_encode($scope_details));
            
            $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>eBalota Admin Account Updated</title>
                    <style>
                        body {
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            margin: 0;
                            padding: 0;
                            background-color: #f5f5f5;
                            color: #333;
                        }
                        .container {
                            max-width: 600px;
                            margin: 0 auto;
                            background-color: #ffffff;
                        }
                        .header {
                            background: linear-gradient(135deg, #1E6F46 0%, #2d8659 100%);
                            color: white;
                            padding: 30px 20px;
                            text-align: center;
                            border-radius: 8px 8px 0 0;
                        }
                        .header h1 {
                            margin: 0;
                            font-size: 24px;
                            font-weight: 600;
                        }
                        .header p {
                            margin: 8px 0 0 0;
                            font-size: 14px;
                            opacity: 0.9;
                        }
                        .content {
                            padding: 30px;
                        }
                        .greeting {
                            font-size: 18px;
                            margin-bottom: 20px;
                            color: #333;
                        }
                        .greeting strong {
                            color: #1E6F46;
                        }
                        .info-box {
                            background-color: #f8f9fa;
                            border-left: 4px solid #1E6F46;
                            padding: 20px;
                            margin: 25px 0;
                            border-radius: 4px;
                        }
                        .info-box h3 {
                            margin: 0 0 15px 0;
                            color: #1E6F46;
                            font-size: 16px;
                        }
                        .info-box p {
                            margin: 8px 0;
                            line-height: 1.5;
                        }
                        .credentials-box {
                            background-color: #e8f5e9;
                            border: 1px solid #d4edda;
                            padding: 20px;
                            margin: 25px 0;
                            border-radius: 4px;
                        }
                        .credentials-box h3 {
                            margin: 0 0 15px 0;
                            color: #155724;
                            font-size: 16px;
                        }
                        .credentials-box .credential-item {
                            display: flex;
                            justify-content: space-between;
                            padding: 8px 0;
                            border-bottom: 1px solid #c3e6cb;
                        }
                        .credentials-box .credential-item:last-child {
                            border-bottom: none;
                        }
                        .credentials-box .credential-label {
                            font-weight: 600;
                            color: #155724;
                        }
                        .credentials-box .credential-value {
                            font-family: inherit;
                            color: #0c5460;
                        }
                        .button-container {
                            text-align: center;
                            margin: 30px 0;
                        }
                        .login-button {
                            display: inline-block;
                            background-color: #1E6F46;
                            padding: 14px 28px;
                            text-decoration: none;
                            border-radius: 50px;
                            font-weight: 600;
                            font-size: 16px;
                            transition: all 0.3s ease;
                        }
                        .login-button span {
                            color: white;
                        }
                        .login-button:link, .login-button:visited, .login-button:hover, .login-button:active {
                            color: white;
                            text-decoration: none;
                        }
                        .reminder {
                            background-color: #fff3cd;
                            border: 1px solid #ffeaa7;
                            color: #856404;
                            padding: 15px;
                            border-radius: 4px;
                            margin: 25px 0;
                            text-align: center;
                        }
                        .reminder strong {
                            color: #856404;
                        }
                        .footer {
                            text-align: center;
                            padding: 20px;
                            color: #6c757d;
                            font-size: 12px;
                            border-top: 1px solid #e9ecef;
                        }
                        .footer p {
                            margin: 5px 0;
                        }
                        @media only screen and (max-width: 600px) {
                            .container {
                                width: 100%;
                            }
                            .header, .content {
                                padding: 20px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Your Admin Account Has Been Updated</h1>
                            <p>eBalota System Notification</p>
                        </div>
                        
                        <div class='content'>
                            <p class='greeting'>Dear <strong>$admin_title</strong>,</p>
                            
                            <p>Your administrator account information has been updated in the eBalota System. Below are your updated account details:</p>
                            
                            <div class='info-box'>
                                <h3>Updated Account Information</h3>
                                <p><strong>Admin Title:</strong> $admin_title</p>
                                <p><strong>Name:</strong> $first_name $last_name</p>
                                <p><strong>Email Address:</strong> $email</p>
                                <p><strong>Scope Category:</strong> " . getScopeCategoryLabel($scope_category) . "</p>
                                <p><strong>Scope Details:</strong> $scope_description</p>
                            </div>
                            
                            <div class='reminder'>
                                <strong>Important:</strong> Your email address has been changed from <strong>$old_email</strong> to <strong>$email</strong>. Please use your new email address for all future communications and login attempts.
                            </div>
                            
                            <div class='button-container'>
                                <a href='http://localhost/ebalota/login.php' class='login-button'>
                                    <span>Login to Your Account</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>If you did not request these changes, please contact your system administrator immediately.</p>
                            <p>© " . date('Y') . " eBalota System. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->AltBody = "Dear $admin_title, Your administrator account information has been updated. Title: $admin_title, Name: $first_name $last_name, Email: $email, Scope: $scope_description. Your email has been changed from $old_email to $email. Please use your new email for login. If you did not request these changes, contact your system administrator.";

            $mail->send();
            error_log("Email notification sent for admin email change: User ID $user_id, Old: $old_email, New: $email");
        } catch (Exception $e) {
            error_log("Email notification failed for admin update: " . $mail->ErrorInfo);
            // Don't fail the update if email fails
        }
    }

    sendJsonResponse('success', 'Admin information updated successfully.' . ($email_changed ? ' A notification has been sent to the new email address.' : ''), [
        'admin_title' => $admin_title,
        'email' => $email,
        'email_changed' => $email_changed
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Admin update failed: " . $e->getMessage());
    sendJsonResponse('error', 'Failed to update admin.');
}

// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}
?>