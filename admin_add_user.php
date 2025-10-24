<?php
session_start();
date_default_timezone_set('Asia/Manila');
// --- DB Connection ---
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
// --- Auth Check ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header('Location: login.php');
    exit();
}

// Get the current admin's role and assigned scope
 $adminRole = $_SESSION['role'];
 $assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// Set role-specific instructions and CSV format based on assigned scope
 $instructions = '';
 $csvExample = '';

// Determine admin type based on assigned scope
if ($adminRole === 'super_admin') {
    $adminType = 'super_admin';
} else if (in_array($assignedScope, ['CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS', 'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'])) {
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

switch ($adminType) {
    case 'admin_students':
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for Student Voters:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing student voters to add to the system</li>
                <li>The CSV must have these columns in order: first_name, last_name, email, position, student_number, college, department, course</li>
                <li>Passwords will be automatically generated for each student</li>
                <li>Students will be added with the role "voter" and position "student"</li>
                <li>Only students from your assigned college will be processed</li>
                <li>is_coop_member will automatically be set to 0</li>
            </ul>
        ';
        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">Student CSV Format Example:</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,college,department,course
John,Doe,john.doe@example.com,student,20231001,CEIT,Department of Computer and Electronics Engineering,BS Computer Science
Jane,Smith,jane.smith@example.com,student,20231002,CAS,Department of Biological Sciences,BS Psychology</pre>
        ';
        break;
        
    case 'admin_academic':
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for Academic Voters (Faculty):</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing faculty members to add to the system</li>
                <li>The CSV must have these columns in order: first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</li>
                <li>Passwords will be automatically generated for each faculty member</li>
                <li>Faculty will be added with the role "voter" and position "academic"</li>
                <li>Only faculty from your assigned departments will be processed</li>
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
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for Non-Academic Employees:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing non-academic staff to add to the system</li>
                <li>The CSV must have these columns in order: first_name, last_name, email, position, employee_number, department, status, is_coop_member</li>
                <li>Passwords will be automatically generated for each employee</li>
                <li>Non-academic staff will be added with the role "voter" and position "non-academic"</li>
                <li>Only employees from your assigned offices will be processed</li>
            </ul>
        ';
        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">Non-Academic CSV Format Example:</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,department,status,is_coop_member
John,Doe,john.doe@example.com,non-academic,1001,Administration,regular,0
Jane,Smith,jane.smith@example.com,non-academic,1002,Library,part-time,1</pre>
        ';
        break;
        
    case 'admin_coop':
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for COOP Members:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing COOP members to add to the system</li>
                <li>The CSV must have these columns in order: first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</li>
                <li>Passwords will be automatically generated for each COOP member</li>
                <li>COOP members will be added with the role "voter"</li>
                <li><strong>For academic staff:</strong> college is required (e.g., "CEIT", "CAS")</li>
                <li><strong>For non-academic staff:</strong> leave college field empty</li>
                <li>is_coop_member will automatically be set to 1</li>
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
    default: // For general admin
        $instructions = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing users to add to the system</li>
                <li>The CSV must have these columns in order: first_name, last_name, email, position, student_number, employee_number, college, department, course, status, is_coop_member</li>
                <li>Passwords will be automatically generated for each user</li>
                <li>Users will be added with the role "voter"</li>
                <li>Leave fields blank if not applicable to the user type</li>
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

 $message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileSize = $_FILES['csv_file']['size'];
        $fileType = $_FILES['csv_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Check if file is csv
        if ($fileExtension === 'csv') {
            // Define upload path
            $uploadFileDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            // Generate new unique file name
            $newFileName = 'users_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Save the path in session along with admin type
                $_SESSION['csv_file_path'] = $dest_path;
                $_SESSION['admin_type'] = $adminType; // Store admin type for processing
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
    
    /* Custom styles for wider card and better text wrapping */
    .wide-card {
      max-width: 5xl; /* Increased from max-w-3xl to max-w-5xl */
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
    
    /* Ensure the file upload area is also wider */
    .file-upload-area {
      min-width: 100%;
    }
    
    /* Error message styling */
    .error-message {
      background-color: #FEE2E2;
      border-left: 4px solid #EF4444;
      color: #B91C1C;
    }
    
    /* File info styling */
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
            <?php echo $message; ?>
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
      const fileInput = document.getElementById('csv_file');
      const fileInfo = document.getElementById('fileInfo');
      const fileName = document.getElementById('fileName');
      const removeFile = document.getElementById('removeFile');
      const fileError = document.getElementById('fileError');
      const errorMessage = document.getElementById('errorMessage');
      const uploadForm = document.getElementById('uploadForm');
      
      // Handle file selection
      fileInput.addEventListener('change', function(e) {
        // Hide any previous error
        fileError.classList.add('hidden');
        
        if (this.files && this.files[0]) {
          const file = this.files[0];
          
          // Check if file is CSV
          if (file.name.toLowerCase().endsWith('.csv')) {
            // Show file info
            fileName.textContent = file.name;
            fileInfo.classList.remove('hidden');
          } else {
            // Show error for non-CSV files
            errorMessage.textContent = 'Please upload a valid CSV file (.csv).';
            fileError.classList.remove('hidden');
            fileInfo.classList.add('hidden');
            // Clear the file input
            this.value = '';
          }
        } else {
          // No file selected
          fileInfo.classList.add('hidden');
        }
      });
      
      // Handle file removal
      removeFile.addEventListener('click', function() {
        fileInput.value = '';
        fileInfo.classList.add('hidden');
      });
      
      // Handle form submission
      uploadForm.addEventListener('submit', function(e) {
        // Hide any previous error
        fileError.classList.add('hidden');
        
        // Check if a file is selected
        if (!fileInput.files || fileInput.files.length === 0) {
          e.preventDefault();
          errorMessage.textContent = 'Please select a CSV file to upload.';
          fileError.classList.remove('hidden');
          return;
        }
        
        // Check if the selected file is CSV
        const file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.csv')) {
          e.preventDefault();
          errorMessage.textContent = 'Please upload a valid CSV file (.csv).';
          fileError.classList.remove('hidden');
          return;
        }
      });
    });
  </script>
</body>
</html>