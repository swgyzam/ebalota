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
        'CEIT' => [
            'Computer Science Department',
            'Information Technology Department', 
            'Electronics Engineering Department',
            'Mathematics Department'
        ],
        'CAS' => [
            'Natural Sciences Department',
            'Social Sciences Department',
            'Language Department',
            'Mathematics Department'
        ],
        'CAFENR' => [
            'Agriculture Department',
            'Animal Science Department',
            'Food Technology Department',
            'Environmental Science Department'
        ],
        'CVMBS' => [
            'Veterinary Medicine Department',
            'Biomedical Sciences Department'
        ],
        'CED' => [
            'Elementary Education Department',
            'Secondary Education Department',
            'Physical Education Department'
        ],
        'CEMDS' => [
            'Business Administration Department',
            'Economics Department',
            'Management Department'
        ],
        'CSPEAR' => [
            'Sports Science Department',
            'Physical Education Department',
            'Recreation Department'
        ],
        'CCJ' => [
            'Criminal Justice Department',
            'Law Enforcement Department'
        ],
        'CON' => [
            'Nursing Fundamentals Department',
            'Community Health Department'
        ],
        'CTHM' => [
            'Hospitality Management Department',
            'Tourism Management Department'
        ],
        'COM' => [
            'Medicine Proper Department',
            'Clinical Sciences Department'
        ],
        'GS-OLC' => [
            'Graduate Studies Department',
            'Open Learning Department'
        ]
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

function formatScopeDetails($scope_type, $scope_details) {
    if (empty($scope_details)) {
        return 'No specific scope assigned';
    }
    
    $details = json_decode($scope_details, true);
    if (!$details) {
        return 'Invalid scope data';
    }
    
    switch ($scope_type) {
        case 'Academic-Student': {
            $college     = $details['college'] ?? '';
            $courses     = $details['courses'] ?? [];
            $colleges    = getColleges();
            $all_courses = getCoursesByCollege($college);
            
            $result = $colleges[$college] ?? $college;
            if (!empty($courses)) {
                $course_names = array_map(function($course) use ($all_courses) {
                    return $all_courses[$course] ?? $course;
                }, $courses);
                $result .= ' - ' . implode(', ', $course_names);
            } else {
                // No specific courses = all courses for that college
                $result .= ' - All courses';
            }
            return $result;
        }
            
        case 'Academic-Faculty': {
            $college     = $details['college'] ?? '';
            $departments = $details['departments'] ?? [];
            $colleges    = getColleges();
            $all_depts   = getAcademicDepartments();
            
            $result = $colleges[$college] ?? $college;
            if (!empty($departments)) {
                // NOTE: dito sa helpers, departments array ay names, hindi codes,
                // so we just join them directly.
                $result .= ' - ' . implode(', ', $departments);
            } else {
                $result .= ' - All departments';
            }
            return $result;
        }
            
        case 'Non-Academic-Employee': {
            $departments = $details['departments'] ?? [];
            $all_depts   = getNonAcademicDepartments();
            
            if (!empty($departments)) {
                $dept_names = array_map(function($dept) use ($all_depts) {
                    return $all_depts[$dept] ?? $dept;
                }, $departments);
                return implode(', ', $dept_names);
            }
            return 'All Non-Academic Departments';
        }

        case 'Non-Academic-Student':
            return 'All non-academic student organizations';
            
        case 'Others':
            // Unified Others: COOP, Alumni, Retired, etc. – all handled as custom elections.
            return 'Others Admin (custom election via uploaded voters)';
            
        case 'Special-Scope':
            return 'CSG Admin';
            
        default:
            return 'Unknown scope type';
    }
}

function getScopeCategoryLabel($scope_category) {
    $labels = [
        'Academic-Student'      => 'Academic - Student',
        'Non-Academic-Student'  => 'Non-Academic - Student',
        'Academic-Faculty'      => 'Academic - Faculty',
        'Non-Academic-Employee' => 'Non-Academic - Employee',
        'Others'                => 'Others',
        'Special-Scope'         => 'Special Scope - CSG Admin',
    ];
    
    return $labels[$scope_category] ?? $scope_category;
}
?>