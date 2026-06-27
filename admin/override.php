<?php
/**
 * Admin Clearance Status Override Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce admin auth
check_auth('admin');

$request_id = $_GET['request_id'] ?? '';

if (empty($request_id)) {
    header('Location: dashboard.php');
    exit();
}

// Fetch request and student details
$req_stmt = $pdo->prepare("
    SELECT cr.*, u.full_name, u.matric_no 
    FROM clearance_requests cr
    JOIN users u ON cr.student_id = u.user_id
    WHERE cr.request_id = ?
");
$req_stmt->execute([$request_id]);
$request = $req_stmt->fetch();

if (!$request) {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Process manual approve all POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_all'])) {
    try {
        $pdo->beginTransaction();

        // 1. Update all clearance items to approved
        $stmt = $pdo->prepare("
            UPDATE clearance_items 
            SET status = 'approved', rejection_reason = NULL, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
            WHERE request_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $request_id]);

        // 2. Log ADMIN_OVERRIDE in audit log for this request
        log_audit($_SESSION['user_id'], 'ADMIN_OVERRIDE', 'clearance_requests', $request_id);

        // 3. Update overall request status to approved
        $update_req = $pdo->prepare("
            UPDATE clearance_requests 
            SET status = 'approved', updated_at = CURRENT_TIMESTAMP 
            WHERE request_id = ?
        ");
        $update_req->execute([$request_id]);

        $pdo->commit();
        $success_message = 'All clearance departments approved successfully.';
        
        // Reload request details
        $req_stmt->execute([$request_id]);
        $request = $req_stmt->fetch();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = 'Failed to approve all: ' . $e->getMessage();
    }
}

// Process manual override POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_override'])) {
    $item_id = $_POST['item_id'] ?? '';
    $new_status = $_POST['status'] ?? '';
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($item_id) || !in_array($new_status, ['pending', 'approved', 'rejected'])) {
        $error_message = 'Invalid parameters submitted.';
    } else {
        try {
            $pdo->beginTransaction();

            // Update specific clearance item
            $stmt = $pdo->prepare("
                UPDATE clearance_items 
                SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
                WHERE item_id = ? AND request_id = ?
            ");
            $stmt->execute([
                $new_status, 
                ($new_status === 'rejected') ? $rejection_reason : null, 
                $_SESSION['user_id'], 
                $item_id, 
                $request_id
            ]);

            // Log ADMIN_OVERRIDE in audit log
            log_audit($_SESSION['user_id'], 'ADMIN_OVERRIDE', 'clearance_items', $item_id);

            // Re-evaluate if all items are approved now
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM clearance_items 
                WHERE request_id = ? AND status != 'approved'
            ");
            $check_stmt->execute([$request_id]);
            $remaining = $check_stmt->fetchColumn();

            // Update overall request status
            $req_status = ($remaining == 0) ? 'approved' : 'in_progress';
            $update_req = $pdo->prepare("
                UPDATE clearance_requests 
                SET status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE request_id = ?
            ");
            $update_req->execute([$req_status, $request_id]);

            $pdo->commit();
            $success_message = 'Clearance item override applied successfully.';
            
            // Reload request details
            $req_stmt->execute([$request_id]);
            $request = $req_stmt->fetch();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Failed to apply override: ' . $e->getMessage();
        }
    }
}

// Fetch all clearance items for this request
$items_stmt = $pdo->prepare("
    SELECT ci.*, d.department_name 
    FROM clearance_items ci
    JOIN departments d ON ci.department_id = d.department_id
    WHERE ci.request_id = ?
    ORDER BY d.department_name ASC
");
$items_stmt->execute([$request_id]);
$clearance_items = $items_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Override - Clearance System</title>
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
            --text-color: #1E293B;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
            
            --approved-bg: #E6F4EA;
            --approved-text: #137333;
            --pending-bg: #FEF7E0;
            --pending-text: #B06000;
            --rejected-bg: #FCE8E6;
            --rejected-text: #C5221F;
            
            --shadow-subtle: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding-bottom: 60px;
        }

        .navbar-custom {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 24px;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-color);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            color: var(--primary-accent);
        }

        .dashboard-container {
            max-width: 900px;
            margin: 40px auto 0 auto;
            padding: 0 20px;
        }

        .card-custom {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-subtle);
            padding: 28px;
            margin-bottom: 24px;
        }

        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            padding: 14px 16px;
        }

        .table-custom td {
            font-size: 0.875rem;
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 50px;
        }

        .status-approved {
            background-color: var(--approved-bg);
            color: var(--approved-text);
        }

        .status-pending {
            background-color: var(--pending-bg);
            color: var(--pending-text);
        }

        .status-rejected {
            background-color: var(--rejected-bg);
            color: var(--rejected-text);
        }

        .btn-outline-custom {
            border: 1px solid var(--border-color);
            background-color: transparent;
            color: var(--text-color);
            font-weight: 500;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .btn-outline-custom:hover {
            background-color: #F1F5F9;
        }

        .btn-accent {
            background-color: var(--primary-accent);
            border: none;
            color: #FFFFFF;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-accent:hover {
            background-color: #008F4C;
            color: #FFFFFF;
        }

        .alert-custom {
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.875rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
        }

        .alert-success-custom {
            background-color: var(--approved-bg);
            color: var(--approved-text);
            border-color: rgba(19, 115, 51, 0.1);
        }

        .alert-error-custom {
            background-color: var(--rejected-bg);
            color: var(--rejected-text);
            border-color: rgba(225, 29, 72, 0.1);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="/caleb_banner.jpg" alt="Caleb University Logo" style="height: 35px; object-fit: contain; margin-right: 8px;">
            <span style="font-size: 1.1rem; border-left: 1px solid var(--border-color); padding-left: 12px; margin-left: 4px; font-weight: 600; color: #475569;">Clearance Portal - Admin</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="dashboard-container">

    <!-- Alert Notifications -->
    <?php if (!empty($success_message)): ?>
        <div class="alert-custom alert-success-custom">
            <i class="fa-solid fa-circle-check"></i>
            <div><?= htmlspecialchars($success_message) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert-custom alert-error-custom">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= htmlspecialchars($error_message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Student Info Card -->
    <div class="card-custom">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 style="font-weight: 700; letter-spacing: -0.5px; margin: 0;">Student Clearance Overview</h4>
            <?php if ($request['status'] !== 'approved'): ?>
                <form action="" method="POST" onsubmit="return confirm('Are you sure you want to approve all clearance departments for this student?');" class="d-inline">
                    <input type="hidden" name="approve_all" value="1">
                    <button type="submit" class="btn btn-accent btn-sm">
                        <i class="fa-solid fa-check-double me-1"></i> Approve All Departments
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="row pt-2 border-top">
            <div class="col-md-4 mb-2 mb-md-0">
                <span class="text-muted" style="font-size:0.8125rem;">Full Name</span>
                <div style="font-weight:600;"><?= htmlspecialchars($request['full_name']) ?></div>
            </div>
            <div class="col-md-4 mb-2 mb-md-0">
                <span class="text-muted" style="font-size:0.8125rem;">Matriculation Number</span>
                <div style="font-weight:600;"><?= htmlspecialchars($request['matric_no']) ?></div>
            </div>
            <div class="col-md-4">
                <span class="text-muted" style="font-size:0.8125rem;">Overall Status</span>
                <div>
                    <?php if ($request['status'] === 'approved'): ?>
                        <span class="status-badge status-approved">Approved (Cleared)</span>
                    <?php else: ?>
                        <span class="status-badge status-pending">In Progress</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Override Form Card -->
    <div class="card-custom p-0 overflow-hidden">
        <div class="px-4 py-3 border-bottom">
            <h5 class="mb-0" style="font-weight:700;">Department Clearance Items</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Current Status</th>
                        <th>Rejection Remark</th>
                        <th>Attached Document</th>
                        <th class="text-end">Override Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clearance_items as $item): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($item['department_name']) ?></td>
                            <td>
                                <?php if ($item['status'] === 'approved'): ?>
                                    <span class="status-badge status-approved">Approved</span>
                                <?php elseif ($item['status'] === 'rejected'): ?>
                                    <span class="status-badge status-rejected">Rejected</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:0.8125rem;" class="text-muted">
                                    <?= htmlspecialchars(str_replace('RESUBMITTED: ', 'Explanation: ', $item['rejection_reason'] ?? 'None')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($item['document_path'])): ?>
                                    <a href="/uploads/<?= htmlspecialchars($item['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:0.75rem; font-weight:600; padding: 2px 8px;">
                                        <i class="fa-solid fa-eye me-1"></i> View Doc
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.8rem;">No doc</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#overrideModal"
                                        data-item-id="<?= $item['item_id'] ?>"
                                        data-dept-name="<?= htmlspecialchars($item['department_name']) ?>"
                                        data-status="<?= $item['status'] ?>"
                                        data-reason="<?= htmlspecialchars($item['rejection_reason'] ?? '') ?>">
                                    <i class="fa-solid fa-sliders me-1"></i> Override
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1" aria-labelledby="overrideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="overrideModalLabel" style="font-weight: 700;">Manual Status Override</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="modal-item-id">
                    <input type="hidden" name="apply_override" value="1">
                    
                    <p>Modify the clearance status for department: <strong id="modal-dept-name"></strong>.</p>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label" style="font-size:0.8125rem; font-weight:600;">Override Status</label>
                        <select class="form-select" id="modal-status" name="status" onchange="toggleReasonField(this.value)" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 d-none" id="modal-reason-group">
                        <label for="rejection_reason" class="form-label" style="font-size:0.8125rem; font-weight:600;">Rejection Reason (Required if Rejected)</label>
                        <textarea class="form-control" id="modal-rejection-reason" name="rejection_reason" rows="3" placeholder="Explain the reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent">Apply Override</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const overrideModal = document.getElementById('overrideModal');
    if (overrideModal) {
        overrideModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const itemId = button.getAttribute('data-item-id');
            const deptName = button.getAttribute('data-dept-name');
            const status = button.getAttribute('data-status');
            const reason = button.getAttribute('data-reason');

            document.getElementById('modal-item-id').value = itemId;
            document.getElementById('modal-dept-name').textContent = deptName;
            document.getElementById('modal-status').value = status;
            document.getElementById('modal-rejection-reason').value = reason;

            toggleReasonField(status);
        });
    }

    function toggleReasonField(status) {
        const group = document.getElementById('modal-reason-group');
        const textarea = document.getElementById('modal-rejection-reason');
        if (status === 'rejected') {
            group.classList.remove('d-none');
            textarea.setAttribute('required', 'required');
        } else {
            group.classList.add('d-none');
            textarea.removeAttribute('required');
        }
    }
</script>
</body>
</html>
