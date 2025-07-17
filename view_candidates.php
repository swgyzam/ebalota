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

$stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if (!$election) {
    die("Election not found.");
}

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE election_id = ?");
$stmt->execute([$election_id]);
$candidates = $stmt->fetchAll();

$groupedCandidates = [];
foreach ($candidates as $candidate) {
    $groupedCandidates[$candidate['position']][] = $candidate;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($election['title']) ?> - Ballot</title>
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
  <header class="mb-10 text-center">
    <h1 class="text-4xl font-extrabold text-[var(--cvsu-green-dark)]"><?= htmlspecialchars($election['title']) ?></h1>
    <p class="mt-2 text-gray-700 whitespace-pre-line"><?= nl2br(htmlspecialchars($election['description'])) ?></p>
  </header>

  <?php if (!empty($groupedCandidates)): ?>
    <form method="POST" action="submit_vote.php" onsubmit="return validateVotes()" class="space-y-10">
  <input type="hidden" name="election_id" value="<?= htmlspecialchars($election['election_id']) ?>">

  <?php foreach ($groupedCandidates as $position => $candidates): ?>
    <?php
      $positionLower = strtolower($position);
      $isSingleVote = in_array($positionLower, ['president', 'vice-president']);
    ?>
    <section class="bg-white p-6 rounded-xl shadow-md border-l-8 border-[var(--cvsu-green)]">
      <header class="mb-4">
        <h2 class="text-2xl font-bold text-[var(--cvsu-green-dark)]"><?= htmlspecialchars($position) ?></h2>
        <p class="text-sm text-gray-600">
          <?= $isSingleVote ? 'Select only one candidate' : 'Select at least 6 candidates as you prefer' ?>
        </p>
      </header>

      <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-2">
        <?php foreach ($candidates as $candidate): ?>
          <label class="flex items-start gap-4 p-4 rounded-lg border border-gray-200 hover:border-[var(--cvsu-green-light)] transition cursor-pointer bg-gray-50">
            <input
              type="<?= $isSingleVote ? 'radio' : 'checkbox' ?>"
              name="vote[<?= htmlspecialchars($position) ?>]<?= $isSingleVote ? '' : '[]' ?>"
              value="<?= $candidate['id'] ?>"
              class="mt-1 w-5 h-5 accent-[var(--cvsu-green-light)] <?= !$isSingleVote ? 'multi-checkbox' : '' ?>"
            />
            <div class="flex-1">
              <p class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($candidate['full_name']) ?></p>
              <?php if (!empty($candidate['manifesto'])): ?>
                <p class="mt-1 text-sm text-gray-600 whitespace-pre-line"><?= htmlspecialchars($candidate['manifesto']) ?></p>
              <?php endif; ?>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="text-center">
    <button
      type="submit"
      class="inline-block bg-[var(--cvsu-green-light)] hover:bg-[var(--cvsu-green)] text-white text-lg px-10 py-3 rounded-xl font-semibold shadow-sm transition-colors duration-300"
    >
      Submit Vote
    </button>
  </div>
</form>

  <?php else: ?>
    <p class="text-gray-600 text-center">No candidates available for this election.</p>
  <?php endif; ?>

  <div class="mt-10 text-center">
    <a href="voters_dashboard.php" class="text-[var(--cvsu-green)] underline">&larr; Back to Elections</a>
  </div>
</main>

<script>
function validateVotes() {
  const multiChecked = document.querySelectorAll('.multi-checkbox:checked');
  const totalMulti = multiChecked.length;

  if (totalMulti < 6) {
    alert('You must select at least 6 candidates in total (excluding President and Vice President).');
    return false;
  }

  return true;
}
</script>

</body>
</html>
