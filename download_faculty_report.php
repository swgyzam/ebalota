<?php
/**
 * download_faculty_report.php
 *
 * Entry point for downloading a Faculty (Academic-Faculty) election report as PDF.
 * Works with includes/pdf/faculty_election_report_pdf.php
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
    error_log("Database connection failed (PDF faculty): " . $e->getMessage());
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

// Confirm user is admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();
$role = $userInfo['role'] ?? '';

if ($role !== 'admin') {
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   FIND THIS ADMIN'S ACADEMIC-FACULTY SCOPE SEAT
   ========================================================== */
$mySeat   = null;
$facSeats = getScopeSeats($pdo, SCOPE_ACAD_FACULTY);

foreach ($facSeats as $seat) {
    if ((int)$seat['admin_user_id'] === $userId) {
        $mySeat = $seat;
        break;
    }
}

if (!$mySeat) {
    $_SESSION['toast_message'] = 'You are not assigned as an Academic-Faculty admin.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

$scopeId     = (int) $mySeat['scope_id']; // owner_scope_id for faculty elections
$collegeCode = strtoupper(trim($mySeat['assigned_scope'] ?? ''));

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

// Make sure this election belongs to this faculty scope seat
$scopedElections    = getScopedElections($pdo, SCOPE_ACAD_FACULTY, $scopeId);
$allowedElectionIds = array_map('intval', array_column($scopedElections, 'election_id'));

if (!in_array($electionId, $allowedElectionIds, true)) {
    $_SESSION['toast_message'] = 'You are not allowed to generate a report for this faculty election.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   GENERATE PDF
   ========================================================== */
require_once __DIR__ . '/includes/pdf/faculty_election_report_pdf.php';

generateFacultyElectionReportPDF($pdo, $electionId, $userId, $scopeId);

// TCPDF will send the PDF and exit.
