<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
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
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Comprehensive Mapping System ---
 $mappingSystem = [
    // Department codes to full names
    'departments' => [
        'DCEE' => 'Department of Computer and Electronics Engineering',
        'DCET' => 'Department of Civil Engineering Technology',
        'DMET' => 'Department of Mechanical Engineering Technology',
        'DEET' => 'Department of Electrical Engineering Technology',
        'DIT' => 'Department of Information Technology',
        'DCS' => 'Department of Computer Science',
        'DCE' => 'Department of Chemical Engineering',
        'DN' => 'Department of Nursing',
        'DTHM' => 'Department of Tourism and Hospitality Management',
        'DED' => 'Department of Education',
        'DAE' => 'Department of Agricultural Engineering',
        'DAB' => 'Department of Agribusiness',
        'DAFS' => 'Department of Animal Science',
        'DCFS' => 'Department of Crop and Forest Science',
        'DEVS' => 'Department of Environmental Science',
        'DFT' => 'Department of Food Technology',
        'DF' => 'Department of Forestry',
        'DBE' => 'Department of Biological Engineering',
        'DAG' => 'Department of Agricultural Economics',
        'DL' => 'Department of Languages',
        'DSS' => 'Department of Social Sciences',
        'DPS' => 'Department of Physical Sciences',
        'DM' => 'Department of Mathematics',
        'DPE' => 'Department of Physical Education',
        'DCrim' => 'Department of Criminology',
        'DCom' => 'Department of Communication',
        'DPSY' => 'Department of Psychology',
        'DLib' => 'Department of Library and Information Science',
        'DHRM' => 'Department of Human Resource Management',
        'DFM' => 'Department of Financial Management',
        'DMKT' => 'Department of Marketing',
        'DOA' => 'Department of Office Administration',
        'DET' => 'Department of Educational Technology',
        'DGE' => 'Department of Guidance and Counseling',
        'DSE' => 'Department of Special Education',
        'DVE' => 'Department of Vocational Education',
        'DVM' => 'Department of Veterinary Medicine',
        'DBS' => 'Department of Basic Sciences',
        'DCL' => 'Department of Clinical Sciences',
        'DVP' => 'Department of Veterinary Parasitology',
        'DVPth' => 'Department of Veterinary Pathology',
        'DVPH' => 'Department of Public Health',
        'DVMed' => 'Department of Veterinary Medicine',
        'DAN' => 'Department of Animal Nutrition',
        'DVS' => 'Department of Veterinary Surgery',
        'DVMicro' => 'Department of Veterinary Microbiology',
        'DVPharm' => 'Department of Veterinary Pharmacology',
        'DVEpi' => 'Department of Veterinary Epidemiology',
        'DVL' => 'Department of Veterinary Laboratory',
        'DVTh' => 'Department of Veterinary Theriogenology',
        'DVAn' => 'Department of Veterinary Anatomy',
        'DVPhys' => 'Department of Veterinary Physiology',
        'DVBio' => 'Department of Veterinary Biochemistry',
        'DVPath' => 'Department of Veterinary Pathology',
        'DVP' => 'Department of Veterinary Public Health',
        'DVPr' => 'Department of Veterinary Preventive Medicine',
        'DVC' => 'Department of Veterinary Clinical Sciences',
        'DVSurg' => 'Department of Veterinary Surgery and Radiology',
        'DVMed' => 'Department of Veterinary Medicine',
        'DVPh' => 'Department of Veterinary Pharmacology and Toxicology',
        'DVPar' => 'Department of Veterinary Parasitology',
        'DVMic' => 'Department of Veterinary Microbiology',
        'DVPath' => 'Department of Veterinary Pathology',
        'DVEpi' => 'Department of Veterinary Epidemiology and Public Health',
        'DVTh' => 'Department of Veterinary Theriogenology',
        'DVAn' => 'Department of Veterinary Anatomy and Histology',
        'DVPhys' => 'Department of Veterinary Physiology',
        'DVBio' => 'Department of Veterinary Biochemistry',
        'DVGen' => 'Department of Veterinary Genetics and Animal Breeding',
        'DVLiv' => 'Department of Veterinary Livestock Products Technology',
        'DVEth' => 'Department of Veterinary Ethics and Jurisprudence',
        'DVExt' => 'Department of Veterinary Extension Education',
        'DVEco' => 'Department of Veterinary Economics',
        'DVStat' => 'Department of Veterinary Biostatistics',
        'DVComp' => 'Department of Veterinary Computer Science',
        'DVL' => 'Department of Veterinary Laboratory Diagnosis',
        'DVClin' => 'Department of Veterinary Clinical Medicine',
        'DVSurg' => 'Department of Veterinary Surgery',
        'DVOph' => 'Department of Veterinary Ophthalmology',
        'DVDerm' => 'Department of Veterinary Dermatology',
        'DVCa' => 'Department of Veterinary Cardiology',
        'DVNeu' => 'Department of Veterinary Neurology',
        'DVEnd' => 'Department of Veterinary Endocrinology',
        'DVGas' => 'Department of Veterinary Gastroenterology',
        'DVResp' => 'Department of Veterinary Respiratory Medicine',
        'DVUro' => 'Department of Veterinary Urology',
        'DVNeph' => 'Department of Veterinary Nephrology',
        'DVHem' => 'Department of Veterinary Hematology',
        'DVImm' => 'Department of Veterinary Immunology',
        'DVAll' => 'Department of Veterinary Allergy',
        'DVRhe' => 'Department of Veterinary Rheumatology',
        'DVInf' => 'Department of Veterinary Infectious Diseases',
        'DVPed' => 'Department of Veterinary Pediatrics',
        'DVGer' => 'Department of Veterinary Geriatrics',
        'DVObs' => 'Department of Veterinary Obstetrics and Gynecology',
        'DVAnd' => 'Department of Veterinary Andrology',
        'DVRepro' => 'Department of Veterinary Reproduction',
        'DVFert' => 'Department of Veterinary Fertility',
        'DVArt' => 'Department of Veterinary Artificial Insemination',
        'DVEmb' => 'Department of Veterinary Embryo Transfer',
        'DVGyn' => 'Department of Veterinary Gynecology',
        'DVObs' => 'Department of Veterinary Obstetrics',
        'DVNeo' => 'Department of Veterinary Neonatology',
        'DVMam' => 'Department of Veterinary Mammary Gland',
        'DVLac' => 'Department of Veterinary Lactation',
        'DVNut' => 'Department of Veterinary Nutrition',
        'DVFeed' => 'Department of Veterinary Feed Technology',
        'DVFor' => 'Department of Veterinary Forage Production',
        'DVPas' => 'Department of Veterinary Pasture Management',
        'DVGr' => 'Department of Veterinary Grassland Management',
        'DVIrr' => 'Department of Veterinary Irrigation',
        'DVSol' => 'Department of Veterinary Soil Science',
        'DVMet' => 'Department of Veterinary Meteorology',
        'DVAgro' => 'Department of Veterinary Agroforestry',
        'DVEcol' => 'Department of Veterinary Ecology',
        'DVEnv' => 'Department of Veterinary Environmental Science',
        'DVPoll' => 'Department of Veterinary Pollution Control',
        'DWW' => 'Department of Veterinary Wildlife',
        'DZoo' => 'Department of Veterinary Zoology',
        'DVMus' => 'Department of Veterinary Museum',
        'DVL' => 'Department of Veterinary Library',
        'DVCom' => 'Department of Veterinary Communication',
        'DVStat' => 'Department of Veterinary Statistics',
        'DVComp' => 'Department of Veterinary Computer',
        // Add more as needed
    ],
    
    // Course codes to full names
    'courses' => [
        'BSIT' => 'Bachelor of Science in Information Technology',
        'BSCS' => 'Bachelor of Science in Computer Science',
        'BSCpE' => 'Bachelor of Science in Computer Engineering',
        'BSECE' => 'Bachelor of Science in Electronics Engineering',
        'BSCE' => 'Bachelor of Science in Civil Engineering',
        'BSME' => 'Bachelor of Science in Mechanical Engineering',
        'BSEE' => 'Bachelor of Science in Electrical Engineering',
        'BSIE' => 'Bachelor of Science in Industrial Engineering',
        'BSN' => 'Bachelor of Science in Nursing',
        'BSHM' => 'Bachelor of Science in Hospitality Management',
        'BSTM' => 'Bachelor of Science in Tourism Management',
        'BEED' => 'Bachelor of Elementary Education',
        'BSED' => 'Bachelor of Secondary Education',
        'BSAB' => 'Bachelor of Science in Agribusiness',
        'BSAF' => 'Bachelor of Science in Agriculture',
        'BSFT' => 'Bachelor of Science in Food Technology',
        'BSFor' => 'Bachelor of Science in Forestry',
        'BSA' => 'Bachelor of Science in Agriculture',
        'BSABE' => 'Bachelor of Science in Agricultural and Biosystems Engineering',
        'BSAE' => 'Bachelor of Science in Agricultural Engineering',
        'BSAET' => 'Bachelor of Science in Agricultural Engineering Technology',
        'BSAG' => 'Bachelor of Science in Agriculture',
        'BSAN' => 'Bachelor of Science in Animal Nutrition',
        'BSAS' => 'Bachelor of Science in Animal Science',
        'BSC' => 'Bachelor of Science in Chemistry',
        'BSBio' => 'Bachelor of Science in Biology',
        'BSChem' => 'Bachelor of Science in Chemistry',
        'BSMath' => 'Bachelor of Science in Mathematics',
        'BSPhysics' => 'Bachelor of Science in Physics',
        'BSPsych' => 'Bachelor of Science in Psychology',
        'BAComm' => 'Bachelor of Arts in Communication',
        'BAELS' => 'Bachelor of Arts in English Language Studies',
        'BSEco' => 'Bachelor of Science in Economics',
        'BSEnt' => 'Bachelor of Science in Entrepreneurship',
        'BSAcc' => 'Bachelor of Science in Accountancy',
        'BSBA' => 'Bachelor of Science in Business Administration',
        'BSOA' => 'Bachelor of Science in Office Administration',
        'BSCRIM' => 'Bachelor of Science in Criminology',
        'BSP' => 'Bachelor of Science in Physics',
        'BSStat' => 'Bachelor of Science in Statistics',
        'BSESS' => 'Bachelor of Science in Exercise and Sports Sciences',
        'BLIS' => 'Bachelor of Library and Information Science',
        'BPE' => 'Bachelor of Physical Education',
        'BTLE' => 'Bachelor of Technology and Livelihood Education',
        'BSPV' => 'Bachelor of Science in Pre-Veterinary Medicine',
        'DVM' => 'Doctor of Veterinary Medicine',
        'BSAgri' => 'Bachelor of Science in Agriculture',
        'BSAB' => 'Bachelor of Science in Agribusiness',
        'BSES' => 'Bachelor of Science in Environmental Science',
        'BSFT' => 'Bachelor of Science in Food Technology',
        'BSFor' => 'Bachelor of Science in Forestry',
        'BSABE' => 'Bachelor of Science in Agricultural and Biosystems Engineering',
        'BAE' => 'Bachelor of Agricultural Engineering',
        'BSLDM' => 'Bachelor of Science in Land Use Design and Management',
        'BSBio' => 'Bachelor of Science in Biology',
        'BSChem' => 'Bachelor of Science in Chemistry',
        'BSMath' => 'Bachelor of Science in Mathematics',
        'BSPhysics' => 'Bachelor of Science in Physics',
        'BSPsych' => 'Bachelor of Science in Psychology',
        'BAELS' => 'Bachelor of Arts in English Language Studies',
        'BAComm' => 'Bachelor of Arts in Communication',
        'BSStat' => 'Bachelor of Science in Statistics',
        'DVM' => 'Doctor of Veterinary Medicine',
        'BSPV' => 'Bachelor of Science in Pre-Veterinary Medicine',
        'BEEd' => 'Bachelor of Elementary Education',
        'BSEd' => 'Bachelor of Secondary Education',
        'BPE' => 'Bachelor of Physical Education',
        'BTLE' => 'Bachelor of Technology and Livelihood Education',
        'BSBA' => 'Bachelor of Science in Business Administration',
        'BSAcc' => 'Bachelor of Science in Accountancy',
        'BSEco' => 'Bachelor of Science in Economics',
        'BSEnt' => 'Bachelor of Science in Entrepreneurship',
        'BSOA' => 'Bachelor of Science in Office Administration',
        'BSESS' => 'Bachelor of Science in Exercise and Sports Sciences',
        'BSCRIM' => 'Bachelor of Science in Criminology',
        'BSN' => 'Bachelor of Science in Nursing',
        'BSHM' => 'Bachelor of Science in Hospitality Management',
        'BSTM' => 'Bachelor of Science in Tourism Management',
        'BLIS' => 'Bachelor of Library and Information Science',
        'PhD' => 'Doctor of Philosophy',
        'MS' => 'Master of Science',
        'MA' => 'Master of Arts',
        // Add more as needed
    ],
    
    // College codes to full names
    'colleges' => [
        'CEIT' => 'College of Engineering and Information Technology',
        'CAS' => 'College of Arts and Sciences',
        'CAFENR' => 'College of Agriculture, Forestry, Environment and Natural Resources',
        'CVMBS' => 'College of Veterinary Medicine and Biomedical Sciences',
        'CED' => 'College of Education',
        'CEMDS' => 'College of Economics, Management and Development Studies',
        'CSPEAR' => 'College of Sports, Physical Education and Recreation',
        'CCJ' => 'College of Criminal Justice',
        'CON' => 'College of Nursing',
        'CTHM' => 'College of Tourism and Hospitality Management',
        'COM' => 'College of Medicine',
        'GS-OLC' => 'Graduate School - Open Learning College',
        // Add more as needed
    ],
    
    // Position codes to full names
    'positions' => [
        'academic' => 'Academic Faculty',
        'non-academic' => 'Non-Academic Staff',
        'student' => 'Student',
        // Add more as needed
    ]
];

// Helper function to get mapped value with fallback
function getMappedValue(array $mapping, string $key, string $fallback = null) {
    return $mapping[$key] ?? ($fallback ?? $key);
}

// Helper function to reverse map (get code from full name)
function getMappedCode(array $mapping, string $value, string $fallback = null) {
    $code = array_search($value, $mapping);
    return $code !== false ? $code : ($fallback ?? $value);
}

// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header('Location: login.php');
    exit();
}

 $currentRole    = $_SESSION['role'];
 $assignedScope  = strtoupper(trim($_SESSION['assigned_scope']   ?? ''));
 $scopeCategory  = $_SESSION['scope_category']   ?? '';           // e.g. Academic-Student
 $assignedScope1 = $_SESSION['assigned_scope_1'] ?? '';           // e.g. "Multiple: BSIT, BSCS"

// NEW: Resolve this admin's scope seat (admin_scopes) if applicable
 $myScopeId       = null;
 $myScopeType     = null;
 $myScopeDetails  = [];

if ($currentRole === 'admin' && !empty($scopeCategory)) {
    $scopeStmt = $pdo->prepare("
        SELECT scope_id, scope_type, scope_details
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid'   => $_SESSION['user_id'],
        ':stype' => $scopeCategory,
    ]);
    $scopeRow = $scopeStmt->fetch();

    if ($scopeRow) {
        $myScopeId   = (int)$scopeRow['scope_id'];
        $myScopeType = $scopeRow['scope_type'];

        if (!empty($scopeRow['scope_details'])) {
            $decoded = json_decode($scopeRow['scope_details'], true);
            if (is_array($decoded)) {
                $myScopeDetails = $decoded;
            }
        }
    }
}

/* ==========================================================
   HELPERS: COURSE SCOPE / NORMALIZATION
   ========================================================== */

function normalize_course_code(string $raw): string {
    $s = strtoupper(trim($raw));
    if ($s === '') return 'UNSPECIFIED';

    $s = preg_replace('/[.\-_,]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    $replacements = [
        'BACHELOR OF SCIENCE IN ' => 'BS ',
        'BACHELOR OF SCIENCE '    => 'BS ',
        'BACHELOR OF '            => 'B ',
        'INFORMATION TECHNOLOGY'  => 'IT',
        'COMPUTER SCIENCE'        => 'CS',
        'COMPUTER ENGINEERING'    => 'CPE',
        'ELECTRONICS ENGINEERING' => 'ECE',
        'CIVIL ENGINEERING'       => 'CE',
        'MECHANICAL ENGINEERING'  => 'ME',
        'ELECTRICAL ENGINEERING'  => 'EE',
        'INDUSTRIAL ENGINEERING'  => 'IE',
        'AGRICULTURE'             => 'AGRI',
        'AGRIBUSINESS'            => 'AB',
        'ENVIRONMENTAL SCIENCE'   => 'ES',
        'FOOD TECHNOLOGY'         => 'FT',
        'FORESTRY'                => 'FOR',
        'AGRICULTURAL AND BIOSYSTEMS ENGINEERING' => 'ABE',
        'AGRICULTURAL ENTREPRENEURSHIP'           => 'AE',
        'LAND USE DESIGN AND MANAGEMENT'          => 'LDM',
        'BIOLOGY'                 => 'BIO',
        'CHEMISTRY'               => 'CHEM',
        'MATHEMATICS'             => 'MATH',
        'PHYSICS'                 => 'PHYSICS',
        'PSYCHOLOGY'              => 'PSYCH',
        'ENGLISH LANGUAGE STUDIES'=> 'ELS',
        'COMMUNICATION'           => 'COMM',
        'STATISTICS'              => 'STAT',
        'CRIMINOLOGY'             => 'CRIM',
        'NURSING'                 => 'N',
        'HOSPITALITY MANAGEMENT'  => 'HM',
        'TOURISM MANAGEMENT'      => 'TM',
        'LIBRARY AND INFORMATION SCIENCE' => 'LIS',
        'LIBRARY & INFORMATION SCIENCE'   => 'LIS',
        'EXERCISE AND SPORTS SCIENCES'    => 'ESS',
        'OFFICE ADMINISTRATION'   => 'OA',
        'ENTREPRENEURSHIP'        => 'ENT',
        'ECONOMICS'               => 'ECO',
        'ACCOUNTANCY'             => 'ACC',
        'SECONDARY EDUCATION'     => 'SED',
        'ELEMENTARY EDUCATION'    => 'EED',
        'PHYSICAL EDUCATION'      => 'PE',
        'TECHNOLOGY AND LIVELIHOOD EDUCATION' => 'TLE',
        'PRE VETERINARY'          => 'PV',
        'VETERINARY MEDICINE'     => 'DVM',
    ];
    foreach ($replacements as $from => $to) {
        $s = str_replace($from, $to, $s);
    }

    $s       = preg_replace('/\s+/', ' ', trim($s));
    $noSpace = str_replace(' ', '', $s);

    $patterns = [
        '/^BSIT$/'      => 'BSIT',
        '/^BSCS$/'      => 'BSCS',
        '/^BSCPE$/'     => 'BSCpE',
        '/^BSECE$/'     => 'BSECE',
        '/^BSCE$/'      => 'BSCE',
        '/^BSME$/'      => 'BSME',
        '/^BSEE$/'      => 'BSEE',
        '/^BSIE$/'      => 'BSIE',
        '/^BSAGRI$/'    => 'BSAgri',
        '/^BSAB$/'      => 'BSAB',
        '/^BSES$/'      => 'BSES',
        '/^BSFT$/'      => 'BSFT',
        '/^BSFOR$/'     => 'BSFor',
        '/^BSABE$/'     => 'BSABE',
        '/^BAE$/'       => 'BAE',
        '/^BSLDM$/'     => 'BSLDM',
        '/^BSBIO$/'     => 'BSBio',
        '/^BSCHEM$/'    => 'BSChem',
        '/^BSMATH$/'    => 'BSMath',
        '/^BSPHYSICS$/' => 'BSPhysics',
        '/^BSPSYCH$/'   => 'BSPsych',
        '/^BAELS$/'     => 'BAELS',
        '/^BACOMM$/'    => 'BAComm',
        '/^BSSTAT$/'    => 'BSStat',
        '/^DVM$/'       => 'DVM',
        '/^BSPV$/'      => 'BSPV',
        '/^BEED$/'      => 'BEEd',
        '/^BSED$/'      => 'BSEd',
        '/^BPE$/'       => 'BPE',
        '/^BTLE$/'      => 'BTLE',
        '/^BSBA$/'      => 'BSBA',
        '/^BSACC$/'     => 'BSAcc',
        '/^BSECO$/'     => 'BSEco',
        '/^BSENT$/'     => 'BSEnt',
        '/^BSOA$/'      => 'BSOA',
        '/^BSESS$/'     => 'BSESS',
        '/^BSCRIM$/'    => 'BSCrim',
        '/^BSN$/'       => 'BSN',
        '/^BSHM$/'      => 'BSHM',
        '/^BSTM$/'      => 'BSTM',
        '/^BLIS$/'      => 'BLIS',
        '/^PHD$/'       => 'PhD',
        '/^MS$/'        => 'MS',
        '/^MA$/'        => 'MA',
    ];
    foreach ($patterns as $regex => $code) {
        if (preg_match($regex, $noSpace)) {
            return $code;
        }
    }

    return $noSpace !== '' ? $noSpace : 'UNSPECIFIED';
}

/**
 * Parse course scope like:
 *   "Multiple: BSIT, BSCS"
 *   "BSIT, BSCS"
 *   "All"
 * returns normalized codes e.g. ['BSIT','BSCS'].
 */
function parse_normalized_course_scope(?string $scopeString): array {
    if ($scopeString === null) return [];
    $clean = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $scopeString);
    $parts = array_filter(array_map('trim', explode(',', $clean)));
    $codes = [];
    foreach ($parts as $p) {
        if ($p === '' || strcasecmp($p, 'All') === 0) continue;
        $codes[] = strtoupper(normalize_course_code($p));
    }
    return array_unique($codes);
}

/* ==========================================================
   BUILD CONDITIONS
   ========================================================== */

 $conditions        = ["role = 'voter'"];
 $params            = [];
 $columns           = [];
 $filterOptions     = [];
 $courseScopeCodes  = [];  // used later for Academic-Student scope

// 1) SUPER ADMIN – sees all voters
if ($currentRole === 'super_admin') {

    $columns = ['Photo', 'Name', 'Position', 'College', 'Department', 'Course', 'Actions'];

    $filterOptions['positions'] = $pdo->query("
        SELECT DISTINCT position 
        FROM users 
        WHERE role = 'voter' 
        ORDER BY position ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $filterOptions['colleges']  = $pdo->query("
        SELECT DISTINCT 
            IF(position = 'academic', department, 'ALL STUDENTS') as college 
        FROM users 
        WHERE role = 'voter' 
        ORDER BY college ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $filterOptions['departments'] = $pdo->query("
        SELECT DISTINCT 
            IF(position = 'academic', department1, department) as dept 
        FROM users 
        WHERE role = 'voter' 
          AND (department IS NOT NULL OR department1 IS NOT NULL) 
        ORDER BY dept ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $filterOptions['courses']   = $pdo->query("
        SELECT DISTINCT course 
        FROM users 
        WHERE role = 'voter' 
          AND course IS NOT NULL 
          AND course != '' 
        ORDER BY course ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    // Convert filter options to full names for display
    $filterOptions['positions'] = array_map(function($pos) use ($mappingSystem) {
        return getMappedValue($mappingSystem['positions'], $pos, $pos);
    }, $filterOptions['positions']);
    
    // Keep colleges as codes
    $filterOptions['colleges'] = array_map(function($college) use ($mappingSystem) {
        return getMappedCode($mappingSystem['colleges'], $college, $college);
    }, $filterOptions['colleges']);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);
    
    $filterOptions['courses'] = array_map(function($course) use ($mappingSystem) {
        return getMappedValue($mappingSystem['courses'], $course, $course);
    }, $filterOptions['courses']);
}

// 2) NON-ACADEMIC - STUDENT ADMIN (org-based, scope_category = Non-Academic-Student)
//    Manages student users tied to their scope seat via users.owner_scope_id.
else if ($scopeCategory === 'Non-Academic-Student' && $myScopeId !== null) {

    $conditions[]             = "position = 'student'";
    $conditions[]             = "owner_scope_id = :ownerScopeId";
    $params[':ownerScopeId']  = $myScopeId;

    $columns = ['Photo', 'Name', 'Student Number', 'College', 'Department', 'Course', 'Actions'];

    // Optional filter options limited to this scope's members
    $stmtCol = $pdo->prepare("
        SELECT DISTINCT department 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student'
          AND owner_scope_id = :sid
          AND department IS NOT NULL 
          AND department != '' 
        ORDER BY department ASC
    ");
    $stmtCol->execute([':sid' => $myScopeId]);
    $filterOptions['colleges'] = $stmtCol->fetchAll(PDO::FETCH_COLUMN);

    $stmtDept = $pdo->prepare("
        SELECT DISTINCT department1 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student'
          AND owner_scope_id = :sid
          AND department1 IS NOT NULL 
          AND department1 != '' 
        ORDER BY department1 ASC
    ");
    $stmtDept->execute([':sid' => $myScopeId]);
    $filterOptions['departments'] = $stmtDept->fetchAll(PDO::FETCH_COLUMN);

    $stmtCourse = $pdo->prepare("
        SELECT DISTINCT course 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student'
          AND owner_scope_id = :sid
          AND course IS NOT NULL 
          AND course != '' 
        ORDER BY course ASC
    ");
    $stmtCourse->execute([':sid' => $myScopeId]);
    $filterOptions['courses'] = $stmtCourse->fetchAll(PDO::FETCH_COLUMN);
    
    // Keep colleges as codes
    $filterOptions['colleges'] = array_map(function($college) use ($mappingSystem) {
        return getMappedCode($mappingSystem['colleges'], $college, $college);
    }, $filterOptions['colleges']);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);
    
    $filterOptions['courses'] = array_map(function($course) use ($mappingSystem) {
        return getMappedValue($mappingSystem['courses'], $course, $course);
    }, $filterOptions['courses']);
}

// 3) OTHERS - DEFAULT ADMIN (scope_category = Others-Default)
//    Manages ONLY its own "Others" members (faculty + non-academic) via owner_scope_id + is_other_member.
else if ($scopeCategory === 'Others-Default' && $myScopeId !== null) {

    $conditions[]            = "(position = 'academic' OR position = 'non-academic')";
    $conditions[]            = "owner_scope_id = :ownerScopeId";
    $conditions[]            = "is_other_member = 1";   // NEW: only rows explicitly marked as Others members
    $params[':ownerScopeId'] = $myScopeId;

    // Show both College (for academic) and Department (for non-ac)
    $columns = ['Photo', 'Name', 'Employee Number', 'Position', 'Status', 'College', 'Department', 'Actions'];

    // Colleges filter (academic staff only under this scope)
    $stmtCollege = $pdo->prepare("
        SELECT DISTINCT department
        FROM users
        WHERE role = 'voter'
          AND position = 'academic'
          AND owner_scope_id = :sid
          AND is_other_member = 1
          AND department IS NOT NULL
          AND department != ''
        ORDER BY department ASC
    ");
    $stmtCollege->execute([':sid' => $myScopeId]);
    $filterOptions['colleges'] = $stmtCollege->fetchAll(PDO::FETCH_COLUMN);
    
    // Keep colleges as codes
    $filterOptions['colleges'] = array_map(function($college) use ($mappingSystem) {
        return getMappedCode($mappingSystem['colleges'], $college, $college);
    }, $filterOptions['colleges']);

    // Filter options limited to this scope's members
    $stmtStatus = $pdo->prepare("
        SELECT DISTINCT status 
        FROM users 
        WHERE role = 'voter' 
          AND (position = 'academic' OR position = 'non-academic')
          AND owner_scope_id = :sid
          AND is_other_member = 1
        ORDER BY status ASC
    ");
    $stmtStatus->execute([':sid' => $myScopeId]);
    $filterOptions['statuses'] = $stmtStatus->fetchAll(PDO::FETCH_COLUMN);

    // DEPARTMENTS filter:
    //  - academic staff   → department1 (full department name)
    //  - non-ac staff     → department (code, e.g. LIBRARY)
    $stmtDept = $pdo->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN position = 'academic'     THEN department1
                WHEN position = 'non-academic' THEN department
                ELSE NULL
            END AS dept
        FROM users 
        WHERE role = 'voter' 
          AND (position = 'academic' OR position = 'non-academic')
          AND owner_scope_id = :sid
          AND is_other_member = 1
          AND (
               (position = 'academic'     AND department1 IS NOT NULL AND department1 != '')
            OR (position = 'non-academic' AND department  IS NOT NULL AND department  != '')
          )
        ORDER BY dept ASC
    ");
    $stmtDept->execute([':sid' => $myScopeId]);
    $filterOptions['departments'] = $stmtDept->fetchAll(PDO::FETCH_COLUMN);
    // For Others-Default, we keep raw values (full dept name or code)
}

// 3b) COLLEGE FACULTY ADMINS (Academic-Faculty) – CEIT, CAS, etc.
//     NOTE: hide Others-Default members (is_other_member = 1)
else if ($scopeCategory === 'Academic-Faculty' && in_array($assignedScope, [
    'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
    'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
])) {

    $conditions[] = "position = 'academic'";
    // HIDE users owned by Others-Default admin
    $conditions[] = "is_other_member = 0";
    
    // For college, we use the code directly (as stored in department field)
    $conditions[] = "UPPER(TRIM(department)) = :college";
    $params[':college'] = $assignedScope;

    // If admin has specific department scope (e.g. DCEE), limit further
    if (!empty($assignedScope1) && strcasecmp($assignedScope1, 'All') !== 0) {
        // Map department code to full name for comparison
        $deptFullName = getMappedValue($mappingSystem['departments'], $assignedScope1);
        
        $conditions[] = "department1 = :deptScope";
        $params[':deptScope'] = $deptFullName;
    }

    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'College', 'Department', 'Actions'];

    // Filter options (statuses + departments) within this faculty scope
    $stmtStatus = $pdo->prepare("
        SELECT DISTINCT status
        FROM users
        WHERE role = 'voter'
          AND position = 'academic'
          AND UPPER(TRIM(department)) = :college
          AND is_other_member = 0
        ORDER BY status ASC
    ");
    $stmtStatus->execute([':college' => $assignedScope]);
    $filterOptions['statuses'] = $stmtStatus->fetchAll(PDO::FETCH_COLUMN);

    $deptParams = [':college' => $assignedScope];

    $deptSql = "
        SELECT DISTINCT department1
        FROM users
        WHERE role = 'voter'
          AND position = 'academic'
          AND UPPER(TRIM(department)) = :college
          AND department1 IS NOT NULL
          AND department1 != ''
          AND is_other_member = 0
    ";

    if (!empty($assignedScope1) && strcasecmp($assignedScope1, 'All') !== 0) {
        $deptFullName = getMappedValue($mappingSystem['departments'], $assignedScope1);
        $deptSql .= " AND department1 = :deptScope";
        $deptParams[':deptScope'] = $deptFullName;
    }

    $deptSql .= " ORDER BY department1 ASC";

    $stmtDept = $pdo->prepare($deptSql);
    $stmtDept->execute($deptParams);
    $filterOptions['departments'] = $stmtDept->fetchAll(PDO::FETCH_COLUMN);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);
}

// 4) COLLEGE STUDENT ADMINS (Academic-Student) – CEIT, CAS, etc.
//    Only students from their college + optional course-scope (BSIT, BSCS, ...)
else if ($scopeCategory === 'Academic-Student' && in_array($assignedScope, [
    'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
    'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
])) {

    $conditions[] = "position = 'student'";
    
    // Map college code to full name
    $collegeFullName = getMappedValue($mappingSystem['colleges'], $assignedScope);
    $conditions[] = "UPPER(TRIM(department)) = :scope";
    $params[':scope'] = $collegeFullName;

    $columns = ['Photo', 'Name', 'Student Number', 'College', 'Department', 'Course', 'Actions'];

    // Base filters for students in this college
    $stmtCourses = $pdo->prepare("
        SELECT DISTINCT course 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student' 
          AND UPPER(TRIM(department)) = :scope 
          AND course IS NOT NULL 
          AND course != '' 
        ORDER BY course ASC
    ");
    $stmtCourses->execute([':scope' => $collegeFullName]);
    $filterOptions['courses'] = $stmtCourses->fetchAll(PDO::FETCH_COLUMN);

    $stmtDepts = $pdo->prepare("
        SELECT DISTINCT department1 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student' 
          AND UPPER(TRIM(department)) = :scope 
          AND department1 IS NOT NULL 
          AND department1 != '' 
        ORDER BY department1 ASC
    ");
    $stmtDepts->execute([':scope' => $collegeFullName]);
    $filterOptions['departments'] = $stmtDepts->fetchAll(PDO::FETCH_COLUMN);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);

    // If this admin is Academic-Student, apply course scope from assigned_scope_1
    if ($scopeCategory === 'Academic-Student') {
        $courseScopeCodes = parse_normalized_course_scope($assignedScope1); // e.g. ['BSIT','BSCS']
        if (!empty($courseScopeCodes)) {
            // Map course codes to full names for comparison
            $mappedCourseNames = array_map(function($code) use ($mappingSystem) {
                return getMappedValue($mappingSystem['courses'], $code);
            }, $courseScopeCodes);
            
            $filterOptions['courses'] = array_values(array_filter(
                $filterOptions['courses'],
                function ($c) use ($mappedCourseNames) {
                    return in_array($c, $mappedCourseNames, true);
                }
            ));
        }
    }
    
    $filterOptions['courses'] = array_map(function($course) use ($mappingSystem) {
        return getMappedValue($mappingSystem['courses'], $course, $course);
    }, $filterOptions['courses']);
}

// 5) Faculty Association Admin - academic faculty
//    NOTE: hide Others-Default members
else if ($assignedScope === 'FACULTY ASSOCIATION') {

    $conditions[] = "position = 'academic'";
    $conditions[] = "is_other_member = 0";  // hide Others-Default users
    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'College', 'Department', 'Actions'];

    $filterOptions['statuses'] = $pdo->query("
        SELECT DISTINCT status 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'academic'
          AND is_other_member = 0
        ORDER BY status ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $filterOptions['departments'] = $pdo->query("
        SELECT DISTINCT department1 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'academic' 
          AND is_other_member = 0
          AND department1 IS NOT NULL 
          AND department1 != '' 
        ORDER BY department1 ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);
}

// 6) Non-Academic Admin / Non-Academic-Employee scope - non-academic staff
//    NOTE: hide Others-Default members (is_other_member = 1)
else if ($scopeCategory === 'Non-Academic-Employee' || $assignedScope === 'NON-ACADEMIC') {

    $conditions[] = "position = 'non-academic'";
    $conditions[] = "is_other_member = 0";  // hide Others-Default users
    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'Department', 'Actions'];

    // --- apply department scope for Non-Academic-Employee using scope_details ---
    $allowedDeptCodes = [];

    if ($scopeCategory === 'Non-Academic-Employee') {
        // First try to get from scope_details
        if (!empty($myScopeDetails) && isset($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
            $allowedDeptCodes = array_filter(array_map('trim', $myScopeDetails['departments']));
        }
        // Fallback: if scope_details is empty, use assigned_scope
        else if (!empty($assignedScope)) {
            $allowedDeptCodes = [$assignedScope];
        }
    }

    // If may specific department scope, restrict query with IN (...)
    if (!empty($allowedDeptCodes)) {
        $placeholders = [];
        foreach ($allowedDeptCodes as $idx => $code) {
            $ph = ':deptScope' . $idx;
            $placeholders[]      = $ph;
            $params[$ph]         = $code;  // users.department stores codes (e.g. 'LIBRARY','ADMIN')
        }
        $conditions[] = "department IN (" . implode(',', $placeholders) . ")";
    }

    // --- Filter options (statuses + departments) ---
    $sqlStatus = "
        SELECT DISTINCT status 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'non-academic'
          AND is_other_member = 0
    ";
    $sqlDept = "
        SELECT DISTINCT department 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'non-academic'
          AND is_other_member = 0
          AND department IS NOT NULL 
          AND department != ''
    ";

    // If may specific departments, limit filterOptions to those department codes
    if (!empty($allowedDeptCodes)) {
        $inPh = [];
        $statusParams = [];
        $deptParams   = [];
        foreach ($allowedDeptCodes as $idx => $code) {
            $ph = ':deptF' . $idx;
            $inPh[]             = $ph;
            $statusParams[$ph]  = $code;
            $deptParams[$ph]    = $code;
        }
        $sqlStatus .= " AND department IN (" . implode(',', $inPh) . ")";
        $sqlDept   .= " AND department IN (" . implode(',', $inPh) . ")";
        
        $stmtStatus = $pdo->prepare($sqlStatus . " ORDER BY status ASC");
        $stmtStatus->execute($statusParams);
        $filterOptions['statuses'] = $stmtStatus->fetchAll(PDO::FETCH_COLUMN);

        $stmtDept = $pdo->prepare($sqlDept . " ORDER BY department ASC");
        $stmtDept->execute($deptParams);
        $filterOptions['departments'] = $stmtDept->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Old behaviour: full non-academic pool
        $filterOptions['statuses'] = $pdo->query($sqlStatus . " ORDER BY status ASC")->fetchAll(PDO::FETCH_COLUMN);
        $filterOptions['departments'] = $pdo->query($sqlDept . " ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
    }

    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);
}

// 7) COOP Admin - COOP members
//    NOTE: hide Others-Default members
else if ($assignedScope === 'COOP') {

    $conditions[] = "is_coop_member = 1";
    $conditions[] = "is_other_member = 0";  // DO NOT include Others-Default pool

    $columns = ['Photo', 'Name', 'Employee Number', 'Status', 'College', 'Department', 'MIGS Status', 'Actions'];

    $filterOptions['statuses'] = $pdo->query("
        SELECT DISTINCT status 
        FROM users 
        WHERE role = 'voter' 
          AND is_coop_member = 1
          AND is_other_member = 0
        ORDER BY status ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $filterOptions['colleges'] = $pdo->query("
        SELECT DISTINCT department 
        FROM users 
        WHERE role = 'voter' 
          AND is_coop_member = 1
          AND is_other_member = 0
          AND department IS NOT NULL 
          AND department != '' 
        ORDER BY department ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    // Keep colleges as codes
    $filterOptions['colleges'] = array_map(function($college) use ($mappingSystem) {
        return getMappedCode($mappingSystem['colleges'], $college, $college);
    }, $filterOptions['colleges']);

    $filterOptions['departments'] = $pdo->query("
        SELECT DISTINCT 
            IF(position = 'academic', department1, department) as dept 
        FROM users 
        WHERE role = 'voter' 
          AND is_coop_member = 1
          AND is_other_member = 0
          AND (department IS NOT NULL OR department1 IS NOT NULL) 
        ORDER BY dept ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);
}

// 8) CSG Admin - all students (GLOBAL ONLY: exclude org-specific Non-Academic-Student uploads)
else if ($assignedScope === 'CSG ADMIN') {

    $conditions[] = "position = 'student'";
    // EXCLUDE org-owned student voters (Non-Academic-Student CSV uploads)
    $conditions[] = "owner_scope_id IS NULL";

    $columns = ['Photo', 'Name', 'Student Number', 'College', 'Department', 'Course', 'Actions'];

    // Colleges filter: only from global students (no owner_scope_id)
    $filterOptions['colleges'] = $pdo->query("
        SELECT DISTINCT department 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student'
          AND owner_scope_id IS NULL
          AND department IS NOT NULL 
          AND department != '' 
        ORDER BY department ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    // Keep colleges as codes
    $filterOptions['colleges'] = array_map(function($college) use ($mappingSystem) {
        return getMappedCode($mappingSystem['colleges'], $college, $college);
    }, $filterOptions['colleges']);

    // Departments filter: from global students only
    $filterOptions['departments'] = $pdo->query("
        SELECT DISTINCT department1 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student'
          AND owner_scope_id IS NULL
          AND department1 IS NOT NULL 
          AND department1 != '' 
        ORDER BY department1 ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $filterOptions['departments'] = array_map(function($dept) use ($mappingSystem) {
        return getMappedValue($mappingSystem['departments'], $dept, $dept);
    }, $filterOptions['departments']);

    // Courses filter: from global students only
    $filterOptions['courses'] = $pdo->query("
        SELECT DISTINCT course 
        FROM users 
        WHERE role = 'voter' 
          AND position = 'student'
          AND owner_scope_id IS NULL
          AND course IS NOT NULL 
          AND course != '' 
        ORDER BY course ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $filterOptions['courses'] = array_map(function($course) use ($mappingSystem) {
        return getMappedValue($mappingSystem['courses'], $course, $course);
    }, $filterOptions['courses']);
}

/* ==========================================================
   GET FILTERS FROM GET
   ========================================================== */

 $filterPosition   = $_GET['position']   ?? '';
 $filterStatus     = $_GET['status']     ?? '';
 $filterCollege    = $_GET['college']    ?? '';
 $filterDepartment = $_GET['department'] ?? '';
 $filterCourse     = $_GET['course']     ?? '';

// Apply additional filters if set
if (!empty($filterPosition) && isset($filterOptions['positions']) && in_array($filterPosition, $filterOptions['positions'])) {
    // Map position full name to code for database query
    $positionCode = getMappedCode($mappingSystem['positions'], $filterPosition, $filterPosition);
    $conditions[] = "position = :position";
    $params[':position'] = $positionCode;
}

if (!empty($filterStatus) && isset($filterOptions['statuses']) && in_array($filterStatus, $filterOptions['statuses'])) {
    $conditions[] = "status = :status";
    $params[':status'] = $filterStatus;
}

// College filter - keep as code
if (!empty($filterCollege) && isset($filterOptions['colleges']) && in_array($filterCollege, $filterOptions['colleges'])) {
    $conditions[] = "department = :college";
    $params[':college'] = $filterCollege;
}

// Department filter
if (!empty($filterDepartment) && isset($filterOptions['departments']) && in_array($filterDepartment, $filterOptions['departments'])) {

    // SPECIAL CASE: Others-Default admin
    // - academic staff   → department1 = full dept name
    // - non-ac staff     → department  = dept code
    if ($scopeCategory === 'Others-Default') {
        $conditions[] = "(
            (position = 'academic'     AND department1 = :department)
         OR (position = 'non-academic' AND department  = :department)
        )";
        // Use the exact value selected in dropdown (e.g. "Department of IT" or "LIBRARY")
        $params[':department'] = $filterDepartment;

    } else {
        // Generic behavior for other admin types
        // Map department full name to code for database query
        $deptCode = getMappedCode($mappingSystem['departments'], $filterDepartment, $filterDepartment);
        
        if ($assignedScope === 'NON-ACADEMIC' || $scopeCategory === 'Non-Academic-Employee') {
            $conditions[] = "department = :department";
        } else {
            $conditions[] = "department1 = :department";
        }
        $params[':department'] = $deptCode;
    }
}

// Course filter (only within what's allowed)
if (!empty($filterCourse) && isset($filterOptions['courses']) && in_array($filterCourse, $filterOptions['courses'])) {
    // Map course full name to code for database query
    $courseCode = getMappedCode($mappingSystem['courses'], $filterCourse, $filterCourse);
    $conditions[] = "course = :course";
    $params[':course'] = $courseCode;
}

/* ==========================================================
   BUILD & EXECUTE QUERY
   ========================================================== */

 $sql = "SELECT * FROM users WHERE " . implode(' AND ', $conditions) . " ORDER BY user_id DESC";
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $users = $stmt->fetchAll();

/* ==========================================================
   APPLY COURSE SCOPE ON FETCHED USERS (Academic-Student)
   So CEIT + BSIT, BSCS admin will only see those courses.
   ========================================================== */

if (
    $currentRole === 'admin' &&
    $scopeCategory === 'Academic-Student' &&
    in_array($assignedScope, ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC']) &&
    !empty($courseScopeCodes)
) {
    $users = array_values(array_filter($users, function($u) use ($courseScopeCodes) {
        $code = strtoupper(normalize_course_code($u['course'] ?? ''));
        return in_array($code, $courseScopeCodes, true);
    }));
}

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Users - Admin Panel</title>
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
    .btn-primary { background-color: var(--cvsu-green); transition: all 0.3s ease; }
    .btn-primary:hover {
      background-color: var(--cvsu-green-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .btn-danger { background-color: #ef4444; transition: all 0.3s ease; }
    .btn-danger:hover {
      background-color: #dc2626;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .btn-info { background-color: #3b82f6; transition: all 0.3s ease; }
    .btn-info:hover {
      background-color: #2563eb;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .btn-edit { background-color: var(--cvsu-yellow); transition: all 0.3s ease; }
    .btn-edit:hover {
      background-color: #e6c200;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .status-badge {
      display:inline-flex;align-items:center;
      padding:0.25rem 0.75rem;border-radius:9999px;
      font-size:0.75rem;font-weight:600;
    }
    .table-hover tbody tr:hover { background-color:#f3f4f6; }
    /* Toast */
    .toast {
      position:fixed;bottom:20px;right:20px;
      padding:16px 24px;border-radius:8px;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
      z-index:1000;display:flex;align-items:center;
      color:white;font-weight:500;
      transform:translateY(100px);opacity:0;
      transition:all 0.3s ease;
    }
    .toast.show { transform:translateY(0);opacity:1; }
    .toast.success { background-color:#10b981; }
    .toast.error   { background-color:#ef4444; }
    .toast i { margin-right:8px; }

    .filter-container { display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end; }
    .filter-group { flex:1;min-width:180px; }
    .filter-group label {
      display:block;font-size:0.875rem;font-weight:600;
      color:#374151;margin-bottom:0.25rem;
    }
    .filter-group select {
      width:100%;border:1px solid #d1d5db;border-radius:0.375rem;
      padding:0.5rem 0.75rem;font-size:0.875rem;line-height:1.25rem;
      color:#1f2937;background-color:#fff;
      transition:border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .filter-group select:focus {
      outline:none;border-color:var(--cvsu-green);
      box-shadow:0 0 0 3px rgba(30, 111, 70, 0.1);
    }
    .reset-button-container { display:flex;align-items:flex-end; }
    .reset-button {
      white-space:nowrap;background-color:#e5e7eb;color:#374151;
      font-weight:500;padding:0.5rem 1rem;border-radius:0.375rem;
      transition:background-color 0.15s ease-in-out,color 0.15s ease-in-out;
      display:flex;align-items:center;
    }
    .reset-button:hover { background-color:#d1d5db;color:#1f2937; }

    @media (max-width:768px) {
      .filter-container { flex-direction:column;gap:0.75rem; }
      .filter-group { min-width:100%; }
      .reset-button-container { width:100%;justify-content:flex-end; }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <div class="fixed inset-0 opacity-5 pointer-events-none">
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"2\" fill=\"%23154734\"/></svg>'); background-size: 20px 20px;"></div>
  </div>
  
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
            <h1 class="text-3xl font-extrabold">Manage Users</h1>
            <p class="text-green-100 mt-1">
              <?php 
              if ($currentRole === 'super_admin') {
                echo "All registered users in the system";
              } else if ($scopeCategory === 'Non-Academic-Student') {
                echo "Organization members under your scope";
              } else if ($scopeCategory === 'Others-Default') {
                echo "Faculty and Non-Academic employees under your scope";
              } else if ($assignedScope === 'FACULTY ASSOCIATION') {
                echo "Faculty Association members";
              } else if ($scopeCategory === 'Non-Academic-Employee' || $assignedScope === 'NON-ACADEMIC') {
                // Display department scope if applicable
                if ($scopeCategory === 'Non-Academic-Employee') {
                    if (!empty($myScopeDetails) && isset($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
                        $deptNames = array_map(function($code) use ($mappingSystem) {
                            return getMappedValue($mappingSystem['departments'], $code, $code);
                        }, $myScopeDetails['departments']);
                        echo "Non-Academic staff (" . implode(', ', array_map('htmlspecialchars', $deptNames)) . ")";
                    } else if (!empty($assignedScope)) {
                        $deptName = getMappedValue($mappingSystem['departments'], $assignedScope, $assignedScope);
                        echo "Non-Academic staff (" . htmlspecialchars($deptName) . ")";
                    } else {
                        echo "Non-Academic staff";
                    }
                } else {
                    echo "Non-Academic staff";
                }
              } else if ($assignedScope === 'COOP') {
                echo "COOP members";
              } else if ($assignedScope === 'CSG ADMIN') {
                echo "All student voters (Global only)";
              } else {
                if ($scopeCategory === 'Academic-Faculty' && in_array($assignedScope, [
                    'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
                    'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
                ])) {
                    $collegeName = getMappedValue($mappingSystem['colleges'], $assignedScope);
                    echo htmlspecialchars($collegeName) . " faculty";
                    
                    if (!empty($assignedScope1) && strcasecmp($assignedScope1, 'All') !== 0) {
                        $deptName = getMappedValue($mappingSystem['departments'], $assignedScope1);
                        echo " (" . htmlspecialchars($deptName) . ")";
                    }
                } elseif ($scopeCategory === 'Academic-Student' && in_array($assignedScope, [
                    'CAFENR','CEIT','CAS','CVMBS','CED','CEMDS',
                    'CSPEAR','CCJ','CON','CTHM','COM','GS-OLC'
                ])) {
                    $collegeName = getMappedValue($mappingSystem['colleges'], $assignedScope);
                    echo htmlspecialchars($collegeName) . " students";
                    
                    if (!empty($assignedScope1) && strcasecmp($assignedScope1, 'All') !== 0) {
                        $courses = parse_normalized_course_scope($assignedScope1);
                        $courseNames = array_map(function($code) use ($mappingSystem) {
                            return getMappedValue($mappingSystem['courses'], $code);
                        }, $courses);
                        echo " (" . implode(', ', array_map('htmlspecialchars', $courseNames)) . ")";
                    }
                } else {
                    echo "Users";
                }
              }
              ?>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_add_user.php" class="btn-primary text-white px-4 py-2 rounded font-semibold transition">
            <i class="fas fa-user-plus mr-2"></i>Add User
          </a>
          <a href="admin_restrict_users.php" class="btn-danger text-white px-4 py-2 rounded font-semibold transition">
            <i class="fas fa-user-slash mr-2"></i>Restrict Users
          </a>
          <?php if ($assignedScope === 'COOP'): ?>
            <a href="admin_migs_status.php" class="btn-info text-white px-4 py-2 rounded font-semibold transition">
              <i class="fas fa-id-card mr-2"></i>MIGS Status
            </a>
          <?php endif; ?>
        </div>
      </header>
      
      <?php if (isset($_SESSION['message'])): ?>
        <div class="mb-6 p-4 rounded <?= $_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
          <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php 
        unset($_SESSION['message'], $_SESSION['message_type']);
        ?>
      <?php endif; ?>
      
      <!-- Filters Section -->
      <div class="mb-6 bg-white p-4 rounded shadow">
        <div class="filter-container">
          <?php if (isset($filterOptions['positions'])): ?>
          <div class="filter-group">
            <label for="position">Filter by Position:</label>
            <select id="position" name="position" onchange="filter()">
              <option value="">All Positions</option>
              <?php foreach ($filterOptions['positions'] as $position): ?>
                <option value="<?= htmlspecialchars($position) ?>" <?= ($position == $filterPosition) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($position) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          
          <?php if (isset($filterOptions['statuses'])): ?>
          <div class="filter-group">
            <label for="status">Filter by Status:</label>
            <select id="status" name="status" onchange="filter()">
              <option value="">All Statuses</option>
              <?php foreach ($filterOptions['statuses'] as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= ($status == $filterStatus) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($status) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          
          <?php if (isset($filterOptions['colleges'])): ?>
          <div class="filter-group">
            <label for="college">Filter by College:</label>
            <select id="college" name="college" onchange="filter()">
              <option value="">All Colleges</option>
              <?php foreach ($filterOptions['colleges'] as $college): ?>
                <option value="<?= htmlspecialchars($college) ?>" <?= ($college == $filterCollege) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($college) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          
          <?php if (isset($filterOptions['departments'])): ?>
          <div class="filter-group">
            <label for="department">Filter by Department:</label>
            <select id="department" name="department" onchange="filter()">
              <option value="">All Departments</option>
              <?php foreach ($filterOptions['departments'] as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>" <?= ($dept == $filterDepartment) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($dept) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          
          <?php if (isset($filterOptions['courses'])): ?>
          <div class="filter-group">
            <label for="course">Filter by Course:</label>
            <select id="course" name="course" onchange="filter()">
              <option value="">All Courses</option>
              <?php foreach ($filterOptions['courses'] as $course): ?>
                <option value="<?= htmlspecialchars($course) ?>" <?= ($course == $filterCourse) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($course) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          
          <div class="reset-button-container">
            <a href="admin_manage_users.php" class="reset-button">
              <i class="fas fa-sync-alt mr-2"></i>Reset Filters
            </a>
          </div>
        </div>
      </div>
      
      <!-- Pagination header -->
      <div class="mb-4 flex justify-between items-center">
        <div class="text-sm text-gray-700">
          Showing <span id="showing-start">1</span> to <span id="showing-end">20</span> of <span id="total-records"><?= count($users) ?></span> users
        </div>
        <div class="flex space-x-2">
          <button id="prev-page" class="px-3 py-1 border rounded text-gray-600 hover:bg-gray-100 disabled:opacity-50" disabled>
            <i class="fas fa-chevron-left"></i> Previous
          </button>
          <div id="page-numbers" class="flex space-x-1"></div>
          <button id="next-page" class="px-3 py-1 border rounded text-gray-600 hover:bg-gray-100">
            Next <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
      
      <!-- Users Table -->
      <div class="overflow-x-auto bg-white rounded shadow-lg">
        <table class="min-w-full table-auto table-hover" role="table" aria-label="Users list">
          <thead class="bg-[var(--cvsu-green)] text-white">
            <tr>
              <?php foreach ($columns as $column): ?>
                <th class="py-2 px-4 text-left text-sm" scope="col"><?= htmlspecialchars($column) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody id="users-table-body">
            <?php if (count($users) > 0): ?>
              <?php foreach ($users as $user): ?>
                <tr class="border-b hover:bg-gray-100">
                  <!-- Photo -->
                  <td class="py-3 px-4">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                      <i class="fas fa-user text-gray-400 text-sm"></i>
                    </div>
                  </td>
                  <!-- Name -->
                  <td class="py-3 px-4 font-medium text-sm">
                    <?= htmlspecialchars(trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name'])) ?>
                  </td>
                  
                  <?php if (in_array('Student Number', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?= !empty($user['student_number']) ? htmlspecialchars($user['student_number']) : '<span class="text-gray-400">Not set</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Employee Number', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?= !empty($user['employee_number']) ? htmlspecialchars($user['employee_number']) : '<span class="text-gray-400">Not set</span>' ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Status', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?php if (!empty($user['status'])): ?>
                      <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                        <?= htmlspecialchars($user['status']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">Not set</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Position', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?php if (!empty($user['position'])): ?>
                      <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">
                        <?= htmlspecialchars(getMappedValue($mappingSystem['positions'], $user['position'], $user['position'])) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">Not assigned</span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('College', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?php 
                    if ($user['position'] === 'student' && !empty($user['department'])) {
                        // Keep colleges as codes
                        echo htmlspecialchars($user['department']);
                    } else if ($user['position'] === 'academic' && !empty($user['department'])) {
                        // Keep colleges as codes
                        echo htmlspecialchars($user['department']);
                    } else if ($user['is_coop_member'] == 1 && $user['position'] === 'academic' && !empty($user['department'])) {
                        // Keep colleges as codes
                        echo htmlspecialchars($user['department']);
                    } else if ($user['position'] === 'non-academic') {
                        echo '<span class="text-gray-400">N/A</span>';
                    } else {
                        echo '<span class="text-gray-400">N/A</span>';
                    }
                    ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Department', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?php 
                    if ($user['position'] === 'student' && !empty($user['department1'])) {
                        // department1 might already be full name; if it's a code, map it
                        $deptFull = getMappedValue($mappingSystem['departments'], $user['department1'], $user['department1']);
                        echo htmlspecialchars($deptFull);
                    } else if ($user['position'] === 'academic' && !empty($user['department1'])) {
                        $deptFull = getMappedValue($mappingSystem['departments'], $user['department1'], $user['department1']);
                        echo htmlspecialchars($deptFull);
                    } else if ($user['is_coop_member'] == 1 && $user['position'] === 'academic' && !empty($user['department1'])) {
                        $deptFull = getMappedValue($mappingSystem['departments'], $user['department1'], $user['department1']);
                        echo htmlspecialchars($deptFull);
                    } else if ($user['is_coop_member'] == 1 && $user['position'] === 'non-academic' && !empty($user['department'])) {
                        // non-academic: department column usually stores codes like LIBRARY, ADMIN, NAEA
                        $deptFull = getMappedValue($mappingSystem['departments'], $user['department'], $user['department']);
                        echo htmlspecialchars($deptFull);
                    } else if ($user['position'] === 'non-academic' && !empty($user['department'])) {
                        $deptFull = getMappedValue($mappingSystem['departments'], $user['department'], $user['department']);
                        echo htmlspecialchars($deptFull);
                    } else {
                        echo '<span class="text-gray-400">N/A</span>';
                    }
                    ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('Course', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?php 
                    if (!empty($user['course'])) {
                        $courseFull = getMappedValue($mappingSystem['courses'], $user['course'], $user['course']);
                        echo htmlspecialchars($courseFull);
                    } else {
                        echo '<span class="text-gray-400">Not set</span>';
                    }
                    ?>
                  </td>
                  <?php endif; ?>
                  
                  <?php if (in_array('MIGS Status', $columns)): ?>
                  <td class="py-3 px-4 text-sm">
                    <?php if ($user['migs_status'] == 1): ?>
                      <span class="status-badge bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>MIGS
                      </span>
                    <?php else: ?>
                      <span class="status-badge bg-gray-100 text-gray-800">
                        <i class="fas fa-times-circle mr-1"></i>Non-MIGS
                      </span>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                  
                  <!-- Actions -->
                  <td class="py-3 px-4 text-center">
                    <div class="flex flex-col space-y-2">
                      <?php if ($assignedScope !== 'COOP'): ?>
                        <button 
                          onclick='triggerEditUser(<?= $user["user_id"] ?>)'
                          class="btn-edit text-white px-3 py-1 rounded text-sm font-medium inline-flex items-center justify-center w-full"
                          aria-label="Edit user">
                          <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                      <?php endif; ?>
                      
                      <?php if ($assignedScope === 'COOP'): ?>
                        <a href="admin_toggle_migs.php?user_id=<?= $user['user_id'] ?>" 
                           class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm font-medium inline-flex items-center justify-center w-full"
                           onclick="return confirm('Are you sure you want to toggle MIGS status for this user?');">
                          <i class="fas fa-sync-alt mr-1"></i>Toggle MIGS
                        </a>
                      <?php endif; ?>
                      
                      <a href="admin_delete_users.php?user_id=<?= $user['user_id'] ?>" 
                         class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium inline-flex items-center justify-center w-full"
                         onclick="return confirm('Are you sure you want to delete this user?');"
                         aria-label="Delete user">
                        <i class="fas fa-trash mr-1"></i>Delete
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?= count($columns) ?>" class="text-center py-6 text-gray-500">
                  No users found. <a href="admin_add_user.php" class="text-green-600 hover:underline">Add your first user</a>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <?php 
      if (file_exists('user_modal_update.php')) {
          include 'user_modal_update.php'; 
      }
      ?>
    </main>
  </div>
  
  <div id="toast-container"></div>
  
<script>
let selectedUser = null;

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
    document.getElementById('toast-container').appendChild(toast);
    setTimeout(() => { toast.classList.add('show'); }, 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => { toast.remove(); }, 300);
    }, 3000);
}

function triggerEditUser(userId) {
  fetch('get_user.php?user_id=' + userId)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        openUpdateModal(data.data);
      } else {
        showToast("User not found.", "error");
      }
    })
    .catch(() => showToast("Fetch failed", "error"));
}

function openUpdateModal(user) {
  selectedUser = user;
  document.getElementById('update_user_id').value      = user.user_id;
  document.getElementById('update_first_name').value   = user.first_name;
  document.getElementById('update_last_name').value    = user.last_name;
  document.getElementById('update_email').value        = user.email;
  document.getElementById('update_department').value   = user.department ?? '';
  document.getElementById('update_course').value       = user.course ?? '';
  document.getElementById('update_position').value     = user.position ?? '';
  document.getElementById('updateModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('update_first_name').focus(), 100);
}

function filter() {
  const position   = document.getElementById('position')   ? document.getElementById('position').value   : '';
  const status     = document.getElementById('status')     ? document.getElementById('status').value     : '';
  const college    = document.getElementById('college')    ? document.getElementById('college').value    : '';
  const department = document.getElementById('department') ? document.getElementById('department').value : '';
  const course     = document.getElementById('course')     ? document.getElementById('course').value     : '';
  
  const url = new URL(window.location.href);
  
  if (position)   url.searchParams.set('position', position);   else url.searchParams.delete('position');
  if (status)     url.searchParams.set('status', status);       else url.searchParams.delete('status');
  if (college)    url.searchParams.set('college', college);     else url.searchParams.delete('college');
  if (department) url.searchParams.set('department', department); else url.searchParams.delete('department');
  if (course)     url.searchParams.set('course', course);       else url.searchParams.delete('course');
  
  window.location.href = url.toString();
}

// Pagination
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['message'])): ?>
        showToast("<?= htmlspecialchars($_SESSION['message']) ?>", "<?= $_SESSION['message_type'] ?? 'success' ?>");
    <?php endif; ?>
    updatePagination();
});

function updatePagination() {
    const rowsPerPage = 20;
    const tableBody   = document.getElementById('users-table-body');
    const rows        = tableBody.querySelectorAll('tr');
    const totalRows   = rows.length;
    const totalPages  = Math.ceil(totalRows / rowsPerPage) || 1;
    
    document.getElementById('total-records').textContent = totalRows;
    
    rows.forEach(row => { row.style.display = 'none'; });
    
    const storedPage  = parseInt(localStorage.getItem('currentPage') || '1');
    const currentPage = Math.min(Math.max(storedPage, 1), totalPages);
    
    const start = (currentPage - 1) * rowsPerPage;
    const end   = Math.min(start + rowsPerPage, totalRows);
    
    for (let i = start; i < end; i++) {
        if (rows[i]) rows[i].style.display = '';
    }
    
    document.getElementById('showing-start').textContent = totalRows === 0 ? 0 : start + 1;
    document.getElementById('showing-end').textContent   = end;
    
    const pageNumbers = document.getElementById('page-numbers');
    pageNumbers.innerHTML = '';
    
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage   = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    const prevButton = document.getElementById('prev-page');
    prevButton.disabled = currentPage === 1;
    prevButton.onclick  = () => goToPage(currentPage - 1);
    
    for (let i = startPage; i <= endPage; i++) {
        const pageButton = document.createElement('button');
        pageButton.className = `px-3 py-1 border rounded ${i === currentPage ? 'bg-[var(--cvsu-green)] text-white' : 'text-gray-600 hover:bg-gray-100'}`;
        pageButton.textContent = i;
        pageButton.onclick     = () => goToPage(i);
        pageNumbers.appendChild(pageButton);
    }
    
    const nextButton = document.getElementById('next-page');
    nextButton.disabled = currentPage === totalPages;
    nextButton.onclick  = () => goToPage(currentPage + 1);
}

function goToPage(page) {
    localStorage.setItem('currentPage', page);
    updatePagination();
}
</script>
</body>
</html>