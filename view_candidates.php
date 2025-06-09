<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: login.html");
    exit;
}

if (!isset($_GET['election_id'])) {
    die("Election ID not specified.");
}

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

$election_id = (int)$_GET['election_id'];

// Get election info
$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if (!$election) {
    die("Election not found.");
}

// Fetch candidates for this election
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE election_id = ?");
$stmt->execute([$election_id]);
$candidates = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($election['title']) ?> - Candidates</title>
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
<body class="bg-gray-50 text-gray-900 font-sans">

<main class="max-w-4xl mx-auto p-8">
  <header class="mb-8">
    <h1 class="text-3xl font-extrabold text-[var(--cvsu-green-dark)]"><?= htmlspecialchars($election['title']) ?></h1>
    <p class="mt-2 text-gray-700 whitespace-pre-line"><?= htmlspecialchars($election['description']) ?></p>
  </header>

  <?php if (count($candidates) > 0): ?>
    <div class="space-y-6 mb-8">
      <?php foreach ($candidates as $candidate): ?>
        <div class="bg-white rounded-lg shadow p-6 border-l-8 border-[var(--cvsu-green)]">
          <h2 class="text-xl font-semibold"><?= htmlspecialchars($candidate['full_name']) ?></h2>
          <?php if (!empty($candidate['position'])): ?>
            <p class="text-sm italic text-gray-600">Position: <?= htmlspecialchars($candidate['position']) ?></p>
          <?php endif; ?>
          <?php if (!empty($candidate['manifesto'])): ?>
            <p class="mt-2 text-gray-700 whitespace-pre-line"><?= htmlspecialchars($candidate['manifesto']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="submit_vote.php" class="bg-white p-6 rounded shadow">
      <input type="hidden" name="election_id" value="<?= htmlspecialchars($election['election_id']) ?>">
      <fieldset>
        <legend class="text-xl font-bold mb-4 text-[var(--cvsu-green-dark)]">Select your candidate:</legend>
        <?php foreach ($candidates as $candidate): ?>
          <label class="flex items-center mb-4 cursor-pointer">
            <input type="radio" name="id" value="<?= $candidate['id'] ?>" required class="mr-3 w-5 h-5 accent-[var(--cvsu-green-light)]" />
            <span class="text-lg"><?= htmlspecialchars($candidate['full_name']) ?> — <span class="italic text-gray-600"><?= htmlspecialchars($candidate['position']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <button type="submit" class="mt-4 bg-[var(--cvsu-green-light)] hover:bg-[var(--cvsu-green)] text-white px-6 py-3 rounded font-semibold transition-colors duration-300">
        Submit Vote
      </button>
    </form>

  <?php else: ?>
    <p class="text-gray-600">No candidates found for this election.</p>
  <?php endif; ?>

  <p class="mt-8">
    <a href="voters_dashboard.php" class="text-[var(--cvsu-green)] underline">&larr; Back to Elections</a>
  </p>
</main>

</body>
</html>
