<?php
/**
 * Digital Student Clearance System
 * Login Page
 */
require_once __DIR__ . '/../includes/db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_role = $_POST['login_role'] ?? 'student';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both your credentials and password.';
    } else {
        try {
            // Check both matric_no and email since we support student and staff login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE matric_no = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Enforce that the logging-in user matches the role tab they selected
                $is_staff_tab = ($login_role === 'staff');
                $user_role = $user['role'];
                
                $role_mismatch = false;
                if ($is_staff_tab && $user_role === 'student') {
                    $role_mismatch = true;
                } elseif (!$is_staff_tab && $user_role !== 'student') {
                    $role_mismatch = true;
                }

                if ($role_mismatch) {
                    $error_message = 'Role mismatch. Please use the correct login tab.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['matric_no'] ?? $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['department_id'] = $user['department_id'];

                    // Log audit action
                    require_once __DIR__ . '/../includes/functions.php';
                    log_audit($user['user_id'], 'SUBMIT_REQUEST', 'users', $user['user_id']); 

                    // Redirect based on role
                    switch ($user['role']) {
                        case 'student':
                            header('Location: /student/dashboard.php');
                            exit();
                        case 'officer':
                            header('Location: /officer/dashboard.php');
                            exit();
                        case 'admin':
                            header('Location: /admin/dashboard.php');
                            exit();
                    }
                }
            } else {
                $error_message = 'Invalid credentials or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'An error occurred during authentication. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Clearance Portal</title>
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
            --primary-accent: #00A859;
            --primary-accent-hover: #008F4C;
            --border-focus: #34D399;
            --error-bg: #FEE2E2;
            --error-text: #991B1B;
            --error-border: #FCA5A5;
            --shadow-subtle: 0 10px 30px -10px rgba(15, 23, 42, 0.15), 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        body {
            background: linear-gradient(rgba(15, 23, 42, 0.15), rgba(15, 23, 42, 0.35)), url('../IMG_8492.WEBP');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #0F172A;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.76);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 16px;
            box-shadow: var(--shadow-subtle);
            padding: 40px 32px;
            transition: transform 0.2s ease;
        }

        .logo-placeholder {
            width: auto;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px auto;
        }

        .system-title {
            font-size: 1.55rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 6px;
            color: #0F172A;
            letter-spacing: -0.5px;
        }

        .system-subtitle {
            font-size: 0.875rem;
            color: #475569;
            text-align: center;
            margin-bottom: 28px;
        }

        .form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1E293B;
            margin-bottom: 6px;
        }

        .form-control {
            border: 1px solid rgba(0, 0, 0, 0.12);
            border-radius: 8px;
            font-size: 0.9375rem;
            padding: 11px 16px;
            color: #0F172A;
            background-color: rgba(255, 255, 255, 0.65);
            transition: all 0.2s ease;
        }

        .form-control::placeholder {
            color: #94A3B8;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--border-focus);
            background-color: rgba(255, 255, 255, 0.95);
            color: #0F172A;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.18);
        }

        .btn-primary {
            background-color: var(--primary-accent);
            border: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 600;
            padding: 12px 20px;
            width: 100%;
            transition: all 0.2s ease;
            color: #FFFFFF;
        }

        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-accent-hover);
        }

        .error-alert {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.875rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-note {
            font-size: 0.75rem;
            color: #64748B;
            text-align: center;
            margin-top: 32px;
            line-height: 1.5;
        }
        
        .input-group-text {
            background-color: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(0, 0, 0, 0.12);
            color: #64748B;
            border-radius: 8px;
        }

        .nav-tabs-custom {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            display: flex;
        }

        .nav-tabs-custom .nav-item {
            flex: 1;
            text-align: center;
        }

        .nav-tabs-custom .nav-link {
            width: 100%;
            border: none;
            background: transparent;
            padding: 10px;
            font-weight: 500;
            color: #64748B;
            border-bottom: 2px solid transparent;
            transition: all 0.15s ease;
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary-accent);
            border-bottom: 2px solid var(--primary-accent);
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <!-- Logo Placeholder -->
        <div class="logo-placeholder">
            <img src="../caleb_logo.png" alt="Caleb University Logo" style="height: 80px; object-fit: contain;">
        </div>

        <h1 class="system-title">Clearance Portal</h1>
        <p class="system-subtitle">Digital Workflow Student Clearance System</p>

        <!-- Custom Role Tabs -->
        <div class="nav-tabs-custom">
            <div class="nav-item">
                <button type="button" class="nav-link active" id="student-tab">
                    Student Login
                </button>
            </div>
            <div class="nav-item">
                <button type="button" class="nav-link" id="staff-tab">
                    Staff Login
                </button>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
            <input type="hidden" name="login_role" id="login-role" value="student">

            <div class="mb-4">
                <label for="username" class="form-label" id="username-label">Matric Number</label>
                <div class="input-group">
                    <span class="input-group-text" style="border-right: none; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                        <i class="fa-regular fa-user"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           placeholder="e.g. 19/10204" 
                           style="border-top-left-radius: 0; border-bottom-left-radius: 0;"
                           required>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text" style="border-right: none; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="••••••••" 
                           style="border-top-left-radius: 0; border-bottom-left-radius: 0;"
                           required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Log In <i class="fa-solid fa-arrow-right-to-bracket ms-2"></i>
            </button>
        </form>

        <div class="footer-note">
            If you forgot your password, contact your administrator.
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const studentTab = document.getElementById('student-tab');
    const staffTab = document.getElementById('staff-tab');
    const loginRole = document.getElementById('login-role');
    const usernameLabel = document.getElementById('username-label');
    const usernameInput = document.getElementById('username');

    studentTab.addEventListener('click', () => {
        studentTab.classList.add('active');
        staffTab.classList.remove('active');
        loginRole.value = 'student';
        usernameLabel.textContent = 'Matric Number';
        usernameInput.placeholder = 'e.g. 19/10204';
    });

    staffTab.addEventListener('click', () => {
        staffTab.classList.add('active');
        studentTab.classList.remove('active');
        loginRole.value = 'staff';
        usernameLabel.textContent = 'Email Address';
        usernameInput.placeholder = 'e.g. library@oascs.edu.ng';
    });
</script>
</body>
</html>
