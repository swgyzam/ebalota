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
$adminRole      = $_SESSION['role'];
$assignedScope  = strtoupper(trim($_SESSION['assigned_scope']   ?? ''));  // e.g. CEIT, FACULTY ASSOCIATION
$assignedScope1 = trim($_SESSION['assigned_scope_1'] ?? '');             // e.g. "Multiple: BSIT, BSCS" or "All"
$scopeCategory  = $_SESSION['scope_category']   ?? '';                   // e.g. Academic-Student, Academic-Faculty, Non-Academic-Student, Others-Default, Non-Academic-Employee

// Resolve this admin's scope seat (admin_scopes), if applicable
$myScopeId       = null;
$myScopeType     = null;
$myScopeDetails  = [];

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

// NEW: refine adminType semantics using scope_category (override old assigned_scope logic).
// Legacy assigned_scope mapping stays above as a fallback for older admins.
if ($scopeCategory === 'Non-Academic-Student') {
    // Org-based student admins: student CSV format
    $adminType = 'admin_students';

} elseif ($scopeCategory === 'Others') {
    // Unified Others admins: flexible employee/other-group CSV,
    // but still treated as "non-academic style" in process_users_csv (role=voter, is_other_member=1).
    $adminType = 'admin_non_academic';

} elseif ($scopeCategory === 'Academic-Faculty') {
    // Academic-Faculty admins: faculty/academic CSV
    $adminType = 'admin_academic';

} elseif ($scopeCategory === 'Non-Academic-Employee') {
    // Non-Academic-Employee admins: non-academic CSV
    $adminType = 'admin_non_academic';

} elseif ($scopeCategory === 'Special-Scope') {
    // CSG Admin (global students)
    $adminType = 'admin_students';
}

// ---------------------------------------------------------------------
// Build scope-aware summary for instructions (human readable)
// ---------------------------------------------------------------------
$scopeSummaryHtml = '';

if ($adminRole === 'super_admin') {
    $scopeSummaryHtml = '
        <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
            <p><strong>Scope:</strong> You are a super admin and can upload voters for any position, college, department, and course.</p>
            <p class="mt-1 text-xs text-blue-900/80">
                <strong>Note:</strong> For students and academic staff, use <em>codes</em> in the CSV (e.g., <code>CEIT</code>, <code>DIT</code>, <code>BSIT</code>).
                The system automatically converts these codes into full names when saving to the database.
            </p>
        </div>
    ';
} else {

    // Helper: pretty course scope from assigned_scope1
    $courseScopeDisplay = '';
    if ($assignedScope1 !== '' && strcasecmp($assignedScope1, 'All') !== 0) {
        $clean = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $assignedScope1);
        $parts = array_filter(array_map('trim', explode(',', $clean)));
        if (!empty($parts)) {
            $courseScopeDisplay = implode(', ', $parts);
        }
    }

    // Helper: pretty departments from myScopeDetails
    $deptScopeDisplay = '';
    if (!empty($myScopeDetails['departments']) && is_array($myScopeDetails['departments'])) {
        $deptCodes = array_filter(array_map('trim', $myScopeDetails['departments']));
        if (!empty($deptCodes)) {
            $deptScopeDisplay = implode(', ', $deptCodes);
        }
    }

    if ($scopeCategory === 'Academic-Student') {
        $collegeCode = $assignedScope ?: ($myScopeDetails['college'] ?? '');
        $courseText  = 'All courses in this college';
        if ($courseScopeDisplay !== '') {
            $courseText = $courseScopeDisplay;
        } elseif (!empty($myScopeDetails['courses']) && is_array($myScopeDetails['courses'])) {
            $courseText = implode(', ', $myScopeDetails['courses']);
        }

        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Allowed position: <code>student</code> only.</li>
                    <li>College scope (code): <code>' . htmlspecialchars($collegeCode) . '</code>.</li>
                    <li>Course scope (codes): <code>' . htmlspecialchars($courseText) . '</code>.</li>
                    <li>Rows with different positions/colleges/courses will be rejected during processing.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    <strong>IMPORTANT:</strong> In the CSV, use <em>codes</em> only for:
                    <code>college</code> (e.g., <code>CEIT</code>), 
                    <code>department</code> (e.g., <code>DIT</code>, <code>DCEE</code>), and 
                    <code>course</code> (e.g., <code>BSIT</code>, <code>BSCS</code>).
                    The system will automatically convert these codes into full department and course names in the database.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Academic-Faculty') {
        $collegeCode = $assignedScope ?: ($myScopeDetails['college'] ?? '');
        $deptText    = 'All departments in this college';
        if ($deptScopeDisplay !== '') {
            $deptText = $deptScopeDisplay;
        } elseif ($assignedScope1 !== '' && strcasecmp($assignedScope1, 'All') !== 0) {
            $deptText = $assignedScope1;
        }

        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Allowed position: <code>academic</code> (faculty) only.</li>
                    <li>College scope (code): <code>' . htmlspecialchars($collegeCode) . '</code>.</li>
                    <li>Department scope (codes): <code>' . htmlspecialchars($deptText) . '</code>.</li>
                    <li>Rows outside these departments/college will be rejected during processing.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    <strong>IMPORTANT:</strong> In the CSV, use department <em>codes</em> (e.g., <code>DIT</code>, <code>DCEE</code>, <code>DMS</code>) 
                    in the <code>department</code> column. The system automatically converts these codes to full department names in the database.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Non-Academic-Employee') {
        $deptText = $deptScopeDisplay !== '' ? $deptScopeDisplay : 'Your assigned non-academic departments';

        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Allowed position: <code>non-academic</code> only.</li>
                    <li>Department scope (codes): <code>' . htmlspecialchars($deptText) . '</code>.</li>
                    <li>Rows with other positions or departments will be rejected during processing.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    For non-academic staff, use department codes like <code>ADMIN</code>, <code>FINANCE</code>, <code>LIBRARY</code>, <code>NAEA</code>.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Non-Academic-Student') {
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Allowed position: <code>student</code> only.</li>
                    <li>College, department, and course can be any valid values (multi-college org members are allowed).</li>
                    <li>All uploaded students will belong <strong>only to your organization scope</strong> and will be visible/manageable by you.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    Use college and course codes (e.g., <code>CEIT</code>, <code>BSIT</code>) in the CSV. The system converts them into full names internally.
                </p>
            </div>
        ';

    } elseif ($scopeCategory === 'Others') {
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope (Others):</strong></p>
                <ul class="list-disc pl-5">
                    <li>All uploaded rows will be tagged as <code>Others</code> members under your scope and stored with <code>is_other_member = 1</code> and your <code>owner_scope_id</code>.</li>
                    <li>You may upload <strong>employees</strong> (with full credentials) or <strong>external members</strong> (alumni, retirees, org members) using only name + email.</li>
                    <li>Students are generally not recommended here; student voters usually go under student/CSG/org admins.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    For rows where you <strong>do</strong> have employee data, you can fill in <code>position</code>, <code>employee_number</code>, <code>college</code>, <code>department</code>, and <code>status</code>.
                    For alumni or external groups that only have name and email, you may leave those fields blank – the system will still store them as valid Others voters.
                </p>
            </div>
        ';    

    } elseif ($scopeCategory === 'Special-Scope') {
        // CSG Admin
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope:</strong></p>
                <ul class="list-disc pl-5">
                    <li>Allowed position: <code>student</code> only (global CSG voters).</li>
                    <li>Students uploaded here will be treated as <strong>global students</strong> (not tied to any organization scope).</li>
                    <li>Org-specific student members belong under Non-Academic-Student admins, not here.</li>
                </ul>
                <p class="mt-2 text-xs text-blue-900/80">
                    Use college, department, and course <em>codes</em> (e.g., <code>CEIT</code>, <code>DIT</code>, <code>BSIT</code>); the system will expand them to full names.
                </p>
            </div>
        ';
    } else {
        // Generic admin (fallback)
        $scopeSummaryHtml = '
            <div class="mt-2 bg-blue-100 text-blue-900 p-3 rounded text-sm">
                <p><strong>Your upload scope:</strong> generic admin — please follow the column rules below. Additional scope-based validation may apply.</p>
                <p class="mt-1 text-xs text-blue-900/80">
                    For consistency, use college/department/course codes in the CSV; the system maps these into full names where needed.
                </p>
            </div>
        ';
    }
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
            $instructions  = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Non-Academic Student Organization Members:</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing student members of <strong>your organization/scope</strong>.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, student_number, college, department, course</code>
                    </li>
                    <li><strong>position</strong> must be <code>student</code> for all rows.</li>
                    <li><strong>college</strong> must use the college <em>code</em> (e.g., <code>CEIT</code>, <code>CAS</code>).</li>
                    <li><strong>department</strong> must use the academic department <em>code</em> (e.g., <code>DIT</code>, <code>DCEE</code>, <code>DMS</code>).</li>
                    <li><strong>course</strong> must use the course <em>code</em> (e.g., <code>BSIT</code>, <code>BSCS</code>, <code>BSAGRI</code>).</li>
                    <li>All uploaded students will be added with role <code>voter</code> and position <code>student</code>.</li>
                    <li>Ownership: all uploaded students will be tied <strong>only to your organization scope</strong> (owner_scope_id) so only your org admin can manage them.</li>
                </ul>
            ';
            // Append scope summary block
            $instructions .= $scopeSummaryHtml;

            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Non-Academic Student Org CSV Format Example (using codes):</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,college,department,course
Raven,Kyle,raven.kyle@example.com,student,202200151,CEIT,DIT,BSIT
Jam,Benito,jam.benito@example.com,student,202200146,CEIT,DCEE,BSCS</pre>
            ';
        } else {
            // Default: college-level student admins / CSG ADMIN
            $instructions  = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Student Voters:</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing student voters to add to the system.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, student_number, college, department, course</code>
                    </li>
                    <li><strong>position</strong> must be <code>student</code> for all rows.</li>
                    <li><strong>college</strong> must be the college <em>code</em> (e.g., <code>CEIT</code>, <code>CAS</code>, <code>CAFENR</code>).</li>
                    <li><strong>department</strong> must be the academic department <em>code</em> (e.g., <code>DIT</code>, <code>DCE</code>, <code>DBS</code>). These will be stored as full department names.</li>
                    <li><strong>course</strong> must be the course <em>code</em> (e.g., <code>BSIT</code>, <code>BSCS</code>, <code>BSN</code>). These will be stored as full course names.</li>
                    <li>Passwords will be automatically generated for each student.</li>
                    <li>Students will be added with the role <code>voter</code> and position <code>student</code>.</li>
                    <li>Scope-based validation will ensure that only students matching your allowed college/course scope are accepted.</li>
                    <li><strong>Note:</strong> <code>is_coop_member</code> will automatically be set to 0 for student uploads.</li>
                </ul>
            ';
            // Append scope summary block
            $instructions .= $scopeSummaryHtml;

            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Student CSV Format Example (using codes):</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,college,department,course
John,Doe,john.doe@example.com,student,20231001,CEIT,DCEE,BSCS
Jane,Smith,jane.smith@example.com,student,20231002,CAS,DBS,BSPSYCH</pre>
            ';
        }
        break;

    case 'admin_academic':
        $instructions  = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for Academic Voters (Faculty):</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing faculty members to add to the system.</li>
                <li>The CSV must have these columns in order:
                    <code>first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</code>
                </li>
                <li><strong>position</strong> must be <code>academic</code> for all rows.</li>
                <li><strong>college</strong> must be the college <em>code</em> (e.g., <code>CEIT</code>, <code>CAS</code>).</li>
                <li><strong>department</strong> must be the academic department <em>code</em> (e.g., <code>DIT</code>, <code>DCEE</code>, <code>DMS</code>). It will be stored as a full department name.</li>
                <li><strong>status</strong> can be <code>Regular</code>, <code>Part-time</code>, or <code>Contractual</code> (common variants like "full-time" will be normalized).</li>
                <li><strong>is_coop_member</strong> should be <code>0</code> or <code>1</code> depending on COOP membership (for standard faculty admins this is usually 0).</li>
                <li>Scope-based validation will ensure that only faculty from your allowed college/department scope are accepted.</li>
            </ul>
        ';
        $instructions .= $scopeSummaryHtml;

        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">Academic CSV Format Example (using codes):</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,college,department,status,is_coop_member
John,Doe,john.doe@example.com,academic,1001,CEIT,DCEE,Regular,0
Jane,Smith,jane.smith@example.com,academic,1002,CAS,DBS,Part-time,1</pre>
        ';
        break;

    case 'admin_non_academic':
        if ($scopeCategory === 'Others') {
            // Unified Others admin: flexible rows (employees or external members)
            $instructions  = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Others Admin (Flexible Members):</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing <strong>members of your Others group</strong> (e.g., faculty/non-ac staff in an association, COOP members, alumni, retirees, or other custom groups).</li>
                    <li>The recommended CSV columns (in this order) are:
                        <code>first_name, last_name, email, position, employee_number, college, department, status</code>
                    </li>
                    <li><strong>Required:</strong> <code>first_name</code>, <code>last_name</code>, and <code>email</code>.</li>
                    <li><strong>Optional:</strong> <code>position</code>, <code>employee_number</code>, <code>college</code>, <code>department</code>, and <code>status</code>. You may leave them blank for external members who only have name and email.</li>
                    <li>Common <code>position</code> values: <code>academic</code>, <code>non-academic</code>. For alumni/retirees/external groups, you may leave it blank.</li>
                    <li><code>college</code> (if used) should be the college code (e.g., <code>CEIT</code>, <code>CAS</code>).</li>
                    <li><code>department</code> (if used) can be either an academic department code (e.g., <code>DIT</code>, <code>DMS</code>) or a non-ac department code (e.g., <code>ADMIN</code>, <code>LIBRARY</code>).</li>
                    <li><code>status</code> (if used) can be any descriptive employment status such as <code>Regular</code>, <code>Part-time</code>, <code>Contractual</code>, etc.</li>
                    <li>All uploaded rows will be stored as role <code>voter</code> with <strong>is_other_member = 1</strong> and tied to your Others scope via <code>owner_scope_id</code>.</li>
                </ul>
            ';
            $instructions .= $scopeSummaryHtml;

            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Others Admin CSV Format Example:</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,college,department,status
Juan,Dela Cruz,juan.dc@example.com,academic,2001,CEIT,DIT,Regular
Maria,Santos,maria.santos@example.com,non-academic,2002,,LIBRARY,Contractual
Ana,Reyes,ana.reyes@example.com,,,,,   <!-- Alumni with name + email only --></pre>
            ';
        } else {
            // Non-Academic-Employee & legacy NON-ACADEMIC: department-scoped non-ac staff
            $instructions  = '
                <h3 class="font-semibold text-blue-800 mb-2">Instructions for Non-Academic Employees:</h3>
                <ul class="list-disc pl-5 text-blue-700 space-y-1">
                    <li>Upload a CSV file containing non-academic staff to add to the system.</li>
                    <li>The CSV must have these columns in order:
                        <code>first_name, last_name, email, position, employee_number, department, status, is_coop_member</code>
                    </li>
                    <li><strong>position</strong> must be <code>non-academic</code> for all rows.</li>
                    <li><strong>department</strong> must be a non-ac department code: e.g., <code>ADMIN</code>, <code>FINANCE</code>, <code>HR</code>, <code>IT</code>, <code>LIBRARY</code>, <code>NAEA</code>.</li>
                    <li><strong>status</strong> can be <code>Regular</code>, <code>Part-time</code>, or <code>Contractual</code> (variants normalized).</li>
                    <li><strong>is_coop_member</strong> should be 0/1 depending on COOP membership (if applicable).</li>
                    <li>Scope-based validation will ensure that only staff from your allowed department scope are accepted.</li>
                </ul>
            ';
            $instructions .= $scopeSummaryHtml;

            $csvExample = '
                <h3 class="font-semibold text-yellow-800 mb-2">Non-Academic CSV Format Example (using department codes):</h3>
                <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,department,status,is_coop_member
John,Doe,john.doe@example.com,non-academic,1001,ADMIN,Regular,0
Jane,Smith,jane.smith@example.com,non-academic,1002,LIBRARY,Part-time,1</pre>
            ';
        }
        break;

    case 'admin_coop':
        $instructions  = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions for COOP Members:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing COOP members to add to the system.</li>
                <li>The CSV must have these columns in order:
                    <code>first_name, last_name, email, position, employee_number, college, department, status, is_coop_member</code>
                </li>
                <li><strong>position</strong> must be <code>academic</code> or <code>non-academic</code>.</li>
                <li>For <strong>academic COOP</strong> members:
                    <ul class="list-disc pl-6">
                        <li><code>college</code> = college code (e.g., <code>CEIT</code>, <code>CAS</code>).</li>
                        <li><code>department</code> = academic department code (e.g., <code>DIT</code>, <code>DMS</code>, <code>DBS</code>).</li>
                    </ul>
                </li>
                <li>For <strong>non-academic COOP</strong> members:
                    <ul class="list-disc pl-6">
                        <li><code>college</code> can be left blank.</li>
                        <li><code>department</code> = non-ac department code (e.g., <code>ADMIN</code>, <code>LIBRARY</code>).</li>
                    </ul>
                </li>
                <li><strong>status</strong> must be a valid employment status (Regular, Part-time, Contractual, or variants).</li>
                <li><strong>is_coop_member</strong> should be 1 for all rows (non-1 values will be rejected or normalized).</li>
                <li>COOP members are added as <code>voter</code> accounts with their appropriate position.</li>
            </ul>
        ';
        $instructions .= $scopeSummaryHtml;

        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">COOP CSV Format Example (using department codes):</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,employee_number,college,department,status,is_coop_member
John,Doe,john.doe@example.com,academic,1001,CEIT,DIT,Regular,1
Jane,Smith,jane.smith@example.com,non-academic,1002,,ADMIN,Part-time,1</pre>
        ';
        break;

    case 'super_admin':
    default: // For general admin / fallback
        $instructions  = '
            <h3 class="font-semibold text-blue-800 mb-2">Instructions:</h3>
            <ul class="list-disc pl-5 text-blue-700 space-y-1">
                <li>Upload a CSV file containing users to add to the system.</li>
                <li>The CSV must have these columns in order:
                    <code>first_name, last_name, email, position, student_number, employee_number, college, department, course, status, is_coop_member</code>
                </li>
                <li>Use <code>student</code>, <code>academic</code>, or <code>non-academic</code> in the <code>position</code> column.</li>
                <li>For <strong>students</strong>:
                    <ul class="list-disc pl-6">
                        <li><code>college</code> = college code (e.g., <code>CEIT</code>).</li>
                        <li><code>department</code> = academic department code (e.g., <code>DIT</code>).</li>
                        <li><code>course</code> = course code (e.g., <code>BSIT</code>, <code>BSCS</code>).</li>
                    </ul>
                </li>
                <li>For <strong>academic</strong> staff:
                    <ul class="list-disc pl-6">
                        <li><code>college</code> = college code.</li>
                        <li><code>department</code> = academic department code.</li>
                    </ul>
                </li>
                <li>For <strong>non-academic</strong> staff:
                    <ul class="list-disc pl-6">
                        <li><code>department</code> = non-ac department code (e.g., <code>ADMIN</code>, <code>LIBRARY</code>).</li>
                        <li><code>college</code> may be left blank.</li>
                    </ul>
                </li>
                <li><strong>status</strong> is required for employees (Regular, Part-time, Contractual).</li>
                <li>Passwords will be automatically generated; all accounts are created as <code>voter</code> unless otherwise configured.</li>
            </ul>
            <p class="mt-2 text-xs text-blue-900/80">
                The system automatically converts college/department/course codes into canonical full names where appropriate (e.g., <code>DIT</code> → <em>Department of Information Technology</em>, <code>BSIT</code> → <em>BS Information Technology</em>).
            </p>
        ';
        $instructions .= $scopeSummaryHtml;

        $csvExample = '
            <h3 class="font-semibold text-yellow-800 mb-2">CSV Format Example (mixed positions, using codes):</h3>
            <pre class="text-sm text-yellow-700 bg-yellow-100 p-2 rounded overflow-x-auto">first_name,last_name,email,position,student_number,employee_number,college,department,course,status,is_coop_member
John,Doe,john.doe@example.com,academic,,1001,CEIT,DCEE,,Regular,0
Jane,Smith,jane.smith@example.com,student,20231002,,CAS,DBS,BSPSYCH,,0
Mark,Reyes,mark.reyes@example.com,non-academic,,2003,,ADMIN,,Contractual,0</pre>
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

                // Non-Academic-Student and Others use owner_scope_id to "own" their uploaded users
                if (in_array($scopeCategory, ['Non-Academic-Student', 'Others'], true) && $myScopeId !== null) {
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
