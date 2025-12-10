<?php
/**
 * download_nonacad_report.php
 *
 * Entry point for downloading a Non-Academic Student (NAS) election report as PDF.
 * Works with includes/pdf/nonacad_election_report_pdf.php
 */

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
    error_log("Database connection failed (PDF NAS): " . $e->getMessage());
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

// Confirm user is admin/super_admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['admin','super_admin'], true)) {
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   FIND THIS ADMIN'S NON-ACADEMIC-STUDENT SCOPE SEAT
   ========================================================== */

$mySeat   = null;
$nasSeats = getScopeSeats($pdo, SCOPE_NONACAD_STUDENT);

foreach ($nasSeats as $seat) {
    if ((int)$seat['admin_user_id'] === $userId) {
        $mySeat = $seat;
        break;
    }
}

if (!$mySeat) {
    $_SESSION['toast_message'] = 'You are not assigned as a Non-Academic Student admin.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    return;
}

$scopeId = (int) $mySeat['scope_id'];

/* ==========================================================
   ELECTION SELECTION & SCOPE GUARD
   ========================================================== */

$electionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($electionId <= 0) {
    $_SESSION['toast_message'] = 'Invalid election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

// Make sure this election belongs to this NAS seat
$scopedElections    = getScopedElections($pdo, SCOPE_NONACAD_STUDENT, $scopeId);
$allowedElectionIds = array_map('intval', array_column($scopedElections, 'election_id'));

if (!in_array($electionId, $allowedElectionIds, true)) {
    $_SESSION['toast_message'] = 'You are not allowed to generate a report for this Non-Academic Student election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   ACTIVITY LOG — NON-ACADEMIC STUDENT REPORT GENERATION
   ========================================================== */
try {

    // Find election title for readable logging
    $electionTitle = '';
    foreach ($scopedElections as $el) {
        if ((int)$el['election_id'] === $electionId) {
            $electionTitle = $el['title'] ?? '';
            break;
        }
    }

    $actionText = 'Generated Non-Academic Student election report for election: ' .
                  ($electionTitle ?: 'Unknown Title') .
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
    error_log('[LOG ERROR] NAS Report Generation: ' . $e->getMessage());
}

/* ==========================================================
   GENERATE PDF
   ========================================================== */

require_once __DIR__ . '/includes/pdf/non_acad_students_election_report_pdf.php';

generateNonAcadElectionReportPDF($pdo, $electionId, $userId, $scopeId);

// TCPDF will output and exit.
