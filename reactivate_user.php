<?php
session_start();
date_default_timezone_set('Asia/Manila');

$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

// --- PHPMailer setup (same style as register.php / login.php) ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

/**
 * Log lifetime actions into user_lifetime_logs.
 *
 * @param PDO         $pdo
 * @param int         $userId
 * @param string      $action        'DEACTIVATE' | 'REACTIVATE'
 * @param string      $triggerType   'manual' | 'auto_duration' | 'auto_missed' | etc.
 * @param string|null $reasonCode
 * @param string|null $reasonText
 * @param int|null    $adminId
 */
function logLifetimeAction(
    PDO $pdo,
    int $userId,
    string $action,
    string $triggerType,
    ?string $reasonCode,
    ?string $reasonText,
    ?int $adminId
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_lifetime_logs
                (user_id, action, trigger_type, reason_code, reason_text, admin_id, created_at)
            VALUES
                (:user_id, :action, :trigger_type, :reason_code, :reason_text, :admin_id, NOW())
        ");
        $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':trigger_type'=> $triggerType,
            ':reason_code' => $reasonCode,
            ':reason_text' => $reasonText,
            ':admin_id'    => $adminId,
        ]);
    } catch (Exception $e) {
        // Huwag ihulog sa user, log lang sa server
        error_log('user_lifetime_logs insert failed: ' . $e->getMessage());
    }
}

// Helper: send reactivation email
function sendReactivationEmail(array $user, string $newExpiry): bool
{
    $mail = new PHPMailer(true);

    try {
        $verificationUrl = "http://localhost/ebalota/login.html";

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
        $mail->Subject = 'Your eBalota voting account has been reactivated';

        $firstName = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
        $expiryTxt = htmlspecialchars($newExpiry, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0a5f2d;'>eBalota Account Reactivation</h2>
                <p>Dear {$firstName},</p>
                <p>Your <strong>student voting account</strong> has been <strong>re-activated</strong> by the election administrator.</p>
                <p>You can now participate again in upcoming eBalota elections.</p>
                <p>
                    <strong>New account expiry:</strong><br>
                    {$expiryTxt}
                </p>
                <p style='margin: 25px 0;'>
                    <a href='{$verificationUrl}' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #0a5f2d;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Go to Login</a>
                </p>
                <p>If you did not request or expect this reactivation, please contact your college/CSG election office.</p>
                <p>Regards,<br>eBalota | Cavite State University</p>
            </div>
        ";
        $mail->AltBody = "Your eBalota student voting account has been reactivated. You can now log in at: http://localhost/ebalota/login.html";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Reactivation email error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Helper: send deactivation email
function sendDeactivationEmail(array $user, string $reasonTextForEmail): bool
{
    $mail = new PHPMailer(true);

    try {
        $helpUrl = "http://localhost/ebalota/login.html"; // or info page if meron ka

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
        $mail->Subject = 'Your eBalota voting account has been deactivated';

        $firstName = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
        $reasonEsc = htmlspecialchars($reasonTextForEmail, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #b91c1c;'>eBalota Account Deactivation</h2>
                <p>Dear {$firstName},</p>
                <p>Your <strong>student voting account</strong> in eBalota has been <strong>deactivated</strong> by the election administrator.</p>
                <p><strong>Reason:</strong> {$reasonEsc}</p>
                <p>
                    If you believe this is a mistake or if you need your account reviewed,
                    please contact your college election committee, CSG, or the OSAS office.
                </p>
                <p>You can still visit the login page for more information:</p>
                <p style='margin: 20px 0;'>
                    <a href='{$helpUrl}' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #6b7280;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Open eBalota Login</a>
                </p>
                <p>Regards,<br>eBalota | Cavite State University</p>
            </div>
        ";
        $mail->AltBody = "Your eBalota student voting account has been deactivated. Reason: {$reasonTextForEmail}. If you believe this is an error, please contact your college/CSG/OSAS.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Deactivation email error: ' . $mail->ErrorInfo);
        return false;
    }
}

// --- auth guard: only admin/super_admin ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if ($userId <= 0 || !in_array($action, ['reactivate', 'deactivate'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$currentAdminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// --- fetch target user (with email + names) ---
$stmt = $pdo->prepare("
    SELECT 
        user_id, position, is_active, account_expires_at, reactivation_count,
        first_name, last_name, email
    FROM users
    WHERE user_id = ? AND role = 'voter'
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

// lifetime rules: students only (for this endpoint)
if ($user['position'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Only student accounts can be managed via this action']);
    exit;
}

try {
    if ($action === 'reactivate') {
        $now       = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $expiresAt = (clone $now)->modify('+1 year');

        $newExpiryStr  = $expiresAt->format('Y-m-d H:i:s');
        $nowStr        = $now->format('Y-m-d H:i:s');
        $newReactCount = (int)$user['reactivation_count'] + 1;

        $upd = $pdo->prepare("
            UPDATE users
            SET is_active = 1,
                account_expires_at = :exp,
                reactivation_count = :rc,
                last_reactivated_at = :now,
                consecutive_missed_elections = 0
            WHERE user_id = :uid
        ");
        $upd->execute([
            ':exp' => $newExpiryStr,
            ':rc'  => $newReactCount,
            ':now' => $nowStr,
            ':uid' => $userId,
        ]);

        // Try to send email, but don't fail the action if email fails
        $emailOk = sendReactivationEmail($user, $newExpiryStr);

        // Log action (manual reactivation)
        logLifetimeAction(
            $pdo,
            $userId,
            'REACTIVATE',
            'manual',
            'MANUAL_REACTIVATE',
            'Account reactivated manually by admin.',
            $currentAdminId
        );

        $msg = 'Student account reactivated for +1 year.';
        if (!$emailOk) {
            $msg .= ' (Account updated, but email notification failed.)';
        }

        echo json_encode([
            'status'             => 'success',
            'message'            => $msg,
            'new_expiry'         => $newExpiryStr,
            'reactivation_count' => $newReactCount,
        ]);
        exit;

    } elseif ($action === 'deactivate') {
        $upd = $pdo->prepare("
            UPDATE users
            SET is_active = 0
            WHERE user_id = :uid
        ");
        $upd->execute([':uid' => $userId]);

        // For now, generic manual reason; later papalitan natin galing modal dropdown.
        $reasonCode = 'MANUAL_DEACTIVATE';
        $reasonText = 'Account manually deactivated by an administrator.';

        $emailOk = sendDeactivationEmail($user, $reasonText);

        // Log action (manual deactivation)
        logLifetimeAction(
            $pdo,
            $userId,
            'DEACTIVATE',
            'manual',
            $reasonCode,
            $reasonText,
            $currentAdminId
        );

        $msg = 'Student account deactivated.';
        if (!$emailOk) {
            $msg .= ' (Account updated, but email notification failed.)';
        }

        echo json_encode([
            'status'  => 'success',
            'message' => $msg,
        ]);
        exit;
    }

} catch (Exception $e) {
    error_log('reactivate_user.php error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Operation failed']);
    exit;
}
