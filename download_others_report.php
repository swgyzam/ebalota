<?php
/**
 * download_others_report.php
 *
 * Entry point for downloading an OTHERS (custom groups) election report as PDF.
 * Parallel to download_coop_report.php, but for scope_type = "Others".
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
    error_log("Database connection failed (PDF others): " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Shared helpers ---
require_once __DIR__ . '/includes/analytics_scopes.php'; // for SCOPE_OTHERS, getScopeSeats, getScopedElections
require_once __DIR__ . '/admin_functions.php';           // for any shared admin utilities you might use

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Confirm user is admin (same style as COOP report)
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();
$role = $userInfo['role'] ?? '';

if ($role !== 'admin') {
    $_SESSION['toast_message'] = 'Only admin accounts can generate this report.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   FIND THIS ADMIN'S OTHERS SCOPE SEAT (scope_type = SCOPE_OTHERS)
   ========================================================== */

$mySeat   = null;
$othersSeats = getScopeSeats($pdo, SCOPE_OTHERS); // returns all seats for scope_type "Others"

foreach ($othersSeats as $seat) {
    if ((int)$seat['admin_user_id'] === $userId) {
        $mySeat = $seat;
        break;
    }
}

if (!$mySeat) {
    // This admin has no OTHERS scope seat
    $_SESSION['toast_message'] = 'You are not assigned as an Others admin.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

$scopeId = (int) $mySeat['scope_id']; // owner_scope_id for Others elections

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

// Make sure this election belongs to this Others scope seat
$scopedElections    = getScopedElections($pdo, SCOPE_OTHERS, $scopeId);
$allowedElectionIds = array_map('intval', array_column($scopedElections, 'election_id'));

if (!in_array($electionId, $allowedElectionIds, true)) {
    $_SESSION['toast_message'] = 'You are not allowed to generate a report for this Others election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   GENERATE PDF
   ========================================================== */

require_once __DIR__ . '/includes/pdf/others_election_report_pdf.php';

/**
 * This function must be implemented in:
 *   includes/pdf/others_election_report_pdf.php
 *
 * Signature:
 *   generateOthersElectionReportPDF(PDO $pdo, int $electionId, int $adminUserId, int $scopeId): void
 *
 * It should:
 *   - Validate that the election has election_scope_type = 'Others'
 *     and owner_scope_id = $scopeId
 *   - Compute eligible voters + turnout for this Others scope
 *   - Build dynamic sections based on available credentials
 *     (e.g., if there is position / status / department data)
 *   - Render candidate ranking (with ties) and turnout tables
 *   - Output the PDF (TCPDF -> Output(...)) and exit
 */
generateOthersElectionReportPDF($pdo, $electionId, $userId, $scopeId);

// TCPDF will send the PDF and exit; nothing after this line will run.
