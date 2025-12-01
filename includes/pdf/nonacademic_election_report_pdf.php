<?php
/**
 * nonacademic_election_report_pdf.php
 *
 * Non-Academic-Employee election report generator.
 * Mirrors the faculty report style but scoped to:
 *   - Non-Academic-Employee elections
 *   - Non-academic employee voters (position='non-academic')
 *   - department & employment status breakdown
 */

if (!function_exists('generateNonAcademicElectionReportPDF')) {
    function generateNonAcademicElectionReportPDF(PDO $pdo, int $electionId, int $adminUserId, int $scopeId): void
    {
        /* ======================================================
           1. LOAD DEPENDENCIES
           ====================================================== */
        require_once __DIR__ . '/../../tcpdf/tcpdf.php';

        if (!defined('SCOPE_NONACAD_EMPLOYEE')) {
            require_once __DIR__ . '/../analytics_scopes.php';
        }
        require_once __DIR__ . '/../../admin_functions.php';

        /* ======================================================
           2. FETCH ELECTION + GUARDS
           ====================================================== */
        $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();

        if (!$election) {
            die('Election not found.');
        }

        if (($election['election_scope_type'] ?? '') !== 'Non-Academic-Employee') {
            die('This election is not a Non-Academic-Employee election.');
        }

        if ((int) ($election['owner_scope_id'] ?? 0) !== $scopeId) {
            die('You do not have permission to generate this non-academic report.');
        }

        /* ======================================================
           3. FETCH ADMIN + SCOPE SEAT (FOR SIGNATORY)
           ====================================================== */
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$adminUserId]);
        $admin = $stmt->fetch() ?: ['first_name' => '', 'last_name' => '', 'email' => ''];

        $adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
        if ($adminName === '') {
            $adminName = $admin['email'] ?? ('Admin #' . $adminUserId);
        }

        $mySeat   = null;
        $nonacSeats = getScopeSeats($pdo, SCOPE_NONACAD_EMPLOYEE);
        foreach ($nonacSeats as $seat) {
            if ((int)$seat['scope_id'] === $scopeId) {
                $mySeat = $seat;
                break;
            }
        }

        if (!$mySeat) {
            die('Non-academic scope seat not found.');
        }

        $scopeDetails     = $mySeat['scope_details'] ?? [];
        $seatDeptCodes    = $scopeDetails['departments'] ?? []; // e.g. ['ADMIN','LIBRARY',...]
        $scopeDescription = formatScopeDetails(
            $mySeat['scope_type'],
            json_encode($scopeDetails)
        );

        /* ======================================================
           4. ELIGIBLE NON-ACADEMIC EMPLOYEES + TURNOUT
           ====================================================== */
        $yearEnd = $election['end_datetime'] ?? null;

        // All non-academic employees for this seat as of election end
        $seatEmployeesAtEnd = getScopedVoters(
            $pdo,
            SCOPE_NONACAD_EMPLOYEE,
            $scopeId,
            [
                'year_end'      => $yearEnd,
                'include_flags' => true,
            ]
        );

        // Election allowed_departments filter
        $allowedDeptCodes = array_filter(
            array_map('strtoupper', array_map('trim', explode(',', $election['allowed_departments'] ?? '')))
        );
        $restrictByDept = !empty($allowedDeptCodes) && !in_array('ALL', $allowedDeptCodes, true);

        // Election allowed_status filter
        $allowedStatusCodes = array_filter(
            array_map('strtoupper', array_map('trim', explode(',', $election['allowed_status'] ?? '')))
        );
        $restrictByStatus = !empty($allowedStatusCodes) && !in_array('ALL', $allowedStatusCodes, true);

        $eligibleEmployeesForElection = [];

        foreach ($seatEmployeesAtEnd as $emp) {
            // Department guard (seat already restricts departments, but we still apply election-level filter)
            if ($restrictByDept) {
                $deptCode = strtoupper(trim($emp['department'] ?? ''));
                if (!in_array($deptCode, $allowedDeptCodes, true)) {
                    continue;
                }
            }

            // Status guard
            if ($restrictByStatus) {
                $empStatus = strtoupper(trim($emp['status'] ?? ''));
                if (!in_array($empStatus, $allowedStatusCodes, true)) {
                    continue;
                }
            }

            $eligibleEmployeesForElection[(int)$emp['user_id']] = $emp;
        }

        $totalEligibleVoters = count($eligibleEmployeesForElection);

        // Distinct voters who actually voted in this election (we’ll intersect with eligible set)
        $stmt = $pdo->prepare("SELECT DISTINCT voter_id FROM votes WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $votedSet = array_flip($votedIds);

        $totalVotesCast = 0;
        foreach ($eligibleEmployeesForElection as $uid => $_) {
            if (isset($votedSet[$uid])) {
                $totalVotesCast++;
            }
        }

        $totalDidNotVote = max(0, $totalEligibleVoters - $totalVotesCast);

        // For abstain stats: use all seat employees (no year_end) so computePerElectionStatsWithAbstain
        // can handle created_at cutoffs + allowed_depts/status
        $seatEmployeesAll = getScopedVoters(
            $pdo,
            SCOPE_NONACAD_EMPLOYEE,
            $scopeId,
            [
                'year_end'      => null,
                'include_flags' => true,
            ]
        );

        // Year of this election
        $electionYear = (int) date('Y', strtotime($election['start_datetime']));

        $perElectionStats = computePerElectionStatsWithAbstain(
            $pdo,
            SCOPE_NONACAD_EMPLOYEE,
            $scopeId,
            $seatEmployeesAll,
            $electionYear
        );

        $totalAbstained = 0;
        foreach ($perElectionStats as $row) {
            if ((int)$row['election_id'] === $electionId) {
                $totalAbstained = (int)($row['abstain_count'] ?? 0);
                break;
            }
        }

        $turnoutPercentage = $totalEligibleVoters > 0
            ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1)
            : 0.0;

        /* ======================================================
           5. DEPARTMENT & STATUS BREAKDOWN
           ====================================================== */

        // Department label now uses full department name if available
        $deptBuckets   = []; // department label => [eligible, voted]
        $statusBuckets = []; // status          => [eligible, voted]

        foreach ($eligibleEmployeesForElection as $uid => $emp) {
            // Prefer full department name (department1); fall back to department code or 'Unspecified'
            $deptLabel = $emp['department1'] ?: ($emp['department'] ?: 'Unspecified');
            $status    = $emp['status']      ?: 'Unspecified';

            if (!isset($deptBuckets[$deptLabel])) {
                $deptBuckets[$deptLabel] = ['eligible' => 0, 'voted' => 0];
            }
            $deptBuckets[$deptLabel]['eligible']++;

            if (isset($votedSet[$uid])) {
                $deptBuckets[$deptLabel]['voted']++;
            }

            if (!isset($statusBuckets[$status])) {
                $statusBuckets[$status] = ['eligible' => 0, 'voted' => 0];
            }
            $statusBuckets[$status]['eligible']++;

            if (isset($votedSet[$uid])) {
                $statusBuckets[$status]['voted']++;
            }
        }

        $deptSummary = [];
        foreach ($deptBuckets as $deptLabel => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $deptSummary[] = [
                'department' => $deptLabel,
                'eligible'   => $eligible,
                'voted'      => $voted,
                'rate'       => $rate,
            ];
        }
        usort($deptSummary, fn($a, $b) => strcmp($a['department'], $b['department']));

        $statusSummary = [];
        foreach ($statusBuckets as $status => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $statusSummary[] = [
                'status'   => $status,
                'eligible' => $eligible,
                'voted'    => $voted,
                'rate'     => $rate,
            ];
        }
        usort($statusSummary, fn($a, $b) => strcmp($a['status'], $b['status']));

        /* ======================================================
           6. CANDIDATES GROUPED BY POSITION
           ====================================================== */
        $sql = "
           SELECT 
               ec.position,
               c.id AS candidate_id,
               CONCAT(c.first_name, ' ', c.last_name) AS candidate_name,
               COUNT(v.vote_id) AS vote_count
           FROM election_candidates ec
           JOIN candidates c ON ec.candidate_id = c.id
           LEFT JOIN votes v 
                  ON ec.election_id = v.election_id 
                 AND ec.candidate_id = v.candidate_id
           WHERE ec.election_id = ?
           GROUP BY ec.position, c.id, c.first_name, c.last_name
           ORDER BY ec.position, vote_count DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$electionId]);
        $allCandidates = $stmt->fetchAll();

        $positions = [];
        foreach ($allCandidates as $row) {
            $pos = $row['position'];
            if (!isset($positions[$pos])) {
                $positions[$pos] = [];
            }
            $positions[$pos][] = $row;
        }

        // NOTE: ranking with ties will now be handled in section 14 (no simple 1..N here)

        /* ======================================================
           7. CUSTOM TCPDF CLASS (FOOTER)
           ====================================================== */
        class NonAcadPDF extends TCPDF {
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', '', 9);
                $pageText = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();
                $this->Cell(0, 10, $pageText, 0, 0, 'L');
            }
        }

        /* ======================================================
           8. INIT PDF
           ====================================================== */
        $pdf = new NonAcadPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('eBalota');
        $pdf->SetAuthor($adminName);
        $pdf->SetTitle('Non-Academic Election Report - ' . ($election['title'] ?? ''));
        $pdf->SetSubject('Non-Academic-Employee Election Report');

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Logo
        $logoPath = __DIR__ . '/../../assets/img/ebalota_logo.jpg';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 8, 30);
        }

        $pdf->SetY(20);

        /* ======================================================
           9. HEADER BAR
           ====================================================== */
        $headerHtml = '
        <table cellpadding="4" cellspacing="0" border="0" width="100%">
          <tr>
            <td width="60%" style="background-color:#154734;color:#ffffff;font-size:13pt;font-weight:bold;">
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Non-Academic Election Report
            </td>
            <td width="40%" align="right" style="background-color:#154734;color:#FFD166;font-size:9pt;">
              eBalota &nbsp;&bull;&nbsp; Cavite State University
            </td>
          </tr>
          <tr>
            <td colspan="2" align="center" style="background-color:#1E6F46;color:#ffffff;font-size:12pt;font-weight:bold;">
              ' . htmlspecialchars($election['title'] ?? 'Election') . '
            </td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($headerHtml, true, false, true, false, '');
        $pdf->Ln(4);

        /* ======================================================
           10. ELECTION DETAILS
           ====================================================== */
        $start  = $election['start_datetime'] ?? '';
        $end    = $election['end_datetime']   ?? '';
        $status = ucfirst($election['status'] ?? '');

        $startFormatted = $start ? date('Y-m-d h:i A', strtotime($start)) : '';
        $endFormatted   = $end   ? date('Y-m-d h:i A', strtotime($end))   : '';

        /* === SIMPLIFIED ELIGIBLE DESCRIPTION (ONE LINE) === */

        $eligibleDepartments = !empty($allowedDeptCodes)
            ? implode(', ', $allowedDeptCodes)
            : (!empty($seatDeptCodes) ? implode(', ', $seatDeptCodes) : 'ALL');

        $eligibleStatuses = !empty($allowedStatusCodes)
            ? implode(', ', $allowedStatusCodes)
            : 'ALL';

        // Final text shown in the PDF cell:
        $eligibleScopeText = 'Departments: ' . $eligibleDepartments . '; Status: ' . $eligibleStatuses;

        $detailsHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Election Details</h4>
        <table cellpadding="2" cellspacing="0" border="0" width="100%" style="font-size:9.5pt;">
          <tr>
            <td width="25%"><b>Scope:</b></td>
            <td width="30%">Non-Academic-Employee</td>
            <td width="20%"><b>Status:</b></td>
            <td width="25%">' . htmlspecialchars($status) . '</td>
          </tr>
          <tr>
            <td width="25%"><b>Start Date:</b></td>
            <td width="30%">' . htmlspecialchars($startFormatted) . '</td>
            <td width="20%"><b>End Date:</b></td>
            <td width="25%">' . htmlspecialchars($endFormatted) . '</td>
          </tr>
          <tr>
            <td width="25%"><b>Eligible Voters:</b></td>
            <td width="75%" colspan="3">' . htmlspecialchars($eligibleScopeText) . '</td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($detailsHtml, true, false, true, false, '');
        $pdf->Ln(3);

        /* ======================================================
           11. ELECTION SUMMARY (UPDATED – NO ABSTAIN COLUMN)
           ====================================================== */
        $summaryHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Election Summary</h4>
        <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
          <tr style="background-color:#154734;color:#ffffff;">
            <th><b>Eligible Voters</b></th>
            <th><b>Voters Who Voted</b></th>
            <th><b>Voters Who Didn\'t Vote</b></th>
            <th><b>Turnout Rate</b></th>
          </tr>
          <tr>
            <td>' . number_format($totalEligibleVoters) . '</td>
            <td>' . number_format($totalVotesCast) . '</td>
            <td>' . number_format($totalDidNotVote) . '</td>
            <td>' . $turnoutPercentage . '%</td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($summaryHtml, true, false, true, false, '');
        $pdf->Ln(3);

        /* ======================================================
           12. TURNOUT BY DEPARTMENT (NOW SHOWS FULL NAME)
           ====================================================== */
        if (!empty($deptSummary)) {
            $deptHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Department</h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="50%"><b>Department</b></th>
                <th width="17%"><b>Eligible Voters</b></th>
                <th width="17%"><b>Voted</b></th>
                <th width="16%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($deptSummary as $row) {
                $deptHtml .= '
                  <tr>
                    <td>&nbsp;' . htmlspecialchars($row['department']) . '</td>
                    <td align="right">' . number_format($row['eligible']) . '</td>
                    <td align="right">' . number_format($row['voted']) . '</td>
                    <td align="right">' . number_format($row['rate'], 1) . '%</td>
                  </tr>
                ';
            }

            $deptHtml .= '</table>';
            $pdf->writeHTML($deptHtml, true, false, true, false, '');
            $pdf->Ln(3);
        }

        /* ======================================================
           13. TURNOUT BY EMPLOYMENT STATUS
           ====================================================== */
        if (!empty($statusSummary)) {
            $statusHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Employment Status</h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="50%"><b>Status</b></th>
                <th width="17%"><b>Eligible Voters</b></th>
                <th width="17%"><b>Voted</b></th>
                <th width="16%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($statusSummary as $row) {
                $statusHtml .= '
                  <tr>
                    <td>&nbsp;' . htmlspecialchars($row['status']) . '</td>
                    <td align="right">' . number_format($row['eligible']) . '</td>
                    <td align="right">' . number_format($row['voted']) . '</td>
                    <td align="right">' . number_format($row['rate'], 1) . '%</td>
                  </tr>
                ';
            }

            $statusHtml .= '</table>';
            $pdf->writeHTML($statusHtml, true, false, true, false, '');
        }

        /* ======================================================
           14. CANDIDATE POSITION RANKING (NEW PAGE, WITH TIES)
           ====================================================== */
        if (!empty($positions)) {
            $pdf->AddPage();

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(21, 71, 52);
            $pdf->Cell(0, 8, "Candidate's Position Ranking", 0, 1, 'L');
            $pdf->Ln(3);

            $pdf->SetTextColor(0, 0, 0);
            $firstPos = true;

            foreach ($positions as $posName => $cands) {
                if ($firstPos) {
                    $firstPos = false;
                } else {
                    $pdf->Ln(6);
                }

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(21, 71, 52);
                $pdf->Cell(0, 7, 'Position: ' . $posName, 0, 1, 'L');
                $pdf->Ln(1);

                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->SetTextColor(0, 0, 0);

                $positionTotalVotes = 0;
                foreach ($cands as $cand) {
                    $positionTotalVotes += (int)($cand['vote_count'] ?? 0);
                }

                // --------- RANKING WITH TIES (same logic style as view_vote_counts.php) ----------
                // Candidates are already sorted by vote_count DESC from SQL.
                $prevVoteCount = null;
                $prevRank      = null;
                foreach ($cands as $index => &$cand) {
                    $votes = (int)$cand['vote_count'];

                    if ($votes > 0 && $prevVoteCount !== null && $votes === $prevVoteCount) {
                        // Tie with previous candidate (non-zero votes) → same rank number
                        $cand['rank'] = $prevRank;
                    } else {
                        // New rank
                        $cand['rank'] = $index + 1;
                        $prevRank     = $cand['rank'];
                    }

                    $prevVoteCount = $votes;
                }
                unset($cand);
                // --------------------------------------------------------------------------


                $html = '
                <table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:9pt;border-color:#d1d5db;">
                  <tr style="background-color:#154734;color:#ffffff;">
                    <th width="8%"><b>Rank</b></th>
                    <th width="37%"><b>Candidate Name</b></th>
                    <th width="20%"><b>Total Votes</b></th>
                    <th width="17%"><b>% of Position Votes</b></th>
                    <th width="18%"><b>Did Not Vote<br/>for Candidate</b></th>
                  </tr>
                ';

                foreach ($cands as $cand) {
                    $votes  = (int)$cand['vote_count'];
                    $noVote = max(0, $totalEligibleVoters - $votes);
                    $posPct = $positionTotalVotes > 0
                        ? round(($votes / $positionTotalVotes) * 100, 1)
                        : 0.0;

                    $html .= '
                      <tr>
                        <td align="center">' . (int)$cand['rank'] . '</td>
                        <td>' . htmlspecialchars($cand['candidate_name']) . '</td>
                        <td align="right">' . number_format($votes) . '</td>
                        <td align="right">' . number_format($posPct, 1) . '%</td>
                        <td align="right">' . number_format($noVote) . '</td>
                      </tr>
                    ';
                }

                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }

        /* ======================================================
           15. SIGNATORY
           ====================================================== */
        $pageHeight   = $pdf->getPageHeight();
        $bottomMargin = $pdf->getBreakMargin();
        $currentY     = $pdf->GetY();

        if ($currentY > ($pageHeight - $bottomMargin - 60)) {
            $pdf->AddPage();
        }

        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetY(-60);

        $generatedAt = date('F d, Y h:i A');
        $electionTitle = $election['title'] ?? 'Election';

        $signHtml = '
        <table width="100%" cellpadding="4" cellspacing="0" border="0" style="font-size:10pt;">
        <tr>
            <td width="50%" align="left">
            <b>Generated by:</b><br><br>
            ________________________________<br>
            ' . htmlspecialchars($adminName) . '<br>
            ' . htmlspecialchars($electionTitle) . ' Admin
            </td>
            <td width="50%" align="right">
            <b>Date Generated:</b> ' . htmlspecialchars($generatedAt) . '
            </td>
        </tr>
        </table>
        ';
        $pdf->writeHTML($signHtml, true, false, true, false, '');

        /* ======================================================
           16. OUTPUT PDF
           ====================================================== */
        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $election['title'] ?? 'election');
        $pdf->Output($safeTitle . '_nonacademic_report.pdf', 'I');
        exit;
    }
}
