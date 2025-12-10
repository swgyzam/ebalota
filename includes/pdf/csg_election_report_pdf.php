<?php
/**
 * csg_election_report_pdf.php
 *
 * CSG-specific election report generator (styled, with summary,
 * college breakdown, candidate turnout, and signatory).
 */

if (!function_exists('generateCSGElectionReportPDF')) {
    function generateCSGElectionReportPDF(PDO $pdo, int $electionId, int $adminUserId, int $scopeId): void
    {
        /* ======================================================
           1. LOAD DEPENDENCIES
           ====================================================== */

        // TCPDF library
        require_once __DIR__ . '/../../tcpdf/tcpdf.php';

        // Shared helpers
        if (!defined('SCOPE_SPECIAL_CSG')) {
            require_once __DIR__ . '/../analytics_scopes.php';
        }
        require_once __DIR__ . '/../../admin_functions.php';

        /* ======================================================
           2. FETCH ELECTION + PERMISSION GUARDS
           ====================================================== */

        $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();

        if (!$election) {
            die('Election not found.');
        }

        if (($election['election_scope_type'] ?? '') !== 'Special-Scope') {
            die('This election is not a CSG (Special-Scope) election.');
        }

        if ((int) ($election['owner_scope_id'] ?? 0) !== $scopeId) {
            die('You do not have permission to generate this CSG report.');
        }

        /* ======================================================
           3. FETCH ADMIN + SCOPE SEAT (FOR SIGNATORY)
           ====================================================== */

        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$adminUserId]);
        $admin = $stmt->fetch() ?: ['first_name' => '', 'last_name' => '', 'email' => ''];

        $adminName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
        if ($adminName === '') {
            $adminName = $admin['email'] ?? 'Admin #' . $adminUserId;
        }

        // Scope seat details for this CSG admin
        $mySeat   = null;
        $csgSeats = getScopeSeats($pdo, SCOPE_SPECIAL_CSG);

        foreach ($csgSeats as $seat) {
            if ((int) $seat['scope_id'] === $scopeId) {
                $mySeat = $seat;
                break;
            }
        }

        $scopeDescription = 'CSG Admin';
        if ($mySeat) {
            $scopeDescription = formatScopeDetails(
                $mySeat['scope_type'],
                json_encode($mySeat['scope_details'])
            );
        }

        /* ======================================================
           4. ELIGIBLE VOTERS + TURNOUT (CSG GLOBAL)
           ====================================================== */

        $yearEnd = $election['end_datetime'] ?? null;

        // All CSG-eligible students as of election end
        $scopedStudents = getScopedVoters(
            $pdo,
            SCOPE_SPECIAL_CSG,
            null, // CSG is global
            [
                'year_end'      => $yearEnd,
                'include_flags' => true,
            ]
        );

        $eligibleStudentsForElection = [];
        foreach ($scopedStudents as $v) {
            $eligibleStudentsForElection[$v['user_id']] = $v;
        }
        $totalEligibleVoters = count($eligibleStudentsForElection);

        // Distinct voters who voted in this election
        $stmt = $pdo->prepare("SELECT DISTINCT voter_id FROM votes WHERE election_id = ?");
        $stmt->execute([$electionId]);
        $votedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $votedSet = array_flip($votedIds);

        $totalVotesCast = 0;
        foreach ($eligibleStudentsForElection as $uid => $_row) {
            if (isset($votedSet[$uid])) {
                $totalVotesCast++;
            }
        }

        $totalDidNotVote = max(0, $totalEligibleVoters - $totalVotesCast);

        // Abstain counts per election (still computed, but not shown in summary table)
        $perElectionStats = computePerElectionStatsWithAbstain(
            $pdo,
            SCOPE_SPECIAL_CSG,
            $scopeId,
            $scopedStudents,
            (int) date('Y', strtotime($election['start_datetime']))
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
           5. COLLEGE + DEPARTMENT + COURSE BREAKDOWN
           ====================================================== */

        $collegesMap = getColleges(); // for nicer display

        $collegeBuckets       = []; // [college => ['eligible' => x, 'voted' => y]]
        $collegeDeptBuckets   = []; // [college][department1] => ['eligible' => x, 'voted' => y]
        $collegeCourseBuckets = []; // [college][course]      => ['eligible' => x, 'voted' => y]

        foreach ($eligibleStudentsForElection as $uid => $v) {
            $college = $v['department']  ?: 'UNSPECIFIED';
            $dept    = $v['department1'] ?: 'General';
            $course  = $v['course']      ?: 'UNSPECIFIED';

            // College level
            if (!isset($collegeBuckets[$college])) {
                $collegeBuckets[$college] = [
                    'eligible' => 0,
                    'voted'    => 0,
                ];
            }
            $collegeBuckets[$college]['eligible']++;

            if (isset($votedSet[$uid])) {
                $collegeBuckets[$college]['voted']++;
            }

            // Department per college
            if (!isset($collegeDeptBuckets[$college])) {
                $collegeDeptBuckets[$college] = [];
            }
            if (!isset($collegeDeptBuckets[$college][$dept])) {
                $collegeDeptBuckets[$college][$dept] = [
                    'eligible' => 0,
                    'voted'    => 0,
                ];
            }
            $collegeDeptBuckets[$college][$dept]['eligible']++;
            if (isset($votedSet[$uid])) {
                $collegeDeptBuckets[$college][$dept]['voted']++;
            }

            // Course per college
            if (!isset($collegeCourseBuckets[$college])) {
                $collegeCourseBuckets[$college] = [];
            }
            if (!isset($collegeCourseBuckets[$college][$course])) {
                $collegeCourseBuckets[$college][$course] = [
                    'eligible' => 0,
                    'voted'    => 0,
                ];
            }
            $collegeCourseBuckets[$college][$course]['eligible']++;
            if (isset($votedSet[$uid])) {
                $collegeCourseBuckets[$college][$course]['voted']++;
            }
        }

        // ---- College Summary (used for ordering + top table) ----
        $collegeSummary = [];
        foreach ($collegeBuckets as $college => $data) {
            $eligible = (int) $data['eligible'];
            $voted    = (int) $data['voted'];
            $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

            $collegeSummary[] = [
                'college'  => $college,
                'eligible' => $eligible,
                'voted'    => $voted,
                'rate'     => $rate,
            ];
        }

        usort($collegeSummary, function ($a, $b) {
            return strcmp($a['college'], $b['college']);
        });

        /* ======================================================
           6. CANDIDATES GROUPED BY POSITION (TIE-AWARE RANKING)
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

        // Tie-aware ranking (1,1,3 style)
        foreach ($positions as $pos => &$cands) {
            $prevVoteCount = null;
            $prevRank      = null;

            foreach ($cands as $index => &$cand) {
                $votes = (int)($cand['vote_count'] ?? 0);

                if ($votes > 0 && $prevVoteCount !== null && $votes === $prevVoteCount) {
                    $cand['rank'] = $prevRank;
                } else {
                    $cand['rank'] = $index + 1;
                    $prevRank     = $cand['rank'];
                }

                $prevVoteCount = $votes;
            }
            unset($cand);
        }
        unset($cands);

        // ==========================
        // CUSTOM TCPDF CLASS (PAGE NUMBERS)
        // ==========================
        class PDF extends TCPDF {
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', '', 9);
                $pageText = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();
                $this->Cell(0, 10, $pageText, 0, 0, 'L');
            }
        }

        /* ======================================================
           7. INIT TCPDF + THEME COLORS + LOGO
           ====================================================== */

        $pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('eBalota');
        $pdf->SetAuthor($adminName);
        $pdf->SetTitle('Election Report - ' . ($election['title'] ?? ''));
        $pdf->SetSubject('CSG Election Report');

        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Place logo (30mm width) at top-left
        $logoPath = __DIR__ . '/../../assets/img/ebalota_logo.jpg';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 8, 30);
        }

        $pdf->SetY(20);

        // Header bar
        $headerHtml = '
        <table cellpadding="4" cellspacing="0" border="0" width="100%">
          <tr>
            <td width="60%" style="background-color:#154734;color:#ffffff;font-size:13pt;font-weight:bold;">
              &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CSG Election Report
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
           8. ELECTION DETAILS
           ====================================================== */

        $start  = $election['start_datetime'] ?? '';
        $end    = $election['end_datetime']   ?? '';
        $status = ucfirst($election['status'] ?? '');

        $startFormatted = $start ? date('Y-m-d h:i A', strtotime($start)) : '';
        $endFormatted   = $end   ? date('Y-m-d h:i A', strtotime($end))   : '';

        $detailsHtml = '
        <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Election Details</h4>
        <table cellpadding="2" cellspacing="0" border="0" width="100%" style="font-size:9.5pt;">
          <tr>
            <td width="25%"><b>Scope:</b></td>
            <td width="30%">CSG (Special-Scope)</td>
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
            <td colspan="3">All student voters (University wide)</td>
          </tr>
        </table>
        ';
        $pdf->writeHTML($detailsHtml, true, false, true, false, '');
        $pdf->Ln(3);

        /* ======================================================
           9. ELECTION SUMMARY (NO ABSTAIN COLUMN)
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
           10. TURNOUT BY COLLEGE
           ====================================================== */

        if (!empty($collegeSummary)) {
            $collegeHtml = '
            <h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by College</h4>
            <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
              <tr style="background-color:#154734;color:#ffffff;">
                <th width="40%"><b>College</b></th>
                <th width="20%"><b>Eligible</b></th>
                <th width="20%"><b>Voted</b></th>
                <th width="20%"><b>Turnout %</b></th>
              </tr>
            ';

            foreach ($collegeSummary as $row) {
                $collegeCode    = $row['college'];
                $collegeDisplay = $collegesMap[$collegeCode] ?? $collegeCode;

                $collegeHtml .= '
                  <tr>
                    <td>&nbsp;' . htmlspecialchars($collegeCode . ' – ' . $collegeDisplay) . '</td>
                    <td align="right">' . number_format($row['eligible']) . '</td>
                    <td align="right">' . number_format($row['voted']) . '</td>
                    <td align="right">' . number_format($row['rate'], 1) . '%</td>
                  </tr>
                ';
            }

            $collegeHtml .= '</table>';
            $pdf->writeHTML($collegeHtml, true, false, true, false, '');
        }

        /* ======================================================
           10.B TURNOUT BY DEPARTMENT (PER COLLEGE)
           ====================================================== */

        if (!empty($collegeDeptBuckets)) {
            $pdf->Ln(4);
            $pdf->writeHTML(
                '<h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Department (Per College)</h4>',
                true,
                false,
                true,
                false,
                ''
            );

            foreach ($collegeSummary as $colRow) {
                $collegeCode = $colRow['college'];

                if (empty($collegeDeptBuckets[$collegeCode])) {
                    continue;
                }

                $deptData = $collegeDeptBuckets[$collegeCode];
                ksort($deptData);

                $collegeDisplay = $collegesMap[$collegeCode] ?? $collegeCode;
                $collegeTitle   = $collegeCode . ' – ' . $collegeDisplay;

                // Subheading per college
                $pdf->Ln(3);
                $pdf->writeHTML(
                    '<h5 style="font-family: helvetica; font-size: 10pt; font-weight: bold;color:#1E6F46;">' .
                    'College: ' . htmlspecialchars($collegeTitle) .
                    '</h5>',
                    true,
                    false,
                    true,
                    false,
                    ''
                );
                $pdf->Ln(1.5); // extra space before table

                $deptHtml = '
                <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
                  <tr style="background-color:#154734;color:#ffffff;">
                    <th width="50%"><b>Department</b></th>
                    <th width="17%"><b>Eligible</b></th>
                    <th width="17%"><b>Voted</b></th>
                    <th width="16%"><b>Turnout %</b></th>
                  </tr>
                ';

                foreach ($deptData as $deptName => $stats) {
                    $eligible = (int) $stats['eligible'];
                    $voted    = (int) $stats['voted'];
                    $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

                    $deptHtml .= '
                      <tr>
                        <td>&nbsp;' . htmlspecialchars($deptName) . '</td>
                        <td align="right">' . number_format($eligible) . '</td>
                        <td align="right">' . number_format($voted) . '</td>
                        <td align="right">' . number_format($rate, 1) . '%</td>
                      </tr>
                    ';
                }

                $deptHtml .= '</table>';
                $pdf->writeHTML($deptHtml, true, false, true, false, '');
            }
        }

        /* ======================================================
           10.C TURNOUT BY COURSE (PER COLLEGE)
           ====================================================== */

        if (!empty($collegeCourseBuckets)) {
            $pdf->Ln(4);
            $pdf->writeHTML(
                '<h4 style="font-family: helvetica; font-size: 11pt; font-weight: bold;color:#154734;">Turnout by Course (Per College)</h4>',
                true,
                false,
                true,
                false,
                ''
            );

            foreach ($collegeSummary as $colRow) {
                $collegeCode = $colRow['college'];

                if (empty($collegeCourseBuckets[$collegeCode])) {
                    continue;
                }

                $courseData = $collegeCourseBuckets[$collegeCode];
                ksort($courseData);

                $collegeDisplay = $collegesMap[$collegeCode] ?? $collegeCode;
                $collegeTitle   = $collegeCode . ' – ' . $collegeDisplay;

                // Subheading per college
                $pdf->Ln(3);
                $pdf->writeHTML(
                    '<h5 style="font-family: helvetica; font-size: 10pt; font-weight: bold;color:#1E6F46;">' .
                    'College: ' . htmlspecialchars($collegeTitle) .
                    '</h5>',
                    true,
                    false,
                    true,
                    false,
                    ''
                );
                $pdf->Ln(1.5); // extra space before table

                $courseHtml = '
                <table cellpadding="4" cellspacing="0" border="1" width="100%" style="font-size:9.5pt;border-color:#d1d5db;">
                  <tr style="background-color:#154734;color:#ffffff;">
                    <th width="50%"><b>Course</b></th>
                    <th width="17%"><b>Eligible</b></th>
                    <th width="17%"><b>Voted</b></th>
                    <th width="16%"><b>Turnout %</b></th>
                  </tr>
                ';

                foreach ($courseData as $courseName => $stats) {
                    $eligible = (int) $stats['eligible'];
                    $voted    = (int) $stats['voted'];
                    $rate     = $eligible > 0 ? round(($voted / $eligible) * 100, 1) : 0.0;

                    $courseHtml .= '
                      <tr>
                        <td>&nbsp;' . htmlspecialchars($courseName) . '</td>
                        <td align="right">' . number_format($eligible) . '</td>
                        <td align="right">' . number_format($voted) . '</td>
                        <td align="right">' . number_format($rate, 1) . '%</td>
                      </tr>
                    ';
                }

                $courseHtml .= '</table>';
                $pdf->writeHTML($courseHtml, true, false, true, false, '');
            }
        }

        /* ======================================================
        11. CANDIDATE TABLES PER POSITION (WITH GREEN WINNERS)
        ====================================================== */

        if (!empty($positions)) {

            // ✨ ADD SEPARATOR BEFORE STARTING CANDIDATES SECTION
            $pdf->Ln(6); // double-space
            $pdf->writeHTML('<hr style="height:1.2px;border:none;background-color:#333;">', true, false, true, false, '');
            $pdf->Ln(3); // extra spacing

            // Section title
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(21, 71, 52);
            $pdf->Cell(0, 8, "Candidate's Position Ranking", 0, 1, 'L');
            $pdf->Ln(3);

            $pdf->SetTextColor(0, 0, 0);
            $firstPos = true;

            foreach ($positions as $posName => $cands) {

                // Compute total votes
                $positionTotalVotes = 0;
                foreach ($cands as $cand) {
                    $positionTotalVotes += (int)($cand['vote_count'] ?? 0);
                }

                // Sort
                usort($cands, function($a, $b) {
                    return ((int)$b['vote_count'] <=> (int)$a['vote_count']);
                });

                // Tie-aware ranking
                $prevVoteCount = null;
                $prevRank      = null;
                foreach ($cands as $idx => &$cand) {
                    $votes = (int)$cand['vote_count'];
                    if ($votes > 0 && $prevVoteCount !== null && $votes === $prevVoteCount) {
                        $cand['rank'] = $prevRank;
                    } else {
                        $cand['rank'] = $idx + 1;
                        $prevRank     = $cand['rank'];
                    }
                    $prevVoteCount = $votes;
                }
                unset($cand);

                if (!$firstPos) {
                    $pdf->Ln(5); // spacing between positions
                }
                $firstPos = false;

                // Title
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(21, 71, 52);
                $pdf->Cell(0, 7, 'Position: ' . $posName, 0, 1, 'L');
                $pdf->Ln(1);
                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->SetTextColor(0, 0, 0);

                // Table header
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

                foreach ($cands as $candRow) {

                    $votes  = (int)$candRow['vote_count'];
                    $noVote = max(0, $totalEligibleVoters - $votes);
                    $posPct = $positionTotalVotes > 0
                        ? round(($votes / $positionTotalVotes) * 100, 1)
                        : 0.0;

                    // Winner highlight (rank 1 + votes > 0)
                    $isWinner = ($candRow['rank'] === 1 && $votes > 0);

                    $rowStyle = $isWinner
                        ? ' style="background-color:#E6F4EA;"'
                        : '';

                    $html .= '
                    <tr' . $rowStyle . '>
                        <td align="center">' . (int)$candRow['rank'] . '</td>
                        <td>' . htmlspecialchars($candRow['candidate_name']) . '</td>
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
           12. SIGNATORY ON LAST PAGE (BOTTOM)
           ====================================================== */

        $pageHeight   = $pdf->getPageHeight();
        $bottomMargin = $pdf->getBreakMargin(); // usually 20mm
        $currentY     = $pdf->GetY();

        if ($currentY > ($pageHeight - $bottomMargin - 60)) {
            $pdf->AddPage();
        }

        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetY(-60); // position ~60mm from bottom

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
           13. OUTPUT PDF
           ====================================================== */

        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $election['title'] ?? 'election');
        $pdf->Output($safeTitle . '_report.pdf', 'I');
        exit;
    }
}
