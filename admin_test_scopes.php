<?php
// admin_test_scopes.php
session_start();
date_default_timezone_set('Asia/Manila');

// ===== Auth: only admin (super admin / any admin for now) =====
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

// ===== DB Connection (same pattern as other files) =====
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

// ===== Include analytics helpers =====
require_once __DIR__ . '/includes/analytics_scopes.php';

// ===== 1. Fetch all scope seats =====
$scopeSeatsAll = getScopeSeats($pdo, null);

// Group by scope_type for readability
$scopeSeatsByType = [];
foreach ($scopeSeatsAll as $seat) {
    $scopeSeatsByType[$seat['scope_type']][] = $seat;
}

// ===== 2. Academic-Student scope seat (if any) + global =====
$sampleAcadSeat = null;
if (!empty($scopeSeatsByType[SCOPE_ACAD_STUDENT])) {
    $sampleAcadSeat = $scopeSeatsByType[SCOPE_ACAD_STUDENT][0];
}

$acadVoters = [];
if ($sampleAcadSeat !== null) {
    $acadVoters = getScopedVoters(
        $pdo,
        SCOPE_ACAD_STUDENT,
        $sampleAcadSeat['scope_id'],
        [
            'year_end'      => date('Y') . '-12-31 23:59:59',
            'include_flags' => true,
        ]
    );
}

$acadVotersGlobal = getScopedVoters(
    $pdo,
    SCOPE_ACAD_STUDENT,
    null,
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ===== 3. Global COOP voters =====
$coopVoters = getScopedVoters(
    $pdo,
    SCOPE_OTHERS_COOP,
    null,
    [
        'year_end'      => null,
        'include_flags' => true,
    ]
);

// ===== 4. Academic-Faculty scope + global =====
$sampleAcadFacSeat = null;
if (!empty($scopeSeatsByType[SCOPE_ACAD_FACULTY])) {
    $sampleAcadFacSeat = $scopeSeatsByType[SCOPE_ACAD_FACULTY][0];
}

$acadFacVoters = [];
if ($sampleAcadFacSeat !== null) {
    $acadFacVoters = getScopedVoters(
        $pdo,
        SCOPE_ACAD_FACULTY,
        $sampleAcadFacSeat['scope_id'],
        [
            'year_end'      => date('Y') . '-12-31 23:59:59',
            'include_flags' => true,
        ]
    );
}

$acadFacGlobal = getScopedVoters(
    $pdo,
    SCOPE_ACAD_FACULTY,
    null,
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ===== 5. Non-Academic-Employee scope + global =====
$sampleNonAcadEmpSeat = null;
if (!empty($scopeSeatsByType[SCOPE_NONACAD_EMPLOYEE])) {
    $sampleNonAcadEmpSeat = $scopeSeatsByType[SCOPE_NONACAD_EMPLOYEE][0];
}

$nonAcadEmpVoters = [];
if ($sampleNonAcadEmpSeat !== null) {
    $nonAcadEmpVoters = getScopedVoters(
        $pdo,
        SCOPE_NONACAD_EMPLOYEE,
        $sampleNonAcadEmpSeat['scope_id'],
        [
            'year_end'      => date('Y') . '-12-31 23:59:59',
            'include_flags' => true,
        ]
    );
}

$nonAcadEmpGlobal = getScopedVoters(
    $pdo,
    SCOPE_NONACAD_EMPLOYEE,
    null,
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ===== 6. Non-Academic-Student scope + global =====
$sampleNonAcadSeat = null;
if (!empty($scopeSeatsByType[SCOPE_NONACAD_STUDENT])) {
    $sampleNonAcadSeat = $scopeSeatsByType[SCOPE_NONACAD_STUDENT][0];
}

$nonAcadVoters = [];
if ($sampleNonAcadSeat !== null) {
    $nonAcadVoters = getScopedVoters(
        $pdo,
        SCOPE_NONACAD_STUDENT,
        $sampleNonAcadSeat['scope_id'],
        [
            'year_end'      => date('Y') . '-12-31 23:59:59',
            'include_flags' => true,
        ]
    );
}

$nonAcadVotersGlobal = getScopedVoters(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    null,
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ===== 7. Others-Default scope + global =====
$sampleOthersDefaultSeat = null;
if (!empty($scopeSeatsByType[SCOPE_OTHERS_DEFAULT])) {
    $sampleOthersDefaultSeat = $scopeSeatsByType[SCOPE_OTHERS_DEFAULT][0];
}

$othersDefaultVoters = [];
if ($sampleOthersDefaultSeat !== null) {
    $othersDefaultVoters = getScopedVoters(
        $pdo,
        SCOPE_OTHERS_DEFAULT,
        $sampleOthersDefaultSeat['scope_id'],
        [
            'year_end'      => date('Y') . '-12-31 23:59:59',
            'include_flags' => true,
        ]
    );
}

$othersDefaultGlobal = getScopedVoters(
    $pdo,
    SCOPE_OTHERS_DEFAULT,
    null,
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ===== 8. Special-Scope (CSG) – global student voters =====
$csgStudentsGlobal = getScopedVoters(
    $pdo,
    SCOPE_SPECIAL_CSG,
    null,
    [
        'year_end'      => null,
        'include_flags' => false,
    ]
);

// ===== 9. Elections per scope (diagnostics) =====

// Sample COOP (Others-COOP) scope seat
$sampleCoopSeat = null;
if (!empty($scopeSeatsByType[SCOPE_OTHERS_COOP])) {
    $sampleCoopSeat = $scopeSeatsByType[SCOPE_OTHERS_COOP][0];
}

// Elections for COOP scope seat (per-seat)
$coopElectionsSeat = [];
if ($sampleCoopSeat !== null) {
    $coopElectionsSeat = getScopedElections(
        $pdo,
        SCOPE_OTHERS_COOP,
        $sampleCoopSeat['scope_id'],
        [
            // Example: last 5 years
            'from_year' => date('Y') - 5,
            'to_year'   => date('Y'),
        ]
    );
}

// All COOP elections globally (all COOP scopes)
$coopElectionsGlobal = getScopedElections(
    $pdo,
    SCOPE_OTHERS_COOP,
    null,
    []
);

// Elections for Non-Academic-Student seat (per-seat)
$nonAcadElectionsSeat = [];
if ($sampleNonAcadSeat !== null) {
    $nonAcadElectionsSeat = getScopedElections(
        $pdo,
        SCOPE_NONACAD_STUDENT,
        $sampleNonAcadSeat['scope_id'],
        [
            'from_year' => date('Y') - 5,
            'to_year'   => date('Y'),
        ]
    );
}

// Global Non-Academic-Student elections
$nonAcadElectionsGlobal = getScopedElections(
    $pdo,
    SCOPE_NONACAD_STUDENT,
    null,
    []
);

// Elections for Others-Default seat (per-seat)
$othersDefaultElectionsSeat = [];
if ($sampleOthersDefaultSeat !== null) {
    $othersDefaultElectionsSeat = getScopedElections(
        $pdo,
        SCOPE_OTHERS_DEFAULT,
        $sampleOthersDefaultSeat['scope_id'],
        [
            'from_year' => date('Y') - 5,
            'to_year'   => date('Y'),
        ]
    );
}

// Global Others-Default elections
$othersDefaultElectionsGlobal = getScopedElections(
    $pdo,
    SCOPE_OTHERS_DEFAULT,
    null,
    []
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Scopes Test</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#f3f4f6; padding:20px; }
        h1, h2, h3 { margin-top: 1.5rem; }
        pre { background:#111827; color:#e5e7eb; padding:10px; border-radius:6px; max-height:400px; overflow:auto; font-size:13px; }
        .card { background:#fff; border-radius:8px; padding:16px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
        .tag { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; background:#e5e7eb; margin-right:4px; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background:#f9fafb; }
        .small { font-size: 12px; color:#6b7280; }
    </style>
</head>
<body>

<h1>Analytics Scopes – Test Page (Phase 1)</h1>
<p class="small">
    Logged in as: <?= htmlspecialchars($_SESSION['role'] ?? '') ?> (User ID: <?= (int)($_SESSION['user_id'] ?? 0) ?>)
</p>

<div class="card">
    <h2>1. Scope Seats (admin_scopes)</h2>
    <p>Total scope seats found: <strong><?= count($scopeSeatsAll) ?></strong></p>

    <?php foreach ($scopeSeatsByType as $type => $seats): ?>
        <h3><?= htmlspecialchars($type) ?> (<?= count($seats) ?>)</h3>
        <table>
            <thead>
            <tr>
                <th>Scope ID</th>
                <th>Admin</th>
                <th>Email</th>
                <th>Assigned Scope</th>
                <th>Label</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($seats as $s): ?>
                <tr>
                    <td><?= (int)$s['scope_id'] ?></td>
                    <td><?= htmlspecialchars($s['admin_full_name']) ?></td>
                    <td><?= htmlspecialchars($s['admin_email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['assigned_scope'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['label']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>2. Sample Academic-Student Scope Seat</h2>
    <?php if ($sampleAcadSeat === null): ?>
        <p>No <code>Academic-Student</code> scope seat found in <code>admin_scopes</code>.</p>
    <?php else: ?>
        <p>
            Using scope_id = <strong><?= (int)$sampleAcadSeat['scope_id'] ?></strong><br>
            Label: <strong><?= htmlspecialchars($sampleAcadSeat['label']) ?></strong>
        </p>
        <p>
            Voters in this scope (with year_end = <?= htmlspecialchars(date('Y') . '-12-31 23:59:59') ?>): 
            <strong><?= count($acadVoters) ?></strong>
        </p>

        <details>
            <summary>Show first 20 voters for this scope</summary>
            <pre><?php
                $slice = array_slice($acadVoters, 0, 20);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>All Academic-Student voters (global view)</h3>
    <p>Total global Academic-Student voters: <strong><?= count($acadVotersGlobal) ?></strong></p>
    <details>
        <summary>Show first 20 global Academic-Student voters</summary>
        <pre><?php
            $slice = array_slice($acadVotersGlobal, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>3. Global COOP Voters (Others-COOP)</h2>
    <p>Total COOP voters (is_coop_member=1 &amp; migs_status=1): <strong><?= count($coopVoters) ?></strong></p>
    <details>
        <summary>Show first 20 COOP voters</summary>
        <pre><?php
            $slice = array_slice($coopVoters, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>4. Sample Academic-Faculty Scope Seat</h2>
    <?php if ($sampleAcadFacSeat === null): ?>
        <p>No <code>Academic-Faculty</code> scope seat found in <code>admin_scopes</code>.</p>
    <?php else: ?>
        <p>
            Using scope_id = <strong><?= (int)$sampleAcadFacSeat['scope_id'] ?></strong><br>
            Label: <strong><?= htmlspecialchars($sampleAcadFacSeat['label']) ?></strong>
        </p>
        <p>
            Voters in this scope (Academic-Faculty, with year_end = <?= htmlspecialchars(date('Y') . '-12-31 23:59:59') ?>):
            <strong><?= count($acadFacVoters) ?></strong>
        </p>

        <details>
            <summary>Show first 20 Academic-Faculty voters in this scope</summary>
            <pre><?php
                $slice = array_slice($acadFacVoters, 0, 20);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>All Academic-Faculty voters (global view)</h3>
    <p>Total global Academic-Faculty voters: <strong><?= count($acadFacGlobal) ?></strong></p>
    <details>
        <summary>Show first 20 global Academic-Faculty voters</summary>
        <pre><?php
            $slice = array_slice($acadFacGlobal, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>5. Sample Non-Academic-Employee Scope Seat</h2>
    <?php if ($sampleNonAcadEmpSeat === null): ?>
        <p>No <code>Non-Academic-Employee</code> scope seat found in <code>admin_scopes</code>.</p>
    <?php else: ?>
        <p>
            Using scope_id = <strong><?= (int)$sampleNonAcadEmpSeat['scope_id'] ?></strong><br>
            Label: <strong><?= htmlspecialchars($sampleNonAcadEmpSeat['label']) ?></strong>
        </p>
        <p>
            Voters in this scope (Non-Academic-Employee, with year_end = <?= htmlspecialchars(date('Y') . '-12-31 23:59:59') ?>):
            <strong><?= count($nonAcadEmpVoters) ?></strong>
        </p>

        <details>
            <summary>Show first 20 Non-Academic-Employee voters in this scope</summary>
            <pre><?php
                $slice = array_slice($nonAcadEmpVoters, 0, 20);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>All Non-Academic-Employee voters (global view)</h3>
    <p>Total global Non-Academic-Employee voters: <strong><?= count($nonAcadEmpGlobal) ?></strong></p>
    <details>
        <summary>Show first 20 global Non-Academic-Employee voters</summary>
        <pre><?php
            $slice = array_slice($nonAcadEmpGlobal, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>6. Sample Non-Academic-Student Scope Seat</h2>
    <?php if ($sampleNonAcadSeat === null): ?>
        <p>No <code>Non-Academic-Student</code> scope seat found in <code>admin_scopes</code>.</p>
    <?php else: ?>
        <p>
            Using scope_id = <strong><?= (int)$sampleNonAcadSeat['scope_id'] ?></strong><br>
            Label: <strong><?= htmlspecialchars($sampleNonAcadSeat['label']) ?></strong>
        </p>
        <p>
            Voters in this scope (Non-Academic-Student, with year_end = <?= htmlspecialchars(date('Y') . '-12-31 23:59:59') ?>):
            <strong><?= count($nonAcadVoters) ?></strong>
        </p>

        <details>
            <summary>Show first 20 Non-Academic-Student voters in this scope</summary>
            <pre><?php
                $slice = array_slice($nonAcadVoters, 0, 20);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>All Non-Academic-Student voters (global view)</h3>
    <p>Total global Non-Academic-Student voters: <strong><?= count($nonAcadVotersGlobal) ?></strong></p>
    <details>
        <summary>Show first 20 global Non-Academic-Student voters</summary>
        <pre><?php
            $slice = array_slice($nonAcadVotersGlobal, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>7. Sample Others-Default Scope Seat</h2>
    <?php if ($sampleOthersDefaultSeat === null): ?>
        <p>No <code>Others-Default</code> scope seat found in <code>admin_scopes</code>.</p>
    <?php else: ?>
        <p>
            Using scope_id = <strong><?= (int)$sampleOthersDefaultSeat['scope_id'] ?></strong><br>
            Label: <strong><?= htmlspecialchars($sampleOthersDefaultSeat['label']) ?></strong>
        </p>
        <p>
            Voters in this scope (Others-Default, with year_end = <?= htmlspecialchars(date('Y') . '-12-31 23:59:59') ?>):
            <strong><?= count($othersDefaultVoters) ?></strong>
        </p>

        <details>
            <summary>Show first 20 Others-Default voters in this scope</summary>
            <pre><?php
                $slice = array_slice($othersDefaultVoters, 0, 20);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>All Others-Default voters (global view)</h3>
    <p>Total global Others-Default voters: <strong><?= count($othersDefaultGlobal) ?></strong></p>
    <details>
        <summary>Show first 20 global Others-Default voters</summary>
        <pre><?php
            $slice = array_slice($othersDefaultGlobal, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>8. Special-Scope (CSG) – Global Student Voters</h2>
    <p>Total CSG-eligible student voters (global, role=voter &amp; position=student): 
        <strong><?= count($csgStudentsGlobal) ?></strong>
    </p>
    <details>
        <summary>Show first 20 CSG global student voters</summary>
        <pre><?php
            $slice = array_slice($csgStudentsGlobal, 0, 20);
            print_r($slice);
        ?></pre>
    </details>
</div>

<div class="card">
    <h2>9. Elections per Scope (Diagnostics)</h2>

    <h3>COOP (Others-COOP)</h3>
    <?php if ($sampleCoopSeat === null): ?>
        <p>No <code>Others-COOP</code> scope seat found.</p>
    <?php else: ?>
        <p>
            Scope: <strong><?= htmlspecialchars($sampleCoopSeat['label']) ?></strong><br>
            Elections in this COOP scope (last 5 years): <strong><?= count($coopElectionsSeat) ?></strong><br>
            All COOP elections (all scopes): <strong><?= count($coopElectionsGlobal) ?></strong>
        </p>
        <details>
            <summary>Show first 10 COOP elections in this scope</summary>
            <pre><?php
                $slice = array_slice($coopElectionsSeat, 0, 10);
                print_r($slice);
            ?></pre>
        </details>
        <details>
            <summary>Show first 10 COOP elections (global)</summary>
            <pre><?php
                $slice = array_slice($coopElectionsGlobal, 0, 10);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>Non-Academic-Student</h3>
    <?php if ($sampleNonAcadSeat === null): ?>
        <p>No <code>Non-Academic-Student</code> scope seat found.</p>
    <?php else: ?>
        <p>
            Scope: <strong><?= htmlspecialchars($sampleNonAcadSeat['label']) ?></strong><br>
            Elections in this Non-Academic-Student scope (last 5 years): <strong><?= count($nonAcadElectionsSeat) ?></strong><br>
            All Non-Academic-Student elections (all scopes): <strong><?= count($nonAcadElectionsGlobal) ?></strong>
        </p>
        <details>
            <summary>Show first 10 Non-Academic-Student elections in this scope</summary>
            <pre><?php
                $slice = array_slice($nonAcadElectionsSeat, 0, 10);
                print_r($slice);
            ?></pre>
        </details>
        <details>
            <summary>Show first 10 Non-Academic-Student elections (global)</summary>
            <pre><?php
                $slice = array_slice($nonAcadElectionsGlobal, 0, 10);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

    <hr>

    <h3>Others-Default</h3>
    <?php if ($sampleOthersDefaultSeat === null): ?>
        <p>No <code>Others-Default</code> scope seat found.</p>
    <?php else: ?>
        <p>
            Scope: <strong><?= htmlspecialchars($sampleOthersDefaultSeat['label']) ?></strong><br>
            Elections in this Others-Default scope (last 5 years): <strong><?= count($othersDefaultElectionsSeat) ?></strong><br>
            All Others-Default elections (all scopes): <strong><?= count($othersDefaultElectionsGlobal) ?></strong>
        </p>
        <details>
            <summary>Show first 10 Others-Default elections in this scope</summary>
            <pre><?php
                $slice = array_slice($othersDefaultElectionsSeat, 0, 10);
                print_r($slice);
            ?></pre>
        </details>
        <details>
            <summary>Show first 10 Others-Default elections (global)</summary>
            <pre><?php
                $slice = array_slice($othersDefaultElectionsGlobal, 0, 10);
                print_r($slice);
            ?></pre>
        </details>
    <?php endif; ?>

</div>

</body>
</html>
