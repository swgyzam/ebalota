<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'config.php';
// --- DB Connection ---
 $host = 'localhost';
 $db   = 'evoting_system';
 $user = 'root';
 $pass = '';
 $charset = 'utf8mb4';
 $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
 $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create disabled_default_positions table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS disabled_default_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        position_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_admin_position (admin_id, position_name)
    )");
    
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
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

// --- Get current admin info (+ scope category) ---
 $userId = $_SESSION['user_id'];
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
        // If you ever need scope_details in this file, you can decode here:
        // $myScopeDetails = json_decode($scopeRow['scope_details'] ?? '[]', true) ?: [];
    }
}

// --- Create upload directories if they don't exist ---
if (!file_exists(PROFILE_PIC_DIR)) mkdir(PROFILE_PIC_DIR, 0755, true);
if (!file_exists(CREDENTIALS_DIR)) mkdir(CREDENTIALS_DIR, 0755, true);

// --- Handle Form Submission ---
 $errors = [];
 $success = '';
 $first_name = $middle_name = $last_name = $position = $party_list = '';
 $election_id = '';
 $position_id = '';
 $user_id = '';
 $identifier = '';

// File handling
 $profile_pic_path = '';
 $credentials_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $election_id = $_POST['election_id'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $position_id = $_POST['position_id'] ?? '';
    $party_list = trim($_POST['party_list'] ?? '');
    $user_id = $_POST['user_id'] ?? '';
    $identifier = $_POST['identifier'] ?? '';
    
    // Validate required fields
    if (empty($election_id)) $errors[] = "Election selection is required.";
    if (empty($first_name)) $errors[] = "First Name is required.";
    if (empty($last_name)) $errors[] = "Last Name is required.";
    if (empty($position_id)) $errors[] = "Position is required.";
    
    // Validate file uploads
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] != 0) {
        $errors[] = "Profile picture is required.";
    } else {
        // Handle Profile Picture Upload
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        // Check file type
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $errors[] = "Only JPG, JPEG, and PNG images are allowed for profile picture.";
        } 
        // Check file size
        elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $errors[] = "Profile picture is too large. Maximum size is 2MB.";
        } 
        // Check if file is actually an image
        elseif (!getimagesize($_FILES['profile_picture']['tmp_name'])) {
            $errors[] = "Uploaded file is not a valid image.";
        }
        else {
            $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $file_name = 'candidate_' . time() . '.' . $file_ext;
            $target_file = PROFILE_PIC_DIR . $file_name;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_pic_path = 'uploads/profile_pictures/' . $file_name;
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }
    }
    
    if (!isset($_FILES['credentials_pdf']) || $_FILES['credentials_pdf']['error'] != 0) {
        $errors[] = "Credentials PDF is required.";
    } else {
        // Handle Credentials PDF Upload
        $allowed_types = ['application/pdf'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        // Check file type
        if (!in_array($_FILES['credentials_pdf']['type'], $allowed_types)) {
            $errors[] = "Only PDF files are allowed for credentials.";
        } 
        // Check file size
        elseif ($_FILES['credentials_pdf']['size'] > $max_size) {
            $errors[] = "Credentials file is too large. Maximum size is 2MB.";
        }
        // Additional check for file extension
        elseif (strtolower(pathinfo($_FILES['credentials_pdf']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            $errors[] = "Only PDF files are allowed for credentials.";
        }
        else {
            $file_ext = 'pdf';
            $file_name = 'credentials_' . time() . '.' . $file_ext;
            $target_file = CREDENTIALS_DIR . $file_name;
            
            if (move_uploaded_file($_FILES['credentials_pdf']['tmp_name'], $target_file)) {
                $credentials_path = 'uploads/credentials/' . $file_name;
            } else {
                $errors[] = "Failed to upload credentials.";
            }
        }
    }
    
    // Insert into database if no errors
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into candidates table with user_id and identifier
            $stmt = $pdo->prepare("INSERT INTO candidates (user_id, identifier, first_name, middle_name, last_name, party_list, photo, credentials, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id ? $user_id : null,
                $identifier,
                $first_name, 
                $middle_name, 
                $last_name,
                $party_list,
                $profile_pic_path, 
                $credentials_path, 
                $_SESSION['user_id']
            ]);
            
            $candidate_id = $pdo->lastInsertId();
            
            // Handle position (either default or custom)
            $positionValue = $_POST['position_id'];
            $positionName = '';
            $positionId = null;
            
            if (strpos($positionValue, 'default_') === 0) {
                // It's a default position
                $positionName = urldecode(substr($positionValue, 8)); // Remove 'default_' prefix and decode
            } else {
                // It's a custom position
                $positionId = $positionValue;
                // Get the position name
                $stmt = $pdo->prepare("SELECT position_name FROM positions WHERE id = ?");
                $stmt->execute([$positionId]);
                $result = $stmt->fetch();
                $positionName = $result ? $result['position_name'] : '';
            }
            
            // Insert into election_candidates table
            $stmt = $pdo->prepare("INSERT INTO election_candidates (election_id, candidate_id, position, position_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$election_id, $candidate_id, $positionName, $positionId]);
            
            // Commit transaction
            $pdo->commit();

            // --- Activity Log: Candidate created ---
            try {
                $adminId    = (int)$userId;
                $fullName   = trim($first_name . ' ' . ($middle_name ?: '') . ' ' . $last_name);
                $fullName   = $fullName !== '' ? $fullName : 'Unnamed Candidate';

                // Get election title for nicer log message
                $electionTitle = '';
                $titleStmt = $pdo->prepare("SELECT title FROM elections WHERE election_id = ?");
                $titleStmt->execute([$election_id]);
                $titleRow = $titleStmt->fetch();
                if ($titleRow && !empty($titleRow['title'])) {
                    $electionTitle = $titleRow['title'];
                }

                // Build action text
                $actionText = 'Created candidate: ' . $fullName .
                              ' (ID: ' . $candidate_id . ')';

                if ($electionTitle !== '') {
                    $actionText .= ' for election: ' . $electionTitle .
                                  ' (Election ID: ' . $election_id . ')';
                } else {
                    $actionText .= ' for election ID: ' . $election_id;
                }

                if ($positionName !== '') {
                    $actionText .= ' as position: ' . $positionName;
                }

                if ($adminId > 0) {
                    $logStmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, timestamp)
                        VALUES (:uid, :action, NOW())
                    ");
                    $logStmt->execute([
                        ':uid'    => $adminId,
                        ':action' => $actionText,
                    ]);
                }
            } catch (PDOException $logEx) {
                // Huwag ihagis sa user, log lang silently
                error_log('Activity log error (add_candidate.php): ' . $logEx->getMessage());
            }

            $success = "Candidate added successfully.";
            
            // Clear form values
            $first_name = $middle_name = $last_name = $position = $party_list = '';
            $election_id = '';
            $position_id = '';
            $user_id = '';
            $identifier = '';

            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error adding candidate: " . $e->getMessage();
        }
    }
}

// --- Fetch elections for dropdown (seat-aware) ---
if ($myScopeId !== null && !empty($scopeCategory)) {
    // New behavior: elections tied to this admin's seat OR explicitly assigned to this admin
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
    // Fallback for legacy/edge cases (no seat found): old behavior
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
 $customPositionsStmt = $pdo->prepare("SELECT id, position_name FROM positions WHERE created_by = :userId ORDER BY position_name ASC");
 $customPositionsStmt->execute([':userId' => $userId]);
 $customPositions = $customPositionsStmt->fetchAll();

// --- Fetch disabled default positions ---
try {
    $disabledPositionsStmt = $pdo->prepare("SELECT position_name FROM disabled_default_positions WHERE admin_id = :userId");
    $disabledPositionsStmt->execute([':userId' => $userId]);
    $disabledPositions = $disabledPositionsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    // If table doesn't exist, initialize as empty array
    $disabledPositions = [];
}

// If no elections found, show message
if (empty($elections)) {
    $errors[] = "No elections are assigned to you. Please contact the super admin.";
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Candidate - Admin Panel</title>
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
    
    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }
    
    .form-input:focus {
      transform: translateY(-1px);
      box-shadow: 0 4px 20px rgba(30, 111, 70, 0.1);
    }
    
    .step-indicator {
      position: relative;
    }
    
    .step-indicator::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 100%;
      width: 50px;
      height: 2px;
      background: #e5e7eb;
      transform: translateY(-50%);
    }
    
    .step-indicator.active::after {
      background: var(--cvsu-green);
    }
    
    .step-indicator:last-child::after {
      display: none;
    }
    
    .disabled-select {
      background-color: #f3f4f6;
      cursor: not-allowed;
    }
    
    /* Custom Dropdown Styles */
    #positionOptions {
      z-index: 50;
    }
    
    #positionOptions .px-4 {
      transition: background-color 0.2s ease;
    }
    
    #positionOptions .px-4:hover {
      background-color: #f9fafb;
    }
    
    #positionOptions .group:hover .opacity-0 {
      opacity: 1;
    }
    
    /* Floating label adjustment for custom dropdown */
    .input-group.relative .floating-label {
      z-index: 10;
    }
    
    /* Custom dropdown arrow transition */
    #positionDropdown .fa-chevron-down {
      transition: transform 0.3s ease;
    }
    
    #positionDropdown.active .fa-chevron-down {
      transform: translateY(-50%) rotate(180deg);
    }
    
    /* Visual indicators for auto-filled fields */
    .auto-filled {
      background-color: #f0f9ff;
      border-color: #3b82f6;
    }
    
    .editable {
      background-color: white;
      border-color: #d1d5db;
    }
    
    /* Error styling */
    .error-border {
      border-color: #ef4444 !important;
    }
    
    .error-text {
      color: #ef4444;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }
    .btn-yellow {
      background-color: var(--cvsu-yellow);
      color: #154734; /* dark green text para readable */
    }
    .btn-yellow:hover {
      background-color: #e6c652; /* slightly darker yellow on hover */
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
  <!-- Background Pattern -->
  <div class="fixed inset-0 opacity-5 pointer-events-none">
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"2\" fill=\"%23154734\"/></svg>'); background-size: 20px 20px;"></div>
  </div>
  
  <div class="relative z-10 min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-2xl">
      <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between">
          <!-- LEFT: title -->
          <div class="flex items-center space-x-4">
            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
              <i class="fas fa-user-plus text-xl"></i>
            </div>
            <div>
              <h1 class="text-3xl font-bold">Add New Candidate</h1>
              <p class="text-green-100 mt-1">Complete the form to register a new candidate</p>
            </div>
          </div>

          <!-- RIGHT: buttons side-by-side -->
          <div class="flex items-center space-x-3">
            <!-- Bulk Upload = yellow -->
            <a href="bulk_add_candidates.php"
              class="flex items-center space-x-2 px-6 py-3 rounded-lg transition-all duration-200 backdrop-blur-sm btn-yellow">
              <i class="fas fa-users"></i>
              <span>Bulk Upload</span>
            </a>

            <!-- Back button (same as before) -->
            <a href="admin_manage_candidates.php"
              class="flex items-center space-x-2 px-6 py-3 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-all duration-200 backdrop-blur-sm">
              <i class="fas fa-arrow-left"></i>
              <span>Back to Candidates</span>
            </a>
          </div>
        </div>
      </div>
    </header>
    
    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-6 py-12">
      
      <!-- Progress Steps -->
      <div class="mb-12">
        <div class="flex items-center justify-center space-x-4">
          <div class="step-indicator active flex items-center">
            <div class="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
              1
            </div>
            <span class="ml-3 text-green-600 font-medium">Basic Info</span>
          </div>
          <div class="step-indicator flex items-center">
            <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">
              2
            </div>
            <span class="ml-3 text-gray-500 font-medium">Documents</span>
          </div>
        </div>
      </div>
      
      <!-- Alert Messages -->
      <div id="alertContainer" class="mb-8">
        <?php if ($success): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl border flex items-center space-x-3">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
          </div>
        <?php endif; ?>
        
        <?php if ($errors): ?>
          <?php foreach ($errors as $error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl border flex items-center space-x-3 mb-2">
              <i class="fas fa-exclamation-triangle"></i>
              <span><?php echo $error; ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      
      <!-- Form Card -->
      <div class="glass-card rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-8">
          <?php if (empty($elections)): ?>
            <!-- No Elections Assigned Message -->
            <div class="text-center py-12">
              <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
              </div>
              <h3 class="text-xl font-bold text-gray-800 mb-2">No Elections Assigned</h3>
              <p class="text-gray-600 mb-6">You don't have any elections assigned to you. Please contact the super admin to get election assignments.</p>
              <a href="admin_manage_candidates.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Candidates
              </a>
            </div>
          <?php else: ?>
            <form id="candidateForm" method="POST" enctype="multipart/form-data" class="space-y-8" novalidate>
              <!-- Section 1: Basic Information -->
              <div class="form-section active" id="section1">
                <div class="flex items-center mb-6">
                  <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-user text-green-600"></i>
                  </div>
                  <h2 class="text-2xl font-bold text-gray-800">Basic Information</h2>
                </div>
                
                <div class="grid md:grid-cols-2 gap-6">
                  <!-- Election Selection -->
                  <div class="md:col-span-2">
                    <div class="input-group relative">
                      <select name="election_id" id="election_id" required class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 bg-white">
                        <option value="">Select Election</option>
                        <?php foreach ($elections as $election): ?>
                          <option value="<?= htmlspecialchars($election['election_id']) ?>" <?= ($election_id == $election['election_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($election['title']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                        Election <span class="text-red-500">*</span>
                      </label>
                    </div>
                  </div>
                  
                  <!-- User Search Section -->
                  <div class="md:col-span-2">
                    <div class="input-group relative">
                      <div class="flex items-center space-x-2">
                        <input type="text" id="identifier" name="identifier" placeholder="Enter Student Number or Employee Number" 
                               class="form-input flex-1 p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200">
                        <button type="button" onclick="searchUser()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition-all duration-200">
                          <i class="fas fa-search mr-2"></i> Search
                        </button>
                        <button type="button" onclick="clearSearch()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl transition-all duration-200">
                          <i class="fas fa-times mr-2"></i> Clear
                        </button>
                      </div>
                      <div id="searchMessage" class="mt-2 text-sm hidden"></div>
                    </div>
                  </div>
                  
                  <!-- Hidden user_id field -->
                  <input type="hidden" id="user_id" name="user_id" value="">
                  
                  <!-- First Name -->
                  <div>
                    <div class="input-group relative">
                      <input type="text" name="first_name" id="first_name" required value="<?= htmlspecialchars($first_name) ?>" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer">
                      <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                        First Name <span class="text-red-500">*</span>
                      </label>
                    </div>
                  </div>
                  
                  <!-- Middle Name -->
                  <div>
                    <div class="input-group relative">
                      <input type="text" name="middle_name" id="middle_name" value="<?= htmlspecialchars($middle_name) ?>" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer">
                      <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                        Middle Name
                      </label>
                    </div>
                  </div>
                  
                  <!-- Last Name and Position in one row -->
                  <div class="md:col-span-2">
                    <div class="grid grid-cols-2 gap-6">
                      <!-- Last Name -->
                      <div>
                        <div class="input-group relative">
                          <input type="text" name="last_name" id="last_name" required value="<?= htmlspecialchars($last_name) ?>" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer">
                          <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                            Last Name <span class="text-red-500">*</span>
                          </label>
                        </div>
                      </div>
                      
                      <!-- Position Selection -->
                      <div>
                        <div class="input-group relative">
                          <div class="flex items-center space-x-2">
                            <div class="relative flex-1">
                              <input type="hidden" name="position_id" id="position_id" required>
                              <div id="positionDropdown" class="form-input p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 bg-white cursor-pointer" onclick="togglePositionDropdown()">
                                <span id="selectedPositionText" class="text-gray-500">Select Position</span>
                                <i class="fas fa-chevron-down absolute right-4 top-1/2 transform -translate-y-1/2"></i>
                              </div>
                              
                              <!-- Dropdown Options -->
                              <div id="positionOptions" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg hidden max-h-60 overflow-y-auto">
                                <!-- Default Positions -->
                                <?php
                                $defaultPositions = ['President', 'Vice President', 'Secretary', 'Treasurer', 'Auditor', 'Public Relations Officer', 'Senator', 'Representative'];
                                
                                foreach ($defaultPositions as $position):
                                  // Skip if this position is disabled for this admin
                                  if (in_array($position, $disabledPositions)) continue;
                                  
                                  $value = 'default_'.urlencode($position);
                                  $selected = ($position_id == $value) ? 'selected' : '';
                                ?>
                                  <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-center justify-between group" onclick="selectPosition('<?= $value ?>', '<?= htmlspecialchars($position) ?>')">
                                    <span><?= htmlspecialchars($position) ?></span>
                                    <button type="button" onclick="event.stopPropagation(); confirmDeletePosition('<?= $value ?>', '<?= htmlspecialchars($position) ?>', true)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity">
                                      <i class="fas fa-trash-alt"></i>
                                    </button>
                                  </div>
                                <?php endforeach; ?>
                                
                                <!-- Custom Positions -->
                                <?php if (!empty($customPositions)): ?>
                                  <div class="border-t border-gray-200 my-1"></div>
                                  
                                  <?php foreach ($customPositions as $position): ?>
                                    <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-center justify-between group" onclick="selectPosition('<?= $position['id'] ?>', '<?= htmlspecialchars($position['position_name']) ?>')">
                                      <span><?= htmlspecialchars($position['position_name']) ?></span>
                                      <button type="button" onclick="event.stopPropagation(); confirmDeletePosition(<?= $position['id'] ?>, '<?= htmlspecialchars($position['position_name']) ?>', false)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fas fa-trash-alt"></i>
                                      </button>
                                    </div>
                                  <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <!-- Add New Position Option -->
                                <div class="border-t border-gray-200 mt-1">
                                  <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer text-green-600 font-medium flex items-center" onclick="showAddPositionModal()">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add New Position
                                  </div>
                                </div>
                              </div>
                            </div>
                            
                            <button type="button" onclick="showAddPositionModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                              <i class="fas fa-plus mr-1"></i> Add New
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Party List -->
                  <div>
                    <div class="input-group relative">
                      <input type="text" name="party_list" id="party_list" value="<?= htmlspecialchars($party_list) ?>" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer">
                      <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                        Party List (Optional)
                      </label>
                    </div>
                  </div>
                  
                  <!-- Field Helper Text -->
                  <div id="fieldHelper" class="md:col-span-2 text-sm text-gray-500 hidden">
                    <i class="fas fa-info-circle"></i> 
                    First name and last name are auto-filled from user record. Middle name can be edited.
                  </div>
                </div>
                
                <div class="flex justify-end mt-8">
                  <button type="button" onclick="nextSection()" class="flex items-center space-x-2 px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <span>Next</span>
                    <i class="fas fa-arrow-right"></i>
                  </button>
                </div>
              </div>
              
              <!-- Section 2: Documents -->
              <div class="form-section hidden" id="section2">
                <div class="flex items-center mb-6">
                  <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-file-upload text-blue-600"></i>
                  </div>
                  <h2 class="text-2xl font-bold text-gray-800">Candidate Documents</h2>
                </div>
                
                <div class="space-y-6">
                  <!-- Profile Picture Upload -->
                  <div>
                    <label class="block text-gray-700 font-medium mb-2">Profile Picture <span class="text-red-500">*</span></label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-green-400 transition-colors">
                      <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/jpg,image/png" class="hidden" required>
                      <label for="profile_picture" class="cursor-pointer">
                        <div class="flex flex-col items-center justify-center">
                          <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                          <p class="text-gray-600">Click to upload profile picture</p>
                          <p class="text-sm text-gray-500 mt-1">JPG, JPEG, PNG up to 2MB</p>
                        </div>
                      </label>
                      <div id="profilePreview" class="mt-4 hidden">
                        <img src="" alt="Profile Preview" class="w-32 h-32 object-cover rounded-full mx-auto border-4 border-white shadow-lg">
                      </div>
                    </div>
                    <p id="profilePicError" class="error-text hidden"></p>
                  </div>
                  
                  <!-- Credentials PDF Upload -->
                  <div>
                    <label class="block text-gray-700 font-medium mb-2">Credentials PDF <span class="text-red-500">*</span></label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-green-400 transition-colors">
                      <input type="file" name="credentials_pdf" id="credentials_pdf" accept=".pdf" class="hidden" required>
                      <label for="credentials_pdf" class="cursor-pointer">
                        <div class="flex flex-col items-center justify-center">
                          <i class="fas fa-file-pdf text-4xl text-red-400 mb-3"></i>
                          <p class="text-gray-600">Click to upload credentials</p>
                          <p class="text-sm text-gray-500 mt-1">PDF up to 2MB</p>
                        </div>
                      </label>
                      <div id="pdfPreview" class="mt-4 hidden">
                        <div class="flex items-center justify-center bg-gray-100 p-3 rounded-lg">
                          <i class="fas fa-file-pdf text-red-500 text-2xl mr-3"></i>
                          <span id="pdfFileName" class="text-gray-700 font-medium"></span>
                        </div>
                      </div>
                    </div>
                    <p id="pdfError" class="error-text hidden"></p>
                  </div>
                </div>
                
                <div class="flex justify-between mt-8">
                  <button type="button" onclick="prevSection()" class="flex items-center space-x-2 px-8 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
                    <i class="fas fa-arrow-left"></i>
                    <span>Previous</span>
                  </button>
                  <button type="button" onclick="showConfirmationModal()" class="flex items-center space-x-2 px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-check"></i>
                    <span>Add Candidate</span>
                  </button>
                </div>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Add Position Modal -->
  <div id="addPositionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all duration-300">
      
      <!-- Header (centered) -->
      <div class="text-center">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-plus text-blue-600 text-2xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Add New Position</h3>
        <p class="text-gray-600 mb-6">
          Enter the details of the new position, including how many candidates a voter can select.
        </p>
      </div>

      <!-- FORM CONTENT (LEFT ALIGNED) -->
      <div class="text-left">

        <!-- Position Name -->
        <div class="mb-4">
          <label for="newPositionName" class="block text-sm font-medium text-gray-700 mb-1">
            Position Name <span class="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="newPositionName"
            placeholder="e.g., Senator, Board Member"
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
          >
        </div>
        
        <!-- Allow Multiple -->
        <div class="mb-3 flex items-center space-x-2">
          <input
            type="checkbox"
            id="newAllowMultiple"
            class="h-4 w-4 text-green-600 border-gray-300 rounded"
            onchange="toggleMaxVotes()"
          >
          <label 
            for="newAllowMultiple" 
            class="text-xs text-gray-700 whitespace-nowrap"
          >
            Allow voters to select <span class="font-semibold">multiple candidates</span> for this position
          </label>
        </div>
        <!-- Max Votes -->
        <div class="mb-6">
          <label for="newMaxVotes" class="block text-sm font-medium text-gray-700 mb-1">
            Max votes per voter <span class="text-red-500">*</span>
          </label>
          <input
            type="number"
            id="newMaxVotes"
            min="1"
            value="1"
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
          >
          <p class="text-xs text-gray-500 mt-1">
            Examples: Chairperson = 1, Board Members = 4, Senators = 12.
            If "Allow multiple" is unchecked, this will be forced to 1.
          </p>
        </div>
        
        <!-- Footer buttons -->
        <div class="flex space-x-3">
          <button
            type="button"
            onclick="hideAddPositionModal()"
            class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200"
          >
            Cancel
          </button>
          <button
            type="button"
            onclick="addNewPosition()"
            class="flex-1 px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg"
          >
            Add Position
          </button>
        </div>

      </div> <!-- END text-left -->
      
    </div>
  </div>
  
  <!-- Delete Position Modal -->
  <div id="deletePositionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all duration-300">
      <div class="text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-trash text-red-600 text-2xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Delete Position</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to delete the position "<span id="positionNameToDelete" class="font-semibold"></span>"? This action cannot be undone.</p>
        
        <div class="flex space-x-3">
          <button type="button" onclick="hideDeletePositionModal()" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
            Cancel
          </button>
          <button type="button" onclick="deletePosition()" class="flex-1 px-6 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 transition-all duration-200 shadow-lg">
            Delete Position
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Confirmation Modal -->
  <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all duration-300">
      <div class="text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-user-check text-green-600 text-2xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirm Registration</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to register this candidate? Please review all information before confirming.</p>
        
        <div class="flex space-x-3">
          <button type="button" onclick="hideConfirmationModal()" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
            Cancel
          </button>
          <button type="button" onclick="submitForm()" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg">
            Confirm
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    let currentSection = 1;
    const totalSections = 2;
    let positionIdToDelete = null;
    let positionNameToDelete = null;
    let isDefaultPosition = false;
    
    // Form navigation functions
    function showSection(sectionNum) {
      for (let i = 1; i <= totalSections; i++) {
        document.getElementById(`section${i}`).classList.add('hidden');
      }
      document.getElementById(`section${sectionNum}`).classList.remove('hidden');
      updateProgressIndicators(sectionNum);
    }
    
    function updateProgressIndicators(activeSection) {
      const indicators = document.querySelectorAll('.step-indicator');
      indicators.forEach((indicator, index) => {
        const stepNum = index + 1;
        const circle = indicator.querySelector('div');
        const text = indicator.querySelector('span');
        
        if (stepNum <= activeSection) {
          circle.classList.remove('bg-gray-300', 'text-gray-600');
          circle.classList.add('bg-green-600', 'text-white');
          text.classList.remove('text-gray-500');
          text.classList.add('text-green-600');
          indicator.classList.add('active');
        } else {
          circle.classList.remove('bg-green-600', 'text-white');
          circle.classList.add('bg-gray-300', 'text-gray-600');
          text.classList.remove('text-green-600');
          text.classList.add('text-gray-500');
          indicator.classList.remove('active');
        }
      });
    }
    
    function nextSection() {
      if (validateCurrentSection()) {
        if (currentSection < totalSections) {
          currentSection++;
          showSection(currentSection);
        }
      }
    }
    
    function prevSection() {
      if (currentSection > 1) {
        currentSection--;
        showSection(currentSection);
      }
    }
    
    function validateCurrentSection() {
      const currentSectionElement = document.getElementById(`section${currentSection}`);
      const requiredFields = currentSectionElement.querySelectorAll('[required]');
      let isValid = true;
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('error-border');
          isValid = false;
        } else {
          field.classList.remove('error-border');
        }
      });
      
      // Special validation for position dropdown
      const positionValue = document.getElementById('position_id').value;
      if (!positionValue) {
        document.getElementById('positionDropdown').classList.add('error-border');
        isValid = false;
      } else {
        document.getElementById('positionDropdown').classList.remove('error-border');
      }
      
      if (!isValid) {
        showAlert('Please fill all required fields before proceeding.', 'error');
      }
      
      return isValid;
    }
    
    // Modal functions
    function showConfirmationModal() {
      if (validateCurrentSection()) {
        // Additional validation for required files
        const profilePic = document.getElementById('profile_picture');
        const credentialsPdf = document.getElementById('credentials_pdf');
        
        if (profilePic.files.length === 0) {
          showAlert('Profile picture is required.', 'error');
          return;
        }
        
        if (credentialsPdf.files.length === 0) {
          showAlert('Credentials PDF is required.', 'error');
          return;
        }
        
        if (validateForm()) {
          document.getElementById('confirmationModal').classList.remove('hidden');
        }
      }
    }
    
    function hideConfirmationModal() {
      document.getElementById('confirmationModal').classList.add('hidden');
    }
    
    function submitForm() {
      document.getElementById('candidateForm').submit();
    }
    
    // Position dropdown functions
    function togglePositionDropdown() {
      const dropdown = document.getElementById('positionOptions');
      dropdown.classList.toggle('hidden');
      
      // Update chevron icon
      const chevron = document.querySelector('#positionDropdown .fa-chevron-down');
      if (dropdown.classList.contains('hidden')) {
        chevron.classList.remove('rotate-180');
      } else {
        chevron.classList.add('rotate-180');
      }
    }
    
    function selectPosition(value, text) {
      // Update hidden input
      document.getElementById('position_id').value = value;
      
      // Update display text
      document.getElementById('selectedPositionText').textContent = text;
      document.getElementById('selectedPositionText').classList.remove('text-gray-500');
      
      // Close dropdown
      document.getElementById('positionOptions').classList.add('hidden');
      
      // Trigger change event for validation
      document.getElementById('position_id').dispatchEvent(new Event('change'));
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const dropdown = document.getElementById('positionDropdown');
      const options = document.getElementById('positionOptions');
      
      if (!dropdown.contains(event.target)) {
        options.classList.add('hidden');
      }
    });
    
    // Position management functions
    function showAddPositionModal() {
      document.getElementById('addPositionModal').classList.remove('hidden');
      document.getElementById('newPositionName').focus();
    }
    
    function hideAddPositionModal() {
      document.getElementById('addPositionModal').classList.add('hidden');
      document.getElementById('newPositionName').value = '';
    }
    
    function addNewPosition() {
      const positionName   = document.getElementById('newPositionName').value.trim();
      const allowMultiple  = document.getElementById('newAllowMultiple').checked ? 1 : 0;
      let   maxVotes       = parseInt(document.getElementById('newMaxVotes').value, 10);

      // Basic validation
      if (!positionName) {
        showAlert('Please enter a position name', 'error');
        return;
      }

      if (isNaN(maxVotes) || maxVotes < 1) {
        showAlert('Please enter a valid number for max votes (minimum 1).', 'error');
        return;
      }

      // If single-select, force maxVotes = 1
      if (allowMultiple === 0) {
        maxVotes = 1;
      }

      fetch('save_position.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body:
          'position_name='   + encodeURIComponent(positionName) +
          '&allow_multiple=' + encodeURIComponent(allowMultiple) +
          '&max_votes='      + encodeURIComponent(maxVotes)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Add the new position to the dropdown
          const optionsContainer = document.querySelector('#positionOptions');
          
          // Check if custom positions divider exists
          let divider = optionsContainer.querySelector('.border-t');
          if (!divider) {
            divider = document.createElement('div');
            divider.className = 'border-t border-gray-200 my-1';
            optionsContainer.appendChild(divider);
          }
          
          // Create new position element
          const positionDiv = document.createElement('div');
          positionDiv.className = 'px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-center justify-between group';
          positionDiv.onclick = function() { selectPosition(data.position_id, positionName); };
          positionDiv.innerHTML = `
            <span>${positionName}</span>
            <button type="button" onclick="event.stopPropagation(); confirmDeletePosition(${data.position_id}, '${positionName}', false)" class="text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity">
              <i class="fas fa-trash-alt"></i>
            </button>
          `;
          
          // Insert before "Add New Position"
          const addNewContainer = optionsContainer.querySelector('.border-t:last-child');
          optionsContainer.insertBefore(positionDiv, addNewContainer);
          
          // Select the new position
          selectPosition(data.position_id, positionName);
          
          // Close modal
          hideAddPositionModal();
          
          // Show success message
          showAlert('Position added successfully!', 'success');
          
          // REFRESH THE PAGE
          setTimeout(() => {
            location.reload();
          }, 1000); // Refresh after 1 second
        } else {
          showAlert('Error: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while adding the position', 'error');
      });
    }

    
    function deletePosition() {
      // Walang dapat i-delete kung wala tayong naka-set na ID / name
      if (!positionIdToDelete && !positionNameToDelete) return;
      
      // DEFAULT POSITION (President, VP, etc.) → dinidisable lang via disabled_default_positions
      if (isDefaultPosition) {
        fetch('disable_default_position.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'position_name=' + encodeURIComponent(positionNameToDelete)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Remove sa dropdown (UI only)
            const optionToRemove = Array.from(
              document.querySelectorAll('#positionOptions .px-4')
            ).find(el => el.querySelector('span')?.textContent === positionNameToDelete);

            if (optionToRemove) {
              optionToRemove.remove();
            }

            // Kung selected yung dinelete, i-reset yung dropdown
            const selectedText = document.getElementById('selectedPositionText').textContent;
            if (selectedText === positionNameToDelete) {
              document.getElementById('position_id').value = '';
              document.getElementById('selectedPositionText').textContent = 'Select Position';
              document.getElementById('selectedPositionText').classList.add('text-gray-500');
            }

            hideDeletePositionModal();
            showAlert('Position disabled successfully!', 'success');

            // Para sure na malinis ang state, reload light
            setTimeout(() => {
              location.reload();
            }, 800);
          } else {
            showAlert('Error: ' + (data.message || 'Failed to disable position.'), 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('An error occurred while disabling the position', 'error');
        });

      // CUSTOM POSITION (galing "Add New") → totoong delete sa positions table
      } else {
        fetch('delete_position.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'position_id=' + encodeURIComponent(positionIdToDelete)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            hideDeletePositionModal();
            showAlert('Position deleted successfully!', 'success');

            // Hindi na tayo mag-try mag-manipulate ng DOM dito
            // (minsan doon nagkaka-error), reload na lang
            setTimeout(() => {
              location.reload();
            }, 800);
          } else {
            showAlert(data.message || 'Failed to delete the position.', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('An error occurred while deleting the position', 'error');
        });
      }
    }

    // Toggle max votes field based on "Allow multiple" checkbox
    function toggleMaxVotes() {
      const allowMultiple = document.getElementById('newAllowMultiple').checked;
      const maxVotesInput = document.getElementById('newMaxVotes');
      
      if (!allowMultiple) {
        maxVotesInput.value = 1;
        maxVotesInput.readOnly = true;
        maxVotesInput.classList.add('bg-gray-100', 'cursor-not-allowed');
      } else {
        maxVotesInput.readOnly = false;
        maxVotesInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
      }
    }
    
    function confirmDeletePosition(positionId, positionName, isDefault = false) {
      event.stopPropagation();
      positionIdToDelete = positionId;
      positionNameToDelete = positionName;
      isDefaultPosition = isDefault;
      
      document.getElementById('positionNameToDelete').textContent = positionName;
      document.getElementById('deletePositionModal').classList.remove('hidden');
    }
    
    function hideDeletePositionModal() {
      document.getElementById('deletePositionModal').classList.add('hidden');
      positionIdToDelete = null;
      positionNameToDelete = null;
      isDefaultPosition = false;
    }
    
    // Alert function
    function showAlert(message, type) {
      const alertContainer = document.getElementById('alertContainer');
      const alertClass = type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700';
      
      const alertDiv = document.createElement('div');
      alertDiv.className = `${alertClass} px-4 py-3 rounded-xl border flex items-center space-x-3`;
      alertDiv.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
        <span>${message}</span>
      `;
      
      alertContainer.appendChild(alertDiv);
      
      setTimeout(() => {
        alertDiv.remove();
      }, 5000);
    }
    
    // User search functions
    function searchUser() {
      const identifier = document.getElementById('identifier').value.trim();
      const searchMessage = document.getElementById('searchMessage');
      
      if (!identifier) {
        searchMessage.textContent = 'Please enter a student or employee number.';
        searchMessage.classList.remove('hidden', 'text-green-600');
        searchMessage.classList.add('text-red-600');
        return;
      }
      
      // Show loading
      searchMessage.textContent = 'Searching...';
      searchMessage.classList.remove('hidden', 'text-red-600', 'text-green-600');
      searchMessage.classList.add('text-blue-600');
      
      fetch('search_user.php?identifier=' + encodeURIComponent(identifier))
          .then(response => {
              if (!response.ok) {
                  throw new Error('Network response was not ok');
              }
              return response.json();
          })
          .then(data => {
              if (data.success) {
                  // Only populate first name and last name
                  document.getElementById('first_name').value = data.user.first_name;
                  document.getElementById('last_name').value = data.user.last_name;
                  
                  // Leave middle name empty and editable
                  document.getElementById('middle_name').value = '';
                  
                  // Set the user_id
                  document.getElementById('user_id').value = data.user.user_id;
                  
                  // Make first name and last name read-only, but keep middle name editable
                  document.getElementById('first_name').readOnly = true;
                  document.getElementById('last_name').readOnly = true;
                  document.getElementById('middle_name').readOnly = false;
                  
                  // Add visual classes
                  document.getElementById('first_name').classList.add('auto-filled');
                  document.getElementById('last_name').classList.add('auto-filled');
                  document.getElementById('middle_name').classList.remove('auto-filled');
                  document.getElementById('middle_name').classList.add('editable');
                  
                  // Show helper text
                  document.getElementById('fieldHelper').classList.remove('hidden');
                  
                  searchMessage.textContent = 'User found: ' + data.user.first_name + ' ' + data.user.last_name;
                  searchMessage.classList.remove('text-red-600', 'text-blue-600');
                  searchMessage.classList.add('text-green-600');
              } else {
                  searchMessage.textContent = data.message;
                  searchMessage.classList.remove('text-blue-600', 'text-green-600');
                  searchMessage.classList.add('text-red-600');
                  
                  // Clear the user_id and make all fields editable
                  document.getElementById('user_id').value = '';
                  document.getElementById('first_name').readOnly = false;
                  document.getElementById('last_name').readOnly = false;
                  document.getElementById('middle_name').readOnly = false;
                  
                  // Remove visual classes
                  document.getElementById('first_name').classList.remove('auto-filled');
                  document.getElementById('last_name').classList.remove('auto-filled');
                  document.getElementById('middle_name').classList.remove('auto-filled');
                  document.getElementById('middle_name').classList.add('editable');
                  
                  // Hide helper text
                  document.getElementById('fieldHelper').classList.add('hidden');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              searchMessage.textContent = 'An error occurred while searching. Please check the console for details.';
              searchMessage.classList.remove('text-blue-600', 'text-green-600');
              searchMessage.classList.add('text-red-600');
              
              // Clear the user_id and make all fields editable
              document.getElementById('user_id').value = '';
              document.getElementById('first_name').readOnly = false;
              document.getElementById('last_name').readOnly = false;
              document.getElementById('middle_name').readOnly = false;
              
              // Remove visual classes
              document.getElementById('first_name').classList.remove('auto-filled');
              document.getElementById('last_name').classList.remove('auto-filled');
              document.getElementById('middle_name').classList.remove('auto-filled');
              document.getElementById('middle_name').classList.add('editable');
              
              // Hide helper text
              document.getElementById('fieldHelper').classList.add('hidden');
          });
    }
    
    function clearSearch() {
      document.getElementById('identifier').value = '';
      document.getElementById('user_id').value = '';
      document.getElementById('first_name').value = '';
      document.getElementById('last_name').value = '';
      document.getElementById('middle_name').value = '';
      document.getElementById('first_name').readOnly = false;
      document.getElementById('last_name').readOnly = false;
      document.getElementById('middle_name').readOnly = false;
      
      // Remove visual classes
      document.getElementById('first_name').classList.remove('auto-filled');
      document.getElementById('last_name').classList.remove('auto-filled');
      document.getElementById('middle_name').classList.remove('auto-filled');
      document.getElementById('middle_name').classList.add('editable');
      
      document.getElementById('searchMessage').classList.add('hidden');
      document.getElementById('fieldHelper').classList.add('hidden');
    }
    
    // Form validation function
    function validateForm() {
      let isValid = true;
      
      // Validate profile picture
      const profilePic = document.getElementById('profile_picture');
      const profilePicError = document.getElementById('profilePicError');
      
      if (profilePic.files.length === 0) {
        profilePicError.textContent = 'Profile picture is required.';
        profilePicError.classList.remove('hidden');
        isValid = false;
      } else {
        const file = profilePic.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!allowedTypes.includes(file.type)) {
          profilePicError.textContent = 'Only JPG, JPEG, and PNG images are allowed for profile picture.';
          profilePicError.classList.remove('hidden');
          isValid = false;
        } else if (file.size > maxSize) {
          profilePicError.textContent = 'Profile picture is too large. Maximum size is 2MB.';
          profilePicError.classList.remove('hidden');
          isValid = false;
        } else {
          profilePicError.classList.add('hidden');
        }
      }
      
      // Validate PDF
      const credentialsPdf = document.getElementById('credentials_pdf');
      const pdfError = document.getElementById('pdfError');
      
      if (credentialsPdf.files.length === 0) {
        pdfError.textContent = 'Credentials PDF is required.';
        pdfError.classList.remove('hidden');
        isValid = false;
      } else {
        const file = credentialsPdf.files[0];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (file.type !== 'application/pdf') {
          pdfError.textContent = 'Only PDF files are allowed for credentials.';
          pdfError.classList.remove('hidden');
          isValid = false;
        } else if (file.size > maxSize) {
          pdfError.textContent = 'Credentials file is too large. Maximum size is 2MB.';
          pdfError.classList.remove('hidden');
          isValid = false;
        } else {
          pdfError.classList.add('hidden');
        }
      }
      
      return isValid;
    }
    
    // Initialize form
    document.addEventListener('DOMContentLoaded', function() {
      showSection(1);
      
      // Set initial selected position if any
      const selectedPositionId = "<?php echo htmlspecialchars($position_id); ?>";
      if (selectedPositionId) {
        // Get position name
        let positionName = '';
        if (selectedPositionId.startsWith('default_')) {
          positionName = decodeURIComponent(selectedPositionId.substring(8));
        } else {
          // This would need to be fetched from database
          // For simplicity, we'll just use the ID
          positionName = selectedPositionId;
        }
        
        document.getElementById('position_id').value = selectedPositionId;
        document.getElementById('selectedPositionText').textContent = positionName;
        document.getElementById('selectedPositionText').classList.remove('text-gray-500');
      }
      
      // Profile picture validation
      document.getElementById('profile_picture').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const profilePicError = document.getElementById('profilePicError');
        
        if (file) {
          // Check file type
          const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
          if (!allowedTypes.includes(file.type)) {
            profilePicError.textContent = 'Only JPG, JPEG, and PNG images are allowed for profile picture.';
            profilePicError.classList.remove('hidden');
            this.value = ''; // Clear the file input
            return;
          }
          
          // Check file size (2MB)
          const maxSize = 2 * 1024 * 1024;
          if (file.size > maxSize) {
            profilePicError.textContent = 'Profile picture is too large. Maximum size is 2MB.';
            profilePicError.classList.remove('hidden');
            this.value = ''; // Clear the file input
            return;
          }
          
          // Clear any previous errors
          profilePicError.classList.add('hidden');
          
          // Show preview
          const reader = new FileReader();
          reader.onload = function(e) {
            document.getElementById('profilePreview').classList.remove('hidden');
            document.querySelector('#profilePreview img').src = e.target.result;
          }
          reader.readAsDataURL(file);
        }
      });
      
      // PDF validation
      document.getElementById('credentials_pdf').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const pdfError = document.getElementById('pdfError');
        
        if (file) {
          // Check file type
          if (file.type !== 'application/pdf') {
            pdfError.textContent = 'Only PDF files are allowed for credentials.';
            pdfError.classList.remove('hidden');
            this.value = ''; // Clear the file input
            return;
          }
          
          // Check file extension as additional validation
          const fileName = file.name;
          const fileExtension = fileName.split('.').pop().toLowerCase();
          if (fileExtension !== 'pdf') {
            pdfError.textContent = 'Only PDF files are allowed for credentials.';
            pdfError.classList.remove('hidden');
            this.value = ''; // Clear the file input
            return;
          }
          
          // Check file size (2MB)
          const maxSize = 2 * 1024 * 1024;
          if (file.size > maxSize) {
            pdfError.textContent = 'Credentials file is too large. Maximum size is 2MB.';
            pdfError.classList.remove('hidden');
            this.value = ''; // Clear the file input
            return;
          }
          
          // Clear any previous errors
          pdfError.classList.add('hidden');
          
          // Show preview
          document.getElementById('pdfPreview').classList.remove('hidden');
          document.getElementById('pdfFileName').textContent = file.name;
        }
      });
    });
  </script>
</body>
</html>