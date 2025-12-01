<?php
// admin_modal_update.php
include_once 'admin_functions.php'; // Include the helper functions (only once)

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!-- Edit Admin Modal -->
<div id="updateModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden"
     role="dialog" aria-modal="true" aria-labelledby="updateModalTitle">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl relative flex flex-col" style="max-height: 90vh;">
    <!-- Fixed Header -->
    <div class="p-6 border-b flex-shrink-0">
      <h2 id="updateModalTitle" class="text-xl font-bold text-[var(--cvsu-green-dark)]">Edit Admin</h2>
      <button onclick="closeUpdateModal()"
              class="close-modal-btn absolute top-4 right-4 text-gray-500 hover:text-red-600 text-3xl font-bold leading-none"
              aria-label="Close modal">
        &times;
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="flex-grow overflow-y-auto p-6 modal-body">
      <div id="updateFormError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm mb-4" role="alert"></div>
      <form id="updateAdminForm" class="space-y-4">
        <input type="hidden" name="user_id" id="update_user_id" />
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>" />
        <!-- Hidden field for scope_category (since display select is disabled) -->
        <input type="hidden" name="scope_category" id="update_scope_category_hidden" />

        <div>
          <label class="block font-semibold" for="update_admin_title">Admin Title</label>
          <input type="text" name="admin_title" id="update_admin_title" required
                 class="w-full p-2 border rounded focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-transparent" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold" for="update_first_name">First Name</label>
            <input type="text" name="first_name" id="update_first_name" required
                   class="w-full p-2 border rounded focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-transparent" />
          </div>
          <div>
            <label class="block font-semibold" for="update_last_name">Last Name</label>
            <input type="text" name="last_name" id="update_last_name" required
                   class="w-full p-2 border rounded focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-transparent" />
          </div>
        </div>

        <div>
          <label class="block font-semibold" for="update_email">Email</label>
          <input type="email" name="email" id="update_email" required
                 class="w-full p-2 border rounded focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-transparent" />
        </div>

        <div>
          <label class="block font-semibold" for="updateScopeCategoryModal">Scope Category</label>
          <div class="relative">
            <select name="scope_category_display" id="updateScopeCategoryModal" required
                    class="w-full p-2 border rounded bg-gray-50 cursor-not-allowed focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-transparent"
                    disabled>
              <option value="">Select Scope Category</option>
              <option value="Academic-Student">Academic - Student</option>
              <option value="Non-Academic-Student">Non-Academic - Student</option>
              <option value="Academic-Faculty">Academic - Faculty</option>
              <option value="Non-Academic-Employee">Non-Academic - Employee</option>
              <option value="Others">Others</option>
              <option value="Special-Scope">Special Scope - CSG Admin</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
          <div class="mt-1 flex items-center text-sm text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M6.938 4h10.124c1.54 0 2.502 1.667 1.732 3L13.732 20a2 2 0 01-3.464 0L5.206 7C4.436 5.667 5.398 4 6.938 4z" />
            </svg>
            <span>Scope category cannot be changed</span>
          </div>
        </div>

        <div id="updateDynamicScopeFieldsModal" class="space-y-4">
          <!-- Dynamic read-only scope fields will be inserted here -->
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t mt-6">
          <button type="button" onclick="resetUpdateForm()"
                  class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded font-semibold transition">
            Clear
          </button>
          <button type="submit" id="updateBtn"
                  class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-4 py-2 rounded font-semibold flex items-center justify-center gap-2 transition">
            <span id="updateBtnTextModal">Save Changes</span>
            <svg id="updateLoaderIconModal" class="w-5 h-5 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"></circle>
              <path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Update Success Modal -->
<div id="updateSuccessModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden"
     role="dialog" aria-modal="true" aria-labelledby="updateSuccessModalTitle">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 text-center relative transform transition-all scale-100">
    <button onclick="closeUpdateSuccessModal()"
            class="close-modal-btn absolute top-4 right-4 text-gray-400 hover:text-green-600 text-4xl font-bold leading-none"
            aria-label="Close success modal">
      &times;
    </button>

    <div class="w-20 h-20 mx-auto mb-4 bg-green-100 text-green-600 rounded-full flex items-center justify-center shadow-md">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>

    <h2 id="updateSuccessModalTitle" class="text-2xl font-bold text-[var(--cvsu-green)] mb-2">Admin Successfully Updated</h2>
    <p class="text-gray-600 text-sm mb-4">Admin information has been updated successfully.</p>
    <p class="text-gray-500 text-sm mb-4">This page will refresh in <span id="countdown">5</span> seconds.</p>

    <button onclick="closeUpdateSuccessModal()"
            class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-6 py-2 rounded-full text-sm font-semibold mt-2 transition">
      Close
    </button>
  </div>
</div>

<style>
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

/* Disabled fields */
input:disabled,
select:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
    border-color: #e5e7eb;
    opacity: 1;
}

/* Read-only containers */
.disabled-field-container {
    position: relative;
    background-color: #f9fafb;
    border: 1px dashed #d1d5db;
    border-radius: 0.375rem;
    padding: 0.75rem 0.75rem 0.5rem 0.75rem;
    margin-top: 0.75rem;
}
.disabled-field-label {
    position: absolute;
    top: -0.75rem;
    left: 0.75rem;
    background-color: #f9fafb;
    padding: 0 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
}
.read-only-display {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5rem;
    margin-top: 0.5rem;
    min-height: 40px;
}
.read-only-label {
    font-weight: 600;
    margin-bottom: 0.25rem;
    display: block;
}
.read-only-value {
    color: #495057;
}
.read-only-note {
    font-size: 0.75rem;
    color: #6c757d;
    font-style: italic;
    margin-top: 0.25rem;
}

/* Focus styles, excluding close buttons */
button:focus:not(.close-modal-btn),
input:focus,
select:focus {
    outline: 1px solid var(--cvsu-green);
    outline-offset: 1px;
    box-shadow: 0 0 0 1px rgba(30, 111, 70, 0.2);
}
input:focus,
select:focus {
    border-color: var(--cvsu-green);
}
.close-modal-btn:focus {
    outline: none;
    box-shadow: none;
}
</style>
<script>
(function() {
    // Bootstrap JS copies of PHP data if not already set
    if (typeof window.collegesData === 'undefined') {
        window.collegesData = <?php echo json_encode(getColleges()); ?>;
    }
    if (typeof window.academicDepartmentsData === 'undefined') {
        window.academicDepartmentsData = <?php echo json_encode(getAcademicDepartments()); ?>;
    }
    if (typeof window.nonAcademicDepartmentsData === 'undefined') {
        window.nonAcademicDepartmentsData = <?php echo json_encode(getNonAcademicDepartments()); ?>;
    }
    if (typeof window.coursesByCollegeData === 'undefined') {
        window.coursesByCollegeData = {};
        <?php
        foreach (array_keys(getColleges()) as $college) {
            $courses = getCoursesByCollege($college);
            $coursesArray = [];
            foreach ($courses as $code => $name) {
                $coursesArray[] = ['code' => $code, 'name' => $name];
            }
            echo "window.coursesByCollegeData['$college'] = " . json_encode($coursesArray) . ";\n";
        }
        ?>
    }

    const updateModal        = document.getElementById('updateModal');
    const updateForm         = document.getElementById('updateAdminForm');
    const updateFormError    = document.getElementById('updateFormError');
    const updateBtn          = document.getElementById('updateBtn');
    const updateBtnText      = document.getElementById('updateBtnTextModal');
    const updateLoaderIcon   = document.getElementById('updateLoaderIconModal');
    const updateSuccessModal = document.getElementById('updateSuccessModal');
    const countdownElement   = document.getElementById('countdown');

    // ==== SCOPE FIELDS (READ-ONLY) ====

    function updateScopeFieldsForEdit() {
        const scopeCategoryDisplay = document.getElementById('updateScopeCategoryModal');
        const scopeCategoryHidden  = document.getElementById('update_scope_category_hidden');
        const container            = document.getElementById('updateDynamicScopeFieldsModal');
        if (!scopeCategoryDisplay || !container) return;

        container.innerHTML = '';

        // Gamitin ang hidden value para sa logic (real value from DB)
        const categoryForLogic =
            (scopeCategoryHidden && scopeCategoryHidden.value) ||
            scopeCategoryDisplay.value;

        switch (categoryForLogic) {
            case 'Academic-Student':
                container.innerHTML = getAcademicStudentFieldsForEdit();
                break;

            case 'Academic-Faculty':
                container.innerHTML = getAcademicFacultyFieldsForEdit();
                break;

            case 'Non-Academic-Employee':
                container.innerHTML = getNonAcademicEmployeeFieldsForEdit();
                break;

            case 'Non-Academic-Student':
                container.innerHTML = getNonAcademicStudentFieldsForEdit();
                break;

            case 'Others':
                container.innerHTML = getOthersFieldsForEdit();
                break;

            case 'Special-Scope':
                container.innerHTML = getSpecialScopeFieldsForEdit();
                break;

            default:
                container.innerHTML = '<p class="text-gray-500">Scope details are not available for this category.</p>';
        }
    }

    function getAcademicStudentFieldsForEdit() {
        return `
          <div>
            <label class="read-only-label">College Scope</label>
            <div id="updateCollegeDisplay" class="read-only-display read-only-value"></div>
            <input type="hidden" name="college" id="updateCollegeHidden" />
          </div>
          <div class="mt-3">
            <label class="read-only-label">Course Scope</label>
            <div id="updateCoursesDisplay" class="read-only-display read-only-value"></div>
            <div id="updateCoursesHiddenContainer"></div>
          </div>
        `;
    }

    function getAcademicFacultyFieldsForEdit() {
        return `
          <div>
            <label class="read-only-label">College Scope</label>
            <div id="updateFacultyCollegeDisplay" class="read-only-display read-only-value"></div>
            <input type="hidden" name="college" id="updateFacultyCollegeHidden" />
          </div>
          <div class="mt-3">
            <label class="read-only-label">Department Scope</label>
            <div id="updateDepartmentsDisplay" class="read-only-display read-only-value"></div>
            <div id="updateDepartmentsHiddenContainer"></div>
          </div>
        `;
    }

    function getNonAcademicEmployeeFieldsForEdit() {
        return `
          <div>
            <label class="read-only-label">Department Scope</label>
            <div id="updateNonAcademicDeptsDisplay" class="read-only-display read-only-value"></div>
            <div id="updateNonAcademicDeptsHiddenContainer"></div>
          </div>
        `;
    }

    function getNonAcademicStudentFieldsForEdit() {
        return `
          <div class="disabled-field-container">
            <div class="disabled-field-label">Admin Scope Information</div>
            <div class="bg-blue-50 p-3 rounded text-sm text-blue-800">
              <strong>Non-Academic - Student Admin</strong><br>
              Scope: All non-academic student organizations
            </div>
          </div>
        `;
    }

    function getOthersFieldsForEdit() {
        return `
          <div class="disabled-field-container">
            <div class="disabled-field-label">Admin Scope Information</div>
            <div class="bg-purple-50 p-3 rounded text-sm text-purple-800">
              <strong>Others Admin</strong><br>
              Scope: Special elections with custom uploaded voters (e.g. COOP, Alumni, Retired).<br>
              Semantics (COOP/Alumni/Retired/etc.) are based on the Admin Title and the uploaded voter list.
            </div>
          </div>
        `;
    }

    function getSpecialScopeFieldsForEdit() {
        return `
          <div class="disabled-field-container">
            <div class="disabled-field-label">Admin Scope Information</div>
            <div class="bg-yellow-50 p-3 rounded text-sm text-yellow-800">
              <strong>CSG Admin</strong><br>
              Scope: All Student Organizations.
            </div>
          </div>
        `;
    }

    // Fill in read-only scope details + hidden fields for submit
    function populateScopeDetailsForEdit(admin) {
        let scopeDetails = {};
        if (admin.scope_details) {
            try {
                scopeDetails = JSON.parse(admin.scope_details) || {};
            } catch (e) {
                scopeDetails = {};
            }
        }

        switch (admin.scope_category) {
            case 'Academic-Student': {
                const collegeDisplay = document.getElementById('updateCollegeDisplay');
                const collegeHidden  = document.getElementById('updateCollegeHidden');
                const coursesDisplay = document.getElementById('updateCoursesDisplay');
                const coursesHiddenWrap = document.getElementById('updateCoursesHiddenContainer');

                const collegeCode = scopeDetails.college || admin.assigned_scope || '';
                const collegeName = window.collegesData[collegeCode] || '';

                if (collegeDisplay) {
                    collegeDisplay.innerHTML = collegeCode
                        ? `${collegeCode}${collegeName ? ' - ' + collegeName : ''}`
                        : '<span class="read-only-note">No college assigned</span>';
                }
                if (collegeHidden) collegeHidden.value = collegeCode;

                if (coursesHiddenWrap) coursesHiddenWrap.innerHTML = '';

                // Determine course codes: from scope_details.courses or assigned_scope_1
                let courseCodes = [];
                if (Array.isArray(scopeDetails.courses) && scopeDetails.courses.length) {
                    courseCodes = scopeDetails.courses;
                } else if (admin.assigned_scope_1) {
                    if (admin.assigned_scope_1.startsWith('Multiple: ')) {
                        courseCodes = admin.assigned_scope_1
                            .substring(9)
                            .split(',')
                            .map(c => c.trim())
                            .filter(Boolean);
                    } else if (admin.assigned_scope_1 !== 'All') {
                        courseCodes = [admin.assigned_scope_1];
                    }
                }

                const collegeCourses = window.coursesByCollegeData[collegeCode] || [];

                if (coursesDisplay) {
                    if (!courseCodes.length && collegeCode) {
                        coursesDisplay.innerHTML = '<span class="read-only-note">All courses in this college</span>';

                        const hiddenAll = document.createElement('input');
                        hiddenAll.type  = 'hidden';
                        hiddenAll.name  = 'select_all_courses';
                        hiddenAll.value = 'true';
                        coursesHiddenWrap && coursesHiddenWrap.appendChild(hiddenAll);
                    } else if (courseCodes.length) {
                        const lines = courseCodes.map(code => {
                            const found = collegeCourses.find(c => c.code === code);
                            return found ? `${code} - ${found.name}` : code;
                        });
                        coursesDisplay.innerHTML = lines.join('<br>');

                        courseCodes.forEach(code => {
                            const input = document.createElement('input');
                            input.type  = 'hidden';
                            input.name  = 'courses[]';
                            input.value = code;
                            coursesHiddenWrap && coursesHiddenWrap.appendChild(input);
                        });
                    } else {
                        coursesDisplay.innerHTML = '<span class="read-only-note">No course scope data</span>';
                    }
                }
                break;
            }

            case 'Academic-Faculty': {
                const collegeDisplay = document.getElementById('updateFacultyCollegeDisplay');
                const collegeHidden  = document.getElementById('updateFacultyCollegeHidden');
                const deptsDisplay   = document.getElementById('updateDepartmentsDisplay');
                const deptsWrap      = document.getElementById('updateDepartmentsHiddenContainer');

                const collegeCode = scopeDetails.college || admin.assigned_scope || '';
                const collegeName = window.collegesData[collegeCode] || '';

                if (collegeDisplay) {
                    collegeDisplay.innerHTML = collegeCode
                        ? `${collegeCode}${collegeName ? ' - ' + collegeName : ''}`
                        : '<span class="read-only-note">No college assigned</span>';
                }
                if (collegeHidden) collegeHidden.value = collegeCode;

                if (deptsWrap) deptsWrap.innerHTML = '';

                let deptCodes = [];
                if (Array.isArray(scopeDetails.departments) && scopeDetails.departments.length) {
                    deptCodes = scopeDetails.departments;
                } else if (admin.assigned_scope_1) {
                    if (admin.assigned_scope_1.startsWith('Multiple: ')) {
                        deptCodes = admin.assigned_scope_1
                            .substring(9)
                            .split(',')
                            .map(c => c.trim())
                            .filter(Boolean);
                    } else if (admin.assigned_scope_1 !== 'All') {
                        deptCodes = [admin.assigned_scope_1];
                    }
                }

                if (deptsDisplay) {
                    if (!deptCodes.length && collegeCode) {
                        deptsDisplay.innerHTML = '<span class="read-only-note">All departments in this college</span>';

                        const input = document.createElement('input');
                        input.type  = 'hidden';
                        input.name  = 'select_all_departments';
                        input.value = 'true';
                        deptsWrap && deptsWrap.appendChild(input);
                    } else if (deptCodes.length) {
                        const lines = deptCodes.map(code => {
                            const nameMap = (window.academicDepartmentsData || {})[collegeCode] || {};
                            const full    = nameMap[code] || '';
                            return full ? `${code} - ${full}` : code;
                        });
                        deptsDisplay.innerHTML = lines.join('<br>');

                        deptCodes.forEach(code => {
                            const input = document.createElement('input');
                            input.type  = 'hidden';
                            input.name  = 'departments[]';
                            input.value = code;
                            deptsWrap && deptsWrap.appendChild(input);
                        });
                    } else {
                        deptsDisplay.innerHTML = '<span class="read-only-note">No department scope data</span>';
                    }
                }
                break;
            }

            case 'Non-Academic-Employee': {
                const display = document.getElementById('updateNonAcademicDeptsDisplay');
                const wrap    = document.getElementById('updateNonAcademicDeptsHiddenContainer');
                if (wrap) wrap.innerHTML = '';

                let deptCodes = [];
                if (Array.isArray(scopeDetails.departments) && scopeDetails.departments.length) {
                    deptCodes = scopeDetails.departments;
                } else if (admin.assigned_scope_1) {
                    if (admin.assigned_scope_1.startsWith('Multiple: ')) {
                        deptCodes = admin.assigned_scope_1
                            .substring(9)
                            .split(',')
                            .map(c => c.trim())
                            .filter(Boolean);
                    } else if (admin.assigned_scope_1 !== 'All') {
                        deptCodes = [admin.assigned_scope_1];
                    }
                } else if (admin.assigned_scope && admin.assigned_scope !== 'Non-Academic') {
                    deptCodes = [admin.assigned_scope];
                }

                const map = window.nonAcademicDepartmentsData || {};
                if (display) {
                    if (!deptCodes.length) {
                        display.innerHTML = '<span class="read-only-note">All non-academic departments</span>';

                        const input = document.createElement('input');
                        input.type  = 'hidden';
                        input.name  = 'select_all_non_academic_depts';
                        input.value = 'true';
                        wrap && wrap.appendChild(input);
                    } else {
                        const lines = deptCodes.map(code => map[code] ? `${code} - ${map[code]}` : code);
                        display.innerHTML = lines.join('<br>');

                        deptCodes.forEach(code => {
                            const input = document.createElement('input');
                            input.type  = 'hidden';
                            input.name  = 'departments[]';
                            input.value = code;
                            wrap && wrap.appendChild(input);
                        });
                    }
                }
                break;
            }

            // Non-Academic-Student, Others-Default, Others-COOP, Special-Scope:
            // purely descriptive; no hidden extra fields needed for now.
        }
    }

    // ==== OPEN / CLOSE / RESET ====

    function triggerEditAdmin(userId) {
        const overlay = document.getElementById('loadingOverlay');
        overlay && overlay.classList.remove('hidden');

        fetch('get_admin.php?user_id=' + encodeURIComponent(userId))
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                overlay && overlay.classList.add('hidden');
                if (data.status === 'success') {
                    openUpdateModal(data.data);
                } else {
                    alert(data.message || 'Admin not found.');
                }
            })
            .catch(err => {
                overlay && overlay.classList.add('hidden');
                alert('Fetch failed: ' + err.message);
            });
    }

    function openUpdateModal(admin) {
        document.getElementById('update_user_id').value      = admin.user_id;
        document.getElementById('update_admin_title').value  = admin.admin_title || '';
        document.getElementById('update_first_name').value   = admin.first_name || '';
        document.getElementById('update_last_name').value    = admin.last_name || '';
        document.getElementById('update_email').value        = admin.email || '';

        const scopeSel    = document.getElementById('updateScopeCategoryModal');
        const scopeHidden = document.getElementById('update_scope_category_hidden');

        if (scopeHidden) {
            // Real value galing DB (Others-Default / Others-COOP / etc.)
            scopeHidden.value = admin.scope_category || '';
        }

        if (scopeSel) {
            // Display value sa dropdown
            if (admin.scope_category === 'Others-Default' || admin.scope_category === 'Others-COOP') {
                scopeSel.value = 'Others';   // UI: iisang “Others”
            } else {
                scopeSel.value = admin.scope_category || '';
            }
        }

        updateScopeFieldsForEdit();
        setTimeout(() => populateScopeDetailsForEdit(admin), 100);

        updateModal.classList.remove('hidden');

        setTimeout(() => {
            document.getElementById('update_first_name').focus();
            trapFocus(updateModal);
        }, 120);
    }

    function closeUpdateModal() {
        updateModal.classList.add('hidden');
        resetUpdateForm();
    }

    function resetUpdateForm() {
        updateForm && updateForm.reset();
        updateFormError && updateFormError.classList.add('hidden');
        const scopeContainer = document.getElementById('updateDynamicScopeFieldsModal');
        if (scopeContainer) scopeContainer.innerHTML = '';
        updateLoaderIcon && updateLoaderIcon.classList.add('hidden');
    }

    // ==== SUBMIT UPDATE ====

    function submitUpdateAdmin(e) {
        e.preventDefault();

        if (!updateForm) return;
        if (!updateForm.checkValidity()) {
            updateForm.reportValidity();
            return;
        }

        updateFormError.classList.add('hidden');
        updateBtn.disabled = true;
        updateBtnText.textContent = 'Updating...';
        updateLoaderIcon.classList.remove('hidden');

        const formData = new FormData(updateForm);

        fetch('update_admin.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            updateBtn.disabled = false;
            updateBtnText.textContent = 'Save Changes';
            updateLoaderIcon.classList.add('hidden');

            if (data.status === 'error') {
                updateFormError.textContent = data.message || 'Update failed.';
                updateFormError.classList.remove('hidden');
            } else {
                closeUpdateModal();
                openUpdateSuccessModal();
            }
        })
        .catch(err => {
            updateBtn.disabled = false;
            updateBtnText.textContent = 'Save Changes';
            updateLoaderIcon.classList.add('hidden');
            updateFormError.textContent = 'Something went wrong. Please try again.';
            updateFormError.classList.remove('hidden');
        });
    }

    function openUpdateSuccessModal() {
        updateSuccessModal.classList.remove('hidden');
        startCountdown(5);
        trapFocus(updateSuccessModal);
    }

    function closeUpdateSuccessModal() {
        updateSuccessModal.classList.add('hidden');
        window.location.reload();
    }

    function startCountdown(seconds) {
        if (!countdownElement) return;
        countdownElement.textContent = seconds;
        const interval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(interval);
                closeUpdateSuccessModal();
            }
        }, 1000);
    }

    // ==== Focus Trap ====

    function trapFocus(element) {
        const focusableSelectors = [
            'button:not(.close-modal-btn)',
            '[href]',
            'input',
            'select',
            'textarea',
            '[tabindex]:not([tabindex="-1"])'
        ];
        const focusableElements = Array.from(element.querySelectorAll(focusableSelectors.join(',')))
            .filter(el => !el.disabled && el.offsetParent !== null);

        if (!focusableElements.length) return;

        const firstEl = focusableElements[0];
        const lastEl  = focusableElements[focusableElements.length - 1];

        firstEl.focus();

        function handleKey(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstEl) {
                        lastEl.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastEl) {
                        firstEl.focus();
                        e.preventDefault();
                    }
                }
            } else if (e.key === 'Escape') {
                if (element === updateModal) {
                    closeUpdateModal();
                } else if (element === updateSuccessModal) {
                    closeUpdateSuccessModal();
                }
            }
        }

        element.addEventListener('keydown', handleKey);

        // Clean up on close
        const observer = new MutationObserver(function() {
            if (element.classList.contains('hidden')) {
                element.removeEventListener('keydown', handleKey);
                observer.disconnect();
            }
        });
        observer.observe(element, { attributes: true, attributeFilter: ['class'] });
    }

    // ==== Init listeners ====

    document.addEventListener('DOMContentLoaded', () => {
        const scopeSel = document.getElementById('updateScopeCategoryModal');
        if (scopeSel) scopeSel.addEventListener('change', updateScopeFieldsForEdit);

        if (updateForm) updateForm.addEventListener('submit', submitUpdateAdmin);

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                if (!updateModal.classList.contains('hidden')) {
                    closeUpdateModal();
                } else if (!updateSuccessModal.classList.contains('hidden')) {
                    closeUpdateSuccessModal();
                }
            }
        });
    });

    // Expose globally
    window.triggerEditAdmin        = triggerEditAdmin;
    window.openUpdateModal         = openUpdateModal;
    window.closeUpdateModal        = closeUpdateModal;
    window.resetUpdateForm         = resetUpdateForm;
    window.submitUpdateAdmin       = submitUpdateAdmin;
    window.openUpdateSuccessModal  = openUpdateSuccessModal;
    window.closeUpdateSuccessModal = closeUpdateSuccessModal;
    window.updateScopeFieldsForEdit= updateScopeFieldsForEdit;
    window.populateScopeDetailsForEdit = populateScopeDetailsForEdit;
})();
</script>
