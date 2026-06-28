<?php
/**
 * Student Resubmit Rejection Handler
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/validation.php';

// Enforce student role
check_auth('student');

$student_id = $_SESSION['user_id'];
$department_id = $_GET['department_id'] ?? '';

if (empty($department_id)) {
    header('Location: dashboard.php');
    exit();
}

// Fetch the rejected item details and verify ownership
$stmt = $pdo->prepare("
    SELECT ci.item_id, ci.rejection_reason, d.department_name, u.email as officer_email, u.user_id as officer_user_id
    FROM clearance_items ci
    JOIN departments d ON ci.department_id = d.department_id
    LEFT JOIN users u ON d.officer_id = u.user_id
    JOIN clearance_requests cr ON ci.request_id = cr.request_id
    WHERE ci.department_id = ? AND cr.student_id = ? AND ci.status = 'rejected'
");
$stmt->execute([$department_id, $student_id]);
$item = $stmt->fetch();

if (!$item) {
    // If no rejected item exists, redirect back to dashboard
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $explanation = trim($_POST['explanation'] ?? '');

    // --- Validate inputs & business rules ---
    $val_errors = validate_resubmission($department_id, $student_id, $explanation);
    if (!empty($val_errors)) {
        $error_message = $val_errors[0];
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update status back to pending, save explanation to rejection_reason so officer can read it
            // (or reset rejection_reason, but the prompt says: "show the student's resubmission explanation".
            // So we can save the explanation inside the rejection_reason column, or append it, or we can store it.
            // Let's store it as: "RESUBMITTED: [explanation]" in rejection_reason, so the officer sees it!)
            $remark_text = "RESUBMITTED: " . $explanation;
            
            $update_stmt = $pdo->prepare("
                UPDATE clearance_items 
                SET status = 'pending', rejection_reason = ? 
                WHERE item_id = ?
            ");
            $update_stmt->execute([$remark_text, $item['item_id']]);

            // 2. Log RESUBMIT_ITEM in audit logs
            log_audit($student_id, 'RESUBMIT_ITEM', 'clearance_items', $item['item_id']);

            $pdo->commit();

            // 3. Trigger email notification to the officer (PHPMailer)
            if (!empty($item['officer_email'])) {
                $student_name = $_SESSION['full_name'];
                $matric_no = $_SESSION['username'];
                
                $subject = "Clearance Resubmission — " . $student_name;
                $body_html = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #E2E8F0; border-radius: 8px;'>
                    <h2 style='color: #475569; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;'>Clearance Resubmission</h2>
                    <p>Dear Officer,</p>
                    <p><strong>" . htmlspecialchars($student_name) . "</strong> (Matric: " . htmlspecialchars($matric_no) . ") has resubmitted their clearance request to your department.</p>
                    
                    <div style='margin-top: 15px; padding: 12px; background-color: #F8FAFC; border-left: 4px solid #00A859;'>
                        <strong>Student Explanation:</strong><br>
                        " . nl2br(htmlspecialchars($explanation)) . "
                    </div>
                    
                    <p style='margin-top: 25px; font-size: 0.9em; color: #64748B;'>
                        Please log in to the Clearance Staff Portal to review this submission.
                    </p>
                </div>
                ";
                
                send_clearance_email($item['officer_user_id'], $item['officer_email'], $subject, $body_html);
            }

            $_SESSION['success_message'] = 'Clearance details resubmitted successfully.';
            header('Location: dashboard.php');
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Failed to resubmit request: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resubmit Clearance - <?= htmlspecialchars($item['department_name']) ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #F8FAFC;
            --card-bg: #FFFFFF;
            --primary-accent: #00A859;
            --primary-hover: #008F4C;
            --text-color: #1E293B;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
            --shadow-subtle: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .resubmit-container {
            width: 100%;
            max-width: 500px;
        }

        .card-custom {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-subtle);
            padding: 32px;
        }

        .rejection-box {
            background-color: #FFF5F5;
            color: #C53030;
            border-left: 4px solid #FC8181;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }

        .form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .btn-accent {
            background-color: var(--primary-accent);
            border: none;
            color: #FFFFFF;
            font-weight: 600;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.9375rem;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-accent:hover {
            background-color: var(--primary-hover);
        }
    </style>
</head>
<body>

<div class="resubmit-container">
    <div class="card-custom">
        <a href="dashboard.php" class="text-decoration-none text-muted mb-4 d-inline-block" style="font-size:0.875rem;">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>

        <h3 class="mb-2" style="font-weight: 700; letter-spacing: -0.5px;">Resubmit Clearance</h3>
        <p class="text-muted mb-4" style="font-size:0.9rem;">Department: <strong><?= htmlspecialchars($item['department_name']) ?></strong></p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" style="font-size:0.875rem;"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="rejection-box">
            <strong>Original Rejection Reason:</strong><br>
            <?= htmlspecialchars(str_replace('RESUBMITTED: ', '', $item['rejection_reason'] ?? '')) ?>
        </div>

        <form action="" method="POST">
            <div class="mb-4">
                <label for="explanation" class="form-label">Resolution Explanation</label>
                <textarea class="form-control" 
                          id="explanation" 
                          name="explanation" 
                          rows="5" 
                          placeholder="Describe what outstanding issues, bills, or items you have resolved to clear this rejection..." 
                          required></textarea>
            </div>

            <button type="submit" class="btn btn-accent">
                Submit Resubmission <i class="fa-solid fa-paper-plane ms-1"></i>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
