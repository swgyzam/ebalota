<?php

if (!function_exists('generateSuperAdminScopeDetailPDF')) {

function generateSuperAdminScopeDetailPDF(
    PDO $pdo,
    int $superAdminId,
    array $selectedSeat,
    array $selectedScopeVoters,
    array $selectedScopeElections,
    array $selectedScopeTurnout,
    array $selectedScopeBreakdown,
    int $fromYear,
    int $toYear
) {
    require_once __DIR__ . '/../../tcpdf/tcpdf.php';

    // Fetch super admin info
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
    $stmt->execute([$superAdminId]);
    $admin = $stmt->fetch() ?: ['first_name'=>'','last_name'=>'','email'=>''];

    $adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
    if ($adminName === '') $adminName = $admin['email'] ?? 'Super Admin';

    // Custom footer
    class ScopePDF extends TCPDF {
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', '', 9);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'L');
        }
    }

    $pdf = new ScopePDF('P','mm','A4',true,'UTF-8',false);
    $pdf->SetCreator('eBalota');
    $pdf->SetAuthor($adminName);
    $pdf->SetTitle("Scope Detail Report - {$selectedSeat['label']}");
    $pdf->SetMargins(15,15,15);
    $pdf->SetAutoPageBreak(true,20);
    $pdf->AddPage();

    // HEADER =======================================================
    $label = htmlspecialchars($selectedSeat['label']);
    $type  = htmlspecialchars($selectedSeat['scope_type']);

    $headerHtml = "
    <table width='100%' cellpadding='4'>
        <tr>
            <td style='background-color:#154734;color:white;font-size:13pt;font-weight:bold;'>
                &nbsp;&nbsp;Scope Detail Report
            </td>
            <td align='right' style='background-color:#154734;color:#FFD166;font-size:9pt;'>
                eBalota • Cavite State University
            </td>
        </tr>
        <tr>
            <td colspan='2' align='center' style='background-color:#1E6F46;color:white;font-size:12pt;font-weight:bold;'>
                {$label} ({$type})
            </td>
        </tr>
    </table>
    ";
    $pdf->writeHTML($headerHtml,true,false,true,false,'');

    $pdf->Ln(3);

    // SECTION 1: BASIC INFO ========================================
    $adminFull = htmlspecialchars($selectedSeat['admin_full_name'] ?? 'N/A');
    $adminMail = htmlspecialchars($selectedSeat['admin_email'] ?? 'N/A');

    $voterCount    = number_format(count($selectedScopeVoters));
    $electCount    = number_format(count($selectedScopeElections));

    $latestTurnout = '0%';
    if (!empty($selectedScopeTurnout)) {
        $years = array_keys($selectedScopeTurnout);
        sort($years);
        $last = end($years);
        $latestTurnout = number_format($selectedScopeTurnout[$last]['turnout_rate'] ?? 0,1) . '%';
    }

    $infoHtml = "
    <h4 style='color:#154734;font-size:11pt;font-weight:bold;'>Scope Summary</h4>
    <table width='100%' cellpadding='4' border='1' style='font-size:9.5pt;border-color:#d1d5db;'>
        <tr style='background-color:#154734;color:white;'>
            <th>Scope Label</th>
            <th>Admin</th>
            <th>Total Voters</th>
            <th>Total Elections</th>
            <th>Latest Turnout</th>
        </tr>
        <tr>
            <td>{$label}</td>
            <td>{$adminFull}<br><span style='font-size:8pt;color:#666;'>{$adminMail}</span></td>
            <td align='right'>{$voterCount}</td>
            <td align='right'>{$electCount}</td>
            <td align='right'>{$latestTurnout}</td>
        </tr>
    </table>
    ";
    $pdf->writeHTML($infoHtml,true,false,true,false,'');

    $pdf->Ln(5);

    // SECTION 2: TURNOUT BY YEAR ===================================
    $pdf->writeHTML("<h4 style='color:#154734;font-size:11pt;font-weight:bold;'>Turnout by Year ({$fromYear}–{$toYear})</h4>",true,false,true,false,'');

    $turnHtml = "
    <table width='100%' cellpadding='4' border='1' style='font-size:9pt;border-color:#d1d5db;'>
        <tr style='background-color:#154734;color:white;'>
            <th>Year</th>
            <th>Elections</th>
            <th>Eligible</th>
            <th>Voted</th>
            <th>Turnout %</th>
        </tr>
    ";

    foreach ($selectedScopeTurnout as $yr => $row) {
        $turnHtml .= "
            <tr>
                <td>{$yr}</td>
                <td align='right'>".number_format($row['election_count'])."</td>
                <td align='right'>".number_format($row['total_eligible'])."</td>
                <td align='right'>".number_format($row['total_voted'])."</td>
                <td align='right'>".number_format($row['turnout_rate'],1)."%</td>
            </tr>
        ";
    }

    $turnHtml .= "</table>";

    $pdf->writeHTML($turnHtml,true,false,true,false,'');

    $pdf->Ln(4);

    // SECTION 3: BREAKDOWN ==========================================
    $pdf->writeHTML("<h4 style='color:#154734;font-size:11pt;font-weight:bold;'>Breakdown</h4>",true,false,true,false,'');

    $breakHtml = "
    <table width='100%' cellpadding='4' border='1' style='font-size:9pt;border-color:#d1d5db;'>
        <tr style='background-color:#154734;color:white;'>
            <th>Label</th>
            <th>Voters</th>
            <th>Share</th>
        </tr>
    ";

    $total = max(1,count($selectedScopeVoters));

    foreach ($selectedScopeBreakdown as $label => $cnt) {
        $pct = round(($cnt / $total) * 100, 1);
        $labelTxt = htmlspecialchars($label ?: 'Unspecified');

        $breakHtml .= "
        <tr>
            <td>{$labelTxt}</td>
            <td align='right'>".number_format($cnt)."</td>
            <td align='right'>{$pct}%</td>
        </tr>
        ";
    }

    $breakHtml .= "</table>";

    $pdf->writeHTML($breakHtml,true,false,true,false,'');

    // SIGNATORY ====================================================
    $pdf->Ln(10);

    $gen = date('F d, Y h:i A');

    $sig = "
    <table width='100%' cellpadding='4'>
        <tr>
            <td width='60%'>
                <b>Generated by:</b><br><br>
                ________________________________<br>
                {$adminName}<br>
                Super Admin
            </td>
            <td width='40%' align='right'>
                <b>Date Generated:</b><br>
                {$gen}
            </td>
        </tr>
    </table>
    ";

    $pdf->writeHTML($sig,true,false,true,false,'');

    // OUTPUT ========================================================
    $filename = "Scope_Report_{$selectedSeat['scope_id']}.pdf";
    $pdf->Output($filename,'I');
    exit;
}

}
