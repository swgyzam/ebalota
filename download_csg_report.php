<?php
/**
 * download_csg_report.php
 *
 * Entry point for downloading a CSG election report as PDF.
 * Works together with includes/pdf/csg_election_report_pdf.php
 */

session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection (same as other admin pages) ---
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
    error_log("Database connection failed (PDF): " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Shared helpers ---
require_once __DIR__ . '/includes/analytics_scopes.php';
require_once __DIR__ . '/admin_functions.php';

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Confirm user is admin / super_admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();
$role     = $userInfo['role'] ?? '';

if ($role !== 'admin' && $role !== 'super_admin') {
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   ELECTION ID
   ========================================================== */

$electionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($electionId <= 0) {
    $_SESSION['toast_message'] = 'Invalid election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   LOAD ELECTION + BASIC GUARDS
   ========================================================== */

$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$electionId]);
$election = $stmt->fetch();

if (!$election) {
    $_SESSION['toast_message'] = 'Election not found.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

if (($election['election_scope_type'] ?? '') !== 'Special-Scope') {
    $_SESSION['toast_message'] = 'This election is not a CSG (Special-Scope) election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

$scopeId = (int)($election['owner_scope_id'] ?? 0);
if ($scopeId <= 0) {
    $_SESSION['toast_message'] = 'This CSG election is missing its scope assignment.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   PERMISSION CHECK
   - admin  -> must be the seat admin for this scope_id
   - super_admin -> allowed for any CSG election
   ========================================================== */

$mySeat   = null;
$csgSeats = getScopeSeats($pdo, SCOPE_SPECIAL_CSG);

if ($role === 'admin') {
    // admin must match BOTH admin_user_id and scope_id
    foreach ($csgSeats as $seat) {
        if ((int)$seat['scope_id'] === $scopeId && (int)$seat['admin_user_id'] === $userId) {
            $mySeat = $seat;
            break;
        }
    }

    if (!$mySeat) {
        $_SESSION['toast_message'] = 'You are not assigned as the CSG admin for this election.';
        $_SESSION['toast_type']    = 'error';
        header('Location: admin_analytics.php');
        exit();
    }
} else {
    // super_admin: find the seat only by scope_id (for nicer logging / signatory),
    // but do NOT block if we can't find it
    foreach ($csgSeats as $seat) {
        if ((int)$seat['scope_id'] === $scopeId) {
            $mySeat = $seat;
            break;
        }
    }
}

/* ==========================================================
   ACTIVITY LOG – REPORT GENERATION (CSG)
   ========================================================== */

try {
    $electionTitle = $election['title'] ?? 'Unknown Title';

    $actionText = 'Generated CSG election report for election: ' .
                  $electionTitle .
                  ' (ID: ' . $electionId . '), scope_id: ' . $scopeId;

    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, timestamp)
        VALUES (:uid, :action, NOW())
    ");
    $logStmt->execute([
        ':uid'    => $userId,
        ':action' => $actionText
    ]);

} catch (Exception $e) {
    // Silent fail so PDF still downloads
    error_log('[LOG ERROR] CSG Report Generation: ' . $e->getMessage());
}

/* ==========================================================
   GENERATE PDF
   ========================================================== */

require_once __DIR__ . '/includes/pdf/csg_election_report_pdf.php';

// Note: we pass $scopeId taken from the election (owner_scope_id)
// so both admins and super_admin can generate the correct report.
generateCSGElectionReportPDF($pdo, $electionId, $userId, $scopeId);

// No further output – TCPDF will send the PDF and exit.
