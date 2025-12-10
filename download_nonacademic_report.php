<?php
/**
 * download_nonacademic_report.php
 *
 * Entry point for downloading a Non-Academic-Employee election report as PDF.
 * Works with includes/pdf/nonacademic_election_report_pdf.php
 */
session_start();
date_default_timezone_set('Asia/Manila');

/* ==========================================================
   1. DATABASE CONNECTION
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
    error_log("Database connection failed (PDF non-acad): " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

/* ==========================================================
   2. SHARED HELPERS
   ========================================================== */
require_once __DIR__ . '/includes/analytics_scopes.php';
require_once __DIR__ . '/admin_functions.php';

/* ==========================================================
   3. AUTH CHECK
   ========================================================== */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();
$role = $userInfo['role'] ?? '';

if ($role !== 'admin') {
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   4. FIND THIS ADMIN'S NON-ACADEMIC-EMPLOYEE SCOPE SEAT
   ========================================================== */
$mySeat     = null;
$nonacSeats = getScopeSeats($pdo, SCOPE_NONACAD_EMPLOYEE);

foreach ($nonacSeats as $seat) {
    if ((int)$seat['admin_user_id'] === $userId) {
        $mySeat = $seat;
        break;
    }
}

if (!$mySeat) {
    $_SESSION['toast_message'] = 'You are not assigned as a Non-Academic-Employee admin.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

$scopeId = (int) $mySeat['scope_id']; // owner_scope_id for non-academic elections

/* ==========================================================
   5. ELECTION SELECTION & SCOPE GUARD
   ========================================================== */
$electionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($electionId <= 0) {
    $_SESSION['toast_message'] = 'Invalid election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

// Make sure this election belongs to this Non-Academic-Employee scope seat
$scopedElections    = getScopedElections($pdo, SCOPE_NONACAD_EMPLOYEE, $scopeId);
$allowedElectionIds = array_map('intval', array_column($scopedElections, 'election_id'));

if (!in_array($electionId, $allowedElectionIds, true)) {
    $_SESSION['toast_message'] = 'You are not allowed to generate a report for this non-academic election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   5.1 ACTIVITY LOG — NON-ACADEMIC-EMPLOYEE REPORT GENERATION
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

    $actionText = 'Generated Non-Academic Employee election report for election: ' .
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
    error_log('[LOG ERROR] NON-ACAD EMPLOYEE REPORT GENERATION: ' . $e->getMessage());
}


/* ==========================================================
   6. GENERATE PDF
   ========================================================== */
require_once __DIR__ . '/includes/pdf/nonacademic_election_report_pdf.php';

generateNonAcademicElectionReportPDF($pdo, $electionId, $userId, $scopeId);

// TCPDF will send the PDF and exit.
