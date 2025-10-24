<?php 
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
 $host = 'localhost';
 $db = 'evoting_system';
 $user = 'root';
 $pass = '';
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
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user role and assigned scope
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $userInfo = $stmt->fetch();

 $role = $userInfo['role'] ?? '';
 $scope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

// Valid college scopes
 $validCollegeScopes = ['CEIT', 'CAS', 'CEMDS', 'CCJ', 'CAFENR', 'CON', 'COED', 'CVM', 'GRADUATE SCHOOL', 'CSPEAR'];

// Get election ID from URL
 $electionId = $_GET['id'] ?? 0;
if (!$electionId) {
    header('Location: admin_view_elections.php');
    exit();
}

// Fetch election details
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
 $stmt->execute([$electionId]);
 $election = $stmt->fetch();

if (!$election) {
    header('Location: admin_view_elections.php');
    exit();
}

// Course mapping for student elections
 $course_map = [
  // CEIT Courses
  'bs computer science' => 'BSCS',
  'bachelor of science in computer science' => 'BSCS',
  'computer science' => 'BSCS',
  'Bachelor of Science in Computer Science' => 'BSCS',
  'BS Computer Science' => 'BSCS',
  'BSCS' => 'BSCS',

  'bs information technology' => 'BSIT',
  'bachelor of science in information technology' => 'BSIT',
  'information technology' => 'BSIT',
  'Bachelor of Science in Information Technology' => 'BSIT',
  'BS Information Technology' => 'BSIT',
  'BSIT' => 'BSIT',

  'bs computer engineering' => 'BSCpE',
  'bachelor of science in computer engineering' => 'BSCpE',
  'computer engineering' => 'BSCpE',
  'Bachelor of Science in Computer Engineering' => 'BSCpE',
  'BS Computer Engineering' => 'BSCpE',
  'BSCpE' => 'BSCpE',

  'bs electronics engineering' => 'BSECE',
  'bachelor of science in electronics engineering' => 'BSECE',
  'electronics engineering' => 'BSECE',
  'Bachelor of Science in Electronics Engineering' => 'BSECE',
  'BS Electronics Engineering' => 'BSECE',
  'BSECE' => 'BSECE',

  'bs civil engineering' => 'BSCE',
  'bachelor of science in civil engineering' => 'BSCE',
  'civil engineering' => 'BSCE',
  'Bachelor of Science in Civil Engineering' => 'BSCE',
  'BS Civil Engineering' => 'BSCE',
  'BSCE' => 'BSCE',

  'bs mechanical engineering' => 'BSME',
  'bachelor of science in mechanical engineering' => 'BSME',
  'mechanical engineering' => 'BSME',
  'Bachelor of Science in Mechanical Engineering' => 'BSME',
  'BS Mechanical Engineering' => 'BSME',
  'BSME' => 'BSME',

  'bs electrical engineering' => 'BSEE',
  'bachelor of science in electrical engineering' => 'BSEE',
  'electrical engineering' => 'BSEE',
  'Bachelor of Science in Electrical Engineering' => 'BSEE',
  'BS Electrical Engineering' => 'BSEE',
  'BSEE' => 'BSEE',

  'bs industrial engineering' => 'BSIE',
  'bachelor of science in industrial engineering' => 'BSIE',
  'industrial engineering' => 'BSIE',
  'Bachelor of Science in Industrial Engineering' => 'BSIE',
  'BS Industrial Engineering' => 'BSIE',
  'BSIE' => 'BSIE',

  'bs architecture' => 'BSArch',
  'bachelor of science in architecture' => 'BSArch',
  'architecture' => 'BSArch',
  'Bachelor of Science in Architecture' => 'BSArch',
  'BS Architecture' => 'BSArch',
  'BSArch' => 'BSArch',

  // CAFENR Courses
  'bs agriculture' => 'BSAgri',
  'bachelor of science in agriculture' => 'BSAgri',
  'agriculture' => 'BSAgri',
  'Bachelor of Science in Agriculture' => 'BSAgri',
  'BS Agriculture' => 'BSAgri',
  'BSAgri' => 'BSAgri',

  'bs agribusiness' => 'BSAB',
  'bachelor of science in agribusiness' => 'BSAB',
  'agribusiness' => 'BSAB',
  'Bachelor of Science in Agribusiness' => 'BSAB',
  'BS Agribusiness' => 'BSAB',
  'BSAB' => 'BSAB',

  'bs environmental science' => 'BSES',
  'bachelor of science in environmental science' => 'BSES',
  'environmental science' => 'BSES',
  'Bachelor of Science in Environmental Science' => 'BSES',
  'BS Environmental Science' => 'BSES',
  'BSES' => 'BSES',

  'bs food technology' => 'BSFT',
  'bachelor of science in food technology' => 'BSFT',
  'food technology' => 'BSFT',
  'Bachelor of Science in Food Technology' => 'BSFT',
  'BS Food Technology' => 'BSFT',
  'BSFT' => 'BSFT',

  'bs forestry' => 'BSFor',
  'bachelor of science in forestry' => 'BSFor',
  'forestry' => 'BSFor',
  'Bachelor of Science in Forestry' => 'BSFor',
  'BS Forestry' => 'BSFor',
  'BSFor' => 'BSFor',

  'bs agricultural and biosystems engineering' => 'BSABE',
  'bachelor of science in agricultural and biosystems engineering' => 'BSABE',
  'agricultural and biosystems engineering' => 'BSABE',
  'Bachelor of Science in Agricultural and Biosystems Engineering' => 'BSABE',
  'BS Agricultural and Biosystems Engineering' => 'BSABE',
  'BSABE' => 'BSABE',

  'bachelor of agricultural entrepreneurship' => 'BAE',
  'agricultural entrepreneurship' => 'BAE',
  'Bachelor of Agricultural Entrepreneurship' => 'BAE',
  'BA Agricultural Entrepreneurship' => 'BAE',
  'BAE' => 'BAE',

  'bs land use design and management' => 'BSLDM',
  'bachelor of science in land use design and management' => 'BSLDM',
  'land use design and management' => 'BSLDM',
  'Bachelor of Science in Land Use Design and Management' => 'BSLDM',
  'BS Land Use Design and Management' => 'BSLDM',
  'BSLDM' => 'BSLDM',

  // CAS Courses
  'bs biology' => 'BSBio',
  'bachelor of science in biology' => 'BSBio',
  'biology' => 'BSBio',
  'Bachelor of Science in Biology' => 'BSBio',
  'BS Biology' => 'BSBio',
  'BSBio' => 'BSBio',

  'bs chemistry' => 'BSChem',
  'bachelor of science in chemistry' => 'BSChem',
  'chemistry' => 'BSChem',
  'Bachelor of Science in Chemistry' => 'BSChem',
  'BS Chemistry' => 'BSChem',
  'BSChem' => 'BSChem',

  'bs mathematics' => 'BSMath',
  'bachelor of science in mathematics' => 'BSMath',
  'mathematics' => 'BSMath',
  'Bachelor of Science in Mathematics' => 'BSMath',
  'BS Mathematics' => 'BSMath',
  'BSMath' => 'BSMath',

  'bs physics' => 'BSPhysics',
  'bachelor of science in physics' => 'BSPhysics',
  'physics' => 'BSPhysics',
  'Bachelor of Science in Physics' => 'BSPhysics',
  'BS Physics' => 'BSPhysics',
  'BSPhysics' => 'BSPhysics',

  'bs psychology' => 'BSPsych',
  'bachelor of science in psychology' => 'BSPsych',
  'psychology' => 'BSPsych',
  'Bachelor of Science in Psychology' => 'BSPsych',
  'BS Psychology' => 'BSPsych',
  'BSPsych' => 'BSPsych',

  'ba english language studies' => 'BAELS',
  'bachelor of arts in english language studies' => 'BAELS',
  'english language studies' => 'BAELS',
  'Bachelor of Arts in English Language Studies' => 'BAELS',
  'BA English Language Studies' => 'BAELS',
  'BAELS' => 'BAELS',

  'ba communication' => 'BAComm',
  'bachelor of arts in communication' => 'BAComm',
  'communication' => 'BAComm',
  'Bachelor of Arts in Communication' => 'BAComm',
  'BA Communication' => 'BAComm',
  'BAComm' => 'BAComm',

  'bs statistics' => 'BSStat',
  'bachelor of science in statistics' => 'BSStat',
  'statistics' => 'BSStat',
  'Bachelor of Science in Statistics' => 'BSStat',
  'BS Statistics' => 'BSStat',
  'BSStat' => 'BSStat',

  // CVMBS Courses
  'doctor of veterinary medicine' => 'DVM',
  'veterinary medicine' => 'DVM',
  'Doctor of Veterinary Medicine' => 'DVM',
  'DVM' => 'DVM',

  'bs biology (pre-veterinary)' => 'BSPV',
  'bachelor of science in biology (pre-veterinary)' => 'BSPV',
  'biology (pre-veterinary)' => 'BSPV',
  'Bachelor of Science in Biology (Pre-Veterinary)' => 'BSPV',
  'BS Biology (Pre-Veterinary)' => 'BSPV',
  'BSPV' => 'BSPV',

  // CED Courses
  'bachelor of elementary education' => 'BEEd',
  'elementary education' => 'BEEd',
  'Bachelor of Elementary Education' => 'BEEd',
  'BE Elementary Education' => 'BEEd',
  'BEEd' => 'BEEd',

  'bachelor of secondary education' => 'BSEd',
  'secondary education' => 'BSEd',
  'Bachelor of Secondary Education' => 'BSEd',
  'BS Secondary Education' => 'BSEd',
  'BSEd' => 'BSEd',

  'bachelor of physical education' => 'BPE',
  'physical education' => 'BPE',
  'Bachelor of Physical Education' => 'BPE',
  'BS Physical Education' => 'BPE',
  'BPE' => 'BPE',

  'bachelor of technology and livelihood education' => 'BTLE',
  'technology and livelihood education' => 'BTLE',
  'Bachelor of Technology and Livelihood Education' => 'BTLE',
  'BS Technology and Livelihood Education' => 'BTLE',
  'BTLE' => 'BTLE',

  // CEMDS Courses
  'bs business administration' => 'BSBA',
  'bachelor of science in business administration' => 'BSBA',
  'business administration' => 'BSBA',
  'Bachelor of Science in Business Administration' => 'BSBA',
  'BS Business Administration' => 'BSBA',
  'BSBA' => 'BSBA',

  'bs accountancy' => 'BSAcc',
  'bachelor of science in accountancy' => 'BSAcc',
  'accountancy' => 'BSAcc',
  'Bachelor of Science in Accountancy' => 'BSAcc',
  'BS Accountancy' => 'BSAcc',
  'BSAcc' => 'BSAcc',

  'bs economics' => 'BSEco',
  'bachelor of science in economics' => 'BSEco',
  'economics' => 'BSEco',
  'Bachelor of Science in Economics' => 'BSEco',
  'BS Economics' => 'BSEco',
  'BSEco' => 'BSEco',

  'bs entrepreneurship' => 'BSEnt',
  'bachelor of science in entrepreneurship' => 'BSEnt',
  'entrepreneurship' => 'BSEnt',
  'Bachelor of Science in Entrepreneurship' => 'BSEnt',
  'BS Entrepreneurship' => 'BSEnt',
  'BSEnt' => 'BSEnt',

  'bs office administration' => 'BSOA',
  'bachelor of science in office administration' => 'BSOA',
  'office administration' => 'BSOA',
  'Bachelor of Science in Office Administration' => 'BSOA',
  'BS Office Administration' => 'BSOA',
  'BSOA' => 'BSOA',

  // CSPEAR Courses
  'bs exercise and sports sciences' => 'BSESS',
  'bachelor of science in exercise and sports sciences' => 'BSESS',
  'exercise and sports sciences' => 'BSESS',
  'Bachelor of Science in Exercise and Sports Sciences' => 'BSESS',
  'BS Exercise and Sports Sciences' => 'BSESS',
  'BSESS' => 'BSESS',

  // CCJ Courses
  'bs criminology' => 'BSCrim',
  'bachelor of science in criminology' => 'BSCrim',
  'criminology' => 'BSCrim',
  'Bachelor of Science in Criminology' => 'BSCrim',
  'BS Criminology' => 'BSCrim',
  'BSCrim' => 'BSCrim',

  // CON Courses
  'bs nursing' => 'BSN',
  'bachelor of science in nursing' => 'BSN',
  'nursing' => 'BSN',
  'Bachelor of Science in Nursing' => 'BSN',
  'BS Nursing' => 'BSN',
  'BSN' => 'BSN',

  // CTHM Courses
  'bs hospitality management' => 'BSHM',
  'bachelor of science in hospitality management' => 'BSHM',
  'hospitality management' => 'BSHM',
  'Bachelor of Science in Hospitality Management' => 'BSHM',
  'BS Hospitality Management' => 'BSHM',
  'BSHM' => 'BSHM',

  'bs tourism management' => 'BSTM',
  'bachelor of science in tourism management' => 'BSTM',
  'tourism management' => 'BSTM',
  'Bachelor of Science in Tourism Management' => 'BSTM',
  'BS Tourism Management' => 'BSTM',
  'BSTM' => 'BSTM',

  // COM Courses
  'bachelor of library and information science' => 'BLIS',
  'library and information science' => 'BLIS',
  'Bachelor of Library and Information Science' => 'BLIS',
  'BS Library and Information Science' => 'BLIS',
  'BLIS' => 'BLIS',

  // GS-OLC Courses
  'doctor of philosophy' => 'PhD',
  'Doctor of Philosophy' => 'PhD',
  'PhD' => 'PhD',

  'master of science' => 'MS',
  'Master of Science' => 'MS',
  'MS' => 'MS',

  'master of arts' => 'MA',
  'Master of Arts' => 'MA',
  'MA' => 'MA',
];

// Determine if election is completed
 $now = new DateTime();
 $start = new DateTime($election['start_datetime']);
 $end = new DateTime($election['end_datetime']);
 $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

// ===== GET UNIQUE VOTERS WHO HAVE VOTED (not total votes) =====
 $sql = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE election_id = ?";
 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $totalVotesCast = $stmt->fetch()['total'];

// ===== GET ELIGIBLE VOTERS COUNT (Same logic as voters dashboard) =====
 $conditions = ["role = 'voter'"];
 $params = [];

// Check if user is a college admin and add department filter
if (in_array($scope, $validCollegeScopes)) {
   // For college admins, filter by their assigned college
   $conditions[] = "UPPER(TRIM(department)) = ?";
   $params[] = $scope;
}
// Add handling for Non-Academic Admin
else if ($scope === 'NON-ACADEMIC') {
   // For Non-Academic Admin, only show non-academic voters
   $conditions[] = "position = 'non-academic'";
}

if ($election['target_position'] === 'coop') {
   // For COOP elections - only users with both is_coop_member=1 AND migs_status=1
   $conditions[] = "is_coop_member = 1";
   $conditions[] = "migs_status = 1";
} else {
   // For other elections - apply position filter first
   if ($election['target_position'] !== 'All') {
       if ($election['target_position'] === 'faculty') {
           $conditions[] = "position = ?";
           $params[] = 'academic';
       } else {
           $conditions[] = "position = ?";
           $params[] = $election['target_position'];
       }
   }
   
   // Get allowed filters from election
   $allowed_colleges = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_colleges'] ?? ''))));
   $allowed_courses = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_courses'] ?? ''))));
   $allowed_status = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_status'] ?? ''))));
   $allowed_departments = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_departments'] ?? ''))));
   
   // Apply college filter if specified (but not for college admins, as they're already filtered by their scope)
   if (!empty($allowed_colleges) && !in_array('ALL', $allowed_colleges) && !in_array($scope, $validCollegeScopes)) {
       $placeholders = implode(',', array_fill(0, count($allowed_colleges), '?'));
       $conditions[] = "UPPER(department) IN ($placeholders)";
       $params = array_merge($params, $allowed_colleges);
   }
   
   // Apply department filter if specified (for non-academic elections)
   if ($election['target_position'] === 'non-academic' && !empty($allowed_departments) && !in_array('ALL', $allowed_departments)) {
       $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
       $conditions[] = "UPPER(department) IN ($placeholders)";
       $params = array_merge($params, $allowed_departments);
   }
   
   // Apply course filter if specified (mainly for students)
   if (!empty($allowed_courses) && !in_array('ALL', $allowed_courses)) {
       // Create reverse course map: short_code => array of full names (in lowercase)
       $reverse_course_map = [];
       foreach ($course_map as $full_name => $short_code) {
           $reverse_course_map[strtoupper($short_code)][] = strtolower($full_name);
       }

       $course_list = [];
       foreach ($allowed_courses as $course) {
           if (isset($reverse_course_map[$course])) {
               $course_list = array_merge($course_list, $reverse_course_map[$course]);
           }
           // If the course is not in the map, add it as is (in case it's already a full name)
           else {
               $course_list[] = strtolower($course);
           }
       }

       if (!empty($course_list)) {
           $placeholders = implode(',', array_fill(0, count($course_list), '?'));
           $conditions[] = "LOWER(course) IN ($placeholders)";
           $params = array_merge($params, $course_list);
       }
   }
   
   // Apply status filter if specified (mainly for faculty and non-academic)
   if (!empty($allowed_status) && !in_array('ALL', $allowed_status)) {
       $placeholders = implode(',', array_fill(0, count($allowed_status), '?'));
       $conditions[] = "UPPER(status) IN ($placeholders)";
       $params = array_merge($params, $allowed_status);
   }
}

// Build and execute the query for eligible voters
 $sql = "SELECT COUNT(*) as total FROM users WHERE " . implode(' AND ', $conditions);
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $totalEligibleVoters = $stmt->fetch()['total'];

// Calculate turnout percentage
 $turnoutPercentage = ($totalEligibleVoters > 0) ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1) : 0;

// ===== GET DISTINCT POSITIONS FOR THIS ELECTION =====
 $positionSql = "SELECT DISTINCT position FROM election_candidates WHERE election_id = ? ORDER BY position";
 $stmt = $pdo->prepare($positionSql);
 $stmt->execute([$electionId]);
 $positions = $stmt->fetchAll();
 $positionOptions = array_column($positions, 'position');

// Add "All" option at the beginning
array_unshift($positionOptions, 'All');

// ===== GET CANDIDATES WITH VOTE COUNTS =====
 $sql = "
    SELECT 
        ec.id as election_candidate_id,
        c.id as candidate_id,
        CONCAT(c.first_name, ' ', c.last_name) as candidate_name,
        c.photo,
        ec.position as election_position,
        COUNT(v.vote_id) as vote_count
    FROM election_candidates ec
    JOIN candidates c ON ec.candidate_id = c.id
    LEFT JOIN votes v ON ec.election_id = v.election_id 
                   AND ec.candidate_id = v.candidate_id
    WHERE ec.election_id = ?
    GROUP BY ec.id, c.id, c.first_name, c.last_name, c.photo, ec.position
    ORDER BY ec.position, vote_count DESC
";

 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $candidatesWithVotes = $stmt->fetchAll();

// Group candidates by position
 $candidatesByPosition = [];
foreach ($candidatesWithVotes as $candidate) {
    $position = $candidate['election_position'];
    if (!isset($candidatesByPosition[$position])) {
        $candidatesByPosition[$position] = [];
    }
    $candidatesByPosition[$position][] = $candidate;
}

 $pageTitle = $status === 'completed' ? 'Election Results' : 'Vote Counts';

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($election['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .progress-bar {
      transition: width 1s ease-in-out;
    }
    .candidate-card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .candidate-card:hover {
      transform: translateY(-2px);
    }
    .candidate-card-highlight {
      border: 2px solid #FFD700;
      box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
      background-color: #FFFBEB;
    }
    .live-indicator {
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }
    .position-section {
      scroll-margin-top: 80px;
    }
    .rank-badge {
      width: 40px;
      height: 40px;
    }
    .rank-1 {
      background: linear-gradient(135deg, #FFD700, #FFA500);
      color: white;
    }
    .rank-2 {
      background: linear-gradient(135deg, #9e9e9e, #757575);
      color: white;
    }
    .rank-3 {
      background: linear-gradient(135deg, #9e9e9e, #757575);
      color: white;
    }
    .rank-other {
      background: linear-gradient(135deg, #9e9e9e, #757575);
      color: white;
    }
    .tie-indicator {
      background: linear-gradient(135deg, #FFD166, #FFA500);
      color: white;
    }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
  
  <main class="flex-1 p-6 md:p-8 md:ml-64">
    <div class="max-w-6xl mx-auto">
      <!-- Election Information Header -->
      <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl shadow-xl overflow-hidden mb-8 border border-gray-100">
        <!-- Card Header -->
        <div class="bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] p-6 relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-5 rounded-full -ml-12 -mb-12"></div>
            
            <div class="flex flex-col md:flex-row md:items-center md:justify-between relative z-10">
            <div class="flex-1">
                <div class="flex items-center mb-3">
                <div class="bg-white bg-opacity-20 p-3 rounded-xl mr-4 shadow-md">
                    <i class="fas fa-vote-yea text-white text-3xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white leading-tight">
                    <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="text-green-100 text-lg font-medium">
                    <?= htmlspecialchars($election['title']) ?>
                    </p>
                </div>
                </div>
            </div>
            
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
                <?php if ($status === 'ongoing' && $election['realtime_results']): ?>
                <span class="live-indicator inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-red-500 text-white shadow-lg animate-pulse">
                    <i class="fas fa-circle mr-2 text-xs"></i> LIVE
                </span>
                <?php endif; ?>
                
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold shadow-md
                    <?= $status === 'completed' ? 'bg-green-500 text-white' : 
                        ($status === 'ongoing' ? 'bg-blue-500 text-white' : 'bg-yellow-600 text-white') ?>">
                <?php if ($status === 'completed'): ?>
                    <i class="fas fa-check-circle mr-2"></i> Completed
                <?php elseif ($status === 'ongoing'): ?>
                    <i class="fas fa-clock mr-2"></i> Ongoing
                <?php else: ?>
                    <i class="fas fa-hourglass-start mr-2"></i> Upcoming
                <?php endif; ?>
                </span>
            </div>
            </div>
        </div>
        
        <!-- Card Body -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center">
                <div class="bg-green-50 p-4 rounded-xl mr-4 shadow-sm border border-green-100">
                    <i class="far fa-calendar-alt text-green-600 text-2xl"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-500 mb-1">Election Period</p>
                    <p class="text-lg font-semibold text-gray-800">
                    <?= date("F j, Y, g:i A", strtotime($election['start_datetime'])) ?>
                    </p>
                    <div class="flex items-center my-2">
                    <div class="h-px bg-green-200 flex-grow"></div>
                    <span class="px-3 text-xs font-medium text-green-600 bg-green-50 rounded-full">to</span>
                    <div class="h-px bg-green-200 flex-grow"></div>
                    </div>
                    <p class="text-lg font-semibold text-gray-800">
                    <?= date("F j, Y, g:i A", strtotime($election['end_datetime'])) ?>
                    </p>
                </div>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center">
                <div class="bg-green-50 p-4 rounded-xl mr-4 shadow-sm border border-green-100">
                    <i class="fas fa-filter text-green-600 text-2xl"></i>
                </div>
                <div class="w-full">
                    <label for="positionFilter" class="block text-sm font-medium text-gray-700 mb-2">
                    Filter by Position
                    </label>
                    <select id="positionFilter" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)] transition-all">
                    <?php foreach ($positionOptions as $position): ?>
                        <option value="<?= htmlspecialchars($position) ?>" <?= $position === 'All' ? 'selected' : '' ?>>
                        <?= htmlspecialchars($position) ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </div>
                </div>
            </div>
            </div>
        </div>
        </div>
      
      <!-- Vote Turnout Statistics and Candidates Section -->
      <div id="electionResults">
        <!-- Vote Turnout Statistics -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-users text-blue-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-blue-800">Eligible Voters</p>
                <p class="text-2xl font-bold text-blue-900"><?= number_format($totalEligibleVoters) ?></p>
              </div>
            </div>
          </div>
          
          <div class="bg-gradient-to-r from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-green-800">Votes Cast</p>
                <p class="text-2xl font-bold text-green-900"><?= number_format($totalVotesCast) ?></p>
              </div>
            </div>
          </div>
          
          <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-percentage text-purple-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-purple-800">Turnout Rate</p>
                <p class="text-2xl font-bold text-purple-900"><?= $turnoutPercentage ?>%</p>
              </div>
            </div>
          </div>
          
          <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <i class="fas fa-user-friends text-yellow-600 text-xl"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-yellow-800">Candidates</p>
                <p class="text-2xl font-bold text-yellow-900"><?= count($candidatesWithVotes) ?></p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Candidates Vote Counts by Position -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Candidate Vote Counts by Position</h2>
          </div>
          
          <div class="p-6">
            <?php if (empty($candidatesByPosition)): ?>
              <div class="text-center py-8">
                <i class="fas fa-users text-gray-400 text-4xl mb-3"></i>
                <p class="text-gray-600">No candidates found for this election.</p>
              </div>
            <?php else: ?>
              <?php foreach ($candidatesByPosition as $position => $candidates): ?>
                <?php
                // Sort candidates by vote count for ranking
                usort($candidates, function($a, $b) {
                  return $b['vote_count'] - $a['vote_count'];
                });
                
                $totalVotesForPosition = array_sum(array_column($candidates, 'vote_count'));
                
                // Check if there's a tie for first place with votes > 0
                $isFirstPlaceTie = false;
                if (count($candidates) > 1 && $candidates[0]['vote_count'] > 0) {
                    $firstPlaceVotes = $candidates[0]['vote_count'];
                    for ($i = 1; $i < count($candidates); $i++) {
                        if ($candidates[$i]['vote_count'] == $firstPlaceVotes) {
                            $isFirstPlaceTie = true;
                            break;
                        } else {
                            break; // Since candidates are sorted by vote count
                        }
                    }
                }
                
                // Initialize tie detection variables
                $prevVoteCount = null;
                $prevRank = null;
                $isTie = false;
                ?>
                
                <!-- Position Header -->
                <div class="position-section mb-8 last:mb-0">
                  <div class="flex items-center mb-4 pb-2 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-[var(--cvsu-green-dark)]">
                      <?= htmlspecialchars($position) ?>
                    </h3>
                    <span class="ml-3 text-sm text-gray-500">
                      <?= count($candidates) ?> candidate<?= count($candidates) != 1 ? 's' : '' ?> • 
                      <?= number_format($totalVotesForPosition) ?> vote<?= $totalVotesForPosition != 1 ? 's' : '' ?>
                    </span>
                  </div>
                  
                  <!-- Candidates List for this Position (Full Width Cards) -->
                  <div class="space-y-4">
                    <?php foreach ($candidates as $index => $data): ?>
                      <?php
                      $candidateId = $data['candidate_id'];
                      $candidateName = $data['candidate_name'];
                      $candidatePhoto = $data['photo'];
                      $electionPosition = $data['election_position'];
                      $voteCount = $data['vote_count'];
                      $percentage = $totalVotesForPosition > 0 ? round(($voteCount / $totalVotesForPosition) * 100, 1) : 0;
                      
                      // Check for tie only if vote count > 0
                      if ($voteCount > 0 && $prevVoteCount === $voteCount) {
                          $isTie = true;
                          $rank = $prevRank;
                      } else {
                          $isTie = false;
                          $rank = $index + 1;
                          $prevRank = $rank;
                      }
                      
                      $prevVoteCount = $voteCount;
                      
                      // Determine if candidate card should be highlighted (rank 1 AND has votes > 0)
                      $isHighlighted = ($rank === 1 && $voteCount > 0);
                      ?>
                      
                      <div class="candidate-card <?= $isHighlighted ? 'candidate-card-highlight' : 'border border-gray-200' ?> bg-white rounded-lg shadow-sm p-4 hover:shadow-md" data-position="<?= htmlspecialchars($position) ?>">
                        <div class="flex items-center">
                          <!-- Rank Badge -->
                          <div class="flex-shrink-0 mr-4">
                            <div class="rank-badge rounded-full flex items-center justify-center font-bold text-lg 
                                <?= $rank === 1 ? ($isFirstPlaceTie ? 'tie-indicator' : 'rank-1') : 
                                   ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other')) ?>">
                              <?= $rank ?>
                            </div>
                            <?php if ($isTie && $voteCount > 0): ?>
                              <div class="text-xs text-center text-yellow-600 mt-1 font-bold">TIE</div>
                            <?php endif; ?>
                          </div>
                          
                          <!-- Candidate Photo -->
                          <div class="flex-shrink-0 mr-4">
                            <?php if (!empty($candidatePhoto)): ?>
                              <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                                   alt="<?= htmlspecialchars($candidateName) ?>" 
                                   class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-md">
                            <?php else: ?>
                              <div class="w-16 h-16 rounded-full bg-gray-200 border-2 border-white shadow-md flex items-center justify-center">
                                <span class="text-gray-500 text-sm font-medium">
                                  <?= substr($candidateName, 0, 1) ?>
                                </span>
                              </div>
                            <?php endif; ?>
                          </div>
                          
                          <!-- Candidate Details -->
                          <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                              <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                  <?= htmlspecialchars($candidateName) ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                  <?= htmlspecialchars($electionPosition) ?>
                                </p>
                              </div>
                              
                              <div class="mt-2 md:mt-0 text-right">
                                <p class="text-xl font-bold text-gray-900"><?= number_format($voteCount) ?></p>
                                <p class="text-sm text-gray-500">votes</p>
                              </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="mt-3">
                              <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span><?= $percentage ?>% of position votes</span>
                                <span><?= round(($voteCount / max($totalVotesForPosition, 1)) * 100, 1) ?>%</span>
                              </div>
                              <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="progress-bar bg-gradient-to-r from-[var(--cvsu-green)] to-[var(--cvsu-green-light)] h-3 rounded-full flex items-center justify-end pr-2" 
                                     style="width: <?= $percentage ?>%">
                                  <?php if ($percentage > 15): ?>
                                    <span class="text-xs text-white font-medium"><?= $percentage ?>%</span>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="mt-2 flex items-center justify-between text-sm">
                              <span class="text-gray-500">
                                <i class="fas fa-chart-line mr-1"></i>
                                Rank #<?= $rank ?> in <?= htmlspecialchars($position) ?>
                                <?php if ($isTie && $voteCount > 0): ?>
                                  <span class="text-yellow-600 font-medium">(TIE)</span>
                                <?php endif; ?>
                              </span>
                              <?php if ($status === 'completed' && $rank === 1 && $voteCount > 0): ?>
                                <?php if ($isFirstPlaceTie): ?>
                                  <span class="text-yellow-600 font-medium">
                                    <i class="fas fa-trophy mr-1"></i> TIE for <?= htmlspecialchars($position) ?>
                                  </span>
                                <?php else: ?>
                                  <span class="text-green-600 font-medium">
                                    <i class="fas fa-trophy mr-1"></i> Winner for <?= htmlspecialchars($position) ?>
                                  </span>
                                <?php endif; ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Back Button -->
      <div class="mt-6">
        <a href="admin_view_elections.php" 
           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          <i class="fas fa-arrow-left mr-2"></i>
          Back to Elections
        </a>
      </div>
    </div>
  </main>
</div>

<!-- Real-time Update Script (for ongoing elections with realtime_results enabled) -->
<?php if ($status === 'ongoing' && $election['realtime_results']): ?>
<script>
  function updateVoteCounts() {
    fetch('view_vote_counts.php?id=<?= $electionId ?>&ajax=1')
      .then(response => response.text())
      .then(html => {
        // Update the election results section
        document.getElementById('electionResults').innerHTML = html;
        
        // Reinitialize position filter
        const positionFilter = document.getElementById('positionFilter');
        if (positionFilter) {
          positionFilter.dispatchEvent(new Event('change'));
        }
        
        // Reinitialize progress bars animation
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
          const width = bar.style.width;
          bar.style.width = '0%';
          
          setTimeout(() => {
            bar.style.width = width;
          }, 100);
        });
      })
      .catch(error => console.error('Error updating vote counts:', error));
  }
  
  // Update every 30 seconds
  setInterval(updateVoteCounts, 30000);
</script>
<?php endif; ?>

<!-- Position Filter Script -->
<script>
  document.getElementById('positionFilter').addEventListener('change', function() {
    const selectedPosition = this.value;
    const positionSections = document.querySelectorAll('.position-section');
    
    positionSections.forEach(section => {
      const sectionPosition = section.querySelector('h3').textContent.trim();
      
      if (selectedPosition === 'All' || sectionPosition === selectedPosition) {
        section.style.display = 'block';
      } else {
        section.style.display = 'none';
      }
    });
  });
</script>

<!-- AJAX Handler for Real-time Updates -->
<?php if (isset($_GET['ajax']) && $_GET['ajax'] === '1'): ?>
  <?php
  // Recalculate unique voters count
  $sql = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE election_id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$electionId]);
  $totalVotesCast = $stmt->fetch()['total'];
  
  // Recalculate turnout percentage
  $turnoutPercentage = ($totalEligibleVoters > 0) ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1) : 0;
  
  // Re-run the candidate vote count query
  $sql = "
      SELECT 
          ec.id as election_candidate_id,
          c.id as candidate_id,
          CONCAT(c.first_name, ' ', c.last_name) as candidate_name,
          c.photo,
          ec.position as election_position,
          COUNT(v.vote_id) as vote_count
      FROM election_candidates ec
      JOIN candidates c ON ec.candidate_id = c.id
      LEFT JOIN votes v ON ec.election_id = v.election_id 
                     AND ec.candidate_id = v.candidate_id
      WHERE ec.election_id = ?
      GROUP BY ec.id, c.id, c.first_name, c.last_name, c.photo, ec.position
      ORDER BY ec.position, vote_count DESC
  ";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$electionId]);
  $candidatesWithVotes = $stmt->fetchAll();
  
  // Group by position
  $candidatesByPosition = [];
  foreach ($candidatesWithVotes as $candidate) {
      $position = $candidate['election_position'];
      if (!isset($candidatesByPosition[$position])) {
          $candidatesByPosition[$position] = [];
      }
      $candidatesByPosition[$position][] = $candidate;
  }
  
  // Return only the election results section for AJAX updates
  ob_start();
  ?>
  <!-- Vote Turnout Statistics -->
  <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-users text-blue-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-blue-800">Eligible Voters</p>
          <p class="text-2xl font-bold text-blue-900"><?= number_format($totalEligibleVoters) ?></p>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-r from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-check-circle text-green-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-green-800">Votes Cast</p>
          <p class="text-2xl font-bold text-green-900"><?= number_format($totalVotesCast) ?></p>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-percentage text-purple-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-purple-800">Turnout Rate</p>
          <p class="text-2xl font-bold text-purple-900"><?= $turnoutPercentage ?>%</p>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fas fa-user-friends text-yellow-600 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-sm font-medium text-yellow-800">Candidates</p>
          <p class="text-2xl font-bold text-yellow-900"><?= count($candidatesWithVotes) ?></p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Candidates Vote Counts by Position -->
  <div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-800">Candidate Vote Counts by Position</h2>
    </div>
    
    <div class="p-6">
      <?php if (empty($candidatesByPosition)): ?>
        <div class="text-center py-8">
          <i class="fas fa-users text-gray-400 text-4xl mb-3"></i>
          <p class="text-gray-600">No candidates found for this election.</p>
        </div>
      <?php else: ?>
        <?php foreach ($candidatesByPosition as $position => $candidates): 
          usort($candidates, function($a, $b) {
            return $b['vote_count'] - $a['vote_count'];
          });
          
          $totalVotesForPosition = array_sum(array_column($candidates, 'vote_count'));
          
          // Check if there's a tie for first place with votes > 0
          $isFirstPlaceTie = false;
          if (count($candidates) > 1 && $candidates[0]['vote_count'] > 0) {
              $firstPlaceVotes = $candidates[0]['vote_count'];
              for ($i = 1; $i < count($candidates); $i++) {
                  if ($candidates[$i]['vote_count'] == $firstPlaceVotes) {
                      $isFirstPlaceTie = true;
                      break;
                  } else {
                      break; // Since candidates are sorted by vote count
                  }
              }
          }
          
          // Initialize tie detection variables
          $prevVoteCount = null;
          $prevRank = null;
          $isTie = false;
        ?>
          <div class="position-section mb-8 last:mb-0">
            <div class="flex items-center mb-4 pb-2 border-b border-gray-200">
              <h3 class="text-xl font-bold text-[var(--cvsu-green-dark)]">
                <?= htmlspecialchars($position) ?>
              </h3>
              <span class="ml-3 text-sm text-gray-500">
                <?= count($candidates) ?> candidate<?= count($candidates) != 1 ? 's' : '' ?> • 
                <?= number_format($totalVotesForPosition) ?> vote<?= $totalVotesForPosition != 1 ? 's' : '' ?>
              </span>
            </div>
            
            <!-- Candidates List for this Position (Full Width Cards) -->
            <div class="space-y-4">
              <?php foreach ($candidates as $index => $data): 
                $candidateName = $data['candidate_name'];
                $candidatePhoto = $data['photo'];
                $electionPosition = $data['election_position'];
                $voteCount = $data['vote_count'];
                $percentage = $totalVotesForPosition > 0 ? round(($voteCount / $totalVotesForPosition) * 100, 1) : 0;
                
                // Check for tie only if vote count > 0
                if ($voteCount > 0 && $prevVoteCount === $voteCount) {
                    $isTie = true;
                    $rank = $prevRank;
                } else {
                    $isTie = false;
                    $rank = $index + 1;
                    $prevRank = $rank;
                }
                
                $prevVoteCount = $voteCount;
                
                // Determine if candidate card should be highlighted (rank 1 AND has votes > 0)
                $isHighlighted = ($rank === 1 && $voteCount > 0);
              ?>
                <div class="candidate-card <?= $isHighlighted ? 'candidate-card-highlight' : 'border border-gray-200' ?> bg-white rounded-lg shadow-sm p-4 hover:shadow-md" data-position="<?= htmlspecialchars($position) ?>">
                  <div class="flex items-center">
                    <!-- Rank Badge -->
                    <div class="flex-shrink-0 mr-4">
                      <div class="rank-badge rounded-full flex items-center justify-center font-bold text-lg 
                          <?= $rank === 1 ? ($isFirstPlaceTie ? 'tie-indicator' : 'rank-1') : 
                             ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other')) ?>">
                        <?= $rank ?>
                      </div>
                      <?php if ($isTie && $voteCount > 0): ?>
                        <div class="text-xs text-center text-yellow-600 mt-1 font-bold">TIE</div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Candidate Photo -->
                    <div class="flex-shrink-0 mr-4">
                      <?php if (!empty($candidatePhoto)): ?>
                        <img src="<?= htmlspecialchars($candidatePhoto) ?>" 
                             alt="<?= htmlspecialchars($candidateName) ?>" 
                             class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-md">
                      <?php else: ?>
                        <div class="w-16 h-16 rounded-full bg-gray-200 border-2 border-white shadow-md flex items-center justify-center">
                          <span class="text-gray-500 text-sm font-medium">
                            <?= substr($candidateName, 0, 1) ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Candidate Details -->
                    <div class="flex-1">
                      <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                          <h3 class="text-lg font-semibold text-gray-900">
                            <?= htmlspecialchars($candidateName) ?>
                          </h3>
                          <p class="text-sm text-gray-600">
                            <?= htmlspecialchars($electionPosition) ?>
                          </p>
                        </div>
                        
                        <div class="mt-2 md:mt-0 text-right">
                          <p class="text-xl font-bold text-gray-900"><?= number_format($voteCount) ?></p>
                          <p class="text-sm text-gray-500">votes</p>
                        </div>
                      </div>
                      
                      <!-- Progress Bar -->
                      <div class="mt-3">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                          <span><?= $percentage ?>% of position votes</span>
                          <span><?= round(($voteCount / max($totalVotesForPosition, 1)) * 100, 1) ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                          <div class="progress-bar bg-gradient-to-r from-[var(--cvsu-green)] to-[var(--cvsu-green-light)] h-3 rounded-full flex items-center justify-end pr-2" 
                               style="width: <?= $percentage ?>%">
                            <?php if ($percentage > 15): ?>
                              <span class="text-xs text-white font-medium"><?= $percentage ?>%</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Additional Info -->
                      <div class="mt-2 flex items-center justify-between text-sm">
                        <span class="text-gray-500">
                          <i class="fas fa-chart-line mr-1"></i>
                          Rank #<?= $rank ?> in <?= htmlspecialchars($position) ?>
                          <?php if ($isTie && $voteCount > 0): ?>
                            <span class="text-yellow-600 font-medium">(TIE)</span>
                          <?php endif; ?>
                        </span>
                        <?php if ($status === 'completed' && $rank === 1 && $voteCount > 0): ?>
                          <?php if ($isFirstPlaceTie): ?>
                            <span class="text-yellow-600 font-medium">
                              <i class="fas fa-trophy mr-1"></i> TIE for <?= htmlspecialchars($position) ?>
                            </span>
                          <?php else: ?>
                            <span class="text-green-600 font-medium">
                              <i class="fas fa-trophy mr-1"></i> Winner for <?= htmlspecialchars($position) ?>
                            </span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php
  echo ob_get_clean();
  ?>
<?php endif; ?>

<script>
  // Animate progress bars on page load
  document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    setTimeout(() => {
      progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        setTimeout(() => {
          bar.style.width = width;
        }, 100);
      });
    }, 300);
    
    // Initialize position filter
    const positionFilter = document.getElementById('positionFilter');
    if (positionFilter) {
      positionFilter.dispatchEvent(new Event('change'));
    }
  });
</script>
</body>
</html>