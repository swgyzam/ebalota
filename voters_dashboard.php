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

// Fetch all ongoing elections
$sql = "SELECT * FROM elections WHERE status = 'ongoing' ORDER BY election_id DESC";
$stmt = $pdo->query($sql);
$all_elections = $stmt->fetchAll();

$filtered_elections = [];

foreach ($all_elections as $election) {
  $allowed_colleges = array_map('strtolower', array_map('trim', explode(',', $election['allowed_colleges'])));
  $allowed_courses = array_map('strtolower', array_map('trim', explode(',', $election['allowed_courses'])));
  $allowed_status = array_map('strtolower', array_map('trim', explode(',', $election['allowed_status'])));

  // Special handling for COOP elections
  $is_coop_election = strpos(strtolower($election['target_position']), 'coop') !== false;
  
  $college_allowed = in_array('all', $allowed_colleges) || in_array($voter_college, $allowed_colleges);
  $course_allowed = in_array('all', $allowed_courses) || in_array($voter_course, $allowed_courses);
  $status_allowed = in_array('all', $allowed_status) || in_array($voter_status, $allowed_status);
  
  // If it's a COOP election, check COOP membership status
  if ($is_coop_election) {
      if ($is_coop_member) {
          $filtered_elections[] = $election;
      }
  } 
  // For non-COOP elections, use normal filtering
  elseif ($college_allowed && $course_allowed && $status_allowed) {
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

<!-- Privacy Policy Modal -->
<div id="privacyModal" class="fixed inset-0 z-50 bg-black bg-opacity-40 hidden">
  <div class="flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4 mx-auto">
      <h2 class="text-xl font-bold text-[var(--cvsu-green-dark)]">Data Privacy Notice</h2>
      <p class="text-gray-700 text-sm">
        Before casting your vote, please be informed that your personal information will be processed in accordance with the Data Privacy Act of 2012. By proceeding, you acknowledge that you understand and accept this policy.
      </p>
      <div class="flex items-start space-x-2">
        <input type="checkbox" id="privacyCheck" class="mt-1">
        <label for="privacyCheck" class="text-sm text-gray-600">I have read and understood the Data Privacy Policy.</label>
      </div>
      <div class="flex justify-end space-x-2">
        <button id="cancelModal" class="px-4 py-2 text-sm rounded bg-gray-200 hover:bg-gray-300">Cancel</button>
        <button id="proceedVote" class="px-4 py-2 text-sm rounded bg-[var(--cvsu-green)] text-white hover:bg-[var(--cvsu-green-dark)]" disabled>Proceed</button>
      </div>
    </div>
  </div>
</div>

<main class="flex-1 p-8 ml-64">
  <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
    <h1 class="text-3xl font-extrabold">Voter Dashboard Overview</h1>
  </header>
  <?php if (isset($_GET['message']) && $_GET['message'] === 'vote_success'): ?>
  <div class="mb-6 p-4 rounded bg-green-100 text-green-800 border border-green-300">
    Your vote has been successfully submitted. Thank you for voting!
  </div>
<?php endif; ?>


  <?php if (count($filtered_elections) > 0): ?>
    <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($filtered_elections as $election): ?>
        <div class="bg-white rounded-xl shadow-lg border-l-8 border-[var(--cvsu-green)] hover:shadow-2xl transition-shadow duration-300 p-6 flex flex-col justify-between">
          <div>
            <h2 class="text-2xl font-semibold text-[var(--cvsu-green-dark)] mb-2"><?= htmlspecialchars($election['title']) ?></h2>
            <p class="text-gray-700 mb-4"><?= nl2br(htmlspecialchars($election['description'])) ?></p>
            <p class="text-sm text-gray-500 mb-4">
              Target: <span class="font-medium"><?= htmlspecialchars($election['target_position']) ?></span> - 
              <span class="font-medium"><?= htmlspecialchars($election['target_department']) ?></span><br>
              Colleges: <span class="font-medium"><?= htmlspecialchars($election['allowed_colleges']) ?></span><br>
              Courses: <span class="font-medium"><?= htmlspecialchars($election['allowed_courses']) ?></span>
            </p>
          </div>
          <a href="view_candidates.php?election_id=<?= $election['election_id'] ?>" class="inline-block bg-[var(--cvsu-green-light)] text-white px-5 py-2 rounded hover:bg-[var(--cvsu-green)] transition self-start">
            Cast your Vote
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-gray-700 text-center text-lg">No available elections for your role, course, or college.</p>
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
