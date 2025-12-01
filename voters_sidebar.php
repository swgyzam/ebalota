<?php
// voters_sidebar.php
?>
<!-- Sidebar -->
<aside 
    id="sidebar"
    class="w-64 bg-[var(--cvsu-green-dark)] text-white fixed top-0 left-0 h-full shadow-lg overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-30"
>
    <div class="p-6 flex items-center gap-3 font-extrabold text-2xl tracking-wide border-b border-[var(--cvsu-green)]">
        <img src="assets/img/ebalota_logo.png" alt="eBalota Logo" class="h-10 w-auto" />
    </div>

    <nav class="mt-8 space-y-2">
        <!-- Dashboard -->
        <a href="voters_dashboard.php" class="flex items-center gap-3 py-3 px-8 bg-[var(--cvsu-green)] font-semibold rounded-r-lg shadow-md hover:bg-[var(--cvsu-green-light)] transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
            </svg>
            Dashboard
        </a>

        <!-- Profile -->
        <a href="voter_profile.php" class="flex items-center gap-3 py-3 px-8 font-semibold rounded-r-lg hover:bg-[var(--cvsu-green)] transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.879 6.196M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Profile
        </a>

        <!-- Logout -->
        <a href="logout.php" class="flex items-center gap-3 py-3 px-8 mt-6 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
            </svg>
            Logout
        </a>
    </nav>
</aside>

<!-- Overlay for mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-20 hidden md:hidden transition-opacity duration-300"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const navLinks = sidebar.querySelectorAll('a');
    const SIDEBAR_WIDTH = 256; // w-64

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('-translate-x-full')) {
            openSidebar();
        } else {
            closeSidebar();
        }
    }

    // Expose to global so header buttons can use it
    window.openSidebar = openSidebar;
    window.closeSidebar = closeSidebar;
    window.toggleSidebar = toggleSidebar;

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
            // Desktop: sidebar always visible
            sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
            document.body.style.overflow = '';
        } else {
            // Mobile: hidden by default
            sidebar.classList.add('-translate-x-full');
        }
    }

    window.addEventListener('resize', handleResize);
    handleResize();
});
</script>
