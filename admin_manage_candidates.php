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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

 $userId = $_SESSION['user_id'];

// --- Fetch user info ---
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = :userId");
 $stmt->execute([':userId' => $userId]);
 $user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

 $role = $user['role'];
 $assignedScope = $user['assigned_scope'];

// --- Fetch elections for dropdown (ONLY elections assigned to this admin) ---
 $electionStmt = $pdo->prepare("SELECT election_id, title FROM elections 
                               WHERE assigned_admin_id = :adminId
                               ORDER BY title ASC");
 $electionStmt->execute([':adminId' => $userId]);
 $elections = $electionStmt->fetchAll();

// Fetch distinct positions for dropdown from election_candidates, but only for candidates created by the current admin
 $positionsStmt = $pdo->prepare("SELECT DISTINCT ec.position 
                              FROM election_candidates ec
                              JOIN candidates c ON ec.candidate_id = c.id
                              WHERE c.created_by = ?
                              ORDER BY ec.position ASC");
 $positionsStmt->execute([$userId]);
 $positions = $positionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get filters
 $filterPosition = $_GET['position'] ?? '';
 $filterElection = $_GET['election'] ?? '';

// Pagination settings
 $limit = 10; // 10 candidates per page
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $offset = ($page - 1) * $limit;

// Build query - IMPORTANT: WHERE c.created_by = ? ensures only this admin's candidates
 $query = "SELECT c.id, c.first_name, c.middle_name, c.last_name, c.photo, 
                 c.credentials, ec.position, e.title as election_name, e.election_id, c.created_at 
          FROM candidates c
          LEFT JOIN election_candidates ec ON c.id = ec.candidate_id
          LEFT JOIN elections e ON ec.election_id = e.election_id
          WHERE c.created_by = ?"; // DITO NAKA-FILTER PER ADMIN
 $conditions = [];
 $params = [$userId]; // DITO NAKALAGAY YUNG USER ID NG CURRENT ADMIN

// Add additional filters if specified
if (!empty($filterElection)) {
    $conditions[] = "ec.election_id = ?";
    $params[] = $filterElection;
}

if (!empty($filterPosition) && in_array($filterPosition, $positions)) {
    $conditions[] = "ec.position = ?";
    $params[] = $filterPosition;
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

 $query .= " ORDER BY c.created_at DESC";

// Add pagination directly to the query (not as parameters)
 $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

// Execute the query with pagination
 $stmt = $pdo->prepare($query);
 $stmt->execute($params);
 $candidates = $stmt->fetchAll();

// Get total number of candidates for pagination
 $countQuery = "SELECT COUNT(*) as total 
               FROM candidates c
               LEFT JOIN election_candidates ec ON c.id = ec.candidate_id
               WHERE c.created_by = ?";
 $countParams = [$userId];

if (!empty($filterElection)) {
    $countQuery .= " AND ec.election_id = ?";
    $countParams[] = $filterElection;
}

if (!empty($filterPosition) && in_array($filterPosition, $positions)) {
    $countQuery .= " AND ec.position = ?";
    $countParams[] = $filterPosition;
}

 $countStmt = $pdo->prepare($countQuery);
 $countStmt->execute($countParams);
 $totalCandidates = $countStmt->fetch()['total'];
 $totalPages = ceil($totalCandidates / $limit);
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Candidates - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    
    .pagination-btn {
      transition: all 0.2s ease;
    }
    
    .pagination-btn:hover:not(.active):not(:disabled) {
      background-color: #e5e7eb;
    }
    
    .pagination-btn.active {
      background-color: var(--cvsu-green);
      color: white;
    }
    
    .pagination-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">
  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <main class="flex-1 p-8 ml-64">
    <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
      <h1 class="text-3xl font-extrabold">Manage Candidates</h1>
      <div class="flex items-center gap-4">
        <a href="add_candidate.php" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">Add Candidate</a>
      </div>
    </header>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap gap-4 items-center bg-white p-4 rounded shadow">
      <div>
        <label for="election" class="font-semibold">Filter by Election:</label>
        <select id="election" name="election" class="border rounded px-3 py-2" onchange="filter()">
          <option value="" <?= empty($filterElection) ? 'selected' : '' ?>>All Elections</option>
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
          <option value="" <?= empty($filterPosition) ? 'selected' : '' ?>>All Positions</option>
          <?php foreach ($positions as $pos): ?>
            <option value="<?= htmlspecialchars($pos) ?>" <?= ($pos == $filterPosition) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pos) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="ml-auto">
        <a href="admin_manage_candidates.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded font-medium">
          <i class="fas fa-sync-alt mr-2"></i>Reset Filters
        </a>
      </div>
    </div>

    <!-- Pagination Info -->
    <div class="mb-4 flex justify-between items-center bg-white p-3 rounded shadow">
      <div class="text-sm text-gray-700">
        Showing <span class="font-semibold"><?= ($offset + 1) ?></span> to 
        <span class="font-semibold"><?= min($offset + $limit, $totalCandidates) ?></span> of 
        <span class="font-semibold"><?= $totalCandidates ?></span> candidates
      </div>
    </div>

    <!-- Candidates Table -->
    <div class="overflow-x-auto bg-white rounded shadow-lg">
      <table class="min-w-full table-auto">
        <thead class="bg-[var(--cvsu-green)] text-white">
          <tr>
            <th class="py-3 px-6 text-left">Photo</th>
            <th class="py-3 px-6 text-left">Name</th>
            <th class="py-3 px-6 text-left">Position</th>
            <th class="py-3 px-6 text-left">Election</th>
            <th class="py-3 px-6 text-left">Credentials</th>
            <th class="py-3 px-6 text-left">Created</th>
            <th class="py-3 px-6 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($candidates) > 0): ?>
            <?php foreach ($candidates as $c): ?>
              <tr class="border-b hover:bg-gray-100">
                <td class="py-3 px-6">
                  <?php if (!empty($c['photo'])): ?>
                    <img src="<?= htmlspecialchars($c['photo']) ?>" alt="photo" class="h-12 w-12 rounded-full object-cover">
                  <?php else: ?>
                    <div class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center">
                      <i class="fas fa-user text-gray-400"></i>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-6 font-medium">
                  <?= htmlspecialchars($c['first_name'] . ' ' . ($c['middle_name'] ?? '') . ' ' . $c['last_name']) ?>
                </td>
                <td class="py-3 px-6">
                  <?php if (!empty($c['position'])): ?>
                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                      <?= htmlspecialchars($c['position']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-6">
                  <?= !empty($c['election_name']) ? htmlspecialchars($c['election_name']) : '<span class="text-gray-400">Not assigned</span>' ?>
                </td>
                <td class="py-3 px-6">
                  <?php if (!empty($c['credentials'])): ?>
                    <a href="<?= htmlspecialchars($c['credentials']) ?>" target="_blank" class="text-blue-600 underline">View</a>
                  <?php else: ?>
                    <span class="text-gray-400">N/A</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-6"><?= date("M d, Y", strtotime($c['created_at'])) ?></td>
                <td class="py-3 px-6 text-center space-x-2">
                  <a href="edit_candidate.php?id=<?= $c['id'] ?>" class="text-blue-500 hover:text-blue-600 font-semibold">
                    <i class="fas fa-edit mr-1"></i>Edit
                  </a>
                  <a href="delete_candidate.php?id=<?= $c['id'] ?>" class="text-red-600 hover:text-red-700 font-semibold" onclick="return confirm('Are you sure you want to delete this candidate?');">
                    <i class="fas fa-trash mr-1"></i>Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center py-6 text-gray-500">
                No candidates found. <a href="add_candidate.php" class="text-green-600 hover:underline">Add your first candidate</a>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex justify-center">
      <nav class="flex items-center space-x-1">
        <!-- Previous Button -->
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?><?= !empty($filterElection) ? '&election=' . $filterElection : '' ?><?= !empty($filterPosition) ? '&position=' . $filterPosition : '' ?>" 
             class="pagination-btn px-3 py-1 rounded border">
            <i class="fas fa-chevron-left"></i>
          </a>
        <?php else: ?>
          <button class="pagination-btn px-3 py-1 rounded border" disabled>
            <i class="fas fa-chevron-left"></i>
          </button>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php 
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          
          if ($startPage > 1) {
            echo '<a href="?page=1' . (!empty($filterElection) ? '&election=' . $filterElection : '') . (!empty($filterPosition) ? '&position=' . $filterPosition : '') . '" class="pagination-btn px-3 py-1 rounded border">1</a>';
            if ($startPage > 2) {
              echo '<span class="px-2">...</span>';
            }
          }
          
          for ($i = $startPage; $i <= $endPage; $i++) {
            $activeClass = $i == $page ? 'active' : '';
            echo '<a href="?page=' . $i . (!empty($filterElection) ? '&election=' . $filterElection : '') . (!empty($filterPosition) ? '&position=' . $filterPosition : '') . '" class="pagination-btn px-3 py-1 rounded border ' . $activeClass . '">' . $i . '</a>';
          }
          
          if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
              echo '<span class="px-2">...</span>';
            }
            echo '<a href="?page=' . $totalPages . (!empty($filterElection) ? '&election=' . $filterElection : '') . (!empty($filterPosition) ? '&position=' . $filterPosition : '') . '" class="pagination-btn px-3 py-1 rounded border">' . $totalPages . '</a>';
          }
        ?>

        <!-- Next Button -->
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?><?= !empty($filterElection) ? '&election=' . $filterElection : '' ?><?= !empty($filterPosition) ? '&position=' . $filterPosition : '' ?>" 
             class="pagination-btn px-3 py-1 rounded border">
            <i class="fas fa-chevron-right"></i>
          </a>
        <?php else: ?>
          <button class="pagination-btn px-3 py-1 rounded border" disabled>
            <i class="fas fa-chevron-right"></i>
          </button>
        <?php endif; ?>
      </nav>
    </div>
    <?php endif; ?>
  </main>
</div>

<script>
  function filter() {
    const election = document.getElementById('election').value;
    const position = document.getElementById('position').value;
    
    const url = new URL(window.location.href);
    
    // Reset to page 1 when applying new filters
    url.searchParams.delete('page');
    
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