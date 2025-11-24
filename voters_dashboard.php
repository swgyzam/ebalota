<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Manila');

// === DB CONNECTION ===
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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Shared helpers for normalization & scopes
require_once __DIR__ . '/includes/election_scope_helpers.php'; // normalize_course_code
require_once __DIR__ . '/includes/analytics_scopes.php';       // getScopedVoters, scope constants

// ===== AUTH CHECK =====
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'voter') {
    header('Location: login.html');
    exit();
}

// ===== MAPPINGS (COLLEGES & COURSES) =====
$department_map = [
    // Colleges
    'cafenr' => 'CAFENR',
    'college of agriculture, food and natural resources' => 'CAFENR',
    'caf enr' => 'CAFENR',

    'cas'   => 'CAS',
    'college of arts and sciences' => 'CAS',

    'ceit'  => 'CEIT',
    'college of engineering and information technology' => 'CEIT',

    'cemds' => 'CEMDS',
    'college of economics, management and development studies' => 'CEMDS',

    'ced'   => 'CED',
    'college of education' => 'CED',

    'cspear'=> 'CSPEAR',
    'college of sports, physical education and recreation' => 'CSPEAR',

    'cthm'  => 'CTHM',
    'college of tourism and hospitality management' => 'CTHM',

    'cvmbs' => 'CVMBS',
    'college of veterinary medicine and biomedical sciences' => 'CVMBS',

    'com'   => 'COM',
    'college of medicine' => 'COM',

    'con'   => 'CON',
    'college of nursing' => 'CON',

    'ccj'   => 'CCJ',
    'college of criminal justice education' => 'CCJ',

    'gs-olc'=> 'GS-OLC',
    'graduate school - open learning college' => 'GS-OLC',

    // For non-academic departments, keep as-is (they're already codes)
    'naea'       => 'NAEA',
    'admin'      => 'ADMIN',
    'finance'    => 'FINANCE',
    'hr'         => 'HR',
    'it'         => 'IT',
    'maintenance'=> 'MAINTENANCE',
    'security'   => 'SECURITY',
    'library'    => 'LIBRARY',
    'naes'       => 'NAES',
    'naem'       => 'NAEM',
    'naeh'       => 'NAEH',
    'nae it'     => 'NAEIT',
];

// Faculty department full-name → code
$faculty_department_map = [
    // CAFENR
    'department of animal science'                          => 'DAS',
    'department of crop science'                            => 'DCS',
    'department of food science and technology'             => 'DFST',
    'department of forestry and environmental science'      => 'DFES',
    'department of agricultural economics and development'  => 'DAED',

    // CAS
    'department of biological sciences'                     => 'DBS',
    'department of physical sciences'                       => 'DPS',
    'department of languages and mass communication'        => 'DLMC',
    'department of social sciences'                         => 'DSS',
    'department of mathematics and statistics'              => 'DMS',

    // CCJ
    'department of criminal justice'                        => 'DCJ',

    // CEMDS
    'department of economics'                               => 'DE',
    'department of business and management'                 => 'DBM',
    'department of development studies'                     => 'DDS',

    // CED
    'department of science education'                       => 'DSE',
    'department of technology and livelihood education'     => 'DTLE',
    'department of curriculum and instruction'              => 'DCI',
    'department of human kinetics'                          => 'DHK',

    // CEIT
    'department of civil engineering'                       => 'DCE',
    'department of computer and electronics engineering'    => 'DCEE',
    'department of industrial engineering and technology'   => 'DIET',
    'department of mechanical and electronics engineering'  => 'DMEE',
    'department of information technology'                  => 'DIT',

    // CON
    'department of nursing'                                => 'DN',

    // COM
    'department of basic medical sciences'                 => 'DBMS',
    'department of clinical sciences'                      => 'DCS',

    // CSPEAR
    'department of physical education and recreation'      => 'DPER',

    // CVMBS
    'department of veterinary medicine'                    => 'DVM',
    'department of biomedical sciences'                    => 'DBS',

    // GS-OLC
    'department of various graduate programs'              => 'DVGP',
];

// Non-academic department full-name → code
$nonacad_department_map = [
    'administration'                 => 'ADMIN',
    'admin'                          => 'ADMIN',
    'administrative'                 => 'ADMIN',

    'finance'                        => 'FINANCE',
    'financial'                      => 'FINANCE',

    'human resources'                => 'HR',
    'human resource'                 => 'HR',
    'hr'                             => 'HR',

    'information technology'         => 'IT',
    'it'                             => 'IT',
    'information tech'               => 'IT',
    'tech support'                   => 'IT',

    'maintenance'                    => 'MAINTENANCE',
    'maint'                          => 'MAINTENANCE',

    'security'                       => 'SECURITY',
    'guard'                          => 'SECURITY',
    'security guard'                 => 'SECURITY',

    'library'                        => 'LIBRARY',
    'lib'                            => 'LIBRARY',

    'non-academic employees association' => 'NAEA',
    'naea'                               => 'NAEA',

    'non-academic employee services'     => 'NAES',
    'naes'                               => 'NAES',

    'non-academic employee management'   => 'NAEM',
    'naem'                               => 'NAEM',

    'non-academic employee health'       => 'NAEH',
    'naeh'                               => 'NAEH',

    'non-academic employee it'           => 'NAEIT',
    'nae it'                             => 'NAEIT',
];

// Course map for election visibility (kept for filtering which elections appear)
$course_map = [
  // === CEIT ===
  'bs computer science'               => 'BSCS',
  'bs cs'                             => 'BSCS',
  'bachelor of science in computer science' => 'BSCS',
  'bscs'                              => 'BSCS',

  'bs information technology'         => 'BSIT',
  'bs it'                             => 'BSIT',
  'bachelor of science in information technology' => 'BSIT',
  'bsit'                              => 'BSIT',

  'bs computer engineering'           => 'BSCpE',
  'bachelor of science in computer engineering' => 'BSCpE',
  'bscpe'                             => 'BSCpE',

  'bs electronics engineering'        => 'BSECE',
  'bachelor of science in electronics engineering' => 'BSECE',
  'bsece'                             => 'BSECE',

  'bs civil engineering'              => 'BSCE',
  'bachelor of science in civil engineering' => 'BSCE',
  'bsce'                              => 'BSCE',

  'bs mechanical engineering'         => 'BSME',
  'bachelor of science in mechanical engineering' => 'BSME',
  'bsme'                              => 'BSME',

  'bs electrical engineering'         => 'BSEE',
  'bachelor of science in electrical engineering' => 'BSEE',
  'bsee'                              => 'BSEE',

  'bs industrial engineering'         => 'BSIE',
  'bachelor of science in industrial engineering' => 'BSIE',
  'bsie'                              => 'BSIE',

  'bs architecture'                   => 'BSArch',
  'bachelor of science in architecture' => 'BSArch',
  'bsarch'                            => 'BSArch',


  // === CAS ===
  'bs biology'                        => 'BSBio',
  'bachelor of science in biology'    => 'BSBio',
  'bsbio'                             => 'BSBio',

  'bs chemistry'                      => 'BSChem',
  'bachelor of science in chemistry'  => 'BSChem',
  'bschem'                            => 'BSChem',

  'bs mathematics'                    => 'BSMath',
  'bachelor of science in mathematics'=> 'BSMath',
  'bsmath'                            => 'BSMath',

  'bs physics'                        => 'BSPhysics',
  'bachelor of science in physics'    => 'BSPhysics',
  'bsphysics'                         => 'BSPhysics',

  'bs psychology'                     => 'BSPsych',
  'bachelor of science in psychology' => 'BSPsych',
  'bspsych'                           => 'BSPsych',

  'ba english language studies'       => 'BAELS',
  'bachelor of arts in english language studies' => 'BAELS',
  'baels'                             => 'BAELS',

  'ba communication'                  => 'BAComm',
  'bachelor of arts in communication' => 'BAComm',
  'bacomm'                            => 'BAComm',

  'bs statistics'                     => 'BSStat',
  'bachelor of science in statistics' => 'BSStat',
  'bsstat'                            => 'BSStat',


  // === CAFENR ===
  'bs agriculture'                    => 'BSAgri',
  'bachelor of science in agriculture'=> 'BSAgri',
  'bsagri'                            => 'BSAgri',

  'bs agribusiness'                   => 'BSAB',
  'bachelor of science in agribusiness' => 'BSAB',
  'bsab'                              => 'BSAB',

  'bs environmental science'          => 'BSES',
  'bachelor of science in environmental science' => 'BSES',
  'bses'                              => 'BSES',

  'bs food technology'                => 'BSFT',
  'bachelor of science in food technology' => 'BSFT',
  'bsft'                              => 'BSFT',

  'bs forestry'                       => 'BSFor',
  'bachelor of science in forestry'   => 'BSFor',
  'bsfor'                             => 'BSFor',

  'bs agricultural and biosystems engineering' => 'BSABE',
  'bachelor of science in agricultural and biosystems engineering' => 'BSABE',
  'bsabe'                             => 'BSABE',

  'bachelor of agricultural entrepreneurship' => 'BAE',
  'bachelor of agricultural engineering'      => 'BAE',
  'bae'                               => 'BAE',

  'bs land use design and management' => 'BSLDM',
  'bachelor of science in land use design and management' => 'BSLDM',
  'bsldm'                             => 'BSLDM',


  // === CVMBS ===
  'doctor of veterinary medicine'     => 'DVM',
  'dvm'                               => 'DVM',

  'bs pre-veterinary medicine'        => 'BSPV',
  'bachelor of science in pre-veterinary medicine' => 'BSPV',
  'bspv'                              => 'BSPV',


  // === CED ===
  'bachelor of elementary education'  => 'BEEd',
  'beed'                              => 'BEEd',

  'bachelor of secondary education'   => 'BSEd',
  'bsed'                              => 'BSEd',

  'bachelor of physical education'    => 'BPE',
  'bpe'                               => 'BPE',

  'bachelor of technology and livelihood education' => 'BTLE',
  'btle'                              => 'BTLE',


  // === CEMDS ===
  'bs business administration'        => 'BSBA',
  'bachelor of science in business administration' => 'BSBA',
  'bsba'                              => 'BSBA',

  'bs accountancy'                    => 'BSAcc',
  'bachelor of science in accountancy'=> 'BSAcc',
  'bsacc'                             => 'BSAcc',

  'bs economics'                      => 'BSEco',
  'bachelor of science in economics'  => 'BSEco',
  'bseco'                             => 'BSEco',

  'bs entrepreneurship'               => 'BSEnt',
  'bachelor of science in entrepreneurship' => 'BSEnt',
  'bsent'                             => 'BSEnt',

  'bs office administration'          => 'BSOA',
  'bachelor of science in office administration' => 'BSOA',
  'bsoa'                              => 'BSOA',


  // === CSPEAR ===
  'bachelor of physical education'    => 'BPE',    
  'bpe'                               => 'BPE',

  'bs exercise and sports sciences'   => 'BSESS',
  'bachelor of science in exercise and sports sciences' => 'BSESS',
  'bsess'                             => 'BSESS',


  // === CCJ ===
  'bs criminology'                    => 'BSCrim',
  'bachelor of science in criminology'=> 'BSCrim',
  'bscrim'                            => 'BSCrim',


  // === CON ===
  'bs nursing'                        => 'BSN',
  'bachelor of science in nursing'    => 'BSN',
  'bsn'                               => 'BSN',


  // === CTHM ===
  'bs hospitality management'         => 'BSHM',
  'bachelor of science in hospitality management' => 'BSHM',
  'bshm'                              => 'BSHM',

  'bs tourism management'             => 'BSTM',
  'bachelor of science in tourism management' => 'BSTM',
  'bstm'                              => 'BSTM',


  // === COM ===
  'bachelor of library and information science' => 'BLIS',
  'blis'                              => 'BLIS',


  // === GS-OLC ===
  'doctor of philosophy'              => 'PhD',
  'phd'                               => 'PhD',

  'master of science'                 => 'MS',
  'ms'                                => 'MS',

  'master of arts'                    => 'MA',
  'ma'                                => 'MA',
];

// ===== LOAD CURRENT VOTER =====
$user_id = (int)$_SESSION['user_id'];

$userSql = "
   SELECT 
       position,
       department,
       course,
       status,
       migs_status,
       force_password_change,
       is_coop_member,
       owner_scope_id,
       is_other_member,
       department1
   FROM users
   WHERE user_id = :uid
   LIMIT 1
";
$stmtUser = $pdo->prepare($userSql);
$stmtUser->execute([':uid' => $user_id]);
$user = $stmtUser->fetch();

if (!$user) {
    die('User not found.');
}

$voter_position      = $user['position'] ?? '';
$voter_department    = $user['department'] ?? '';
$voter_course        = $user['course'] ?? '';
$voter_status        = $user['status'] ?? '';
$voter_migs_status    = (int)($user['migs_status'] ?? 0);
$force_password_flag  = (int)($user['force_password_change'] ?? 0);
$raw_is_coop_member   = (int)($user['is_coop_member'] ?? 0);
$is_coop_member       = ($raw_is_coop_member === 1 && $voter_migs_status === 1);
$voter_owner_scope_id = (int)($user['owner_scope_id'] ?? 0);
$voter_is_other_mem   = (int)($user['is_other_member'] ?? 0);
$voter_department1   = $user['department1'] ?? '';

// Normalize college / department & course
$voter_department_lower = strtolower(trim($voter_department));
if (isset($department_map[$voter_department_lower])) {
    $voter_college_normalized = $department_map[$voter_department_lower];
} elseif (isset($department_map[strtoupper($voter_department_lower)])) {
    $voter_college_normalized = $department_map[strtoupper($voter_department_lower)];
} else {
    $voter_college_normalized = strtoupper($voter_department_lower);
}

// Normalize course using course_map for visibility logic
$voter_course_raw   = $voter_course ?? '';
$voter_course_lower = strtolower(trim($voter_course_raw));

if (isset($course_map[$voter_course_lower])) {
    $voter_course_normalized = $course_map[$voter_course_lower];
} else {
    $key_spaces = preg_replace('/\s+/', ' ', $voter_course_lower);
    if (isset($course_map[$key_spaces])) {
        $voter_course_normalized = $course_map[$key_spaces];
    } else {
        $key_letters = preg_replace('/[^a-z]/', '', $voter_course_lower);
        if (isset($course_map[$key_letters])) {
            $voter_course_normalized = $course_map[$key_letters];
        } else {
            $voter_course_normalized = strtoupper($voter_course_lower);
        }
    }
}

$voter_status_normalized = strtoupper(trim($voter_status));

// ===== DEBUG: Log voter info =====
error_log("Voter Info:");
error_log("Position: " . $voter_position);
error_log("Department: " . $voter_department);
error_log("Department1: " . ($voter_department1 ?? 'NULL'));
error_log("Status: " . $voter_status);
error_log("Normalized College: " . $voter_college_normalized);
error_log("Normalized Course: " . $voter_course_normalized);
error_log("Normalized Status: " . $voter_status_normalized);
error_log("Is Coop Member: " . ($is_coop_member ? 'Yes' : 'No'));
error_log("Owner Scope ID: " . $voter_owner_scope_id);
error_log("Is Other Member: " . ($voter_is_other_mem ? 'Yes' : 'No'));

// ===== LOAD ALL ELECTIONS (we will filter in PHP) =====
$sql = "SELECT * FROM elections ORDER BY election_id DESC";
$stmt = $pdo->query($sql);
$all_elections = $stmt->fetchAll();

// Mapper: users.position → elections.target_position
function mapUserPositionToElection($position) {
    $pos = strtolower(trim($position));
    if ($pos === 'non-academic') {
        return 'non-academic';
    } elseif ($pos === 'academic') {
        return 'faculty'; // academic (users) = faculty (elections)
    } elseif ($pos === 'student') {
        return 'student';
    } elseif ($pos === 'coop') {
        return 'coop';
    } else {
        return 'all';
    }
}

$voter = [
    'position'        => $voter_position,
    'department'      => $voter_department,
    'status'          => $voter_status,
    'is_coop_member'  => $is_coop_member,
    'owner_scope_id'  => $voter_owner_scope_id,
    'is_other_member' => $voter_is_other_mem,
];

$filtered_elections = [];
$now = date('Y-m-d H:i:s'); // current date/time

$mappedPosition = mapUserPositionToElection($voter['position']);

// ===== MAIN ELECTION FILTERING LOOP =====
foreach ($all_elections as $election) {
    // Only show elections that are launched to voters
    if (($election['creation_stage'] ?? '') !== 'ready_for_voters') {
        continue;
    }

    $electionTargetPos   = strtolower($election['target_position'] ?? 'all');
    $electionScopeType   = $election['election_scope_type'] ?? null;
    $electionOwnerScope  = isset($election['owner_scope_id']) ? (int)$election['owner_scope_id'] : 0;
    $isCoopElection      = ($electionTargetPos === 'coop');
    $isOthersElection    = ($electionTargetPos === 'others');
    $isNonAcadElection   = ($electionTargetPos === 'non-academic');

    // ---- SCOPE-BASED VISIBILITY FOR ORG ADMINS ----
    if (in_array($electionScopeType, ['Non-Academic-Student', 'Others-Default'], true)) {
        if ($electionOwnerScope > 0 && $voter['owner_scope_id'] !== $electionOwnerScope) {
            continue;
        }
    }

    // ---- COOP elections: MIGS only (global for now) ----
    if ($isCoopElection) {
        if ($voter['is_coop_member']) {
            $filtered_elections[] = $election;
        }
        continue;
    }

    // ---- Position-level filtering ----
    if ($isOthersElection) {
        // "others" = employees: academic + non-academic
        if (!in_array($mappedPosition, ['faculty', 'non-academic'], true)) {
            continue;
        }
    } else {
        // Normal behaviour: student/faculty/non-acad
        if ($electionTargetPos !== 'all' && $electionTargetPos !== $mappedPosition) {
            continue;
        }
    }

    // ---- Parse allowed filters from election row ----
    $allowed_colleges    = array_filter(array_map('strtoupper', array_map('trim', explode(',', (string)($election['allowed_colleges'] ?? '')))));
    $allowed_departments = array_filter(array_map('strtoupper', array_map('trim', explode(',', (string)($election['allowed_departments'] ?? '')))));
    $allowed_courses     = array_filter(array_map('strtoupper', array_map('trim', explode(',', (string)($election['allowed_courses'] ?? '')))));
    $allowed_status      = array_filter(array_map('strtoupper', array_map('trim', explode(',', (string)($election['allowed_status'] ?? '')))));

    // ===== DEBUG: Log election details =====
    error_log("Election: " . $election['title'] . " (ID: " . $election['election_id'] . ")");
    error_log("Target Position: " . $electionTargetPos);
    error_log("Allowed Colleges: " . $election['allowed_colleges']);
    error_log("Allowed Departments: " . $election['allowed_departments']);
    error_log("Allowed Courses: " . $election['allowed_courses']);
    error_log("Allowed Status: " . $election['allowed_status']);
    error_log("Election Scope Type: " . $electionScopeType);
    error_log("Owner Scope ID: " . $electionOwnerScope);

    // ---- Non-academic & "others" elections: departments + status ----
    if ($isNonAcadElection || $isOthersElection) {
        $deptMatch   = false;
        $statusMatch = false;

        if (empty($allowed_departments) || in_array('ALL', $allowed_departments, true)) {
            $deptMatch = true;
        } else {
            $rawDept   = $voter['department'] ?: $voter_college_normalized;
            $deptKey   = strtolower(trim($rawDept));

            if (isset($nonacad_department_map[$deptKey])) {
                $voterDeptUpper = $nonacad_department_map[$deptKey];
            } else {
                $voterDeptUpper = strtoupper($rawDept);
            }

            if (in_array($voterDeptUpper, $allowed_departments, true)) {
                $deptMatch = true;
            }
        }

        if (!$deptMatch) {
            continue;
        }

        if (empty($allowed_status) || in_array('ALL', $allowed_status, true)) {
            $statusMatch = true;
        } else {
            $voterStatusUpper = strtoupper($voter_status_normalized);
            if (in_array($voterStatusUpper, $allowed_status, true)) {
                $statusMatch = true;
            }
        }

        if ($statusMatch) {
            $filtered_elections[] = $election;
        }

        continue;
    }

    // ---- Student / Faculty elections: colleges + courses + status ----
    $collegeAllowed = empty($allowed_colleges)
        || in_array('ALL', $allowed_colleges, true)
        || in_array(strtoupper($voter_college_normalized), $allowed_colleges, true);

    if ($electionTargetPos === 'faculty') {
        $courseAllowed = empty($allowed_courses) || in_array('ALL', $allowed_courses, true);
    } else {
        $courseAllowed = empty($allowed_courses)
            || in_array('ALL', $allowed_courses, true)
            || in_array(strtoupper($voter_course_normalized), $allowed_courses, true);
    }

    $statusAllowed = empty($allowed_status)
        || in_array('ALL', $allowed_status, true)
        || in_array(strtoupper($voter_status_normalized), $allowed_status, true);

    if ($electionTargetPos === 'faculty') {
        if (!empty($allowed_departments) && !in_array('ALL', $allowed_departments, true)) {
            $voterDept1     = trim($voter_department1 ?? '');
            $voterDeptUpper = 'GENERAL';

            if ($voterDept1 !== '') {
                $deptKey        = strtolower($voterDept1);
                $voterDeptUpper = $faculty_department_map[$deptKey] ?? $voterDeptUpper;
            }

            if (!in_array($voterDeptUpper, $allowed_departments, true)) {
                $collegeAllowed = false;
            }
        }
    }

    if ($collegeAllowed && $courseAllowed && $statusAllowed) {
        $filtered_elections[] = $election;
        error_log("Election ADDED to filtered list: " . $election['title']);
    } else {
        error_log("Election NOT ADDED - Failed conditions: " . $election['title']);
    }
}

// ===== DONE FILTERING, PASS TO VIEW =====
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
          
          <div id="notificationContainer" class="space-y-3">
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
            <?= htmlspecialchars($voter_college_normalized) ?> - <?= htmlspecialchars($voter_course_normalized) ?>
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
          $nowDT = new DateTime();
          $nowString = $nowDT->format('Y-m-d H:i:s');
          
          foreach ($filtered_elections as $election): 
          ?>
            <?php
              $start = $election['start_datetime'];
              $end   = $election['end_datetime'];
              
              $startDateTime = ($start instanceof DateTime) ? $start : new DateTime($start);
              $endDateTime   = ($end   instanceof DateTime) ? $end   : new DateTime($end);
              
              if ($nowDT < $startDateTime) {
                  $status = 'upcoming';
              } elseif ($nowDT >= $startDateTime && $nowDT <= $endDateTime) {
                  $status = 'ongoing';
              } else {
                  $status = 'completed';
              }
              
              error_log("Election ID: {$election['election_id']}, Title: {$election['title']}, Start: {$startDateTime->format('Y-m-d H:i:s')}, End: {$endDateTime->format('Y-m-d H:i:s')}, Now: {$nowString}, Status: $status");
              
              // Check if user has voted
              $stmt = $pdo->prepare("SELECT * FROM votes WHERE voter_id = ? AND election_id = ?");
              $stmt->execute([$_SESSION['user_id'], $election['election_id']]);
              $hasVoted = $stmt->fetch();
              
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
              
              // ===== VOTER TURNOUT CALCULATION (seat-based via analytics_scopes) =====
              $targetPosTurnout = strtolower($election['target_position'] ?? 'all');
              $scopeTypeTurnout = $election['election_scope_type'] ?? null;
              $scopeIdTurnout   = isset($election['owner_scope_id']) ? (int)$election['owner_scope_id'] : null;

              $allowed_colleges    = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_colleges']    ?? ''))));
              $allowed_courses_raw = array_filter(array_map('trim',       explode(',', $election['allowed_courses']     ?? '')));
              $allowed_status      = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_status']      ?? ''))));
              $allowed_departments = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_departments'] ?? ''))));

              $restrictStatus = !empty($allowed_status) && !in_array('ALL', $allowed_status, true);

              // array filters
              $filterByStatus = function(array $rows) use ($allowed_status, $restrictStatus): array {
                  if (!$restrictStatus) return $rows;
                  $set = array_flip($allowed_status);
                  $out = [];
                  foreach ($rows as $r) {
                      $s = strtoupper(trim($r['status'] ?? ''));
                      if (isset($set[$s])) $out[] = $r;
                  }
                  return $out;
              };

              $filterByColleges = function(array $rows) use ($allowed_colleges): array {
                  if (empty($allowed_colleges) || in_array('ALL', $allowed_colleges, true)) {
                      return $rows;
                  }
                  $set = array_flip($allowed_colleges);
                  $out = [];
                  foreach ($rows as $r) {
                      $c = strtoupper(trim($r['department'] ?? ''));
                      if (isset($set[$c])) $out[] = $r;
                  }
                  return $out;
              };

              $filterByDepartmentsCodes = function(array $rows, array $allowed_codes): array {
                  if (empty($allowed_codes) || in_array('ALL', $allowed_codes, true)) {
                      return $rows;
                  }
                  $set = array_flip($allowed_codes);
                  $out = [];
                  foreach ($rows as $r) {
                      $d = strtoupper(trim($r['department'] ?? ''));
                      if (isset($set[$d])) $out[] = $r;
                  }
                  return $out;
              };

              $filterFacultyByDeptCodes = function(array $rows, array $allowed_codes, array $faculty_department_map) : array {
                  if (empty($allowed_codes) || in_array('ALL', $allowed_codes, true)) {
                      return $rows;
                  }
                  $set = array_flip($allowed_codes);
                  $out = [];
                  foreach ($rows as $r) {
                      $full = strtolower(trim($r['department1'] ?? ''));
                      $code = isset($faculty_department_map[$full]) ? strtoupper($faculty_department_map[$full]) : null;
                      if ($code !== null && isset($set[$code])) {
                          $out[] = $r;
                      }
                  }
                  return $out;
              };

              $filterByCoursesNorm = function(array $rows, array $allowed_courses_raw): array {
                  if (empty($allowed_courses_raw)) return $rows;
                  $allowedCodes = [];
                  foreach ($allowed_courses_raw as $c) {
                      if (strcasecmp($c, 'ALL') === 0) return $rows;
                      $code = normalize_course_code($c);
                      if ($code !== '' && $code !== 'UNSPECIFIED') {
                          $allowedCodes[] = strtoupper($code);
                      }
                  }
                  $allowedCodes = array_unique($allowedCodes);
                  if (empty($allowedCodes)) return $rows;
                  $set = array_flip($allowedCodes);
                  $out = [];
                  foreach ($rows as $r) {
                      $stuCode = strtoupper(normalize_course_code($r['course'] ?? ''));
                      if (isset($set[$stuCode])) $out[] = $r;
                  }
                  return $out;
              };

              // compute eligible voters
              $totalVoters = null;

              if ($targetPosTurnout === 'coop') {
                  $sqlTotal = "
                      SELECT COUNT(*) AS total
                      FROM users
                      WHERE role = 'voter'
                        AND is_coop_member = 1
                        AND migs_status = 1
                  ";
                  $stmtTotal = $pdo->query($sqlTotal);
                  $totalVoters = (int)($stmtTotal->fetch()['total'] ?? 0);
              } else {
                  $seatTypes = [
                      SCOPE_ACAD_STUDENT,
                      SCOPE_ACAD_FACULTY,
                      SCOPE_NONACAD_STUDENT,
                      SCOPE_NONACAD_EMPLOYEE,
                      SCOPE_OTHERS_DEFAULT,
                      SCOPE_SPECIAL_CSG,
                  ];

                  if ($scopeTypeTurnout && in_array($scopeTypeTurnout, $seatTypes, true)) {
                      $scopeIdArg = ($scopeTypeTurnout === SCOPE_SPECIAL_CSG) ? null : $scopeIdTurnout;
                      $yearEnd    = $election['end_datetime'] ?? null;

                      $scoped = getScopedVoters(
                          $pdo,
                          $scopeTypeTurnout,
                          $scopeIdArg,
                          [
                              'year_end'      => $yearEnd,
                              'include_flags' => true,
                          ]
                      );

                      $eligible = $scoped;
                      $eligible = $filterByStatus($eligible);

                      if (in_array($scopeTypeTurnout, [SCOPE_ACAD_STUDENT, SCOPE_ACAD_FACULTY], true)) {
                          $eligible = $filterByColleges($eligible);
                      }

                      if ($scopeTypeTurnout === SCOPE_NONACAD_EMPLOYEE) {
                          $eligible = $filterByDepartmentsCodes($eligible, $allowed_departments);
                      }

                      if ($scopeTypeTurnout === SCOPE_ACAD_FACULTY) {
                          $eligible = $filterFacultyByDeptCodes($eligible, $allowed_departments, $faculty_department_map);
                      }

                      if (in_array($scopeTypeTurnout, [SCOPE_ACAD_STUDENT, SCOPE_NONACAD_STUDENT, SCOPE_SPECIAL_CSG], true)) {
                          $eligible = $filterByCoursesNorm($eligible, $allowed_courses_raw);
                      }

                      $totalVoters = count($eligible);
                  }
              }

              // Fallback generic count if seat logic not applicable
              if ($totalVoters === null) {
                  $conditions = ["role = 'voter'"];
                  $params     = [];

                  if ($targetPosTurnout === 'coop') {
                      $conditions[] = "is_coop_member = 1";
                      $conditions[] = "migs_status = 1";
                  } else {
                      if ($targetPosTurnout !== 'all') {
                          if ($targetPosTurnout === 'faculty') {
                              $conditions[] = "position = 'academic'";
                          } elseif ($targetPosTurnout === 'non-academic') {
                              $conditions[] = "position = 'non-academic'";
                          } elseif ($targetPosTurnout === 'others') {
                              $conditions[] = "(position = 'academic' OR position = 'non-academic')";
                          } else {
                              $conditions[] = "position = ?";
                              $params[]     = $targetPosTurnout;
                          }
                      }

                      if (!empty($allowed_colleges) && !in_array('ALL', $allowed_colleges, true)) {
                          $placeholders = implode(',', array_fill(0, count($allowed_colleges), '?'));
                          $conditions[] = "UPPER(department) IN ($placeholders)";
                          $params       = array_merge($params, $allowed_colleges);
                      }

                      if (!empty($allowed_departments) && !in_array('ALL', $allowed_departments, true)
                          && in_array($targetPosTurnout, ['non-academic','others'], true)
                      ) {
                          $placeholders = implode(',', array_fill(0, count($allowed_departments), '?'));
                          $conditions[] = "UPPER(department) IN ($placeholders)";
                          $params       = array_merge($params, $allowed_departments);
                      }

                      if ($restrictStatus) {
                          $placeholders = implode(',', array_fill(0, count($allowed_status), '?'));
                          $conditions[] = "UPPER(status) IN ($placeholders)";
                          $params       = array_merge($params, $allowed_status);
                      }
                  }

                  $sqlTotal = "SELECT COUNT(*) as total FROM users WHERE " . implode(' AND ', $conditions);
                  $stmtTotal = $pdo->prepare($sqlTotal);
                  $stmtTotal->execute($params);
                  $totalVoters = (int)($stmtTotal->fetch()['total'] ?? 0);
              }

              // Get total votes cast (distinct voters)
              $stmtVotes = $pdo->prepare("SELECT COUNT(DISTINCT voter_id) as voted FROM votes WHERE election_id = ?");
              $stmtVotes->execute([$election['election_id']]);
              $votesCast = (int)($stmtVotes->fetch()['voted'] ?? 0);

              $turnout = $totalVoters > 0 ? round(($votesCast / $totalVoters) * 100, 1) : 0;
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
                  <h2 class="election-title text-lg font-bold text-[var(--cvsu-green-dark)] mb-2 truncate">
                    <?= htmlspecialchars($election['title']) ?>
                  </h2>
                  
                  <p class="text-gray-700 text-sm mb-4 line-clamp-2">
                    <?= nl2br(htmlspecialchars($election['description'])) ?>
                  </p>
                  
                  <!-- Countdown Timer for Ongoing Elections -->
                  <?php if ($status === 'ongoing'): ?>
                    <?php
                      $interval = $nowDT->diff($endDateTime);
                      
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
                    <div class="mb-3">
                      <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Voter Turnout</span>
                        <span><?= $votesCast ?>/<?= $totalVoters ?> (<?= $turnout ?>%)</span>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z" />
                      </svg>
                      <span><strong class="text-gray-700">End:</strong> <?= $endDateTime->format("M d, Y h:i A") ?></span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Action Button -->
              <div class="mt-auto p-4 bg-gray-50 border-t">
                <?php if ($status === 'ongoing' && !$hasVoted): ?>
                  <a href="vote_candidates.php?election_id=<?= $election['election_id'] ?>" 
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
    
    function toggleSidebar() {
      const sidebar = document.getElementById('votersSidebar');
      sidebar.classList.toggle('open');
    }
    
    function closeNotification() {
      document.getElementById('notificationBanner').classList.add('hidden');
    }
    
    function toggleMenu(menuId) {
      const menu = document.getElementById(menuId);
      menu.classList.toggle('hidden');
      
      document.querySelectorAll('[id^="menu_"]').forEach(m => {
        if (m.id !== menuId) {
          m.classList.add('hidden');
        }
      });
    }
    
    function showResults(electionId) {
      window.location.href = `election_results.php?election_id=${electionId}`;
    }
    
    function filterElections() {
      const searchTerm = document.getElementById('searchElections').value.toLowerCase().trim();
      const activeTab = document.querySelector('.tab-btn.active').dataset.category;
      const electionCards = document.querySelectorAll('.election-card');
      
      electionCards.forEach(card => {
        const titleElement = card.querySelector('h2');
        if (!titleElement) {
          card.style.display = 'none';
          return;
        }
        
        const title      = titleElement.textContent.toLowerCase().trim();
        const cardStatus = card.dataset.status;
        
        const matchesSearch = searchTerm === '' || title.includes(searchTerm);
        const matchesTab    = activeTab === 'all' || cardStatus === activeTab;
        
        card.style.display = (matchesSearch && matchesTab) ? 'block' : 'none';
      });
    }

    document.getElementById('searchElections').addEventListener('input', filterElections);

    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterElections();
      });
    });
    
    document.addEventListener('DOMContentLoaded', () => {
      const forcePasswordChange = <?= $force_password_flag ?>;
      
      if (forcePasswordChange === 1) {
        document.getElementById('forcePasswordChangeModal').classList.remove('hidden');
        document.body.style.pointerEvents = 'none';
        document.getElementById('forcePasswordChangeModal').style.pointerEvents = 'auto';
      }
      
      document.querySelector('[data-category="ongoing"]').click();
      
      const voteLinks = document.querySelectorAll('a[href^="vote_candidates.php"]');
      const modal = document.getElementById('privacyModal');
      const checkbox = document.getElementById('privacyCheck');
      const proceedBtn = document.getElementById('proceedVote');
      const cancelBtn = document.getElementById('cancelModal');
      
      voteLinks.forEach(link => {
        link.addEventListener('click', function (e) {
          e.preventDefault();
          targetUrl = this.href;
          modal.classList.remove('hidden');
        });
      });
      
      checkbox.addEventListener('change', () => {
        proceedBtn.disabled = !checkbox.checked;
      });
      
      cancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        checkbox.checked = false;
        proceedBtn.disabled = true;
      });
      
      proceedBtn.addEventListener('click', () => {
        if (checkbox.checked) {
          window.location.href = targetUrl;
        }
      });
      
      const togglePassword = document.getElementById('togglePassword');
      const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
      const passwordInput = document.getElementById('newPassword');
      const confirmPasswordInput = document.getElementById('confirmPassword');
      
      togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
      });
      
      toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
      });
      
      passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('passwordStrength');
        
        const length = password.length >= 8;
        const uppercase = /[A-Z]/.test(password);
        const number = /[0-9]/.test(password);
        const special = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        updateCheck('lengthCheck', length);
        updateCheck('uppercaseCheck', uppercase);
        updateCheck('numberCheck', number);
        updateCheck('specialCheck', special);
        
        let strength = 0;
        if (length) strength++;
        if (uppercase) strength++;
        if (number) strength++;
        if (special) strength++;
        
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
        
        checkPasswordMatch();
      });
      
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
          
          passwordError.classList.add('hidden');
          passwordSuccess.classList.add('hidden');
          passwordLoading.classList.remove('hidden');
          
          const newPassword = passwordInput.value;
          const confirmPassword = confirmPasswordInput.value;
          
          const length = newPassword.length >= 8;
          const uppercase = /[A-Z]/.test(newPassword);
          const number = /[0-9]/.test(newPassword);
          const special = /[!@#$%^&*(),.?":{}|<>]/.test(newPassword);
          
          let strength = 0;
          if (length) strength++;
          if (uppercase) strength++;
          if (number) strength++;
          if (special) strength++;
          
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
          
          submitBtn.disabled = true;
          submitBtnText.textContent = 'Updating...';
          submitLoader.classList.remove('hidden');
          
          fetch('update_voters_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_password: newPassword })
          })
          .then(response => response.json())
          .then(data => {
            passwordLoading.classList.add('hidden');
            
            if (data.status === 'success') {
              passwordSuccess.classList.remove('hidden');
              submitBtn.disabled = false;
              submitBtnText.textContent = 'Update Password';
              submitLoader.classList.add('hidden');
              
              setTimeout(() => {
                document.getElementById('forcePasswordChangeModal').classList.add('hidden');
                document.body.style.pointerEvents = 'auto';
                
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
                
                setTimeout(() => {
                  window.location.reload();
                }, 1500);
              }, 2000);
            } else {
              submitBtn.disabled = false;
              submitBtnText.textContent = 'Update Password';
              submitLoader.classList.add('hidden');
              
              passwordErrorText.textContent = data.message || "Failed to update password.";
              passwordError.classList.remove('hidden');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            passwordLoading.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtnText.textContent = 'Update Password';
            submitLoader.classList.add('hidden');
            passwordErrorText.textContent = "An error occurred. Please try again.";
            passwordError.classList.remove('hidden');
          });
        });
      }
    });
    
    function closePasswordModal() {
      const forcePasswordChange = <?= $force_password_flag ?>;
      if (forcePasswordChange === 1) return;
      document.getElementById('forcePasswordChangeModal').classList.add('hidden');
      document.body.style.pointerEvents = 'auto';
    }
    
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
