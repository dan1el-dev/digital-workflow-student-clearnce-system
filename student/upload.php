<?php
/**
 * Handle Student Document Upload
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce student role
check_auth('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['user_id'];
    $department_id = trim($_POST['department_id'] ?? '');

    if (empty($department_id)) {
        $_SESSION['error_message'] = 'Invalid department specified.';
        header('Location: dashboard.php');
        exit();
    }

    // 1. Fetch clearance item to upload document for
    $stmt = $pdo->prepare("
        SELECT ci.item_id, d.department_name, ci.status 
        FROM clearance_items ci
        JOIN clearance_requests cr ON ci.request_id = cr.request_id
        JOIN departments d ON ci.department_id = d.department_id
        WHERE cr.student_id = ? AND ci.department_id = ? AND cr.status != 'approved'
    ");
    $stmt->execute([$student_id, $department_id]);
    $item = $stmt->fetch();

    if (!$item) {
        $_SESSION['error_message'] = 'No active clearance item found for the specified department.';
        header('Location: dashboard.php');
        exit();
    }

    if ($item['status'] === 'approved') {
        $_SESSION['error_message'] = 'Clearance for ' . htmlspecialchars($item['department_name']) . ' has already been approved.';
        header('Location: dashboard.php');
        exit();
    }

    // 2. Validate file upload
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $err_code = $_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $_SESSION['error_message'] = 'File upload failed. Error code: ' . $err_code;
        header('Location: dashboard.php');
        exit();
    }

    $file_tmp = $_FILES['document_file']['tmp_name'];
    $file_name = $_FILES['document_file']['name'];
    $file_size = $_FILES['document_file']['size'];
    
    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file_size > $max_size) {
        $_SESSION['error_message'] = 'File is too large. Maximum size is 5MB.';
        header('Location: dashboard.php');
        exit();
    }

    // Validate file extension
    $allowed_extensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['error_message'] = 'Invalid file type. Only PDF and images (PNG, JPG, JPEG, WEBP) are allowed.';
        header('Location: dashboard.php');
        exit();
    }

    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate a clean, unique name to prevent any collisions or path traversal
    $clean_student_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $student_id);
    $clean_dept_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $department_id);
    $new_filename = 'clearance_' . $clean_student_id . '_' . $clean_dept_id . '_' . time() . '.' . $file_ext;
    
    $dest_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file_tmp, $dest_path)) {
        try {
            $pdo->beginTransaction();

            // Update item details: reset status to pending (so it counts as Reviewing since document is attached)
            // Reset rejection reason when a new file is uploaded
            $update_stmt = $pdo->prepare("
                UPDATE clearance_items 
                SET status = 'pending', document_path = ?, uploaded_at = CURRENT_TIMESTAMP, rejection_reason = NULL 
                WHERE item_id = ?
            ");
            $update_stmt->execute([$new_filename, $item['item_id']]);

            // Log upload in audit logs
            log_audit($student_id, 'RESUBMIT_ITEM', 'clearance_items', $item['item_id']);

            $pdo->commit();
            $_SESSION['success_message'] = 'Document for ' . htmlspecialchars($item['department_name']) . ' uploaded successfully for review.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error_message'] = 'Database update failed: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Failed to save the uploaded file on the server.';
    }
}

header('Location: dashboard.php');
exit();
