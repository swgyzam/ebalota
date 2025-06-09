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
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Session timeout 1 hour ---
$timeout_duration = 3600;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Session expired. Please login again.');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// --- Check if logged in and admin ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit();
}

// --- Position Options ---
$positionOptions = [
    'President',
    'Vice President',
    'Secretary',
    'Treasurer',
    'Auditor',
    'P.R.O.',
    'Senator',
    'Representative',
    'Governor',
    'Mayor'
];

// --- Handle Form Submission ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $election_id = $_POST['election_id'] ?? '';
    $candidate_name = trim($_POST['candidate_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $manifesto = trim($_POST['manifesto'] ?? '');

    // Validate inputs
    if (empty($election_id) || empty($candidate_name) || empty($position)) {
        $error = "Please fill all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO candidates (election_id, candidate_name, position, manifesto) VALUES (?, ?, ?, ?)");
            $stmt->execute([$election_id, $candidate_name, $position, $manifesto]);
            $message = "Candidate added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding candidate: " . $e->getMessage();
        }
    }
}

// Get elections for dropdown
$elections = $pdo->query("SELECT election_id, title FROM elections ORDER BY start_datetime DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Candidate - Admin Panel</title>
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
<body class="bg-gray-50 font-sans text-gray-900">

  <div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-8 ml-64">
      <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <h1 class="text-3xl font-extrabold">Add New Candidate</h1>
        <a href="manage_candidates.php" class="px-4 py-2 bg-[var(--cvsu-green)] text-white rounded hover:bg-[var(--cvsu-green-light)] transition">Back to Candidates</a>
      </header>

      <?php if ($message): ?>
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" class="space-y-6">
          <div>
            <label class="block mb-2 font-semibold text-gray-700">Election *</label>
            <select name="election_id" required class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]">
              <option value="">-- Select Election --</option>
              <?php foreach ($elections as $election): ?>
                <option value="<?= htmlspecialchars($election['election_id']) ?>">
                  <?= htmlspecialchars($election['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700">Candidate Name *</label>
            <input type="text" name="candidate_name" required 
                   class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]">
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700">Position *</label>
            <select name="position" required class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]">
              <option value="">-- Select Position --</option>
              <?php foreach ($positionOptions as $option): ?>
                <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700">Manifesto (Optional)</label>
            <textarea name="manifesto" rows="4" 
                      class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"></textarea>
          </div>

          <div class="pt-4">
            <button type="submit" class="px-6 py-3 bg-[var(--cvsu-green)] text-white font-semibold rounded hover:bg-[var(--cvsu-green-light)] transition">
              Add Candidate
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>

</body>
</html>