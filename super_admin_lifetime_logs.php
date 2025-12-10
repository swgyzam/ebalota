<?php
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
   AUTH CHECK — ONLY SUPER ADMIN CAN VIEW THIS PAGE
   ========================================================== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: login.php");
    exit();
}

/* ==========================================================
   FILTER INPUTS
   ========================================================== */
$search       = trim($_GET['search'] ?? '');
$action       = trim($_GET['action'] ?? '');
$triggerType  = trim($_GET['trigger_type'] ?? '');
$reasonCode   = trim($_GET['reason_code'] ?? '');
$position     = trim($_GET['position'] ?? '');

$fromDate     = trim($_GET['from_date'] ?? '');
$toDate       = trim($_GET['to_date'] ?? '');

/* ==========================================================
   BASE QUERY
   ========================================================== */

$where  = ["1 = 1"];
$params = [];

/* ----------------------------------------------------------
   Search by name or email
---------------------------------------------------------- */
if ($search !== '') {
    $where[]              = "(u.first_name LIKE :s OR u.last_name LIKE :s OR u.email LIKE :s)";
    $params[':s']         = "%" . $search . "%";
}

/* ----------------------------------------------------------
   Filter by Action (activate / deactivate / reactivate)
---------------------------------------------------------- */
if ($action !== '') {
    $where[]             = "l.action = :action";
    $params[':action']   = $action;
}

/* ----------------------------------------------------------
   Filter by Trigger Type
---------------------------------------------------------- */
if ($triggerType !== '') {
    $where[]                 = "l.trigger_type = :tt";
    $params[':tt']           = $triggerType;
}

/* ----------------------------------------------------------
   Filter by Reason Code
---------------------------------------------------------- */
if ($reasonCode !== '') {
    $where[]                 = "l.reason_code = :rc";
    $params[':rc']           = $reasonCode;
}

/* ----------------------------------------------------------
   Filter by Position (student, academic, non-academic)
---------------------------------------------------------- */
if ($position !== '') {
    $where[]               = "u.position = :pos";
    $params[':pos']        = $position;
}

/* ----------------------------------------------------------
   Filter by Date Range (From – To)
---------------------------------------------------------- */
if ($fromDate !== '') {
    $where[]                 = "DATE(l.created_at) >= :fromDate";
    $params[':fromDate']     = $fromDate;
}

if ($toDate !== '') {
    $where[]                 = "DATE(l.created_at) <= :toDate";
    $params[':toDate']       = $toDate;
}

/* ==========================================================
   FINAL SQL QUERY — ALWAYS SORT NEWEST FIRST
   ========================================================== */

$sql = "
    SELECT 
        l.*,
        u.first_name, u.last_name, u.email, u.position
    FROM user_lifetime_logs l
    LEFT JOIN users u ON u.user_id = l.user_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY l.created_at DESC
";

/* ==========================================================
   PAGINATION
   ========================================================== */
$recordsPerPage = 30;
$page           = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset         = ($page - 1) * $recordsPerPage;

/* Count total logs */
$countSql = "
    SELECT COUNT(*) 
    FROM user_lifetime_logs l
    LEFT JOIN users u ON u.user_id = l.user_id
    WHERE " . implode(" AND ", $where);

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = max(1, ceil($totalRecords / $recordsPerPage));

/* Fetch logs */
$sql .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit',  $recordsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$logs = $stmt->fetchAll();

/* ==========================================================
   HELPER: Format Names
   ========================================================== */
function formatName($fn, $ln) {
    return trim("$fn $ln");
}

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account Lifetime Logs - Super Admin</title>
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
      @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">
  <?php include 'super_admin_sidebar.php'; ?>
  <?php
    // Change password modal (if you have one)
    if (file_exists('super_admin_change_password_modal.php')) {
        include 'super_admin_change_password_modal.php';
    }
  ?>

  <main class="flex-1 px-8 pb-10 ml-64">
    <!-- Header -->
    <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-md rounded-b-md mb-8 mt-0">
      <div class="flex items-center space-x-4">
        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
          <i class="fas fa-history text-xl"></i>
        </div>
        <div>
          <h1 class="text-3xl font-extrabold">Account Lifetime Logs</h1>
          <p class="text-green-100 mt-1 text-sm">
            View all activations, deactivations, and reactivations across the eBalota system.
          </p>
        </div>
      </div>
      <div class="text-right text-xs sm:text-sm">
        <p class="font-semibold">Super Admin View</p>
        <p class="text-green-100">Global audit trail for all voter accounts</p>
      </div>
    </header>

    <!-- Filter Form -->
    <section class="bg-white rounded-xl shadow-md p-6 mb-6">
      <form method="GET" class="space-y-4">
        <!-- Top row: Search + Date range -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Search -->
          <div>
            <label for="search" class="block text-sm font-semibold text-gray-700 mb-1">
              Search (Name or Email)
            </label>
            <div class="relative">
              <input
                type="text"
                id="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="e.g. Juan, Santos, juan.dc@example.com"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm pr-9 focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
              />
              <span class="absolute right-3 top-2.5 text-gray-400">
                <i class="fas fa-search"></i>
              </span>
            </div>
          </div>

          <!-- From Date -->
          <div>
            <label for="from_date" class="block text-sm font-semibold text-gray-700 mb-1">
              From Date
            </label>
            <input
              type="date"
              id="from_date"
              name="from_date"
              value="<?= htmlspecialchars($fromDate) ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            />
          </div>

          <!-- To Date -->
          <div>
            <label for="to_date" class="block text-sm font-semibold text-gray-700 mb-1">
              To Date
            </label>
            <input
              type="date"
              id="to_date"
              name="to_date"
              value="<?= htmlspecialchars($toDate) ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            />
          </div>
        </div>

        <!-- Second row: Action / Trigger / Reason / Position -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Action -->
          <div>
            <label for="action" class="block text-sm font-semibold text-gray-700 mb-1">
              Action
            </label>
            <select
              id="action"
              name="action"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
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
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
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
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            >
              <option value="">All</option>
              <option value="GRADUATED"         <?= $reasonCode === 'GRADUATED'         ? 'selected' : '' ?>>GRADUATED</option>
              <option value="TRANSFERRED"       <?= $reasonCode === 'TRANSFERRED'       ? 'selected' : '' ?>>TRANSFERRED</option>
              <option value="DUPLICATE_ACCOUNT" <?= $reasonCode === 'DUPLICATE_ACCOUNT' ? 'selected' : '' ?>>DUPLICATE_ACCOUNT</option>
              <option value="VIOLATION_TOS"     <?= $reasonCode === 'VIOLATION_TOS'     ? 'selected' : '' ?>>VIOLATION_TOS</option>
              <option value="DISCIPLINARY_ACTION" <?= $reasonCode === 'DISCIPLINARY_ACTION' ? 'selected' : '' ?>>DISCIPLINARY_ACTION</option>
              <option value="DATA_CORRECTION"   <?= $reasonCode === 'DATA_CORRECTION'   ? 'selected' : '' ?>>DATA_CORRECTION</option>
              <option value="MISSED_ELECTIONS"  <?= $reasonCode === 'MISSED_ELECTIONS'  ? 'selected' : '' ?>>MISSED_ELECTIONS</option>
              <option value="DURATION_EXPIRED"  <?= $reasonCode === 'DURATION_EXPIRED'  ? 'selected' : '' ?>>DURATION_EXPIRED</option>
              <option value="BULK_ACTIVATE"     <?= $reasonCode === 'BULK_ACTIVATE'     ? 'selected' : '' ?>>BULK_ACTIVATE</option>
            </select>
          </div>

          <!-- Position -->
          <div>
            <label for="position" class="block text-sm font-semibold text-gray-700 mb-1">
              Position
            </label>
            <select
              id="position"
              name="position"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
            >
              <option value="">All</option>
              <option value="student"      <?= $position === 'student'      ? 'selected' : '' ?>>Student</option>
              <option value="academic"     <?= $position === 'academic'     ? 'selected' : '' ?>>Academic (Faculty)</option>
              <option value="non-academic" <?= $position === 'non-academic' ? 'selected' : '' ?>>Non-Academic</option>
            </select>
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex flex-wrap gap-3 justify-end pt-2">
          <button
            type="submit"
            class="px-4 py-2 bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <i class="fas fa-filter"></i>
            Apply Filters
          </button>
          <a
            href="super_admin_lifetime_logs.php"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-semibold flex items-center gap-2"
          >
            <i class="fas fa-sync-alt"></i>
            Reset
          </a>
        </div>
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
            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide">Admin</th>
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

                $adminLabel = 'System';
                if (!empty($log['admin_id'])) {
                    $adminLabel = 'Admin #' . (int)$log['admin_id'];
                }

                // Action badge colors
                $actionVal = strtoupper($log['action'] ?? '');
                $actionClass = 'bg-gray-100 text-gray-800';
                if ($actionVal === 'DEACTIVATE') {
                    $actionClass = 'bg-red-100 text-red-700';
                } elseif ($actionVal === 'ACTIVATE' || $actionVal === 'REACTIVATE') {
                    $actionClass = 'bg-green-100 text-green-700';
                }

                // Trigger type badge
                $triggerVal = $log['trigger_type'] ?? '';
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
                  <?= $reasonCodeVal !== '' ? htmlspecialchars($reasonCodeVal) : '<span class="text-gray-400 italic">None</span>' ?>
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

                <!-- Admin -->
                <td class="px-4 py-2 align-top text-xs text-gray-700">
                  <?= htmlspecialchars($adminLabel) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="px-4 py-6 text-center text-gray-500 text-sm">
                No lifetime log entries match your filter criteria.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Pagination Controls -->
    <section class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <?php
        // Build base query string without page parameter
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseQueryString = http_build_query($queryParams);
        $baseUrl = 'super_admin_lifetime_logs.php';
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

        <!-- Page numbers (simple window) -->
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

<!-- Toast container (for future JS toast if needed) -->
<div id="toast-container"></div>
<script>
/* ============================================================
   GLOBAL CONSTANTS
============================================================ */
const filterForm = document.getElementById("filterForm");
const searchInput = document.getElementById("searchInput");
const fromDateInput = document.getElementById("fromDate");
const toDateInput = document.getElementById("toDate");
const pageButtons = document.querySelectorAll("[data-page]");
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

    let bgClass = "bg-gray-800 text-white";
    if (type === "success") bgClass = "bg-green-600 text-white";
    if (type === "error") bgClass = "bg-red-600 text-white";
    if (type === "warning") bgClass = "bg-yellow-500 text-gray-900";

    toast.classList.add(...bgClass.split(" "));

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
   2. FORM AUTO-SUBMIT ON FILTER CHANGE
============================================================ */
document.querySelectorAll(".filter-auto").forEach(el => {
    el.addEventListener("change", () => filterForm.submit());
});

/* ============================================================
   3. DATE RANGE VALIDATION
============================================================ */
if (fromDateInput && toDateInput) {
    toDateInput.addEventListener("change", () => {
        if (fromDateInput.value && toDateInput.value && (toDateInput.value < fromDateInput.value)) {
            showToast("End date cannot be earlier than start date.", "error");
            toDateInput.value = "";
        }
        filterForm.submit();
    });

    fromDateInput.addEventListener("change", () => {
        if (fromDateInput.value && toDateInput.value && (toDateInput.value < fromDateInput.value)) {
            showToast("Start date cannot be later than end date.", "error");
            fromDateInput.value = "";
        }
        filterForm.submit();
    });
}

/* ============================================================
   4. LIVE SEARCH (DEBOUNCED)
============================================================ */
let searchTimer = null;

if (searchInput) {
    searchInput.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            filterForm.submit();
        }, 350); // Delay for better UX
    });
}

/* ============================================================
   5. PAGINATION
============================================================ */
pageButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        const page = btn.dataset.page;
        if (!page) return;

        // Modify form action with page parameter
        const url = new URL(window.location.href);
        url.searchParams.set("page", page);

        window.location.href = url.toString();
    });
});

/* ============================================================
   6. SMOOTH PAGE LOADING TRANSITION (OPTIONAL)
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    document.body.classList.add("opacity-100");
});

/* ============================================================
   7. OPTIONAL: CLEAR FILTERS BUTTON
============================================================ */
const clearBtn = document.getElementById("clearFilters");
if (clearBtn) {
    clearBtn.addEventListener("click", () => {
        window.location.href = "super_admin_lifetime_logs.php";
    });
}
</script>
</body>
</html>