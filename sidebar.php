<?php
// sidebar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- PROFILE NAME (first word lang + last name) ---
$firstNameRaw = $_SESSION['first_name'] ?? '';
$lastName     = $_SESSION['last_name']  ?? '';

// kunin lang yung unang salita ng first_name
$firstToken = '';
if ($firstNameRaw !== '') {
    $parts = preg_split('/\s+/', trim($firstNameRaw));
    $firstToken = $parts[0] ?? '';
}

// "Mark Dano" (Mark lang kahit "Mark Anthony" or "Mark Bryan Anthony")
$displayName = trim($firstToken . ' ' . $lastName);

// fallback kung wala pa sa session
if ($displayName === '') {
    $displayName = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'Admin');
}

$handle       = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'admin');
$handleLabel  = '@' . $handle;

// initial galing sa first word kung meron, else sa buong display name
$initialSource = $firstToken !== '' ? $firstToken : $displayName;
$initial       = strtoupper(substr(trim($initialSource), 0, 1) ?: 'A');

// fallback kung sakaling wala pa sa session
if ($displayName === '') {
    $displayName = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'Admin');
}

$handle  = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'admin');
$initial = strtoupper(substr(trim($displayName), 0, 1) ?: 'A');

// === PROFILE PICTURE FOR SIDEBAR (with cache-busting) ===
$sidebarProfileFile = $_SESSION['profile_picture'] ?? null;

if ($sidebarProfileFile) {
    $sidebarProfileAbs = __DIR__ . '/uploads/profile_pictures/' . $sidebarProfileFile;
    $version           = is_file($sidebarProfileAbs) ? filemtime($sidebarProfileAbs) : time();

    // ?v=timestamp para hindi na kailangan ng hard refresh
    $sidebarProfilePath = 'uploads/profile_pictures/' . $sidebarProfileFile . '?v=' . $version;
} else {
    $sidebarProfilePath = null;
}

// --- HIDE ACCOUNT SECTION IF SUPER ADMIN IMPERSONATING (optional) ---
$showAccountSection = true;
if (function_exists('isSuperAdmin') && function_exists('getImpersonatedScopeId')) {
    if (isSuperAdmin() && getImpersonatedScopeId() !== null) {
        $showAccountSection = false;
    }
}
?>

<!-- Responsive Sidebar Component -->
<button 
    id="sidebarToggle" 
    class="md:hidden fixed top-4 left-4 z-50 p-4 rounded bg-[var(--cvsu-green-dark)] text-white shadow-lg focus:outline-none transition-transform duration-300"
    aria-label="Open sidebar"
    type="button"
>
    <svg id="sidebarToggleIcon" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<aside 
    id="sidebar"
    class="sidebar w-64 bg-[var(--cvsu-green-dark)] text-white fixed top-0 left-0 h-full shadow-lg overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-40"
>
    <!-- Logo -->
    <div class="p-6 flex items-center gap-3 font-extrabold text-2xl tracking-wide border-b border-[var(--cvsu-green)]">
        <img src="assets/img/ebalota_logo.png" alt="Logo" />
    </div>

    <?php if ($showAccountSection): ?>
    <!-- PROFILE HEADER (click to toggle menu) -->
    <div class="px-4 py-3 border-b border-[var(--cvsu-green)] bg-[var(--cvsu-green)]/20">
        <button
            id="sidebarProfileToggle"
            type="button"
            class="w-full flex items-center justify-between gap-3 text-left"
        >
            <div class="flex items-center gap-3 flex-shrink-0">
                <div class="w-9 h-9 rounded-full bg-white/20 overflow-hidden flex items-center justify-center text-sm font-semibold">
                    <?php if ($sidebarProfilePath): ?>
                        <!-- Real profile picture -->
                        <img
                            id="sidebarProfileAvatar"
                            src="<?= htmlspecialchars($sidebarProfilePath) ?>"
                            alt="Profile"
                            class="w-full h-full object-cover"
                        >
                    <?php else: ?>
                        <!-- Fallback initials kung wala pang profile_picture -->
                        <?= htmlspecialchars($initial) ?>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col leading-tight w-[140px] whitespace-normal break-words">
                    <span class="text-sm font-semibold break-words">
                        <?= htmlspecialchars($displayName) ?>
                    </span>
                    <span class="text-xs text-gray-200 opacity-80 break-words">
                        <?= htmlspecialchars($handleLabel) ?>
                    </span>
                </div>
            </div>
            <svg id="sidebarProfileChevron"
                class="h-4 w-4 text-gray-200 transition-transform duration-200 flex-shrink-0"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- DROPDOWN MENU (View profile / password / logs) -->
        <div id="sidebarProfileMenu" class="mt-2 ml-2 space-y-1 hidden">
            <!-- View Profile (stub page, palitan mo yung href kung may profile page ka) -->
            <a href="admin_profile.php"
               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm hover:bg-[var(--cvsu-green-light)]/70">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4 text-[var(--cvsu-yellow)]"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5.121 17.804A13.937 13.937 0 0112 15c2.89 0 5.56 1.02 7.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>View Profile</span>
            </a>

            <!-- Change Password (uses existing modal) -->
            <button type="button"
                    id="sidebarChangePasswordBtn"
                    class="w-full flex items-center gap-2 px-3 py-1.5 rounded-md text-sm text-left hover:bg-[var(--cvsu-green-light)]/70">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4 text-[var(--cvsu-yellow)]"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 11c1.105 0 2-.895 2-2V7a4 4 0 10-8 0v2a2 2 0 002 2m10 4v3a2 2 0 01-2 2H6a2 2 0 01-2-2v-3a2 2 0 012-2h12a2 2 0 012 2z" />
                </svg>
                <span>Change Password</span>
            </button>

            <!-- Activity Logs -->
            <a href="admin_activity_logs.php"
               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-sm hover:bg-[var(--cvsu-green-light)]/70">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4 text-[var(--cvsu-yellow)]"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Activity Logs</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN NAV -->
    <nav class="mt-6">
        <a href="admin_dashboard_redirect.php" class="flex items-center gap-3 py-3 px-8 bg-[var(--cvsu-green)] font-semibold rounded-r-lg shadow-md hover:bg-[var(--cvsu-green-light)] transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
            </svg>
            Dashboard
        </a>

        <a href="admin_view_elections.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 4h8m-8 4h8" />
            </svg>
            View Elections
        </a>

        <a href="admin_manage_users.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.89 0 5.56 1.02 7.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Manage Users
        </a>

        <a href="admin_manage_candidates.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h6v6m2 4H7a2 2 0 01-2-2V7a2 2 0 012-2h5l2 2h5a2 2 0 012 2v10a2 2 0 01-2 2z" />
            </svg>
            Manage Candidates
        </a>

        <a href="admin_analytics.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 11V7a4 4 0 018 0v4m-4 4v4m-8-4v4m-4-4v4" />
            </svg>
            View Analytics
        </a>

        <!-- Logout -->
        <a href="logout.php" class="flex items-center gap-3 py-3 px-8 mt-10 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
            </svg>
            Logout
        </a>
    </nav>
</aside>

<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-30 hidden md:hidden transition-opacity duration-300"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar   = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay   = document.getElementById('sidebarOverlay');
    const navLinks  = sidebar.querySelectorAll('a');
    const SIDEBAR_WIDTH = 256;

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        setTimeout(() => overlay.classList.add('opacity-100'), 10);
        document.body.style.overflow = 'hidden';
        toggleBtn.style.transform = `translateX(${SIDEBAR_WIDTH}px)`;
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        overlay.classList.remove('opacity-100');
        document.body.style.overflow = '';
        toggleBtn.style.transform = 'translateX(0)';
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function () {
            if (window.innerWidth < 768) {
                closeSidebar();
            }
        });
    });

    function handleResize() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.add('hidden');
            overlay.classList.remove('opacity-100');
            document.body.style.overflow = '';
            toggleBtn.style.transform = 'translateX(0)';
        } else {
            sidebar.classList.add('-translate-x-full');
            toggleBtn.style.transform = 'translateX(0)';
        }
    }
    window.addEventListener('resize', handleResize);
    handleResize();

    // === PROFILE DROPDOWN TOGGLE ===
    const profileToggle = document.getElementById('sidebarProfileToggle');
    const profileMenu   = document.getElementById('sidebarProfileMenu');
    const profileChevron= document.getElementById('sidebarProfileChevron');

    if (profileToggle && profileMenu && profileChevron) {
        profileToggle.addEventListener('click', function () {
            profileMenu.classList.toggle('hidden');
            profileChevron.classList.toggle('rotate-180');
        });
    }

    // === CHANGE PASSWORD FROM PROFILE MENU ===
    const changeBtn          = document.getElementById('sidebarChangePasswordBtn');
    const forcePwModal       = document.getElementById('forcePasswordChangeModal');   // used on dashboards
    const adminChangePwModal = document.getElementById('adminChangePasswordModal');  // global modal

    if (changeBtn) {
        changeBtn.addEventListener('click', function () {

            // 1) Kung naka-force password pa (force modal exists at HINDI hidden),
            //    huwag mag-open ng global change-password modal.
            if (forcePwModal && !forcePwModal.classList.contains('hidden')) {
                // siguraduhin lang na naka-focus yung force modal
                document.body.style.pointerEvents = 'none';
                forcePwModal.style.pointerEvents = 'auto';
                if (window.innerWidth < 768) {
                    // isara sidebar sa mobile
                    if (typeof closeSidebar === 'function') {
                        closeSidebar();
                    }
                }
                return;
            }

            // 2) Normal case: gamitin global admin change password modal kung meron
            if (adminChangePwModal) {
                adminChangePwModal.classList.remove('hidden');
                adminChangePwModal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                if (window.innerWidth < 768) {
                    if (typeof closeSidebar === 'function') {
                        closeSidebar();
                    }
                }
                return;
            }

            // 3) Fallback: kung wala talagang modal sa page, redirect na lang sa page
            if (forcePwModal) {
                // may force modal pero hidden (no force flag) -> pwede rin gamitin as fallback
                forcePwModal.classList.remove('hidden');
                forcePwModal.style.pointerEvents = 'auto';
                document.body.style.pointerEvents = 'none';
                if (window.innerWidth < 768) {
                    if (typeof closeSidebar === 'function') {
                        closeSidebar();
                    }
                }
                return;
            }

            window.location.href = 'admin_change_password.php';
        });
    }
});
</script>
