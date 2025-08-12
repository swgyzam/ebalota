<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}
?>
