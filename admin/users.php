<?php
/**
 * Admin User Management
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce admin auth
check_auth('admin');

$success_message = '';
$error_message = '';

$academic_departments = [
    'Computer Science',
    'Accounting',
    'Business Administration',
    'Mass Communication',
    'Economics',
    'Biochemistry',
    'Microbiology',
    'Architecture',
    'Law',
    'International Relations'
];

function normalize_academic_dept($dept_name) {
    $dept_name = strtolower(trim($dept_name));
    if (empty($dept_name)) return null;
    
    $mapping = [
        'computer science' => 'Computer Science',
        'compt science' => 'Computer Science',
        'comp sci' => 'Computer Science',
        'csc' => 'Computer Science',
        'accounting' => 'Accounting',
        'acc' => 'Accounting',
        'business administration' => 'Business Administration',
        'business admin' => 'Business Administration',
        'bus admin' => 'Business Administration',
        'mass communication' => 'Mass Communication',
        'mass comm' => 'Mass Communication',
        'economics' => 'Economics',
        'eco' => 'Economics',
        'biochemistry' => 'Biochemistry',
        'biochem' => 'Biochemistry',
        'microbiology' => 'Microbiology',
        'microbio' => 'Microbiology',
        'architecture' => 'Architecture',
        'arch' => 'Architecture',
        'law' => 'Law',
        'international relations' => 'International Relations',
        'int relations' => 'International Relations',
        'ir' => 'International Relations'
    ];
    
    if (isset($mapping[$dept_name])) {
        return $mapping[$dept_name];
    }
    
    foreach ($mapping as $key => $val) {
        if (strpos($key, $dept_name) !== false || strpos($dept_name, $key) !== false) {
            return $val;
        }
    }
    
    return ucwords($dept_name);
}

// Handle add user request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    $academic_dept = $_POST['academic_dept'] ?? null;
    
    $matric_no = null;
    $email = null;

    if ($role === 'student') {
        $matric_no = trim($_POST['username'] ?? '');
        $validation_field = $matric_no;
        if (empty($password)) {
            $password = 'studentpass';
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $validation_field = $email;
    }

    if (empty($validation_field) || empty($password) || empty($full_name) || empty($role)) {
        $error_message = 'All required fields must be filled.';
    } elseif ($role === 'student' && empty($academic_dept)) {
        $error_message = 'Academic department is required for students.';
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $dept = ($role === 'officer' && !empty($department_id)) ? $department_id : null;
            $acad_dept = ($role === 'student' && !empty($academic_dept)) ? $academic_dept : null;
            $user_id = generate_uuid();

            $stmt = $pdo->prepare("
                INSERT INTO users (user_id, matric_no, email, password_hash, role, department_id, academic_dept, full_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $matric_no, $email, $hashed_password, $role, $dept, $acad_dept, $full_name]);
            
            // If they are an officer, update the department officer_id
            if ($role === 'officer' && !empty($dept)) {
                $up_dept = $pdo->prepare("UPDATE departments SET officer_id = ? WHERE department_id = ?");
                $up_dept->execute([$user_id, $dept]);
            }

            $success_message = 'User created successfully.';
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE') !== false) {
                $error_message = 'Matric Number / Email already exists.';
            } else {
                $error_message = 'Failed to create user: ' . $e->getMessage();
            }
        }
    }
}

// Handle delete user request
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    if ($delete_id === $_SESSION['user_id']) {
        $error_message = 'You cannot delete your own admin account.';
    } else {
        try {
            // First, remove officer associations from departments table
            $up_dept = $pdo->prepare("UPDATE departments SET officer_id = NULL WHERE officer_id = ?");
            $up_dept->execute([$delete_id]);

            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$delete_id]);
            $success_message = 'User deleted successfully.';
        } catch (PDOException $e) {
            $error_message = 'Failed to delete user: ' . $e->getMessage();
        }
    }
}

// Handle edit user request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $edit_user_id = $_POST['edit_user_id'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? null;
    $academic_dept = $_POST['academic_dept'] ?? null;
    
    $matric_no = null;
    $email = null;
    
    // Fetch current user details
    $check_stmt = $pdo->prepare("SELECT role, email, matric_no FROM users WHERE user_id = ?");
    $check_stmt->execute([$edit_user_id]);
    $curr_user = $check_stmt->fetch();
    
    if (!$curr_user) {
        $error_message = 'User not found.';
    } else {
        $role = $curr_user['role'];
        if ($role === 'student') {
            $matric_no = trim($_POST['username'] ?? '');
            $validation_field = $matric_no;
        } else {
            $email = trim($_POST['email'] ?? '');
            $validation_field = $email;
        }

        if (empty($validation_field) || empty($full_name)) {
            $error_message = 'All required fields must be filled.';
        } elseif ($role === 'student' && empty($academic_dept)) {
            $error_message = 'Academic department is required for students.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $dept = ($role === 'officer' && !empty($department_id)) ? $department_id : null;
                $acad_dept = ($role === 'student' && !empty($academic_dept)) ? $academic_dept : null;
                
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $update_stmt = $pdo->prepare("
                        UPDATE users 
                        SET matric_no = ?, email = ?, password_hash = ?, department_id = ?, academic_dept = ?, full_name = ? 
                        WHERE user_id = ?
                    ");
                    $update_stmt->execute([$matric_no, $email, $hashed_password, $dept, $acad_dept, $full_name, $edit_user_id]);
                } else {
                    $update_stmt = $pdo->prepare("
                        UPDATE users 
                        SET matric_no = ?, email = ?, department_id = ?, academic_dept = ?, full_name = ? 
                        WHERE user_id = ?
                    ");
                    $update_stmt->execute([$matric_no, $email, $dept, $acad_dept, $full_name, $edit_user_id]);
                }

                if ($role === 'officer') {
                    $clear_dept = $pdo->prepare("UPDATE departments SET officer_id = NULL WHERE officer_id = ?");
                    $clear_dept->execute([$edit_user_id]);
                    if (!empty($dept)) {
                        $up_dept = $pdo->prepare("UPDATE departments SET officer_id = ? WHERE department_id = ?");
                        $up_dept->execute([$edit_user_id, $dept]);
                    }
                }

                $pdo->commit();
                $success_message = 'User updated successfully.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $error_message = 'Matric Number / Email already exists.';
                } else {
                    $error_message = 'Failed to update user: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle CSV upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file_tmp, 'r');
        
        if ($handle !== false) {
            $header = fgetcsv($handle);
            $header = array_map('strtolower', array_map('trim', $header));
            
            $col_matric = array_search('matric_no', $header);
            if ($col_matric === false) $col_matric = array_search('matric', $header);
            if ($col_matric === false) $col_matric = array_search('username', $header);
            
            $col_name = array_search('full_name', $header);
            if ($col_name === false) $col_name = array_search('name', $header);
            
            $col_dept = array_search('department', $header);
            if ($col_dept === false) $col_dept = array_search('dept', $header);
            
            $col_pass = array_search('password', $header);
            $col_email = array_search('email', $header);

            if ($col_matric === false || $col_name === false) {
                $error_message = 'CSV must contain at least "matric_no" (or "username") and "full_name" columns.';
            } else {
                $imported_count = 0;
                $skipped_count = 0;

                try {
                    $pdo->beginTransaction();
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO users (user_id, matric_no, email, password_hash, role, department_id, academic_dept, full_name)
                        VALUES (?, ?, ?, ?, 'student', NULL, ?, ?)
                    ");

                    while (($row = fgetcsv($handle)) !== false) {
                        $matric = trim($row[$col_matric] ?? '');
                        $name = trim($row[$col_name] ?? '');
                        
                        if (empty($matric) || empty($name)) {
                            $skipped_count++;
                            continue;
                        }

                        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE matric_no = ?");
                        $check->execute([$matric]);
                        if ($check->fetchColumn() > 0) {
                            $skipped_count++;
                            continue;
                        }

                        $email_val = null;
                        if ($col_email !== false && isset($row[$col_email])) {
                            $email_val = trim($row[$col_email]);
                        }

                        $pass_val = 'studentpass';
                        if ($col_pass !== false && !empty($row[$col_pass])) {
                            $pass_val = trim($row[$col_pass]);
                        }

                        $dept_val = null;
                        if ($col_dept !== false && isset($row[$col_dept])) {
                            $dept_val = normalize_academic_dept($row[$col_dept]);
                        }

                        $user_id = generate_uuid();
                        $hashed_password = password_hash($pass_val, PASSWORD_BCRYPT, ['cost' => 12]);

                        $insert_stmt->execute([$user_id, $matric, $email_val, $hashed_password, $dept_val, $name]);
                        $imported_count++;
                    }

                    $pdo->commit();
                    $success_message = "Successfully imported $imported_count student(s). Skipped $skipped_count (empty or duplicates).";
                    
                    // Refresh users list
                    $stmt = $pdo->prepare($users_query);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error_message = "CSV Import failed: " . $e->getMessage();
                }
            }
            fclose($handle);
        } else {
            $error_message = "Failed to open uploaded CSV file.";
        }
    } else {
        $error_message = "Failed to upload CSV file.";
    }
}

// Fetch departments for dropdown
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();

// Fetch all users
$search_filter = trim($_GET['search'] ?? '');
$users_query = "
    SELECT u.*, d.department_name 
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
";
$params = [];

if (!empty($search_filter)) {
    $users_query .= " WHERE u.full_name LIKE ? OR u.matric_no LIKE ? OR u.email LIKE ? OR u.role LIKE ? ";
    $params = ["%$search_filter%", "%$search_filter%", "%$search_filter%", "%$search_filter%"];
}

$users_query .= " ORDER BY u.role ASC, COALESCE(u.matric_no, u.email) ASC ";
$stmt = $pdo->prepare($users_query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Clearance System</title>
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

        .form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 6px;
        }

        .form-control, .form-select {
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
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="/caleb_banner.jpg" alt="Caleb University Logo" style="height: 35px; object-fit: contain; margin-right: 8px;">
            <span style="font-size: 1.1rem; border-left: 1px solid var(--border-color); padding-left: 12px; margin-left: 4px; font-weight: 600; color: #475569;">Clearance Portal - Admin</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-gauge me-1"></i> Dashboard
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
        <!-- Add User Form -->
        <div class="col-lg-4">
            <div class="card-custom">
                <h5 class="mb-4" style="font-weight:700; letter-spacing:-0.5px;">Add New User</h5>
                
                <form action="" method="POST" novalidate>
                    <input type="hidden" name="add_user" value="1">
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">User Role</label>
                        <select class="form-select" id="role" name="role" onchange="toggleFormFields(this.value)" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="officer">Departmental Officer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3" id="matric-group">
                        <label for="username" class="form-label">Matric Number</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="e.g. 19/10204">
                    </div>

                    <div class="mb-3 d-none" id="email-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="e.g. staff@oascs.edu.ng">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="mb-4 d-none" id="department-select-group">
                        <label for="department_id" class="form-label">Assigned Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4 d-none" id="academic-dept-group">
                        <label for="academic_dept" class="form-label">Academic Department</label>
                        <select class="form-select" id="academic_dept" name="academic_dept">
                            <option value="">Select Department</option>
                            <?php foreach ($academic_departments as $adept): ?>
                                <option value="<?= htmlspecialchars($adept) ?>"><?= htmlspecialchars($adept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-accent w-100">
                        Create Account <i class="fa-solid fa-plus ms-1"></i>
                    </button>
                </form>
            </div>

            <!-- Bulk Import Card -->
            <div class="card-custom mt-4">
                <h5 class="mb-3" style="font-weight:700; letter-spacing:-0.5px;">Bulk Import Students</h5>
                <p class="text-muted" style="font-size:0.8rem; line-height:1.4;">Upload a CSV containing: <code>matric_no</code>, <code>full_name</code>, <code>department</code> (name or ID), and optionally <code>password</code>, <code>email</code>.</p>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="import_csv" value="1">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input class="form-control form-control-sm" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm w-100">
                        Upload & Import <i class="fa-solid fa-file-import ms-1"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="col-lg-8">
            <div class="card-custom p-0 overflow-hidden">
                <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0" style="font-weight:700; letter-spacing:-0.3px;">System Users</h5>
                    <form method="GET" class="d-flex gap-2" style="max-width: 300px; width: 100%;">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, matric, email or role..." value="<?= htmlspecialchars($search_filter) ?>">
                        <button type="submit" class="btn btn-sm btn-accent py-1 px-3">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <?php if (!empty($search_filter)): ?>
                            <a href="users.php" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Matric / Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td style="font-weight:500;"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['matric_no'] ?? $user['email']) ?></td>
                                    <td>
                                        <span class="badge px-2 py-1 bg-secondary-subtle text-secondary border uppercase" style="font-size:0.75rem;">
                                            <?= htmlspecialchars($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'student'): ?>
                                            <?= htmlspecialchars($user['academic_dept'] ?? 'N/A') ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($user['department_name'] ?? 'N/A') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-custom text-primary border-primary-subtle bg-primary-subtle bg-opacity-25 px-2 py-1 me-1"
                                                    onclick="openEditModal(
                                                        '<?= $user['user_id'] ?>',
                                                        '<?= htmlspecialchars(addslashes($user['full_name'])) ?>',
                                                        '<?= htmlspecialchars(addslashes($user['role'])) ?>',
                                                        '<?= htmlspecialchars(addslashes($user['matric_no'] ?? '')) ?>',
                                                        '<?= htmlspecialchars(addslashes($user['email'] ?? '')) ?>',
                                                        '<?= htmlspecialchars(addslashes($user['department_id'] ?? '')) ?>',
                                                        '<?= htmlspecialchars(addslashes($user['academic_dept'] ?? '')) ?>'
                                                    )">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </button>
                                            <a href="?delete=<?= $user['user_id'] ?>" 
                                               class="btn btn-outline-custom text-danger border-danger-subtle bg-danger-subtle bg-opacity-25 px-2 py-1"
                                               onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:0.8125rem; font-style:italic;">Logged In</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleFormFields(role) {
        const deptGroup = document.getElementById('department-select-group');
        const acadDeptGroup = document.getElementById('academic-dept-group');
        const matricGroup = document.getElementById('matric-group');
        const emailGroup = document.getElementById('email-group');
        const passwordInput = document.getElementById('password');
        
        if (role === 'student') {
            matricGroup.classList.remove('d-none');
            emailGroup.classList.add('d-none');
            deptGroup.classList.add('d-none');
            acadDeptGroup.classList.remove('d-none');
            document.getElementById('email').value = '';
            passwordInput.value = 'studentpass';
        } else if (role === 'officer') {
            matricGroup.classList.add('d-none');
            emailGroup.classList.remove('d-none');
            deptGroup.classList.remove('d-none');
            acadDeptGroup.classList.add('d-none');
            document.getElementById('username').value = '';
            if (passwordInput.value === 'studentpass') {
                passwordInput.value = '';
            }
        } else if (role === 'admin') {
            matricGroup.classList.add('d-none');
            emailGroup.classList.remove('d-none');
            deptGroup.classList.add('d-none');
            acadDeptGroup.classList.add('d-none');
            document.getElementById('username').value = '';
            document.getElementById('department_id').value = '';
            document.getElementById('academic_dept').value = '';
            if (passwordInput.value === 'studentpass') {
                passwordInput.value = '';
            }
        } else {
            matricGroup.classList.remove('d-none');
            emailGroup.classList.add('d-none');
            deptGroup.classList.add('d-none');
            acadDeptGroup.classList.add('d-none');
            if (passwordInput.value === 'studentpass') {
                passwordInput.value = '';
            }
        }
    }
</script>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="edit_user_id" id="edit-user-id">
                
                <div class="modal-header px-4 py-3 border-bottom">
                    <h5 class="modal-title" id="editUserModalLabel" style="font-weight: 700; letter-spacing: -0.5px;">Edit User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label for="edit-full-name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="edit-full-name" name="full_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit-role-display" class="form-label">Role</label>
                        <input type="text" class="form-control bg-light text-muted" id="edit-role-display" readonly style="cursor: not-allowed; font-size: 0.9rem;">
                    </div>

                    <div class="mb-3 d-none" id="edit-matric-group">
                        <label for="edit-username" class="form-label">Matric Number</label>
                        <input type="text" class="form-control" id="edit-username" name="username">
                    </div>

                    <div class="mb-3 d-none" id="edit-email-group">
                        <label for="edit-email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="edit-email" name="email">
                    </div>

                    <div class="mb-3 d-none" id="edit-department-group">
                        <label for="edit-department-id" class="form-label">Assigned Department</label>
                        <select class="form-select" id="edit-department-id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="edit-academic-dept-group">
                        <label for="edit-academic-dept" class="form-label">Academic Department</label>
                        <select class="form-select" id="edit-academic-dept" name="academic_dept">
                            <option value="">Select Department</option>
                            <?php foreach ($academic_departments as $adept): ?>
                                <option value="<?= htmlspecialchars($adept) ?>"><?= htmlspecialchars($adept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit-password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit-password" name="password" placeholder="••••••••" minlength="6">
                    </div>
                </div>
                <div class="modal-footer px-4 py-3 border-top">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditModal(userId, fullName, role, matricNo, email, departmentId, academicDept) {
        document.getElementById('edit-user-id').value = userId;
        document.getElementById('edit-full-name').value = fullName;
        document.getElementById('edit-role-display').value = role.toUpperCase();
        document.getElementById('edit-password').value = '';
        
        const matricGroup = document.getElementById('edit-matric-group');
        const emailGroup = document.getElementById('edit-email-group');
        const deptGroup = document.getElementById('edit-department-group');
        const acadDeptGroup = document.getElementById('edit-academic-dept-group');
        
        const deptSelect = document.getElementById('edit-department-id');
        const acadDeptSelect = document.getElementById('edit-academic-dept');
        
        deptSelect.value = departmentId;
        acadDeptSelect.value = academicDept;
        
        if (role === 'student') {
            matricGroup.classList.remove('d-none');
            emailGroup.classList.add('d-none');
            deptGroup.classList.add('d-none');
            acadDeptGroup.classList.remove('d-none');
            document.getElementById('edit-username').value = matricNo;
            document.getElementById('edit-email').value = '';
        } else if (role === 'officer') {
            matricGroup.classList.add('d-none');
            emailGroup.classList.remove('d-none');
            deptGroup.classList.remove('d-none');
            acadDeptGroup.classList.add('d-none');
            document.getElementById('edit-username').value = '';
            document.getElementById('edit-email').value = email;
        } else { // admin
            matricGroup.classList.add('d-none');
            emailGroup.classList.remove('d-none');
            deptGroup.classList.add('d-none');
            acadDeptGroup.classList.add('d-none');
            document.getElementById('edit-username').value = '';
            document.getElementById('edit-email').value = email;
        }
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }
</script>
</body>
</html>
