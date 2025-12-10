<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/includes/analytics_scopes.php';

/***************************************************
 * DATABASE CONNECTION
 ***************************************************/
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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/***************************************************
 * AUTH CHECK
 ***************************************************/
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
    header('Location: login.php');
    exit();
}

$userId        = (int) $_SESSION['user_id'];
$scopeCategory = $_SESSION['scope_category'] ?? '';
$adminStatus   = $_SESSION['admin_status']   ?? 'inactive';

/***************************************************
 * FORCE PASSWORD CHANGE FLAG
 ***************************************************/
$stmtFP = $pdo->prepare("SELECT force_password_change FROM users WHERE user_id = :uid");
$stmtFP->execute([':uid' => $userId]);
$forceRow = $stmtFP->fetch();
$force_password_flag = (int)($forceRow['force_password_change'] ?? 0);

/***************************************************
 * SUPER ADMIN IMPERSONATION VIA ?scope_id=
 ***************************************************/
$impersonatedScopeId = getImpersonatedScopeId();
$scopeId = null;

if ($impersonatedScopeId !== null) {
    $seat = fetchScopeSeatById($pdo, $impersonatedScopeId);

    if (!$seat || $seat['scope_type'] !== SCOPE_OTHERS) {
        die('Invalid scope for Others dashboard.');
    }

    $scopeCategory = SCOPE_OTHERS;
    $adminStatus   = 'active';
    $scopeId       = (int)$impersonatedScopeId;
} else {
    // Normal Others admin
    if ($scopeCategory !== SCOPE_OTHERS) {
        header('Location: admin_dashboard_redirect.php');
        exit();
    }

    if ($adminStatus !== 'active') {
        header("Location: login.php?error=" . urlencode("Your admin account is inactive."));
        exit();
    }

    // Resolve Others scope seat
    $scopeStmt = $pdo->prepare("
        SELECT scope_id
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid'   => $userId,
        ':stype' => SCOPE_OTHERS,
    ]);
    if ($row = $scopeStmt->fetch()) {
        $scopeId = (int)$row['scope_id'];
    }
}

if ($scopeId === null || $scopeId === 0) {
    die('No Others scope seat found for this admin.');
}

/***************************************************
 * SCOPED VOTERS (OTHERS)
 ***************************************************/
$scopedOthers = getScopedVoters(
    $pdo,
    SCOPE_OTHERS,
    $scopeId,
    [
        'year_end'      => null,   // no cutoff; per-year eligibility handled later
        'include_flags' => true,
    ]
);

/***************************************************
 * DYNAMIC FIELD DETECTION (FOR BREAKDOWNS)
 ***************************************************/
$hasDepartment  = false; // users.department
$hasPosition    = false; // users.position
$hasStatus      = false; // users.status

foreach ($scopedOthers as $v) {
    if (!empty($v['department']))  $hasDepartment  = true;
    if (!empty($v['position']))    $hasPosition    = true;
    if (!empty($v['status']))      $hasStatus      = true;

    // Early exit if all found
    if ($hasDepartment && $hasPosition && $hasStatus) {
        break;
    }
}

/***************************************************
 * AVAILABLE YEARS (FOR TOP YEAR DROPDOWN)
 ***************************************************/
$stmt = $pdo->prepare("
    SELECT DISTINCT YEAR(created_at) AS year
    FROM users
    WHERE role = 'voter'
      AND is_other_member = 1
      AND owner_scope_id = ?
    ORDER BY year DESC
");
$stmt->execute([$scopeId]);
$availableYears = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$currentYear  = (int)date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

// Ensure current year included
if (!in_array($currentYear, $availableYears, true)) {
    $availableYears[] = $currentYear;
}
rsort($availableYears);

/***************************************************
 * BASIC COUNTS: TOTAL VOTERS, ELECTIONS, ONGOING
 ***************************************************/
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total_voters
    FROM users
    WHERE role = 'voter'
      AND is_other_member = 1
      AND owner_scope_id = ?
");
$stmt->execute([$scopeId]);
$total_voters = (int)($stmt->fetch()['total_voters'] ?? 0);

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total_elections
    FROM elections
    WHERE election_scope_type = :stype
      AND owner_scope_id      = :sid
");
$stmt->execute([
    ':stype' => SCOPE_OTHERS,
    ':sid'   => $scopeId,
]);
$total_elections = (int)($stmt->fetch()['total_elections'] ?? 0);

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS ongoing_elections
    FROM elections
    WHERE election_scope_type = :stype
      AND owner_scope_id      = :sid
      AND status              = 'ongoing'
");
$stmt->execute([
    ':stype' => SCOPE_OTHERS,
    ':sid'   => $scopeId,
]);
$ongoing_elections = (int)($stmt->fetch()['ongoing_elections'] ?? 0);

/***************************************************
 * OPTIONAL: FULL ELECTION LIST FOR THIS SCOPE
 ***************************************************/
$stmt = $pdo->prepare("
    SELECT *
    FROM elections
    WHERE election_scope_type = :stype
      AND owner_scope_id      = :sid
    ORDER BY start_datetime DESC
");
$stmt->execute([
    ':stype' => SCOPE_OTHERS,
    ':sid'   => $scopeId,
]);
$elections = $stmt->fetchAll();

/***************************************************
 * NEW VOTERS THIS MONTH (SUMMARY CARD)
 ***************************************************/
$currentMonthStart = date('Y-m-01 00:00:00');
$currentMonthEnd   = date('Y-m-t 23:59:59');

$newVoters = 0;

foreach ($scopedOthers as $v) {
    $created = $v['created_at'] ?? null;
    if (!$created) continue;

    if ($created >= $currentMonthStart && $created <= $currentMonthEnd) {
        $newVoters++;
    }
}

/***************************************************
 * VOTERS BY "COLLEGE/DEPARTMENT" (USING department)
 ***************************************************/
$votersByCollege = [];
if ($hasDepartment) {
    $agg = [];
    foreach ($scopedOthers as $v) {
        $dept = $v['department'] ?: 'Unspecified';
        if (!isset($agg[$dept])) $agg[$dept] = 0;
        $agg[$dept]++;
    }
    foreach ($agg as $name => $count) {
        $votersByCollege[] = [
            'college_name' => $name,
            'count'        => (int)$count,
        ];
    }
    usort($votersByCollege, fn($a,$b) => $b['count'] <=> $a['count']);
}

/***************************************************
 * BAR DATA FOR POSITION & STATUS BREAKDOWNS
 *   - collegePositionBar: { college_name, position, count }
 *   - collegeStatusBar:   { college_name, status, count }
 ***************************************************/
$collegePositionBar = [];
if ($hasDepartment && $hasPosition) {
    $agg = [];
    foreach ($scopedOthers as $v) {
        $college  = $v['department'] ?: 'Unspecified';
        $position = $v['position']   ?: 'Unspecified';
        $key = $college . '|' . $position;
        if (!isset($agg[$key])) {
            $agg[$key] = [
                'college_name' => $college,
                'position'     => $position,
                'count'        => 0,
            ];
        }
        $agg[$key]['count']++;
    }
    $collegePositionBar = array_values($agg);
}

$collegeStatusBar = [];
if ($hasDepartment && $hasStatus) {
    $agg = [];
    foreach ($scopedOthers as $v) {
        $college = $v['department'] ?: 'Unspecified';
        $status  = $v['status']     ?: 'Unspecified';
        $key = $college . '|' . $status;
        if (!isset($agg[$key])) {
            $agg[$key] = [
                'college_name' => $college,
                'status'       => $status,
                'count'        => 0,
            ];
        }
        $agg[$key]['count']++;
    }
    $collegeStatusBar = array_values($agg);
}

/***************************************************
 * TURNOUT BY YEAR (USING analytics_scopes.php)
 ***************************************************/
$turnoutDataByYear = computeTurnoutByYear(
    $pdo,
    SCOPE_OTHERS,
    $scopeId,
    $scopedOthers,
    [
        'year_from' => null,
        'year_to'   => null,
    ]
);

// Ensure current + previous year exist
$currentYearInt = (int)date('Y');
$prevYearInt    = $currentYearInt - 1;

if (!isset($turnoutDataByYear[$currentYearInt])) {
    $turnoutDataByYear[$currentYearInt] = [
        'year'           => $currentYearInt,
        'total_voted'    => 0,
        'total_eligible' => 0,
        'turnout_rate'   => 0.0,
        'election_count' => 0,
        'growth_rate'    => 0.0,
    ];
}
if (!isset($turnoutDataByYear[$prevYearInt])) {
    $turnoutDataByYear[$prevYearInt] = [
        'year'           => $prevYearInt,
        'total_voted'    => 0,
        'total_eligible' => 0,
        'turnout_rate'   => 0.0,
        'election_count' => 0,
        'growth_rate'    => 0.0,
    ];
}

// Sort years
ksort($turnoutDataByYear);
$allTurnoutYears = array_keys($turnoutDataByYear);

// Year range for table / chart
$defaultYear = $currentYearInt;
$minYear     = $allTurnoutYears ? min($allTurnoutYears) : $defaultYear;
$maxYear     = $allTurnoutYears ? max($allTurnoutYears) : $defaultYear;

$fromYear = isset($_GET['from_year']) ? (int)$_GET['from_year'] : $minYear;
$toYear   = isset($_GET['to_year'])   ? (int)$_GET['to_year']   : $maxYear;

if ($fromYear < $minYear) $fromYear = $minYear;
if ($toYear   > $maxYear) $toYear   = $maxYear;
if ($toYear   < $fromYear) $toYear  = $fromYear;

// Build turnoutRangeData
$turnoutRangeData = [];
for ($y = $fromYear; $y <= $toYear; $y++) {
    if (isset($turnoutDataByYear[$y])) {
        $turnoutRangeData[$y] = $turnoutDataByYear[$y];
    } else {
        $turnoutRangeData[$y] = [
            'year'           => $y,
            'total_voted'    => 0,
            'total_eligible' => 0,
            'turnout_rate'   => 0.0,
            'election_count' => 0,
            'growth_rate'    => 0.0,
        ];
    }
}

// Recompute growth_rate within selected range
$prevY = null;
foreach ($turnoutRangeData as $y => &$dataRow) {
    if ($prevY === null) {
        $dataRow['growth_rate'] = 0.0;
    } else {
        $prevRate = $turnoutRangeData[$prevY]['turnout_rate'] ?? 0.0;
        $currRate = $dataRow['turnout_rate'] ?? 0.0;
        $dataRow['growth_rate'] = $prevRate > 0
            ? round((($currRate - $prevRate) / $prevRate) * 100, 1)
            : 0.0;
    }
    $prevY = $y;
}
unset($dataRow);

$turnoutYears = array_keys($turnoutDataByYear);
sort($turnoutYears);

// Current & previous year summary for cards
$currentYearTurnout  = $turnoutDataByYear[$selectedYear]     ?? ['turnout_rate' => 0, 'election_count' => 0];
$previousYearTurnout = $turnoutDataByYear[$selectedYear - 1] ?? ['turnout_rate' => 0, 'election_count' => 0];

/***************************************************
 * PER-ELECTION STATS WITH ABSTAIN (THIS YEAR)
 ***************************************************/
$electionTurnoutStats = [];
if (!empty($scopedOthers)) {
    $electionTurnoutStats = computePerElectionStatsWithAbstain(
        $pdo,
        SCOPE_OTHERS,
        $scopeId,
        $scopedOthers,
        $selectedYear
    );
}

/***************************************************
 * COLLEGE / POSITION / STATUS TURNOUT
 * (USING SQL LIKE BEFORE, BUT SCOPE='Others')
 ***************************************************/
$collegeTurnoutData  = [];
$positionTurnoutData = [];
$statusTurnoutData   = [];

// College turnout
if ($hasDepartment) {
    $stmt = $pdo->prepare("
        SELECT
            u.department AS college_name,
            COUNT(DISTINCT u.user_id) AS eligible_count,
            COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) AS voted_count
        FROM users u
        LEFT JOIN (
            SELECT DISTINCT voter_id
            FROM votes
            WHERE election_id IN (
                SELECT election_id
                FROM elections
                WHERE election_scope_type = :stype
                  AND owner_scope_id      = :sid
                  AND YEAR(start_datetime) = :year
            )
        ) v ON u.user_id = v.voter_id
        WHERE u.role = 'voter'
          AND u.is_other_member = 1
          AND u.owner_scope_id = :sid2
        GROUP BY college_name
        ORDER BY college_name
    ");
    $stmt->execute([
        ':stype' => SCOPE_OTHERS,
        ':sid'   => $scopeId,
        ':year'  => $selectedYear,
        ':sid2'  => $scopeId,
    ]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $rate = $r['eligible_count'] > 0
            ? round(($r['voted_count'] / $r['eligible_count']) * 100, 1)
            : 0.0;
        $collegeTurnoutData[] = [
            'college'        => $r['college_name'],
            'eligible_count' => (int)$r['eligible_count'],
            'voted_count'    => (int)$r['voted_count'],
            'turnout_rate'   => $rate,
        ];
    }
}

// Position turnout
if ($hasPosition && $hasDepartment) {
    $stmt = $pdo->prepare("
        SELECT
            u.department AS college_name,
            u.position,
            COUNT(DISTINCT u.user_id) AS eligible_count,
            COUNT(DISTINCT CASE WHEN v.voter_id IS NOT NULL THEN u.user_id END) AS voted_count
        FROM users u
        LEFT JOIN (
            SELECT DISTINCT voter_id
            FROM votes
            WHERE election_id IN (
                SELECT election_id
                FROM elections
                WHERE election_scope_type = :stype
                  AND owner_scope_id      = :sid
                  AND YEAR(start_datetime) = :year
            )
        ) v ON u.user_id = v.voter_id
        WHERE u.role = 'voter'
          AND u.is_other_member = 1
          AND u.owner_scope_id = :sid2
        GROUP BY college_name, position
        ORDER BY college_name, position
    ");
    $stmt->execute([
        ':stype' => SCOPE_OTHERS,
        ':sid'   => $scopeId,
        ':year'  => $selectedYear,
        ':sid2'  => $scopeId,
    ]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $rate = $r['eligible_count'] > 0
            ? round(($r['voted_count'] / $r['eligible_count']) * 100, 1)
            : 0.0;
        $positionTurnoutData[] = [
            'college'        => $r['college_name'],
            'position'       => $r['position'],
            'eligible_count' => (int)$r['eligible_count'],
            'voted_count'    => (int)$r['voted_count'],
            'turnout_rate'   => $rate,
        ];
    }
}

// Status turnout
if ($hasStatus) {
    // Collect voted IDs this year
    $stmt = $pdo->prepare("
        SELECT DISTINCT v.voter_id
        FROM votes v
        JOIN elections e ON v.election_id = e.election_id
        WHERE e.election_scope_type = :stype
          AND e.owner_scope_id      = :sid
          AND YEAR(e.start_datetime) = :year
    ");
    $stmt->execute([
        ':stype' => SCOPE_OTHERS,
        ':sid'   => $scopeId,
        ':year'  => $selectedYear,
    ]);
    $votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $votedSet = array_flip($votedIds);

    // Group by college + position + status
    $statusGroups = [];
    foreach ($scopedOthers as $v) {
        $college  = $v['department'] ?: 'UNKNOWN';
        $position = $v['position']   ?: 'Unspecified';
        $status   = $v['status']     ?: 'Not Specified';
        $uid      = (int)$v['user_id'];

        $key = $college . '|' . $position . '|' . $status;
        if (!isset($statusGroups[$key])) {
            $statusGroups[$key] = [
                'college_name'   => $college,
                'position'       => $position,
                'status'         => $status,
                'eligible_count' => 0,
                'voted_count'    => 0,
            ];
        }
        $statusGroups[$key]['eligible_count']++;
        if (isset($votedSet[$uid])) {
            $statusGroups[$key]['voted_count']++;
        }
    }

    foreach ($statusGroups as $data) {
        $rate = $data['eligible_count'] > 0
            ? round(($data['voted_count'] / $data['eligible_count']) * 100, 1)
            : 0.0;
        $statusTurnoutData[] = [
            'college_name'   => $data['college_name'],
            'position'       => $data['position'],
            'status'         => $data['status'],
            'eligible_count' => (int)$data['eligible_count'],
            'voted_count'    => (int)$data['voted_count'],
            'turnout_rate'   => (float)$rate,
        ];
    }

    usort($statusTurnoutData, fn($a,$b) => $b['eligible_count'] <=> $a['eligible_count']);
}

/***************************************************
 * ABSTAIN BY YEAR (USING analytics_scopes.php)
 ***************************************************/
$abstainAllYears   = [];
$abstainByYear     = [];
$abstainYears      = [];
$abstainCountsYear = [];
$abstainRatesYear  = [];

if (!empty($scopedOthers)) {
    $abstainAllYears = computeAbstainByYear(
        $pdo,
        SCOPE_OTHERS,
        $scopeId,
        $scopedOthers,
        [
            'year_from' => null,
            'year_to'   => null,
        ]
    );

    if (!empty($turnoutRangeData)) {
        $minY = min(array_keys($turnoutRangeData));
        $maxY = max(array_keys($turnoutRangeData));
    } else {
        $minY = (int)date('Y');
        $maxY = $minY;
    }

    for ($y = $minY; $y <= $maxY; $y++) {
        if (isset($abstainAllYears[$y])) {
            $abstainByYear[$y] = $abstainAllYears[$y];
        } else {
            $abstainByYear[$y] = [
                'year'           => $y,
                'abstain_count'  => 0,
                'total_eligible' => 0,
                'abstain_rate'   => 0.0,
            ];
        }
    }

    $abstainYears = array_keys($abstainByYear);
    sort($abstainYears);

    foreach ($abstainYears as $y) {
        $abstainCountsYear[] = (int)($abstainByYear[$y]['abstain_count']  ?? 0);
        $abstainRatesYear[]  = (float)($abstainByYear[$y]['abstain_rate'] ?? 0.0);
    }
}

/***************************************************
 * COLLEGES LIST (FOR SELECT BOXES)
 ***************************************************/
$allColleges = [];
if ($hasDepartment) {
    $seen = [];
    foreach ($scopedOthers as $v) {
        $dept = $v['department'] ?: 'Unspecified';
        $seen[$dept] = true;
    }
    $allColleges = array_keys($seen);
    sort($allColleges);
}

/***************************************************
 * PAGE LABELS
 ***************************************************/
$pageTitle       = "OTHERS ADMIN DASHBOARD";
$pageSubtitle    = "Others – Custom Uploaded Groups";
$collegeFullName = "All Colleges/Departments"; // used by UI; safe generic
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

    .scope-badge {
      display:inline-block;
      background-color: var(--cvsu-green);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: bold;
      margin-bottom: 0;
    }

    .modal-backdrop {
      background-color: rgba(0,0,0,0.7);
      z-index: 9999;
    }
    .password-strength-bar {
      transition: width 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<!-- Force Password Change Modal -->
<div id="forcePasswordChangeModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative">
      <button onclick="closePasswordModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl">
          &times;
      </button>
      
      <div class="text-center mb-6">
        <div class="w-16 h-16 bg-[var(--cvsu-green-light)] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-[var(--cvsu-green-dark)]">Change Your Password</h2>
        <p class="text-gray-600 mt-2">For security reasons, you must change your password before continuing.</p>
      </div>
      
      <form id="forcePasswordChangeForm" class="space-y-5">
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <div class="relative">
                  <input type="password" id="newPassword" name="new_password" required 
                         class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                  <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268-2.943 9.542-7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div class="mt-2">
                  <div class="flex items-center text-xs text-gray-500 mb-1">
                      <span id="passwordStrength" class="font-medium">Password strength:</span>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                          <div id="strengthBar" class="h-2 rounded-full password-strength-bar" style="width: 0%"></div>
                      </div>
                  </div>
                  <ul class="text-xs text-gray-500 space-y-1">
                      <li id="lengthCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          At least 8 characters
                      </li>
                      <li id="uppercaseCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains uppercase letter
                      </li>
                      <li id="numberCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains number
                      </li>
                      <li id="specialCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains special character
                      </li>
                  </ul>
              </div>
          </div>
          
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <div class="relative">
                  <input type="password" id="confirmPassword" name="confirm_password" required 
                         class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                  <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268-2.943 9.542-7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div id="matchError" class="mt-1 text-xs text-red-500 hidden">Passwords do not match</div>
          </div>
          
          <div id="notificationContainer" class="space-y-3">
              <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-red-800">Error</h3>
                          <div class="mt-1 text-sm text-red-700" id="passwordErrorText"></div>
                      </div>
                  </div>
              </div>
              
              <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-green-800">Success</h3>
                          <div class="mt-1 text-sm text-green-700">
                              Password updated successfully! Redirecting to dashboard...
                          </div>
                      </div>
                  </div>
              </div>
              
              <div id="passwordLoading" class="hidden bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="animate-spin h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-blue-800">Processing</h3>
                          <div class="mt-1 text-sm text-blue-700">
                              Updating your password...
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <div class="flex justify-center pt-4">
              <button type="submit" id="submitBtn" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-8 py-3 rounded-lg font-medium flex items-center justify-center min-w-[180px] transition-all duration-200 transform hover:scale-105">
                  <span id="submitBtnText">Update Password</span>
                  <svg id="submitLoader" class="ml-2 h-5 w-5 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </button>
          </div>
      </form>
  </div>
</div>

<div class="flex min-h-screen">
<?php
  // If super admin is impersonating a scope seat, use super admin sidebar
  if (function_exists('isSuperAdmin') && isSuperAdmin() && getImpersonatedScopeId() !== null) {
      include 'super_admin_sidebar.php';
  } else {
      include 'sidebar.php';
  }
?>
<?php 
if ($force_password_flag !== 1) {
  include 'admin_change_password_modal.php';
}
?>

<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:var(--cvsu-green-dark);">
  <div class="flex flex-col">
    <h1 class="text-2xl font-bold text-white">
      <?= htmlspecialchars($pageTitle) ?>
    </h1>

    <?php if (function_exists('isSuperAdmin') && isSuperAdmin() && getImpersonatedScopeId() !== null): ?>
      <span class="inline-flex items-center max-w-fit mt-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-yellow-300 text-gray-900 shadow-sm">
        Viewing as Others Admin
      </span>
    <?php endif; ?>
  </div>

  <div class="text-white">
    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2z"/>
    </svg>
  </div>
</header>

<main class="flex-1 pt-20 px-8 ml-64">

  <!-- Statistics Cards (TOP) -->
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
        <h2 class="text-2xl font-bold text-white">Others Admin Analytics</h2>
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
      <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="p-4 rounded-lg border" style="background-color: rgba(30,111,70,0.05); border-color: var(--cvsu-green-light);">
          <div class="flex items-center">
            <div class="p-3 rounded-lg mr-4" style="background-color: var(--cvsu-green-light);">
              <i class="fas fa-user-plus text-white text-xl"></i>
            </div>
            <div>
              <p class="text-sm" style="color: var(--cvsu-green);">New This Month</p>
              <p class="text-2xl font-bold" style="color: var(--cvsu-green-dark);">
                <?= number_format($newVoters) ?>
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
                <?= $hasDepartment ? count($allColleges) : 0 ?>
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
                  if ($hasStatus) {
                      $statusSet = [];
                      foreach ($scopedOthers as $v) {
                          if (!empty($v['status'])) {
                              $statusSet[$v['status']] = true;
                          }
                      }
                      echo count($statusSet);
                  } else {
                      echo 0;
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
            Voters by College/Department
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
              <!-- Populated by JS -->
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
      $currentYearTurnout = $currentYearTurnout ?? ['turnout_rate' => 0, 'election_count' => 0];
      $previousYearTurnout = $previousYearTurnout ?? ['turnout_rate' => 0, 'election_count' => 0];
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
                <option value="abstained">Abstained</option>
              </select>
            </div>
            <div class="flex items-center">
              <label for="breakdownSelect" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
              <select id="breakdownSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <option value="year">Year</option>
                <option value="college">College/Department</option>
                <option value="position">Position</option>
                <option value="status">Status</option>
              </select>
            </div>
            <div id="turnoutCollegeSelector" class="flex items-center" style="display: none;">
              <label for="turnoutCollegeSelect" class="mr-3 text-sm font-medium text-gray-700">Select College/Department:</label>
              <select id="turnoutCollegeSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm">
                <!-- Options populated by JS -->
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

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  /* =========================================
   * 1. FORCE PASSWORD CHANGE (ADMIN)
   * =======================================*/
  const forcePasswordChange = <?= $force_password_flag ?>;
  const modal = document.getElementById('forcePasswordChangeModal');
  const passwordInput = document.getElementById('newPassword');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const togglePassword = document.getElementById('togglePassword');
  const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
  const forcePasswordChangeForm = document.getElementById('forcePasswordChangeForm');
  const passwordError = document.getElementById('passwordError');
  const passwordSuccess = document.getElementById('passwordSuccess');
  const passwordLoading = document.getElementById('passwordLoading');
  const passwordErrorText = document.getElementById('passwordErrorText');
  const submitBtn = document.getElementById('submitBtn');
  const submitBtnText = document.getElementById('submitBtnText');
  const submitLoader = document.getElementById('submitLoader');

  if (forcePasswordChange === 1 && modal) {
    modal.classList.remove('hidden');
    document.body.style.pointerEvents = 'none';
    modal.style.pointerEvents = 'auto';
  }

  function updateCheck(id, isValid) {
    const element = document.getElementById(id);
    if (!element) return;
    const icon = element.querySelector('svg');
    if (isValid) {
      element.classList.remove('text-gray-500');
      element.classList.add('text-green-500');
      if (icon) {
        icon.classList.remove('text-gray-400');
        icon.classList.add('text-green-500');
      }
    } else {
      element.classList.remove('text-green-500');
      element.classList.add('text-gray-500');
      if (icon) {
        icon.classList.remove('text-green-500');
        icon.classList.add('text-gray-400');
      }
    }
  }

  function checkPasswordMatch() {
    if (!passwordInput || !confirmPasswordInput) return;
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    const matchError = document.getElementById('matchError');
    if (!matchError) return;
    if (confirmPassword && password !== confirmPassword) {
      matchError.classList.remove('hidden');
      confirmPasswordInput.classList.add('border-red-500');
    } else {
      matchError.classList.add('hidden');
      confirmPasswordInput.classList.remove('border-red-500');
    }
  }

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function () {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
    });
  }

  if (toggleConfirmPassword && confirmPasswordInput) {
    toggleConfirmPassword.addEventListener('click', function () {
      const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      confirmPasswordInput.setAttribute('type', type);
    });
  }

  if (passwordInput) {
    passwordInput.addEventListener('input', function () {
      const password = this.value;
      const strengthBar = document.getElementById('strengthBar');
      const strengthText = document.getElementById('passwordStrength');

      const length = password.length >= 8;
      const uppercase = /[A-Z]/.test(password);
      const number = /[0-9]/.test(password);
      const special = /[!@#$%^&*(),.?":{}|<>]/.test(password);

      updateCheck('lengthCheck', length);
      updateCheck('uppercaseCheck', uppercase);
      updateCheck('numberCheck', number);
      updateCheck('specialCheck', special);

      let strength = 0;
      if (length) strength++;
      if (uppercase) strength++;
      if (number) strength++;
      if (special) strength++;

      if (strengthBar && strengthText) {
        const strengthPercentage = (strength / 4) * 100;
        strengthBar.style.width = strengthPercentage + '%';

        if (strength === 0) {
          strengthBar.className = 'h-2 rounded-full bg-red-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Weak';
          strengthText.className = 'font-medium text-red-500';
        } else if (strength === 1) {
          strengthBar.className = 'h-2 rounded-full bg-orange-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Weak';
          strengthText.className = 'font-medium text-orange-500';
        } else if (strength === 2) {
          strengthBar.className = 'h-2 rounded-full bg-yellow-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Medium';
          strengthText.className = 'font-medium text-yellow-500';
        } else if (strength === 3) {
          strengthBar.className = 'h-2 rounded-full bg-blue-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Strong';
          strengthText.className = 'font-medium text-blue-500';
        } else {
          strengthBar.className = 'h-2 rounded-full bg-green-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Strong';
          strengthText.className = 'font-medium text-green-500';
        }
      }

      checkPasswordMatch();
    });
  }

  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
  }

  if (forcePasswordChangeForm && passwordInput && confirmPasswordInput) {
    forcePasswordChangeForm.addEventListener('submit', function (e) {
      e.preventDefault();

      if (passwordError) passwordError.classList.add('hidden');
      if (passwordSuccess) passwordSuccess.classList.add('hidden');
      if (passwordLoading) passwordLoading.classList.remove('hidden');

      const newPassword = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      const length = newPassword.length >= 8;
      const uppercase = /[A-Z]/.test(newPassword);
      const number = /[0-9]/.test(newPassword);
      const special = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);

      let strength = 0;
      if (length) strength++;
      if (uppercase) strength++;
      if (number) strength++;
      if (special) strength++;

      if (!length) {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "Password must be at least 8 characters long.";
        if (passwordError) passwordError.classList.remove('hidden');
        return;
      }

      if (strength < 3) {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "Password is not strong enough. Please include at least 2 of the following: uppercase letter, number, special character.";
        if (passwordError) passwordError.classList.remove('hidden');
        return;
      }

      if (newPassword !== confirmPassword) {
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "Passwords do not match.";
        if (passwordError) passwordError.classList.remove('hidden');
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      if (submitBtnText) submitBtnText.textContent = 'Updating...';
      if (submitLoader) submitLoader.classList.remove('hidden');

      fetch('update_admin_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ new_password: newPassword })
      })
      .then(response => response.json())
      .then(data => {
        if (passwordLoading) passwordLoading.classList.add('hidden');

        if (data.status === 'success') {
          if (passwordSuccess) passwordSuccess.classList.remove('hidden');
          if (submitBtn) submitBtn.disabled = false;
          if (submitBtnText) submitBtnText.textContent = 'Update Password';
          if (submitLoader) submitLoader.classList.add('hidden');

          setTimeout(() => {
            if (modal) modal.classList.add('hidden');
            document.body.style.pointerEvents = 'auto';

            const redirectOverlay = document.createElement('div');
            redirectOverlay.className = 'fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center';
            redirectOverlay.innerHTML = `
              <div class="text-center">
                <svg class="animate-spin h-12 w-12 text-[var(--cvsu-green)] mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-lg font-medium text-gray-700">Redirecting to dashboard...</p>
              </div>
            `;
            document.body.appendChild(redirectOverlay);

            setTimeout(() => {
              window.location.reload();
            }, 1500);
          }, 2000);
        } else {
          if (submitBtn) submitBtn.disabled = false;
          if (submitBtnText) submitBtnText.textContent = 'Update Password';
          if (submitLoader) submitLoader.classList.add('hidden');
          if (passwordErrorText) passwordErrorText.textContent = data.message || "Failed to update password.";
          if (passwordError) passwordError.classList.remove('hidden');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (passwordLoading) passwordLoading.classList.add('hidden');
        if (submitBtn) submitBtn.disabled = false;
        if (submitBtnText) submitBtnText.textContent = 'Update Password';
        if (submitLoader) submitLoader.classList.add('hidden');
        if (passwordErrorText) passwordErrorText.textContent = "An error occurred. Please try again.";
        if (passwordError) passwordError.classList.remove('hidden');
      });
    });
  }

  /* =========================================
   * 2. TURNOUT YEAR SELECTOR
   * =======================================*/
  const turnoutYearSelector = document.getElementById('turnoutYearSelector');
  if (turnoutYearSelector) {
    turnoutYearSelector.addEventListener('change', function () {
      const url = new URL(window.location.href);
      url.searchParams.set('year', this.value);
      window.location.href = url.toString();
    });
  }

  /* =========================================
   * 3. DATA FROM PHP FOR CHARTS
   * =======================================*/
  const turnoutRangeData = <?= json_encode($turnoutRangeData) ?>;
  const turnoutYears = Object.keys(turnoutRangeData);
  const turnoutRates = Object.values(turnoutRangeData).map(r => r.turnout_rate || 0);

  const collegeTurnoutData  = <?= json_encode($collegeTurnoutData) ?>;
  const positionTurnoutData = <?= json_encode($positionTurnoutData) ?>;
  const statusTurnoutData   = <?= json_encode($statusTurnoutData) ?>;

  const votersByCollege     = <?= json_encode($votersByCollege) ?>;
  const collegePositionBar  = <?= json_encode($collegePositionBar) ?>;
  const collegeStatusBar    = <?= json_encode($collegeStatusBar) ?>;
  const allCollegesJS       = <?= json_encode($allColleges) ?>;
  const selectedYearJS      = <?= (int)$selectedYear ?>;

  const allTurnoutYearsJS   = <?= json_encode($allTurnoutYears) ?>;
  const fromYearJS          = <?= (int)$fromYear ?>;
  const toYearJS            = <?= (int)$toYear ?>;

  // Abstain data (from PHP computeAbstainByYear)
  const abstainYearsJS      = <?= json_encode($abstainYears) ?>;
  const abstainCountsYearJS = <?= json_encode($abstainCountsYear) ?>;
  const abstainRatesYearJS  = <?= json_encode($abstainRatesYear) ?>;
  const electionTurnoutStats = <?= json_encode($electionTurnoutStats) ?>;

  const hasDepartment   = <?= $hasDepartment ? 'true' : 'false' ?>;
  const hasPosition     = <?= $hasPosition ? 'true' : 'false' ?>;
  const hasStatus       = <?= $hasStatus ? 'true' : 'false' ?>;

  /* =========================================
   * 4. HELPER MAPS & FUNCTIONS
   * =======================================*/
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

  const collegeFullNameMap = {
    'CAFENR': 'College of Agriculture, Food, Environment and Natural Resources',
    'CEIT'  : 'College of Engineering and Information Technology',
    'CAS'   : 'College of Arts and Sciences',
    'CVMBS' : 'College of Veterinary Medicine and Biomedical Sciences',
    'CED'   : 'College of Education',
    'CEMDS' : 'College of Economics, Management and Development Studies',
    'CSPEAR': 'College of Sports, Physical Education and Recreation',
    'CCJ'   : 'College of Criminal Justice',
    'CON'   : 'College of Nursing',
    'CTHM'  : 'College of Tourism and Hospitality Management',
    'COM'   : 'College of Medicine',
    'GS-OLC': 'Graduate School and Open Learning College'
  };

  const positionStatusAbbrevMap = {
    'academic'     : 'ACAD',
    'non-academic' : 'NON-ACAD',
    'Full-time'    : 'FT',
    'Part-time'    : 'PT',
    'Regular'      : 'REG',
    'Probationary' : 'PROB',
    'Contractual'  : 'CON',
    'General'      : 'GEN'
  };

  function getFullCollegeNameJS(code) {
    return collegeFullNameMap[code] || code;
  }

  function getPositionAbbrevJS(name) {
    if (departmentAbbrevMap[name]) return departmentAbbrevMap[name];
    if (positionStatusAbbrevMap[name]) return positionStatusAbbrevMap[name];

    const excludeWords = ['and','of','the','in','for','at','by'];

    if (name && name.startsWith('College of ')) {
      let rest = name.substring(11).trim();
      let words = rest.split(' ');
      return words
        .filter(w => !excludeWords.includes(w.toLowerCase()))
        .map(w => w[0])
        .join('')
        .toUpperCase();
    }
    if (name === 'General') return 'GEN';
    return name || '';
  }

  function getPositionDisplayName(position) {
    if (position === 'academic') return 'Faculty';
    if (position === 'non-academic') return 'Non-Academic';
    return position || 'N/A';
  }

  /* =========================================
   * 5. TURNOUT TREND LINE CHART
   * =======================================*/
  let turnoutTrendChartInstance = null;
  const turnoutTrendCtx = document.getElementById('turnoutTrendChart');
  if (turnoutTrendCtx) {
    turnoutTrendChartInstance = new Chart(turnoutTrendCtx, {
      type: 'line',
      data: {
        labels: turnoutYears,
        datasets: [{
          label: 'Turnout Rate (%)',
          data: turnoutRates,
          borderColor: '#1E6F46',
          backgroundColor: 'rgba(30,111,70,0.1)',
          borderWidth: 3,
          pointBackgroundColor: '#1E6F46',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 7,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 14 },
            bodyFont:  { size: 13 },
            padding: 12,
            callbacks: {
              label: c => `Turnout: ${c.raw}%`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { callback: v => v + '%' },
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }

  /* =========================================
   * 6. ELECTIONS vs TURNOUT / VOTERS vs TURNOUT / ABSTAINED
   * =======================================*/
  const chartData = {
    elections: {
      year: {
        labels: Object.keys(turnoutRangeData),
        electionCounts: Object.values(turnoutRangeData).map(r => r.election_count || 0),
        turnoutRates:   Object.values(turnoutRangeData).map(r => r.turnout_rate || 0)
      },
      election: (electionTurnoutStats || []).map(e => ({
        title:          e.title,
        total_voted:    e.total_voted || 0,
        turnout_rate:   e.turnout_rate || 0,
        total_eligible: e.total_eligible || 0
      }))
    },

    voters: {
      year: {
        labels: Object.keys(turnoutRangeData),
        eligibleCounts: Object.values(turnoutRangeData).map(r => r.total_eligible || 0),
        turnoutRates:   Object.values(turnoutRangeData).map(r => r.turnout_rate || 0)
      },
      college:  collegeTurnoutData,
      position: positionTurnoutData,
      status:   statusTurnoutData,
      election: (electionTurnoutStats || []).map(e => ({
        title:          e.title,
        total_eligible: e.total_eligible || 0,
        total_voted:    e.total_voted    || 0,
        turnout_rate:   e.turnout_rate   || 0
      }))
    },

    abstained: {
      year: {
        labels: abstainYearsJS || [],
        abstainCounts: abstainCountsYearJS || [],
        abstainRates:  abstainRatesYearJS  || []
      },
      election: (electionTurnoutStats || []).map(e => ({
        title:          e.title,
        abstain_count:  e.abstain_count || 0,
        abstain_rate:   e.abstain_rate  || 0,
        total_eligible: e.total_eligible || 0
      }))
    }
  };

  let currentDataSeries = 'elections';
  let currentBreakdown  = 'year';

  const dataSeriesSelect  = document.getElementById('dataSeriesSelect');
  const breakdownSelect   = document.getElementById('breakdownSelect');
  const turnoutCollegeSelWrapper = document.getElementById('turnoutCollegeSelector');
  const turnoutCollegeSelect = document.getElementById('turnoutCollegeSelect');

  function updateTurnoutCollegeOptions(breakdownType) {
    if (!turnoutCollegeSelect) return;
    turnoutCollegeSelect.innerHTML = '';

    if (breakdownType === 'college') {
      const optAll = document.createElement('option');
      optAll.value = 'all';
      optAll.textContent = 'All Colleges/Departments';
      turnoutCollegeSelect.appendChild(optAll);

      allCollegesJS.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        turnoutCollegeSelect.appendChild(opt);
      });
    } else {
      allCollegesJS.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        turnoutCollegeSelect.appendChild(opt);
      });
      if (!allCollegesJS.length) {
        const opt = document.createElement('option');
        opt.value = 'all';
        opt.textContent = 'All Colleges/Departments';
        turnoutCollegeSelect.appendChild(opt);
      }
    }
  }

  function updateTurnoutCollegeVisibility() {
    if (!breakdownSelect || !turnoutCollegeSelWrapper) return;
    const val = breakdownSelect.value;
    if (val === 'college' || val === 'position' || val === 'status') {
      turnoutCollegeSelWrapper.style.display = 'flex';
      updateTurnoutCollegeOptions(val);
    } else {
      turnoutCollegeSelWrapper.style.display = 'none';
    }
  }

  let electionsVsTurnoutChartInstance = null;

  function renderElectionsVsTurnout() {
    const canvas = document.getElementById('electionsVsTurnoutChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    if (electionsVsTurnoutChartInstance) {
      electionsVsTurnoutChartInstance.destroy();
    }

    const selectedCollege = (turnoutCollegeSelect && turnoutCollegeSelect.value)
      ? turnoutCollegeSelect.value
      : 'all';

    let data, titleText = '', leftLabel = '';

    if (currentDataSeries === 'elections') {
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.elections.year.labels,
          datasets: [
            {
              label: 'Number of Elections',
              data: chartData.elections.year.electionCounts,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: chartData.elections.year.turnoutRates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = 'Elections vs Turnout Rate (By Year)';
        leftLabel = 'Number of Elections';

      } else if (currentBreakdown === 'election') {
        const stats = chartData.elections.election || [];
        data = {
          labels: stats.map(e => e.title),
          datasets: [
            {
              label: 'Voters Participated',
              data: stats.map(e => e.total_voted || 0),
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: stats.map(e => e.turnout_rate || 0),
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Elections vs Turnout Rate (By Election, ${selectedYearJS})`;
        leftLabel = 'Voters Participated';
      }

    } else if (currentDataSeries === 'voters') {
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.voters.year.labels,
          datasets: [
            {
              label: 'Eligible Voters',
              data: chartData.voters.year.eligibleCounts,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: chartData.voters.year.turnoutRates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = 'Eligible Voters vs Turnout Rate (By Year)';
        leftLabel = 'Number of Voters';

      } else if (currentBreakdown === 'election') {
        const stats = chartData.voters.election || [];
        data = {
          labels: stats.map(e => e.title),
          datasets: [
            {
              label: 'Eligible Voters',
              data: stats.map(e => e.total_eligible || 0),
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: stats.map(e => e.turnout_rate || 0),
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Eligible Voters vs Turnout Rate (By Election, ${selectedYearJS})`;
        leftLabel = 'Number of Voters';

      } else if (currentBreakdown === 'college') {
        let filtered = chartData.voters.college;
        if (selectedCollege !== 'all') {
          filtered = filtered.filter(r => r.college === selectedCollege);
        }
        const labels   = filtered.map(r => getPositionAbbrevJS(getFullCollegeNameJS(r.college)));
        const eligible = filtered.map(r => r.eligible_count);
        const rates    = filtered.map(r => r.turnout_rate);
        data = {
          labels,
          datasets: [
            {
              label: 'Eligible Voters',
              data: eligible,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: rates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Eligible Voters vs Turnout Rate by College/Department (${selectedCollege === 'all' ? 'All Colleges/Departments' : selectedCollege})`;
        leftLabel = 'Number of Voters';

      } else if (currentBreakdown === 'position') {
        let labels, eligible, rates;
        if (selectedCollege === 'all') {
          const agg = {};
          (positionTurnoutData || []).forEach(r => {
            const name = getPositionDisplayName(r.position);
            if (!agg[name]) agg[name] = { eligible: 0, voted: 0 };
            agg[name].eligible += r.eligible_count;
            agg[name].voted    += r.voted_count;
          });
          labels   = Object.keys(agg);
          eligible = labels.map(l => agg[l].eligible);
          rates    = labels.map(l =>
            agg[l].eligible > 0 ? Math.round((agg[l].voted / agg[l].eligible) * 1000) / 10 : 0
          );
        } else {
          const filtered = (positionTurnoutData || []).filter(r => r.college === selectedCollege);
          labels   = filtered.map(r => getPositionDisplayName(r.position));
          eligible = filtered.map(r => r.eligible_count);
          rates    = filtered.map(r => r.turnout_rate);
        }
        data = {
          labels,
          datasets: [
            {
              label: 'Eligible Voters',
              data: eligible,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: rates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Eligible Voters vs Turnout Rate by Position (${selectedCollege === 'all' ? 'All Colleges/Departments' : selectedCollege})`;
        leftLabel = 'Number of Voters';

      } else if (currentBreakdown === 'status') {
        let labels, eligible, rates;
        if (selectedCollege === 'all') {
          const agg = {};
          (statusTurnoutData || []).forEach(r => {
            const name = r.status;
            if (!agg[name]) agg[name] = { eligible: 0, voted: 0 };
            agg[name].eligible += r.eligible_count;
            agg[name].voted    += r.voted_count;
          });
          labels   = Object.keys(agg);
          eligible = labels.map(l => agg[l].eligible);
          rates    = labels.map(l =>
            agg[l].eligible > 0 ? Math.round((agg[l].voted / agg[l].eligible) * 1000) / 10 : 0
          );
        } else {
          const filtered = (statusTurnoutData || []).filter(r => r.college_name === selectedCollege);
          labels   = filtered.map(r => r.status);
          eligible = filtered.map(r => r.eligible_count);
          rates    = filtered.map(r => r.turnout_rate);
        }
        data = {
          labels,
          datasets: [
            {
              label: 'Eligible Voters',
              data: eligible,
              backgroundColor: '#1E6F46',
              borderColor: '#154734',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Turnout Rate (%)',
              data: rates,
              backgroundColor: '#FFD166',
              borderColor: '#F59E0B',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Eligible Voters vs Turnout Rate by Status (${selectedCollege === 'all' ? 'All Colleges/Departments' : selectedCollege})`;
        leftLabel = 'Number of Voters';
      }

    } else if (currentDataSeries === 'abstained') {
      if (currentBreakdown === 'year') {
        data = {
          labels: chartData.abstained.year.labels,
          datasets: [
            {
              label: 'Abstained Voters',
              data: chartData.abstained.year.abstainCounts,
              backgroundColor: '#EF4444',
              borderColor: '#B91C1C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Abstain Rate (%)',
              data: chartData.abstained.year.abstainRates,
              backgroundColor: '#F97316',
              borderColor: '#C2410C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = 'Abstained Voters (By Year)';
        leftLabel = 'Abstained Voters';

      } else if (currentBreakdown === 'election') {
        const stats = chartData.abstained.election || [];
        data = {
          labels: stats.map(e => e.title),
          datasets: [
            {
              label: 'Abstained Voters',
              data: stats.map(e => e.abstain_count || 0),
              backgroundColor: '#EF4444',
              borderColor: '#B91C1C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y'
            },
            {
              label: 'Abstain Rate (%)',
              data: stats.map(e => e.abstain_rate || 0),
              backgroundColor: '#F97316',
              borderColor: '#C2410C',
              borderWidth: 1,
              borderRadius: 4,
              yAxisID: 'y1'
            }
          ]
        };
        titleText = `Abstained Voters (By Election, ${selectedYearJS})`;
        leftLabel = 'Abstained Voters';
      }
    }

    const options = {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 600,
        easing: 'easeOutQuad'
      },
      plugins: {
        legend: {
          position: 'top',
          labels: { font: { size: 12 }, padding: 15 }
        },
        title: {
          display: true,
          text: titleText,
          font: { size: 16, weight: 'bold' },
          padding: { top: 10, bottom: 20 }
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleFont: { size: 14 },
          bodyFont:  { size: 13 },
          padding: 12,
          callbacks: {
            label: ctx => {
              const lbl = ctx.dataset.label || '';
              if (lbl.includes('Rate')) return `${lbl}: ${ctx.raw}%`;
              return `${lbl}: ${Number(ctx.raw).toLocaleString()}`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          position: 'left',
          title: {
            display: true,
            text: leftLabel,
            font: { size: 14, weight: 'bold' }
          }
        },
        y1: {
          beginAtZero: true,
          max: 100,
          position: 'right',
          title: {
            display: true,
            text: currentDataSeries === 'abstained' ? 'Abstain Rate (%)' : 'Turnout Rate (%)',
            font: { size: 14, weight: 'bold' }
          },
          ticks: { callback: v => v + '%' },
          grid: { drawOnChartArea: false }
        },
        x: { grid: { display: false } }
      }
    };

    electionsVsTurnoutChartInstance = new Chart(ctx, {
      type: 'bar',
      data,
      options
    });

    buildTurnoutBreakdownTable();
  }

  function buildTurnoutBreakdownTable() {
    const container = document.getElementById('turnoutBreakdownTable');
    if (!container) return;
    container.innerHTML = '';

    const selectedCollege = (turnoutCollegeSelect && turnoutCollegeSelect.value)
      ? turnoutCollegeSelect.value
      : 'all';

    let headers = [];
    let rows    = [];

    if (currentDataSeries === 'elections') {
      if (currentBreakdown === 'year') {
        headers = ['Year', 'Number of Elections', 'Turnout Rate'];
        rows = chartData.elections.year.labels.map((label, i) => [
          label,
          (chartData.elections.year.electionCounts[i] || 0).toLocaleString(),
          (chartData.elections.year.turnoutRates[i]  || 0) + '%'
        ]);
      } else if (currentBreakdown === 'election') {
        headers = ['Election', 'Voters Participated', 'Turnout Rate', 'Eligible Voters'];
        const stats = chartData.elections.election || [];
        rows = stats.map(e => [
          e.title,
          (e.total_voted    || 0).toLocaleString(),
          (e.turnout_rate   || 0) + '%',
          (e.total_eligible || 0).toLocaleString()
        ]);
      }

    } else if (currentDataSeries === 'voters') {
      if (currentBreakdown === 'year') {
        headers = ['Year', 'Eligible Voters', 'Voted (approx)', 'Turnout Rate'];
        rows = chartData.voters.year.labels.map((label, i) => {
          const eligible = chartData.voters.year.eligibleCounts[i] || 0;
          const rate     = chartData.voters.year.turnoutRates[i]   || 0;
          const voted    = Math.round(eligible * rate / 100);
          return [
            label,
            eligible.toLocaleString(),
            voted.toLocaleString(),
            rate + '%'
          ];
        });
      } else if (currentBreakdown === 'election') {
        headers = ['Election', 'Eligible Voters', 'Voters Participated', 'Turnout Rate'];
        const stats = chartData.voters.election || [];
        rows = stats.map(e => [
          e.title,
          (e.total_eligible || 0).toLocaleString(),
          (e.total_voted    || 0).toLocaleString(),
          (e.turnout_rate   || 0) + '%'
        ]);
      } else if (currentBreakdown === 'college') {
        headers = ['College/Department', 'Eligible Voters', 'Voted', 'Turnout Rate'];
        let filtered = chartData.voters.college || [];
        if (selectedCollege !== 'all') {
          filtered = filtered.filter(r => r.college === selectedCollege);
        }
        rows = filtered.map(r => [
          getFullCollegeNameJS(r.college),
          (r.eligible_count || 0).toLocaleString(),
          (r.voted_count    || 0).toLocaleString(),
          (r.turnout_rate   || 0) + '%'
        ]);
      } else if (currentBreakdown === 'position') {
        headers = ['Position', 'Eligible Voters', 'Voted', 'Turnout Rate'];
        if (selectedCollege === 'all') {
          const agg = {};
          (positionTurnoutData || []).forEach(r => {
            const name = getPositionDisplayName(r.position);
            if (!agg[name]) agg[name] = { eligible: 0, voted: 0 };
            agg[name].eligible += r.eligible_count;
            agg[name].voted    += r.voted_count;
          });
          Object.keys(agg).forEach(name => {
            const eligible = agg[name].eligible;
            const voted    = agg[name].voted;
            const rate     = eligible > 0 ? Math.round((voted / eligible) * 1000) / 10 : 0;
            rows.push([
              name,
              eligible.toLocaleString(),
              voted.toLocaleString(),
              rate + '%'
            ]);
          });
        } else {
          const filtered = (positionTurnoutData || []).filter(r => r.college === selectedCollege);
          rows = filtered.map(r => [
            getPositionDisplayName(r.position),
            (r.eligible_count || 0).toLocaleString(),
            (r.voted_count    || 0).toLocaleString(),
            (r.turnout_rate   || 0) + '%'
          ]);
        }
      } else if (currentBreakdown === 'status') {
        headers = ['Status', 'Eligible Voters', 'Voted', 'Turnout Rate'];
        if (selectedCollege === 'all') {
          const agg = {};
          (statusTurnoutData || []).forEach(r => {
            const name = r.status;
            if (!agg[name]) agg[name] = { eligible: 0, voted: 0 };
            agg[name].eligible += r.eligible_count;
            agg[name].voted    += r.voted_count;
          });
          Object.keys(agg).forEach(name => {
            const eligible = agg[name].eligible;
            const voted    = agg[name].voted;
            const rate     = eligible > 0 ? Math.round((voted / eligible) * 1000) / 10 : 0;
            rows.push([
              name,
              eligible.toLocaleString(),
              voted.toLocaleString(),
              rate + '%'
            ]);
          });
        } else {
          const filtered = (statusTurnoutData || []).filter(r => r.college_name === selectedCollege);
          rows = filtered.map(r => [
            r.status,
            (r.eligible_count || 0).toLocaleString(),
            (r.voted_count    || 0).toLocaleString(),
            (r.turnout_rate   || 0) + '%'
          ]);
        }
      }

    } else if (currentDataSeries === 'abstained') {
      if (currentBreakdown === 'year') {
        headers = ['Year', 'Abstained Voters', 'Abstain Rate'];
        rows = (chartData.abstained.year.labels || []).map((label, i) => [
          label,
          (chartData.abstained.year.abstainCounts[i] || 0).toLocaleString(),
          (chartData.abstained.year.abstainRates[i]  || 0) + '%'
        ]);
      } else if (currentBreakdown === 'election') {
        headers = ['Election', 'Abstained Voters', 'Abstain Rate', 'Eligible Voters'];
        const withAbstain = (electionTurnoutStats || []).filter(e => (e.abstain_count || 0) > 0);
        if (!withAbstain.length) {
          const msg = document.createElement('div');
          msg.className = 'text-center text-gray-600 text-sm py-4';
          msg.textContent = `No abstained voters found for ${selectedYearJS}.`;
          container.appendChild(msg);
          return;
        }
        rows = withAbstain.map(e => [
          e.title,
          (e.abstain_count  || 0).toLocaleString(),
          (e.abstain_rate   || 0) + '%',
          (e.total_eligible || 0).toLocaleString()
        ]);
      }
    }

    if (!headers.length) {
      const msg = document.createElement('div');
      msg.className = 'text-center text-gray-500 text-sm py-4';
      msg.textContent = 'No data available for this selection.';
      container.appendChild(msg);
      return;
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
        td.className = 'px-6 py-4 whitespace-nowrap ' +
          (idx === 0 ? 'font-medium text-gray-900' : 'text-gray-700');
        td.textContent = cell;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    container.appendChild(table);
  }

  if (dataSeriesSelect) {
    dataSeriesSelect.addEventListener('change', () => {
      currentDataSeries = dataSeriesSelect.value;

      if (currentDataSeries === 'elections') {
        if (breakdownSelect) {
          breakdownSelect.disabled = false;
          breakdownSelect.innerHTML = `
            <option value="year">Year</option>
            <option value="election">Election (Current Year)</option>
          `;
          breakdownSelect.value = 'year';
        }
        currentBreakdown = 'year';
        if (turnoutCollegeSelWrapper) turnoutCollegeSelWrapper.style.display = 'none';

      } else if (currentDataSeries === 'voters') {
        if (breakdownSelect) {
          breakdownSelect.disabled = false;
          breakdownSelect.innerHTML = `
            <option value="year">Year</option>
            <option value="election">Election (Current Year)</option>
            <option value="college">College/Department</option>
            <option value="position">Position</option>
            <option value="status">Status</option>
          `;
          breakdownSelect.value = 'year';
        }
        currentBreakdown = 'year';
        if (turnoutCollegeSelWrapper) turnoutCollegeSelWrapper.style.display = 'none';

      } else if (currentDataSeries === 'abstained') {
        if (breakdownSelect) {
          breakdownSelect.disabled = false;
          breakdownSelect.innerHTML = `
            <option value="year">Year</option>
            <option value="election">Election (Current Year)</option>
          `;
          breakdownSelect.value = 'year';
        }
        currentBreakdown = 'year';
        if (turnoutCollegeSelWrapper) turnoutCollegeSelWrapper.style.display = 'none';
      }

      renderElectionsVsTurnout();
    });
  }

  if (breakdownSelect) {
    breakdownSelect.addEventListener('change', () => {
      currentBreakdown = breakdownSelect.value;
      updateTurnoutCollegeVisibility();
      renderElectionsVsTurnout();
    });
  }

  if (turnoutCollegeSelect) {
    turnoutCollegeSelect.addEventListener('change', () => {
      renderElectionsVsTurnout();
    });
  }

  if (dataSeriesSelect) dataSeriesSelect.value = 'elections';
  renderElectionsVsTurnout();

  /* =========================================
   * 7. DETAILED ANALYTICS (DONUT + BAR + TABLE)
   * =======================================*/
  const donutData = {
    labels: <?= json_encode(array_column($votersByCollege, 'college_name')) ?>,
    counts: <?= json_encode(array_column($votersByCollege, 'count')) ?>
  };

  const barDataDetailed = {
    position: <?= json_encode($collegePositionBar) ?>,
    status:   <?= json_encode($collegeStatusBar) ?>
  };

  const donutCtx = document.getElementById('donutChart');
  if (donutCtx) {
    new Chart(donutCtx, {
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
        animation: {
          duration: 700,
          easing: 'easeOutQuart'
        },
        plugins: {
          legend: {
            position: 'right',
            labels: {
              font: { size: 12 },
              padding: 10
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            titleFont: { size: 14 },
            bodyFont:  { size: 13 },
            padding: 12,
            callbacks: {
              label: ctx => {
                const label = ctx.label || '';
                const value = ctx.raw || 0;
                return `${label}: ${Number(value).toLocaleString()}`;
              }
            }
          }
        },
        cutout: '55%'
      }
    });
  }

  const barCtx = document.getElementById('barChart');
  if (barCtx) {
    let barChart;

    function getBarChartDataDetailed(breakdownType, college) {
      let labels = [];
      let counts = [];

      if (breakdownType === 'all') {
        labels = donutData.labels.map(label => getPositionAbbrevJS(label));
        counts = donutData.counts;
      } else if (breakdownType === 'position') {
        if (college === 'all') {
          const agg = {};
          (barDataDetailed.position || []).forEach(r => {
            const name = getPositionDisplayName(r.position);
            if (!agg[name]) agg[name] = 0;
            agg[name] += r.count;
          });
          labels = Object.keys(agg);
          counts = labels.map(l => agg[l]);
        } else {
          const filtered = (barDataDetailed.position || []).filter(r => r.college_name === college);
          labels = filtered.map(r => getPositionDisplayName(r.position));
          counts = filtered.map(r => r.count);
        }
      } else if (breakdownType === 'status') {
        if (college === 'all') {
          const agg = {};
          (barDataDetailed.status || []).forEach(r => {
            const name = r.status;
            if (!agg[name]) agg[name] = 0;
            agg[name] += r.count;
          });
          labels = Object.keys(agg);
          counts = labels.map(l => agg[l]);
        } else {
          const filtered = (barDataDetailed.status || []).filter(r => r.college_name === college);
          labels = filtered.map(r => r.status);
          counts = filtered.map(r => r.count);
        }
      }
      return { labels, counts };
    }

    const detailedBreakdownSelect = document.getElementById('detailedBreakdownSelect');
    const detailedCollegeSelect   = document.getElementById('detailedCollegeSelect');
    const detailedCollegeLabel    = document.getElementById('detailedCollegeLabel');
    const detailedTableHeader     = document.getElementById('detailedTableHeader');
    const detailedTableBody       = document.getElementById('detailedTableBody');

    const initialCollege    = detailedCollegeSelect ? detailedCollegeSelect.value : 'all';
    const initialBreakdown  = 'all';
    const initialBarData    = getBarChartDataDetailed(initialBreakdown, initialCollege);

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
        animation: {
          duration: 700,
          easing: 'easeOutQuart'
        },
        plugins: {
          legend: { display: false },
          title: {
            display: true,
            text: 'All Colleges/Departments',
            font: { size: 16, weight: 'bold' }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: { display: true, text: 'Number of Voters' }
          },
          x: {
            title: {
              display: true,
              text: 'College/Department'
            },
            ticks: { maxRotation: 0, autoSkip: false }
          }
        }
      }
    });

    function updateDetailedTable(breakdownType, college) {
      if (!detailedTableHeader || !detailedTableBody) return;
      detailedTableHeader.innerHTML = '';
      detailedTableBody.innerHTML   = '';

      if (breakdownType === 'all') {
        detailedTableHeader.innerHTML = `
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
        `;
        const data = votersByCollege || [];
        data.forEach(r => {
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-gray-50';
          tr.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${getFullCollegeNameJS(r.college_name) || r.college_name}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700">${(r.count || 0).toLocaleString()}</td>
          `;
          detailedTableBody.appendChild(tr);
        });
      } else if (breakdownType === 'position') {
        if (college === 'all') {
          detailedTableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          const agg = {};
          (barDataDetailed.position || []).forEach(r => {
            const name = getPositionDisplayName(r.position);
            if (!agg[name]) agg[name] = 0;
            agg[name] += r.count;
          });
          Object.keys(agg).forEach(name => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${name}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${agg[name].toLocaleString()}</td>
            `;
            detailedTableBody.appendChild(tr);
          });
        } else {
          detailedTableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          (barDataDetailed.position || [])
            .filter(r => r.college_name === college)
            .forEach(r => {
              const tr = document.createElement('tr');
              tr.className = 'hover:bg-gray-50';
              tr.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${getFullCollegeNameJS(r.college_name)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-700">${getPositionDisplayName(r.position)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-700">${(r.count || 0).toLocaleString()}</td>
              `;
              detailedTableBody.appendChild(tr);
            });
        }
      } else if (breakdownType === 'status') {
        if (college === 'all') {
          detailedTableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          const agg = {};
          (barDataDetailed.status || []).forEach(r => {
            const name = r.status;
            if (!agg[name]) agg[name] = 0;
            agg[name] += r.count;
          });
          Object.keys(agg).forEach(name => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${name}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-700">${agg[name].toLocaleString()}</td>
            `;
            detailedTableBody.appendChild(tr);
          });
        } else {
          detailedTableHeader.innerHTML = `
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">College/Department</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Voters</th>
          `;
          (barDataDetailed.status || [])
            .filter(r => r.college_name === college)
            .forEach(r => {
              const tr = document.createElement('tr');
              tr.className = 'hover:bg-gray-50';
              tr.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">${getFullCollegeNameJS(r.college_name)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-700">${r.status}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-700">${(r.count || 0).toLocaleString()}</td>
              `;
              detailedTableBody.appendChild(tr);
            });
        }
      }
    }

    function updateDetailedAnalytics() {
      const breakdownType = detailedBreakdownSelect ? detailedBreakdownSelect.value : 'all';
      const college       = detailedCollegeSelect   ? detailedCollegeSelect.value   : 'all';

      const info = getBarChartDataDetailed(breakdownType, college);
      barChart.data.labels = info.labels;
      barChart.data.datasets[0].data = info.counts;

      if (breakdownType === 'all') {
        barChart.options.plugins.title.text = 'All Colleges/Departments';
        barChart.options.scales.x.title.text = 'College/Department';
      } else if (breakdownType === 'position') {
        barChart.options.plugins.title.text =
          college === 'all' ? 'Positions (All Colleges/Departments)' : 'Positions';
        barChart.options.scales.x.title.text = 'Position';
      } else {
        barChart.options.plugins.title.text =
          college === 'all' ? 'Statuses (All Colleges/Departments)' : 'Statuses';
        barChart.options.scales.x.title.text = 'Status';
      }

      barChart.update();
      updateDetailedTable(breakdownType, college);
    }

    function handleDetailedBreakdownChange() {
      if (!detailedBreakdownSelect || !detailedCollegeSelect || !detailedCollegeLabel) return;
      const val = detailedBreakdownSelect.value;
      if (val === 'position' || val === 'status') {
        detailedCollegeSelect.disabled = false;
        detailedCollegeLabel.classList.remove('label-disabled');
      } else {
        detailedCollegeSelect.disabled = true;
        detailedCollegeLabel.classList.add('label-disabled');
      }
      updateDetailedAnalytics();
    }

    if (detailedBreakdownSelect) {
      detailedBreakdownSelect.addEventListener('change', handleDetailedBreakdownChange);
    }
    if (detailedCollegeSelect) {
      detailedCollegeSelect.addEventListener('change', updateDetailedAnalytics);
    }

    handleDetailedBreakdownChange();
  }

  /* =========================================
   * 8. YEAR RANGE SELECT (TURNOUT TABLE)
   * =======================================*/
  const fromYearSelect = document.getElementById('fromYear');
  const toYearSelect   = document.getElementById('toYear');

  function submitYearRange() {
    if (!fromYearSelect || !toYearSelect) return;
    const from = fromYearSelect.value;
    const to   = toYearSelect.value;
    const url  = new URL(window.location.href);
    if (from) url.searchParams.set('from_year', from); else url.searchParams.delete('from_year');
    if (to)   url.searchParams.set('to_year', to);     else url.searchParams.delete('to_year');

    const yearSel = document.getElementById('turnoutYearSelector');
    if (yearSel && yearSel.value) {
      url.searchParams.set('year', yearSel.value);
    }
    window.location.href = url.toString();
  }

  if (fromYearSelect) fromYearSelect.addEventListener('change', submitYearRange);
  if (toYearSelect)   toYearSelect.addEventListener('change', submitYearRange);
});

// Global close function for modal (used by X button)
function closePasswordModal() {
  const forcePasswordChange = <?= $force_password_flag ?>;
  if (forcePasswordChange === 1) return;
  const modal = document.getElementById('forcePasswordChangeModal');
  if (modal) modal.classList.add('hidden');
  document.body.style.pointerEvents = 'auto';
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const forceFlag = <?= $force_password_flag ?>;

  // ❌ Kapag hindi na forced, huwag galawin si force modal.
  //    Yung global admin_change_password_modal.php na ang bahala.
  if (forceFlag !== 1) {
    return;
  }

  // ✅ Kapag FIRST LOGIN (forceFlag === 1),
  //    sidebar "Change Password" dapat mag-open ng SAME force modal.
  const changeBtn     = document.getElementById('sidebarChangePasswordBtn');
  const passwordModal = document.getElementById('forcePasswordChangeModal');

  if (changeBtn && passwordModal) {
    changeBtn.addEventListener('click', function () {
      passwordModal.classList.remove('hidden');
      document.body.style.pointerEvents = 'none';
      passwordModal.style.pointerEvents = 'auto';
    });
  }
});
</script>

</body>
</html>
