<?php
session_start();
date_default_timezone_set('Asia/Manila');

/* ==========================================================
   DB CONNECTION
   ========================================================== */
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
    error_log("Database connection failed in release_results.php: " . $e->getMessage());
    $_SESSION['toast_message'] = 'System error: could not connect to database.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_view_elections.php');
    exit();
}

/* ==========================================================
   MAILER + LOG HELPERS
   ========================================================== */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

/**
 * Log lifetime actions into user_lifetime_logs.
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
        error_log('user_lifetime_logs insert failed (auto_missed): ' . $e->getMessage());
    }
}

/**
 * Send auto-deactivation email for MISSED_ELECTIONS.
 */
function sendAutoMissedDeactivationEmail(array $user, string $reasonTextForEmail): bool
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
                <p>Your voting account in <strong>eBalota</strong> has been <strong>automatically deactivated</strong> by the system.</p>
                <p><strong>Reason:</strong> {$reasonEsc}</p>
                <p>This rule is applied when a voter does not participate in two consecutive elections where they are eligible to vote.</p>
                <p>If you believe this is a mistake or you need further clarification, please contact your college/CSG/OSAS office.</p>
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
        $mail->AltBody = "Your eBalota voting account has been automatically deactivated. Reason: {$reasonTextForEmail}. If this seems incorrect, please contact your college/CSG/OSAS office.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Auto MISSED_ELECTIONS deactivation email error: ' . $mail->ErrorInfo);
        return false;
    }
}

/* ==========================================================
   AUTH CHECK
   ========================================================== */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Get user role
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

$role = $userInfo['role'] ?? '';

if (!in_array($role, ['admin', 'super_admin'], true)) {
    $_SESSION['toast_message'] = 'You are not authorized to release election results.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_view_elections.php');
    exit();
}

/* ==========================================================
   GET ELECTION
   ========================================================== */
$electionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($electionId <= 0) {
    $_SESSION['toast_message'] = 'Invalid election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_view_elections.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$electionId]);
$election = $stmt->fetch();

if (!$election) {
    $_SESSION['toast_message'] = 'Election not found.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_view_elections.php');
    exit();
}

/* ==========================================================
   SCOPE CHECK (ADMIN ONLY)
   ========================================================== */
require_once __DIR__ . '/includes/election_scope_helpers.php';
require_once __DIR__ . '/includes/analytics_scopes.php'; // for SCOPE_* + getScopedVoters

if ($role === 'admin') {
    $visibleElections = fetchScopedElections($pdo, $userId);
    $visibleIds       = array_map('intval', array_column($visibleElections, 'election_id'));

    if (!in_array($electionId, $visibleIds, true)) {
        $_SESSION['toast_message'] = 'You are not allowed to manage this election.';
        $_SESSION['toast_type']    = 'error';
        header('Location: admin_view_elections.php');
        exit();
    }
}

/* ==========================================================
   CHECK STATUS (MUST BE COMPLETED)
   ========================================================== */
$now   = new DateTime();
$start = new DateTime($election['start_datetime']);
$end   = new DateTime($election['end_datetime']);

$status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

if ($status !== 'completed') {
    $_SESSION['toast_message'] = 'You can only release results for completed elections.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_view_elections.php');
    exit();
}

/* ==========================================================
   CHECK IF ALREADY RELEASED
   ========================================================== */
$alreadyReleased = !empty($election['results_released']);

if ($alreadyReleased) {
    $_SESSION['toast_message'] = 'Results for this election have already been released.';
    $_SESSION['toast_type']    = 'info'; 
    header('Location: admin_view_elections.php');
    exit();
}

/* ==========================================================
   RELEASE RESULTS (FLAG IN DB) + ELECTION-AWARE MISSED LOGIC
   ========================================================== */
try {
    // 1) Flag results as released
    $stmt = $pdo->prepare("
        UPDATE elections
        SET results_released   = 1,
            results_released_at = NOW()
        WHERE election_id = ?
    ");
    $stmt->execute([$electionId]);

    // 2) Activity log
    try {
        $stmtLog = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");

        $electionTitle = $election['title'] ?? 'Untitled Election';
        $actionText    = 'Released results for election: ' . $electionTitle .
                        ' (ID: ' . $electionId . ')';

        $stmtLog->execute([
            ':uid'    => $userId,
            ':action' => $actionText,
        ]);
    } catch (\Exception $e) {
        error_log('Failed to log activity in release_results.php: ' . $e->getMessage());
    }

    // 3) ELECTION-AWARE AUTO UPDATE: 2 consecutive missed elections rule
    try {
        $electionScopeType = $election['election_scope_type'] ?? '';
        $ownerScopeId      = $election['owner_scope_id']      ?? null;
        $electionEnd       = $election['end_datetime']        ?? null;

        $scopeTypeConst = null;

        // Map DB scope type to SCOPE_* constants used in analytics_scopes.php
        switch ($electionScopeType) {
            case 'Academic-Student':
                $scopeTypeConst = SCOPE_ACAD_STUDENT;
                break;
            case 'Academic-Faculty':
                $scopeTypeConst = SCOPE_ACAD_FACULTY;
                break;
            case 'Non-Academic-Student':
                $scopeTypeConst = SCOPE_NONACAD_STUDENT;
                break;
            case 'Non-Academic-Employee':
                $scopeTypeConst = SCOPE_NONACAD_EMPLOYEE;
                break;
            case 'Others':
                $scopeTypeConst = SCOPE_OTHERS;
                break;
            case 'Special-Scope':
                $scopeTypeConst = SCOPE_SPECIAL_CSG;
                break;
            default:
                $scopeTypeConst = null; // unknown / legacy
        }

        $eligibleUsers = [];

        if ($scopeTypeConst !== null && $ownerScopeId !== null) {
            // Election-aware: only voters in this election's scope seat
            $eligibleUsers = getScopedVoters(
                $pdo,
                $scopeTypeConst,
                (int)$ownerScopeId,
                [
                    'year_end'      => $electionEnd,
                    'include_flags' => true,
                ]
            );
        } else {
            // Legacy fallback: all voters
            $eligibleStmt = $pdo->query("
                SELECT user_id, email, first_name, last_name, position, is_active, consecutive_missed_elections
                FROM users
                WHERE role = 'voter'
            ");
            $eligibleUsers = $eligibleStmt->fetchAll();
        }

        // Prepared statements for votes + updates
        $voteCheckStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM votes 
            WHERE election_id = :eid AND voter_id = :uid
        ");

        $resetMissedStmt = $pdo->prepare("
            UPDATE users
            SET consecutive_missed_elections = 0,
                last_voted_at = NOW()
            WHERE user_id = :uid
        ");

        $incMissedStmt = $pdo->prepare("
            UPDATE users
            SET consecutive_missed_elections = consecutive_missed_elections + 1
            WHERE user_id = :uid
        ");

        $userStateStmt = $pdo->prepare("
            SELECT is_active, consecutive_missed_elections
            FROM users
            WHERE user_id = :uid
        ");

        $deactivateStmt = $pdo->prepare("
            UPDATE users
            SET is_active = 0
            WHERE user_id = :uid
        ");

        foreach ($eligibleUsers as $u) {
            // When using getScopedVoters, we have: user_id, email, first_name, last_name, position, etc.
            // When using legacy fallback, we also have user_id, email, first_name, last_name, position, is_active, consecutive_missed_elections
            $uid = (int)$u['user_id'];

            // Check if this user voted in this election
            $voteCheckStmt->execute([
                ':eid' => $electionId,
                ':uid' => $uid,
            ]);
            $hasVoted = ((int)$voteCheckStmt->fetchColumn() > 0);

            if ($hasVoted) {
                // Reset missed count if they voted
                $resetMissedStmt->execute([':uid' => $uid]);
                continue;
            }

            // No vote -> increment missed counter
            // Need current is_active & missed count
            $userStateStmt->execute([':uid' => $uid]);
            $state = $userStateStmt->fetch();

            $isActive = isset($state['is_active']) ? (int)$state['is_active'] : 1;
            $before   = isset($state['consecutive_missed_elections'])
                ? (int)$state['consecutive_missed_elections']
                : 0;
            $after    = $before + 1;

            $incMissedStmt->execute([':uid' => $uid]);

            // If 2 or more consecutive misses AND currently active, auto deactivate
            if ($after >= 2 && $isActive === 1) {
                $deactivateStmt->execute([':uid' => $uid]);

                $reasonText = 'Account automatically deactivated after missing two consecutive elections.';

                // Log (auto_missed)
                logLifetimeAction(
                    $pdo,
                    $uid,
                    'DEACTIVATE',
                    'auto_missed',
                    'MISSED_ELECTIONS',
                    $reasonText,
                    $userId      // admin who released results
                );

                // For email, we need first_name/last_name/email; if not present (legacy fallback), fetch them
                $userForEmail = $u;
                if (!isset($u['email']) || !isset($u['first_name'])) {
                    $fetchUserStmt = $pdo->prepare("
                        SELECT first_name, last_name, email 
                        FROM users 
                        WHERE user_id = :uid
                    ");
                    $fetchUserStmt->execute([':uid' => $uid]);
                    $uf = $fetchUserStmt->fetch();
                    if ($uf) {
                        $userForEmail = array_merge($u, $uf);
                    }
                }

                sendAutoMissedDeactivationEmail($userForEmail, $reasonText);
            }
        }

    } catch (\Exception $e) {
        // Do not block result release if auto-missed logic fails
        error_log("Election-aware auto missed-elections deactivation failed for election_id {$electionId}: " . $e->getMessage());
    }

    $_SESSION['toast_message'] = 'Election results have been released. Eligible voters have been checked for missed elections.';
    $_SESSION['toast_type']    = 'success';

} catch (\PDOException $e) {
    error_log("Failed to release results for election_id $electionId: " . $e->getMessage());
    $_SESSION['toast_message'] = 'Error releasing election results. Please try again.';
    $_SESSION['toast_type']    = 'error';
}

/* ==========================================================
   REDIRECT BACK TO MANAGE ELECTIONS
   ========================================================== */
header('Location: admin_view_elections.php');
exit();
