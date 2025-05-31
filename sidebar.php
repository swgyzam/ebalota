<!-- sidebar.php -->
<aside class="w-64 bg-[var(--cvsu-green-dark)] text-white fixed top-0 left-0 h-full shadow-lg overflow-y-auto">
    <div class="p-6 flex items-center gap-3 font-extrabold text-2xl tracking-wide border-b border-[var(--cvsu-green)]">
        <img src="assets/img/ebalota_logo.png" alt="CvSU Logo" class="h-10 w-10 rounded-full shadow-md" />
        <span class="text-[var(--cvsu-yellow)]">eBalota</span>
    </div>
  <nav class="mt-8">
    <a href="admin_dashboard.php" class="flex items-center gap-3 py-3 px-8 bg-[var(--cvsu-green)] font-semibold rounded-r-lg shadow-md hover:bg-[var(--cvsu-green-light)] transition duration-300">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
      </svg>
      Dashboard
    </a>
    <a href="manage_elections.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a1 1 0 001 1h6a1 1 0 001-1V7" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v4m0 0l-3 3m3-3l3 3" />
      </svg>
      Manage Elections
    </a>
    <a href="manage_users.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.89 0 5.56 1.02 7.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      Manage Users
    </a>
    <a href="manage_candidates.php" class="flex items-center gap-3 py-3 px-8 hover:bg-[var(--cvsu-green-light)] transition duration-300 rounded-r-lg mt-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[var(--cvsu-yellow)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.89 0 5.56 1.02 7.879 2.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      Manage Candidates
    </a>
    <a href="logout.php" class="flex items-center gap-3 py-3 px-8 mt-10 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition duration-300">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
      </svg>
      Logout
    </a>
  </nav>
</aside>
