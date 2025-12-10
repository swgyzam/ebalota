<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Auth check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

// Get the current admin's role and assigned scope
 $adminRole = $_SESSION['role'];
 $assignedScope = $_SESSION['assigned_scope'] ?? '';
 $normalizedAssignedScope = strtoupper(trim($assignedScope)); // Normalize the scope

// New scope context from admin_add_user.php / current admin session
 $scopeCategoryForCsv   = $_SESSION['scope_category_for_csv'] ?? ($_SESSION['scope_category'] ?? null);
 $ownerScopeIdForCsv    = $_SESSION['owner_scope_id_for_csv'] ?? null;
// Course / department scope (e.g. "BSIT" or "Multiple: BSIT, BSCS" for Academic-Student)
 $assignedScope1ForCsv  = $_SESSION['assigned_scope_1'] ?? null;

// Check if we have the CSV file path in session
if (!isset($_SESSION['csv_file_path'])) {
    die("No CSV file to process.");
}

 $csvFilePath = $_SESSION['csv_file_path'];
unset($_SESSION['csv_file_path']); // Clear the session

// Get admin type from session or determine it
 $adminType = $_SESSION['admin_type'] ?? null;

if (!$adminType && $scopeCategoryForCsv) {
    // Prefer new scope_category-based mapping
    switch ($scopeCategoryForCsv) {
        case 'Academic-Student':
            $adminType = 'admin_students';
            break;
        case 'Non-Academic-Student':
            $adminType = 'admin_students';
            break;
        case 'Academic-Faculty':
            $adminType = 'admin_academic';
            break;
        case 'Non-Academic-Employee':
            $adminType = 'admin_non_academic';
            break;
        case 'Others':
            // Unified Others → treat as non-academic style CSV
            $adminType = 'admin_non_academic';
            break;
        case 'Special-Scope': // CSG Admin
            $adminType = 'admin_students';
            break;
        default:
            // fall through to legacy logic
            break;
    }
}

if (!$adminType) {
    // Legacy fallback based on assigned_scope (for older admins)
    if ($adminRole === 'super_admin') {
        $adminType = 'super_admin';
    } else if (in_array($normalizedAssignedScope, ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'])) {
        $adminType = 'admin_students';
    } else if ($normalizedAssignedScope === 'FACULTY ASSOCIATION') {
        $adminType = 'admin_academic';
    } else if ($normalizedAssignedScope === 'NON-ACADEMIC') {
        $adminType = 'admin_non_academic';
    } else if ($normalizedAssignedScope === 'COOP') {
        $adminType = 'admin_coop';
    } else if ($normalizedAssignedScope === 'CSG ADMIN') {
        $adminType = 'admin_students';
    } else {
        $adminType = 'general_admin';
    }
}

// Database connection
 $host = 'localhost';
 $db = 'evoting_system';
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

// ---------------------------------------------------------------------
// Load admin_scopes for this admin to get scope_details (latest model)
// ---------------------------------------------------------------------
 $myScopeDetails          = [];
 $allowedCourseScopeCodes = []; // for Academic-Student
 $allowedDeptScopeAcademic = []; // for Academic-Faculty
 $allowedDeptScopeNonAcad  = []; // for Non-Academic-Employee

if ($adminRole === 'admin' && !empty($scopeCategoryForCsv)) {
    $scopeStmt = $pdo->prepare("
        SELECT scope_details
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid'   => $_SESSION['user_id'],
        ':stype' => $scopeCategoryForCsv,
    ]);
    $scopeRow = $scopeStmt->fetch();

    if ($scopeRow && !empty($scopeRow['scope_details'])) {
        $decoded = json_decode($scopeRow['scope_details'], true);
        if (is_array($decoded)) {
            $myScopeDetails = $decoded;
        }
    }
}

// Build course scope list for Academic-Student admins
if ($scopeCategoryForCsv === 'Academic-Student') {
    if (!empty($myScopeDetails['courses']) && is_array($myScopeDetails['courses'])) {
        foreach ($myScopeDetails['courses'] as $c) {
            $code = normalizeCourseCodeForCsv($c);
            if ($code !== '') {
                $allowedCourseScopeCodes[] = $code;
            }
        }
        $allowedCourseScopeCodes = array_unique($allowedCourseScopeCodes);
    } elseif (!empty($assignedScope1ForCsv)) {
        // Fallback to assigned_scope_1 string
        $allowedCourseScopeCodes = parseCourseScopeList($assignedScope1ForCsv); // already uppercased
    }
}

// Build department scope list for Academic-Faculty
if ($scopeCategoryForCsv === 'Academic-Faculty') {
    if (!empty($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
        // For Academic-Faculty, scope_details['departments'] stores full department names
        $allowedDeptScopeAcademic = array_values(array_filter(array_map('trim', $myScopeDetails['departments'])));
    }
    // If departments_display == 'All', we treat as no restriction
    if (isset($myScopeDetails['departments_display']) && $myScopeDetails['departments_display'] === 'All') {
        $allowedDeptScopeAcademic = [];
    }
}

// Build department scope list for Non-Academic-Employee (codes like ADMIN, LIBRARY, ...)
if ($scopeCategoryForCsv === 'Non-Academic-Employee') {
    if (!empty($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
        $allowedDeptScopeNonAcad = array_values(array_filter(array_map('trim', $myScopeDetails['departments'])));
    }
    if (isset($myScopeDetails['departments_display']) && $myScopeDetails['departments_display'] === 'All') {
        $allowedDeptScopeNonAcad = [];
    }
}

// Check if the file exists
if (!file_exists($csvFilePath)) {
    die("CSV file not found.");
}

// Open the CSV file
 $file = fopen($csvFilePath, 'r');
if (!$file) {
    die("Failed to open CSV file.");
}

// Skip header row if it exists
 $header = fgetcsv($file);
if ($header && count($header) >= 6) {
    // We have a header row, continue processing
} else {
    // No header, rewind to start
    rewind($file);
}

// Initialize counters
 $totalRows = 0;
 $inserted = 0;
 $duplicates = 0;
 $errors = 0;
 $restrictedRows = 0; // New counter for restricted rows
 $emailSent = 0;
 $emailFailed = 0;
 $claimedExisting = 0; // NEW: existing users linked to Others-Default scope
 $errorMessages = []; // Store error messages for display

// Set expected columns based on admin type
 $expectedColumns = 8; // Default for general admin
switch ($adminType) {
    case 'admin_students':
        $expectedColumns = 8; // first_name, last_name, email, position, student_number, college, department, course
        break;
    case 'admin_academic':
        $expectedColumns = 9; // first_name, last_name, email, position, employee_number, college, department, status, is_coop_member
        break;
    case 'admin_non_academic':
        // For both Others and Non-Academic-Employee, we expect 8 CSV columns.
        // Others: first_name,last_name,email,position,employee_number,college,department,status
        // Non-Academic-Employee: first_name,last_name,email,position,employee_number,department,status,is_coop_member
        $expectedColumns = 8;
        break;
    case 'admin_coop':
        $expectedColumns = 9; // first_name, last_name, email, position, employee_number, college, department, status, is_coop_member
        break;
}

// College mapping - Full name to code
 $collegeMapping = [
    // CEIT
    'college of engineering and information technology' => 'CEIT',
    'College of Engineering and Information Technology' => 'CEIT',
    'college of engineering, information and technology' => 'CEIT',
    'College of Engineering, Information and Technology' => 'CEIT',
    'ceit' => 'CEIT',
    'CEIT' => 'CEIT',
    'engineering' => 'CEIT',
    'Engineering' => 'CEIT',

    // CAS
    'college of arts and sciences' => 'CAS',
    'College of Arts and Sciences' => 'CAS',
    'cas' => 'CAS',
    'CAS' => 'CAS',
    'arts and sciences' => 'CAS',
    'Arts and Sciences' => 'CAS',

    // CEMDS
    'college of economics, management and development studies' => 'CEMDS',
    'College of Economics, Management and Development Studies' => 'CEMDS',
    'college of economics management and development studies' => 'CEMDS',
    'College of Economics Management and Development Studies' => 'CEMDS',
    'cemds' => 'CEMDS',
    'CEMDS' => 'CEMDS',
    'economics' => 'CEMDS',
    'Economics' => 'CEMDS',

    // CCJ
    'college of criminal justice education' => 'CCJ',
    'College of Criminal Justice Education' => 'CCJ',
    'ccj' => 'CCJ',
    'CCJ' => 'CCJ',
    'criminal justice' => 'CCJ',
    'Criminal Justice' => 'CCJ',

    // CAFENR
    'college of agriculture, food, environment and natural resources' => 'CAFENR',
    'College of Agriculture, Food, Environment and Natural Resources' => 'CAFENR',
    'college of agriculture food environment and natural resources' => 'CAFENR',
    'College of Agriculture Food Environment and Natural Resources' => 'CAFENR',
    'cafenr' => 'CAFENR',
    'CAFENR' => 'CAFENR',
    'agriculture' => 'CAFENR',
    'Agriculture' => 'CAFENR',

    // CON
    'college of nursing' => 'CON',
    'College of Nursing' => 'CON',
    'con' => 'CON',
    'CON' => 'CON',
    'nursing' => 'CON',
    'Nursing' => 'CON',

    // CED
    'college of education' => 'CED',
    'College of Education' => 'CED',
    'ced' => 'CED',
    'CED' => 'CED',
    'education' => 'CED',
    'Education' => 'CED',
    'coed' => 'CED',
    'CoEd' => 'CED',

    // CVMBS
    'college of veterinary medicine and biomedical sciences' => 'CVMBS',
    'College of Veterinary Medicine and Biomedical Sciences' => 'CVMBS',
    'college of veterinary medicine & biomedical sciences' => 'CVMBS',
    'College of Veterinary Medicine & Biomedical Sciences' => 'CVMBS',
    'cvmbs' => 'CVMBS',
    'CVMBS' => 'CVMBS',
    'veterinary medicine' => 'CVMBS',
    'Veterinary Medicine' => 'CVMBS',

    // CSPEAR
    'college of sports, physical education and recreation' => 'CSPEAR',
    'College of Sports, Physical Education and Recreation' => 'CSPEAR',
    'csphear' => 'CSPEAR',
    'CSPEAR' => 'CSPEAR',
    'sports' => 'CSPEAR',
    'Sports' => 'CSPEAR',

    // COM
    'college of medicine' => 'COM',
    'College of Medicine' => 'COM',
    'com' => 'COM',
    'COM' => 'COM',
    'medicine' => 'COM',
    'Medicine' => 'COM',

    // CTHM
    'college of tourism and hospitality management' => 'CTHM',
    'College of Tourism and Hospitality Management' => 'CTHM',
    'cthm' => 'CTHM',
    'CTHM' => 'CTHM',
    'tourism' => 'CTHM',
    'Tourism' => 'CTHM',
    'hospitality' => 'CTHM',
    'Hospitality' => 'CTHM',

    // GS-OLC
    'graduate school and open learning college' => 'GS-OLC',
    'Graduate School and Open Learning College' => 'GS-OLC',
    'graduate school and open learning center' => 'GS-OLC',
    'Graduate School and Open Learning Center' => 'GS-OLC',
    'gs-olc' => 'GS-OLC',
    'GS-OLC' => 'GS-OLC',
    'graduate school' => 'GS-OLC',
    'Graduate School' => 'GS-OLC',
    'open learning' => 'GS-OLC',
    'Open Learning' => 'GS-OLC',
];

// Department mapping for academic staff
// Keys must be LOWERCASED before lookup (we use strtolower($department_lower)).
 $academicDepartmentMapping = [
    // ================= CAFENR =================
    'department of animal science'                    => 'Department of Animal Science',
    'animal science department'                       => 'Department of Animal Science',
    'animal science'                                  => 'Department of Animal Science',
    'das'                                             => 'Department of Animal Science',

    'department of crop science'                      => 'Department of Crop Science',
    'crop science department'                         => 'Department of Crop Science',
    'crop science'                                    => 'Department of Crop Science',
    'dcs'                                             => 'Department of Crop Science',

    'department of food science and technology'       => 'Department of Food Science and Technology',
    'food science and technology department'          => 'Department of Food Science and Technology',
    'food science and technology'                     => 'Department of Food Science and Technology',
    'dfst'                                            => 'Department of Food Science and Technology',

    'department of forestry and environmental science' => 'Department of Forestry and Environmental Science',
    'forestry and environmental science department'    => 'Department of Forestry and Environmental Science',
    'forestry and environmental science'               => 'Department of Forestry and Environmental Science',
    'dfes'                                             => 'Department of Forestry and Environmental Science',

    'department of agricultural economics and development' => 'Department of Agricultural Economics and Development',
    'agricultural economics and development department'    => 'Department of Agricultural Economics and Development',
    'agricultural economics and development'               => 'Department of Agricultural Economics and Development',
    'daed'                                                 => 'Department of Agricultural Economics and Development',

    // ================= CAS =================
    'department of biological sciences'               => 'Department of Biological Sciences',
    'biological sciences department'                  => 'Department of Biological Sciences',
    'biological sciences'                             => 'Department of Biological Sciences',
    'dbs'                                             => 'Department of Biological Sciences',

    'department of physical sciences'                 => 'Department of Physical Sciences',
    'physical sciences department'                    => 'Department of Physical Sciences',
    'physical sciences'                               => 'Department of Physical Sciences',
    'dps'                                             => 'Department of Physical Sciences',

    'department of languages and mass communication'  => 'Department of Languages and Mass Communication',
    'languages and mass communication department'     => 'Department of Languages and Mass Communication',
    'languages and mass communication'                => 'Department of Languages and Mass Communication',
    'dlmc'                                            => 'Department of Languages and Mass Communication',

    'department of social sciences'                   => 'Department of Social Sciences',
    'social sciences department'                      => 'Department of Social Sciences',
    'social sciences'                                 => 'Department of Social Sciences',
    'dss'                                             => 'Department of Social Sciences',

    'department of mathematics and statistics'        => 'Department of Mathematics and Statistics',
    'mathematics and statistics department'           => 'Department of Mathematics and Statistics',
    'mathematics and statistics'                      => 'Department of Mathematics and Statistics',
    'dms'                                             => 'Department of Mathematics and Statistics',

    // ================= CCJ =================
    'department of criminal justice'                  => 'Department of Criminal Justice',
    'criminal justice department'                     => 'Department of Criminal Justice',
    'criminal justice'                                => 'Department of Criminal Justice',
    'dcj'                                             => 'Department of Criminal Justice',

    // ================= CEMDS =================
    'department of economics'                         => 'Department of Economics',
    'economics department'                            => 'Department of Economics',
    'economics'                                       => 'Department of Economics',
    'dec'                                             => 'Department of Economics',

    'department of business and management'           => 'Department of Business and Management',
    'business and management department'              => 'Department of Business and Management',
    'business and management'                         => 'Department of Business and Management',
    'dbm'                                             => 'Department of Business and Management',

    'department of development studies'               => 'Department of Development Studies',
    'development studies department'                  => 'Department of Development Studies',
    'development studies'                             => 'Department of Development Studies',
    'dds'                                             => 'Department of Development Studies',

    // ================= CED =================
    'department of science education'                 => 'Department of Science Education',
    'science education department'                    => 'Department of Science Education',
    'science education'                               => 'Department of Science Education',
    'dse'                                             => 'Department of Science Education',

    'department of technology and livelihood education' => 'Department of Technology and Livelihood Education',
    'technology and livelihood education department'    => 'Department of Technology and Livelihood Education',
    'technology and livelihood education'               => 'Department of Technology and Livelihood Education',
    'dtle'                                             => 'Department of Technology and Livelihood Education',

    'department of curriculum and instruction'        => 'Department of Curriculum and Instruction',
    'curriculum and instruction department'           => 'Department of Curriculum and Instruction',
    'curriculum and instruction'                      => 'Department of Curriculum and Instruction',
    'dci'                                             => 'Department of Curriculum and Instruction',

    'department of human kinetics'                    => 'Department of Human Kinetics',
    'human kinetics department'                       => 'Department of Human Kinetics',
    'human kinetics'                                  => 'Department of Human Kinetics',
    'dhk'                                             => 'Department of Human Kinetics',

    // ================= CEIT =================
    'department of civil engineering'                 => 'Department of Civil Engineering',
    'civil engineering department'                    => 'Department of Civil Engineering',
    'civil engineering'                               => 'Department of Civil Engineering',
    'dce'                                             => 'Department of Civil Engineering',

    'department of computer and electronics engineering' => 'Department of Computer and Electronics Engineering',
    'computer and electronics engineering department'    => 'Department of Computer and Electronics Engineering',
    'computer and electronics engineering'               => 'Department of Computer and Electronics Engineering',
    'dcee'                                              => 'Department of Computer and Electronics Engineering',

    'department of industrial engineering and technology' => 'Department of Industrial Engineering and Technology',
    'industrial engineering and technology department'    => 'Department of Industrial Engineering and Technology',
    'industrial engineering and technology'               => 'Department of Industrial Engineering and Technology',
    'diet'                                               => 'Department of Industrial Engineering and Technology',

    'department of mechanical and electronics engineering' => 'Department of Mechanical and Electronics Engineering',
    'mechanical and electronics engineering department'    => 'Department of Mechanical and Electronics Engineering',
    'mechanical and electronics engineering'               => 'Department of Mechanical and Electronics Engineering',
    'dmee'                                                => 'Department of Mechanical and Electronics Engineering',

    'department of information technology'             => 'Department of Information Technology',
    'information technology department'                => 'Department of Information Technology',
    'information technology'                           => 'Department of Information Technology',
    'dit'                                             => 'Department of Information Technology',

    // ================= CON =================
    'department of nursing'                           => 'Department of Nursing',
    'nursing department'                              => 'Department of Nursing',
    'nursing'                                         => 'Department of Nursing',
    'dn'                                              => 'Department of Nursing',

    // ================= COM =================
    'department of basic medical sciences'            => 'Department of Basic Medical Sciences',
    'basic medical sciences department'               => 'Department of Basic Medical Sciences',
    'basic medical sciences'                          => 'Department of Basic Medical Sciences',
    'dbms'                                            => 'Department of Basic Medical Sciences',

    'department of clinical sciences'                 => 'Department of Clinical Sciences',
    'clinical sciences department'                    => 'Department of Clinical Sciences',
    'clinical sciences'                               => 'Department of Clinical Sciences',
    'dcs'                                             => 'Department of Clinical Sciences',

    // ================= CSPEAR =================
    'department of physical education and recreation' => 'Department of Physical Education and Recreation',
    'physical education and recreation department'    => 'Department of Physical Education and Recreation',
    'physical education and recreation'               => 'Department of Physical Education and Recreation',
    'dper'                                           => 'Department of Physical Education and Recreation',

    // ================= CVMBS =================
    'department of veterinary medicine'               => 'Department of Veterinary Medicine',
    'veterinary medicine department'                  => 'Department of Veterinary Medicine',
    'veterinary medicine'                             => 'Department of Veterinary Medicine',
    'dvm'                                             => 'Department of Veterinary Medicine',

    'department of biomedical sciences'               => 'Department of Biomedical Sciences',
    'biomedical sciences department'                  => 'Department of Biomedical Sciences',
    'biomedical sciences'                             => 'Department of Biomedical Sciences',
    'dbs'                                             => 'Department of Biomedical Sciences',

    // ================= GS-OLC =================
    'department of various graduate programs'         => 'Department of Various Graduate Programs',
    'various graduate programs department'            => 'Department of Various Graduate Programs',
    'various graduate programs'                       => 'Department of Various Graduate Programs',
    'dvgp'                                            => 'Department of Various Graduate Programs',

    // Fallback "General"
    'general'                                         => 'General',
    'gen'                                             => 'General',
];

// Department mapping for non-academic staff
 $nonAcademicDepartmentMapping = [
    // Non-Academic Departments
    'administration' => 'ADMIN',
    'admin' => 'ADMIN',
    'administrative' => 'ADMIN',
    
    'finance' => 'FINANCE',
    'financial' => 'FINANCE',
    
    'human resources' => 'HR',
    'hr' => 'HR',
    'human resource' => 'HR',
    
    'information technology' => 'IT',
    'it' => 'IT',
    'information tech' => 'IT',
    'tech support' => 'IT',
    
    'maintenance' => 'MAINTENANCE',
    'maint' => 'MAINTENANCE',
    
    'security' => 'SECURITY',
    'guard' => 'SECURITY',
    'security guard' => 'SECURITY',
    
    'library' => 'LIBRARY',
    'lib' => 'LIBRARY',
    
    'non-academic employees association' => 'NAEA',
    'naea' => 'NAEA',
    'non-academic employees assoc' => 'NAEA',
];

// Course code → full display name mapping for students
 $courseCodeToFullName = [
    // ===== CEIT / Engineering =====
    'BSIT'      => 'BS Information Technology',
    'BSCS'      => 'BS Computer Science',
    'BSCPE'     => 'BS Computer Engineering',
    'BSECE'     => 'BS Electronics Engineering',
    'BSCE'      => 'BS Civil Engineering',
    'BSME'      => 'BS Mechanical Engineering',
    'BSEE'      => 'BS Electrical Engineering',
    'BSIE'      => 'BS Industrial Engineering',

    // ===== CAFENR =====
    'BSAGRI'    => 'BS Agriculture',
    'BSAB'      => 'BS Agribusiness',
    'BSES'      => 'BS Environmental Science',
    'BSFT'      => 'BS Food Technology',
    'BSFOR'     => 'BS Forestry',
    'BSABE'     => 'BS Agricultural and Biosystems Engineering',
    'BAE'       => 'BA Agricultural Entrepreneurship',
    'BSLDM'     => 'BS Land Use Design and Management',

    // ===== CAS =====
    'BSBIO'     => 'BS Biology',
    'BSCHEM'    => 'BS Chemistry',
    'BSMATH'    => 'BS Mathematics',
    'BSPHYSICS' => 'BS Physics',
    'BSPSYCH'   => 'BS Psychology',
    'BAELS'     => 'BA English Language Studies',
    'BACOMM'    => 'BA Communication',
    'BSSTAT'    => 'BS Statistics',

    // ===== CVMBS =====
    'DVM'       => 'Doctor of Veterinary Medicine',
    'BSPV'      => 'BS Biology (Pre-Veterinary)',

    // ===== CED =====
    'BEED'      => 'Bachelor of Elementary Education',
    'BSED'      => 'Bachelor of Secondary Education',
    'BPE'       => 'Bachelor of Physical Education',
    'BTLE'      => 'Bachelor of Technology and Livelihood Education',

    // ===== CEMDS =====
    'BSBA'      => 'BS Business Administration',
    'BSACC'     => 'BS Accountancy',
    'BSECO'     => 'BS Economics',
    'BSENT'     => 'BS Entrepreneurship',
    'BSOA'      => 'BS Office Administration',

    // ===== CSPEAR / CCJ / CON / CTHM / COM =====
    'BSESS'     => 'BS Exercise and Sports Sciences',
    'BSCRIM'    => 'BS Criminology',
    'BSN'       => 'BS Nursing',
    'BSHM'      => 'BS Hospitality Management',
    'BSTM'      => 'BS Tourism Management',
    'BLIS'      => 'BLIS',

    // ===== Graduate Programs =====
    'PHD'       => 'PhD',
    'MS'        => 'MS',
    'MA'        => 'MA',
];

// Allowed values
 $allowedColleges = array_unique(array_values($collegeMapping));
 $allowedNonAcademicDepts = array_values($nonAcademicDepartmentMapping);
 $allowedStatuses = ['Regular', 'Part-time', 'Contractual'];

// Status mapping and validation
 $statusMapping = [
    'full-time' => 'Regular',
    'part-time' => 'Part-time',
    'contractual' => 'Contractual',
    'regular' => 'Regular',
    'full time' => 'Regular',
    'part time' => 'Part-time',
    'permanent' => 'Regular',
    'temporary' => 'Contractual',
    'probationary' => 'Contractual',
    'casual' => 'Contractual',
];

// Department to College Mapping for COOP academic staff
 $departmentToCollegeMapping = [
    'Department of Computer and Electronics Engineering' => 'CEIT',
    'Department of Information Technology' => 'CEIT',
    'Department of Electronics Engineering' => 'CEIT',
    'Department of Biological Sciences' => 'CAS',
    'Department of Mathematics and Physics' => 'CAS',
    'Department of Social Sciences' => 'CAS',
    'Department of Languages and Literature' => 'CAS',
    'Department of Business Administration' => 'CAFENR',
    'Department of Economics' => 'CAFENR',
    'Department of Agricultural Technology' => 'CAFENR',
    'Department of Nursing' => 'CON',
    'Department of Public Health' => 'CON',
    'Department of Medical Technology' => 'CON',
    'Department of Hotel and Restaurant Management' => 'CTHM',
    'Department of Tourism' => 'CTHM',
    'Department of Criminal Justice Education' => 'CCJ',
    'Department of Criminology' => 'CCJ',
    'Department of Education' => 'CED',
    'Department of Teacher Education' => 'CED',
    'Department of Fisheries' => 'CAFENR',
    'Department of Forestry' => 'CAFENR',
    'Department of Environmental Science' => 'CAFENR',
    'Department of Veterinary Medicine' => 'CVMBS',
    'Department of Animal Science' => 'CVMBS',
];

// Function to format names
function formatName($name) {
    $name = strtolower($name);
    $words = explode(' ', $name);
    $formattedName = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $formattedName .= ucfirst($word) . ' ';
        }
    }
    
    return trim($formattedName);
}

// Function to generate random password
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Normalize course string to a simple code (e.g. "bsit", "BSIT", "BS IT" -> "BSIT")
function normalizeCourseCodeForCsv($raw) {
    $s = strtoupper(trim($raw));
    if ($s === '') return '';
    // remove spaces, dots, commas etc.
    $s = preg_replace('/[.\-_,]/', ' ', $s);
    $s = preg_replace('/\s+/', '', $s);
    return $s;
}

// Parse a scope string like "BSIT" or "Multiple: BSIT, BSCS" into ['BSIT','BSCS']
function parseCourseScopeList($scopeString) {
    if ($scopeString === null) return [];
    $clean = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $scopeString);
    $parts = array_filter(array_map('trim', explode(',', $clean)));
    $codes = [];
    foreach ($parts as $p) {
        if ($p === '' || strcasecmp($p, 'All') === 0) continue;
        $codes[] = strtoupper($p);
    }
    return array_unique($codes);
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

// Read each row
while (($row = fgetcsv($file)) !== FALSE) {
    $totalRows++;
    
    // Check if we have the expected number of columns
    if (count($row) < $expectedColumns) {
        $errors++;
        $errorMessages[] = "Row $totalRows: Insufficient columns";
        continue;
    }
    
    // Per-row defaults
    $student_number   = null;
    $employee_number  = null;
    $department_db    = null;
    $department1      = null;
    $course           = null;
    $status           = null;
    $is_coop_member   = 0;
    $is_other_member  = 0;          // NEW
    $owner_scope_id   = null;       // NEW
    
    // Extract data based on admin type
    switch ($adminType) {
        case 'admin_students':
            $first_name = trim($row[0] ?? '');
            $last_name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $position = trim($row[3] ?? '');
            $student_number = trim($row[4] ?? '');
            $college = trim($row[5] ?? '');
            $department = trim($row[6] ?? '');
            $course = trim($row[7] ?? '');
            $is_coop_member = 0; // Default to 0 for students
            $status = null; // No status for students
            
            // VALIDATION: Check if position is valid for this admin type
            if (strtolower($position) !== 'student') {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: Invalid position '$position' for admin_students. Only 'student' position is allowed.";
                continue 2; // Skip to next iteration of while loop
            }
            
            // MAP COLLEGE
            $college_lower = strtolower($college);
            if (isset($collegeMapping[$college_lower])) {
                $college = $collegeMapping[$college_lower];
            } elseif (in_array(strtoupper($college), $allowedColleges)) {
                // It's already a valid college code, so we can use it as is
                $college = strtoupper($college);
            } else {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid college '$college'. College must be one of: " . implode(', ', $allowedColleges);
                continue 2;
            }
            
            // VALIDATION: Check if college matches assigned scope (for Academic-Student admins only, not CSG)
            if (
                $scopeCategoryForCsv === 'Academic-Student' &&
                $normalizedAssignedScope !== 'CSG ADMIN' &&
                $college !== $normalizedAssignedScope
            ) {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: College '$college' not in your assigned scope '$assignedScope'.";
                continue 2;
            }
            
            // VALIDATION: For Academic-Student admins with course scope
            // ensure uploaded course is within allowed course list (e.g. BSIT only).
            if ($scopeCategoryForCsv === 'Academic-Student' && !empty($allowedCourseScopeCodes)) {
                $courseCode = normalizeCourseCodeForCsv($course);
                if (!in_array($courseCode, $allowedCourseScopeCodes, true)) {
                    $restrictedRows++;
                    $errorMessages[] = "Row $totalRows: Course '$course' not allowed for your scope. Allowed: " . implode(', ', $allowedCourseScopeCodes) . ".";
                    continue 2;
                }
            }

            // OPTIONAL: Academic-Student department1 (department) must not be empty
            if ($scopeCategoryForCsv === 'Academic-Student') {
                if ($department === '') {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Department is required for Academic-Student uploads.";
                    continue 2;
                }
            }

            // Map department code/name → canonical full academic department name (for department1)
            $department_lower = strtolower($department);
            if (isset($academicDepartmentMapping[$department_lower])) {
                $department = $academicDepartmentMapping[$department_lower];
            }

            // Convert course code → full course name before saving
            $courseCodeNorm = normalizeCourseCodeForCsv($course);
            if ($courseCodeNorm !== '' && isset($courseCodeToFullName[$courseCodeNorm])) {
                $course = $courseCodeToFullName[$courseCodeNorm];
            }

            // Map to database fields
            $department_db   = $college;    // Store college code in department
            $department1     = $department; // Store full department name in department1
            $employee_number = null;

            // If this upload is from a Non-Academic-Student admin, tie to their scope
            if ($scopeCategoryForCsv === 'Non-Academic-Student' && $ownerScopeIdForCsv !== null) {
                $owner_scope_id = (int)$ownerScopeIdForCsv;
            }
            break;
            
        case 'admin_academic':
            $first_name = trim($row[0] ?? '');
            $last_name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $position = trim($row[3] ?? '');
            $employee_number = trim($row[4] ?? '');
            $college = trim($row[5] ?? '');
            $department = trim($row[6] ?? '');
            $status = trim($row[7] ?? '');
            $is_coop_member = intval(trim($row[8] ?? '0'));
            
            // VALIDATION: Check if position is valid for this admin type
            if (strtolower($position) !== 'academic') {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: Invalid position '$position' for admin_academic. Only 'academic' position is allowed.";
                continue 2; // Skip to next iteration of while loop
            }
            
            // MAP COLLEGE
            $college_lower = strtolower($college);
            if (isset($collegeMapping[$college_lower])) {
                $college = $collegeMapping[$college_lower];
            } elseif (!in_array($college, $allowedColleges)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid college '$college'. College must be one of: " . implode(', ', $allowedColleges);
                continue 2;
            }
            
            // VALIDATION: For Academic-Faculty admins, college must match their scope
            if ($scopeCategoryForCsv === 'Academic-Faculty' && $normalizedAssignedScope !== '' && $college !== $normalizedAssignedScope) {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: College '$college' not in your faculty scope '$assignedScope'.";
                continue 2;
            }
            
            // MAP DEPARTMENT (academic)
            $department_lower = strtolower($department);
            if (isset($academicDepartmentMapping[$department_lower])) {
                $department = $academicDepartmentMapping[$department_lower]; // canonical full name
            }
            
            // VALIDATE & MAP STATUS
            $status_lower = strtolower($status);
            if (isset($statusMapping[$status_lower])) {
                $status = $statusMapping[$status_lower];
            } elseif (!in_array($status, $allowedStatuses)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid status '$status'. Only Regular, Part-time, or Contractual are allowed.";
                continue 2;
            }

            // VALIDATION: Academic-Faculty department scope (if restricted)
            if ($scopeCategoryForCsv === 'Academic-Faculty' && !empty($allowedDeptScopeAcademic)) {
                if (!in_array($department, $allowedDeptScopeAcademic, true)) {
                    $restrictedRows++;
                    $errorMessages[] = "Row $totalRows: Department '$department' is not allowed for your faculty scope.";
                    continue 2;
                }
            }
            
            // Map to database fields
            $department_db   = $college;    // Store college code in department field
            $department1     = $department; // Store department in department1 field
            $student_number  = null;
            $course          = null;
            break;
            
        case 'admin_non_academic':

            if ($scopeCategoryForCsv === 'Others') {
                // Unified Others admin: flexible members (employees or external)
                // CSV columns (8):
                // 0 first_name, 1 last_name, 2 email, 3 position, 4 employee_number, 5 college, 6 department, 7 status
                $first_name      = trim($row[0] ?? '');
                $last_name       = trim($row[1] ?? '');
                $email           = trim($row[2] ?? '');
                $position        = trim($row[3] ?? '');  // may be '', 'academic', 'non-academic'
                $employee_number = trim($row[4] ?? '');
                $college         = trim($row[5] ?? '');
                $department      = trim($row[6] ?? '');
                $status          = trim($row[7] ?? '');

                $student_number  = null;
                $course          = null;
                $is_coop_member  = 0;
                $is_other_member = 1;  // mark as Others member
                $owner_scope_id  = ($ownerScopeIdForCsv !== null) ? (int)$ownerScopeIdForCsv : null;

                // Position validation: allow blank, 'academic', or 'non-academic'
                $posLower = strtolower($position);
                if ($position !== '' && !in_array($posLower, ['academic', 'non-academic'], true)) {
                    $restrictedRows++;
                    $errorMessages[] = "Row $totalRows: Invalid position '$position' for Others. Use 'academic', 'non-academic', or leave blank.";
                    continue 2;
                }

                // College mapping (optional)
                if ($college !== '') {
                    $college_lower = strtolower($college);
                    if (isset($collegeMapping[$college_lower])) {
                        $college = $collegeMapping[$college_lower];
                    } elseif (!in_array($college, $allowedColleges, true)) {
                        $errors++;
                        $errorMessages[] = "Row $totalRows: Invalid college '$college'. College must be one of: " . implode(', ', $allowedColleges);
                        continue 2;
                    }
                } else {
                    $college = null;
                }

                // Department mapping (optional, tolerant)
                if ($department !== '') {
                    $department_lower = strtolower($department);

                    if (isset($academicDepartmentMapping[$department_lower])) {
                        // Academic department – store in department1, and college (if any) in department
                        $departmentFull = $academicDepartmentMapping[$department_lower];
                        $department1    = $departmentFull;

                        // If no college yet, try inferring from mapping (best-effort)
                        if ($college === null && isset($departmentToCollegeMapping[$departmentFull])) {
                            $college = $departmentToCollegeMapping[$departmentFull];
                        }

                        $department_db = $college; // may be null if college unknown

                    } elseif (isset($nonAcademicDepartmentMapping[$department_lower])) {
                        // Non-academic department – store code in department
                        $departmentCode = $nonAcademicDepartmentMapping[$department_lower];
                        $department_db  = $departmentCode;
                        $department1    = null;

                    } else {
                        // Unknown / custom – just store raw into department
                        $department_db = $department;
                        $department1   = null;
                    }
                } else {
                    $department_db = null;
                    $department1   = null;
                }

                // Status mapping (optional)
                if ($status !== '') {
                    $status_lower = strtolower($status);
                    if (isset($statusMapping[$status_lower])) {
                        $status = $statusMapping[$status_lower];
                    }
                    // else: keep raw status, no hard error
                } else {
                    $status = null;
                }

            } else {
                // Non-Academic-Employee & legacy NON-ACADEMIC behaviour (old path)
                // CSV columns (8):
                // 0 first_name, 1 last_name, 2 email, 3 position, 4 employee_number, 5 department, 6 status, 7 is_coop_member
                $first_name      = trim($row[0] ?? '');
                $last_name       = trim($row[1] ?? '');
                $email           = trim($row[2] ?? '');
                $position        = trim($row[3] ?? '');
                $employee_number = trim($row[4] ?? '');
                $department      = trim($row[5] ?? '');
                $status          = trim($row[6] ?? '');
                $is_coop_member  = intval(trim($row[7] ?? '0'));

                // VALIDATION: must be non-academic
                if (strtolower($position) !== 'non-academic') {
                    $restrictedRows++;
                    $errorMessages[] = "Row $totalRows: Invalid position '$position' for admin_non_academic. Only 'non-academic' position is allowed.";
                    continue 2;
                }

                // MAP DEPARTMENT (non-ac)
                $department_lower = strtolower($department);
                if (isset($nonAcademicDepartmentMapping[$department_lower])) {
                    $department = $nonAcademicDepartmentMapping[$department_lower];
                } elseif (!in_array($department, $allowedNonAcademicDepts)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid department '$department'. Department must be one of: " . implode(', ', $allowedNonAcademicDepts);
                    continue 2;
                }

                // VALIDATION: Non-Academic-Employee department scope (if restricted)
                if ($scopeCategoryForCsv === 'Non-Academic-Employee' && !empty($allowedDeptScopeNonAcad)) {
                    if (!in_array($department, $allowedDeptScopeNonAcad, true)) {
                        $restrictedRows++;
                        $errorMessages[] = "Row $totalRows: Department '$department' is not allowed for your non-academic scope.";
                        continue 2;
                    }
                }

                // VALIDATE & MAP STATUS
                $status_lower = strtolower($status);
                if (isset($statusMapping[$status_lower])) {
                    $status = $statusMapping[$status_lower];
                } elseif (!in_array($status, $allowedStatuses)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid status '$status'. Only Regular, Part-time, or Contractual are allowed.";
                    continue 2;
                }

                $department_db   = $department;
                $department1     = null;
                $student_number  = null;
                $course          = null;
                $is_other_member = 0;
                // Non-Academic-Employee rows are not Others members by default
            }
            break;
            
        case 'admin_coop':
            $first_name = trim($row[0] ?? '');
            $last_name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $position = trim($row[3] ?? ''); // 'academic' or 'non-academic'
            $employee_number = trim($row[4] ?? '');
            $college = trim($row[5] ?? ''); // College for academic, empty for non-academic
            $department = trim($row[6] ?? ''); // Department for both
            $status = trim($row[7] ?? '');
            $is_coop_member = 1; // Always 1 for COOP members
            
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($email) || empty($position) || empty($employee_number) || empty($department)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Missing required fields for COOP member.";
                continue 2; // Skip to next iteration of while loop
            }
            
            // VALIDATION: Check if position is valid for this admin type
            if (!in_array(strtolower($position), ['academic', 'non-academic'])) {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: Invalid position '$position' for admin_coop. Only 'academic' or 'non-academic' positions are allowed.";
                continue 2; // Skip to next iteration of while loop
            }
            
            // Handle differently based on position
            if ($position === 'academic') {
                // For academic staff: college in department field, department in department1 field
                
                // MAP COLLEGE if provided
                if (!empty($college)) {
                    $college_lower = strtolower($college);
                    if (isset($collegeMapping[$college_lower])) {
                        $college = $collegeMapping[$college_lower];
                    } elseif (!in_array($college, $allowedColleges)) {
                        $errors++;
                        $errorMessages[] = "Row $totalRows: Invalid college '$college'. College must be one of: " . implode(', ', $allowedColleges);
                        continue 2;
                    }
                } else {
                    // Try to infer college from department using mapping
                    $college = $departmentToCollegeMapping[$department] ?? null;
                    
                    if (empty($college)) {
                        $errors++;
                        $errorMessages[] = "Row $totalRows: Cannot determine college for academic staff with department '$department'.";
                        continue 2; // Skip to next iteration of while loop
                    }
                }
                
                // MAP DEPARTMENT
                $department_lower = strtolower($department);
                if (isset($academicDepartmentMapping[$department_lower])) {
                    $department = $academicDepartmentMapping[$department_lower];
                }
                
                $department_db = $college; // This is the college (e.g., "CEIT")
                $department1 = $department; // This is the actual department
            } else {
                // For non-academic staff: department in department field, department1 is NULL
                // MAP DEPARTMENT for non-academic COOP staff
                $department_lower = strtolower($department);
                if (isset($nonAcademicDepartmentMapping[$department_lower])) {
                    $department = $nonAcademicDepartmentMapping[$department_lower];
                } elseif (!in_array($department, $allowedNonAcademicDepts)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid department '$department'. Department must be one of: " . implode(', ', $allowedNonAcademicDepts);
                    continue 2;
                }
                
                $department_db = $department;
                $department1 = null;
            }
            
            $student_number = null;
            $course = null;
            
            // VALIDATE AND MAP STATUS
            $status_lower = strtolower($status);
            if (isset($statusMapping[$status_lower])) {
                $status = $statusMapping[$status_lower];
            } elseif (!in_array($status, $allowedStatuses)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid status '$status'. Only Regular, Part-time, or Contractual are allowed.";
                continue 2;
            }
            break;
            
        case 'super_admin':
        default: // For general admin
            $first_name = trim($row[0] ?? '');
            $last_name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $position = trim($row[3] ?? '');
            $student_number = !empty(trim($row[4] ?? '')) ? trim($row[4]) : null;
            $employee_number = !empty(trim($row[5] ?? '')) ? trim($row[5]) : null;
            $college = trim($row[6] ?? '');
            $department = trim($row[7] ?? '');
            $course = trim($row[8] ?? '');
            $status = trim($row[9] ?? '');
            $is_coop_member = intval(trim($row[10] ?? '0'));
            
            // Apply mapping based on position
            if ($position === 'student') {
                // MAP COLLEGE
                $college_lower = strtolower($college);
                if (isset($collegeMapping[$college_lower])) {
                    $college = $collegeMapping[$college_lower];
                } elseif (!empty($college) && !in_array($college, $allowedColleges)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid college '$college'. College must be one of: " . implode(', ', $allowedColleges);
                    continue 2;
                }

                // Map department code/name → full department name (optional, if you want consistency)
                $department_lower = strtolower($department);
                if (isset($academicDepartmentMapping[$department_lower])) {
                    $department = $academicDepartmentMapping[$department_lower];
                }

                // Convert course code → full course name
                $courseCodeNorm = normalizeCourseCodeForCsv($course);
                if ($courseCodeNorm !== '' && isset($courseCodeToFullName[$courseCodeNorm])) {
                    $course = $courseCodeToFullName[$courseCodeNorm];
                }
            } elseif ($position === 'academic') {
                // MAP COLLEGE
                $college_lower = strtolower($college);
                if (isset($collegeMapping[$college_lower])) {
                    $college = $collegeMapping[$college_lower];
                } elseif (!empty($college) && !in_array($college, $allowedColleges)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid college '$college'. College must be one of: " . implode(', ', $allowedColleges);
                    continue 2;
                }
                
                // MAP DEPARTMENT
                $department_lower = strtolower($department);
                if (isset($academicDepartmentMapping[$department_lower])) {
                    $department = $academicDepartmentMapping[$department_lower];
                }
            } elseif ($position === 'non-academic') {
                // MAP DEPARTMENT
                $department_lower = strtolower($department);
                if (isset($nonAcademicDepartmentMapping[$department_lower])) {
                    $department = $nonAcademicDepartmentMapping[$department_lower];
                } elseif (!in_array($department, $allowedNonAcademicDepts)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid department '$department'. Department must be one of: " . implode(', ', $allowedNonAcademicDepts);
                    continue 2;
                }
            }
            
            // VALIDATE AND MAP STATUS for non-student positions
            if ($position !== 'student' && !empty($status)) {
                $status_lower = strtolower($status);
                if (isset($statusMapping[$status_lower])) {
                    $status = $statusMapping[$status_lower];
                } elseif (!in_array($status, $allowedStatuses)) {
                    $errors++;
                    $errorMessages[] = "Row $totalRows: Invalid status '$status'. Only Regular, Part-time, or Contractual are allowed.";
                    continue 2;
                }
            }
            
            // Map to database fields based on position
            if ($position === 'student') {
                $department_db = $college;
                $department1 = $department;
            } elseif ($position === 'academic') {
                $department_db = $college;
                $department1 = $department;
            } else {
                $department_db = $department;
                $department1 = null;
            }
            break;
    }
    
    if (empty($email) || empty($first_name) || empty($last_name)) {
        $errors++;
        $errorMessages[] = "Row $totalRows: Missing required fields (first_name, last_name, or email).";
        continue;
    }

    try {
        // Check if email already exists in users or pending_users
        $checkUserStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkUserStmt->execute([$email]);
        $existingUserId = $checkUserStmt->fetchColumn();

        $checkPendingStmt = $pdo->prepare("SELECT pending_id FROM pending_users WHERE email = ?");
        $checkPendingStmt->execute([$email]);
        $existingPendingId = $checkPendingStmt->fetchColumn();

        /**
         * SPECIAL CASE:
         * Others-Default admin wants to "claim" existing employees
         * instead of treating them purely as duplicates.
         */
        if ($existingUserId && $scopeCategoryForCsv === 'Others' && !empty($ownerScopeIdForCsv)) {
            // Update existing user: mark as Others member and tie to this scope seat
            $updateUserStmt = $pdo->prepare("
                UPDATE users
                SET is_other_member = 1,
                    owner_scope_id  = :ownerScopeId
                WHERE user_id = :uid
            ");
            $updateUserStmt->execute([
                ':ownerScopeId' => (int)$ownerScopeIdForCsv,
                ':uid'          => (int)$existingUserId,
            ]);
        
            $claimedExisting++;
            $errorMessages[] = "Row $totalRows: Existing user '$email' linked to your Others scope.";
        
            continue;
        }        

        // For all other cases (or if only pending_users exists), treat as duplicate
        if ($existingUserId || $existingPendingId) {
            $duplicates++;
            $errorMessages[] = "Row $totalRows: Email '$email' already exists in the system.";
            continue;
        }

        // Format names
        $first_name = formatName($first_name);
        $last_name = formatName($last_name);
        
        // Generate a random password
        $password = generateRandomPassword(10);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        // Insert into pending_users table - UPDATED to include is_other_member and owner_scope_id
        $insertStmt = $pdo->prepare("INSERT INTO pending_users 
            (first_name, last_name, email, position, student_number, employee_number, 
             is_coop_member, is_other_member, department, department1, course, status, password, 
             token, expires_at, source, is_restricted, owner_scope_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'csv', 0, ?)");
        
        $insertStmt->execute([
            $first_name,
            $last_name,
            $email,
            $position,
            $student_number,
            $employee_number,
            $is_coop_member,
            $is_other_member,
            $department_db,
            $department1,
            $course,
            $status,
            $hashedPassword,
            $token,
            $expiresAt,
            $owner_scope_id
        ]);
        
        if ($insertStmt->rowCount() > 0) {
            $inserted++;
            
            // Send verification email
            $verificationUrl = "http://localhost/ebalota/verify_csv_user.php?token=$token";
            
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'krpmab@gmail.com';
                $mail->Password = 'ghdumnwrjbphujbs';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('makimaki.maki123567@gmail.com', 'eBalota');
                $mail->addAddress($email, "$first_name $last_name");
                
                $mail->isHTML(true);
                $mail->Subject = 'Your Account Credentials - eBalota';
                $mail->Body = "
                    Hi $first_name $last_name,<br><br>
                    Your account has been created in the eBalota system. Please use the following credentials to log in:<br><br>
                    <strong>Email:</strong> $email<br>
                    <strong>Password:</strong> $password<br><br>
                    For security reasons, you will be required to change your password upon first login.<br><br>
                    Please verify your email by clicking the button below:<br><br>
                    <a href='$verificationUrl' style='
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #28a745;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                    '>Verify Email</a><br><br>
                    This link will expire in 24 hours.<br><br>
                    Regards,<br>eBalota | Cavite State University
                ";
                $mail->AltBody = "Your account has been created. Email: $email, Password: $password. Please verify by visiting: $verificationUrl";
                
                $mail->send();
                $emailSent++;
            } catch (Exception $e) {
                $emailFailed++;
                error_log("Email sending failed to $email: " . $mail->ErrorInfo);
            }
        }
    } catch (Exception $e) {
        $errors++;
        $errorMessages[] = "Row $totalRows: Database error - " . $e->getMessage();
        error_log("Error processing user with email $email: " . $e->getMessage());
    }
}

fclose($file);

// Delete the CSV file
unlink($csvFilePath);

// --- Activity Log: CSV Users Upload Summary ---
try {
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    if ($adminId > 0) {
        // Short description of what kind of upload this was
        $contextParts = [];

        if (!empty($adminType)) {
            $contextParts[] = "adminType: {$adminType}";
        }
        if (!empty($scopeCategoryForCsv)) {
            $contextParts[] = "scopeCategory: {$scopeCategoryForCsv}";
        }
        if (!empty($assignedScope) && $assignedScope !== '') {
            $contextParts[] = "assignedScope: {$assignedScope}";
        }

        $contextText = '';
        if (!empty($contextParts)) {
            $contextText = ' [' . implode(' | ', $contextParts) . ']';
        }

        // Stats summary
        $summaryParts = [];
        $summaryParts[] = "total rows: {$totalRows}";
        $summaryParts[] = "added: {$inserted}";
        $summaryParts[] = "duplicates: {$duplicates}";
        $summaryParts[] = "errors: {$errors}";
        $summaryParts[] = "restricted: {$restrictedRows}";
        $summaryParts[] = "emails sent: {$emailSent}";
        $summaryParts[] = "emails failed: {$emailFailed}";
        if (isset($claimedExisting)) {
            $summaryParts[] = "existing claimed: {$claimedExisting}";
        }

        $summaryText = implode(', ', $summaryParts);

        $actionText = "Processed users CSV upload{$contextText}. Summary: {$summaryText}.";

        $stmtLog = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, timestamp)
            VALUES (:uid, :action, NOW())
        ");
        $stmtLog->execute([
            ':uid'    => $adminId,
            ':action' => $actionText,
        ]);
    }
} catch (PDOException $e) {
    // Silent fail – huwag ipakita sa user
    error_log('Activity log error (process_users_csv.php): ' . $e->getMessage());
}

// Now, show the result
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CSV Processing Result - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }
    
    .error-container {
      max-height: 300px;
      overflow-y: auto;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-users text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">CSV Processing Result</h1>
            <p class="text-green-100 mt-1">Summary of processed CSV file</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_add_user.php" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">Upload Another</a>
          <a href="admin_manage_users.php" class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded font-semibold transition">Back to Users</a>
        </div>
      </header>

      <div class="bg-white p-8 rounded-lg shadow-md max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">Processing Summary</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
          <div class="bg-blue-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-blue-600"><?= $totalRows ?></div>
            <div class="text-gray-600">Total Rows</div>
          </div>
          <div class="bg-green-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-green-600"><?= $inserted ?></div>
            <div class="text-gray-600">Users Added</div>
          </div>
          <div class="bg-yellow-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-yellow-600"><?= $duplicates ?></div>
            <div class="text-gray-600">Duplicates</div>
          </div>
          <div class="bg-red-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-red-600"><?= $errors ?></div>
            <div class="text-gray-600">Errors</div>
          </div>
          <div class="bg-purple-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-purple-600"><?= $restrictedRows ?></div>
            <div class="text-gray-600">Restricted</div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
          <div class="bg-green-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-green-600"><?= $emailSent ?></div>
            <div class="text-gray-600">Emails Sent</div>
          </div>
          <div class="bg-red-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-red-600"><?= $emailFailed ?></div>
            <div class="text-gray-600">Emails Failed</div>
          </div>
        </div>
        
        <?php if ($claimedExisting > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-8">
                <div class="bg-indigo-50 p-6 rounded-lg text-center">
                    <div class="text-3xl font-bold text-indigo-600"><?= $claimedExisting ?></div>
                    <div class="text-gray-600">Existing Users Claimed</div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($inserted > 0): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Users Added Successfully</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p><?= $inserted ?> user(s) in the CSV file have been added to the system. Verification emails have been sent to all successfully added users with their login credentials.</p>
                            <p class="mt-2">Users will need to verify their email and change their password on first login.</p>
                            <?php if ($duplicates > 0 || $errors > 0 || $restrictedRows > 0): ?>
                                <p class="mt-2 font-medium">Note: Some rows were not processed due to duplicates, errors, or restrictions. See details below.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">No Users Added</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>No users were added to the system. Please check the details below for more information.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($claimedExisting > 0): ?>
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-link text-indigo-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-indigo-800">Existing Users Claimed</h3>
                        <div class="mt-2 text-sm text-indigo-700">
                            <p><?= $claimedExisting ?> existing user(s) have been linked to your Others scope. These users will now appear in your user management list.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($restrictedRows > 0): ?>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-purple-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-purple-800">Restricted Rows</h3>
                        <div class="mt-2 text-sm text-purple-700">
                            <p><?= $restrictedRows ?> row(s) were skipped because they contained users outside your admin scope.</p>
                            <p class="mt-2">Please ensure you only upload users that fall within your assigned scope.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($duplicates > 0): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-yellow-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Duplicate Entries</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p><?= $duplicates ?> row(s) were skipped because the email addresses already exist in the system.</p>
                            <p class="mt-2">Please check for duplicate entries in your CSV file.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($errors > 0): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Processing Errors</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?= $errors ?> row(s) encountered errors during processing.</p>
                            <p class="mt-2">Please check the error details below and correct your CSV file.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessages)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div class="ml-3 w-full">
                        <h3 class="text-sm font-medium text-red-800">Error Details</h3>
                        <div class="mt-2 text-sm text-red-700 error-container">
                            <ul class="list-disc pl-5 space-y-1">
                                <?php foreach ($errorMessages as $message): ?>
                                    <li><?= htmlspecialchars($message) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <h3 class="font-medium text-yellow-800 mb-2">CSV Format Requirements:</h3>
          <p class="text-sm text-yellow-700">
            The CSV file should have the following columns in this order:<br>
            <?php
            switch ($adminType) {
                case 'admin_students':
                    echo '<code>first_name, last_name, email, position, student_number, college, department, course</code>';
                    break;
                case 'admin_academic':
                    echo '<code>first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</code>';
                    break;
                case 'admin_non_academic':
                    if ($scopeCategoryForCsv === 'Others') {
                        echo '<code>first_name, last_name, email, position, employee_number, college, department, status</code>';
                    } else {
                        echo '<code>first_name, last_name, email, position, employee_number, department, status, is_coop_member</code>';
                    }
                    break;
                case 'admin_coop':
                    echo '<code>first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</code>';
                    break;
                default:
                    echo '<code>first_name, last_name, email, position, student_number, employee_number, college, department, course, status, is_coop_member</code>';
                    break;
            }
            ?>
          </p>
        </div>
        
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 class="font-medium text-blue-800 mb-2">Admin Scope Restrictions:</h3>
          <p class="text-sm text-blue-700">
            <?php
            switch ($adminType) {
                case 'admin_students':
                    if ($scopeCategoryForCsv === 'Academic-Student') {
                        echo "As an <strong>Academic-Student Admin</strong>, you can only upload users with position <code>student</code>.<br>";
                        echo "Rows must have college <strong>" . htmlspecialchars($normalizedAssignedScope) . "</strong>.";
                        if (!empty($allowedCourseScopeCodes)) {
                            echo " Courses must be one of: <strong>" . htmlspecialchars(implode(', ', $allowedCourseScopeCodes)) . "</strong>.";
                        } else {
                            echo " All courses in that college are allowed.";
                        }
                        echo " Department (academic unit) is required for each row.";
                    } elseif ($scopeCategoryForCsv === 'Non-Academic-Student') {
                        echo "As a <strong>Non-Academic-Student Admin</strong>, you can only upload users with position <code>student</code>.<br>";
                        echo "You may upload students from any college, department, or course, but all uploaded students will be owned by <strong>your organization scope</strong> (tied via <code>owner_scope_id</code>) and visible only to you.";
                    } elseif ($scopeCategoryForCsv === 'Special-Scope' || $normalizedAssignedScope === 'CSG ADMIN') {
                        echo "As a <strong>CSG Admin</strong>, you can only upload users with position <code>student</code>.<br>";
                        echo "Students uploaded here are treated as <strong>global</strong> (no owner_scope_id) and may come from any college or course.";
                    } else {
                        echo "As a <strong>Student Admin</strong>, you can only upload users with position <code>student</code>.";
                    }
                    break;

                case 'admin_academic':
                    if ($scopeCategoryForCsv === 'Academic-Faculty') {
                        echo "As an <strong>Academic-Faculty Admin</strong>, you can only upload users with position <code>academic</code> (faculty).<br>";
                        echo "Rows must have college <strong>" . htmlspecialchars($normalizedAssignedScope) . "</strong>.";
                        if (!empty($allowedDeptScopeAcademic)) {
                            echo " Departments must be one of: <strong>" . htmlspecialchars(implode(', ', $allowedDeptScopeAcademic)) . "</strong>.";
                        } else {
                            echo " All departments under that college are allowed.";
                        }
                    } else {
                        echo "As a <strong>Faculty Association Admin</strong>, you can only upload users with position <code>academic</code>.";
                    }
                    break;

                case 'admin_non_academic':
                    if ($scopeCategoryForCsv === 'Others') {
                        echo "As an <strong>Others Admin</strong>, you can upload a mixture of <code>academic</code>/<code>non-academic</code> staff and external group members (e.g., alumni, retirees).<br>";
                        echo "All uploaded rows will be stored as <strong>Others members</strong> (<code>is_other_member = 1</code>) and tied to your <strong>scope seat</strong> via <code>owner_scope_id</code>.";
                        echo " Only <code>first_name</code>, <code>last_name</code>, and <code>email</code> are strictly required; other fields are optional.";
                    } elseif ($scopeCategoryForCsv === 'Non-Academic-Employee') {
                        echo "As a <strong>Non-Academic-Employee Admin</strong>, you can only upload users with position <code>non-academic</code>.<br>";
                        if (!empty($allowedDeptScopeNonAcad)) {
                            echo "Departments must be one of: <strong>" . htmlspecialchars(implode(', ', $allowedDeptScopeNonAcad)) . "</strong>.";
                        } else {
                            echo "Any valid non-academic department is allowed.";
                        }
                    } else {
                        echo "As a <strong>Non-Academic Admin</strong>, you can only upload users with position <code>non-academic</code>.";
                    }
                    break;

                case 'admin_coop':
                    echo "As a <strong>COOP Admin</strong>, you can only upload users with positions <code>academic</code> or <code>non-academic</code> who are COOP members.<br>";
                    echo "All rows must effectively have <code>is_coop_member = 1</code>.";
                    break;

                case 'super_admin':
                    echo "As a <strong>Super Admin</strong>, you can upload users with any position, college, department, or course. No scope restrictions apply.";
                    break;

                default:
                    echo "As a <strong>General Admin</strong>, you can upload users with any position, subject to any additional scope validation configured for your account.";
                    break;
            }
            ?>
          </p>
          <p class="text-sm text-blue-700 mt-2">
            <strong>Automatic Field Mapping:</strong> The system automatically maps various input formats to standardized values:
          </p>
          <ul class="text-sm text-blue-700 mt-1 ml-5 list-disc">
            <li><strong>Colleges:</strong> Full names (e.g., "College of Engineering") are converted to codes (e.g., "CEIT")</li>
            <li><strong>Departments:</strong> Various formats are standardized (e.g., "Administration" → "ADMIN")</li>
            <li><strong>Status:</strong> Variations like "full-time" are converted to "Regular"</li>
            <li><strong>Courses:</strong> Now stored as full names (e.g., "BS Computer Science") to match registration process</li>
          </ul>
          <p class="text-sm text-blue-700 mt-2">
            This ensures consistency with users registered through the web form and proper matching with election eligibility criteria.
          </p>
          <p class="text-sm text-blue-700 mt-2">
            <strong>CSV Format:</strong> Use codes for college (CEIT), department (DIT, DCEE, DCE, ...) and course (BSIT, BSCS, ...). The system will automatically convert them to full names when saving to the database.
          </p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>