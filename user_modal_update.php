<!-- Edit User Modal -->
<div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
  <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
    <div class="mt-3">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900">Edit User</h3>
        <button onclick="document.getElementById('updateModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form id="updateUserForm" action="admin_update_user.php" method="post" class="space-y-4">
        <input type="hidden" id="update_user_id" name="user_id">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="update_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
            <input type="text" id="update_first_name" name="first_name" required
                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
          </div>
          
          <div>
            <label for="update_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
            <input type="text" id="update_last_name" name="last_name" required
                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
          </div>
        </div>
        
        <div>
          <label for="update_email" class="block text-sm font-medium text-gray-700">Email</label>
          <input type="email" id="update_email" name="email" required
                 class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        
        <div>
          <label for="update_position" class="block text-sm font-medium text-gray-700">Position</label>
          <select id="update_position" name="position" 
                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
            <option value="student">Student</option>
            <option value="academic">Faculty</option>
            <option value="non-academic">Non-Academic</option>
          </select>
        </div>
        
        <div>
          <label for="update_department" class="block text-sm font-medium text-gray-700">Department/College</label>
          <input type="text" id="update_department" name="department"
                 class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        
        <div>
          <label for="update_course" class="block text-sm font-medium text-gray-700">Course</label>
          <input type="text" id="update_course" name="course"
                 class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        
        <div class="flex justify-end space-x-3 pt-4">
          <button type="button" onclick="document.getElementById('updateModal').classList.add('hidden')"
                  class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
            Cancel
          </button>
          <button type="submit"
                  class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>