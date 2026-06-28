<?php
/**
 * Officer Action Handler (Approve / Reject)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';

// Enforce officer auth
check_auth('officer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_id        = trim($_POST['log_id'] ?? '');
    $action        = trim($_POST['action'] ?? '');
    $remark        = trim($_POST['remark'] ?? '');
    $officer_id    = $_SESSION['user_id'];
    $department_id = $_SESSION['department_id'];

    // --- Validate inputs & business rules ---
    $errors = validate_officer_action($log_id, $action, $remark, $department_id);
    if (!empty($errors)) {
        $_SESSION['error_message'] = $errors[0];
        header('Location: dashboard.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Verify the log exists, is pending, and matches the officer's department
        $log_stmt = $pdo->prepare("
            SELECT cl.*, d.department_name, cr.student_id, cr.request_id, u.full_name, u.matric_no as username, u.email as student_email
            FROM clearance_items cl
            JOIN departments d ON cl.department_id = d.department_id
            JOIN clearance_requests cr ON cl.request_id = cr.request_id
            JOIN users u ON cr.student_id = u.user_id
            WHERE cl.item_id = ? AND cl.department_id = ? AND cl.status = 'pending'
        ");
        $log_stmt->execute([$log_id, $department_id]);
        $log = $log_stmt->fetch();

        if (!$log) {
            $pdo->rollBack();
            die("Request not found or already processed.");
        }

        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // 2. Update clearance_items
        $update_log = $pdo->prepare("
            UPDATE clearance_items 
            SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
            WHERE item_id = ?
        ");
        $update_log->execute([$new_status, $remark ?: null, $officer_id, $log_id]);

        // 3. Log audit action
        $audit_action = ($action === 'approve') ? 'APPROVE_ITEM' : 'REJECT_ITEM';
        log_audit($officer_id, $audit_action, 'clearance_items', $log_id);

        // 4. If approved, check if all other department logs for this request are approved
        if ($new_status === 'approved') {
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM clearance_items 
                WHERE request_id = ? AND status != 'approved'
            ");
            $check_stmt->execute([$log['request_id']]);
            $remaining = $check_stmt->fetchColumn();

            if ($remaining == 0) {
                // Auto-approve the overall request
                $update_req = $pdo->prepare("
                    UPDATE clearance_requests 
                    SET status = 'approved', updated_at = CURRENT_TIMESTAMP 
                    WHERE request_id = ?
                ");
                $update_req->execute([$log['request_id']]);
            }
        }

        $pdo->commit();

        // 5. Send Email Notification (PHPMailer)
        // Construct student email from matric number (username) or email if set
        $student_email = $log['student_email'] ?? $log['username'];
        if (empty($student_email)) {
            $student_email = $log['username'] . '@student.calebuniversity.edu.ng';
        } elseif (strpos($student_email, '@') === false) {
            $student_email .= '@student.calebuniversity.edu.ng';
        }

        // Clean matric no string for email representation
        $student_email = str_replace('/', '-', $student_email);

        $subject = "Clearance Update: " . ($action === 'approve' ? 'Approved' : 'Action Required') . " - " . $log['department_name'];
        
        $status_label = ($action === 'approve') ? 'Approved' : 'Rejected';
        $status_color = ($action === 'approve') ? '#137333' : '#C5221F';
        $status_bg = ($action === 'approve') ? '#E6F4EA' : '#FCE8E6';
        
        $body_html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #E2E8F0; border-radius: 8px;'>
            <h2 style='color: #475569; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;'>Clearance Update Notification</h2>
            <p>Dear <strong>" . htmlspecialchars($log['full_name']) . "</strong>,</p>
            <p>Your graduation clearance request has been reviewed by the <strong>" . htmlspecialchars($log['department_name']) . "</strong> department.</p>
            
            <div style='background-color: {$status_bg}; color: {$status_color}; padding: 12px 16px; border-radius: 6px; font-weight: bold; font-size: 1.1em; display: inline-block; margin: 15px 0;'>
                Status: {$status_label}
            </div>
            
            " . (!empty($remark) ? "<div style='margin-top: 15px; padding: 12px; background-color: #F8FAFC; border-left: 4px solid #00A859;'>
                <strong>Officer Remark:</strong><br>
                " . nl2br(htmlspecialchars($remark)) . "
            </div>" : "") . "
            
            <p style='margin-top: 25px; font-size: 0.9em; color: #64748B;'>
                Please log in to the Student Clearance Portal to view your complete status.
            </p>
        </div>
        ";

        send_clearance_email($log['student_id'], $student_email, $subject, $body_html);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error processing action: " . $e->getMessage());
    }
}

header('Location: dashboard.php');
exit();
