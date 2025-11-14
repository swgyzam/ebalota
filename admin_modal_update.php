<?php
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
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
        <!-- Added hidden field for scope_category -->
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
              <option value="Others-Default">Others - Default</option>
              <option value="Others-COOP">Others - COOP</option>
              <option value="Special-Scope">Special Scope - CSG Admin</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
          <div class="mt-1 flex items-center text-sm text-gray-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Scope category cannot be changed</span>
          </div>
        </div>

        <div id="updateDynamicScopeFieldsModal" class="space-y-4">
          <!-- Dynamic fields will be inserted here -->
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

<!-- Success Modal -->
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

/* Disabled field styling */
input:disabled, select:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
    border-color: #e5e7eb;
    opacity: 1;
}

/* Disabled field container styling */
.disabled-field-container {
    position: relative;
    background-color: #f9fafb;
    border: 1px dashed #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem;
    margin-top: 0.5rem;
}

.disabled-field-label {
    position: absolute;
    top: -0.75rem;
    left: 0.5rem;
    background-color: #f9fafb;
    padding: 0 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
    display: flex;
    align-items: center;
}

/* Read-only display styling */
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

/* Focus styles for accessibility - UPDATED to exclude close modal buttons */
button:focus:not(.close-modal-btn), input:focus, select:focus {
    outline: 1px solid var(--cvsu-green);
    outline-offset: 1px;
    box-shadow: 0 0 0 1px rgba(30, 111, 70, 0.2);
}

input:focus, select:focus {
    border-color: var(--cvsu-green);
}

/* Close modal button styling - UPDATED to remove focus indicator */
.close-modal-btn:focus {
    outline: none;
    box-shadow: none;
}
</style>

<script>
(function() {
    // Get data from PHP and convert to JavaScript objects
    // Only define if not already defined in global scope
    if (typeof window.collegesData === 'undefined') {
        window.collegesData = <?php echo json_encode(getColleges()); ?>;
    }
    if (typeof window.academicDepartmentsData === 'undefined') {
        window.academicDepartmentsData = <?php echo json_encode(getAcademicDepartments()); ?>;
    }
    if (typeof window.nonAcademicDepartmentsData === 'undefined') {
        window.nonAcademicDepartmentsData = <?php echo json_encode(getNonAcademicDepartments()); ?>;
    }

    // Process courses data to match expected format
    if (typeof window.coursesByCollegeData === 'undefined') {
        window.coursesByCollegeData = {};
        <?php 
        // Get all colleges
        $allColleges = getColleges();
        foreach (array_keys($allColleges) as $college) {
            $courses = getCoursesByCollege($college);
            $coursesArray = [];
            foreach ($courses as $code => $name) {
                $coursesArray[] = ['code' => $code, 'name' => $name];
            }
            echo "window.coursesByCollegeData['$college'] = " . json_encode($coursesArray) . ";\n";
        }
        ?>
    }

    // Cache DOM elements
    const updateModal = document.getElementById('updateModal');
    const updateForm = document.getElementById('updateAdminForm');
    const updateFormError = document.getElementById('updateFormError');
    const updateBtn = document.getElementById('updateBtn');
    const updateBtnText = document.getElementById('updateBtnTextModal');
    const updateLoaderIcon = document.getElementById('updateLoaderIconModal');
    const updateSuccessModal = document.getElementById('updateSuccessModal');
    const countdownElement = document.getElementById('countdown');

    // Main function to update scope fields for edit modal
    function updateScopeFieldsForEdit() {
        const scopeCategory = document.getElementById('updateScopeCategoryModal');
        const container = document.getElementById('updateDynamicScopeFieldsModal');
        
        if (!scopeCategory || !container) {
            return;
        }
        
        container.innerHTML = '';
        
        switch(scopeCategory.value) {
            case 'Academic-Student':
                container.innerHTML = getAcademicStudentFieldsForEdit();
                break;
            case 'Non-Academic-Student':
                container.innerHTML = getNonAcademicStudentFieldsForEdit();
                break;
            case 'Academic-Faculty':
                container.innerHTML = getAcademicFacultyFieldsForEdit();
                break;
            case 'Non-Academic-Employee':
                container.innerHTML = getNonAcademicEmployeeFieldsForEdit();
                break;
            case 'Others-Default':
                container.innerHTML = getOtherFieldsForEdit();
                break;
            case 'Others-COOP':
                container.innerHTML = getCoopFieldsForEdit();
                break;
            case 'Special-Scope':
                container.innerHTML = getSpecialScopeFieldsForEdit();
                break;
            default:
                container.innerHTML = '<p class="text-gray-500">Select a scope category to see options</p>';
        }
    }

    // Academic-Student Fields for Edit - Read-only with hidden inputs
    function getAcademicStudentFieldsForEdit() {
        return `
            <div>
                <label class="read-only-label">College Scope (Read-only)</label>
                <div id="updateCollegeDisplay" class="read-only-display read-only-value">
                    <!-- College will be populated here -->
                </div>
                <input type="hidden" name="college" id="updateCollegeHidden" />
            </div>
            <div class="mt-3">
                <label class="read-only-label">Course Scope (Read-only)</label>
                <div id="updateCoursesDisplay" class="read-only-display read-only-value">
                    <!-- Courses will be populated here -->
                </div>
                <div id="updateCoursesHiddenContainer"></div>
            </div>
        `;
    }

    // Academic-Faculty Fields for Edit - Read-only with hidden inputs
    function getAcademicFacultyFieldsForEdit() {
        return `
            <div>
                <label class="read-only-label">College Scope (Read-only)</label>
                <div id="updateFacultyCollegeDisplay" class="read-only-display read-only-value">
                    <!-- College will be populated here -->
                </div>
                <input type="hidden" name="college" id="updateFacultyCollegeHidden" />
            </div>
            <div class="mt-3">
                <label class="read-only-label">Department Scope (Read-only)</label>
                <div id="updateDepartmentsDisplay" class="read-only-display read-only-value">
                    <!-- Departments will be populated here -->
                </div>
                <div id="updateDepartmentsHiddenContainer"></div>
            </div>
        `;
    }

    // Non-Academic-Employee Fields for Edit - Read-only with hidden inputs
    function getNonAcademicEmployeeFieldsForEdit() {
        return `
            <div>
                <label class="read-only-label">Department Scope (Read-only)</label>
                <div id="updateNonAcademicDeptsDisplay" class="read-only-display read-only-value">
                    <!-- Departments will be populated here -->
                </div>
                <div id="updateNonAcademicDeptsHiddenContainer"></div>
            </div>
        `;
    }

    // Others-Default Fields for Edit
    function getOtherFieldsForEdit() {
        return `
            <div class="disabled-field-container">
                <div class="disabled-field-label">
                    Admin Scope Information (Read-only)
                </div>
                <div class="bg-purple-50 p-3 rounded">
                    <p class="text-sm text-purple-800">
                        <strong>Others - Default Admin</strong>
                    </p>
                    <p class="text-sm text-purple-700 mt-1">
                        This admin can manage all faculty and non-academic employees regardless of COOP/MIGS status.
                    </p>
                    <div class="mt-2 text-xs text-purple-600">
                        <strong>Scope:</strong> All Faculty and Non-Academic Employees
                    </div>
                    <div class="mt-1 text-xs text-purple-600">
                        <strong>No Filters:</strong> No membership restrictions
                    </div>
                </div>
            </div>
        `;
    }

    // Others-COOP Fields for Edit
    function getCoopFieldsForEdit() {
        return `
            <div class="disabled-field-container">
                <div class="disabled-field-label">
                    Admin Scope Information (Read-only)
                </div>
                <div class="bg-green-50 p-3 rounded">
                    <p class="text-sm text-green-800">
                        <strong>Others - COOP Admin</strong>
                    </p>
                    <p class="text-sm text-green-700 mt-1">
                        This admin can manage faculty and non-academic employees who are both COOP and MIGS members.
                    </p>
                    <div class="mt-2 text-xs text-green-600">
                        <strong>Filters:</strong> COOP Member + MIGS Status
                    </div>
                    <div class="mt-1 text-xs text-green-600">
                        <strong>Scope:</strong> Faculty and Non-Academic Employees
                    </div>
                </div>
            </div>
        `;
    }

    // Non-Academic-Student Fields for Edit
    function getNonAcademicStudentFieldsForEdit() {
        return `
            <div class="disabled-field-container">
                <div class="disabled-field-label">
                    Admin Scope Information (Read-only)
                </div>
                <div class="bg-blue-50 p-3 rounded">
                    <p class="text-sm text-blue-800">
                        <strong>Non-Academic - Student Admin</strong>
                    </p>
                    <p class="text-sm text-blue-700 mt-1">
                        This admin will have the same scope as CSG Admin - can manage all non-academic student organizations.
                    </p>
                    <div class="mt-2 text-xs text-blue-600">
                        <strong>Scope:</strong> All non-academic student organizations
                    </div>
                </div>
            </div>
        `;
    }

    // Special Scope Fields for Edit
    function getSpecialScopeFieldsForEdit() {
        return `
            <div class="disabled-field-container">
                <div class="disabled-field-label">
                    Admin Scope Information (Read-only)
                </div>
                <div class="bg-yellow-50 p-3 rounded">
                    <p class="text-sm text-yellow-800">
                        <strong>CSG Admin</strong>
                    </p>
                    <p class="text-sm text-yellow-700 mt-1">
                        This admin will have CSG Admin privileges - can manage all student organizations.
                    </p>
                    <div class="mt-2 text-xs text-yellow-600">
                        <strong>Scope:</strong> All Student Organizations
                    </div>
                    <div class="mt-1 text-xs text-yellow-600">
                        <strong>Privileges:</strong> System-wide student management
                    </div>
                </div>
            </div>
        `;
    }

    // Updated functions for Edit modal
    function triggerEditAdmin(userId) {
        // Show loading overlay
        if (document.getElementById('loadingOverlay')) {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }
        
        fetch('get_admin.php?user_id=' + userId)
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok');
                }
                return res.json();
            })
            .then(data => {
                // Hide loading overlay
                if (document.getElementById('loadingOverlay')) {
                    document.getElementById('loadingOverlay').classList.add('hidden');
                }
                
                if (data.status === 'success') {
                    openUpdateModal(data.data);
                } else {
                    alert("Error: " + (data.message || "Admin not found."));
                }
            })
            .catch(error => {
                // Hide loading overlay
                if (document.getElementById('loadingOverlay')) {
                    document.getElementById('loadingOverlay').classList.add('hidden');
                }
                alert("Fetch failed: " + error.message);
            });
    }

    function openUpdateModal(admin) {
        // Set basic admin information
        document.getElementById('update_user_id').value = admin.user_id;
        document.getElementById('update_admin_title').value = admin.admin_title || '';
        document.getElementById('update_first_name').value = admin.first_name;
        document.getElementById('update_last_name').value = admin.last_name;
        document.getElementById('update_email').value = admin.email;
        
        // Set scope category
        const scopeCategory = document.getElementById('updateScopeCategoryModal');
        const scopeCategoryHidden = document.getElementById('update_scope_category_hidden');
        if (scopeCategory && admin.scope_category) {
            scopeCategory.value = admin.scope_category;
            // Set the hidden field value
            scopeCategoryHidden.value = admin.scope_category;
            // Trigger change to populate dynamic fields
            updateScopeFieldsForEdit();
            
            // After a short delay, set the specific scope values
            setTimeout(() => {
                populateScopeDetailsForEdit(admin);
            }, 300);
        }
        
        updateModal.classList.remove('hidden');
        // Focus management
        setTimeout(() => {
            document.getElementById('update_first_name').focus();
            // Trap focus inside modal
            trapFocus(updateModal);
        }, 100);
    }

    // UPDATED populateScopeDetailsForEdit function with hidden inputs
    function populateScopeDetailsForEdit(admin) {
        console.log('Admin data:', admin);
        console.log('Assigned scope:', admin.assigned_scope);
        console.log('Assigned scope 1:', admin.assigned_scope_1);
        console.log('coursesByCollegeData object:', window.coursesByCollegeData);
        
        // Try to parse scope_details if it exists
        let scopeDetails = {};
        if (admin.scope_details) {
            try {
                scopeDetails = JSON.parse(admin.scope_details);
                console.log('Parsed scope details:', scopeDetails);
            } catch (e) {
                console.error('Error parsing scope_details:', e);
            }
        }
        
        switch(admin.scope_category) {
            case 'Academic-Student':
                // Display college
                const collegeDisplay = document.getElementById('updateCollegeDisplay');
                const collegeHidden = document.getElementById('updateCollegeHidden');
                if (collegeDisplay && collegeHidden) {
                    let collegeText = '';
                    // Use scopeDetails.college if available, otherwise use assigned_scope
                    const collegeCode = scopeDetails.college || admin.assigned_scope;
                    console.log('College code:', collegeCode);
                    
                    if (collegeCode) {
                        const collegeName = window.collegesData[collegeCode] || '';
                        collegeText = collegeCode + (collegeName ? ` - ${collegeName}` : '');
                        // Set hidden input value
                        collegeHidden.value = collegeCode;
                    } else {
                        collegeText = '<span class="read-only-note">No college assigned</span>';
                        collegeHidden.value = '';
                    }
                    collegeDisplay.innerHTML = collegeText;
                } else {
                    console.error('College display or hidden input not found');
                }
                
                // Display courses with full names
                const coursesDisplay = document.getElementById('updateCoursesDisplay');
                const coursesContainer = document.getElementById('updateCoursesHiddenContainer');
                if (coursesDisplay && coursesContainer) {
                    let coursesHtml = '';
                    const collegeCode = scopeDetails.college || admin.assigned_scope;
                    console.log('Looking up courses for college:', collegeCode);
                    
                    // Clear previous hidden inputs
                    coursesContainer.innerHTML = '';
                    
                    // Check if we have assigned_scope_1 in "Multiple: " format
                    if (admin.assigned_scope_1 && admin.assigned_scope_1.startsWith('Multiple: ')) {
                        const coursesStr = admin.assigned_scope_1.substring(9); // Remove "Multiple: "
                        const courseCodes = coursesStr.split(',').map(course => course.trim());
                        console.log('Course codes from assigned_scope_1:', courseCodes);
                        
                        // Get college courses
                        const collegeCourses = window.coursesByCollegeData[collegeCode] || [];
                        console.log('College courses array:', collegeCourses);
                        
                        if (collegeCourses.length === 0) {
                            console.log('No courses found for college:', collegeCode);
                            // Fallback: just display the course codes
                            coursesHtml = courseCodes.join('<br>');
                        } else {
                            const coursesWithNames = courseCodes.map(courseCode => {
                                const course = collegeCourses.find(c => c.code === courseCode);
                                console.log('Looking for course:', courseCode, 'Found:', course);
                                return course ? `${courseCode} - ${course.name}` : courseCode;
                            });
                            
                            coursesHtml = coursesWithNames.join('<br>');
                        }
                        
                        // Create hidden inputs for each course
                        courseCodes.forEach(courseCode => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'courses[]';
                            input.value = courseCode;
                            coursesContainer.appendChild(input);
                        });
                    } 
                    // Check if we have courses in scope_details
                    else if (scopeDetails.courses && Array.isArray(scopeDetails.courses) && scopeDetails.courses.length > 0) {
                        const collegeCourses = window.coursesByCollegeData[collegeCode] || [];
                        console.log('Course codes from scope_details:', scopeDetails.courses);
                        
                        if (collegeCourses.length === 0) {
                            console.log('No courses found for college:', collegeCode);
                            // Fallback: just display the course codes
                            coursesHtml = scopeDetails.courses.join('<br>');
                        } else {
                            const coursesWithNames = scopeDetails.courses.map(courseCode => {
                                const course = collegeCourses.find(c => c.code === courseCode);
                                console.log('Looking for course:', courseCode, 'Found:', course);
                                return course ? `${courseCode} - ${course.name}` : courseCode;
                            });
                            
                            coursesHtml = coursesWithNames.join('<br>');
                        }
                        
                        // Create hidden inputs for each course
                        scopeDetails.courses.forEach(courseCode => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'courses[]';
                            input.value = courseCode;
                            coursesContainer.appendChild(input);
                        });
                    } 
                    // Check if we have a single course in assigned_scope_1
                    else if (admin.assigned_scope_1) {
                        const collegeCourses = window.coursesByCollegeData[collegeCode] || [];
                        console.log('Single course code from assigned_scope_1:', admin.assigned_scope_1);
                        
                        if (collegeCourses.length === 0) {
                            console.log('No courses found for college:', collegeCode);
                            // Fallback: just display the course code
                            coursesHtml = admin.assigned_scope_1;
                        } else {
                            const course = collegeCourses.find(c => c.code === admin.assigned_scope_1);
                            console.log('Looking for course:', admin.assigned_scope_1, 'Found:', course);
                            
                            coursesHtml = course ? `${admin.assigned_scope_1} - ${course.name}` : admin.assigned_scope_1;
                        }
                        
                        // Create hidden input for the single course
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'courses[]';
                        input.value = admin.assigned_scope_1;
                        coursesContainer.appendChild(input);
                    } 
                    // Default: all courses
                    else {
                        coursesHtml = '<span class="read-only-note">All courses in selected college</span>';
                        
                        // Create hidden input for "All" courses
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'select_all_courses';
                        input.value = 'true';
                        coursesContainer.appendChild(input);
                    }
                    
                    console.log('Final courses HTML:', coursesHtml);
                    coursesDisplay.innerHTML = coursesHtml;
                } else {
                    console.error('Courses display or container not found');
                }
                break;
                
            case 'Academic-Faculty':
                // Display college
                const facultyCollegeDisplay = document.getElementById('updateFacultyCollegeDisplay');
                const facultyCollegeHidden = document.getElementById('updateFacultyCollegeHidden');
                if (facultyCollegeDisplay && facultyCollegeHidden) {
                    let collegeText = '';
                    // Use scopeDetails.college if available, otherwise use assigned_scope
                    const collegeCode = scopeDetails.college || admin.assigned_scope;
                    if (collegeCode) {
                        const collegeName = window.collegesData[collegeCode] || '';
                        collegeText = collegeCode + (collegeName ? ` - ${collegeName}` : '');
                        // Set hidden input value
                        facultyCollegeHidden.value = collegeCode;
                    } else {
                        collegeText = '<span class="read-only-note">No college assigned</span>';
                        facultyCollegeHidden.value = '';
                    }
                    facultyCollegeDisplay.innerHTML = collegeText;
                } else {
                    console.error('Faculty college display or hidden input not found');
                }
                
                // Display departments
                const departmentsDisplay = document.getElementById('updateDepartmentsDisplay');
                const departmentsContainer = document.getElementById('updateDepartmentsHiddenContainer');
                if (departmentsDisplay && departmentsContainer) {
                    // Clear previous hidden inputs
                    departmentsContainer.innerHTML = '';
                    
                    if (admin.assigned_scope_1 && admin.assigned_scope_1.startsWith('Multiple: ')) {
                        // Use assigned_scope_1 if it's in "Multiple: " format
                        const deptsStr = admin.assigned_scope_1.substring(9); // Remove "Multiple: "
                        const deptCodes = deptsStr.split(',').map(dept => dept.trim());
                        departmentsDisplay.innerHTML = deptCodes.join('<br>');
                        
                        // Create hidden inputs for each department
                        deptCodes.forEach(deptCode => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'departments[]';
                            input.value = deptCode;
                            departmentsContainer.appendChild(input);
                        });
                    } else if (scopeDetails.departments && Array.isArray(scopeDetails.departments) && scopeDetails.departments.length > 0) {
                        // Use departments from scope_details
                        departmentsDisplay.innerHTML = scopeDetails.departments.join('<br>');
                        
                        // Create hidden inputs for each department
                        scopeDetails.departments.forEach(deptCode => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'departments[]';
                            input.value = deptCode;
                            departmentsContainer.appendChild(input);
                        });
                    } else if (admin.assigned_scope_1) {
                        // Use assigned_scope_1 as a single department
                        departmentsDisplay.innerHTML = admin.assigned_scope_1;
                        
                        // Create hidden input for the single department
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'departments[]';
                        input.value = admin.assigned_scope_1;
                        departmentsContainer.appendChild(input);
                    } else {
                        departmentsDisplay.innerHTML = '<span class="read-only-note">All departments in selected college</span>';
                        
                        // Create hidden input for "All" departments
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'select_all_departments';
                        input.value = 'true';
                        departmentsContainer.appendChild(input);
                    }
                } else {
                    console.error('Departments display or container not found');
                }
                break;
                
            case 'Non-Academic-Employee':
                // Display departments with full names
                const nonAcademicDeptsDisplay = document.getElementById('updateNonAcademicDeptsDisplay');
                const nonAcademicDeptsContainer = document.getElementById('updateNonAcademicDeptsHiddenContainer');
                if (nonAcademicDeptsDisplay && nonAcademicDeptsContainer) {
                    // Clear previous hidden inputs
                    nonAcademicDeptsContainer.innerHTML = '';
                    
                    if (admin.assigned_scope_1 && admin.assigned_scope_1.startsWith('Multiple: ')) {
                        // Use assigned_scope_1 if it's in "Multiple: " format
                        const deptsStr = admin.assigned_scope_1.substring(9); // Remove "Multiple: "
                        const deptCodes = deptsStr.split(',').map(dept => dept.trim());
                        
                        // Get department names from nonAcademicDepartmentsData
                        const departmentNames = deptCodes.map(deptCode => 
                            window.nonAcademicDepartmentsData[deptCode] || deptCode
                        );
                        
                        nonAcademicDeptsDisplay.innerHTML = departmentNames.join('<br>');
                        
                        // Create hidden inputs for each department
                        deptCodes.forEach(deptCode => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'departments[]';
                            input.value = deptCode;
                            nonAcademicDeptsContainer.appendChild(input);
                        });
                    } else if (scopeDetails.departments && Array.isArray(scopeDetails.departments) && scopeDetails.departments.length > 0) {
                        // Use departments from scope_details
                        const departmentNames = scopeDetails.departments.map(deptCode => 
                            window.nonAcademicDepartmentsData[deptCode] || deptCode
                        );
                        nonAcademicDeptsDisplay.innerHTML = departmentNames.join('<br>');
                        
                        // Create hidden inputs for each department
                        scopeDetails.departments.forEach(deptCode => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'departments[]';
                            input.value = deptCode;
                            nonAcademicDeptsContainer.appendChild(input);
                        });
                    } else if (admin.assigned_scope_1) {
                        // Use assigned_scope_1 as a single department
                        const deptName = window.nonAcademicDepartmentsData[admin.assigned_scope_1] || admin.assigned_scope_1;
                        nonAcademicDeptsDisplay.innerHTML = deptName;
                        
                        // Create hidden input for the single department
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'departments[]';
                        input.value = admin.assigned_scope_1;
                        nonAcademicDeptsContainer.appendChild(input);
                    } else {
                        nonAcademicDeptsDisplay.innerHTML = '<span class="read-only-note">All non-academic departments</span>';
                        
                        // Create hidden input for "All" departments
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'select_all_non_academic_depts';
                        input.value = 'true';
                        nonAcademicDeptsContainer.appendChild(input);
                    }
                } else {
                    console.error('Non-academic departments display or container not found');
                }
                break;
        }
    }

    function closeUpdateModal() {
        updateModal.classList.add('hidden');
        resetUpdateForm();
        // Return focus to the element that opened the modal
        if (document.activeElement === document.body) {
            document.querySelector('[data-edit-btn]')?.focus();
        }
    }

    function resetUpdateForm() {
        updateForm.reset();
        updateFormError.classList.add('hidden');
        document.getElementById('updateDynamicScopeFieldsModal').innerHTML = '';
        updateLoaderIcon.classList.add('hidden');
    }

    function submitUpdateAdmin(event) {
        event.preventDefault();
        
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
                updateFormError.textContent = data.message;
                updateFormError.classList.remove('hidden');
            } else {
                closeUpdateModal();
                
                // Show special message if email was changed
                if (data.email_changed) {
                    showEmailChangedNotification();
                } else {
                    openUpdateSuccessModal();
                }
            }
        })
        .catch(error => {
            updateBtn.disabled = false;
            updateBtnText.textContent = 'Save Changes';
            updateLoaderIcon.classList.add('hidden');
            updateFormError.textContent = "Something went wrong. Please try again.";
            updateFormError.classList.remove('hidden');
        });
    }

    function openUpdateSuccessModal() {
        updateSuccessModal.classList.remove('hidden');
        startCountdown(5);
        // Focus management
        setTimeout(() => {
            trapFocus(updateSuccessModal);
        }, 100);
    }

    function closeUpdateSuccessModal() {
        updateSuccessModal.classList.add('hidden');
        window.location.reload();
    }

    function startCountdown(seconds) {
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

    // Function to show email change notification
    function showEmailChangedNotification() {
        const notificationModal = document.createElement('div');
        notificationModal.className = 'fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center';
        notificationModal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 text-center relative transform transition-all scale-100">
                <button onclick="this.closest('.fixed').remove()" 
                        class="close-modal-btn absolute top-4 right-4 text-gray-400 hover:text-green-600 text-4xl font-bold leading-none"
                        aria-label="Close success modal">
                    &times;
                </button>

                <div class="w-20 h-20 mx-auto mb-4 bg-green-100 text-green-600 rounded-full flex items-center justify-center shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>

                <h2 class="text-2xl font-bold text-green-600 mb-2">Email Updated Successfully</h2>
                <p class="text-gray-600 text-sm mb-4">Admin information has been updated and a notification has been sent to the new email address.</p>
                <p class="text-gray-500 text-sm mb-4">This page will refresh in <span id="emailCountdown">5</span> seconds.</p>

                <button onclick="this.closest('.fixed').remove(); window.location.reload();" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-full text-sm font-semibold mt-2 transition">
                    Close
                </button>
            </div>
        `;
        
        document.body.appendChild(notificationModal);
        
        // Start countdown
        let seconds = 5;
        const countdownElement = notificationModal.querySelector('#emailCountdown');
        const interval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(interval);
                window.location.reload();
            }
        }, 1000);
    }

    // Focus trap function for accessibility
    function trapFocus(element) {
        const focusableElements = element.querySelectorAll(
            'button:not(.close-modal-btn), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];
        
        firstFocusableElement.focus();
        
        element.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusableElement) {
                        lastFocusableElement.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastFocusableElement) {
                        firstFocusableElement.focus();
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
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener for scope category change
        const scopeCategory = document.getElementById('updateScopeCategoryModal');
        if (scopeCategory) {
            scopeCategory.addEventListener('change', updateScopeFieldsForEdit);
        }
        
        // Add event listener for form submission
        if (updateForm) {
            updateForm.addEventListener('submit', submitUpdateAdmin);
        }
        
        // Add keyboard event for closing modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!updateModal.classList.contains('hidden')) {
                    closeUpdateModal();
                } else if (!updateSuccessModal.classList.contains('hidden')) {
                    closeUpdateSuccessModal();
                }
            }
        });
    });

    // Make functions globally accessible
    window.triggerEditAdmin = triggerEditAdmin;
    window.openUpdateModal = openUpdateModal;
    window.closeUpdateModal = closeUpdateModal;
    window.resetUpdateForm = resetUpdateForm;
    window.submitUpdateAdmin = submitUpdateAdmin;
    window.openUpdateSuccessModal = openUpdateSuccessModal;
    window.closeUpdateSuccessModal = closeUpdateSuccessModal;
    window.updateScopeFieldsForEdit = updateScopeFieldsForEdit;
    window.populateScopeDetailsForEdit = populateScopeDetailsForEdit;
})();
</script>