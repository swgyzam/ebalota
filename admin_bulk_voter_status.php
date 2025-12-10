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
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','super_admin'], true)) {
    header('Location: login.php');
    exit();
}

// Get current admin info from session
$adminRole      = $_SESSION['role'];
$assignedScope  = strtoupper(trim($_SESSION['assigned_scope']   ?? ''));
$assignedScope1 = trim($_SESSION['assigned_scope_1'] ?? '');
$scopeCategory  = $_SESSION['scope_category']   ?? '';

$myScopeId       = null;
$myScopeType     = null;
$myScopeDetails  = [];

// Resolve admin_scopes row if applicable
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
        if (!empty($scopeRow['scope_details'])) {
            $decoded = json_decode($scopeRow['scope_details'], true);
            if (is_array($decoded)) {
                $myScopeDetails = $decoded;
            }
        }
    }
}

// ---------------------------------------------------------------------
// Determine adminType (like admin_add_user.php)
// ---------------------------------------------------------------------
if ($adminRole === 'super_admin') {
    $adminType = 'super_admin';
} else if (in_array($assignedScope, [
    'CAFENR', 'CEIT', 'CAS', 'CVMBS', 'CED', 'CEMDS',
    'CSPEAR', 'CCJ', 'CON', 'CTHM', 'COM', 'GS-OLC'
], true)) {
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

// Override using scope_category (like admin_add_user.php)
if ($scopeCategory === 'Non-Academic-Student') {
    $adminType = 'admin_students';
} elseif ($scopeCategory === 'Others') {
    $adminType = 'admin_non_academic';
} elseif ($scopeCategory === 'Academic-Faculty') {
    $adminType = 'admin_academic';
} elseif ($scopeCategory === 'Non-Academic-Employee') {
    $adminType = 'admin_non_academic';
} elseif ($scopeCategory === 'Special-Scope') {
    $adminType = 'admin_students';
}

/* --------------------------------------------------------------------
   Academic-Student course scope codes (for bulk status)
   -------------------------------------------------------------------- */

$allowedCourseScopeCodes = [];

/**
 * Normalize course like "BS IT", "bsit", "BS.IT" → "BSIT"
 */
if (!function_exists('normalizeCourseCodeBulk')) {
    function normalizeCourseCodeBulk(string $raw): string {
        $s = strtoupper(trim($raw));
        if ($s === '') return '';
        $s = preg_replace('/[.\-_,]/', ' ', $s);
        $s = preg_replace('/\s+/', '', $s);
        return $s;
    }
}

/**
 * Parse "Multiple: BSIT, BSCS" or "BSIT,BSCS" → ['BSIT','BSCS']
 */
if (!function_exists('parseCourseScopeListBulk')) {
    function parseCourseScopeListBulk(?string $scopeString): array {
        if ($scopeString === null) return [];
        $clean = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $scopeString);
        $parts = array_filter(array_map('trim', explode(',', $clean)));
        $codes = [];
        foreach ($parts as $p) {
            if ($p === '' || strcasecmp($p, 'All') === 0) continue;
            $codes[] = strtoupper($p);
        }
        return array_values(array_unique($codes));
    }
}

// Build course scope list for Academic-Student admins
if ($scopeCategory === 'Academic-Student') {
    if (!empty($myScopeDetails['courses']) && is_array($myScopeDetails['courses'])) {
        foreach ($myScopeDetails['courses'] as $c) {
            $code = normalizeCourseCodeBulk($c);
            if ($code !== '') {
                $allowedCourseScopeCodes[] = $code;
            }
        }
        $allowedCourseScopeCodes = array_values(array_unique($allowedCourseScopeCodes));
    } elseif (!empty($assignedScope1)) {
        $allowedCourseScopeCodes = parseCourseScopeListBulk($assignedScope1);
    }
}

// ---------------------------------------------------------------------
// Scope summary (short, for lifetime ops)
// ---------------------------------------------------------------------
$scopeSummaryHtml = '';

if ($adminRole === 'super_admin') {
    $scopeSummaryHtml = '
        <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
            <p><strong>Scope:</strong> You are a super admin and can update account status (activate/deactivate) for any voter.</p>
            <p class="mt-1 text-xs text-blue-900/80">
                All changes will be logged and email notifications will be sent to the affected users.
            </p>
        </div>
    ';
} else {
    // Helper: course scope (for Academic-Student)
    $courseScopeDisplay = '';
    if (!empty($allowedCourseScopeCodes)) {
        $courseScopeDisplay = implode(', ', $allowedCourseScopeCodes);
    } elseif ($assignedScope1 !== '' && strcasecmp($assignedScope1, 'All') !== 0) {
        $clean = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $assignedScope1);
        $parts = array_filter(array_map('trim', explode(',', $clean)));
        if (!empty($parts)) {
            $courseScopeDisplay = implode(', ', $parts);
        }
    }

    // Helper: departments from scope_details (for Non-Academic-Employee)
    $deptScopeDisplay = '';
    if (!empty($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
        $deptCodes = array_filter(array_map('trim', $myScopeDetails['departments']));
        if (!empty($deptCodes)) {
            $deptScopeDisplay = implode(', ', $deptCodes);
        }
    }

    if ($scopeCategory === 'Academic-Student') {
        $collegeCode = $assignedScope ?: ($myScopeDetails['college'] ?? '');
        $courseText  = $courseScopeDisplay !== '' ? $courseScopeDisplay : 'All courses in your college';

        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Position: <code>student</code> only.</li>
                    <li>College: <code>' . htmlspecialchars($collegeCode) . '</code></li>
                    <li>Courses: <code>' . htmlspecialchars($courseText) . '</code></li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    The CSV can only activate/deactivate student voters that belong to your college/course scope.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Non-Academic-Student') {
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Position: <code>student</code> only.</li>
                    <li>Org-based scope: only members tied to your organization (owner_scope_id) can be changed.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    The CSV can only activate/deactivate student voters that belong to your organization scope.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Academic-Faculty') {
        $collegeCode = $assignedScope ?: ($myScopeDetails['college'] ?? '');
        $deptText    = $deptScopeDisplay !== '' ? $deptScopeDisplay : ($assignedScope1 !== '' ? $assignedScope1 : 'All departments in your college');

        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Position: <code>academic</code> (faculty) only.</li>
                    <li>College: <code>' . htmlspecialchars($collegeCode) . '</code></li>
                    <li>Departments: <code>' . htmlspecialchars($deptText) . '</code></li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    The CSV can only activate/deactivate academic voters under your college/department scope.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Non-Academic-Employee') {
        $deptText = $deptScopeDisplay !== '' ? $deptScopeDisplay : 'Your assigned non-academic departments';

        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Position: <code>non-academic</code> only.</li>
                    <li>Departments: <code>' . htmlspecialchars($deptText) . '</code></li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    The CSV can only activate/deactivate non-academic voters in your allowed departments.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Others') {
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your scope (Others group):</strong></p>
                <ul class="list-disc pl-5">
                    <li>You can only activate/deactivate voters that belong to your <code>Others</code> scope (is_other_member = 1, owner_scope_id = yours).</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    This is typically used for alumni/association/other special groups.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Special-Scope') {
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your scope (CSG):</strong></p>
                <ul class="list-disc pl-5">
                    <li>Position: <code>student</code> only.</li>
                    <li>Global student voters (no org owner_scope_id restriction).</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    The CSV can only activate/deactivate global student voters.
                </p>
            </div>
        ';

    } else {
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Scope:</strong> Generic admin. Scope-based checks will still apply using your assigned scope and scope_category.</p>
            </div>
        ';
    }
}

// ---------------------------------------------------------------------
// Instructions for this page (bulk status)
// ---------------------------------------------------------------------
$instructions = '
    <h3 class="font-semibold text-blue-800 mb-2">Instructions for Account Status (Activate / Deactivate):</h3>
    <ul class="list-disc pl-5 text-blue-700 space-y-1">
        <li>This tool lets you <strong>activate/reactivate</strong> or <strong>deactivate</strong> multiple voter accounts in one CSV upload.</li>
        <li>The CSV must have these columns in order:
            <code>email, position</code>
        </li>
        <li><strong>email</strong> must match an existing voter account in the system.</li>
        <li><strong>position</strong> must be one of:
            <code>student</code>, <code>academic</code>, <code>non-academic</code>.
        </li>
        <li><strong>Scope restrictions apply:</strong> you can only change accounts that belong to your allowed scope (college, department, organization, etc.).</li>
        <li>For <strong>Activate</strong>:
            <ul class="list-disc pl-6">
                <li>Students: the account is set to active; expiry is extended (+1 year from now).</li>
                <li>Academic/Non-academic: the account is set to active.</li>
            </ul>
        </li>
        <li>For <strong>Deactivate</strong>:
            <ul class="list-disc pl-6">
                <li>You must choose a <strong>reason</strong> (e.g., Graduated, Transferred, Violation of Terms of Service).</li>
                <li>All rows in one upload share the same reason.</li>
            </ul>
        </li>
        <li>Every change is logged in the audit table and an email notification is sent to each affected voter.</li>
    </ul>
';

$csvExample = '
    <h3 class="font-semibold text-yellow-800 mb-2">CSV Format Example:</h3>
    <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">email,position
juan.dc@example.com,student
maria.santos@example.com,academic
pedro.cruz@example.com,non-academic</pre>
';

// Deactivation reasons (same codes as before)
$deactivationReasons = [
    'GRADUATED'          => 'Graduated / no longer enrolled',
    'TRANSFERRED'        => 'Transferred to another campus / institution',
    'DUPLICATE_ACCOUNT'  => 'Duplicate or incorrect account',
    'VIOLATION_TOS'      => 'Violation of Terms of Service / misuse of system',
    'DISCIPLINARY_ACTION'=> 'Disciplinary or conduct-related decision',
    'DATA_CORRECTION'    => 'Data correction / account migrated',
    'OTHER'              => 'Other (please specify)',
];

// ---------------------------------------------------------------------
// Handle CSV upload + operation selection
// ---------------------------------------------------------------------
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operation = $_POST['operation_type'] ?? '';
    $reasonCode = $_POST['reason_code'] ?? '';
    $reasonText = trim($_POST['reason_text'] ?? '');

    // Basic validation
    if (!in_array($operation, ['activate', 'deactivate'], true)) {
        $message = "Please select a valid operation (activate or deactivate).";
    } elseif ($operation === 'deactivate') {
        if ($reasonCode === '') {
            $message = "Please select a deactivation reason.";
        } elseif ($reasonCode === 'OTHER' && $reasonText === '') {
            $message = "Please specify the deactivation reason.";
        }
    }

    if ($message === '') {
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

                $newFileName = 'bulk_status_' . time() . '.' . $fileExtension;
                $dest_path   = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Store context in session for processor
                    $_SESSION['bulk_status_file_path']      = $dest_path;
                    $_SESSION['bulk_status_operation']      = $operation;
                    $_SESSION['bulk_status_reason_code']    = $operation === 'deactivate' ? $reasonCode : null;
                    $_SESSION['bulk_status_reason_text']    = $operation === 'deactivate' ? $reasonText : null;
                    $_SESSION['bulk_status_scope_category'] = $scopeCategory;

                    // Scope seat for Non-Academic-Student / Others
                    if (in_array($scopeCategory, ['Non-Academic-Student', 'Others'], true) && $myScopeId !== null) {
                        $_SESSION['bulk_status_owner_scope_id'] = $myScopeId;
                    } else {
                        $_SESSION['bulk_status_owner_scope_id'] = null;
                    }

                    // Academic-Student: pass allowed course codes to processor
                    if ($scopeCategory === 'Academic-Student') {
                        $_SESSION['bulk_status_allowed_courses'] = $allowedCourseScopeCodes;
                    } else {
                        $_SESSION['bulk_status_allowed_courses'] = [];
                    }

                    header("Location: process_bulk_voter_status_csv.php");
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
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account Status - Admin Panel</title>
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
            <i class="fas fa-toggle-on text-xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-extrabold">Account Status</h1>
            <p class="text-green-100 mt-1">Activate or deactivate multiple voter accounts using a CSV file</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <a href="admin_manage_users.php" class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded font-semibold transition">
            Back to Users
          </a>
          <a href="admin_duration_logs.php" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded font-semibold transition flex items-center text-sm">
            <i class="fas fa-history mr-2"></i>Lifetime Logs
          </a>
        </div>
      </header>
      
      <div class="wide-card mx-auto bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-2 text-gray-800">Operation & Scope</h2>
        
        <?php if (!empty($message)): ?>
          <div class="mb-4 p-4 rounded error-message">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="mb-4 bg-blue-50 p-4 rounded-lg instruction-card">
          <?php echo $instructions; ?>
          <?php echo $scopeSummaryHtml; ?>
        </div>

        <div class="mb-6 bg-yellow-50 p-4 rounded-lg example-card">
          <?php echo $csvExample; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" id="bulkStatusForm">
          <!-- Operation selection -->
          <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block mb-1 font-semibold text-gray-700">Operation <span class="text-red-500">*</span></label>
              <select
                name="operation_type"
                id="operation_type"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--cvsu-green)] focus:border-[var(--cvsu-green)]"
                required
              >
                <option value="">-- Select Operation --</option>
                <option value="activate">Activate / Reactivate accounts</option>
                <option value="deactivate">Deactivate accounts</option>
              </select>
              <p class="mt-1 text-xs text-gray-500">
                Activate/Reactivate sets accounts to active; Deactivate disables login and voting access.
              </p>
            </div>

            <div id="deactivationReasonGroup" class="hidden">
              <label class="block mb-1 font-semibold text-gray-700">Deactivation Reason <span class="text-red-500">*</span></label>
              <select
                name="reason_code"
                id="reason_code"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
              >
                <option value="">-- Select Reason --</option>
                <?php foreach ($deactivationReasons as $code => $label): ?>
                  <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="mt-1 text-xs text-gray-500">
                This reason will be applied to all rows in this upload and included in the notification email.
              </p>
            </div>
          </div>

          <div id="deactivationOtherGroup" class="mb-4 hidden">
            <label class="block mb-1 font-semibold text-gray-700">Other Reason (please specify)</label>
            <textarea
              name="reason_text"
              id="reason_text"
              rows="3"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
              placeholder="Enter detailed reason for deactivation"
            ></textarea>
          </div>

          <!-- CSV upload -->
          <div class="mb-6">
            <label for="csv_file" class="block mb-2 font-semibold text-gray-700">Select CSV file <span class="text-red-500">*</span></label>
            <div class="flex items-center justify-center w-full file-upload-area">
              <label for="csv_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                  <i class="fas fa-file-csv text-4xl text-gray-400 mb-4"></i>
                  <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                  <p class="text-xs text-gray-500">CSV file (.csv) with columns: <code>email, position</code></p>
                </div>
                <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" required />
              </label>
            </div>
            
            <div id="fileInfo" class="mt-3 p-3 rounded hidden file-info">
              <div class="flex items-center">
                <i class="fas fa-file-csv text-blue-500 mr-2"></i>
                <span id="fileName" class="text-sm font-medium"></span>
                <button type="button" id="removeFile" class="ml-auto text-red-500 hover:text-red-700">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>

            <div id="fileError" class="mt-3 p-3 rounded hidden error-message">
              <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                <span id="errorMessage" class="text-sm"></span>
              </div>
            </div>
          </div>

          <button
            type="submit"
            class="w-full bg-[var(--cvsu-green-dark)] hover:bg-[var(--cvsu-green)] text-white font-semibold px-6 py-3 rounded shadow transition"
          >
            Process Account Status
          </button>
        </form>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const operationSelect = document.getElementById('operation_type');
      const reasonGroup     = document.getElementById('deactivationReasonGroup');
      const reasonCode      = document.getElementById('reason_code');
      const otherGroup      = document.getElementById('deactivationOtherGroup');
      const otherText       = document.getElementById('reason_text');

      const fileInput   = document.getElementById('csv_file');
      const fileInfo    = document.getElementById('fileInfo');
      const fileNameEl  = document.getElementById('fileName');
      const removeFile  = document.getElementById('removeFile');
      const fileError   = document.getElementById('fileError');
      const errorMsgEl  = document.getElementById('errorMessage');
      const bulkForm    = document.getElementById('bulkStatusForm');

      function updateReasonVisibility() {
        const op = operationSelect.value;
        if (op === 'deactivate') {
          reasonGroup.classList.remove('hidden');
        } else {
          reasonGroup.classList.add('hidden');
          otherGroup.classList.add('hidden');
          if (reasonCode) reasonCode.value = '';
          if (otherText)  otherText.value  = '';
        }
      }

      function updateOtherReasonVisibility() {
        const code = reasonCode ? reasonCode.value : '';
        if (code === 'OTHER') {
          otherGroup.classList.remove('hidden');
        } else {
          otherGroup.classList.add('hidden');
          if (otherText) otherText.value = '';
        }
      }

      if (operationSelect) {
        operationSelect.addEventListener('change', updateReasonVisibility);
      }
      if (reasonCode) {
        reasonCode.addEventListener('change', updateOtherReasonVisibility);
      }

      // File handling
      fileInput.addEventListener('change', function() {
        if (fileError) fileError.classList.add('hidden');
        
        if (this.files && this.files[0]) {
          const file = this.files[0];
          
          if (file.name.toLowerCase().endsWith('.csv')) {
            if (fileNameEl) fileNameEl.textContent = file.name;
            if (fileInfo)   fileInfo.classList.remove('hidden');
          } else {
            if (errorMsgEl) errorMsgEl.textContent = 'Please upload a valid CSV file (.csv).';
            if (fileError)  fileError.classList.remove('hidden');
            if (fileInfo)   fileInfo.classList.add('hidden');
            this.value = '';
          }
        } else {
          if (fileInfo) fileInfo.classList.add('hidden');
        }
      });
      
      if (removeFile) {
        removeFile.addEventListener('click', function() {
          fileInput.value = '';
          if (fileInfo) fileInfo.classList.add('hidden');
        });
      }
      
      bulkForm.addEventListener('submit', function(e) {
        if (fileError) fileError.classList.add('hidden');

        const op = operationSelect.value;
        if (!op) {
          e.preventDefault();
          if (errorMsgEl) errorMsgEl.textContent = 'Please select an operation.';
          if (fileError)  fileError.classList.remove('hidden');
          return;
        }

        if (!fileInput.files || fileInput.files.length === 0) {
          e.preventDefault();
          if (errorMsgEl) errorMsgEl.textContent = 'Please select a CSV file to upload.';
          if (fileError)  fileError.classList.remove('hidden');
          return;
        }

        const file = fileInput.files[0];
        if (!file.name.toLowerCase().endsWith('.csv')) {
          e.preventDefault();
          if (errorMsgEl) errorMsgEl.textContent = 'Please upload a valid CSV file (.csv).';
          if (fileError)  fileError.classList.remove('hidden');
          return;
        }
      });

      // Initialize visibility
      updateReasonVisibility();
      updateOtherReasonVisibility();
    });
  </script>
</body>
</html>
