<?php
session_start();
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$host = 'localhost';
$db = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

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

// Simple activity logger
function logActivity(PDO $pdo, int $userId, string $action): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
        ]);
    } catch (\PDOException $e) {
        // Huwag i-echo sa user, log lang
        error_log('Activity log insert failed: ' . $e->getMessage());
    }
}

/**
 * Log lifetime events into user_lifetime_logs for AUTO DURATION rule.
 */
function logLifetimeDurationDeactivation(PDO $pdo, int $userId, string $reasonText): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_lifetime_logs
                (user_id, action, trigger_type, reason_code, reason_text, admin_id, created_at)
            VALUES
                (:user_id, :action, :trigger_type, :reason_code, :reason_text, :admin_id, NOW())
        ");
        $stmt->execute([
            ':user_id'      => $userId,
            ':action'       => 'DEACTIVATE',
            ':trigger_type' => 'auto_duration',
            ':reason_code'  => 'DURATION_EXPIRED',
            ':reason_text'  => $reasonText,
            ':admin_id'     => null,   // system-triggered (no specific admin)
        ]);
    } catch (\Exception $e) {
        error_log('user_lifetime_logs insert failed (auto_duration): ' . $e->getMessage());
    }
}

/**
 * Send email for student account expiry (duration rule).
 */
function sendStudentExpiryEmail(array $user, string $expiryDateStr): bool
{
    $mail = new PHPMailer(true);
    try {
        $loginUrl = "http://localhost/ebalota/login.html";

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'krpmab@gmail.com';
        $mail->Password   = 'ghdumnwrjbphujbs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota | Cavite State University');
        $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Your eBalota student voting account has expired';

        $firstName = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
        $expiryEsc = htmlspecialchars($expiryDateStr, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0a5f2d;'>eBalota Account Expiry</h2>
                <p>Dear {$firstName},</p>
                <p>Your <strong>student voting account</strong> in eBalota has <strong>expired</strong> based on the duration assigned to your year level.</p>
                <p><strong>Expiry date recorded in the system:</strong> {$expiryEsc}</p>
                <p>After this date, your account is automatically deactivated and you will no longer be able to log in or vote as a student.</p>
                <p>If you believe you should still be an eligible student voter (e.g. extended enrollment or special cases), please contact your college/CSG/OSAS office to request reactivation.</p>
                <p style='margin: 20px 0;'>
                    <a href='{$loginUrl}' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #0a5f2d;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Open eBalota</a>
                </p>
                <p>Regards,<br>eBalota | Cavite State University</p>
            </div>
        ";
        $mail->AltBody = "Your eBalota student voting account has expired as of {$expiryDateStr}. If you believe this is incorrect, please contact your college/CSG/OSAS office.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Student expiry email error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Function to send super admin verification email
function sendSuperAdminVerificationEmail($email, $first_name, $last_name, $token) {
    error_log("[EMAIL DEBUG] sendSuperAdminVerificationEmail() CALLED for $email at " . date('Y-m-d H:i:s'));
    $mail = new PHPMailer(true);
    $verificationUrl = "http://localhost/ebalota/super_admin_verify.php?token=$token";

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'krpmab@gmail.com';
        $mail->Password = 'ghdumnwrjbphujbs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota System');
        $mail->addAddress($email, "$first_name $last_name");

        $mail->isHTML(true);
        $mail->Subject = 'Super Admin Login Verification';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #aa1e1e;'>Super Admin Login Verification</h2>
                <p>Hello $first_name,</p>
                <p>To complete your <strong>Super Admin</strong> login, please click the link below:</p>
                <p>
                    <a href='$verificationUrl' style='
                        display: inline-block;
                        padding: 12px 24px;
                        background-color: #aa1e1e;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Verify Super Admin Login</a>
                </p>
                <p>This link will expire in 1 hour.</p>
            </div>
        ";
        $mail->AltBody = "Verify your Super Admin login: $verificationUrl";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send admin verification email
function sendAdminVerificationEmail($email, $first_name, $last_name, $token) {
    error_log("[EMAIL DEBUG] sendAdminVerificationEmail() CALLED for $email at " . date('Y-m-d H:i:s'));
    $mail = new PHPMailer(true);
    $verificationUrl = "http://localhost/ebalota/admin_verify_token.php?token=$token";

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'krpmab@gmail.com';
        $mail->Password = 'ghdumnwrjbphujbs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota');
        $mail->addAddress($email, "$first_name $last_name");

        $mail->isHTML(true);
        $mail->Subject = 'Admin Login Verification';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0a5f2d;'>Admin Login Verification</h2>
                <p>Hello $first_name,</p>
                <p>Click the button below to complete your admin login:</p>
                <p style='margin: 25px 0;'>
                    <a href='$verificationUrl' style='
                        display: inline-block;
                        padding: 12px 24px;
                        background-color: #0a5f2d;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Verify Admin Login</a>
                </p>
                <p>This link expires in 1 hour.</p>
            </div>
        ";
        $mail->AltBody = "Verify your admin login: $verificationUrl";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Basic validations
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: login.html?error=Invalid email format.&email=" . urlencode($email));
        exit;
    }

    if (empty($password)) {
        header("Location: login.html?error=Password required.&email=" . urlencode($email));
        exit;
    }

    try {
        // Updated query - includes cooldown fields
        $stmt = $pdo->prepare("SELECT 
                user_id, first_name, last_name, email, password, role, admin_title,
                position, is_coop_member, department, course, status, 
                assigned_scope, scope_category, assigned_scope_1, admin_status,
                owner_scope_id, is_other_member,
                profile_picture,
                year_level_at_registration,
                account_expires_at,
                is_active,
                failed_attempts,
                last_failed_login
            FROM users 
            WHERE email = ?");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            header("Location: login.html?error=Email does not exist.&email=" . urlencode($email));
            exit;
        }

        $failedAttempts = (int)($user['failed_attempts'] ?? 0);
        $lastFailedStr  = $user['last_failed_login'] ?? null;

        // 🔹 3-ATTEMPT / 3-MIN COOLDOWN CHECK (before password_verify)
        try {
            if ($failedAttempts >= 3 && !empty($lastFailedStr)) {
                $now        = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $lastFailed = new DateTime($lastFailedStr, new DateTimeZone('Asia/Manila'));
                $diffSeconds = $now->getTimestamp() - $lastFailed->getTimestamp();

                // 180 seconds = 3 minutes
                if ($diffSeconds < 180) {
                    $lockUntilTs = $lastFailed->getTimestamp() + 180;

                    $msg = "Too many failed attempts. Please wait a few minutes before trying again, or just use the Forgot Password link to reset your password.";

                    header(
                        "Location: login.html?error=" . urlencode($msg) .
                        "&lock_until=" . $lockUntilTs .
                        "&email=" . urlencode($email)
                    );
                    exit;
                } else {
                    // Lumipas na ang cooldown → reset counter
                    $failedAttempts = 0;
                    $resetStmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, last_failed_login = NULL WHERE user_id = ?");
                    $resetStmt->execute([$user['user_id']]);
                }
            }
        } catch (\Exception $e) {
            error_log('Cooldown date error: ' . $e->getMessage());
        }

        // 🔹 PASSWORD CHECK + ATTEMPT INCREMENT
        if (!password_verify($password, $user['password'])) {
            $failedAttemptsBefore = (int)($failedAttempts ?? 0);
            $failedAttemptsAfter = $failedAttemptsBefore + 1;

            try {
                $upd = $pdo->prepare("UPDATE users SET failed_attempts = ?, last_failed_login = NOW() WHERE user_id = ?");
                $upd->execute([$failedAttemptsAfter, $user['user_id']]);
            } catch (\PDOException $e) {
                error_log('Failed to update failed_attempts: ' . $e->getMessage());
            }

            if ($failedAttemptsAfter >= 3) {
                $lockUntilTs = time() + 180;
                $msg = "Too many failed attempts. Login is locked for 3 minutes. You can also use the Forgot Password link to reset your password.";

                header(
                    "Location: login.html?error=" . urlencode($msg) .
                    "&lock_until=" . $lockUntilTs .
                    "&email=" . urlencode($email)
                );
                exit;
            }

            $attemptsLeft = 3 - $failedAttemptsAfter; // 2 or 1
            $msg = "Incorrect password. You have {$attemptsLeft} attempt(s) remaining before login is locked for 3 minutes.";

            header("Location: login.html?error=" . urlencode($msg) . "&email=" . urlencode($email));
            exit;
        }

        // 🔹 PASSWORD OK – RESET FAILED ATTEMPTS
        try {
            $reset = $pdo->prepare("UPDATE users SET failed_attempts = 0, last_failed_login = NULL WHERE user_id = ?");
            $reset->execute([$user['user_id']]);
        } catch (\PDOException $e) {
            error_log("Failed to reset failed_attempts: " . $e->getMessage());
        }

        // 🔹 STUDENT ACCOUNT LIFETIME CHECKS (DURATION RULE)
        if ($user['position'] === 'student') {

            // 1. If already inactive, respect that (could be auto_missed, manual, or duration)
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                header("Location: login.html?error=" . urlencode("Your student voting account is deactivated. Please contact the election administrator for reactivation.") . "&email=" . urlencode($email));
                exit;
            }

            // 2. Check if account has expired based on account_expires_at
            if (!empty($user['account_expires_at'])) {
                try {
                    $now    = new DateTime('now', new DateTimeZone('Asia/Manila'));
                    $expiry = new DateTime($user['account_expires_at'], new DateTimeZone('Asia/Manila'));

                    if ($now > $expiry) {
                        try {
                            $pdo->beginTransaction();

                            // Mark inactive
                            $upd = $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
                            $upd->execute([$user['user_id']]);

                            // Log lifetime deactivation (auto_duration)
                            $reasonText = 'Student voting account expired based on the assigned year-level duration rule.';
                            logLifetimeDurationDeactivation($pdo, (int)$user['user_id'], $reasonText);

                            $pdo->commit();
                        } catch (\PDOException $e) {
                            $pdo->rollBack();
                            error_log('Failed to auto-deactivate expired student (login): ' . $e->getMessage());
                        }

                        // Send email notification (best-effort; errors are logged only)
                        try {
                            sendStudentExpiryEmail($user, $user['account_expires_at']);
                        } catch (\Exception $e) {
                            error_log('Failed to send student expiry email: ' . $e->getMessage());
                        }

                        header("Location: login.html?error=" . urlencode("Your student voting account has expired. Please contact the election administrator for reactivation.") . "&email=" . urlencode($email));
                        exit;
                    }
                } catch (\Exception $e) {
                    error_log('Student expiry date parse error: ' . $e->getMessage());
                }
            }
        }

        // Check if admin account is inactive
        if ($user['role'] === 'admin' && $user['admin_status'] === 'inactive') {
            header("Location: login.html?error=Your admin account is inactive. Please contact super admin.&email=" . urlencode($email));
            exit;
        }

        // Admin/super_admin require email verification
        if (in_array($user['role'], ['super_admin', 'admin'])) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("INSERT INTO admin_login_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $token, $expires]);

            $success = false;
            if ($user['role'] === 'super_admin') {
                $success = sendSuperAdminVerificationEmail($user['email'], $user['first_name'], $user['last_name'], $token);
            } elseif ($user['role'] === 'admin') {
                $success = sendAdminVerificationEmail($user['email'], $user['first_name'], $user['last_name'], $token);
            }
            
            if ($success) {
                // Store pending admin data with scope information
                $_SESSION['pending_admin_auth'] = $user['user_id'];
                $_SESSION['pending_auth_role']  = $user['role'];
                $_SESSION['pending_admin_title'] = $user['admin_title'] ?? null;
                
                if ($user['role'] === 'admin') {
                    $_SESSION['pending_scope_category'] = $user['scope_category'];
                    $_SESSION['pending_assigned_scope'] = $user['assigned_scope'];
                    $_SESSION['pending_assigned_scope_1'] = $user['assigned_scope_1'];
                    
                    try {
                        $scopeStmt = $pdo->prepare("SELECT scope_details FROM admin_scopes WHERE user_id = ?");
                        $scopeStmt->execute([$user['user_id']]);
                        $scopeData = $scopeStmt->fetch();
                        $_SESSION['pending_scope_details'] = !empty($scopeData['scope_details']) ? 
                            json_decode($scopeData['scope_details'], true) : [];
                    } catch (\PDOException $e) {
                        error_log("Error fetching scope details: " . $e->getMessage());
                        $_SESSION['pending_scope_details'] = [];
                    }
                    
                    $_SESSION['pending_admin_status'] = $user['admin_status'];
                }
                
                header("Location: admin_login_pending.php");
                exit;            
            } else {
                header("Location: login.html?error=Failed to send verification email.&email=" . urlencode($email));
                exit;
            }
        }

        // Normal user login (no extra email step)
        $_SESSION['user_id']    = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['role']       = $user['role'];

        $_SESSION['position']       = $user['position'];
        $_SESSION['is_coop_member'] = (bool)$user['is_coop_member']; // legacy / can be unused later
        $_SESSION['department']     = $user['department'] ?? '';
        $_SESSION['course']         = $user['course'] ?? '';
        $_SESSION['status']         = $user['status'] ?? '';

        // Others-related flags
        $_SESSION['is_other_member'] = (bool)$user['is_other_member'];
        $_SESSION['owner_scope_id']  = $user['owner_scope_id'];
        
        $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;

        logActivity(
            $pdo,
            (int)$user['user_id'],
            'User logged in (role: ' . $user['role'] . ')'
        );

        // Remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
            $stmt->execute([$token, $user['user_id']]);

            setcookie('remember_me', $token, [
                'expires'  => time() + (86400 * 30),
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }

        // Determine redirect URL based on role
        $redirectUrl = '';
        switch ($_SESSION['role']) {
            case 'super_admin':
                $redirectUrl = 'super_admin/dashboard.php';
                break;
            case 'admin':
                $redirectUrl = 'admin_dashboard_redirect.php';
                break;
            default:
                $redirectUrl = 'voters_dashboard.php';
        }

        // Redirect to login page with success parameter and redirect URL
        header("Location: login.html?success=true&redirect=" . urlencode($redirectUrl));
        exit;

    } catch (\PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header("Location: login.html?error=System error. Please try again.");
        exit;
    }

} else {
    header("Location: login.html");
    exit;
}
