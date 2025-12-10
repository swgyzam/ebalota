<?php
// admin_activity_logs.php
session_start();
date_default_timezone_set('Asia/Manila');

// --- AUTH CHECK ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
    header('Location: login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

// --- DB CONNECTION ---
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
    error_log('DB error (admin_activity_logs.php): ' . $e->getMessage());
    die('Database connection failed.');
}

// --- BASIC USER INFO (for header) ---
$userStmt = $pdo->prepare("
    SELECT first_name, last_name, admin_title, profile_picture
    FROM users
    WHERE user_id = :uid
    LIMIT 1
");
$userStmt->execute([':uid' => $userId]);
$admin = $userStmt->fetch();

if (!$admin) {
    die('Admin not found.');
}

$firstName   = trim($admin['first_name'] ?? '');
$lastName    = trim($admin['last_name'] ?? '');
$fullName    = trim($firstName . ' ' . $lastName);
$adminTitle  = $admin['admin_title'] ?: 'Administrator';
$profileFile = $admin['profile_picture'] ?? null;
$profilePath = $profileFile ? 'uploads/profile_pictures/'.$profileFile : null;

$today = date('Y-m-d');
$dateError = '';

// GET params for range
$fromInput = $_GET['from'] ?? $today;
$toInput   = $_GET['to']   ?? $today;

// Helper to validate & clamp a date (no future)
function normalizeDateOrToday(string $input, string $today, string &$errLabel, string $fieldLabel): string {
    $dt = DateTime::createFromFormat('Y-m-d', $input);
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

// Normalise both dates
$fromDate = normalizeDateOrToday($fromInput, $today, $dateError, 'Start');
$toDate   = normalizeDateOrToday($toInput,   $today, $dateError, 'End');

// If start > end, swap them
if ($fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
    $dateError .= ($dateError ? ' ' : '') . 'Start date was after end date, so the range has been adjusted.';
}

// For UI labels
if ($fromDate === $toDate) {
    $rangeLabel = date('M d, Y', strtotime($fromDate));
} else {
    $rangeLabel = date('M d, Y', strtotime($fromDate)) . ' – ' . date('M d, Y', strtotime($toDate));
}

// --- LOAD LOGS FOR THE SELECTED RANGE ---
$logs      = [];
$logsError = '';

try {
    $logStmt = $pdo->prepare("
        SELECT log_id, action, timestamp
        FROM activity_logs
        WHERE user_id = :uid
          AND DATE(timestamp) BETWEEN :start_date AND :end_date
        ORDER BY timestamp DESC
        LIMIT 500
    ");
    $logStmt->execute([
        ':uid'        => $userId,
        ':start_date' => $fromDate,
        ':end_date'   => $toDate,
    ]);
    $logs = $logStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Logs error (admin_activity_logs.php): ' . $e->getMessage());
    $logsError = 'Unable to load activity logs at this time.';
}

function formatDateTime($ts) {
    if (!$ts) return '';
    return date('M d, Y h:i A', strtotime($ts));
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Activity Logs - eBalota</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          referrerpolicy="no-referrer" />

    <style>
        :root {
            --cvsu-green-dark:#154734;
            --cvsu-green:#1E6F46;
            --cvsu-green-light:#37A66B;
            --cvsu-yellow:#FFD166;
        }
        body { background:#f3f6f9; }
    </style>
</head>
<body class="font-sans text-gray-900">
<div class="flex min-h-screen">

    <?php
        $role = $_SESSION['role'] ?? '';
        if ($role === 'super_admin') {
            include 'super_admin_sidebar.php';
        } else {
            include 'sidebar.php';
        }
    ?>

    <?php
        $role = $_SESSION['role'] ?? '';
        if ($role === 'super_admin') {
            include 'super_admin_change_password_modal.php';
        } else {
            include 'admin_change_password_modal.php';
        }
    ?>

    <main class="flex-1 ml-64 p-6">

        <!-- HEADER BAR -->
        <section class="rounded-xl px-6 py-4 bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] text-white flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-xl md:text-2xl font-extrabold">Activity Logs</h1>
                <p class="text-white/80 text-sm">
                    Recent admin actions for <span class="font-semibold"><?= htmlspecialchars($fullName ?: 'Admin User') ?></span>.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden md:flex flex-col text-right text-xs text-white/80">
                    <!-- Admin title only, no date -->
                    <span class="font-semibold"><?= htmlspecialchars($adminTitle) ?></span>
                </div>
                <div class="w-10 h-10 rounded-full bg-white/15 overflow-hidden flex items-center justify-center">
                    <?php if ($profilePath): ?>
                        <img src="<?= htmlspecialchars($profilePath) ?>" alt="Avatar" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-shield text-white"></i>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- FILTER BAR -->
        <section class="mt-4 mb-4 flex flex-wrap items-center gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200">
                <label class="text-xs md:text-sm text-gray-600 font-medium flex items-center gap-1">
                    <i class="fas fa-calendar-day text-[var(--cvsu-green)]"></i>
                    <span>Date range:</span>
                </label>

                <!-- FROM -->
                <div class="flex items-center gap-1 text-xs md:text-sm">
                    <span class="text-gray-500">From</span>
                    <input
                        type="date"
                        name="from"
                        value="<?= htmlspecialchars($fromDate) ?>"
                        max="<?= htmlspecialchars($today) ?>"
                        class="border border-gray-300 rounded-md text-xs md:text-sm px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
                    />
                </div>

                <!-- TO -->
                <div class="flex items-center gap-1 text-xs md:text-sm">
                    <span class="text-gray-500">to</span>
                    <input
                        type="date"
                        name="to"
                        value="<?= htmlspecialchars($toDate) ?>"
                        max="<?= htmlspecialchars($today) ?>"
                        class="border border-gray-300 rounded-md text-xs md:text-sm px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
                    />
                </div>

                <button
                    type="submit"
                    class="text-xs md:text-sm px-3 py-1 rounded-md bg-[var(--cvsu-green)] text-white font-semibold hover:bg-[var(--cvsu-green-dark)]"
                >
                    Apply
                </button>
                <a
                    href="admin_activity_logs.php"
                    class="text-xs md:text-sm px-3 py-1 rounded-md bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200"
                >
                    Today
                </a>
            </form>

            <?php if (!empty($dateError)): ?>
                <div class="mt-2 text-xs md:text-sm text-red-600 bg-red-50 border border-red-200 rounded-md px-3 py-2">
                    <?= htmlspecialchars($dateError) ?>
                </div>
            <?php endif; ?>

            <p class="text-xs text-gray-500">
                Showing logs for
                <span class="font-semibold"><?= htmlspecialchars($rangeLabel) ?></span>.
            </p>
        </section>


        <!-- LOGS CARD -->
        <section class="bg-white rounded-2xl shadow-md p-5 md:p-7">
            <?php if ($logsError): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md text-sm text-red-700">
                    <?= htmlspecialchars($logsError) ?>
                </div>
            <?php elseif (empty($logs)): ?>
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-gray-600 text-sm md:text-base">
                        No activity logs recorded for this date.
                    </p>
                </div>
            <?php else: ?>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm md:text-base font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-list text-[var(--cvsu-green)]"></i>
                        Logs for <?= htmlspecialchars($rangeLabel) ?>
                    </h2>
                    <span class="text-xs text-gray-500">
                        Showing <?= count($logs) ?> entr<?= count($logs) === 1 ? 'y' : 'ies' ?>
                    </span>
                </div>

                <!-- Timeline-style logs -->
                <div class="space-y-4">
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $ts      = $log['timestamp'] ?? '';
                            $when    = formatDateTime($ts);
                            $action  = trim($log['action'] ?? '');
                        ?>
                        <div class="flex items-start gap-3 md:gap-4">
                            <div class="mt-1">
                                <div class="w-2 h-2 md:w-2.5 md:h-2.5 rounded-full bg-[var(--cvsu-green)]"></div>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs md:text-sm text-gray-500"><?= htmlspecialchars($when) ?></p>
                                <p class="text-sm md:text-base text-gray-800 mt-0.5 break-words">
                                    <?= nl2br(htmlspecialchars($action)) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Small note -->
        <p class="mt-4 text-xs text-gray-500">
            Logs are stored permanently in the database. Use the date filter above to review past actions.
        </p>
    </main>
</div>
</body>
</html>
