<?php
session_start();
header('Content-Type: application/json');

// Include the scope_helpers.php file to get the label function
include_once 'scope_helpers.php';

// DB Connection
 $host = 'localhost';
 $db   = 'evoting_system';
 $user = 'root';
 $pass = '';
 $charset = 'utf8mb4';
 $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
 $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Check if user is logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Get user ID from request
if (!isset($_GET['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
    exit();
}

 $userId = $_GET['user_id'];

// Fetch admin data with scope details from admin_scopes table
 $stmt = $pdo->prepare("
    SELECT u.*, asd.scope_details 
    FROM users u 
    LEFT JOIN admin_scopes asd ON u.user_id = asd.user_id 
    WHERE u.user_id = ? AND u.role = 'admin'
");
 $stmt->execute([$userId]);
 $admin = $stmt->fetch();

if (!$admin) {
    echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
    exit();
}

// Initialize variables
 $scopeCategory = $admin['scope_category'] ?? '';
 $scopeDetails = $admin['scope_details'] ?? '';

// Debug: Log the admin data
error_log("=== GET_ADMIN.PHP DEBUG ===");
error_log("Raw admin data from database: " . json_encode($admin));

// If scope_category is empty, try to convert from assigned_scope
if (empty($scopeCategory) && !empty($admin['assigned_scope'])) {
    $assignedScope = $admin['assigned_scope'];
    
    // Check if it's a college code
    $colleges = ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'];
    
    if (in_array($assignedScope, $colleges)) {
        // This is an Academic-Student scope
        $scopeCategory = 'Academic-Student';
    } else if ($assignedScope === 'Faculty Association') {
        // This is an Academic-Faculty scope
        $scopeCategory = 'Academic-Faculty';
    } else if ($assignedScope === 'COOP') {
        // This is an Others-COOP scope
        $scopeCategory = 'Others-COOP';
    } else if ($assignedScope === 'Non-Academic') {
        // This is a Non-Academic-Employee scope
        $scopeCategory = 'Non-Academic-Employee';
    } else if ($assignedScope === 'CSG Admin') {
        // This is a Special-Scope
        $scopeCategory = 'Special-Scope';
    } else {
        // Default to Others-Default for any other scope
        $scopeCategory = 'Others-Default';
    }
} else if (empty($scopeCategory)) {
    // Default to Others-Default if no scope is assigned
    $scopeCategory = 'Others-Default';
}

// Parse scope_details if it's a valid JSON string
 $parsedScopeDetails = null;
if (!empty($scopeDetails) && $scopeDetails !== 'NULL') {
    $parsedScopeDetails = json_decode($scopeDetails, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        $parsedScopeDetails = null;
    }
}

// If scopeDetails is empty or invalid, create it based on scope_category and assigned_scope_1
if ($parsedScopeDetails === null) {
    $details = [];
    
    switch ($scopeCategory) {
        case 'Academic-Student':
            if (!empty($admin['assigned_scope'])) {
                $details['college'] = $admin['assigned_scope'];
                
                // Parse assigned_scope_1 if it exists and starts with "Multiple: "
                if (!empty($admin['assigned_scope_1']) && strpos($admin['assigned_scope_1'], 'Multiple: ') === 0) {
                    $coursesStr = substr($admin['assigned_scope_1'], 9); // Remove "Multiple: "
                    $details['courses'] = array_map('trim', explode(',', $coursesStr));
                } else if (!empty($admin['assigned_scope_1'])) {
                    $details['courses'] = [$admin['assigned_scope_1']];
                } else {
                    $details['courses'] = []; // All courses
                }
            }
            break;
            
        case 'Academic-Faculty':
            if (!empty($admin['assigned_scope'])) {
                $details['college'] = $admin['assigned_scope'];
                
                // Parse assigned_scope_1 if it exists and starts with "Multiple: "
                if (!empty($admin['assigned_scope_1']) && strpos($admin['assigned_scope_1'], 'Multiple: ') === 0) {
                    $deptsStr = substr($admin['assigned_scope_1'], 9); // Remove "Multiple: "
                    $details['departments'] = array_map('trim', explode(',', $deptsStr));
                } else if (!empty($admin['assigned_scope_1'])) {
                    $details['departments'] = [$admin['assigned_scope_1']];
                } else {
                    $details['departments'] = []; // All departments
                }
            }
            break;
            
        case 'Non-Academic-Employee':
            // For Non-Academic-Employee, only use assigned_scope_1 for departments
            // The assigned_scope field should be ignored for this scope type
            if (!empty($admin['assigned_scope_1']) && strpos($admin['assigned_scope_1'], 'Multiple: ') === 0) {
                $deptsStr = substr($admin['assigned_scope_1'], 9); // Remove "Multiple: "
                $details['departments'] = array_map('trim', explode(',', $deptsStr));
                $details['departments_display'] = $admin['assigned_scope_1'];
            } else if (!empty($admin['assigned_scope_1'])) {
                $details['departments'] = [$admin['assigned_scope_1']];
                $details['departments_display'] = $admin['assigned_scope_1'];
            } else {
                // If no departments are specified, check if there's a department in assigned_scope
                if (!empty($admin['assigned_scope']) && $admin['assigned_scope'] !== 'Non-Academic') {
                    $details['departments'] = [$admin['assigned_scope']];
                    $details['departments_display'] = $admin['assigned_scope'];
                } else {
                    $details['departments'] = []; // All departments
                    $details['departments_display'] = 'All';
                }
            }
            break;
            
        // For other categories, keep empty array
    }
    
    $scopeDetails = json_encode($details);
} else {
    // Use the parsed scope details
    $scopeDetails = json_encode($parsedScopeDetails);
}

// Get the scope category label
 $scopeCategoryLabel = '';
if (function_exists('getScopeCategoryLabel')) {
    $scopeCategoryLabel = getScopeCategoryLabel($scopeCategory);
} else {
    // Fallback if function doesn't exist
    $scopeCategoryLabel = $scopeCategory;
}

// Debug: Log the final values
error_log("Final scopeCategory: $scopeCategory");
error_log("Final scopeDetails: $scopeDetails");
error_log("Final scopeCategoryLabel: $scopeCategoryLabel");

// Prepare response data
 $response = [
    'status' => 'success',
    'data' => [
        'user_id' => $admin['user_id'],
        'admin_title' => $admin['admin_title'] ?? '',
        'first_name' => $admin['first_name'],
        'last_name' => $admin['last_name'],
        'email' => $admin['email'],
        'scope_category' => $scopeCategory,
        'scope_details' => $scopeDetails,
        'scope_category_label' => $scopeCategoryLabel,
        // Keep the old fields for reference
        'assigned_scope' => $admin['assigned_scope'],
        'assigned_scope_1' => $admin['assigned_scope_1']
    ]
];

// Debug: Log the final response
error_log("Final response: " . json_encode($response));
error_log("=== END GET_ADMIN.PHP DEBUG ===");

echo json_encode($response);
?>