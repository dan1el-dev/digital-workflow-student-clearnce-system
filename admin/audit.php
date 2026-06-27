<?php
/**
 * Admin Audit Log View Page
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce admin auth
check_auth('admin');

// Pagination setup
$limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter setup
$action_filter = $_GET['action'] ?? '';
$search_filter = $_GET['search'] ?? '';

$where_clauses = [];
$params = [];

if (!empty($action_filter)) {
    $where_clauses[] = "al.action = ?";
    $params[] = $action_filter;
}

if (!empty($search_filter)) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.matric_no LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) 
    FROM audit_logs al
    JOIN users u ON al.user_id = u.user_id
    $where_sql
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch paginated records
$data_query = "
    SELECT al.*, u.full_name, u.role, u.matric_no, u.email
    FROM audit_logs al
    JOIN users u ON al.user_id = u.user_id
    $where_sql
    ORDER BY al.timestamp DESC
    LIMIT $limit OFFSET $offset
";
$data_stmt = $pdo->prepare($data_query);
$data_stmt->execute($params);
$logs = $data_stmt->fetchAll();

// Available action types for filtering
$actions = ['SUBMIT_REQUEST', 'APPROVE_ITEM', 'REJECT_ITEM', 'RESUBMIT_ITEM', 'ADMIN_OVERRIDE', 'GENERATE_CERTIFICATE'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Log - Clearance Portal</title>
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
            padding: 24px;
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
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
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

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9375rem;
            padding: 8px 12px;
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
                <i class="fa-solid fa-gauge me-1"></i> Dashboard
            </a>
            <a href="users.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-users me-1"></i> Manage Users
            </a>
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

    <!-- Title and Filters -->
    <div class="card-custom">
        <h3 class="mb-3" style="font-weight: 700; letter-spacing: -0.5px;">System Audit Logs</h3>
        
        <!-- Search and Filter Form -->
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label text-muted" style="font-size:0.75rem; font-weight:600;">Search User Name / ID</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="e.g. Adewale or 19/10204" value="<?= htmlspecialchars($search_filter) ?>">
            </div>
            
            <div class="col-md-4">
                <label for="action" class="form-label text-muted" style="font-size:0.75rem; font-weight:600;">Filter by Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?= $act ?>" <?= $action_filter === $act ? 'selected' : '' ?>><?= $act ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100" style="background-color: var(--primary-accent); border: none; border-radius: 8px; font-weight: 600; padding: 10px;">
                    Apply Filters <i class="fa-solid fa-filter ms-1"></i>
                </button>
                <a href="audit.php" class="btn btn-outline-custom w-100 text-center" style="padding: 10px;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="card-custom p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-custom table-hover">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Affected Record ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No audit logs match the query filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size:0.8125rem; font-weight: 500; color:var(--text-muted);">
                                    <?= !empty($log['timestamp']) ? date('M d, Y h:i A', strtotime($log['timestamp'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($log['full_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($log['matric_no'] ?? $log['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 text-uppercase" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($log['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-weight: 600; font-size: 0.8125rem; color: var(--primary-accent);">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </code>
                                </td>
                                <td style="font-size: 0.75rem;" class="text-muted">
                                    <?= htmlspecialchars($log['affected_record_id'] ?? 'N/A') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Links -->
        <?php if ($total_pages > 1): ?>
            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center">
                <span style="font-size: 0.8125rem; color: var(--text-muted);">
                    Showing page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong> (<?= $total_records ?> logs total)
                </span>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm m-0">
                        <!-- Previous Page -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_filter) ?>&action=<?= urlencode($action_filter) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_filter) ?>&action=<?= urlencode($action_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_filter) ?>&action=<?= urlencode($action_filter) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
