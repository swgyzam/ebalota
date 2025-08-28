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
    'College of Arts and Sciences (CAS)' => 'CAS',
    'College of Criminal Justice (CCJ)' => 'CCJ',
    'College of Education (CED)' => 'CED',
    'College of Agriculture, Food, Environment and Natural Resources (CAFENR)' => 'CAFENR',
    'College of Economics, Management and Development Studies (CEMDS)' => 'CEMDS',
    'College of Nursing (CON)' => 'CON',
    'College of Sports, Physical Education and Recreation (CSPEAR)' => 'CSPEAR',
    'College of Veterinary Medicine and Biomedical Sciences (CVMBS)' => 'CVMBS',
    'Graduate School and Open Learning College (GS.OLC)' => 'GS.OLC',
    'College of Medicine (COM)' => 'COM',
    'College of Tourism and Hospitality Management (CTHM)' => 'CTHM',
];

$course_map = [
  // CEIT
  'bs computer science' => 'bscs',
  'bs information technology' => 'bsit',
  'bs computer engineering' => 'bscpe',
  'bs electronics engineering' => 'bsece',
  'bs civil engineering' => 'bsce',
  'bs mechanical engineering' => 'bsme',
  'bs electrical engineering' => 'bsee',
  'bs industrial engineering' => 'bsie',

  // CAFENR
  'bs agriculture' => 'bsag',
  'bs agribusiness' => 'bsab',
  'bs environmental science' => 'bses',
  'bs food technology' => 'bsft',
  'bs forestry' => 'bsfor',
  'bs agricultural and biosystems engineering' => 'bsabe',
  'bachelor of agricultural entrepreneurship' => 'bae',
  'bs land use design and management' => 'bsldm',

  // CAS
  'bs biology' => 'bsbio',
  'bs chemistry' => 'bschem',
  'bs mathematics' => 'bsmath',
  'bs physics' => 'bsphy',
  'bs psychology' => 'bspsy',
  'ba english language studies' => 'baels',
  'ba communication' => 'bacomm',
  'bs statistics' => 'bsstat',

  // CVMBS
  'doctor of veterinary medicine' => 'dvm',
  'bs biology (pre-veterinary)' => 'bspv',

  // CED
  'bachelor of elementary education' => 'bee',
  'bachelor of secondary education' => 'bse',
  'bachelor of physical education' => 'bpe',
  'bachelor of technology and livelihood education' => 'btle',

  // CEMDS
  'bs business administration' => 'bsba',
  'bs accountancy' => 'bsacc',
  'bs economics' => 'bseco',
  'bs entrepreneurship' => 'bsent',
  'bs office administration' => 'bsoa',

  // CSPEAR
  'bachelor of physical education' => 'bpe',  // same as CED bpe
  'bs exercise and sports sciences' => 'bsess',

  // CCJ
  'bs criminology' => 'bscrim',

  // CON
  'bs nursing' => 'bsn',
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
    header("Location: login.html");
    exit;
}

// After establishing the database connection, fetch the voter's migs_status
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT migs_status FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$voter_data = $stmt->fetch();

// Add migs_status to session
$_SESSION['migs_status'] = $voter_data['migs_status'] ?? 0;
$is_coop_member = ($_SESSION['migs_status'] ?? 0) == 1;

$voter_role = $_SESSION['position'] ?? '';
$role_parts = explode(',', $voter_role);

$is_coop = in_array('COOP', $role_parts);
$is_student = in_array('student', $role_parts);
$is_faculty = in_array('faculty', $role_parts);
$is_non_academic = in_array('non-academic', $role_parts);

$voter_college = strtolower(trim($_SESSION['department'] ?? ''));
$voter_course_full = strtolower(trim($_SESSION['course'] ?? ''));
$voter_status = strtolower(trim($_SESSION['status'] ?? ''));

// Normalize course name to code
$voter_course = $course_map[$voter_course_full] ?? $voter_course_full;

// Fetch all elections
$sql = "SELECT * FROM elections ORDER BY election_id DESC";
$stmt = $pdo->query($sql);
$all_elections = $stmt->fetchAll();

$filtered_elections = [];
$now = date('Y-m-d H:i:s'); // current server datetime

// 🔧 Mapper: users.position → elections.target_position
function mapUserPositionToElection($user) {
  $pos = strtolower(trim($user['position'] ?? ''));
  $isCoop = $user['is_coop_member'] ?? 0;

  if ($isCoop) {
      return 'coop';
  }

  switch ($pos) {
      case 'academic':
          return 'faculty'; // academic (users) = faculty (elections)
      case 'student':
          return 'student';
      case 'non-academic':
          return 'non-academic';
      default:
          return 'All';
  }
}

// 🔧 Kunin current voter info galing session
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$voter = $stmt->fetch();

if (!$voter) {
    die("User not found.");
}


// 🔧 Election Filtering
$filtered_elections = [];
$positions = array_map('trim', explode(',', strtolower($voter['position'] ?? '')));
$mappedPosition = in_array('COOP', $positions) ? 'coop' : (
                    in_array('academic', $positions) ? 'faculty' : (
                    in_array('student', $positions) ? 'student' : (
                    in_array('non-academic', $positions) ? 'non-academic' : 'All'
                    )));

foreach ($all_elections as $election) {
  $allowed_colleges = array_filter(array_map('strtolower', array_map('trim', explode(',', $election['allowed_colleges'] ?? ''))));
  $allowed_courses  = array_filter(array_map('strtolower', array_map('trim', explode(',', $election['allowed_courses'] ?? ''))));
  $allowed_status   = array_filter(array_map('strtolower', array_map('trim', explode(',', $election['allowed_status'] ?? ''))));

  $voter_college = strtolower(trim($voter['department'] ?? ''));
  $voter_course  = strtolower(trim($voter['course'] ?? ''));
  $voter_status  = strtolower(trim($voter['status'] ?? ''));

  $is_coop_election = ($election['target_position'] === 'coop');
  $is_coop_member   = (bool)($voter['is_coop_member'] ?? 0);

  // ✅ Allowed checks
  $college_allowed = empty($allowed_colleges) || in_array('all', $allowed_colleges) || in_array($voter_college, $allowed_colleges);
  $course_allowed  = empty($allowed_courses)  || in_array('all', $allowed_courses)  || in_array($voter_course, $allowed_courses);
  $status_allowed  = empty($allowed_status)   || in_array('all', $allowed_status)   || in_array($voter_status, $allowed_status);

  if ($election['target_position'] === 'coop') {
    if ($voter['is_coop_member']) {   // only check COOP membership
        $filtered_elections[] = $election;
    }
} elseif (($election['target_position'] === 'All' || $election['target_position'] === $mappedPosition)
          && $college_allowed && $course_allowed && $status_allowed) {
    $filtered_elections[] = $election;
}
}

include 'voters_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eBalota - Voter Dashboard</title>
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

<main class="flex-1 p-4 md:p-8 md:ml-64">
  <header class="bg-[var(--cvsu-green-dark)] text-white p-4 md:p-6 flex justify-between items-center shadow-md rounded-lg mb-6">
    <h1 class="text-2xl md:text-3xl font-bold">Voter Dashboard Overview</h1>
  </header>

  <?php if (isset($_GET['message']) && $_GET['message'] === 'vote_success'): ?>
  <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border border-green-300 flex items-center">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
    </svg>
    Your vote has been successfully submitted. Thank you for voting!
  </div>
  <?php endif; ?>

  <?php if (count($filtered_elections) > 0): ?>
    <div class="grid gap-6 md:gap-8 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($filtered_elections as $election): ?>
        <?php
          $start = $election['start_datetime'];
          $end   = $election['end_datetime'];
          $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');
          
          // Status colors
          $statusColors = [
            'ongoing' => 'border-l-green-600 bg-green-50',
            'completed' => 'border-l-gray-500 bg-gray-50',
            'upcoming' => 'border-l-yellow-500 bg-yellow-50'
          ];
          
          $statusIcons = [
            'ongoing' => '🟢',
            'completed' => '⚫',
            'upcoming' => '🟡'
          ];
        ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 <?= $statusColors[$status] ?> flex flex-col h-full transition-transform hover:scale-[1.02]">
  
  <div class="p-5 flex flex-grow">
    <!-- Logo Container with relative positioning -->
    <div class="flex-shrink-0 w-32 h-32 mr-5 relative">
    <!-- Status indicator sa taas ng logo sa left side - mas malaking adjustment -->
    <div class="absolute -top-3 left-0 z-10">
      <span class="text-xs font-medium px-2 py-1 rounded-br-lg bg-white border shadow-sm">
        <?= $statusIcons[$status] ?> <?= ucfirst($status) ?>
      </span>
    </div>

    <!-- Logo - may added margin-top -->
    <?php if (!empty($election['logo_path'])): ?>
      <div class="w-full h-full rounded-full overflow-hidden border-4 border-white shadow-md flex items-center justify-center bg-white mt-3">
        <img src="<?= htmlspecialchars($election['logo_path']) ?>" 
            alt="Election Logo" 
            class="w-full h-full object-cover">
      </div>
    <?php else: ?>
      <div class="w-full h-full rounded-full bg-gray-100 border-4 border-white shadow-md flex items-center justify-center bg-white mt-3">
        <span class="text-lg text-gray-500">Logo</span>
      </div>
    <?php endif; ?>
    </div>

            <!-- Info -->
            <div class="flex-1">
              <h2 class="text-lg font-bold text-[var(--cvsu-green-dark)] mb-2 truncate">
                <?= htmlspecialchars($election['title']) ?>
              </h2>
              
              <p class="text-gray-700 text-sm mb-4 line-clamp-2">
                <?= nl2br(htmlspecialchars($election['description'])) ?>
              </p>
              
              <div class="space-y-2 text-sm">
                <div class="flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span><strong class="text-gray-700">Start:</strong> <?= date("M d, Y h:i A", strtotime($start)) ?></span>
                </div>
                <div class="flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span><strong class="text-gray-700">End:</strong> <?= date("M d, Y h:i A", strtotime($end)) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Button -->
          <?php if ($status === 'ongoing'): ?>
          <div class="mt-auto p-4 bg-gray-50 border-t">
            <a href="view_candidates.php?election_id=<?= $election['election_id'] ?>" 
               class="block w-full bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white py-2 px-4 rounded-lg font-semibold transition text-center flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Cast your Vote
            </a>
          </div>
          <?php elseif ($status === 'upcoming'): ?>
          <div class="mt-auto p-4 bg-gray-50 border-t">
            <button class="block w-full bg-gray-300 text-gray-600 py-2 px-4 rounded-lg font-semibold text-center cursor-not-allowed">
              Coming Soon
            </button>
          </div>
          <?php else: ?>
          <div class="mt-auto p-4 bg-gray-50 border-t">
            <button class="block w-full bg-gray-400 text-white py-2 px-4 rounded-lg font-semibold text-center cursor-not-allowed">
              Election Ended
            </button>
          </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-lg shadow-md p-8 text-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <h3 class="text-xl font-semibold text-gray-700 mb-2">No Elections Available</h3>
      <p class="text-gray-600">There are no available elections for your role, course, or college at this time.</p>
    </div>
  <?php endif; ?>

  <?php include 'footer.php'; ?>
</main>
</body>
</html>

<script>
  let targetUrl = '';

  document.addEventListener('DOMContentLoaded', () => {
    const voteLinks = document.querySelectorAll('a[href^="view_candidates.php"]');
    const modal = document.getElementById('privacyModal');
    const checkbox = document.getElementById('privacyCheck');
    const proceedBtn = document.getElementById('proceedVote');
    const cancelBtn = document.getElementById('cancelModal');

    // Intercept vote link click
    voteLinks.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        targetUrl = this.href;
        modal.classList.remove('hidden');
      });
    });

    // Toggle Proceed button based on checkbox
    checkbox.addEventListener('change', () => {
      proceedBtn.disabled = !checkbox.checked;
    });

    // Cancel: close modal and reset state
    cancelBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
      checkbox.checked = false;
      proceedBtn.disabled = true;
    });

    // Proceed: only allowed if checkbox is checked
    proceedBtn.addEventListener('click', () => {
      if (checkbox.checked) {
        window.location.href = targetUrl;
      }
    });
  });
</script>
