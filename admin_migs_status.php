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

// --- Auth Check (COOP admin only: new model + legacy fallback) ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$scopeCategory = $_SESSION['scope_category'] ?? '';
$assignedScope = strtoupper(trim($_SESSION['assigned_scope'] ?? ''));

// Bagong modelo: Others-COOP
// Legacy fallback: assigned_scope = 'COOP'
if ($scopeCategory !== 'Others-COOP' && $assignedScope !== 'COOP') {
    header('Location: admin_dashboard_redirect.php');
    exit();
}

// Set instructions and CSV format for MIGS status update (EMAIL-based)
$instructions = '
    <h3 class="font-semibold text-blue-800 mb-2">Instructions for MIGS Status Update:</h3>
    <ul class="list-disc pl-5 text-blue-700 space-y-1">
        <li>Upload a CSV file containing COOP members whose MIGS status you want to update.</li>
        <li><strong>The CSV must have these columns in order: <code>email,action</code></strong>.</li>
        <li><strong>Email</strong> must match exactly the email address used by the user in the system.</li>
        <li><strong>Action</strong> must be either <code>activate</code> or <code>deactivate</code> (case-insensitive).</li>
        <li>Only users who are marked as <code>is_coop_member = 1</code> will be processed.</li>
        <li>Email notifications will be sent to users when their MIGS status is updated.</li>
    </ul>
';

$csvExample = '
    <h3 class="font-semibold text-yellow-800 mb-2">MIGS Status CSV Format Example:</h3>
    <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">email,action
juan.delacruz@example.com,activate
maria.santos@example.com,deactivate
pedro.cruz@example.com,activate</pre>
';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath  = $_FILES['csv_file']['tmp_name'];
        $fileName     = $_FILES['csv_file']['name'];
        $fileSize     = $_FILES['csv_file']->size ?? 0;
        $fileType     = $_FILES['csv_file']['type'] ?? '';
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
            $newFileName = 'migs_status_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Save the path in session
                $_SESSION['csv_file_path'] = $dest_path;
                // Redirect to processing page
                header("Location: process_migs_status_csv.php");
                exit;
            } else {
                $message = "There was an error moving the uploaded file.";
            }
        } else {
            $message = "Please upload a valid CSV file (.csv).";
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
  <title>Manage MIGS Status - COOP Admin</title>
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
      max-width: 72rem; /* ~5xl */
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
    
    .success-message {
      background-color: #D1FAE5;
      border-left: 4px solid #10B981;
      color: #065F46;
    }
    
    .error-container {
      max-height: 300px;
      overflow-y: auto;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <!-- Background Pattern -->
  <div class="fixed inset-0 opacity-5 pointer-events-none">
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><circle cx=\'50\' cy=\'50\' r=\'2\' fill=\'%23154734\'/></svg>'); background-size: 20px 20px;"></div>
  </div>
  
  <div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 p-8 ml-64">
      <!-- Header -->
      <header class="gradient-bg text-white p-6 flex justify-between items-center shadow-md rounded-md mb-8">
        <div class="flex items-center space-x-4">
          <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
            <i class="fas fa-id-card text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Manage MIGS Status</h1>
            <p class="text-green-100 mt-1">Upload a CSV file to update MIGS status for COOP members (by email)</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_manage_users.php" class="bg-green-600 hover:bg-green-500 px  -4 py-2 rounded font-semibold transition">Back to Users</a>
        </div>
      </header>
      
      <!-- Display processing results if available -->
      <?php if (isset($_SESSION['migs_processing_results'])): ?>
        <?php 
        $results = $_SESSION['migs_processing_results'];
        unset($_SESSION['migs_processing_results']);
        ?>
        <div class="mb-6 bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-bold mb-4">MIGS Status Update Results</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-blue-600"><?= $results['totalRows'] ?></div>
              <div class="text-gray-600">Total Rows</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-green-600"><?= $results['successCount'] ?></div>
              <div class="text-gray-600">Updated</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-yellow-600"><?= $results['notFoundCount'] ?></div>
              <div class="text-gray-600">Email Not Found</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-red-600"><?= $results['invalidActionCount'] ?></div>
              <div class="text-gray-600">Invalid Action</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-purple-600"><?= $results['notCoopCount'] ?></div>
              <div class="text-gray-600">Non-COOP</div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-green-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-green-600"><?= $results['emailSent'] ?></div>
              <div class="text-gray-600">Emails Sent</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg text-center">
              <div class="text-2xl font-bold text-red-600"><?= $results['emailFailed'] ?></div>
              <div class="text-gray-600">Emails Failed</div>
            </div>
          </div>
          
          <?php if ($results['successCount'] > 0): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-green-800">MIGS Status Updated Successfully</h3>
                  <div class="mt-2 text-sm text-green-700">
                    <p><?= $results['successCount'] ?> user(s) have had their MIGS status updated. Email notifications have been sent to all affected users.</p>
                    <?php if ($results['notFoundCount'] > 0 || $results['invalidActionCount'] > 0 || $results['notCoopCount'] > 0): ?>
                      <p class="mt-2 font-medium">Note: Some rows were not processed due to errors. See details below.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800">No MIGS Status Updates</h3>
                  <div class="mt-2 text-sm text-red-700">
                    <p>No users had their MIGS status updated. Please check the details below for more information.</p>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($results['notFoundCount'] > 0): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-yellow-800">Emails Not Found</h3>
                  <div class="mt-2 text-sm text-yellow-700">
                    <p><?= $results['notFoundCount'] ?> row(s) were skipped because the email address was not found in the system.</p>
                    <p class="mt-2">Please verify that all email addresses in your CSV file exist in the system.</p>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($results['notCoopCount'] > 0): ?>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-triangle text-purple-500 text-xl"></i>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-purple-800">Non-COOP Members</h3>
                  <div class="mt-2 text-sm text-purple-700">
                    <p><?= $results['notCoopCount'] ?> row(s) were skipped because the users are not COOP members.</p>
                    <p class="mt-2">Only users with <code>is_coop_member = 1</code> are eligible for MIGS status updates.</p>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($results['invalidActionCount'] > 0): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800">Invalid Actions</h3>
                  <div class="mt-2 text-sm text-red-700">
                    <p><?= $results['invalidActionCount'] ?> row(s) were skipped because they contained invalid actions.</p>
                    <p class="mt-2">Actions must be either <code>activate</code> or <code>deactivate</code>.</p>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($results['errorMessages'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                </div>
                <div class="ml-3 w-full">
                  <h3 class="text-sm font-medium text-red-800">Error Details</h3>
                  <div class="mt-2 text-sm text-red-700 error-container">
                    <ul class="list-disc pl-5 space-y-1">
                      <?php foreach ($results['errorMessages'] as $m): ?>
                        <li><?= htmlspecialchars($m) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      
      <div class="wide-card mx-auto bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Upload CSV to Update MIGS Status (by Email)</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
          <div class="mb-4 p-4 rounded <?= $_SESSION['message_type'] === 'success' ? 'success-message' : 'error-message' ?>">
            <?= $_SESSION['message'] ?>
          </div>
          <?php 
          unset($_SESSION['message']);
          unset($_SESSION['message_type']);
          ?>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
          <div class="mb-4 p-4 rounded error-message">
            <?= htmlspecialchars($message) ?>
          </div>
        <?php endif; ?>
        
        <div class="mb-6 bg-blue-50 p-4 rounded-lg instruction-card">
          <?= $instructions ?>
        </div>
        
        <div class="mb-6 bg-yellow-50 p-4 rounded-lg example-card">
          <?= $csvExample ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <div class="mb-6">
            <label for="csv_file" class="block mb-2 font-semibold text-gray-700">Select CSV file:</label>
            <div class="flex items-center justify-center w-full file-upload-area">
              <label for="csv_file" class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                <div class="flex flex-col items-center justify-content pt-5 pb-6">
                  <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                  <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                  <p class="text-xs text-gray-500">CSV file only (email,action)</p>
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
      
      removeFile.addEventListener('click', function() {
        fileInput.value = '';
        fileInfo.classList.add('hidden');
      });
      
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
