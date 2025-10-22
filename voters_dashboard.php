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
    PDO::ATTR_STRINGIFY_FETCHES => true, // Force datetime values as strings
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Redirect if not logged in or not a voter
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: login.html");
    exit();
}
// After establishing the database connection, fetch the voter's migs_status and force_password_change
 $user_id = $_SESSION['user_id'];
 $stmt = $pdo->prepare("SELECT migs_status, force_password_change FROM users WHERE user_id = ?");
 $stmt->execute([$user_id]);
 $voter_data = $stmt->fetch();

// Add migs_status to session
 $_SESSION['migs_status'] = $voter_data['migs_status'] ?? 0;
 $is_coop_member = ($_SESSION['migs_status'] ?? 0) == 1; // Only MIGS members are COOP members for voting
 $forcePasswordChange = $voter_data['force_password_change'] ?? 0;

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
  
  // ✅ Allowed checks
  $college_allowed = empty($allowed_colleges) || in_array('all', $allowed_colleges) || in_array($voter_college, $allowed_colleges);
  $course_allowed  = empty($allowed_courses)  || in_array('all', $allowed_courses)  || in_array($voter_course, $allowed_courses);
  $status_allowed  = empty($allowed_status)   || in_array('all', $allowed_status)   || in_array($voter_status, $allowed_status);

  // FIX: Only MIGS members can vote in COOP elections
  if ($election['target_position'] === 'coop') {
    if ($is_coop_member) {   // Check MIGS status
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    
    .mobile-sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease-in-out;
    }
    
    .mobile-sidebar.open {
      transform: translateX(0);
    }
    
    .tab-btn.active {
      color: var(--cvsu-green);
      border-bottom-color: var(--cvsu-green);
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    .pulse-animation {
      animation: pulse 2s infinite;
    }
    
    /* Force password modal styles */
    .modal-backdrop {
      background-color: rgba(0, 0, 0, 0.7);
      z-index: 9999;
    }
    
    .password-strength-bar {
      transition: width 0.3s ease;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl">
      <div class="flex items-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mr-3"></div>
        <span class="text-gray-700">Loading...</span>
      </div>
    </div>
  </div>
  
  <!-- Force Password Change Modal -->
  <div id="forcePasswordChangeModal" class="fixed inset-0 modal-backdrop flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 relative">
      <!-- Close button (X) at top right -->
      <button onclick="closePasswordModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl">
          &times;
      </button>
      
      <div class="text-center mb-6">
        <div class="w-16 h-16 bg-[var(--cvsu-green-light)] rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-[var(--cvsu-green-dark)]">Change Your Password</h2>
        <p class="text-gray-600 mt-2">For security reasons, you must change your password before continuing.</p>
      </div>
      
      <form id="forcePasswordChangeForm" class="space-y-5">
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <div class="relative">
                  <input type="password" id="newPassword" name="new_password" required 
                         class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                  <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div class="mt-2">
                  <div class="flex items-center text-xs text-gray-500 mb-1">
                      <span id="passwordStrength" class="font-medium">Password strength:</span>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                          <div id="strengthBar" class="h-2 rounded-full password-strength-bar" style="width: 0%"></div>
                      </div>
                  </div>
                  <ul class="text-xs text-gray-500 space-y-1">
                      <li id="lengthCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          At least 8 characters
                      </li>
                      <li id="uppercaseCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains uppercase letter
                      </li>
                      <li id="numberCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains number
                      </li>
                      <li id="specialCheck" class="flex items-center">
                          <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Contains special character
                      </li>
                  </ul>
              </div>
          </div>
          
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <div class="relative">
                  <input type="password" id="confirmPassword" name="confirm_password" required 
                         class="block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]">
                  <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                      <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div id="matchError" class="mt-1 text-xs text-red-500 hidden">Passwords do not match</div>
          </div>
          
          <!-- Notification Container -->
          <div id="notificationContainer" class="space-y-3">
              <!-- Error Notification -->
              <div id="passwordError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-red-800">Error</h3>
                          <div class="mt-1 text-sm text-red-700" id="passwordErrorText"></div>
                      </div>
                  </div>
              </div>
              
              <!-- Success Notification -->
              <div id="passwordSuccess" class="hidden bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-green-800">Success</h3>
                          <div class="mt-1 text-sm text-green-700">
                              Password updated successfully! Redirecting to dashboard...
                          </div>
                      </div>
                  </div>
              </div>
              
              <!-- Loading Notification -->
              <div id="passwordLoading" class="hidden bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-sm">
                  <div class="flex items-start">
                      <div class="flex-shrink-0">
                          <svg class="animate-spin h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                      </div>
                      <div class="ml-3">
                          <h3 class="text-sm font-medium text-blue-800">Processing</h3>
                          <div class="mt-1 text-sm text-blue-700">
                              Updating your password...
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          
          <!-- Button Container -->
          <div class="flex justify-center pt-4">
              <button type="submit" id="submitBtn" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-8 py-3 rounded-lg font-medium flex items-center justify-center min-w-[180px] transition-all duration-200 transform hover:scale-105">
                  <span id="submitBtnText">Update Password</span>
                  <svg id="submitLoader" class="ml-2 h-5 w-5 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
              </button>
          </div>
      </form>
    </div>
  </div>

  <div class="flex">
    <!-- Main Content -->
    <main class="flex-1 p-4 md:p-8 md:ml-64">
      <!-- Header -->
      <header class="bg-[var(--cvsu-green-dark)] text-white p-4 md:p-6 flex justify-between items-center shadow-md rounded-lg mb-6">
        <div class="flex items-center">
          <button class="md:hidden text-white mr-4" onclick="toggleSidebar()">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <div>
            <h1 class="text-2xl md:text-3xl font-bold">Voter Dashboard Overview</h1>
            <p class="text-green-100 mt-1">Welcome back, <?= htmlspecialchars($_SESSION['first_name']) ?>!</p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <span class="text-green-200 text-sm hidden sm:block">
            <?= htmlspecialchars($voter_college) ?> - <?= htmlspecialchars($voter_course) ?>
          </span>
          <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-user text-white"></i>
          </div>
        </div>
      </header>
      <!-- Notification Banner -->
      <div id="notificationBanner" class="hidden mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 rounded-r-lg">
        <div class="flex items-center">
          <i class="fas fa-bullhorn text-blue-600 mr-3"></i>
          <div class="flex-1">
            <p class="text-blue-800 font-medium">Important Announcement</p>
            <p class="text-blue-700 text-sm">Election period extended until 5:00 PM tomorrow due to high voter turnout.</p>
          </div>
          <button onclick="closeNotification()" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <!-- Success Message -->
      <?php if (isset($_GET['message']) && $_GET['message'] === 'vote_success'): ?>
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border border-green-300 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          Your vote has been successfully submitted. Thank you for voting!
        </div>
      <?php endif; ?>
      <!-- Search Bar -->
      <div class="mb-6">
        <div class="relative">
          <input type="text" id="searchElections" placeholder="Search elections..." 
                 class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
        </div>
      </div>
      <!-- Election Categories Filter -->
      <div class="mb-6">
        <div class="flex flex-wrap gap-2 border-b">
          <button class="tab-btn active px-4 py-2 font-medium border-b-2" data-category="ongoing">
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
      <!-- Elections Grid -->
      <?php if (count($filtered_elections) > 0): ?>
        <div class="grid gap-6 md:gap-8 grid-cols-1 md:grid-cols-2 lg:grid-cols-3" id="electionsGrid">
          <?php 
          // Create current datetime once outside the loop
          $now = new DateTime();
          $nowString = $now->format('Y-m-d H:i:s');
          
          foreach ($filtered_elections as $election): 
          ?>
            <?php
              $start = $election['start_datetime'];
              $end   = $election['end_datetime'];
              
              // Handle both string and DateTime objects
              if ($start instanceof DateTime) {
                  $startDateTime = $start;
              } else {
                  $startDateTime = new DateTime($start);
              }
              
              if ($end instanceof DateTime) {
                  $endDateTime = $end;
              } else {
                  $endDateTime = new DateTime($end);
              }
              
              // Calculate status
              if ($now < $startDateTime) {
                  $status = 'upcoming';
              } elseif ($now >= $startDateTime && $now <= $endDateTime) {
                  $status = 'ongoing';
              } else {
                  $status = 'completed';
              }
              
              // Debug logging
              error_log("Election ID: {$election['election_id']}, Title: {$election['title']}, Start: {$startDateTime->format('Y-m-d H:i:s')}, End: {$endDateTime->format('Y-m-d H:i:s')}, Now: {$nowString}, Status: $status");
              
              // Check if user has voted
              $stmt = $pdo->prepare("SELECT * FROM votes WHERE voter_id = ? AND election_id = ?");
              $stmt->execute([$_SESSION['user_id'], $election['election_id']]);
              $hasVoted = $stmt->fetch();
              
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
            <div class="election-card bg-white rounded-lg shadow-md overflow-hidden border-l-4 <?= $statusColors[$status] ?> flex flex-col h-full transition-transform hover:scale-[1.02]" data-status="<?= $status ?>">
  
              <!-- Card Header with Status and Menu -->
              <div class="p-4 pb-0 flex justify-between items-start">
                <div>
                  <span class="text-xs font-medium px-2 py-1 rounded-br-lg bg-white border shadow-sm">
                    <?= $statusIcons[$status] ?> <?= ucfirst($status) ?>
                  </span>
                  <?php if ($hasVoted): ?>
                    <span class="text-xs font-medium bg-purple-100 text-purple-800 px-2 py-1 rounded ml-2">
                      <i class="fas fa-check-circle mr-1"></i> Voted
                    </span>
                  <?php endif; ?>
                </div>
                <div class="relative">
                  <button class="text-gray-500 hover:text-gray-700 p-1" onclick="toggleMenu('menu_<?= $election['election_id'] ?>')">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div id="menu_<?= $election['election_id'] ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                    <a href="election_details.php?election_id=<?= $election['election_id'] ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                      <i class="fas fa-info-circle mr-2"></i> Details
                    </a>
                  </div>
                </div>
              </div>
              
              <div class="p-5 flex flex-grow">
                <!-- Logo Container -->
                <div class="flex-shrink-0 w-32 h-32 mr-5">
                  <!-- Logo -->
                  <?php if (!empty($election['logo_path'])): ?>
                    <div class="w-full h-full rounded-full overflow-hidden border-4 border-white shadow-md flex items-center justify-center bg-white">
                      <img src="<?= htmlspecialchars($election['logo_path']) ?>" 
                           alt="Election Logo" 
                           class="w-full h-full object-cover">
                    </div>
                  <?php else: ?>
                    <div class="w-full h-full rounded-full bg-gray-100 border-4 border-white shadow-md flex items-center justify-center bg-white">
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
                  
                  <!-- Countdown Timer for Ongoing Elections -->
                  <?php if ($status === 'ongoing'): ?>
                    <?php
                      // Use the DateTime objects we already created
                      $interval = $now->diff($endDateTime);
                      
                      if ($interval->days > 0) {
                        $timeLeft = $interval->days . " day" . ($interval->days > 1 ? "s" : "") . " left";
                      } elseif ($interval->h > 0) {
                        $timeLeft = $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " left";
                      } elseif ($interval->i > 0) {
                        $timeLeft = $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " left";
                      } else {
                        $timeLeft = "Less than a minute left";
                      }
                    ?>
                    <div class="text-sm text-orange-600 font-medium mb-2">
                      <i class="fas fa-clock mr-1"></i> <?= $timeLeft ?>
                    </div>
                  <?php endif; ?>
                  
                  <!-- Voter Turnout Progress -->
                  <?php if ($status === 'ongoing'): ?>
                    <?php
                      // Get total eligible voters for this election
                      $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'voter'");
                      $stmt->execute();
                      $totalVoters = $stmt->fetch()['total'];
                      
                      // Get total votes cast
                      $stmt = $pdo->prepare("SELECT COUNT(*) as voted FROM votes WHERE election_id = ?");
                      $stmt->execute([$election['election_id']]);
                      $votesCast = $stmt->fetch()['voted'];
                      
                      $turnout = $totalVoters > 0 ? round(($votesCast / $totalVoters) * 100, 1) : 0;
                    ?>
                    <div class="mb-3">
                      <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Voter Turnout</span>
                        <span><?= $turnout ?>%</span>
                      </div>
                      <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full transition-all duration-500" style="width: <?= $turnout ?>%"></div>
                      </div>
                    </div>
                  <?php endif; ?>
                  
                  <div class="space-y-2 text-sm">
                    <div class="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <span><strong class="text-gray-700">Start:</strong> <?= $startDateTime->format("M d, Y h:i A") ?></span>
                    </div>
                    <div class="flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <span><strong class="text-gray-700">End:</strong> <?= $endDateTime->format("M d, Y h:i A") ?></span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Action Button -->
              <div class="mt-auto p-4 bg-gray-50 border-t">
                <?php if ($status === 'ongoing' && !$hasVoted): ?>
                  <a href="view_candidates.php?election_id=<?= $election['election_id'] ?>" 
                     class="block w-full bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white py-2 px-4 rounded-lg font-semibold transition text-center flex items-center justify-center pulse-animation">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Cast your Vote
                  </a>
                <?php elseif ($status === 'ongoing' && $hasVoted): ?>
                  <button class="block w-full bg-purple-600 text-white py-2 px-4 rounded-lg font-semibold text-center cursor-not-allowed">
                    <i class="fas fa-check mr-2"></i> Already Voted
                  </button>
                <?php elseif ($status === 'upcoming'): ?>
                  <button class="block w-full bg-gray-300 text-gray-600 py-2 px-4 rounded-lg font-semibold text-center cursor-not-allowed">
                    Coming Soon
                  </button>
                <?php else: ?>
                  <button onclick="showResults(<?= $election['election_id'] ?>)" class="block w-full bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg font-semibold transition text-center">
                    <i class="fas fa-chart-bar mr-2"></i> Results
                  </button>
                <?php endif; ?>
              </div>
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
  </div>
  
  <!-- Privacy Modal -->
  <div id="privacyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
      <div class="text-center">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-shield-alt text-blue-600 text-2xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Privacy Agreement</h3>
        <p class="text-gray-600 mb-6">Your vote is confidential. By proceeding, you agree that your voting choices will remain private and will not be disclosed to any party.</p>
        
        <div class="mb-6 text-left">
          <label class="flex items-start">
            <input type="checkbox" id="privacyCheck" class="mt-1 mr-2">
            <span class="text-sm text-gray-700">I understand that my vote is confidential and agree to the privacy terms</span>
          </label>
        </div>
        
        <div class="flex space-x-3">
          <button id="cancelModal" type="button" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
            Cancel
          </button>
          <button id="proceedVote" type="button" disabled class="flex-1 px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed">
            Proceed to Vote
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    let targetUrl = '';
    
    // Toggle mobile sidebar
    function toggleSidebar() {
      const sidebar = document.getElementById('votersSidebar');
      sidebar.classList.toggle('open');
    }
    
    // Close notification banner
    function closeNotification() {
      document.getElementById('notificationBanner').classList.add('hidden');
    }
    
    // Toggle dropdown menu
    function toggleMenu(menuId) {
      const menu = document.getElementById(menuId);
      menu.classList.toggle('hidden');
      
      // Close other menus
      document.querySelectorAll('[id^="menu_"]').forEach(m => {
        if (m.id !== menuId) {
          m.classList.add('hidden');
        }
      });
    }
    
    // Show results (placeholder function)
    function showResults(electionId) {
      window.location.href = `election_results.php?election_id=${electionId}`;
    }
    
    // Search functionality
    document.getElementById('searchElections').addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const electionCards = document.querySelectorAll('.election-card');
      
      electionCards.forEach(card => {
        const title = card.querySelector('h2').textContent.toLowerCase();
        const description = card.querySelector('p').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
    
    // Tab filtering
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        // Update active tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Filter elections
        const category = this.dataset.category;
        const electionCards = document.querySelectorAll('.election-card');
        
        electionCards.forEach(card => {
          if (card.dataset.status === category) {
            card.style.display = 'block';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
    
    // Set default view to ongoing elections
    document.addEventListener('DOMContentLoaded', () => {
      // Check if password change is required
      const forcePasswordChange = <?= $forcePasswordChange ?>;
      
      if (forcePasswordChange) {
        document.getElementById('forcePasswordChangeModal').classList.remove('hidden');
        // Prevent interaction with the rest of the page
        document.body.style.pointerEvents = 'none';
        document.getElementById('forcePasswordChangeModal').style.pointerEvents = 'auto';
      }
      
      // Trigger click on ongoing tab to set it as default
      document.querySelector('[data-category="ongoing"]').click();
      
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
      
      // Password visibility toggle
      const togglePassword = document.getElementById('togglePassword');
      const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
      const passwordInput = document.getElementById('newPassword');
      const confirmPasswordInput = document.getElementById('confirmPassword');
      
      togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.querySelector('svg').innerHTML = type === 'password' 
          ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
          : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
      });
      
      toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        this.querySelector('svg').innerHTML = type === 'password' 
          ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5,12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
          : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
      });
      
      // Password strength validation
      passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('passwordStrength');
        
        // Check password requirements
        const length = password.length >= 8;
        const uppercase = /[A-Z]/.test(password);
        const number = /[0-9]/.test(password);
        const special = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        // Update check marks
        updateCheck('lengthCheck', length);
        updateCheck('uppercaseCheck', uppercase);
        updateCheck('numberCheck', number);
        updateCheck('specialCheck', special);
        
        // Calculate strength
        let strength = 0;
        if (length) strength++;
        if (uppercase) strength++;
        if (number) strength++;
        if (special) strength++;
        
        // Update strength bar
        const strengthPercentage = (strength / 4) * 100;
        strengthBar.style.width = strengthPercentage + '%';
        
        if (strength === 0) {
          strengthBar.className = 'h-2 rounded-full bg-red-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Weak';
          strengthText.className = 'font-medium text-red-500';
        } else if (strength === 1) {
          strengthBar.className = 'h-2 rounded-full bg-orange-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Weak';
          strengthText.className = 'font-medium text-orange-500';
        } else if (strength === 2) {
          strengthBar.className = 'h-2 rounded-full bg-yellow-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Medium';
          strengthText.className = 'font-medium text-yellow-500';
        } else if (strength === 3) {
          strengthBar.className = 'h-2 rounded-full bg-blue-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Strong';
          strengthText.className = 'font-medium text-blue-500';
        } else {
          strengthBar.className = 'h-2 rounded-full bg-green-500 password-strength-bar';
          strengthText.textContent = 'Password strength: Very Strong';
          strengthText.className = 'font-medium text-green-500';
        }
        
        // Check password match
        checkPasswordMatch();
      });
      
      // Confirm password validation
      confirmPasswordInput.addEventListener('input', checkPasswordMatch);
      
      function updateCheck(id, isValid) {
        const element = document.getElementById(id);
        const icon = element.querySelector('svg');
        
        if (isValid) {
          element.classList.remove('text-gray-500');
          element.classList.add('text-green-500');
          icon.classList.remove('text-gray-400');
          icon.classList.add('text-green-500');
        } else {
          element.classList.remove('text-green-500');
          element.classList.add('text-gray-500');
          icon.classList.remove('text-green-500');
          icon.classList.add('text-gray-400');
        }
      }
      
      function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const matchError = document.getElementById('matchError');
        
        if (confirmPassword && password !== confirmPassword) {
          matchError.classList.remove('hidden');
          confirmPasswordInput.classList.add('border-red-500');
        } else {
          matchError.classList.add('hidden');
          confirmPasswordInput.classList.remove('border-red-500');
        }
      }
      
      // Handle password change form submission
      const forcePasswordChangeForm = document.getElementById('forcePasswordChangeForm');
      const passwordError = document.getElementById('passwordError');
      const passwordSuccess = document.getElementById('passwordSuccess');
      const passwordLoading = document.getElementById('passwordLoading');
      const passwordErrorText = document.getElementById('passwordErrorText');
      const submitBtn = document.getElementById('submitBtn');
      const submitBtnText = document.getElementById('submitBtnText');
      const submitLoader = document.getElementById('submitLoader');
      
      if (forcePasswordChangeForm) {
        forcePasswordChangeForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          // Hide all notifications
          passwordError.classList.add('hidden');
          passwordSuccess.classList.add('hidden');
          passwordLoading.classList.remove('hidden');
          
          const newPassword = passwordInput.value;
          const confirmPassword = confirmPasswordInput.value;
          
          // Check password requirements
          const length = newPassword.length >= 8;
          const uppercase = /[A-Z]/.test(newPassword);
          const number = /[0-9]/.test(newPassword);
          const special = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
          
          // Calculate strength (0-4)
          let strength = 0;
          if (length) strength++;
          if (uppercase) strength++;
          if (number) strength++;
          if (special) strength++;
          
          // Check minimum requirements
          if (!length) {
            passwordLoading.classList.add('hidden');
            passwordErrorText.textContent = "Password must be at least 8 characters long.";
            passwordError.classList.remove('hidden');
            return;
          }
          
          if (strength < 3) {
            passwordLoading.classList.add('hidden');
            passwordErrorText.textContent = "Password is not strong enough. Please include at least 2 of the following: uppercase letter, number, special character.";
            passwordError.classList.remove('hidden');
            return;
          }
          
          if (newPassword !== confirmPassword) {
            passwordLoading.classList.add('hidden');
            passwordErrorText.textContent = "Passwords do not match.";
            passwordError.classList.remove('hidden');
            return;
          }
          
          // Show loading state
          submitBtn.disabled = true;
          submitBtnText.textContent = 'Updating...';
          submitLoader.classList.remove('hidden');
          
          // Submit the form
          fetch('update_voters_password.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              new_password: newPassword
            })
          })
          .then(response => response.json())
          .then(data => {
            // Hide loading notification
            passwordLoading.classList.add('hidden');
            
            if (data.status === 'success') {
              // Show success notification
              passwordSuccess.classList.remove('hidden');
              
              // Reset button state
              submitBtn.disabled = false;
              submitBtnText.textContent = 'Update Password';
              submitLoader.classList.add('hidden');
              
              // Hide the modal after delay and redirect
              setTimeout(() => {
                document.getElementById('forcePasswordChangeModal').classList.add('hidden');
                document.body.style.pointerEvents = 'auto';
                
                // Show a brief loading overlay during redirect
                const redirectOverlay = document.createElement('div');
                redirectOverlay.className = 'fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center';
                redirectOverlay.innerHTML = `
                  <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-[var(--cvsu-green)] mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg font-medium text-gray-700">Redirecting to dashboard...</p>
                  </div>
                `;
                document.body.appendChild(redirectOverlay);
                
                // Reload the page after a short delay
                setTimeout(() => {
                  window.location.reload();
                }, 1500);
              }, 2000);
            } else {
              // Reset button state
              submitBtn.disabled = false;
              submitBtnText.textContent = 'Update Password';
              submitLoader.classList.add('hidden');
              
              // Show error
              passwordErrorText.textContent = data.message || "Failed to update password.";
              passwordError.classList.remove('hidden');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            
            // Hide loading notification
            passwordLoading.classList.add('hidden');
            
            // Reset button state
            submitBtn.disabled = false;
            submitBtnText.textContent = 'Update Password';
            submitLoader.classList.add('hidden');
            
            // Show error
            passwordErrorText.textContent = "An error occurred. Please try again.";
            passwordError.classList.remove('hidden');
          });
        });
      }
    });
    
    function closePasswordModal() {
      // Prevent closing if password change is required
      const forcePasswordChange = <?= $forcePasswordChange ?>;
      if (forcePasswordChange) {
        return;
      }
      document.getElementById('forcePasswordChangeModal').classList.add('hidden');
      document.body.style.pointerEvents = 'auto';
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.relative')) {
        document.querySelectorAll('[id^="menu_"]').forEach(menu => {
          menu.classList.add('hidden');
        });
      }
    });
  </script>
</body>
</html>