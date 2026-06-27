<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce admin auth
check_auth('admin');

// Fetch metrics
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_requests = $pdo->query("SELECT COUNT(*) FROM clearance_requests")->fetchColumn();
$cleared_requests = $pdo->query("SELECT COUNT(*) FROM clearance_requests WHERE status = 'approved'")->fetchColumn();
$pending_requests = $pdo->query("SELECT COUNT(*) FROM clearance_requests WHERE status != 'approved'")->fetchColumn();

// Fetch request logs
$stmt = $pdo->query("
    SELECT cr.request_id as req_id, cr.level, cr.status, cr.created_at, u.full_name, u.matric_no, u.academic_dept
    FROM clearance_requests cr
    JOIN users u ON cr.student_id = u.user_id
    ORDER BY cr.created_at DESC
    LIMIT 20
");
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Clearance System</title>
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

        .stat-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background-color: var(--card-bg);
            padding: 24px;
            box-shadow: var(--shadow-subtle);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background-color: rgba(99, 102, 241, 0.08);
            color: var(--primary-accent);
        }

        .stat-val {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .stat-lbl {
            font-size: 0.8125rem;
            color: var(--text-muted);
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

        .status-cleared {
            background-color: var(--approved-bg);
            color: var(--approved-text);
        }

        .status-pending {
            background-color: var(--pending-bg);
            color: var(--pending-text);
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
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="/caleb_banner.jpg" alt="Caleb University Logo" style="height: 35px; object-fit: contain; margin-right: 8px;">
            <span style="font-size: 1.1rem; border-left: 1px solid var(--border-color); padding-left: 12px; margin-left: 4px; font-weight: 600; color: #475569;">Clearance Portal - Admin</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="users.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-users me-1"></i> Manage Users
            </a>
            <a href="audit.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-file-shield me-1"></i> Audit Logs
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

    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight:700; letter-spacing:-0.5px; margin:0;">Admin Control Center</h2>
            <p class="text-muted m-0">Oversee clearance requests and user configurations.</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div>
                    <h3 class="stat-val"><?= $total_students ?></h3>
                    <p class="stat-lbl">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:rgba(99, 102, 241, 0.08); color:var(--primary-accent);"><i class="fa-regular fa-clipboard"></i></div>
                <div>
                    <h3 class="stat-val"><?= $total_requests ?></h3>
                    <p class="stat-lbl">Requests Initiated</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:rgba(19, 115, 51, 0.08); color:#137333;"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <h3 class="stat-val"><?= $cleared_requests ?></h3>
                    <p class="stat-lbl">Cleared</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background-color:rgba(176, 96, 0, 0.08); color:#B06000;"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
                <div>
                    <h3 class="stat-val"><?= $pending_requests ?></h3>
                    <p class="stat-lbl">Pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card-custom p-0 overflow-hidden">
        <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0" style="font-weight: 700; letter-spacing: -0.3px;">Clearance Request Logs</h5>
        </div>
        
        <?php if (count($requests) === 0): ?>
            <div class="text-center py-5">
                <p class="text-muted mb-0">No student clearance requests initiated yet.</p>
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
                            <th>Date Initiated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
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
                                <td><?= htmlspecialchars($req['level'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($req['status'] === 'approved'): ?>
                                        <span class="status-badge status-cleared">Cleared</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($req['created_at']) ? date('M d, Y h:i A', strtotime($req['created_at'])) : 'N/A' ?></td>
                                <td class="text-end">
                                    <a href="override.php?request_id=<?= $req['req_id'] ?>" class="btn btn-sm btn-outline-custom">
                                        <i class="fa-solid fa-sliders me-1"></i> View / Override
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
