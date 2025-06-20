<?php
// voter_sidebar.php
?>
<!-- Sidebar Toggle Button -->
<button 
    id="sidebarToggle" 
    class="md:hidden fixed top-4 left-4 z-50 p-4 rounded bg-[var(--cvsu-green-dark)] text-white shadow-lg focus:outline-none transition-transform duration-300"
    aria-label="Open sidebar"
    type="button"
>
    <!-- Hamburger icon -->
    <svg id="sidebarToggleIcon" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>

<!-- Sidebar -->
<aside 
    id="sidebar"
    class="sidebar w-64 bg-[var(--cvsu-green-dark)] text-white fixed top-0 left-0 h-full shadow-lg overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-40"
>
    <div class="p-6 flex items-center gap-3 font-extrabold text-2xl tracking-wide border-b border-[var(--cvsu-green)]">
        <img src="assets/img/ebalota_logo.png" alt="Logo" />
    </div>
    <nav class="mt-8">
        <a href="voters_dashboard.php" class="flex items-center gap-3 py-3 px-8 bg-[var(--cvsu-green)] font-semibold rounded-r-lg shadow-md hover:bg-[var(--cvsu-green-light)] transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
            </svg>
            Dashboard
        </a>
        <a href="profile.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.89 0 5.56 1.02 7.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            View Candidates
        </a>
        <a href="logout.php" class="flex items-center gap-3 py-3 px-8 mt-10 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
            </svg>
            Logout
        </a>
    </nav>
</aside>

<!-- Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-30 hidden md:hidden transition-opacity duration-300"></div>

<!-- Sidebar Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const navLinks = sidebar.querySelectorAll('a');
    const SIDEBAR_WIDTH = 256; // 64 * 4px (w-64)

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

    toggleBtn.addEventListener('click', function () {
        if (sidebar.classList.contains('-translate-x-full')) {
            openSidebar();
        } else {
            closeSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

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
});
</script>
