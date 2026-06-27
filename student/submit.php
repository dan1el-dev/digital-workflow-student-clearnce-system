<?php
/**
 * Student clearance submission handler
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce student auth
check_auth('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['user_id'];
    $level = trim($_POST['level'] ?? '400L');

    try {
        $pdo->beginTransaction();

        // 1. Verify no active request exists
        $check_stmt = $pdo->prepare("SELECT request_id FROM clearance_requests WHERE student_id = ? AND status != 'rejected'");
        $check_stmt->execute([$student_id]);
        if ($check_stmt->fetch() !== false) {
            $pdo->rollBack();
            header('Location: dashboard.php');
            exit();
        }

        // 2. Insert new clearance request
        $request_id = generate_uuid();
        $insert_req = $pdo->prepare("
            INSERT INTO clearance_requests (request_id, student_id, level, status) 
            VALUES (?, ?, ?, 'pending')
        ");
        $insert_req->execute([$request_id, $student_id, $level]);

        // 3. Fetch all departments
        $dept_query = $pdo->query("SELECT department_id FROM departments");
        $departments = $dept_query->fetchAll(PDO::FETCH_COLUMN);

        // 4. Create pending items for each department
        $insert_item = $pdo->prepare("
            INSERT INTO clearance_items (item_id, request_id, department_id, status, rejection_reason, reviewed_by, reviewed_at) 
            VALUES (?, ?, ?, 'pending', NULL, NULL, NULL)
        ");
        
        foreach ($departments as $dept_id) {
            $item_id = generate_uuid();
            $insert_item->execute([$item_id, $request_id, $dept_id]);
        }

        // 5. Log audit action
        log_audit($student_id, 'SUBMIT_REQUEST', 'clearance_requests', $request_id);

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error initiating clearance: " . $e->getMessage());
    }
}

header('Location: dashboard.php');
exit();
