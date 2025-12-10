<?php 
session_start();
date_default_timezone_set('Asia/Manila');

/* ==========================================================
   DB CONNECTION
   ========================================================== */
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed.");
}

/* ==========================================================
   AUTH CHECK
   ========================================================== */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

/* ==========================================================
   LOAD ROLE (for “no elections” messaging)
   ========================================================== */
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = :userId");
$stmt->execute([':userId' => $userId]);
$userRow = $stmt->fetch();
$role    = $userRow['role'] ?? '';

/* ==========================================================
   SHARED ELECTION SCOPE / ANALYTICS HELPERS
   ========================================================== */
   require_once __DIR__ . '/includes/auth_helpers.php';
   require_once __DIR__ . '/includes/analytics_scopes.php';
   require_once __DIR__ . '/includes/election_scope_helpers.php';   

/* ==========================================================
   FETCH SCOPED ELECTIONS
   ========================================================== */

$now = date('Y-m-d H:i:s');

// Default: per-admin elections (legacy helper)
$elections = fetchScopedElections($pdo, $userId);

// If SUPER ADMIN with scope impersonation, override with scope-based elections
if (
    ($role === 'super_admin') &&
    isset($_GET['scope_type'], $_GET['scope_id']) &&
    $_GET['scope_type'] !== '' &&
    ctype_digit((string)$_GET['scope_id'])
) {
    $scopeTypeParam = $_GET['scope_type'];
    $scopeIdParam   = (int)$_GET['scope_id'];

    // Use analytics_scopes helper: getScopedElections(PDO $pdo, string $scopeType, ?int $scopeId, array $options = [])
    $elections = getScopedElections($pdo, $scopeTypeParam, $scopeIdParam, [
        // optional filters dito kung gusto mo ng year range, status, etc.
    ]);
}

/* ==========================================================
   TOAST NOTIFICATION
   ========================================================== */
$toastMessage = $_SESSION['toast_message'] ?? null;
$toastType    = $_SESSION['toast_type'] ?? null;
unset($_SESSION['toast_message'], $_SESSION['toast_type']);

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/img/weblogo.png" type="image/png">
  <title>eBalota - Election Analytics</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    .tab-btn.active {
      color: var(--cvsu-green);
      border-bottom-color: var(--cvsu-green);
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

<div class="flex min-h-screen">

<?php
  if (($role === 'super_admin') && isset($_GET['scope_id']) && ctype_digit((string)$_GET['scope_id'])) {
      // Super admin impersonating a scope seat
      include 'super_admin_sidebar.php';
  } else {
      include 'sidebar.php';
  }
?>
<?php include 'admin_change_password_modal.php'; ?>

<!-- Top Bar -->
<header class="w-full fixed top-0 left-64 h-16 shadow z-10 flex items-center px-6" style="background-color:rgb(25, 72, 49);"> 
  <h1 class="text-2xl font-bold text-white">
    Election Analytics
  </h1>
</header>

<!-- Toast Notification -->
<?php if ($toastMessage): ?>
  <div id="toast" 
       class="fixed top-20 left-1/2 transform -translate-x-1/2 
              px-6 py-3 rounded-lg shadow-lg text-white font-semibold text-center z-50
              <?= $toastType === 'success' ? 'bg-green-600' : 'bg-red-600' ?>">
    <?= htmlspecialchars($toastMessage) ?>
  </div>
  <script>
    setTimeout(() => {
      const toast = document.getElementById("toast");
      if (toast) toast.style.display = "none";
    }, 3000);
  </script>
<?php endif; ?>

<!-- Main Content -->
<main class="flex-1 pt-20 px-8 ml-64">
  <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
    
    <!-- Search Bar -->
    <div class="mb-6">
      <div class="relative">
        <input type="text" id="searchElections" placeholder="Search elections by title..." 
               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
        <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
      </div>
    </div>
    
    <!-- Election Categories Filter -->
    <div class="mb-6">
      <div class="flex flex-wrap gap-2 border-b">
        <button class="tab-btn active px-4 py-2 font-medium border-b-2" data-category="all">
          All
        </button>
        <button class="tab-btn px-4 py-2 font-medium text-gray-600 hover:text-green-600 border-b-2 border-transparent" data-category="ongoing">
          Ongoing
        </button>
        <button class="tab-btn px-4 py-2 font-medium text-gray-600 hover:text-green-600 border-b-2 border-transparent" data-category="upcoming">
          Upcoming
        </button>
        <button class="tab-btn px-4 py-2 font-medium text-gray-600 hover:text-green-600 border-b-2 border-transparent" data-category="completed">
          Completed
        </button>
      </div>
    </div>

    <?php if (!empty($elections)): ?>
      <section class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" id="electionsGrid">
        <?php foreach ($elections as $election): ?>
          <?php
            $start  = $election['start_datetime'];
            $end    = $election['end_datetime'];
            $status = ($now < $start) ? 'upcoming'
                     : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

            $statusColors = [
              'ongoing'   => 'border-l-green-600 bg-green-50',
              'completed' => 'border-l-gray-500 bg-gray-50',
              'upcoming'  => 'border-l-yellow-500 bg-yellow-50'
            ];
            $statusIcons = [
              'ongoing'   => '🟢',
              'completed' => '⚫',
              'upcoming'  => '🟡'
            ];
          ?>
          <div class="election-card bg-white rounded-lg shadow-md overflow-hidden border-l-4 <?= $statusColors[$status] ?> flex flex-col h-full transition-transform hover:scale-[1.02]" data-status="<?= $status ?>">
            <div class="p-5 flex flex-grow">
              <!-- Logo - Larger and on the Left -->
              <div class="flex-shrink-0 w-32 h-32 mr-5 relative">
                <!-- Status indicator -->
                <div class="absolute -top-3 left-0 z-10">
                  <span class="text-xs font-medium px-2 py-1 rounded-br-lg bg-white border shadow-sm">
                    <?= $statusIcons[$status] ?> <?= ucfirst($status) ?>
                  </span>
                </div>

                <?php if (!empty($election['logo_path'])): ?>
                  <div class="w-full h-full rounded-full overflow-hidden border-4 border-white shadow-md flex items-center justify-center bg-white">
                    <img src="<?= htmlspecialchars($election['logo_path']) ?>" 
                         alt="Election Logo" 
                         class="w-full h-full object-cover">
                  </div>
                <?php else: ?>
                  <div class="w-full h-full rounded-full bg-gray-100 border-4 border-white shadow-md flex items-center justify-center">
                    <span class="text-lg text-gray-500">Logo</span>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Info -->
              <div class="flex-1 min-w-0">
                <h2 class="election-title text-lg font-bold text-[var(--cvsu-green-dark)] mb-2 line-clamp-2 break-words">
                  <?= htmlspecialchars($election['title']) ?>
                </h2>
                
                <div class="space-y-2 text-sm">
                  <!-- Start Date -->
                  <div class="flex items-center text-[11px] md:text-xs whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <span>
                      <strong class="text-gray-700 mr-1">Start:</strong>
                      <?= date("M d, Y h:i A", strtotime($start)) ?>
                    </span>
                  </div>

                  <!-- End Date -->
                  <div class="flex items-center text-[11px] md:text-xs whitespace-nowrap mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2 2H5a2 2 0 00-2 2z" />
                    </svg>
                    <span>
                      <strong class="text-gray-700 mr-1">End:</strong>
                      <?= date("M d, Y h:i A", strtotime($end)) ?>
                    </span>
                  </div>
                  <!-- Status Indicator -->
                  <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                    <span><strong class="text-gray-700">Status:</strong> 
                      <?php if (($election['creation_stage'] ?? '') === 'ready_for_voters'): ?>
                        <span class="text-green-600">Launched to Voters</span>
                      <?php else: ?>
                        <span class="text-yellow-600">Not Yet Launched</span>
                      <?php endif; ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="mt-auto p-4 bg-gray-50 border-t">
              <div class="flex flex-col sm:flex-row gap-3">
                <!-- Only Analytics Button -->
                <?php
                  // Dalhin scope params kung galing sa super admin dashboard
                  $scopeTypeParam = $_GET['scope_type'] ?? null;
                  $scopeIdParam   = (isset($_GET['scope_id']) && ctype_digit((string)$_GET['scope_id']))
                      ? (int)$_GET['scope_id']
                      : null;

                  $analyticsUrl = 'admin_analytics_redirect.php?id=' . (int)$election['election_id'];
                  if ($scopeTypeParam && $scopeIdParam !== null) {
                      $analyticsUrl .= '&scope_type=' . urlencode($scopeTypeParam) . '&scope_id=' . $scopeIdParam;
                  }
                ?>
                <a href="<?= htmlspecialchars($analyticsUrl) ?>" 
                  class="flex-1 bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white py-2 px-4 rounded-lg font-semibold transition text-center flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2z" />
                  </svg>
                  View Analytics
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php else: ?>
      <div class="bg-white rounded-lg shadow-md p-8 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Elections Available</h3>
        <p class="text-gray-600">
          <?php if ($role === 'admin'): ?>
            There are no elections in your scope at this time.
          <?php else: ?>
            There are no elections available at this time.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const searchInput   = document.getElementById('searchElections');
    const tabButtons    = document.querySelectorAll('.tab-btn');
    const electionCards = document.querySelectorAll('.election-card');
    
    function filterElections() {
      const searchTerm = searchInput.value.toLowerCase().trim();
      const activeTab  = document.querySelector('.tab-btn.active').dataset.category;
      
      electionCards.forEach(card => {
        const titleElement = card.querySelector('.election-title');
        if (!titleElement) {
          card.style.display = 'none';
          return;
        }
        const title      = titleElement.textContent.toLowerCase().trim();
        const cardStatus = card.dataset.status;
        
        const matchesSearch = (searchTerm === '' || title.includes(searchTerm));
        const matchesTab    = (activeTab === 'all' || cardStatus === activeTab);
        
        card.style.display = (matchesSearch && matchesTab) ? 'block' : 'none';
      });
    }
    
    if (searchInput) {
      searchInput.addEventListener('input', filterElections);
    }
    
    tabButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        tabButtons.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterElections();
      });
    });
    
    filterElections();
  });
</script>

</body>
</html>
