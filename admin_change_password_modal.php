<?php
// admin_change_password_modal.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- Global Change Password Modal (for sidebar "Change Password") -->
<div id="adminChangePasswordModal"
     class="fixed inset-0 bg-black bg-opacity-70 z-[9999] hidden items-center justify-center">
  <!-- Modal shell: limited height, flex column -->
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col relative">

      <!-- Close button (always visible at top-right of modal) -->
      <button type="button"
              onclick="closeAdminChangePasswordModal()"
              class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-2xl z-10">
          &times;
      </button>
      
      <!-- Header (fixed area, no scroll) -->
      <div class="pt-7 pb-4 px-8 border-b border-gray-100">
        <div class="text-center">
          <div class="w-14 h-14 bg-[var(--cvsu-green-light)] rounded-full flex items-center justify-center mx-auto mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
          </div>
          <h2 class="text-xl font-bold text-[var(--cvsu-green-dark)]">Change Your Password</h2>
          <p class="text-gray-600 mt-1 text-xs sm:text-sm">
            Update your admin password. You’ll need this next time you log in.
          </p>

          <!-- TOP ERROR BOX (single error for all cases) -->
          <div id="adminPasswordErrorTop"
               class="hidden mt-3 bg-red-100 border border-red-500 text-red-800 text-xs sm:text-sm px-4 py-2 rounded-md shadow-sm">
              <span id="adminPasswordErrorTopText"></span>
          </div>

          <!-- SUCCESS BOX -->
          <div id="adminPasswordSuccess"
               class="hidden mt-3 bg-green-100 border border-green-500 text-green-800 text-xs sm:text-sm px-4 py-2 rounded-md shadow-sm">
              <strong>Password updated successfully!</strong>
          </div>

          <!-- Locked hint + cooldown moved just under header so kita agad -->
          <p id="adminLockedHint"
             class="hidden mt-2 text-[11px] text-red-600 text-center font-medium">
            Too many incorrect attempts. Please use the <strong>Forgot Password</strong> link on the login page,
            or wait for the cooldown below.
          </p>
          <p id="adminCooldownText"
             class="hidden mt-1 text-[11px] text-orange-600 text-center font-medium"></p>
        </div>
      </div>
      
      <!-- Scrollable form area -->
      <form id="adminChangePasswordForm"
            class="flex-1 px-8 pt-4 pb-4 space-y-4 overflow-y-auto">

          <!-- Current password -->
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
              <div class="relative">
                  <input type="password"
                         id="adminCurrentPassword"
                         name="current_password"
                         required
                         class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)] text-sm">
                  <!-- eye toggle -->
                  <button type="button"
                          class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600"
                          data-toggle-password="adminCurrentPassword">
                      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268-2.943 9.542-7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
          </div>

          <!-- New password -->
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
              <div class="relative">
                  <input type="password"
                         id="adminNewPassword"
                         name="new_password"
                         required
                         class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)] text-sm">
                  <!-- eye toggle -->
                  <button type="button"
                          class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600"
                          data-toggle-password="adminNewPassword">
                      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268-2.943 9.542-7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>

              <!-- Strength + requirements -->
              <div class="mt-2">
                  <div class="flex items-center text-[11px] text-gray-500 mb-1">
                      <span id="adminPasswordStrength" class="font-medium">Password strength:</span>
                      <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                          <div id="adminStrengthBar"
                               class="h-2 rounded-full password-strength-bar"
                               style="width: 0%"></div>
                      </div>
                  </div>
                  <ul class="text-[11px] text-gray-500 space-y-1">
                      <li id="adminLengthCheck" class="flex items-center">
                          <svg class="h-3.5 w-3.5 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                          </svg>
                          At least 8 characters
                      </li>
                      <li id="adminUppercaseCheck" class="flex items-center">
                          <svg class="h-3.5 w-3.5 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                          </svg>
                          Contains uppercase letter
                      </li>
                      <li id="adminNumberCheck" class="flex items-center">
                          <svg class="h-3.5 w-3.5 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                          </svg>
                          Contains number
                      </li>
                      <li id="adminSpecialCheck" class="flex items-center">
                          <svg class="h-3.5 w-3.5 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                          </svg>
                          Contains special character
                      </li>
                  </ul>
              </div>
          </div>

          <!-- Confirm password -->
          <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
              <div class="relative">
                  <input type="password"
                         id="adminConfirmPassword"
                         name="confirm_password"
                         required
                         class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)] text-sm">
                  <!-- eye toggle -->
                  <button type="button"
                          class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600"
                          data-toggle-password="adminConfirmPassword">
                      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268-2.943 9.542-7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                  </button>
              </div>
              <div id="adminMatchError" class="mt-1 text-[11px] text-red-500 hidden">
                  Passwords do not match
              </div>
          </div>
      </form>

      <!-- Footer: always visible button -->
      <div class="px-8 pb-5 pt-1 border-t border-gray-100">
          <div class="flex justify-center">
              <button type="submit"
                      form="adminChangePasswordForm"
                      id="adminChangePasswordBtn"
                      class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-dark)] text-white px-8 py-2.5 rounded-lg font-medium flex items-center justify-center min-w-[180px] transition-all duration-200 transform hover:scale-105 text-sm">
                  <span id="adminChangePasswordBtnText">Update Password</span>
                  <svg id="adminChangePasswordLoader"
                       class="ml-2 h-5 w-5 animate-spin hidden"
                       fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10"
                              stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2
                               5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824
                               3 7.938l3-2.647z"></path>
                  </svg>
              </button>
          </div>
      </div>
  </div>
</div>

<script>
function openAdminChangePasswordModal() {
  const modal = document.getElementById('adminChangePasswordModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden';

  // Make sure cooldown state reapplies whenever we open the modal
  if (typeof restoreAdminPwdCooldownIfNeeded === 'function') {
    restoreAdminPwdCooldownIfNeeded();
  }
}

function closeAdminChangePasswordModal() {
  const modal = document.getElementById('adminChangePasswordModal');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function () {
  const COOLDOWN_SECONDS = 180; // 3 minutes

  const form         = document.getElementById('adminChangePasswordForm');
  if (!form) return;

  const currentInput = document.getElementById('adminCurrentPassword');
  const newInput     = document.getElementById('adminNewPassword');
  const confirmInput = document.getElementById('adminConfirmPassword');

  const strengthBar  = document.getElementById('adminStrengthBar');
  const strengthText = document.getElementById('adminPasswordStrength');
  const lengthCheck  = document.getElementById('adminLengthCheck');
  const upperCheck   = document.getElementById('adminUppercaseCheck');
  const numberCheck  = document.getElementById('adminNumberCheck');
  const specialCheck = document.getElementById('adminSpecialCheck');

  const matchError   = document.getElementById('adminMatchError');
  const okBox        = document.getElementById('adminPasswordSuccess');
  const lockedHint   = document.getElementById('adminLockedHint');
  const cooldownText = document.getElementById('adminCooldownText');

  const topErrBox    = document.getElementById('adminPasswordErrorTop');
  const topErrText   = document.getElementById('adminPasswordErrorTopText');

  const btn          = document.getElementById('adminChangePasswordBtn');
  const btnText      = document.getElementById('adminChangePasswordBtnText');
  const loader       = document.getElementById('adminChangePasswordLoader');

  let cooldownInterval = null;

  function setLoading(isLoading) {
    if (!btn) return;
    btn.disabled = isLoading;
    if (loader)  loader.classList.toggle('hidden', !isLoading);
    if (btnText) btnText.textContent = isLoading ? 'Updating…' : 'Update Password';
  }

  function showError(msg) {
    if (!topErrBox || !topErrText) return;
    topErrText.textContent = msg;
    topErrBox.classList.remove('hidden');
    topErrBox.classList.add('border-red-500', 'ring-1', 'ring-red-300');
    if (okBox) okBox.classList.add('hidden');
  }

  function hideError() {
    if (!topErrBox) return;
    topErrBox.classList.add('hidden');
    topErrBox.classList.remove('ring-1', 'ring-red-300');
  }

  function enableFormFields(enable) {
    const disabled = !enable;
    if (currentInput) currentInput.disabled = disabled;
    if (newInput)     newInput.disabled     = disabled;
    if (confirmInput) confirmInput.disabled = disabled;
    if (btn)          btn.disabled          = disabled;
  }

  function startCooldown(untilTimestamp) {
    if (!cooldownText) return;

    localStorage.setItem('adminPwdCooldownUntil', String(untilTimestamp));

    enableFormFields(false);
    if (lockedHint) lockedHint.classList.remove('hidden');
    cooldownText.classList.remove('hidden');

    if (cooldownInterval) clearInterval(cooldownInterval);

    function updateCooldown() {
      const now  = Date.now();
      const diff = untilTimestamp - now;

      if (diff <= 0) {
        clearInterval(cooldownInterval);
        cooldownInterval = null;
        cooldownText.classList.add('hidden');
        if (lockedHint) lockedHint.classList.add('hidden');
        localStorage.removeItem('adminPwdCooldownUntil');
        enableFormFields(true);
        return;
      }

      const totalSeconds = Math.floor(diff / 1000);
      const mins = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
      const secs = String(totalSeconds % 60).padStart(2, '0');

      cooldownText.textContent =
        `Please try again after ${mins}:${secs} or use the Forgot Password option on the login page.`;
    }

    updateCooldown();
    cooldownInterval = setInterval(updateCooldown, 1000);
  }

  // Expose so we can call it from openAdminChangePasswordModal()
  window.restoreAdminPwdCooldownIfNeeded = function restoreAdminPwdCooldownIfNeeded() {
    const stored = localStorage.getItem('adminPwdCooldownUntil');
    if (!stored) return;
    const until = parseInt(stored, 10);
    if (Number.isNaN(until)) {
      localStorage.removeItem('adminPwdCooldownUntil');
      return;
    }
    if (until > Date.now()) {
      showError('Too many incorrect attempts.');
      startCooldown(until);
    } else {
      localStorage.removeItem('adminPwdCooldownUntil');
    }
  };

  function showLocked(msg, untilTimestamp) {
    showError(msg || 'Too many incorrect attempts.');
    if (okBox) okBox.classList.add('hidden');

    const until = untilTimestamp || (Date.now() + COOLDOWN_SECONDS * 1000);
    startCooldown(until);
  }

  function showSuccess(msg) {
    hideError();
    if (!okBox) return;
    okBox.classList.remove('hidden');
  }

  function updateCheck(el, isValid) {
    if (!el) return;
    const icon = el.querySelector('svg');
    if (isValid) {
      el.classList.remove('text-gray-500');
      el.classList.add('text-green-500');
      if (icon) {
        icon.classList.remove('text-gray-400');
        icon.classList.add('text-green-500');
      }
    } else {
      el.classList.remove('text-green-500');
      el.classList.add('text-gray-500');
      if (icon) {
        icon.classList.remove('text-green-500');
        icon.classList.add('text-gray-400');
      }
    }
  }

  function updateStrength() {
    const val = newInput.value || '';
    const length    = val.length >= 8;
    const uppercase = /[A-Z]/.test(val);
    const number    = /[0-9]/.test(val);
    const special   = /[!@#$%^&*(),.?":{}|<>]/.test(val);

    updateCheck(lengthCheck,  length);
    updateCheck(upperCheck,   uppercase);
    updateCheck(numberCheck,  number);
    updateCheck(specialCheck, special);

    let strength = 0;
    if (length)    strength++;
    if (uppercase) strength++;
    if (number)    strength++;
    if (special)   strength++;

    const percent = (strength / 4) * 100;
    if (strengthBar) strengthBar.style.width = percent + '%';

    if (!strengthBar || !strengthText) return;

    if (strength === 0) {
      strengthBar.className = 'h-2 rounded-full bg-red-500 password-strength-bar';
      strengthText.textContent = 'Password strength: Very Weak';
      strengthText.className   = 'font-medium text-red-500';
    } else if (strength === 1) {
      strengthBar.className = 'h-2 rounded-full bg-orange-500 password-strength-bar';
      strengthText.textContent = 'Password strength: Weak';
      strengthText.className   = 'font-medium text-orange-500';
    } else if (strength === 2) {
      strengthBar.className = 'h-2 rounded-full bg-yellow-500 password-strength-bar';
      strengthText.textContent = 'Password strength: Medium';
      strengthText.className   = 'font-medium text-yellow-500';
    } else if (strength === 3) {
      strengthBar.className = 'h-2 rounded-full bg-blue-500 password-strength-bar';
      strengthText.textContent = 'Password strength: Strong';
      strengthText.className   = 'font-medium text-blue-500';
    } else {
      strengthBar.className = 'h-2 rounded-full bg-green-500 password-strength-bar';
      strengthText.textContent = 'Password strength: Very Strong';
      strengthText.className   = 'font-medium text-green-500';
    }
  }

  function checkMatch() {
    if (!newInput || !confirmInput || !matchError) return;
    if (confirmInput.value && newInput.value !== confirmInput.value) {
      matchError.classList.remove('hidden');
      confirmInput.classList.add('border-red-500');
    } else {
      matchError.classList.add('hidden');
      confirmInput.classList.remove('border-red-500');
    }
  }

  function validateClientSide() {
    const current = (currentInput?.value || '').trim();
    const next    = (newInput?.value     || '').trim();
    const confirm = (confirmInput?.value || '').trim();

    if (!current || !next || !confirm) {
      showError('Please fill in all password fields.');
      return false;
    }

    if (next !== confirm) {
      showError('New password and confirmation do not match.');
      return false;
    }

    if (next.length < 8 || !/[A-Z]/.test(next) || !/\d/.test(next)) {
      showError('Password must be at least 8 characters and include at least one uppercase letter and one number.');
      return false;
    }

    return true;
  }

  if (newInput)     newInput.addEventListener('input', () => { updateStrength(); checkMatch(); });
  if (confirmInput) confirmInput.addEventListener('input', checkMatch);

  const toggleButtons = document.querySelectorAll('[data-toggle-password]');
  toggleButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-toggle-password');
      const input = document.getElementById(targetId);
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      this.classList.toggle('text-[var(--cvsu-green)]', isHidden);
    });
  });

  // initial restore if may cooldown
  window.restoreAdminPwdCooldownIfNeeded();

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const storedUntil = localStorage.getItem('adminPwdCooldownUntil');
    if (storedUntil && parseInt(storedUntil, 10) > Date.now()) {
      showError('Please wait for the cooldown to finish before trying again.');
      return;
    }

    hideError();
    if (okBox) okBox.classList.add('hidden');
    if (lockedHint) lockedHint.classList.add('hidden');
    if (cooldownText) cooldownText.classList.add('hidden');

    if (!validateClientSide()) return;

    const payload = {
      current_password: currentInput.value,
      new_password:     newInput.value,
      confirm_password: confirmInput.value
    };

    setLoading(true);

    fetch('update_admin_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
      setLoading(false);

      if (!data || !data.status) {
        showError('Unexpected server response. Please try again.');
        return;
      }

      if (data.status === 'success') {
        showSuccess(data.message || 'Password updated successfully!');

        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-white bg-opacity-90 z-[99999] flex items-center justify-center';
        overlay.innerHTML = `
          <div class="text-center">
            <svg class="animate-spin h-12 w-12 text-[var(--cvsu-green)] mx-auto mb-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10"
                      stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824
                       3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-lg font-medium text-gray-700">Password updated. Reloading…</p>
          </div>
        `;
        document.body.appendChild(overlay);

        setTimeout(() => {
          window.location.reload();
        }, 1500);

      } else if (data.status === 'locked') {
        const serverUntil = data.cooldown_until ? Number(data.cooldown_until) : null;
        showLocked(
          data.message || 'Current password is incorrect. You have reached the maximum number of attempts.',
          serverUntil
        );

      } else if (data.status === 'error') {
        showError(data.message || 'Unable to update password. Please try again.');

      } else {
        showError('Unexpected status from server. Please try again.');
      }
    })
    .catch(err => {
      console.error(err);
      setLoading(false);
      showError('Network error. Please check your connection and try again.');
    });
  });
});
</script>
