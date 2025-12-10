<?php
/**
 * download_total_report.php
 *
 * Entry point for downloading a TOTAL system summary report as PDF.
 * Uses includes/pdf/super_admin_total_report_pdf.php
 */

session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection (same pattern as other admin pages) ---
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
    error_log("Database connection failed (TOTAL PDF): " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Shared helpers / auth ---
require_once __DIR__ . '/includes/analytics_scopes.php';
require_once __DIR__ . '/includes/super_admin_helpers.php';
require_once __DIR__ . '/admin_functions.php'; // for activity_logs if you log there

requireSuperAdmin();

$superAdminId = (int)($_SESSION['user_id'] ?? 0);

// --- Year range from GET (same logic style as dashboard) ---
$currentYear = (int)date('Y');
$year2024    = 2024;

// Get full turnout array to know available years
$globalTurnoutByYear = getGlobalTurnoutByYear($pdo, null);
$allTurnoutYears     = array_keys($globalTurnoutByYear);

// Always include 2024 and current year in available years
if (!in_array($year2024, $allTurnoutYears, true)) {
    $allTurnoutYears[] = $year2024;
}
if (!in_array($currentYear, $allTurnoutYears, true)) {
    $allTurnoutYears[] = $currentYear;
}
sort($allTurnoutYears);

$minYear = !empty($allTurnoutYears) ? min($allTurnoutYears) : $year2024;
$maxYear = !empty($allTurnoutYears) ? max($allTurnoutYears) : $currentYear;

$fromYear = isset($_GET['from_year']) && ctype_digit((string)$_GET['from_year'])
    ? (int)$_GET['from_year']
    : $year2024;

$toYear = isset($_GET['to_year']) && ctype_digit((string)$_GET['to_year'])
    ? (int)$_GET['to_year']
    : $currentYear;

// clamp
if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear   < $fromYear) $toYear  = $fromYear;

/* ==========================================================
   ACTIVITY LOG – REPORT GENERATION (TOTAL SUMMARY)
   ========================================================== */
try {
    $actionText = 'Generated TOTAL system summary report for years '
                . $fromYear . '–' . $toYear;

    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, timestamp)
        VALUES (:uid, :action, NOW())
    ");
    $logStmt->execute([
        ':uid'    => $superAdminId,
        ':action' => $actionText,
    ]);
} catch (\Exception $e) {
    error_log('[LOG ERROR] TOTAL Report Generation: ' . $e->getMessage());
}

/* ==========================================================
   GENERATE PDF
   ========================================================== */

require_once __DIR__ . '/includes/pdf/super_admin_total_report_pdf.php';

generateSuperAdminTotalReportPDF($pdo, $superAdminId, $fromYear, $toYear);

// TCPDF will output and exit.
