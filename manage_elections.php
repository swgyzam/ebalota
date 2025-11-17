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

// For dropdowns
 $colleges = ['CAFENR', 'CAS', 'CEIT', 'CEMDS', 'CED', 'CSPEAR', 'CTHM', 'CVMBS', 'COM', 'GS-OLC', 'CON'];
 $nonAcadDepartments = [
    'NAEA','ADMIN','FINANCE','HR','IT','MAINTENANCE',
    'SECURITY','LIBRARY','NAES','NAEM','NAEH','NAEIT'
];

// Fetch all ACTIVE admins once for dropdowns
 $adminStmt = $pdo->query("
  SELECT user_id, first_name, last_name, assigned_scope, scope_category, assigned_scope_1, admin_title
  FROM users
  WHERE role = 'admin'
    AND admin_status = 'active'
  ORDER BY scope_category, user_id DESC
");
 $allAdmins = $adminStmt->fetchAll();

// Group admins by scope_category (for nicer optgroups)
 $scopeCategoryLabels = [
    'Academic-Student'      => 'Academic - Student',
    'Non-Academic-Student'  => 'Non-Academic - Student',
    'Academic-Faculty'      => 'Academic - Faculty',
    'Non-Academic-Employee' => 'Non-Academic - Employee',
    'Others-Default'        => 'Others - Default',
    'Others-COOP'           => 'Others - COOP',
    'Special-Scope'         => 'CSG Admin',
];
 $adminsByCategory = [];
foreach ($allAdmins as $a) {
    $cat = $a['scope_category'] ?? '';
    if (!isset($adminsByCategory[$cat])) {
        $adminsByCategory[$cat] = [];
    }
    $adminsByCategory[$cat][] = $a;
}

// Format current datetime for input fields
 $currentDateTime = date('Y-m-d\TH:i');
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
    .modal-backdrop { background-color: rgba(0,0,0,0.5); }
    .input-error {
      border-color: #ef4444 !important;
      background-color: #fef2f2 !important;
    }
  </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex">
<?php include 'super_admin_sidebar.php'; ?>

<main class="flex-1 p-8 ml-64">
  <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
    <h1 class="text-3xl font-extrabold">Manage Elections</h1>
    <button id="openModalBtn" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">
      + Create Election
    </button>
  </header>

  <!-- Filter Buttons -->
  <div class="flex justify-center mb-6">
    <div class="inline-flex bg-gray-100 rounded-full shadow-sm p-1">
      <?php
        $statuses = ['all' => 'All', 'upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed'];
        foreach ($statuses as $key => $label):
          $isActive = ($filter_status === $key);
          $btnClass = $isActive
            ? 'bg-green-600 text-white shadow-md'
            : 'bg-transparent text-gray-700 hover:bg-green-100';
      ?>
        <a href="?status=<?= htmlspecialchars($key) ?>"
           class="px-4 py-2 rounded-full font-medium transition-colors duration-200 <?= $btnClass ?>">
           <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Election Cards -->
  <section class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
    <?php if (count($elections) === 0): ?>
      <p class="text-gray-700 col-span-full text-center mt-12">No elections found.</p>
    <?php else: ?>
      <?php foreach ($elections as $election): ?>
        <?php
          $start  = $election['start_datetime'];
          $end    = $election['end_datetime'];
          $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

          $tp   = $election['target_position'] ?: 'All';
          $as   = $election['allowed_status'] ?: 'All';
          $tp_l = strtolower(trim($tp));
          $as_l = strtolower(trim($as));

          if ($tp_l === 'student') {
              $displayVoters = 'Students';
          } elseif ($tp_l === 'faculty') {
              $displayVoters = 'Academic (Faculty)';
          } elseif ($tp_l === 'non-academic') {
              $displayVoters = 'Non-Academic Employees';
          } elseif ($tp_l === 'others') {
              $displayVoters = 'Others (Employees: Faculty + Non-Academic)';
          } elseif ($tp_l === 'coop') {
              $displayVoters = ($as_l === 'migs')
                  ? 'COOP (MIGS Members)'
                  : 'COOP';
          } else {
              $displayVoters = 'All Voters';
          }

          $allowed_courses      = $election['allowed_courses'] ?: 'All';
          $allowed_status       = $election['allowed_status'] ?: 'All';
          $allowed_departments  = $election['allowed_departments'] ?? 'All';
        ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 border-l-4 <?= $status === 'ongoing' ? 'border-green-600' : ($status === 'completed' ? 'border-gray-400' : 'border-yellow-400') ?> flex flex-col">
          <div class="flex flex-col lg:flex-row flex-grow">
            <div class="flex-1 pr-4 flex flex-col max-w-[calc(100%-10rem)]">
              <h2 class="text-lg font-bold text-[var(--cvsu-green-dark)] mb-2 truncate">
                <?= htmlspecialchars($election['title']) ?>
              </h2>
              <div class="space-y-0.5 text-xs leading-tight">
                <p><strong class="text-gray-700">Start:</strong> <?= date('M d, Y h:i A', strtotime($election['start_datetime'])) ?></p>
                <p><strong class="text-gray-700">End:</strong> <?= date('M d, Y h:i A', strtotime($election['end_datetime'])) ?></p>
                <p><strong class="text-gray-700">Status:</strong> <?= ucfirst($status) ?></p>
                <p><strong class="text-gray-700">Partial Results:</strong> <?= $election['realtime_results'] ? 'Yes' : 'No' ?></p>
                <p><strong class="text-gray-700">Allowed Voters:</strong> <?= htmlspecialchars($displayVoters) ?></p>

                <?php
                $positions = explode(',', $election['target_position']);

                if (in_array('non-academic', $positions, true)) {
                    echo '<p class="text-xs text-gray-700"><strong>Allowed Department:</strong> ' . htmlspecialchars($election['allowed_departments'] ?: 'All') . '</p>';
                } elseif (in_array('faculty', $positions, true) || in_array('student', $positions, true)) {
                    echo '<p class="text-xs text-gray-700"><strong>Allowed Colleges:</strong> ' . htmlspecialchars($election['allowed_colleges'] ?: 'All') . '</p>';
                }
                ?>

                <?php if (strpos($election['target_position'], 'student') !== false): ?>
                  <p class="text-xs text-gray-700"><strong>Allowed Courses:</strong> 
                    <?= !empty($election['allowed_courses']) ? htmlspecialchars($election['allowed_courses']) : 'All' ?>
                  </p>
                <?php endif; ?>

                <?php if (strpos($election['target_position'], 'faculty') !== false): ?>
                  <?php if (!empty($election['allowed_departments']) && strtolower($election['allowed_departments']) !== 'all'): ?>
                    <p class="text-xs text-gray-700"><strong>Allowed Departments:</strong> 
                      <?= htmlspecialchars($election['allowed_departments']) ?>
                    </p>
                  <?php endif; ?>
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

            <!-- Logo -->
            <div class="w-40 h-40 flex-shrink-0 ml-4">
              <?php if (!empty($election['logo_path'])): ?>
                <div class="w-full h-full rounded-full overflow-hidden border-2 border-gray-200 flex items-center justify-center bg-gray-100">
                  <img src="<?= htmlspecialchars($election['logo_path']) ?>" 
                       alt="Election Logo" 
                       class="min-w-full min-h-full object-cover">
                </div>
              <?php else: ?>
                <div class="w-full h-full rounded-full bg-gray-200 border-2 border-gray-300 flex items-center justify-center">
                  <span class="text-sm text-gray-500">Logo</span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="mt-auto pt-4">
            <div class="flex gap-3">
              <button onclick='openUpdateModal(<?= json_encode($election) ?>)' 
                      class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white py-2 rounded font-semibold transition">
                Update
              </button>
              <form action="delete_election.php" method="POST" onsubmit="return confirm('Are you sure?');" class="flex-1">
                <input type="hidden" name="election_id" value="<?= (int)$election['election_id'] ?>">
                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded font-semibold transition">
                  Delete
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <?php include 'footer.php'; ?>
</main>

<!-- CREATE ELECTION MODAL -->
<div id="modal" class="fixed inset-0 hidden z-50 flex items-center justify-center modal-backdrop">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-8 relative max-h-[90vh] overflow-y-auto">
    <button id="closeModalBtn" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-4 text-[var(--cvsu-green-dark)]">Create Election</h2>

    <div id="createFormError" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm"></div>

    <form id="createElectionForm" enctype="multipart/form-data">
      <!-- Election Name -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Election Name *</label>
        <input type="text" name="election_name" required class="w-full p-2 border rounded" />
      </div>

      <!-- Election Logo -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Election Logo</label>
        <input type="file" name="election_logo" accept="image/*" class="w-full p-2 border rounded">
        <p class="text-xs text-gray-500">Upload a JPG or PNG image (max 2MB).</p>
      </div>
      
      <!-- Description -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Description</label>
        <textarea name="description" class="w-full p-2 border rounded"></textarea>
      </div>

      <!-- Start & End DateTime -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Start and End Date *</label>
        <div class="flex gap-2">
          <input type="datetime-local" name="start_datetime" id="create_start_datetime" required 
                 class="w-1/2 p-2 border rounded"
                 min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
          <input type="datetime-local" name="end_datetime" id="create_end_datetime" required 
                 class="w-1/2 p-2 border rounded"
                 min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
        </div>
        <p class="text-xs text-gray-500 mt-1">Start date must be in the future. End date must be after start date.</p>
      </div>

      <!-- Target Voters -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex flex-wrap gap-4">
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="student" required> Student</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="academic" required> Academic (Faculty)</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="non_academic" required> Non-Academic Employees</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="others" required> Others</label>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          <strong>Others:</strong> Employee elections (faculty + non-academic). Use the MIGS checkbox below to limit to COOP + MIGS voters.
        </p>
      </div>

      <!-- Student Fields -->
      <div id="studentFields" class="hidden space-y-4 mb-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Colleges (Student)</label>
          <select name="allowed_colleges_student" id="studentCollegeSelect" class="w-full p-2 border rounded">
            <option value="all">All Colleges</option>
            <?php foreach ($colleges as $college): ?>
              <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="studentCoursesContainer" class="hidden">
          <label class="block mb-2 font-semibold">Allowed Courses (Student)</label>
          <div id="studentCoursesList" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm"></div>
          <div class="mt-1">
            <button type="button" onclick="toggleAllCheckboxes('allowed_courses_student[]')" class="text-xs text-blue-600 hover:text-blue-800">
              Select All
            </button>
          </div>
        </div>
      </div>

      <!-- Academic Fields -->
      <div id="academicFields" class="hidden space-y-4 mb-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Colleges (Academic)</label>
          <select name="allowed_colleges_academic" id="academicCollegeSelect" class="w-full p-2 border rounded">
            <option value="all">All Colleges</option>
            <?php foreach ($colleges as $college): ?>
              <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Faculty Departments by College (single column) -->
        <div id="academicDepartmentsContainer" class="hidden">
          <label class="block mb-2 font-semibold">Allowed Departments (Faculty)</label>
          <div id="academicDepartmentsList" class="space-y-1 max-h-40 overflow-y-auto border p-2 rounded text-sm"></div>
          <div class="mt-1 flex items-center justify-between">
            <button type="button" onclick="toggleAllCheckboxes('allowed_departments_faculty[]')" class="text-xs text-blue-600 hover:text-blue-800">
              Select All Departments
            </button>
            <span class="text-[10px] text-gray-500">Leave all unchecked = All departments in this college</span>
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
            <button type="button" onclick="toggleAllCheckboxes('allowed_status_academic[]')" class="text-xs text-blue-600 hover:text-blue-800">
              Select All Status
            </button>
          </div>
        </div>
      </div>

      <!-- Non-Academic Fields -->
      <div id="nonAcademicFields" class="hidden space-y-4 mb-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Departments (Non-Academic)</label>
          <select name="allowed_departments_nonacad" class="w-full p-2 border rounded">
            <option value="all">All Departments</option>
            <?php foreach ($nonAcadDepartments as $dept): ?>
              <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
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
            <button type="button" onclick="toggleAllCheckboxes('allowed_status_nonacad[]')" class="text-xs text-blue-600 hover:text-blue-800">
              Select All
            </button>
          </div>
        </div>
      </div>

      <!-- Others / COOP – MIGS Fields -->
      <div id="coopFields" class="hidden space-y-4 mb-4">
        <div>
          <label class="block mb-2 font-semibold">MIGS Status (COOP)</label>
          <div class="flex gap-6 text-sm border p-2 rounded">
            <label class="flex items-center"><input type="checkbox" name="allowed_status_coop[]" value="MIGS" class="mr-1">MIGS</label>
          </div>
          <p class="text-xs text-gray-500 mt-1">If checked, this election is limited to COOP + MIGS voters.</p>
        </div>
      </div>

      <!-- Assign Admin -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Assign Admin *</label>
        <select name="assigned_admin_id" id="create_assigned_admin_id" required class="w-full p-2 border rounded">
          <option value="">-- Select Admin --</option>
          <?php foreach ($adminsByCategory as $cat => $list): 
            $label = $scopeCategoryLabels[$cat] ?? ($cat ?: 'Uncategorized');
          ?>
            <optgroup label="<?= htmlspecialchars($label) ?>">
              <?php foreach ($list as $row):
                $pieces = [];
                if (!empty($row['admin_title'])) $pieces[] = $row['admin_title'];
                if (!empty($row['assigned_scope'])) $pieces[] = $row['assigned_scope'];
                if (!empty($row['assigned_scope_1'])) $pieces[] = $row['assigned_scope_1'];
                $tail = $pieces ? ' - '.implode(' | ',$pieces) : '';
              ?>
                <option value="<?= (int)$row['user_id'] ?>">
                  <?= htmlspecialchars($row['first_name'].' '.$row['last_name'].$tail) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="clearFormBtn" 
                class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 transition">
          Clear
        </button>
        <button type="submit" 
                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
          Create Election
        </button>
      </div>
    </form>
  </div>
</div>

<!-- UPDATE ELECTION MODAL -->
<div id="updateModal" class="fixed inset-0 hidden z-50 flex items-center justify-center modal-backdrop">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-8 relative max-h-[90vh] overflow-y-auto">
    <button id="closeUpdateModalBtn" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-4 text-[var(--cvsu-green-dark)]">Update Election</h2>

    <div id="updateFormError" class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm"></div>

    <form id="updateElectionForm" action="update_election.php" method="POST" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="election_id" id="update_election_id">

      <!-- Election Name -->
      <div>
        <label class="block mb-2 font-semibold">Election Name *</label>
        <input type="text" name="election_name" id="update_election_name" required class="w-full p-2 border rounded" />
      </div>

      <!-- Election Logo -->
      <div>
        <label class="block mb-2 font-semibold">Election Logo</label>
        <input type="file" name="update_logo" id="update_logo" class="w-full p-2 border rounded" accept="image/*">
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
          <input type="datetime-local" name="start_datetime" id="update_start_datetime" required 
                 class="w-1/2 p-2 border rounded"
                 min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
          <input type="datetime-local" name="end_datetime" id="update_end_datetime" required 
                 class="w-1/2 p-2 border rounded"
                 min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
        </div>
        <p class="text-xs text-gray-500 mt-1">Start date must be in the future. End date must be after start date.</p>
      </div>

      <!-- Target Voters -->
      <div>
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex flex-wrap gap-4">
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="student" id="update_target_student" required> Student</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="faculty" id="update_target_faculty" required> Faculty</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="non_academic" id="update_target_non_academic" required> Non-Academic</label>
          <label class="flex items-center gap-1"><input type="radio" name="target_voter" value="coop" id="update_target_coop" required> COOP / Others</label>
        </div>
      </div>

      <!-- Student Fields -->
      <div id="update_studentFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Colleges (Student)</label>
          <select name="allowed_colleges" id="update_allowed_colleges" class="w-full p-2 border rounded">
            <option value="all">All Colleges</option>
            <?php foreach ($colleges as $college): ?>
              <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="update_studentCoursesContainer" class="hidden">
          <label class="block mb-2 font-semibold">Allowed Courses (Student)</label>
          <div id="update_studentCoursesList" class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm"></div>
          <div class="mt-1">
            <button type="button" id="update_selectAllStudentCourses" class="text-xs text-blue-600 hover:text-blue-800">
              Select All
            </button>
          </div>
        </div>
      </div>

      <!-- Faculty Fields -->
      <div id="update_facultyFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Allowed Colleges (Faculty)</label>
          <select name="allowed_colleges_faculty" id="update_allowed_colleges_faculty" class="w-full p-2 border rounded">
            <option value="all">All Colleges</option>
            <?php foreach ($colleges as $college): ?>
              <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="update_facultyDepartmentsContainer" class="hidden">
          <label class="block mb-2 font-semibold">Allowed Departments (Faculty)</label>
          <div id="update_facultyDepartmentsList" class="space-y-1 max-h-40 overflow-y-auto border p-2 rounded text-sm"></div>
          <div class="mt-1 flex items-center justify-between">
            <button type="button" id="update_selectAllFacultyDepartments" class="text-xs text-blue-600 hover:text-blue-800">
              Select All Departments
            </button>
            <span class="text-[10px] text-gray-500">Leave all unchecked = All departments in this college</span>
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
            <button type="button" id="update_selectAllFacultyStatus" class="text-xs text-blue-600 hover:text-blue-800">
              Select All Status
            </button>
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
            $departments2 = ['NAEA','ADMIN','FINANCE','HR','IT','MAINTENANCE','SECURITY','LIBRARY'];
            foreach ($departments2 as $dept): ?>
              <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
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
            <button type="button" id="update_selectAllNonAcadStatus" class="text-xs text-blue-600 hover:text-blue-800">
              Select All
            </button>
          </div>
        </div>
      </div>

      <!-- COOP Fields -->
      <div id="update_coopFields" class="hidden space-y-4">
        <div>
          <label class="block mb-2 font-semibold">MIGS Status (COOP)</label>
          <div class="flex gap-6 text-sm border p-2 rounded">
            <label class="flex items-center"><input type="checkbox" name="allowed_status_coop[]" value="MIGS" class="mr-1">MIGS</label>
          </div>
        </div>
      </div>

      <!-- Assign Admin -->
      <div class="mb-4">
        <label class="block font-semibold mb-1">Assign Admin *</label>
        <select name="assigned_admin_id" id="update_assigned_admin_id" required class="w-full p-2 border rounded">
          <option value="">-- Select Admin --</option>
          <?php foreach ($adminsByCategory as $cat => $list): 
            $label = $scopeCategoryLabels[$cat] ?? ($cat ?: 'Uncategorized');
          ?>
            <optgroup label="<?= htmlspecialchars($label) ?>">
              <?php foreach ($list as $row):
                $pieces = [];
                if (!empty($row['admin_title'])) $pieces[] = $row['admin_title'];
                if (!empty($row['assigned_scope'])) $pieces[] = $row['assigned_scope'];
                if (!empty($row['assigned_scope_1'])) $pieces[] = $row['assigned_scope_1'];
                $tail = $pieces ? ' - '.implode(' | ',$pieces) : '';
              ?>
                <option value="<?= (int)$row['user_id'] ?>">
                  <?= htmlspecialchars($row['first_name'].' '.$row['last_name'].$tail) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" id="clearUpdateFormBtn" 
                class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600 transition">
          Clear
        </button>
        <button type="submit" 
                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
          Update Election
        </button>
      </div>
    </form>
  </div>
</div>
<script>
// ===== Shared mappings =====
const collegeCourses = {
  'CAFENR': ['BSAgri','BSAB','BSES','BSFT','BSFor','BSABE','BAE','BSLDM'],
  'CAS':    ['BSBio','BSChem','BSMath','BSPhysics','BSPsych','BAELS','BAComm','BSStat'],
  'CEIT':   ['BSCS','BSIT','BSCpE','BSECE','BSCE','BSME','BSEE','BSIE','BSArch'],
  'CVMBS':  ['DVM','BSPV'],
  'CED':    ['BEEd','BSEd','BPE','BTLE'],
  'CEMDS':  ['BSBA','BSAcc','BSEco','BSEnt','BSOA'],
  'CSPEAR': ['BPE','BSESS'],
  'CCJ':    ['BSCrim'],
  'CON':    ['BSN'],
  'CTHM':   ['BSHM','BSTM'],
  'COM':    ['BLIS'],
  'GS-OLC': ['PhD','MS','MA']
};

// Academic departments by college (same as admin_functions.php)
const collegeDepartments = {
  "CAFENR": {
    "DAS": "Department of Animal Science",
    "DCS": "Department of Crop Science",
    "DFST": "Department of Food Science and Technology",
    "DFES": "Department of Forestry and Environmental Science",
    "DAED": "Department of Agricultural Economics and Development"
  },
  "CAS": {
    "DBS": "Department of Biological Sciences",
    "DPS": "Department of Physical Sciences",
    "DLMC": "Department of Languages and Mass Communication",
    "DSS": "Department of Social Sciences",
    "DMS": "Department of Mathematics and Statistics"
  },
  "CCJ": {
    "DCJ": "Department of Criminal Justice"
  },
  "CEMDS": {
    "DE": "Department of Economics",
    "DBM": "Department of Business and Management",
    "DDS": "Department of Development Studies"
  },
  "CED": {
    "DSE": "Department of Science Education",
    "DTLE": "Department of Technology and Livelihood Education",
    "DCI": "Department of Curriculum and Instruction",
    "DHK": "Department of Human Kinetics"
  },
  "CEIT": {
    "DCE": "Department of Civil Engineering",
    "DCEE": "Department of Computer and Electronics Engineering",
    "DIET": "Department of Industrial Engineering and Technology",
    "DMEE": "Department of Mechanical and Electronics Engineering",
    "DIT": "Department of Information Technology"
  },
  "CON": {
    "DN": "Department of Nursing"
  },
  "COM": {
    "DBMS": "Department of Basic Medical Sciences",
    "DCS": "Department of Clinical Sciences"
  },
  "CSPEAR": {
    "DPER": "Department of Physical Education and Recreation"
  },
  "CVMBS": {
    "DVM": "Department of Veterinary Medicine",
    "DBS": "Department of Biomedical Sciences"
  },
  "GS-OLC": {
    "DVGP": "Department of Various Graduate Programs"
  }
};

// ===== Validation Functions =====
function validateYearFormat(input) {
  const value = input.value;
  if (!value) return true;
  const yearMatch = value.match(/^(\d{4})-/);
  if (!yearMatch) return false;
  const year = parseInt(yearMatch[1]);
  return year >= 1900 && year <= 2100;
}

function validateDateRange(startInput, endInput) {
  if (!startInput.value || !endInput.value) return true;
  const startDate = new Date(startInput.value);
  const endDate = new Date(endInput.value);
  return endDate > startDate;
}

function validateDateInputs(startInput, endInput) {
  let isValid = true;
  if (!validateYearFormat(startInput)) {
    startInput.classList.add('input-error');
    isValid = false;
  } else {
    startInput.classList.remove('input-error');
  }
  if (!validateYearFormat(endInput)) {
    endInput.classList.add('input-error');
    isValid = false;
  } else {
    endInput.classList.remove('input-error');
  }
  if (isValid && !validateDateRange(startInput, endInput)) {
    endInput.classList.add('input-error');
    isValid = false;
  }
  return isValid;
}

function setupDateValidation(startInput, endInput) {
  function validateBoth() {
    validateDateInputs(startInput, endInput);
  }
  startInput.addEventListener('blur', validateBoth);
  endInput.addEventListener('blur', validateBoth);
  startInput.addEventListener('input', function() {
    if (validateYearFormat(this)) this.classList.remove('input-error');
  });
  endInput.addEventListener('input', function() {
    if (validateYearFormat(this)) this.classList.remove('input-error');
  });
}

function toggleAllCheckboxes(name) {
  const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
  if (!checkboxes.length) return;
  const allChecked = Array.from(checkboxes).every(cb => cb.checked);
  checkboxes.forEach(cb => cb.checked = !allChecked);
}

// Helper: build department checkboxes (single column)
function buildDeptCheckboxes(college, listEl, fieldName) {
  if (!listEl) return;
  const depts = collegeDepartments[college] || {};
  listEl.innerHTML = '';
  Object.keys(depts).forEach(code => {
    listEl.innerHTML += `
      <label class="flex items-center">
        <input type="checkbox" name="${fieldName}" value="${code}" class="mr-1">
        ${code} - ${depts[code]}
      </label>
    `;
  });
}

// Helper: build course checkboxes (3-column layout)
function buildCourseCheckboxes(college, listEl, fieldName) {
  if (!listEl) return;
  const courses = collegeCourses[college] || [];
  listEl.innerHTML = '';
  courses.forEach(course => {
    listEl.innerHTML += `
      <label class="flex items-center">
        <input type="checkbox" name="${fieldName}" value="${course}" class="mr-1">
        ${course}
      </label>
    `;
  });
}

// ===== PAGE JS =====
document.addEventListener('DOMContentLoaded', function() {

  // ===== CREATE MODAL =====
  const modal          = document.getElementById('modal');
  const openModalBtn   = document.getElementById('openModalBtn');
  const closeModalBtn  = document.getElementById('closeModalBtn');
  const createForm     = document.getElementById('createElectionForm');
  const createError    = document.getElementById('createFormError');
  const clearFormBtn   = document.getElementById('clearFormBtn');

  const studentFields     = document.getElementById('studentFields');
  const academicFields    = document.getElementById('academicFields');
  const nonAcademicFields = document.getElementById('nonAcademicFields');
  const coopFields        = document.getElementById('coopFields');

  const studentCollegeSelect      = document.getElementById('studentCollegeSelect');
  const studentCoursesContainer   = document.getElementById('studentCoursesContainer');
  const studentCoursesList        = document.getElementById('studentCoursesList');

  const academicCollegeSelect          = document.getElementById('academicCollegeSelect');
  const academicDepartmentsContainer   = document.getElementById('academicDepartmentsContainer');
  const academicDepartmentsList        = document.getElementById('academicDepartmentsList');

  function hideCreateFields() {
    studentFields && studentFields.classList.add('hidden');
    academicFields && academicFields.classList.add('hidden');
    nonAcademicFields && nonAcademicFields.classList.add('hidden');
    coopFields && coopFields.classList.add('hidden');
  }

  if (openModalBtn && modal) {
    openModalBtn.addEventListener('click', () => {
      createError && (createError.classList.add('hidden'), createError.textContent = '');
      modal.classList.remove('hidden');
    });
  }
  if (closeModalBtn && modal) {
    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    window.addEventListener('click', e => {
      if (e.target === modal) modal.classList.add('hidden');
    });
  }

  // Target voters (CREATE)
  document.querySelectorAll('#createElectionForm input[name="target_voter"]').forEach(radio => {
    radio.addEventListener('change', e => {
      hideCreateFields();
      const val = e.target.value;
      if (val === 'student') {
        studentFields && studentFields.classList.remove('hidden');
      } else if (val === 'academic') {
        academicFields && academicFields.classList.remove('hidden');
      } else if (val === 'non_academic') {
        nonAcademicFields && nonAcademicFields.classList.remove('hidden');
      } else if (val === 'others') {
        coopFields && coopFields.classList.remove('hidden');
      }
    });
  });

  // Load courses for CREATE student - UPDATED FOR 3-COLUMN LAYOUT
  if (studentCollegeSelect && studentCoursesContainer && studentCoursesList) {
    studentCollegeSelect.addEventListener('change', () => {
      const college = studentCollegeSelect.value;
      if (college === 'all') {
        studentCoursesContainer.classList.add('hidden');
        studentCoursesList.innerHTML = '';
        return;
      }
      buildCourseCheckboxes(college, studentCoursesList, 'allowed_courses_student[]');
      studentCoursesContainer.classList.remove('hidden');
    });
  }

  // Load departments for CREATE faculty
  if (academicCollegeSelect && academicDepartmentsContainer && academicDepartmentsList) {
    academicCollegeSelect.addEventListener('change', () => {
      const college = academicCollegeSelect.value;
      if (college === 'all') {
        academicDepartmentsContainer.classList.add('hidden');
        academicDepartmentsList.innerHTML = '';
        return;
      }
      buildDeptCheckboxes(college, academicDepartmentsList, 'allowed_departments_faculty[]');
      academicDepartmentsContainer.classList.remove('hidden');
    });
  }

  // Default MIGS checked for CREATE
  const coopCheckboxCreate = document.querySelector('#coopFields input[name="allowed_status_coop[]"]');
  if (coopCheckboxCreate) coopCheckboxCreate.checked = true;

  // Clear button (CREATE)
  if (clearFormBtn && createForm) {
    clearFormBtn.addEventListener('click', () => {
      createForm.reset();
      hideCreateFields();
      studentCoursesContainer && studentCoursesContainer.classList.add('hidden');
      studentCoursesList && (studentCoursesList.innerHTML = '');
      academicDepartmentsContainer && academicDepartmentsContainer.classList.add('hidden');
      academicDepartmentsList && (academicDepartmentsList.innerHTML = '');
      if (createError) {
        createError.classList.add('hidden');
        createError.textContent = '';
      }
      if (coopCheckboxCreate) coopCheckboxCreate.checked = true;
      document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    });
  }

  // Setup date validation for create form
  const createStartInput = document.getElementById('create_start_datetime');
  const createEndInput   = document.getElementById('create_end_datetime');
  if (createStartInput && createEndInput) {
    setupDateValidation(createStartInput, createEndInput);
  }

  // Client-side validation + AJAX submit for CREATE
  if (createForm && createError) {
    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      createError.classList.add('hidden');
      createError.textContent = '';

      const formData = new FormData(createForm);
      const name   = (formData.get('election_name') || '').trim();
      const start  = formData.get('start_datetime');
      const end    = formData.get('end_datetime');
      const target = formData.get('target_voter');
      const admin  = formData.get('assigned_admin_id');

      if (!name || !start || !end || !target || !admin) {
        createError.textContent = 'Please fill in all required fields.';
        createError.classList.remove('hidden');
        return;
      }

      const startDate = new Date(start);
      const endDate   = new Date(end);
      if (isNaN(startDate) || isNaN(endDate)) {
        createError.textContent = 'Invalid date/time format.';
        createError.classList.remove('hidden');
        return;
      }
      if (endDate <= startDate) {
        createError.textContent = 'End date/time must be after the start date/time.';
        createError.classList.remove('hidden');
        return;
      }

      try {
        const res  = await fetch('create_election.php', { method: 'POST', body: formData });
        const data = await res.json().catch(() => null);

        if (!data) {
          createError.textContent = 'Unexpected server response.';
          createError.classList.remove('hidden');
          return;
        }

        if (data.status === 'error') {
          createError.textContent = data.message || 'An error occurred while creating the election.';
          createError.classList.remove('hidden');
        } else {
          window.location.reload();
        }
      } catch (err) {
        console.error(err);
        createError.textContent = 'Network or server error. Please try again.';
        createError.classList.remove('hidden');
      }
    });
  }

  // ===== UPDATE MODAL =====
  const updateModal        = document.getElementById('updateModal');
  const closeUpdateBtn     = document.getElementById('closeUpdateModalBtn');
  const updateForm         = document.getElementById('updateElectionForm');
  const updateError        = document.getElementById('updateFormError');
  const clearUpdateFormBtn = document.getElementById('clearUpdateFormBtn');

  const updateStudentFields     = document.getElementById('update_studentFields');
  const updateFacultyFields     = document.getElementById('update_facultyFields');
  const updateNonAcademicFields = document.getElementById('update_nonAcademicFields');
  const updateCoopFields        = document.getElementById('update_coopFields');

  const updateStudentCollegeSelect    = document.getElementById('update_allowed_colleges');
  const updateStudentCoursesContainer = document.getElementById('update_studentCoursesContainer');
  const updateStudentCoursesList      = document.getElementById('update_studentCoursesList');

  const updateFacultyCollegeSelect        = document.getElementById('update_allowed_colleges_faculty');
  const updateFacultyDepartmentsContainer = document.getElementById('update_facultyDepartmentsContainer');
  const updateFacultyDepartmentsList      = document.getElementById('update_facultyDepartmentsList');

  const updateSelectAllStudentCourses = document.getElementById('update_selectAllStudentCourses');
  const updateSelectAllFacultyStatus  = document.getElementById('update_selectAllFacultyStatus');
  const updateSelectAllNonAcadStatus  = document.getElementById('update_selectAllNonAcadStatus');
  const updateSelectAllFacultyDepartments = document.getElementById('update_selectAllFacultyDepartments');

  function hideUpdateFields() {
    updateStudentFields && updateStudentFields.classList.add('hidden');
    updateFacultyFields && updateFacultyFields.classList.add('hidden');
    updateNonAcademicFields && updateNonAcademicFields.classList.add('hidden');
    updateCoopFields && updateCoopFields.classList.add('hidden');
  }

  if (closeUpdateBtn && updateModal) {
    closeUpdateBtn.addEventListener('click', () => updateModal.classList.add('hidden'));
    window.addEventListener('click', e => {
      if (e.target === updateModal) updateModal.classList.add('hidden');
    });
  }

  // Target voter (UPDATE)
  document.querySelectorAll('#updateElectionForm input[name="target_voter"]').forEach(radio => {
    radio.addEventListener('change', e => {
      hideUpdateFields();
      const v = e.target.value;
      if (v === 'student') {
        updateStudentFields && updateStudentFields.classList.remove('hidden');
      } else if (v === 'faculty') {
        updateFacultyFields && updateFacultyFields.classList.remove('hidden');
      } else if (v === 'non_academic') {
        updateNonAcademicFields && updateNonAcademicFields.classList.remove('hidden');
      } else if (v === 'coop') {
        updateCoopFields && updateCoopFields.classList.remove('hidden');
      }
    });
  });

  // Load courses for UPDATE student - UPDATED FOR 3-COLUMN LAYOUT
  if (updateStudentCollegeSelect && updateStudentCoursesContainer && updateStudentCoursesList) {
    updateStudentCollegeSelect.addEventListener('change', () => {
      const college = updateStudentCollegeSelect.value;
      if (college === 'all') {
        updateStudentCoursesContainer.classList.add('hidden');
        updateStudentCoursesList.innerHTML = '';
        return;
      }
      buildCourseCheckboxes(college, updateStudentCoursesList, 'allowed_courses_student[]');
      updateStudentCoursesContainer.classList.remove('hidden');
    });
  }
  
  // Load departments for UPDATE faculty
  if (updateFacultyCollegeSelect && updateFacultyDepartmentsContainer && updateFacultyDepartmentsList) {
    updateFacultyCollegeSelect.addEventListener('change', () => {
      const college = updateFacultyCollegeSelect.value;
      if (college === 'all') {
        updateFacultyDepartmentsContainer.classList.add('hidden');
        updateFacultyDepartmentsList.innerHTML = '';
        return;
      }
      buildDeptCheckboxes(college, updateFacultyDepartmentsList, 'allowed_departments_faculty[]');
      updateFacultyDepartmentsContainer.classList.remove('hidden');
    });
  }

  if (updateSelectAllStudentCourses) {
    updateSelectAllStudentCourses.addEventListener('click', () => toggleAllCheckboxes('allowed_courses_student[]'));
  }
  if (updateSelectAllFacultyStatus) {
    updateSelectAllFacultyStatus.addEventListener('click', () => toggleAllCheckboxes('allowed_status_faculty[]'));
  }
  if (updateSelectAllNonAcadStatus) {
    updateSelectAllNonAcadStatus.addEventListener('click', () => toggleAllCheckboxes('allowed_status_nonacad[]'));
  }
  if (updateSelectAllFacultyDepartments) {
    updateSelectAllFacultyDepartments.addEventListener('click', () => toggleAllCheckboxes('allowed_departments_faculty[]'));
  }

  // Default MIGS checked for UPDATE
  const coopCheckboxUpdate = document.querySelector('#update_coopFields input[name="allowed_status_coop[]"]');
  if (coopCheckboxUpdate) coopCheckboxUpdate.checked = true;

  // Clear button (UPDATE)
  if (clearUpdateFormBtn && updateForm) {
    clearUpdateFormBtn.addEventListener('click', () => {
      updateForm.reset();
      hideUpdateFields();
      updateStudentCoursesContainer && updateStudentCoursesContainer.classList.add('hidden');
      updateStudentCoursesList && (updateStudentCoursesList.innerHTML = '');
      updateFacultyDepartmentsContainer && updateFacultyDepartmentsContainer.classList.add('hidden');
      updateFacultyDepartmentsList && (updateFacultyDepartmentsList.innerHTML = '');
      if (updateError) {
        updateError.classList.add('hidden');
        updateError.textContent = '';
      }
      if (coopCheckboxUpdate) coopCheckboxUpdate.checked = true;
      document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    });
  }

  // Setup date validation for update form
  const updateStartInput = document.getElementById('update_start_datetime');
  const updateEndInput   = document.getElementById('update_end_datetime');
  if (updateStartInput && updateEndInput) {
    setupDateValidation(updateStartInput, updateEndInput);
  }

  // Client-side validation for UPDATE
  if (updateForm && updateError) {
    updateForm.addEventListener('submit', (e) => {
      updateError.classList.add('hidden');
      updateError.textContent = '';

      const name  = (document.getElementById('update_election_name')?.value || '').trim();
      const start = document.getElementById('update_start_datetime')?.value;
      const end   = document.getElementById('update_end_datetime')?.value;
      const admin = document.getElementById('update_assigned_admin_id')?.value;

      if (!name || !start || !end || !admin) {
        e.preventDefault();
        updateError.textContent = 'Please fill in all required fields.';
        updateError.classList.remove('hidden');
        return;
      }

      const startDate = new Date(start);
      const endDate   = new Date(end);
      if (isNaN(startDate) || isNaN(endDate)) {
        e.preventDefault();
        updateError.textContent = 'Invalid date/time format.';
        updateError.classList.remove('hidden');
        return;
      }

      if (endDate <= startDate) {
        e.preventDefault();
        updateError.textContent = 'End date/time must be after the start date/time.';
        updateError.classList.remove('hidden');
        return;
      }
    });
  }

  // ==== openUpdateModal (called from PHP onclick) ====
  window.openUpdateModal = function(election) {
    if (!updateModal || !updateForm) return;
    updateForm.reset();
    hideUpdateFields();
    updateStudentCoursesContainer && updateStudentCoursesContainer.classList.add('hidden');
    updateStudentCoursesList && (updateStudentCoursesList.innerHTML = '');
    updateFacultyDepartmentsContainer && updateFacultyDepartmentsContainer.classList.add('hidden');
    updateFacultyDepartmentsList && (updateFacultyDepartmentsList.innerHTML = '');
    if (updateError) {
      updateError.classList.add('hidden');
      updateError.textContent = '';
    }
    document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

    document.getElementById('update_election_id').value   = election.election_id;
    document.getElementById('update_election_name').value = election.title || '';
    document.getElementById('update_description').value   = election.description || '';

    const startDate = new Date(election.start_datetime);
    const endDate   = new Date(election.end_datetime);
    const fmt = d => {
      const yyyy = d.getFullYear();
      const mm   = String(d.getMonth() + 1).padStart(2,'0');
      const dd   = String(d.getDate()).padStart(2,'0');
      const hh   = String(d.getHours()).padStart(2,'0');
      const mi   = String(d.getMinutes()).padStart(2,'0');
      return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
    };
    document.getElementById('update_start_datetime').value = fmt(startDate);
    document.getElementById('update_end_datetime').value   = fmt(endDate);

    // Assign Admin
    const adminSelect = document.getElementById('update_assigned_admin_id');
    if (adminSelect) {
      if (election.assigned_admin_id) {
        const exists = Array.from(adminSelect.options).some(o => o.value == election.assigned_admin_id);
        if (exists) {
          adminSelect.value = election.assigned_admin_id;
        } else {
          const opt = document.createElement('option');
          opt.value = election.assigned_admin_id;
          opt.text  = 'Unknown Admin (ID: '+ election.assigned_admin_id +')';
          opt.selected = true;
          adminSelect.appendChild(opt);
        }
      } else {
        adminSelect.value = '';
      }
    }

    const pos = (election.target_position || '').toLowerCase();
    hideUpdateFields();

    // Student
    if (pos.includes('student')) {
      document.getElementById('update_target_student').checked = true;
      updateStudentFields && updateStudentFields.classList.remove('hidden');
      if (updateStudentCollegeSelect && election.allowed_colleges) {
        updateStudentCollegeSelect.value = election.allowed_colleges;
        if (election.allowed_colleges !== 'all' && election.allowed_colleges !== 'All') {
          const courses = (election.allowed_courses || '').split(',').map(c=>c.trim()).filter(Boolean);
          const cc = collegeCourses[election.allowed_colleges] || [];
          if (cc.length && updateStudentCoursesContainer && updateStudentCoursesList) {
            updateStudentCoursesList.innerHTML = '';
            cc.forEach(course => {
              const checked = courses.includes(course) ? 'checked' : '';
              updateStudentCoursesList.innerHTML += `
                <label class="flex items-center">
                  <input type="checkbox" name="allowed_courses_student[]" value="${course}" class="mr-1" ${checked}>
                  ${course}
                </label>
              `;
            });
            updateStudentCoursesContainer.classList.remove('hidden');
          }
        }
      }
    }
    // Faculty
    else if (pos.includes('faculty')) {
      document.getElementById('update_target_faculty').checked = true;
      updateFacultyFields && updateFacultyFields.classList.remove('hidden');

      const college = election.allowed_colleges || 'all';
      if (updateFacultyCollegeSelect) {
        updateFacultyCollegeSelect.value = college;
      }

      const deptStr = (election.allowed_departments || '').trim();
      if (college.toLowerCase() !== 'all') {
        buildDeptCheckboxes(college, updateFacultyDepartmentsList, 'allowed_departments_faculty[]');
        if (deptStr && deptStr.toLowerCase() !== 'all') {
          const selected = deptStr.split(',').map(d=>d.trim());
          updateFacultyDepartmentsList.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (selected.includes(cb.value)) cb.checked = true;
          });
        }
        updateFacultyDepartmentsContainer && updateFacultyDepartmentsContainer.classList.remove('hidden');
      } else {
        updateFacultyDepartmentsContainer && updateFacultyDepartmentsContainer.classList.add('hidden');
        updateFacultyDepartmentsList && (updateFacultyDepartmentsList.innerHTML = '');
      }

      if (election.allowed_status && election.allowed_status.toLowerCase() !== 'all') {
        const statuses = election.allowed_status.split(',').map(s=>s.trim());
        document.querySelectorAll('#update_facultyFields input[name="allowed_status_faculty[]"]').forEach(cb=>{
          cb.checked = statuses.includes(cb.value);
        });
      }
    }
    // Non-Academic
    else if (pos.includes('non-academic')) {
      document.getElementById('update_target_non_academic').checked = true;
      updateNonAcademicFields && updateNonAcademicFields.classList.remove('hidden');
      if (election.allowed_departments && election.allowed_departments !== 'All') {
        const depts = election.allowed_departments.split(',').map(d=>d.trim());
        document.getElementById('update_allowed_departments_nonacad').value = depts[0] || 'all';
      }
      if (election.allowed_status && election.allowed_status.toLowerCase() !== 'all') {
        const statuses = election.allowed_status.split(',').map(s=>s.trim());
        document.querySelectorAll('#update_nonAcademicFields input[name="allowed_status_nonacad[]"]').forEach(cb=>{
          cb.checked = statuses.includes(cb.value);
        });
      }
    }
    // COOP / Others
    else if (pos.includes('coop') || pos.includes('others')) {
      document.getElementById('update_target_coop').checked = true;
      updateCoopFields && updateCoopFields.classList.remove('hidden');
      if (coopCheckboxUpdate) {
        coopCheckboxUpdate.checked = (election.allowed_status || '').toUpperCase().includes('MIGS');
      }
    }

    updateModal && updateModal.classList.remove('hidden');
  };
});
</script>
</body>
</html>