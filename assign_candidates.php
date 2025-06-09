<?php
// Start session with secure settings
session_start();

date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
// DB connection (PDO)
$pdo = new PDO('mysql:host=localhost;dbname=evoting_system;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Fetch elections
$elections = $pdo->query("SELECT election_id, title FROM elections")->fetchAll(PDO::FETCH_ASSOC);

// Fetch candidates
$candidates = $pdo->query("SELECT id, full_name, position FROM candidates")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Candidates to Election</title>
</head>
<body>
    <h2>Assign Candidates to Election</h2>
    <form method="POST" action="save_assignments.php">
        <label for="election">Select Election:</label><br>
        <select name="election_id" id="election" required>
            <option value="">-- Select Election --</option>
            <?php foreach ($elections as $election): ?>
                <option value="<?= htmlspecialchars($election['election_id']) ?>">
                    <?= htmlspecialchars($election['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <h3>Select Candidates:</h3>
        <?php foreach ($candidates as $candidate): ?>
            <label>
                <input type="checkbox" name="candidate_ids[]" value="<?= $candidate['id'] ?>">
                <?= htmlspecialchars($candidate['full_name']) ?> (<?= htmlspecialchars($candidate['position']) ?>)
            </label><br>
        <?php endforeach; ?>

        <br>
        <button type="submit">Assign Selected Candidates</button>
    </form>
</body>
</html>
