<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'] ?? '';
$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$user_reg   = $_SESSION['reg_no'] ?? $_SESSION['register_no'] ?? '';

// 2. Supabase Config
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Parse Marks Helper
function parseStudentMarks($rawData) {
    if (empty($rawData)) return [];
    $data = is_string($rawData) ? json_decode($rawData, true) : $rawData;
    if (is_string($data)) { $data = json_decode($data, true); }
    if (!is_array($data)) return [];

    $cleanData = [];
    foreach ($data as $row) {
        if (!is_array($row)) continue;

        $ia = isset($row['ia']) ? intval($row['ia']) : (isset($row['ia_marks']) ? intval($row['ia_marks']) : 0);
        $ue = isset($row['ue']) ? intval($row['ue']) : (isset($row['ue_marks']) ? intval($row['ue_marks']) : 0);
        $subject = $row['subject'] ?? $row['Subject'] ?? $row['subject_code'] ?? 'Subject';
        $total = isset($row['total']) ? intval($row['total']) : ($ia + $ue);
        
        $pf_status = $row['pass_fail'] ?? $row['result'] ?? '';
        if (empty($pf_status)) {
            $pf_status = ($ia >= 10 && $ue >= 30 && $total >= 40) ? 'PASS' : 'FAIL';
        }

        $cleanData[] = [
            'subject' => $subject,
            'ia'      => $ia,
            'ue'      => $ue,
            'total'   => $total,
            'result'  => strtoupper(trim($pf_status))
        ];
    }
    return $cleanData;
}

// 3. Fetch Data
$selected_sem = $_GET['sem'] ?? 'sem1_marks';
$student_data = null;

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

    $all_students = json_decode($res, true) ?? [];
    foreach ($all_students as $st) {
        $st_email = strtolower(trim($st['email'] ?? ''));
        $st_reg   = strtolower(trim($st['reg_no'] ?? $st['register_no'] ?? ''));
        $st_id    = strval($st['id'] ?? '');

        if (
            (!empty($user_email) && $st_email === strtolower(trim($user_email))) ||
            (!empty($user_reg) && $st_reg === strtolower(trim($user_reg))) ||
            (!empty($user_id) && $st_id === strval($user_id))
        ) {
            $student_data = $st;
            break;
        }
    }
}

// Combine all 6 semester marks for consolidated printing
$all_sems_data = [];
$sl_no = 1;

if ($student_data) {
    for ($i = 1; $i <= 6; $i++) {
        $sem_key = "sem{$i}_marks";
        $parsed = parseStudentMarks($student_data[$sem_key] ?? '[]');
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
<title>Semester Marks - SRMS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified Student Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
.sidebar-top { overflow-y: auto; padding: 22px 16px 10px 16px; }
.sidebar-top::-webkit-scrollbar { width: 4px; }
.sidebar-top::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 22px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 18px; }
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #059669, #047857); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
.sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; }
.sidebar-brand span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.nav-category { font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 10px 6px 10px; }
.sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; color: #94A3B8; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-menu a i { font-size: 14px; width: 18px; text-align: center; }
.sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }
.sidebar-menu a.active { background: #059669; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 4px; }
.sidebar-footer a { display: flex; align-items: center; gap: 12px; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: #FFFFFF;
    padding: 20px 24px;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #ECFDF5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

.header-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.sem-select-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #F8FAFC;
    padding: 6px 12px;
    border-radius: 10px;
    border: 1px solid #CBD5E1;
}

.sem-select-wrapper label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
}

.sem-select {
    padding: 6px 10px;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #0F172A;
    background: #FFFFFF;
    outline: none;
    cursor: pointer;
}

.btn-print {
    background: #059669;
    color: #FFFFFF;
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
}

.btn-print:hover {
    background: #047857;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
}

/* Card */
.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

/* Table Design */
.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    margin-top: 18px;
}

.marks-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.marks-table th {
    background: #F8FAFC;
    color: #475569;
    padding: 14px 18px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #E2E8F0;
}

.marks-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #F1F5F9;
    font-size: 13.5px;
    color: #1E293B;
}

.marks-table tr:hover td {
    background: #F8FAFC;
}

.marks-table tr:last-child td {
    border-bottom: none;
}

.badge-result {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.badge-pass {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}

.badge-fail {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

/* Summary stats tiles */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-chip {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.stat-chip-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.stat-chip-info h4 {
    font-size: 18px;
    font-weight: 800;
    color: #0F172A;
}

.stat-chip-info span {
    font-size: 11.5px;
    color: #64748B;
    font-weight: 600;
}

/* Print Sheet */
.print-only-sheet { display: none; }

@media print {
    body * { visibility: hidden; }
    .sidebar, .navbar, .topbar, .page-header, .stats-row, .screen-view { display: none !important; }
    
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
    .print-table th { background-color: #E2E8F0 !important; color: #000; font-weight: bold; -webkit-print-color-adjust: exact; }

    .footer-note { font-size: 9px; margin-top: 15px; text-align: center; }
    .signatures { display: flex; justify-content: space-between; margin-top: 40px; font-size: 10px; font-weight: bold; }
}

@media(max-width: 900px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Unified Student Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Student Administration</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_student.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
            <li><a href="marks.php" class="active"><i class="fa-solid fa-award"></i> <span>Marks / Grades</span></a></li>

            <li class="nav-category">College & Campus</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievance.php"><i class="fa-solid fa-headset"></i> <span>Student Grievance</span></a></li>

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
            <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="student_profile.php" style="color:#94A3B8;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>My Profile</span>
        </a>
        <a href="../auth/logout.php" style="color:#F87171;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Academic Results & Grades</h1>
            <p>Semester evaluation statements and marksheet preview</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($student_data['name'] ?? 'Student')?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-award"></i>
                </div>
                <div>
                    <h1>Semester Marksheet</h1>
                    <p>Student: <strong><?=htmlspecialchars($student_data['name'] ?? $student_data['full_name'] ?? 'Student')?></strong> &nbsp;|&nbsp; Reg: <strong><?=htmlspecialchars($student_data['reg_no'] ?? $student_data['register_no'] ?? 'N/A')?></strong></p>
                </div>
            </div>
            
            <div class="header-controls">
                <form method="GET">
                    <div class="sem-select-wrapper">
                        <label for="sem">Term:</label>
                        <select id="sem" name="sem" class="sem-select" onchange="this.form.submit()">
                            <option value="sem1_marks" <?php echo $selected_sem === 'sem1_marks' ? 'selected' : ''; ?>>Semester 1</option>
                            <option value="sem2_marks" <?php echo $selected_sem === 'sem2_marks' ? 'selected' : ''; ?>>Semester 2</option>
                            <option value="sem3_marks" <?php echo $selected_sem === 'sem3_marks' ? 'selected' : ''; ?>>Semester 3</option>
                            <option value="sem4_marks" <?php echo $selected_sem === 'sem4_marks' ? 'selected' : ''; ?>>Semester 4</option>
                            <option value="sem5_marks" <?php echo $selected_sem === 'sem5_marks' ? 'selected' : ''; ?>>Semester 5</option>
                            <option value="sem6_marks" <?php echo $selected_sem === 'sem6_marks' ? 'selected' : ''; ?>>Semester 6</option>
                        </select>
                    </div>
                </form>

                <button type="button" class="btn-print" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Marksheet (6 Sems)
                </button>
            </div>
        </div>

        <!-- Single Semester View on Screen -->
        <div class="screen-view">
            <?php if (!$student_data): ?>
                <div class="card" style="text-align: center; color: #64748B; padding: 40px;">
                    <i class="fa-solid fa-user-xmark" style="font-size: 32px; margin-bottom: 10px; color: #CBD5E1;"></i>
                    <p>Student record not found in system.</p>
                </div>
            <?php else: 
                $curr_marks = parseStudentMarks($student_data[$selected_sem] ?? '[]');
                $pass_count = 0;
                $fail_count = 0;
                $total_sum = 0;
                foreach ($curr_marks as $m) {
                    if ($m['result'] === 'PASS') { $pass_count++; } else { $fail_count++; }
                    $total_sum += $m['total'];
                }
            ?>
                <!-- Summary Chips -->
                <div class="stats-row">
                    <div class="stat-chip">
                        <div class="stat-chip-icon" style="background:#EFF6FF; color:#2563EB;">
                            <i class="fa-solid fa-book"></i>
                        </div>
                        <div class="stat-chip-info">
                            <h4><?=count($curr_marks)?></h4>
                            <span>Total Papers</span>
                        </div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-icon" style="background:#ECFDF5; color:#059669;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="stat-chip-info">
                            <h4><?=$pass_count?></h4>
                            <span>Passed</span>
                        </div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-icon" style="background:#FEF2F2; color:#DC2626;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <div class="stat-chip-info">
                            <h4><?=$fail_count?></h4>
                            <span>Needs Re-appear</span>
                        </div>
                    </div>
                    <div class="stat-chip">
                        <div class="stat-chip-icon" style="background:#F3E8FF; color:#7C3AED;">
                            <i class="fa-solid fa-calculator"></i>
                        </div>
                        <div class="stat-chip-info">
                            <h4><?=$total_sum?></h4>
                            <span>Total Marks</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #0F172A;">
                            Course Evaluation Table (<?=strtoupper(str_replace('_marks', '', $selected_sem))?>)
                        </h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>Subject Code / Title</th>
                                    <th style="text-align:center;">Internal (IA)</th>
                                    <th style="text-align:center;">External (UE)</th>
                                    <th style="text-align:center;">Total Marks</th>
                                    <th style="text-align:center;">Result Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($curr_marks)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding: 25px; color: #94A3B8;">No marks registered for this semester yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($curr_marks as $m): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($m['subject']); ?></td>
                                        <td style="text-align:center;"><?php echo $m['ia']; ?> / 25</td>
                                        <td style="text-align:center;"><?php echo $m['ue']; ?> / 75</td>
                                        <td style="text-align:center; font-weight: 800;"><?php echo $m['total']; ?></td>
                                        <td style="text-align:center;">
                                            <?php $is_p = ($m['result'] === 'PASS'); ?>
                                            <span class="badge-result <?=$is_p ? 'badge-pass' : 'badge-fail'?>">
                                                <i class="fa-solid <?=$is_p ? 'fa-check' : 'fa-xmark'?>"></i>
                                                <?php echo $m['result']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ================= OFFICIAL CONSOLIDATED MARKSHEET (PRINT ONLY) ================= -->
<?php if ($student_data): ?>
<div class="print-only-sheet">
    
    <div class="univ-header">
        <h2>ANNAMALAI UNIVERSITY</h2>
        <p>Re-accredited with 'A+' Grade by NAAC<br>Villupuram - 605 402</p>
        <h3 style="margin-top: 8px;">UG DEGREE EXAMINATIONS RESULTS</h3>
    </div>

    <div class="student-info-grid">
        <div>
            <strong>Name of the Student:</strong> <?php echo strtoupper(htmlspecialchars($student_data['name'] ?? $student_data['full_name'] ?? 'N/A')); ?><br>
            <strong>Enrolment Number:</strong> <?php echo htmlspecialchars($student_data['id'] ?? 'N/A'); ?><br>
            <strong>Roll No:</strong> <?php echo htmlspecialchars($student_data['roll_no'] ?? 'N/A'); ?>
        </div>
        <div>
            <strong>DOB:</strong> <?php echo htmlspecialchars($student_data['dob'] ?? '01-01-2003'); ?><br>
            <strong>Reg. No:</strong> <?php echo htmlspecialchars($student_data['reg_no'] ?? $student_data['register_no'] ?? 'N/A'); ?><br>
            <strong>Medium:</strong> ENGLISH
        </div>
        <div>
            <strong>Centre:</strong> 201 - ARIGNAR ANNA GOVERNMENT ARTS COLLEGE , VILLUPURAM<br>
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

</body>
</html>