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
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Update election statuses based on time ---
$now = date('Y-m-d H:i:s');
$pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
$pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
$pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");

// --- Filter elections by status ---
$filter_status = $_GET['status'] ?? 'all';
if ($filter_status === 'all') {
    $stmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
} else {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE status = :status ORDER BY start_datetime DESC");
    $stmt->execute(['status' => $filter_status]);
}
$elections = $stmt->fetchAll();

// For dropdowns (create/update modals)
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
    'Others'                => 'Others',
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

// Format current datetime for min attribute
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
  <!-- Header -->
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
              $displayVoters = 'Others (custom uploaded voters)';
          } elseif ($tp_l === 'coop') {
              // Legacy COOP – you can treat this the same as Others now
              $displayVoters = 'Others (COOP – legacy)';
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
                  <p class="text-xs text-gray-700">
                    <strong>Allowed Courses:</strong> 
                    <?= !empty($election['allowed_courses']) ? htmlspecialchars($election['allowed_courses']) : 'All' ?>
                  </p>
                <?php endif; ?>

                <?php if (strpos($election['target_position'], 'faculty') !== false): ?>
                  <?php if (!empty($election['allowed_departments']) && strtolower($election['allowed_departments']) !== 'all'): ?>
                    <p class="text-xs text-gray-700">
                      <strong>Allowed Departments:</strong> 
                      <?= htmlspecialchars($election['allowed_departments']) ?>
                    </p>
                  <?php endif; ?>
                  <p class="text-xs text-gray-700">
                    <strong>Allowed Status:</strong> 
                    <?= !empty($election['allowed_status']) ? htmlspecialchars($election['allowed_status']) : 'All' ?>
                  </p>
                <?php endif; ?>

                <?php if (strpos($election['target_position'], 'non-academic') !== false): ?>
                  <p class="text-xs text-gray-700">
                    <strong>Allowed Status:</strong> 
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

      <!-- Target Voters (CREATE) -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex flex-wrap gap-4">
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="student" required> Student
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="academic" required> Academic (Faculty)
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="non_academic" required> Non-Academic Employees
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="others" required> Others
          </label>
        </div>

        <!-- Note for Others: shown only when "Others" is selected -->
        <p id="create_othersNote" class="text-xs text-gray-500 mt-1 hidden">
          <strong>Others:</strong> Special elections that are not tied to colleges or departments
          (e.g. COOP, Alumni, Retired). Voters are defined entirely by the list you upload
          for the assigned admin.
        </p>
      </div>

      <!-- Student Fields (CREATE) -->
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

      <!-- Academic (Faculty) Fields (CREATE) -->
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

      <!-- Non-Academic Fields (CREATE) -->
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

      <!-- Assign Admin (CREATE) -->
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
                if (!empty($row['admin_title']))      $pieces[] = $row['admin_title'];
                if (!empty($row['assigned_scope']))   $pieces[] = $row['assigned_scope'];
                if (!empty($row['assigned_scope_1'])) $pieces[] = $row['assigned_scope_1'];
                $tail = $pieces ? ' - '.implode(' | ', $pieces) : '';
              ?>
                <option value="<?= (int)$row['user_id'] ?>">
                  <?= htmlspecialchars($row['first_name'].' '.$row['last_name'].$tail) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons (CREATE) -->
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

      <!-- Target Voters (UPDATE) -->
      <div class="mb-4">
        <label class="block mb-2 font-semibold">Target Voters *</label>
        <div class="flex flex-wrap gap-4">
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="student" id="update_target_student" required> Student
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="faculty" id="update_target_faculty" required> Faculty
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="non_academic" id="update_target_non_academic" required> Non-Academic
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="target_voter" value="others" id="update_target_others" required> Others
          </label>
        </div>

        <!-- (Optional) Note for Others – if you want it in update too -->
        <p id="update_othersNote" class="text-xs text-gray-500 mt-1 hidden">
          <strong>Others:</strong> Special elections that are not tied to colleges or departments
          (e.g. COOP, Alumni, Retired). Voters are defined entirely by the list you upload
          for the assigned admin.
        </p>
      </div>

      <!-- Student Fields (UPDATE) -->
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

      <!-- Faculty Fields (UPDATE) -->
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

      <!-- Non-Academic Fields (UPDATE) -->
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

      <!-- Assign Admin (UPDATE) -->
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
                if (!empty($row['admin_title']))      $pieces[] = $row['admin_title'];
                if (!empty($row['assigned_scope']))   $pieces[] = $row['assigned_scope'];
                if (!empty($row['assigned_scope_1'])) $pieces[] = $row['assigned_scope_1'];
                $tail = $pieces ? ' - '.implode(' | ', $pieces) : '';
              ?>
                <option value="<?= (int)$row['user_id'] ?>">
                  <?= htmlspecialchars($row['first_name'].' '.$row['last_name'].$tail) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons (UPDATE) -->
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

<!-- External JS (no inline JS logic now) -->
<script src="create_election.js"></script>
<script src="update_election.js"></script>

</body>
</html>
