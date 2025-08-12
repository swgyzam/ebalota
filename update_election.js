// Update Modal Functions
function openUpdateModal(election) {
    // Fill basic info
    document.getElementById('update_election_id').value = election.election_id;
    document.getElementById('update_election_name').value = election.title;
    document.getElementById('update_description').value = election.description;
    
    function formatDateTimeForInput(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const hh = String(date.getHours()).padStart(2, '0');
        const min = String(date.getMinutes()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
      }
      
    // Format dates
    const startDate = new Date(election.start_datetime);
    const endDate = new Date(election.end_datetime);
    document.getElementById('update_start_datetime').value = formatDateTimeForInput(startDate);
    document.getElementById('update_end_datetime').value = formatDateTimeForInput(endDate);

    
    // Set target voter type and show appropriate fields
    const targetPosition = election.target_position.toLowerCase().replace(/\s+/g, '_');
    hideAllUpdateFields();
    
    if (targetPosition.includes('student')) {
        document.getElementById('target_student').checked = true;
        document.getElementById('update_studentFields').classList.remove('hidden');
        initializeStudentFields(election);
    } 
    else if (targetPosition.includes('faculty')) {
        document.getElementById('target_faculty').checked = true;
        document.getElementById('update_facultyFields').classList.remove('hidden');
        initializeFacultyFields(election);
    }
    else if (targetPosition.includes('non-academic')) {
        document.getElementById('target_non_academic').checked = true;
        document.getElementById('update_nonAcademicFields').classList.remove('hidden');
        initializeNonAcademicFields(election);
    }
    else if (targetPosition.includes('coop')) {
        document.getElementById('target_coop').checked = true;
        document.getElementById('update_coopFields').classList.remove('hidden');
        initializeCoopFields(election);
    }
    
    // Show the modal
    document.getElementById('updateModal').classList.remove('hidden');
}

function closeUpdateModal() {
    document.getElementById('updateModal').classList.add('hidden');
}

function hideAllUpdateFields() {
    document.getElementById('update_studentFields').classList.add('hidden');
    document.getElementById('update_facultyFields').classList.add('hidden');
    document.getElementById('update_nonAcademicFields').classList.add('hidden');
    document.getElementById('update_coopFields').classList.add('hidden');
}

// Helper function to format date for datetime-local input
function formatDateTimeForInput(date) {
    return date.toISOString().slice(0, 16);
}

// Field Initialization Functions
function initializeStudentFields(election) {
    const collegeSelect = document.getElementById('update_allowed_colleges');
    
    if (election.allowed_colleges && election.allowed_colleges !== 'all') {
        collegeSelect.value = election.allowed_colleges;
        loadUpdateCourses('student', election.allowed_courses);
    }
}

function initializeFacultyFields(election) {
    const collegeSelect = document.getElementById('update_allowed_colleges_faculty');

    if (election.allowed_colleges && election.allowed_colleges !== 'all') {
        collegeSelect.value = election.allowed_colleges;
    }

    // Hide courses container only if it exists
    const coursesContainer = document.getElementById('update_facultyCoursesContainer');
    if (coursesContainer) {
        coursesContainer.classList.add('hidden');
    }

    // Set allowed status
    if (election.allowed_status && election.allowed_status !== 'all') {
        const statuses = election.allowed_status.split(',');
        document.querySelectorAll('#update_facultyFields input[name="allowed_status_faculty[]"]').forEach(checkbox => {
            checkbox.checked = statuses.includes(checkbox.value);
        });
    }
}

function initializeNonAcademicFields(election) {
    // Departments (dropdown)
    const deptSelect = document.getElementById('update_allowed_departments_nonacad');
    if (election.allowed_departments) {
        if (election.allowed_departments.toLowerCase() === 'all') {
            deptSelect.value = 'all';
        } else {
            // Try to select the first department in the list (if dropdown is single select)
            const depts = election.allowed_departments.split(',').map(d => d.trim());
            if (deptSelect.multiple) {
                // For multi-select dropdown
                Array.from(deptSelect.options).forEach(option => {
                    option.selected = depts.includes(option.value);
                });
            } else {
                // If single select, just pick the first one
                deptSelect.value = depts[0] || 'all';
            }
        }
    }

    // Status checkboxes
    if (election.allowed_status) {
        if (election.allowed_status.toLowerCase() === 'all') {
            document.querySelectorAll('#update_nonAcademicFields input[name="allowed_status_nonacad[]"]').forEach(cb => cb.checked = true);
        } else {
            const statuses = election.allowed_status.split(',').map(s => s.trim());
            document.querySelectorAll('#update_nonAcademicFields input[name="allowed_status_nonacad[]"]').forEach(checkbox => {
                checkbox.checked = statuses.includes(checkbox.value);
            });
        }
    }
}

function initializeCoopFields(election) {
    if (election.allowed_status && election.allowed_status !== 'all') {
        document.querySelector('#update_coopFields input[name="allowed_status_coop[]"]').checked = 
            election.allowed_status.includes('MIGS');
    } else {
        document.querySelector('#update_coopFields input[name="allowed_status_coop[]"]').checked = true;
    }
}

// Course Loading Function
function loadUpdateCourses(type, allowedCourses = '') {
    const collegeSelect = document.getElementById(`update_allowed_colleges${type === 'faculty' ? '_faculty' : ''}`);
    const coursesContainer = document.getElementById(`update_${type}CoursesContainer`);
    const coursesList = document.getElementById(`update_${type}CoursesList`);
    
    if (collegeSelect.value === 'all') {
        coursesContainer.classList.add('hidden');
        return;
    }
    
    // Clear existing courses
    coursesList.innerHTML = '';
    
    // Get courses for selected college
    const courses = collegeCourses[collegeSelect.value] || [];
    
    // Add courses to the list
    courses.forEach(course => {
        const isChecked = allowedCourses && allowedCourses.includes(course) ? 'checked' : '';
        coursesList.innerHTML += `
            <label class="flex items-center">
                <input type="checkbox" name="allowed_courses_${type}[]" value="${course}" class="mr-1" ${isChecked}>
                ${course}
            </label>
        `;
    });
    
    // Show courses container
    coursesContainer.classList.remove('hidden');
}

// Toggle Checkboxes Function
function toggleUpdateCheckboxes(name) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
    if (checkboxes.length === 0) return;
    
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    checkboxes.forEach(checkbox => checkbox.checked = !allChecked);
}

// Event Listeners for Update Modal
document.addEventListener('DOMContentLoaded', function() {
    // Radio button change handlers
    document.querySelectorAll('#updateModal input[name="target_voter"]').forEach(radio => {
        radio.addEventListener('change', function() {
            hideAllUpdateFields();
            switch(this.value) {
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
    
    // College select change handlers
    document.getElementById('update_allowed_colleges').addEventListener('change', function() {
        loadUpdateCourses('student');
    });
    
    document.getElementById('update_allowed_colleges_faculty').addEventListener('change', function() {
        loadUpdateCourses('faculty');
    });
    
    // Ensure COOP checkbox is checked by default
    const coopCheckbox = document.querySelector('#update_coopFields input[type="checkbox"]');
    if (coopCheckbox) {
        coopCheckbox.checked = true;
    }
});