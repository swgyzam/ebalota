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
    header('Location: login.php');
    exit();
}

// Fetch all elections
$stmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
$elections = $stmt->fetchAll();

$now = date('Y-m-d H:i:s');
$pdo->query("UPDATE elections SET status = 'completed' WHERE end_datetime < '$now'");
$pdo->query("UPDATE elections SET status = 'ongoing' WHERE start_datetime <= '$now' AND end_datetime >= '$now'");
$pdo->query("UPDATE elections SET status = 'upcoming' WHERE start_datetime > '$now'");

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
      <button id="openModalBtn" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-light)] px-4 py-2 rounded font-semibold transition">
        + Create Election
      </button>
    </header>

    <section class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
  <?php if (count($elections) === 0): ?>
    <p class="text-gray-700 col-span-full text-center mt-12">No elections found. Click "Create Election" to add one.</p>
  <?php else: ?>
    <?php foreach ($elections as $election): ?>
      <div class="bg-white rounded-lg shadow p-6 border-l-8 <?= $election['status'] === 'ongoing' ? 'border-blue-500' : 'border-gray-300' ?>">
        <h2 class="text-xl font-bold text-[var(--cvsu-green-dark)] mb-2"><?= htmlspecialchars($election['title']) ?></h2>
        <p class="text-sm text-gray-500 mb-1">
          <strong>Start:</strong> <?= date('M d, Y h:i A', strtotime($election['start_datetime'])) ?>
        </p>
        <p class="text-sm text-gray-500 mb-3">
          <strong>End:</strong> <?= date('M d, Y h:i A', strtotime($election['end_datetime'])) ?>
        </p>

        <?php
            $now = date('Y-m-d H:i:s');
            $start = $election['start_datetime'];
            $end = $election['end_datetime'];

            if ($now < $start) {
                $status = 'upcoming';
            } elseif ($now >= $start && $now <= $end) {
                $status = 'ongoing';
            } else {
                $status = 'completed';
            }
        ?>

        <p class="text-sm font-semibold <?= $status === 'ongoing' ? 'text-blue-600' : 'text-gray-600' ?>">
          Status: <?= ucfirst($status) ?>
        </p>

        <!-- Added fields -->
        <p class="text-sm text-gray-700 mt-2">
          <strong>Real-time Results:</strong> <?= $election['realtime_results'] ? 'Yes' : 'No' ?>
        </p>

        <p class="text-sm text-gray-700">
          <strong>Allowed Colleges:</strong> <?= htmlspecialchars($election['allowed_colleges']) ?>
        </p>

        <p class="text-sm text-gray-700 mt-2">
          <strong>Allowed Positions:</strong> <?= htmlspecialchars(!empty($election['allowed_positions']) ? $election['allowed_positions'] : 'All') ?>
        </p>

        <p class="text-sm text-gray-700">
          <strong>Allowed Courses:</strong> <?= htmlspecialchars(!empty($election['allowed_courses']) ? $election['allowed_courses'] : 'All') ?>
        </p>

        <p class="text-sm text-gray-700">
          <strong>Allowed Status:</strong> <?= htmlspecialchars(!empty($election['allowed_status']) ? $election['allowed_status'] : 'All') ?>
        </p>

      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>



    <?php include 'footer.php'; ?>
  </main>

  <!-- Modal -->
  <div id="modal" class="fixed inset-0 hidden items-center justify-center modal-backdrop">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-8 relative">
      <button id="closeModalBtn" class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
      <h2 class="text-2xl font-bold mb-6">Create Election</h2>

      <form action="create_election.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label class="block mb-2 font-semibold">Election Name *</label>
          <input type="text" name="election_name" required class="w-full p-2 border rounded">
        </div>

        <div>
          <label class="block mb-2 font-semibold">Description</label>
          <textarea name="description" class="w-full p-2 border rounded"></textarea>
        </div>

        <div>
          <label class="block mb-2 font-semibold">Start and End Date *</label>
          <div class="flex gap-2">
            <input type="date" name="start_date" required class="w-1/2 p-2 border rounded">
            <input type="date" name="end_date" required class="w-1/2 p-2 border rounded">
          </div>
        </div>

        <div>
          <label class="flex items-center mb-2">
            <input type="checkbox" name="realtime" class="mr-2">
            Show real-time result when the election is ongoing
          </label>
        </div>

        <div>
          <label class="block mb-2 font-semibold">Election Restriction</label>
          <div class="grid grid-cols-4 gap-2 text-sm max-h-48 overflow-y-auto border p-2 rounded">
            <?php
              $colleges = ['CAFENR', 'CAS', 'CEIT', 'CEMDS', 'CED', 'CSPEAR', 'CTHM', 'CVBMS', 'COM', 'GS-OLC', 'CON'];
              foreach ($colleges as $college) {
                  echo "<label><input type='checkbox' name='colleges[]' value='$college' class='mr-1'>$college</label>";
              }
            ?>
          </div>
         <!-- Target Position -->
        <div>
        <label class="block mb-2 font-semibold">Target Position</label>
        <div class="flex flex-wrap gap-4 text-sm">
            <label><input type="checkbox" name="target_position[]" value="student" class="mr-1 target-check" data-target="student">Student</label>
            <label><input type="checkbox" name="target_position[]" value="faculty" class="mr-1 target-check" data-target="faculty">Faculty</label>
            <label><input type="checkbox" name="target_position[]" value="coop" class="mr-1 target-check" data-target="coop">COOP</label>
        </div>
        </div>

        <!-- Allowed Courses (Hidden by default) -->
        <div id="allowedCoursesSection" class="mt-4 hidden">
        <label class="block mb-2 font-semibold">Allowed Courses</label>
        <div class="grid grid-cols-2 gap-2 text-sm max-h-32 overflow-y-auto border p-2 rounded">
            <?php
            $courses = ['BSIT', 'BSCS', 'BSEd', 'BEEd', 'BSBA', 'BSHM', 'BSTM', 'BSN', 'BSAgri', 'DVM', 'BLIS', 'BSE', 'BSAIS'];
            foreach ($courses as $course) {
                echo "<label><input type='checkbox' name='allowed_courses[]' value='$course' class='mr-1'>$course</label>";
            }
            ?>
        </div>
        </div>

        <!-- Allowed Status (Hidden by default) -->
        <div id="allowedStatusSection" class="mt-4 hidden">
        <label class="block mb-2 font-semibold">Allowed Status</label>
        <div class="flex flex-wrap gap-4 text-sm">
            <label><input type="checkbox" name="allowed_status[]" value="Regular" class="mr-1">Regular</label>
            <label><input type="checkbox" name="allowed_status[]" value="Lecturer" class="mr-1">Lecturer</label>
            <label><input type="checkbox" name="allowed_status[]" value="Part-time" class="mr-1">Part-time</label>
        </div>
        </div>  
        </div>

        <div class="text-right">
          <button type="submit" class="bg-[var(--cvsu-green)] hover:bg-[var(--cvsu-green-light)] px-6 py-2 rounded font-semibold transition">
            Create
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.target-check');
        const courseSection = document.getElementById('allowedCoursesSection');
        const statusSection = document.getElementById('allowedStatusSection');

        function toggleSections() {
            let studentChecked = false;
            let facultyChecked = false;
            let coopChecked = false;

            checkboxes.forEach(checkbox => {
                if (checkbox.checked && checkbox.dataset.target === 'student') studentChecked = true;
                if (checkbox.checked && checkbox.dataset.target === 'faculty') facultyChecked = true;
                if (checkbox.checked && checkbox.dataset.target === 'coop') coopChecked = true;
            });

            courseSection.classList.toggle('hidden', !studentChecked);
            statusSection.classList.toggle('hidden', !(facultyChecked || coopChecked));
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', toggleSections);
        });

        toggleSections(); // initialize visibility on load
    });

    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modal = document.getElementById('modal');

    openModalBtn.addEventListener('click', () => {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });

    closeModalBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    });
  </script>
</body>
</html>
