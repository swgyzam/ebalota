<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'voter') {
    header("Location: login.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect if accessed without POST
    header("Location: voter_dashboard.php");
    exit;
}

$voter_id = $_SESSION['user_id'];
$election_id = $_POST['election_id'] ?? null;
$candidate_id = $_POST['candidate_id'] ?? null;

if (!$election_id || !$candidate_id) {
    die("Invalid vote submission.");
}

$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Check if voter has already voted in this election
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ? AND voter_id = ?");
    $stmt->execute([$election_id, $voter_id]);
    $already_voted = $stmt->fetchColumn();

    if ($already_voted) {
        die("You have already voted in this election.");
    }

    // Insert the vote
    $stmt = $pdo->prepare("INSERT INTO votes (election_id, candidate_id, voter_id) VALUES (?, ?, ?)");
    $stmt->execute([$election_id, $candidate_id, $voter_id]);

    // Redirect or show success message
    header("Location: voters_dashboard.php?message=vote_success");
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
