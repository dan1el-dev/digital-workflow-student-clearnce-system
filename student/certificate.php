<?php
/**
 * Print-Friendly High-Fidelity Clearance Form
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

// Enforce student auth
check_auth('student');

$student_id = $_SESSION['user_id'];

// Get active request
$stmt = $pdo->prepare("SELECT * FROM clearance_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$student_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: dashboard.php');
    exit();
}

$student_name = $_SESSION['full_name'];
$matric_no = $_SESSION['username'];
$level = $request['level'];

// Get academic department
$user_stmt = $pdo->prepare("SELECT academic_dept FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$student_user = $user_stmt->fetch();
$academic_dept = $student_user['academic_dept'] ?? 'Computer Science';

// Get all clearance items for this request
$log_stmt = $pdo->prepare("
    SELECT cl.*, d.department_name, u.full_name as officer_name
    FROM clearance_items cl
    JOIN departments d ON cl.department_id = d.department_id
    LEFT JOIN users u ON cl.reviewed_by = u.user_id
    WHERE cl.request_id = ?
    ORDER BY d.department_name ASC
");
$log_stmt->execute([$request['request_id']]);
$items = $log_stmt->fetchAll();

$dept_items = [];
foreach ($items as $item) {
    $dept_items[$item['department_name']] = $item;
}

$session_year = date('Y', strtotime($request['created_at'])) . '/' . (date('Y', strtotime($request['created_at'])) + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Record - <?= htmlspecialchars($matric_no) ?></title>
    <!-- Google Fonts for Handwriting & Styling -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Caveat:wght@700&family=Dancing+Script:wght@700&family=Alex+Brush&family=Reenie+Beanie&family=Just+Another+Hand&family=Sacramento&family=Marck+Script&family=Mrs+Saint+Delafield&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --paper-width: 210mm;
            --paper-height: 297mm;
            --primary-accent: #00A859; /* Caleb Green */
            --text-color: #0f172a;
            --border-color: #000000;
        }

        body {
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            color: var(--text-color);
            -webkit-print-color-adjust: exact;
        }

        /* ── Toolbar ── */
        .toolbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .toolbar-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
        }

        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-back {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        .btn-back:hover {
            background-color: #f1f5f9;
        }

        .btn-print {
            background-color: var(--primary-accent);
            border: none;
            color: #ffffff;
        }

        .btn-print:hover {
            background-color: #008747;
        }

        /* ── Paper Container ── */
        .paper-container {
            margin: 30px auto;
            width: var(--paper-width);
            min-height: var(--paper-height);
            background: white;
            padding: 20mm 16mm;
            box-sizing: border-box;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            position: relative;
            border: 1px solid #cbd5e1;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-crest {
            height: 80px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .univ-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 4px;
            letter-spacing: -0.5px;
        }

        .title-reg {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 1.15rem;
            color: #475569;
            margin: 0 0 10px;
        }

        .session-line {
            display: inline-block;
            position: relative;
            padding: 0 15px;
            font-weight: 700;
            font-size: 0.95rem;
            color: #1e293b;
        }

        .session-line::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            border-bottom: 2px dotted #475569;
        }

        .title-clearance {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 20px 0 0;
            color: #000;
        }

        /* ── Student Details ── */
        .student-details {
            margin: 30px 0;
        }

        .detail-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 18px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
        }

        .detail-label {
            margin-right: 8px;
            white-space: nowrap;
        }

        .detail-dots {
            flex-grow: 1;
            border-bottom: 2px dotted #64748b;
            margin-left: 4px;
            padding-bottom: 2px;
            position: relative;
        }

        .line-val {
            font-family: 'Caveat', cursive;
            font-size: 24px;
            color: #1e3d59; /* Blue pen ink */
            font-weight: 700;
            position: absolute;
            bottom: -4px;
            left: 8px;
            white-space: nowrap;
            text-transform: none; /* Keep natural case */
        }

        /* ── Clearance Table ── */
        .clearance-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid var(--border-color);
            margin-bottom: 25px;
        }

        .clearance-table th, .clearance-table td {
            border: 1px solid var(--border-color);
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .clearance-table th {
            background-color: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
        }

        .stage-header {
            background-color: #f1f5f9;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem;
        }

        .dept-col {
            width: 25%;
            font-weight: 700;
            color: #0f172a;
        }

        .desc-col {
            width: 45%;
            color: #334155;
            line-height: 1.4;
        }

        .sig-col {
            width: 30%;
            height: 70px;
            position: relative;
            vertical-align: middle;
            text-align: center;
        }

        /* Stamps & Signatures */
        .sig-text {
            font-size: 26px;
            font-weight: 500;
            color: #000080; /* Blue ink */
            user-select: none;
            opacity: 0.8;
            display: inline-block;
        }

        .officer-name-lbl {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            position: absolute;
            bottom: 2px;
            left: 0;
            right: 0;
            text-align: center;
        }

        .stamp-box {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 2px dashed rgba(0, 0, 128, 0.65);
            border-radius: 4px;
            padding: 2px 6px;
            font-family: 'Courier New', monospace;
            font-size: 8px;
            font-weight: 900;
            background-color: rgba(255, 255, 255, 0.9);
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 0.5px;
        }

        .stamp-academic {
            color: rgba(30, 58, 138, 0.75); /* Navy Blue */
            border-color: rgba(30, 58, 138, 0.75);
            transform: translate(-50%, -50%) rotate(-3deg) translate(-2px, 1px);
        }

        .stamp-bursary {
            color: rgba(185, 28, 28, 0.75); /* Crimson Red */
            border-color: rgba(185, 28, 28, 0.75);
            transform: translate(-50%, -50%) rotate(2deg) translate(3px, -2px);
        }

        .stamp-student {
            color: rgba(21, 128, 61, 0.75); /* Dark Green */
            border-color: rgba(21, 128, 61, 0.75);
            transform: translate(-50%, -50%) rotate(-2deg) translate(-3px, -1px);
        }

        .stamp-college {
            color: rgba(109, 40, 217, 0.75); /* Deep Purple */
            border-color: rgba(109, 40, 217, 0.75);
            transform: translate(-50%, -50%) rotate(3deg) translate(2px, 2px);
        }

        .stamp-library {
            color: rgba(30, 58, 138, 0.75); /* Navy Blue */
            border-color: rgba(30, 58, 138, 0.75);
            transform: translate(-50%, -50%) rotate(-1deg) translate(-1px, -3px);
        }

        .stamp-ict {
            color: rgba(21, 128, 61, 0.75); /* Dark Green */
            border-color: rgba(21, 128, 61, 0.75);
            transform: translate(-50%, -50%) rotate(1.5deg) translate(4px, 1px);
        }

        .stamp-medical {
            color: rgba(185, 28, 28, 0.75); /* Crimson Red */
            border-color: rgba(185, 28, 28, 0.75);
            transform: translate(-50%, -50%) rotate(-2.5deg) translate(-2px, 3px);
        }

        .stamp-counselling {
            color: rgba(109, 40, 217, 0.75); /* Deep Purple */
            border-color: rgba(109, 40, 217, 0.75);
            transform: translate(-50%, -50%) rotate(2.5deg) translate(1px, -2px);
        }

        /* ── Late Registration ── */
        .late-terms {
            border: 2px solid var(--border-color);
            padding: 10px 14px;
            font-size: 0.75rem;
            line-height: 1.45;
            color: #000;
        }

        .late-terms-title {
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        /* ── Print Settings ── */
        @media print {
            body {
                background-color: #ffffff;
            }

            .no-print {
                display: none !important;
            }

            .paper-container {
                margin: 0;
                padding: 10mm 10mm;
                width: 100%;
                min-height: unset;
                box-shadow: none;
                border: none;
            }

            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation Toolbar -->
    <div class="toolbar no-print">
        <div class="toolbar-title">Clearance Certificate System</div>
        <div style="display: flex; gap: 12px;">
            <a href="dashboard.php" class="toolbar-btn btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="window.print()" class="toolbar-btn btn-print">
                <i class="fa-solid fa-print"></i> Print Clearance
            </button>
        </div>
    </div>

    <!-- Paper Page Container -->
    <div class="paper-container">

        <!-- Logo & Crest Header -->
        <div class="header">
            <img src="../caleb_logo.png" alt="University Crest" class="logo-crest" onerror="this.src='../caleb_banner.jpg'">
            <h1 class="univ-name">Caleb University, Imota, Lagos</h1>
            <p class="title-reg">Registration for Fresh/Returning Students</p>
            <div class="session-line">SESSION: <?= htmlspecialchars($session_year) ?></div>
            <h2 class="title-clearance">RECORD OF CLEARANCE</h2>
        </div>

        <!-- Student Information sitting on Dotted Lines -->
        <div class="student-details">
            <div class="detail-row">
                <span class="detail-label">Name of Student:</span>
                <div class="detail-dots">
                    <span class="line-val"><?= htmlspecialchars($student_name) ?></span>
                </div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Matric No:</span>
                <div class="detail-dots" style="flex-grow: 2;">
                    <span class="line-val"><?= htmlspecialchars($matric_no) ?></span>
                </div>
                <span class="detail-label" style="margin-left: 20px;">Department:</span>
                <div class="detail-dots" style="flex-grow: 3;">
                    <span class="line-val"><?= htmlspecialchars($academic_dept) ?></span>
                </div>
                <span class="detail-label" style="margin-left: 20px;">Level:</span>
                <div class="detail-dots" style="flex-grow: 1; min-width: 80px;">
                    <span class="line-val"><?= htmlspecialchars($level) ?></span>
                </div>
            </div>
        </div>

        <!-- Two-Stage Clearance Table -->
        <table class="clearance-table">
            <thead>
                <tr>
                    <th>Point of Registration</th>
                    <th>Registration Activities</th>
                    <th>Name / Signature of Clearance Officer</th>
                </tr>
            </thead>
            <tbody>
                <!-- STAGE ONE -->
                <tr>
                    <td colspan="3" class="stage-header">Stage One (On Resumption)</td>
                </tr>
                <tr>
                    <td class="dept-col">Academic Affairs</td>
                    <td class="desc-col">Verification of Credentials</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['Academic Affairs'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Prof. James Audu';
                        ?>
                            <span class="sig-text" style="font-family: 'Caveat', cursive; font-size: 28px;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-academic">ACADEMIC AFFAIRS<br>APPROVED</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="dept-col">Bursary</td>
                    <td class="desc-col">Verification of payment status</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['Bursary'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Alhaji Musa Bello';
                        ?>
                            <span class="sig-text" style="font-family: 'Dancing Script', cursive; font-size: 24px;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-bursary">BURSARY OFFICE<br>PAID & CLEARED</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="dept-col">Student Affairs</td>
                    <td class="desc-col">Registration / Induction into the Hall of Residence</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['Student Affairs'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Mrs. Grace Adebayo';
                        ?>
                            <span class="sig-text" style="font-family: 'Alex Brush', cursive; font-size: 30px;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-student">STUDENT AFFAIRS<br>OK / CLEARED</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- STAGE TWO -->
                <tr>
                    <td colspan="3" class="stage-header">Stage Two (Completed Within a Week)</td>
                </tr>
                <tr>
                    <td class="dept-col">College Office</td>
                    <td class="desc-col">Course Registration</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['College'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Dr. Ngozi Okafor';
                        ?>
                            <span class="sig-text" style="font-family: 'Reenie Beanie', cursive; font-size: 32px; font-weight: bold;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-college">COLLEGE OFFICE<br>PAID / CLEARED</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="dept-col">Library</td>
                    <td class="desc-col">Registration/Collection of Library Card</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['Library'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Mrs. Angela Nduka';
                        ?>
                            <span class="sig-text" style="font-family: 'Just Another Hand', cursive; font-size: 34px;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-library">LIBRARIAN<br>VERIFIED OK</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="dept-col">ICT Centre</td>
                    <td class="desc-col">ICT Registration/Data Capturing/Evidence of Global IT Certification</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['ICT'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Mr. Victor Okafor';
                        ?>
                            <span class="sig-text" style="font-family: 'Sacramento', cursive; font-size: 32px; font-weight: bold;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-ict">ICT CENTRE<br>COMPLETED OK</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="dept-col">Health Centre (Medical)</td>
                    <td class="desc-col">Submission of Medical Report/Registration</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['Medical'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Dr. Kunle Coker';
                        ?>
                            <span class="sig-text" style="font-family: 'Marck Script', cursive; font-size: 26px;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-medical">HEALTH CENTRE<br>FIT & APPROVED</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="dept-col">Counselling</td>
                    <td class="desc-col">Collection/Filling of Counselling Form</td>
                    <td class="sig-col">
                        <?php 
                        $item = $dept_items['Counselling'] ?? null;
                        if ($item && $item['status'] === 'approved'): 
                            $name = $item['officer_name'] ?? 'Dr. Toyin Phillips';
                        ?>
                            <span class="sig-text" style="font-family: 'Mrs Saint Delafield', cursive; font-size: 42px;"><?= htmlspecialchars($name) ?></span>
                            <div class="stamp-box stamp-counselling">COUNSELLING DEPT<br>APPROVED</div>
                            <span class="officer-name-lbl"><?= htmlspecialchars($name) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Late Registration Terms -->
        <div class="late-terms">
            <div class="late-terms-title">LATE REGISTRATION TERMS & CONDITIONS</div>
            <div>
                <strong>LATE REGISTRATION:</strong> A student who fails to complete registration within the first week of resumption shall be subjected to the provisions of late registration. Late registration attracts a penalty of <strong>N80,000</strong> for registrations completed in week 2, and <strong>N100,000</strong> for week 3. Any student unregistered after week 3 will be deemed to have suspended studies for the semester.
            </div>
        </div>

    </div>

    <!-- Auto-Print Trigger -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 600);
        });
    </script>
</body>
</html>
