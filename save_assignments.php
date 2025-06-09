<?php
// DB connection (PDO)
$pdo = new PDO('mysql:host=localhost;dbname=evoting_system;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $election_id = $_POST['election_id'] ?? null;
    $candidate_ids = $_POST['candidate_ids'] ?? [];

    if (!$election_id || empty($candidate_ids)) {
        die("Please select an election and at least one candidate.");
    }

    // Prepare statement to insert into election_candidates
    $stmt = $pdo->prepare("INSERT INTO election_candidates (election_id, candidate_id, position) VALUES (:election_id, :candidate_id, :position)");

    // Fetch positions for selected candidates (to insert into election_candidates.position)
    $placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
    $query = "SELECT id, position FROM candidates WHERE id IN ($placeholders)";
    $positions = $pdo->prepare($query);
    $positions->execute($candidate_ids);
    $candidates_positions = $positions->fetchAll(PDO::FETCH_KEY_PAIR); // id => position

    // Insert each candidate to election_candidates
    foreach ($candidate_ids as $candidate_id) {
        $position = $candidates_positions[$candidate_id] ?? '';
        $stmt->execute([
            ':election_id' => $election_id,
            ':candidate_id' => $candidate_id,
            ':position' => $position
        ]);
    }

    echo "Candidates successfully assigned to the election.";
} else {
    echo "Invalid request.";
}
