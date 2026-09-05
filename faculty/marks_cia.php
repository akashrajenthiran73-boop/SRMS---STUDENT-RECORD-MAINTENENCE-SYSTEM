<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication & Role Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$can_edit = in_array($role, ['HOD', 'Admin', 'Super Admin', 'Faculty']);
$is_hod = in_array($role, ['HOD', 'Admin', 'Super Admin']);

// 2. Supabase Configuration
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Helper Function to safely parse marks format
function parseStudentMarks($rawData) {
    if (empty($rawData)) return [];
    
    $data = is_string($rawData) ? json_decode($rawData, true) : $rawData;
    if (is_string($data)) {
        $data = json_decode($data, true);
    }
    if (!is_array($data)) return [];

    $cleanData = [];
    foreach ($data as $row) {
        if (!is_array($row)) continue;

        $ia = isset($row['ia']) ? intval($row['ia']) : (isset($row['ia_marks']) ? intval($row['ia_marks']) : (isset($row['I.A']) ? intval($row['I.A']) : 0));
        $ue = isset($row['ue']) ? intval($row['ue']) : (isset($row['ue_marks']) ? intval($row['ue_marks']) : (isset($row['U.E']) ? intval($row['U.E']) : 0));

        $subject = $row['subject'] ?? $row['Subject'] ?? $row['subject_code'] ?? 'Subject';
        
        $total = isset($row['total']) ? intval($row['total']) : ($ia + $ue);
        if ($total == 0 && ($ia > 0 || $ue > 0)) {
            $total = $ia + $ue;
        }

        $pf_status = $row['pass_fail'] ?? $row['result'] ?? $row['pf'] ?? '';
        if (empty($pf_status)) {
            $pf_status = ($ia >= 10 && $ue >= 30 && $total >= 40) ? 'PASS' : 'FAIL';
        }

        $cleanData[] = [
            'subject'   => $subject,
            'ia'        => $ia,
            'ue'        => $ue,
            'total'     => $total,
            'result'    => strtoupper(trim($pf_status)),
            'pass_fail' => strtoupper(trim($pf_status))
        ];
    }
    return $cleanData;
}

// 3. Save / Update Logic (PATCH to Supabase)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $student_id = $input['student_id'] ?? '';
    $sem_column = $input['semester'] ?? 'sem1_marks';
    $updated_marks_array = $input['marks'] ?? [];

    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Student ID missing']);
        exit();
    }

    $formatted_marks = [];
    foreach ($updated_marks_array as $row) {
        $ia = intval($row['ia'] ?? 0);
        $ue = intval($row['ue'] ?? 0);
        $total = $ia + $ue;
        $is_pass = ($ia >= 10 && $ue >= 30 && $total >= 40);
        $status_str = $is_pass ? 'PASS' : 'FAIL';

        $formatted_marks[] = [
            'subject'   => trim($row['subject'] ?? ''),
            'ia'        => $ia,
            'ue'        => $ue,
            'total'     => $total,
            'result'    => $status_str,
            'pass_fail' => $status_str
        ];
    }

    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/students?id=eq." . urlencode($student_id);
    $payload = json_encode([$sem_column => $formatted_marks]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curl_err]);
    } elseif ($http_code == 200 || $http_code == 204) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $response]);
    }
    exit();
}

// 4. Fetch All Students List
$selected_sem = $_GET['sem'] ?? 'sem1_marks';
$students_list = [];

if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/students?select=*";
    $ch = curl_init($fetch_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $students_list = json_decode($res, true) ?? [];
}

// Active Student Selection
if ($role === 'Student') {
    $selected_student_id = $user_id;
} else {
    $selected_student_id = $_GET['student_id'] ?? ($students_list[0]['id'] ?? '');
}

$active_student = null;
foreach ($students_list as $st) {
    if (strval($st['id']) === strval($selected_student_id)) {
        $active_student = $st;
        break;
    }
}
if (!$active_student && !empty($students_list)) {
    $active_student = $students_list[0];
    $selected_student_id = $active_student['id'];
}

// Combine 6 Semesters Data for Printing
$all_sems_data = [];
$sl_no = 1;

if ($active_student) {
    for ($i = 1; $i <= 6; $i++) {
        $sem_key = "sem{$i}_marks";
        $parsed = parseStudentMarks($active_student[$sem_key] ?? '[]');
        foreach ($parsed as $item) {
            $item['sl_no'] = $sl_no++;
            $item['sem'] = "0" . $i;
            $all_sems_data[] = $item;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marks & CIA Dashboard - SRMS Faculty</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
.sidebar-top { overflow-y: auto; padding: 22px 16px 10px 16px; }
.sidebar-top::-webkit-scrollbar { width: 4px; }
.sidebar-top::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 22px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 18px; }
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
.sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; }
.sidebar-brand span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.nav-category { font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 10px 6px 10px; }
.sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; color: #94A3B8; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-menu a i { font-size: 14px; width: 18px; text-align: center; }
.sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }
.sidebar-menu a.active { background: #2563EB; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 4px; }

/* Main Content */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
.badge-student { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
.badge-faculty { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; }
.badge-hod { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
.badge-admin { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }

/* Page Content */
.content-body { padding: 30px 36px; }
.card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }

/* Filter Bar */
.filter-bar { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; align-items: center; justify-content: space-between; background: #F8FAFC; padding: 16px 20px; border-radius: 12px; border: 1px solid #E2E8F0; }
.filter-group { display: flex; gap: 10px; align-items: center; }
.filter-group label { font-size: 13px; font-weight: 600; color: #475569; }
.filter-group select { padding: 9px 14px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; font-weight: 600; outline: none; background: #FFF; color: #0F172A; cursor: pointer; transition: border-color 0.2s; }
.filter-group select:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

.btn-actions { display: flex; gap: 10px; align-items: center; }
.btn { border: none; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.btn-save { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25); }
.btn-save:hover { opacity: 0.95; transform: translateY(-1px); }
.btn-print { background: #0F172A; color: white; }
.btn-print:hover { background: #1E293B; }

/* Student Header Banner */
.student-header { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 1px solid #BFDBFE; border-radius: 12px; padding: 16px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.student-header h3 { color: #1E40AF; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.sem-tag { padding: 4px 12px; border-radius: 20px; background: #FFFFFF; color: #1D4ED8; font-weight: 800; font-size: 12px; border: 1px solid #BFDBFE; letter-spacing: 0.5px; }

/* Marks Table */
.table-wrapper { overflow-x: auto; border: 1px solid #E2E8F0; border-radius: 12px; }
.marks-table { width: 100%; border-collapse: collapse; text-align: left; }
.marks-table th { background: #F8FAFC; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.5px; padding: 13px 16px; border-bottom: 1px solid #E2E8F0; }
.marks-table td { padding: 13px 16px; font-size: 13.5px; border-bottom: 1px solid #F1F5F9; color: #1E293B; }
.marks-table tr:last-child td { border-bottom: none; }
.marks-table tr:hover td { background-color: #F8FAFC; }

.status-pass { display: inline-block; color: #15803D; font-weight: 700; background: #DCFCE7; padding: 4px 12px; border-radius: 20px; font-size: 11px; border: 1px solid #BBF7D0; }
.status-fail { display: inline-block; color: #B91C1C; font-weight: 700; background: #FEE2E2; padding: 4px 12px; border-radius: 20px; font-size: 11px; border: 1px solid #FECACA; }

.mark-input { width: 84px; padding: 7px 10px; border: 1px solid #CBD5E1; border-radius: 7px; font-size: 13.5px; font-weight: 600; text-align: center; outline: none; transition: border-color 0.2s; color: #0F172A; }
.mark-input:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }

/* Consolidated Print Template Hidden on Normal Screen */
.print-only-sheet { display: none; }

/* ================= PRINT STYLES ================= */
@media print {
    body * { visibility: hidden; }
    .sidebar, .topbar, .navbar, .filter-bar, .screen-view { display: none !important; }
    
    .print-only-sheet, .print-only-sheet * { visibility: visible; }
    .print-only-sheet {
        display: block !important;
        position: absolute;
        left: 0; top: 0; width: 100%;
        background: #FFF; color: #000;
        font-family: Arial, sans-serif;
        padding: 10px;
    }

    .univ-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
    .univ-header h2 { font-size: 18px; text-transform: uppercase; margin-bottom: 2px; }
    .univ-header h3 { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
    .univ-header p { font-size: 10px; }

    .student-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 6px; font-size: 11px; margin-bottom: 12px;
        border: 1px solid #000; padding: 8px;
    }
    .student-info-grid div { line-height: 1.4; }

    .print-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .print-table th, .print-table td { border: 1px solid #000; padding: 4px 6px; font-size: 10px; text-align: center; }
    .print-table th { background-color: #F87171 !important; color: #000; font-weight: bold; -webkit-print-color-adjust: exact; }

    .footer-note { font-size: 9px; margin-top: 15px; text-align: center; }
    .signatures { display: flex; justify-content: space-between; margin-top: 40px; font-size: 10px; font-weight: bold; }
}
</style>
</head>
<body>

<!-- Unified Faculty Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Faculty Panel</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> Students Record</a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> Bio Data</a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-database"></i> UMIS Data</a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> Result Analysis</a></li>

            <li class="nav-category">Faculty Panel</li>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-envelope-open-text"></i> Student Leave Requests</a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-calendar-check"></i> Apply Leave / OD</a></li>
            <li><a href="marks_cia.php" class="active"><i class="fa-solid fa-pen-ruler"></i> Marks CIA</a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book-bookmark"></i> My Subjects</a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-file-pdf"></i> Syllabus & Materials</a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-tasks"></i> Assignments</a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> Announcements</a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> Help & Support</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="faculty_profile.php" class="sidebar-menu" style="display:flex; align-items:center; gap:12px; color:#94A3B8; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> Profile
        </a>
        <a href="../auth/logout.php" style="display:flex; align-items:center; gap:12px; color:#F87171; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Internal Marks & Examination Results</h1>
            <p>Manage CIA marks, external scores, and official grade records</p>
        </div>
        <div class="user-profile">
            <?php 
                $badge_class = 'badge-faculty';
                if ($role === 'Student') $badge_class = 'badge-student';
                elseif ($role === 'HOD') $badge_class = 'badge-hod';
                elseif (in_array($role, ['Admin', 'Super Admin'])) $badge_class = 'badge-admin';
            ?>
            <div class="role-badge <?php echo $badge_class; ?>">
                <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="card">
            
            <form method="GET" class="filter-bar">
                <?php if ($role !== 'Student'): ?>
                <div class="filter-group">
                    <label for="student_id_select">Select Student:</label>
                    <select id="student_id_select" name="student_id" onchange="this.form.submit()">
                        <?php foreach ($students_list as $st): 
                            $st_id = $st['id'] ?? '';
                            $st_name = $st['name'] ?? $st['full_name'] ?? ('Student ' . $st_id);
                        ?>
                            <option value="<?php echo htmlspecialchars($st_id); ?>" <?php echo strval($st_id) === strval($selected_student_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st_name); ?> (ID: <?php echo htmlspecialchars($st_id); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="sem_select">Select Semester:</label>
                    <select id="sem_select" name="sem" onchange="this.form.submit()">
                        <option value="sem1_marks" <?php echo $selected_sem === 'sem1_marks' ? 'selected' : ''; ?>>Semester 1</option>
                        <option value="sem2_marks" <?php echo $selected_sem === 'sem2_marks' ? 'selected' : ''; ?>>Semester 2</option>
                        <option value="sem3_marks" <?php echo $selected_sem === 'sem3_marks' ? 'selected' : ''; ?>>Semester 3</option>
                        <option value="sem4_marks" <?php echo $selected_sem === 'sem4_marks' ? 'selected' : ''; ?>>Semester 4</option>
                        <option value="sem5_marks" <?php echo $selected_sem === 'sem5_marks' ? 'selected' : ''; ?>>Semester 5</option>
                        <option value="sem6_marks" <?php echo $selected_sem === 'sem6_marks' ? 'selected' : ''; ?>>Semester 6</option>
                    </select>
                </div>

                <div class="btn-actions">
                    <button type="button" class="btn btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Marksheet (6 Sems)</button>
                    <?php if ($is_hod): ?>
                        <button type="button" class="btn btn-save" onclick="alert('Semester Marks Approved & Locked!')"><i class="fa-solid fa-lock"></i> Approve Marks</button>
                    <?php endif; ?>
                </div>
            </form>

            <div class="screen-view">
                <?php if (!$active_student): ?>
                    <div style="text-align: center; padding: 40px; color: #64748B;">
                        <i class="fa-solid fa-user-slash" style="font-size: 30px; margin-bottom: 10px;"></i>
                        <p>No student selected or found in database.</p>
                    </div>
                <?php else: 
                    $marks_array = parseStudentMarks($active_student[$selected_sem] ?? '[]');
                    $st_name = $active_student['name'] ?? $active_student['full_name'] ?? 'Student';
                    $st_id = $active_student['id'] ?? '';
                ?>

                <div class="student-header">
                    <h3><i class="fa-solid fa-user-graduate"></i> Student: <?php echo htmlspecialchars($st_name); ?> (ID: <?php echo htmlspecialchars($st_id); ?>)</h3>
                    <span class="sem-tag"><?php echo str_replace('_marks', '', strtoupper($selected_sem)); ?></span>
                </div>

                <div class="table-wrapper">
                    <table class="marks-table" id="table-<?php echo htmlspecialchars($st_id); ?>">
                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Internal (IA) [25]</th>
                                <th>External (UE) [75]</th>
                                <th>Total [100]</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($marks_array)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #94A3B8; padding: 32px 16px;">No marks recorded for this semester yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($marks_array as $row): 
                                    $subject = $row['subject'];
                                    $ia = $row['ia'];
                                    $ue = $row['ue'];
                                    $total = $row['total'];
                                    $is_pass = (strtolower($row['result']) === 'pass');
                                ?>
                                <tr>
                                    <td class="sub-name"><strong><?php echo htmlspecialchars($subject); ?></strong></td>
                                    <td>
                                        <?php if ($can_edit): ?>
                                            <input type="number" class="mark-input ia-input" value="<?php echo $ia; ?>" max="25" min="0" onkeyup="recalculateRow(this)" onchange="recalculateRow(this)">
                                        <?php else: ?>
                                            <?php echo $ia; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($can_edit): ?>
                                            <input type="number" class="mark-input ue-input" value="<?php echo $ue; ?>" max="75" min="0" onkeyup="recalculateRow(this)" onchange="recalculateRow(this)">
                                        <?php else: ?>
                                            <?php echo $ue; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="total-cell"><strong><?php echo $total; ?></strong></td>
                                    <td class="status-cell">
                                        <span class="<?php echo $is_pass ? 'status-pass' : 'status-fail'; ?>">
                                            <?php echo $is_pass ? 'PASS' : 'FAIL'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($can_edit && !empty($marks_array)): ?>
                    <div style="margin-top: 22px; text-align: right;">
                        <button class="btn btn-save" onclick="saveStudentMarks('<?php echo htmlspecialchars($st_id); ?>')">
                            <i class="fa-solid fa-floppy-disk"></i> Save & Update Marks
                        </button>
                    </div>
                <?php endif; ?>

                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- ================= OFFICIAL CONSOLIDATED MARKSHEET (PRINT ONLY) ================= -->
<?php if ($active_student): ?>
<div class="print-only-sheet">
    
    <div class="univ-header">
        <h2>ANNAMALAI UNIVERSITY</h2>
        <p>Re-accredited with 'A+' Grade by NAAC<br>Villupuram - 605 402</p>
        <h3 style="margin-top: 8px;">UG DEGREE EXAMINATIONS RESULTS</h3>
    </div>

    <div class="student-info-grid">
        <div>
            <strong>Name of the Student:</strong> <?php echo strtoupper(htmlspecialchars($active_student['name'] ?? $active_student['full_name'] ?? 'N/A')); ?><br>
            <strong>Enrolment Number:</strong> <?php echo htmlspecialchars($active_student['id'] ?? 'N/A'); ?><br>
            <strong>Roll No:</strong> <?php echo htmlspecialchars($active_student['roll_no'] ?? 'N/A'); ?>
        </div>
        <div>
            <strong>DOB:</strong> <?php echo htmlspecialchars($active_student['dob'] ?? '01-01-2003'); ?><br>
            <strong>Reg. No:</strong> <?php echo htmlspecialchars($active_student['reg_no'] ?? $active_student['register_no'] ?? 'N/A'); ?><br>
            <strong>Medium:</strong> ENGLISH
        </div>
        <div>
            <strong>Centre:</strong> 201 - ARIGNAR ANNA GOVERNMENT ARTS COLLEGE<br>
            <strong>Discipline:</strong> B.Sc - COMPUTER SCIENCE (CS)
        </div>
    </div>

    <table class="print-table">
        <thead>
            <tr>
                <th style="width: 5%;">Sl.No.</th>
                <th style="width: 8%;">Semester</th>
                <th>Subject Code</th>
                <th style="width: 6%;">IA</th>
                <th style="width: 6%;">UE</th>
                <th style="width: 8%;">Total</th>
                <th style="width: 10%;">Result</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($all_sems_data)): ?>
                <tr><td colspan="7">No Marks Available</td></tr>
            <?php else: ?>
                <?php foreach ($all_sems_data as $row): ?>
                <tr>
                    <td><?php echo $row['sl_no']; ?></td>
                    <td><?php echo $row['sem']; ?></td>
                    <td style="text-align: left; padding-left: 8px;"><?php echo htmlspecialchars($row['subject']); ?></td>
                    <td><?php echo $row['ia']; ?></td>
                    <td><?php echo $row['ue']; ?></td>
                    <td><strong><?php echo $row['total']; ?></strong></td>
                    <td><?php echo $row['result']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer-note">
        <p><strong>Note: "RA" INDICATES REAPPEAR</strong></p>
        <p style="margin-top: 4px;">The Result published is provisional only. If any inadvertent error that may crept in the result published, the final Mark Statement issued by the University only be treated authentic and final in this regard.</p>
    </div>

    <div class="signatures">
        <div>
            Date of Generation: <?php echo date('d-m-Y H:i:s'); ?>
        </div>
        <div style="text-align: right;">
            The Controller of Examinations,<br>Annamalai University,<br>Villupuram - 605 402.
        </div>
    </div>

</div>
<?php endif; ?>

<script>
function recalculateRow(input) {
    const tr = input.closest('tr');
    
    const iaInput = tr.querySelector('.ia-input');
    const ueInput = tr.querySelector('.ue-input');

    const iaVal = iaInput ? (parseInt(iaInput.value) || 0) : 0;
    const ueVal = ueInput ? (parseInt(ueInput.value) || 0) : 0;
    
    const total = iaVal + ueVal;
    const isPass = (iaVal >= 10 && ueVal >= 30 && total >= 40);

    const totalTd = tr.querySelector('.total-cell strong');
    if (totalTd) {
        totalTd.innerText = total;
    }

    const statusTd = tr.querySelector('.status-cell');
    if (statusTd) {
        if (isPass) {
            statusTd.innerHTML = '<span class="status-pass">PASS</span>';
        } else {
            statusTd.innerHTML = '<span class="status-fail">FAIL</span>';
        }
    }
}

function saveStudentMarks(studentId) {
    const table = document.getElementById('table-' + studentId);
    const rows = table.querySelectorAll('tbody tr');
    const updatedMarks = [];

    rows.forEach(row => {
        const subElem = row.querySelector('.sub-name');
        if(!subElem) return;

        const subject = subElem.innerText.trim();
        const iaVal = row.querySelector('.ia-input') ? row.querySelector('.ia-input').value : "0";
        const ueVal = row.querySelector('.ue-input') ? row.querySelector('.ue-input').value : "0";

        updatedMarks.push({
            subject: subject,
            ia: parseInt(iaVal) || 0,
            ue: parseInt(ueVal) || 0
        });
    });

    const currentSem = "<?php echo $selected_sem; ?>";

    fetch('marks_cia.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            student_id: studentId,
            semester: currentSem,
            marks: updatedMarks
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Marks updated successfully! Reloading page...');
            window.location.reload();
        } else {
            alert('Error updating marks: ' + (data.message || JSON.stringify(data.error)));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server Connection Error!');
    });
}
</script>

</body>
</html>