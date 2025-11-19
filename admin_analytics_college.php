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

// Verify this is a College Admin
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $userInfo = $stmt->fetch();

 $role = $userInfo['role'] ?? '';
 $scope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

// Valid college scopes
 $validCollegeScopes = ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'];

if (!in_array($scope, $validCollegeScopes)) {
    header('Location: admin_analytics.php');
    exit();
}

// Get election ID from URL
 $electionId = $_GET['id'] ?? 0;
if (!$electionId) {
    header('Location: admin_analytics_college.php');
    exit();
}

// Fetch election details (only student elections that include this college)
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ? AND (target_position = 'student' OR target_position = 'All')");
 $stmt->execute([$electionId]);
 $election = $stmt->fetch();

if (!$election) {
    header('Location: admin_analytics_college.php');
    exit();
}

// Check if this college is included in the election
 $allowed_colleges = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_colleges'] ?? ''))));
if (!empty($allowed_colleges) && !in_array('ALL', $allowed_colleges) && !in_array($scope, $allowed_colleges)) {
    header('Location: admin_analytics_college.php');
    exit();
}

// UPDATED COURSE MAPPINGS - Match election creation format exactly
 $course_map = [
  // CEIT Courses
  'BS Computer Science' => 'BSCS',
  'Bachelor of Science in Computer Science' => 'BSCS',
  'Computer Science' => 'BSCS',
  'bscs' => 'BSCS',
  
  'BS Information Technology' => 'BSIT',
  'Bachelor of Science in Information Technology' => 'BSIT',
  'Information Technology' => 'BSIT',
  'bsit' => 'BSIT',
  
  'BS Computer Engineering' => 'BSCpE',
  'Bachelor of Science in Computer Engineering' => 'BSCpE',
  'Computer Engineering' => 'BSCpE',
  'bscpe' => 'BSCpE',
  
  'BS Electronics Engineering' => 'BSECE',
  'Bachelor of Science in Electronics Engineering' => 'BSECE',
  'Electronics Engineering' => 'BSECE',
  'bsece' => 'BSECE',
  
  'BS Civil Engineering' => 'BSCE',
  'Bachelor of Science in Civil Engineering' => 'BSCE',
  'Civil Engineering' => 'BSCE',
  'bsce' => 'BSCE',
  
  'BS Mechanical Engineering' => 'BSME',
  'Bachelor of Science in Mechanical Engineering' => 'BSME',
  'Mechanical Engineering' => 'BSME',
  'bsme' => 'BSME',
  
  'BS Electrical Engineering' => 'BSEE',
  'Bachelor of Science in Electrical Engineering' => 'BSEE',
  'Electrical Engineering' => 'BSEE',
  'bsee' => 'BSEE',
  
  'BS Industrial Engineering' => 'BSIE',
  'Bachelor of Science in Industrial Engineering' => 'BSIE',
  'Industrial Engineering' => 'BSIE',
  'bsie' => 'BSIE',
  
  'BS Architecture' => 'BSArch',
  'Bachelor of Science in Architecture' => 'BSArch',
  'Architecture' => 'BSArch',
  'bsarch' => 'BSArch',
  
  // CAFENR Courses
  'BS Agriculture' => 'BSAgri',
  'Bachelor of Science in Agriculture' => 'BSAgri',
  'Agriculture' => 'BSAgri',
  'bsagri' => 'BSAgri',
  
  'BS Agribusiness' => 'BSAB',
  'Bachelor of Science in Agribusiness' => 'BSAB',
  'Agribusiness' => 'BSAB',
  'bsab' => 'BSAB',
  
  'BS Environmental Science' => 'BSES',
  'Bachelor of Science in Environmental Science' => 'BSES',
  'Environmental Science' => 'BSES',
  'bses' => 'BSES',
  
  'BS Food Technology' => 'BSFT',
  'Bachelor of Science in Food Technology' => 'BSFT',
  'Food Technology' => 'BSFT',
  'bsft' => 'BSFT',
  
  'BS Forestry' => 'BSFor',
  'Bachelor of Science in Forestry' => 'BSFor',
  'Forestry' => 'BSFor',
  'bsfor' => 'BSFor',
  
  'BS Agricultural and Biosystems Engineering' => 'BSABE',
  'Bachelor of Science in Agricultural and Biosystems Engineering' => 'BSABE',
  'Agricultural and Biosystems Engineering' => 'BSABE',
  'bsabe' => 'BSABE',
  
  'Bachelor of Agricultural Entrepreneurship' => 'BAE',
  'Agricultural Entrepreneurship' => 'BAE',
  'bae' => 'BAE',
  
  'BS Land Use Design and Management' => 'BSLDM',
  'Bachelor of Science in Land Use Design and Management' => 'BSLDM',
  'Land Use Design and Management' => 'BSLDM',
  'bsldm' => 'BSLDM',
  
  // CAS Courses
  'BS Biology' => 'BSBio',
  'Bachelor of Science in Biology' => 'BSBio',
  'Biology' => 'BSBio',
  'bsbio' => 'BSBio',
  
  'BS Chemistry' => 'BSChem',
  'Bachelor of Science in Chemistry' => 'BSChem',
  'Chemistry' => 'BSChem',
  'bschem' => 'BSChem',
  
  'BS Mathematics' => 'BSMath',
  'Bachelor of Science in Mathematics' => 'BSMath',
  'Mathematics' => 'BSMath',
  'bsmath' => 'BSMath',
  
  'BS Physics' => 'BSPhysics',
  'Bachelor of Science in Physics' => 'BSPhysics',
  'Physics' => 'BSPhysics',
  'bsphysics' => 'BSPhysics',
  
  'BS Psychology' => 'BSPsych',
  'Bachelor of Science in Psychology' => 'BSPsych',
  'Psychology' => 'BSPsych',
  'bspsych' => 'BSPsych',
  
  'BA English Language Studies' => 'BAELS',
  'Bachelor of Arts in English Language Studies' => 'BAELS',
  'English Language Studies' => 'BAELS',
  'baels' => 'BAELS',
  
  'BA Communication' => 'BAComm',
  'Bachelor of Arts in Communication' => 'BAComm',
  'Communication' => 'BAComm',
  'bacomm' => 'BAComm',
  
  'BS Statistics' => 'BSStat',
  'Bachelor of Science in Statistics' => 'BSStat',
  'Statistics' => 'BSStat',
  'bsstat' => 'BSStat',
  
  // CVMBS Courses
  'Doctor of Veterinary Medicine' => 'DVM',
  'Veterinary Medicine' => 'DVM',
  'dvm' => 'DVM',
  
  'BS Biology (Pre-Veterinary)' => 'BSPV',
  'Biology (Pre-Veterinary)' => 'BSPV',
  'Pre-Veterinary' => 'BSPV',
  'bspv' => 'BSPV',
  
  // CED Courses
  'Bachelor of Elementary Education' => 'BEEd',
  'Elementary Education' => 'BEEd',
  'beed' => 'BEEd',
  
  'Bachelor of Secondary Education' => 'BSEd',
  'Secondary Education' => 'BSEd',
  'bsed' => 'BSEd',
  
  'Bachelor of Physical Education' => 'BPE',
  'Physical Education' => 'BPE',
  'bpe' => 'BPE',
  
  'Bachelor of Technology and Livelihood Education' => 'BTLE',
  'Technology and Livelihood Education' => 'BTLE',
  'btle' => 'BTLE',
  
  // CEMDS Courses
  'BS Business Administration' => 'BSBA',
  'Bachelor of Science in Business Administration' => 'BSBA',
  'Business Administration' => 'BSBA',
  'bsba' => 'BSBA',
  
  'BS Accountancy' => 'BSAcc',
  'Bachelor of Science in Accountancy' => 'BSAcc',
  'Accountancy' => 'BSAcc',
  'bsacc' => 'BSAcc',
  
  'BS Economics' => 'BSEco',
  'Bachelor of Science in Economics' => 'BSEco',
  'Economics' => 'BSEco',
  'bseco' => 'BSEco',
  
  'BS Entrepreneurship' => 'BSEnt',
  'Bachelor of Science in Entrepreneurship' => 'BSEnt',
  'Entrepreneurship' => 'BSEnt',
  'bsent' => 'BSEnt',
  
  'BS Office Administration' => 'BSOA',
  'Bachelor of Science in Office Administration' => 'BSOA',
  'Office Administration' => 'BSOA',
  'bsoa' => 'BSOA',
  
  // CSPEAR Courses
  'BS Exercise and Sports Sciences' => 'BSESS',
  'Bachelor of Science in Exercise and Sports Sciences' => 'BSESS',
  'Exercise and Sports Sciences' => 'BSESS',
  'bsess' => 'BSESS',
  
  // CCJ Courses
  'BS Criminology' => 'BSCrim',
  'Bachelor of Science in Criminology' => 'BSCrim',
  'Criminology' => 'BSCrim',
  'bscrim' => 'BSCrim',
  
  // CON Courses
  'BS Nursing' => 'BSN',
  'Bachelor of Science in Nursing' => 'BSN',
  'Nursing' => 'BSN',
  'bsn' => 'BSN',
  
  // CTHM Courses
  'BS Hospitality Management' => 'BSHM',
  'Bachelor of Science in Hospitality Management' => 'BSHM',
  'Hospitality Management' => 'BSHM',
  'bshm' => 'BSHM',
  
  'BS Tourism Management' => 'BSTM',
  'Bachelor of Science in Tourism Management' => 'BSTM',
  'Tourism Management' => 'BSTM',
  'bstm' => 'BSTM',
  
  // COM Courses
  'Bachelor of Library and Information Science' => 'BLIS',
  'Library and Information Science' => 'BLIS',
  'blis' => 'BLIS',
  
  // GS-OLC Courses
  'Doctor of Philosophy' => 'PhD',
  'Master of Science' => 'MS',
  'Master of Arts' => 'MA',
];

// COURSE NORMALIZATION FUNCTION
function normalizeCourseName($course) {
    if (empty($course)) return 'UNSPECIFIED';
    
    global $course_map;
    
    $course = strtoupper(trim($course));
    
    // Check if it's already a standard code
    if (isset($course_map[$course])) {
        return $course;
    }
    
    // Check if it's a full name that maps to a code
    foreach ($course_map as $fullName => $code) {
        if (strtoupper($fullName) === $course) {
            return $code;
        }
    }
    
    // Handle common variations
    $patterns = [
        '/^BACHELOR OF SCIENCE IN /i' => 'BS ',
        '/^BACHELOR OF ARTS IN /i' => 'BA ',
        '/^DOCTOR OF /i' => '',
        '/^MASTER OF /i' => '',
        '/^BS /i' => '',
        '/^BA /i' => '',
        '/^BSCS$/i' => 'BS COMPUTER SCIENCE',
        '/^BSIT$/i' => 'BS INFORMATION TECHNOLOGY',
        '/^BSCPE$/i' => 'BS COMPUTER ENGINEERING',
        '/^BSECE$/i' => 'BS ELECTRONICS ENGINEERING',
        '/^BSCE$/i' => 'BS CIVIL ENGINEERING',
        '/^BSME$/i' => 'BS MECHANICAL ENGINEERING',
        '/^BSEE$/i' => 'BS ELECTRICAL ENGINEERING',
        '/^BSIE$/i' => 'BS INDUSTRIAL ENGINEERING',
        '/^BSAGRI$/i' => 'BS AGRICULTURE',
        '/^BSAB$/i' => 'BS AGRIBUSINESS',
        '/^BSES$/i' => 'BS ENVIRONMENTAL SCIENCE',
        '/^BSFT$/i' => 'BS FOOD TECHNOLOGY',
        '/^BSFOR$/i' => 'BS FORESTRY',
        '/^BSABE$/i' => 'BS AGRICULTURAL AND BIOSYSTEMS ENGINEERING',
        '/^BSBIO$/i' => 'BS BIOLOGY',
        '/^BSCHEM$/i' => 'BS CHEMISTRY',
        '/^BSMATH$/i' => 'BS MATHEMATICS',
        '/^BSPHYSICS$/i' => 'BS PHYSICS',
        '/^BSPSYCH$/i' => 'BS PSYCHOLOGY',
        '/^BSSTAT$/i' => 'BS STATISTICS',
        '/^DVM$/i' => 'DOCTOR OF VETERINARY MEDICINE',
        '/^BSPV$/i' => 'BS BIOLOGY (PRE-VETERINARY)',
        '/^BEED$/i' => 'BACHELOR OF ELEMENTARY EDUCATION',
        '/^BSED$/i' => 'BACHELOR OF SECONDARY EDUCATION',
        '/^BPE$/i' => 'BACHELOR OF PHYSICAL EDUCATION',
        '/^BTLE$/i' => 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION',
        '/^BSBA$/i' => 'BS BUSINESS ADMINISTRATION',
        '/^BSACC$/i' => 'BS ACCOUNTANCY',
        '/^BSECO$/i' => 'BS ECONOMICS',
        '/^BSENT$/i' => 'BS ENTREPRENEURSHIP',
        '/^BSOA$/i' => 'BS OFFICE ADMINISTRATION',
        '/^BSESS$/i' => 'BS EXERCISE AND SPORTS SCIENCES',
        '/^BSCRIM$/i' => 'BS CRIMINOLOGY',
        '/^BSN$/i' => 'BS NURSING',
        '/^BSHM$/i' => 'BS HOSPITALITY MANAGEMENT',
        '/^BSTM$/i' => 'BS TOURISM MANAGEMENT',
        '/^BLIS$/i' => 'BACHELOR OF LIBRARY AND INFORMATION SCIENCE',
        '/^PHD$/i' => 'DOCTOR OF PHILOSOPHY',
        '/^MS$/i' => 'MASTER OF SCIENCE',
        '/^MA$/i' => 'MASTER OF ARTS'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $course)) {
            $normalized = preg_replace($pattern, $replacement, $course);
            return strtoupper(trim($normalized));
        }
    }
    
    // Handle specific keyword matches
    $keywords = [
        'COMPUTER SCIENCE' => 'BS COMPUTER SCIENCE',
        'INFORMATION TECHNOLOGY' => 'BS INFORMATION TECHNOLOGY',
        'COMPUTER ENGINEERING' => 'BS COMPUTER ENGINEERING',
        'ELECTRONICS ENGINEERING' => 'BS ELECTRONICS ENGINEERING',
        'CIVIL ENGINEERING' => 'BS CIVIL ENGINEERING',
        'MECHANICAL ENGINEERING' => 'BS MECHANICAL ENGINEERING',
        'ELECTRICAL ENGINEERING' => 'BS ELECTRICAL ENGINEERING',
        'INDUSTRIAL ENGINEERING' => 'BS INDUSTRIAL ENGINEERING',
        'AGRICULTURE' => 'BS AGRICULTURE',
        'AGRIBUSINESS' => 'BS AGRIBUSINESS',
        'ENVIRONMENTAL SCIENCE' => 'BS ENVIRONMENTAL SCIENCE',
        'FOOD TECHNOLOGY' => 'BS FOOD TECHNOLOGY',
        'FORESTRY' => 'BS FORESTRY',
        'AGRICULTURAL AND BIOSYSTEMS ENGINEERING' => 'BS AGRICULTURAL AND BIOSYSTEMS ENGINEERING',
        'AGRICULTURAL ENTREPRENEURSHIP' => 'BACHELOR OF AGRICULTURAL ENTREPRENEURSHIP',
        'LAND USE DESIGN AND MANAGEMENT' => 'BS LAND USE DESIGN AND MANAGEMENT',
        'BIOLOGY' => 'BS BIOLOGY',
        'CHEMISTRY' => 'BS CHEMISTRY',
        'MATHEMATICS' => 'BS MATHEMATICS',
        'PHYSICS' => 'BS PHYSICS',
        'PSYCHOLOGY' => 'BS PSYCHOLOGY',
        'ENGLISH LANGUAGE STUDIES' => 'BA ENGLISH LANGUAGE STUDIES',
        'COMMUNICATION' => 'BA COMMUNICATION',
        'STATISTICS' => 'BS STATISTICS',
        'VETERINARY MEDICINE' => 'DOCTOR OF VETERINARY MEDICINE',
        'PRE-VETERINARY' => 'BS BIOLOGY (PRE-VETERINARY)',
        'ELEMENTARY EDUCATION' => 'BACHELOR OF ELEMENTARY EDUCATION',
        'SECONDARY EDUCATION' => 'BACHELOR OF SECONDARY EDUCATION',
        'PHYSICAL EDUCATION' => 'BACHELOR OF PHYSICAL EDUCATION',
        'TECHNOLOGY AND LIVELIHOOD EDUCATION' => 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION',
        'BUSINESS ADMINISTRATION' => 'BS BUSINESS ADMINISTRATION',
        'ACCOUNTANCY' => 'BS ACCOUNTANCY',
        'ECONOMICS' => 'BS ECONOMICS',
        'ENTREPRENEURSHIP' => 'BS ENTREPRENEURSHIP',
        'OFFICE ADMINISTRATION' => 'BS OFFICE ADMINISTRATION',
        'EXERCISE AND SPORTS SCIENCES' => 'BS EXERCISE AND SPORTS SCIENCES',
        'CRIMINOLOGY' => 'BS CRIMINOLOGY',
        'NURSING' => 'BS NURSING',
        'HOSPITALITY MANAGEMENT' => 'BS HOSPITALITY MANAGEMENT',
        'TOURISM MANAGEMENT' => 'BS TOURISM MANAGEMENT',
        'LIBRARY AND INFORMATION SCIENCE' => 'BACHELOR OF LIBRARY AND INFORMATION SCIENCE'
    ];
    
    foreach ($keywords as $keyword => $standard) {
        if (strpos($course, $keyword) !== false) {
            return $standard;
        }
    }
    
    // Return original if no match found
    return $course;
}

// Department abbreviation mapping
 $department_abbr_map = [
    // CEIT
    'Department of Civil Engineering' => 'DCE',
    'Department of Computer and Electronics Engineering' => 'DCEE',
    'Department of Industrial Engineering and Technology' => 'DIET',
    'Department of Mechanical and Electronics Engineering' => 'DMEE',
    'Department of Information Technology' => 'DIT',
    
    // CAFENR
    'Department of Animal Science' => 'DAS',
    'Department of Crop Science' => 'DCS',
    'Department of Food Science and Technology' => 'DFST',
    'Department of Forestry and Environmental Science' => 'DFES',
    'Department of Agricultural Economics and Development' => 'DAED',
    
    // CAS
    'Department of Biological Sciences' => 'DBS',
    'Department of Physical Sciences' => 'DPS',
    'Department of Languages and Mass Communication' => 'DLMC',
    'Department of Social Sciences' => 'DSS',
    'Department of Mathematics and Statistics' => 'DMS',
    
    // CCJ
    'Department of Criminal Justice' => 'DCJ',
    
    // CEMDS
    'Department of Economics' => 'DE',
    'Department of Business and Management' => 'DBM',
    'Department of Development Studies' => 'DDS',
    
    // CED
    'Department of Science Education' => 'DSE',
    'Department of Technology and Livelihood Education' => 'DTLE',
    'Department of Curriculum and Instruction' => 'DCI',
    'Department of Human Kinetics' => 'DHK',
    
    // CON
    'Department of Nursing' => 'DN',
    
    // COM
    'Department of Basic Medical Sciences' => 'DBMS',
    'Department of Clinical Sciences' => 'DCS',
    
    // CSPEAR
    'Department of Physical Education and Recreation' => 'DPER',
    
    // CVMBS
    'Department of Veterinary Medicine' => 'DVM',
    'Department of Biomedical Sciences' => 'DBS',
    
    // GS-OLC
    'Department of Various Graduate Programs' => 'DVGP',
];

// Define college departments structure
 $collegeDepartments = [
    "CAFENR" => [
        "Department of Animal Science",
        "Department of Crop Science",
        "Department of Food Science and Technology",
        "Department of Forestry and Environmental Science",
        "Department of Agricultural Economics and Development"
    ],
    "CAS" => [
        "Department of Biological Sciences",
        "Department of Physical Sciences",
        "Department of Languages and Mass Communication",
        "Department of Social Sciences",
        "Department of Mathematics and Statistics"
    ],
    "CCJ" => ["Department of Criminal Justice"],
    "CEMDS" => [
        "Department of Economics",
        "Department of Business and Management",
        "Department of Development Studies"
    ],
    "CED" => [
        "Department of Science Education",
        "Department of Technology and Livelihood Education",
        "Department of Curriculum and Instruction",
        "Department of Human Kinetics"
    ],
    "CEIT" => [
        "Department of Civil Engineering",
        "Department of Computer and Electronics Engineering",
        "Department of Industrial Engineering and Technology",
        "Department of Mechanical and Electronics Engineering",
        "Department of Information Technology"
    ],
    "CON" => ["Department of Nursing"],
    "COM" => [
        "Department of Basic Medical Sciences",
        "Department of Clinical Sciences"
    ],
    "CSPEAR" => ["Department of Physical Education and Recreation"],
    "CVMBS" => [
        "Department of Veterinary Medicine",
        "Department of Biomedical Sciences"
    ],
    "GS-OLC" => ["Department of Various Graduate Programs"]
];

// Define college courses structure
 $collegeCourses = [
    "CEIT" => [
        "BS Computer Science",
        "BS Information Technology", 
        "BS Computer Engineering",
        "BS Electronics Engineering",
        "BS Civil Engineering",
        "BS Mechanical Engineering",
        "BS Electrical Engineering",
        "BS Industrial Engineering"
    ],
    "CAFENR" => [
        "BS Agriculture",
        "BS Agribusiness",
        "BS Environmental Science",
        "BS Food Technology",
        "BS Forestry",
        "BS Agricultural and Biosystems Engineering",
        "Bachelor of Agricultural Entrepreneurship",
        "BS Land Use Design and Management"
    ],
    "CAS" => [
        "BS Biology",
        "BS Chemistry",
        "BS Mathematics",
        "BS Physics",
        "BS Psychology",
        "BA English Language Studies",
        "BA Communication",
        "BS Statistics"
    ],
    "CVMBS" => [
        "Doctor of Veterinary Medicine",
    ],
    "CED" => [
        "Bachelor of Elementary Education",
        "Bachelor of Secondary Education",
        "Bachelor of Physical Education",
        "Bachelor of Technology and Livelihood Education"
    ],
    "CEMDS" => [
        "BS Business Administration",
        "BS Accountancy",
        "BS Economics",
        "BS Entrepreneurship",
        "BS Office Administration"
    ],
    "CSPEAR" => [
        "Bachelor of Physical Education",
        "BS Exercise and Sports Sciences"
    ],
    "CCJ" => [
        "BS Criminology"
    ],
    "CON" => [
        "BS Nursing"
    ]
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

// ===== GET ELIGIBLE VOTERS COUNT =====
 $conditions = ["role = 'voter'", "position = 'student'", "UPPER(TRIM(department)) = ?"];
 $params = [$scope];

// Get allowed filters from election
 $allowed_courses = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_courses'] ?? ''))));

// Apply course filter if specified
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
        } else {
            $course_list[] = strtolower($course);
        }
    }
    
    if (!empty($course_list)) {
        $placeholders = implode(',', array_fill(0, count($course_list), '?'));
        $conditions[] = "LOWER(course) IN ($placeholders)";
        $params = array_merge($params, $course_list);
    }
}

// Build and execute the query for eligible voters
 $sql = "SELECT COUNT(*) as total FROM users WHERE " . implode(' AND ', $conditions);
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $totalEligibleVoters = $stmt->fetch()['total'];

// Calculate turnout percentage
 $turnoutPercentage = ($totalEligibleVoters > 0) ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1) : 0;

// ===== GET WINNERS BY POSITION =====
 $sql = "
   SELECT 
       ec.position,
       c.id as candidate_id,
       CONCAT(c.first_name, ' ', c.last_name) as candidate_name,
       COUNT(v.vote_id) as vote_count
   FROM election_candidates ec
   JOIN candidates c ON ec.candidate_id = c.id
   LEFT JOIN votes v ON ec.election_id = v.election_id 
                  AND ec.candidate_id = v.candidate_id
   WHERE ec.election_id = ?
   GROUP BY ec.position, c.id, c.first_name, c.last_name
   ORDER BY ec.position, vote_count DESC
";

 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $allCandidates = $stmt->fetchAll();

// Group by position and find winners
 $winnersByPosition = [];
foreach ($allCandidates as $candidate) {
    $position = $candidate['position'];
    if (!isset($winnersByPosition[$position])) {
        $winnersByPosition[$position] = [];
    }
    $winnersByPosition[$position][] = $candidate;
}

// For each position, determine winners (handle ties)
foreach ($winnersByPosition as $position => &$candidates) {
    if (empty($candidates)) continue;
    
    $maxVotes = $candidates[0]['vote_count'];
    $winners = [];
    
    foreach ($candidates as $candidate) {
        if ($candidate['vote_count'] == $maxVotes && $maxVotes > 0) {
            $winners[] = $candidate;
        } else {
            break;
        }
    }
    
    $candidates = $winners;
}

// ===== GET VOTER TURNOUT BREAKDOWN =====
 $sql = "
   SELECT 
       u.department,
       u.department1,
       u.course,
       COUNT(DISTINCT u.user_id) as eligible_count,
       COUNT(DISTINCT v.voter_id) as voted_count
   FROM users u
   LEFT JOIN votes v ON u.user_id = v.voter_id AND v.election_id = ?
   WHERE u.role = 'voter' AND u.position = 'student' AND UPPER(TRIM(u.department)) = ?
";

 $params = [$electionId, $scope];

// Apply course filter if specified
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
        } else {
            $course_list[] = strtolower($course);
        }
    }
    
    if (!empty($course_list)) {
        $placeholders = implode(',', array_fill(0, count($course_list), '?'));
        $sql .= " AND LOWER(u.course) IN ($placeholders)";
        $params = array_merge($params, $course_list);
    }
}

 $sql .= " GROUP BY u.department, u.department1, u.course ORDER BY u.department, u.department1, u.course";

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $voterTurnoutData = $stmt->fetchAll();

// Calculate turnout percentage for each group
foreach ($voterTurnoutData as &$data) {
    $data['turnout_percentage'] = ($data['eligible_count'] > 0) ? 
        round(($data['voted_count'] / $data['eligible_count']) * 100, 1) : 0;
}

// Prepare additional breakdown data for JavaScript
 $departmentData = [];
 $courseData = [];

// Group by department only
 $departmentMap = [];
foreach ($voterTurnoutData as $item) {
    $department = $item['department1'];
    if (!isset($departmentMap[$department])) {
        $departmentMap[$department] = [
            'department1' => $department,
            'eligible_count' => 0,
            'voted_count' => 0
        ];
    }
    $departmentMap[$department]['eligible_count'] += $item['eligible_count'];
    $departmentMap[$department]['voted_count'] += $item['voted_count'];
}

foreach ($departmentMap as &$data) {
    $data['turnout_percentage'] = ($data['eligible_count'] > 0) ? 
        round(($data['voted_count'] / $data['eligible_count']) * 100, 1) : 0;
}
 $departmentData = array_values($departmentMap);

// Group by course only - WITH NORMALIZATION
 $courseMap = [];
foreach ($voterTurnoutData as $item) {
    $normalizedCourse = normalizeCourseName($item['course']);
    if (!isset($courseMap[$normalizedCourse])) {
        $courseMap[$normalizedCourse] = [
            'course' => $normalizedCourse,
            'eligible_count' => 0,
            'voted_count' => 0
        ];
    }
    $courseMap[$normalizedCourse]['eligible_count'] += $item['eligible_count'];
    $courseMap[$normalizedCourse]['voted_count'] += $item['voted_count'];
}

foreach ($courseMap as &$data) {
    $data['turnout_percentage'] = ($data['eligible_count'] > 0) ? 
        round(($data['voted_count'] / $data['eligible_count']) * 100, 1) : 0;
}
 $courseData = array_values($courseMap);

// Get list of departments and courses for this college
 $departmentsList = isset($collegeDepartments[$scope]) ? $collegeDepartments[$scope] : [];
 $coursesList = isset($collegeCourses[$scope]) ? $collegeCourses[$scope] : [];

 $pageTitle = htmlspecialchars($scope) . ' Election Analytics';

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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .analytics-card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .analytics-card:hover {
      transform: translateY(-2px);
    }
    .winner-badge {
      background: linear-gradient(135deg, #FFD700, #FFA500);
      color: white;
    }
    
    /* Custom table styles */
    .data-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .data-table th, .data-table td {
      padding: 0.75rem;
      text-align: left;
    }
    
    .data-table th {
      background-color: #f3f4f6;
      font-weight: 600;
      color: #374151;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    
    .data-table td {
      border-bottom: 1px solid #e5e7eb;
    }
    
    .data-table tr:hover {
      background-color: #f9fafb;
    }
    
    .data-table .text-center {
      text-align: center;
    }
    
    .data-table .text-right {
      text-align: right;
    }
    
    .turnout-bar-container {
      width: 100%;
      height: 8px;
      background-color: #e5e7eb;
      border-radius: 4px;
      overflow: hidden;
    }
    
    .turnout-bar {
      height: 100%;
      border-radius: 4px;
    }
    
    .turnout-high {
      background-color: #10b981;
    }
    
    .turnout-medium {
      background-color: #f59e0b;
    }
    
    .turnout-low {
      background-color: #ef4444;
    }
    
    /* Custom scrollbar for table */
    .table-container::-webkit-scrollbar {
      height: 8px;
    }
    
    .table-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    
    /* Loading indicator */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s, visibility 0.3s;
    }
    
    .loading-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    
    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 5px solid #f3f3f3;
      border-top: 5px solid var(--cvsu-green);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* No data message styles */
    .no-data-message {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      text-align: center;
      color: #6b7280;
    }
    
    .no-data-message i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: #d1d5db;
    }
    
    .no-data-message p {
      font-size: 1.125rem;
      font-weight: 500;
    }
    
    /* Chart container with no data message */
    .chart-wrapper {
      position: relative;
      height: 100%;
      width: 100%;
    }
    
    .chart-no-data {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background-color: rgba(249, 250, 251, 0.9);
      z-index: 10;
    }
    
    .table-no-data {
      padding: 3rem;
      text-align: center;
    }

    .tooltip {
      position: relative;
      display: inline-block;
    }

    .tooltip .tooltiptext {
      visibility: hidden;
      width: 200px;
      background-color: rgba(0, 0, 0, 0.8);
      color: #fff;
      text-align: center;
      border-radius: 6px;
      padding: 8px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -100px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 14px;
    }

    .tooltip:hover .tooltiptext {
      visibility: visible;
      opacity: 1;
    }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
  
  <main class="flex-1 p-6 md:p-8 md:ml-64">
    <div class="max-w-7xl mx-auto">
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
                  <i class="fas fa-chart-line text-white text-3xl"></i>
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
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-green-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-users text-green-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Eligible Voters</p>
                  <p class="text-2xl font-bold text-gray-800"><?= number_format($totalEligibleVoters) ?></p>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-blue-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-check-circle text-blue-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Votes Cast</p>
                  <p class="text-2xl font-bold text-gray-800"><?= number_format($totalVotesCast) ?></p>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-purple-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-percentage text-purple-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Turnout Rate</p>
                  <p class="text-2xl font-bold text-gray-800"><?= $turnoutPercentage ?>%</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Winners Section -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-800">
            <i class="fas fa-trophy text-yellow-500 mr-2"></i>Election Winners
          </h2>
        </div>
        
        <div class="p-6">
          <?php if (empty($winnersByPosition)): ?>
            <div class="no-data-message">
              <i class="fas fa-users"></i>
              <p>No winners data available</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <?php foreach ($winnersByPosition as $position => $winners): ?>
                <?php foreach ($winners as $winner): ?>
                  <div class="analytics-card bg-gradient-to-br from-yellow-50 to-white rounded-xl border border-yellow-200 p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                      <div class="winner-badge w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg mr-4">
                        <i class="fas fa-trophy"></i>
                      </div>
                      <div>
                        <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($winner['candidate_name']) ?></h3>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($position) ?></p>
                      </div>
                    </div>
                    <div class="flex justify-between items-center">
                      <div>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($winner['vote_count']) ?></p>
                        <p class="text-sm text-gray-500">votes</p>
                      </div>
                      <?php if (count($winners) > 1): ?>
                        <span class="text-xs font-bold bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">TIE</span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Voter Turnout Analytics Section -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-800">
            <i class="fas fa-chart-pie text-green-600 mr-2"></i>Voter Turnout Analytics
          </h2>
          <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($scope) ?> Student Election (by department and course)</p>
        </div>
        
        <div class="p-6">
          <?php if (empty($voterTurnoutData)): ?>
            <div class="no-data-message">
              <i class="fas fa-chart-bar"></i>
              <p>No voters and votes data available</p>
            </div>
          <?php else: ?>
            <!-- Filter Section -->
            <div class="mb-6">
              <!-- Single flex container for all dropdowns -->
              <div class="flex flex-wrap items-center justify-center gap-6 mb-4">
                <!-- Breakdown Type Selector -->
                <div class="flex items-center">
                  <label for="breakdownType" class="mr-3 text-sm font-medium text-gray-700">Breakdown by:</label>
                  <select id="breakdownType" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="department">Department</option>
                    <option value="course">Course</option>
                  </select>
                </div>
                
                <!-- Department/Course Selector -->
                <div class="flex items-center">
                  <label id="filterLabel" for="filterSelect" class="mr-3 text-sm font-medium text-gray-700">Select Department:</label>
                  <select id="filterSelect" class="block w-48 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="all">All Departments</option>
                    <?php foreach ($departmentsList as $department): ?>
                      <option value="<?= htmlspecialchars($department) ?>"><?= htmlspecialchars($department) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- Chart Section -->
            <div class="mb-12">
              <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Turnout Visualization</h3>
              <div class="bg-gray-50 p-6 rounded-xl shadow-sm">
                <div class="h-96">
                  <div class="chart-wrapper">
                    <canvas id="turnoutChart"></canvas>
                    <div id="chartNoData" class="chart-no-data" style="display: none;">
                      <i class="fas fa-chart-bar text-gray-400 text-4xl mb-3"></i>
                      <p class="text-gray-600 text-lg">No voters and votes data available</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Detailed Breakdown Section -->
            <div class="mt-12">
              <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Detailed Breakdown</h3>
              <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto table-container">
                  <div id="tableContainer" class="w-full">
                    <!-- Table will be dynamically generated here -->
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Back Button -->
      <div class="mt-6">
        <a href="admin_analytics_college.php" 
           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          <i class="fas fa-arrow-left mr-2"></i>
          Back to Election Analytics
        </a>
      </div>
    </div>
  </main>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
  <div class="loading-spinner"></div>
</div>

<script>
// Store all breakdown data
const breakdownData = {
  'department': <?= json_encode($departmentData) ?>,
  'course': <?= json_encode($courseData) ?>
};

// Store departments and courses for this college
const collegeDepartments = <?= json_encode($departmentsList) ?>;
const collegeCourses = <?= json_encode($coursesList) ?>;

// Map from full name to abbreviation for courses
const fullNameToAbbrMap = {
  <?php 
  foreach ($course_map as $full_name => $abbr) {
    echo "'" . addslashes($full_name) . "': '$abbr',\n";
  }
  ?>
};

// Map from full name to abbreviation for departments
const departmentFullNameToAbbrMap = {
  <?php 
  foreach ($department_abbr_map as $full_name => $abbr) {
    echo "'" . addslashes($full_name) . "': '$abbr',\n";
  }
  ?>
};

// User scope (college)
const userScope = '<?= $scope ?>';

// Chart instance
let turnoutChartInstance = null;

// Current state
let currentState = {
  breakdownType: 'department',
  filterValue: 'all'
};

document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded');
  
  // Initialize URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  currentState.breakdownType = urlParams.get('breakdown') || 'department';
  currentState.filterValue = urlParams.get('filter') || 'all';
  
  // Set initial dropdown values
  document.getElementById('breakdownType').value = currentState.breakdownType;
  updateFilterDropdown();
  
  if (currentState.filterValue !== 'all') {
    document.getElementById('filterSelect').value = currentState.filterValue;
  }
  
  // Check if Chart.js is loaded
  if (typeof Chart === 'undefined') {
    console.error('Chart.js is not loaded!');
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload = function() {
      console.log('Chart.js loaded dynamically');
      updateView();
    };
    script.onerror = function() {
      console.error('Failed to load Chart.js');
      showChartNoDataMessage('Error loading chart library');
    };
    document.head.appendChild(script);
  } else {
    console.log('Chart.js is already loaded');
    updateView();
  }
  
  // Add event listener to breakdown type selector
  document.getElementById('breakdownType')?.addEventListener('change', function() {
    const selectedBreakdown = this.value;
    
    // Update state and URL
    updateState({ 
      breakdownType: selectedBreakdown,
      filterValue: 'all'
    });
    
    // Update filter dropdown options
    updateFilterDropdown();
  });
  
  // Add event listener to filter selector
  document.getElementById('filterSelect')?.addEventListener('change', function() {
    const selectedFilter = this.value;
    
    // Update state and URL
    updateState({ filterValue: selectedFilter });
  });
  
  // Handle back/forward buttons
  window.addEventListener('popstate', function(event) {
    if (event.state) {
      currentState = event.state;
      
      // Update dropdowns
      document.getElementById('breakdownType').value = currentState.breakdownType;
      updateFilterDropdown();
      document.getElementById('filterSelect').value = currentState.filterValue;
      
      // Update view without showing loading
      updateView(false);
    }
  });
});

function updateFilterDropdown() {
  const filterSelect = document.getElementById('filterSelect');
  const filterLabel = document.getElementById('filterLabel');
  
  // Clear existing options
  filterSelect.innerHTML = '';
  
  if (currentState.breakdownType === 'department') {
    filterLabel.textContent = 'Select Department:';
    filterSelect.innerHTML = '<option value="all">All Departments</option>';
    
    collegeDepartments.forEach(department => {
      const option = document.createElement('option');
      option.value = department;
      option.textContent = department;
      filterSelect.appendChild(option);
    });
  } else {
    filterLabel.textContent = 'Select Course:';
    filterSelect.innerHTML = '<option value="all">All Courses</option>';
    
    collegeCourses.forEach(course => {
      const option = document.createElement('option');
      option.value = course;
      option.textContent = course;
      filterSelect.appendChild(option);
    });
  }
  
  // Reset filter value to 'all' when breakdown type changes
  currentState.filterValue = 'all';
}

function updateState(newState) {
  // Show loading
  showLoading();
  
  // Update current state
  currentState = { ...currentState, ...newState };
  
  // Update URL without reloading the page
  const url = new URL(window.location);
  url.searchParams.set('breakdown', currentState.breakdownType);
  url.searchParams.set('filter', currentState.filterValue);
  
  // Push new state to history
  window.history.pushState(currentState, '', url);
  
  // Update view
  updateView();
}

function updateView(showLoading = true) {
  if (showLoading) {
    // Small delay to show loading indicator
    setTimeout(() => {
      const data = getFilteredData();
      updateChart(data);
      generateTable(data);
      hideLoading();
    }, 300);
  } else {
    const data = getFilteredData();
    updateChart(data);
    generateTable(data);
  }
}

function getFilteredData() {
  let data = breakdownData[currentState.breakdownType];
  
  // Filter data if a specific department or course is selected
  if (currentState.filterValue !== 'all') {
    data = data.filter(item => {
      if (currentState.breakdownType === 'department') {
        return item.department1 === currentState.filterValue;
      } else {
        // For courses, we need to normalize the selected course name
        const normalizedFilter = normalizeCourseNameInJS(currentState.filterValue);
        return item.course === normalizedFilter;
      }
    });
  }
  
  return data;
}

function updateChart(data) {
  const canvas = document.getElementById('turnoutChart');
  const noDataDiv = document.getElementById('chartNoData');
  
  // If data is empty, show no data message and return
  if (data.length === 0) {
    if (canvas) {
      canvas.style.display = 'none';
    }
    if (noDataDiv) {
      noDataDiv.style.display = 'flex';
    }
    return;
  }

  // If we have data, make sure the canvas is visible and the no data message is hidden
  if (canvas) {
    canvas.style.display = 'block';
  }
  if (noDataDiv) {
    noDataDiv.style.display = 'none';
  }
  
  const ctx = canvas.getContext('2d');
  
  // Prepare labels - use abbreviations for display
  const labels = data.map(item => {
    if (currentState.breakdownType === 'course') {
      // Show the normalized course name
      return item.course;
    } else {
      // For departments, convert full name to abbreviation
      return departmentFullNameToAbbrMap[item.department1] || item.department1;
    }
  });
  
  // Prepare data
  const eligibleData = data.map(item => parseInt(item.eligible_count) || 0);
  const votedData = data.map(item => parseInt(item.voted_count) || 0);
  
  // Destroy existing chart if it exists
  if (turnoutChartInstance) {
    turnoutChartInstance.destroy();
  }
  
  // Calculate dynamic settings based on data count
  const dataCount = labels.length;
  
  // Adjust bar thickness and spacing based on data count
  let barThickness, categorySpacing, fontSize, maxBarThickness;
  
  if (dataCount <= 5) {
    // Few data points - thicker bars, larger text
    barThickness = 0.8;
    categorySpacing = 0.2;
    fontSize = 14;
    maxBarThickness = 80;
  } else if (dataCount <= 10) {
    // Medium data points - medium bars and text
    barThickness = 0.6;
    categorySpacing = 0.3;
    fontSize = 12;
    maxBarThickness = 60;
  } else if (dataCount <= 20) {
    // Many data points - thinner bars, smaller text
    barThickness = 0.4;
    categorySpacing = 0.4;
    fontSize = 10;
    maxBarThickness = 40;
  } else {
    // Very many data points - very thin bars, smallest text
    barThickness = 0.3;
    categorySpacing = 0.5;
    fontSize = 9;
    maxBarThickness = 30;
  }
  
  try {
    turnoutChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Eligible Voters',
            data: eligibleData,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            borderRadius: 4,
            barPercentage: barThickness,
            categoryPercentage: 1 - categorySpacing,
            maxBarThickness: maxBarThickness
          },
          {
            label: 'Voted',
            data: votedData,
            backgroundColor: 'rgba(75, 192, 192, 0.7)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1,
            borderRadius: 4,
            barPercentage: barThickness,
            categoryPercentage: 1 - categorySpacing,
            maxBarThickness: maxBarThickness
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            labels: {
              font: {
                size: 14
              },
              padding: 20
            }
          },
          title: {
            display: true,
            text: `Voter Turnout by ${currentState.breakdownType === 'course' ? 'Course' : 'Department'}`,
            font: {
              size: 18,
              weight: 'bold'
            },
            padding: {
              top: 10,
              bottom: 30
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleFont: {
              size: 14
            },
            bodyFont: {
              size: 13
            },
            padding: 12,
            cornerRadius: 4,
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                if (context.parsed.y !== null) {
                  label += new Intl.NumberFormat('en-US', { 
                    style: 'decimal', 
                    maximumFractionDigits: 0 
                  }).format(context.parsed.y);
                }
                return label;
              },
              // Use the title callback to show the full name
              title: function(context) {
                // Get the abbreviation from the chart label
                const abbreviation = context[0].label;
                // Convert the abbreviation to the full name
                return convertToFullName(abbreviation);
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
              font: {
                size: 12
              }
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.1)'
            },
            title: {
              display: true,
              text: 'Number of Voters',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          },
          x: {
            ticks: {
              font: {
                size: fontSize
              },
              maxRotation: 0, // Keep labels horizontal
              minRotation: 0,
              autoSkip: true,
              maxTicksLimit: 20 // Limit number of ticks shown
            },
            grid: {
              display: false
            },
            title: {
              display: true,
              text: currentState.breakdownType === 'course' ? 'Course' : 'Department',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          }
        },
        animation: {
          duration: 1000,
          easing: 'easeOutQuart'
        },
        layout: {
          padding: {
            left: 10,
            right: 10,
            top: 10,
            bottom: 10
          }
        }
      }
    });
    
    console.log('Chart updated successfully!');
  } catch (error) {
    console.error('Error updating chart:', error);
    showChartNoDataMessage('Error loading chart data');
  }
}

function generateTable(data) {
  const tableContainer = document.getElementById('tableContainer');
  
  // Clear existing content
  tableContainer.innerHTML = '';
  
  // If data is empty, show no data message and return
  if (data.length === 0) {
    const noDataDiv = document.createElement('div');
    noDataDiv.className = 'table-no-data';
    noDataDiv.innerHTML = `
      <i class="fas fa-table text-gray-400 text-4xl mb-3"></i>
      <p class="text-gray-600 text-lg">No voters and votes data available</p>
    `;
    tableContainer.appendChild(noDataDiv);
    return;
  }
  
  // Create table element
  const table = document.createElement('table');
  table.className = 'data-table';
  
  // Create table header
  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  
  if (currentState.breakdownType === 'course') {
    headerRow.innerHTML = `
      <th style="width: 40%">Course</th>
      <th style="width: 20%" class="text-center">Eligible</th>
      <th style="width: 20%" class="text-center">Voted</th>
      <th style="width: 20%" class="text-center">Turnout %</th>
    `;
  } else {
    headerRow.innerHTML = `
      <th style="width: 40%">Department</th>
      <th style="width: 20%" class="text-center">Eligible</th>
      <th style="width: 20%" class="text-center">Voted</th>
      <th style="width: 20%" class="text-center">Turnout %</th>
    `;
  }
  
  thead.appendChild(headerRow);
  table.appendChild(thead);
  
  // Create table body
  const tbody = document.createElement('tbody');
  
  data.forEach(item => {
    const row = document.createElement('tr');
    
    if (currentState.breakdownType === 'course') {
      // Show the full name for courses instead of abbreviation
      const fullName = convertToFullName(item.course);
      row.innerHTML = `
        <td style="width: 40%; white-space: normal; word-wrap: break-word;">${fullName}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.eligible_count)}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.voted_count)}</td>
        <td style="width: 20%" class="text-center">${createTurnoutBar(item.turnout_percentage)}</td>
      `;
    } else {
      // Show the full name for departments
      row.innerHTML = `
        <td style="width: 40%; white-space: normal; word-wrap: break-word;">${item.department1}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.eligible_count)}</td>
        <td style="width: 20%" class="text-center">${numberFormat(item.voted_count)}</td>
        <td style="width: 20%" class="text-center">${createTurnoutBar(item.turnout_percentage)}</td>
      `;
    }
    
    tbody.appendChild(row);
  });
  
  table.appendChild(tbody);
  tableContainer.appendChild(table);
}

function createTurnoutBar(percentage) {
  // Determine color based on percentage
  let barColor = 'turnout-low'; // Low turnout
  if (percentage >= 70) {
    barColor = 'turnout-high'; // High turnout
  } else if (percentage >= 40) {
    barColor = 'turnout-medium'; // Medium turnout
  }
  
  return `
    <div class="flex flex-col items-center">
      <div class="turnout-bar-container w-32">
        <div class="turnout-bar ${barColor}" style="width: ${percentage}%"></div>
      </div>
      <span class="text-sm font-bold text-gray-700 mt-1">${percentage}%</span>
    </div>
  `;
}

// JavaScript course normalization function
function normalizeCourseNameInJS(course) {
    if (!course) return 'UNSPECIFIED';
    
    course = course.toUpperCase().trim();
    
    // Check if it's already a standard code
    if (fullNameToAbbrMap[course]) {
        return course;
    }
    
    // Handle common patterns
    const patterns = [
        /^BACHELOR OF SCIENCE IN /i,
        /^BACHELOR OF ARTS IN /i,
        /^DOCTOR OF /i,
        /^MASTER OF /i,
        /^BS /i,
        /^BA /i
    ];
    
    let normalized = course;
    patterns.forEach(pattern => {
        normalized = normalized.replace(pattern, '');
    });
    
    // Handle specific abbreviations
    const abbrMap = {
        'BSCS': 'BS COMPUTER SCIENCE',
        'BSIT': 'BS INFORMATION TECHNOLOGY',
        'BSCPE': 'BS COMPUTER ENGINEERING',
        'BSECE': 'BS ELECTRONICS ENGINEERING',
        'BSCE': 'BS CIVIL ENGINEERING',
        'BSME': 'BS MECHANICAL ENGINEERING',
        'BSEE': 'BS ELECTRICAL ENGINEERING',
        'BSIE': 'BS INDUSTRIAL ENGINEERING',
        'BSAGRI': 'BS AGRICULTURE',
        'BSAB': 'BS AGRIBUSINESS',
        'BSES': 'BS ENVIRONMENTAL SCIENCE',
        'BSFT': 'BS FOOD TECHNOLOGY',
        'BSFOR': 'BS FORESTRY',
        'BSABE': 'BS AGRICULTURAL AND BIOSYSTEMS ENGINEERING',
        'BSBIO': 'BS BIOLOGY',
        'BSCHEM': 'BS CHEMISTRY',
        'BSMATH': 'BS MATHEMATICS',
        'BSPHYSICS': 'BS PHYSICS',
        'BSPSYCH': 'BS PSYCHOLOGY',
        'BSSTAT': 'BS STATISTICS',
        'DVM': 'DOCTOR OF VETERINARY MEDICINE',
        'BSPV': 'BS BIOLOGY (PRE-VETERINARY)',
        'BEED': 'BACHELOR OF ELEMENTARY EDUCATION',
        'BSED': 'BACHELOR OF SECONDARY EDUCATION',
        'BPE': 'BACHELOR OF PHYSICAL EDUCATION',
        'BTLE': 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION',
        'BSBA': 'BS BUSINESS ADMINISTRATION',
        'BSACC': 'BS ACCOUNTANCY',
        'BSECO': 'BS ECONOMICS',
        'BSENT': 'BS ENTREPRENEURSHIP',
        'BSOA': 'BS OFFICE ADMINISTRATION',
        'BSESS': 'BS EXERCISE AND SPORTS SCIENCES',
        'BSCRIM': 'BS CRIMINOLOGY',
        'BSN': 'BS NURSING',
        'BSHM': 'BS HOSPITALITY MANAGEMENT',
        'BSTM': 'BS TOURISM MANAGEMENT',
        'BLIS': 'BACHELOR OF LIBRARY AND INFORMATION SCIENCE'
    };
    
    // Check if normalized matches an abbreviation
    if (abbrMap[normalized]) {
        return abbrMap[normalized];
    }
    
    // Handle keyword matches
    const keywords = [
        'COMPUTER SCIENCE',
        'INFORMATION TECHNOLOGY',
        'COMPUTER ENGINEERING',
        'ELECTRONICS ENGINEERING',
        'CIVIL ENGINEERING',
        'MECHANICAL ENGINEERING',
        'ELECTRICAL ENGINEERING',
        'INDUSTRIAL ENGINEERING',
        'AGRICULTURE',
        'AGRIBUSINESS',
        'ENVIRONMENTAL SCIENCE',
        'FOOD TECHNOLOGY',
        'FORESTRY',
        'AGRICULTURAL AND BIOSYSTEMS ENGINEERING',
        'AGRICULTURAL ENTREPRENEURSHIP',
        'LAND USE DESIGN AND MANAGEMENT',
        'BIOLOGY',
        'CHEMISTRY',
        'MATHEMATICS',
        'PHYSICS',
        'PSYCHOLOGY',
        'ENGLISH LANGUAGE STUDIES',
        'COMMUNICATION',
        'STATISTICS',
        'VETERINARY MEDICINE',
        'PRE-VETERINARY',
        'ELEMENTARY EDUCATION',
        'SECONDARY EDUCATION',
        'PHYSICAL EDUCATION',
        'TECHNOLOGY AND LIVELIHOOD EDUCATION',
        'BUSINESS ADMINISTRATION',
        'ACCOUNTANCY',
        'ECONOMICS',
        'ENTREPRENEURSHIP',
        'OFFICE ADMINISTRATION',
        'EXERCISE AND SPORTS SCIENCES',
        'CRIMINOLOGY',
        'NURSING',
        'HOSPITALITY MANAGEMENT',
        'TOURISM MANAGEMENT',
        'LIBRARY AND INFORMATION SCIENCE'
    ];
    
    for (const keyword of keywords) {
        if (course.includes(keyword)) {
            // Convert keyword to standard format
            if (keyword.includes('COMPUTER SCIENCE')) return 'BS COMPUTER SCIENCE';
            if (keyword.includes('INFORMATION TECHNOLOGY')) return 'BS INFORMATION TECHNOLOGY';
            if (keyword.includes('COMPUTER ENGINEERING')) return 'BS COMPUTER ENGINEERING';
            if (keyword.includes('ELECTRONICS ENGINEERING')) return 'BS ELECTRONICS ENGINEERING';
            if (keyword.includes('CIVIL ENGINEERING')) return 'BS CIVIL ENGINEERING';
            if (keyword.includes('MECHANICAL ENGINEERING')) return 'BS MECHANICAL ENGINEERING';
            if (keyword.includes('ELECTRICAL ENGINEERING')) return 'BS ELECTRICAL ENGINEERING';
            if (keyword.includes('INDUSTRIAL ENGINEERING')) return 'BS INDUSTRIAL ENGINEERING';
            if (keyword.includes('AGRICULTURE')) return 'BS AGRICULTURE';
            if (keyword.includes('AGRIBUSINESS')) return 'BS AGRIBUSINESS';
            if (keyword.includes('ENVIRONMENTAL SCIENCE')) return 'BS ENVIRONMENTAL SCIENCE';
            if (keyword.includes('FOOD TECHNOLOGY')) return 'BS FOOD TECHNOLOGY';
            if (keyword.includes('FORESTRY')) return 'BS FORESTRY';
            if (keyword.includes('AGRICULTURAL AND BIOSYSTEMS ENGINEERING')) return 'BS AGRICULTURAL AND BIOSYSTEMS ENGINEERING';
            if (keyword.includes('AGRICULTURAL ENTREPRENEURSHIP')) return 'BACHELOR OF AGRICULTURAL ENTREPRENEURSHIP';
            if (keyword.includes('LAND USE DESIGN AND MANAGEMENT')) return 'BS LAND USE DESIGN AND MANAGEMENT';
            if (keyword.includes('BIOLOGY')) return 'BS BIOLOGY';
            if (keyword.includes('CHEMISTRY')) return 'BS CHEMISTRY';
            if (keyword.includes('MATHEMATICS')) return 'BS MATHEMATICS';
            if (keyword.includes('PHYSICS')) return 'BS PHYSICS';
            if (keyword.includes('PSYCHOLOGY')) return 'BS PSYCHOLOGY';
            if (keyword.includes('ENGLISH LANGUAGE STUDIES')) return 'BA ENGLISH LANGUAGE STUDIES';
            if (keyword.includes('COMMUNICATION')) return 'BA COMMUNICATION';
            if (keyword.includes('STATISTICS')) return 'BS STATISTICS';
            if (keyword.includes('VETERINARY MEDICINE')) return 'DOCTOR OF VETERINARY MEDICINE';
            if (keyword.includes('PRE-VETERINARY')) return 'BS BIOLOGY (PRE-VETERINARY)';
            if (keyword.includes('ELEMENTARY EDUCATION')) return 'BACHELOR OF ELEMENTARY EDUCATION';
            if (keyword.includes('SECONDARY EDUCATION')) return 'BACHELOR OF SECONDARY EDUCATION';
            if (keyword.includes('PHYSICAL EDUCATION')) return 'BACHELOR OF PHYSICAL EDUCATION';
            if (keyword.includes('TECHNOLOGY AND LIVELIHOOD EDUCATION')) return 'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION';
            if (keyword.includes('BUSINESS ADMINISTRATION')) return 'BS BUSINESS ADMINISTRATION';
            if (keyword.includes('ACCOUNTANCY')) return 'BS ACCOUNTANCY';
            if (keyword.includes('ECONOMICS')) return 'BS ECONOMICS';
            if (keyword.includes('ENTREPRENEURSHIP')) return 'BS ENTREPRENEURSHIP';
            if (keyword.includes('OFFICE ADMINISTRATION')) return 'BS OFFICE ADMINISTRATION';
            if (keyword.includes('EXERCISE AND SPORTS SCIENCES')) return 'BS EXERCISE AND SPORTS SCIENCES';
            if (keyword.includes('CRIMINOLOGY')) return 'BS CRIMINOLOGY';
            if (keyword.includes('NURSING')) return 'BS NURSING';
            if (keyword.includes('HOSPITALITY MANAGEMENT')) return 'BS HOSPITALITY MANAGEMENT';
            if (keyword.includes('TOURISM MANAGEMENT')) return 'BS TOURISM MANAGEMENT';
            if (keyword.includes('LIBRARY AND INFORMATION SCIENCE')) return 'BACHELOR OF LIBRARY AND INFORMATION SCIENCE';
        }
    }
    
    // Return original if no match found
    return course;
}

// Function to convert abbreviations to full names
function convertToFullName(abbreviation) {
    // Complete mapping of abbreviations to full names
    const fullNameMap = {
        // Colleges
        'CEIT': 'College of Engineering and Information Technology',
        'CAS': 'College of Arts and Sciences',
        'CAFENR': 'College of Agriculture, Food, Environment and Natural Resources',
        'CCJ': 'College of Criminal Justice',
        'CEMDS': 'College of Economics, Management and Development Studies',
        'CED': 'College of Education',
        'CON': 'College of Nursing',
        'COM': 'College of Medicine',
        'CSPEAR': 'College of Sports, Physical Education and Recreation',
        'CVMBS': 'College of Veterinary Medicine and Biomedical Sciences',
        'GS-OLC': 'Graduate School and Open Learning College',
        
        // Departments under CEIT
        'DCE': 'Department of Civil Engineering',
        'DCEE': 'Department of Computer and Electronics Engineering',
        'DIET': 'Department of Industrial Engineering and Technology',
        'DMEE': 'Department of Mechanical and Electronics Engineering',
        'DIT': 'Department of Information Technology',
        
        // Departments under CAS
        'DBS': 'Department of Biological Sciences',
        'DPS': 'Department of Physical Sciences',
        'DLMC': 'Department of Languages and Mass Communication',
        'DSS': 'Department of Social Sciences',
        'DMS': 'Department of Mathematics and Statistics',
        
        // Departments under CAFENR
        'DAS': 'Department of Animal Science',
        'DCS': 'Department of Crop Science',
        'DFST': 'Department of Food Science and Technology',
        'DFES': 'Department of Forestry and Environmental Science',
        'DAED': 'Department of Agricultural Economics and Development',
        
        // Departments under CCJ
        'DCJ': 'Department of Criminal Justice',
        
        // Departments under CEMDS
        'DE': 'Department of Economics',
        'DBM': 'Department of Business and Management',
        'DDS': 'Department of Development Studies',
        
        // Departments under CED
        'DSE': 'Department of Science Education',
        'DTLE': 'Department of Technology and Livelihood Education',
        'DCI': 'Department of Curriculum and Instruction',
        'DHK': 'Department of Human Kinetics',
        
        // Departments under CON
        'DN': 'Department of Nursing',
        
        // Departments under COM
        'DBMS': 'Department of Basic Medical Sciences',
        'DCS': 'Department of Clinical Sciences',
        
        // Departments under CSPEAR
        'DPER': 'Department of Physical Education and Recreation',
        
        // Departments under CVMBS
        'DVM': 'Department of Veterinary Medicine',
        'DBS': 'Department of Biomedical Sciences',
        
        // Departments under GS-OLC
        'DVGP': 'Department of Various Graduate Programs',
        
        // Courses
        'BS COMPUTER SCIENCE': 'Bachelor of Science in Computer Science',
        'BS INFORMATION TECHNOLOGY': 'Bachelor of Science in Information Technology',
        'BS COMPUTER ENGINEERING': 'Bachelor of Science in Computer Engineering',
        'BS ELECTRONICS ENGINEERING': 'Bachelor of Science in Electronics Engineering',
        'BS CIVIL ENGINEERING': 'Bachelor of Science in Civil Engineering',
        'BS MECHANICAL ENGINEERING': 'Bachelor of Science in Mechanical Engineering',
        'BS ELECTRICAL ENGINEERING': 'Bachelor of Science in Electrical Engineering',
        'BS INDUSTRIAL ENGINEERING': 'Bachelor of Science in Industrial Engineering',
        'BS AGRICULTURE': 'Bachelor of Science in Agriculture',
        'BS AGRIBUSINESS': 'Bachelor of Science in Agribusiness',
        'BS ENVIRONMENTAL SCIENCE': 'Bachelor of Science in Environmental Science',
        'BS FOOD TECHNOLOGY': 'Bachelor of Science in Food Technology',
        'BS FORESTRY': 'Bachelor of Science in Forestry',
        'BS AGRICULTURAL AND BIOSYSTEMS ENGINEERING': 'Bachelor of Science in Agricultural and Biosystems Engineering',
        'BACHELOR OF AGRICULTURAL ENTREPRENEURSHIP': 'Bachelor of Agricultural Entrepreneurship',
        'BS LAND USE DESIGN AND MANAGEMENT': 'Bachelor of Science in Land Use Design and Management',
        'BS BIOLOGY': 'Bachelor of Science in Biology',
        'BS CHEMISTRY': 'Bachelor of Science in Chemistry',
        'BS MATHEMATICS': 'Bachelor of Science in Mathematics',
        'BS PHYSICS': 'Bachelor of Science in Physics',
        'BS PSYCHOLOGY': 'Bachelor of Science in Psychology',
        'BA ENGLISH LANGUAGE STUDIES': 'Bachelor of Arts in English Language Studies',
        'BA COMMUNICATION': 'Bachelor of Arts in Communication',
        'BS STATISTICS': 'Bachelor of Science in Statistics',
        'DOCTOR OF VETERINARY MEDICINE': 'Doctor of Veterinary Medicine',
        'BS BIOLOGY (PRE-VETERINARY)': 'Bachelor of Science in Biology (Pre-Veterinary)',
        'BACHELOR OF ELEMENTARY EDUCATION': 'Bachelor of Elementary Education',
        'BACHELOR OF SECONDARY EDUCATION': 'Bachelor of Secondary Education',
        'BACHELOR OF PHYSICAL EDUCATION': 'Bachelor of Physical Education',
        'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION': 'Bachelor of Technology and Livelihood Education',
        'BS BUSINESS ADMINISTRATION': 'Bachelor of Science in Business Administration',
        'BS ACCOUNTANCY': 'Bachelor of Science in Accountancy',
        'BS ECONOMICS': 'Bachelor of Science in Economics',
        'BS ENTREPRENEURSHIP': 'Bachelor of Science in Entrepreneurship',
        'BS OFFICE ADMINISTRATION': 'Bachelor of Science in Office Administration',
        'BS EXERCISE AND SPORTS SCIENCES': 'Bachelor of Science in Exercise and Sports Sciences',
        'BS CRIMINOLOGY': 'Bachelor of Science in Criminology',
        'BS NURSING': 'Bachelor of Science in Nursing',
        'BS HOSPITALITY MANAGEMENT': 'Bachelor of Science in Hospitality Management',
        'BS TOURISM MANAGEMENT': 'Bachelor of Science in Tourism Management',
        'BACHELOR OF LIBRARY AND INFORMATION SCIENCE': 'Bachelor of Library and Information Science'
    };
    
    // Return the full name if found, otherwise return the original abbreviation
    return fullNameMap[abbreviation] || abbreviation;
}

function numberFormat(num) {
  return new Intl.NumberFormat('en-US').format(num);
}

function showLoading() {
  document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
  document.getElementById('loadingOverlay').classList.remove('active');
}

function showChartNoDataMessage(message = 'No data available for chart') {
  const canvas = document.getElementById('turnoutChart');
  const noDataDiv = document.getElementById('chartNoData');
  
  if (canvas) {
    canvas.style.display = 'none';
  }
  if (noDataDiv) {
    noDataDiv.querySelector('p').textContent = message;
    noDataDiv.style.display = 'flex';
  }
}
</script>
</body>
</html>