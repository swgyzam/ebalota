<!-- ✅ Stylish Success Notification Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 text-center relative transform transition-all scale-100">
    
    <!-- Close Icon -->
    <button onclick="closeSuccessModal()" 
            class="absolute top-4 right-4 text-gray-400 hover:text-green-600 text-4xl font-bold leading-none">
      &times;
    </button>

    <!-- Success Icon -->
    <div class="w-20 h-20 mx-auto mb-4 bg-green-100 text-green-600 rounded-full flex items-center justify-center shadow-md">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>

    <!-- Text Content -->
    <h2 class="text-2xl font-bold text-[var(--cvsu-green)] mb-2">Admin Successfully Created</h2>
    <p class="text-gray-600 text-sm mb-4">Credentials have been emailed and account is now active.</p>

    <!-- Optional Manual Close Button -->
    <button onclick="closeSuccessModal()" 
            class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-6 py-2 rounded-full text-sm font-semibold mt-2 transition">
      Close
    </button>
  </div>
</div>


<!-- ✅ Create Admin Modal -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-xl relative">

    <!-- Close Button -->
    <button onclick="closeCreateModal()" class="absolute top-4 right-4 text-gray-500 hover:text-red-600 text-3xl font-bold leading-none">
      &times;
    </button>

    <div class="p-6">
      <h2 class="text-xl font-bold text-[var(--cvsu-green-dark)] mb-4">Create New Admin</h2>

      <!-- 🔴 Error Message -->
      <div id="formError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm mb-4"></div>

      <form onsubmit="submitCreateAdmin(event)" id="createAdminForm" class="space-y-4">
        <div>
          <label class="block font-semibold">First Name</label>
          <input type="text" name="first_name" required class="w-full p-2 border rounded" />
        </div>

        <div>
          <label class="block font-semibold">Last Name</label>
          <input type="text" name="last_name" required class="w-full p-2 border rounded" />
        </div>

        <div>
          <label class="block font-semibold">Email</label>
          <input type="email" name="email" required class="w-full p-2 border rounded" />
        </div>

        <div>
          <label class="block font-semibold">Assigned Scope</label>
          <select name="assigned_scope" required class="w-full p-2 border rounded">
            <option value="" disabled selected>Select Scope</option>
            <optgroup label="Colleges">
              <option value="CAFENR">CAFENR</option>
              <option value="CEIT">CEIT</option>
              <option value="CAS">CAS</option>
              <option value="CVMBS">CVMBS</option>
              <option value="CED">CED</option>
              <option value="CEMDS">CEMDS</option>
              <option value="CSPEAR">CSPEAR</option>
              <option value="CCJ">CCJ</option>
              <option value="CON">CON</option>
              <option value="CTHM">CTHM</option>
              <option value="COM">COM</option>
              <option value="GS-OLC">GS-OLC</option>
            </optgroup>
            <optgroup label="Other Sectors">
              <option value="FACULTY_ASSOCIATION">Faculty Association</option>
              <option value="COOP">COOP</option>
              <option value="NON_ACADEMIC">Non-Academic</option>
            </optgroup>
            <optgroup label="Special Scope">
              <option value="CSG Admin">CSG Admin</option>
            </optgroup>
          </select>
        </div>

        <div>
          <label class="block font-semibold mb-1">Auto-Generated Password</label>
          <div class="flex items-center gap-2">
            <input type="text" name="password" id="generatedPassword" readonly 
                   class="w-full p-2 border rounded bg-gray-100 font-mono text-sm" />
            <button 
              type="button" 
              onclick="copyPassword()" 
              class="text-gray-500 hover:text-gray-700"
              title="Copy to clipboard">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16h8M8 12h8m-7 8h6a2 2 0 002-2V8a2 2 0 00-2-2h-2.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0012.586 4H8a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </button>
          </div>
        </div>

        <!-- ✅ Buttons -->
        <div class="flex justify-end gap-3 pt-4 border-t mt-6">
          <button type="button" onclick="clearCreateAdminForm()" class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded font-semibold">
            Clear
          </button>
          <button type="submit" id="submitBtn" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-4 py-2 rounded font-semibold flex items-center justify-center gap-2">
            <span id="submitBtnText">Create Admin</span>
            <svg id="loaderIcon" class="w-5 h-5 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"></circle>
              <path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function generatePassword(length = 12) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  return Array.from({ length }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
}

function openCreateModal() {
  document.getElementById('generatedPassword').value = generatePassword();
  document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
  document.getElementById('createModal').classList.add('hidden');
}

function clearCreateAdminForm() {
  document.getElementById('createAdminForm').reset();
  document.getElementById('generatedPassword').value = generatePassword();
  document.getElementById('formError').classList.add('hidden');
}

function copyPassword() {
  const input = document.getElementById("generatedPassword");
  navigator.clipboard.writeText(input.value)
    .then(() => alert("Password copied to clipboard!"))
    .catch(err => console.error("Copy failed: ", err));
}

function submitCreateAdmin(event) {
  event.preventDefault();

  const form = document.getElementById('createAdminForm');
  const errorDiv = document.getElementById('formError');
  const submitBtn = document.getElementById('submitBtn');
  const submitText = document.getElementById('submitBtnText');
  const loaderIcon = document.getElementById('loaderIcon');

  errorDiv.classList.add('hidden');
  submitBtn.disabled = true;
  submitText.textContent = 'Creating...';
  loaderIcon.classList.remove('hidden');

  const formData = new FormData(form);

  fetch('create_admin.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    submitBtn.disabled = false;
    submitText.textContent = 'Create Admin';
    loaderIcon.classList.add('hidden');

    if (data.status === 'error') {
      errorDiv.textContent = data.message;
      errorDiv.classList.remove('hidden');
    } else {
      closeCreateModal();
      openSuccessModal();
      setTimeout(() => window.location.reload(), 3000);
    }
  })
  .catch(() => {
    submitBtn.disabled = false;
    submitText.textContent = 'Create Admin';
    loaderIcon.classList.add('hidden');
    errorDiv.textContent = "Something went wrong. Please try again.";
    errorDiv.classList.remove('hidden');
  });
}

function openSuccessModal() {
  document.getElementById('successModal').classList.remove('hidden');
}

function closeSuccessModal() {
  document.getElementById('successModal').classList.add('hidden');
}

if (document.getElementById('openModalBtn')) {
  document.getElementById('openModalBtn').addEventListener('click', openCreateModal);
}
</script>
