<?php
// Helper functions for admin scope management

function getColleges() {
    return [
        'CAFENR' => 'College of Agriculture, Food and Natural Resources',
        'CEIT' => 'College of Engineering and Information Technology',
        'CAS' => 'College of Arts and Sciences',
        'CVMBS' => 'College of Veterinary Medicine and Biomedical Sciences',
        'CED' => 'College of Education',
        'CEMDS' => 'College of Economics, Management and Development Studies',
        'CSPEAR' => 'College of Sports, Physical Education and Recreation',
        'CCJ' => 'College of Criminal Justice Education',
        'CON' => 'College of Nursing',
        'CTHM' => 'College of Tourism and Hospitality Management',
        'COM' => 'College of Medicine',
        'GS-OLC' => 'Graduate School - Open Learning College'
    ];
}

function getAcademicDepartments() {
    return [
        "CAFENR" => [
            "DAS" => "Department of Animal Science",
            "DCS" => "Department of Crop Science",
            "DFST" => "Department of Food Science and Technology",
            "DFES" => "Department of Forestry and Environmental Science",
            "DAED" => "Department of Agricultural Economics and Development"
        ],
        "CAS" => [
            "DBS" => "Department of Biological Sciences",
            "DPS" => "Department of Physical Sciences",
            "DLMC" => "Department of Languages and Mass Communication",
            "DSS" => "Department of Social Sciences",
            "DMS" => "Department of Mathematics and Statistics"
        ],
        "CCJ" => ["DCJ" => "Department of Criminal Justice"],
        "CEMDS" => [
            "DE" => "Department of Economics",
            "DBM" => "Department of Business and Management",
            "DDS" => "Department of Development Studies"
        ],
        "CED" => [
            "DSE" => "Department of Science Education",
            "DTLE" => "Department of Technology and Livelihood Education",
            "DCI" => "Department of Curriculum and Instruction",
            "DHK" => "Department of Human Kinetics"
        ],
        "CEIT" => [
            "DCE" => "Department of Civil Engineering",
            "DCEE" => "Department of Computer and Electronics Engineering",
            "DIET" => "Department of Industrial Engineering and Technology",
            "DMEE" => "Department of Mechanical and Electronics Engineering",
            "DIT" => "Department of Information Technology"
        ],
        "CON" => ["DN" => "Department of Nursing"],
        "COM" => [
            "DBMS" => "Department of Basic Medical Sciences",
            "DCS" => "Department of Clinical Sciences"
        ],
        "CSPEAR" => ["DPER" => "Department of Physical Education and Recreation"],
        "CVMBS" => [
            "DVM" => "Department of Veterinary Medicine",
            "DBS" => "Department of Biomedical Sciences"
        ],
        "GS-OLC" => ["DVGP" => "Department of Various Graduate Programs"]
    ];
}

function getNonAcademicDepartments() {
    return [
        'HR' => 'Human Resources',
        'ADMIN' => 'Administration',
        'FINANCE' => 'Finance',
        'IT' => 'Information Technology',
        'MAINTENANCE' => 'Maintenance',
        'SECURITY' => 'Security',
        'LIBRARY' => 'Library',
        'NAEA' => 'Non-Academic Employees Association',
        'NAES' => 'Non-Academic Employee Services',
        'NAEM' => 'Non-Academic Employee Management',
        'NAEH' => 'Non-Academic Employee Health',
        'NAEIT' => 'Non-Academic Employee IT'
    ];
}

function getCoursesByCollege($college) {
    $courses = [
        'CEIT' => [
            'BSIT' => 'Bachelor of Science in Information Technology',
            'BSCS' => 'Bachelor of Science in Computer Science',
            'BSCpE' => 'Bachelor of Science in Computer Engineering',
            'BSECE' => 'Bachelor of Science in Electronics Engineering',
            'BSCE' => 'Bachelor of Science in Civil Engineering',
            'BSME' => 'Bachelor of Science in Mechanical Engineering',
            'BSEE' => 'Bachelor of Science in Electrical Engineering',
            'BSIE' => 'Bachelor of Science in Industrial Engineering',
            'BSArch' => 'Bachelor of Science in Architecture'
        ],
        'CAS' => [
            'BSBio' => 'Bachelor of Science in Biology',
            'BSChem' => 'Bachelor of Science in Chemistry',
            'BSMath' => 'Bachelor of Science in Mathematics',
            'BSPhysics' => 'Bachelor of Science in Physics',
            'BSPsych' => 'Bachelor of Science in Psychology',
            'BAELS' => 'Bachelor of Arts in English Language Studies',
            'BAComm' => 'Bachelor of Arts in Communication',
            'BSStat' => 'Bachelor of Science in Statistics'
        ],
        'CAFENR' => [
            'BSAgri' => 'Bachelor of Science in Agriculture',
            'BSAB' => 'Bachelor of Science in Agribusiness',
            'BSES' => 'Bachelor of Science in Environmental Science',
            'BSFT' => 'Bachelor of Science in Food Technology',
            'BSFor' => 'Bachelor of Science in Forestry',
            'BSABE' => 'Bachelor of Science in Agricultural and Biosystems Engineering',
            'BAE' => 'Bachelor of Agricultural Entrepreneurship',
            'BSLDM' => 'Bachelor of Science in Land Use Design and Management'
        ],
        'CVMBS' => [
            'DVM' => 'Doctor of Veterinary Medicine',
            'BSPV' => 'Bachelor of Science in Pre-Veterinary Medicine'
        ],
        'CED' => [
            'BEEd' => 'Bachelor of Elementary Education',
            'BSEd' => 'Bachelor of Secondary Education',
            'BPE' => 'Bachelor of Physical Education',
            'BTLE' => 'Bachelor of Technology and Livelihood Education'
        ],
        'CEMDS' => [
            'BSBA' => 'Bachelor of Science in Business Administration',
            'BSAcc' => 'Bachelor of Science in Accountancy',
            'BSEco' => 'Bachelor of Science in Economics',
            'BSEnt' => 'Bachelor of Science in Entrepreneurship',
            'BSOA' => 'Bachelor of Science in Office Administration'
        ],
        'CSPEAR' => [
            'BPE' => 'Bachelor of Physical Education',
            'BSESS' => 'Bachelor of Science in Exercise and Sports Sciences'
        ],
        'CCJ' => [
            'BSCrim' => 'Bachelor of Science in Criminology'
        ],
        'CON' => [
            'BSN' => 'Bachelor of Science in Nursing'
        ],
        'CTHM' => [
            'BSHM' => 'Bachelor of Science in Hospitality Management',
            'BSTM' => 'Bachelor of Science in Tourism Management'
        ],
        'COM' => [
            'BLIS' => 'Bachelor of Library and Information Science'
        ],
        'GS-OLC' => [
            'PhD' => 'Doctor of Philosophy',
            'MS' => 'Master of Science',
            'MA' => 'Master of Arts'
        ]
    ];
    
    return $courses[$college] ?? [];
}

// Function to get department code from full name
function getDepartmentCodeFromName($full_name, $college) {
    $all_depts = getAcademicDepartments();
    $college_depts = $all_depts[$college] ?? [];
    
    // First, try to find an exact match in the departments array
    foreach ($college_depts as $code => $name) {
        if ($name === $full_name) {
            return $code;
        }
    }
    
    // If not found, try to extract a potential code from the name
    // For example, "Department of Computer and Electronics Engineering" -> "DCE"
    if (strpos($full_name, 'Department of') === 0) {
        $words = explode(' ', $full_name);
        $code = '';
        
        // Take the first letter of each important word
        foreach ($words as $word) {
            if ($word !== 'Department' && $word !== 'of' && $word !== 'and') {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }
        
        return $code;
    }
    
    return $full_name; // Return original if no pattern matches
}

// UPDATED formatScopeDetails function to handle multiple selections and "All" display
function formatScopeDetails($scope_type, $scope_details) {
    if (empty($scope_details)) {
        return 'No specific scope assigned';
    }
    
    $details = json_decode($scope_details, true);
    if (!$details) {
        return 'Invalid scope data';
    }
    
    switch ($scope_type) {
        case 'Academic-Student':
            $college = $details['college'] ?? '';
            // FIXED: Display college code only instead of full name
            $college_code = $college;
            
            // Check if all courses are selected
            if (isset($details['courses_display']) && $details['courses_display'] === 'All') {
                return "$college_code - All courses";
            }
            
            $courses = $details['courses'] ?? [];
            if (empty($courses)) {
                return $college_code;
            }
            
            // FIXED: Display course codes only instead of full names
            $course_codes = array_map(function($course) {
                return $course;
            }, $courses);
            
            // If there are multiple courses, list them all
            return "$college_code - " . implode(', ', $course_codes);
            
        case 'Academic-Faculty':
            $college = $details['college'] ?? '';
            // FIXED: Display college code only instead of full name
            $college_code = $college;
            
            // Check if all departments are selected
            if (isset($details['departments_display']) && $details['departments_display'] === 'All') {
                return "$college_code - All departments";
            }
            
            $departments = $details['departments'] ?? [];
            if (empty($departments)) {
                return $college_code;
            }
            
            // FIXED: Display department codes only instead of full names
            $dept_codes = array_map(function($dept) use ($college) {
                // Try to get the code from the full name
                return getDepartmentCodeFromName($dept, $college);
            }, $departments);
            
            // If there are multiple departments, list them all
            return "$college_code - " . implode(', ', $dept_codes);
            
        case 'Non-Academic-Employee':
            // Check if all non-academic departments are selected
            if (isset($details['departments_display']) && $details['departments_display'] === 'All') {
                return "All non-academic departments";
            }
            
            $departments = $details['departments'] ?? [];
            if (empty($departments)) {
                return 'All Non-Academic Departments';
            }
            
            // FIXED: Display department codes only instead of full names
            $dept_codes = array_map(function($dept) {
                return $dept;
            }, $departments);
            
            // If there are multiple departments, list them all
            return implode(', ', $dept_codes);
            
        case 'Others-COOP':
            return 'COOP Admin (COOP + MIGS members only)';
            
        case 'Others-Default':
            return 'Default Admin (All faculty and non-academic employees)';
            
        case 'Special-Scope':
            return 'CSG Admin';
            
        default:
            return 'Unknown scope type';
    }
}

function getScopeCategoryLabel($scope_category) {
    $labels = [
        'Academic-Student' => 'Academic - Student',
        'Non-Academic-Student' => 'Non-Academic - Student',
        'Academic-Faculty' => 'Academic - Faculty',
        'Non-Academic-Employee' => 'Non-Academic - Employee',
        'Others-COOP' => 'Others - COOP',
        'Others-Default' => 'Others - Default',
        'Special-Scope' => 'CSG Admin'
    ];
    
    return $labels[$scope_category] ?? $scope_category;
}

// Comprehensive scope details mapping for JavaScript
function getScopeDetailsMapping() {
    return [
        'Academic-Student' => [
            'colleges' => getColleges(),
            'courses' => [
                'CEIT' => getCoursesByCollege('CEIT'),
                'CAS' => getCoursesByCollege('CAS'),
                'CAFENR' => getCoursesByCollege('CAFENR'),
                'CVMBS' => getCoursesByCollege('CVMBS'),
                'CED' => getCoursesByCollege('CED'),
                'CEMDS' => getCoursesByCollege('CEMDS'),
                'CSPEAR' => getCoursesByCollege('CSPEAR'),
                'CCJ' => getCoursesByCollege('CCJ'),
                'CON' => getCoursesByCollege('CON'),
                'CTHM' => getCoursesByCollege('CTHM'),
                'COM' => getCoursesByCollege('COM'),
                'GS-OLC' => getCoursesByCollege('GS-OLC')
            ]
        ],
        'Academic-Faculty' => [
            'colleges' => getColleges(),
            'departments' => getAcademicDepartments()
        ],
        'Non-Academic-Employee' => [
            'departments' => getNonAcademicDepartments()
        ],
        'Others-COOP' => [
            'description' => 'COOP Admin (COOP + MIGS members only)',
            'scope' => 'Faculty and Non-Academic Employees with COOP and MIGS membership'
        ],
        'Others-Default' => [
            'description' => 'Default Admin (All faculty and non-academic employees)',
            'scope' => 'All Faculty and Non-Academic Employees'
        ],
        'Special-Scope' => [
            'description' => 'CSG Admin',
            'scope' => 'All Student Organizations'
        ],
        'Non-Academic-Student' => [
            'description' => 'Non-Academic - Student Admin',
            'scope' => 'All non-academic student organizations'
        ]
    ];
}

// Function to get all scope categories with their labels
function getAllScopeCategories() {
    return [
        'Academic-Student' => 'Academic - Student',
        'Non-Academic-Student' => 'Non-Academic - Student',
        'Academic-Faculty' => 'Academic - Faculty',
        'Non-Academic-Employee' => 'Non-Academic - Employee',
        'Others-COOP' => 'Others - COOP',
        'Others-Default' => 'Others - Default',
        'Special-Scope' => 'CSG Admin'
    ];
}

// Function to get scope details for a specific category
function getScopeDetailsForCategory($category) {
    $mapping = getScopeDetailsMapping();
    return $mapping[$category] ?? [];
}
?>