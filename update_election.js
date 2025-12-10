// === UPDATE ELECTION JS ===

// Same college → courses map as create
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

// Academic departments (same as create)
const collegeDepartmentsUpdate = {
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

function buildDeptCheckboxesUpdate(college, listEl, fieldName) {
  if (!listEl) return;
  const depts = collegeDepartmentsUpdate[college] || {};
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

function hideAllUpdateFields() {
  const s = document.getElementById('update_studentFields');
  const f = document.getElementById('update_facultyFields');
  const n = document.getElementById('update_nonAcademicFields');
  if (s) s.classList.add('hidden');
  if (f) f.classList.add('hidden');
  if (n) n.classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  const updateModal        = document.getElementById('updateModal');
  const closeUpdateBtn     = document.getElementById('closeUpdateModalBtn');
  const updateForm         = document.getElementById('updateElectionForm');
  const updateError        = document.getElementById('updateFormError');
  const clearUpdateFormBtn = document.getElementById('clearUpdateFormBtn');

  const updateStudentFields     = document.getElementById('update_studentFields');
  const updateFacultyFields     = document.getElementById('update_facultyFields');
  const updateNonAcademicFields = document.getElementById('update_nonAcademicFields');
  const updateOthersNote        = document.getElementById('update_othersNote');

  const updateStudentCollegeSelect    = document.getElementById('update_allowed_colleges');
  const updateStudentCoursesContainer = document.getElementById('update_studentCoursesContainer');
  const updateStudentCoursesList      = document.getElementById('update_studentCoursesList');

  const updateFacultyCollegeSelect        = document.getElementById('update_allowed_colleges_faculty');
  const updateFacultyDepartmentsContainer = document.getElementById('update_facultyDepartmentsContainer');
  const updateFacultyDepartmentsList      = document.getElementById('update_facultyDepartmentsList');

  const updateSelectAllStudentCourses     = document.getElementById('update_selectAllStudentCourses');
  const updateSelectAllFacultyStatus      = document.getElementById('update_selectAllFacultyStatus');
  const updateSelectAllNonAcadStatus      = document.getElementById('update_selectAllNonAcadStatus');
  const updateSelectAllFacultyDepartments = document.getElementById('update_selectAllFacultyDepartments');

  const updateAdminSelect = document.getElementById('update_assigned_admin_id');

  // Logo preview elements
  const updateLogoInput       = document.getElementById('update_logo');
  const updateLogoPreview     = document.getElementById('update_logo_preview');
  const updateLogoPlaceholder = document.getElementById('update_logo_placeholder');

  function resetUpdateLogoPreview() {
    if (updateLogoPreview) {
      updateLogoPreview.src = '';
      updateLogoPreview.classList.add('hidden');
    }
    if (updateLogoPlaceholder) {
      updateLogoPlaceholder.classList.remove('hidden');
    }
  }

  function hideOthersNoteUpdate() {
    if (updateOthersNote) updateOthersNote.classList.add('hidden');
  }

  // === Filter admins in update modal based on target ===
  function filterUpdateAdminsByTarget(target) {
    if (!updateAdminSelect) return;

    const map = {
      student:      ['Academic-Student', 'Special-Scope'],
      faculty:      ['Academic-Faculty'],
      non_academic:['Non-Academic-Employee'],
      others:       ['Others']
    };

    const allowedScopes = map[target] || null;

    Array.from(updateAdminSelect.options).forEach((opt, idx) => {
      if (idx === 0) {
        opt.disabled = false;
        opt.hidden   = false;
        return;
      }
      const scope = opt.dataset.scope || '';
      if (!allowedScopes) {
        opt.disabled = false;
        opt.hidden   = false;
      } else {
        const ok = allowedScopes.includes(scope);
        opt.disabled = !ok;
        opt.hidden   = !ok;
      }
    });

    if (updateAdminSelect.selectedOptions.length &&
        updateAdminSelect.selectedOptions[0].hidden) {
      updateAdminSelect.value = '';
    }
  }

  // Close update modal
  if (closeUpdateBtn && updateModal) {
    closeUpdateBtn.addEventListener('click', () => updateModal.classList.add('hidden'));
    window.addEventListener('click', e => {
      if (e.target === updateModal) updateModal.classList.add('hidden');
    });
  }

  // Target voter change (UPDATE)
  document.querySelectorAll('#updateElectionForm input[name="target_voter"]').forEach(radio => {
    radio.addEventListener('change', e => {
      hideAllUpdateFields();
      hideOthersNoteUpdate();
      const v = e.target.value;

      if (v === 'student') {
        updateStudentFields && updateStudentFields.classList.remove('hidden');
      } else if (v === 'faculty') {
        updateFacultyFields && updateFacultyFields.classList.remove('hidden');
      } else if (v === 'non_academic') {
        updateNonAcademicFields && updateNonAcademicFields.classList.remove('hidden');
      } else if (v === 'others') {
        updateOthersNote && updateOthersNote.classList.remove('hidden');
      }

      filterUpdateAdminsByTarget(v);
    });
  });

  // Logo preview change
  if (updateLogoInput && updateLogoPreview) {
    updateLogoInput.addEventListener('change', () => {
      const file = updateLogoInput.files && updateLogoInput.files[0];
      if (!file) {
        resetUpdateLogoPreview();
        return;
      }
      const url = URL.createObjectURL(file);
      updateLogoPreview.src = url;
      updateLogoPreview.classList.remove('hidden');
      if (updateLogoPlaceholder) updateLogoPlaceholder.classList.add('hidden');
    });
  }

  // Load courses for UPDATE student
  if (updateStudentCollegeSelect && updateStudentCoursesContainer && updateStudentCoursesList) {
    updateStudentCollegeSelect.addEventListener('change', () => {
      const college = updateStudentCollegeSelect.value;
      if (college === 'all') {
        updateStudentCoursesContainer.classList.add('hidden');
        updateStudentCoursesList.innerHTML = '';
        return;
      }
      const courses = collegeCoursesUpdate[college] || [];
      updateStudentCoursesList.innerHTML = '';
      courses.forEach(course => {
        updateStudentCoursesList.innerHTML += `
          <label class="flex items-center">
            <input type="checkbox" name="allowed_courses_student[]" value="${course}" class="mr-1">
            ${course}
          </label>
        `;
      });
      updateStudentCoursesContainer.classList.remove('hidden');
    });
  }

  // Load departments for UPDATE faculty
  if (updateFacultyCollegeSelect && updateFacultyDepartmentsContainer && updateFacultyDepartmentsList) {
    updateFacultyCollegeSelect.addEventListener('change', () => {
      const college = updateFacultyCollegeSelect.value;
      if (college === 'all') {
        updateFacultyDepartmentsContainer.classList.add('hidden');
        updateFacultyDepartmentsList.innerHTML = '';
        return;
      }
      buildDeptCheckboxesUpdate(college, updateFacultyDepartmentsList, 'allowed_departments_faculty[]');
      updateFacultyDepartmentsContainer.classList.remove('hidden');
    });
  }

  // Select all helpers
  if (updateSelectAllStudentCourses) {
    updateSelectAllStudentCourses.addEventListener('click', () =>
      toggleAllCheckboxes('allowed_courses_student[]')
    );
  }
  if (updateSelectAllFacultyStatus) {
    updateSelectAllFacultyStatus.addEventListener('click', () =>
      toggleAllCheckboxes('allowed_status_faculty[]')
    );
  }
  if (updateSelectAllNonAcadStatus) {
    updateSelectAllNonAcadStatus.addEventListener('click', () =>
      toggleAllCheckboxes('allowed_status_nonacad[]')
    );
  }
  if (updateSelectAllFacultyDepartments) {
    updateSelectAllFacultyDepartments.addEventListener('click', () =>
      toggleAllCheckboxes('allowed_departments_faculty[]')
    );
  }

  // Clear button (UPDATE)
  if (clearUpdateFormBtn && updateForm) {
    clearUpdateFormBtn.addEventListener('click', () => {
      updateForm.reset();
      hideAllUpdateFields();
      hideOthersNoteUpdate();
      if (updateStudentCoursesContainer) updateStudentCoursesContainer.classList.add('hidden');
      if (updateStudentCoursesList) updateStudentCoursesList.innerHTML = '';
      if (updateFacultyDepartmentsContainer) updateFacultyDepartmentsContainer.classList.add('hidden');
      if (updateFacultyDepartmentsList) updateFacultyDepartmentsList.innerHTML = '';
      if (updateError) {
        updateError.classList.add('hidden');
        updateError.textContent = '';
      }
      document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
      resetUpdateLogoPreview();
      filterUpdateAdminsByTarget(null);
    });
  }

  // Client-side validation before submit
  if (updateForm && updateError) {
    updateForm.addEventListener('submit', (e) => {
      updateError.classList.add('hidden');
      updateError.textContent = '';

      const name  = (document.getElementById('update_election_name')?.value || '').trim();
      const start = document.getElementById('update_start_datetime')?.value;
      const end   = document.getElementById('update_end_datetime')?.value;
      const admin = document.getElementById('update_assigned_admin_id')?.value;

      if (!name || !start || !end || !admin) {
        e.preventDefault();
        updateError.textContent = 'Please fill in all required fields.';
        updateError.classList.remove('hidden');
        return;
      }

      const startDate = new Date(start);
      const endDate   = new Date(end);
      if (isNaN(startDate) || isNaN(endDate)) {
        e.preventDefault();
        updateError.textContent = 'Invalid date/time format.';
        updateError.classList.remove('hidden');
        return;
      }

      if (endDate <= startDate) {
        e.preventDefault();
        updateError.textContent = 'End date/time must be after the start date/time.';
        updateError.classList.remove('hidden');
        return;
      }
    });
  }

  // Expose global openUpdateModal for PHP onclick
  window.openUpdateModal = function(election) {
    if (!updateModal || !updateForm) return;
    updateForm.reset();
    hideAllUpdateFields();
    hideOthersNoteUpdate();

    if (updateStudentCoursesContainer) updateStudentCoursesContainer.classList.add('hidden');
    if (updateStudentCoursesList) updateStudentCoursesList.innerHTML = '';
    if (updateFacultyDepartmentsContainer) updateFacultyDepartmentsContainer.classList.add('hidden');
    if (updateFacultyDepartmentsList) updateFacultyDepartmentsList.innerHTML = '';
    if (updateError) {
      updateError.classList.add('hidden');
      updateError.textContent = '';
    }
    document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    resetUpdateLogoPreview();

    // Populate base fields
    document.getElementById('update_election_id').value   = election.election_id;
    document.getElementById('update_election_name').value = election.title || '';
    document.getElementById('update_description').value   = election.description || '';

    const startDateObj = new Date(election.start_datetime);
    const endDateObj   = new Date(election.end_datetime);
    const fmt = d => {
      const yyyy = d.getFullYear();
      const mm   = String(d.getMonth() + 1).padStart(2,'0');
      const dd   = String(d.getDate()).padStart(2,'0');
      const hh   = String(d.getHours()).padStart(2,'0');
      const mi   = String(d.getMinutes()).padStart(2,'0');
      return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
    };
    document.getElementById('update_start_datetime').value = fmt(startDateObj);
    document.getElementById('update_end_datetime').value   = fmt(endDateObj);

    // Logo preview from existing logo_path
    if (election.logo_path && updateLogoPreview) {
      updateLogoPreview.src = election.logo_path;
      updateLogoPreview.classList.remove('hidden');
      if (updateLogoPlaceholder) updateLogoPlaceholder.classList.add('hidden');
    } else {
      resetUpdateLogoPreview();
    }

    // Determine target position
    const pos = (election.target_position || '').toLowerCase();
    let targetForFilter = null;

    hideAllUpdateFields();
    hideOthersNoteUpdate();

    if (pos.includes('student')) {
      targetForFilter = 'student';
      const r = document.getElementById('update_target_student');
      if (r) r.checked = true;
      updateStudentFields && updateStudentFields.classList.remove('hidden');

      if (updateStudentCollegeSelect) {
        // 1. Normalise DB value → select value
        let col = (election.allowed_colleges || '').trim();
        if (!col || col.toLowerCase() === 'all') {
          col = 'all'; // dapat tumama sa <option value="all">
        }
      
        updateStudentCollegeSelect.value = col;
      
        // 2. Safety net: kung wala pa ring selected (blank pa rin sa UI),
        //    gumawa tayo ng option para sa value na 'col'
        if (!updateStudentCollegeSelect.value) {
          const opt = document.createElement('option');
          opt.value = col;
          opt.textContent = (col === 'all') ? 'All Colleges' : col;
          // ilagay sa unahan para consistent
          updateStudentCollegeSelect.insertBefore(opt, updateStudentCollegeSelect.firstChild);
          updateStudentCollegeSelect.value = col;
        }
      
        // 3. Courses handling
        if (col === 'all') {
          // All Colleges → walang specific course filter
          if (updateStudentCoursesContainer) updateStudentCoursesContainer.classList.add('hidden');
          if (updateStudentCoursesList) updateStudentCoursesList.innerHTML = '';
        } else {
          const courses = (election.allowed_courses || '')
            .split(',')
            .map(c => c.trim())
            .filter(Boolean);
      
          const cc = collegeCoursesUpdate[col] || [];
          if (cc.length && updateStudentCoursesContainer && updateStudentCoursesList) {
            updateStudentCoursesList.innerHTML = '';
            cc.forEach(course => {
              const checked = courses.includes(course) ? 'checked' : '';
              updateStudentCoursesList.innerHTML += `
                <label class="flex items-center">
                  <input type="checkbox" name="allowed_courses_student[]" value="${course}" class="mr-1" ${checked}>
                  ${course}
                </label>
              `;
            });
            updateStudentCoursesContainer.classList.remove('hidden');
          } else {
            // safety kung walang map
            updateStudentCoursesContainer.classList.add('hidden');
            updateStudentCoursesList.innerHTML = '';
          }
        }
      }
    } else if (pos.includes('faculty')) {
      targetForFilter = 'faculty';
      const r = document.getElementById('update_target_faculty');
      if (r) r.checked = true;
      updateFacultyFields && updateFacultyFields.classList.remove('hidden');

      let college = (election.allowed_colleges || '').trim();
      if (!college || college.toLowerCase() === 'all') {
        college = 'all';
      }
      if (updateFacultyCollegeSelect) {
        updateFacultyCollegeSelect.value = college;
      }

      const deptStr = (election.allowed_departments || '').trim();
      if (college.toLowerCase() !== 'all') {
        buildDeptCheckboxesUpdate(college, updateFacultyDepartmentsList, 'allowed_departments_faculty[]');
        if (deptStr && deptStr.toLowerCase() !== 'all') {
          const selected = deptStr.split(',').map(d=>d.trim());
          updateFacultyDepartmentsList
            .querySelectorAll('input[type="checkbox"]')
            .forEach(cb => {
              if (selected.includes(cb.value)) cb.checked = true;
            });
        }
        updateFacultyDepartmentsContainer && updateFacultyDepartmentsContainer.classList.remove('hidden');
      } else {
        updateFacultyDepartmentsContainer && updateFacultyDepartmentsContainer.classList.add('hidden');
        if (updateFacultyDepartmentsList) updateFacultyDepartmentsList.innerHTML = '';
      }

      if (election.allowed_status && election.allowed_status.toLowerCase() !== 'all') {
        const statuses = election.allowed_status.split(',').map(s=>s.trim());
        document
          .querySelectorAll('#update_facultyFields input[name="allowed_status_faculty[]"]')
          .forEach(cb => cb.checked = statuses.includes(cb.value));
      }

    } else if (pos.includes('non-academic')) {
      targetForFilter = 'non_academic';
      const r = document.getElementById('update_target_non_academic');
      if (r) r.checked = true;
      updateNonAcademicFields && updateNonAcademicFields.classList.remove('hidden');

      if (election.allowed_departments && election.allowed_departments !== 'All') {
        const depts = election.allowed_departments.split(',').map(d=>d.trim());
        const sel   = document.getElementById('update_allowed_departments_nonacad');
        if (sel) sel.value = depts[0] || 'all';
      }
      if (election.allowed_status && election.allowed_status.toLowerCase() !== 'all') {
        const statuses = election.allowed_status.split(',').map(s=>s.trim());
        document
          .querySelectorAll('#update_nonAcademicFields input[name="allowed_status_nonacad[]"]')
          .forEach(cb => cb.checked = statuses.includes(cb.value));
      }

    } else if (pos.includes('others') || pos.includes('coop')) {
      targetForFilter = 'others';
      const r = document.getElementById('update_target_others');
      if (r) r.checked = true;
      updateOthersNote && updateOthersNote.classList.remove('hidden');
    }

    // Filter admins for this target, then set assigned admin
    filterUpdateAdminsByTarget(targetForFilter);

    if (updateAdminSelect) {
      if (election.assigned_admin_id) {
        const exists = Array.from(updateAdminSelect.options)
          .some(o => o.value == election.assigned_admin_id && !o.hidden);

        if (exists) {
          updateAdminSelect.value = election.assigned_admin_id;
        } else {
          // fallback: show as unknown if it doesn't match scope mapping
          let opt = Array.from(updateAdminSelect.options)
            .find(o => o.value == election.assigned_admin_id);
          if (!opt) {
            opt = document.createElement('option');
            opt.value = election.assigned_admin_id;
            opt.text  = 'Unknown Admin (ID: ' + election.assigned_admin_id + ')';
            updateAdminSelect.appendChild(opt);
          }
          opt.hidden   = false;
          opt.disabled = false;
          updateAdminSelect.value = election.assigned_admin_id;
        }
      } else {
        updateAdminSelect.value = '';
      }
    }

    updateModal && updateModal.classList.remove('hidden');
  };
});
