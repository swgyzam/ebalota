<?php
session_start();
date_default_timezone_set('Asia/Manila');

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

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit();
}

// Fetch elections for dropdown
$electionStmt = $pdo->query("SELECT election_id, title FROM elections ORDER BY title ASC");
$elections = $electionStmt->fetchAll();

// Fetch distinct positions for the dropdown
$positionsStmt = $pdo->query("SELECT DISTINCT position FROM candidates ORDER BY position ASC");
$positions = $positionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get filters from GET request
$filterPosition = $_GET['position'] ?? '';
$filterElection = $_GET['election'] ?? '';

// Build dynamic query with filters
$query = "SELECT id, full_name, position, party_list, manifesto, platform FROM candidates";
$conditions = [];
$params = [];

if (!empty($filterElection)) {
    $conditions[] = "election_id = ?";
    $params[] = $filterElection;
}

if (!empty($filterPosition) && in_array($filterPosition, $positions)) {
    $conditions[] = "position = ?";
    $params[] = $filterPosition;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY full_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidates = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Candidates - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    ::-webkit-scrollbar {
      width: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: var(--cvsu-green-light);
      border-radius: 3px;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

  <div class="flex min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-8 ml-64">
      <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <h1 class="text-3xl font-extrabold">Manage Candidates</h1>
        <a href="add_candidate.php" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">Add Candidate</a>
      </header>

      <!-- Filters -->
      <div class="mb-4 flex flex-wrap gap-4 items-center">
        <div>
          <label for="election" class="font-semibold">Filter by Election:</label>
          <select id="election" name="election" class="border rounded px-3 py-2" onchange="filter()">
            <option value="" <?= $filterElection === '' ? 'selected' : '' ?>>All Elections</option>
            <?php foreach ($elections as $e): ?>
              <option value="<?= $e['election_id'] ?>" <?= ($e['election_id'] == $filterElection) ? 'selected' : '' ?>>
                <?= htmlspecialchars($e['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="position" class="font-semibold">Filter by Position:</label>
          <select id="position" name="position" class="border rounded px-3 py-2" onchange="filter()">
            <option value="" <?= $filterPosition === '' ? 'selected' : '' ?>>All Positions</option>
            <?php foreach ($positions as $pos): ?>
              <option value="<?= htmlspecialchars($pos) ?>" <?= ($pos === $filterPosition) ? 'selected' : '' ?>>
                <?= htmlspecialchars($pos) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto bg-white rounded shadow-lg">
        <table class="min-w-full table-auto">
          <thead class="bg-[var(--cvsu-green)] text-white">
            <tr>
              <th class="py-3 px-6 text-left">Name</th>
              <th class="py-3 px-6 text-left">Position</th>
              <th class="py-3 px-6 text-left">Party List</th>
              <th class="py-3 px-6 text-left">Manifesto</th>
              <th class="py-3 px-6 text-left">Platform</th>
              <th class="py-3 px-6 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($candidates) > 0): ?>
              <?php foreach ($candidates as $c): ?>
                <tr class="border-b hover:bg-gray-100">
                  <td class="py-3 px-6"><?= htmlspecialchars($c['full_name']) ?></td>
                  <td class="py-3 px-6"><?= htmlspecialchars($c['position'] ?? 'N/A') ?></td>
                  <td class="py-3 px-6"><?= htmlspecialchars($c['party_list'] ?? '') ?></td>
                  <td class="py-3 px-6"><?= htmlspecialchars(substr($c['manifesto'] ?? '', 0, 50)) ?>...</td>
                  <td class="py-3 px-6"><?= htmlspecialchars(substr($c['platform'] ?? '', 0, 50)) ?>...</td>
                  <td class="py-3 px-6 text-center space-x-2">
                    <a href="edit_candidate.php?id=<?= $c['id'] ?>" class="text-yellow-500 hover:text-yellow-600 font-semibold">Edit</a>
                    <form action="delete_candidate.php" method="POST" class="inline" onsubmit="return confirm('Delete this candidate?');">
                      <input type="hidden" name="id" value="<?= $c['id'] ?>">
                      <button type="submit" class="text-red-600 hover:text-red-700 font-semibold">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-6 text-gray-500">No candidates found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <script>
    function filter() {
      const election = document.getElementById('election').value;
      const position = document.getElementById('position').value;

      const url = new URL(window.location.href);
      if (election) {
        url.searchParams.set('election', election);
      } else {
        url.searchParams.delete('election');
      }

      if (position) {
        url.searchParams.set('position', position);
      } else {
        url.searchParams.delete('position');
      }

      window.location.href = url.toString();
    }
  </script>

</body>
</html>
