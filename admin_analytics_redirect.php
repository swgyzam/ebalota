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
} catch (\PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

/* ==========================================================
   AUTH CHECK
   ========================================================== */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

/* ==========================================================
   FETCH USER INFO (ROLE + SCOPE CATEGORY)
   ========================================================== */
$stmt = $pdo->prepare("
    SELECT role, scope_category, assigned_scope
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$userInfo = $stmt->fetch();

if (!$userInfo) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$role          = $userInfo['role'] ?? '';
$scopeCategory = $userInfo['scope_category'] ?? '';
$assignedScope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

/* ==========================================================
   GET ELECTION ID
   ========================================================== */
$electionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($electionId <= 0) {
    $_SESSION['toast_message'] = 'Invalid election ID.';
    $_SESSION['toast_type']    = 'error';
    header('Location: admin_analytics.php');
    exit();
}

/* ==========================================================
   DETERMINE REDIRECT BASED ON SCOPE CATEGORY (NEW MODEL)
   ========================================================== */

$redirectUrl = '';

// Super admin: always allowed to see global analytics
if ($role === 'super_admin') {
    $redirectUrl = "admin_analytics_all.php?id={$electionId}";
} elseif ($role === 'admin') {
    switch ($scopeCategory) {
        case 'Special-Scope':
            // CSG Admin – system-wide student org management
            $redirectUrl = "admin_analytics_csg.php?id={$electionId}";
            break;

        case 'Others-COOP':
            // COOP Admin – COOP + MIGS employees
            $redirectUrl = "admin_analytics_coop.php?id={$electionId}";
            break;

        case 'Academic-Student':
            // College / program-based student elections
            $redirectUrl = "admin_analytics_college.php?id={$electionId}";
            break;

        case 'Academic-Faculty':
            // Faculty elections by college + department
            $redirectUrl = "admin_analytics_faculty.php?id={$electionId}";
            break;

        case 'Non-Academic-Employee':
            // Non-academic employees (HR, ADMIN, LIBRARY, etc.)
            $redirectUrl = "admin_analytics_nonacademic.php?id={$electionId}";
            break;

        case 'Non-Academic-Student':
            // Non-academic student org admins (esports, bands, etc.)
            $redirectUrl = "admin_analytics_non_acad_students.php?id={$electionId}";
            break;

        case 'Others-Default':
            // Default admin – all faculty + all non-academic employees
            $redirectUrl = "admin_analytics_default.php?id={$electionId}";
            break;

        default:
            // Very old admins or unknown scope → safe global-ish analytics
            $redirectUrl = "admin_analytics_all.php?id={$electionId}";
            break;
    }
} else {
    // Voters or any other unexpected role – fallback
    $redirectUrl = "admin_analytics_all.php?id={$electionId}";
}

/* ==========================================================
   REDIRECT
   ========================================================== */
header("Location: {$redirectUrl}");
exit();
