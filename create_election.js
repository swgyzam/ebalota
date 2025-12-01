// === CREATE ELECTION JS ===

// Map colleges to courses
const collegeCoursesCreate = {
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

// Academic departments by college (mirrors admin_functions.php)
const collegeDepartmentsCreate = {
  "CAFENR": {
    "DAS": "Department of Animal Science",
    "DCS": "Department of Crop Science",
    "DFST": "Department of Food Science and Technology",
    "DFES": "Department of Forestry and Environmental Science",
    "DAED": "Department of Agricultural Economics and Development"
  },
  "CAS": {
    "DBS": "Department of Biological Sciences",
    "DPS": "Department of Physical Sciences",
    "DLMC": "Department of Languages and Mass Communication",
    "DSS": "Department of Social Sciences",
    "DMS": "Department of Mathematics and Statistics"
  },
  "CCJ": {
    "DCJ": "Department of Criminal Justice"
  },
  "CEMDS": {
    "DE": "Department of Economics",
    "DBM": "Department of Business and Management",
    "DDS": "Department of Development Studies"
  },
  "CED": {
    "DSE": "Department of Science Education",
    "DTLE": "Department of Technology and Livelihood Education",
    "DCI": "Department of Curriculum and Instruction",
    "DHK": "Department of Human Kinetics"
  },
  "CEIT": {
    "DCE": "Department of Civil Engineering",
    "DCEE": "Department of Computer and Electronics Engineering",
    "DIET": "Department of Industrial Engineering and Technology",
    "DMEE": "Department of Mechanical and Electronics Engineering",
    "DIT": "Department of Information Technology"
  },
  "CON": {
    "DN": "Department of Nursing"
  },
  "COM": {
    "DBMS": "Department of Basic Medical Sciences",
    "DCS": "Department of Clinical Sciences"
  },
  "CSPEAR": {
    "DPER": "Department of Physical Education and Recreation"
  },
  "CVMBS": {
    "DVM": "Department of Veterinary Medicine",
    "DBS": "Department of Biomedical Sciences"
  },
  "GS-OLC": {
    "DVGP": "Department of Various Graduate Programs"
  }
};

function toggleAllCheckboxes(name) {
  const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
  if (!checkboxes.length) return;
  const allChecked = Array.from(checkboxes).every(cb => cb.checked);
  checkboxes.forEach(cb => cb.checked = !allChecked);
}

function buildCourseCheckboxesCreate(college, listEl, fieldName) {
  if (!listEl) return;
  const courses = collegeCoursesCreate[college] || [];
  listEl.innerHTML = '';
  courses.forEach(course => {
    listEl.innerHTML += `
      <label class="flex items-center">
        <input type="checkbox" name="${fieldName}" value="${course}" class="mr-1">
        ${course}
      </label>
    `;
  });
}

function buildDeptCheckboxesCreate(college, listEl, fieldName) {
  if (!listEl) return;
  const depts = collegeDepartmentsCreate[college] || {};
  listEl.innerHTML = '';
  Object.keys(depts).forEach(code => {
    listEl.innerHTML += `
      <label class="flex items-center">
        <input type="checkbox" name="${fieldName}" value="${code}" class="mr-1">
        ${code} - ${depts[code]}
      </label>
    `;
  });
}

document.addEventListener('DOMContentLoaded', function() {
  const targetRadios      = document.querySelectorAll('#createElectionForm input[name="target_voter"]');
  const studentFields     = document.getElementById('studentFields');
  const academicFields    = document.getElementById('academicFields');
  const nonAcademicFields = document.getElementById('nonAcademicFields');
  const othersNote        = document.getElementById('create_othersNote');

  const openModalBtn      = document.getElementById('openModalBtn');
  const closeModalBtn     = document.getElementById('closeModalBtn');
  const modal             = document.getElementById('modal');
  const createForm        = document.getElementById('createElectionForm');
  const createError       = document.getElementById('createFormError');
  const clearBtn          = document.getElementById('clearFormBtn');

  const studentCollegeSelect      = document.getElementById('studentCollegeSelect');
  const studentCoursesContainer   = document.getElementById('studentCoursesContainer');
  const studentCoursesList        = document.getElementById('studentCoursesList');

  const academicCollegeSelect          = document.getElementById('academicCollegeSelect');
  const academicDepartmentsContainer   = document.getElementById('academicDepartmentsContainer');
  const academicDepartmentsList        = document.getElementById('academicDepartmentsList');

  function hideAllFields() {
    if (studentFields)     studentFields.classList.add('hidden');
    if (academicFields)    academicFields.classList.add('hidden');
    if (nonAcademicFields) nonAcademicFields.classList.add('hidden');
  }

  function hideOthersNote() {
    if (othersNote) othersNote.classList.add('hidden');
  }

  // Target voter change → show relevant fields + Others note
  targetRadios.forEach(radio => {
    radio.addEventListener('change', e => {
      hideAllFields();
      hideOthersNote();
      const val = e.target.value;

      if (val === 'student') {
        if (studentFields) studentFields.classList.remove('hidden');
      } else if (val === 'academic') {
        if (academicFields) academicFields.classList.remove('hidden');
      } else if (val === 'non_academic') {
        if (nonAcademicFields) nonAcademicFields.classList.remove('hidden');
      } else if (val === 'others') {
        // Others: no extra fields, just the note
        if (othersNote) othersNote.classList.remove('hidden');
      }
    });
  });

  // Open / Close create modal
  if (openModalBtn && modal) {
    openModalBtn.addEventListener('click', () => {
      if (createError) {
        createError.classList.add('hidden');
        createError.textContent = '';
      }
      if (createForm) createForm.reset();
      hideAllFields();
      hideOthersNote();
      if (studentCoursesContainer) studentCoursesContainer.classList.add('hidden');
      if (studentCoursesList) studentCoursesList.innerHTML = '';
      if (academicDepartmentsContainer) academicDepartmentsContainer.classList.add('hidden');
      if (academicDepartmentsList) academicDepartmentsList.innerHTML = '';
      modal.classList.remove('hidden');
    });
  }
  if (closeModalBtn && modal) {
    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    window.addEventListener('click', e => {
      if (e.target === modal) modal.classList.add('hidden');
    });
  }

  // Clear form
  if (clearBtn && createForm) {
    clearBtn.addEventListener('click', () => {
      createForm.reset();
      hideAllFields();
      hideOthersNote();
      if (studentCoursesContainer) studentCoursesContainer.classList.add('hidden');
      if (studentCoursesList) studentCoursesList.innerHTML = '';
      if (academicDepartmentsContainer) academicDepartmentsContainer.classList.add('hidden');
      if (academicDepartmentsList) academicDepartmentsList.innerHTML = '';
      if (createError) {
        createError.classList.add('hidden');
        createError.textContent = '';
      }
      document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    });
  }

  // Student college → courses
  if (studentCollegeSelect && studentCoursesContainer && studentCoursesList) {
    studentCollegeSelect.addEventListener('change', () => {
      const college = studentCollegeSelect.value;
      if (college === 'all') {
        studentCoursesContainer.classList.add('hidden');
        studentCoursesList.innerHTML = '';
        return;
      }
      buildCourseCheckboxesCreate(college, studentCoursesList, 'allowed_courses_student[]');
      studentCoursesContainer.classList.remove('hidden');
    });
  }

  // Academic college → departments
  if (academicCollegeSelect && academicDepartmentsContainer && academicDepartmentsList) {
    academicCollegeSelect.addEventListener('change', () => {
      const college = academicCollegeSelect.value;
      if (college === 'all') {
        academicDepartmentsContainer.classList.add('hidden');
        academicDepartmentsList.innerHTML = '';
        return;
      }
      buildDeptCheckboxesCreate(college, academicDepartmentsList, 'allowed_departments_faculty[]');
      academicDepartmentsContainer.classList.remove('hidden');
    });
  }

  // Simple date validation + submit via fetch
  if (createForm && createError) {
    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      createError.classList.add('hidden');
      createError.textContent = '';

      const formData = new FormData(createForm);

      const name   = (formData.get('election_name') || '').trim();
      const start  = formData.get('start_datetime');
      const end    = formData.get('end_datetime');
      const target = formData.get('target_voter');
      const admin  = formData.get('assigned_admin_id');

      if (!name || !start || !end || !target || !admin) {
        createError.textContent = 'Please fill in all required fields.';
        createError.classList.remove('hidden');
        return;
      }

      const startDate = new Date(start);
      const endDate   = new Date(end);
      if (isNaN(startDate) || isNaN(endDate)) {
        createError.textContent = 'Invalid date/time format.';
        createError.classList.remove('hidden');
        return;
      }
      if (endDate <= startDate) {
        createError.textContent = 'End date/time must be after the start date/time.';
        createError.classList.remove('hidden');
        return;
      }

      try {
        const res  = await fetch('create_election.php', { method: 'POST', body: formData });
        const data = await res.json().catch(() => null);

        if (!data) {
          createError.textContent = 'Unexpected server response.';
          createError.classList.remove('hidden');
          return;
        }

        if (data.status === 'error') {
          createError.textContent = data.message || 'An error occurred while creating the election.';
          createError.classList.remove('hidden');
        } else {
          window.location.reload();
        }
      } catch (err) {
        console.error(err);
        createError.textContent = 'Network or server error. Please try again.';
        createError.classList.remove('hidden');
      }
    });
  }
});
