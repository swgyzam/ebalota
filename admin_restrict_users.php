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
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header('Location: login.php');
    exit();
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
            $newFileName = 'restricted_users_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Save the path in session
                $_SESSION['csv_file_path'] = $dest_path;
                // Redirect to processing page
                header("Location: process_restricted_users.php");
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

// Get restricted users (those added via CSV)
$stmt = $pdo->prepare("SELECT * FROM pending_users WHERE source = 'csv' ORDER BY created_at DESC");
$stmt->execute();
$restrictedUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Restrict Users - Admin Panel</title>
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
            <i class="fas fa-user-slash text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Restrict Users</h1>
            <p class="text-green-100 mt-1">Upload CSV to restrict users from registering</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_manage_users.php" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">Back to Users</a>
        </div>
      </header>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column: Upload Form -->
        <div class="bg-white p-8 rounded-lg shadow-md">
          <h2 class="text-2xl font-bold mb-4 text-gray-800">Upload CSV to Restrict Users</h2>
          <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
              <?php echo $message; ?>
            </div>
          <?php endif; ?>
          
          <div class="mb-6 bg-blue-50 p-4 rounded-lg">
            <h3 class="font-semibold text-blue-800 mb-2">Instructions:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
              <li>Upload a CSV file containing users to restrict</li>
              <li>The CSV must have these columns in order: first_name, last_name, email, position</li>
              <li>Only the email is used for restriction - other fields are for reference</li>
              <li>Users in this list will be prevented from registering</li>
            </ul>
          </div>
          
          <div class="mb-6 bg-yellow-50 p-4 rounded-lg">
            <h3 class="font-semibold text-yellow-800 mb-2">CSV Format Example:</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded">first_name,last_name,email,position
John,Doe,john.doe@example.com,academic
Jane,Smith,jane.smith@example.com,student</pre>
          </div>
          
          <form method="POST" enctype="multipart/form-data">
            <div class="mb-6">
              <label for="csv_file" class="block mb-2 font-semibold text-gray-700">Select CSV file:</label>
              <div class="flex items-center justify-center w-full">
                <label for="csv_file" class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                  <div class="flex flex-col items-center justify-center pt-5 pb-6">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                    <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                    <p class="text-xs text-gray-500">CSV file only</p>
                  </div>
                  <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" required />
                </label>
              </div>
            </div>
            
            <button type="submit" class="w-full bg-[var(--cvsu-green-dark)] hover:bg-[var(--cvsu-green)] text-white font-semibold px-6 py-3 rounded shadow transition">
              Upload and Process
            </button>
          </form>
        </div>
        
        <!-- Right Column: Restricted Users List -->
        <div class="bg-white p-8 rounded-lg shadow-md">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Restricted Users</h2>
            <span class="bg-red-100 text-red-800 text-sm font-medium px-2.5 py-0.5 rounded">
              <?= count($restrictedUsers) ?> Users
            </span>
          </div>
          
          <?php if (count($restrictedUsers) > 0): ?>
            <div class="overflow-x-auto">
              <table class="min-w-full table-auto">
                <thead class="bg-gray-100 text-gray-700">
                  <tr>
                    <th class="py-2 px-4 text-left text-xs font-medium">Name</th>
                    <th class="py-2 px-4 text-left text-xs font-medium">Email</th>
                    <th class="py-2 px-4 text-left text-xs font-medium">Position</th>
                    <th class="py-2 px-4 text-left text-xs font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <?php foreach ($restrictedUsers as $user): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 px-4 text-sm">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                      </td>
                      <td class="py-3 px-4 text-sm text-gray-600">
                        <?= htmlspecialchars($user['email']) ?>
                      </td>
                      <td class="py-3 px-4 text-sm">
                        <?= !empty($user['position']) ? htmlspecialchars($user['position']) : '<span class="text-gray-400">Not set</span>' ?>
                      </td>
                      <td class="py-3 px-4 text-sm">
                        <a href="delete_restricted_user.php?pending_id=<?= $user['pending_id'] ?>" 
                           class="text-red-600 hover:text-red-700 font-medium text-xs" 
                           onclick="return confirm('Are you sure you want to remove this user from the restriction list?');">
                          <i class="fas fa-trash mr-1"></i>Remove
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-12">
              <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100">
                <i class="fas fa-user-slash text-gray-400"></i>
              </div>
              <h3 class="mt-2 text-sm font-medium text-gray-900">No restricted users</h3>
              <p class="mt-1 text-sm text-gray-500">Upload a CSV file to add users to the restriction list.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Stats Section -->
      <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-6 rounded-lg shadow">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
              <i class="fas fa-user-slash text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Total Restricted</p>
              <p class="text-2xl font-semibold text-gray-900"><?= count($restrictedUsers) ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Recently Added</p>
              <p class="text-2xl font-semibold text-gray-900">
                <?= count($restrictedUsers) > 0 ? date('M d', strtotime($restrictedUsers[0]['created_at'])) : 'None' ?>
              </p>
            </div>
          </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fas fa-shield-alt text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Registration Status</p>
              <p class="text-2xl font-semibold text-gray-900">Blocked</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>