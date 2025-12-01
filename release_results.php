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
    $_SESSION['toast_type']    = 'success';
    header('Location: admin_view_elections.php');
    exit();
}

/* ==========================================================
   RELEASE RESULTS (FLAG IN DB)
   ========================================================== */
try {
    $stmt = $pdo->prepare("
        UPDATE elections
        SET results_released   = 1,
            results_released_at = NOW()
        WHERE election_id = ?
    ");
    $stmt->execute([$electionId]);

    // Optional: activity log
    try {
        $stmtLog = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");

        $actionText = 'Released results for election ID ' . $electionId;
        if (!empty($election['title'])) {
            $actionText .= ' (' . $election['title'] . ')';
        }

        $stmtLog->execute([
            ':uid'    => $userId,
            ':action' => $actionText,
        ]);
    } catch (\Exception $e) {
        error_log('Failed to log activity in release_results.php: ' . $e->getMessage());
    }

    $_SESSION['toast_message'] = 'Election results have been released and are now visible to voters.';
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
