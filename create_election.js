  // Define college to courses mapping
  const collegeCourses = {
    'CAFENR': [
      'BSAgri',    // BS Agriculture
      'BSAB',      // BS Agribusiness
      'BSES',      // BS Environmental Science
      'BSFT',      // BS Food Technology
      'BSFor',     // BS Forestry
      'BSABE',     // BS Agricultural and Biosystems Engineering
      'BAE',       // Bachelor of Agricultural Entrepreneurship
      'BSLDM'      // BS Land Use Design and Management
    ],
    'CAS': [
      'BSBio',     // BS Biology
      'BSChem',    // BS Chemistry
      'BSMath',    // BS Mathematics
      'BSPhysics', // BS Physics
      'BSPsych',   // BS Psychology
      'BAELS',     // BA English Language Studies
      'BAComm',    // BA Communication
      'BSStat'     // BS Statistics
    ],
    'CEIT': [
      'BSCS',      // BS Computer Science
      'BSIT',      // BS Information Technology
      'BSCpE',     // BS Computer Engineering
      'BSECE',     // BS Electronics Engineering
      'BSCE',      // BS Civil Engineering
      'BSME',      // BS Mechanical Engineering
      'BSEE',      // BS Electrical Engineering
      'BSIE',      // BS Industrial Engineering
      'BSArch'      // BS Architecture
    ],
    'CVMBS': [
      'DVM',       // Doctor of Veterinary Medicine
      'BSPV'       // BS Biology (Pre-Veterinary)
    ],
    'CED': [
      'BEEd',      // Bachelor of Elementary Education
      'BSEd',      // Bachelor of Secondary Education
      'BPE',       // Bachelor of Physical Education
      'BTLE'       // Bachelor of Technology and Livelihood Education
    ],
    'CEMDS': [
      'BSBA',      // BS Business Administration
      'BSAcc',     // BS Accountancy
      'BSEco',     // BS Economics
      'BSEnt',     // BS Entrepreneurship
      'BSOA'       // BS Office Administration
    ],
    'CSPEAR': [
      'BPE',       // Bachelor of Physical Education
      'BSESS'      // BS Exercise and Sports Sciences
    ],
    'CCJ': [
      'BSCrim'     // BS Criminology
    ],
    'CON': [
      'BSN',       // BS Nursing
    ],
    'CTHM': [
      'BSHM',      // BS Hospitality Management (example, add if needed)
      'BSTM'       // BS Tourism Management (example, add if needed)
    ],
    'COM': [
      'BLIS'       // Bachelor of Library and Information Science
    ],
    'GS-OLC': [
      'PhD',
      'MS',
      'MA'
    ]
  };
  
  // Function to load courses based on selected college
  function loadCourses(type) {
    // Huwag mag-load ng courses para sa academic
    if (type === 'academic') return;

    const collegeSelect = document.getElementById(`${type}CollegeSelect`);
    const coursesContainer = document.getElementById(`${type}CoursesContainer`);
    const coursesList = document.getElementById(`${type}CoursesList`);

    if (collegeSelect.value === 'all') {
      coursesContainer.classList.add('hidden');
      return;
    }

    coursesList.innerHTML = '';
    const courses = collegeCourses[collegeSelect.value] || [];

    courses.forEach(course => {
      coursesList.innerHTML += `
        <label class="flex items-center">
          <input type="checkbox" name="allowed_courses_${type}[]" value="${course}" class="mr-1">
          ${course}
        </label>
      `;
    });

    coursesContainer.classList.remove('hidden');
}

  
  // Update the toggleAllCheckboxes function to work with dynamic checkboxes
  function toggleAllCheckboxes(name) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
    if (checkboxes.length === 0) return;
    
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    
    checkboxes.forEach(checkbox => {
      checkbox.checked = !allChecked;
    });
  }
    // Elements
    const targetRadios = document.querySelectorAll('input[name="target_voter"]');
    const studentFields = document.getElementById('studentFields');
    const academicFields = document.getElementById('academicFields');
    const nonAcademicFields = document.getElementById('nonAcademicFields');
    const coopFields = document.getElementById('coopFields');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modal = document.getElementById('modal');
  
    function toggleAllCheckboxes(name) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    
    checkboxes.forEach(checkbox => {
      checkbox.checked = !allChecked;
    });
  }
  
  // Add this to handle the initial state of the COOP toggle button
  document.addEventListener('DOMContentLoaded', function() {
    const coopCheckbox = document.querySelector('input[name="allowed_status_coop[]"]');
    if (coopCheckbox) {
      coopCheckbox.checked = true; // Default checked for COOP
    }
  });
    function hideAllFields() {
      studentFields.classList.add('hidden');
      academicFields.classList.add('hidden');
      nonAcademicFields.classList.add('hidden');
      coopFields.classList.add('hidden');
    }
  
    targetRadios.forEach(radio => {
      radio.addEventListener('change', e => {
        hideAllFields();
        switch(e.target.value) {
          case 'student':
            studentFields.classList.remove('hidden');
            break;
          case 'academic':
            academicFields.classList.remove('hidden');
            break;
          case 'non_academic':
            nonAcademicFields.classList.remove('hidden');
            break;
          case 'coop':
            coopFields.classList.remove('hidden');
            break;
        }
      });
    });
  
    closeModalBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
    });
  document.addEventListener('DOMContentLoaded', function () {
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modal = document.getElementById('modal');
  
    openModalBtn.addEventListener('click', () => modal.classList.remove('hidden'));
    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    window.addEventListener('click', e => {
      if (e.target === modal) modal.classList.add('hidden');
    });
  
    const checkboxes = document.querySelectorAll('.target-check');
    const courseSection = document.getElementById('allowedCoursesSection');
    const statusSection = document.getElementById('allowedStatusSection');
    const form = document.querySelector('form');
  
    // Create hidden inputs for null values
    const nullCoursesInput = document.createElement('input');
    nullCoursesInput.type = 'hidden';
    nullCoursesInput.name = 'allowed_courses[]';
    nullCoursesInput.value = '';
    
    const nullStatusInput = document.createElement('input');
    nullStatusInput.type = 'hidden';
    nullStatusInput.name = 'allowed_status[]';
    nullStatusInput.value = '';
  
    function toggleSections() {
      let studentChecked = false;
      let facultyChecked = false;
      let coopChecked = false;
  
      checkboxes.forEach(checkbox => {
        if (checkbox.checked && checkbox.dataset.target === 'student') studentChecked = true;
        if (checkbox.checked && checkbox.dataset.target === 'faculty') facultyChecked = true;
        if (checkbox.checked && checkbox.dataset.target === 'non_academic') facultyChecked = true;
        if (checkbox.checked && checkbox.dataset.target === 'coop') coopChecked = true;
      });
  
      // Show courses only if student is selected
      if (studentChecked) {
        courseSection.style.display = 'block';
        // Remove null courses input if it exists
        if (form.contains(nullCoursesInput)) {
          form.removeChild(nullCoursesInput);
        }
      } else {
        courseSection.style.display = 'none';
        // Add null courses input if not already added
        if (!form.contains(nullCoursesInput)) {
          form.appendChild(nullCoursesInput);
        }
      }
  
      // Show status only if faculty or coop is selected
      if (facultyChecked || coopChecked) {
        statusSection.style.display = 'block';
        // Remove null status input if it exists
        if (form.contains(nullStatusInput)) {
          form.removeChild(nullStatusInput);
        }
      } else {
        statusSection.style.display = 'none';
        // Add null status input if not already added
        if (!form.contains(nullStatusInput)) {
          form.appendChild(nullStatusInput);
        }
      }
    }
  
    checkboxes.forEach(cb => cb.addEventListener('change', toggleSections));
    
    // Initialize sections on page load
    toggleSections();
  });

  