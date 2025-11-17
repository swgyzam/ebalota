// === CREATE ELECTION JS ===

// Map colleges to courses
const collegeCourses = {
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

function loadCourses(type) {
  if (type === 'academic') return; // no per-college course list for faculty

  const collegeSelect    = document.getElementById(`${type}CollegeSelect`);
  const coursesContainer = document.getElementById(`${type}CoursesContainer`);
  const coursesList      = document.getElementById(`${type}CoursesList`);

  if (!collegeSelect || !coursesContainer || !coursesList) return;

  if (collegeSelect.value === 'all') {
    coursesContainer.classList.add('hidden');
    coursesList.innerHTML = '';
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

function toggleAllCheckboxes(name) {
  const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
  if (checkboxes.length === 0) return;
  const allChecked = Array.from(checkboxes).every(cb => cb.checked);
  checkboxes.forEach(cb => cb.checked = !allChecked);
}

document.addEventListener('DOMContentLoaded', function() {
  const targetRadios      = document.querySelectorAll('input[name="target_voter"]');
  const studentFields     = document.getElementById('studentFields');
  const academicFields    = document.getElementById('academicFields');
  const nonAcademicFields = document.getElementById('nonAcademicFields');
  const coopFields        = document.getElementById('coopFields');
  const openModalBtn      = document.getElementById('openModalBtn');
  const closeModalBtn     = document.getElementById('closeModalBtn');
  const modal             = document.getElementById('modal');
  const createForm        = document.getElementById('createElectionForm');
  const createError       = document.getElementById('createFormError');
  const clearBtn          = document.getElementById('clearFormBtn');

  function hideAllFields() {
    if (studentFields)     studentFields.classList.add('hidden');
    if (academicFields)    academicFields.classList.add('hidden');
    if (nonAcademicFields) nonAcademicFields.classList.add('hidden');
    if (coopFields)        coopFields.classList.add('hidden');
  }

  // Target voter change → show sections
  targetRadios.forEach(radio => {
    radio.addEventListener('change', e => {
      hideAllFields();
      switch (e.target.value) {
        case 'student':
          if (studentFields) studentFields.classList.remove('hidden');
          break;
        case 'academic':
          if (academicFields) academicFields.classList.remove('hidden');
          break;
        case 'non_academic':
          if (nonAcademicFields) nonAcademicFields.classList.remove('hidden');
          break;
        case 'others':
          if (coopFields) coopFields.classList.remove('hidden');
          break;
      }
    });
  });

  // Default MIGS checked (optional)
  const coopCheckbox = document.querySelector('input[name="allowed_status_coop[]"]');
  if (coopCheckbox) coopCheckbox.checked = true;

  // Open / Close modal
  if (openModalBtn && modal) {
    openModalBtn.addEventListener('click', () => {
      if (createError) {
        createError.classList.add('hidden');
        createError.textContent = '';
      }
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
      const collegeStudent = document.getElementById('studentCollegeSelect');
      const coursesStudent = document.getElementById('studentCoursesList');
      const containerStudent = document.getElementById('studentCoursesContainer');
      if (collegeStudent) collegeStudent.value = 'all';
      if (coursesStudent) coursesStudent.innerHTML = '';
      if (containerStudent) containerStudent.classList.add('hidden');
      if (createError) {
        createError.classList.add('hidden');
        createError.textContent = '';
      }
      if (coopCheckbox) coopCheckbox.checked = true;
    });
  }

  // Client-side validation + AJAX submit
  if (createForm && createError) {
    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      createError.classList.add('hidden');
      createError.textContent = '';

      const formData = new FormData(createForm);

      const name   = formData.get('election_name')?.trim();
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
      if (!(startDate instanceof Date) || !(endDate instanceof Date) || isNaN(startDate) || isNaN(endDate)) {
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
          // Success: reload the page to see new election
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
