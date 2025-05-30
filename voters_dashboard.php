<?php
session_start();
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$department_map = [
    'College of Engineering and Information Technology (CEIT)' => 'CEIT',
    'College of Business Administration (CBA)' => 'CBA',
    'College of Education (COED)' => 'COED',
    'College of Agriculture (CA)' => 'CA',
    'College of Arts and Sciences (CAS)' => 'CAS',
    'College of Criminal Justice (CCJ)' => 'CCJ',
    // dagdag pa kung may iba pang colleges
];

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

// Redirect if not logged in or not a voter
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: ../login.php");
    exit;
}

$course = $_SESSION['course'] ?? '';
$status = $_SESSION['status'] ?? '';

// Get voter info from session
$voter_id = $_SESSION['user_id'];
$voter_role = $_SESSION['position'];       // 'student', 'faculty', or 'coop'
$voter_department_full = $_SESSION['department'] ?? 'All';

// Map full college name to abbreviation, default to 'All'
$voter_department = $department_map[$voter_department_full] ?? 'All';

// Prepare SQL to get ongoing elections targeting user's role and department
$sql = "SELECT * FROM elections 
        WHERE status = 'ongoing'
        AND (target_position = :role OR target_position = 'All')
        AND (target_department = :department OR target_department = 'All')
        ORDER BY election_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':role', $voter_role);
$stmt->bindParam(':department', $voter_department);
$stmt->execute();
$all_elections = $stmt->fetchAll();

// Filter elections by allowed_colleges field (flexible multiple allowed colleges)
$filtered_elections = [];
foreach ($all_elections as $election) {
    $allowed_colleges = $election['allowed_colleges'];
    // If allowed_colleges is 'All', allow all voters
    if (strtolower($allowed_colleges) === 'all') {
        $filtered_elections[] = $election;
        continue;
    }

    // Otherwise, explode allowed colleges by comma, trim spaces
    $allowed_list = array_map('trim', explode(',', $allowed_colleges));
    if (in_array($voter_department, $allowed_list)) {
        $filtered_elections[] = $election;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voter Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-2xl font-bold mb-4">Welcome to Your Voter Dashboard</h1>

    <?php if (count($filtered_elections) > 0): ?>
        <div class="grid gap-4">
            <?php foreach ($filtered_elections as $election): ?>
                <div class="bg-white rounded-lg shadow p-4">
                    <h2 class="text-xl font-semibold"><?= htmlspecialchars($election['title']) ?></h2>
                    <p class="text-gray-600"><?= htmlspecialchars($election['description']) ?></p>
                    <p class="text-sm text-gray-500">
                        Target: <?= htmlspecialchars($election['target_position']) ?> - <?= htmlspecialchars($election['target_department']) ?><br>
                        Allowed Colleges: <?= htmlspecialchars($election['allowed_colleges']) ?>
                    </p>
                    <a href="view_candidates.php?election_id=<?= $election['election_id'] ?>" class="mt-2 inline-block text-white bg-blue-500 px-4 py-2 rounded hover:bg-blue-600">
                        View Candidates / Vote
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-gray-700">No available elections for your role or department.</p>
    <?php endif; ?>
</body>
</html>
