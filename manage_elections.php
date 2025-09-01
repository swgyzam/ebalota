<?php
session_start();
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Update statuses
$now = date('Y-m-d H:i:s');
$pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
$pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
$pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");

// Get filter
$filter_status = $_GET['status'] ?? 'all';
if ($filter_status === 'all') {
    $stmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
} else {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE status = :status ORDER BY start_datetime DESC");
    $stmt->execute(['status' => $filter_status]);
}
$elections = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Elections</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    @media (max-width: 767px) {
      .main-content {
        margin-left: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      .fixed-header {
        left: 0 !important;
        width: 100vw !important;
      }
    }
    @media (min-width: 768px) {
      .main-content {
        margin-left: 16rem !important;
      }
      .fixed-header {
        left: 16rem !important;
        width: calc(100vw - 16rem) !important;
      }
    }
  </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen">

<?php include 'super_admin_sidebar.php'; ?>

<header class="w-full fixed top-0 h-16 shadow z-10 flex items-center justify-between px-6" style="background-color:rgb(25, 72, 49);">
  <div class="flex items-center space-x-4">
    <!-- Hamburger button (always shown on mobile, hidden on desktop) -->
    <button 
      id="sidebarToggle" 
      class="md:hidden mr-2 p-2 rounded bg-[var(--cvsu-green-dark)] text-white shadow-lg focus:outline-none flex items-center justify-center"
      aria-label="Open sidebar"
      type="button"
    >
      <svg id="sidebarToggleIcon" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path id="hamburgerIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
        <path id="closeIcon" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
    <h1 class="text-lg font-bold text-white">Manage Elections</h1>
  </div>
  <button id="openModalBtn" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 text-xs rounded font-semibold transition">+ Create Election</button>
</header>

<main class="main-content flex-1 pt-20 px-2 md:px-8">
  <!-- Filter Buttons -->
  <div class="flex justify-center mb-6 mt-2">
    <div class="inline-flex bg-gray-100 rounded-full shadow-sm p-1">
      <?php
        $statuses = ['all' => 'All', 'upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed'];
        foreach ($statuses as $key => $label):
          $isActive = ($filter_status === $key);
          $btnClass = $isActive
            ? 'bg-green-600 text-white shadow-md'
            : 'bg-transparent text-gray-700 hover:bg-green-100';
      ?>
        <a href="?status=<?= $key ?>"
           class="px-4 py-2 rounded-full font-medium transition-colors duration-200 <?= $btnClass ?>">
           <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

<!-- Election Cards -->
<section class="grid gap-5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
  <?php if (count($elections) === 0): ?>
    <p class="text-gray-700 col-span-full text-center mt-12">No elections found.</p>
  <?php else: ?>
    <?php foreach ($elections as $election): ?>
      <?php
        $start = $election['start_datetime'];
        $end = $election['end_datetime'];
        $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

        $allowed_positions = $election['target_position'] ?: 'All';
        $allowed_courses = $election['allowed_courses'] ?: 'All';
        $allowed_status = $election['allowed_status'] ?: 'All';
      ?>
      <div class="grid bg-white rounded-lg shadow-md p-6 mb-6 border-l-4 grid-cols-2 <?= $status === 'ongoing' ? 'border-green-600' : ($status === 'completed' ? 'border-gray-400' : 'border-yellow-400') ?>">
        <!-- Outer Container for Info and Logo -->
         <!-- Logo Container (Right) -->
          <div class="flex items-center justify-center">
            <div class="w-32 h-32 lg:w-40 lg:h-40 rounded-full overflow-hidden border-2 border-gray-200 bg-gray-100 flex items-center justify-center">
              <?php if (!empty($election['logo_path'])): ?>
                <img src="<?= htmlspecialchars($election['logo_path']) ?>" 
                     alt="Election Logo" 
                     class="min-w-full min-h-full object-cover">
              <?php else: ?>
                <span class="text-sm text-gray-500">Logo</span>
              <?php endif; ?>
            </div>
          </div>
        <div class="flex flex-col lg:flex-row gap-6">
          <!-- Info Container (Left) -->
          <div class="flex-1 flex flex-col justify-between">
            <div>
              <h2 class="text-lg font-bold text-[var(--cvsu-green-dark)] mb-2 truncate"><?= htmlspecialchars($election['title']) ?></h2>
              <div class="space-y-0.5 text-xs leading-tight">
                <p><strong class="text-gray-700">Start:</strong> <?= date('M d, Y h:i A', strtotime($election['start_datetime'])) ?></p>
                <p><strong class="text-gray-700">End:</strong> <?= date('M d, Y h:i A', strtotime($election['end_datetime'])) ?></p>
                <p><strong class="text-gray-700">Status:</strong> <?= ucfirst($status) ?></p>
                <p><strong class="text-gray-700">Partial Results:</strong> <?= $election['realtime_results'] ? 'Yes' : 'No' ?></p>
                <p><strong class="text-gray-700">Allowed Voters:</strong> <?= htmlspecialchars($allowed_positions) ?></p>
                <?php
                $positions = explode(',', $election['target_position']);
                if (in_array('non-academic', $positions)) {
                  echo '<p class="text-xs text-gray-700"><strong>Allowed Department:</strong> ' . htmlspecialchars($election['allowed_departments'] ?: 'All') . '</p>';
                } elseif (in_array('faculty', $positions) || in_array('student', $positions)) {
                  echo '<p class="text-xs text-gray-700"><strong>Allowed Colleges:</strong> ' . htmlspecialchars($election['allowed_colleges'] ?: 'All') . '</p>';
                }
                ?>
                <?php if (strpos($election['target_position'], 'student') !== false): ?>
                  <p class="text-xs text-gray-700"><strong>Allowed Courses:</strong> 
                    <?= !empty($election['allowed_courses']) ? htmlspecialchars($election['allowed_courses']) : 'All' ?>
                  </p>
                <?php endif; ?>
                <?php if (strpos($election['target_position'], 'faculty') !== false): ?>
                  <p class="text-xs text-gray-700"><strong>Allowed Status:</strong> 
                    <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
                  </p>
                <?php endif; ?>
                <?php if (strpos($election['target_position'], 'coop') !== false): ?>
                  <p class="text-xs text-gray-700"><strong>Allowed Status:</strong> 
                    <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
                  </p>
                <?php endif; ?>
                <?php if (strpos($election['target_position'], 'non-academic') !== false): ?>
                  <p class="text-xs text-gray-700"><strong>Allowed Status:</strong> 
                    <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
                  </p>
                <?php endif; ?>
              </div>
            </div>
            <!-- Action Buttons (Bottom of Info, left side) -->
            <div class="pt-4">
              <div class="flex gap-3">
                <button onclick='openUpdateModal(<?= json_encode($election) ?>)' 
                        class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white py-2 rounded font-semibold transition">
                  Update
                </button>
                <form action="delete_election.php" method="POST" onsubmit="return confirm('Are you sure?');" class="flex-1">
                  <input type="hidden" name="election_id" value="<?= $election['election_id'] ?>">
                  <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold transition">
                    Delete
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

  <?php include 'footer.php'; ?>
</main>

<!-- Modal -->
<div id="modal" class="fixed inset-0 hidden z-50 flex items-center justify-center modal-backdrop">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-8 relative max-h-[90vh] overflow-y-auto">
    <button id="closeModalBtn" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6">Create Election</h2>

    <form id="createElectionForm" enctype="multipart/form-data">

      <!-- Election Name -->
      <div>
        <label class="block mb-2 font-semibold">Election Name *</label>
        <input type="text" name="election_name" required class="w-full p-2 border rounded" />
      </div>

      <!-- Election Logo -->
      <div>
        <label class="block mb-2 font-semibold">Election Logo</label>
        <input type="file" name="election_logo" accept="image/*" class="w-full p-2 border rounded">
        <p class="text-xs text-gray-500">Upload a JPG or PNG image (max 2MB).</p>
      </div>
      
      <!-- Description -->
      <div>
        <label class="block mb-2 font-semibold">Description</label>
        <textarea name="description" class="w-full p-2 border rounded"></textarea>
      </div>

      <!-- Start & End DateTime -->
      <div>
        <label class="block mb-2 font-semibold">Start and End Date *</label>
        <div class="flex gap-2">
          <input type="datetime-local" name="start_datetime" required 
                 class="w-1/2 p-2 border rounded"
                 min="<?= $now ?>" max="2100-12-31T23:59">
          <input type="datetime-local" name="end_datetime" required 
                 class="w-1/2 p-2 border rounded"
                 min="<?= $now ?>" max="2100-12-31T23:59">
        </div>
      </div>

      <!-- Target Voters -->
      <div>
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex gap-6">
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="student" required> Student</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="academic" required> Academic</label>
          <label class="flex items-center gap-1 text-sm"><input type="radio" name="target_voter" value="non_academic" required> Non-Academic</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="coop" required> COOP</label>
        </div>
      </div>

<!-- Replace the studentFields section with this: -->
<div id="studentFields" class="hidden space-y-4">
  <div>
    <label class="block mb-2 font-semibold">Allowed Colleges (Student)</label>
    <select name="allowed_colleges_student" id="studentCollegeSelect" class="w-full p-2 border rounded" onchange="loadCourses('student')">
      <option value="all">All Colleges</option>
      <?php
      $colleges = ['CAFENR', 'CAS', 'CEIT', 'CEMDS', 'CED', 'CSPEAR', 'CTHM', 'CVMBS', 'COM', 'GS-OLC', 'CON'];
      foreach ($colleges as $college) {
          echo "<option value='$college'>$college</option>";
      }
      ?>
    </select>
  </div>

  <div id="studentCoursesContainer" class="hidden">
    <label class="block mb-2 font-semibold">Allowed Courses (Student)</label>
    <div id="studentCoursesList" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
      <!-- Courses will be loaded here dynamically -->
    </div>
    <div class="mt-1">
      <button type="button" onclick="toggleAllCheckboxes('allowed_courses_student[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
    </div>
  </div>
</div>

<!-- Replace the academicFields section with this: -->
<div id="academicFields" class="hidden space-y-4">
  <div>
    <label class="block mb-2 font-semibold">Allowed Colleges (Academic)</label>
    <select name="allowed_colleges_academic" id="academicCollegeSelect" class="w-full p-2 border rounded" onchange="loadCourses('academic')">
      <option value="all">All Colleges</option>
      <?php
      foreach ($colleges as $college) {
          echo "<option value='$college'>$college</option>";
      }
      ?>
    </select>
  </div>

  <div>
    <label class="block mb-2 font-semibold">Allowed Status (Academic)</label>
    <div class="flex gap-6 text-sm border p-2 rounded">
      <label class="flex items-center"><input type="checkbox" name="allowed_status_academic[]" value="Regular" class="mr-1">Regular</label>
      <label class="flex items-center"><input type="checkbox" name="allowed_status_academic[]" value="Part-time" class="mr-1">Part-time</label>
      <label class="flex items-center"><input type="checkbox" name="allowed_status_academic[]" value="Contractual" class="mr-1">Contractual</label>
    </div>
    <div class="mt-1">
      <button type="button" onclick="toggleAllCheckboxes('allowed_status_academic[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
    </div>
  </div>
</div>

<!-- Replace the nonAcademicFields section with this: -->
<div id="nonAcademicFields" class="hidden space-y-4">
  <div>
    <label class="block mb-2 font-semibold">Allowed Departments (Non-Academic)</label>
    <select name="allowed_departments_nonacad" class="w-full p-2 border rounded">
      <option value="all">All Departments</option>
      <?php
      $departments = [
        'NAEA',      // Non-Academic Employees Association
        'ADMIN',     // Administration
        'FINANCE',   // Finance
        'HR',        // Human Resources
        'IT',        // Information Technology
        'MAINTENANCE', // Maintenance
        'SECURITY',  // Security
        'LIBRARY',   // Library
        'NAES',      // Non-Academic Employee Services
        'NAEM',      // Non-Academic Employee Management
        'NAEH',      // Non-Academic Employee Health
        'NAEIT'      // Non-Academic Employee IT
      ];
      foreach ($departments as $dept) {
          echo "<option value='$dept'>$dept</option>";
      }
      ?>
    </select>
  </div>

  <div>
    <label class="block mb-2 font-semibold">Allowed Status (Non-Academic)</label>
    <div class="flex gap-6 text-sm border p-2 rounded">
      <label class="flex items-center"><input type="checkbox" name="allowed_status_nonacad[]" value="Regular" class="mr-1">Regular</label>
      <label class="flex items-center"><input type="checkbox" name="allowed_status_nonacad[]" value="Part-time" class="mr-1">Part-time</label>
      <label class="flex items-center"><input type="checkbox" name="allowed_status_nonacad[]" value="Contractual" class="mr-1">Contractual</label>
    </div>
    <div class="mt-1">
      <button type="button" onclick="toggleAllCheckboxes('allowed_status_nonacad[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
    </div>
  </div>
</div>

<!-- Replace the coopFields section with this: -->
<div id="coopFields" class="hidden space-y-4">
  <div>
    <label class="block mb-2 font-semibold">Allowed Status (COOP - MIGS)</label>
    <div class="flex gap-6 text-sm border p-2 rounded">
      <label class="flex items-center"><input type="checkbox" name="allowed_status_coop[]" value="MIGS" class="mr-1">MIGS</label>
    </div>
    <div class="mt-1">
      <button type="button" onclick="toggleAllCheckboxes('allowed_status_coop[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
    </div>
    <p class="text-xs text-gray-500 mt-1">Note: MIGS status is assigned to COOP members in user management.</p>
  </div>
</div>

<!-- Assign Admin -->
<div class="mb-4">
  <label class="block font-semibold mb-1">Assign Admin *</label>
  <select name="assigned_admin_id" required class="w-full p-2 border rounded">
    <option value="">-- Select Admin --</option>
    <?php
    // ----- College Admins -----
    $collegeScopes = "('CAFENR','CEIT','CAS','CVMBS','CED','CEMDS','CSPEAR','CCJ','CON','CTHM','COM','GS-OLC')";
    $stmt = $pdo->query("SELECT user_id, first_name, last_name, assigned_scope FROM users WHERE role='admin' AND assigned_scope IN $collegeScopes");
    $collegeAdmins = $stmt->fetchAll();
    if ($collegeAdmins) {
        echo "<optgroup label='College Admins'>";
        foreach ($collegeAdmins as $row) {
            echo "<option value='{$row['user_id']}'>{$row['first_name']} {$row['last_name']} ({$row['assigned_scope']})</option>";
        }
        echo "</optgroup>";
    }

    // ----- Other Sectors -----
    $otherScopes = "('FACULTY_ASSOCIATION','COOP','NON_ACADEMIC')";
    $stmt = $pdo->query("SELECT user_id, first_name, last_name, assigned_scope FROM users WHERE role='admin' AND assigned_scope IN $otherScopes");
    $otherAdmins = $stmt->fetchAll();
    if ($otherAdmins) {
        echo "<optgroup label='Other Sectors'>";
        foreach ($otherAdmins as $row) {
            echo "<option value='{$row['user_id']}'>{$row['first_name']} {$row['last_name']} ({$row['assigned_scope']})</option>";
        }
        echo "</optgroup>";
    }

    // ----- CSG Admins -----
    $stmt = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role='admin' AND assigned_scope = 'CSG_ADMIN'");
    $csgAdmins = $stmt->fetchAll();
    if ($csgAdmins) {
        echo "<optgroup label='CSG Admins'>";
        foreach ($csgAdmins as $row) {
            echo "<option value='{$row['user_id']}'>{$row['first_name']} {$row['last_name']}</option>";
        }
        echo "</optgroup>";
    }
    ?>
  </select>
</div>

<!-- Buttons -->
<div class="flex justify-end gap-3 mt-6">
  <!-- Clear Button -->
  <button type="button" id="clearFormBtn" 
          class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 transition">
    Clear
  </button>

  <!-- Create Election Button -->
  <button type="submit" 
          class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
    Create Election
  </button>
</div>

    </form>
  </div>
</div>

<!-- Update Election Modal -->
<div id="updateModal" class="fixed inset-0 hidden z-50 flex items-center justify-center modal-backdrop">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-8 relative max-h-[90vh] overflow-y-auto">
    <button onclick="closeUpdateModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6">Update Election</h2>

    <form action="update_election.php" method="POST" enctype="multipart/form-data" class="space-y-6">
      <input type="hidden" name="election_id" id="update_election_id">

      <!-- Election Name -->
      <div>
        <label class="block mb-2 font-semibold">Election Name *</label>
        <input type="text" name="election_name" id="update_election_name" required class="w-full p-2 border rounded" />
      </div>

      <!-- Election Logo -->
      <div class="mb-4">
          <label class="block mb-2 font-semibold">Election Logo</label>
          <input type="file" name="update_logo" id="update_logo" class="w-full p-2 border rounded" accept="image/*">
          <?php if (!empty($election['logo_path'])): ?>
          <?php endif; ?>
      </div>

      <!-- Description -->
      <div>
        <label class="block mb-2 font-semibold">Description</label>
        <textarea name="description" id="update_description" class="w-full p-2 border rounded"></textarea>
      </div>

      <!-- Start & End Dates -->
      <div>
        <label class="block mb-2 font-semibold">Start and End Date *</label>
        <div class="flex gap-2">
          <input type="datetime-local" name="start_datetime" id="update_start_datetime" required class="w-1/2 p-2 border rounded">
          <input type="datetime-local" name="end_datetime" id="update_end_datetime" required class="w-1/2 p-2 border rounded">
        </div>
      </div>

      <!-- Target Voters -->
      <div>
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex gap-6">
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="student" id="target_student" required> Student
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="faculty" id="target_faculty" required> Faculty
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="non_academic" id="target_non_academic" required> Non-Academic
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="coop" id="target_coop" required> COOP
          </label>
        </div>
      </div>

      <!-- Student Fields -->
      <div id="update_studentFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Colleges (Student)</label>
          <select name="allowed_colleges" id="update_allowed_colleges" class="w-full p-2 border rounded" onchange="loadCourses('update')">
            <option value="all">All Colleges</option>
            <?php
            $colleges = ['CAFENR', 'CAS', 'CEIT', 'CEMDS', 'CED', 'CSPEAR', 'CTHM', 'CVMBS', 'COM', 'GS-OLC', 'CON'];
            foreach ($colleges as $college) {
                echo "<option value='$college'>$college</option>";
            }
            ?>
          </select>
        </div>

        <div id="update_studentCoursesContainer" class="hidden">
          <label class="block mb-2 font-semibold">Allowed Courses (Student)</label>
          <div id="update_studentCoursesList" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
            <!-- Courses will be loaded here dynamically -->
          </div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('allowed_courses_student[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
          </div>
        </div>
      </div>

      <!-- Faculty Fields -->
      <div id="update_facultyFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Colleges (Faculty)</label>
          <select name="allowed_colleges_faculty" id="update_allowed_colleges_faculty" class="w-full p-2 border rounded" onchange="loadCourses('update_faculty')">
            <option value="all">All Colleges</option>
            <?php foreach ($colleges as $college) { echo "<option value='$college'>$college</option>"; } ?>
          </select>
        </div>
        
        <div>
          <label class="block mb-2 font-semibold">Allowed Status (Faculty)</label>
          <div class="flex gap-6 text-sm border p-2 rounded">
            <label class="flex items-center"><input type="checkbox" name="allowed_status_faculty[]" value="Regular" class="mr-1">Regular</label>
            <label class="flex items-center"><input type="checkbox" name="allowed_status_faculty[]" value="Part-time" class="mr-1">Part-time</label>
            <label class="flex items-center"><input type="checkbox" name="allowed_status_faculty[]" value="Contractual" class="mr-1">Contractual</label>
          </div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('allowed_status_faculty[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
          </div>
        </div>
      </div>

      <!-- Non-Academic Fields -->
      <div id="update_nonAcademicFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Departments (Non-Academic)</label>
          <select name="allowed_departments_nonacad" id="update_allowed_departments_nonacad" class="w-full p-2 border rounded">
            <option value="all">All Departments</option>
            <?php
            $departments = ['NAEA', 'ADMIN', 'FINANCE', 'HR', 'IT', 'MAINTENANCE', 'SECURITY', 'LIBRARY'];
            foreach ($departments as $dept) {
                echo "<option value='$dept'>$dept</option>";
            }
            ?>
          </select>
        </div>

        <div>
          <label class="block mb-2 font-semibold">Allowed Status (Non-Academic)</label>
          <div class="flex gap-6 text-sm border p-2 rounded">
            <label class="flex items-center"><input type="checkbox" name="allowed_status_nonacad[]" value="Regular" class="mr-1">Regular</label>
            <label class="flex items-center"><input type="checkbox" name="allowed_status_nonacad[]" value="Part-time" class="mr-1">Part-time</label>
            <label class="flex items-center"><input type="checkbox" name="allowed_status_nonacad[]" value="Contractual" class="mr-1">Contractual</label>
          </div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('allowed_status_nonacad[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
          </div>
        </div>
      </div>

      <!-- COOP Fields -->
      <div id="update_coopFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Status (COOP - MIGS)</label>
          <div class="flex gap-6 text-sm border p-2 rounded">
            <label class="flex items-center"><input type="checkbox" name="allowed_status_coop[]" value="MIGS" class="mr-1">MIGS</label>
          </div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('allowed_status_coop[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
          </div>
        </div>
      </div>

<!-- Assign Admin -->
<div class="mb-4">
    <label class="block font-semibold mb-1">Assign Admin *</label>
    <select name="assigned_admin_id" id="update_assigned_admin_id" required class="w-full p-2 border rounded">
        <option value="">-- Select Admin --</option>
        <?php
        // make sure we have the assigned admin id from election
        $assignedAdminId = $election['assigned_admin_id'] ?? null;

        // ----- College Admins -----
        $collegeScopes = "('CAFENR','CEIT','CAS','CVMBS','CED','CEMDS','CSPEAR','CCJ','CON','CTHM','COM','GS-OLC')";
        $stmt = $pdo->query("SELECT user_id, first_name, last_name, assigned_scope FROM users WHERE role='admin' AND assigned_scope IN $collegeScopes");
        $collegeAdmins = $stmt->fetchAll();
        if ($collegeAdmins) {
            echo "<optgroup label='College Admins'>";
            foreach ($collegeAdmins as $row) {
                $selected = ($row['user_id'] == $assignedAdminId) ? 'selected' : '';
                echo "<option value='{$row['user_id']}' $selected>{$row['first_name']} {$row['last_name']} ({$row['assigned_scope']})</option>";
            }
            echo "</optgroup>";
        }

        // ----- Other Sectors -----
        $otherScopes = "('FACULTY_ASSOCIATION','COOP','NON_ACADEMIC')";
        $stmt = $pdo->query("SELECT user_id, first_name, last_name, assigned_scope FROM users WHERE role='admin' AND assigned_scope IN $otherScopes");
        $otherAdmins = $stmt->fetchAll();
        if ($otherAdmins) {
            echo "<optgroup label='Other Sectors'>";
            foreach ($otherAdmins as $row) {
                $selected = ($row['user_id'] == $assignedAdminId) ? 'selected' : '';
                echo "<option value='{$row['user_id']}' $selected>{$row['first_name']} {$row['last_name']} ({$row['assigned_scope']})</option>";
            }
            echo "</optgroup>";
        }

        // ----- CSG Admins -----
        $stmt = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role='admin' AND assigned_scope = 'CSG_ADMIN'");
        $csgAdmins = $stmt->fetchAll();
        if ($csgAdmins) {
            echo "<optgroup label='CSG Admins'>";
            foreach ($csgAdmins as $row) {
                $selected = ($row['user_id'] == $assignedAdminId) ? 'selected' : '';
                echo "<option value='{$row['user_id']}' $selected>{$row['first_name']} {$row['last_name']}</option>";
            }
            echo "</optgroup>";
        }
        ?>
    </select>
</div>

<!-- Buttons -->
<div class="flex justify-end gap-3 mt-6">
  <!-- Clear Button -->
  <button type="button" id="clearUpdateFormBtn" 
          class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 transition">
    Clear
  </button>

  <!-- Update Button -->
  <button type="submit" 
          class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
    Update Election
  </button>
</div>

<script src="create_election.js"></script>
<script src="update_election.js"></script>
<script src="success.js"></script>
<script src="clear_button.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const navLinks = sidebar.querySelectorAll('a');
  const hamburgerIcon = document.getElementById('hamburgerIcon');
  const closeIcon = document.getElementById('closeIcon');

  function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    setTimeout(() => overlay.classList.add('opacity-100'), 10);
    document.body.style.overflow = 'hidden';
    hamburgerIcon.classList.add('hidden');
    closeIcon.classList.remove('hidden');
  }

  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    overlay.classList.remove('opacity-100');
    document.body.style.overflow = '';
    hamburgerIcon.classList.remove('hidden');
    closeIcon.classList.add('hidden');
  }

  toggleBtn.addEventListener('click', function () {
    if (sidebar.classList.contains('-translate-x-full')) openSidebar();
    else closeSidebar();
  });

  overlay.addEventListener('click', closeSidebar);

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
      hamburgerIcon.classList.remove('hidden');
      closeIcon.classList.add('hidden');
    } else {
      sidebar.classList.add('-translate-x-full');
      hamburgerIcon.classList.remove('hidden');
      closeIcon.classList.add('hidden');
    }
  }

  window.addEventListener('resize', handleResize);
  handleResize();
});
</script>
</body>
</html>