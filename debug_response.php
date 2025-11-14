<?php
// Start output buffering to catch any output
ob_start();

// Disable error display (log errors instead)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// Set header early to prevent any output issues
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Get the raw POST data
 $raw_post = file_get_contents('php://input');
 $post_data = [];
if ($raw_post) {
    parse_str($raw_post, $post_data);
}

// Return what we received
echo json_encode([
    'status' => 'debug',
    'post_data' => $_POST,
    'raw_post' => $raw_post,
    'parsed_post' => $post_data,
    'session' => [
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'role' => $_SESSION['role'] ?? 'not set'
    ]
]);

// Clean any output buffer
while (ob_get_level()) {
    ob_end_clean();
}
?>