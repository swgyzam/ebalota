<?php
/**
 * faculty_election_report_pdf.php
 *
 * Academic-Faculty election report generator.
 * For elections with scope_type = 'Academic-Faculty'
 * and voters with position='academic'.
 */

if (!function_exists('generateFacultyElectionReportPDF')) {
    function generateFacultyElectionReportPDF(PDO $pdo, int $electionId, int $adminUserId, int $scopeId): void
    {
        /* ======================================================
           1. LOAD DEPENDENCIES
           ====================================================== */
        require_once __DIR__ . '/../../tcpdf/tcpdf.php';

        if (!defined('SCOPE_ACAD_FACULTY')) {
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

        if (($election['election_scope_type'] ?? '') !== 'Academic-Faculty') {
            die('This election is not an Academic-Faculty election.');
        }

        if ((int)($election['owner_scope_id'] ?? 0) !== $scopeId) {
            die('You do not have permission to generate this faculty report.');
        }

        /* ======================================================
           3. FETCH ADMIN + SCOPE SEAT (FOR SIGNATORY & DETAILS)
           ====================================================== */
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$adminUserId]);
        $admin = $stmt->fetch() ?: ['first_name' => '', 'last_name' => '', 'email' => ''];

        $adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
        if ($adminName === '') {
            $adminName = $admin['email'] ?? ('Admin #' . $adminUserId);
        }

        $mySeat   = null;
        $facSeats = getScopeSeats($pdo, SCOPE_ACAD_FACULTY);
        foreach ($facSeats as $seat) {
            if ((int)$seat['scope_id'] === $scopeId) {
                $mySeat = $seat;
                break;
            }
        }

        if (!$mySeat) {
            die('Academic-Faculty scope seat not found.');
        }

        $scopeDetails  = $mySeat['scope_details'] ?? [];
        $collegeCode   = strtoupper(trim($mySeat['assigned_scope'] ?? '')); // CEIT, CAS, ...
        $seatDeptCodes = [];
        if (!empty($scopeDetails['departments']) && is_array($scopeDetails['departments'])) {
            $seatDeptCodes = array_values(
                array_filter(array_map('trim', $scopeDetails['departments']))
            ); // e.g. ['DIT','DCEE']
        }

        /* ======================================================
           4. ELIGIBLE FACULTY + TURNOUT
           ====================================================== */
        $yearEnd = $election['end_datetime'] ?? null;

        // All faculty for this seat as of election end (college + dept filtering handled in helper)
        $seatFacultyAtEnd = getScopedVoters(
            $pdo,
            SCOPE_ACAD_FACULTY,
            $scopeId,
            [
                'year_end'      => $yearEnd,
                'include_flags' => true,
            ]
        );

        // Election allowed_status filter (Regular, Probationary, etc.)
        $allowedStatusCodes = array_filter(
            array_map('strtoupper', array_map('trim', explode(',', $election['allowed_status'] ?? '')))
        );
        $restrictByStatus = !empty($allowedStatusCodes) && !in_array('ALL', $allowedStatusCodes, true);

        $eligibleFacultyForElection = [];

        foreach ($seatFacultyAtEnd as $f) {
            // Explicit college guard just in case
            if ($collegeCode !== '' && strtoupper($f['department'] ?? '') !== $collegeCode) {
                continue;
            }

            // Status guard
            if ($restrictByStatus) {
                $facStatus = strtoupper(trim($f['status'] ?? ''));
                if (!in_array($facStatus, $allowedStatusCodes, true)) {
                    continue;
                }
            }

            $eligibleFacultyForElection[(int)$f['user_id']] = $f;
        }

        $totalEligibleVoters = count($eligibleFacultyForElection);

        // Distinct voters who actually voted
        $stmt = $pdo->prepare("SELECT DISTINCT voter_id FROM votes WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $votedSet = array_flip($votedIds);

        $totalVotesCast = 0;
        foreach ($eligibleFacultyForElection as $uid => $_) {
            if (isset($votedSet[$uid])) {
                $totalVotesCast++;
            }
        }

        $totalDidNotVote = max(0, $totalEligibleVoters - $totalVotesCast);

        // For abstain stats (even if not shown in table), use all seat faculty without year_end cutoff
        $seatFacultyAll = getScopedVoters(
            $pdo,
            SCOPE_ACAD_FACULTY,
            $scopeId,
            [
                'year_end'      => null,
                'include_flags' => true,
            ]
        );

        $electionYear    = (int)date('Y', strtotime($election['start_datetime']));
        $perElectionStats = computePerElectionStatsWithAbstain(
            $pdo,
            SCOPE_ACAD_FACULTY,
            $scopeId,
            $seatFacultyAll,
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
           5. DEPARTMENT BREAKDOWN (BY FULL DEPARTMENT NAME)
           ====================================================== */
        $deptBuckets = []; // department1 => [eligible, voted]

        foreach ($eligibleFacultyForElection as $uid => $f) {
            $dept = $f['department1'] ?: 'Unspecified';

            if (!isset($deptBuckets[$dept])) {
                $deptBuckets[$dept] = ['eligible' => 0, 'voted' => 0];
            }
            $deptBuckets[$dept]['eligible']++;

            if (isset($votedSet[$uid])) {
                $deptBuckets[$dept]['voted']++;
            }
        }

        $deptSummary = [];
        foreach ($deptBuckets as $deptName => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $deptSummary[] = [
                'department' => $deptName,
                'eligible'   => $eligible,
                'voted'      => $voted,
                'rate'       => $rate,
            ];
        }
        usort($deptSummary, fn($a, $b) => strcmp($a['department'], $b['department']));

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

        // Tie-aware ranking: same votes => same rank, next rank skips (1,1,3, ...)
        foreach ($positions as $pos => &$cands) {
            $prevVoteCount = null;
            $prevRank      = null;

            foreach ($cands as $index => &$c) {
                $votes = (int)($c['vote_count'] ?? 0);

                if ($votes > 0 && $prevVoteCount !== null && $votes === $prevVoteCount) {
                    $c['rank'] = $prevRank;          // tie
                } else {
                    $c['rank'] = $index + 1;         // new rank
                    $prevRank  = $c['rank'];
                }

                $prevVoteCount = $votes;
            }
            unset($c);
        }
        unset($cands);

        /* ======================================================
           7. CUSTOM TCPDF CLASS (FOOTER)
           ====================================================== */
        class FacultyPDF extends TCPDF {
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
        $pdf = new FacultyPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('eBalota');
        $pdf->SetAuthor($adminName);
        $pdf->SetTitle('Faculty Election Report - ' . ($election['title'] ?? ''));
        $pdf->SetSubject('Academic-Faculty Election Report');

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
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Academic-Faculty Election Report
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
           10. ELECTION DETAILS (UPDATED LAYOUT)
           ====================================================== */
        $start  = $election['start_datetime'] ?? '';
        $end    = $election['end_datetime']   ?? '';
        $status = ucfirst($election['status'] ?? '');

        $startFormatted = $start ? date('Y-m-d h:i A', strtotime($start)) : '';
        $endFormatted   = $end   ? date('Y-m-d h:i A', strtotime($end))   : '';

        // Build pretty status text (not ALL CAPS)
        $prettyStatuses = 'All statuses';
        if ($restrictByStatus) {
            $pretty = [];
            foreach ($allowedStatusCodes as $code) {
                if ($code === '' || $code === 'ALL') {
                    continue;
                }
                $s = strtolower($code);               // regular, part-time
                $s = str_replace('_', ' ', $s);
                $s = ucwords($s);                     // Regular, Part-Time
                $pretty[] = $s;
            }
            if ($pretty) {
                $prettyStatuses = implode(', ', $pretty);
            }
        }

        // Departments in seat scope (codes)
        $deptCodesText = $seatDeptCodes ? implode(', ', $seatDeptCodes) : 'All departments';
        $collegeText   = $collegeCode ?: 'All colleges';

        // Eligible voters description (one line)
        $eligibleScopeText =
            $collegeText . ' – ' . $deptCodesText . 
            ' Faculty | Status: ' . $prettyStatuses . ' (seat scope)';

        // Scope line: Academic-Faculty (CEIT scope)
        $scopeDisplay = 'Academic-Faculty';
        if ($collegeCode !== '') {
            $scopeDisplay .= ' (' . $collegeCode . ' scope)';
        }

        $detailsHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Election Details</h4>
        <table cellpadding="2" cellspacing="0" border="0" width="100%" style="font-size:9.5pt;">
          <tr>
            <td width="25%"><b>Scope:</b></td>
            <td width="30%">' . htmlspecialchars($scopeDisplay) . '</td>
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
           11. ELECTION SUMMARY (NO ABSTAIN COLUMN)
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
           12. TURNOUT BY DEPARTMENT
           ====================================================== */
        if (!empty($deptSummary)) {
            $deptHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Department</h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="50%"><b>Department</b></th>
                <th width="17%"><b>Eligible</b></th>
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
           13. CANDIDATE POSITION RANKING (NEW PAGE, WITH TIES)
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
           14. SIGNATORY
           ====================================================== */
        $pageHeight   = $pdf->getPageHeight();
        $bottomMargin = $pdf->getBreakMargin();
        $currentY     = $pdf->GetY();

        if ($currentY > ($pageHeight - $bottomMargin - 60)) {
            $pdf->AddPage();
        }

        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetY(-60);

        $generatedAt   = date('F d, Y h:i A');
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
           15. OUTPUT PDF
           ====================================================== */
        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $election['title'] ?? 'faculty_election');
        $pdf->Output($safeTitle . '_faculty_report.pdf', 'I');
        exit;
    }
}
