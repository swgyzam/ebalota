<?php
session_start();
date_default_timezone_set('Asia/Manila');

// ========== DB CONNECTION ==========
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database error.");
}

// ========== INCLUDES & AUTH ==========
require_once __DIR__ . '/includes/analytics_scopes.php';
require_once __DIR__ . '/includes/super_admin_helpers.php';
requireSuperAdmin();

// ⚠ IMPORTANT: groupByField is only defined in super_admin_dashboard.php,
// so we need it here as well.
if (!function_exists('groupByField')) {
    function groupByField(array $rows, string $field): array {
        $agg = [];
        foreach ($rows as $r) {
            $key = $r[$field] ?? '';
            if ($key === null) {
                $key = '';
            }
            if (!isset($agg[$key])) {
                $agg[$key] = 0;
            }
            $agg[$key]++;
        }
        arsort($agg);
        return $agg;
    }
}

// ========== READ INPUT PARAMS ==========
$scopeType = $_GET['scope_type'] ?? null;
$scopeId   = isset($_GET['scope_id']) ? (int)$_GET['scope_id'] : null;

$fromYear = isset($_GET['from_year']) && ctype_digit((string)$_GET['from_year'])
    ? (int)$_GET['from_year']
    : (int)date('Y');

$toYear = isset($_GET['to_year']) && ctype_digit((string)$_GET['to_year'])
    ? (int)$_GET['to_year']
    : (int)date('Y');

if (!$scopeType || !$scopeId) {
    die("Invalid scope.");
}

// ========== LOAD SCOPE SEAT ==========
$scopeSeats = getScopeSeats($pdo, null);

$selectedSeat = null;
foreach ($scopeSeats as $seat) {
    if ((int)$seat['scope_id'] === $scopeId && $seat['scope_type'] === $scopeType) {
        $selectedSeat = $seat;
        break;
    }
}

if (!$selectedSeat) {
    die("Scope not found.");
}

// ========== LOAD VOTERS FOR THIS SCOPE ==========
$selectedScopeVoters = getScopedVoters(
    $pdo,
    $selectedSeat['scope_type'],
    // CSG is treated as global in getScopedVoters; keep consistent:
    $selectedSeat['scope_type'] === SCOPE_SPECIAL_CSG ? null : $selectedSeat['scope_id'],
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ========== LOAD ELECTIONS FOR THIS SCOPE (YEAR RANGE) ==========
$selectedScopeElections = getScopedElections(
    $pdo,
    $selectedSeat['scope_type'],
    $selectedSeat['scope_type'] === SCOPE_SPECIAL_CSG ? null : $selectedSeat['scope_id'],
    [
        'year_from' => $fromYear,
        'year_to'   => $toYear,
    ]
);

// ========== LOAD TURNOUT BY YEAR FOR THIS SCOPE ==========
$selectedScopeTurnout = computeTurnoutByYear(
    $pdo,
    $selectedSeat['scope_type'],
    $selectedSeat['scope_id'],
    $selectedScopeVoters,
    [
        'year_from' => $fromYear,
        'year_to'   => $toYear,
    ]
);

// ========== BREAKDOWN (DEPARTMENT OR POSITION) ==========
if (in_array($selectedSeat['scope_type'], [
    SCOPE_ACAD_STUDENT,
    SCOPE_ACAD_FACULTY,
    SCOPE_NONACAD_STUDENT,
    SCOPE_NONACAD_EMPLOYEE,
    SCOPE_OTHERS
], true)) {
    $selectedScopeBreakdown = groupByField($selectedScopeVoters, 'department');
} else {
    $selectedScopeBreakdown = groupByField($selectedScopeVoters, 'position');
}

// ========== GENERATE PDF ==========
require_once __DIR__ . '/includes/pdf/super_admin_scope_detail_report_pdf.php';

generateSuperAdminScopeDetailPDF(
    $pdo,
    (int)$_SESSION['user_id'],
    $selectedSeat,
    $selectedScopeVoters,
    $selectedScopeElections,
    $selectedScopeTurnout,
    $selectedScopeBreakdown,
    $fromYear,
    $toYear
);
