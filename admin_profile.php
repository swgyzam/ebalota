<?php
// admin_profile.php (modular, compact, responsive)

session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    header('Location: login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

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
} catch (PDOException $e) {
    error_log('DB error (admin_profile.php): ' . $e->getMessage());
    die('Database connection failed.');
}

require_once __DIR__ . '/admin_functions.php';

// Fetch admin record
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
$stmt->execute([':uid' => $userId]);
$admin = $stmt->fetch();

$isSuperAdmin = (($_SESSION['role'] ?? '') === 'super_admin')
    || (($admin['role'] ?? '') === 'super_admin');

if (!$admin) {
    die('Admin account not found.');
}

// Profile picture path (with cache-busting using filemtime)
if (!empty($admin['profile_picture'])) {
    $profileFile = $admin['profile_picture'];
    $profileAbs  = __DIR__ . '/uploads/profile_pictures/' . $profileFile;
    $version     = is_file($profileAbs) ? filemtime($profileAbs) : time();

    // Add ?v=timestamp para ma-bypass ang cache kahit normal reload lang
    $profilePic  = 'uploads/profile_pictures/' . $profileFile . '?v=' . $version;
} else {
    $profilePic  = 'assets/img/default_profile.png';
}

// Clean fields
$firstName  = trim($admin['first_name'] ?? '');
$lastName   = trim($admin['last_name'] ?? '');
$fullName   = trim($firstName . ' ' . $lastName);
$email      = $admin['email'] ?? '';
$adminTitle = $admin['admin_title'] ?: ($isSuperAdmin ? 'Super Administrator' : 'Administrator');
$status     = $admin['admin_status'] ?? 'inactive';

$roleLabelText = $isSuperAdmin ? 'Super Admin' : 'Admin';

// initials for fallback avatar
$initials = strtoupper(
    ($firstName !== '' ? mb_substr($firstName, 0, 1) : '') .
    ($lastName  !== '' ? mb_substr($lastName, 0, 1)  : '')
);
if ($initials === '') {
    $initials = strtoupper(substr($email, 0, 1) ?: 'A');
}

$scopeCategory = $admin['scope_category'] ?? '';
$scopeLabel    = $scopeCategory ? getScopeCategoryLabel($scopeCategory) : 'No scope';
$scopeDetails  = $scopeCategory
    ? formatScopeDetails($scopeCategory, $admin['scope_details'] ?? '')
    : 'No specific scope assigned';

$assignedScope  = $admin['assigned_scope']   ?? '';
$assignedScope1 = $admin['assigned_scope_1'] ?? '';

if ($isSuperAdmin) {
    $scopeCategory  = 'System-wide';
    $scopeLabel     = 'System-wide';
    $assignedScope  = 'System-wide';
    $assignedScope1 = 'System-wide';
    $scopeDetails   = 'System-wide';
}

$academicYear = $admin['academic_year'] ?? '';
$createdAt    = $admin['created_at'] ?? null;
$lastLogin    = $admin['last_login'] ?? null;
$isVerified   = (int)($admin['is_verified'] ?? 0);

// Derived
function niceDate(?string $dt): string {
    if (!$dt) return 'Not recorded';
    $ts = strtotime($dt);
    if (!$ts) return htmlspecialchars($dt, ENT_QUOTES, 'UTF-8');
    return date('M d, Y h:i A', $ts);
}

// Elections managed
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM elections WHERE assigned_admin_id = :uid");
    $countStmt->execute([':uid' => $userId]);
    $electionsManaged = (int)($countStmt->fetch()['cnt'] ?? 0);
} catch (PDOException $e) {
    error_log('Stats error (admin_profile.php): ' . $e->getMessage());
    $electionsManaged = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - eBalota</title>

    <!-- Tailwind (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
            extend: {
                colors: {
                cvsu: {
                    dark: '#154734',
                    DEFAULT: '#1E6F46',
                    light: '#37A66B',
                    yellow: '#FFD166',
                }
                },
                boxShadow: {
                'soft': '0 10px 30px rgba(15,23,42,0.12)',
                },
                borderRadius: {
                'xl2': '1.25rem',
                }
            }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          referrerpolicy="no-referrer" />

    <!-- Cropper.js CSS (for later JS module) -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />

    <!-- Custom admin profile styles -->
    <link rel="stylesheet" href="assets/styles/admin_profile.css">
</head>
<body class="bg-gray-100 font-sans">
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
        <section class="header-bar">
            <div>
                <h1 class="title">Admin Profile</h1>
                <p class="subtitle">View your account details.</p>
            </div>
            <div class="icon-box">
                <i class="fas fa-user-shield"></i>
            </div>
        </section>

        <!-- MAIN CARD -->
        <section class="main-card mt-6">
            <div class="grid-layout">

                <!-- LEFT: PROFILE / IDENTITY -->
                <div class="profile-section">
                    <div class="picture-wrapper relative inline-block">
                        <img
                            id="profilePreview"
                            src="<?= htmlspecialchars($profilePic) ?>"
                            alt="Profile picture"
                            class="profile-img"
                        >

                        <?php if (empty($admin['profile_picture'])): ?>
                            <!-- Fallback initials kapag wala pang profile picture -->
                            <div class="profile-initials-overlay">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                        <?php endif; ?>

                        <button
                            id="openCropper"
                            type="button"
                            class="edit-btn"
                        >
                            Change
                        </button>
                    </div>

                    <h2 class="profile-name">
                        <?= htmlspecialchars($fullName ?: 'Admin User') ?>
                    </h2>

                    <p class="profile-email">
                        <i class="fas fa-envelope mr-2"></i>
                        <span class="break-all">
                            <?= htmlspecialchars($email) ?>
                        </span>
                    </p>

                    <p class="role-label">
                        <strong><?= htmlspecialchars($adminTitle) ?></strong> · <?= htmlspecialchars($roleLabelText) ?>
                    </p>

                    <div class="badges">
                        <span class="badge <?= $status === 'active' ? 'active' : 'inactive' ?>">
                            <?= ucfirst($status) ?>
                        </span>
                        <?php if ($scopeCategory): ?>
                            <span class="badge scope">
                                <?= htmlspecialchars($scopeLabel) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MIDDLE: ACCOUNT INFO -->
                <div class="info-box">
                    <h3 class="box-title">
                        <i class="fas fa-id-card"></i>
                        ACCOUNT INFORMATION
                    </h3>

                    <p class="info-label">Email</p>
                    <p class="info-value"><?= htmlspecialchars($email) ?></p>

                    <p class="info-label mt-3">Academic Year</p>
                    <p class="info-value">
                        <?= htmlspecialchars($academicYear ?: 'Not set') ?>
                    </p>

                    <p class="info-label mt-3">Registered On</p>
                    <p class="info-value"><?= niceDate($createdAt) ?></p>

                </div>

                <!-- RIGHT: SECURITY / ACTIVITY -->
                <div class="info-box">
                    <h3 class="box-title">
                        <i class="fas fa-shield-alt"></i>
                        SECURITY & ACTIVITY
                    </h3>

                    <p class="info-label">Email Verified</p>
                    <p class="info-value"><?= $isVerified ? 'Yes' : 'No' ?></p>

                    <?php if (!$isSuperAdmin): ?>
                        <p class="info-label mt-3">Elections Managed</p>
                        <p class="info-value"><?= number_format($electionsManaged) ?></p>
                    <?php endif; ?>

                    <p class="info-label mt-3">Status</p>
                    <p class="info-value"><?= ucfirst($status) ?></p>
                </div>

                <!-- SCOPE DETAILS (FULL WIDTH) -->
                <div class="scope-box">
                    <h3 class="box-title">
                        <i class="fas fa-diagram-project"></i>
                        SCOPE & PERMISSIONS
                    </h3>

                    <!-- 3-column layout using Tailwind grid -->
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-6">

                        <!-- Column 1: Scope Category -->
                        <div>
                            <p class="info-label" style="margin-top: 0;">Scope Category</p>
                            <p class="info-value">
                                <?= htmlspecialchars($scopeLabel) ?>
                            </p>
                        </div>

                        <!-- Column 2: Assigned Scope -->
                        <div>
                            <p class="info-label" style="margin-top: 0;">Assigned Scope</p>
                            <p class="info-value">
                                <?= htmlspecialchars($assignedScope) ?>
                                <?php if ($assignedScope1 && $assignedScope1 !== $assignedScope): ?>
                                    <span class="text-gray-500"> · </span>
                                    <span><?= htmlspecialchars($assignedScope1) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Column 3: Scope Details -->
                        <div>
                            <p class="info-label" style="margin-top: 0;">Scope Details</p>
                            <p class="info-value">
                                <?= htmlspecialchars($scopeDetails) ?>
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- ACTION BUTTONS -->
        <section class="actions">
            <a href="admin_activity_logs.php" class="btn-primary">
                <i class="fas fa-clipboard-list"></i>
                Activity Logs
            </a>
            <a href="admin_dashboard_redirect.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>
        </section>

    </main>
</div>

<!-- CROP MODAL (markup only, logic is in admin_cropper.js) -->
<?php include 'admin_profile_cropper_modal.php'; ?>

<!-- SUCCESS MODAL (Premium Glass Design) -->
<div
    id="profileSuccessModal"
    class="fixed inset-0 z-[999] hidden flex items-center justify-center
           bg-black/20 backdrop-blur-sm transition-opacity duration-300"
>
    <div
        class="bg-white/90 backdrop-blur-xl shadow-2xl rounded-2xl p-8 w-full max-w-sm
               transform transition-all duration-300 scale-95 opacity-0"
        id="profileSuccessCard"
    >
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center">
                <i class="fas fa-check text-emerald-600 text-3xl"></i>
            </div>
        </div>

        <h3 class="text-xl font-semibold text-gray-800 text-center mb-2">
            Profile Picture Updated
        </h3>

        <p id="profileSuccessMessage"
           class="text-gray-600 text-center text-sm mb-6 leading-relaxed">
            Your profile picture has been successfully changed.
        </p>

        <div class="flex justify-center">
            <button
                id="profileSuccessOk"
                class="px-6 py-2.5 rounded-lg bg-emerald-600 text-white text-sm
                       font-semibold hover:bg-emerald-700 shadow-md hover:shadow-lg
                       transition-all"
            >
                Continue
            </button>
        </div>
    </div>
</div>

<!-- Cropper.js + custom JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script src="assets/js/admin_cropper.js"></script>
<script>
function showSuccessModal() {
    const modal = document.getElementById('profileSuccessModal');
    const card  = document.getElementById('profileSuccessCard');

    modal.classList.remove('hidden');

    // Animation in
    setTimeout(() => {
        card.classList.remove('scale-95', 'opacity-0');
        card.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function hideSuccessModalAndReload() {
    const modal = document.getElementById('profileSuccessModal');
    const card  = document.getElementById('profileSuccessCard');

    // Animation out
    card.classList.add('scale-95', 'opacity-0');

    setTimeout(() => {
        modal.classList.add('hidden');
        window.location.reload();
    }, 200);
}
</script>


</body>
</html>

