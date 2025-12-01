document.addEventListener('DOMContentLoaded', function() {
    // Hamburger menu
    const menuBtn = document.getElementById('menuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (menuBtn && mobileMenu) {
      menuBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        mobileMenu.classList.toggle('hidden');
      });
      document.addEventListener('click', function(e) {
        if (!mobileMenu.classList.contains('hidden')) {
          if (!mobileMenu.contains(e.target) && e.target !== menuBtn) {
            mobileMenu.classList.add('hidden');
          }
        }
      });
      window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
          mobileMenu.classList.add('hidden');
        }
      });
    }
  
    // Data
    const departmentCourses = {
      'CAFENR': [
        'BS Agriculture','BS Agribusiness','BS Environmental Science','BS Food Technology',
        'BS Forestry','BS Agricultural and Biosystems Engineering',
        'Bachelor of Agricultural Entrepreneurship','BS Land Use Design and Management'
      ],
      'CEIT': [
        'BS Computer Science','BS Information Technology','BS Computer Engineering',
        'BS Electronics Engineering','BS Civil Engineering','BS Mechanical Engineering',
        'BS Electrical Engineering','BS Industrial Engineering'
      ],
      'CAS': [
        'BS Biology','BS Chemistry','BS Mathematics','BS Physics','BS Psychology',
        'BA English Language Studies','BA Communication','BS Statistics'
      ],
      'CVMBS': [
        'Doctor of Veterinary Medicine','BS Biology (Pre-Veterinary)'
      ],
      'CED': [
        'Bachelor of Elementary Education','Bachelor of Secondary Education',
        'Bachelor of Physical Education','Bachelor of Technology and Livelihood Education'
      ],
      'CEMDS': [
        'BS Business Administration','BS Accountancy','BS Economics',
        'BS Entrepreneurship','BS Office Administration'
      ],
      'CSPEAR': [
        'Bachelor of Physical Education','BS Exercise and Sports Sciences'
      ],
      'CCJ': ['BS Criminology'],
      'CON': ['BS Nursing']
    };
  
    const collegeDepartments = {
      "CAFENR": [
        "Department of Animal Science","Department of Crop Science",
        "Department of Food Science and Technology","Department of Forestry and Environmental Science",
        "Department of Agricultural Economics and Development"
      ],
      "CAS": [
        "Department of Biological Sciences","Department of Physical Sciences",
        "Department of Languages and Mass Communication","Department of Social Sciences",
        "Department of Mathematics and Statistics"
      ],
      "CCJ": ["Department of Criminal Justice"],
      "CEMDS": [
        "Department of Economics","Department of Business and Management",
        "Department of Development Studies"
      ],
      "CED": [
        "Department of Science Education","Department of Technology and Livelihood Education",
        "Department of Curriculum and Instruction","Department of Human Kinetics"
      ],
      "CEIT": [
        "Department of Civil Engineering","Department of Computer and Electronics Engineering",
        "Department of Industrial Engineering and Technology","Department of Mechanical and Electronics Engineering",
        "Department of Information Technology"
      ],
      "CON": ["Department of Nursing"],
      "COM": [
        "Department of Basic Medical Sciences","Department of Clinical Sciences"
      ],
      "CSPEAR": ["Department of Physical Education and Recreation"],
      "CVMBS": [
        "Department of Veterinary Medicine","Department of Biomedical Sciences"
      ],
      "GS-OLC": ["Department of Various Graduate Programs"]
    };
  
    // DOM elements
    const position = document.getElementById('position');
    const emailInput = document.getElementById('email');
    const emailHelp = document.getElementById('emailHelp');
    const emailErrorInline = document.getElementById('emailErrorInline');
  
    const studentFields = document.getElementById('studentFields');
    const academicFields = document.getElementById('academicFields');
    const nonAcademicFields = document.getElementById('nonAcademicFields');
    const positionDetails = document.getElementById('positionDetails');
    const detailsTitle = document.getElementById('detailsTitle');
    
    const studentNumberField = document.getElementById('studentNumberField');
    const employeeNumberField = document.getElementById('employeeNumberField');
    const studentNumberInput = document.getElementById('student_number');
    const employeeNumberInput = document.getElementById('employee_number');
    
    const studentDepartment = document.getElementById('studentDepartment');
    const studentDepartment1 = document.getElementById('studentDepartment1');
    const studentCourse = document.getElementById('studentCourse');
    
    const academicCollege = document.getElementById('academicCollege');
    const academicDepartment = document.getElementById('academicDepartment');
    const academicStatus = document.getElementById('academicStatus');
    
    const nonAcademicDept = document.getElementById('nonAcademicDept');
    const nonAcademicStatus = document.getElementById('nonAcademicStatus');
  
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const form = document.getElementById('registrationForm');
  
    // Step indicator elements
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
  
    // Modal elements
    const successModal = document.getElementById('successModal');
    const adminVerificationModal = document.getElementById('adminVerificationModal');
    const errorModal = document.getElementById('errorModal');
    const errorMessage = document.getElementById('errorMessage');
    const errorModalClose = document.getElementById('errorModalClose');
  
    // ===== MODALS VIA URL PARAMS =====
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const adminVerification = urlParams.get('admin_verification');
    const error = urlParams.get('error');
  
    if (success === 'true' && successModal) {
      successModal.classList.remove('hidden');
      // auto-redirect to login after a bit
      setTimeout(() => {
        window.location.href = 'login.html';
      }, 3000);
    }
  
    if (adminVerification === 'sent' && adminVerificationModal) {
      adminVerificationModal.classList.remove('hidden');
      setTimeout(() => {
        window.location.href = 'login.html';
      }, 5000);
    }
  
    if (error && errorModal && errorMessage) {
      errorMessage.textContent = decodeURIComponent(error);
      errorModal.classList.remove('hidden');
    }
  
    if (errorModalClose && errorModal) {
      errorModalClose.addEventListener('click', () => {
        errorModal.classList.add('hidden');
        // optional: clear query params
        const cleanUrl = window.location.origin + window.location.pathname;
        window.history.replaceState({}, '', cleanUrl);
      });
    }
  
    // ===== Position change handler =====
    position.addEventListener('change', function() {
      const value = this.value;
  
      // Hide all groups
      studentFields.classList.add('hidden');
      academicFields.classList.add('hidden');
      nonAcademicFields.classList.add('hidden');
      positionDetails.classList.add('hidden');
      studentNumberField.classList.add('hidden');
      employeeNumberField.classList.add('hidden');
  
      // Clear required
      studentDepartment.required = false;
      studentDepartment1.required = false;
      studentCourse.required = false;
      studentNumberInput.required = false;
      academicCollege.required = false;
      academicDepartment.required = false;
      academicStatus.required = false;
      nonAcademicDept.required = false;
      nonAcademicStatus.required = false;
      employeeNumberInput.required = false;
  
      // Reset email helper
      emailErrorInline.classList.add('hidden');
      emailErrorInline.textContent = '';
  
      // Update step indicator
      step2.classList.add('active');
      
      if (value === 'student') {
        studentFields.classList.remove('hidden');
        positionDetails.classList.remove('hidden');
        studentNumberField.classList.remove('hidden');
        detailsTitle.textContent = 'Student Details';
  
        studentDepartment.required = true;
        studentDepartment1.required = true;
        studentCourse.required = true;
        studentNumberInput.required = true;
  
        emailHelp.textContent = 'For Students, use your CvSU email (@cvsu.edu.ph).';
        step3.classList.add('active');
      } else if (value === 'academic') {
        academicFields.classList.remove('hidden');
        positionDetails.classList.remove('hidden');
        employeeNumberField.classList.remove('hidden');
        detailsTitle.textContent = 'Academic Details';
  
        academicCollege.required = true;
        academicDepartment.required = true;
        academicStatus.required = true;
        employeeNumberInput.required = true;
  
        emailHelp.textContent = 'For Academic (Faculty), use your CvSU email (@cvsu.edu.ph).';
        step3.classList.add('active');
      } else if (value === 'non-academic') {
        nonAcademicFields.classList.remove('hidden');
        positionDetails.classList.remove('hidden');
        employeeNumberField.classList.remove('hidden');
        detailsTitle.textContent = 'Non-Academic Details';
  
        nonAcademicDept.required = true;
        nonAcademicStatus.required = true;
        employeeNumberInput.required = true;
  
        emailHelp.textContent = 'For Non-Academic, any valid email (e.g. Gmail) is allowed.';
        step3.classList.add('active');
      } else {
        emailHelp.textContent = 'For Students and Academic (Faculty), use your CvSU email (@cvsu.edu.ph).';
        step2.classList.remove('active');
        step3.classList.remove('active');
      }
    });
  
    // Populate student departments + courses
    studentDepartment.addEventListener('change', function() {
      const selectedCollege = this.value;
      studentDepartment1.innerHTML = '<option value="">-- Select Department --</option>';
      studentCourse.innerHTML = '<option value="">-- Select Course --</option>';
  
      if (selectedCollege && collegeDepartments[selectedCollege]) {
        collegeDepartments[selectedCollege].forEach(dept => {
          const option = document.createElement('option');
          option.value = dept;
          option.textContent = dept;
          studentDepartment1.appendChild(option);
        });
      }
  
      if (selectedCollege && departmentCourses[selectedCollege]) {
        departmentCourses[selectedCollege].forEach(course => {
          const option = document.createElement('option');
          option.value = course;
          option.textContent = course;
          studentCourse.appendChild(option);
        });
      }
    });
  
    // Populate academic departments
    academicCollege.addEventListener('change', function() {
      const selectedCollege = this.value;
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
  
    // ===== Password strength indicator =====
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('passwordStrength');
    const lengthCheck = document.getElementById('lengthCheck');
    const uppercaseCheck = document.getElementById('uppercaseCheck');
    const numberCheck = document.getElementById('numberCheck');
    const specialCheck = document.getElementById('specialCheck');
  
    function updateCheckElement(el, isValid) {
      const icon = el.querySelector('svg');
      if (isValid) {
        el.classList.remove('text-gray-500');
        el.classList.add('text-green-500');
        icon.classList.remove('text-gray-400');
        icon.classList.add('text-green-500');
      } else {
        el.classList.remove('text-green-500');
        el.classList.add('text-gray-500');
        icon.classList.remove('text-green-500');
        icon.classList.add('text-gray-400');
      }
    }
  
    function updatePasswordStrength() {
      const val = password.value || '';
  
      const length = val.length >= 8;
      const uppercase = /[A-Z]/.test(val);
      const number = /[0-9]/.test(val);
      const special = /[!@#$%^&*(),.?":{}|<>]/.test(val);
  
      updateCheckElement(lengthCheck, length);
      updateCheckElement(uppercaseCheck, uppercase);
      updateCheckElement(numberCheck, number);
      updateCheckElement(specialCheck, special);
  
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
    }
  
    // Password match + strength validation
    function validatePassword() {
      if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity("Passwords don't match");
      } else {
        confirmPassword.setCustomValidity('');
      }
    }
  
    password.addEventListener('input', () => {
      updatePasswordStrength();
      validatePassword();
    });
  
    confirmPassword.addEventListener('input', validatePassword);
  
    // ===== Form submission =====
    form.addEventListener('submit', function(e) {
      validatePassword();
  
      if (password.value !== confirmPassword.value) {
        e.preventDefault();
        return false;
      }
  
      const positionValue = position.value;
      if (!positionValue) {
        e.preventDefault();
        return false;
      }
  
      // EMAIL VALIDATION
      const emailVal = (emailInput.value || '').trim();
      const basicEmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  
      emailErrorInline.classList.add('hidden');
      emailErrorInline.textContent = '';
  
      if (!basicEmailPattern.test(emailVal)) {
        e.preventDefault();
        emailErrorInline.textContent = 'Please enter a valid email address.';
        emailErrorInline.classList.remove('hidden');
        emailInput.focus();
        return false;
      }
  
      if ((positionValue === 'student' || positionValue === 'academic') &&
          !emailVal.toLowerCase().endsWith('@cvsu.edu.ph')) {
        e.preventDefault();
        emailErrorInline.textContent = 'CvSU email (@cvsu.edu.ph) is required for this position.';
        emailErrorInline.classList.remove('hidden');
        emailInput.focus();
        return false;
      }
  
      // Number / department validation
      if (positionValue === 'student' && !studentNumberInput.value.trim()) {
        e.preventDefault();
        return false;
      }
  
      if ((positionValue === 'academic' || positionValue === 'non-academic') && !employeeNumberInput.value.trim()) {
        e.preventDefault();
        return false;
      }
  
      if (positionValue === 'student' && !studentDepartment1.value.trim()) {
        e.preventDefault();
        return false;
      }
  
      if (!document.getElementById('terms').checked) {
        e.preventDefault();
        return false;
      }
    });
  
    // === Terms & Privacy Modal Logic ===
    const termsModal       = document.getElementById('termsModal');
    const termsModalTitle  = document.getElementById('termsModalTitle');
    const termsModalBody   = document.getElementById('termsModalBody');
    const termsModalClose  = document.getElementById('termsModalClose');
    const termsModalClose2 = document.getElementById('termsModalCloseBottom');
    const openTermsLink    = document.getElementById('openTermsLink');
    const openPrivacyLink  = document.getElementById('openPrivacyLink');
  
    function openModal(type) {
      if (type === 'terms') {
        termsModalTitle.textContent = 'Terms of Service';
        termsModalBody.innerHTML = `
          <div class="modal-section">
            <h4>1. Acceptance of Terms</h4>
            <p>By creating an account with eBalota, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, please do not use our service.</p>
            
            <h4>2. Eligibility</h4>
            <p>You must be a student, faculty member, or staff of Cavite State University to register for an account. You must provide accurate and complete information during registration.</p>
            
            <h4>3. Account Security</h4>
            <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>
            
            <h4>4. Acceptable Use</h4>
            <p>You agree to use eBalota only for legitimate voting purposes as authorized by Cavite State University. You shall not:</p>
            <ul>
              <li>Impersonate another person or use a false identity</li>
              <li>Interfere with or disrupt the voting system or servers</li>
              <li>Use the system for any illegal or unauthorized purpose</li>
            </ul>
            
            <h4>5. Privacy</h4>
            <p>Your privacy is important to us. Please review our Privacy Policy, which also governs your use of the service, to understand our practices.</p>
            
            <h4>7. Termination</h4>
            <p>We may terminate or suspend your account immediately, including without limitation if you breach the terms.</p>
            
            <h4>8. Governing Law</h4>
            <p>These terms shall be governed and construed in accordance with the laws of the Republic of the Philippines.</p>
          </div>
        `;
      } else if (type === 'privacy') {
        termsModalTitle.textContent = 'Privacy Policy';
        termsModalBody.innerHTML = `
          <div class="modal-section">
            <h4>Information We Collect</h4>
            <p>When you register for an eBalota account, we collect the following types of information:</p>
            <ul>
              <li><strong>Personal Information:</strong> Your name, email address, position (student, faculty, staff), student/employee ID, and college/department affiliation.</li>
              <li><strong>Usage Information:</strong> Information about how you use the eBalota system, including voting activity and system interactions.</li>
            </ul>
            
            <h4>How We Use Your Information</h4>
            <p>We use the information we collect to:</p>
            <ul>
              <li>Verify your eligibility to participate in elections</li>
              <li>Prevent duplicate voting and ensure election integrity</li>
              <li>Provide you with access to the voting system</li>
              <li>Communicate with you about elections and system updates</li>
              <li>Generate anonymized election reports and statistics</li>
            </ul>
            
            <h4>Data Security</h4>
            <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p>
            <ul>
              <li>Encryption of sensitive data</li>
              <li>Secure authentication mechanisms</li>
              <li>Regular security assessments</li>
              <li>Access controls to limit data access to authorized personnel</li>
            </ul>
            
            <h4>Voting Privacy</h4>
            <p>Your individual votes are kept confidential and will not be disclosed to other users or the public. Only aggregated and anonymized election results are shared.</p>
            
            <h4>Data Retention</h4>
            <p>We retain your personal information only as long as necessary for the purposes outlined in this policy. After graduation, employment termination, or upon request, your account may be deactivated or deleted in accordance with university policies.</p>
            
            <h4>Your Rights</h4>
            <p>You have the right to:</p>
            <ul>
              <li>Access the personal information we hold about you</li>
              <li>Request correction of inaccurate information</li>
              <li>Request deletion of your account (subject to university record retention policies)</li>
            </ul>
            
            <h4>Changes to This Policy</h4>
            <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date.</p>
            
            <h4>Contact Us</h4>
            <p>If you have any questions about this Privacy Policy, please contact us at: <a href="mailto:privacy@cvsu.edu.ph">privacy@cvsu.edu.ph</a></p>
          </div>
        `;
      }
      termsModal.classList.remove('hidden');
    }
  
    function closeModal() {
      termsModal.classList.add('hidden');
    }
  
    if (openTermsLink) {
      openTermsLink.addEventListener('click', function() {
        openModal('terms');
      });
    }
  
    if (openPrivacyLink) {
      openPrivacyLink.addEventListener('click', function() {
        openModal('privacy');
      });
    }
  
    if (termsModalClose) {
      termsModalClose.addEventListener('click', closeModal);
    }
    if (termsModalClose2) {
      termsModalClose2.addEventListener('click', closeModal);
    }
  
    if (termsModal) {
      termsModal.addEventListener('click', function(e) {
        if (e.target === termsModal) {
          closeModal();
        }
      });
    }
  });
  