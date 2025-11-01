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

// Check if we have the CSV file path in session
if (!isset($_SESSION['csv_file_path'])) {
    die("No CSV file to process.");
}

 $csvFilePath = $_SESSION['csv_file_path'];
unset($_SESSION['csv_file_path']); // Clear the session

// Get admin type from session or determine it
 $adminType = $_SESSION['admin_type'] ?? null;

if (!$adminType) {
    // Determine admin type based on assigned scope
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
        $expectedColumns = 8; // first_name, last_name, email, position, employee_number, department, status, is_coop_member
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
 $academicDepartmentMapping = [
    // CEIT Departments
    'department of civil engineering' => 'Department of Civil Engineering',
    'civil engineering department' => 'Department of Civil Engineering',
    'civil engineering' => 'Department of Civil Engineering',
    'Department of Civil Engineering' => 'Department of Civil Engineering',
    
    'department of computer and electronics engineering' => 'Department of Computer and Electronics Engineering',
    'computer and electronics engineering department' => 'Department of Computer and Electronics Engineering',
    'computer and electronics engineering' => 'Department of Computer and Electronics Engineering',
    'Department of Computer and Electronics Engineering' => 'Department of Computer and Electronics Engineering',
    
    'department of industrial engineering and technology' => 'Department of Industrial Engineering and Technology',
    'industrial engineering and technology department' => 'Department of Industrial Engineering and Technology',
    'industrial engineering and technology' => 'Department of Industrial Engineering and Technology',
    'Department of Industrial Engineering and Technology' => 'Department of Industrial Engineering and Technology',
    
    'department of mechanical and electronics engineering' => 'Department of Mechanical and Electronics Engineering',
    'mechanical and electronics engineering department' => 'Department of Mechanical and Electronics Engineering',
    'mechanical and electronics engineering' => 'Department of Mechanical and Electronics Engineering',
    'Department of Mechanical and Electronics Engineering' => 'Department of Mechanical and Electronics Engineering',
    
    'department of information technology' => 'Department of Information Technology',
    'information technology department' => 'Department of Information Technology',
    'information technology' => 'Department of Information Technology',
    'Department of Information Technology' => 'Department of Information Technology',
    
    // CAFENR Departments
    'department of animal science' => 'Department of Animal Science',
    'animal science department' => 'Department of Animal Science',
    'animal science' => 'Department of Animal Science',
    
    'department of agriculture' => 'Department of Agriculture',
    'agriculture department' => 'Department of Agriculture',
    'agriculture' => 'Department of Agriculture',
    
    // CAS Departments
    'department of biological sciences' => 'Department of Biological Sciences',
    'biological sciences department' => 'Department of Biological Sciences',
    'biological sciences' => 'Department of Biological Sciences',
    'Department of Biological Sciences' => 'Department of Biological Sciences',
    
    // CSPEAR Departments
    'department of physical education and recreation' => 'Department of Physical Education and Recreation',
    'physical education and recreation department' => 'Department of Physical Education and Recreation',
    'physical education and recreation' => 'Department of Physical Education and Recreation',
    'Department of Physical Education and Recreation' => 'Department of Physical Education and Recreation',
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
            
            // VALIDATION: Check if college matches assigned scope (except for CSG ADMIN)
            if ($normalizedAssignedScope !== 'CSG ADMIN' && $college !== $normalizedAssignedScope) {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: College '$college' not in your assigned scope '$assignedScope'.";
                continue 2; // Skip to next iteration of while loop
            }
            
            // NO COURSE MAPPING - Store as full name to match registration process
            // The course is already in full name format from the CSV
            
            // Map to database fields
            $department_db = $college;      // Store college in department field
            $department1 = $department;     // Store department in department1 field
            $employee_number = null;
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
            
            // MAP DEPARTMENT
            $department_lower = strtolower($department);
            if (isset($academicDepartmentMapping[$department_lower])) {
                $department = $academicDepartmentMapping[$department_lower];
            }
            
            // VALIDATE AND MAP STATUS
            $status_lower = strtolower($status);
            if (isset($statusMapping[$status_lower])) {
                $status = $statusMapping[$status_lower];
            } elseif (!in_array($status, $allowedStatuses)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid status '$status'. Only Regular, Part-time, or Contractual are allowed.";
                continue 2;
            }
            
            // Map to database fields
            $department_db = $college;      // Store college in department field
            $department1 = $department;     // Store department in department1 field
            $student_number = null;
            $course = null;
            break;
            
        case 'admin_non_academic':
            $first_name = trim($row[0] ?? '');
            $last_name = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');
            $position = trim($row[3] ?? '');
            $employee_number = trim($row[4] ?? '');
            $department = trim($row[5] ?? '');
            $status = trim($row[6] ?? '');
            $is_coop_member = intval(trim($row[7] ?? '0'));
            
            // VALIDATION: Check if position is valid for this admin type
            if (strtolower($position) !== 'non-academic') {
                $restrictedRows++;
                $errorMessages[] = "Row $totalRows: Invalid position '$position' for admin_non_academic. Only 'non-academic' position is allowed.";
                continue 2; // Skip to next iteration of while loop
            }
            
            // MAP DEPARTMENT
            $department_lower = strtolower($department);
            if (isset($nonAcademicDepartmentMapping[$department_lower])) {
                $department = $nonAcademicDepartmentMapping[$department_lower];
            } elseif (!in_array($department, $allowedNonAcademicDepts)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid department '$department'. Department must be one of: " . implode(', ', $allowedNonAcademicDepts);
                continue 2;
            }
            
            // VALIDATE AND MAP STATUS
            $status_lower = strtolower($status);
            if (isset($statusMapping[$status_lower])) {
                $status = $statusMapping[$status_lower];
            } elseif (!in_array($status, $allowedStatuses)) {
                $errors++;
                $errorMessages[] = "Row $totalRows: Invalid status '$status'. Only Regular, Part-time, or Contractual are allowed.";
                continue 2;
            }
            
            // Map to database fields
            $department_db = $department;   // Store department as is (now mapped)
            $department1 = null;            // No department1 for non-academic
            $student_number = null;
            $course = null;
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
                
                // NO COURSE MAPPING - Store as full name to match registration process
                // The course is already in full name format from the CSV
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
        
        $checkPendingStmt = $pdo->prepare("SELECT pending_id FROM pending_users WHERE email = ?");
        $checkPendingStmt->execute([$email]);
        
        if ($checkUserStmt->fetch() || $checkPendingStmt->fetch()) {
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
        
        // Insert into pending_users table - UPDATED to include is_restricted
        $insertStmt = $pdo->prepare("INSERT INTO pending_users 
            (first_name, last_name, email, position, student_number, employee_number, 
             is_coop_member, department, department1, course, status, password, 
             token, expires_at, source, is_restricted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'csv', 0)");
        
        $insertStmt->execute([
            $first_name,
            $last_name,
            $email,
            $position,
            $student_number,
            $employee_number,
            $is_coop_member,
            $department_db,
            $department1,
            $course,
            $status,
            $hashedPassword,
            $token,
            $expiresAt
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
                    echo '<code>first_name, last_name, email, position, employee_number, department, status, is_coop_member</code>';
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
                    if ($normalizedAssignedScope === 'CSG ADMIN') {
                        echo "As a CSG Admin, you can upload students from all colleges.";
                    } else {
                        echo "As a College Admin, you can only upload students from your assigned college: <strong>$assignedScope</strong>.";
                    }
                    echo " You can only upload users with position 'student'.";
                    break;
                case 'admin_academic':
                    echo "As a Faculty Association Admin, you can only upload users with position 'academic'.";
                    break;
                case 'admin_non_academic':
                    echo "As a Non-Academic Admin, you can only upload users with position 'non-academic'.";
                    break;
                case 'admin_coop':
                    echo "As a COOP Admin, you can only upload users with positions 'academic' or 'non-academic' who are COOP members.";
                    break;
                case 'super_admin':
                    echo "As a Super Admin, you can upload users with any position.";
                    break;
                default:
                    echo "As a General Admin, you can upload users with any position.";
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
        </div>
      </div>
    </main>
  </div>
</body>
</html>