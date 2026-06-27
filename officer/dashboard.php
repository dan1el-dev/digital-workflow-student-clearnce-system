<?php
/**
 * Officer Dashboard
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce officer auth
check_auth('officer');

$officer_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];

// Get department name
$dept_stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
$dept_stmt->execute([$department_id]);
$department_name = $dept_stmt->fetchColumn() ?: 'Your Department';

$search_filter = trim($_GET['search'] ?? '');

// Get pending clearance items
$pending_query = "
    SELECT cl.item_id as log_id, cr.created_at as timestamp, u.full_name, u.matric_no, u.academic_dept, cr.level, cl.document_path
    FROM clearance_items cl
    JOIN clearance_requests cr ON cl.request_id = cr.request_id
    JOIN users u ON cr.student_id = u.user_id
    WHERE cl.department_id = ? AND cl.status = 'pending'
";
$pending_params = [$department_id];

if (!empty($search_filter)) {
    $pending_query .= " AND (u.full_name LIKE ? OR u.matric_no LIKE ?)";
    $pending_params[] = "%$search_filter%";
    $pending_params[] = "%$search_filter%";
}

$pending_query .= " ORDER BY cr.created_at ASC ";
$pending_stmt = $pdo->prepare($pending_query);
$pending_stmt->execute($pending_params);
$pending_requests = $pending_stmt->fetchAll();

// Get reviewed clearance items
$reviewed_query = "
    SELECT cl.item_id as log_id, cl.status, cl.rejection_reason as remark, cl.reviewed_at, u.full_name, u.matric_no, u.academic_dept, cr.level, cl.document_path
    FROM clearance_items cl
    JOIN clearance_requests cr ON cl.request_id = cr.request_id
    JOIN users u ON cr.student_id = u.user_id
    WHERE cl.department_id = ? AND cl.status != 'pending'
";
$reviewed_params = [$department_id];

if (!empty($search_filter)) {
    $reviewed_query .= " AND (u.full_name LIKE ? OR u.matric_no LIKE ?)";
    $reviewed_params[] = "%$search_filter%";
    $reviewed_params[] = "%$search_filter%";
}

$reviewed_query .= " ORDER BY cl.reviewed_at DESC ";
if (empty($search_filter)) {
    $reviewed_query .= " LIMIT 10 ";
}

$reviewed_stmt = $pdo->prepare($reviewed_query);
$reviewed_stmt->execute($reviewed_params);
$recent_reviews = $reviewed_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - Clearance System</title>
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
            
            /* Status colors */
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
            max-width: 1100px;
            margin: 40px auto 0 auto;
            padding: 0 20px;
        }

        .card-custom {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-subtle);
            padding: 28px;
            margin-bottom: 32px;
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .welcome-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
        }

        .welcome-title p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 0;
        }

        .table-custom {
            margin: 0;
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

        .btn-action-approve {
            background-color: var(--approved-bg);
            color: var(--approved-text);
            border: 1px solid rgba(19, 115, 51, 0.15);
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
        }

        .btn-action-approve:hover {
            background-color: #D2EBD9;
        }

        .btn-action-reject {
            background-color: var(--rejected-bg);
            color: var(--rejected-text);
            border: 1px solid rgba(197, 34, 31, 0.15);
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
        }

        .btn-action-reject:hover {
            background-color: #F8D7DA;
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

        .nav-tabs-custom {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.9375rem;
            padding: 12px 20px;
            position: relative;
            background: transparent;
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary-accent);
            font-weight: 600;
        }

        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--primary-accent);
        }

        /* Modal styling */
        .modal-content {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 16px 24px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="/caleb_banner.jpg" alt="Caleb University Logo" style="height: 35px; object-fit: contain; margin-right: 8px;">
            <span style="font-size: 1.1rem; border-left: 1px solid var(--border-color); padding-left: 12px; margin-left: 4px; font-weight: 600; color: #475569;">Clearance Portal - Staff</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="d-none d-sm-block text-end">
                <div style="font-size: 0.875rem; font-weight: 600;"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($department_name) ?> Officer</div>
            </div>
            <a href="/profile.php" class="btn btn-outline-custom">
                <i class="fa-regular fa-user me-1"></i> Profile
            </a>
            <a href="/auth/logout.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Log Out
            </a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="dashboard-container">

    <!-- Welcome Header -->
    <div class="welcome-header">
        <div class="welcome-title">
            <h2><?= htmlspecialchars($department_name) ?> Queue</h2>
            <p>Review and act on pending graduation clearance requests.</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card-custom py-3 px-4 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-9 col-sm-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="fa-solid fa-magnifying-glass text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="search" name="search" placeholder="Search student name or matric number..." value="<?= htmlspecialchars($search_filter) ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-accent btn-sm w-100 py-2">
                    Search
                </button>
                <?php if (!empty($search_filter)): ?>
                    <a href="dashboard.php" class="btn btn-outline-custom btn-sm w-50 py-2 text-center text-decoration-none">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-panel" type="button" role="tab" aria-controls="pending-panel" aria-selected="true">
                Pending Requests (<?= count($pending_requests) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent-panel" type="button" role="tab" aria-controls="recent-panel" aria-selected="false">
                Recent Reviews
            </button>
        </li>
    </ul>

    <div class="tab-content" id="dashboardTabsContent">
        <!-- Pending Requests Panel -->
        <div class="tab-pane fade show active" id="pending-panel" role="tabpanel" aria-labelledby="pending-tab">
            <div class="card-custom p-0 overflow-hidden">
                <?php if (count($pending_requests) === 0): ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted" style="font-size: 2.5rem;">
                            <i class="fa-regular fa-clipboard"></i>
                        </div>
                        <h5>No pending requests</h5>
                        <p class="text-muted mb-0">All student clearance items for your department have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Matric Number</th>
                                    <th>Level</th>
                                    <th>Submission Date</th>
                                    <th>Attached Document</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr>
                                        <td style="font-weight: 500;">
                                            <?= htmlspecialchars($req['full_name']) ?>
                                            <?php if (!empty($req['academic_dept'])): ?>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-top: 2px;">
                                                    <?= htmlspecialchars($req['academic_dept']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($req['matric_no']) ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><?= htmlspecialchars($req['level']) ?></span></td>
                                        <td><?= !empty($req['timestamp']) ? date('M d, Y h:i A', strtotime($req['timestamp'])) : 'N/A' ?></td>
                                        <td>
                                            <?php if (!empty($req['document_path'])): ?>
                                                <a href="/uploads/<?= htmlspecialchars($req['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem; font-weight:600;">
                                                    <i class="fa-solid fa-eye me-1"></i> View Doc
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.8rem;">No doc uploaded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form action="action.php" method="POST" class="d-inline">
                                                <input type="hidden" name="log_id" value="<?= $req['log_id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-action-approve me-2">
                                                    <i class="fa-solid fa-check me-1"></i> Approve
                                                </button>
                                            </form>
                                            <button class="btn btn-action-reject" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal" 
                                                    data-log-id="<?= $req['log_id'] ?>"
                                                    data-student-name="<?= htmlspecialchars($req['full_name']) ?>">
                                                <i class="fa-solid fa-xmark me-1"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Reviews Panel -->
        <div class="tab-pane fade" id="recent-panel" role="tabpanel" aria-labelledby="recent-tab">
            <div class="card-custom p-0 overflow-hidden">
                <?php if (count($recent_reviews) === 0): ?>
                    <div class="text-center py-5">
                        <p class="text-muted mb-0">No reviews recorded in this department recently.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Matric Number</th>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Attached Document</th>
                                    <th>Date Reviewed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reviews as $rev): ?>
                                    <tr>
                                        <td style="font-weight: 500;">
                                            <?= htmlspecialchars($rev['full_name']) ?>
                                            <?php if (!empty($rev['academic_dept'])): ?>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-top: 2px;">
                                                    <?= htmlspecialchars($rev['academic_dept']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($rev['matric_no']) ?></td>
                                        <td><?= htmlspecialchars($rev['level']) ?></td>
                                        <td>
                                            <?php if ($rev['status'] === 'approved'): ?>
                                                <span class="status-badge status-approved">Approved</span>
                                            <?php else: ?>
                                                <span class="status-badge status-rejected">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 240px;" class="text-truncate" title="<?= htmlspecialchars($rev['remark'] ?? '') ?>">
                                            <?= htmlspecialchars(str_replace('RESUBMITTED: ', '', $rev['remark'] ?? '')) ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($rev['document_path'])): ?>
                                                <a href="/uploads/<?= htmlspecialchars($rev['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:0.75rem; font-weight:600;">
                                                    <i class="fa-solid fa-eye me-1"></i> View Doc
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.8rem;">No doc</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !empty($rev['reviewed_at']) ? date('M d, Y h:i A', strtotime($rev['reviewed_at'])) : 'N/A' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="action.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel" style="font-weight: 700; letter-spacing: -0.5px;">Reject Clearance Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="log_id" id="modal-log-id">
                    <input type="hidden" name="action" value="reject">
                    
                    <p style="font-size: 0.9375rem;">You are rejecting the clearance request for <strong id="modal-student-name"></strong>.</p>
                    
                    <div class="mb-3">
                        <label for="remark" class="form-label" style="font-size: 0.8125rem; font-weight: 600;">Rejection Remark (Required)</label>
                        <textarea class="form-control" id="remark" name="remark" rows="4" placeholder="Explain what obligations or outstanding tasks the student needs to fulfill..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent bg-danger">Submit Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Dynamically set modal values when open
    const rejectModal = document.getElementById('rejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const logId = button.getAttribute('data-log-id');
            const studentName = button.getAttribute('data-student-name');
            
            document.getElementById('modal-log-id').value = logId;
            document.getElementById('modal-student-name').textContent = studentName;
        });
    }
</script>
</body>
</html>
