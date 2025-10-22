// Wait for the DOM to be fully loaded before executing the script
document.addEventListener('DOMContentLoaded', function() {
    // Course data for each college (still needed for students)
    const departmentCourses = {
        'CAFENR': [
            'BS Agriculture',
            'BS Agribusiness',
            'BS Environmental Science',
            'BS Food Technology',
            'BS Forestry',
            'BS Agricultural and Biosystems Engineering',
            'Bachelor of Agricultural Entrepreneurship',
            'BS Land Use Design and Management'
        ],
        'CEIT': [
            'BS Computer Science',
            'BS Information Technology',
            'BS Computer Engineering',
            'BS Electronics Engineering',
            'BS Civil Engineering',
            'BS Mechanical Engineering',
            'BS Electrical Engineering',
            'BS Industrial Engineering'
        ],
        'CAS': [
            'BS Biology',
            'BS Chemistry',
            'BS Mathematics',
            'BS Physics',
            'BS Psychology',
            'BA English Language Studies',
            'BA Communication',
            'BS Statistics'
        ],
        'CVMBS': [
            'Doctor of Veterinary Medicine',
            'BS Biology (Pre-Veterinary)'
        ],
        'CED': [
            'Bachelor of Elementary Education',
            'Bachelor of Secondary Education',
            'Bachelor of Physical Education',
            'Bachelor of Technology and Livelihood Education'
        ],
        'CEMDS': [
            'BS Business Administration',
            'BS Accountancy',
            'BS Economics',
            'BS Entrepreneurship',
            'BS Office Administration'
        ],
        'CSPEAR': [
            'Bachelor of Physical Education',
            'BS Exercise and Sports Sciences'
        ],
        'CCJ': [
            'BS Criminology'
        ],
        'CON': [
            'BS Nursing'
        ]
    };
  
    // College departments data
    const collegeDepartments = {
        "CAFENR": [
            "Department of Animal Science",
            "Department of Crop Science",
            "Department of Food Science and Technology",
            "Department of Forestry and Environmental Science",
            "Department of Agricultural Economics and Development"
        ],
        "CAS": [
            "Department of Biological Sciences",
            "Department of Physical Sciences",
            "Department of Languages and Mass Communication",
            "Department of Social Sciences",
            "Department of Mathematics and Statistics"
        ],
        "CCJ": ["Department of Criminal Justice"],
        "CEMDS": [
            "Department of Economics",
            "Department of Business and Management",
            "Department of Development Studies"
        ],
        "CED": [
            "Department of Science Education",
            "Department of Technology and Livelihood Education",
            "Department of Curriculum and Instruction",
            "Department of Human Kinetics"
        ],
        "CEIT": [
            "Department of Civil Engineering",
            "Department of Computer and Electronics Engineering",
            "Department of Industrial Engineering and Technology",
            "Department of Mechanical and Electronics Engineering",
            "Department of Information Technology"
        ],
        "CON": ["Department of Nursing"],
        "COM": [
            "Department of Basic Medical Sciences",
            "Department of Clinical Sciences"
        ],
        "CSPEAR": ["Department of Physical Education and Recreation"],
        "CVMBS": [
            "Department of Veterinary Medicine",
            "Department of Biomedical Sciences"
        ],
        "GS-OLC": ["Department of Various Graduate Programs"]
    };
  
    // Get DOM elements
    const position = document.getElementById('position');
    const studentFields = document.getElementById('studentFields');
    const academicFields = document.getElementById('academicFields');
    const nonAcademicFields = document.getElementById('nonAcademicFields');
    
    // Get number field elements
    const studentNumberField = document.getElementById('studentNumberField');
    const employeeNumberField = document.getElementById('employeeNumberField');
    const studentNumberInput = document.getElementById('student_number');
    const employeeNumberInput = document.getElementById('employee_number');
    
    const studentDepartment = document.getElementById('studentDepartment');
    const studentDepartment1 = document.getElementById('studentDepartment1'); // Student department
    const studentCourse = document.getElementById('studentCourse');
    
    const academicCollege = document.getElementById('academicCollege');
    const academicDepartment = document.getElementById('academicDepartment');
    const academicStatus = document.getElementById('academicStatus');
    
    const nonAcademicDept = document.getElementById('nonAcademicDept');
    const nonAcademicStatus = document.getElementById('nonAcademicStatus');
  
    // Debug: Check if elements are found
    if (!academicCollege || !academicDepartment) {
        console.error('Academic elements not found!');
        return;
    }
  
    // Handle position selection
    position.addEventListener('change', function() {
        const value = this.value;
        
        // Hide all field groups and number fields
        studentFields.classList.add('hidden');
        academicFields.classList.add('hidden');
        nonAcademicFields.classList.add('hidden');
        studentNumberField.classList.add('hidden');
        employeeNumberField.classList.add('hidden');
        
        // Show relevant fields based on position
        if (value === 'student') {
            studentFields.classList.remove('hidden');
            studentNumberField.classList.remove('hidden');
            // Make student fields required
            studentDepartment.required = true;
            studentDepartment1.required = true; // Make department required
            studentCourse.required = true;
            studentNumberInput.required = true;
            // Remove required from other fields
            academicCollege.required = false;
            academicDepartment.required = false;
            academicStatus.required = false;
            nonAcademicDept.required = false;
            nonAcademicStatus.required = false;
            employeeNumberInput.required = false;
        } else if (value === 'academic') {
            academicFields.classList.remove('hidden');
            employeeNumberField.classList.remove('hidden');
            // Make academic fields required
            academicCollege.required = true;
            academicDepartment.required = true;
            academicStatus.required = true;
            employeeNumberInput.required = true;
            // Remove required from other fields
            studentDepartment.required = false;
            studentDepartment1.required = false;
            studentCourse.required = false;
            studentNumberInput.required = false;
            nonAcademicDept.required = false;
            nonAcademicStatus.required = false;
        } else if (value === 'non-academic') {
            nonAcademicFields.classList.remove('hidden');
            employeeNumberField.classList.remove('hidden');
            // Make non-academic fields required
            nonAcademicDept.required = true;
            nonAcademicStatus.required = true;
            employeeNumberInput.required = true;
            // Remove required from other fields
            studentDepartment.required = false;
            studentDepartment1.required = false;
            studentCourse.required = false;
            studentNumberInput.required = false;
            academicCollege.required = false;
            academicDepartment.required = false;
            academicStatus.required = false;
        }
    });
  
    // Handle student department change to populate departments and courses
    studentDepartment.addEventListener('change', function() {
        const selectedDept = this.value;
        
        // Clear current department options
        studentDepartment1.innerHTML = '<option value="">-- Select Department --</option>';
        
        // Populate departments based on selected college
        if (selectedDept && collegeDepartments[selectedDept]) {
            collegeDepartments[selectedDept].forEach(dept => {
                const option = document.createElement('option');
                option.value = dept;
                option.textContent = dept;
                studentDepartment1.appendChild(option);
            });
        }
        
        // Clear current course options
        studentCourse.innerHTML = '<option value="">-- Select Course --</option>';
        
        // Populate courses based on selected department
        if (selectedDept && departmentCourses[selectedDept]) {
            departmentCourses[selectedDept].forEach(course => {
                const option = document.createElement('option');
                option.value = course;
                option.textContent = course;
                studentCourse.appendChild(option);
            });
        }
    });
  
    // Handle academic college change to populate departments
    academicCollege.addEventListener('change', function() {
        const selectedCollege = this.value;
        
        console.log('College selected:', selectedCollege); // Debug log
        
        // Clear current department options
        academicDepartment.innerHTML = '<option value="">-- Select Department --</option>';
        
        // Populate departments based on selected college
        if (selectedCollege && collegeDepartments[selectedCollege]) {
            console.log('Departments found:', collegeDepartments[selectedCollege]); // Debug log
            collegeDepartments[selectedCollege].forEach(dept => {
                const option = document.createElement('option');
                option.value = dept;
                option.textContent = dept;
                academicDepartment.appendChild(option);
            });
        } else {
            console.log('No departments found for:', selectedCollege); // Debug log
        }
    });
  
    // COOP checkboxes are now always visible for academic and non-academic
    // No need for status-based visibility toggle
    
    document.getElementById('email').addEventListener('input', function(e) {
        const email = e.target.value;
        const errorDiv = document.getElementById('email-error');
        const emailPattern = /^[a-zA-Z0-9._%+-]+@cvsu\.edu\.ph$/;
        
        if (email && !emailPattern.test(email)) {
            errorDiv.classList.remove('hidden');
            e.target.setCustomValidity('Please enter a valid CVSU email address');
        } else {
            errorDiv.classList.add('hidden');
            e.target.setCustomValidity('');
        }
    });
  
    // Additional validation on form submit
    document.querySelector('form').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const emailPattern = /^[a-zA-Z0-9._%+-]+@cvsu\.edu\.ph$/;
        
        if (!emailPattern.test(email)) {
            e.preventDefault();
            alert('Please enter a valid CVSU email address (@cvsu.edu.ph)');
            return false;
        }
        
        // Validate number fields based on position
        const positionValue = position.value;
        if (positionValue === 'student' && !studentNumberInput.value.trim()) {
            e.preventDefault();
            alert('Student number is required for students.');
            return false;
        }
        
        if ((positionValue === 'academic' || positionValue === 'non-academic') && !employeeNumberInput.value.trim()) {
            e.preventDefault();
            alert('Employee number is required for academic and non-academic staff.');
            return false;
        }
    });
  
    // Password visibility toggle
    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const passwordInput = this.parentElement.querySelector('input[type="password"], input[type="text"]');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
  
    // Password confirmation validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
  
    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
  
    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
  
    // Form submission handling
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        // Additional client-side validation can be added here
        validatePassword();
        
        // Check if passwords match
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        // Check if terms are accepted
        if (!document.getElementById('terms').checked) {
            e.preventDefault();
            alert('Please accept the Terms of Service and Privacy Policy to continue.');
            return false;
        }
    });
});