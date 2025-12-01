<?php
/**
 * coop_election_report_pdf.php
 *
 * COOP (Others-COOP) election report generator.
 * For elections with scope_type = 'Others-COOP'
 * and voters under SCOPE_OTHERS_COOP (MIGS).
 */

if (!function_exists('generateCoopElectionReportPDF')) {
    function generateCoopElectionReportPDF(PDO $pdo, int $electionId, int $adminUserId, int $scopeId): void
    {
        /* ======================================================
           1. LOAD DEPENDENCIES
           ====================================================== */
        require_once __DIR__ . '/../../tcpdf/tcpdf.php';

        if (!defined('SCOPE_OTHERS_COOP')) {
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

        // Guard: must be a COOP election under Others-COOP scope
        if (($election['election_scope_type'] ?? '') !== 'Others-COOP') {
            die('This election is not a COOP (Others-COOP) election.');
        }

        if ((int)($election['owner_scope_id'] ?? 0) !== $scopeId) {
            die('You do not have permission to generate this COOP report.');
        }

        $targetPos = strtolower($election['target_position'] ?? '');
        if (!in_array($targetPos, ['coop', 'all'], true)) {
            die('This election is not configured as a COOP/all election.');
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

        $mySeat    = null;
        $coopSeats = getScopeSeats($pdo, SCOPE_OTHERS_COOP);
        foreach ($coopSeats as $seat) {
            if ((int)$seat['scope_id'] === $scopeId) {
                $mySeat = $seat;
                break;
            }
        }

        if (!$mySeat) {
            die('COOP scope seat not found.');
        }

        $scopeDetails = $mySeat['scope_details'] ?? [];
        $coopName     = $scopeDetails['coop_name'] ?? ($election['title'] ?? 'COOP');

        /* ======================================================
           4. ELIGIBLE COOP MIGS + TURNOUT
           ====================================================== */

        // Same logic as admin_analytics_coop.php: yearEnd cutoff, scoped voters, MIGS only.
        $yearEnd = $election['end_datetime'] ?? null;

        // All scoped COOP MIGS as of this election's end
        $scopedCoopMembers = getScopedVoters(
            $pdo,
            SCOPE_OTHERS_COOP,
            $scopeId,
            [
                'year_end'      => $yearEnd,
                'include_flags' => true,
            ]
        );

        // Build eligible set for this election (currently: all MIGS in this scope)
        $eligibleCoopForElection = [];
        foreach ($scopedCoopMembers as $v) {
            $eligibleCoopForElection[(int)$v['user_id']] = $v;
        }
        $totalEligibleVoters = count($eligibleCoopForElection);

        // Distinct voters who voted in this election
        $stmt = $pdo->prepare("SELECT DISTINCT voter_id FROM votes WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $votedSet = array_flip($votedIds);

        $totalVotesCast = 0;
        foreach ($eligibleCoopForElection as $uid => $_row) {
            if (isset($votedSet[$uid])) {
                $totalVotesCast++;
            }
        }

        $totalDidNotVote = max(0, $totalEligibleVoters - $totalVotesCast);

        // For abstain stats, use all COOP members without year_end cutoff
        $seatCoopAll = getScopedVoters(
            $pdo,
            SCOPE_OTHERS_COOP,
            $scopeId,
            [
                'year_end'      => null,
                'include_flags' => true,
            ]
        );

        $electionYear     = (int) date('Y', strtotime($election['start_datetime']));
        $perElectionStats = computePerElectionStatsWithAbstain(
            $pdo,
            SCOPE_OTHERS_COOP,
            $scopeId,
            $seatCoopAll,
            $electionYear
        );

        $totalAbstained = 0;
        foreach ($perElectionStats as $row) {
            if ((int) $row['election_id'] === $electionId) {
                $totalAbstained = (int) ($row['abstain_count'] ?? 0);
                break;
            }
        }

        $turnoutPercentage = $totalEligibleVoters > 0
            ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1)
            : 0.0;

        /* ======================================================
           5. TURNOUT BREAKDOWN (POSITION / STATUS / COLLEGE / DEPT)
           ====================================================== */

        $positionBuckets        = []; // position => [eligible, voted]
        $statusBuckets          = []; // status   => [eligible, voted]
        $facultyCollegeBuckets  = []; // college (department) for Faculty
        $nonAcadDeptBuckets     = []; // department1 for Non-Academic Employees

        foreach ($eligibleCoopForElection as $uid => $v) {
            $pos      = $v['position']    ?: 'UNSPECIFIED';
            $status   = $v['status']      ?: 'Unspecified';
            $college  = $v['department']  ?: 'UNSPECIFIED';       // for faculty colleges
            $deptCode = $v['department']  ?: 'Unspecified';       // for non-ac departments
            $deptFull = $v['department1'] ?: 'Unspecified';       // full dept (mostly for acad)
        
            // -----------------------------
            // 1) Position bucket
            // -----------------------------
            if (!isset($positionBuckets[$pos])) {
                $positionBuckets[$pos] = ['eligible' => 0, 'voted' => 0];
            }
            $positionBuckets[$pos]['eligible']++;
            if (isset($votedSet[$uid])) {
                $positionBuckets[$pos]['voted']++;
            }
        
            // -----------------------------
            // 2) Status bucket
            // -----------------------------
            if (!isset($statusBuckets[$status])) {
                $statusBuckets[$status] = ['eligible' => 0, 'voted' => 0];
            }
            $statusBuckets[$status]['eligible']++;
            if (isset($votedSet[$uid])) {
                $statusBuckets[$status]['voted']++;
            }
        
            // -----------------------------
            // 3) Faculty – by college (department code)
            // -----------------------------
            if ($pos === 'academic') {
                if (!isset($facultyCollegeBuckets[$college])) {
                    $facultyCollegeBuckets[$college] = ['eligible' => 0, 'voted' => 0];
                }
                $facultyCollegeBuckets[$college]['eligible']++;
                if (isset($votedSet[$uid])) {
                    $facultyCollegeBuckets[$college]['voted']++;
                }
            }
        
            // -----------------------------
            // 4) Non-Academic – by department (DEPARTMENT CODE!)
            // -----------------------------
            if ($pos === 'non-academic') {
                // gamitin department (ADMIN, LIBRARY, FINANCE, IT, HR)
                $deptName = $deptCode ?: 'Unspecified';
        
                if (!isset($nonAcadDeptBuckets[$deptName])) {
                    $nonAcadDeptBuckets[$deptName] = ['eligible' => 0, 'voted' => 0];
                }
                $nonAcadDeptBuckets[$deptName]['eligible']++;
                if (isset($votedSet[$uid])) {
                    $nonAcadDeptBuckets[$deptName]['voted']++;
                }
            }
        }        

        $positionSummary = [];
        foreach ($positionBuckets as $pos => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            // Pretty name for display
            $displayPos = $pos;
            if ($pos === 'academic') {
                $displayPos = 'Faculty';
            } elseif ($pos === 'non-academic') {
                $displayPos = 'Non-Academic Employee';
            }

            $positionSummary[] = [
                'position'  => $displayPos,
                'raw_pos'   => $pos,
                'eligible'  => $eligible,
                'voted'     => $voted,
                'rate'      => $rate,
            ];
        }
        usort($positionSummary, fn($a, $b) => strcmp($a['position'], $b['position']));

        $statusSummary = [];
        foreach ($statusBuckets as $stat => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $statusSummary[] = [
                'status'   => $stat,
                'eligible' => $eligible,
                'voted'    => $voted,
                'rate'     => $rate,
            ];
        }
        usort($statusSummary, fn($a, $b) => strcmp($a['status'], $b['status']));

        $facultyCollegeSummary = [];
        foreach ($facultyCollegeBuckets as $college => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $facultyCollegeSummary[] = [
                'college'  => $college,
                'eligible' => $eligible,
                'voted'    => $voted,
                'rate'     => $rate,
            ];
        }
        usort($facultyCollegeSummary, fn($a, $b) => strcmp($a['college'], $b['college']));

        $nonAcadDeptSummary = [];
        foreach ($nonAcadDeptBuckets as $dept => $stats) {
            $eligible = (int)$stats['eligible'];
            $voted    = (int)$stats['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $nonAcadDeptSummary[] = [
                'department' => $dept,
                'eligible'   => $eligible,
                'voted'      => $voted,
                'rate'       => $rate,
            ];
        }
        usort($nonAcadDeptSummary, fn($a, $b) => strcmp($a['department'], $b['department']));

        /* ======================================================
           6. CANDIDATES GROUPED BY POSITION (WITH RANK & TIES)
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

        // Tie-aware ranking: same votes => same rank
        foreach ($positions as $pos => &$cands) {
            $prevVoteCount = null;
            $prevRank      = null;

            foreach ($cands as $index => &$c) {
                $votes = (int)($c['vote_count'] ?? 0);

                if ($votes > 0 && $prevVoteCount !== null && $votes === $prevVoteCount) {
                    $c['rank'] = $prevRank;
                } else {
                    $c['rank'] = $index + 1;
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
        class CoopPDF extends TCPDF {
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
        $pdf = new CoopPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('eBalota');
        $pdf->SetAuthor($adminName);
        $pdf->SetTitle('COOP Election Report - ' . ($election['title'] ?? ''));
        $pdf->SetSubject('COOP Election Report');

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

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
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;COOP Election Report
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

        $scopeDisplay = 'Others-COOP';
        if (!empty($coopName)) {
            $scopeDisplay .= ' (' . $coopName . ')';
        }

        $eligibleScopeText = 'COOP MIGS members scoped to this seat';

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
           11. ELECTION SUMMARY (BASIC TURNOUT)
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
           12. TURNOUT BY POSITION
           ====================================================== */
        if (!empty($positionSummary)) {
            $posHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Position</h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="40%"><b>Position</b></th>
                <th width="20%"><b>Eligible</b></th>
                <th width="20%"><b>Voted</b></th>
                <th width="20%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($positionSummary as $row) {
                $posHtml .= '
                  <tr>
                    <td>&nbsp;' . htmlspecialchars($row['position']) . '</td>
                    <td align="right">' . number_format($row['eligible']) . '</td>
                    <td align="right">' . number_format($row['voted']) . '</td>
                    <td align="right">' . number_format($row['rate'], 1) . '%</td>
                  </tr>
                ';
            }

            $posHtml .= '</table>';
            $pdf->writeHTML($posHtml, true, false, true, false, '');
            $pdf->Ln(3);
        }

        /* ======================================================
           13. TURNOUT BY STATUS
           ====================================================== */
        if (!empty($statusSummary)) {
            $statHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Status</h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="40%"><b>Status</b></th>
                <th width="20%"><b>Eligible</b></th>
                <th width="20%"><b>Voted</b></th>
                <th width="20%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($statusSummary as $row) {
                $statHtml .= '
                  <tr>
                    <td>&nbsp;' . htmlspecialchars($row['status']) . '</td>
                    <td align="right">' . number_format($row['eligible']) . '</td>
                    <td align="right">' . number_format($row['voted']) . '</td>
                    <td align="right">' . number_format($row['rate'], 1) . '%</td>
                  </tr>
                ';
            }

            $statHtml .= '</table>';
            $pdf->writeHTML($statHtml, true, false, true, false, '');
            $pdf->Ln(3);
        }

        /* ======================================================
           14. TURNOUT BY COLLEGE – FACULTY
           ====================================================== */
        if (!empty($facultyCollegeSummary)) {
            $facHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">
              Turnout by College – Faculty
            </h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="50%"><b>College</b></th>
                <th width="17%"><b>Eligible</b></th>
                <th width="17%"><b>Voted</b></th>
                <th width="16%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($facultyCollegeSummary as $row) {
                $facHtml .= '
                  <tr>
                    <td>&nbsp;' . htmlspecialchars($row['college']) . '</td>
                    <td align="right">' . number_format($row['eligible']) . '</td>
                    <td align="right">' . number_format($row['voted']) . '</td>
                    <td align="right">' . number_format($row['rate'], 1) . '%</td>
                  </tr>
                ';
            }

            $facHtml .= '</table>';
            $pdf->writeHTML($facHtml, true, false, true, false, '');
            $pdf->Ln(3);
        }

        /* ======================================================
           15. TURNOUT BY DEPARTMENT – NON-ACADEMIC EMPLOYEES
           ====================================================== */
        if (!empty($nonAcadDeptSummary)) {
            $deptHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">
              Turnout by Department – Non-Academic Employees
            </h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="50%"><b>Department</b></th>
                <th width="17%"><b>Eligible</b></th>
                <th width="17%"><b>Voted</b></th>
                <th width="16%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($nonAcadDeptSummary as $row) {
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
           16. CANDIDATE POSITION RANKING (NEW PAGE, WITH TIES)
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
           17. SIGNATORY
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
           18. OUTPUT PDF
           ====================================================== */
        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $election['title'] ?? 'coop_election');
        $pdf->Output($safeTitle . '_coop_report.pdf', 'I');
        exit;
    }
}
