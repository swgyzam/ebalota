<?php
// upload_admin_profile.php
// Save already-cropped blob from Cropper.js and return clean JSON.

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

function json_exit(array $data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    json_exit(['status' => 'error', 'message' => 'Not authorized']);
}

$userId = (int) $_SESSION['user_id'];

// Check upload
if (
    !isset($_FILES['image']) ||
    !is_uploaded_file($_FILES['image']['tmp_name']) ||
    $_FILES['image']['error'] !== UPLOAD_ERR_OK
) {
    json_exit(['status' => 'error', 'message' => 'No image received or upload error']);
}

$imageFile = $_FILES['image'];

// ✅ SIZE LIMIT: max 3 MB
$maxSize = 3 * 1024 * 1024; // 3 MB in bytes
if ($imageFile['size'] > $maxSize) {
    json_exit([
        'status'  => 'error',
        'message' => 'Image is too large. Maximum size is 3 MB.'
    ]);
}

// ✅ TYPE LIMIT: jpg / jpeg / png ONLY
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png']; // removed webp
if (!in_array($imageFile['type'], $allowedTypes, true)) {
    json_exit([
        'status'  => 'error',
        'message' => 'Invalid image format. Only JPG and PNG are allowed.'
    ]);
}

// Ensure folder exists
$uploadDirAbs = __DIR__ . '/uploads/profile_pictures/';
$uploadDirRel = 'uploads/profile_pictures/';

if (!is_dir($uploadDirAbs)) {
    if (!mkdir($uploadDirAbs, 0777, true) && !is_dir($uploadDirAbs)) {
        json_exit(['status' => 'error', 'message' => 'Cannot create upload directory']);
    }
}

// Build filename & target
$ext       = '.jpg'; // we send JPEG from JS
$newName   = 'admin_' . $userId . '_profile' . $ext;
$targetAbs = $uploadDirAbs . $newName;
$targetRel = $uploadDirRel . $newName;

// Move uploaded file
if (!move_uploaded_file($imageFile['tmp_name'], $targetAbs)) {
    json_exit(['status' => 'error', 'message' => 'Failed to save image file']);
}

// Update DB
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=evoting_system;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->prepare("UPDATE users SET profile_picture = :pic WHERE user_id = :uid");
    $stmt->execute([
        ':pic' => $newName,
        ':uid' => $userId,
    ]);

    // Update session for sidebar usage
    $_SESSION['profile_picture'] = $newName;

    json_exit([
        'status'  => 'success',
        'newPath' => $targetRel,
    ]);

} catch (PDOException $e) {
    error_log('Profile pic update error: ' . $e->getMessage());
    json_exit(['status' => 'error', 'message' => 'Database error']);
}
