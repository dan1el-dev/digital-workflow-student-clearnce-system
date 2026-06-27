<?php
/**
 * Role-Based Access Control Check Helper
 */
require_once __DIR__ . '/../config.php';

function check_auth($required_role = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: /auth/login.php');
        exit();
    }

    if ($required_role !== null && $_SESSION['role'] !== $required_role) {
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
                header('Location: /auth/login.php');
                exit();
        }
    }
    return true;
}
