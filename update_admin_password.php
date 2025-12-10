<?php
session_start();
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
    echo json_encode(["status" => "error", "message" => "Unauthorized."]);
    exit;
}

$input            = json_decode(file_get_contents("php://input"), true) ?: [];
$currentPassword  = trim($input['current_password']  ?? '');
$newPassword      = trim($input['new_password']      ?? '');
$confirmPassword  = trim($input['confirm_password']  ?? $newPassword); // fallback for force-reset

// Basic validations
if ($newPassword === '' || $confirmPassword === '') {
    echo json_encode(["status" => "error", "message" => "Please fill in all password fields."]);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(["status" => "error", "message" => "New password and confirmation do not match."]);
    exit;
}

// Same policy as reset_password.php
if (
    strlen($newPassword) < 8 ||
    !preg_match('/[A-Z]/', $newPassword) ||
    !preg_match('/\d/', $newPassword)
) {
    echo json_encode([
        "status"  => "error",
        "message" => "Password must be at least 8 characters and include at least one uppercase letter and one number."
    ]);
    exit;
}

// DB connection
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
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit;
}

// Simple activity logger
function logActivity(PDO $pdo, int $userId, string $action): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
        ]);
    } catch (PDOException $e) {
        error_log('Activity log insert failed: ' . $e->getMessage());
    }
}

$userId = (int)$_SESSION['user_id'];

// Get current hash + force flag
$stmt = $pdo->prepare("SELECT password, force_password_change FROM users WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $userId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(["status" => "error", "message" => "User not found."]);
    exit;
}

$currentHash   = $row['password'];
$forceRequired = (int)$row['force_password_change'] === 1;

// --- attempt counter (for normal change only) ---
if (!isset($_SESSION['admin_pw_attempts'])) {
    $_SESSION['admin_pw_attempts'] = 0;
}
$attempts =& $_SESSION['admin_pw_attempts'];

// ==========================
// Normal change (NOT forced):
//   - require current password
//   - 3 attempts max
// ==========================
if (!$forceRequired) {

    if ($attempts >= 3) {
        echo json_encode([
            "status"  => "locked",
            "message" => "Too many incorrect attempts. Please use the 'Forgot Password' link on the login page to reset your password via email."
        ]);
        exit;
    }

    if ($currentPassword === '' || !password_verify($currentPassword, $currentHash)) {
        $attempts++;
        $remaining = max(0, 3 - $attempts);

        if ($attempts >= 3) {
            echo json_encode([
                "status"  => "locked",
                "message" => "Current password is incorrect. You have reached the maximum attempts. Please use the 'Forgot Password' link on the login page."
            ]);
        } else {
            echo json_encode([
                "status"  => "error",
                "message" => "Current password is incorrect. You have {$remaining} attempt(s) remaining."
            ]);
        }
        exit;
    }

    // current password correct → reset attempts
    $attempts = 0;

} else {
    // Force-reset path (first login). Walang current password, walang attempts.
    $attempts = 0;
}

// === UPDATE PASSWORD ===
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    UPDATE users 
    SET password = :pwd, force_password_change = 0 
    WHERE user_id = :uid
");
$ok = $stmt->execute([
    ":pwd" => $hashed,
    ":uid" => $userId
]);

if ($ok) {
    logActivity(
        $pdo,
        $userId,
        $forceRequired
            ? 'Password changed (forced first-login change)'
            : 'Password changed via Change Password modal'
    );
}

echo json_encode([
    "status"  => $ok ? "success" : "error",
    "message" => $ok ? "Password updated successfully." : "Failed to update password."
]);
