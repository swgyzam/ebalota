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

// Optional scope impersonation (mainly for super admin coming from Super Admin Dashboard)
$scopeTypeParam = $_GET['scope_type'] ?? null;
$scopeIdParam   = (isset($_GET['scope_id']) && ctype_digit((string)$_GET['scope_id']))
    ? (int)$_GET['scope_id']
    : null;

/* ==========================================================
   DETERMINE REDIRECT BASED ON SCOPE CATEGORY (NEW MODEL)
   ========================================================== */

$redirectUrl = '';

// SUPER ADMIN
if ($role === 'super_admin') {

    // Kung may scope impersonation (galing sa Super Admin Dashboard)
    if ($scopeTypeParam && $scopeIdParam !== null) {
        switch ($scopeTypeParam) {
            case 'Special-Scope':          // CSG
                $redirectUrl = "admin_analytics_csg.php?id={$electionId}&scope_type=" . urlencode($scopeTypeParam) . "&scope_id={$scopeIdParam}";
                break;

            case 'Academic-Student':
                $redirectUrl = "admin_analytics_college.php?id={$electionId}&scope_type=" . urlencode($scopeTypeParam) . "&scope_id={$scopeIdParam}";
                break;

            case 'Academic-Faculty':
                $redirectUrl = "admin_analytics_faculty.php?id={$electionId}&scope_type=" . urlencode($scopeTypeParam) . "&scope_id={$scopeIdParam}";
                break;

            case 'Non-Academic-Employee':
                $redirectUrl = "admin_analytics_nonacademic.php?id={$electionId}&scope_type=" . urlencode($scopeTypeParam) . "&scope_id={$scopeIdParam}";
                break;

            case 'Non-Academic-Student':
                $redirectUrl = "admin_analytics_non_acad_students.php?id={$electionId}&scope_type=" . urlencode($scopeTypeParam) . "&scope_id={$scopeIdParam}";
                break;

            case 'Others':
                $redirectUrl = "admin_analytics_default.php?id={$electionId}&scope_type=" . urlencode($scopeTypeParam) . "&scope_id={$scopeIdParam}";
                break;

            default:
                // Unknown scope → safe global analytics
                $redirectUrl = "admin_analytics_all.php?id={$electionId}";
                break;
        }
    } else {
        // Walang scope_id/scope_type → global view
        $redirectUrl = "admin_analytics_all.php?id={$electionId}";
    }

}
// ADMIN
elseif ($role === 'admin') {
    switch ($scopeCategory) {
        case 'Special-Scope':
            $redirectUrl = "admin_analytics_csg.php?id={$electionId}";
            break;

        case 'Academic-Student':
            $redirectUrl = "admin_analytics_college.php?id={$electionId}";
            break;

        case 'Academic-Faculty':
            $redirectUrl = "admin_analytics_faculty.php?id={$electionId}";
            break;

        case 'Non-Academic-Employee':
            $redirectUrl = "admin_analytics_nonacademic.php?id={$electionId}";
            break;

        case 'Non-Academic-Student':
            $redirectUrl = "admin_analytics_non_acad_students.php?id={$electionId}";
            break;

        case 'Others':
            $redirectUrl = "admin_analytics_default.php?id={$electionId}";
            break;

        default:
            $redirectUrl = "admin_analytics_all.php?id={$electionId}";
            break;
    }

// ANY OTHER ROLE (fallback)
} else {
    $redirectUrl = "admin_analytics_all.php?id={$electionId}";
}

/* ==========================================================
   REDIRECT
   ========================================================== */
header("Location: {$redirectUrl}");
exit();
