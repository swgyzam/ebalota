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

// --- Handle Form Submission ---
$errors = [];
$success = '';

$full_name = $position = $party_list = $credentials = $manifesto = $platform = '';
$election_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $election_id = $_POST['election_id'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $party_list = trim($_POST['party_list'] ?? '');
    $credentials = trim($_POST['credentials'] ?? '');
    $manifesto = trim($_POST['manifesto'] ?? '');
    $platform = trim($_POST['platform'] ?? '');

    // Validate required fields
    if (empty($election_id)) {
        $errors[] = "Election selection is required.";
    }
    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($position)) {
        $errors[] = "Position is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO candidates (election_id, full_name, position, party_list, credentials, manifesto, platform) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$election_id, $full_name, $position, $party_list, $credentials, $manifesto, $platform]);
            $success = "Candidate added successfully.";

            // Clear form values after success
            $full_name = $position = $party_list = $credentials = $manifesto = $platform = '';
            $election_id = '';
        } catch (PDOException $e) {
            $errors[] = "Error adding candidate: " . $e->getMessage();
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
    <?php // include 'sidebar.php'; // Uncomment if you have a sidebar ?>

    <main class="flex-1 p-8 <?php // if sidebar included, add 'ml-64' ?>">
      <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <h1 class="text-3xl font-extrabold">Add New Candidate</h1>
        <a href="manage_candidates.php" class="px-4 py-2 bg-[var(--cvsu-green)] text-white rounded hover:bg-[var(--cvsu-green-light)] transition">Back to Candidates</a>
      </header>

      <?php if ($errors): ?>
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
          <ul class="list-disc list-inside">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <div class="bg-white rounded-lg shadow p-6 max-w-3xl mx-auto">
        <form method="POST" class="space-y-6" novalidate>

          <div>
            <label class="block mb-2 font-semibold text-gray-700">Election *</label>
            <select name="election_id" required
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]">
              <option value="">-- Select Election --</option>
              <?php foreach ($elections as $election): ?>
                <option value="<?= htmlspecialchars($election['election_id']) ?>" <?= ($election_id == $election['election_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($election['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700" for="full_name">Full Name <span class="text-red-600">*</span></label>
            <input type="text" name="full_name" id="full_name" required
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"
              value="<?= htmlspecialchars($full_name) ?>" />
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700" for="position">Position <span class="text-red-600">*</span></label>
            <input type="text" name="position" id="position" required placeholder="e.g. President, Secretary"
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"
              value="<?= htmlspecialchars($position) ?>" />
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700" for="party_list">Party List</label>
            <input type="text" name="party_list" id="party_list"
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"
              value="<?= htmlspecialchars($party_list) ?>" />
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700" for="credentials">Credentials</label>
            <textarea name="credentials" id="credentials" rows="3"
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"><?= htmlspecialchars($credentials) ?></textarea>
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700" for="manifesto">Manifesto</label>
            <textarea name="manifesto" id="manifesto" rows="3"
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"><?= htmlspecialchars($manifesto) ?></textarea>
          </div>

          <div>
            <label class="block mb-2 font-semibold text-gray-700" for="platform">Platform</label>
            <textarea name="platform" id="platform" rows="3"
              class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-[var(--cvsu-green-light)] focus:border-[var(--cvsu-green-light)]"><?= htmlspecialchars($platform) ?></textarea>
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
