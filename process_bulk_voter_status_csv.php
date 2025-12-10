<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'], true)) {
    header('Location: login.php');
    exit();
}

$adminId        = (int)$_SESSION['user_id'];
$adminRole      = $_SESSION['role'];
$assignedScope  = strtoupper(trim($_SESSION['assigned_scope']   ?? ''));
$scopeCategory  = $_SESSION['bulk_status_scope_category'] ?? ($_SESSION['scope_category'] ?? '');
$assignedScope1 = $_SESSION['assigned_scope_1'] ?? '';
$ownerScopeId   = $_SESSION['bulk_status_owner_scope_id'] ?? null;

// Load file + operation info from session
$filePath   = $_SESSION['bulk_status_file_path']   ?? '';
$operation  = $_SESSION['bulk_status_operation']   ?? '';
$reasonCode = $_SESSION['bulk_status_reason_code'] ?? null;
$reasonText = $_SESSION['bulk_status_reason_text'] ?? null;

// NEW: allowed course codes for Academic-Student bulk ops
$allowedCourseScopeCodes = $_SESSION['bulk_status_allowed_courses'] ?? [];

// Reset context to avoid re-processing on refresh
unset(
    $_SESSION['bulk_status_file_path'],
    $_SESSION['bulk_status_operation'],
    $_SESSION['bulk_status_reason_code'],
    $_SESSION['bulk_status_reason_text'],
    $_SESSION['bulk_status_scope_category'],
    $_SESSION['bulk_status_owner_scope_id'],
    $_SESSION['bulk_status_allowed_courses']
);

// Basic validation
if (!file_exists($filePath) || !is_readable($filePath)) {
    die("Bulk status CSV file not found or not readable.");
}
if (!in_array($operation, ['activate','deactivate'], true)) {
    die("Invalid operation type for bulk status.");
}
if ($operation === 'deactivate' && empty($reasonCode)) {
    die("Deactivation reason is required.");
}

// --- PHPMailer + logging helpers ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

// For mapCourseCodesToFullNames (reused from analytics_scopes)
require_once __DIR__ . '/includes/analytics_scopes.php';

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
            ':user_id'      => $userId,
            ':action'       => $action,
            ':trigger_type' => $triggerType,
            ':reason_code'  => $reasonCode,
            ':reason_text'  => $reasonText,
            ':admin_id'     => $adminId,
        ]);
    } catch (Exception $e) {
        error_log('user_lifetime_logs insert failed (bulk): ' . $e->getMessage());
    }
}

function sendBulkActivationEmail(array $user, bool $isStudent, ?string $newExpiry): bool
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
        $mail->Subject = 'Your eBalota voting account has been activated';

        $firstName = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');

        $expiryHtml = '';
        $expiryAlt  = '';
        if ($isStudent && $newExpiry !== null) {
            $expiryEsc  = htmlspecialchars($newExpiry, ENT_QUOTES, 'UTF-8');
            $expiryHtml = "<p><strong>New account expiry:</strong> {$expiryEsc}</p>";
            $expiryAlt  = " New account expiry: {$newExpiry}.";
        }

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0a5f2d;'>eBalota Account Activation</h2>
                <p>Dear {$firstName},</p>
                <p>Your voting account in <strong>eBalota</strong> has been <strong>activated or reactivated</strong> by your election administrator.</p>
                {$expiryHtml}
                <p style='margin: 20px 0;'>
                    <a href='{$loginUrl}' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #0a5f2d;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Go to Login</a>
                </p>
                <p>If you did not expect this change, please contact your college/CSG/OSAS office.</p>
                <p>Regards,<br>eBalota | Cavite State University</p>
            </div>
        ";
        $mail->AltBody = "Your eBalota voting account has been activated or reactivated by your election administrator."
                         . $expiryAlt . " You can log in at: {$loginUrl}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Bulk activation email error: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendBulkDeactivationEmail(array $user, string $reasonTextForEmail): bool
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
        $mail->Subject = 'Your eBalota voting account has been deactivated';

        $firstName = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
        $reasonEsc = htmlspecialchars($reasonTextForEmail, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #b91c1c;'>eBalota Account Deactivation</h2>
                <p>Dear {$firstName},</p>
                <p>Your voting account in <strong>eBalota</strong> has been <strong>deactivated</strong> by your election administrator.</p>
                <p><strong>Reason:</strong> {$reasonEsc}</p>
                <p>If you believe this is a mistake or need clarification, please contact your college/CSG/OSAS office.</p>
                <p>You may still open the login page for more information:</p>
                <p style='margin: 20px 0;'>
                    <a href='{$loginUrl}' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #6b7280;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Open eBalota</a>
                </p>
                <p>Regards,<br>eBalota | Cavite State University</p>
            </div>
        ";
        $mail->AltBody = "Your eBalota voting account has been deactivated. Reason: {$reasonTextForEmail}. "
                         . "If this seems incorrect, please contact your college/CSG/OSAS office.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Bulk deactivation email error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ---------------------------------------------------------------------
// Scope helper: build scope conditions similar to admin_manage_users.php
// ---------------------------------------------------------------------
function buildScopeConditionsForBulk(
    string $adminRole,
    string $scopeCategory,
    string $assignedScope,
    string $assignedScope1,
    ?int $ownerScopeId
): array {
    $conds  = ["role = 'voter'"];
    $params = [];

    // super_admin: no extra scope filters
    if ($adminRole === 'super_admin') {
        return [$conds, $params];
    }

    // Non-Academic-Student (org-based students)
    if ($scopeCategory === 'Non-Academic-Student' && $ownerScopeId !== null) {
        $conds[]                = "position = 'student'";
        $conds[]                = "owner_scope_id = :ownerScopeId";
        $params[':ownerScopeId'] = $ownerScopeId;
        return [$conds, $params];
    }

    // Others
    if ($scopeCategory === 'Others' && $ownerScopeId !== null) {
        $conds[]                = "owner_scope_id = :ownerScopeId";
        $conds[]                = "is_other_member = 1";
        $params[':ownerScopeId'] = $ownerScopeId;
        return [$conds, $params];
    }

    // Academic-Student (college student admins / CSG college)
    if ($scopeCategory === 'Academic-Student' && in_array($assignedScope, [
        'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
        'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
    ], true)) {
        $conds[]                 = "position = 'student'";
        $conds[]                 = "UPPER(TRIM(department)) = :scopeCollege";
        $params[':scopeCollege'] = $assignedScope;
        return [$conds, $params];
    }

    // Special-Scope (CSG) - global students
    if ($scopeCategory === 'Special-Scope' || $assignedScope === 'CSG ADMIN') {
        $conds[] = "position = 'student'";
        return [$conds, $params];
    }

    // Academic-Faculty
    if ($scopeCategory === 'Academic-Faculty' && in_array($assignedScope, [
        'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
        'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
    ], true)) {
        $conds[]                 = "position = 'academic'";
        $conds[]                 = "UPPER(TRIM(department)) = :scopeCollege";
        $params[':scopeCollege'] = $assignedScope;

        if (!empty($assignedScope1) && strcasecmp($assignedScope1, 'All') !== 0) {
            $conds[]               = "department1 = :scopeDept";
            $params[':scopeDept']  = $assignedScope1; // we can refine if needed
        }
        return [$conds, $params];
    }

    // Non-Academic-Employee / NON-ACADEMIC
    if ($scopeCategory === 'Non-Academic-Employee' || $assignedScope === 'NON-ACADEMIC') {
        $conds[] = "position = 'non-academic'";
        return [$conds, $params];
    }

    // default generic admin: role=voter only
    return [$conds, $params];
}

// ---------------------------------------------------------------------
// Process CSV
// ---------------------------------------------------------------------
$totalRows        = 0;
$processed        = 0;
$activated        = 0;
$deactivated      = 0;
$notFound         = 0;
$outOfScope       = 0;
$positionMismatch = 0;
$emailInvalid     = 0;

$handle = fopen($filePath, 'r');
if ($handle === false) {
    die("Unable to open CSV file.");
}

// Read header
$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    die("CSV file is empty.");
}

// Map header columns
$headerMap = [];
foreach ($header as $idx => $colName) {
    $col = strtolower(trim($colName));
    $headerMap[$col] = $idx;
}

if (!isset($headerMap['email']) || !isset($headerMap['position'])) {
    fclose($handle);
    die("CSV must have columns: email, position (in any order).");
}

// If admin is Academic-Student and we have course scope, pre-map them to full names once
$fullAllowedCourseNames = [];
if ($scopeCategory === 'Academic-Student' && !empty($allowedCourseScopeCodes)) {
    // uses mapCourseCodesToFullNames from analytics_scopes.php
    $fullAllowedCourseNames = mapCourseCodesToFullNames($allowedCourseScopeCodes);
}

// Process rows
while (($row = fgetcsv($handle)) !== false) {
    $totalRows++;

    $emailIdx    = $headerMap['email'];
    $positionIdx = $headerMap['position'];

    $email    = isset($row[$emailIdx])    ? trim($row[$emailIdx])    : '';
    $position = isset($row[$positionIdx]) ? trim(strtolower($row[$positionIdx])) : '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailInvalid++;
        continue;
    }
    if (!in_array($position, ['student','academic','non-academic'], true)) {
        $positionMismatch++;
        continue;
    }

    // Build scope-aware query (WITHOUT email)
    list($conds, $params) = buildScopeConditionsForBulk(
        $adminRole,
        $scopeCategory,
        $assignedScope,
        $assignedScope1,
        $ownerScopeId ? (int)$ownerScopeId : null
    );
    // Add email condition exactly once
    $conds[]          = "email = :email";
    $params[':email'] = $email;

    $sql = "
        SELECT 
            user_id, email, first_name, last_name, position, 
            is_active, account_expires_at, reactivation_count, 
            consecutive_missed_elections, course
        FROM users
        WHERE " . implode(' AND ', $conds) . "
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch();

    if (!$user) {
        // Could be not existing at all or out-of-scope; we treat as outOfScope to be safe
        $outOfScope++;
        continue;
    }

    // Check position match with DB
    $dbPosition = strtolower($user['position'] ?? '');
    if ($dbPosition !== $position) {
        $positionMismatch++;
        continue;
    }

    // EXTRA SCOPE: Academic-Student course-level restriction (like process_users_csv)
    if ($scopeCategory === 'Academic-Student' && !empty($fullAllowedCourseNames)) {
        $userCourse = $user['course'] ?? '';
        if ($userCourse === '' || !in_array($userCourse, $fullAllowedCourseNames, true)) {
            // valid student + college, but course not in admin scope
            $outOfScope++;
            continue;
        }
    }

    $processed++;

    $isStudent = ($dbPosition === 'student');
    $userId    = (int)$user['user_id'];

    if ($operation === 'activate') {
        // Activation / reactivation
        if ($isStudent) {
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

            sendBulkActivationEmail($user, true, $newExpiryStr);

            logLifetimeAction(
                $pdo,
                $userId,
                'REACTIVATE',
                'manual_csv',
                'BULK_ACTIVATE',
                'Account activated/reactivated via bulk CSV upload.',
                $adminId
            );
            $activated++;

        } else {
            // Academic / Non-academic / Others: just set is_active, reset missed elections
            $upd = $pdo->prepare("
                UPDATE users
                SET is_active = 1,
                    consecutive_missed_elections = 0
                WHERE user_id = :uid
            ");
            $upd->execute([':uid' => $userId]);

            sendBulkActivationEmail($user, false, null);

            logLifetimeAction(
                $pdo,
                $userId,
                'REACTIVATE',
                'manual_csv',
                'BULK_ACTIVATE',
                'Account activated/reactivated via bulk CSV upload.',
                $adminId
            );
            $activated++;
        }
    } elseif ($operation === 'deactivate') {
        // Deactivation
        $upd = $pdo->prepare("
            UPDATE users
            SET is_active = 0
            WHERE user_id = :uid
        ");
        $upd->execute([':uid' => $userId]);

        $finalReasonText = $reasonText;
        if ($finalReasonText === '' && $reasonCode !== null) {
            switch ($reasonCode) {
                case 'GRADUATED':
                    $finalReasonText = 'Graduated or no longer enrolled.';
                    break;
                case 'TRANSFERRED':
                    $finalReasonText = 'Transferred to another campus or institution.';
                    break;
                case 'DUPLICATE_ACCOUNT':
                    $finalReasonText = 'Duplicate or incorrect account record.';
                    break;
                case 'VIOLATION_TOS':
                    $finalReasonText = 'Violation of the system’s Terms of Service or misuse of the voting system.';
                    break;
                case 'DISCIPLINARY_ACTION':
                    $finalReasonText = 'Deactivation following a disciplinary or conduct-related decision.';
                    break;
                case 'DATA_CORRECTION':
                    $finalReasonText = 'Account deactivated as part of a data correction or migration.';
                    break;
                default:
                    $finalReasonText = 'Administrative decision by the election administrator.';
            }
        } elseif ($finalReasonText === '') {
            $finalReasonText = 'Administrative decision by the election administrator.';
        }

        sendBulkDeactivationEmail($user, $finalReasonText);

        logLifetimeAction(
            $pdo,
            $userId,
            'DEACTIVATE',
            'manual_csv',
            $reasonCode,
            $finalReasonText,
            $adminId
        );
        $deactivated++;
    }
}

fclose($handle);

// Optionally, delete file
@unlink($filePath);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bulk Account Status Result</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <div class="max-w-3xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold mb-4 text-gray-800">
      Bulk Account Status Result
    </h1>
    <p class="mb-4 text-sm text-gray-600">
      Operation: <strong><?= htmlspecialchars($operation === 'activate' ? 'Activate / Reactivate' : 'Deactivate') ?></strong>
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div class="bg-blue-50 border border-blue-200 rounded p-4 text-sm">
        <h2 class="font-semibold text-blue-800 mb-2">Summary</h2>
        <ul class="space-y-1 text-blue-900">
          <li>Total rows in CSV: <strong><?= (int)$totalRows ?></strong></li>
          <li>Processed (within scope + valid): <strong><?= (int)$processed ?></strong></li>
          <?php if ($operation === 'activate'): ?>
            <li>Activated / reactivated: <strong><?= (int)$activated ?></strong></li>
          <?php else: ?>
            <li>Deactivated: <strong><?= (int)$deactivated ?></strong></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="bg-yellow-50 border border-yellow-200 rounded p-4 text-sm">
        <h2 class="font-semibold text-yellow-800 mb-2">Skipped / Issues</h2>
        <ul class="space-y-1 text-yellow-900">
          <li>Invalid / missing email: <strong><?= (int)$emailInvalid ?></strong></li>
          <li>Position mismatch or invalid position: <strong><?= (int)$positionMismatch ?></strong></li>
          <li>Not found / out-of-scope: <strong><?= (int)$outOfScope ?></strong></li>
        </ul>
      </div>
    </div>

    <div class="flex justify-end space-x-3">
      <a href="admin_bulk_voter_status.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
        Back to Bulk Account Status
      </a>
      <a href="admin_manage_users.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
        Back to Users
      </a>
    </div>
  </div>
</body>
</html>
