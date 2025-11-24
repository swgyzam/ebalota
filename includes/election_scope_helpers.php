<?php
// includes/election_scope_helpers.php

/**
 * Shared election-scope helpers for admin pages
 * Used by:
 *  - admin_view_elections.php
 *  - admin_analytics.php
 */

function normalize_course_code(string $raw): string {
    $s = strtoupper(trim($raw));
    if ($s === '') return 'UNSPECIFIED';

    $s = preg_replace('/[.\-_,]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    $replacements = [
        'BACHELOR OF SCIENCE IN ' => 'BS ',
        'BACHELOR OF SCIENCE '    => 'BS ',
        'BACHELOR OF '            => 'B ',
        'INFORMATION TECHNOLOGY'  => 'IT',
        'COMPUTER SCIENCE'        => 'CS',
        'COMPUTER ENGINEERING'    => 'CPE',
        'ELECTRONICS ENGINEERING' => 'ECE',
        'CIVIL ENGINEERING'       => 'CE',
        'MECHANICAL ENGINEERING'  => 'ME',
        'ELECTRICAL ENGINEERING'  => 'EE',
        'INDUSTRIAL ENGINEERING'  => 'IE',
        'AGRICULTURE'             => 'AGRI',
        'AGRIBUSINESS'            => 'AB',
        'ENVIRONMENTAL SCIENCE'   => 'ES',
        'FOOD TECHNOLOGY'         => 'FT',
        'FORESTRY'                => 'FOR',
        'AGRICULTURAL AND BIOSYSTEMS ENGINEERING' => 'ABE',
        'AGRICULTURAL ENTREPRENEURSHIP'           => 'AE',
        'LAND USE DESIGN AND MANAGEMENT'          => 'LDM',
        'BIOLOGY'                 => 'BIO',
        'CHEMISTRY'               => 'CHEM',
        'MATHEMATICS'             => 'MATH',
        'PHYSICS'                 => 'PHYSICS',
        'PSYCHOLOGY'              => 'PSYCH',
        'ENGLISH LANGUAGE STUDIES'=> 'ELS',
        'COMMUNICATION'           => 'COMM',
        'STATISTICS'              => 'STAT',
        'CRIMINOLOGY'             => 'CRIM',
        'NURSING'                 => 'N',
        'HOSPITALITY MANAGEMENT'  => 'HM',
        'TOURISM MANAGEMENT'      => 'TM',
        'LIBRARY AND INFORMATION SCIENCE' => 'LIS',
        'LIBRARY & INFORMATION SCIENCE'   => 'LIS',
        'EXERCISE AND SPORTS SCIENCES'    => 'ESS',
        'OFFICE ADMINISTRATION'   => 'OA',
        'ENTREPRENEURSHIP'        => 'ENT',
        'ECONOMICS'               => 'ECO',
        'ACCOUNTANCY'             => 'ACC',
        'SECONDARY EDUCATION'     => 'SED',
        'ELEMENTARY EDUCATION'    => 'EED',
        'PHYSICAL EDUCATION'      => 'PE',
        'TECHNOLOGY AND LIVELIHOOD EDUCATION' => 'TLE',
        'PRE VETERINARY'          => 'PV',
        'VETERINARY MEDICINE'     => 'DVM',
    ];
    foreach ($replacements as $from => $to) {
        $s = str_replace($from, $to, $s);
    }

    $s       = preg_replace('/\s+/', ' ', trim($s));
    $noSpace = str_replace(' ', '', $s);

    $patterns = [
        '/^BSIT$/'      => 'BSIT',
        '/^BSCS$/'      => 'BSCS',
        '/^BSCPE$/'     => 'BSCpE',
        '/^BSECE$/'     => 'BSECE',
        '/^BSCE$/'      => 'BSCE',
        '/^BSME$/'      => 'BSME',
        '/^BSEE$/'      => 'BSEE',
        '/^BSIE$/'      => 'BSIE',
        '/^BSAGRI$/'    => 'BSAgri',
        '/^BSAB$/'      => 'BSAB',
        '/^BSES$/'      => 'BSES',
        '/^BSFT$/'      => 'BSFT',
        '/^BSFOR$/'     => 'BSFor',
        '/^BSABE$/'     => 'BSABE',
        '/^BAE$/'       => 'BAE',
        '/^BSLDM$/'     => 'BSLDM',
        '/^BSBIO$/'     => 'BSBio',
        '/^BSCHEM$/'    => 'BSChem',
        '/^BSMATH$/'    => 'BSMath',
        '/^BSPHYSICS$/' => 'BSPhysics',
        '/^BSPSYCH$/'   => 'BSPsych',
        '/^BAELS$/'     => 'BAELS',
        '/^BACOMM$/'    => 'BAComm',
        '/^BSSTAT$/'    => 'BSStat',
        '/^DVM$/'       => 'DVM',
        '/^BSPV$/'      => 'BSPV',
        '/^BEED$/'      => 'BEEd',
        '/^BSED$/'      => 'BSEd',
        '/^BPE$/'       => 'BPE',
        '/^BTLE$/'      => 'BTLE',
        '/^BSBA$/'      => 'BSBA',
        '/^BSACC$/'     => 'BSAcc',
        '/^BSECO$/'     => 'BSEco',
        '/^BSENT$/'     => 'BSEnt',
        '/^BSOA$/'      => 'BSOA',
        '/^BSESS$/'     => 'BSESS',
        '/^BSCRIM$/'    => 'BSCrim',
        '/^BSN$/'       => 'BSN',
        '/^BSHM$/'      => 'BSHM',
        '/^BSTM$/'      => 'BSTM',
        '/^BLIS$/'      => 'BLIS',
        '/^PHD$/'       => 'PhD',
        '/^MS$/'        => 'MS',
        '/^MA$/'        => 'MA',
    ];
    foreach ($patterns as $regex => $code) {
        if (preg_match($regex, $noSpace)) {
            return $code;
        }
    }

    return $noSpace !== '' ? $noSpace : 'UNSPECIFIED';
}

/**
 * Parse course scope string like:
 *  "BSIT,BSCS" or "Multiple: BSIT, BSCS"
 * into normalized codes array: ['BSIT','BSCS']
 */
function parse_normalized_course_scope(?string $scopeString): array {
    if ($scopeString === null) return [];

    $clean = preg_replace('/^(Courses?:\s*)?Multiple:\s*/i', '', $scopeString);
    $parts = array_filter(array_map('trim', explode(',', $clean)));
    $codes = [];
    foreach ($parts as $p) {
        if ($p === '' || strcasecmp($p, 'All') === 0) continue;
        $codes[] = strtoupper(normalize_course_code($p));
    }
    return array_unique($codes);
}

/**
 * Check if an election is in admin's college scope.
 */
function election_matches_college_scope(array $election, string $adminCollege): bool {
    $allowed = $election['allowed_colleges'] ?? 'All';
    $allowed = trim($allowed);

    if ($allowed === '' || strcasecmp($allowed, 'All') === 0) {
        return true; // open to all colleges
    }

    $list = array_filter(array_map('trim', explode(',', $allowed)));
    foreach ($list as $college) {
        if (strcasecmp($college, $adminCollege) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Check if election courses overlap admin courses.
 */
function election_matches_course_scope(array $election, array $adminCourseCodes): bool {
    $allowedCourses = $election['allowed_courses'] ?? '';
    $clean = trim($allowedCourses);

    if ($clean === '' || strcasecmp($clean, 'All') === 0) {
        return true;
    }

    $parts     = array_filter(array_map('trim', explode(',', $clean)));
    $elecCodes = [];
    foreach ($parts as $p) {
        $elecCodes[] = strtoupper(normalize_course_code($p));
    }
    $elecCodes = array_unique($elecCodes);

    if (empty($adminCourseCodes)) {
        return true;
    }

    return count(array_intersect($elecCodes, $adminCourseCodes)) > 0;
}

/**
 * Check if election departments overlap admin departments (for faculty scope).
 */
function election_matches_department_scope(array $election, array $adminDepartments): bool {
    $allowedDepts = $election['allowed_departments'] ?? '';
    $clean = trim($allowedDepts);

    if ($clean === '' || strcasecmp($clean, 'All') === 0) {
        return true;
    }

    $elecDepts = array_filter(array_map('trim', explode(',', $clean)));
    if (empty($adminDepartments)) {
        return true;
    }
    return count(array_intersect($elecDepts, $adminDepartments)) > 0;
}

/**
 * Master helper: fetch elections visible to this user
 * using new admin_scopes model + legacy fallback.
 */
function fetchScopedElections(PDO $pdo, int $userId): array {
    // Fetch user info (role + scope fields)
    $stmt = $pdo->prepare("
        SELECT role, assigned_scope, scope_category, assigned_scope_1
        FROM users
        WHERE user_id = :userId
    ");
    $stmt->execute([':userId' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return [];
    }

    $role          = $user['role'];
    $assignedScope = $user['assigned_scope'];    // e.g. CEIT
    $scopeCategory = $user['scope_category'];    // Academic-Student / Academic-Faculty / ...
    $userScope1    = $user['assigned_scope_1'];  // e.g. "Multiple: BSIT, BSCS"

    // Super admin or other roles -> see everything
    if ($role !== 'admin') {
        $stmt = $pdo->query("SELECT * FROM elections ORDER BY start_datetime DESC");
        return $stmt->fetchAll();
    }

    // Admin: try new scope model first
    $usingNewScope = false;
    $scopeId       = null;
    $scopeType     = null;

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
            $usingNewScope = true;
            $scopeId       = (int)$scopeRow['scope_id'];
            $scopeType     = $scopeRow['scope_type'];
        }
    }

    // NEW MODEL: use election_scope_type + owner_scope_id
    if ($usingNewScope && $scopeId !== null && $scopeType !== null) {
        $electionStmt = $pdo->prepare("
            SELECT *
            FROM elections
            WHERE election_scope_type = :scopeType
              AND owner_scope_id      = :scopeId
            ORDER BY start_datetime DESC
        ");
        $electionStmt->execute([
            ':scopeType' => $scopeType,
            ':scopeId'   => $scopeId,
        ]);
        return $electionStmt->fetchAll();
    }

    // LEGACY MODEL: fallbacks based on scope_category / assigned_scope / target_position

    // Academic - Student admin
    if ($scopeCategory === 'Academic-Student') {
        $adminCourseCodes = parse_normalized_course_scope($userScope1);

        $stmt = $pdo->prepare("
            SELECT *
            FROM elections
            WHERE LOWER(target_position) IN ('student', 'all')
            ORDER BY start_datetime DESC
        ");
        $stmt->execute();
        $allElections = $stmt->fetchAll();

        $res = [];
        foreach ($allElections as $e) {
            if (!election_matches_college_scope($e, $assignedScope)) {
                continue;
            }
            if (!election_matches_course_scope($e, $adminCourseCodes)) {
                continue;
            }
            $res[] = $e;
        }
        return $res;
    }

    // Academic - Faculty admin
    if ($scopeCategory === 'Academic-Faculty') {
        $adminDepartments = [];
        if (!empty($userScope1) && strcasecmp($userScope1, 'All') !== 0) {
            $adminDepartments = array_filter(array_map('trim', explode(',', $userScope1)));
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM elections
            WHERE LOWER(target_position) IN ('faculty', 'all')
            ORDER BY start_datetime DESC
        ");
        $stmt->execute();
        $allElections = $stmt->fetchAll();

        $res = [];
        foreach ($allElections as $e) {
            if (!election_matches_college_scope($e, $assignedScope)) {
                continue;
            }
            if (!election_matches_department_scope($e, $adminDepartments)) {
                continue;
            }
            $res[] = $e;
        }
        return $res;
    }

    // Everything else (old model) – elections tied directly to admin_id
    $electionStmt = $pdo->prepare("
        SELECT *
        FROM elections
        WHERE assigned_admin_id = :adminId
        ORDER BY start_datetime DESC
    ");
    $electionStmt->execute([':adminId' => $userId]);
    return $electionStmt->fetchAll();
}
