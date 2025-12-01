<?php
// admin_modal_create.php
include_once 'admin_functions.php'; // taxonomy & helpers

// Preload taxonomy into PHP variables for JS
$colleges             = getColleges();
$academicDepartments  = getAcademicDepartments();
$nonAcademicDepts     = getNonAcademicDepartments();

// Build courses-by-college map
$coursesByCollege = [];
foreach (array_keys($colleges) as $collegeCode) {
    $courses = getCoursesByCollege($collegeCode);
    $coursesArray = [];
    foreach ($courses as $code => $name) {
        $coursesArray[] = ['code' => $code, 'name' => $name];
    }
    $coursesByCollege[$collegeCode] = $coursesArray;
}
?>
<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 text-center relative transform transition-all scale-100">
    <button onclick="closeSuccessModal()"
            class="absolute top-4 right-4 text-gray-400 hover:text-green-600 text-4xl font-bold leading-none">
      &times;
    </button>

    <div class="w-20 h-20 mx-auto mb-4 bg-green-100 text-green-600 rounded-full flex items-center justify-center shadow-md">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>

    <h2 class="text-2xl font-bold text-[var(--cvsu-green)] mb-2">Admin Successfully Created</h2>
    <p class="text-gray-600 text-sm mb-4">Credentials have been emailed and account is now active.</p>

    <button onclick="closeSuccessModal()"
            class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-6 py-2 rounded-full text-sm font-semibold mt-2 transition">
      Close
    </button>
  </div>
</div>

<!-- Create Admin Modal with Scrollable Structure -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative flex flex-col" style="max-height: 90vh;">
    <!-- Fixed Header -->
    <div class="p-6 border-b flex-shrink-0">
      <h2 class="text-xl font-bold text-[var(--cvsu-green-dark)]">Create New Admin</h2>
      <button onclick="closeCreateModal()" class="absolute top-4 right-4 text-gray-500 hover:text-red-600 text-3xl font-bold leading-none">
        &times;
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="flex-grow overflow-y-auto p-6 modal-body">
      <div id="formError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm mb-4"></div>
      <form id="createAdminFormModal" class="space-y-4">
        <div>
          <label class="block font-semibold">Admin Title</label>
          <input type="text" name="admin_title" required class="w-full p-2 border rounded"
                 placeholder="e.g., CEIT BSIT Coordinator">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold">First Name</label>
            <input type="text" name="first_name" required class="w-full p-2 border rounded" />
          </div>
          <div>
            <label class="block font-semibold">Last Name</label>
            <input type="text" name="last_name" required class="w-full p-2 border rounded" />
          </div>
        </div>

        <div>
          <label class="block font-semibold">Email</label>
          <input type="email" name="email" required class="w-full p-2 border rounded" />
        </div>

        <div>
          <label class="block font-semibold">Scope Category</label>
          <select name="scope_category" id="scopeCategoryModal" required class="w-full p-2 border rounded" onchange="updateScopeFields()">
            <option value="">Select Scope Category</option>
            <option value="Academic-Student">Academic - Student</option>
            <option value="Non-Academic-Student">Non-Academic - Student</option>
            <option value="Academic-Faculty">Academic - Faculty</option>
            <option value="Non-Academic-Employee">Non-Academic - Employee</option>
            <option value="Others">Others</option>
            <option value="Special-Scope">Special Scope - CSG Admin</option>
          </select>
        </div>

        <div id="dynamicScopeFieldsModal" class="space-y-4">
            <!-- Dynamic fields will be inserted here -->
        </div>

        <div>
          <label class="block font-semibold mb-1">Auto-Generated Password</label>
          <div class="flex items-center gap-2">
            <input type="text" name="password" id="generatedPasswordModal" readonly
                   class="w-full p-2 border rounded bg-gray-100 font-mono text-sm" />
            <button type="button" onclick="copyPassword()" class="text-gray-500 hover:text-gray-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16h8M8 12h8m-7 8h6a2 2 0 002-2V8a2 2 0 00-2-2h-2.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0012.586 4H8a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </button>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t mt-6">
          <button type="button" onclick="clearCreateAdminForm()"
                  class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded font-semibold">
            Clear
          </button>
          <button type="submit" id="submitBtnModal"
                  class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-4 py-2 rounded font-semibold flex items-center justify-center gap-2">
            <span id="submitBtnTextModal">Create Admin</span>
            <svg id="loaderIconModal" class="w-5 h-5 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"></circle>
              <path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Include Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Modal Scroll Fix */
.modal-body {
    overflow-y: auto;
    max-height: calc(90vh - 200px);
}

/* Custom scrollbar */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Read-only note helper (used in other modals) */
.read-only-note {
    color: #6b7280;
    font-style: italic;
}
</style>
<script>
// === PHP data bootstrapped into JS ===
const colleges = <?php echo json_encode($colleges); ?>;
const academicDepartments = <?php echo json_encode($academicDepartments); ?>;
const nonAcademicDepartments = <?php echo json_encode($nonAcademicDepts); ?>;
const coursesByCollege = <?php echo json_encode($coursesByCollege); ?>;

console.log('coursesByCollege object:', coursesByCollege);

// === Dynamic scope fields ===
function updateScopeFields() {
    const scopeCategory = document.getElementById('scopeCategoryModal');
    const container     = document.getElementById('dynamicScopeFieldsModal');
    const scopeError    = document.getElementById('scopeValidationError');

    console.log('updateScopeFields called with:', scopeCategory ? scopeCategory.value : 'not found');

    if (!scopeCategory || !container) {
        console.error('Required elements not found:', {scopeCategory: !!scopeCategory, container: !!container});
        return;
    }

    // clear dynamic fields + scope error
    container.innerHTML = '';
    if (scopeError) {
        scopeError.textContent = '';
        scopeError.classList.add('hidden');
    }

    switch(scopeCategory.value) {
        case 'Academic-Student':
            container.innerHTML = getAcademicStudentFields();
            break;
        case 'Non-Academic-Student':
            container.innerHTML = getNonAcademicStudentFields();
            break;
        case 'Academic-Faculty':
            container.innerHTML = getAcademicFacultyFields();
            break;
        case 'Non-Academic-Employee':
            container.innerHTML = getNonAcademicEmployeeFields();
            break;
        case 'Others':
            container.innerHTML = getUnifiedOthersFields();
            break;
        case 'Special-Scope':
            container.innerHTML = getSpecialScopeFields();
            break;
        default:
            container.innerHTML = '<p class="text-gray-500">Select a scope category to see options</p>';
    }
}

// Academic-Student Fields
function getAcademicStudentFields() {
    return `
        <div>
            <label class="block font-semibold">College Scope <span class="text-red-600">*</span></label>
            <select name="college" id="collegeSelectModal" class="w-full p-2 border rounded" onchange="updateCourseOptions()">
                <option value="">Select College</option>
    ` + Object.entries(colleges).map(([code, name]) =>
        `<option value="${code}">${code} - ${name}</option>`
    ).join('') + `
            </select>
            <p class="text-xs text-gray-500 mt-1">Required: choose a college.</p>
        </div>
        <div id="coursesContainerModal" class="hidden mt-3">
            <label class="block font-semibold">Course Scope <span class="text-red-600">*</span></label>
            <div id="coursesListModal" class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
                <!-- Courses will be populated here -->
            </div>
            <div class="mt-1 flex flex-col gap-1">
                <button type="button" onclick="toggleAllCourses()" class="text-xs text-blue-600 hover:text-blue-800 text-left">Select All</button>
                <p class="text-xs text-gray-500">Required: select at least one course or use Select All.</p>
            </div>
        </div>
    `;
}

// Academic-Faculty Fields
function getAcademicStudentFields() {
    return `
        <div>
            <label class="block font-semibold">College Scope <span class="text-red-600">*</span></label>
            <select name="college" id="collegeSelectModal" class="w-full p-2 border rounded" onchange="updateCourseOptions()">
                <option value="">Select College</option>
    ` + Object.entries(colleges).map(([code, name]) =>
        `<option value="${code}">${code} - ${name}</option>`
    ).join('') + `
            </select>
            <p class="text-xs text-gray-500 mt-1">Required: choose a college.</p>
        </div>
        <div id="coursesContainerModal" class="hidden mt-3">
            <label class="block font-semibold">Course Scope <span class="text-red-600">*</span></label>
            <div id="coursesListModal" class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
                <!-- Courses will be populated here -->
            </div>
            <div class="mt-1 flex flex-col gap-1">
                <button type="button" onclick="toggleAllCourses()" class="text-xs text-blue-600 hover:text-blue-800 text-left">Select All</button>
                <p class="text-xs text-gray-500">Required: select at least one course or use Select All.</p>
            </div>
        </div>
    `;
}

// Non-Academic-Employee Fields
function getNonAcademicEmployeeFields() {
    return `
        <div>
            <label class="block font-semibold">Department Scope <span class="text-red-600">*</span></label>
            <div id="nonAcademicDeptsContainer" class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
    ` + Object.entries(nonAcademicDepartments).map(([code, name]) =>
        `<label class="flex items-center">
            <input type="checkbox" name="departments[]" value="${code}" class="mr-1">
            ${name}
        </label>`
    ).join('') + `
            </div>
            <div class="mt-1 flex flex-col gap-1">
                <button type="button" onclick="toggleAllNonAcademicDepts()" class="text-xs text-blue-600 hover:text-blue-800 text-left">Select All</button>
                <p class="text-xs text-gray-500">Required: select at least one department or use Select All.</p>
            </div>
        </div>
    `;
}

// Others-Default Fields
function getOtherFields() {
    return `
        <div class="bg-purple-50 p-3 rounded">
            <p class="text-sm text-purple-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Others - Default Admin</strong>
            </p>
            <p class="text-sm text-purple-700 mt-1">
                This admin can manage all faculty and non-academic employees regardless of COOP/MIGS status.
            </p>
            <div class="mt-2 text-xs text-purple-600">
                <i class="fas fa-users mr-1"></i>
                <strong>Scope:</strong> All Faculty and Non-Academic Employees
            </div>
            <div class="mt-1 text-xs text-purple-600">
                <i class="fas fa-universal-access mr-1"></i>
                <strong>No Filters:</strong> No membership restrictions
            </div>
        </div>
    `;
}

// Others-COOP Fields
function getCoopFields() {
    return `
        <div class="bg-green-50 p-3 rounded">
            <p class="text-sm text-green-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Others - COOP Admin</strong>
            </p>
            <p class="text-sm text-green-700 mt-1">
                This admin can manage faculty and non-academic employees who are both COOP and MIGS members.
            </p>
            <div class="mt-2 text-xs text-green-600">
                <i class="fas fa-filter mr-1"></i>
                <strong>Filters:</strong> COOP Member + MIGS Status
            </div>
            <div class="mt-1 text-xs text-green-600">
                <i class="fas fa-user-check mr-1"></i>
                <strong>Scope:</strong> Faculty and Non-Academic Employees
            </div>
        </div>
    `;
}

// Unified Others Fields (single category only)
// Ginagamit para sa lahat ng special elections: COOP, Alumni, Retired, etc.
function getUnifiedOthersFields() {
    return `
        <div class="bg-purple-50 p-3 rounded">
            <p class="text-sm text-purple-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Others Admin</strong>
            </p>
            <p class="text-sm text-purple-700 mt-1">
                This admin manages a special election that is <strong>not tied to colleges/departments</strong>,
                but instead to a custom uploaded voter list.
            </p>

            <div class="mt-3 text-xs text-purple-700 space-y-1">
                <p>
                    <i class="fas fa-users mr-1"></i>
                    <strong>Examples:</strong> Cooperative Election, Alumni Election, Retired Staff Election, and other similar organizations.
                </p>
                <p>
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>How it works:</strong> The <em>Admin Title</em> (e.g. "Cooperative Election Admin", "Alumni Election Admin")
                    and the uploaded voter list will define what type of "Others" election this is.
                </p>
            </div>
        </div>
    `;
}

// Non-Academic-Student Fields
function getNonAcademicStudentFields() {
    return `
        <div class="bg-blue-50 p-3 rounded">
            <p class="text-sm text-blue-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Non-Academic - Student Admin</strong>
            </p>
            <p class="text-sm text-blue-700 mt-1">
                This admin manages non-academic student organizations (e.g. music, esports clubs).
            </p>
            <div class="mt-2 text-xs text-blue-600">
                <i class="fas fa-users mr-1"></i>
                <strong>Scope:</strong> All non-academic student organizations
            </div>
        </div>
    `;
}

// Special Scope Fields
function getSpecialScopeFields() {
    return `
        <div class="bg-yellow-50 p-3 rounded">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>CSG Admin</strong>
            </p>
            <p class="text-sm text-yellow-700 mt-1">
                This admin will have CSG Admin privileges - can manage all student organizations.
            </p>
            <div class="mt-2 text-xs text-yellow-600">
                <i class="fas fa-graduation-cap mr-1"></i>
                <strong>Scope:</strong> All Student Organizations
            </div>
            <div class="mt-1 text-xs text-yellow-600">
                <i class="fas fa-crown mr-1"></i>
                <strong>Privileges:</strong> System-wide student management
            </div>
        </div>
    `;
}

// === Dynamic update helpers ===
function updateCourseOptions() {
    const college = document.getElementById('collegeSelectModal');
    const container = document.getElementById('coursesContainerModal');
    const coursesList = document.getElementById('coursesListModal');

    console.log('updateCourseOptions called with:', college ? college.value : 'not found');

    if (!college || !container || !coursesList) {
        console.error('Required elements not found');
        return;
    }

    if (!college.value) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    coursesList.innerHTML = '';

    const courses = coursesByCollege[college.value] || [];
    console.log('Courses for college', college.value, ':', courses);

    if (courses.length === 0) {
        coursesList.innerHTML = '<p class="text-gray-500">No courses available for this college</p>';
        return;
    }

    courses.forEach(course => {
        coursesList.innerHTML += `
            <label class="flex items-center">
                <input type="checkbox" name="courses[]" value="${course.code}" class="mr-1">
                ${course.code}
            </label>
        `;
    });
}

function updateFacultyDepartmentOptions() {
    const college = document.getElementById('facultyCollegeSelectModal');
    const container = document.getElementById('departmentsContainerModal');
    const departmentsList = document.getElementById('departmentsListModal');

    console.log('updateFacultyDepartmentOptions called with:', college ? college.value : 'not found');

    if (!college || !container || !departmentsList) {
        console.error('Required elements not found');
        return;
    }

    if (!college.value) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    departmentsList.innerHTML = '';

    const departments = academicDepartments[college.value] || {};
    console.log('Departments for college', college.value, ':', departments);

    if (Object.keys(departments).length === 0) {
        departmentsList.innerHTML = '<p class="text-gray-500">No departments available for this college</p>';
        return;
    }

    Object.entries(departments).forEach(([code, name]) => {
        departmentsList.innerHTML += `
            <label class="flex items-center">
                <input type="checkbox" name="departments[]" value="${code}" class="mr-1">
                ${code} - ${name}
            </label>
        `;
    });
}

// === Toggle helpers ===
function toggleAllCourses() {
    const checkboxes = document.querySelectorAll('input[name="courses[]"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);

    let selectAllInput = document.getElementById('selectAllCoursesInput');
    if (!allChecked) {
        if (!selectAllInput) {
            selectAllInput = document.createElement('input');
            selectAllInput.type = 'hidden';
            selectAllInput.name = 'select_all_courses';
            selectAllInput.id   = 'selectAllCoursesInput';
            selectAllInput.value= 'true';
            document.getElementById('coursesContainerModal').appendChild(selectAllInput);
        }
    } else {
        if (selectAllInput) selectAllInput.remove();
    }
}

function toggleAllDepartments() {
    const checkboxes = document.querySelectorAll('input[name="departments[]"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);

    let selectAllInput = document.getElementById('selectAllDepartmentsInput');
    if (!allChecked) {
        if (!selectAllInput) {
            selectAllInput = document.createElement('input');
            selectAllInput.type = 'hidden';
            selectAllInput.name = 'select_all_departments';
            selectAllInput.id   = 'selectAllDepartmentsInput';
            selectAllInput.value= 'true';
            document.getElementById('departmentsContainerModal').appendChild(selectAllInput);
        }
    } else {
        if (selectAllInput) selectAllInput.remove();
    }
}

function toggleAllNonAcademicDepts() {
    const checkboxes = document.querySelectorAll('input[name="departments[]"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);

    let selectAllInput = document.getElementById('selectAllNonAcademicDeptsInput');
    if (!allChecked) {
        if (!selectAllInput) {
            selectAllInput = document.createElement('input');
            selectAllInput.type = 'hidden';
            selectAllInput.name = 'select_all_non_academic_depts';
            selectAllInput.id   = 'selectAllNonAcademicDeptsInput';
            selectAllInput.value= 'true';
            document.getElementById('nonAcademicDeptsContainer').appendChild(selectAllInput);
        }
    } else {
        if (selectAllInput) selectAllInput.remove();
    }
}

// === Modal + form helpers ===
function generatePassword(length = 12) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  return Array.from({ length }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
}

function openCreateModal() {
  const modal = document.getElementById('createModal');
  const passwordField = document.getElementById('generatedPasswordModal');

  if (modal && passwordField) {
    passwordField.value = generatePassword();
    modal.classList.remove('hidden');
  } else {
    console.error('Modal or password field not found');
  }
}

function closeCreateModal() {
  const modal = document.getElementById('createModal');
  if (modal) modal.classList.add('hidden');
}

function clearCreateAdminForm() {
  const form = document.getElementById('createAdminFormModal');
  const passwordField = document.getElementById('generatedPasswordModal');
  const errorDiv = document.getElementById('formError');
  const dynamicFields = document.getElementById('dynamicScopeFieldsModal');

  if (form) form.reset();
  if (passwordField) passwordField.value = generatePassword();
  if (errorDiv) errorDiv.classList.add('hidden');
  if (dynamicFields) dynamicFields.innerHTML = '';
}

function copyPassword() {
  const input = document.getElementById("generatedPasswordModal");
  if (!input) return;

  navigator.clipboard.writeText(input.value)
    .then(() => {
      const btn = event.target.closest('button');
      const originalHTML = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check mr-1"></i>Copied!';
      btn.classList.add('text-green-600');

      setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('text-green-600');
      }, 2000);
    })
    .catch(err => console.error("Copy failed: ", err));
}

// === AJAX submit ===
async function submitCreateAdmin(event) {
    console.log('submitCreateAdmin called');
    event.preventDefault();

    try {
        const form = document.getElementById('createAdminFormModal');
        if (!form) throw new Error('Form not found');

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const errorDiv   = document.getElementById('formError');
        const scopeError = document.getElementById('scopeValidationError');
        const submitBtn  = document.getElementById('submitBtnModal');
        const submitText = document.getElementById('submitBtnTextModal');
        const loaderIcon = document.getElementById('loaderIconModal');

        if (!errorDiv || !submitBtn || !submitText || !loaderIcon) {
            throw new Error('Required elements not found');
        }

        // clear old errors
        errorDiv.classList.add('hidden');
        if (scopeError) {
            scopeError.textContent = '';
            scopeError.classList.add('hidden');
        }

        const scopeCategory = document.getElementById('scopeCategoryModal')?.value || '';

        // helper for inline scope error
        const failScope = (msg) => {
            if (!scopeError) return false;
            scopeError.textContent = msg;
            scopeError.classList.remove('hidden');

            // auto-scroll yung mismong scope section sa gitna ng modal
            scopeError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        };

        // === CUSTOM SCOPE VALIDATION ===

        if (scopeCategory === 'Academic-Student') {
            const college = document.getElementById('collegeSelectModal')?.value || '';
            if (!college) {
                if (failScope('Please select a college for this Academic-Student admin.')) return;
            }

            const selectedCourses = document.querySelectorAll('input[name="courses[]"]:checked');
            const selectAllHidden = document.getElementById('selectAllCoursesInput');
            if (!selectedCourses.length && !selectAllHidden) {
                if (failScope('Please select at least one course or use Select All for this Academic-Student admin.')) return;
            }
        }

        if (scopeCategory === 'Academic-Faculty') {
            const college = document.getElementById('facultyCollegeSelectModal')?.value || '';
            if (!college) {
                if (failScope('Please select a college for this Academic-Faculty admin.')) return;
            }

            const selectedDepts   = document.querySelectorAll('input[name="departments[]"]:checked');
            const selectAllHidden = document.getElementById('selectAllDepartmentsInput');
            if (!selectedDepts.length && !selectAllHidden) {
                if (failScope('Please select at least one department or use Select All for this Academic-Faculty admin.')) return;
            }
        }

        if (scopeCategory === 'Non-Academic-Employee') {
            const selectedDepts   = document.querySelectorAll('input[name="departments[]"]:checked');
            const selectAllHidden = document.getElementById('selectAllNonAcademicDeptsInput');
            if (!selectedDepts.length && !selectAllHidden) {
                if (failScope('Please select at least one department or use Select All for this Non-Academic-Employee admin.')) return;
            }
        }

        // ✅ kung umabot dito, pasado na scope validation

        submitBtn.disabled = true;
        submitText.textContent = 'Creating...';
        loaderIcon.classList.remove('hidden');

        const formData = new FormData(form);

        console.log('Form data entries:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ', pair[1]);
        }

        const response = await fetch('create_admin.php', {
            method: 'POST',
            body: formData
        });

        console.log('Response status:', response.status);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseText = await response.text();
        console.log('Response text:', responseText);

        if (!responseText) {
            throw new Error('Empty response from server');
        }

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', responseText);
            throw new Error('Invalid JSON response from server');
        }

        console.log('Response data:', data);

        submitBtn.disabled = false;
        submitText.textContent = 'Create Admin';
        loaderIcon.classList.add('hidden');

        if (data.status === 'error') {
            // server-side validation error -> pwede mo pa ring ipakita sa top
            errorDiv.textContent = data.message;
            errorDiv.classList.remove('hidden');
            // optional: scroll to top of modal
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            closeCreateModal();
            openSuccessModal();
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (error) {
        console.error('Error in submitCreateAdmin:', error);

        const errorDiv   = document.getElementById('formError');
        const submitBtn  = document.getElementById('submitBtnModal');
        const submitText = document.getElementById('submitBtnTextModal');
        const loaderIcon = document.getElementById('loaderIconModal');

        if (errorDiv && submitBtn && submitText && loaderIcon) {
            submitBtn.disabled = false;
            submitText.textContent = 'Create Admin';
            loaderIcon.classList.add('hidden');
            errorDiv.textContent = "Error: " + error.message;
            errorDiv.classList.remove('hidden');
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

function openSuccessModal() {
  const modal = document.getElementById('successModal');
  if (modal) modal.classList.remove('hidden');
}

function closeSuccessModal() {
  const modal = document.getElementById('successModal');
  if (modal) modal.classList.add('hidden');
}

// === Initialize ===
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');

    const scopeCategory = document.getElementById('scopeCategoryModal');
    if (scopeCategory) {
        scopeCategory.addEventListener('change', updateScopeFields);
        console.log('Event listener attached to scopeCategory');
    } else {
        console.error('scopeCategory element not found');
    }

    const form = document.getElementById('createAdminFormModal');
    if (form) {
        form.addEventListener('submit', submitCreateAdmin);
        console.log('Event listener attached to form');
    } else {
        console.error('createAdminForm element not found');
    }

    const passwordField = document.getElementById('generatedPasswordModal');
    if (passwordField) {
        passwordField.value = generatePassword();
    }

    const openModalBtn = document.getElementById('openModalBtn');
    if (openModalBtn) {
        openModalBtn.addEventListener('click', openCreateModal);
    }
});
</script>
