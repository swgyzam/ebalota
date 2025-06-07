<?php
session_start();

$message = '';

// Clear previous csv file path on fresh upload page load (optional but recommended)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['csv_file_path']);
}

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
            // Define upload path (make sure this folder exists and is writable)
            $uploadFileDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Generate new unique file name to avoid collisions
            $newFileName = 'voters_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Save the path in session to be accessed in process_voters.php
                $_SESSION['csv_file_path'] = $dest_path;

                // Redirect to process voters page
                header("Location: process_voters.php");
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
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Upload Voters CSV</title>
</head>
<body>
    <h2>Upload Voters CSV File</h2>
    <?php if (!empty($message)) echo "<p style='color:red;'>$message</p>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <label for="csv_file">Select CSV file:</label><br>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required><br><br>
        <button type="submit">Upload and Process</button>
    </form>
</body>
</html>
