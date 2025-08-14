document.getElementById('clearFormBtn').addEventListener('click', function () {
    const form = document.getElementById('createElectionForm');
    
    // Reset form fields
    form.reset();
  
    // Hide all conditional sections
    document.getElementById('studentFields').classList.add('hidden');
    document.getElementById('academicFields').classList.add('hidden');
    document.getElementById('nonAcademicFields').classList.add('hidden');
    document.getElementById('coopFields').classList.add('hidden');
  
    // Also clear any dynamically loaded courses
    document.getElementById('studentCoursesList').innerHTML = '';
    document.getElementById('studentCoursesContainer').classList.add('hidden');
  });
  
  document.getElementById('clearUpdateFormBtn').addEventListener('click', function () {
    const form = document.querySelector('#updateModal form');
    
    // Reset form fields
    form.reset();
  
    // Hide all conditional sections
    document.getElementById('update_studentFields').classList.add('hidden');
    document.getElementById('update_facultyFields').classList.add('hidden');
    document.getElementById('update_nonAcademicFields').classList.add('hidden');
    document.getElementById('update_coopFields').classList.add('hidden');
  
    // Clear dynamically loaded courses if any
    document.getElementById('update_studentCoursesList').innerHTML = '';
    document.getElementById('update_studentCoursesContainer').classList.add('hidden');
  });
  