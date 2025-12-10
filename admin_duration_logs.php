<?php
// admin_duration_logs.php
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
} catch (PDOException $e) {
    die("Database connection failed.");
}

/* ==========================================================
   AUTH CHECK — ONLY ADMIN (NOT SUPER ADMIN)
   ========================================================== */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole   = $_SESSION['role'] ?? '';

if ($currentRole === 'super_admin') {
    // Super admin should use the global logs page
    header("Location: super_admin_lifetime_logs.php");
    exit();
}

/* ==========================================================
   LOAD SCOPE INFO FROM SESSION (SAME AS admin_manage_users.php)
   ========================================================== */
$assignedScope  = strtoupper(trim($_SESSION['assigned_scope']   ?? ''));
$scopeCategory  = $_SESSION['scope_category']   ?? '';
$assignedScope1 = $_SESSION['assigned_scope_1'] ?? '';

$scopeSeatId    = null;
$scopeDetails   = [];

if (!empty($scopeCategory)) {
    $stSeat = $pdo->prepare("
        SELECT scope_id, scope_details
        FROM admin_scopes
        WHERE user_id = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $stSeat->execute([
        ':uid'   => $currentUserId,
        ':stype' => $scopeCategory,
    ]);
    $seat = $stSeat->fetch();
    if ($seat) {
        $scopeSeatId = (int)$seat['scope_id'];
        if (!empty($seat['scope_details'])) {
            $decoded = json_decode($seat['scope_details'], true);
            if (is_array($decoded)) {
                $scopeDetails = $decoded;
            }
        }
    }
}

/* ==========================================================
   BUILD SCOPE-AWARE USER CONDITIONS
   ========================================================== */

$scopeWhere  = ["u.role = 'voter'"];
$scopeParams = [];

/*
   We only need to restrict which users the admin can see.
   Then logs are limited to those users.
*/

if ($scopeCategory === 'Non-Academic-Student' && $scopeSeatId !== null) {

    $scopeWhere[]               = "u.position = 'student'";
    $scopeWhere[]               = "u.owner_scope_id = :scopeOwner";
    $scopeParams[':scopeOwner'] = $scopeSeatId;

} elseif ($scopeCategory === 'Others' && $scopeSeatId !== null) {

    $scopeWhere[]               = "u.is_other_member = 1";
    $scopeWhere[]               = "u.owner_scope_id = :scopeOwner";
    $scopeParams[':scopeOwner'] = $scopeSeatId;

} elseif ($scopeCategory === 'Academic-Student' && in_array($assignedScope, [
        'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
        'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
    ], true)) {

    $scopeWhere[]                 = "u.position = 'student'";
    $scopeWhere[]                 = "UPPER(TRIM(u.department)) = :scopeCollege";
    $scopeParams[':scopeCollege'] = $assignedScope;

} elseif ($scopeCategory === 'Academic-Faculty' && in_array($assignedScope, [
        'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
        'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
    ], true)) {

    $scopeWhere[]                 = "u.position = 'academic'";
    $scopeWhere[]                 = "UPPER(TRIM(u.department)) = :scopeCollege";
    $scopeParams[':scopeCollege'] = $assignedScope;

} elseif ($scopeCategory === 'Non-Academic-Employee') {

    $scopeWhere[] = "u.position = 'non-academic'";
    if (!empty($scopeDetails['departments']) && is_array($scopeDetails['departments'])) {
        $deptCodes = array_filter(array_map('trim', $scopeDetails['departments']));
        if (!empty($deptCodes)) {
            $phs = [];
            foreach ($deptCodes as $idx => $code) {
                $ph = ':scopeDept' . $idx;
                $phs[] = $ph;
                $scopeParams[$ph] = $code;  // e.g. ADMIN, LIBRARY
            }
            $scopeWhere[] = "u.department IN (" . implode(',', $phs) . ")";
        }
    }

} elseif ($scopeCategory === 'Special-Scope' || $assignedScope === 'CSG ADMIN') {

    $scopeWhere[] = "u.position = 'student'";

} elseif ($assignedScope === 'FACULTY ASSOCIATION') {

    $scopeWhere[] = "u.position = 'academic'";

} elseif ($assignedScope === 'NON-ACADEMIC') {

    $scopeWhere[] = "u.position = 'non-academic'";

} else {
    // Safety fallback
    $scopeWhere[] = "1 = 0";
}

/* ==========================================================
   DATE + FILTER INPUTS (from GET)
   ========================================================== */

$today     = date('Y-m-d');
$dateError = '';

function normalizeDateOrToday2(string $input, string $today, string &$errLabel, string $fieldLabel): string {
    $dt       = DateTime::createFromFormat('Y-m-d', $input);
    $todayObj = new DateTime($today);

    if (!$dt) {
        $errLabel .= ($errLabel ? ' ' : '') . "$fieldLabel date is invalid. Falling back to today.";
        return $today;
    }

    $dt->setTime(0, 0, 0);
    if ($dt > $todayObj) {
        $errLabel .= ($errLabel ? ' ' : '') . "$fieldLabel date cannot be in the future. Using today instead.";
        return $today;
    }

    return $dt->format('Y-m-d');
}

$search      = trim($_GET['search'] ?? '');
$action      = trim($_GET['action'] ?? '');
$triggerType = trim($_GET['trigger_type'] ?? '');
$reasonCode  = trim($_GET['reason_code'] ?? '');
$position    = trim($_GET['position'] ?? '');

/* Date range: default today → today */
$fromInput = $_GET['from_date'] ?? $today;
$toInput   = $_GET['to_date']   ?? $today;

$fromDate = normalizeDateOrToday2($fromInput, $today, $dateError, 'Start');
$toDate   = normalizeDateOrToday2($toInput,   $today, $dateError, 'End');

if ($fromDate > $toDate) {
    $tmp      = $fromDate;
    $fromDate = $toDate;
    $toDate   = $tmp;
    $dateError .= ($dateError ? ' ' : '') . 'Start date was after end date, so the range has been adjusted.';
}

/* ==========================================================
   HELPER: Reason label formatter
   ========================================================== */
function formatReasonLabel(?string $reason): string {
    if ($reason === null || $reason === '') {
        return '';
    }
    $reason = strtoupper($reason);
    $reason = str_replace('_', ' ', $reason);
    return ucwords(strtolower($reason));
}

/* ==========================================================
   LOG FILTER CONDITIONS
   ========================================================== */
$logWhere  = ["1 = 1"];
$logParams = [];

/* Search BY EMAIL ONLY */
if ($search !== '') {
    $logWhere[]      = "u.email LIKE :s";
    $logParams[':s'] = "%" . $search . "%";
}

/* Action filter */
if ($action !== '') {
    $logWhere[]           = "l.action = :action";
    $logParams[':action'] = $action;
}

/* Trigger type filter */
if ($triggerType !== '') {
    $logWhere[]             = "l.trigger_type = :tt";
    $logParams[':tt']       = $triggerType;
}

/* Reason code filter */
if ($reasonCode !== '') {
    $logWhere[]             = "l.reason_code = :rc";
    $logParams[':rc']       = $reasonCode;
}

/* Position filter (student/academic/non-academic) */
if ($position !== '') {
    $logWhere[]             = "u.position = :pos";
    $logParams[':pos']      = $position;
}

/* Date range filter (always set) */
$logWhere[]             = "DATE(l.created_at) >= :fromDate";
$logParams[':fromDate'] = $fromDate;
$logWhere[]             = "DATE(l.created_at) <= :toDate";
$logParams[':toDate']   = $toDate;

/* Only logs performed by this admin OR auto system logs */
$logWhere[]                    = "(l.admin_id = :currentAdmin OR l.admin_id IS NULL)";
$logParams[':currentAdmin']    = $currentUserId;

/* ==========================================================
   MERGE CONDITIONS
   ========================================================== */

$allWhere   = array_merge($scopeWhere, $logWhere);
$allParams  = array_merge($scopeParams, $logParams);

/* ==========================================================
   BASE SQL (JOIN logs + users)
   ========================================================== */

$baseSql = "
    FROM user_lifetime_logs l
    INNER JOIN users u ON u.user_id = l.user_id
    WHERE " . implode(" AND ", $allWhere);

/* ==========================================================
   PAGINATION
   ========================================================== */

$recordsPerPage = 30;
$page           = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset         = ($page - 1) * $recordsPerPage;

/* Count total logs */
$countSql = "SELECT COUNT(*) " . $baseSql;
$countStmt = $pdo->prepare($countSql);
foreach ($allParams as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, ceil($totalRecords / $recordsPerPage));

/* Fetch paginated logs */
$dataSql = "
    SELECT
        l.*,
        u.first_name, u.last_name, u.email, u.position
    " . $baseSql . "
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($dataSql);
foreach ($allParams as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit',  $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,         PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

/* ==========================================================
   HELPER: Format name
   ========================================================== */
function formatName(string $fn = null, string $ln = null): string {
    $fn = $fn ?? '';
    $ln = $ln ?? '';
    return trim($fn . ' ' . $ln);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account Duration Logs - Admin Scope</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }
    .chip {
      display: inline-flex;
      align-items: center;
      padding: 0.125rem 0.625rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">
  <?php include 'sidebar.php'; ?>
  <?php
    if (file_exists('admin_change_password_modal.php')) {
        include 'admin_change_password_modal.php';
    }
  ?>

  <main class="flex-1 px-8 pb-10 pt-6 ml-64">
    <!-- Header -->
    <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-md rounded-b-md mb-8">
      <div class="flex items-center space-x-4">
        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
          <i class="fas fa-history text-xl"></i>
        </div>
        <div>
          <h1 class="text-3xl font-extrabold">Account Lifetime Logs</h1>
          <p class="text-green-100 mt-1 text-sm">
            View activation/deactivation history for voters within your scope.
          </p>
        </div>
      </div>
      <div class="text-right text-xs sm:text-sm">
        <p class="font-semibold">Admin View</p>
        <p class="text-green-100">Logs are limited to your assigned scope</p>
      </div>
    </header>

    <!-- Filter Form -->
    <section class="bg-white rounded-xl shadow-md p-6 mb-4">
      <form method="GET" id="filterForm" class="space-y-4">
        <!-- Top row: Search + Date range -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Search (email) -->
          <div>
            <label for="searchInput" class="block text-sm font-semibold text-gray-700 mb-1">
              Search (Email)
            </label>
            <div class="flex">
              <input
                type="text"
                id="searchInput"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="e.g. juan.dc@example.com"
                class="flex-1 border border-gray-300 rounded-l-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
              />
              <button
                type="submit"
                class="px-4 py-2 text-sm font-semibold bg-[var(--cvsu-green)] text-white rounded-r-lg hover:bg-[var(--cvsu-green-dark)] flex items-center gap-1"
              >
                <i class="fas fa-search text-xs"></i>
                Search
              </button>
            </div>
            <p class="text-[11px] text-gray-500 mt-1">
              Search is by email only to avoid partial-name conflicts.
            </p>
          </div>

          <!-- From Date -->
          <div>
            <label for="fromDate" class="block text-sm font-semibold text-gray-700 mb-1">
              From Date
            </label>
            <input
              type="date"
              id="fromDate"
              name="from_date"
              value="<?= htmlspecialchars($fromDate) ?>"
              max="<?= htmlspecialchars($today) ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            />
          </div>

          <!-- To Date -->
          <div>
            <label for="toDate" class="block text-sm font-semibold text-gray-700 mb-1">
              To Date
            </label>
            <input
              type="date"
              id="toDate"
              name="to_date"
              value="<?= htmlspecialchars($toDate) ?>"
              max="<?= htmlspecialchars($today) ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            />
          </div>
        </div>

        <!-- Second row: Action / Trigger / Reason / Position + Reset -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Action -->
          <div>
            <label for="action" class="block text-sm font-semibold text-gray-700 mb-1">
              Action
            </label>
            <select
              id="action"
              name="action"
              class="filter-auto w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            >
              <option value="">All</option>
              <option value="ACTIVATE"   <?= $action === 'ACTIVATE'   ? 'selected' : '' ?>>Activate</option>
              <option value="DEACTIVATE" <?= $action === 'DEACTIVATE' ? 'selected' : '' ?>>Deactivate</option>
              <option value="REACTIVATE" <?= $action === 'REACTIVATE' ? 'selected' : '' ?>>Reactivate</option>
            </select>
          </div>

          <!-- Trigger Type -->
          <div>
            <label for="trigger_type" class="block text-sm font-semibold text-gray-700 mb-1">
              Trigger Type
            </label>
            <select
              id="trigger_type"
              name="trigger_type"
              class="filter-auto w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            >
              <option value="">All</option>
              <option value="manual"         <?= $triggerType === 'manual'         ? 'selected' : '' ?>>Manual (per user)</option>
              <option value="manual_csv"     <?= $triggerType === 'manual_csv'     ? 'selected' : '' ?>>Manual (bulk CSV)</option>
              <option value="auto_duration"  <?= $triggerType === 'auto_duration'  ? 'selected' : '' ?>>Auto – Duration Expired</option>
              <option value="auto_missed"    <?= $triggerType === 'auto_missed'    ? 'selected' : '' ?>>Auto – Missed Elections</option>
            </select>
          </div>

          <!-- Reason Code -->
          <div>
            <label for="reason_code" class="block text-sm font-semibold text-gray-700 mb-1">
              Reason Code
            </label>
            <select
              id="reason_code"
              name="reason_code"
              class="filter-auto w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            >
              <option value="">All</option>
              <?php
              $reasonCodesList = [
                  'GRADUATED',
                  'TRANSFERRED',
                  'DUPLICATE_ACCOUNT',
                  'VIOLATION_TOS',
                  'DISCIPLINARY_ACTION',
                  'DATA_CORRECTION',
                  'MISSED_ELECTIONS',
                  'DURATION_EXPIRED',
                  'BULK_ACTIVATE',
              ];
              foreach ($reasonCodesList as $rcVal):
              ?>
                <option value="<?= $rcVal ?>" <?= $reasonCode === $rcVal ? 'selected' : '' ?>>
                  <?= htmlspecialchars(formatReasonLabel($rcVal)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Position + Reset -->
          <div>
            <label for="position" class="block text-sm font-semibold text-gray-700 mb-1">
              Position
            </label>
            <div class="flex items-end gap-2">
              <select
                id="position"
                name="position"
                class="filter-auto flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
              >
                <option value="">All</option>
                <option value="student"      <?= $position === 'student'      ? 'selected' : '' ?>>Student</option>
                <option value="academic"     <?= $position === 'academic'     ? 'selected' : '' ?>>Academic (Faculty)</option>
                <option value="non-academic" <?= $position === 'non-academic' ? 'selected' : '' ?>>Non-Academic</option>
              </select>

              <button
                type="button"
                id="clearFilters"
                class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-xs md:text-sm font-semibold flex items-center gap-1 whitespace-nowrap"
              >
                <i class="fas fa-sync-alt text-xs"></i>
                <span>Reset</span>
              </button>
            </div>
          </div>
        </div>

        <?php if (!empty($dateError)): ?>
          <div class="mt-1 text-xs md:text-sm text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-2">
            <?= htmlspecialchars($dateError) ?>
          </div>
        <?php endif; ?>
      </form>
    </section>

    <!-- Summary + Pagination Header -->
    <section class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
      <div class="text-sm text-gray-700">
        <?php
          $startRecord = $totalRecords ? $offset + 1 : 0;
          $endRecord   = min($offset + $recordsPerPage, $totalRecords);
        ?>
        <span class="font-semibold">Showing</span>
        <span><?= $startRecord ?></span>–<span><?= $endRecord ?></span>
        <span class="font-semibold">of</span>
        <span><?= $totalRecords ?></span> records
      </div>
      <div class="text-xs text-gray-500">
        Sorted by <span class="font-semibold">Newest First</span> (most recent changes on top)
      </div>
    </section>

    <!-- Logs Table -->
    <section class="bg-white rounded-xl shadow-md overflow-x-auto">
      <table class="min-w-full text-sm table-auto">
        <thead class="bg-[var(--cvsu-green)] text-white">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Date/Time</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">User</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Email</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Position</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Action</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Trigger</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Reason Code</th>
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Reason Text</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
              <?php
                $dt    = $log['created_at'] ?? null;
                $uName = formatName($log['first_name'] ?? '', $log['last_name'] ?? '');
                $email = $log['email'] ?? '';
                $pos   = $log['position'] ?? null;

                // Action badge colors
                $actionVal   = strtoupper($log['action'] ?? '');
                $actionClass = 'bg-gray-100 text-gray-800';
                if ($actionVal === 'DEACTIVATE') {
                    $actionClass = 'bg-red-100 text-red-700';
                } elseif ($actionVal === 'ACTIVATE' || $actionVal === 'REACTIVATE') {
                    $actionClass = 'bg-green-100 text-green-700';
                }

                // Trigger type badge
                $triggerVal   = $log['trigger_type'] ?? '';
                $triggerClass = 'bg-gray-100 text-gray-800';
                if ($triggerVal === 'auto_duration') {
                    $triggerClass = 'bg-yellow-100 text-yellow-800';
                } elseif ($triggerVal === 'auto_missed') {
                    $triggerClass = 'bg-orange-100 text-orange-800';
                } elseif ($triggerVal === 'manual_csv') {
                    $triggerClass = 'bg-blue-100 text-blue-800';
                } elseif ($triggerVal === 'manual') {
                    $triggerClass = 'bg-purple-100 text-purple-800';
                }

                $reasonCodeVal = $log['reason_code'] ?? '';
              ?>
              <tr class="border-b hover:bg-gray-50">
                <!-- Date/Time -->
                <td class="px-4 py-2 align-top text-xs text-gray-700 whitespace-nowrap">
                  <?= $dt ? htmlspecialchars(date('Y-m-d H:i:s', strtotime($dt))) : '-' ?>
                </td>

                <!-- User -->
                <td class="px-4 py-2 align-top text-xs">
                  <?php if ($uName !== ''): ?>
                    <div class="font-semibold text-gray-800"><?= htmlspecialchars($uName) ?></div>
                  <?php else: ?>
                    <div class="text-gray-400 italic">User #<?= (int)$log['user_id'] ?></div>
                  <?php endif; ?>
                </td>

                <!-- Email -->
                <td class="px-4 py-2 align-top text-xs text-gray-700">
                  <?= $email ? htmlspecialchars($email) : '<span class="text-gray-400 italic">No email</span>' ?>
                </td>

                <!-- Position -->
                <td class="px-4 py-2 align-top text-xs text-gray-700">
                  <?php if ($pos): ?>
                    <span class="chip bg-gray-100 text-gray-800">
                      <?= htmlspecialchars($pos) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-gray-400 italic">N/A</span>
                  <?php endif; ?>
                </td>

                <!-- Action -->
                <td class="px-4 py-2 align-top text-xs">
                  <span class="chip <?= $actionClass ?>">
                    <?= $actionVal !== '' ? htmlspecialchars($actionVal) : 'N/A' ?>
                  </span>
                </td>

                <!-- Trigger Type -->
                <td class="px-4 py-2 align-top text-xs">
                  <span class="chip <?= $triggerClass ?>">
                    <?= $triggerVal !== '' ? htmlspecialchars($triggerVal) : 'N/A' ?>
                  </span>
                </td>

                <!-- Reason Code -->
                <td class="px-4 py-2 align-top text-xs text-gray-700">
                  <?php if ($reasonCodeVal !== ''): ?>
                    <?= htmlspecialchars(formatReasonLabel($reasonCodeVal)) ?>
                  <?php else: ?>
                    <span class="text-gray-400 italic">None</span>
                  <?php endif; ?>
                </td>

                <!-- Reason Text -->
                <td class="px-4 py-2 align-top text-xs text-gray-600 max-w-xs">
                  <?php if (!empty($log['reason_text'])): ?>
                    <span class="block truncate" title="<?= htmlspecialchars($log['reason_text']) ?>">
                      <?= htmlspecialchars($log['reason_text']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-gray-400 italic">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="px-4 py-6 text-center text-gray-500 text-sm">
                No duration log entries match your filter criteria for your scope.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Pagination Controls -->
    <section class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseQueryString = http_build_query($queryParams);
        $baseUrl = 'admin_duration_logs.php';
        $buildPageUrl = function($pageNum) use ($baseUrl, $baseQueryString) {
            $qs = $baseQueryString;
            if ($qs !== '') {
                $qs .= '&page=' . $pageNum;
            } else {
                $qs = 'page=' . $pageNum;
            }
            return $baseUrl . '?' . $qs;
        };
      ?>
      <div class="text-sm text-gray-700">
        Page <span class="font-semibold"><?= $page ?></span> of <span class="font-semibold"><?= $totalPages ?></span>
      </div>
      <div class="flex items-center gap-2">
        <!-- Prev -->
        <?php if ($page > 1): ?>
          <a
            href="<?= htmlspecialchars($buildPageUrl($page - 1)) ?>"
            class="px-3 py-1 rounded border text-gray-700 bg-white hover:bg-gray-100 text-sm flex items-center gap-1"
          >
            <i class="fas fa-chevron-left text-xs"></i>
            Prev
          </a>
        <?php else: ?>
          <span class="px-3 py-1 rounded border bg-gray-100 text-gray-400 text-sm flex items-center gap-1 cursor-not-allowed">
            <i class="fas fa-chevron-left text-xs"></i>
            Prev
          </span>
        <?php endif; ?>

        <!-- Page numbers -->
        <?php
          $window = 3;
          $startPage = max(1, $page - $window);
          $endPage   = min($totalPages, $page + $window);
          for ($p = $startPage; $p <= $endPage; $p++):
        ?>
          <?php if ($p === $page): ?>
            <span class="px-3 py-1 rounded bg-[var(--cvsu-green)] text-white text-sm font-semibold">
              <?= $p ?>
            </span>
          <?php else: ?>
            <a
              href="<?= htmlspecialchars($buildPageUrl($p)) ?>"
              class="px-3 py-1 rounded border bg-white text-gray-700 hover:bg-gray-100 text-sm"
            >
              <?= $p ?>
            </a>
          <?php endif; ?>
        <?php endfor; ?>

        <!-- Next -->
        <?php if ($page < $totalPages): ?>
          <a
            href="<?= htmlspecialchars($buildPageUrl($page + 1)) ?>"
            class="px-3 py-1 rounded border text-gray-700 bg-white hover:bg-gray-100 text-sm flex items-center gap-1"
          >
            Next
            <i class="fas fa-chevron-right text-xs"></i>
          </a>
        <?php else: ?>
          <span class="px-3 py-1 rounded border bg-gray-100 text-gray-400 text-sm flex items-center gap-1 cursor-not-allowed">
            Next
            <i class="fas fa-chevron-right text-xs"></i>
          </span>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>

<!-- Toast container -->
<div id="toast-container"></div>
<script>
/* ============================================================
   GLOBAL REFERENCES
============================================================ */
const filterForm     = document.getElementById("filterForm");
const fromDateInput  = document.getElementById("fromDate");
const toDateInput    = document.getElementById("toDate");
const clearBtn       = document.getElementById("clearFilters");
const toastContainer = document.getElementById("toast-container");

/* ============================================================
   1. TOAST NOTIFICATIONS
============================================================ */
function showToast(message, type = "info") {
    if (!toastContainer) return;

    const toast = document.createElement("div");
    toast.className = `
        fixed bottom-4 right-4 px-4 py-2 rounded shadow-lg text-sm font-semibold
        flex items-center gap-2 z-50 transition-opacity duration-300
    `;

    let classes = "bg-gray-800 text-white";
    if (type === "success") classes = "bg-green-600 text-white";
    else if (type === "error") classes = "bg-red-600 text-white";
    else if (type === "warning") classes = "bg-yellow-500 text-gray-900";

    toast.classList.add(...classes.split(" "));

    const icon = document.createElement("i");
    if (type === "success") icon.className = "fas fa-check-circle";
    else if (type === "error") icon.className = "fas fa-exclamation-circle";
    else if (type === "warning") icon.className = "fas fa-exclamation-triangle";
    else icon.className = "fas fa-info-circle";

    const span = document.createElement("span");
    span.textContent = message;

    toast.appendChild(icon);
    toast.appendChild(span);
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/* ============================================================
   2. AUTO-SUBMIT FOR DROPDOWN FILTERS
============================================================ */
document.querySelectorAll(".filter-auto").forEach(el => {
    el.addEventListener("change", () => {
        if (filterForm) filterForm.submit();
    });
});

/* ============================================================
   3. DATE RANGE VALIDATION + AUTO-SUBMIT
============================================================ */
if (fromDateInput && toDateInput && filterForm) {
    fromDateInput.addEventListener("change", () => {
        if (fromDateInput.value && toDateInput.value && toDateInput.value < fromDateInput.value) {
            showToast("Start date cannot be later than end date.", "error");
            fromDateInput.value = "";
            return;
        }
        filterForm.submit();
    });

    toDateInput.addEventListener("change", () => {
        if (fromDateInput.value && toDateInput.value && toDateInput.value < fromDateInput.value) {
            showToast("End date cannot be earlier than start date.", "error");
            toDateInput.value = "";
            return;
        }
        filterForm.submit();
    });
}

/* ============================================================
   4. CLEAR FILTERS BUTTON
============================================================ */
if (clearBtn) {
    clearBtn.addEventListener("click", () => {
        window.location.href = "admin_duration_logs.php";
    });
}

/* ============================================================
   5. OPTIONAL: SMOOTH APPEARANCE
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    document.body.classList.add("opacity-100");
});
</script>
</body>
</html>
