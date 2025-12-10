<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config.php';

// --- DB Connection ---
$host    = 'localhost';
$db      = 'evoting_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Table for disabled default positions (same as add_candidate.php)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS disabled_default_positions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            position_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_admin_position (admin_id, position_name)
        )
    ");
} catch (\PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// --- Session timeout 1 hour ---
$timeout_duration = 3600;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Session expired. Please login again.');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// --- Get current admin info (+ scope category) ---
$stmt = $pdo->prepare("
    SELECT role, assigned_scope, scope_category
    FROM users
    WHERE user_id = :userId
");
$stmt->execute([':userId' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

$role          = $user['role'];
$assignedScope = $user['assigned_scope'];
$scopeCategory = $user['scope_category'] ?? '';

// --- Resolve this admin's scope seat (admin_scopes) ---
$myScopeId = null;

if (!empty($scopeCategory)) {
    $scopeStmt = $pdo->prepare("
        SELECT scope_id, scope_type, scope_details
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid'   => $userId,
        ':stype' => $scopeCategory,
    ]);
    $scopeRow = $scopeStmt->fetch();

    if ($scopeRow) {
        $myScopeId = (int)$scopeRow['scope_id'];
        // $myScopeDetails = json_decode($scopeRow['scope_details'] ?? '[]', true) ?: [];
    }
}

// --- Create upload directories if they don't exist ---
if (!file_exists(PROFILE_PIC_DIR)) mkdir(PROFILE_PIC_DIR, 0755, true);
if (!file_exists(CREDENTIALS_DIR)) mkdir(CREDENTIALS_DIR, 0755, true);

// --- Vars for page / form state ---
$errors             = [];
$success            = '';
$rowsInserted       = 0;
$selectedElectionId = '';
$selectedPositionId = '';

// Arrays for repopulating form when there are PHP validation errors
$identifiers = [];
$bulkUserIds = [];
$firstNames  = [];
$middleNames = [];
$lastNames   = [];
$partyLists  = [];

$hasPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

// --- Handle POST (bulk upload) ---
if ($hasPost) {
    $selectedElectionId = $_POST['election_id'] ?? '';
    $selectedPositionId = $_POST['position_id'] ?? '';

    if (empty($selectedElectionId)) {
        $errors[] = 'Election selection is required.';
    }
    if (empty($selectedPositionId)) {
        $errors[] = 'Position selection is required.';
    }

    // Candidates data (arrays)
    $identifiers = $_POST['identifier']   ?? [];
    $bulkUserIds = $_POST['bulk_user_id'] ?? [];
    $firstNames  = $_POST['first_name']   ?? [];
    $middleNames = $_POST['middle_name']  ?? [];
    $lastNames   = $_POST['last_name']    ?? [];
    $partyLists  = $_POST['party_list']   ?? [];

    if (!is_array($firstNames) || count($firstNames) === 0) {
        $errors[] = 'At least one candidate row is required.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Resolve position name + id (same logic as in add_candidate.php)
            $positionValue = $selectedPositionId;
            $positionName  = '';
            $positionId    = null;

            if (strpos($positionValue, 'default_') === 0) {
                // Default position (e.g. default_President or default_Vice%20President)
                $positionName = urldecode(substr($positionValue, 8));
            } else {
                // Custom position (id from positions table)
                $positionId = $positionValue;
                $pStmt = $pdo->prepare('SELECT position_name FROM positions WHERE id = ?');
                $pStmt->execute([$positionId]);
                $pRow = $pStmt->fetch();
                $positionName = $pRow ? $pRow['position_name'] : '';
            }

            $allowedImgTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxImgSize      = 2 * 1024 * 1024; // 2MB
            $maxPdfSize      = 2 * 1024 * 1024; // 2MB

            foreach ($firstNames as $idx => $fn) {
                $fn = trim($fn);
                $ln = trim($lastNames[$idx]   ?? '');
                $mn = trim($middleNames[$idx] ?? '');
                $pl = trim($partyLists[$idx]  ?? '');
                $idVal = trim($identifiers[$idx] ?? '');
                $userIdVal = !empty($bulkUserIds[$idx]) ? (int)$bulkUserIds[$idx] : null;

                // Skip totally empty rows (no names, no identifier, no files)
                $hasFiles =
                    !empty($_FILES['profile_picture']['name'][$idx] ?? '') ||
                    !empty($_FILES['credentials_pdf']['name'][$idx] ?? '');

                if (
                    $fn === '' && $ln === '' && $mn === '' && $pl === '' &&
                    $idVal === '' && !$hasFiles
                ) {
                    continue;
                }

                // Require first + last name
                if ($fn === '' || $ln === '') {
                    throw new Exception('Row ' . ($idx + 1) . ': First name and last name are required.');
                }

                // If identifier empty, auto-generate something but not NULL
                if ($idVal === '') {
                    $idVal = 'BULK-' . date('YmdHis') . '-' . ($idx + 1);
                    $identifiers[$idx] = $idVal; // so HTML can reflect it if we re-render
                }

                $profilePicPath  = null;
                $credentialsPath = null;

                // --- Profile picture: profile_picture[] ---
                if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'][$idx])) {
                    $imgName = $_FILES['profile_picture']['name'][$idx];
                    $imgTmp  = $_FILES['profile_picture']['tmp_name'][$idx];
                    $imgType = $_FILES['profile_picture']['type'][$idx];
                    $imgSize = $_FILES['profile_picture']['size'][$idx];
                    $imgErr  = $_FILES['profile_picture']['error'][$idx];

                    if ($imgErr === UPLOAD_ERR_OK) {
                        if (!in_array($imgType, $allowedImgTypes)) {
                            throw new Exception('Row ' . ($idx + 1) . ': Invalid profile picture type.');
                        }
                        if ($imgSize > $maxImgSize) {
                            throw new Exception('Row ' . ($idx + 1) . ': Profile picture too large (max 2MB).');
                        }
                        if (!getimagesize($imgTmp)) {
                            throw new Exception('Row ' . ($idx + 1) . ': Uploaded file is not a valid image.');
                        }

                        $ext      = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
                        $fileName = 'candidate_' . time() . '_' . $idx . '.' . $ext;
                        $target   = PROFILE_PIC_DIR . $fileName;

                        if (!move_uploaded_file($imgTmp, $target)) {
                            throw new Exception('Row ' . ($idx + 1) . ': Failed to upload profile picture.');
                        }

                        $profilePicPath = 'uploads/profile_pictures/' . $fileName;
                    } elseif ($imgErr !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception('Row ' . ($idx + 1) . ': Error uploading profile picture.');
                    }
                }

                // --- Credentials PDF: credentials_pdf[] ---
                if (isset($_FILES['credentials_pdf']) && !empty($_FILES['credentials_pdf']['name'][$idx])) {
                    $pdfName = $_FILES['credentials_pdf']['name'][$idx];
                    $pdfTmp  = $_FILES['credentials_pdf']['tmp_name'][$idx];
                    $pdfType = $_FILES['credentials_pdf']['type'][$idx];
                    $pdfSize = $_FILES['credentials_pdf']['size'][$idx];
                    $pdfErr  = $_FILES['credentials_pdf']['error'][$idx];

                    if ($pdfErr === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));
                        if ($pdfType !== 'application/pdf' || $ext !== 'pdf') {
                            throw new Exception('Row ' . ($idx + 1) . ': Credentials must be a PDF file.');
                        }
                        if ($pdfSize > $maxPdfSize) {
                            throw new Exception('Row ' . ($idx + 1) . ': Credentials PDF too large (max 2MB).');
                        }

                        $fileName = 'credentials_' . time() . '_' . $idx . '.pdf';
                        $target   = CREDENTIALS_DIR . $fileName;

                        if (!move_uploaded_file($pdfTmp, $target)) {
                            throw new Exception('Row ' . ($idx + 1) . ': Failed to upload credentials PDF.');
                        }

                        $credentialsPath = 'uploads/credentials/' . $fileName;
                    } elseif ($pdfErr !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception('Row ' . ($idx + 1) . ': Error uploading credentials PDF.');
                    }
                }

                // --- Insert into candidates ---
                $cStmt = $pdo->prepare("
                    INSERT INTO candidates
                    (user_id, identifier, first_name, last_name, middle_name, photo, credentials, created_by, party_list)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $cStmt->execute([
                    $userIdVal ?: null, // NULL kung manual, user_id kung galing search
                    $idVal,             // never NULL
                    $fn,
                    $ln,
                    $mn,
                    $profilePicPath,
                    $credentialsPath,
                    $userId,            // current admin (created_by)
                    $pl
                ]);

                $candidateId = $pdo->lastInsertId();

                // --- Link to election_candidates ---
                $ecStmt = $pdo->prepare("
                    INSERT INTO election_candidates (election_id, candidate_id, position, position_id)
                    VALUES (?, ?, ?, ?)
                ");
                $ecStmt->execute([
                    $selectedElectionId,
                    $candidateId,
                    $positionName,
                    $positionId
                ]);

                $rowsInserted++;
            }

            // --- Activity log (one entry for the whole bulk op) ---
            if ($rowsInserted > 0) {
                try {
                    $electionTitle = '';
                    $titleStmt = $pdo->prepare('SELECT title FROM elections WHERE election_id = ?');
                    $titleStmt->execute([$selectedElectionId]);
                    $titleRow = $titleStmt->fetch();
                    if ($titleRow && !empty($titleRow['title'])) {
                        $electionTitle = $titleRow['title'];
                    }

                    $actionText = "Bulk created {$rowsInserted} candidate(s)";

                    if ($electionTitle !== '') {
                        $actionText .= " for election: {$electionTitle} (Election ID: {$selectedElectionId})";
                    } else {
                        $actionText .= " for election ID: {$selectedElectionId}";
                    }

                    if (!empty($positionName)) {
                        $actionText .= " as position: {$positionName}";
                    }

                    $logStmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, timestamp)
                        VALUES (:uid, :action, NOW())
                    ");
                    $logStmt->execute([
                        ':uid'    => $userId,
                        ':action' => $actionText,
                    ]);
                } catch (PDOException $logEx) {
                    error_log('Activity log error (bulk_add_candidates.php): ' . $logEx->getMessage());
                }
            }

            $pdo->commit();

            if ($rowsInserted > 0) {
                $success            = "Successfully added {$rowsInserted} candidate(s) to this election and position.";
                // Clear form on success (JS autosave will also clear its draft later)
                $selectedElectionId = '';
                $selectedPositionId = '';
                $identifiers        = [];
                $bulkUserIds        = [];
                $firstNames         = [];
                $middleNames        = [];
                $lastNames          = [];
                $partyLists         = [];
                $hasPost            = false;
            } else {
                $errors[] = 'No valid rows were found in the bulk form.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Bulk upload error: ' . $e->getMessage();
        }
    }
}

// --- Fetch elections for dropdown (same seat-aware logic as add_candidate.php) ---
if ($myScopeId !== null && !empty($scopeCategory)) {
    $electionStmt = $pdo->prepare("
        SELECT election_id, title
        FROM elections
        WHERE (assigned_admin_id = :adminId)
           OR (owner_scope_id = :scopeId AND election_scope_type = :scopeCategory)
        ORDER BY title ASC
    ");
    $electionStmt->execute([
        ':adminId'       => $userId,
        ':scopeId'       => $myScopeId,
        ':scopeCategory' => $scopeCategory,
    ]);
} else {
    $electionStmt = $pdo->prepare("
        SELECT election_id, title
        FROM elections
        WHERE assigned_admin_id = :adminId
        ORDER BY title ASC
    ");
    $electionStmt->execute([':adminId' => $userId]);
}
$elections = $electionStmt->fetchAll();

// --- Fetch custom positions for dropdown ---
$customPositionsStmt = $pdo->prepare("
    SELECT id, position_name
    FROM positions
    WHERE created_by = :userId
    ORDER BY position_name ASC
");
$customPositionsStmt->execute([':userId' => $userId]);
$customPositions = $customPositionsStmt->fetchAll();

// --- Fetch disabled default positions ---
try {
    $disabledPositionsStmt = $pdo->prepare("
        SELECT position_name
        FROM disabled_default_positions
        WHERE admin_id = :userId
    ");
    $disabledPositionsStmt->execute([':userId' => $userId]);
    $disabledPositions = $disabledPositionsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    $disabledPositions = [];
}

// Default positions list (same as add_candidate.php)
$defaultPositions = [
    'President',
    'Vice President',
    'Secretary',
    'Treasurer',
    'Auditor',
    'Public Relations Officer',
    'Senator',
    'Representative'
];

// If no elections found, show message
if (empty($elections)) {
    $errors[] = 'No elections are assigned to you. Please contact the super admin.';
}

// Expose flags to JS
$HAS_SERVER_POST = $hasPost ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bulk Upload Candidates - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }

    .form-input:focus {
      transform: translateY(-1px);
      box-shadow: 0 4px 20px rgba(30, 111, 70, 0.1);
    }

    .floating-label {
      transition: all 0.2s ease;
    }

    .input-group:focus-within .floating-label {
      transform: translateY(-1.5rem) scale(0.85);
      color: var(--cvsu-green);
    }

    .input-group input:not(:placeholder-shown) + .floating-label,
    .input-group select:not([value=""]) + .floating-label,
    .input-group textarea:not(:placeholder-shown) + .floating-label {
      transform: translateY(-1.5rem) scale(0.85);
      color: var(--cvsu-green);
    }

    .error-border {
      border-color: #ef4444 !important;
    }

    .error-text {
      color: #ef4444;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }

    .table-wrapper {
      max-height: 420px;
      overflow-y: auto;
      border-radius: 0.75rem;
    }

    .readonly-name {
      background-color: #eff6ff; /* light blue */
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
  <!-- Background Pattern -->
  <div class="fixed inset-0 opacity-5 pointer-events-none">
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 100 100&quot;><circle cx=&quot;50&quot; cy=&quot;50&quot; r=&quot;2&quot; fill=&quot;%23154734&quot;/></svg>'); background-size: 20px 20px;"></div>
  </div>

  <div class="relative z-10 min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-2xl">
      <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center space-x-4">
            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
              <i class="fas fa-users text-xl"></i>
            </div>
            <div>
              <h1 class="text-3xl font-bold">Bulk Upload Candidates</h1>
              <p class="text-green-100 mt-1">
                Add multiple candidates to the same election and position in one submission.
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <a href="add_candidate.php"
               class="flex items-center space-x-2 px-5 py-3 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-all duration-200 backdrop-blur-sm">
              <i class="fas fa-user-plus"></i>
              <span>Single Candidate</span>
            </a>
            <a href="admin_manage_candidates.php"
               class="flex items-center space-x-2 px-5 py-3 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-all duration-200 backdrop-blur-sm">
              <i class="fas fa-arrow-left"></i>
              <span>Back to Candidates</span>
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-6 py-10">
      <!-- Alert Messages -->
      <div id="alertContainer" class="mb-6">
        <?php if ($success): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl border flex items-center space-x-3">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <?php foreach ($errors as $error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl border flex items-center space-x-3 mb-2">
              <i class="fas fa-exclamation-triangle"></i>
              <span><?php echo htmlspecialchars($error); ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Main Card -->
      <div class="glass-card rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-8">
          <?php if (empty($elections)): ?>
            <!-- No Elections Assigned Message -->
            <div class="text-center py-12">
              <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800 mb-2">No Elections Assigned</h3>
              <p class="text-gray-600 mb-6">
                You don't have any elections assigned to you. Please contact the super admin to get election assignments.
              </p>
              <a href="admin_manage_candidates.php"
                 class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Candidates
              </a>
            </div>
          <?php else: ?>

            <!-- Top info -->
            <div class="mb-6">
              <div class="flex items-center mb-2">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-layer-group text-green-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Bulk Candidate Details</h2>
              </div>
              <p class="text-gray-600 text-sm">
                Select an election and position, then add multiple candidates below. Each row represents one candidate.
              </p>
            </div>

            <!-- Form -->
            <form id="bulkCandidateForm" method="POST" enctype="multipart/form-data" class="space-y-8">
              <!-- Election & Position -->
              <div class="grid md:grid-cols-2 gap-6">
                <!-- Election -->
                <div>
                  <div class="input-group relative">
                    <select
                      name="election_id"
                      id="election_id"
                      required
                      class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 bg-white"
                    >
                      <option value="">Select Election</option>
                      <?php foreach ($elections as $election): ?>
                        <option
                          value="<?php echo htmlspecialchars($election['election_id']); ?>"
                          <?php echo ($selectedElectionId == $election['election_id']) ? 'selected' : ''; ?>
                        >
                          <?php echo htmlspecialchars($election['title']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                      Election <span class="text-red-500">*</span>
                    </label>
                  </div>
                </div>

                <!-- Position -->
                <div>
                  <div class="input-group relative">
                    <select
                      name="position_id"
                      id="position_id"
                      required
                      class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 bg-white"
                    >
                      <option value="">Select Position</option>

                      <!-- Default positions (skip disabled) -->
                      <?php foreach ($defaultPositions as $pos): ?>
                        <?php if (in_array($pos, $disabledPositions)) continue; ?>
                        <?php
                          $val = 'default_' . urlencode($pos);
                          $sel = ($selectedPositionId == $val) ? 'selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $sel; ?>>
                          <?php echo htmlspecialchars($pos); ?>
                        </option>
                      <?php endforeach; ?>

                      <!-- Custom positions -->
                      <?php if (!empty($customPositions)): ?>
                        <optgroup label="Custom Positions">
                          <?php foreach ($customPositions as $pos): ?>
                            <?php
                              $val = $pos['id'];
                              $sel = ($selectedPositionId == $val) ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $sel; ?>>
                              <?php echo htmlspecialchars($pos['position_name']); ?>
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endif; ?>
                    </select>
                    <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                      Position <span class="text-red-500">*</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Info box -->
              <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl flex items-start space-x-3 text-sm">
                <i class="fas fa-info-circle mt-1"></i>
                <div>
                  <p class="font-semibold">How it works:</p>
                  <ul class="list-disc list-inside mt-1 space-y-1">
                    <li>All candidates in the table below will be assigned to the selected Election and Position.</li>
                    <li>You can <strong>search by student/employee number</strong> to auto-fill names, or type names manually.</li>
                    <li>When a user is found, first and last name fields will be locked to prevent accidental changes.</li>
                    <li>Profile picture and credentials PDF are optional per row (can be uploaded later individually).</li>
                  </ul>
                </div>
              </div>

              <!-- Candidates Table -->
              <div>
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                      <i class="fas fa-users text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Candidate List</h3>
                  </div>
                  <button
                    type="button"
                    id="addRowBtn"
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all duration-200 shadow-md hover:shadow-lg text-sm"
                  >
                    <i class="fas fa-plus mr-2"></i>
                    Add Row
                  </button>
                </div>

                <div class="table-wrapper border border-gray-200 bg-white rounded-xl overflow-hidden">
                  <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700 sticky top-0 z-10">
                      <tr>
                        <th class="px-3 py-2 border-b text-left font-medium w-44">
                          ID / Search
                        </th>
                        <th class="px-3 py-2 border-b text-left font-medium w-1/6">First Name <span class="text-red-500">*</span></th>
                        <th class="px-3 py-2 border-b text-left font-medium w-1/6">Middle Name</th>
                        <th class="px-3 py-2 border-b text-left font-medium w-1/6">Last Name <span class="text-red-500">*</span></th>
                        <th class="px-3 py-2 border-b text-left font-medium w-1/6">Party List</th>
                        <th class="px-3 py-2 border-b text-left font-medium w-1/6">Profile Picture</th>
                        <th class="px-3 py-2 border-b text-left font-medium w-1/6">Credentials PDF</th>
                        <th class="px-3 py-2 border-b text-center font-medium w-20">Action</th>
                      </tr>
                    </thead>
                    <tbody id="candidateBody" class="divide-y divide-gray-100">
                      <?php
                        $hasRows = is_array($firstNames) && count($firstNames) > 0;
                        if ($hasRows):
                          foreach ($firstNames as $idx => $fnRow):
                            $idVal  = $identifiers[$idx]  ?? '';
                            $uidVal = $bulkUserIds[$idx]  ?? '';
                            $fnVal  = $fnRow               ?? '';
                            $mnVal  = $middleNames[$idx]   ?? '';
                            $lnVal  = $lastNames[$idx]     ?? '';
                            $plVal  = $partyLists[$idx]    ?? '';
                      ?>
                        <tr>
                          <!-- Identifier + search + hidden user_id -->
                          <td class="px-3 py-2">
                            <div class="flex items-center space-x-1">
                              <input
                                type="text"
                                name="identifier[]"
                                class="flex-1 border border-gray-300 rounded-lg p-1.5 text-xs identifier-input
                                       focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                                placeholder="Student/Employee #"
                                value="<?php echo htmlspecialchars($idVal); ?>"
                              >
                              <button
                                type="button"
                                class="px-2 py-1 bg-green-600 text-white rounded text-xs search-row-btn"
                                title="Search user by ID"
                              >
                                <i class="fas fa-search"></i>
                              </button>
                            </div>
                            <input
                              type="hidden"
                              name="bulk_user_id[]"
                              class="bulk-user-id"
                              value="<?php echo htmlspecialchars($uidVal); ?>"
                            >
                            <p class="text-[10px] text-gray-400 mt-1"></p>
                          </td>

                          <!-- First Name -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="first_name[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm first-name-input
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                              value="<?php echo htmlspecialchars($fnVal); ?>"
                              required
                            >
                          </td>

                          <!-- Middle Name -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="middle_name[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm middle-name-input
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                              value="<?php echo htmlspecialchars($mnVal); ?>"
                            >
                          </td>

                          <!-- Last Name -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="last_name[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm last-name-input
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                              value="<?php echo htmlspecialchars($lnVal); ?>"
                              required
                            >
                          </td>

                          <!-- Party List -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="party_list[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                              value="<?php echo htmlspecialchars($plVal); ?>"
                            >
                          </td>

                          <!-- Profile Picture -->
                          <td class="px-3 py-2">
                            <input
                              type="file"
                              name="profile_picture[]"
                              accept="image/jpeg,image/png,image/jpg"
                              class="w-full text-xs text-gray-600"
                            >
                            <p class="text-[10px] text-gray-400 mt-1">JPG/PNG up to 2MB</p>
                          </td>

                          <!-- Credentials PDF -->
                          <td class="px-3 py-2">
                            <input
                              type="file"
                              name="credentials_pdf[]"
                              accept=".pdf"
                              class="w-full text-xs text-gray-600"
                            >
                            <p class="text-[10px] text-gray-400 mt-1">PDF up to 2MB</p>
                          </td>

                          <!-- Action -->
                          <td class="px-3 py-2 text-center">
                            <button
                              type="button"
                              class="text-red-600 hover:text-red-800 inline-flex items-center justify-center removeRowBtn"
                            >
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </td>
                        </tr>
                      <?php
                          endforeach;
                        else:
                      ?>
                        <!-- Default empty row -->
                        <tr>
                          <!-- Identifier + search + hidden user_id -->
                          <td class="px-3 py-2">
                            <div class="flex items-center space-x-1">
                              <input
                                type="text"
                                name="identifier[]"
                                class="flex-1 border border-gray-300 rounded-lg p-1.5 text-xs identifier-input
                                       focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                                placeholder="Student/Employee #"
                              >
                              <button
                                type="button"
                                class="px-2 py-1 bg-green-600 text-white rounded text-xs search-row-btn"
                                title="Search user by ID"
                              >
                                <i class="fas fa-search"></i>
                              </button>
                            </div>
                            <input
                              type="hidden"
                              name="bulk_user_id[]"
                              class="bulk-user-id"
                              value=""
                            >
                            <p class="text-[10px] text-gray-400 mt-1"></p>
                          </td>

                          <!-- First Name -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="first_name[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm first-name-input
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                              required
                            >
                          </td>

                          <!-- Middle Name -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="middle_name[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm middle-name-input
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                            >
                          </td>

                          <!-- Last Name -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="last_name[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm last-name-input
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                              required
                            >
                          </td>

                          <!-- Party List -->
                          <td class="px-3 py-2">
                            <input
                              type="text"
                              name="party_list[]"
                              class="w-full border border-gray-300 rounded-lg p-2 text-sm
                                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                            >
                          </td>

                          <!-- Profile Picture -->
                          <td class="px-3 py-2">
                            <input
                              type="file"
                              name="profile_picture[]"
                              accept="image/jpeg,image/png,image/jpg"
                              class="w-full text-xs text-gray-600"
                            >
                            <p class="text-[10px] text-gray-400 mt-1">JPG/PNG up to 2MB</p>
                          </td>

                          <!-- Credentials PDF -->
                          <td class="px-3 py-2">
                            <input
                              type="file"
                              name="credentials_pdf[]"
                              accept=".pdf"
                              class="w-full text-xs text-gray-600"
                            >
                            <p class="text-[10px] text-gray-400 mt-1">PDF up to 2MB</p>
                          </td>

                          <!-- Action -->
                          <td class="px-3 py-2 text-center">
                            <button
                              type="button"
                              class="text-red-600 hover:text-red-800 inline-flex items-center justify-center removeRowBtn"
                            >
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <p class="text-xs text-gray-500 mt-2">
                  Tip: You can leave Profile Picture and Credentials empty for some rows and upload them later individually.
                </p>
              </div>

              <!-- Submit -->
              <div class="flex justify-end pt-4 border-t border-gray-100">
                <button
                  type="submit"
                  class="inline-flex items-center px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg hover:shadow-xl"
                >
                  <i class="fas fa-cloud-upload-alt mr-2"></i>
                  <span>Save All Candidates</span>
                </button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
  <script>
    // ====== CONFIG FROM PHP ======
    const BULK_DRAFT_KEY   = 'bulk_candidates_draft_admin_<?php echo $userId; ?>';
    const HAS_SERVER_POST  = <?php echo $HAS_SERVER_POST; ?>;   // true if PHP handled a POST (even with errors)
    const HAS_SUCCESS      = <?php echo $success ? 'true' : 'false'; ?>;
    const SEARCH_URL       = 'search_user.php';

    // ====== ALERT HELPER ======
    function showJsAlert(message, type = 'error') {
      const alertContainer = document.getElementById('alertContainer');
      if (!alertContainer) return;

      const wrapper = document.createElement('div');
      const baseClass = type === 'error'
        ? 'bg-red-100 border border-red-400 text-red-700'
        : 'bg-green-100 border border-green-400 text-green-700';

      const iconClass = type === 'error'
        ? 'fa-exclamation-triangle'
        : 'fa-check-circle';

      wrapper.className = baseClass + ' px-4 py-3 rounded-xl border flex items-center space-x-3 mb-2';
      wrapper.innerHTML = `
        <i class="fas ${iconClass}"></i>
        <span>${message}</span>
      `;
      alertContainer.appendChild(wrapper);

      setTimeout(() => {
        wrapper.remove();
      }, 5000);
    }

    // ====== DRAFT (AUTOSAVE) HELPERS ======
    function loadDraft() {
      try {
        const raw = localStorage.getItem(BULK_DRAFT_KEY);
        if (!raw) return null;
        return JSON.parse(raw);
      } catch (e) {
        console.error('Failed to load draft', e);
        return null;
      }
    }

    function saveDraftFromDOM() {
      const form = document.getElementById('bulkCandidateForm');
      if (!form) return;

      const electionSelect = document.getElementById('election_id');
      const positionSelect = document.getElementById('position_id');
      const candidateBody  = document.getElementById('candidateBody');

      const draft = {
        election_id: electionSelect ? electionSelect.value : '',
        position_id: positionSelect ? positionSelect.value : '',
        rows: []
      };

      if (!candidateBody) return;

      candidateBody.querySelectorAll('tr').forEach(tr => {
        const identifierInput = tr.querySelector('.identifier-input');
        const userIdInput     = tr.querySelector('.bulk-user-id');
        const fnInput         = tr.querySelector('.first-name-input');
        const mnInput         = tr.querySelector('.middle-name-input');
        const lnInput         = tr.querySelector('.last-name-input');
        const plInput         = tr.querySelector('input[name="party_list[]"]');

        const rowData = {
          identifier: identifierInput ? identifierInput.value : '',
          bulk_user_id: userIdInput ? userIdInput.value : '',
          first_name: fnInput ? fnInput.value : '',
          middle_name: mnInput ? mnInput.value : '',
          last_name: lnInput ? lnInput.value : '',
          party_list: plInput ? plInput.value : ''
        };

        // Save all rows (including "empty" ones) so we preserve count/layout
        draft.rows.push(rowData);
      });

      try {
        localStorage.setItem(BULK_DRAFT_KEY, JSON.stringify(draft));
      } catch (e) {
        console.error('Failed to save draft', e);
      }
    }

    function clearDraft() {
      try {
        localStorage.removeItem(BULK_DRAFT_KEY);
      } catch (e) {
        console.error('Failed to clear draft', e);
      }
    }

    function applyDraftToDOM(draft) {
      if (!draft) return;

      const electionSelect = document.getElementById('election_id');
      const positionSelect = document.getElementById('position_id');
      const candidateBody  = document.getElementById('candidateBody');

      if (electionSelect && draft.election_id) {
        electionSelect.value = draft.election_id;
      }
      if (positionSelect && draft.position_id) {
        positionSelect.value = draft.position_id;
      }

      if (!candidateBody) return;

      // Clear existing rows first, then rebuild from draft.rows
      candidateBody.innerHTML = '';

      if (!Array.isArray(draft.rows) || draft.rows.length === 0) {
        // If no rows in draft, leave body empty; JS will add default row later if needed
        return;
      }

      draft.rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <!-- Identifier + search + hidden user_id -->
          <td class="px-3 py-2">
            <div class="flex items-center space-x-1">
              <input
                type="text"
                name="identifier[]"
                class="flex-1 border border-gray-300 rounded-lg p-1.5 text-xs identifier-input
                       focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
                placeholder="Student/Employee #"
                value="${row.identifier ? escapeHtml(row.identifier) : ''}"
              >
              <button
                type="button"
                class="px-2 py-1 bg-green-600 text-white rounded text-xs search-row-btn"
                title="Search user by ID"
              >
                <i class="fas fa-search"></i>
              </button>
            </div>
            <input
              type="hidden"
              name="bulk_user_id[]"
              class="bulk-user-id"
              value="${row.bulk_user_id ? escapeHtml(row.bulk_user_id) : ''}"
            >
            <p class="text-[10px] text-gray-400 mt-1"></p>
          </td>

          <!-- First Name -->
          <td class="px-3 py-2">
            <input
              type="text"
              name="first_name[]"
              class="w-full border border-gray-300 rounded-lg p-2 text-sm first-name-input
                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
              value="${row.first_name ? escapeHtml(row.first_name) : ''}"
              required
            >
          </td>

          <!-- Middle Name -->
          <td class="px-3 py-2">
            <input
              type="text"
              name="middle_name[]"
              class="w-full border border-gray-300 rounded-lg p-2 text-sm middle-name-input
                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
              value="${row.middle_name ? escapeHtml(row.middle_name) : ''}"
            >
          </td>

          <!-- Last Name -->
          <td class="px-3 py-2">
            <input
              type="text"
              name="last_name[]"
              class="w-full border border-gray-300 rounded-lg p-2 text-sm last-name-input
                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
              value="${row.last_name ? escapeHtml(row.last_name) : ''}"
              required
            >
          </td>

          <!-- Party List -->
          <td class="px-3 py-2">
            <input
              type="text"
              name="party_list[]"
              class="w-full border border-gray-300 rounded-lg p-2 text-sm
                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
              value="${row.party_list ? escapeHtml(row.party_list) : ''}"
            >
          </td>

          <!-- Profile Picture -->
          <td class="px-3 py-2">
            <input
              type="file"
              name="profile_picture[]"
              accept="image/jpeg,image/png,image/jpg"
              class="w-full text-xs text-gray-600"
            >
            <p class="text-[10px] text-gray-400 mt-1">JPG/PNG up to 2MB</p>
          </td>

          <!-- Credentials PDF -->
          <td class="px-3 py-2">
            <input
              type="file"
              name="credentials_pdf[]"
              accept=".pdf"
              class="w-full text-xs text-gray-600"
            >
            <p class="text-[10px] text-gray-400 mt-1">PDF up to 2MB</p>
          </td>

          <!-- Action -->
          <td class="px-3 py-2 text-center">
            <button
              type="button"
              class="text-red-600 hover:text-red-800 inline-flex items-center justify-center removeRowBtn"
            >
              <i class="fas fa-trash-alt"></i>
            </button>
          </td>
        `;

        candidateBody.appendChild(tr);

        // If row was linked to a user, lock first/last name
        if (row.bulk_user_id) {
          const fnInput = tr.querySelector('.first-name-input');
          const lnInput = tr.querySelector('.last-name-input');
          if (fnInput) {
            fnInput.readOnly = true;
            fnInput.classList.add('readonly-name');
          }
          if (lnInput) {
            lnInput.readOnly = true;
            lnInput.classList.add('readonly-name');
          }
        }
      });
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    // ====== ROW CREATION/REMOVAL ======
    function createCandidateRow() {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <!-- Identifier + search + hidden user_id -->
        <td class="px-3 py-2">
          <div class="flex items-center space-x-1">
            <input
              type="text"
              name="identifier[]"
              class="flex-1 border border-gray-300 rounded-lg p-1.5 text-xs identifier-input
                     focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
              placeholder="Student/Employee #"
            >
            <button
              type="button"
              class="px-2 py-1 bg-green-600 text-white rounded text-xs search-row-btn"
              title="Search user by ID"
            >
              <i class="fas fa-search"></i>
            </button>
          </div>
          <input
            type="hidden"
            name="bulk_user_id[]"
            class="bulk-user-id"
            value=""
          >
          <p class="text-[10px] text-gray-400 mt-1"></p>
        </td>

        <!-- First Name -->
        <td class="px-3 py-2">
          <input
            type="text"
            name="first_name[]"
            class="w-full border border-gray-300 rounded-lg p-2 text-sm first-name-input
                   focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
            required
          >
        </td>

        <!-- Middle Name -->
        <td class="px-3 py-2">
          <input
            type="text"
            name="middle_name[]"
            class="w-full border border-gray-300 rounded-lg p-2 text-sm middle-name-input
                   focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
          >
        </td>

        <!-- Last Name -->
        <td class="px-3 py-2">
          <input
            type="text"
            name="last_name[]"
            class="w-full border border-gray-300 rounded-lg p-2 text-sm last-name-input
                   focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
            required
          >
        </td>

        <!-- Party List -->
        <td class="px-3 py-2">
          <input
            type="text"
            name="party_list[]"
            class="w-full border border-gray-300 rounded-lg p-2 text-sm
                   focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
          >
        </td>

        <!-- Profile Picture -->
        <td class="px-3 py-2">
          <input
            type="file"
            name="profile_picture[]"
            accept="image/jpeg,image/png,image/jpg"
            class="w-full text-xs text-gray-600"
          >
          <p class="text-[10px] text-gray-400 mt-1">JPG/PNG up to 2MB</p>
        </td>

        <!-- Credentials PDF -->
        <td class="px-3 py-2">
          <input
            type="file"
            name="credentials_pdf[]"
            accept=".pdf"
            class="w-full text-xs text-gray-600"
          >
          <p class="text-[10px] text-gray-400 mt-1">PDF up to 2MB</p>
        </td>

        <!-- Action -->
        <td class="px-3 py-2 text-center">
          <button
            type="button"
            class="text-red-600 hover:text-red-800 inline-flex items-center justify-center removeRowBtn"
          >
            <i class="fas fa-trash-alt"></i>
          </button>
        </td>
      `;
      return tr;
    }

    // ====== SEARCH HANDLER (PER ROW) ======
    function handleRowSearch(tr) {
      const identifierInput = tr.querySelector('.identifier-input');
      const userIdInput     = tr.querySelector('.bulk-user-id');
      const fnInput         = tr.querySelector('.first-name-input');
      const lnInput         = tr.querySelector('.last-name-input');
      const mnInput         = tr.querySelector('.middle-name-input');

      if (!identifierInput) return;

      const identifier = identifierInput.value.trim();
      if (!identifier) {
        showJsAlert('Please enter a student/employee number before searching.', 'error');
        return;
      }

      fetch(SEARCH_URL + '?identifier=' + encodeURIComponent(identifier))
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (userIdInput) userIdInput.value = data.user.user_id;
            if (fnInput) {
              fnInput.value = data.user.first_name || '';
              fnInput.readOnly = true;
              fnInput.classList.add('readonly-name');
            }
            if (lnInput) {
              lnInput.value = data.user.last_name || '';
              lnInput.readOnly = true;
              lnInput.classList.add('readonly-name');
            }
            if (mnInput) {
              mnInput.value = ''; // optional
            }

            showJsAlert(
              'User found: ' +
              (data.user.first_name || '') +
              ' ' +
              (data.user.last_name || ''),
              'success'
            );

            // Save after search fill
            saveDraftFromDOM();
          } else {
            if (userIdInput) userIdInput.value = '';
            showJsAlert(data.message || 'User not found for this identifier.', 'error');
          }
        })
        .catch(() => {
          showJsAlert('Error searching user. Please try again.', 'error');
        });
    }

    // ====== DOM READY ======
    document.addEventListener('DOMContentLoaded', function () {
      const addRowBtn         = document.getElementById('addRowBtn');
      const candidateBody     = document.getElementById('candidateBody');
      const bulkCandidateForm = document.getElementById('bulkCandidateForm');
      const electionSelect    = document.getElementById('election_id');
      const positionSelect    = document.getElementById('position_id');

      // If server-side success, clear draft
      if (HAS_SUCCESS) {
        clearDraft();
      } else if (!HAS_SERVER_POST) {
        // Only auto-apply draft when page is loaded fresh / no server POST
        const draft = loadDraft();
        if (draft) {
          applyDraftToDOM(draft);
        }
      }

      // If after applying draft we ended up with no rows, ensure we have at least 1 row
      if (candidateBody && candidateBody.querySelectorAll('tr').length === 0) {
        const row = createCandidateRow();
        candidateBody.appendChild(row);
      }

      // If any rows already have linked user_id, lock their names
      if (candidateBody) {
        candidateBody.querySelectorAll('tr').forEach(tr => {
          const userIdInput = tr.querySelector('.bulk-user-id');
          const fnInput     = tr.querySelector('.first-name-input');
          const lnInput     = tr.querySelector('.last-name-input');
          if (userIdInput && userIdInput.value) {
            if (fnInput) {
              fnInput.readOnly = true;
              fnInput.classList.add('readonly-name');
            }
            if (lnInput) {
              lnInput.readOnly = true;
              lnInput.classList.add('readonly-name');
            }
          }
        });
      }

      // Add row
      if (addRowBtn && candidateBody) {
        addRowBtn.addEventListener('click', function () {
          const row = createCandidateRow();
          candidateBody.appendChild(row);
          saveDraftFromDOM();
        });
      }

      // Event delegation for remove/search and change/input events
      if (candidateBody) {
        candidateBody.addEventListener('click', function (e) {
          // Delete row
          const delBtn = e.target.closest('.removeRowBtn');
          if (delBtn) {
            const rows = candidateBody.querySelectorAll('tr');
            if (rows.length <= 1) {
              showJsAlert('At least one row is required.', 'error');
              return;
            }
            const tr = delBtn.closest('tr');
            if (tr) tr.remove();
            saveDraftFromDOM();
            return;
          }

          // Search user
          const searchBtn = e.target.closest('.search-row-btn');
          if (searchBtn) {
            const tr = searchBtn.closest('tr');
            handleRowSearch(tr);
            return;
          }
        });

        // Any change/input should update draft.
        candidateBody.addEventListener('input', function (e) {
          const target = e.target;

          // If user edits identifier manually, we can choose to unlock names (optional)
          if (target.classList.contains('identifier-input')) {
            const tr          = target.closest('tr');
            const userIdInput = tr.querySelector('.bulk-user-id');
            const fnInput     = tr.querySelector('.first-name-input');
            const lnInput     = tr.querySelector('.last-name-input');

            if (userIdInput && userIdInput.value) {
              // If identifier changed after being linked, unlock names and clear user link
              userIdInput.value = '';
              if (fnInput) {
                fnInput.readOnly = false;
                fnInput.classList.remove('readonly-name');
              }
              if (lnInput) {
                lnInput.readOnly = false;
                lnInput.classList.remove('readonly-name');
              }
            }
          }

          saveDraftFromDOM();
        });

        candidateBody.addEventListener('change', function () {
          saveDraftFromDOM();
        });
      }

      // Save draft when election/position changes
      if (electionSelect) {
        electionSelect.addEventListener('change', saveDraftFromDOM);
      }
      if (positionSelect) {
        positionSelect.addEventListener('change', saveDraftFromDOM);
      }

      // ====== FORM VALIDATION ON SUBMIT ======
      if (bulkCandidateForm) {
        bulkCandidateForm.addEventListener('submit', function (e) {
          let valid = true;

          if (electionSelect) electionSelect.classList.remove('error-border');
          if (positionSelect) positionSelect.classList.remove('error-border');

          if (!electionSelect || !electionSelect.value.trim()) {
            valid = false;
            if (electionSelect) electionSelect.classList.add('error-border');
          }
          if (!positionSelect || !positionSelect.value.trim()) {
            valid = false;
            if (positionSelect) positionSelect.classList.add('error-border');
          }

          // Require at least one row with first + last name
          const firstInputs = bulkCandidateForm.querySelectorAll('.first-name-input');
          const lastInputs  = bulkCandidateForm.querySelectorAll('.last-name-input');

          let hasValidRow = false;
          firstInputs.forEach((fnInput, idx) => {
            const lnInput = lastInputs[idx];
            const fnVal = fnInput.value.trim();
            const lnVal = lnInput ? lnInput.value.trim() : '';
            if (fnVal !== '' && lnVal !== '') {
              hasValidRow = true;
            }
          });

          if (!hasValidRow) {
            valid = false;
            showJsAlert('Please enter at least one candidate with both first and last name.', 'error');
          }

          if (!valid) {
            e.preventDefault();
            showJsAlert('Please complete required fields before submitting.', 'error');
          } else {
            // If submit is going through, we keep draft until server responds successfully.
            // Server-side success will clear draft on next load (HAS_SUCCESS).
          }
        });
      }
    });
  </script>
</body>
</html>
