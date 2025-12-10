<?php
// super_admin_sidebar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ========= BASIC NAME / HANDLE / INITIALS (same style as admin) ========= */
$firstNameRaw = $_SESSION['first_name'] ?? '';
$lastName     = $_SESSION['last_name']  ?? '';

$firstToken = '';
if ($firstNameRaw !== '') {
    $parts      = preg_split('/\s+/', trim($firstNameRaw));
    $firstToken = $parts[0] ?? '';
}

$displayName = trim($firstToken . ' ' . $lastName);
if ($displayName === '') {
    $displayName = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'Super Admin');
}

$handle      = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'superadmin');
$handleLabel = '@' . $handle;

$initialSource = $firstToken !== '' ? $firstToken : $displayName;
$initial       = strtoupper(substr(trim($initialSource), 0, 1) ?: 'S');

/* ========= PROFILE PICTURE (optional) ========= */
$sidebarProfileFile = $_SESSION['profile_picture'] ?? null;
if ($sidebarProfileFile) {
    $sidebarProfileAbs = __DIR__ . '/uploads/profile_pictures/' . $sidebarProfileFile;
    $version           = is_file($sidebarProfileAbs) ? filemtime($sidebarProfileAbs) : time();
    $sidebarProfilePath = 'uploads/profile_pictures/' . $sidebarProfileFile . '?v=' . $version;
} else {
    $sidebarProfilePath = null;
}
?>

<!-- Sidebar Toggle Button (Mobile) -->
<button 
    id="sidebarToggle" 
    class="md:hidden fixed top-4 left-4 z-50 p-4 rounded bg-[var(--cvsu-green-dark)] text-white shadow-lg focus:outline-none transition-transform duration-300"
    aria-label="Open sidebar"
    type="button"
>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Sidebar -->
<aside 
    id="sidebar"
    class="sidebar w-64 bg-[var(--cvsu-green-dark)] text-white fixed top-0 left-0 h-full shadow-lg overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-40"
>
    <!-- Logo -->
    <div class="p-6 flex items-center gap-3 font-extrabold text-2xl tracking-wide border-b border-[var(--cvsu-green)]">
        <img src="assets/img/ebalota_logo.png" alt="Logo" />
    </div>

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
                        <img src="<?= htmlspecialchars($sidebarProfilePath) ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
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

        <!-- DROPDOWN MENU -->
        <div id="sidebarProfileMenu" class="mt-2 ml-2 space-y-1 hidden">
            <!-- View Profile -->
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

            <!-- Change Password (opens super admin modal) -->
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

    <!-- MAIN NAV -->
    <nav class="mt-6 space-y-2">
        <a href="super_admin_dashboard.php"
           class="flex items-center gap-3 py-3 px-8 bg-[var(--cvsu-green)] font-semibold rounded-r-lg shadow-md hover:bg-[var(--cvsu-green-light)] transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" viewBox="0 0 24 24" stroke="currentColor" fill="none">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
            </svg>
            Dashboard
        </a>

        <a href="manage_elections.php"
           class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] rounded-r-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" viewBox="0 0 24 24" stroke="currentColor" fill="none">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a1 1 0 001 1h6a1 1 0 001-1V7" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v4m0 0l-3 3m3-3l3 3" />
            </svg>
            Manage Elections
        </a>

        <a href="manage_admins.php"
           class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] rounded-r-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 018 0v2m-1 4H8a1 1 0 01-1-1v-2a3 3 0 013-3h2" />
            </svg>
            Manage Admins
        </a>

        <a href="manage_users.php"
           class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] rounded-r-lg transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.89 0 5.56 1.02 7.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Manage Users
        </a>

        <a href="logout.php"
           class="flex items-center gap-3 py-3 px-8 mt-10 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
            </svg>
            Logout
        </a>
    </nav>
</aside>

<!-- Overlay -->
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
            if (window.innerWidth < 768) closeSidebar();
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

    // PROFILE DROPDOWN TOGGLE
    const profileToggle = document.getElementById('sidebarProfileToggle');
    const profileMenu   = document.getElementById('sidebarProfileMenu');
    const profileChevron= document.getElementById('sidebarProfileChevron');

    if (profileToggle && profileMenu && profileChevron) {
        profileToggle.addEventListener('click', function () {
            profileMenu.classList.toggle('hidden');
            profileChevron.classList.toggle('rotate-180');
        });
    }

    // CHANGE PASSWORD → open super admin modal
    const changePwdBtn = document.getElementById('sidebarChangePasswordBtn');
    if (changePwdBtn && typeof openSuperAdminChangePasswordModal === 'function') {
        changePwdBtn.addEventListener('click', function () {
            openSuperAdminChangePasswordModal();
        });
    }
});
</script>
