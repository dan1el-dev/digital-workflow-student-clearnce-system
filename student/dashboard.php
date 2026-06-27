<?php
/**
 * Student Dashboard
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce student role
check_auth('student');

$student_id = $_SESSION['user_id'];

// Get active request
$stmt = $pdo->prepare("SELECT * FROM clearance_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$student_id]);
$request = $stmt->fetch();

$has_request = ($request !== false);
$logs = [];
$approved_count = 0;
$reviewing_count = 0;
$rejected_count = 0;
$pending_count = 0;
$total_departments = 0;

if ($has_request) {
    // Get total departments dynamically
    $total_departments = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

    // Get all clearance items for this request with full details
    $log_stmt = $pdo->prepare("
        SELECT cl.*, cl.rejection_reason as remark, d.department_name, cl.document_path, cl.uploaded_at, cl.reviewed_at
        FROM clearance_items cl
        JOIN departments d ON cl.department_id = d.department_id
        WHERE cl.request_id = ?
        ORDER BY d.department_name ASC
    ");
    $log_stmt->execute([$request['request_id']]);
    $logs = $log_stmt->fetchAll();

    // Count states
    foreach ($logs as $log) {
        if ($log['status'] === 'approved') {
            $approved_count++;
        } elseif ($log['status'] === 'rejected') {
            $rejected_count++;
        } elseif ($log['status'] === 'pending') {
            if (!empty($log['document_path'])) {
                $reviewing_count++;
            } else {
                $pending_count++;
            }
        }
    }

    // Auto-update request status to approved if all approved and request is still pending/in_progress
    if ($approved_count === $total_departments && $request['status'] !== 'approved') {
        $update_stmt = $pdo->prepare("UPDATE clearance_requests SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE request_id = ?");
        $update_stmt->execute([$request['request_id']]);
        $request['status'] = 'approved';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Caleb University Clearance</title>
    <meta name="description" content="Track and manage your graduation clearance status across all university departments.">
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
            
            /* Status colors */
            --approved-bg: #E6F4EA;
            --approved-text: #137333;
            --approved-border: rgba(19, 115, 51, 0.2);
            --pending-bg: #FEF7E0;
            --pending-text: #B06000;
            --pending-border: rgba(176, 96, 0, 0.2);
            --rejected-bg: #FCE8E6;
            --rejected-text: #C5221F;
            --rejected-border: rgba(197, 34, 31, 0.2);
            --reviewing-bg: #EFF6FF;
            --reviewing-text: #1D4ED8;
            --reviewing-border: rgba(29, 78, 216, 0.2);
            
            --shadow-subtle: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding-bottom: 60px;
        }

        /* ── Navbar ── */
        .navbar-custom {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-color);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00A859 0%, #008F4C 100%);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        /* ── Layout ── */
        .dashboard-container {
            max-width: 1020px;
            margin: 40px auto 0 auto;
            padding: 0 20px;
        }

        .card-custom {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-subtle);
            padding: 28px;
            margin-bottom: 24px;
        }

        /* ── Welcome Header ── */
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
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

        /* ── Status Banner ── */
        .status-banner {
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .status-banner-cleared {
            background: linear-gradient(135deg, #E6F4EA 0%, #D2EBD9 100%);
            color: var(--approved-text);
            border: 1px solid var(--approved-border);
        }

        .banner-text {
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .banner-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: rgba(19, 115, 51, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* ── Progress Card ── */
        .progress-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-subtle);
            padding: 24px 28px;
            margin-bottom: 24px;
        }

        .stat-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 50px;
            font-size: 0.8125rem;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .stat-chip-approved { background-color: var(--approved-bg); color: var(--approved-text); border-color: var(--approved-border); }
        .stat-chip-reviewing { background-color: var(--reviewing-bg); color: var(--reviewing-text); border-color: var(--reviewing-border); }
        .stat-chip-rejected { background-color: var(--rejected-bg); color: var(--rejected-text); border-color: var(--rejected-border); }
        .stat-chip-pending { background-color: #F1F5F9; color: #475569; border-color: #CBD5E1; }

        .stat-chip-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Segmented Bar ── */
        .seg-bar {
            display: flex;
            gap: 3px;
            width: 100%;
            height: 10px;
            border-radius: 6px;
            overflow: hidden;
        }

        .seg-bar-item {
            flex-grow: 1;
            border-radius: 3px;
            transition: opacity 0.2s;
            cursor: help;
        }

        .seg-bar-item:hover { opacity: 0.75; }

        /* ── Department Cards ── */
        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .dept-card {
            background-color: var(--card-bg);
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 20px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .dept-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--border-color);
            transition: background 0.2s ease;
        }

        .dept-card.card-approved::before { background: var(--primary-accent); }
        .dept-card.card-reviewing::before { background: #3B82F6; }
        .dept-card.card-rejected::before { background: #EF4444; }
        .dept-card.card-not_started::before { background: #CBD5E1; }

        .dept-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: #CBD5E1;
        }

        .dept-card.card-approved:hover { border-color: rgba(0,168,89,0.3); }
        .dept-card.card-reviewing:hover { border-color: rgba(59,130,246,0.3); }
        .dept-card.card-rejected:hover { border-color: rgba(239,68,68,0.3); }

        .dept-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 14px;
            flex-shrink: 0;
        }

        .dept-icon-approved { background-color: var(--approved-bg); color: var(--approved-text); }
        .dept-icon-reviewing { background-color: var(--reviewing-bg); color: var(--reviewing-text); }
        .dept-icon-rejected { background-color: var(--rejected-bg); color: var(--rejected-text); }
        .dept-icon-not_started { background-color: #F1F5F9; color: #64748B; }

        .dept-name {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.725rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .pill-approved { background-color: var(--approved-bg); color: var(--approved-text); }
        .pill-reviewing { background-color: var(--reviewing-bg); color: var(--reviewing-text); }
        .pill-rejected { background-color: var(--rejected-bg); color: var(--rejected-text); }
        .pill-not_started { background-color: #F1F5F9; color: #64748B; }

        .dept-doc-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dept-rejection-preview {
            font-size: 0.78rem;
            color: var(--rejected-text);
            background-color: var(--rejected-bg);
            border-radius: 6px;
            padding: 8px 10px;
            margin-top: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .dept-action-hint {
            font-size: 0.78rem;
            color: var(--primary-accent);
            font-weight: 600;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ── Buttons ── */
        .btn-accent {
            background-color: var(--primary-accent);
            border: none;
            color: #FFFFFF;
            font-weight: 600;
            border-radius: 10px;
            padding: 11px 22px;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-accent:hover {
            background-color: var(--primary-hover);
            color: #FFFFFF;
            transform: translateY(-1px);
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
            color: var(--text-color);
        }

        /* ── Modal ── */
        .modal-content {
            border-radius: 18px;
            border: 1px solid var(--border-color);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .modal-header-custom {
            padding: 22px 26px 18px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-dept-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8125rem;
            font-weight: 600;
            margin-top: 6px;
        }

        .modal-body-custom { padding: 24px 26px; }
        .modal-footer-custom {
            padding: 16px 26px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-status-block {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .modal-status-block.block-approved { background-color: var(--approved-bg); border-color: var(--approved-border); color: var(--approved-text); }
        .modal-status-block.block-reviewing { background-color: var(--reviewing-bg); border-color: var(--reviewing-border); color: var(--reviewing-text); }
        .modal-status-block.block-rejected { background-color: var(--rejected-bg); border-color: var(--rejected-border); color: var(--rejected-text); }
        .modal-status-block.block-not_started { background-color: #F8FAFC; border-color: var(--border-color); color: var(--text-muted); }

        .modal-status-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .icon-approved { background-color: rgba(19,115,51,0.15); }
        .icon-reviewing { background-color: rgba(29,78,216,0.15); }
        .icon-rejected { background-color: rgba(197,34,31,0.15); }
        .icon-not_started { background-color: #E2E8F0; }

        .modal-status-title {
            font-weight: 700;
            font-size: 0.9375rem;
            margin-bottom: 2px;
        }

        .modal-status-subtitle {
            font-size: 0.8125rem;
            opacity: 0.85;
        }

        .feedback-box {
            background-color: #FFF5F5;
            border: 1px solid rgba(197,34,31,0.2);
            border-left: 4px solid #EF4444;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .feedback-label {
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--rejected-text);
            margin-bottom: 6px;
        }

        .feedback-text {
            font-size: 0.875rem;
            color: #7F1D1D;
            line-height: 1.55;
        }

        .doc-preview-block {
            background-color: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .doc-preview-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        .doc-preview-icon {
            width: 36px;
            height: 36px;
            background-color: #EFF6FF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3B82F6;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .doc-preview-name {
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-preview-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            margin-bottom: 4px;
        }

        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--primary-accent);
            background-color: rgba(0,168,89,0.03);
        }

        .upload-zone-icon {
            width: 48px;
            height: 48px;
            background-color: rgba(0,168,89,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary-accent);
            margin: 0 auto 12px;
        }

        .upload-zone input[type="file"] {
            display: none;
        }

        .upload-selected-file {
            display: none;
            align-items: center;
            gap: 8px;
            background-color: rgba(0,168,89,0.08);
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 12px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--primary-accent);
        }

        /* ── Animations ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dept-card {
            animation: fadeInUp 0.3s ease both;
        }

        .dept-card:nth-child(1) { animation-delay: 0.05s; }
        .dept-card:nth-child(2) { animation-delay: 0.10s; }
        .dept-card:nth-child(3) { animation-delay: 0.15s; }
        .dept-card:nth-child(4) { animation-delay: 0.20s; }
        .dept-card:nth-child(5) { animation-delay: 0.25s; }
        .dept-card:nth-child(6) { animation-delay: 0.30s; }
        .dept-card:nth-child(7) { animation-delay: 0.35s; }
        .dept-card:nth-child(8) { animation-delay: 0.40s; }

        /* Responsive */
        @media (max-width: 576px) {
            .dashboard-container { padding: 0 14px; }
            .card-custom { padding: 20px; }
            .stat-chips { gap: 8px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="/caleb_banner.jpg" alt="Caleb University Logo" style="height: 34px; object-fit: contain;">
            <span style="font-size: 1rem; border-left: 1px solid var(--border-color); padding-left: 12px; margin-left: 2px; font-weight: 600; color: #475569;">Clearance Portal</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
                <div class="d-none d-sm-block">
                    <div style="font-size: 0.875rem; font-weight: 600; line-height: 1.2;"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
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

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mt-4 mb-0" role="alert" style="border-radius: 10px;">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
            <?php unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-4 mb-0" role="alert" style="border-radius: 10px;">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
            <?php unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Welcome Header -->
    <div class="welcome-header mt-4">
        <div class="welcome-title">
            <h2>Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>!</h2>
            <p>Track and manage your university clearance status</p>
        </div>
    </div>

    <?php if (!$has_request): ?>
        <!-- No Active Request Screen -->
        <div class="card-custom text-center py-5">
            <div class="mb-4" style="font-size: 3.5rem; color: #CBD5E1;">
                <i class="fa-regular fa-folder-open"></i>
            </div>
            <h3 style="font-weight: 700; letter-spacing: -0.5px;">No Clearance Request Active</h3>
            <p class="text-muted mx-auto" style="max-width: 480px; margin-bottom: 28px; font-size: 0.9375rem;">
                You have not initiated your digital clearance workflow yet. Select your current level and click the button below to generate a request across all 8 clearance departments.
            </p>
            <form action="submit.php" method="POST">
                <div class="mb-4 d-inline-block text-start">
                    <label for="level" class="form-label" style="font-weight: 600; font-size: 0.8125rem;">Select Current Level</label>
                    <select class="form-select" id="level" name="level" style="width: 220px;" required>
                        <option value="100L">100 Level</option>
                        <option value="200L">200 Level</option>
                        <option value="300L">300 Level</option>
                        <option value="400L" selected>400 Level</option>
                        <option value="500L">500 Level</option>
                    </select>
                </div>
                <br>
                <button type="submit" class="btn btn-accent">
                    <i class="fa-solid fa-play"></i> Initiate Clearance Workflow
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- Cleared Banner -->
        <?php if ($request['status'] === 'approved'): ?>
            <div class="status-banner status-banner-cleared">
                <div class="banner-text">
                    <div class="banner-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <div style="font-size: 1rem; font-weight: 700;">Clearance Complete — Congratulations! 🎉</div>
                        <div style="font-size: 0.8125rem; font-weight: 400; margin-top: 2px; opacity: 0.85;">All 8 departments have approved your clearance. Your certificate is ready to download.</div>
                    </div>
                </div>
                <a href="certificate.php" target="_blank" class="btn btn-accent" style="background-color: #137333;">
                    <i class="fa-solid fa-print"></i> Print Clearance Form
                </a>
            </div>
        <?php endif; ?>

        <!-- Clearance Progress Card -->
        <?php 
        $completion_pct = $total_departments > 0 ? round(($approved_count / $total_departments) * 100) : 0;
        ?>
        <div class="progress-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h5 class="m-0" style="font-weight: 700; letter-spacing: -0.3px;">Clearance Progress</h5>
                    <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 2px;">Academic Year 2025/2026 &middot; Level <?= htmlspecialchars($request['level']) ?></div>
                </div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--primary-accent); letter-spacing: -1px;"><?= $completion_pct ?>%</div>
            </div>

            <div class="stat-chips">
                <div class="stat-chip stat-chip-approved">
                    <span class="stat-chip-dot" style="background:#00A859;"></span>
                    <?= $approved_count ?> Approved
                </div>
                <div class="stat-chip stat-chip-reviewing">
                    <span class="stat-chip-dot" style="background:#3B82F6;"></span>
                    <?= $reviewing_count ?> Under Review
                </div>
                <div class="stat-chip stat-chip-rejected">
                    <span class="stat-chip-dot" style="background:#EF4444;"></span>
                    <?= $rejected_count ?> Rejected
                </div>
                <div class="stat-chip stat-chip-pending">
                    <span class="stat-chip-dot" style="background:#94A3B8;"></span>
                    <?= $pending_count ?> Pending
                </div>
            </div>

            <!-- Segmented Progress Bar -->
            <div class="seg-bar">
                <?php foreach ($logs as $log): 
                    $seg_color = '#CBD5E1';
                    $seg_label = 'Pending';
                    if ($log['status'] === 'approved') {
                        $seg_color = '#00A859';
                        $seg_label = 'Approved';
                    } elseif ($log['status'] === 'rejected') {
                        $seg_color = '#EF4444';
                        $seg_label = 'Rejected';
                    } elseif ($log['status'] === 'pending' && !empty($log['document_path'])) {
                        $seg_color = '#3B82F6';
                        $seg_label = 'Under Review';
                    }
                ?>
                    <div class="seg-bar-item" 
                         style="background-color: <?= $seg_color ?>;" 
                         title="<?= htmlspecialchars($log['department_name']) ?>: <?= $seg_label ?>"
                         data-bs-toggle="tooltip"
                         data-bs-placement="top"></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.75rem;">Each segment = one department · hover to see status</div>
        </div>

        <!-- Department Cards Grid -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 style="font-weight: 700; letter-spacing: -0.3px; margin: 0;">Department Status</h5>
            <span style="font-size: 0.8125rem; color: var(--text-muted);">Tap any card to manage documents</span>
        </div>
        <div class="dept-grid mb-5">
            <?php foreach ($logs as $log): 
                $card_status = 'not_started';
                $status_label = 'Not Started';
                $dept_icon_class = 'dept-icon-not_started';
                $dept_icon = 'fa-circle-dot';
                $card_class = 'card-not_started';
                $pill_class = 'pill-not_started';

                if ($log['status'] === 'approved') {
                    $card_status = 'approved';
                    $status_label = 'Approved';
                    $dept_icon_class = 'dept-icon-approved';
                    $dept_icon = 'fa-circle-check';
                    $card_class = 'card-approved';
                    $pill_class = 'pill-approved';
                } elseif ($log['status'] === 'rejected') {
                    $card_status = 'rejected';
                    $status_label = 'Rejected';
                    $dept_icon_class = 'dept-icon-rejected';
                    $dept_icon = 'fa-circle-xmark';
                    $card_class = 'card-rejected';
                    $pill_class = 'pill-rejected';
                } elseif ($log['status'] === 'pending') {
                    if (!empty($log['document_path'])) {
                        $card_status = 'reviewing';
                        $status_label = 'Under Review';
                        $dept_icon_class = 'dept-icon-reviewing';
                        $dept_icon = 'fa-hourglass-half';
                        $card_class = 'card-reviewing';
                        $pill_class = 'pill-reviewing';
                    }
                }
                
                $doc_filename = !empty($log['document_path']) ? basename($log['document_path']) : '';
                $rejection_remark = !empty($log['remark']) ? str_replace('RESUBMITTED: ', '', $log['remark']) : '';
                $uploaded_date = !empty($log['uploaded_at']) ? date('M d, Y', strtotime($log['uploaded_at'])) : '';
            ?>
                <div class="dept-card <?= $card_class ?>"
                     onclick="openDeptModal(
                         '<?= $log['department_id'] ?>',
                         '<?= htmlspecialchars(addslashes($log['department_name'])) ?>',
                         '<?= $card_status ?>',
                         '<?= htmlspecialchars(addslashes($doc_filename)) ?>',
                         '<?= htmlspecialchars(addslashes($rejection_remark)) ?>',
                         '<?= $uploaded_date ?>'
                     )">
                    <div class="dept-icon <?= $dept_icon_class ?>">
                        <i class="fa-solid <?= $dept_icon ?>"></i>
                    </div>
                    <div class="dept-name"><?= htmlspecialchars($log['department_name']) ?></div>
                    <div>
                        <span class="status-pill <?= $pill_class ?>">
                            <i class="fa-solid <?= $dept_icon ?>" style="font-size: 0.65rem;"></i>
                            <?= $status_label ?>
                        </span>
                    </div>

                    <?php if ($doc_filename): ?>
                        <div class="dept-doc-info">
                            <i class="fa-regular fa-file-lines"></i>
                            <span><?= htmlspecialchars($doc_filename) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($card_status === 'rejected' && $rejection_remark): ?>
                        <div class="dept-rejection-preview">
                            <i class="fa-solid fa-comment-dots me-1"></i><?= htmlspecialchars($rejection_remark) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($card_status === 'not_started' || $card_status === 'rejected'): ?>
                        <div class="dept-action-hint">
                            <i class="fa-solid fa-arrow-up-from-bracket" style="font-size: 0.75rem;"></i>
                            <?= $card_status === 'rejected' ? 'Re-upload document' : 'Upload document to start' ?>
                        </div>
                    <?php elseif ($card_status === 'reviewing'): ?>
                        <div class="dept-action-hint" style="color: #3B82F6;">
                            <i class="fa-solid fa-eye" style="font-size: 0.75rem;"></i>
                            View submission
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ── Department Management Modal ── -->
<div class="modal fade" id="deptModal" tabindex="-1" aria-labelledby="deptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header-custom">
                <div>
                    <h5 class="m-0" id="deptModalLabel" style="font-weight: 700; letter-spacing: -0.3px;">Department Clearance</h5>
                    <div id="modal-dept-badge" class="modal-dept-badge status-pill pill-not_started mt-1"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body-custom">
                <div id="modal-dept-name-display" style="font-size: 1.1rem; font-weight: 700; color: var(--text-color); margin-bottom: 12px;"></div>

                <!-- Requirement Block -->
                <div id="modal-requirement-box" class="p-3 mb-4 rounded-3" style="background-color: #F8FAFC; border: 1px solid var(--border-color);">
                    <div style="font-size: 0.725rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i> Clearance Requirement
                    </div>
                    <div id="modal-requirement-text" style="font-size: 0.875rem; color: var(--text-color); line-height: 1.5; font-weight: 500;"></div>
                </div>

                <!-- Status Block -->
                <div id="modal-status-block" class="modal-status-block block-not_started">
                    <div id="modal-status-icon" class="modal-status-icon icon-not_started">
                        <i id="modal-status-icon-i" class="fa-solid fa-circle-info"></i>
                    </div>
                    <div>
                        <div id="modal-status-title" class="modal-status-title"></div>
                        <div id="modal-status-subtitle" class="modal-status-subtitle"></div>
                    </div>
                </div>

                <!-- Staff Feedback / Rejection Reason -->
                <div id="modal-feedback-box" class="feedback-box d-none">
                    <div class="feedback-label"><i class="fa-solid fa-comment-dots me-1"></i> Staff Feedback</div>
                    <div id="modal-feedback-text" class="feedback-text"></div>
                </div>

                <!-- Existing Document Preview -->
                <div id="modal-doc-preview" class="doc-preview-block d-none">
                    <div class="doc-preview-info">
                        <div class="doc-preview-icon">
                            <i class="fa-regular fa-file-lines"></i>
                        </div>
                        <div>
                            <div id="modal-doc-name" class="doc-preview-name"></div>
                            <div id="modal-doc-date" class="doc-preview-date"></div>
                        </div>
                    </div>
                    <a id="modal-doc-link" href="#" target="_blank" class="btn btn-outline-custom" style="font-size: 0.75rem; font-weight: 600; padding: 6px 12px; white-space: nowrap;">
                        <i class="fa-solid fa-eye me-1"></i> View
                    </a>
                </div>

                <!-- Upload Form -->
                <form id="modal-upload-form" action="upload.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="department_id" id="modal-dept-id">
                    <div id="modal-upload-section" class="d-none">
                        <div class="upload-zone" id="upload-zone" onclick="document.getElementById('document_file').click()">
                            <div class="upload-zone-icon">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            </div>
                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-color); margin-bottom: 4px;">Click to select a file</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">PDF, PNG, JPG, or WEBP · Max 5MB</div>
                            <input type="file" id="document_file" name="document_file" accept=".pdf,image/*" onchange="handleFileSelect(this)">
                        </div>
                        <div class="upload-selected-file" id="upload-selected-display">
                            <i class="fa-solid fa-file-circle-check"></i>
                            <span id="upload-selected-name"></span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer-custom">
                <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="modal-submit-btn" form="modal-upload-form" class="btn btn-accent d-none">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    <span id="modal-submit-label">Upload Document</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Bootstrap tooltips
    document.addEventListener('DOMContentLoaded', function () {
        const tooltipEls = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipEls.forEach(el => new bootstrap.Tooltip(el));
    });

    // Department modal open function
    function openDeptModal(deptId, deptName, status, docFilename, remark, uploadedDate) {
        // Set core fields
        document.getElementById('modal-dept-id').value = deptId;
        document.getElementById('modal-dept-name-display').textContent = deptName;

        // Set requirement text dynamically
        const requirementsMap = {
            'Academic': 'Upload your admission letter, and any required academic credentials. Your documents will be verified before approval.',
            'Bursary': 'Upload your school fees payment receipt. The Bursary Office will verify your payment before granting clearance.',
            'Student Affairs': 'Upload proof of hostel allocation.',
            'College': 'Upload your evidence of payment for departmental and college fee.',
            'Library': 'Visit the library for clearance verification.',
            'ICT': 'Complete your course registration, provide evidence of Global IT Certification (if required), and upload the certificate or course registration form.',
            'Medical': 'Upload your medical card number.',
            'Counselling': 'Complete the online counselling form using the <a href="https://counselling.calebuniversity.edu.ng" target="_blank" style="color: var(--primary-accent); font-weight: 600; text-decoration: underline;">provided link</a> and upload a screenshot of the successfully completed form.'
        };
        
        let requirementText = 'Please follow the department guidelines for clearance.';
        for (const [key, text] of Object.entries(requirementsMap)) {
            if (deptName.toLowerCase().includes(key.toLowerCase())) {
                requirementText = text;
                break;
            }
        }
        document.getElementById('modal-requirement-text').innerHTML = requirementText;

        // Reset all sections
        document.getElementById('modal-doc-preview').classList.add('d-none');
        document.getElementById('modal-feedback-box').classList.add('d-none');
        document.getElementById('modal-upload-section').classList.add('d-none');
        document.getElementById('modal-submit-btn').classList.add('d-none');
        document.getElementById('upload-selected-display').style.display = 'none';
        document.getElementById('document_file').value = '';

        // Configure status block
        const statusBlock = document.getElementById('modal-status-block');
        const statusIcon = document.getElementById('modal-status-icon');
        const statusIconI = document.getElementById('modal-status-icon-i');
        const statusTitle = document.getElementById('modal-status-title');
        const statusSubtitle = document.getElementById('modal-status-subtitle');
        const badge = document.getElementById('modal-dept-badge');
        const submitBtn = document.getElementById('modal-submit-btn');

        // Clear previous classes
        statusBlock.className = 'modal-status-block';
        statusIcon.className = 'modal-status-icon';
        badge.className = 'modal-dept-badge status-pill';

        if (status === 'approved') {
            statusBlock.classList.add('block-approved');
            statusIcon.classList.add('icon-approved');
            statusIconI.className = 'fa-solid fa-circle-check';
            statusTitle.textContent = 'Clearance Approved';
            statusSubtitle.textContent = 'This department has cleared you. No further action needed.';
            badge.classList.add('pill-approved');
            badge.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size:0.65rem;"></i> Approved';
            // Show existing doc if any
            if (docFilename) showDocPreview(docFilename, uploadedDate);

        } else if (status === 'reviewing') {
            statusBlock.classList.add('block-reviewing');
            statusIcon.classList.add('icon-reviewing');
            statusIconI.className = 'fa-solid fa-hourglass-half';
            statusTitle.textContent = 'Document Under Review';
            statusSubtitle.textContent = 'Your document has been submitted and is being reviewed by the officer.';
            badge.classList.add('pill-reviewing');
            badge.innerHTML = '<i class="fa-solid fa-hourglass-half" style="font-size:0.65rem;"></i> Under Review';
            if (docFilename) showDocPreview(docFilename, uploadedDate);

        } else if (status === 'rejected') {
            statusBlock.classList.add('block-rejected');
            statusIcon.classList.add('icon-rejected');
            statusIconI.className = 'fa-solid fa-circle-xmark';
            statusTitle.textContent = 'Document Rejected';
            statusSubtitle.textContent = 'Please read the feedback below and re-upload an updated document.';
            badge.classList.add('pill-rejected');
            badge.innerHTML = '<i class="fa-solid fa-circle-xmark" style="font-size:0.65rem;"></i> Rejected';
            // Show rejection reason
            if (remark) {
                document.getElementById('modal-feedback-text').textContent = remark;
                document.getElementById('modal-feedback-box').classList.remove('d-none');
            }
            // Show old doc
            if (docFilename) showDocPreview(docFilename, uploadedDate);
            // Show upload section
            document.getElementById('modal-upload-section').classList.remove('d-none');
            submitBtn.classList.remove('d-none');
            document.getElementById('modal-submit-label').textContent = 'Re-upload Document';

        } else { // not_started
            statusBlock.classList.add('block-not_started');
            statusIcon.classList.add('icon-not_started');
            statusIconI.className = 'fa-solid fa-circle-info';
            statusTitle.textContent = 'No Document Uploaded';
            statusSubtitle.textContent = 'Upload your clearance document for this department to begin the review process.';
            badge.classList.add('pill-not_started');
            badge.innerHTML = '<i class="fa-solid fa-circle-dot" style="font-size:0.65rem;"></i> Not Started';
            document.getElementById('modal-upload-section').classList.remove('d-none');
            submitBtn.classList.remove('d-none');
            document.getElementById('modal-submit-label').textContent = 'Upload Document';
        }

        new bootstrap.Modal(document.getElementById('deptModal')).show();
    }

    function showDocPreview(filename, uploadedDate) {
        document.getElementById('modal-doc-name').textContent = filename;
        document.getElementById('modal-doc-date').textContent = uploadedDate ? 'Uploaded: ' + uploadedDate : '';
        document.getElementById('modal-doc-link').href = '/uploads/' + filename;
        document.getElementById('modal-doc-preview').classList.remove('d-none');
    }

    function handleFileSelect(input) {
        const display = document.getElementById('upload-selected-display');
        const nameEl = document.getElementById('upload-selected-name');
        if (input.files && input.files[0]) {
            nameEl.textContent = input.files[0].name;
            display.style.display = 'flex';
        } else {
            display.style.display = 'none';
        }
    }

    // Drag & drop support
    const uploadZone = document.getElementById('upload-zone');
    if (uploadZone) {
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const fileInput = document.getElementById('document_file');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(fileInput);
            }
        });
    }
</script>
</body>
</html>
