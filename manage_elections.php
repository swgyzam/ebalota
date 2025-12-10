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

    .modal-backdrop {
      background-color: rgba(0,0,0,0.55);
    }

    .input-error {
      border-color: #ef4444 !important;
      background-color: #fef2f2 !important;
    }

    /* Modern CVSU modal styling */
    .e-modal-panel {
      width: 100%;
      max-width: 900px;
      max-height: 90vh;
      background: #ffffff;
      border-radius: 1rem;
      box-shadow: 0 20px 40px rgba(0,0,0,0.25);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .e-modal-header {
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, var(--cvsu-green-dark), var(--cvsu-green));
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .e-modal-header-left {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .e-modal-icon {
      width: 2.25rem;
      height: 2.25rem;
      border-radius: 9999px;
      background: rgba(255,255,255,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
    }

    .e-modal-title {
      font-size: 1.1rem;
      font-weight: 700;
    }

    .e-modal-subtitle {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.85);
    }

    .e-modal-close {
      font-size: 1.8rem;
      line-height: 1;
      color: rgba(255,255,255,0.85);
    }

    .e-modal-close:hover {
      color: #ffffff;
    }

    .e-modal-body {
      padding: 1.5rem;
      overflow-y: auto;
    }

    .e-modal-footer {
      padding: 0.75rem 1.5rem;
      border-top: 1px solid #e5e7eb;
      background: #ffffff;
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
    }

    .e-section-label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #6b7280;
      margin-bottom: 0.25rem;
    }

    .e-field-card {
      border-radius: 0.75rem;
      border: 1px solid #e5e7eb;
      background-color: #f9fafb;
      padding: 0.85rem 1rem;
    }

    .e-input {
      width: 100%;
      padding: 0.5rem 0.7rem;
      border-radius: 0.5rem;
      border: 1px solid #d1d5db;
      font-size: 0.875rem;
    }

    .e-input:focus {
      outline: none;
      border-color: var(--cvsu-green);
      box-shadow: 0 0 0 2px rgba(30,111,70,0.25);
    }

    .e-note {
      font-size: 0.7rem;
      color: #6b7280;
    }
  </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex">
<?php include 'super_admin_sidebar.php'; ?>
<?php
$role = $_SESSION['role'] ?? '';
if ($role === 'super_admin') {
    include 'super_admin_change_password_modal.php';
} else {
    include 'admin_change_password_modal.php';
}
?>

<main class="flex-1 p-8 ml-64">
  <!-- Header -->
  <header class="bg-[var(--cvsu-green-dark)] text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
    <h1 class="text-3xl font-extrabold">Manage Elections</h1>
    <button id="openModalBtn" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">
      + Create Election
    </button>
  </header>

  <!-- Flash messages -->
  <?php if (!empty($_SESSION['success_message'])): ?>
    <div class="mb-4 px-4 py-3 rounded bg-green-100 border border-green-300 text-green-800 text-sm">
      <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['error_message'])): ?>
    <div class="mb-4 px-4 py-3 rounded bg-red-100 border border-red-300 text-red-800 text-sm">
      <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>


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
  <div class="e-modal-panel">
    <!-- HEADER -->
    <div class="e-modal-header">
      <div class="e-modal-header-left">
        <div class="e-modal-icon">🗳️</div>
        <div>
          <div class="e-modal-title">Create Election</div>
          <div class="e-modal-subtitle">
            Fill in basic details, schedule, target voters and assigned admin.
          </div>
        </div>
      </div>
      <button id="closeModalBtn" class="e-modal-close">&times;</button>
    </div>

    <!-- BODY -->
    <div class="e-modal-body">
      <div id="createFormError"
           class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm"></div>

      <form id="createElectionForm" enctype="multipart/form-data" class="space-y-4">
        <!-- 1. NAME + LOGO (side by side) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Election Name -->
          <div class="md:col-span-2 e-field-card">
            <p class="e-section-label">Basic details</p>
            <label class="block text-sm font-semibold mb-1">Election Name *</label>
            <input type="text" name="election_name" required class="e-input" />
            <p class="e-note mt-1">Example: CEIT CSG Election 2025</p>
          </div>

          <!-- Election Logo + preview -->
          <div class="e-field-card">
            <p class="e-section-label">Logo</p>
            <label class="block text-sm font-semibold mb-1">Election Logo</label>
            <input type="file"
                   name="election_logo"
                   id="create_election_logo"
                   accept="image/*"
                   class="w-full text-sm border rounded-lg px-2 py-1.5">

            <div class="mt-2 w-20 h-20 rounded-full bg-gray-100 border border-gray-300 overflow-hidden flex items-center justify-center">
              <img id="create_logo_preview"
                   src=""
                   alt="Logo preview"
                   class="hidden w-full h-full object-cover">
              <span id="create_logo_placeholder" class="text-[11px] text-gray-400">
                Preview
              </span>
            </div>

            <p class="e-note mt-1">JPG/PNG up to 2MB. Square image works best.</p>
          </div>
        </div>

        <!-- 2. DESCRIPTION -->
        <div class="e-field-card">
          <p class="e-section-label">Description</p>
          <label class="block text-sm font-semibold mb-1">Description</label>
          <textarea name="description" rows="3" class="e-input"></textarea>
        </div>

        <!-- 3. SCHEDULE (start left, end right) -->
        <div class="e-field-card">
          <p class="e-section-label">Schedule</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold mb-1">Start Date &amp; Time *</label>
              <input type="datetime-local"
                     name="start_datetime"
                     id="create_start_datetime"
                     required
                     class="e-input"
                     min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">End Date &amp; Time *</label>
              <input type="datetime-local"
                     name="end_datetime"
                     id="create_end_datetime"
                     required
                     class="e-input"
                     min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
            </div>
          </div>
          <p class="e-note mt-1">
            Start must be today or in the future. End must be after start.
          </p>
        </div>

        <!-- 4. TARGET VOTERS + CONDITIONAL FIELDS -->
        <div class="e-field-card">
          <p class="e-section-label">Target voters</p>
          <label class="block text-sm font-semibold mb-1">Target Voters *</label>
          <div class="flex flex-wrap gap-4 text-sm mb-1">
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
          <p id="create_othersNote" class="e-note mt-1 hidden">
            <strong>Others:</strong> elections not tied to colleges/departments
            (e.g. COOP, Alumni). Voters are based on the admin’s uploaded list.
          </p>

          <!-- Student Fields (CREATE) -->
          <div id="studentFields" class="hidden space-y-4 mt-4">
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
          <div id="academicFields" class="hidden space-y-4 mt-4">
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
          <div id="nonAcademicFields" class="hidden space-y-4 mt-4">
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
        </div>

        <!-- 5. LAST SECTION: ASSIGN ADMIN -->
        <div class="e-field-card">
          <p class="e-section-label">Assign admin</p>
          <label class="block text-sm font-semibold mb-1">Assign Admin *</label>
          <select name="assigned_admin_id"
                  id="create_assigned_admin_id"
                  required
                  class="e-input bg-white">
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
                  <option value="<?= (int)$row['user_id'] ?>"
                          data-scope="<?= htmlspecialchars($row['scope_category'] ?? '') ?>">
                    <?= htmlspecialchars($row['first_name'].' '.$row['last_name'].$tail) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <p class="e-note mt-1">
            Only admins with a matching scope (students / faculty / non-academic / others) should be assigned.
          </p>
        </div>
      </form>
    </div>

    <!-- FOOTER BUTTONS -->
    <div class="e-modal-footer">
      <button type="button" id="clearFormBtn"
              class="bg-yellow-500 text-white px-5 py-2 rounded-lg text-sm hover:bg-yellow-600 transition">
        Clear
      </button>
      <button type="submit" form="createElectionForm"
              class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 transition">
        Create Election
      </button>
    </div>
  </div>
</div>

<!-- ====================== UPDATE ELECTION MODAL ====================== -->
<div id="updateModal" class="fixed inset-0 hidden z-50 flex items-center justify-center modal-backdrop">
  <div class="e-modal-panel">

    <!-- HEADER -->
    <div class="e-modal-header">
      <div class="e-modal-header-left">
        <div class="e-modal-icon">✏️</div>
        <div>
          <div class="e-modal-title">Update Election</div>
          <div class="e-modal-subtitle">Edit details, schedule, target voters and assigned admin.</div>
        </div>
      </div>
      <button id="closeUpdateModalBtn" class="e-modal-close">&times;</button>
    </div>

    <!-- BODY -->
    <div class="e-modal-body">

      <div id="updateFormError"
           class="hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm"></div>

      <form id="updateElectionForm" action="update_election.php" method="POST"
            enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="election_id" id="update_election_id">

        <!-- === BASIC DETAILS + LOGO PREVIEW === -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

          <!-- Election Name -->
          <div class="md:col-span-2 e-field-card">
            <p class="e-section-label">Basic Details</p>
            <label class="block text-sm font-semibold mb-1">Election Name *</label>
            <input type="text" name="election_name" id="update_election_name" required class="e-input">
          </div>

          <!-- Logo + Preview -->
          <div class="e-field-card">
            <p class="e-section-label">Logo</p>
            <label class="block text-sm font-semibold mb-1">Election Logo</label>

            <input type="file" name="update_logo" id="update_logo"
                   accept="image/*"
                   class="w-full text-sm border rounded-lg px-2 py-1.5">

            <!-- Preview Container -->
            <div class="mt-2 w-20 h-20 rounded-full bg-gray-100 border border-gray-300 overflow-hidden flex items-center justify-center">
              <img id="update_logo_preview"
                   src=""
                   alt="Logo preview"
                   class="hidden w-full h-full object-cover">
              <span id="update_logo_placeholder" class="text-[11px] text-gray-400">
                Preview
              </span>
            </div>

            <p class="e-note mt-1">Leave empty to keep the existing logo.</p>
          </div>

        </div>

        <!-- === DESCRIPTION === -->
        <div class="e-field-card">
          <p class="e-section-label">Description</p>
          <label class="block text-sm font-semibold mb-1">Description</label>
          <textarea name="description" id="update_description" rows="3" class="e-input"></textarea>
        </div>

        <!-- === SCHEDULE (Side-by-side) === -->
        <div class="e-field-card">
          <p class="e-section-label">Schedule</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold mb-1">Start Date & Time *</label>
              <input type="datetime-local" name="start_datetime" id="update_start_datetime"
                     required class="e-input" min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">End Date & Time *</label>
              <input type="datetime-local" name="end_datetime" id="update_end_datetime"
                     required class="e-input" min="<?= $currentDateTime ?>" max="2100-12-31T23:59">
            </div>
          </div>
          <p class="e-note mt-1">End date/time must be after the start date/time.</p>
        </div>

        <!-- === TARGET VOTERS === -->
        <div class="e-field-card">
          <p class="e-section-label">Target voters</p>
          <label class="block text-sm font-semibold mb-1">Target Voters *</label>

          <div class="flex flex-wrap gap-4 text-sm mb-1">
            <label class="flex items-center gap-1">
              <input type="radio" name="target_voter" value="student" id="update_target_student"> Student
            </label>
            <label class="flex items-center gap-1">
              <input type="radio" name="target_voter" value="faculty" id="update_target_faculty"> Faculty
            </label>
            <label class="flex items-center gap-1">
              <input type="radio" name="target_voter" value="non_academic" id="update_target_non_academic"> Non-Academic
            </label>
            <label class="flex items-center gap-1">
              <input type="radio" name="target_voter" value="others" id="update_target_others"> Others
            </label>
          </div>

          <p id="update_othersNote" class="e-note mt-1 hidden">
            <strong>Others:</strong> Not tied to colleges or departments. Voters are controlled by admin uploads.
          </p>

          <!-- CONDITIONAL FIELDS (hidden until needed) -->
          <div id="update_studentFields" class="hidden space-y-4 mt-4">
            <div>
              <label class="block mb-2 font-semibold">Allowed Colleges (Student)</label>
              <select name="allowed_colleges" id="update_allowed_colleges" class="w-full p-2 border rounded">
                <option value="all">All Colleges</option>
                <?php foreach ($colleges as $college): ?>
                  <option value="<?= $college ?>"><?= $college ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div id="update_studentCoursesContainer" class="hidden">
              <label class="block mb-2 font-semibold">Allowed Courses (Student)</label>
              <div id="update_studentCoursesList"
                   class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border p-2 rounded text-sm"></div>

              <button type="button" id="update_selectAllStudentCourses"
                      class="text-xs text-blue-600 hover:text-blue-800 mt-1">
                Select All
              </button>
            </div>
          </div>

          <div id="update_facultyFields" class="hidden space-y-4 mt-4">
            <label class="block mb-2 font-semibold">Allowed Colleges (Faculty)</label>
            <select name="allowed_colleges_faculty" id="update_allowed_colleges_faculty"
                    class="w-full p-2 border rounded">
              <option value="all">All Colleges</option>
              <?php foreach ($colleges as $college): ?>
                <option value="<?= $college ?>"><?= $college ?></option>
              <?php endforeach; ?>
            </select>

            <div id="update_facultyDepartmentsContainer" class="hidden">
              <label class="block mb-2 font-semibold">Allowed Departments (Faculty)</label>
              <div id="update_facultyDepartmentsList"
                   class="space-y-1 max-h-40 overflow-y-auto border p-2 rounded text-sm"></div>

              <div class="mt-1 flex justify-between text-xs">
                <button type="button" id="update_selectAllFacultyDepartments"
                        class="text-blue-600 hover:text-blue-800">Select All Departments</button>
                <span class="text-gray-500">Leave unchecked = All departments</span>
              </div>
            </div>

            <label class="block mb-2 font-semibold">Allowed Status</label>
            <div class="flex gap-6 text-sm border p-2 rounded">
              <label><input type="checkbox" name="allowed_status_faculty[]" value="Regular"> Regular</label>
              <label><input type="checkbox" name="allowed_status_faculty[]" value="Part-time"> Part-time</label>
              <label><input type="checkbox" name="allowed_status_faculty[]" value="Contractual"> Contractual</label>
            </div>
          </div>

          <div id="update_nonAcademicFields" class="hidden space-y-4 mt-4">
            <label class="block mb-2 font-semibold">Allowed Departments (Non-Academic)</label>
            <select name="allowed_departments_nonacad" id="update_allowed_departments_nonacad"
                    class="w-full p-2 border rounded">
              <option value="all">All Departments</option>
              <?php foreach ($nonAcadDepartments as $dept): ?>
                <option value="<?= $dept ?>"><?= $dept ?></option>
              <?php endforeach; ?>
            </select>

            <label class="block mb-2 font-semibold">Allowed Status</label>
            <div class="flex gap-6 text-sm border p-2 rounded">
              <label><input type="checkbox" name="allowed_status_nonacad[]" value="Regular"> Regular</label>
              <label><input type="checkbox" name="allowed_status_nonacad[]" value="Part-time"> Part-time</label>
              <label><input type="checkbox" name="allowed_status_nonacad[]" value="Contractual"> Contractual</label>
            </div>
          </div>

        </div>

        <!-- === ASSIGN ADMIN === -->
        <div class="e-field-card">
          <p class="e-section-label">Assign admin</p>

          <label class="block text-sm font-semibold mb-1">Assign Admin *</label>
          <select name="assigned_admin_id" id="update_assigned_admin_id"
                  class="e-input bg-white" required>
            <option value="">-- Select Admin --</option>
            <?php foreach ($adminsByCategory as $cat => $list): ?>
            <optgroup label="<?= $scopeCategoryLabels[$cat] ?>">
              <?php foreach ($list as $a):
                $desc = trim($a['admin_title'] . ' ' . $a['assigned_scope'] . ' ' . $a['assigned_scope_1']);
              ?>
              <option value="<?= $a['user_id'] ?>" data-scope="<?= $a['scope_category'] ?>">
                <?= $a['first_name'] . ' ' . $a['last_name'] ?> <?= $desc ? ' - '.$desc : '' ?>
              </option>
              <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
          </select>

          <p class="e-note mt-1">
            Only admins with matching voter scope are shown.
          </p>
        </div>

      </form>

    </div>

    <!-- FOOTER -->
    <div class="e-modal-footer">
      <button id="clearUpdateFormBtn"
              class="bg-yellow-500 text-white px-5 py-2 rounded-lg text-sm hover:bg-yellow-600 transition">
        Clear
      </button>
      <button type="submit" form="updateElectionForm"
              class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 transition">
        Update Election
      </button>
    </div>

  </div>
</div>
<!-- ====================== END UPDATE MODAL ====================== -->

<!-- External JS -->
<script src="create_election.js"></script>
<script src="update_election.js"></script>

<!-- Small helpers: logo preview + admin filtering -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // === Logo preview ===
  const logoInput       = document.getElementById('create_election_logo');
  const logoPreview     = document.getElementById('create_logo_preview');
  const logoPlaceholder = document.getElementById('create_logo_placeholder');

  function resetLogoPreview() {
    if (logoPreview) {
      logoPreview.src = '';
      logoPreview.classList.add('hidden');
    }
    if (logoPlaceholder) {
      logoPlaceholder.classList.remove('hidden');
    }
  }

  if (logoInput && logoPreview) {
    logoInput.addEventListener('change', () => {
      const file = logoInput.files && logoInput.files[0];
      if (!file) {
        resetLogoPreview();
        return;
      }
      const url = URL.createObjectURL(file);
      logoPreview.src = url;
      logoPreview.classList.remove('hidden');
      if (logoPlaceholder) logoPlaceholder.classList.add('hidden');
    });
  }

  // === Admin filtering by target_voter ===
  const targetRadios = document.querySelectorAll('#createElectionForm input[name="target_voter"]');
  const adminSelect  = document.getElementById('create_assigned_admin_id');

  function filterAdminsByTarget(target) {
    if (!adminSelect) return;

    const map = {
      student:      ['Academic-Student', 'Special-Scope'],
      academic:     ['Academic-Faculty'],
      non_academic:['Non-Academic-Employee'],
      others:       ['Others']
    };

    const allowedScopes = map[target] || null;

    Array.from(adminSelect.options).forEach((opt, idx) => {
      if (idx === 0) {
        opt.disabled = false;
        opt.hidden   = false;
        return;
      }
      const scope = opt.dataset.scope || '';
      if (!allowedScopes) {
        opt.disabled = false;
        opt.hidden   = false;
      } else {
        const ok = allowedScopes.includes(scope);
        opt.disabled = !ok;
        opt.hidden   = !ok;
      }
    });

    // clear invalid selection
    if (adminSelect.selectedOptions.length &&
        adminSelect.selectedOptions[0].hidden) {
      adminSelect.value = '';
    }
  }

  targetRadios.forEach(radio => {
    radio.addEventListener('change', e => {
      filterAdminsByTarget(e.target.value);
    });
  });

  // initial state: no filter until user chooses target
  filterAdminsByTarget(null);
});
</script>

</body>
</html>
