

Here's the full updated code with the Year Range Selector functionality integrated into the COOP Admin dashboard:

```php
//<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
$host = 'localhost';
$db = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Enable error reporting for debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Auth + scope check (NEW MODEL) ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$userId        = (int) $_SESSION['user_id'];
$scopeCategory = $_SESSION['scope_category'] ?? '';
$adminStatus   = $_SESSION['admin_status']   ?? 'inactive';

// New world: COOP dashboard is only for Others-COOP scope
if ($scopeCategory !== 'Others-COOP') {
    // Optional legacy fallback: allow old admins with assigned_scope = 'COOP'
    $legacyScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));
    if ($legacyScope !== 'COOP') {
        header('Location: admin_dashboard_redirect.php');
        exit();
    }
}

if ($adminStatus !== 'active') {
    header('Location: login.php?error=Your admin account is inactive.');
    exit();
}

// From this point on, this dashboard is treated as COOP admin
$isCoopAdmin = true;

// --- Resolve COOP scope seat (admin_scopes) ---
$scopeId = null;

if ($scopeCategory === 'Others-COOP') {
    $scopeStmt = $pdo->prepare("
        SELECT scope_id
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = 'Others-COOP'
        LIMIT 1
    ");
    $scopeStmt->execute([':uid' => $userId]);
    if ($row = $scopeStmt->fetch()) {
        $scopeId = (int) $row['scope_id'];
    }
}

// --- Debug: Check COOP members count ---
$stmt = $pdo->prepare("SELECT COUNT(*) as coop_count FROM users WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1");
$stmt->execute();
$coopCount = $stmt->fetch()['coop_count'];
error_log("Total COOP members: " . $coopCount);

// --- Debug: Check elections assigned to admin ---
$stmt = $pdo->prepare("SELECT COUNT(*) as election_count FROM elections WHERE assigned_admin_id = ?");
$stmt->execute([$userId]);
$electionCount = $stmt->fetch()['election_count'];
error_log("Elections assigned to admin: " . $electionCount);

// --- Get available years for dropdown ---
$stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM users WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 ORDER BY year DESC");
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
$currentYear = (int)date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;  // ✅ FIXED: added (int)
$previousYear = $selectedYear - 1;

// Ensure current year is ALWAYS included even if no voters exist
if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    rsort($availableYears); // Keep descending order
}

// --- Fetch dashboard stats ---

// Total Voters (all positions if COOP Admin)
if ($isCoopAdmin) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters
    FROM users
    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_voters
    FROM users
    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND UPPER(TRIM(department)) = ?");
    $stmt->execute([$scope]);
}
$total_voters = $stmt->fetch()['total_voters'];

// --- Total & ongoing elections for this COOP scope (NEW MODEL) ---
if ($scopeId !== null) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_elections
        FROM elections
        WHERE election_scope_type = 'Others-COOP'
          AND owner_scope_id      = ?
    ");
    $stmt->execute([$scopeId]);
    $total_elections = (int) ($stmt->fetch()['total_elections'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS ongoing_elections
        FROM elections
        WHERE election_scope_type = 'Others-COOP'
          AND owner_scope_id      = ?
          AND status              = 'ongoing'
    ");
    $stmt->execute([$scopeId]);
    $ongoing_elections = (int) ($stmt->fetch()['ongoing_elections'] ?? 0);

    // Optional: scoped elections list (if gagamitin sa UI)
    $electionStmt = $pdo->prepare("
        SELECT *
        FROM elections
        WHERE election_scope_type = 'Others-COOP'
          AND owner_scope_id      = ?
        ORDER BY start_datetime DESC
    ");
    $electionStmt->execute([$scopeId]);
    $elections = $electionStmt->fetchAll();

} else {
    // Fallback: walang scope seat (very legacy case) – keep old behavior
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_elections
        FROM elections
        WHERE assigned_admin_id = ?
    ");
    $stmt->execute([$userId]);
    $total_elections = (int) ($stmt->fetch()['total_elections'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS ongoing_elections
        FROM elections
        WHERE status = 'ongoing'
          AND assigned_admin_id = ?
    ");
    $stmt->execute([$userId]);
    $ongoing_elections = (int) ($stmt->fetch()['ongoing_elections'] ?? 0);

    $electionStmt = $pdo->prepare("
        SELECT *
        FROM elections
        WHERE assigned_admin_id = ?
        ORDER BY start_datetime DESC
    ");
    $electionStmt->execute([$userId]);
    $elections = $electionStmt->fetchAll();
}

// --- Fetch elections for display (FIXED - using only assigned_admin_id) ---
$electionStmt = $pdo->prepare("SELECT * FROM elections
WHERE assigned_admin_id = ?
ORDER BY start_datetime DESC");
$electionStmt->execute([$userId]);
$elections = $electionStmt->fetchAll();

// --- Get all colleges for dropdown ---
$allColleges = [];
if ($isCoopAdmin) {
    $stmt = $pdo->query("SELECT DISTINCT department as college_name FROM users WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 ORDER BY college_name");
    $allColleges = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $allColleges = [$scope];
}

// Department abbreviation mapping
$departmentAbbrevMap = [
    'College of Agriculture, Food, Environment and Natural Resources' => 'CAFENR',
    'College of Engineering and Information Technology' => 'CEIT',
    'College of Arts and Sciences' => 'CAS',
    'College of Veterinary Medicine and Biomedical Sciences' => 'CVMBS',
    'College of Education' => 'CED',
    'College of Economics, Management and Development Studies' => 'CEMDS',
    'College of Sports, Physical Education and Recreation' => 'CSPEAR',
    'College of Criminal Justice' => 'CCJ',
    'College of Nursing' => 'CON',
    'College of Tourism and Hospitality Management' => 'CTHM',
    'College of Medicine' => 'COM',
    'Graduate School and Open Learning College' => 'GS-OLC'
];

// College full name mapping
$collegeFullNameMap = [
    'CAFENR' => 'College of Agriculture, Food, Environment and Natural Resources',
    'CEIT' => 'College of Engineering and Information Technology',
    'CAS' => 'College of Arts and Sciences',
    'CVMBS' => 'College of Veterinary Medicine and Biomedical Sciences',
    'CED' => 'College of Education',
    'CEMDS' => 'College of Economics, Management and Development Studies',
    'CSPEAR' => 'College of Sports, Physical Education and Recreation',
    'CCJ' => 'College of Criminal Justice',
    'CON' => 'College of Nursing',
    'CTHM' => 'College of Tourism and Hospitality Management',
    'COM' => 'College of Medicine',
    'GS-OLC' => 'Graduate School and Open Learning College',
];

// =========================
// ANALYTICS DATA
// =========================

// Get voters distribution by college (for COOP Admin) or by department (for college admin)
if ($isCoopAdmin) {
    $stmt = $pdo->prepare("SELECT
    department as college_name,
    COUNT(*) as count
    FROM users
    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1
    GROUP BY college_name
    ORDER BY count DESC");
    $stmt->execute();
    $votersByCollege = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT
    COALESCE(NULLIF(department1, ''), department) as department_name,
    COUNT(*) as count
    FROM users
    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND UPPER(TRIM(department)) = ?
    GROUP BY department_name
    ORDER BY count DESC");
    $stmt->execute([$scope]);
    $votersByDepartment = $stmt->fetchAll();
}

// =========================
// BAR DATA (RIGHT OF DOUGHNUT)
// Unified shapes for JS:
// - $collegePositionBar: { college_name, position, count }
// - $collegeStatusBar    : { college_name, status, count }
// =========================
if ($isCoopAdmin) {
    $stmt = $pdo->query("SELECT department AS college_name, position, COUNT(*) AS count
                       FROM users WHERE role='voter' AND is_coop_member=1 AND migs_status=1
                       GROUP BY college_name, position
                       ORDER BY college_name, count DESC");
    $collegePositionBar = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT position, COUNT(*) AS count
                         FROM users WHERE role='voter' AND is_coop_member=1 AND migs_status=1 AND UPPER(TRIM(department))=?
                         GROUP BY position ORDER BY count DESC");
    $stmt->execute([$scope]);
    $rows = $stmt->fetchAll();
    // inject college_name to unify shape
    $collegePositionBar = array_map(function($r) use ($scope) {
        return [
            'college_name' => $scope,
            'position' => $r['position'],
            'count' => (int)$r['count']
        ];
    }, $rows);
}

if ($isCoopAdmin) {
    $stmt = $pdo->query("SELECT department AS college_name, status
                       FROM users
                       WHERE role='voter' AND is_coop_member=1 AND migs_status=1 AND status IS NOT NULL AND status<>''");
    $rows = $stmt->fetchAll();
    $agg = [];
    foreach ($rows as $r) {
        $statusName = $r['status'];
        $key  = $r['college_name'].'|'.$statusName;
        if (!isset($agg[$key])) $agg[$key] = ['college_name'=>$r['college_name'],'status'=>$statusName,'count'=>0];
        $agg[$key]['count']++;
    }
    $collegeStatusBar = array_values($agg);
} else {
    $stmt = $pdo->prepare("SELECT status
                         FROM users
                         WHERE role='voter' AND is_coop_member=1 AND migs_status=1
                           AND UPPER(TRIM(department))=? AND status IS NOT NULL AND status<>''");
    $stmt->execute([$scope]);
    $rows = $stmt->fetchAll();
    $agg = [];
    foreach ($rows as $r) {
        $statusName = $r['status'];
        if (!isset($agg[$statusName])) $agg[$statusName] = ['college_name'=>$scope,'status'=>$statusName,'count'=>0];
        $agg[$statusName]['count']++;
    }
    $collegeStatusBar = array_values($agg);
}

// --- Fetch Voter Turnout Analytics Data (NEW, scope-based) ---
$turnoutDataByYear = [];
$turnoutYears      = [];

if ($scopeId !== null) {
    // Years that have COOP-scope elections
    $stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections
        WHERE election_scope_type = 'Others-COOP'
          AND owner_scope_id      = ?
        ORDER BY year ASC
    ");
    $stmt->execute([$scopeId]);
    $turnoutYears = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    
    // ✅ ADD: Ensure current year is ALWAYS included even if no elections exist
    $currentYear = (int)date('Y');
    if (!in_array($currentYear, $turnoutYears)) {
        $turnoutYears[] = $currentYear;
    }
    
    // ✅ ADD: Also include previous year for comparison
    $prevYear = $currentYear - 1;
    if (!in_array($prevYear, $turnoutYears)) {
        $turnoutYears[] = $prevYear;
    }
    
    sort($turnoutYears);

    foreach ($turnoutYears as $year) {
        // Distinct COOP voters who voted in this scope's elections that year
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.voter_id) AS total_voted
            FROM votes v
            JOIN elections e ON v.election_id = e.election_id
            WHERE e.election_scope_type = 'Others-COOP'
              AND e.owner_scope_id      = ?
              AND YEAR(e.start_datetime) = ?
        ");
        $stmt->execute([$scopeId, $year]);
        $totalVoted = (int) ($stmt->fetch()['total_voted'] ?? 0);

        // Eligible COOP voters as of Dec 31 that year
        $yearEnd = sprintf('%04d-12-31 23:59:59', $year);
        if ($isCoopAdmin) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                                   FROM users 
                                   WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 
                                   AND (created_at <= ? OR created_at IS NULL)");
            $stmt->execute([$yearEnd]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                                   FROM users 
                                   WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND UPPER(TRIM(department)) = ? 
                                   AND (created_at <= ? OR created_at IS NULL)");
            $stmt->execute([$scope, $yearEnd]);
        }
        $totalEligible = $stmt->fetch()['total_eligible'];

        $turnoutRate = ($totalEligible > 0)
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0.0;

        // Number of elections for this scope & year
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS election_count
            FROM elections
            WHERE election_scope_type = 'Others-COOP'
              AND owner_scope_id      = ?
              AND YEAR(start_datetime) = ?
        ");
        $stmt->execute([$scopeId, $year]);
        $electionCount = (int) ($stmt->fetch()['election_count'] ?? 0);

        $turnoutDataByYear[$year] = [
            'year'           => $year,
            'total_voted'    => $totalVoted,
            'total_eligible' => $totalEligible,
            'turnout_rate'   => $turnoutRate,
            'election_count' => $electionCount,
        ];
    }
} else {
    // Fallback: no scope seat (very old admins) — keep previous assigned_admin_id logic
    $stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections
        WHERE assigned_admin_id = ?
        ORDER BY year ASC
    ");
    $stmt->execute([$userId]);
    $turnoutYears = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    
    // ✅ ADD: Ensure current year is ALWAYS included even if no elections exist
    $currentYear = (int)date('Y');
    if (!in_array($currentYear, $turnoutYears)) {
        $turnoutYears[] = $currentYear;
    }
    
    // ✅ ADD: Also include previous year for comparison
    $prevYear = $currentYear - 1;
    if (!in_array($prevYear, $turnoutYears)) {
        $turnoutYears[] = $prevYear;
    }
    
    sort($turnoutYears);

    foreach ($turnoutYears as $year) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.voter_id) AS total_voted
            FROM votes v
            JOIN elections e ON v.election_id = e.election_id
            WHERE e.assigned_admin_id = ?
              AND YEAR(e.start_datetime) = ?
        ");
        $stmt->execute([$userId, $year]);
        $totalVoted = (int) ($stmt->fetch()['total_voted'] ?? 0);

        // Get total voters as of December 31 of this year (FIXED)
        $yearEnd = sprintf('%04d-12-31 23:59:59', $year);
        if ($isCoopAdmin) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                                   FROM users 
                                   WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 
                                   AND (created_at <= ? OR created_at IS NULL)");
            $stmt->execute([$yearEnd]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_eligible 
                                   FROM users 
                                   WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND UPPER(TRIM(department)) = ? 
                                   AND (created_at <= ? OR created_at IS NULL)");
            $stmt->execute([$scope, $yearEnd]);
        }
        $totalEligible = $stmt->fetch()['total_eligible'];

        $turnoutRate = ($totalEligible > 0)
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0.0;

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS election_count
            FROM elections
            WHERE assigned_admin_id = ?
              AND YEAR(start_datetime) = ?
        ");
        $stmt->execute([$userId, $year]);
        $electionCount = (int) ($stmt->fetch()['election_count'] ?? 0);

        $turnoutDataByYear[$year] = [
            'year'           => $year,
            'total_voted'    => $totalVoted,
            'total_eligible' => $totalEligible,
            'turnout_rate'   => $turnoutRate,
            'election_count' => $electionCount,
        ];
    }
}

// Add previous year if not exists (for comparison only)
$prevYear = $selectedYear - 1;
if (!isset($turnoutDataByYear[$prevYear])) {
    $turnoutDataByYear[$prevYear] = [
        'year' => $prevYear,
        'total_voted' => 0,
        'total_eligible' => 0,
        'turnout_rate' => 0,
        'election_count' => 0,
        'growth_rate' => 0
    ];
}

// Calculate year-over-year growth for turnout
$years = array_keys($turnoutDataByYear);
sort($years);
$previousYear = null;
foreach ($years as $year) {
    if ($previousYear !== null) {
        $prevTurnout = $turnoutDataByYear[$previousYear]['turnout_rate'];
        $currentTurnout = $turnoutDataByYear[$year]['turnout_rate'];
        $growthRate = ($prevTurnout > 0) ? round((($currentTurnout - $prevTurnout) / $prevTurnout) * 100, 1) : 0;
        $turnoutDataByYear[$year]['growth_rate'] = $growthRate;
    } else {
        $turnoutDataByYear[$year]['growth_rate'] = 0;
    }
    $previousYear = $year;
}

// --- Year range filtering for turnout analytics ---
$allTurnoutYears = array_keys($turnoutDataByYear);
sort($allTurnoutYears);

$defaultYear = (int)date('Y');
$minYear = $allTurnoutYears ? min($allTurnoutYears) : $defaultYear;
$maxYear = $allTurnoutYears ? max($allTurnoutYears) : $defaultYear;

// Read range from query string (e.g., ?from_year=2021&to_year=2024)
$fromYear = isset($_GET['from_year']) ? (int)$_GET['from_year'] : $minYear;
$toYear   = isset($_GET['to_year'])   ? (int)$_GET['to_year']   : $maxYear;

// Clamp to known bounds
if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear < $fromYear)  $toYear   = $fromYear;

// Build range [fromYear..toYear], ensuring missing years appear with zeros
$turnoutRangeData = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($turnoutDataByYear[$y])) {
        $turnoutRangeData[$y] = $turnoutDataByYear[$y];
    } else {
        $turnoutRangeData[$y] = [
            'total_voted'    => 0,
            'total_eligible' => 0,
            'turnout_rate'   => 0,
            'growth_rate'    => 0,
            'election_count' => 0
        ];
    }
}

// Recompute growth_rate within the selected range
$prevY = null;
foreach ($turnoutRangeData as $y => &$data) {
    if ($prevY === null) {
        $data['growth_rate'] = 0;
    } else {
        $prevRate = $turnoutRangeData[$prevY]['turnout_rate'] ?? 0;
        $data['growth_rate'] = $prevRate > 0 ? round(($data['turnout_rate'] - $prevRate) / $prevRate * 100, 1) : 0;
    }
    $prevY = $y;
}
unset($data);

// --- College Turnout Data (for COOP Admin) ---
$collegeTurnoutData = [];
if ($isCoopAdmin) {
    if ($scopeId !== null) {
        $stmt = $pdo->prepare("
            SELECT
            u.department as college_name,
            COUNT(DISTINCT u.user_id) as eligible_count,
            COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
            FROM users u
            LEFT JOIN (
                SELECT DISTINCT voter_id
                FROM votes
                WHERE election_id IN (
                    SELECT election_id FROM elections
                    WHERE election_scope_type = 'Others-COOP' AND owner_scope_id = ? AND YEAR(start_datetime) = ?
                )
            ) v ON u.user_id = v.voter_id
            WHERE u.role = 'voter' AND u.is_coop_member = 1 AND u.migs_status = 1
            GROUP BY college_name
            ORDER BY college_name
        ");
        $stmt->execute([$scopeId, $selectedYear]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
            u.department as college_name,
            COUNT(DISTINCT u.user_id) as eligible_count,
            COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
            FROM users u
            LEFT JOIN (
                SELECT DISTINCT voter_id
                FROM votes
                WHERE election_id IN (
                    SELECT election_id FROM elections
                    WHERE assigned_admin_id = ? AND YEAR(start_datetime) = ?
                )
            ) v ON u.user_id = v.voter_id
            WHERE u.role = 'voter' AND u.is_coop_member = 1 AND u.migs_status = 1
            GROUP BY college_name
            ORDER BY college_name
        ");
        $stmt->execute([$userId, $selectedYear]);
    }
    $collegeResults = $stmt->fetchAll();
    
    foreach ($collegeResults as $row) {
        $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
        $collegeTurnoutData[] = [
            'college' => $row['college_name'],
            'eligible_count' => (int)$row['eligible_count'],
            'voted_count' => (int)$row['voted_count'],
            'turnout_rate' => (float)$turnoutRate
        ];
    }
}

// --- Position Turnout Data (for COOP Admin) ---
$positionTurnoutData = [];
if ($isCoopAdmin) {
    if ($scopeId !== null) {
        $stmt = $pdo->prepare("
            SELECT
            u.department as college_name,
            u.position,
            COUNT(DISTINCT u.user_id) as eligible_count,
            COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
            FROM users u
            LEFT JOIN (
                SELECT DISTINCT voter_id
                FROM votes
                WHERE election_id IN (
                    SELECT election_id FROM elections
                    WHERE election_scope_type = 'Others-COOP' AND owner_scope_id = ? AND YEAR(start_datetime) = ?
                )
            ) v ON u.user_id = v.voter_id
            WHERE u.role = 'voter' AND u.is_coop_member = 1 AND u.migs_status = 1
            GROUP BY college_name, position
            ORDER BY college_name, position
        ");
        $stmt->execute([$scopeId, $selectedYear]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
            u.department as college_name,
            u.position,
            COUNT(DISTINCT u.user_id) as eligible_count,
            COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) as voted_count
            FROM users u
            LEFT JOIN (
                SELECT DISTINCT voter_id
                FROM votes
                WHERE election_id IN (
                    SELECT election_id FROM elections
                    WHERE assigned_admin_id = ? AND YEAR(start_datetime) = ?
                )
            ) v ON u.user_id = v.voter_id
            WHERE u.role = 'voter' AND u.is_coop_member = 1 AND u.migs_status = 1
            GROUP BY college_name, position
            ORDER BY college_name, position
        ");
        $stmt->execute([$userId, $selectedYear]);
    }
    $positionResults = $stmt->fetchAll();
    
    foreach ($positionResults as $row) {
        $turnoutRate = ($row['eligible_count'] > 0) ? round(($row['voted_count'] / $row['eligible_count']) * 100, 1) : 0;
        $positionTurnoutData[] = [
            'college' => $row['college_name'],
            'position' => $row['position'],
            'eligible_count' => (int)$row['eligible_count'],
            'voted_count' => (int)$row['voted_count'],
            'turnout_rate' => (float)$turnoutRate
        ];
    }
}

/* ==========================================================
STATUS TURNOUT DATA (FIXED)
========================================================== */
$statusTurnoutData = [];

// First, get all distinct voters who voted in any elections assigned to this admin in this year (FIXED)
if ($scopeId !== null) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT v.voter_id
        FROM votes v
        JOIN elections e ON v.election_id = e.election_id
        WHERE e.election_scope_type = 'Others-COOP' AND e.owner_scope_id = ? AND YEAR(e.start_datetime) = ?
    ");
    $stmt->execute([$scopeId, $selectedYear]);
} else {
    $stmt = $pdo->prepare("
        SELECT DISTINCT v.voter_id
        FROM votes v
        JOIN elections e ON v.election_id = e.election_id
        WHERE e.assigned_admin_id = ? AND YEAR(e.start_datetime) = ?
    ");
    $stmt->execute([$userId, $selectedYear]);
}
$votedIds = array_column($stmt->fetchAll(), 'voter_id');
$votedSet = array_flip($votedIds);

// Get all status data first without grouping
$stmt = $pdo->prepare("
    SELECT
    u.user_id,
    u.department as college_name,
    u.position,
    u.status
    FROM users u
    WHERE u.role = 'voter' AND u.is_coop_member = 1 AND u.migs_status = 1
    AND (u.status IS NOT NULL AND u.status <> '' OR 1=1)"); // Allow for NULL status
$stmt->execute();
$rows = $stmt->fetchAll();

// Group by status names
$statusGroups = [];
foreach ($rows as $row) {
    $college = $row['college_name'] ?? 'UNKNOWN';
    $position = $row['position'];
    $statusName = $row['status'] ?? 'Not Specified'; // Handle NULL status
    
    $key = $college . '|' . $position . '|' . $statusName;
    
    if (!isset($statusGroups[$key])) {
        $statusGroups[$key] = [
            'college_name' => $college,
            'position' => $position,
            'status' => $statusName,
            'eligible_count' => 0,
            'voted_count' => 0
        ];
    }
    $statusGroups[$key]['eligible_count']++;
    if (isset($votedSet[$row['user_id']])) {
        $statusGroups[$key]['voted_count']++;
    }
}

// Convert to array and calculate turnout rates
foreach ($statusGroups as $key => $data) {
    $turnoutRate = ($data['eligible_count'] > 0)
        ? round(($data['voted_count'] / $data['eligible_count']) * 100, 1)
        : 0.0;
    
    $statusTurnoutData[] = [
        'college_name' => $data['college_name'],
        'position' => $data['position'],
        'status' => $data['status'],
        'eligible_count' => (int)$data['eligible_count'],
        'voted_count' => (int)$data['voted_count'],
        'turnout_rate' => (float)$turnoutRate,
    ];
}

// Sort by eligible count DESC
usort($statusTurnoutData, function($a, $b) {
    return $b['eligible_count'] <=> $a['eligible_count'];
});

// Set page title based on scope
if ($isCoopAdmin) {
    $pageTitle = "COOP ADMIN DASHBOARD";
    $pageSubtitle = "Cooperative - All Colleges";
    $collegeFullName = "All Colleges";
} else {
    $pageTitle = htmlspecialchars($collegeFullNameMap[$scope] ?? $scope) . " ADMIN DASHBOARD";
    $pageSubtitle = htmlspecialchars($collegeFullNameMap[$scope] ?? $scope);
    $collegeFullName = $collegeFullNameMap[$scope] ?? $scope;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - <?= htmlspecialchars($pageTitle) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      /* CVSU Colors */
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
      --cvsu-accent: #2D5F3F;
      --cvsu-light-accent: #4A7C59;
      
      /* Additional Colors for Better Visual Hierarchy */
      --cvsu-blue: #3B82F6;
      --cvsu-blue-light: #60A5FA;
      --cvsu-purple: #8B5CF6;
      --cvsu-purple-light: #A78BFA;
      --cvsu-red: #EF4444;
      --cvsu-orange: #F97316;
      --cvsu-teal: #14B8A6;
      --cvsu-indigo: #6366F1;
      --cvsu-pink: #EC4899;
      --cvsu-gray-light: #F3F4F6;
      --cvsu-gray: #9CA3AF;
      --cvsu-gray-dark: #4B5563;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb {
      background-color: var(--cvsu-green-light);
      border-radius: 3px;
    }
    .analytics-card { transition: transform .2s, box-shadow .2s; }
    .analytics-card:hover { transform: translateY(-2px); }
    .status-badge {
      display:inline-flex;align-items:center;padding:.25rem .75rem;
      border-radius:9999px;font-size:.75rem;font-weight:600;
    }
    .chart-container{position:relative;height:320px;width:100%;}
    .chart-tooltip{position:absolute;background-color:white;color:#333;padding:12px 16px;
      border-radius:8px;font-size:14px;pointer-events:none;opacity:0;transition:opacity .2s;
      z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.15);border:1px solid #e5e7eb;
      max-width:220px;display:none;right:10px;top:10px;left:auto;}
    .chart-tooltip.show{opacity:1;display:block;}
    .chart-tooltip .title{font-weight:bold;color:var(--cvsu-green-dark);margin-bottom:4px;font-size:16px;}
    .chart-tooltip .count{color:#4b5563;font-size:14px;}
    .breakdown-section{display:none;}
    .breakdown-section.active{display:block;}
    .cvsu-gradient{
      background:linear-gradient(135deg,var(--cvsu-green-dark)0%,var(--cvsu-green)100%);
    }
    .cvsu-card{
      background-color:white;border-left:4px solid var(--cvsu-green);
      box-shadow:0 4px 6px rgba(0,0,0,.05);transition:all .3s;
    }
    .cvsu-card:hover{box-shadow:0 10px 15px rgba(0,0,0,.1);transform:translateY(-2px);}
    
    /* Color Classes for Different Elements */
    .bg-cvsu-green { background-color: var(--cvsu-green); }
    .bg-cvsu-green-dark { background-color: var(--cvsu-green-dark); }
    .bg-cvsu-green-light { background-color: var(--cvsu-green-light); }
    .bg-cvsu-yellow { background-color: var(--cvsu-yellow); }
    .bg-cvsu-blue { background-color: var(--cvsu-blue); }
    .bg-cvsu-blue-light { background-color: var(--cvsu-blue-light); }
    .bg-cvsu-purple { background-color: var(--cvsu-purple); }
    .bg-cvsu-purple-light { background-color: var(--cvsu-purple-light); }
    .bg-cvsu-red { background-color: var(--cvsu-red); }
    .bg-cvsu-orange { background-color: var(--cvsu-orange); }
    .bg-cvsu-teal { background-color: var(--cvsu-teal); }
    .bg-cvsu-indigo { background-color: var(--cvsu-indigo); }
    .bg-cvsu-pink { background-color: var(--cvsu-pink); }
    
    .text-cvsu-green { color: var(--cvsu-green); }
    .text-cvsu-green-dark { color: var(--cvsu-green-dark); }
    .text-cvsu-green-light { color: var(--cvsu-green-light); }
    .text-cvsu-yellow { color: var(--cvsu-yellow); }
    .text-cvsu-blue { color: var(--cvsu-blue); }
    .text-cvsu-blue-light { color: var(--cvsu-blue-light); }
    .text-cvsu-purple { color: var(--cvsu-purple); }
    .text-cvsu-purple-light { color: var(--cvsu-purple-light); }
    .text-cvsu-red { color: var(--cvsu-red); }
    .text-cvsu-orange { color: var(--cvsu-orange); }
    .text-cvsu-teal { color: var(--cvsu-teal); }
    .text-cvsu-indigo { color: var(--cvsu-indigo); }
    .text-cvsu-pink { color: var(--cvsu-pink); }
    
    .border-cvsu-green { border-color: var(--cvsu-green); }
    .border-cvsu-green-dark { border-color: var(--cvsu-green-dark); }
    .border-cvsu-green-light { border-color: var(--cvsu-green-light); }
    .border-cvsu-yellow { border-color: var(--cvsu-yellow); }
    .border-cvsu-blue { border-color: var(--cvsu-blue); }
    .border-cvsu-blue-light { border-color: var(--cvsu-blue-light); }
    .border-cvsu-purple { border-color: var(--cvsu-purple); }
    .border-cvsu-purple-light { border-color: var(--cvsu-purple-light); }
    .border-cvsu-red { border-color: var(--cvsu-red); }
    .border-cvsu-orange { border-color: var(--cvsu-orange); }
    .border-cvsu-teal { border-color: var(--cvsu-teal); }
    .border-cvsu-indigo { border-color: var(--cvsu-indigo); }
    .border-cvsu-pink { border-color: var(--cvsu-pink); }
    
    /* Disabled select styling */
    select:disabled {
      background-color: #f3f4f6;
      color: #9ca3af;
      cursor: not-allowed;
    }
    
    /* Label for disabled select */
    .label-disabled {
      color: #9ca3af;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
<div class="flex min-h-screen">
<?php include 'sidebar.php'; ?>
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
  <h1 class="text-2xl font-bold text-white">
    <?= htmlspecialchars($pageTitle) ?>
  </h1>
  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
  </div>
</header>
<main class="flex-1 pt-20 px-8 ml-64">

  <!-- Statistics Cards (MOVED TO TOP) -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Voters -->
    <div class="cvsu-card p-6 rounded-xl">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Voters</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-green-dark);"><?= number_format($total_voters) ?></p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(30, 111, 70, 0.1);">
          <i class="fas fa-users text-2xl" style="color: var(--cvsu-green);"></i>
        </div>
      </div>
    </div>
    
    <!-- Total Elections -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-yellow);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Total Elections</h2>
          <p class="text-2xl md:text-4xl font-bold" style="color: var(--cvsu-yellow);"><?= $total_elections ?></p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(255, 209, 102, 0.1);">
          <i class="fas fa-vote-yea text-2xl" style="color: var(--cvsu-yellow);"></i>
        </div>
      </div>
    </div>
    
    <!-- Ongoing Elections -->
    <div class="cvsu-card p-6 rounded-xl" style="border-left-color: var(--cvsu-blue);">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base md:text-lg font-semibold text-gray-700">Ongoing Elections</h2>
          <p class="text-2xl md:text-4xl font-bold text-blue-600"><?= $ongoing_elections ?></p>
        </div>
        <div class="p-3 rounded-full" style="background-color: rgba(59, 130, 246, 0.1);">
          <i class="fas fa-clock text-2xl text-blue-600"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Detailed Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">COOP Admin Analytics</h2>
        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
          <div class="flex items-center">
            <label for="detailedBreakdownSelect" class="mr-3 text-sm font-medium text-white">Breakdown by:</label>
            <select id="detailedBreakdownSelect" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <option value="all" selected>College/Department</option>
              <option value="position">College/Department and Position</option>
              <option value="status">College/Department and Status</option>
            </select>
          </div>
          <div id="detailedCollegeSelector" class="flex items-center">
            <label id="detailedCollegeLabel" for="detailedCollegeSelect" class="mr-3 text-sm font-medium text-white">Select College/Department:</label>
            <select id="detailedCollegeSelect" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
              <option value="all" selected>All Colleges/Departments</option>
              <?php foreach ($allColleges as $college): ?>
                <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>
    
    <div class="p-6">
      <!-- Summary Cards -->
        <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color: var(--cvsu-green-light);">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-green-light);">
                        <i class="fas fa-user-plus text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--cvsu-green);">New This Month</p>
                        <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);">
                            <?php
                            // Calculate new voters this month
                            $currentMonthStart = date('Y-m-01');
                            $currentMonthEnd = date('Y-m-t 23:59:59');
                            
                            if ($isCoopAdmin) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as new_voters 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1
                                                        AND created_at BETWEEN ? AND ?");
                                $stmt->execute([$currentMonthStart, $currentMonthEnd]);
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as new_voters 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND UPPER(TRIM(department)) = ?
                                                        AND created_at BETWEEN ? AND ?");
                                $stmt->execute([$scope, $currentMonthStart, $currentMonthEnd]);
                            }
                            $newVoters = $stmt->fetch()['new_voters'];
                            echo number_format($newVoters);
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color: #3B82F6;">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4 bg-blue-500">
                        <i class="fas fa-building-columns text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-blue-600">College/Departments</p>
                        <p class="text-2xl font-bold text-blue-800">
                            <?php
                            // Count distinct colleges/departments
                            if ($isCoopAdmin) {
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT department) as college_count 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1");
                                $stmt->execute();
                                $collegeCount = $stmt->fetch()['college_count'];
                            } else {
                                // For college admin, count distinct departments within their college
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(department1, ''), department)) as dept_count 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 
                                                        AND UPPER(TRIM(department)) = ?");
                                $stmt->execute([$scope]);
                                $collegeCount = $stmt->fetch()['dept_count'];
                            }
                            echo $collegeCount;
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color: #8B5CF6;">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4 bg-purple-500">
                        <i class="fas fa-id-badge text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-purple-600">Status Types</p>
                        <p class="text-2xl font-bold text-purple-800">
                            <?php
                            // Count distinct status types
                            if ($isCoopAdmin) {
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT status) as status_count 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 
                                                        AND status IS NOT NULL AND status <> ''");
                                $stmt->execute();
                                $statusTypesCount = $stmt->fetch()['status_count'];
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT status) as status_count 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 
                                                        AND UPPER(TRIM(department)) = ? 
                                                        AND status IS NOT NULL AND status <> ''");
                                $stmt->execute([$scope]);
                                $statusTypesCount = $stmt->fetch()['status_count'];
                            }
                            echo $statusTypesCount;
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-lg border" style="background-color: rgba(245,158,11,0.05); border-color: var(--cvsu-yellow);">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-yellow);">
                        <i class="fas fa-chart-line text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm" style="color: var(--cvsu-yellow);">Growth Rate</p>
                        <p class="text-2xl font-bold" style="color: #D97706;">
                            <?php
                            // Calculate new voters last month
                            $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
                            $lastMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));
                            
                            if ($isCoopAdmin) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_voters 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1
                                                        AND created_at BETWEEN ? AND ?");
                                $stmt->execute([$lastMonthStart, $lastMonthEnd]);
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as last_month_voters 
                                                    FROM users 
                                                    WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND UPPER(TRIM(department)) = ?
                                                        AND created_at BETWEEN ? AND ?");
                                $stmt->execute([$scope, $lastMonthStart, $lastMonthEnd]);
                            }
                            $lastMonthVoters = $stmt->fetch()['last_month_voters'];
                            
                            // Calculate growth rate
                            if ($lastMonthVoters > 0) {
                                $displayGrowthRate = round((($newVoters - $lastMonthVoters) / $lastMonthVoters) * 100, 1);
                                echo ($displayGrowthRate > 0 ? '+' : '') . $displayGrowthRate . '%';
                            } else { 
                                // If there were no voters last month, we can't calculate growth rate
                                // But if there are voters this month, we should show a positive infinity
                                if ($newVoters > 0) {
                                    echo '+∞%';
                                } else {
                                    echo '0%';
                                }
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
      
      <!-- Charts -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Donut Chart -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <?php if ($isCoopAdmin): ?>Voters by College/Department<?php else: ?>Voters by Department<?php endif; ?>
          </h3>
          <div class="chart-container">
            <canvas id="donutChart"></canvas>
          </div>
        </div>
        
        <!-- Bar Chart -->
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05);">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Breakdown Details</h3>
          <div class="chart-container">
            <canvas id="barChart"></canvas>
          </div>
        </div>
      </div>
      
      <!-- Detailed Breakdown Table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200" id="detailedBreakdownTable">
            <thead class="bg-gray-50">
              <tr id="detailedTableHeader">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="detailedTableBody">
              <!-- Table content will be populated by JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Voter Turnout Analytics Section -->
  <div class="analytics-section mb-8 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="cvsu-gradient p-6">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold text-white">Voter Turnout Analytics</h2>
      </div>
    </div>
    
    <div class="border-t p-6">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Voter Turnout Analytics</h3>
        <div class="flex items-center">
          <label for="turnoutYearSelector" class="mr-2 text-gray-700 font-medium">Select Year:</label>
          <select id="turnoutYearSelector" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
            <?php foreach ($turnoutYears as $year): ?>
              <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <?php
      $currentYearTurnout = $turnoutDataByYear[$selectedYear] ?? null;
      $previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? null;
      ?>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(99,102,241,0.05); border-color:var(--cvsu-indigo);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-indigo-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-indigo-600"><?= $selectedYear ?> Turnout</p>
              <p class="text-2xl font-bold text-indigo-800"><?= $currentYearTurnout['turnout_rate'] ?? 0 ?>%</p>
            </div>
          </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(139,92,246,0.05); border-color:var(--cvsu-purple);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-purple-500"><i class="fas fa-percentage text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-purple-600"><?= $selectedYear - 1 ?> Turnout</p>
              <p class="text-2xl font-bold text-purple-800"><?= $previousYearTurnout['turnout_rate'] ?? 0 ?>%</p>
            </div>
          </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(16,185,129,0.05); border-color:var(--cvsu-teal);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-teal-500"><i class="fas fa-chart-line text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-teal-600">Growth Rate</p>
              <p class="text-2xl font-bold text-teal-800">
                <?php
                $ct = $currentYearTurnout['turnout_rate'] ?? 0;
                $pt = $previousYearTurnout['turnout_rate'] ?? 0;
                echo $pt > 0 ? ((($ct-$pt)/$pt>0?'+':'').round((($ct-$pt)/$pt)*100,1).'%') : '0%';
                ?>
              </p>
            </div>
          </div>
        </div>
        
        <div class="p-4 rounded-lg border" style="background-color: rgba(59,130,246,0.05); border-color:var(--cvsu-blue);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4 bg-blue-500"><i class="fas fa-vote-yea text-white text-xl"></i></div>
            <div>
              <p class="text-sm text-blue-600">Elections</p>
              <p class="text-2xl font-bold text-blue-800"><?= $currentYearTurnout['election_count'] ?? 0 ?></p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="p-4 rounded-lg mb-8" style="background-color: rgba(30,111,70,0.05);">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Turnout Rate Trend</h3>
        <div class="chart-container" style="height: 400px;">
          <canvas id="turnoutTrendChart"></canvas>
        </div>
      </div>
      
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-8">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Year</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Elections</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Eligible Voters</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters Participated</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turnout Rate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Growth</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($turnoutRangeData as $year => $data):
                $isPositive = ($data['growth_rate'] ?? 0) > 0;
                $trendIcon = $isPositive ? 'fa-arrow-up' : (($data['growth_rate'] ?? 0) < 0 ? 'fa-arrow-down' : 'fa-minus');
                $trendColor = $isPositive ? 'text-green-600' : (($data['growth_rate'] ?? 0) < 0 ? 'text-red-600' : 'text-gray-600');
              ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap font-medium"><?= $year ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= $data['election_count'] ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['total_eligible']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= number_format($data['total_voted']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $data['turnout_rate'] >= 70 ? 'text-green-600' : ($data['turnout_rate'] >= 40 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= $data['turnout_rate'] ?>%
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="<?= $trendColor ?>"><?= ($data['growth_rate'] ?? 0) > 0 ? '+' : '' ?><?= $data['growth_rate'] ?? 0 ?>%</span>
                  <i class="fas <?= $trendIcon ?> <?= $trendColor ?> ml-1"></i>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Elections vs Turnout Rate -->
      <div class="p-4 rounded-lg" style="background-color: rgba(30,111,70,0.05);">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Elections vs Turnout Rate</h3>
          <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-6">
            <div class="flex items-center">
              <label for="dataSeriesSelect" class="mr-3 text-sm font-medium text-gray-700">Data Series:</label>
              <select id="dataSeriesSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <option value="elections">Elections vs Turnout</option>
                <option value="voters">Voters vs Turnout</option>
              </select>
            </div>
            <div class="flex items-center">
              <label for="breakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
              <select id="breakdownSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <option value="year">Year</option>
                <?php if ($isCoopAdmin): ?>
                <option value="college">College/Department</option>
                <option value="position">Position</option>
                <?php endif; ?>
                <option value="status">Status</option>
              </select>
            </div>
            <div id="turnoutCollegeSelector" class="flex items-center" style="display: none;">
              <label for="turnoutCollegeSelect" class="mr-3 text-sm font-medium text-gray-700">Select College/Department:</label>
              <select id="turnoutCollegeSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <!-- Options will be populated by JavaScript -->
              </select>
            </div>
          </div>
        </div>
        <div class="chart-container" style="height: 400px;">
          <canvas id="electionsVsTurnoutChart"></canvas>
        </div>
        <div id="turnoutBreakdownTable" class="mt-6 overflow-x-auto"></div>
      </div>
      
      <!-- Year Range Selector -->
      <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-medium text-blue-800 mb-2">Turnout Analysis – Year Range</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="fromYear" class="block text-sm font-medium text-blue-800">From year</label>
                <select id="fromYear" name="from_year" class="mt-1 p-2 border rounded w-full">
                    <?php foreach ($allTurnoutYears as $y): ?>
                        <option value="<?= $y ?>" <?= ($y == $fromYear) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="toYear" class="block text-sm font-medium text-blue-800">To year</label>
                <select id="toYear" name="to_year" class="mt-1 p-2 border rounded w-full">
                    <?php foreach ($allTurnoutYears as $y): ?>
                        <option value="<?= $y ?>" <?= ($y == $toYear) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="text-xs text-blue-700 mt-2">
            Select a start and end year to compare turnout. Years with no elections in this range will appear with zero values.
        </p>
      </div>
    </div>
  </div>

</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Year selectors
  document.getElementById('turnoutYearSelector')?.addEventListener('change', function() {
    window.location.href = window.location.pathname + '?year=' + this.value;
  });
  
  let turnoutTrendChartInstance = null;
  let electionsVsTurnoutChartInstance = null;
  
  // Data from PHP
  const turnoutYears = <?= json_encode(array_keys($turnoutRangeData)) ?>;
  const turnoutRates = <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>;
  const statusTurnoutData = <?= json_encode($statusTurnoutData) ?>;
  
  // College and position data for filtering
  const collegePositionData = <?= json_encode($collegePositionBar) ?>;
  const collegeStatusData = <?= json_encode($collegeStatusBar) ?>;
  
  // Department abbreviation map
  const departmentAbbrevMap = {
    'College of Agriculture, Food, Environment and Natural Resources': 'CAFENR',
    'College of Engineering and Information Technology': 'CEIT',
    'College of Arts and Sciences': 'CAS',
    'College of Veterinary Medicine and Biomedical Sciences': 'CVMBS',
    'College of Education': 'CED',
    'College of Economics, Management and Development Studies': 'CEMDS',
    'College of Sports, Physical Education and Recreation': 'CSPEAR',
    'College of Criminal Justice': 'CCJ',
    'College of Nursing': 'CON',
    'College of Tourism and Hospitality Management': 'CTHM',
    'College of Medicine': 'COM',
    'Graduate School and Open Learning College': 'GS-OLC'
  };
  
  // College name map
  const collegeFullNameMap = {
    'CAFENR': 'College of Agriculture, Food, Environment and Natural Resources',
    'CEIT': 'College of Engineering and Information Technology',
    'CAS': 'College of Arts and Sciences',
    'CVMBS': 'College of Veterinary Medicine and Biomedical Sciences',
    'CED': 'College of Education',
    'CEMDS': 'College of Economics, Management and Development Studies',
    'CSPEAR': 'College of Sports, Physical Education and Recreation',
    'CCJ': 'College of Criminal Justice',
    'CON': 'College of Nursing',
    'CTHM': 'College of Tourism and Hospitality Management',
    'COM': 'College of Medicine',
    'GS-OLC': 'Graduate School and Open Learning College',
  };
  
  // Position/Status abbreviation map
  const positionStatusAbbrevMap = {
    'academic': 'ACAD',
    'non-academic': 'NON-ACAD',
    'Full-time': 'FT',
    'Part-time': 'PT',
    'Regular': 'REG',
    'Probationary': 'PROB',
    'Contractual': 'CON',
    'General': 'GEN'
  };
  
  function getFullCollegeNameJS(code){ 
    return collegeFullNameMap[code] || code; 
  }
  
  function getPositionAbbrevJS(name){ 
    // First check if it's a college name
    if (departmentAbbrevMap[name]) {
      return departmentAbbrevMap[name];
    }
    
    // Then check if it's a position/status name
    if (positionStatusAbbrevMap[name]) {
      return positionStatusAbbrevMap[name];
    }
    
    // Words to exclude from abbreviations
    const excludeWords = ['and', 'of', 'the', 'in', 'for', 'at', 'by'];
    
    // For colleges that start with "College of " but not in the map
    if (name.startsWith("College of ")) {
      // Try to match by key in the map
      for (let [fullName, abbrev] of Object.entries(departmentAbbrevMap)) {
        if (name === fullName) {
          return abbrev;
        }
      }
      // If not found, generate abbreviation from the name
      // Remove "College of " and take first letters of remaining words, excluding common words
      let rest = name.substring(13).trim();
      let words = rest.split(' ');
      let abbr = words
        .filter(word => !excludeWords.includes(word.toLowerCase()))
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase();
      return abbr;
    }
    
    // Handle special cases not covered above
    if (name === "General" || name === "Full-time" || name === "Part-time" || 
        name === "Regular" || name === "Probationary" || name === "Contractual" ||
        name === "academic" || name === "non-academic") {
      return positionStatusAbbrevMap[name] || name;
    }
    
    // Default: return the full name
    return name;
  }
  
  function getPositionDisplayName(position) {
    if (position === 'academic') {
      return 'Faculty';
    } else if (position === 'non-academic') {
      return 'Non-Academic';
    } else {
      return position;
    }
  }
  
  /* Turnout Trend */
  const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
  if (turnoutTrendCtx && !turnoutTrendChartInstance) {
    turnoutTrendChartInstance = new Chart(turnoutTrendCtx, {
      type:'line',
      data:{ labels: turnoutYears, datasets:[{ label:'Turnout Rate (%)', data:turnoutRates, borderColor:'#1E6F46', backgroundColor:'rgba(30,111,70,0.1)', borderWidth:3, pointBackgroundColor:'#1E6F46', pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:5, pointHoverRadius:7, fill:true, tension:0.4 }]},
      options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'rgba(0,0,0,0.8)', titleFont:{size:14}, bodyFont:{size:13}, padding:12, callbacks:{ label:(c)=> `Turnout: ${c.raw}%` }}},
        scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback:(v)=> v+'%' }, grid:{ color:'rgba(0,0,0,0.05)' }}, x:{ grid:{display:false}}}
      }
    });
  }
  
  /* Elections vs Turnout */
  const chartData = {
    elections:{ year:{
      labels: <?= json_encode(array_keys($turnoutRangeData)) ?>,
      electionCounts: <?= json_encode(array_column($turnoutRangeData, 'election_count')) ?>,
      turnoutRates: <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?>
    }},
    voters:{
      year:{ labels: <?= json_encode(array_keys($turnoutRangeData)) ?>, eligibleCounts: <?= json_encode(array_column($turnoutRangeData, 'total_eligible')) ?>, turnoutRates: <?= json_encode(array_column($turnoutRangeData, 'turnout_rate')) ?> },
      <?php if ($isCoopAdmin): ?>
      college: <?= json_encode($collegeTurnoutData) ?>,
      position: <?= json_encode($positionTurnoutData) ?>,
      <?php endif; ?>
      status: <?= json_encode($statusTurnoutData) ?>
    }
  };
  let currentDataSeries = 'elections';
  let currentBreakdown = 'year';
  
  // Get DOM elements
  const dataSeriesSelect = document.getElementById('dataSeriesSelect');
  const breakdownSelect = document.getElementById('breakdownSelect');
  const turnoutCollegeSelector = document.getElementById('turnoutCollegeSelector');
  const turnoutCollegeSelect = document.getElementById('turnoutCollegeSelect');
  
  // Function to update college selector options
  function updateCollegeSelectorOptions(breakdownType) {
    const select = turnoutCollegeSelect;
    select.innerHTML = '';
    
    // Add "All Colleges/Departments" option first
    const allOption = document.createElement('option');
    allOption.value = 'all';
    allOption.textContent = 'All Colleges/Departments';
    select.appendChild(allOption);
    
    if (breakdownType === 'college') {
      // Add individual colleges
      <?php if ($isCoopAdmin): ?>
      const colleges = <?= json_encode($allColleges) ?>;
      colleges.forEach(college => {
        const option = document.createElement('option');
        option.value = college;
        option.textContent = college;
        select.appendChild(option);
      });
      <?php else: ?>
      const option = document.createElement('option');
      option.value = '<?= $scope ?>';
      option.textContent = '<?= $scope ?>';
      select.appendChild(option);
      <?php endif; ?>
    } else {
      // For position and status, only show specific colleges
      <?php if ($isCoopAdmin): ?>
      const colleges = <?= json_encode($allColleges) ?>;
      colleges.forEach(college => {
        const option = document.createElement('option');
        option.value = college;
        option.textContent = college;
        select.appendChild(option);
      });
      <?php else: ?>
      const option = document.createElement('option');
      option.value = '<?= $scope ?>';
      option.textContent = '<?= $scope ?>';
      select.appendChild(option);
      <?php endif; ?>
    }
  }
  
  // Function to show/hide college selector based on breakdown
  function updateCollegeSelectorVisibility() {
    const breakdownValue = breakdownSelect.value;
    
    if (breakdownValue === 'college' || breakdownValue === 'position' || breakdownValue === 'status') {
      turnoutCollegeSelector.style.display = 'flex';
      updateCollegeSelectorOptions(breakdownValue);
    } else {
      turnoutCollegeSelector.style.display = 'none';
    }
  }
  
  // Function to handle data series change
  function handleDataSeriesChange() {
    currentDataSeries = dataSeriesSelect.value;
    
    if (currentDataSeries === 'elections') {
      // For elections, set breakdown to year and disable the breakdown select
      breakdownSelect.value = 'year';
      breakdownSelect.disabled = true;
      currentBreakdown = 'year';
      // Hide the college selector
      turnoutCollegeSelector.style.display = 'none';
    } else {
      // For voters, enable the breakdown select and set to year
      breakdownSelect.disabled = false;
      breakdownSelect.value = 'year';
      currentBreakdown = 'year';
      // Hide the college selector for year
      turnoutCollegeSelector.style.display = 'none';
    }
    
    renderElectionsVsTurnout();
  }
  
  // Function to handle breakdown change
  function handleBreakdownChange() {
    currentBreakdown = breakdownSelect.value;
    
    // Update college selector visibility
    updateCollegeSelectorVisibility();
    
    renderElectionsVsTurnout();
  }
  
  // Function to render the elections vs turnout chart
  function renderElectionsVsTurnout() {
    const ctx = document.getElementById('electionsVsTurnoutChart');
    if (!ctx) return;
    if (electionsVsTurnoutChartInstance) electionsVsTurnoutChartInstance.destroy();
    
    let data, options;
    const selectedCollege = turnoutCollegeSelect ? turnoutCollegeSelect.value : 'all';
    
    if (currentDataSeries === 'elections') {
      data = {
        labels: chartData.elections.year.labels,
        datasets:[
          { label:'Number of Elections', data: chartData.elections.year.electionCounts, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
          { label:'Turnout Rate (%)', data: chartData.elections.year.turnoutRates, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
        ]
      };
      options = {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Elections vs Turnout Rate by Year', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
        scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Elections', font:{size:14, weight:'bold'}}},
                 y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                 x:{ grid:{display:false}}}
      };
    } else {
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.voters.year.labels,
          datasets:[
            { label:'Eligible Voters', data: chartData.voters.year.eligibleCounts, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: chartData.voters.year.turnoutRates, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:'Eligible Voters vs Turnout Rate by Year', font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      } 
      <?php if ($isCoopAdmin): ?>
      else if (currentBreakdown === 'college') {
        let filteredData, labels, eligible, tr;
        
        if (selectedCollege === 'all') {
          // Show all colleges
          filteredData = chartData.voters.college;
          labels = filteredData.map(item => getPositionAbbrevJS(collegeFullNameMap[item.college] || item.college));
          eligible = filteredData.map(item => item.eligible_count);
          tr = filteredData.map(item => item.turnout_rate);
        } else {
          // Show specific college
          filteredData = chartData.voters.college.filter(item => item.college === selectedCollege);
          labels = [getPositionAbbrevJS(collegeFullNameMap[selectedCollege] || selectedCollege)];
          eligible = filteredData.map(item => item.eligible_count);
          tr = filteredData.map(item => item.turnout_rate);
        }
        
        data = {
          labels,
          datasets:[
            { label:'Eligible Voters', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:`Eligible Voters vs Turnout Rate by College/Department (${selectedCollege === 'all' ? 'All Colleges/Departments' : selectedCollege})`, font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      } else if (currentBreakdown === 'position') {
        let labels, eligible, tr;
        
        if (selectedCollege === 'all') {
          // Aggregate positions across all colleges
          let aggregatedData = {};
          chartData.voters.position.forEach(item => {
            const positionName = getPositionDisplayName(item.position);
            if (!aggregatedData[positionName]) {
              aggregatedData[positionName] = { eligible: 0, voted: 0 };
            }
            aggregatedData[positionName].eligible += item.eligible_count;
            aggregatedData[positionName].voted += item.voted_count;
          });
          
          // Convert to arrays
          labels = Object.keys(aggregatedData);
          eligible = labels.map(label => aggregatedData[label].eligible);
          tr = labels.map(label => {
            const turnoutRate = aggregatedData[label].eligible > 0 
              ? Math.round((aggregatedData[label].voted / aggregatedData[label].eligible) * 100, 1) 
              : 0;
            return turnoutRate;
          });
        } else {
          // For a specific college
          const filteredData = chartData.voters.position.filter(item => item.college === selectedCollege);
          labels = filteredData.map(item => getPositionDisplayName(item.position));
          eligible = filteredData.map(item => item.eligible_count);
          tr = filteredData.map(item => item.turnout_rate);
        }
        
        data = {
          labels,
          datasets:[
            { label:'Eligible Voters', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:`Eligible Voters vs Turnout Rate by Position (${selectedCollege === 'all' ? 'All Colleges/Departments' : selectedCollege})`, font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      }
      <?php endif; ?>
      else if (currentBreakdown === 'status') {
        let labels, eligible, tr;
        
        if (selectedCollege === 'all') {
          // Aggregate statuses across all colleges
          let aggregatedData = {};
          chartData.voters.status.forEach(item => {
            const statusName = item.status;
            if (!aggregatedData[statusName]) {
              aggregatedData[statusName] = { eligible: 0, voted: 0 };
            }
            aggregatedData[statusName].eligible += item.eligible_count;
            aggregatedData[statusName].voted += item.voted_count;
          });
          
          // Convert to arrays
          labels = Object.keys(aggregatedData);
          eligible = labels.map(label => aggregatedData[label].eligible);
          tr = labels.map(label => {
            const turnoutRate = aggregatedData[label].eligible > 0 
              ? Math.round((aggregatedData[label].voted / aggregatedData[label].eligible) * 100, 1) 
              : 0;
            return turnoutRate;
          });
        } else {
          // For a specific college
          const filteredData = chartData.voters.status.filter(item => item.college_name === selectedCollege);
          labels = filteredData.map(item => item.status);
          eligible = filteredData.map(item => item.eligible_count);
          tr = filteredData.map(item => item.turnout_rate);
        }
        
        data = {
          labels,
          datasets:[
            { label:'Eligible Voters', data: eligible, backgroundColor:'#1E6F46', borderColor:'#154734', borderWidth:1, borderRadius:4, yAxisID:'y' },
            { label:'Turnout Rate (%)', data: tr, backgroundColor:'#FFD166', borderColor:'#F59E0B', borderWidth:1, borderRadius:4, yAxisID:'y1' }
          ]
        };
        options = {
          responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ position:'top', labels:{ font:{size:12}, padding:15 }}, title:{ display:true, text:`Eligible Voters vs Turnout Rate by Status (${selectedCollege === 'all' ? 'All Colleges/Departments' : selectedCollege})`, font:{ size:16, weight:'bold' }, padding:{top:10,bottom:20}}},
          scales:{ y:{ beginAtZero:true, position:'left', title:{display:true, text:'Number of Voters', font:{size:14, weight:'bold'}}},
                   y1:{ beginAtZero:true, max:100, position:'right', title:{display:true, text:'Turnout Rate (%)', font:{size:14, weight:'bold'}}, ticks:{ callback:(v)=> v+'%' }, grid:{ drawOnChartArea:false }},
                   x:{ grid:{display:false}}}
        };
      }
    }
    
    electionsVsTurnoutChartInstance = new Chart(ctx, { type:'bar', data, options });
    buildTurnoutBreakdownTable();
  }
  
  function buildTurnoutBreakdownTable() {
    const container = document.getElementById('turnoutBreakdownTable');
    container.innerHTML = '';
    let headers = [], rows = [];
    const selectedCollege = turnoutCollegeSelect ? turnoutCollegeSelect.value : 'all';
    
    if (currentDataSeries === 'elections') {
      headers = ['Year','Number of Elections','Turnout Rate'];
      rows = chartData.elections.year.labels.map((label,i)=> [label, chartData.elections.year.electionCounts[i].toLocaleString(), chartData.elections.year.turnoutRates[i] + '%']);
    } else {
      if (currentBreakdown === 'year') {
        headers = ['Year','Eligible Voters','Voted','Turnout Rate'];
        // Calculate voted count from eligible count and turnout rate
        rows = chartData.voters.year.labels.map((label,i)=> {
          const eligible = chartData.voters.year.eligibleCounts[i];
          const turnoutRate = chartData.voters.year.turnoutRates[i];
          const voted = Math.round(eligible * turnoutRate / 100);
          return [label, eligible.toLocaleString(), voted.toLocaleString(), turnoutRate + '%'];
        });
      } 
      <?php if ($isCoopAdmin): ?>
      else if (currentBreakdown === 'college') {
        if (selectedCollege === 'all') {
          headers = ['College/Department','Eligible Voters','Voted','Turnout Rate'];
          rows = chartData.voters.college.map(row => [
            collegeFullNameMap[row.college] || row.college, 
            row.eligible_count.toLocaleString(), 
            row.voted_count.toLocaleString(), 
            row.turnout_rate + '%'
          ]);
        } else {
          headers = ['College/Department','Eligible Voters','Voted','Turnout Rate'];
          const filteredData = chartData.voters.college.filter(item => item.college === selectedCollege);
          rows = filteredData.map(row => [
            collegeFullNameMap[row.college] || row.college, 
            row.eligible_count.toLocaleString(), 
            row.voted_count.toLocaleString(), 
            row.turnout_rate + '%'
          ]);
        }
      } else if (currentBreakdown === 'position') {
        if (selectedCollege === 'all') {
          headers = ['Position','Eligible Voters','Voted','Turnout Rate'];
          
          // Aggregate positions across all colleges
          let aggregatedData = {};
          chartData.voters.position.forEach(item => {
            const positionName = getPositionDisplayName(item.position);
            if (!aggregatedData[positionName]) {
              aggregatedData[positionName] = { eligible: 0, voted: 0 };
            }
            aggregatedData[positionName].eligible += item.eligible_count;
            aggregatedData[positionName].voted += item.voted_count;
          });
          
          // Create rows
          Object.keys(aggregatedData).forEach(positionName => {
            const turnoutRate = aggregatedData[positionName].eligible > 0 
              ? Math.round((aggregatedData[positionName].voted / aggregatedData[positionName].eligible) * 100, 1) 
              : 0;
            rows.push([
              positionName, 
              aggregatedData[positionName].eligible.toLocaleString(), 
              aggregatedData[positionName].voted.toLocaleString(), 
              turnoutRate + '%'
            ]);
          });
        } else {
          headers = ['Position','Eligible Voters','Voted','Turnout Rate'];
          const filteredData = chartData.voters.position.filter(item => item.college === selectedCollege);
          rows = filteredData.map(row => {
            // Map position values to proper display names
            let positionName = getPositionDisplayName(row.position);
            return [
              positionName, 
              row.eligible_count.toLocaleString(), 
              row.voted_count.toLocaleString(), 
              row.turnout_rate + '%'
            ];
          });
        }
      }
      <?php endif; ?>
      else if (currentBreakdown === 'status') {
        if (selectedCollege === 'all') {
          headers = ['Status','Eligible Voters','Voted','Turnout Rate'];
          
          // Aggregate statuses across all colleges
          let aggregatedData = {};
          chartData.voters.status.forEach(item => {
            const statusName = item.status;
            if (!aggregatedData[statusName]) {
              aggregatedData[statusName] = { eligible: 0, voted: 0 };
            }
            aggregatedData[statusName].eligible += item.eligible_count;
            aggregatedData[statusName].voted += item.voted_count;
          });
          
          // Create rows
          Object.keys(aggregatedData).forEach(statusName => {
            const turnoutRate = aggregatedData[statusName].eligible > 0 
              ? Math.round((aggregatedData[statusName].voted / aggregatedData[statusName].eligible) * 100, 1) 
              : 0;
            rows.push([
              statusName, 
              aggregatedData[statusName].eligible.toLocaleString(), 
              aggregatedData[statusName].voted.toLocaleString(), 
              turnoutRate + '%'
            ]);
          });
        } else {
          headers = ['Status','Eligible Voters','Voted','Turnout Rate'];
          const filteredData = chartData.voters.status.filter(item => item.college_name === selectedCollege);
          rows = filteredData.map(row => [
              row.status, 
              row.eligible_count.toLocaleString(), 
              row.voted_count.toLocaleString(), 
              row.turnout_rate + '%'
          ]);
        }
      }
    }
    
    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200';
    const thead = document.createElement('thead');
    const trHead = document.createElement('tr');
    trHead.className = 'bg-gray-50';
    headers.forEach(h => {
      const th = document.createElement('th');
      th.className = 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
      th.textContent = h;
      trHead.appendChild(th);
    });
    thead.appendChild(trHead);
    table.appendChild(thead);
    
    const tbody = document.createElement('tbody');
    tbody.className = 'bg-white divide-y divide-gray-200';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-gray-50';
      r.forEach((cell, idx) => {
        const td = document.createElement('td');
        td.className = 'px-6 py-4 whitespace-nowrap ' + (idx===0?'font-medium text-gray-900':'text-gray-700');
        td.textContent = cell;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }
  
  // Add event listeners
  dataSeriesSelect.addEventListener('change', handleDataSeriesChange);
  breakdownSelect.addEventListener('change', handleBreakdownChange);
  turnoutCollegeSelect?.addEventListener('change', renderElectionsVsTurnout);
  
  // Initialize the page with the correct state
  handleDataSeriesChange();

  // === NEW: Detailed Analytics Section ===
  
  // Data for donut chart
  const donutData = {
    labels: <?= json_encode($isCoopAdmin ? array_column($votersByCollege, 'college_name') : array_column($votersByDepartment, 'department_name')) ?>,
    counts: <?= json_encode($isCoopAdmin ? array_column($votersByCollege, 'count') : array_column($votersByDepartment, 'count')) ?>
  };

  // Data for bar chart
  const barData = {
    position: <?= json_encode($collegePositionBar) ?>,
    status: <?= json_encode($collegeStatusBar) ?>
  };

  // Create donut chart
  const donutCtx = document.getElementById('donutChart');
  if (donutCtx) {
    const donutChart = new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: donutData.labels.map(label => getPositionAbbrevJS(label)),
        datasets: [{
          data: donutData.counts,
          backgroundColor: [
            '#1E6F46', '#37A66B', '#FFD166', '#154734', '#2D5F3F', 
            '#4A7C59', '#5A8F6A', '#6A9F7A', '#7AAFAA', '#8ABFBA'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              font: { size: 12 },
              padding: 15
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.raw || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((value / total) * 100);
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        },
        cutout: '55%'
      }
    });
  }

  // Create bar chart
  const barCtx = document.getElementById('barChart');
  if (barCtx) {
    let barChart;

    // Function to get bar chart data based on selected college and breakdown type
    function getBarChartData(breakdownType, college) {
      let labels, counts;
      
      if (breakdownType === 'all') {
        // For "all" breakdown, use the donut data (all colleges)
        labels = donutData.labels.map(label => getPositionAbbrevJS(label));
        counts = donutData.counts;
      } else {
        if (college === 'all') {
          // Aggregate across colleges for position and status
          let aggregatedData = {};
          
          if (breakdownType === 'position') {
            barData.position.forEach(item => {
              const positionName = getPositionDisplayName(item.position);
              if (!aggregatedData[positionName]) {
                aggregatedData[positionName] = 0;
              }
              aggregatedData[positionName] += item.count;
            });
          } else if (breakdownType === 'status') {
            barData.status.forEach(item => {
              const statusName = item.status;
              if (!aggregatedData[statusName]) {
                aggregatedData[statusName] = 0;
              }
              aggregatedData[statusName] += item.count;
            });
          }
          
          // Convert to arrays
          labels = Object.keys(aggregatedData);
          counts = labels.map(label => aggregatedData[label]);
        } else {
          // For a specific college
          const data = barData[breakdownType].filter(item => item.college_name === college);
          
          if (breakdownType === 'status') {
            labels = data.map(item => item.status);
          } else if (breakdownType === 'position') {
            labels = data.map(item => getPositionDisplayName(item.position));
          }
          
          counts = data.map(item => item.count);
        }
      }
      
      return { labels, counts };
    }

    // Initial bar chart
    const initialCollege = document.getElementById('detailedCollegeSelect').value;
    const initialBreakdown = 'all';
    const initialBarData = getBarChartData(initialBreakdown, initialCollege);

    barChart = new Chart(barCtx, {
      type: 'bar',
      data: {
        labels: initialBarData.labels,
        datasets: [{
          label: 'Number of Voters',
          data: initialBarData.counts,
          backgroundColor: '#1E6F46',
          borderColor: '#154734',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          title: {
            display: true,
            text: initialBreakdown === 'all' ? 'All Colleges/Departments' : (initialBreakdown === 'position' ? 'Positions' : 'Status'),
            font: {
              size: 16,
              weight: 'bold'
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Number of Voters'
            }
          },
          x: {
            title: {
              display: true,
              text: initialBreakdown === 'all' ? 'College/Department' : (initialBreakdown === 'position' ? 'Position' : 'Status')
            },
            ticks: {
              maxRotation: 0,
              autoSkip: false
            }
          }
        }
      }
    });

    // Event listeners for detailed analytics
    const detailedBreakdownSelect = document.getElementById('detailedBreakdownSelect');
    const detailedCollegeSelector = document.getElementById('detailedCollegeSelector');
    const detailedCollegeSelect = document.getElementById('detailedCollegeSelect');
    const detailedCollegeLabel = document.getElementById('detailedCollegeLabel');

    // Function to handle breakdown change
    function handleDetailedBreakdownChange() {
      const value = detailedBreakdownSelect.value;
      
      // Enable/disable the college selector based on breakdown type
      if (value === 'position' || value === 'status') {
        detailedCollegeSelect.disabled = false;
        detailedCollegeLabel.classList.remove('label-disabled');
      } else {
        detailedCollegeSelect.disabled = true;
        detailedCollegeLabel.classList.add('label-disabled');
      }
      
      updateDetailedAnalytics();
    }

    // Show/hide college selector based on breakdown selection
    detailedBreakdownSelect.addEventListener('change', handleDetailedBreakdownChange);

    // Update chart when college selection changes
    detailedCollegeSelect.addEventListener('change', function() {
      updateDetailedAnalytics();
    });

    function updateDetailedAnalytics() {
      const breakdownType = detailedBreakdownSelect.value;
      const college = detailedCollegeSelect.value;
      
      // Update bar chart
      const barData = getBarChartData(breakdownType, college);
      
      barChart.data.labels = barData.labels;
      barChart.data.datasets[0].data = barData.counts;
      
      // Set chart title
      let titleText;
      if (breakdownType === 'all') {
        titleText = 'All Colleges/Departments';
      } else if (breakdownType === 'position') {
        titleText = college === 'all' ? 'Positions (All Colleges/Departments)' : 'Positions';
      } else if (breakdownType === 'status') {
        titleText = college === 'all' ? 'Statuses (All Colleges/Departments)' : 'Statuses';
      }
      barChart.options.plugins.title.text = titleText;
      
      barChart.options.scales.x.title.text = 
        breakdownType === 'all' ? 'College/Department' : 
        (breakdownType === 'position' ? 'Position' : 'Status');
      
      barChart.update();
      
      // Update detailed table
      updateDetailedTable(breakdownType, college);
    }

    function updateDetailedTable(breakdownType, college) {
      const tableHeader = document.getElementById('detailedTableHeader');
      const tableBody = document.getElementById('detailedTableBody');
      
      // Clear existing content
      tableHeader.innerHTML = '';
      tableBody.innerHTML = '';
      
      // Set headers based on breakdown type
      if (breakdownType === 'all') {
        tableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        
        // Add all colleges data
        <?php if ($isCoopAdmin): ?>
        const allCollegesData = <?= json_encode($votersByCollege) ?>;
        allCollegesData.forEach(item => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-gray-50';
          row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${collegeFullNameMap[item.college_name] || item.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(row);
        });
        <?php else: ?>
        const allDepartmentsData = <?= json_encode($votersByDepartment) ?>;
        allDepartmentsData.forEach(item => {
          const row = document.createElement('tr');
          row.className = 'hover:bg-gray-50';
          row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${item.department_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
          `;
          tableBody.appendChild(row);
        });
        <?php endif; ?>
      } else if (breakdownType === 'position') {
        if (college === 'all') {
          tableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          
          // Aggregate positions across all colleges
          let aggregatedData = {};
          barData.position.forEach(item => {
            const positionName = getPositionDisplayName(item.position);
            if (!aggregatedData[positionName]) {
              aggregatedData[positionName] = 0;
            }
            aggregatedData[positionName] += item.count;
          });
          
          // Create rows
          Object.keys(aggregatedData).forEach(positionName => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${positionName}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${aggregatedData[positionName].toLocaleString()}</td>
            `;
            tableBody.appendChild(row);
          });
        } else {
          tableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          
          const positionData = barData.position.filter(item => item.college_name === college);
          positionData.forEach(item => {
            const positionName = getPositionDisplayName(item.position);
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${collegeFullNameMap[item.college_name] || item.college_name}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${positionName}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
            `;
            tableBody.appendChild(row);
          });
        }
      } else if (breakdownType === 'status') {
        if (college === 'all') {
          tableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          
          // Aggregate statuses across all colleges
          let aggregatedData = {};
          barData.status.forEach(item => {
            const statusName = item.status;
            if (!aggregatedData[statusName]) {
              aggregatedData[statusName] = 0;
            }
            aggregatedData[statusName] += item.count;
          });
          
          // Create rows
          Object.keys(aggregatedData).forEach(statusName => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${statusName}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${aggregatedData[statusName].toLocaleString()}</td>
            `;
            tableBody.appendChild(row);
          });
        } else {
          tableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          
          const statusData = barData.status.filter(item => item.college_name === college);
          statusData.forEach(item => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';
            row.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${collegeFullNameMap[item.college_name] || item.college_name}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.status}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${item.count.toLocaleString()}</td>
            `;
            tableBody.appendChild(row);
          });
        }
      }
    }

    // Initialize with default values
    handleDetailedBreakdownChange();
    
    // If the user is a college admin, disable the college selector
    <?php if (!$isCoopAdmin): ?>
      detailedCollegeSelect.disabled = true;
      detailedCollegeLabel.classList.add('label-disabled');
    <?php endif; ?>
  }
  
  // Year range selectors for turnout analytics
  const fromYearSelect = document.getElementById('fromYear');
  const toYearSelect   = document.getElementById('toYear');

  function submitYearRange() {
    if (!fromYearSelect || !toYearSelect) return;
    const from = fromYearSelect.value;
    const to   = toYearSelect.value;

    const url = new URL(window.location.href);
    if (from) url.searchParams.set('from_year', from); else url.searchParams.delete('from_year');
    if (to)   url.searchParams.set('to_year', to);     else url.searchParams.delete('to_year');

    window.location.href = url.toString();
  }

  fromYearSelect?.addEventListener('change', submitYearRange);
  toYearSelect?.addEventListener('change', submitYearRange);
});
</script>

</body>
</html>