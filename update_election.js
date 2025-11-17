// === UPDATE ELECTION JS ===

// Same college→courses map as create
const collegeCoursesUpdate = {
    'CAFENR': ['BSAgri','BSAB','BSES','BSFT','BSFor','BSABE','BAE','BSLDM'],
    'CAS':    ['BSBio','BSChem','BSMath','BSPhysics','BSPsych','BAELS','BAComm','BSStat'],
    'CEIT':   ['BSCS','BSIT','BSCpE','BSECE','BSCE','BSME','BSEE','BSIE','BSArch'],
    'CVMBS':  ['DVM','BSPV'],
    'CED':    ['BEEd','BSEd','BPE','BTLE'],
    'CEMDS':  ['BSBA','BSAcc','BSEco','BSEnt','BSOA'],
    'CSPEAR': ['BPE','BSESS'],
    'CCJ':    ['BSCrim'],
    'CON':    ['BSN'],
    'CTHM':   ['BSHM','BSTM'],
    'COM':    ['BLIS'],
    'GS-OLC': ['PhD','MS','MA']
  };
  
  function loadUpdateCourses(type, allowedCourses = '') {
    const collegeSelect = document.getElementById(
      type === 'faculty' ? 'update_allowed_colleges_faculty' : 'update_allowed_colleges'
    );
    const container = document.getElementById(
      type === 'faculty' ? 'update_facultyCoursesContainer' : 'update_studentCoursesContainer'
    );
    const list = document.getElementById(
      type === 'faculty' ? 'update_facultyCoursesList' : 'update_studentCoursesList'
    );
  
    if (!collegeSelect || !container || !list) return;
  
    if (collegeSelect.value === 'all') {
      container.classList.add('hidden');
      list.innerHTML = '';
      return;
    }
  
    list.innerHTML = '';
    const courses = collegeCoursesUpdate[collegeSelect.value] || [];
  
    courses.forEach(course => {
      const isChecked = allowedCourses && allowedCourses.includes(course) ? 'checked' : '';
      list.innerHTML += `
        <label class="flex items-center">
          <input type="checkbox" name="allowed_courses_${type}[]" value="${course}" class="mr-1" ${isChecked}>
          ${course}
        </label>
      `;
    });
  
    container.classList.remove('hidden');
  }
  
  function toggleAllCheckboxes(name) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
    if (checkboxes.length === 0) return;
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
  }
  
  function hideAllUpdateFields() {
    const s = document.getElementById('update_studentFields');
    const f = document.getElementById('update_facultyFields');
    const n = document.getElementById('update_nonAcademicFields');
    const c = document.getElementById('update_coopFields');
    if (s) s.classList.add('hidden');
    if (f) f.classList.add('hidden');
    if (n) n.classList.add('hidden');
    if (c) c.classList.add('hidden');
  }
  
  function initializeStudentFields(election) {
    const collegeSelect = document.getElementById('update_allowed_colleges');
    if (!collegeSelect) return;
  
    if (election.allowed_colleges && election.allowed_colleges !== 'all') {
      collegeSelect.value = election.allowed_colleges;
      loadUpdateCourses('student', election.allowed_courses || '');
    }
  }
  
  function initializeFacultyFields(election) {
    const collegeSelect = document.getElementById('update_allowed_colleges_faculty');
    if (!collegeSelect) return;
  
    if (election.allowed_colleges && election.allowed_colleges !== 'all') {
      collegeSelect.value = election.allowed_colleges;
    }
  
    if (election.allowed_status && election.allowed_status !== 'all') {
      const statuses = election.allowed_status.split(',').map(s => s.trim());
      document
        .querySelectorAll('#update_facultyFields input[name="allowed_status_faculty[]"]')
        .forEach(cb => cb.checked = statuses.includes(cb.value));
    }
  }
  
  function initializeNonAcademicFields(election) {
    const deptSelect = document.getElementById('update_allowed_departments_nonacad');
    if (deptSelect && election.allowed_departments) {
      if (election.allowed_departments.toLowerCase() === 'all') {
        deptSelect.value = 'all';
      } else {
        const depts = election.allowed_departments.split(',').map(d => d.trim());
        deptSelect.value = depts[0] || 'all';
      }
    }
  
    if (election.allowed_status) {
      if (election.allowed_status.toLowerCase() === 'all') {
        document
          .querySelectorAll('#update_nonAcademicFields input[name="allowed_status_nonacad[]"]')
          .forEach(cb => cb.checked = true);
      } else {
        const statuses = election.allowed_status.split(',').map(s => s.trim());
        document
          .querySelectorAll('#update_nonAcademicFields input[name="allowed_status_nonacad[]"]')
          .forEach(cb => cb.checked = statuses.includes(cb.value));
      }
    }
  }
  
  function initializeCoopFields(election) {
    const migsCheckbox = document.querySelector('#update_coopFields input[name="allowed_status_coop[]"]');
    if (!migsCheckbox) return;
  
    if (election.allowed_status && election.allowed_status !== 'all') {
      migsCheckbox.checked = election.allowed_status.includes('MIGS');
    } else {
      migsCheckbox.checked = true;
    }
  }
  
  // Called from PHP: openUpdateModal(<?= json_encode($election) ?>)
  function openUpdateModal(election) {
    const errBox = document.getElementById('updateFormError');
    if (errBox) {
      errBox.classList.add('hidden');
      errBox.textContent = '';
    }
  
    document.getElementById('update_election_id').value   = election.election_id;
    document.getElementById('update_election_name').value = election.title;
    document.getElementById('update_description').value   = election.description || '';
  
    // Dates
    const startDate = new Date(election.start_datetime);
    const endDate   = new Date(election.end_datetime);
  
    const fmt = d => {
      const yyyy = d.getFullYear();
      const mm   = String(d.getMonth() + 1).padStart(2, '0');
      const dd   = String(d.getDate()).padStart(2, '0');
      const hh   = String(d.getHours()).padStart(2, '0');
      const min  = String(d.getMinutes()).padStart(2, '0');
      return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
    };
  
    document.getElementById('update_start_datetime').value = fmt(startDate);
    document.getElementById('update_end_datetime').value   = fmt(endDate);
  
    // Assign admin
    const assignedAdminSelect = document.getElementById('update_assigned_admin_id');
    if (assignedAdminSelect) {
      if (election.assigned_admin_id) {
        const optionExists = Array.from(assignedAdminSelect.options)
          .some(opt => opt.value == election.assigned_admin_id);
  
        if (optionExists) {
          assignedAdminSelect.value = election.assigned_admin_id;
        } else {
          const tempOption = document.createElement('option');
          tempOption.value    = election.assigned_admin_id;
          tempOption.text     = "Unknown Admin (ID: " + election.assigned_admin_id + ")";
          tempOption.selected = true;
          assignedAdminSelect.appendChild(tempOption);
        }
      } else {
        assignedAdminSelect.value = "";
      }
    }
  
    // Show relevant fields based on target_position
    const targetPosition = (election.target_position || '').toLowerCase().replace(/\s+/g, '_');
    hideAllUpdateFields();
  
    if (targetPosition.includes('student')) {
      document.getElementById('update_target_student').checked = true;
      document.getElementById('update_studentFields').classList.remove('hidden');
      initializeStudentFields(election);
    } else if (targetPosition.includes('faculty')) {
      document.getElementById('update_target_faculty').checked = true;
      document.getElementById('update_facultyFields').classList.remove('hidden');
      initializeFacultyFields(election);
    } else if (targetPosition.includes('non-academic')) {
      document.getElementById('update_target_non_academic').checked = true;
      document.getElementById('update_nonAcademicFields').classList.remove('hidden');
      initializeNonAcademicFields(election);
    } else if (targetPosition.includes('coop')) {
      document.getElementById('update_target_coop').checked = true;
      document.getElementById('update_coopFields').classList.remove('hidden');
      initializeCoopFields(election);
    }
  
    // Show modal
    const modal = document.getElementById('updateModal');
    if (modal) modal.classList.remove('hidden');
  }
  
  function closeUpdateModal() {
    const modal = document.getElementById('updateModal');
    if (modal) modal.classList.add('hidden');
  }
  
  // DOM setup
  document.addEventListener('DOMContentLoaded', function() {
    // Target voter change in update modal
    const updateRadios = document.querySelectorAll('#updateModal input[name="target_voter"]');
    updateRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        hideAllUpdateFields();
        switch (this.value) {
          case 'student':
            document.getElementById('update_studentFields').classList.remove('hidden');
            break;
          case 'faculty':
            document.getElementById('update_facultyFields').classList.remove('hidden');
            break;
          case 'non_academic':
            document.getElementById('update_nonAcademicFields').classList.remove('hidden');
            break;
          case 'coop':
            document.getElementById('update_coopFields').classList.remove('hidden');
            break;
        }
      });
    });
  
    // College selects
    const studCollege = document.getElementById('update_allowed_colleges');
    const facCollege  = document.getElementById('update_allowed_colleges_faculty');
    if (studCollege) {
      studCollege.addEventListener('change', () => loadUpdateCourses('student'));
    }
    if (facCollege) {
      facCollege.addEventListener('change', () => loadUpdateCourses('faculty'));
    }
  
    // Clear button
    const clearBtn = document.getElementById('clearUpdateFormBtn');
    const form     = document.getElementById('updateElectionForm');
    const errBox   = document.getElementById('updateFormError');
  
    if (clearBtn && form) {
      clearBtn.addEventListener('click', () => {
        form.reset();
        hideAllUpdateFields();
        const sList = document.getElementById('update_studentCoursesList');
        const sCont = document.getElementById('update_studentCoursesContainer');
        if (sList) sList.innerHTML = '';
        if (sCont) sCont.classList.add('hidden');
        if (errBox) {
          errBox.classList.add('hidden');
          errBox.textContent = '';
        }
        const migs = document.querySelector('#update_coopFields input[name="allowed_status_coop[]"]');
        if (migs) migs.checked = true;
      });
    }
  
    // Client-side validation before submit (dates, required)
    if (form && errBox) {
      form.addEventListener('submit', (e) => {
        errBox.classList.add('hidden');
        errBox.textContent = '';
  
        const name  = form.querySelector('#update_election_name')?.value.trim();
        const start = form.querySelector('#update_start_datetime')?.value;
        const end   = form.querySelector('#update_end_datetime')?.value;
        const admin = form.querySelector('#update_assigned_admin_id')?.value;
  
        if (!name || !start || !end || !admin) {
          e.preventDefault();
          errBox.textContent = 'Please fill in all required fields.';
          errBox.classList.remove('hidden');
          return;
        }
  
        const startDate = new Date(start);
        const endDate   = new Date(end);
        if (isNaN(startDate) || isNaN(endDate)) {
          e.preventDefault();
          errBox.textContent = 'Invalid date/time format.';
          errBox.classList.remove('hidden');
          return;
        }
  
        if (endDate <= startDate) {
          e.preventDefault();
          errBox.textContent = 'End date/time must be after the start date/time.';
          errBox.classList.remove('hidden');
          return;
        }
      });
    }
  });
  