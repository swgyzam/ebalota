<?php 
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
} catch (\PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user info including scope
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $userInfo = $stmt->fetch();

 $role = $userInfo['role'] ?? '';
 $scope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

// Get election ID from URL
 $electionId = $_GET['id'] ?? 0;
if (!$electionId) {
    // Set error message and redirect back to analytics dashboard
    $_SESSION['toast_message'] = 'Invalid election ID';
    $_SESSION['toast_type'] = 'error';
    header('Location: admin_analytics.php');
    exit();
}

// Determine redirect URL based on user scope
 $redirectUrl = '';
switch ($scope) {
    case 'CSG ADMIN':
        $redirectUrl = "admin_analytics_csg.php?id=$electionId";
        break;
    case 'FACULTY ASSOCIATION':
        $redirectUrl = "admin_analytics_faculty.php?id=$electionId";
        break;
    case 'COOP':
        $redirectUrl = "admin_analytics_coop.php?id=$electionId";
        break;
    case 'NON-ACADEMIC':
        $redirectUrl = "admin_analytics_nonacademic.php?id=$electionId";
        break;
    default:
        // Check if it's a college admin
        $validCollegeScopes = ['CEIT', 'CAS', 'CEMDS', 'CCJ', 'CAFENR', 'CON', 'COED', 'CVM', 'GRADUATE SCHOOL'];
        if (in_array($scope, $validCollegeScopes)) {
            $redirectUrl = "admin_analytics_college.php?id=$electionId";
        } else {
            // Default for other roles (including super admin)
            $redirectUrl = "admin_analytics_all.php?id=$electionId";
        }
        break;
}

// Redirect to the appropriate analytics page
header("Location: $redirectUrl");
exit();
?>