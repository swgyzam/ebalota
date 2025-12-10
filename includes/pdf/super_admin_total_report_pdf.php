<?php
/**
 * super_admin_total_report_pdf.php
 *
 * TOTAL system summary report for Super Admin:
 * - Total voters, elections, admins
 * - Total turnout (current year)
 * - Elections per year (within selected range)
 * - Turnout per year (within selected range)
 */

if (!function_exists('generateSuperAdminTotalReportPDF')) {
    function generateSuperAdminTotalReportPDF(PDO $pdo, int $superAdminId, int $fromYear, int $toYear): void
    {
        // TCPDF
        require_once __DIR__ . '/../../tcpdf/tcpdf.php';
        require_once __DIR__ . '/../analytics_scopes.php';

        // ==========================
        // BASIC TOTAL STATS
        // ==========================
        // Total voters
        $stmt = $pdo->query("SELECT COUNT(*) AS total_voters FROM users WHERE role = 'voter'");
        $totalVoters = (int)($stmt->fetch()['total_voters'] ?? 0);

        // Total elections
        $stmt = $pdo->query("SELECT COUNT(*) AS total_elections FROM elections");
        $totalElections = (int)($stmt->fetch()['total_elections'] ?? 0);

        // Active admins
        $stmt = $pdo->query("
            SELECT COUNT(*) AS active_admins
            FROM users
            WHERE role = 'admin'
              AND admin_status = 'active'
        ");
        $activeAdmins = (int)($stmt->fetch()['active_admins'] ?? 0);

        // Turnout by year (all years first)
        $globalTurnoutByYear = getGlobalTurnoutByYear($pdo, null);
        $currentYear         = (int)date('Y');
        $currentYearTurnout  = $globalTurnoutByYear[$currentYear]['turnout_rate'] ?? 0.0;

        // Build filtered [fromYear..toYear] turnout
        $filteredTurnoutByYear = [];
        for ($y = $fromYear; $y <= $toYear; $y++) {
            if (isset($globalTurnoutByYear[$y])) {
                $filteredTurnoutByYear[$y] = array_merge([
                    'year'           => $y,
                    'total_voted'    => 0,
                    'total_eligible' => 0,
                    'turnout_rate'   => 0.0,
                    'election_count' => 0,
                    'growth_rate'    => 0.0,
                ], $globalTurnoutByYear[$y]);
            } else {
                $filteredTurnoutByYear[$y] = [
                    'year'           => $y,
                    'total_voted'    => 0,
                    'total_eligible' => 0,
                    'turnout_rate'   => 0.0,
                    'election_count' => 0,
                    'growth_rate'    => 0.0,
                ];
            }
        }

        // Elections per year for same range
        $stmt = $pdo->query("
            SELECT YEAR(start_datetime) AS year, COUNT(*) AS count
            FROM elections
            GROUP BY YEAR(start_datetime)
            ORDER BY YEAR(start_datetime)
        ");
        $electionsPerYear = $stmt->fetchAll();
        $electionsPerYearMap = [];
        foreach ($electionsPerYear as $row) {
            $y = (int)$row['year'];
            $electionsPerYearMap[$y] = (int)$row['count'];
        }

        $yearsForChart  = [];
        $countsForChart = [];
        for ($y = $fromYear; $y <= $toYear; $y++) {
            $yearsForChart[]  = $y;
            $countsForChart[] = $electionsPerYearMap[$y] ?? 0;
        }

        // Optional: get super admin name (for PDF Author / footer)
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$superAdminId]);
        $adminRow   = $stmt->fetch() ?: ['first_name' => '', 'last_name' => '', 'email' => ''];
        $adminName  = trim(($adminRow['first_name'] ?? '') . ' ' . ($adminRow['last_name'] ?? ''));
        if ($adminName === '') {
            $adminName = $adminRow['email'] ?? ('Super Admin #' . $superAdminId);
        }

        // ==========================
        // TCPDF CLASS WITH FOOTER
        // ==========================
        class TotalPDF extends TCPDF {
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', '', 9);
                $pageText = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();
                $this->Cell(0, 10, $pageText, 0, 0, 'L');
            }
        }

        // ==========================
        // INIT TCPDF
        // ==========================
        $pdf = new TotalPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('eBalota');
        $pdf->SetAuthor($adminName);
        $pdf->SetTitle('Total System Report (' . $fromYear . '–' . $toYear . ')');
        $pdf->SetSubject('Total Elections & Turnout Summary');

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Logo (optional)
        $logoPath = __DIR__ . '/../../assets/img/ebalota_logo.jpg';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 8, 30);
        }

        $pdf->SetY(18);

        // Header bar (TOTAL, no "Global")
        $headerHtml = '
        <table cellpadding="4" cellspacing="0" border="0" width="100%">
          <tr>
            <td width="60%" style="background-color:#154734;color:#ffffff;font-size:13pt;font-weight:bold;">
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Total System Report
            </td>
            <td width="40%" align="right" style="background-color:#154734;color:#FFD166;font-size:9pt;">
              eBalota &nbsp;&bull;&nbsp; Cavite State University
            </td>
          </tr>
          <tr>
            <td colspan="2" align="center" style="background-color:#1E6F46;color:#ffffff;font-size:11pt;font-weight:bold;">
              Total Elections &amp; Turnout (' . $fromYear . '–' . $toYear . ')
            </td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($headerHtml, true, false, true, false, '');
        $pdf->Ln(4);

        // ==========================
        // SECTION 1: TOTAL OVERVIEW
        // ==========================
        $overviewHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold; color:#154734;">Total Overview</h4>
        <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
          <tr style="background-color:#154734;color:#ffffff;">
            <th width="25%"><b>Total Voters</b></th>
            <th width="25%"><b>Total Elections</b></th>
            <th width="25%"><b>Active Admins</b></th>
            <th width="25%"><b>Turnout (' . $currentYear . ')</b></th>
          </tr>
          <tr>
            <td align="right">' . number_format($totalVoters) . '</td>
            <td align="right">' . number_format($totalElections) . '</td>
            <td align="right">' . number_format($activeAdmins) . '</td>
            <td align="right">' . number_format($currentYearTurnout, 1) . '%</td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($overviewHtml, true, false, true, false, '');
        $pdf->Ln(3);

        // ==========================
        // SECTION 2: ELECTIONS PER YEAR (TOTAL)
        // ==========================
        $pdf->SetFont('helvetica', '', 10);
        $electionsHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold; color:#154734;">Total Elections per Year</h4>
        <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
          <tr style="background-color:#154734;color:#ffffff;">
            <th width="40%"><b>Year</b></th>
            <th width="60%"><b>Number of Elections</b></th>
          </tr>
        ';

        foreach ($yearsForChart as $idx => $year) {
            $cnt = (int)$countsForChart[$idx];
            $electionsHtml .= '
              <tr>
                <td>' . (int)$year . '</td>
                <td align="right">' . number_format($cnt) . '</td>
              </tr>
            ';
        }
        $electionsHtml .= '</table>';

        $pdf->writeHTML($electionsHtml, true, false, true, false, '');
        $pdf->Ln(4);

        // ==========================
        // SECTION 3: TOTAL TURNOUT BY YEAR
        // ==========================
        $turnoutHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold; color:#154734;">Total Turnout by Year</h4>
        <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
          <tr style="background-color:#154734;color:#ffffff;">
            <th width="14%"><b>Year</b></th>
            <th width="18%"><b>Elections</b></th>
            <th width="22%"><b>Eligible Voters</b></th>
            <th width="22%"><b>Voters Participated</b></th>
            <th width="12%"><b>Turnout %</b></th>
            <th width="12%"><b>Growth %</b></th>
          </tr>
        ';

        foreach ($filteredTurnoutByYear as $year => $data) {
            $electionCount = (int)($data['election_count'] ?? 0);
            $eligible      = (int)($data['total_eligible'] ?? 0);
            $voted         = (int)($data['total_voted'] ?? 0);
            $rate          = (float)($data['turnout_rate'] ?? 0.0);
            $growth        = (float)($data['growth_rate'] ?? 0.0);

            $growthStr = ($growth > 0 ? '+' : '') . number_format($growth, 1) . '%';

            $turnoutHtml .= '
              <tr>
                <td>' . (int)$year . '</td>
                <td align="right">' . number_format($electionCount) . '</td>
                <td align="right">' . number_format($eligible) . '</td>
                <td align="right">' . number_format($voted) . '</td>
                <td align="right">' . number_format($rate, 1) . '%</td>
                <td align="right">' . $growthStr . '</td>
              </tr>
            ';
        }

        $turnoutHtml .= '</table>';
        $pdf->writeHTML($turnoutHtml, true, false, true, false, '');

        $pdf->Ln(4);

        // ==========================
        // SECTION 4: SHORT NOTE
        // ==========================
        // simple computed highlights
        $nonZeroRates = array_filter($filteredTurnoutByYear, function($row) {
            return ($row['turnout_rate'] ?? 0) > 0;
        });
        $noteText = '';

        if (!empty($nonZeroRates)) {
            // find max and min
            $maxRow = null;
            $minRow = null;
            foreach ($nonZeroRates as $y => $row) {
                if ($maxRow === null || $row['turnout_rate'] > $maxRow['turnout_rate']) {
                    $maxRow = ['year' => $y, 'rate' => $row['turnout_rate']];
                }
                if ($minRow === null || $row['turnout_rate'] < $minRow['turnout_rate']) {
                    $minRow = ['year' => $y, 'rate' => $row['turnout_rate']];
                }
            }

            $noteText = 'Highest total turnout in this range was '
                . number_format($maxRow['rate'], 1) . '% (Year ' . $maxRow['year'] . '). '
                . 'Lowest total turnout was '
                . number_format($minRow['rate'], 1) . '% (Year ' . $minRow['year'] . ').';
        } else {
            $noteText = 'No turnout data is available in the selected year range.';
        }

        $noteHtml = '
        <p style="font-size:9.5pt; color:#374151; margin-top:6px;">
            <i>' . htmlspecialchars($noteText) . '</i>
        </p>
        ';
        $pdf->writeHTML($noteHtml, true, false, true, false, '');

        // ==========================
        // SIGNATORY / FOOTNOTE
        // ==========================
        $pdf->Ln(6);
        $generatedAt = date('F d, Y h:i A');

        $signHtml = '
        <table width="100%" cellpadding="4" cellspacing="0" border="0" style="font-size:10pt;">
          <tr>
            <td width="60%" align="left">
              <b>Generated for:</b><br><br>
              ________________________________<br>
              ' . htmlspecialchars($adminName) . '<br>
              Super Admin
            </td>
            <td width="40%" align="right">
              <b>Date Generated:</b><br>
              ' . htmlspecialchars($generatedAt) . '
            </td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($signHtml, true, false, true, false, '');

        // ==========================
        // OUTPUT
        // ==========================
        $filename = 'Total_System_Report_' . $fromYear . '-' . $toYear . '.pdf';
        $pdf->Output($filename, 'I');
        exit;
    }
}
