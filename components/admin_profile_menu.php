<?php
// components/admin_profile_menu.php
// Reusable admin profile icon + dropdown
?>
<div class="relative" id="adminProfileWrapper">
    <!-- PROFILE ICON BUTTON -->
    <button id="adminProfileBtn"
            type="button"
            class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center
                   hover:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-offset-2
                   focus:ring-offset-[var(--cvsu-green-dark)] focus:ring-white">
        <i class="fas fa-user text-white"></i>
    </button>

    <!-- DROPDOWN MENU -->
    <div id="adminProfileMenu"
         class="hidden absolute right-0 mt-2 w-52 bg-white rounded-md shadow-lg py-1 z-50 text-sm">

        <button type="button"
                id="adminChangePasswordBtn"
                class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100 flex items-center gap-2">
            <i class="fas fa-key text-gray-500 text-xs"></i>
            <span>Change Password</span>
        </button>

        <a href="admin_activity_logs.php"
           class="block px-4 py-2 text-gray-700 hover:bg-gray-100 flex items-center gap-2">
            <i class="fas fa-clock-rotate-left text-gray-500 text-xs"></i>
            <span>Activity Logs</span>
        </a>

        <a href="logout.php"
           class="block px-4 py-2 text-gray-700 hover:bg-gray-100 flex items-center gap-2">
            <i class="fas fa-sign-out-alt text-gray-500 text-xs"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
