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

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.html');
    exit();
}

// Update election statuses based on current time
$now = date('Y-m-d H:i:s');
$pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
$pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
$pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");



// Fetch all elections ordered by start date descending
$stmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
$elections = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Elections - E-Voting System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .modal-backdrop {
      background: rgba(0, 0, 0, 0.5);
    }
  </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex">
<?php include 'sidebar.php'; ?>

<main class="flex-1 p-8 ml-64">
  <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
    <h1 class="text-3xl font-extrabold">Manage Elections</h1>
    <button id="openModalBtn" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">+ Create Election
</button>

  </header>

  <section class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
    <?php if (count($elections) === 0): ?>
      <p class="text-gray-700 col-span-full text-center mt-12">No elections found. Click "Create Election" to add one.</p>
    <?php else: ?>
      <?php foreach ($elections as $election): ?>
        <?php
          $start = $election['start_datetime'];
          $end = $election['end_datetime'];
          $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

          // Prepare display strings for allowed fields
          // Handle allowed_colleges (comma separated or JSON)
          $allowed_colleges = $election['allowed_colleges'] ?: 'All';

          // For allowed_positions (target_position) - it might be stored as JSON or comma separated
          $allowed_positions_raw = $election['target_position'] ?? '';
          if (empty($allowed_positions_raw)) {
              $allowed_positions = 'All';
          } else {
              $positions = json_decode($allowed_positions_raw, true);
              if (json_last_error() !== JSON_ERROR_NONE) {
                  $positions = array_map('trim', explode(',', $allowed_positions_raw));
              }
              $allowed_positions = implode(', ', array_map('ucfirst', $positions));
          }
        
          // Allowed courses and status, similar handling
          $allowed_courses = $election['allowed_courses'] ?: 'All';
          $allowed_status = $election['allowed_status'] ?: 'All';
          
        ?>

<div class="bg-white rounded-lg shadow p-6 border-l-8 <?= $status === 'ongoing' ? 'border-blue-500' : 'border-gray-300' ?>">
    <h2 class="text-xl font-bold text-[var(--cvsu-green-dark)] mb-2"><?= htmlspecialchars($election['title']) ?></h2>
    <p class="text-sm text-gray-500 mb-1"><strong>Start:</strong> <?= date('M d, Y h:i A', strtotime($start)) ?></p>
    <p class="text-sm text-gray-500 mb-3"><strong>End:</strong> <?= date('M d, Y h:i A', strtotime($end)) ?></p>
    <p class="text-sm font-semibold <?= $status === 'ongoing' ? 'text-blue-600' : 'text-gray-600' ?>">Status: <?= ucfirst($status) ?></p>
    <p class="text-sm text-gray-700 mt-2"><strong>Partial Results:</strong> <?= $election['realtime_results'] ? 'Yes' : 'No' ?></p>
    <p class="text-sm text-gray-700 mt-2"><strong>Allowed Voters:</strong> <?= htmlspecialchars($allowed_positions) ?></p>
    <?php
    $positions = explode(',', $election['target_position']);

    if (in_array('non-academic', $positions)) {
        echo '<p class="text-sm text-gray-700"><strong>Allowed Department:</strong> ' . htmlspecialchars($election['allowed_colleges'] ?: 'All') . '</p>';
    } elseif (in_array('faculty', $positions) || in_array('student', $positions)) {
        echo '<p class="text-sm text-gray-700"><strong>Allowed Colleges:</strong> ' . htmlspecialchars($election['allowed_colleges'] ?: 'All') . '</p>';
    }
    // Optional: You can skip printing anything for COOP-only elections
    ?>
    <?php if (strpos($election['target_position'], 'student') !== false): ?>
        <p class="text-sm text-gray-700"><strong>Allowed Courses:</strong> 
            <?= !empty($election['allowed_courses']) ? htmlspecialchars($election['allowed_courses']) : 'All' ?>
        </p>
    <?php endif; ?>
    
    <?php if (strpos($election['target_position'], 'faculty') !== false): ?>
      <p class="text-sm text-gray-700"><strong>Allowed Courses:</strong> 
            <?= !empty($election['allowed_courses']) ? htmlspecialchars($election['allowed_courses']) : 'All' ?>
        </p>
        <p class="text-sm text-gray-700"><strong>Allowed Status:</strong> 
            <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
        </p>
    <?php endif; ?>
    <?php if (strpos($election['target_position'], 'coop') !== false): ?>
        <p class="text-sm text-gray-700"><strong>Allowed Status:</strong> 
            <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
        </p>
    <?php endif; ?>
    <?php if (strpos($election['target_position'], 'non-academic') !== false): ?>
      <p class="text-sm text-gray-700"><strong>Allowed Status:</strong> 
        <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
      </p>
    <?php endif; ?>

    <div class="mt-6 flex gap-3">
    <button 
  type="button" 
  onclick='openUpdateModal(<?= json_encode($election) ?>)'
  class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white py-2 rounded font-semibold transition"
  data-election='<?= htmlspecialchars(json_encode($election), ENT_QUOTES, 'UTF-8') ?>'
>
  Update
</button>
                <form action="delete_election.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this election?');" class="flex-1">
                    <input type="hidden" name="election_id" value="<?= htmlspecialchars($election['election_id']) ?>">
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold transition">Delete</button>
                </form>
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

    <form action="create_election.php" method="POST" class="space-y-6">

      <!-- Election Name -->
      <div>
        <label class="block mb-2 font-semibold">Election Name *</label>
        <input type="text" name="election_name" required class="w-full p-2 border rounded" />
      </div>

      <!-- Description -->
      <div>
        <label class="block mb-2 font-semibold">Description</label>
        <textarea name="description" class="w-full p-2 border rounded"></textarea>
      </div>

      <!-- Start & End Dates -->
      <div>
        <label class="block mb-2 font-semibold">Start and End Date *</label>
        <div class="flex gap-2">
          <input type="date" name="start_date" required class="w-1/2 p-2 border rounded">
          <input type="date" name="end_date" required class="w-1/2 p-2 border rounded">
        </div>
      </div>

      <!-- Target Voters -->
      <div>
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex gap-6">
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="student" required> Student</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="academic" required> Academic</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="non_academic" required> Non-Academic</label>
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

  <div id="academicCoursesContainer" class="hidden">
    <label class="block mb-2 font-semibold">Allowed Courses (Academic)</label>
    <div id="academicCoursesList" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
      <!-- Courses will be loaded here dynamically -->
    </div>
    <div class="mt-1">
      <button type="button" onclick="toggleAllCheckboxes('allowed_courses_academic[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
    </div>
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

      <!-- Submit -->
      <div class="text-right">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Create Election</button>
      </div>

    </form>
  </div>
</div>

<!-- Update Election Modal -->
<div id="updateModal" class="fixed inset-0 hidden z-50 flex items-center justify-center modal-backdrop">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-8 relative max-h-[90vh] overflow-y-auto">
    <button onclick="closeUpdateModal()" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6">Update Election</h2>

    <form action="update_election.php" method="POST" class="space-y-6">
      <input type="hidden" name="election_id" id="update_election_id">

      <!-- Election Name -->
      <div>
        <label class="block mb-2 font-semibold">Election Name *</label>
        <input type="text" name="election_name" id="update_election_name" required class="w-full p-2 border rounded" />
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
            <button type="button" onclick="toggleAllCheckboxes('update_allowed_courses[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
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

        <div id="update_facultyCoursesContainer" class="hidden">
          <label class="block mb-2 font-semibold">Allowed Courses (Faculty)</label>
          <div id="update_facultyCoursesList" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm">
            <!-- Courses will be loaded here dynamically -->
          </div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('update_allowed_courses_faculty[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
          </div>
        </div>

        <div>
          <label class="block mb-2 font-semibold">Allowed Status (Faculty)</label>
          <div class="flex gap-6 text-sm border p-2 rounded">
            <label class="flex items-center"><input type="checkbox" name="allowed_status_faculty[]" value="Regular" class="mr-1">Regular</label>
            <label class="flex items-center"><input type="checkbox" name="allowed_status_faculty[]" value="Part-time" class="mr-1">Part-time</label>
            <label class="flex items-center"><input type="checkbox" name="allowed_status_faculty[]" value="Contractual" class="mr-1">Contractual</label>
          </div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('update_allowed_status_faculty[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
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
            <button type="button" onclick="toggleAllCheckboxes('update_allowed_status_nonacad[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
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
            <button type="button" onclick="toggleAllCheckboxes('update_allowed_status_coop[]')" class="text-xs text-blue-600 hover:text-blue-800">Select All</button>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="text-right">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Update Election</button>
      </div>
    </form>
  </div>
</div>


<script src="create_election.js"></script>
<script src="update_election.js"></script>
<style>
  .modal-backdrop {
    background-color: rgba(0,0,0,0.5);
  }
</style>
</body>
</html>
