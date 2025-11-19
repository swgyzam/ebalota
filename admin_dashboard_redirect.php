<?php 
session_start();
date_default_timezone_set('Asia/Manila');

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- Super Admin redirect ---
if ($_SESSION['role'] === 'super_admin') {
    header("Location: super_admin/dashboard.php");
    exit;
}

// --- Admin redirect using NEW scope system only ---
if ($_SESSION['role'] === 'admin') {

    // Set by admin_verify_token.php
    $scopeCategory = $_SESSION['scope_category'] ?? '';

    // Safety: kung wala / empty scope_category, huwag mag-crash
    if ($scopeCategory === '' || $scopeCategory === null) {
        // You can change this to an error page if you want
        header("Location: admin_dashboard_default.php");
        exit;
    }

    switch ($scopeCategory) {

        case 'Special-Scope':
            // CSG Admin – system-wide student org management
            $redirectUrl = "admin_dashboard_csg.php";
            break;

        case 'Others-COOP':
            // COOP Admin – COOP + MIGS employees
            $redirectUrl = "admin_dashboard_coop.php";
            break;

        case 'Academic-Student':
            // Academic - Student: by college + department + course (students only)
            $redirectUrl = "admin_dashboard_college.php";
            break;

        case 'Academic-Faculty':
            // Academic - Faculty: faculty employees, with colleges + departments + status
            $redirectUrl = "admin_dashboard_faculty.php";
            break;

        case 'Non-Academic-Employee':
            // Non-Academic Employee Admin
            $redirectUrl = "admin_dashboard_nonacademic.php";
            break;

        case 'Others-Default':
            // Default Admin – all faculty + non-academic employees
            $redirectUrl = "admin_dashboard_default.php";
            break;

        case 'Non-Academic-Student':
            // Non-Academic Student Admin – org-based student admins
            $redirectUrl = "admin_dashboard_nonacad_students.php";
            break;

        default:
            // Unknown / future scope type – safe, neutral fallback
            $redirectUrl = "admin_dashboard_default.php";
    }

    header("Location: $redirectUrl");
    exit;
}

// --- Other roles (voters, etc.) ---
header("Location: voters_dashboard.php");
exit();
