<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Redirect if not super admin or no form data
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !isset($_SESSION['election_data'])) {
    header('Location: login.html');
    exit();
}

// Database connection (same as create_election.php)
$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get form data from session
$formData = $_SESSION['election_data'];
$hasLogo = isset($_SESSION['election_logo_tmp']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Election Release - E-Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --cvsu-green-dark: #154734;
            --cvsu-green: #1E6F46;
            --cvsu-green-light: #37A66B;
            --cvsu-yellow: #FFD166;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex">
        <?php include 'super_admin_sidebar.php'; ?>

        <main class="flex-1 p-8 ml-64">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-[var(--cvsu-green-dark)]">Confirm Election Release</h1>
                        <a href="manage_elections.php" class="text-gray-500 hover:text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Election Details</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <p class="text-sm text-gray-600">Election Name</p>
                                <p class="font-medium"><?= htmlspecialchars($formData['election_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Description</p>
                                <p class="font-medium"><?= !empty($formData['description']) ? htmlspecialchars($formData['description']) : 'None' ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Start Date</p>
                                <p class="font-medium"><?= htmlspecialchars($formData['start_date']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">End Date</p>
                                <p class="font-medium"><?= htmlspecialchars($formData['end_date']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Target Voters</p>
                                <p class="font-medium"><?= ucfirst(str_replace('_', ' ', $formData['target_voter'])) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Real-time Results</p>
                                <p class="font-medium"><?= isset($formData['realtime_results']) ? 'Enabled' : 'Disabled' ?></p>
                            </div>
                        </div>

                        <?php if ($hasLogo): ?>
                        <div class="mb-6">
                            <p class="text-sm text-gray-600 mb-2">Election Logo</p>
                            <div class="w-32 h-32 rounded-full overflow-hidden border-2 border-gray-200">
                                <img src="<?= htmlspecialchars('data:image/jpeg;base64,'.base64_encode(file_get_contents($_SESSION['election_logo_tmp']))) ?>" 
                                     alt="Logo Preview" 
                                     class="w-full h-full object-cover">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Scope Details -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-3">Voter Scope Details</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if ($formData['target_voter'] === 'student'): ?>
                                    <p class="mb-2"><span class="font-medium">Allowed Colleges:</span> 
                                        <?= $formData['allowed_colleges_student'] === 'all' ? 'All Colleges' : htmlspecialchars($formData['allowed_colleges_student']) ?>
                                    </p>
                                    <?php if (!empty($formData['allowed_courses_student'])): ?>
                                        <p><span class="font-medium">Allowed Courses:</span> 
                                            <?= implode(', ', array_map('htmlspecialchars', $formData['allowed_courses_student'])) ?>
                                        </p>
                                    <?php endif; ?>
                                
                                <?php elseif ($formData['target_voter'] === 'academic'): ?>
                                    <p class="mb-2"><span class="font-medium">Allowed Colleges:</span> 
                                        <?= $formData['allowed_colleges_academic'] === 'all' ? 'All Colleges' : htmlspecialchars($formData['allowed_colleges_academic']) ?>
                                    </p>
                                    <?php if (!empty($formData['allowed_courses_academic'])): ?>
                                        <p class="mb-2"><span class="font-medium">Allowed Courses:</span> 
                                            <?= implode(', ', array_map('htmlspecialchars', $formData['allowed_courses_academic'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($formData['allowed_status_academic'])): ?>
                                        <p><span class="font-medium">Allowed Status:</span> 
                                            <?= implode(', ', array_map('htmlspecialchars', $formData['allowed_status_academic'])) ?>
                                        </p>
                                    <?php endif; ?>
                                
                                <?php elseif ($formData['target_voter'] === 'non_academic'): ?>
                                    <p class="mb-2"><span class="font-medium">Allowed Departments:</span> 
                                        <?= $formData['allowed_departments_nonacad'] === 'all' ? 'All Departments' : htmlspecialchars($formData['allowed_departments_nonacad']) ?>
                                    </p>
                                    <?php if (!empty($formData['allowed_status_nonacad'])): ?>
                                        <p><span class="font-medium">Allowed Status:</span> 
                                            <?= implode(', ', array_map('htmlspecialchars', $formData['allowed_status_nonacad'])) ?>
                                        </p>
                                    <?php endif; ?>
                                
                                <?php elseif ($formData['target_voter'] === 'coop'): ?>
                                    <p><span class="font-medium">Allowed Status:</span> MIGS</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <form action="create_election.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <!-- Hidden fields to preserve all form data -->
                            <?php foreach ($formData as $key => $value): ?>
                                <?php if (is_array($value)): ?>
                                    <?php foreach ($value as $val): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($val) ?>">
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Flag for confirmed submission -->
                            <input type="hidden" name="confirmed" value="true">
                            
                            <!-- Re-include the logo file if exists -->
                            <?php if ($hasLogo): ?>
                                <input type="hidden" name="election_logo_name" value="<?= htmlspecialchars($_SESSION['election_logo_name']) ?>">
                            <?php endif; ?>

                            <div class="flex justify-between">
                                <a href="manage_elections.php" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                                    Cancel
                                </a>
                                <button type="submit" class="px-6 py-2 bg-[var(--cvsu-green)] text-white rounded-md hover:bg-[var(--cvsu-green-dark)] transition">
                                    Confirm & Release to Admins
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php
// Clear the temporary session data
unset($_SESSION['election_data']);
if (isset($_SESSION['election_logo_tmp'])) {
    unset($_SESSION['election_logo_tmp']);
    unset($_SESSION['election_logo_name']);
}
?>