<?php
/**
 * User Profile and Password Change Page
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Enforce login
check_auth();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user data with department
$stmt = $pdo->prepare("
    SELECT u.*, d.department_name 
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /auth/logout.php');
    exit();
}

// Determine dashboard redirect URL
$dashboard_url = '/';
switch ($user['role']) {
    case 'student':
        $dashboard_url = '/student/dashboard.php';
        break;
    case 'officer':
        $dashboard_url = '/officer/dashboard.php';
        break;
    case 'admin':
        $dashboard_url = '/admin/dashboard.php';
        break;
}

// Handle password change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'New password must be at least 6 characters long.';
    } elseif (!password_verify($current_password, $user['password_hash'])) {
        $error_message = 'Current password is incorrect.';
    } else {
        try {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_stmt->execute([$new_hash, $user_id]);
            
            log_audit($user_id, 'ADMIN_OVERRIDE', 'users', $user_id); // Log in audit log
            $success_message = 'Password changed successfully.';
        } catch (Exception $e) {
            $error_message = 'Failed to update password: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Clearance System</title>
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
            
            --success-bg: #E6F4EA;
            --success-text: #137333;
            --error-bg: #FFE4E6;
            --error-text: #9F1239;
            
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

        .profile-container {
            max-width: 800px;
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

        .form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 6px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9375rem;
            padding: 10px 14px;
        }

        .btn-accent {
            background-color: var(--primary-accent);
            border: none;
            color: #FFFFFF;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
        }

        .btn-accent:hover {
            background-color: var(--primary-hover);
            color: #FFFFFF;
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
            background-color: var(--success-bg);
            color: var(--success-text);
            border-color: rgba(19, 115, 51, 0.1);
        }

        .alert-error-custom {
            background-color: var(--error-bg);
            color: var(--error-text);
            border-color: rgba(225, 29, 72, 0.1);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?= $dashboard_url ?>">
            <img src="/caleb_banner.jpg" alt="Caleb University Logo" style="height: 35px; object-fit: contain; margin-right: 8px;">
            <span style="font-size: 1.1rem; border-left: 1px solid var(--border-color); padding-left: 12px; margin-left: 4px; font-weight: 600; color: #475569;">Clearance Portal</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $dashboard_url ?>" class="btn btn-outline-custom">
                <i class="fa-solid fa-gauge me-1"></i> Dashboard
            </a>
            <a href="/auth/logout.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Log Out
            </a>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="profile-container">

    <a href="<?= $dashboard_url ?>" class="text-decoration-none text-muted mb-4 d-inline-block" style="font-size: 0.875rem;">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
    </a>

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

    <div class="row g-4">
        <!-- User Information Card -->
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-4" style="font-weight:700; letter-spacing:-0.5px;">Account Details</h5>
                
                <div class="mb-3">
                    <span class="text-muted d-block" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Full Name</span>
                    <span style="font-size: 1rem; font-weight: 550;"><?= htmlspecialchars($user['full_name']) ?></span>
                </div>
                
                <div class="mb-3">
                    <span class="text-muted d-block" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase;"><?= $user['role'] === 'student' ? 'Matric Number' : 'Email Address' ?></span>
                    <span><?= htmlspecialchars($user['matric_no'] ?? $user['email']) ?></span>
                </div>

                <div class="mb-3">
                    <span class="text-muted d-block" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Portal Role</span>
                    <span class="badge bg-secondary-subtle text-secondary border uppercase" style="font-size: 0.75rem; font-weight: 600;">
                        <?= htmlspecialchars($user['role']) ?>
                    </span>
                </div>

                <?php if ($user['role'] === 'student' || $user['role'] === 'officer'): ?>
                    <div class="mb-3">
                        <span class="text-muted d-block" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Department</span>
                        <span>
                            <?php if ($user['role'] === 'student'): ?>
                                <?= htmlspecialchars($user['academic_dept'] ?? 'Not Assigned') ?>
                            <?php else: ?>
                                <?= htmlspecialchars($user['department_name'] ?? 'Not Assigned') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <span class="text-muted d-block" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Member Since</span>
                    <span><?= date('F d, Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="col-md-6">
            <div class="card-custom">
                <h5 class="mb-4" style="font-weight:700; letter-spacing:-0.5px;">Change Password</h5>
                
                <form action="" method="POST">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>

                    <button type="submit" class="btn btn-accent w-100">
                        Update Password <i class="fa-solid fa-key ms-1"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
