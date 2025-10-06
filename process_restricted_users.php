<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Auth check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header('Location: login.php');
    exit();
}

// Check if we have the CSV file path in session
if (!isset($_SESSION['csv_file_path'])) {
    die("No CSV file to process.");
}

$csvFilePath = $_SESSION['csv_file_path'];
unset($_SESSION['csv_file_path']); // Clear the session

// Database connection
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

// Check if the file exists
if (!file_exists($csvFilePath)) {
    die("CSV file not found.");
}

// Open the CSV file
$file = fopen($csvFilePath, 'r');
if (!$file) {
    die("Failed to open CSV file.");
}

// Skip header row if it exists
$header = fgetcsv($file);
if ($header && count($header) >= 4) {
    // We have a header row, continue processing
} else {
    // No header, rewind to start
    rewind($file);
}

// Initialize counters
$totalRows = 0;
$inserted = 0;
$duplicates = 0;
$errors = 0;

// Read each row
while (($row = fgetcsv($file)) !== FALSE) {
    $totalRows++;
    
    // We expect exactly 4 columns: first_name, last_name, email, position
    if (count($row) < 4) {
        $errors++;
        continue;
    }
    
    $first_name = trim($row[0] ?? '');
    $last_name = trim($row[1] ?? '');
    $email = trim($row[2] ?? '');
    $position = trim($row[3] ?? '');
    
    if (empty($email)) {
        $errors++;
        continue;
    }

    try {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT pending_id FROM pending_users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $duplicates++;
            continue;
        }
        
        // Insert new restricted user (only essential fields)
        $insertStmt = $pdo->prepare("INSERT INTO pending_users 
            (first_name, last_name, email, position, source, is_restricted) 
            VALUES (?, ?, ?, ?, 'csv', 1)");
        
        $insertStmt->execute([
            $first_name,
            $last_name,
            $email,
            $position
        ]);
        
        if ($insertStmt->rowCount() > 0) {
            $inserted++;
        }
    } catch (Exception $e) {
        $errors++;
        error_log("Error inserting user with email $email: " . $e->getMessage());
    }
}

fclose($file);

// Delete the CSV file
unlink($csvFilePath);

// Now, show the result
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>CSV Processing Result - Admin Panel</title>
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
            <h1 class="text-3xl font-extrabold">CSV Processing Result</h1>
            <p class="text-green-100 mt-1">Summary of processed CSV file</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_restrict_users.php" class="bg-yellow-500 hover:bg-yellow-400 px-4 py-2 rounded font-semibold transition">Upload Another</a>
          <a href="admin_manage_users.php" class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded font-semibold transition">Back to Users</a>
        </div>
      </header>

      <div class="bg-white p-8 rounded-lg shadow-md max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">Processing Summary</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <div class="bg-blue-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-blue-600"><?= $totalRows ?></div>
            <div class="text-gray-600">Total Rows</div>
          </div>
          <div class="bg-green-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-green-600"><?= $inserted ?></div>
            <div class="text-gray-600">Added to Restriction List</div>
          </div>
          <div class="bg-yellow-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-yellow-600"><?= $duplicates ?></div>
            <div class="text-gray-600">Duplicates Skipped</div>
          </div>
          <div class="bg-red-50 p-6 rounded-lg text-center">
            <div class="text-3xl font-bold text-red-600"><?= $errors ?></div>
            <div class="text-gray-600">Errors</div>
          </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-check-circle text-green-500 text-xl"></i>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">Restriction List Updated</h3>
              <div class="mt-2 text-sm text-green-700">
                <p>The users in the CSV file have been added to the restriction list. They will be restricted from registering on the platform.</p>
              </div>
            </div>
          </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h3 class="font-medium text-blue-800 mb-2">CSV Format Requirements:</h3>
          <p class="text-sm text-blue-700">
            The CSV file should have the following columns in this order:<br>
            <code>first_name, last_name, email, position</code><br>
            <span class="text-xs">Note: Only the email is used for restriction checking</span>
          </p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>