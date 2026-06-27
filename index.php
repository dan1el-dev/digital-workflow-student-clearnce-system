<?php
/**
 * Digital Student Clearance System
 * Landing / Routing Page
 */
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'student':
            header('Location: /student/dashboard.php');
            exit();
        case 'officer':
            header('Location: /officer/dashboard.php');
            exit();
        case 'admin':
            header('Location: /admin/dashboard.php');
            exit();
        default:
            // Invalid role, clear session and show login
            session_destroy();
            header('Location: /auth/login.php');
            exit();
    }
} else {
    header('Location: /auth/login.php');
    exit();
}
