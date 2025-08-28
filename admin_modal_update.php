<!-- ✅ Edit Admin Modal -->
<div id="updateModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-xl w-full max-w-lg p-6 relative shadow-2xl transition-all scale-100">

    <!-- Close X -->
    <button onclick="closeUpdateModal()" class="absolute top-4 right-4 text-gray-500 hover:text-red-600 text-3xl font-bold leading-none">
      &times;
    </button>

    <h2 class="text-2xl font-bold text-[var(--cvsu-green)] mb-4">Edit Admin</h2>

    <div id="updateFormError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm mb-4"></div>

    <form onsubmit="submitUpdateAdmin(event)" id="updateAdminForm" class="space-y-4">
      <input type="hidden" name="user_id" id="update_user_id" />

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block font-semibold mb-1">First Name</label>
          <input type="text" name="first_name" id="update_first_name" required class="w-full p-2 border rounded" />
        </div>
        <div>
          <label class="block font-semibold mb-1">Last Name</label>
          <input type="text" name="last_name" id="update_last_name" required class="w-full p-2 border rounded" />
        </div>
      </div>

      <div>
        <label class="block font-semibold mb-1">Email</label>
        <input type="email" name="email" id="update_email" required class="w-full p-2 border rounded bg-gray-100" readonly />
      </div>

      <div>
        <label class="block font-semibold mb-1">Election Scope</label>
        <select name="assigned_scope" id="update_assigned_scope" required class="w-full p-2 border rounded">
          <option value="" disabled>Select Election Scope</option>
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
          <optgroup label="Others">
            <option value="FACULTY_ASSOCIATION">Faculty Association</option>
            <option value="COOP">Cooperative</option>
            <option value="NON_ACADEMIC">Non-Academic</option>
          </optgroup>
          <optgroup label="Special Scope">
              <option value="CSG Admin">CSG Admin</option>
          </optgroup>
        </select>
      </div>

      <div class="flex justify-end gap-3 pt-4 border-t mt-6">
        <button type="button" onclick="resetUpdateForm()" class="bg-yellow-400 hover:bg-yellow-500 text-white px-4 py-2 rounded font-semibold">Clear</button>
        <button type="submit" id="updateBtn" class="bg-[var(--cvsu-green-dark)] hover:bg-[var(--cvsu-green)] text-white px-4 py-2 rounded font-semibold flex items-center gap-2">
          <span>Save Changes</span>
          <span id="updateSpinner" class="hidden animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full border-solid"></span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Success Modal -->
<div id="updateSuccessModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 flex justify-center items-center hidden">
  <div class="bg-white p-6 rounded-xl shadow-2xl text-center w-full max-w-md relative">
    <button onclick="closeUpdateSuccessModal()" class="absolute top-4 right-4 text-gray-500 hover:text-green-600 text-3xl font-bold leading-none">
      &times;
    </button>
    <svg class="w-16 h-16 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
    </svg>
    <h3 class="text-xl font-bold text-[var(--cvsu-green)] mb-2">Changes Saved!</h3>
    <p class="text-gray-600">Admin information has been updated successfully.</p>
  </div>
</div>

<script>
function triggerEditAdmin(userId) {
  fetch('get_admin.php?user_id=' + userId)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        openUpdateModal(data.data);
      } else {
        alert("Admin not found.");
      }
    })
    .catch(() => {
      alert("Failed to load admin info.");
    });
}

function openUpdateModal(admin) {
  document.getElementById('update_user_id').value = admin.user_id;
  document.getElementById('update_first_name').value = admin.first_name;
  document.getElementById('update_last_name').value = admin.last_name;
  document.getElementById('update_email').value = admin.email;

  const scopeSelect = document.getElementById('update_assigned_scope');
  const scopeValue = admin.assigned_scope?.trim();

  let matched = false;
  Array.from(scopeSelect.options).forEach(opt => {
    opt.selected = false;
    if (opt.value === scopeValue) {
      opt.selected = true;
      matched = true;
    }
  });

  if (!matched && scopeValue) {
    const customOpt = document.createElement("option");
    customOpt.value = scopeValue;
    customOpt.textContent = scopeValue;
    customOpt.selected = true;
    scopeSelect.appendChild(customOpt);
  }

  document.getElementById('updateModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('update_first_name').focus(), 100);
}

function closeUpdateModal() {
  document.getElementById('updateModal').classList.add('hidden');
  resetUpdateForm();
}

function resetUpdateForm() {
  document.getElementById('updateAdminForm').reset();
  document.getElementById('updateFormError').classList.add('hidden');
  document.getElementById('updateSpinner').classList.add('hidden');
}

function submitUpdateAdmin(event) {
  event.preventDefault();
  const form = document.getElementById('updateAdminForm');
  const errorDiv = document.getElementById('updateFormError');
  const spinner = document.getElementById('updateSpinner');
  const btn = document.getElementById('updateBtn');

  errorDiv.classList.add('hidden');
  spinner.classList.remove('hidden');
  btn.disabled = true;

  const formData = new FormData(form);
  const userId = formData.get("user_id");

  fetch('update_admin.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    spinner.classList.add('hidden');
    btn.disabled = false;

    if (data.status === 'error') {
      errorDiv.textContent = data.message;
      errorDiv.classList.remove('hidden');
    } else {
      closeUpdateModal();
      openUpdateSuccessModal();
    }
  })
  .catch(() => {
    spinner.classList.add('hidden');
    btn.disabled = false;
    errorDiv.textContent = "Something went wrong. Please try again.";
    errorDiv.classList.remove('hidden');
  });
}

function openUpdateSuccessModal() {
  document.getElementById('updateSuccessModal').classList.remove('hidden');
  setTimeout(() => {
    closeUpdateSuccessModal();
    window.location.reload();
  }, 2500);
}

function closeUpdateSuccessModal() {
  document.getElementById('updateSuccessModal').classList.add('hidden');
}
</script>
