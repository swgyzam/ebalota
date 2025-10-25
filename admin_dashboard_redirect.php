<?php 
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
 $host = 'localhost';
 $db   = 'evoting_system';
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

// Determine redirect URL based on user scope
 $redirectUrl = '';
switch ($scope) {
    case 'CSG ADMIN':
        $redirectUrl = "admin_dashboard_csg.php";
        break;
    case 'FACULTY ASSOCIATION':
        $redirectUrl = "admin_dashboard_faculty.php";
        break;
    case 'COOP':
        $redirectUrl = "admin_dashboard_coop.php";
        break;
    case 'NON-ACADEMIC':
        $redirectUrl = "admin_dashboard_nonacademic.php";
        break;
    default:
        // Check if it's a college admin
        $validCollegeScopes = ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'];
        if (in_array($scope, $validCollegeScopes)) {
            $redirectUrl = "admin_dashboard_college.php";
        } else {
            // Default for other roles (including super admin or unknown scopes)
            $redirectUrl = "admin_dashboard_default.php";
        }
        break;
}

// Redirect to the appropriate dashboard
header("Location: $redirectUrl");
exit();
?>