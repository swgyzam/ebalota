<?php
session_start();
date_default_timezone_set('Asia/Manila');

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
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header('Location: login.php');
    exit();
}

// Get current admin info from session
 $adminRole     = $_SESSION['role'];
 $assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));
 $scopeCategory = $_SESSION['scope_category'] ?? ''; // e.g. Academic-Student, Academic-Faculty, Non-Academic-Student, Others-Default, Non-Academic-Employee

// Resolve this admin's scope seat (admin_scopes), if applicable
 $myScopeId   = null;
 $myScopeType = null;

if ($adminRole === 'admin' && !empty($scopeCategory)) {
    $scopeStmt = $pdo->prepare("
        SELECT scope_id, scope_type, scope_details
        FROM admin_scopes
        WHERE user_id   = :uid
          AND scope_type = :stype
        LIMIT 1
    ");
    $scopeStmt->execute([
        ':uid'   => $_SESSION['user_id'],
        ':stype' => $scopeCategory,
    ]);
    $scopeRow = $scopeStmt->fetch();
    if ($scopeRow) {
        $myScopeId   = (int)$scopeRow['scope_id'];
        $myScopeType = $scopeRow['scope_type'];
    }
}

// ---------------------------------------------------------------------
// Determine admin_type (compatible with process_users_csv.php)
// ---------------------------------------------------------------------
if ($adminRole === 'super_admin') {
    $adminType = 'super_admin';
} else if (in_array($assignedScope, [
    'CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS',
    'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'
])) {
    $adminType = 'admin_students';
} else if ($assignedScope === 'FACULTY ASSOCIATION') {
    $adminType = 'admin_academic';
} else if ($assignedScope === 'NON-ACADEMIC') {
    $adminType = 'admin_non_academic';
} else if ($assignedScope === 'COOP') {
    $adminType = 'admin_coop';
} else if ($assignedScope === 'CSG ADMIN') {
    $adminType = 'admin_students';
} else {
    $adminType = 'general_admin';
}

// NEW: refine adminType semantics using scope_category (override old assigned_scope logic)
if ($scopeCategory === 'Non-Academic-Student') {
    // Org-based student admins still use student CSV format
    $adminType = 'admin_students';
} elseif ($scopeCategory === 'Others-Default') {
    // Others-Default admins use COOP-like CSV format
    $adminType = 'admin_non_academic'; // Still use admin_non_academic for processing, but with custom format
} elseif ($scopeCategory === 'Academic-Faculty') {
    // Academic-Faculty admins should use faculty/academic CSV format
    $adminType = 'admin_academic';
} elseif ($scopeCategory === 'Non-Academic-Employee') {
    // Non-Academic-Employee admins use non-academic CSV format
    $adminType = 'admin_non_academic';
}

// ---------------------------------------------------------------------
// Build instructions + CSV examples
// ---------------------------------------------------------------------
 $instructions = '';
 $csvExample   = '';

switch ($adminType) {

    case 'admin_students':
        // Two sub-modes:
        // 1) College/Campus student admins (Academic-Student / CSG)
        // 2) Org-based Non-Academic-Student admins
        if ($scopeCategory === 'Non-Academic-Student') {
            // Non-Academic - Student Admin: org-based student voters
            $instructions = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Non-Academic Student Organization Members:</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing student members of <strong>your organization/scope</strong>.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, student_number, college, department, course</code>
                    </li>
                    <li><strong>position</strong> should be set to <code>student</code> for all rows.</li>
                    <li>All uploaded students will be added with role <code>voter</code> and position <code>student</code>.</li>
                    <li>These members will be tied to <strong>your scope</strong> (owner_scope_id) so only your org admin can manage them.</li>
                    <li><strong>Note:</strong> college/department/course are for reference and filtering; scope ownership will still follow your assigned org scope.</li>
                </ul>
            ';
            // Keep same column order as legacy student format (important for process_users_csv.php)
            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Non-Academic Student Org CSV Format Example:</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,college,department,course
Raven,Kyle,raven.kyle@example.com,student,202200151,CEIT,Department of Information Technology,BS Information Technology
Jam,Benito,jam.benito@example.com,student,202200146,CEIT,Department of Information Technology,BS Information Technology</pre>
            ';
        } else {
            // Default: college-level student admins / CSG ADMIN
            $instructions = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Student Voters:</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing student voters to add to the system.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, student_number, college, department, course</code>
                    </li>
                    <li><strong>position</strong> should be set to <code>student</code> for all rows.</li>
                    <li>Passwords will be automatically generated for each student.</li>
                    <li>Students will be added with the role <code>voter</code> and position <code>student</code>.</li>
                    <li>Only students that match your assigned college and course scope will be processed.</li>
                    <li><strong>Note:</strong> is_coop_member will automatically be set to 0.</li>
                </ul>
            ';
            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Student CSV Format Example:</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,college,department,course
John,Doe,john.doe@example.com,student,20231001,CEIT,Department of Computer and Electronics Engineering,BS Computer Science
Jane,Smith,jane.smith@example.com,student,20231002,CAS,Department of Biological Sciences,BS Psychology</pre>
            ';
        }
        break;

    case 'admin_academic':
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for Academic Voters (Faculty):</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing faculty members to add to the system.</li>
                <li>The CSV must have these columns in order:
                    <code>first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</code>
                </li>
                <li>Passwords will be automatically generated for each faculty member.</li>
                <li>Faculty will be added with the role <code>voter</code> and position <code>academic</code>.</li>
                <li>Only faculty from your assigned departments will be processed.</li>
            </ul>
        ';
        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">Academic CSV Format Example:</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,college,department,status,is_coop_member
John,Doe,john.doe@example.com,academic,1001,CEIT,Department of Computer and Electronics Engineering,regular,0
Jane,Smith,jane.smith@example.com,academic,1002,CAS,Department of Biological Sciences,regular,1</pre>
        ';
        break;

    case 'admin_non_academic':
        if ($scopeCategory === 'Others-Default') {
            // Others-Default: faculty + non-ac employees, with owner_scope_id & is_other_member
            $instructions = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Others - Default Admin (Faculty & Non-Academic Employees):</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing <strong>faculty and/or non-academic employees</strong> who belong to your scope.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, employee_number, college, department, status, is_other_member</code>
                    </li>
                    <li><strong>position</strong> should be <code>academic</code> for faculty and <code>non-academic</code> for staff.</li>
                    <li><strong>college</strong>:
                        <ul class="list-disc pl-5">
                            <li>For faculty: set to the college code (e.g., <code>CEIT</code>, <code>CAS</code>).</li>
                            <li>For non-academic staff: you may leave college blank or use an appropriate office/college code.</li>
                        </ul>
                    </li>
                    <li>Passwords will be automatically generated for each employee.</li>
                    <li>All uploaded users will be added with role <code>voter</code> and tied to <strong>your scope</strong> via <code>owner_scope_id</code>, so only your admin can manage them.</li>
                    <li><strong>is_other_member</strong> will be set to <code>1</code> for all uploaded rows (you can put 1 in the CSV or leave it blank; the system will enforce 1 during processing).</li>
                </ul>
            ';
            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Others - Default Employee CSV Format Example:</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,college,department,status,is_other_member
Juan,DelaCruz,juan.dc@example.com,academic,2001,CEIT,DIT,regular,1
Maria,Santos,maria.santos@example.com,non-academic,2002,,LIBRARY,contractual,1</pre>
            ';
        } else {
            // Non-Academic-Employee & legacy NON-ACADEMIC: global non-ac staff, no owner_scope_id
            $instructions = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Non-Academic Employees:</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing non-academic staff to add to the system.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, employee_number, department, status, is_coop_member</code>
                    </li>
                    <li><strong>position</strong> should be set to <code>non-academic</code> for all rows.</li>
                    <li>Passwords will be automatically generated for each employee.</li>
                    <li>Non-academic staff will be added with the role <code>voter</code> and position <code>non-academic</code>.</li>
                    <li>These users are <strong>not</strong> tied to an owner scope; visibility is based on their department (per your Non-Academic-Employee scope settings).</li>
                </ul>
            ';
            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Non-Academic CSV Format Example:</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,department,status,is_coop_member
John,Doe,john.doe@example.com,non-academic,1001,ADMIN,regular,0
Jane,Smith,jane.smith@example.com,non-academic,1002,LIBRARY,part-time,1</pre>
            ';
        }
        break;

    case 'admin_coop':
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for COOP Members:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing COOP members to add to the system.</li>
                <li>The CSV must have these columns in order:
                    <code>first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</code>
                </li>
                <li>Passwords will be automatically generated for each COOP member.</li>
                <li>COOP members will be added with the role <code>voter</code>.</li>
                <li><strong>For academic staff:</strong> college is required (e.g., CEIT, CAS).</li>
                <li><strong>For non-academic staff:</strong> leave college field empty.</li>
                <li><strong>Note:</strong> is_coop_member should be set to 1 for all rows.</li>
            </ul>
        ';
        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">COOP CSV Format Example:</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,college,department,status,is_coop_member
John,Doe,john.doe@example.com,academic,1001,CEIT,Department of Computer and Electronics Engineering,regular,1
Jane,Smith,jane.smith@example.com,non-academic,1002,,Administration,part-time,1</pre>
        ';
        break;

    case 'super_admin':
    default: // For general admin / fallback
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing users to add to the system.</li>
                <li>The CSV must have these columns in order:
                    <code>first_name, last_name, email, position, student_number, employee_number, college, department, course, status, is_coop_member</code>
                </li>
                <li>Passwords will be automatically generated for each user.</li>
                <li>Users will be added with the role <code>voter</code>.</li>
                <li>Leave fields blank if not applicable to the user type.</li>
            </ul>
        ';
        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">CSV Format Example:</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,employee_number,college,department,course,status,is_coop_member
John,Doe,john.doe@example.com,academic,,1001,CEIT,Department of Computer and Electronics Engineering,,regular,0
Jane,Smith,jane.smith@example.com,student,20231002,,CAS,Department of Biological Sciences,BS Psychology,,0</pre>
        ';
        break;
}

// ---------------------------------------------------------------------
// File upload handling
// ---------------------------------------------------------------------
 $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['csv_file']['tmp_name'];
        $fileName      = $_FILES['csv_file']['name'];
        $fileNameCmps  = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        if ($fileExtension === 'csv') {
            $uploadFileDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $newFileName = 'users_' . time() . '.' . $fileExtension;
            $dest_path   = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Save the path and context in session
                $_SESSION['csv_file_path']          = $dest_path;
                $_SESSION['admin_type']             = $adminType;
                $_SESSION['scope_category_for_csv'] = $scopeCategory;

                // Only Non-Academic-Student and Others-Default use owner_scope_id to "own" their uploaded users
                if (in_array($scopeCategory, ['Non-Academic-Student', 'Others-Default'], true) && $myScopeId !== null) {
                    $_SESSION['owner_scope_id_for_csv'] = $myScopeId;
                } else {
                    $_SESSION['owner_scope_id_for_csv'] = null;
                }

                // Redirect to processing page
                header("Location: process_users_csv.php");
                exit;
            } else {
                $message = "There was an error moving the uploaded file.";
            }
        } else {
            $message = "Please upload a valid CSV file.";
        }
    } else {
        $message = "No file uploaded or upload error.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Users via CSV - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }
    
    .wide-card {
      max-width: 5xl;
    }
    
    .instruction-card, .example-card {
      word-wrap: break-word;
      overflow-wrap: break-word;
      hyphens: auto;
    }
    
    .pre-wrap {
      white-space: pre-wrap;
      word-break: break-all;
    }
    
    .file-upload-area {
      min-width: 100%;
    }
    
    .error-message {
      background-color: #FEE2E2;
      border-left: 4px solid #EF4444;
      color: #B91C1C;
    }
    
    .file-info {
      background-color: #EFF6FF;
      border-left: 4px solid #3B82F6;
      color: #1E40AF;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-users text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Add Users via CSV</h1>
            <p class="text-green-100 mt-1">Upload a CSV file to add multiple users at once</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_manage_users.php" class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded font-semibold transition">Back to Users</a>
        </div>
      </header>
      
      <div class="wide-card mx-auto bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Upload CSV to Add Users</h2>
        <?php if (!empty($message)): ?>
          <div class="mb-4 p-4 rounded error-message">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>
        
        <div class="mb-6 bg-blue-50 p-4 rounded-lg instruction-card">
          <?php echo $instructions; ?>
        </div>
        
        <div class="mb-6 bg-yellow-50 p-4 rounded-lg example-card">
          <?php echo $csvExample; ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <div class="mb-6">
            <label for="csv_file" class="block mb-2 font-semibold text-gray-700">Select CSV file:</label>
            <div class="flex items-center justify-center w-full file-upload-area">
              <label for="csv_file" class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                  <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                  <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                  <p class="text-xs text-gray-500">CSV file only</p>
                </div>
                <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" required />
              </label>
            </div>
            
            <!-- File info display area -->
            <div id="fileInfo" class="mt-3 p-3 rounded hidden file-info">
              <div class="flex items-center">
                <i class="fas fa-file-csv text-blue-500 mr-2"></i>
                <span id="fileName" class="text-sm font-medium"></span>
                <button type="button" id="removeFile" class="ml-auto text-red-500 hover:text-red-700">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
            
            <!-- Error message area -->
            <div id="fileError" class="mt-3 p-3 rounded hidden error-message">
              <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                <span id="errorMessage" class="text-sm"></span>
              </div>
            </div>
          </div>
          
          <button type="submit" class="w-full bg-[var(--cvsu-green-dark)] hover:bg-[var(--cvsu-green)] text-white font-semibold px-6 py-3 rounded shadow transition">
            Upload and Process
          </button>
        </form>
      </div>
    </main>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const fileInput   = document.getElementById('csv_file');
      const fileInfo    = document.getElementById('fileInfo');
      const fileNameEl  = document.getElementById('fileName');
      const removeFile  = document.getElementById('removeFile');
      const fileError   = document.getElementById('fileError');
      const errorMsgEl  = document.getElementById('errorMessage');
      const uploadForm  = document.getElementById('uploadForm');
      
      fileInput.addEventListener('change', function() {
        fileError.classList.add('hidden');
        
        if (this.files && this.files[0]) {
          const file = this.files[0];
          
          if (file.name.toLowerCase().endsWith('.csv')) {
            fileNameEl.textContent = file.name;
            fileInfo.classList.remove('hidden');
          } else {
            errorMsgEl.textContent = 'Please upload a valid CSV file (.csv).';
            fileError.classList.remove('hidden');
            fileInfo.classList.add('hidden');
            this.value = '';
          }
        } else {
          fileInfo.classList.add('hidden');
        }
      });
      
      if (removeFile) {
        removeFile.addEventListener('click', function() {
          fileInput.value = '';
          fileInfo.classList.add('hidden');
        });
      }
      
      uploadForm.addEventListener('submit', function(e) {
        fileError.classList.add('hidden');
        
        if (!fileInput.files || fileInput.files.length === 0) {
          e.preventDefault();
          errorMsgEl.textContent = 'Please select a CSV file to upload.';
          fileError.classList.remove('hidden');
          return;
        }
        
        const file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.csv')) {
          e.preventDefault();
          errorMsgEl.textContent = 'Please upload a valid CSV file (.csv).';
          fileError.classList.remove('hidden');
          return;
        }
      });
    });
  </script>
</body>
</html>