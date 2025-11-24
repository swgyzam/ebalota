<?php
/**
 * analytics_scopes.php
 *
 * Shared helpers for fetching scoped voters/elections and computing analytics.
 * Phase 1 – Patch 1.2:
 *  - Implement getScopeSeats()
 *  - Implement getScopedVoters() for:
 *      * Academic-Student
 *      * Others-COOP
 *  - Stubs for other scope types (to be filled in future patches)
 */

if (!defined('SCOPE_ACAD_STUDENT')) {
    // Scope type constants (match admin_scopes.scope_type & elections.election_scope_type)
    define('SCOPE_ACAD_STUDENT',        'Academic-Student');
    define('SCOPE_ACAD_FACULTY',        'Academic-Faculty');
    define('SCOPE_NONACAD_STUDENT',     'Non-Academic-Student');
    define('SCOPE_NONACAD_EMPLOYEE',    'Non-Academic-Employee');
    define('SCOPE_SPECIAL_CSG',         'Special-Scope');          // CSG global
    define('SCOPE_OTHERS_COOP',         'Others-COOP');
    define('SCOPE_OTHERS_DEFAULT',      'Others-Default');
}

/**
 * Fetch scope seats (admin_scopes) with normalized structure.
 *
 * @param PDO         $pdo
 * @param string|null $scopeType  Optional filter by scope_type
 * @return array
 *
 * Each returned row:
 * [
 *   'scope_id'        => int,
 *   'scope_type'      => string,
 *   'admin_user_id'   => int,
 *   'admin_full_name' => string,
 *   'admin_email'     => string|null,
 *   'assigned_scope'  => string|null,      // legacy: e.g. 'CEIT', 'COOP'
 *   'scope_details'   => array,            // decoded JSON
 *   'label'           => string,           // human-readable label for UI
 * ]
 */
function getScopeSeats(PDO $pdo, ?string $scopeType = null): array
{
    $sql = "
        SELECT 
            s.scope_id,
            s.scope_type,
            s.scope_details,
            s.user_id      AS admin_user_id,
            u.first_name,
            u.last_name,
            u.email        AS admin_email,
            u.assigned_scope,
            u.scope_category
        FROM admin_scopes s
        JOIN users u ON u.user_id = s.user_id
    ";

    $params = [];
    $conds  = [];
    if ($scopeType !== null) {
        $conds[]  = 's.scope_type = :stype';
        $params[':stype'] = $scopeType;
    }

    if ($conds) {
        $sql .= ' WHERE ' . implode(' AND ', $conds);
    }

    $sql .= ' ORDER BY s.scope_type, u.assigned_scope, s.scope_id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];

    foreach ($rows as $row) {
        $details = [];
        if (!empty($row['scope_details'])) {
            $decoded = json_decode($row['scope_details'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        $adminFullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($adminFullName === '') {
            $adminFullName = $row['admin_email'] ?? ('Admin #' . $row['admin_user_id']);
        }

        $assignedScope = $row['assigned_scope'] ?? null;

        // Build a human-friendly label depending on scope_type
        $label = buildScopeLabel(
            $row['scope_type'],
            $assignedScope,
            $details,
            $adminFullName
        );

        $result[] = [
            'scope_id'        => (int)$row['scope_id'],
            'scope_type'      => $row['scope_type'],
            'admin_user_id'   => (int)$row['admin_user_id'],
            'admin_full_name' => $adminFullName,
            'admin_email'     => $row['admin_email'],
            'assigned_scope'  => $assignedScope,
            'scope_details'   => $details,
            'label'           => $label,
        ];
    }

    return $result;
}

/**
 * Build a human readable label for a scope seat.
 * This is used by getScopeSeats() only.
 *
 * @param string      $scopeType
 * @param string|null $assignedScope
 * @param array       $details
 * @param string      $adminName
 * @return string
 */
function buildScopeLabel(string $scopeType, ?string $assignedScope, array $details, string $adminName): string
{
    $scopePart = $assignedScope ? strtoupper(trim($assignedScope)) : 'N/A';

    switch ($scopeType) {
        case SCOPE_ACAD_STUDENT:
            // Example: "Academic-Student – CEIT – Courses: BSIT, BSCS (Admin: John Doe)"
            $courses = [];
            if (!empty($details['courses']) && is_array($details['courses'])) {
                $courses = array_filter(array_map('trim', $details['courses']));
            }
            $coursePart = $courses ? ('Courses: ' . implode(', ', $courses)) : 'All Courses';
            return sprintf('%s – %s – %s (Admin: %s)', $scopeType, $scopePart, $coursePart, $adminName);

        case SCOPE_ACAD_FACULTY:
            $depts = [];
            if (!empty($details['departments']) && is_array($details['departments'])) {
                $depts = array_filter(array_map('trim', $details['departments']));
            }
            $deptPart = $depts ? ('Departments: ' . implode(', ', $depts)) : 'All Departments';
            return sprintf('%s – %s – %s (Admin: %s)', $scopeType, $scopePart, $deptPart, $adminName);

        case SCOPE_NONACAD_STUDENT:
            return sprintf('%s – %s (Admin: %s)', $scopeType, $scopePart, $adminName);

        case SCOPE_NONACAD_EMPLOYEE:
            $depts = [];
            if (!empty($details['departments']) && is_array($details['departments'])) {
                $depts = array_filter(array_map('trim', $details['departments']));
            }
            $deptPart = $depts ? ('Departments: ' . implode(', ', $depts)) : 'All Departments';
            return sprintf('%s – %s – %s (Admin: %s)', $scopeType, $scopePart, $deptPart, $adminName);

        case SCOPE_SPECIAL_CSG:
            return sprintf('CSG (Special-Scope) – %s (Admin: %s)', $scopePart ?: 'Global Students', $adminName);

        case SCOPE_OTHERS_COOP:
            return sprintf('COOP – Global COOP Members (Admin: %s)', $adminName);

        case SCOPE_OTHERS_DEFAULT:
            return sprintf('Others-Default – %s (Admin: %s)', $scopePart ?: 'Custom Group', $adminName);

        default:
            return sprintf('%s – %s (Admin: %s)', $scopeType, $scopePart, $adminName);
    }
}

/**
 * Get voters for a given scope type and optional scope seat.
 *
 * Phase 1 – Patch 1.2:
 *  - Implemented:
 *      * Academic-Student
 *      * Others-COOP
 *  - Other scope types return empty arrays for now (to be filled later).
 *
 * @param PDO    $pdo
 * @param string $scopeType
 * @param int|null $scopeId  Specific scope_id (admin_scopes.scope_id), or null for global view per type
 * @param array $options
 *   - 'year_end'      => 'YYYY-MM-DD HH:MM:SS'  (optional eligibility cutoff)
 *   - 'include_flags' => bool (include is_coop_member, migs_status, is_other_member)
 *
 * @return array list of voters with normalized keys:
 * [
 *   'user_id'        => int,
 *   'email'          => string,
 *   'first_name'     => string|null,
 *   'last_name'      => string|null,
 *   'role'           => string,
 *   'position'       => string|null,
 *   'department'     => string|null,
 *   'department1'    => string|null,
 *   'course'         => string|null,
 *   'status'         => string|null,
 *   'created_at'     => string|null,
 *   'owner_scope_id' => int|null,
 *   'is_coop_member' => int|null,
 *   'migs_status'    => int|null,
 *   'is_other_member'=> int|null,
 * ]
 */
function getScopedVoters(PDO $pdo, string $scopeType, ?int $scopeId = null, array $options = []): array
{
    $yearEnd      = $options['year_end']      ?? null;
    $includeFlags = $options['include_flags'] ?? true;

    switch ($scopeType) {
        case SCOPE_ACAD_STUDENT:
            return getVotersAcademicStudent($pdo, $scopeId, $yearEnd, $includeFlags);

        case SCOPE_NONACAD_STUDENT:
            return getVotersNonAcademicStudent($pdo, $scopeId, $yearEnd, $includeFlags);

        case SCOPE_ACAD_FACULTY:
            return getVotersAcademicFaculty($pdo, $scopeId, $yearEnd, $includeFlags);

        case SCOPE_NONACAD_EMPLOYEE:
            return getVotersNonAcademicEmployee($pdo, $scopeId, $yearEnd, $includeFlags);

        case SCOPE_SPECIAL_CSG:
            return getVotersSpecialCSGGlobal($pdo, $yearEnd, $includeFlags);

        case SCOPE_OTHERS_COOP:
            return getVotersCoopGlobal($pdo, $yearEnd, $includeFlags);

        case SCOPE_OTHERS_DEFAULT:
            return getVotersOthersDefault($pdo, $scopeId, $yearEnd, $includeFlags);

        default:
            // Unknown / not yet implemented scope type
            return [];
    }
}

// Map academic course codes (BSCS, BSIT, etc.) to full course names as stored in users.course
if (!function_exists('mapCourseCodesToFullNames')) {
    function mapCourseCodesToFullNames(array $codes): array {
        // IMPORTANT: keep this in sync with your add_user/process_users_csv mappings
        static $map = [
            // CEIT
            'BSCS'   => 'BS Computer Science',
            'BSIT'   => 'BS Information Technology',
            'BSCPE'  => 'BS Computer Engineering',
            'BSECE'  => 'BS Electronics Engineering',
            'BSCE'   => 'BS Civil Engineering',
            'BSME'   => 'BS Mechanical Engineering',
            'BSEE'   => 'BS Electrical Engineering',
            'BSIE'   => 'BS Industrial Engineering',
            'BSARCH' => 'BS Architecture',

            // CAFENR
            'BSAGRI' => 'BS Agriculture',
            'BSAB'   => 'BS Agribusiness',
            'BSES'   => 'BS Environmental Science',
            'BSFT'   => 'BS Food Technology',
            'BSFOR'  => 'BS Forestry',
            'BSABE'  => 'BS Agricultural and Biosystems Engineering',
            'BAE'    => 'Bachelor of Agricultural Entrepreneurship',
            'BSLDM'  => 'BS Land Use Design and Management',

            // CAS
            'BSBIO'     => 'BS Biology',
            'BSCHEM'    => 'BS Chemistry',
            'BSMATH'    => 'BS Mathematics',
            'BSPHYSICS' => 'BS Physics',
            'BSPSYCH'   => 'BS Psychology',
            'BAELS'     => 'BA English Language Studies',
            'BACOMM'    => 'BA Communication',
            'BSSTAT'    => 'BS Statistics',

            // CVMBS
            'DVM'       => 'Doctor of Veterinary Medicine',
            'BSPV'      => 'BS Biology (Pre-Veterinary)',

            // CED
            'BEED'      => 'Bachelor of Elementary Education',
            'BSED'      => 'Bachelor of Secondary Education',
            'BPE'       => 'Bachelor of Physical Education',
            'BTLE'      => 'Bachelor of Technology and Livelihood Education',

            // CEMDS
            'BSBA'      => 'BS Business Administration',
            'BSACC'     => 'BS Accountancy',
            'BSECO'     => 'BS Economics',
            'BSENT'     => 'BS Entrepreneurship',
            'BSOA'      => 'BS Office Administration',

            // CSPEAR / CCJ / CON / CTHM / COM
            'BSESS'     => 'BS Exercise and Sports Sciences',
            'BSCRIM'    => 'BS Criminology',
            'BSN'       => 'BS Nursing',
            'BSHM'      => 'BS Hospitality Management',
            'BSTM'      => 'BS Tourism Management',
            'BLIS'      => 'Bachelor of Library and Information Science',

            // Graduate
            'PHD'       => 'Doctor of Philosophy',
            'MS'        => 'Master of Science',
            'MA'        => 'Master of Arts',
        ];

        $full = [];
        foreach ($codes as $code) {
            $code = strtoupper(trim($code));
            if ($code === '' || $code === 'ALL') {
                continue;
            }
            if (isset($map[$code])) {
                $full[] = $map[$code];
            } else {
                // if it's not a known code, treat it as already a full name
                $full[] = $code;
            }
        }

        // Remove duplicates & blanks
        $full = array_filter(array_unique($full), static fn($v) => $v !== '');
        return array_values($full);
    }
}

/**
 * Internal: Get Academic-Student voters.
 *
 * - If $scopeId is provided:
 *   -> Use admin_scopes + users.assigned_scope + scope_details['courses'].
 * - If $scopeId is null:
 *   -> Return all academic students (role='voter', position='student') globally.
 *
 * @param PDO        $pdo
 * @param int|null   $scopeId
 * @param string|null $yearEnd
 * @param bool       $includeFlags
 * @return array
 */
function getVotersAcademicStudent(PDO $pdo, ?int $scopeId, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = ["u.role = 'voter'", "u.position = 'student'"];

    $details      = [];
    $allowedCourses = []; // full course names to filter on (e.g. "BS Computer Science")

    if ($scopeId !== null) {
        // Fetch the scope seat to determine college and course scope
        $seatSql = "
            SELECT s.scope_id, s.scope_type, s.scope_details, u.assigned_scope
            FROM admin_scopes s
            JOIN users u ON u.user_id = s.user_id
            WHERE s.scope_id = :sid
              AND s.scope_type = :stype
            LIMIT 1
        ";
        $st = $pdo->prepare($seatSql);
        $st->execute([
            ':sid'   => $scopeId,
            ':stype' => SCOPE_ACAD_STUDENT,
        ]);
        $seat = $st->fetch();

        if (!$seat) {
            // No such scope seat – return empty
            return [];
        }

        // College constraint: u.department stores the college code (e.g. CEIT, CAS, ...)
        $collegeCode = strtoupper(trim($seat['assigned_scope'] ?? ''));
        if ($collegeCode !== '') {
            $where[]            = 'u.department = :college';
            $params[':college'] = $collegeCode;
        }

        // Parse scope_details to get course codes (usually like "BSCS", "BSIT", ...)
        if (!empty($seat['scope_details'])) {
            $decoded = json_decode($seat['scope_details'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        if (!empty($details['courses']) && is_array($details['courses'])) {
            // Map codes → full course names to match users.course (which stores full names)
            // Keep this map in sync with your add_user/process_users_csv mappings.
            $codeToFull = [
                // CEIT
                'BSCS'   => 'BS Computer Science',
                'BSIT'   => 'BS Information Technology',
                'BSCPE'  => 'BS Computer Engineering',
                'BSECE'  => 'BS Electronics Engineering',
                'BSCE'   => 'BS Civil Engineering',
                'BSME'   => 'BS Mechanical Engineering',
                'BSEE'   => 'BS Electrical Engineering',
                'BSIE'   => 'BS Industrial Engineering',
                'BSARCH' => 'BS Architecture',

                // CAFENR
                'BSAGRI' => 'BS Agriculture',
                'BSAB'   => 'BS Agribusiness',
                'BSES'   => 'BS Environmental Science',
                'BSFT'   => 'BS Food Technology',
                'BSFOR'  => 'BS Forestry',
                'BSABE'  => 'BS Agricultural and Biosystems Engineering',
                'BAE'    => 'Bachelor of Agricultural Entrepreneurship',
                'BSLDM'  => 'BS Land Use Design and Management',

                // CAS
                'BSBIO'     => 'BS Biology',
                'BSCHEM'    => 'BS Chemistry',
                'BSMATH'    => 'BS Mathematics',
                'BSPHYSICS' => 'BS Physics',
                'BSPSYCH'   => 'BS Psychology',
                'BAELS'     => 'BA English Language Studies',
                'BACOMM'    => 'BA Communication',
                'BSSTAT'    => 'BS Statistics',

                // CVMBS
                'DVM'       => 'Doctor of Veterinary Medicine',
                'BSPV'      => 'BS Biology (Pre-Veterinary)',

                // CED
                'BEED'      => 'Bachelor of Elementary Education',
                'BSED'      => 'Bachelor of Secondary Education',
                'BPE'       => 'Bachelor of Physical Education',
                'BTLE'      => 'Bachelor of Technology and Livelihood Education',

                // CEMDS
                'BSBA'      => 'BS Business Administration',
                'BSACC'     => 'BS Accountancy',
                'BSECO'     => 'BS Economics',
                'BSENT'     => 'BS Entrepreneurship',
                'BSOA'      => 'BS Office Administration',

                // CSPEAR / CCJ / CON / CTHM / COM
                'BSESS'     => 'BS Exercise and Sports Sciences',
                'BSCRIM'    => 'BS Criminology',
                'BSN'       => 'BS Nursing',
                'BSHM'      => 'BS Hospitality Management',
                'BSTM'      => 'BS Tourism Management',
                'BLIS'      => 'Bachelor of Library and Information Science',

                // Graduate
                'PHD'       => 'Doctor of Philosophy',
                'MS'        => 'Master of Science',
                'MA'        => 'Master of Arts',
            ];

            foreach ($details['courses'] as $c) {
                $raw = trim($c);
                if ($raw === '' || strcasecmp($raw, 'ALL') === 0) {
                    continue;
                }
                $code = strtoupper($raw);
                if (isset($codeToFull[$code])) {
                    $allowedName = $codeToFull[$code];
                } else {
                    // If not in map, assume admin stored the full name already
                    $allowedName = $raw;
                }
                $allowedCourses[] = $allowedName;
            }

            $allowedCourses = array_unique($allowedCourses);

            if (!empty($allowedCourses)) {
                $phs = [];
                foreach ($allowedCourses as $i => $name) {
                    $ph        = ':course_' . $i;
                    $phs[]     = $ph;
                    $params[$ph] = $name;
                }
                $where[] = 'u.course IN (' . implode(',', $phs) . ')';
            }
        }
    }

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.course, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Normalize / optionally strip flags
    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],
            'department1'    => $r['department1'],
            'course'         => $r['course'],
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Internal: Get Academic-Faculty voters.
 *
 * Rules:
 *  - If $scopeId is provided:
 *      * Use admin_scopes + users.assigned_scope + scope_details['departments']
 *      * role='voter' AND position='academic'
 *      * department = college code (assigned_scope)
 *      * if departments list non-empty: department1 IN (those full names)
 *  - If $scopeId is null (global):
 *      * role='voter' AND position='academic'  (all faculty)
 *
 * @param PDO         $pdo
 * @param int|null    $scopeId
 * @param string|null $yearEnd
 * @param bool        $includeFlags
 * @return array
 */
function getVotersAcademicFaculty(PDO $pdo, ?int $scopeId, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = [
        "u.role = 'voter'",
        "u.position = 'academic'",
    ];

    if ($scopeId !== null) {
        // Fetch scope seat to know college + departments
        $seatSql = "
            SELECT s.scope_id, s.scope_type, s.scope_details, u.assigned_scope
            FROM admin_scopes s
            JOIN users u ON u.user_id = s.user_id
            WHERE s.scope_id = :sid
              AND s.scope_type = :stype
            LIMIT 1
        ";
        $st = $pdo->prepare($seatSql);
        $st->execute([
            ':sid'   => $scopeId,
            ':stype' => SCOPE_ACAD_FACULTY,
        ]);
        $seat = $st->fetch();

        if (!$seat) {
            return [];
        }

        $collegeCode = strtoupper(trim($seat['assigned_scope'] ?? ''));
        if ($collegeCode !== '') {
            $where[]            = 'u.department = :college';
            $params[':college'] = $collegeCode;
        }

        $details = [];
        if (!empty($seat['scope_details'])) {
            $decoded = json_decode($seat['scope_details'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        // If scope_details['departments'] has full names, use them to filter department1
                // If scope_details['departments'] has department codes or full names,
        // normalize them to match users.department1 (full department name).
        if (!empty($details['departments']) && is_array($details['departments'])) {

            // 1) Map known short codes (DIT, DCEE, etc.) to full names
            //    Keep this in sync with your faculty mappings (same as admin_dashboard_faculty.php).
            $deptCodeToFull = [
                // CEIT
                'DCE'  => 'Department of Civil Engineering',
                'DCEE' => 'Department of Computer and Electronics Engineering',
                'DIET' => 'Department of Industrial Engineering and Technology',
                'DMEE' => 'Department of Mechanical and Electronics Engineering',
                'DIT'  => 'Department of Information Technology',

                // CAFENR
                'DAS'  => 'Department of Animal Science',
                'DCS'  => 'Department of Crop Science',
                'DFST' => 'Department of Food Science and Technology',
                'DFES' => 'Department of Forestry and Environmental Science',
                'DAED' => 'Department of Agricultural Economics and Development',

                // CAS
                'DBS'  => 'Department of Biological Sciences',
                'DPS'  => 'Department of Physical Sciences',
                'DLMC' => 'Department of Languages and Mass Communication',
                'DSS'  => 'Department of Social Sciences',
                'DMS'  => 'Department of Mathematics and Statistics',

                // CEMDS
                'DE'   => 'Department of Economics',
                'DBM'  => 'Department of Business and Management',
                'DDS'  => 'Department of Development Studies',

                // CED
                'DSE'  => 'Department of Science Education',
                'DTLE' => 'Department of Technology and Livelihood Education',
                'DCI'  => 'Department of Curriculum and Instruction',
                'DHK'  => 'Department of Human Kinetics',

                // CON
                'DN'   => 'Department of Nursing',

                // CSPEAR
                'DPER' => 'Department of Physical Education and Recreation',

                // CVMBS
                'DVM'  => 'Department of Veterinary Medicine',
                'DBS' => 'Department of Biomedical Sciences',

                // COM
                'DBMS' => 'Department of Basic Medical Sciences',
                'DCS'  => 'Department of Clinical Sciences',

                // GS-OLC
                'DVGP' => 'Department of Various Graduate Programs',
            ];

            // 2) Normalize values: if it's a code, convert to full name; if not, use as-is
            $normalizedDeptNames = [];
            foreach ($details['departments'] as $raw) {
                $name = trim($raw);
                if ($name === '') {
                    continue;
                }

                $upper = strtoupper($name);
                if (isset($deptCodeToFull[$upper])) {
                    $normalizedDeptNames[] = $deptCodeToFull[$upper];
                } else {
                    // assume already full department1 name
                    $normalizedDeptNames[] = $name;
                }
            }

            $normalizedDeptNames = array_values(array_unique($normalizedDeptNames));

            if ($normalizedDeptNames) {
                $placeholders = [];
                foreach ($normalizedDeptNames as $i => $name) {
                    $ph               = ':d_' . $i;
                    $placeholders[]   = $ph;
                    $params[$ph]      = $name;
                }
                $where[] = 'u.department1 IN (' . implode(',', $placeholders) . ')';
            }
        }
    }

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.department1, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],   // college code
            'department1'    => $r['department1'],  // full dept name
            'course'         => $r['course'],
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Internal: Get Non-Academic-Employee voters.
 *
 * Rules (matching Non-Academic-Employee dashboard):
 *  - Always: role='voter' AND position='non-academic'
 *  - If $scopeId is provided:
 *      * Read scope_details['departments'] (codes like ADMIN, LIBRARY, HR, ...)
 *      * If non-empty: u.department IN (those codes)
 *  - Global (scopeId null): all non-academic voters
 *
 * @param PDO         $pdo
 * @param int|null    $scopeId
 * @param string|null $yearEnd
 * @param bool        $includeFlags
 * @return array
 */
function getVotersNonAcademicEmployee(PDO $pdo, ?int $scopeId, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = [
        "u.role = 'voter'",
        "u.position = 'non-academic'",
    ];

    if ($scopeId !== null) {
        $seatSql = "
            SELECT s.scope_id, s.scope_details
            FROM admin_scopes s
            WHERE s.scope_id = :sid
              AND s.scope_type = :stype
            LIMIT 1
        ";
        $st = $pdo->prepare($seatSql);
        $st->execute([
            ':sid'   => $scopeId,
            ':stype' => SCOPE_NONACAD_EMPLOYEE,
        ]);
        $seat = $st->fetch();

        if (!$seat) {
            return [];
        }

        $details = [];
        if (!empty($seat['scope_details'])) {
            $decoded = json_decode($seat['scope_details'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        if (!empty($details['departments']) && is_array($details['departments'])) {
            $deptCodes = array_values(array_filter(array_map('trim', $details['departments'])));
            if ($deptCodes) {
                $placeholders = [];
                foreach ($deptCodes as $i => $code) {
                    $ph = ':nd_' . $i;
                    $placeholders[]    = $ph;
                    $params[$ph]       = $code; // e.g. ADMIN, LIBRARY
                }
                $where[] = 'u.department IN (' . implode(',', $placeholders) . ')';
            }
        }
    }

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],   // e.g. ADMIN, LIBRARY
            'department1'    => $r['department1'],  // usually null for non-ac
            'course'         => $r['course'],
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Internal: Get Special-Scope (CSG) voters.
 *
 * Current design:
 *  - Treat as global student body:
 *      role='voter'
 *      position='student'
 *  - $scopeId is ignored for now (CSG is global).
 *
 * @param PDO         $pdo
 * @param string|null $yearEnd
 * @param bool        $includeFlags
 * @return array
 */
function getVotersSpecialCSGGlobal(PDO $pdo, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = [
        "u.role = 'voter'",
        "u.position = 'student'",
    ];

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.department1, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],   // college code
            'department1'    => $r['department1'],  // full dept
            'course'         => $r['course'],       // full course name
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Internal: Get COOP voters (global).
 *
 * Scope behaviour:
 *  - Scope seat (scopeId) does NOT restrict voters for now.
 *  - We return all users with is_coop_member = 1 and migs_status = 1.
 *
 * @param PDO        $pdo
 * @param string|null $yearEnd
 * @param bool       $includeFlags
 * @return array
 */
function getVotersCoopGlobal(PDO $pdo, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = [
        "u.role = 'voter'",
        "u.is_coop_member = 1",
        "u.migs_status = 1",
    ];

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],
            'department1'    => $r['department1'],
            'course'         => $r['course'],
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Internal: Get Non-Academic-Student voters.
 *
 * Rules:
 *  - Per scope seat:
 *      role = 'voter'
 *      position = 'student'
 *      owner_scope_id = :scopeId
 *  - Global (scopeId = null):
 *      role = 'voter'
 *      position = 'student'
 *      owner_scope_id IN (all scope_ids where scope_type = 'Non-Academic-Student')
 *
 * @param PDO         $pdo
 * @param int|null    $scopeId
 * @param string|null $yearEnd
 * @param bool        $includeFlags
 * @return array
 */
function getVotersNonAcademicStudent(PDO $pdo, ?int $scopeId, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = [
        "u.role = 'voter'",
        "u.position = 'student'",
    ];

    if ($scopeId !== null) {
        // Specific Non-Academic-Student seat: use owner_scope_id
        $where[]           = 'u.owner_scope_id = :sid';
        $params[':sid']    = $scopeId;
    } else {
        // Global Non-Academic-Student: all students attached to any Non-Academic-Student scope seat
        $seatStmt = $pdo->prepare("
            SELECT scope_id
            FROM admin_scopes
            WHERE scope_type = :stype
        ");
        $seatStmt->execute([':stype' => SCOPE_NONACAD_STUDENT]);
        $ids = $seatStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$ids) {
            return [];
        }

        $placeholders = [];
        foreach ($ids as $i => $id) {
            $ph = ':sid_' . $i;
            $placeholders[]     = $ph;
            $params[$ph]        = (int)$id;
        }
        $where[] = 'u.owner_scope_id IN (' . implode(',', $placeholders) . ')';
    }

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.department1, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],
            'department1'    => $r['department1'],
            'course'         => $r['course'],
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Internal: Get Others-Default voters.
 *
 * Rules:
 *  - Per scope seat:
 *      role = 'voter'
 *      is_other_member = 1
 *      owner_scope_id = :scopeId
 *  - Global (scopeId = null):
 *      role = 'voter'
 *      is_other_member = 1
 *
 * @param PDO         $pdo
 * @param int|null    $scopeId
 * @param string|null $yearEnd
 * @param bool        $includeFlags
 * @return array
 */
function getVotersOthersDefault(PDO $pdo, ?int $scopeId, ?string $yearEnd, bool $includeFlags): array
{
    $params = [];
    $where  = [
        "u.role = 'voter'",
        "u.is_other_member = 1",
    ];

    if ($scopeId !== null) {
        $where[]           = 'u.owner_scope_id = :sid';
        $params[':sid']    = $scopeId;
    }

    if ($yearEnd !== null) {
        $where[]             = '(u.created_at IS NULL OR u.created_at <= :year_end)';
        $params[':year_end'] = $yearEnd;
    }

    $sql = "
        SELECT
            u.user_id,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.position,
            u.department,
            u.department1,
            u.course,
            u.status,
            u.created_at,
            u.owner_scope_id,
            u.is_coop_member,
            u.migs_status,
            u.is_other_member
        FROM users u
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.department, u.last_name, u.first_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $row = [
            'user_id'        => (int)$r['user_id'],
            'email'          => $r['email'],
            'first_name'     => $r['first_name'],
            'last_name'      => $r['last_name'],
            'role'           => $r['role'],
            'position'       => $r['position'],
            'department'     => $r['department'],
            'department1'    => $r['department1'],
            'course'         => $r['course'],
            'status'         => $r['status'],
            'created_at'     => $r['created_at'],
            'owner_scope_id' => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
        ];

        if ($includeFlags) {
            $row['is_coop_member']  = isset($r['is_coop_member'])  ? (int)$r['is_coop_member']  : null;
            $row['migs_status']     = isset($r['migs_status'])     ? (int)$r['migs_status']     : null;
            $row['is_other_member'] = isset($r['is_other_member']) ? (int)$r['is_other_member'] : null;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Generic election fetcher by scope type + scope seat.
 *
 * Supported scope types (same constants used by getScopedVoters):
 *
 *   SCOPE_ACAD_STUDENT      => election_scope_type = 'Academic-Student'
 *   SCOPE_ACAD_FACULTY      => 'Academic-Faculty'
 *   SCOPE_NONACAD_STUDENT   => 'Non-Academic-Student'
 *   SCOPE_NONACAD_EMPLOYEE  => 'Non-Academic-Employee'
 *   SCOPE_OTHERS_COOP       => 'Others-COOP'
 *   SCOPE_OTHERS_DEFAULT    => 'Others-Default'
 *   SCOPE_SPECIAL_CSG       => 'Special-Scope'
 *
 * $scopeId:
 *   - If NOT null: filter by e.owner_scope_id = $scopeId (per scope seat)
 *   - If null: return all elections of that election_scope_type (global view)
 *
 * $options:
 *   - 'from_year' => int|null   (e.g. 2023)
 *   - 'to_year'   => int|null   (e.g. 2025)
 *   - 'status'    => string|null (e.g. 'ongoing', 'completed', 'ended')
 *
 * Returns: array of rows with at least:
 *   - election_id
 *   - title
 *   - election_scope_type
 *   - owner_scope_id
 *   - assigned_admin_id
 *   - start_datetime
 *   - end_datetime
 *   - status
 *   - target_position
 */
function getScopedElections(PDO $pdo, string $scopeType, ?int $scopeId = null, array $options = []): array
{
    // Map our internal scope constants to the DB election_scope_type values
    switch ($scopeType) {
        case SCOPE_ACAD_STUDENT:
            $scopeName = 'Academic-Student';
            break;
        case SCOPE_ACAD_FACULTY:
            $scopeName = 'Academic-Faculty';
            break;
        case SCOPE_NONACAD_STUDENT:
            $scopeName = 'Non-Academic-Student';
            break;
        case SCOPE_NONACAD_EMPLOYEE:
            $scopeName = 'Non-Academic-Employee';
            break;
        case SCOPE_OTHERS_COOP:
            $scopeName = 'Others-COOP';
            break;
        case SCOPE_OTHERS_DEFAULT:
            $scopeName = 'Others-Default';
            break;
        case SCOPE_SPECIAL_CSG:
            $scopeName = 'Special-Scope';
            break;
        default:
            // Unknown scope type → no elections
            return [];
    }

    $params = [
        ':scope_type' => $scopeName,
    ];
    $where  = ["e.election_scope_type = :scope_type"];

    if ($scopeId !== null) {
        $where[]            = 'e.owner_scope_id = :scope_id';
        $params[':scope_id'] = $scopeId;
    }

    if (!empty($options['status'])) {
        $where[]            = 'e.status = :status';
        $params[':status']  = $options['status'];
    }

    if (!empty($options['from_year'])) {
        $where[]               = 'YEAR(e.start_datetime) >= :from_year';
        $params[':from_year']  = (int)$options['from_year'];
    }

    if (!empty($options['to_year'])) {
        $where[]             = 'YEAR(e.start_datetime) <= :to_year';
        $params[':to_year']  = (int)$options['to_year'];
    }

    $sql = "
        SELECT
            e.election_id,
            e.title,
            e.election_scope_type,
            e.owner_scope_id,
            e.assigned_admin_id,
            e.target_position,
            e.status,
            e.start_datetime,
            e.end_datetime
        FROM elections e
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.start_datetime DESC, e.election_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'election_id'         => (int)$r['election_id'],
            'title'               => $r['title'],
            'election_scope_type' => $r['election_scope_type'],
            'owner_scope_id'      => $r['owner_scope_id'] !== null ? (int)$r['owner_scope_id'] : null,
            'assigned_admin_id'   => $r['assigned_admin_id'] !== null ? (int)$r['assigned_admin_id'] : null,
            'target_position'     => $r['target_position'],
            'status'              => $r['status'],
            'start_datetime'      => $r['start_datetime'],
            'end_datetime'        => $r['end_datetime'],
        ];
    }

    return $out;
}

/**
 * Global turnout by year (all elections, all voter positions).
 *
 * Rules:
 *  - For each year where there is at least one election:
 *      - total_voted:
 *          COUNT(DISTINCT v.voter_id)
 *          FROM votes v JOIN elections e ON v.election_id = e.election_id
 *          WHERE YEAR(e.start_datetime) = year
 *      - total_eligible:
 *          COUNT(*) FROM users u
 *          WHERE u.role = 'voter'
 *            AND (u.created_at IS NULL OR u.created_at <= 'YYYY-12-31 23:59:59')
 *      - turnout_rate = total_voted / total_eligible * 100
 *
 * @param PDO      $pdo
 * @param int|null $yearsBack  If set, only include last N calendar years (from current year)
 * @return array array keyed by year:
 *   [
 *      2024 => [
 *          'year'           => 2024,
 *          'total_voted'    => 123,
 *          'total_eligible' => 456,
 *          'turnout_rate'   => 27.0,
 *      ],
 *      ...
 *   ]
 */
function getGlobalTurnoutByYear(PDO $pdo, ?int $yearsBack = null): array
{
    // 1. Find all years that have at least one election
    $stmt = $pdo->query("
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections
        ORDER BY YEAR(start_datetime)
    ");
    $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$years) {
        return [];
    }

    // Optionally limit to last N years
    if ($yearsBack !== null) {
        $currentYear = (int)date('Y');
        $minYear     = $currentYear - $yearsBack + 1;
        $years       = array_values(array_filter($years, function ($y) use ($minYear) {
            return $y >= $minYear;
        }));
        if (!$years) {
            return [];
        }
    }

    $data = [];

    foreach ($years as $year) {
        // 2. total_voted for that year
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.voter_id) AS total_voted
            FROM votes v
            JOIN elections e ON v.election_id = e.election_id
            WHERE YEAR(e.start_datetime) = :year
        ");
        $stmt->execute([':year' => $year]);
        $totalVoted = (int)($stmt->fetch()['total_voted'] ?? 0);

        // 3. total_eligible = all voters existing by Dec 31 that year
        $yearEnd = sprintf('%04d-12-31 23:59:59', $year);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total_eligible
            FROM users u
            WHERE u.role = 'voter'
              AND (u.created_at IS NULL OR u.created_at <= :year_end)
        ");
        $stmt->execute([':year_end' => $yearEnd]);
        $totalEligible = (int)($stmt->fetch()['total_eligible'] ?? 0);

        $turnoutRate = ($totalEligible > 0)
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0.0;

        $data[$year] = [
            'year'           => $year,
            'total_voted'    => $totalVoted,
            'total_eligible' => $totalEligible,
            'turnout_rate'   => $turnoutRate,
        ];
    }

    return $data;
}

/**
 * Compute turnout by year for a given scope type + scope seat.
 *
 * Output format (keyed by year, e.g. 2024 => [...]):
 * [
 *   2024 => [
 *     'year'           => 2024,
 *     'total_voted'    => 123,
 *     'total_eligible' => 456,
 *     'turnout_rate'   => 27.0,
 *     'election_count' => 3,
 *     'growth_rate'    => 0,   // filled after sequence
 *   ],
 *   ...
 * ]
 *
 * @param PDO      $pdo
 * @param string   $scopeType   (matches election_scope_type)
 * @param int|null $scopeId     (matches owner_scope_id for scoped elections)
 * @param array    $scopedVoters result of getScopedVoters(...) for this same scope
 * @param array    $config      ['year_from' => ?, 'year_to' => ?] optional
 * @return array
 */
function computeTurnoutByYear(PDO $pdo, string $scopeType, ?int $scopeId, array $scopedVoters, array $config = []): array
{
    $yearFrom = $config['year_from'] ?? null;
    $yearTo   = $config['year_to']   ?? null;

    // 1) Find all years that have elections for this scope type/seat
    $params = [':stype' => $scopeType];
    $where  = ['election_scope_type = :stype'];

    if ($scopeId !== null) {
        $where[]             = 'owner_scope_id = :sid';
        $params[':sid']      = $scopeId;
    }

    $sqlYears = "
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections
        WHERE " . implode(' AND ', $where) . "
        ORDER BY year ASC
    ";

    $stmt = $pdo->prepare($sqlYears);
    $stmt->execute($params);
    $years = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // Always include current & previous year for comparison, even if no elections there
    $currentYear = (int)date('Y');
    $prevYear    = $currentYear - 1;

    if (!in_array($currentYear, $years, true)) {
        $years[] = $currentYear;
    }
    if (!in_array($prevYear, $years, true)) {
        $years[] = $prevYear;
    }

    // Apply optional year_from / year_to filters
    if ($yearFrom !== null) {
        $years = array_filter($years, static fn($y) => $y >= $yearFrom);
    }
    if ($yearTo !== null) {
        $years = array_filter($years, static fn($y) => $y <= $yearTo);
    }

    $years = array_values(array_unique($years));
    sort($years);

    //make years a continuous range (no gaps)
    if ($years) {
        $minYear = min($years);
        $maxYear = max($years);
        $years   = range($minYear, $maxYear); // e.g. 2022, 2023, 2024, 2025
    }

    if (!$years) {
        return [];
    }

    // 2) Pre-index scoped voters by year (eligible as of Dec 31)
    $eligibleByYear = [];
    foreach ($years as $y) {
        $eligibleByYear[$y] = 0;
    }

    foreach ($scopedVoters as $v) {
        $createdAt = $v['created_at'] ?? null;
        if (!$createdAt) {
            // If no created_at, treat as existing for all years
            foreach ($years as $y) {
                $eligibleByYear[$y]++;
            }
            continue;
        }

        $createdTs = strtotime($createdAt);
        foreach ($years as $y) {
            $yearEndTs = strtotime($y . '-12-31 23:59:59');
            if ($createdTs <= $yearEndTs) {
                $eligibleByYear[$y]++;
            }
        }
    }

    // 3) For each year, count distinct voters & elections from DB
    $turnoutData = [];

    foreach ($years as $y) {
        // Distinct voters who voted in elections of this scope in that year
        $paramsVote = [':year' => $y, ':stype' => $scopeType];
        $whereVote  = ['e.election_scope_type = :stype', 'YEAR(e.start_datetime) = :year'];

        if ($scopeId !== null) {
            $whereVote[]           = 'e.owner_scope_id = :sid';
            $paramsVote[':sid']    = $scopeId;
        }

        $sqlVoted = "
            SELECT COUNT(DISTINCT v.voter_id) AS total_voted
            FROM votes v
            JOIN elections e ON v.election_id = e.election_id
            WHERE " . implode(' AND ', $whereVote) . "
        ";
        $stV = $pdo->prepare($sqlVoted);
        $stV->execute($paramsVote);
        $totalVoted = (int)($stV->fetch()['total_voted'] ?? 0);

        // Number of elections for this scope/year
        $paramsEl = [':year' => $y, ':stype' => $scopeType];
        $whereEl  = ['election_scope_type = :stype', 'YEAR(start_datetime) = :year'];

        if ($scopeId !== null) {
            $whereEl[]           = 'owner_scope_id = :sid';
            $paramsEl[':sid']    = $scopeId;
        }

        $sqlEl = "
            SELECT COUNT(*) AS election_count
            FROM elections
            WHERE " . implode(' AND ', $whereEl) . "
        ";
        $stE = $pdo->prepare($sqlEl);
        $stE->execute($paramsEl);
        $electionCount = (int)($stE->fetch()['election_count'] ?? 0);

        $totalEligible = (int)($eligibleByYear[$y] ?? 0);
        $turnoutRate   = $totalEligible > 0
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0.0;

        $turnoutData[$y] = [
            'year'           => $y,
            'total_voted'    => $totalVoted,
            'total_eligible' => $totalEligible,
            'turnout_rate'   => $turnoutRate,
            'election_count' => $electionCount,
            'growth_rate'    => 0, // fill in next pass
        ];
    }
    // 3b) If a year has NO elections, zero-out eligible/voted/turnout
    //     para consistent sa UI text: "years with no elections will appear with zero values".
    foreach ($turnoutData as $yearKey => &$row) {
        if (($row['election_count'] ?? 0) === 0) {
            $row['total_voted']    = 0;
            $row['total_eligible'] = 0;
            $row['turnout_rate']   = 0.0;
        }
    }
    unset($row);

    // 4) Compute growth_rate year-over-year within this range
    $orderedYears = array_keys($turnoutData);
    sort($orderedYears);

    $prevYearKey = null;
    foreach ($orderedYears as $y) {
        if ($prevYearKey !== null) {
            $prevRate = $turnoutData[$prevYearKey]['turnout_rate'];
            $currRate = $turnoutData[$y]['turnout_rate'];
            $growth   = $prevRate > 0
                ? round((($currRate - $prevRate) / $prevRate) * 100, 1)
                : 0.0;
            $turnoutData[$y]['growth_rate'] = $growth;
        } else {
            $turnoutData[$y]['growth_rate'] = 0.0;
        }
        $prevYearKey = $y;
    }

    return $turnoutData;
}

/**
 * Compute per-election stats (eligible, voted, turnout, abstain) for a given
 * scope + year.
 *
 * This is scope-based (election_scope_type + owner_scope_id), not assigned_admin_id.
 *
 * @param PDO    $pdo
 * @param string $scopeType   one of SCOPE_* constants (e.g. SCOPE_ACAD_STUDENT)
 * @param int    $scopeId     admin_scopes.scope_id for this seat
 * @param array  $scopedVoters result of getScopedVoters(...) for the same scope seat
 * @param int    $year        e.g. 2025
 *
 * @return array of:
 * [
 *   'election_id'    => int,
 *   'title'          => string,
 *   'year'           => int,
 *   'total_eligible' => int,
 *   'total_voted'    => int,
 *   'turnout_rate'   => float,
 *   'abstain_count'  => int,
 *   'abstain_rate'   => float,
 *   'status'         => string|null,
 *   'start_datetime' => string,
 *   'end_datetime'   => string|null,
 *   'allowed_courses'=> string|null,
 * ]
 */
function computePerElectionStatsWithAbstain(
    PDO $pdo,
    string $scopeType,
    int $scopeId,
    array $scopedVoters,
    int $year
): array {
    // Map scopeType → election_scope_type string in DB
    switch ($scopeType) {
        case SCOPE_ACAD_STUDENT:
            $scopeName = 'Academic-Student';
            break;
        case SCOPE_ACAD_FACULTY:
            $scopeName = 'Academic-Faculty';
            break;
        case SCOPE_NONACAD_STUDENT:
            $scopeName = 'Non-Academic-Student';
            break;
        case SCOPE_NONACAD_EMPLOYEE:
            $scopeName = 'Non-Academic-Employee';
            break;
        case SCOPE_OTHERS_COOP:
            $scopeName = 'Others-COOP';
            break;
        case SCOPE_OTHERS_DEFAULT:
            $scopeName = 'Others-Default';
            break;
        case SCOPE_SPECIAL_CSG:
            $scopeName = 'Special-Scope';
            break;
        default:
            return [];
    }

    // 1) Get all elections for this scope seat + year
    $sql = "
        SELECT 
            e.election_id,
            e.title,
            e.start_datetime,
            e.end_datetime,
            e.status,
            e.allowed_courses
        FROM elections e
        WHERE e.election_scope_type = :stype
          AND e.owner_scope_id = :sid
          AND YEAR(e.start_datetime) = :year
        ORDER BY e.start_datetime ASC, e.election_id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':stype' => $scopeName,
        ':sid'   => $scopeId,
        ':year'  => $year,
    ]);
    $elections = $stmt->fetchAll();
    if (!$elections) {
        return [];
    }

    // 2) For each election, compute eligible / voted / abstain
    $results = [];

    foreach ($elections as $erow) {
        $eid   = (int)$erow['election_id'];
        $title = $erow['title'];
        $end   = $erow['end_datetime'] ?: sprintf('%04d-12-31 23:59:59', $year);

        // Parse allowed_courses (course codes) for this election
        $allowedCodesNorm = [];
        $rawAllowed = trim($erow['allowed_courses'] ?? '');
        if ($rawAllowed !== '' && strcasecmp($rawAllowed, 'ALL') !== 0) {
            $parts = array_filter(array_map('trim', explode(',', $rawAllowed)));
            foreach ($parts as $c) {
                $allowedCodesNorm[] = strtoupper($c);
            }
            $allowedCodesNorm = array_unique($allowedCodesNorm);
        }

        // Eligible = scoped voters as of election end, optionally filtered by allowed_courses
        $eligibleIds = [];

        foreach ($scopedVoters as $v) {
            $createdAt = $v['created_at'] ?? null;
            if ($createdAt && $createdAt > $end) {
                continue;
            }

            // If election has specific allowed_courses, filter by course code
            if (!empty($allowedCodesNorm)) {
                $courseName = $v['course'] ?? '';
                if ($courseName === '') {
                    continue;
                }

                // map election course codes -> full names
                $fullAllowed = mapCourseCodesToFullNames($allowedCodesNorm);
                if (!in_array($courseName, $fullAllowed, true)) {
                    continue;
                }
            }

            $eligibleIds[] = (int)$v['user_id'];
        }

        $totalEligible = count($eligibleIds);
        $totalVoted    = 0;
        $abstainCount  = 0;

        if ($totalEligible > 0) {
            $ph = implode(',', array_fill(0, $totalEligible, '?'));
            $sqlVotes = "
                SELECT
                    voter_id,
                    SUM(CASE WHEN is_abstain = 1 THEN 1 ELSE 0 END) AS abstain_rows,
                    SUM(CASE WHEN is_abstain = 0 THEN 1 ELSE 0 END) AS normal_rows
                FROM votes
                WHERE election_id = ?
                  AND voter_id IN ($ph)
                GROUP BY voter_id
            ";
            $params = array_merge([$eid], $eligibleIds);
            $stmtV  = $pdo->prepare($sqlVotes);
            $stmtV->execute($params);
            $rowsV = $stmtV->fetchAll();

            $totalVoted = count($rowsV);

            foreach ($rowsV as $vr) {
                $abRows   = (int)($vr['abstain_rows'] ?? 0);
                $normRows = (int)($vr['normal_rows'] ?? 0);
                // Abstained = may abstain row, walang normal row (walang binotong candidate)
                if ($abRows > 0 && $normRows === 0) {
                    $abstainCount++;
                }
            }
        }

        $turnoutRate = $totalEligible > 0
            ? round(($totalVoted / $totalEligible) * 100, 1)
            : 0.0;

        $abstainRate = $totalEligible > 0
            ? round(($abstainCount / $totalEligible) * 100, 1)
            : 0.0;

        $results[] = [
            'election_id'    => $eid,
            'title'          => $title,
            'year'           => (int)date('Y', strtotime($erow['start_datetime'])),
            'total_eligible' => $totalEligible,
            'total_voted'    => $totalVoted,
            'turnout_rate'   => $turnoutRate,
            'abstain_count'  => $abstainCount,
            'abstain_rate'   => $abstainRate,
            'status'         => $erow['status'] ?? null,
            'start_datetime' => $erow['start_datetime'],
            'end_datetime'   => $erow['end_datetime'],
            'allowed_courses'=> $erow['allowed_courses'] ?? null,
        ];
    }

    return $results;
}

/**
 * Compute abstain stats by year for a given scope type + scope seat.
 *
 * Similar shape to computeTurnoutByYear but focuses on:
 *  - abstain_count: # of distinct voters who abstained (only-abstain, no normal votes)
 *  - total_eligible: scoped voters as of year end
 *  - abstain_rate: abstain_count / total_eligible * 100
 *
 * @param PDO    $pdo
 * @param string $scopeType
 * @param int    $scopeId      scope seat (admin_scopes.scope_id)
 * @param array  $scopedVoters result of getScopedVoters(...)
 * @param array  $config       ['year_from' => ?, 'year_to' => ?]
 *
 * @return array keyed by year:
 *   [
 *     2024 => [
 *       'year'           => 2024,
 *       'abstain_count'  => 15,
 *       'total_eligible' => 300,
 *       'abstain_rate'   => 5.0,
 *     ],
 *     ...
 *   ]
 */
function computeAbstainByYear(
    PDO $pdo,
    string $scopeType,
    int $scopeId,
    array $scopedVoters,
    array $config = []
): array {
    // Map scopeType → election_scope_type
    switch ($scopeType) {
        case SCOPE_ACAD_STUDENT:
            $scopeName = 'Academic-Student';
            break;
        case SCOPE_ACAD_FACULTY:
            $scopeName = 'Academic-Faculty';
            break;
        case SCOPE_NONACAD_STUDENT:
            $scopeName = 'Non-Academic-Student';
            break;
        case SCOPE_NONACAD_EMPLOYEE:
            $scopeName = 'Non-Academic-Employee';
            break;
        case SCOPE_OTHERS_COOP:
            $scopeName = 'Others-COOP';
            break;
        case SCOPE_OTHERS_DEFAULT:
            $scopeName = 'Others-Default';
            break;
        case SCOPE_SPECIAL_CSG:
            $scopeName = 'Special-Scope';
            break;
        default:
            return [];
    }

    $yearFrom = $config['year_from'] ?? null;
    $yearTo   = $config['year_to']   ?? null;

    // 1) Collect all years with elections for this scope seat
    $paramsY = [
        ':stype' => $scopeName,
        ':sid'   => $scopeId,
    ];
    $sqlYears = "
        SELECT DISTINCT YEAR(start_datetime) AS year
        FROM elections
        WHERE election_scope_type = :stype
          AND owner_scope_id = :sid
        ORDER BY year ASC
    ";
    $stY = $pdo->prepare($sqlYears);
    $stY->execute($paramsY);
    $years = array_map('intval', $stY->fetchAll(PDO::FETCH_COLUMN));

    if (!$years) {
        return [];
    }

    if ($yearFrom !== null) {
        $years = array_filter($years, static fn($y) => $y >= $yearFrom);
    }
    if ($yearTo !== null) {
        $years = array_filter($years, static fn($y) => $y <= $yearTo);
    }

    $years = array_values(array_unique($years));
    if (!$years) {
        return [];
    }

    sort($years);

    // 2) Precompute total_eligible per year using scopedVoters
    $eligibleByYear = [];
    foreach ($years as $y) {
        $eligibleByYear[$y] = 0;
    }
    foreach ($scopedVoters as $v) {
        $createdAt = $v['created_at'] ?? null;
        if (!$createdAt) {
            foreach ($years as $y) {
                $eligibleByYear[$y]++;
            }
            continue;
        }
        $createdTs = strtotime($createdAt);
        foreach ($years as $y) {
            $yearEndTs = strtotime($y . '-12-31 23:59:59');
            if ($createdTs <= $yearEndTs) {
                $eligibleByYear[$y]++;
            }
        }
    }

    // 3) For each year, compute abstain_count using votes + elections
    $data = [];
    foreach ($years as $y) {
        // Get elections of this year for this scope seat
        $sel = $pdo->prepare("
            SELECT election_id
            FROM elections
            WHERE election_scope_type = :stype
              AND owner_scope_id       = :sid
              AND YEAR(start_datetime) = :year
        ");
        $sel->execute([
            ':stype' => $scopeName,
            ':sid'   => $scopeId,
            ':year'  => $y,
        ]);
        $eids = array_map('intval', $sel->fetchAll(PDO::FETCH_COLUMN));
        if (!$eids) {
            $data[$y] = [
                'year'           => $y,
                'abstain_count'  => 0,
                'total_eligible' => (int)($eligibleByYear[$y] ?? 0),
                'abstain_rate'   => 0.0,
            ];
            continue;
        }

        // Distinct voters who abstained in ANY election that year (within this scope)
        $placeholders = implode(',', array_fill(0, count($eids), '?'));
        $sqlAb = "
            SELECT
                v.voter_id,
                SUM(CASE WHEN v.is_abstain = 1 THEN 1 ELSE 0 END) AS abstain_rows,
                SUM(CASE WHEN v.is_abstain = 0 THEN 1 ELSE 0 END) AS normal_rows
            FROM votes v
            WHERE v.election_id IN ($placeholders)
            GROUP BY v.voter_id
        ";
        $stAb = $pdo->prepare($sqlAb);
        $stAb->execute($eids);
        $rowsAb = $stAb->fetchAll();

        $abstainSet = [];
        foreach ($rowsAb as $r) {
            $abRows   = (int)($r['abstain_rows'] ?? 0);
            $normRows = (int)($r['normal_rows'] ?? 0);
            if ($abRows > 0 && $normRows === 0) {
                $abstainSet[(int)$r['voter_id']] = true;
            }
        }

        $abstainCount  = count($abstainSet);
        $totalEligible = (int)($eligibleByYear[$y] ?? 0);
        $abstainRate   = $totalEligible > 0
            ? round(($abstainCount / $totalEligible) * 100, 1)
            : 0.0;

        $data[$y] = [
            'year'           => $y,
            'abstain_count'  => $abstainCount,
            'total_eligible' => $totalEligible,
            'abstain_rate'   => $abstainRate,
        ];
    }

    return $data;
}
