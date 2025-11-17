<?php
// Start output buffering immediately
ob_start();

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();
date_default_timezone_set('Asia/Manila');

// Set JSON header immediately
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include required files
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';
require 'admin_functions.php';

// Function to send JSON response and exit
function sendJsonResponse($status, $message, $data = []) {
    $response = array_merge(['status' => $status, 'message' => $message], $data);
    echo json_encode($response);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    error_log("Unauthorized access attempt by user_id: " . ($_SESSION['user_id'] ?? 'unknown'));
    sendJsonResponse('error', 'Unauthorized access.');
}

// Get form data
$admin_title    = trim($_POST['admin_title']    ?? '');
$first_name     = trim($_POST['first_name']     ?? '');
$last_name      = trim($_POST['last_name']      ?? '');
$email          = trim($_POST['email']          ?? '');
$scope_category = trim($_POST['scope_category'] ?? '');
$password       = $_POST['password']            ?? '';
$admin_status   = isset($_POST['admin_status']) ? trim($_POST['admin_status']) : 'inactive'; // Default to inactive

// Calculate current academic year
$currentYear  = date('Y');
$nextYear     = $currentYear + 1;
$academicYear = "$currentYear-$nextYear";

// Validate required fields
if (!$admin_title || !$first_name || !$last_name || !$email || !$scope_category || !$password) {
    sendJsonResponse('error', 'All fields are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse('error', 'Invalid email format.');
}

// Database connection
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=evoting_system;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    sendJsonResponse('error', 'Database connection failed.');
}

// Check for existing email
try {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse('error', 'Email is already in use.');
    }
} catch (PDOException $e) {
    error_log("Email check failed: " . $e->getMessage());
    sendJsonResponse('error', 'Database error occurred.');
}

// Process scope details
$scope_details    = [];
$assigned_scope   = '';
$assigned_scope_1 = '';
$scope_value      = ''; // for admin_scopes.scope_value

switch ($scope_category) {

    case 'Academic-Student': {
        $college = $_POST['college'] ?? '';
        $courses = $_POST['courses'] ?? [];

        if (empty($college)) {
            sendJsonResponse('error', 'College selection is required for Academic-Student admins.');
        }

        // Check if all courses are selected
        if (isset($_POST['select_all_courses']) && $_POST['select_all_courses'] === 'true') {
            $courses_display  = 'All';
            $assigned_scope_1 = 'All';
        } elseif (!empty($courses)) {
            if (count($courses) > 1) {
                $courses_display  = implode(', ', $courses);
                $assigned_scope_1 = 'Multiple: ' . implode(', ', $courses);
            } else {
                $courses_display  = $courses[0];
                $assigned_scope_1 = $courses[0];
            }
        } else {
            $courses_display  = '';
            $assigned_scope_1 = '';
        }

        $scope_details = [
            'college'         => $college,
            'courses'         => $courses,
            'courses_display' => $courses_display
        ];
        $assigned_scope = $college;
        $scope_value    = $college; // key for scope; courses are in details
        break;
    }

    case 'Academic-Faculty': {
        $college     = $_POST['college']    ?? '';
        $departments = $_POST['departments']?? [];

        if (empty($college)) {
            sendJsonResponse('error', 'College selection is required for Academic-Faculty admins.');
        }

        // Check if all departments are selected
        if (isset($_POST['select_all_departments']) && $_POST['select_all_departments'] === 'true') {
            $departments_display = 'All';
            $assigned_scope_1    = 'All';
        } elseif (!empty($departments)) {
            if (count($departments) > 1) {
                $departments_display = implode(', ', $departments);
                $assigned_scope_1    = 'Multiple: ' . implode(', ', $departments);
            } else {
                $departments_display = $departments[0];
                $assigned_scope_1    = $departments[0];
            }
        } else {
            $departments_display = '';
            $assigned_scope_1    = '';
        }

        $scope_details = [
            'college'             => $college,
            'departments'         => $departments,
            'departments_display' => $departments_display
        ];
        $assigned_scope = $college;
        $scope_value    = $college;
        break;
    }

    case 'Non-Academic-Employee': {
        $departments = $_POST['departments'] ?? [];

        if (empty($departments)) {
            sendJsonResponse('error', 'Department selection is required for Non-Academic-Employee admins.');
        }

        if (isset($_POST['select_all_non_academic_depts']) && $_POST['select_all_non_academic_depts'] === 'true') {
            $departments_display = 'All';
            $assigned_scope_1    = 'All';
        } elseif (!empty($departments)) {
            if (count($departments) > 1) {
                $departments_display = implode(', ', $departments);
                $assigned_scope_1    = 'Multiple: ' . implode(', ', $departments);
            } else {
                $departments_display = $departments[0];
                $assigned_scope_1    = $departments[0];
            }
        } else {
            $departments_display = '';
            $assigned_scope_1    = '';
        }

        $scope_details = [
            'departments'         => $departments,
            'departments_display' => $departments_display
        ];
        $assigned_scope = $departments[0] ?? 'Non-Academic';
        $scope_value    = 'Non-Academic-Employee';
        break;
    }

    case 'Non-Academic-Student': {
        // Global non-academic student org scope for now
        $scope_details    = ['type' => 'non_academic_student'];
        $assigned_scope   = 'Non-Academic-Student';
        $assigned_scope_1 = 'All';
        $scope_value      = 'Non-Academic-Student';
        break;
    }

    case 'Others-COOP': {
        $scope_details    = ['type' => 'coop'];
        $assigned_scope   = 'COOP';
        $assigned_scope_1 = 'COOP Admin';
        $scope_value      = 'Others-COOP';
        break;
    }

    case 'Others-Default': {
        $scope_details    = ['type' => 'default'];
        $assigned_scope   = 'Default';
        $assigned_scope_1 = 'Default Admin';
        $scope_value      = 'Others-Default';
        break;
    }

    case 'Special-Scope': {
        $scope_details    = ['type' => 'csg'];
        $assigned_scope   = 'CSG Admin';
        $assigned_scope_1 = 'CSG Admin';
        $scope_value      = 'Special-Scope';
        break;
    }

    default:
        sendJsonResponse('error', 'Invalid scope category.');
}

// Check if there's already an active admin with the same credentials
if ($admin_status === 'active') {
    $conditions = [];
    $params     = [];

    if (!empty($scope_category)) {
        $conditions[]              = "scope_category = :scope_category";
        $params[':scope_category'] = $scope_category;
    }

    if (!empty($assigned_scope)) {
        $conditions[]             = "assigned_scope = :assigned_scope";
        $params[':assigned_scope']= $assigned_scope;
    }

    if (!empty($assigned_scope_1)) {
        $conditions[]               = "assigned_scope_1 = :assigned_scope_1";
        $params[':assigned_scope_1']= $assigned_scope_1;
    }

    if (!empty($conditions)) {
        $whereClause = implode(' AND ', $conditions);
        $checkSql    = "SELECT user_id, first_name, last_name, admin_title
                        FROM users
                        WHERE role = 'admin' AND admin_status = 'active'
                          AND $whereClause";

        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute($params);
        $existingAdmin = $checkStmt->fetch();

        if ($existingAdmin) {
            sendJsonResponse(
                'error',
                "Cannot create admin with active status. There is already an active admin " .
                "({$existingAdmin['admin_title']}: {$existingAdmin['first_name']} {$existingAdmin['last_name']}) " .
                "with the same scope credentials. Only one admin can be active for the same scope."
            );
        }
    }
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert admin into database
try {
    $pdo->beginTransaction();

    // Insert into users table
    $stmt = $pdo->prepare("INSERT INTO users 
        (admin_title, first_name, last_name, email, password, role, is_verified, is_admin, force_password_change,
         assigned_scope, scope_category, assigned_scope_1, admin_status, academic_year)
        VALUES (:admin_title, :first, :last, :email, :pw, 'admin', 1, 1, 1,
                :assigned_scope, :scope_category, :assigned_scope_1, :admin_status, :academic_year)");

    $stmt->execute([
        ':admin_title'      => $admin_title,
        ':first'            => $first_name,
        ':last'             => $last_name,
        ':email'            => $email,
        ':pw'               => $hashed_password,
        ':assigned_scope'   => $assigned_scope,
        ':scope_category'   => $scope_category,
        ':assigned_scope_1' => $assigned_scope_1,
        ':admin_status'     => $admin_status,
        ':academic_year'    => $academicYear
    ]);

    $user_id = $pdo->lastInsertId();

    // Insert into admin_scopes table (with scope_value)
    $stmt = $pdo->prepare("INSERT INTO admin_scopes 
        (user_id, scope_type, scope_value, scope_details)
        VALUES (:user_id, :scope_type, :scope_value, :scope_details)");

    $stmt->execute([
        ':user_id'       => $user_id,
        ':scope_type'    => $scope_category,
        ':scope_value'   => $scope_value,
        ':scope_details' => json_encode($scope_details)
    ]);

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Admin creation failed: " . $e->getMessage());
    sendJsonResponse('error', 'Failed to create admin.');
}

// Prepare email content based on scope category
$scope_description = formatScopeDetails($scope_category, json_encode($scope_details));

// Email admin credentials
$mail = new PHPMailer(true);
try {
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
    $mail->Subject = 'Your eBalota Admin Account Has Been Created';

    $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>eBalota Admin Account</title>
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
                    <h1>Welcome to eBalota System!</h1>
                    <p>Your Admin Account Has Been Created</p>
                </div>
                
                <div class='content'>
                    <p class='greeting'>Dear <strong>$admin_title</strong>,</p>
                    
                    <p>We're pleased to inform you that your administrator account has been successfully created in the eBalota System. Below are your account details:</p>
                    
                    <div class='info-box'>
                        <h3>Account Information</h3>
                        <p><strong>Scope Category:</strong> " . getScopeCategoryLabel($scope_category) . "</p>
                        <p><strong>Scope Details:</strong> $scope_description</p>
                        <p><strong>Status:</strong> " . ucfirst($admin_status) . "</p>
                        <p><strong>Academic Year:</strong> $academicYear</p>
                    </div>
                    
                    <div class='credentials-box'>
                        <h3>Your Login Credentials</h3>
                        <div class='credential-item'>
                            <span class='credential-label'>Email Address:</span>
                            <span class='credential-value'>$email</span>
                        </div>
                        <div class='credential-item'>
                            <span class='credential-label'>Password:</span>
                            <span class='credential-value'>$password</span>
                        </div>
                    </div>
                    
                    <div class='reminder'>
                        <strong>Security Reminder:</strong> Please change your password upon first login for security reasons.
                    </div>
                    
                    <div class='button-container'>
                        <a href='http://localhost/ebalota/login.php' class='login-button'>
                            <span>Login to Your Account</span>
                        </a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>If you did not request this account, please contact your system administrator immediately.</p>
                    <p>© " . date('Y') . " eBalota System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    $mail->AltBody = "Welcome $admin_title! You have been added as an Admin with scope: $scope_description. Your login credentials: Email: $email | Password: $password. Please change your password upon first login for security reasons.";

    $mail->send();

    error_log("Admin created successfully: $email with scope $scope_category and status $admin_status");

    sendJsonResponse('success', 'Admin created successfully and credentials sent to email.', [
        'admin_title'       => $admin_title,
        'scope_category'    => $scope_category,
        'scope_description' => $scope_description,
        'admin_status'      => $admin_status,
        'academic_year'     => $academicYear
    ]);

} catch (Exception $e) {
    error_log("Email error: " . $mail->ErrorInfo);

    // Send success response even if email fails (admin was still created)
    sendJsonResponse('success', 'Admin created successfully, but email sending failed.', [
        'admin_title'       => $admin_title,
        'scope_category'    => $scope_category,
        'scope_description' => $scope_description,
        'admin_status'      => $admin_status,
        'academic_year'     => $academicYear
    ]);
}

// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}
?>
