    // Course data for each college
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
      "CAHS": [
          "Department of Medical Technology",
          "Department of Pharmacy"
      ],
      "GSOLC": ["Department of Various Graduate Programs"]
    };

      // Get DOM elements
      const position = document.getElementById('position');
      const studentFields = document.getElementById('studentFields');
      const academicFields = document.getElementById('academicFields');
      const nonAcademicFields = document.getElementById('nonAcademicFields');
      
      const studentDepartment = document.getElementById('studentDepartment');
      const studentCourse = document.getElementById('studentCourse');
      
      const academicCollege = document.getElementById('academicCollege');
      const academicCourse = document.getElementById('academicCourse');
      const academicStatus = document.getElementById('academicStatus');
      const academicCoopDiv = document.getElementById('academicCoop');
      
      const nonAcademicDept = document.getElementById('nonAcademicDept');
      const nonAcademicStatus = document.getElementById('nonAcademicStatus');
      const nonAcademicCoopDiv = document.getElementById('nonAcademicCoop');

      
  
      // Handle position selection
      position.addEventListener('change', function() {
        const value = this.value;
        academicDepartment.required = true;
        academicDepartment.required = false;

        // Hide all field groups
        studentFields.classList.add('hidden');
        academicFields.classList.add('hidden');
        nonAcademicFields.classList.add('hidden');
        
        // Show relevant fields based on position
        if (value === 'student') {
          studentFields.classList.remove('hidden');
          // Make student fields required
          studentDepartment.required = true;
          studentCourse.required = true;
          // Remove required from other fields
          academicCollege.required = false;
          academicStatus.required = false;
          nonAcademicDept.required = false;
          nonAcademicStatus.required = false;
        } else if (value === 'academic') {
          academicFields.classList.remove('hidden');
          // Make academic fields required
          academicCollege.required = true;
          academicCourse.required = true;
          academicStatus.required = true;
          // Remove required from other fields
          studentDepartment.required = false;
          studentCourse.required = false;
          academicCollege.required = false;
          academicCourse.required = false;
          academicStatus.required = false;
        } else if (value === 'non-academic') {
          nonAcademicFields.classList.remove('hidden');
          // Make non-academic fields required
          nonAcademicDept.required = true;
          nonAcademicStatus.required = true;
          // Remove required from other fields
          studentDepartment.required = false;
          studentCourse.required = false;
          academicCollege.required = false;
          academicCourse.required = false;
          academicStatus.required = false;
        }
      });
  
      // Handle student department change to populate courses
      studentDepartment.addEventListener('change', function() {
        const selectedDept = this.value;
        
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
  
      // Handle academic college change to populate courses
      academicCollege.addEventListener('change', function() {
        const selectedCollege = this.value;
        
        // Clear current course options
        academicCourse.innerHTML = '<option value="">-- Select Course --</option>';
        
        // Populate courses based on selected college
        if (selectedCollege && departmentCourses[selectedCollege]) {
          departmentCourses[selectedCollege].forEach(course => {
            const option = document.createElement('option');
            option.value = course;
            option.textContent = course;
            academicCourse.appendChild(option);
          });
        }
        const academicDepartment = document.getElementById('academicDepartment');
        academicDepartment.innerHTML = '<option value="">-- Select Department --</option>';
        if (selectedCollege && collegeDepartments[selectedCollege]) {
          collegeDepartments[selectedCollege].forEach(dept => {
            const option = document.createElement('option');
            option.value = dept;
            option.textContent = dept;
            academicDepartment.appendChild(option);
          });
        }
      });
  
      // Handle academic status change to show/hide COOP option
      academicStatus.addEventListener('change', function() {
        const selectedStatus = this.value;
        
        if (selectedStatus === 'regular') {
          academicCoopDiv.classList.remove('hidden');
        } else {
          academicCoopDiv.classList.add('hidden');
          document.getElementById('academicIsCoop').checked = false;
        }
      });
  
      // Handle non-academic status change to show/hide COOP option
      nonAcademicStatus.addEventListener('change', function() {
        const selectedStatus = this.value;
        
        if (selectedStatus === 'regular') {
          nonAcademicCoopDiv.classList.remove('hidden');
        } else {
          nonAcademicCoopDiv.classList.add('hidden');
          document.getElementById('nonAcademicIsCoop').checked = false;
        }
      });

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
  
      // Mobile menu toggle (basic implementation)
      document.querySelector('.md\\:hidden').addEventListener('click', function() {
        // Add mobile menu functionality here if needed
        console.log('Mobile menu clicked');
      });