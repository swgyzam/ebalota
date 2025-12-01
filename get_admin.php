<?php
session_start();
header('Content-Type: application/json');

// Use the central scope helpers / taxonomy
include_once 'admin_functions.php';

// --- DB Connection ---
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
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// --- Auth check: super admin only ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unauthorized access',
    ]);
    exit();
}

// --- Require user_id param ---
if (!isset($_GET['user_id'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'User ID is required',
    ]);
    exit();
}

$userId = (int)$_GET['user_id'];

// --- Fetch admin with scope_details from admin_scopes ---
$stmt = $pdo->prepare("
    SELECT u.*, asd.scope_details 
    FROM users u 
    LEFT JOIN admin_scopes asd ON u.user_id = asd.user_id 
    WHERE u.user_id = ? AND u.role = 'admin'
");
$stmt->execute([$userId]);
$admin = $stmt->fetch();

if (!$admin) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Admin not found',
    ]);
    exit();
}

// --- Initialize scope vars ---
$scopeCategory = $admin['scope_category'] ?? '';
$scopeDetails  = $admin['scope_details']  ?? '';

// Debug logs (optional)
error_log("=== GET_ADMIN.PHP DEBUG ===");
error_log("Raw admin data from database: " . json_encode($admin));

// --- If scope_category is empty, infer from assigned_scope (legacy support) ---
if (empty($scopeCategory) && !empty($admin['assigned_scope'])) {
    $assignedScope = $admin['assigned_scope'];

    // College codes (Academic-Student)
    $colleges = array_keys(getColleges());

    if (in_array($assignedScope, $colleges, true)) {
        // Academic-Student
        $scopeCategory = 'Academic-Student';
    } elseif ($assignedScope === 'Faculty Association') {
        // Academic-Faculty (legacy string)
        $scopeCategory = 'Academic-Faculty';
    } elseif ($assignedScope === 'COOP') {
        // Legacy COOP scope → unified as "Others"
        $scopeCategory = 'Others';
    } elseif ($assignedScope === 'Non-Academic') {
        // Non-Academic-Employee
        $scopeCategory = 'Non-Academic-Employee';
    } elseif ($assignedScope === 'CSG Admin') {
        // Special CSG scope
        $scopeCategory = 'Special-Scope';
    } else {
        // Default / fallback: unified Others
        $scopeCategory = 'Others';
    }
} elseif (empty($scopeCategory)) {
    // No scope info at all → treat as unified Others
    $scopeCategory = 'Others';
}

// --- Parse scope_details JSON if present ---
$parsedScopeDetails = null;
if (!empty($scopeDetails) && $scopeDetails !== 'NULL') {
    $parsedScopeDetails = json_decode($scopeDetails, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in get_admin.php: " . json_last_error_msg());
        $parsedScopeDetails = null;
    }
}

// --- If scope_details missing/invalid, reconstruct from assigned_scope / assigned_scope_1 ---
if ($parsedScopeDetails === null) {
    $details = [];

    switch ($scopeCategory) {
        case 'Academic-Student': {
            // assigned_scope = college code
            // assigned_scope_1 = single course code, "Multiple: A, B, C", or "All"
            $college = $admin['assigned_scope']   ?? '';
            $scope1  = $admin['assigned_scope_1'] ?? '';

            if (!empty($college)) {
                $details['college'] = $college;
            }

            $courses = [];
            if (!empty($scope1)) {
                if (strpos($scope1, 'Multiple: ') === 0) {
                    $coursesStr = substr($scope1, 9); // remove "Multiple: "
                    $courses    = array_filter(array_map('trim', explode(',', $coursesStr)));
                } elseif ($scope1 !== 'All') {
                    $courses = [$scope1];
                }
            }

            $details['courses'] = $courses;

            // Optional display helper
            if ($scope1 === 'All' || (empty($courses) && !empty($college))) {
                $details['courses_display'] = 'All';
            } elseif (!empty($courses)) {
                $details['courses_display'] = implode(', ', $courses);
            } else {
                $details['courses_display'] = '';
            }
            break;
        }

        case 'Academic-Faculty': {
            // assigned_scope = college code
            // assigned_scope_1 = single dept code, "Multiple: D1, D2", or "All"
            $college = $admin['assigned_scope']   ?? '';
            $scope1  = $admin['assigned_scope_1'] ?? '';

            if (!empty($college)) {
                $details['college'] = $college;
            }

            $departments = [];
            if (!empty($scope1)) {
                if (strpos($scope1, 'Multiple: ') === 0) {
                    $deptsStr    = substr($scope1, 9); // remove "Multiple: "
                    $departments = array_filter(array_map('trim', explode(',', $deptsStr)));
                } elseif ($scope1 !== 'All') {
                    $departments = [$scope1];
                }
            }

            $details['departments'] = $departments;

            // Optional display helper
            if ($scope1 === 'All' || (empty($departments) && !empty($college))) {
                $details['departments_display'] = 'All';
            } elseif (!empty($departments)) {
                $details['departments_display'] = implode(', ', $departments);
            } else {
                $details['departments_display'] = '';
            }
            break;
        }

        case 'Non-Academic-Employee': {
            // For Non-Academic-Employee, only use assigned_scope_1 for departments.
            // assigned_scope is legacy and can be ignored unless it's a single dept.
            $scope1 = $admin['assigned_scope_1'] ?? '';
            $scope  = $admin['assigned_scope']   ?? '';

            if (!empty($scope1) && strpos($scope1, 'Multiple: ') === 0) {
                $deptsStr = substr($scope1, 9); // Remove "Multiple: "
                $departments = array_filter(array_map('trim', explode(',', $deptsStr)));
                $details['departments']          = $departments;
                $details['departments_display']  = $scope1;
            } elseif (!empty($scope1)) {
                if ($scope1 === 'All') {
                    $details['departments']         = [];
                    $details['departments_display'] = 'All';
                } else {
                    $details['departments']         = [$scope1];
                    $details['departments_display'] = $scope1;
                }
            } else {
                // If no departments specified in scope_1, check assigned_scope
                if (!empty($scope) && $scope !== 'Non-Academic') {
                    $details['departments']         = [$scope];
                    $details['departments_display'] = $scope;
                } else {
                    // All departments (global Non-Academic-Employee)
                    $details['departments']         = [];
                    $details['departments_display'] = 'All';
                }
            }
            break;
        }

        // For Non-Academic-Student, Others, Special-Scope:
        // we don't reconstruct details here; the scope is descriptive.
        default:
            $details = [];
            break;
    }

    $scopeDetails = json_encode($details);
} else {
    // Use the parsed scope details as-is
    $scopeDetails = json_encode($parsedScopeDetails);
}

// --- Get human-readable scope category label (via admin_functions.php) ---
$scopeCategoryLabel = getScopeCategoryLabel($scopeCategory);

// Debug logs (optional)
error_log("Final scopeCategory: $scopeCategory");
error_log("Final scopeDetails: $scopeDetails");
error_log("Final scopeCategoryLabel: $scopeCategoryLabel");

// --- Build response payload ---
$response = [
    'status' => 'success',
    'data'   => [
        'user_id'             => $admin['user_id'],
        'admin_title'         => $admin['admin_title'] ?? '',
        'first_name'          => $admin['first_name'],
        'last_name'           => $admin['last_name'],
        'email'               => $admin['email'],
        'scope_category'      => $scopeCategory,
        'scope_details'       => $scopeDetails,
        'scope_category_label'=> $scopeCategoryLabel,
        // Keep legacy fields for reference / UI if needed
        'assigned_scope'      => $admin['assigned_scope'],
        'assigned_scope_1'    => $admin['assigned_scope_1'],
    ],
];

// Final debug (optional)
error_log("Final response: " . json_encode($response));
error_log("=== END GET_ADMIN.PHP DEBUG ===");

echo json_encode($response);
