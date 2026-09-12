<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication & Role setup
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role']; // 'Student', 'Faculty', 'HOD' / 'Admin' / 'Super Admin'
$can_edit = in_array($role, ['HOD', 'Admin', 'Super Admin', 'Faculty']);
$is_hod = in_array($role, ['HOD', 'Admin', 'Super Admin']);
$is_admin = in_array($role, ['Admin', 'Super Admin']);

require_once __DIR__ . '/../includes/college_data.php';

// 2. Department and Class resolution
$all_departments = get_all_departments();
$session_dept = $_SESSION['department'] ?? 'CS';
$raw_dept = $is_admin ? ($_GET['dept'] ?? $session_dept) : $session_dept;
$selected_dept = normalize_dept_code($raw_dept);

$available_classes = get_department_classes($selected_dept);

$selected_class = $_GET['class'] ?? 'UG_3';
if (!isset($available_classes[$selected_class])) {
    $selected_class = array_key_first($available_classes) ?: 'UG_3';
}
$class_info = $available_classes[$selected_class];

// 3. Handle AJAX Save Request (Multi-Class Slot Updater)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $day = $input['day'] ?? '';
    $hour = (int)($input['hour'] ?? 1);
    $subject = trim($input['subject'] ?? '');
    $span = (int)($input['span'] ?? 1);
    $target_dept = trim($input['dept'] ?? $selected_dept);
    $target_class = trim($input['class'] ?? $selected_class);

    $saved = save_class_timetable_slot($target_dept, $target_class, $day, $hour, $subject, $span);
    if ($saved) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save timetable slot.']);
    }
    exit();
}

// 4. Fetch Multi-Class Timetable Data
$timetable = get_class_timetable($selected_dept, $selected_class);
?>
<?php
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Class Timetable - SRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified HOD Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
.sidebar-top { overflow-y: auto; padding: 22px 16px 10px 16px; }
.sidebar-top::-webkit-scrollbar { width: 4px; }
.sidebar-top::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 22px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 18px; }
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #7C3AED, #6D28D9); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
.sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; }
.sidebar-brand span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.nav-category { font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 10px 6px 10px; }
.sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; color: #94A3B8; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-menu a i { font-size: 14px; width: 18px; text-align: center; }
.sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }
.sidebar-menu a.active { background: #7C3AED; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 4px; }
.sidebar-footer a { display: flex; align-items: center; gap: 12px; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }

/* Main Content Wrapper */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

/* Page Header Card */
.page-header {
    background: #FFFFFF;
    padding: 22px 28px;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
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
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header-title h2 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}

.page-header-title p {
    font-size: 13px;
    color: #64748B;
    margin-top: 2px;
}

.action-btns {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn {
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-print {
    background: #FFFFFF;
    color: #475569;
    border: 1px solid #CBD5E1;
}

.btn-print:hover {
    background: #F1F5F9;
    color: #0F172A;
}

.btn-approve {
    background: #7C3AED;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
}

.btn-approve:hover {
    background: #6D28D9;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.35);
}

/* Class & Degree Switcher Tabs */
.class-nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 22px;
}

.class-nav-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #FFFFFF;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    overflow-x: auto;
}

.class-tab-btn {
    padding: 8px 16px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    color: #64748B;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.class-tab-btn:hover {
    color: #7C3AED;
    background: #F3E8FF;
}

.class-tab-btn.active {
    background: #7C3AED;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
}

.dept-select-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #FFFFFF;
    padding: 8px 16px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.dept-select-box select {
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    padding: 6px 12px;
    outline: none;
    font-size: 13px;
    font-weight: 700;
    color: #0F172A;
    background: #F8FAFC;
    cursor: pointer;
    font-family: inherit;
}

.dept-select-box select:focus {
    border-color: #7C3AED;
}

/* Info Banner */
.info-banner {
    background: #EFF6FF;
    border: 1px solid #DBEAFE;
    border-radius: 12px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    font-size: 13px;
    color: #1E40AF;
}

.info-banner-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-banner i {
    font-size: 16px;
    color: #3B82F6;
}

/* Timetable Card */
.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

/* Timetable Table Design */
.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
}

.timetable {
    width: 100%;
    border-collapse: collapse;
    text-align: center;
    background: #FFFFFF;
}

.timetable th {
    background: #0B132B;
    color: #FFFFFF;
    padding: 14px 16px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border: 1px solid #1E293B;
}

.timetable th.day-header {
    width: 110px;
    background: #090E21;
}

.timetable th .period-time {
    display: block;
    font-size: 10.5px;
    font-weight: 500;
    color: #94A3B8;
    margin-top: 3px;
    text-transform: none;
    letter-spacing: 0;
}

.timetable td {
    border: 1px solid #E2E8F0;
    padding: 16px 12px;
    font-size: 13.5px;
    font-weight: 600;
    color: #1E293B;
    vertical-align: middle;
    transition: all 0.2s ease;
}

.timetable td.day-col {
    background: #F8FAFC;
    color: #0F172A;
    font-weight: 800;
    font-size: 14px;
    border-right: 2px solid #CBD5E1;
}

.day-badge {
    display: inline-block;
    padding: 6px 14px;
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    color: #0F172A;
    font-weight: 800;
    font-size: 13px;
    letter-spacing: 0.5px;
}

/* Dynamic Cell Styling */
.slot-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 48px;
}

.slot-code {
    font-weight: 700;
    font-size: 13.5px;
    color: #0F172A;
}

.timetable td.editable {
    cursor: pointer;
    position: relative;
}

.timetable td.editable:hover {
    background: #FDF4FF;
    border-color: #D8B4FE;
    transform: scale(0.99);
}

.timetable td.editable:hover .slot-code {
    color: #7C3AED;
}

.timetable td.editable:hover::after {
    content: '\f044';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 6px;
    right: 8px;
    font-size: 10px;
    color: #A855F7;
    opacity: 0.8;
}

/* Lab Session Highlight */
.timetable td.lab-cell {
    background: linear-gradient(135deg, #F3E8FF 0%, #EDE9FE 100%);
    border-color: #DDD6FE;
    color: #6D28D9;
}

.timetable td.lab-cell .slot-code {
    color: #5B21B6;
    font-weight: 800;
}

.lab-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #FFFFFF;
    color: #7C3AED;
    border: 1px solid #DDD6FE;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

/* Empty Period Cell & Add Button */
.timetable td.empty-cell {
    background: #F8FAFC;
    border: 1.5px dashed #CBD5E1;
}

.timetable td.empty-cell.editable:hover {
    background: #F0FDF4;
    border-color: #10B981;
}

.empty-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #64748B;
    background: #FFFFFF;
    border: 1px dashed #CBD5E1;
    transition: all 0.2s ease;
}

.timetable td.empty-cell.editable:hover .empty-add-btn {
    color: #059669;
    background: #D1FAE5;
    border-color: #6EE7B7;
    box-shadow: 0 2px 5px rgba(5, 150, 105, 0.15);
    transform: scale(1.04);
}

.empty-code {
    color: #CBD5E1;
    font-size: 15px;
    font-weight: 600;
}

/* Modal Edit Box */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #FFFFFF;
    padding: 28px;
    border-radius: 16px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #F1F5F9;
}

.modal-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.modal-header h3 {
    color: #0F172A;
    font-size: 17px;
    font-weight: 700;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 10px;
    font-size: 13.5px;
    font-family: inherit;
    color: #0F172A;
    outline: none;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #7C3AED;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
    padding-top: 14px;
    border-top: 1px solid #F1F5F9;
}

@media print {
    .sidebar, .topbar, .action-btns, .role-badge, .info-banner { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .content-body { padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
    .page-header { border: none !important; box-shadow: none !important; padding: 0 0 15px 0 !important; }
    .timetable th { background: #E2E8F0 !important; color: #0F172A !important; -webkit-print-color-adjust: exact; }
    .timetable td.day-col { background: #F8FAFC !important; -webkit-print-color-adjust: exact; }
    .timetable td.lab-cell { background: #F1F5F9 !important; -webkit-print-color-adjust: exact; }
}

@media (max-width: 900px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Unified HOD Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>HOD Administration</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_hod.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records_list.php"><i class="fa-solid fa-user-graduate"></i> <span>Students Record</span></a></li>
            <li><a href="bio_data_list.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis_form_list.php"><i class="fa-solid fa-database"></i> <span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-headset"></i> <span>Student Grievances</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
            <li><a href="timetable.php" class="active"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="send_notice.php"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="hod_profile.php" style="color:#94A3B8;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>Profile</span>
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
            <h1>Department Timetable</h1>
            <p>Schedule management and academic hour allocations</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> <?=htmlspecialchars($role)?>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($hod_name)?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">

        <!-- Class & Department Selector Bar -->
        <div class="class-nav-container">
            <div class="class-nav-tabs">
                <?php foreach ($available_classes as $ckey => $cdata): ?>
                    <a href="timetable.php?dept=<?=urlencode($selected_dept)?>&class=<?=urlencode($ckey)?>" 
                       class="class-tab-btn <?=$selected_class === $ckey ? 'active' : ''?>">
                        <i class="fa-solid <?=$cdata['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i>
                        <span><?=htmlspecialchars($cdata['short'])?> (<?=htmlspecialchars($cdata['level'])?> <?=htmlspecialchars($cdata['year'])?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($is_admin): ?>
            <div class="dept-select-box">
                <i class="fa-solid fa-building-columns" style="color:#7C3AED;"></i>
                <label style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase;">Department:</label>
                <select onchange="location.href='timetable.php?dept=' + encodeURIComponent(this.value) + '&class=<?=urlencode($selected_class)?>'">
                    <?php foreach ($all_departments as $d): ?>
                        <option value="<?=htmlspecialchars($d['code'])?>" <?=$selected_dept === $d['code'] ? 'selected' : ''?>>
                            <?=htmlspecialchars($d['code'])?> - <?=htmlspecialchars($d['name'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="dept-select-box">
                <i class="fa-solid fa-building-columns" style="color:#7C3AED;"></i>
                <span style="font-size:12.5px; font-weight:700; color:#334155;">Dept: <b><?=htmlspecialchars($selected_dept)?></b></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-calendar-week"></i>
                </div>
                <div>
                    <h2><?=htmlspecialchars($class_info['label'])?></h2>
                    <p>Department of <?=htmlspecialchars($selected_dept)?> &nbsp;|&nbsp; <?=htmlspecialchars($class_info['sem'])?> &nbsp;|&nbsp; Academic Year: 2026 - 2027 &nbsp;|&nbsp; Section A</p>
                </div>
            </div>
            <div class="action-btns">
                <button class="btn btn-print" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print / PDF
                </button>
                <?php if ($is_hod): ?>
                    <button class="btn btn-approve" onclick="alert('Timetable for <?=htmlspecialchars($class_info['label'], ENT_QUOTES)?> Approved & Published Successfully!')">
                        <i class="fa-solid fa-circle-check"></i> Approve & Publish
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($can_edit): ?>
        <div class="info-banner">
            <div class="info-banner-left">
                <i class="fa-solid fa-circle-info"></i>
                <span><strong>Interactive Timetable:</strong> Click directly on any schedule slot in the table below to edit the subject code, faculty, or session duration.</span>
            </div>
            <div>
                <span style="font-size: 11.5px; font-weight: 700; color: #2563EB; background: #DBEAFE; padding: 4px 10px; border-radius: 20px;">
                    <i class="fa-solid fa-pen"></i> Edit Mode Active
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timetable Card -->
        <div class="card">
            <div class="table-wrapper">
                <table class="timetable">
                    <thead>
                        <tr>
                            <th class="day-header">DO / HRS</th>
                            <th>
                                1
                                <span class="period-time">09:30 - 10:30 AM</span>
                            </th>
                            <th>
                                2
                                <span class="period-time">10:30 - 11:30 AM</span>
                            </th>
                            <th>
                                3
                                <span class="period-time">11:45 - 12:45 PM</span>
                            </th>
                            <th>
                                4
                                <span class="period-time">01:30 - 02:30 PM</span>
                            </th>
                            <th>
                                5
                                <span class="period-time">02:30 - 03:30 PM</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable as $day => $slots): ?>
                            <tr>
                                <td class="day-col">
                                    <span class="day-badge">Day <?=htmlspecialchars($day)?></span>
                                </td>
                                <?php foreach ($slots as $slot): ?>
                                    <?php 
                                        $sub = trim((string)($slot['sub'] ?? ''));
                                        $isEmpty = ($sub === '' || $sub === '—' || $sub === '-');
                                    ?>
                                    <td colspan="<?php echo $slot['span']; ?>" 
                                        class="<?php echo ($slot['span'] > 1) ? 'lab-cell' : ''; ?> <?php echo $isEmpty ? 'empty-cell' : ''; ?> <?php echo $can_edit ? 'editable' : ''; ?>"
                                        <?php if ($can_edit): ?> onclick="openEditModal('<?php echo $day; ?>', <?php echo $slot['hour']; ?>, '<?php echo htmlspecialchars($sub, ENT_QUOTES); ?>', <?php echo $slot['span']; ?>)" <?php endif; ?>
                                        title="<?php echo $can_edit ? ($isEmpty ? 'Click to add subject' : 'Click to edit slot') : ''; ?>">
                                        <div class="slot-content">
                                            <?php if ($isEmpty): ?>
                                                <?php if ($can_edit): ?>
                                                    <span class="empty-add-btn"><i class="fa-solid fa-plus"></i> Add</span>
                                                <?php else: ?>
                                                    <span class="empty-code">—</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="slot-code"><?php echo htmlspecialchars($sub); ?></span>
                                                <?php if ($slot['span'] > 1): ?>
                                                    <span class="lab-tag"><i class="fa-solid fa-flask"></i> <?php echo $slot['span']; ?> Hrs Lab</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- HOD / FACULTY EDIT MODAL -->
<?php if ($can_edit): ?>
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <div>
                <h3 id="modal_title" style="margin:0; font-size:17px; font-weight:700;">Edit Schedule Slot</h3>
                <span id="modal_subtitle" style="font-size:12px; color:#64748B; font-weight:600;">Day I • Period 1</span>
            </div>
        </div>
        <form id="editForm" onsubmit="event.preventDefault(); saveSlot();">
            <input type="hidden" id="modal_day" name="day">
            <input type="hidden" id="modal_hour" name="hour">
            
            <div class="form-group">
                <label for="modal_subject">Subject & Staff Code</label>
                <input type="text" class="form-control" id="modal_subject" name="subject" required placeholder="e.g. DBMS(GKM) or — to clear">
                <small style="display:block; margin-top:5px; color:#94A3B8; font-size:11.5px;">Enter subject code (e.g. DBMS(GKM)) or '—' to leave period empty.</small>
            </div>

            <?php if ($is_hod): ?>
            <div class="form-group">
                <label for="modal_span">Span (Hours Count)</label>
                <select class="form-control" id="modal_span" name="span">
                    <option value="1">1 Hour (Single Period)</option>
                    <option value="2">2 Hours (Lab / Block)</option>
                    <option value="3">3 Hours (Full Session)</option>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" id="modal_span" value="1">
            <?php endif; ?>

            <div class="modal-footer">
                <button type="button" class="btn btn-print" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-approve"><i class="fa-solid fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openEditModal(day, hour, subject, span) {
    document.getElementById('modal_day').value = day;
    document.getElementById('modal_hour').value = hour;
    
    const isBlank = (!subject || subject === '—' || subject === '-');
    document.getElementById('modal_subject').value = isBlank ? '' : subject;
    
    const titleEl = document.getElementById('modal_title');
    if (titleEl) titleEl.textContent = isBlank ? 'Add Subject to Period' : 'Edit Schedule Slot';
    
    const subEl = document.getElementById('modal_subtitle');
    if (subEl) subEl.textContent = 'Day ' + day + ' • Period ' + hour;

    if(document.getElementById('modal_span')) {
        document.getElementById('modal_span').value = span || 1;
    }
    document.getElementById('editModal').style.display = 'flex';
    setTimeout(() => {
        const inp = document.getElementById('modal_subject');
        if (inp) inp.focus();
    }, 50);
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

const currentDept = "<?=htmlspecialchars($selected_dept, ENT_QUOTES)?>";
const currentClass = "<?=htmlspecialchars($selected_class, ENT_QUOTES)?>";

function saveSlot() {
    const day = document.getElementById('modal_day').value;
    const hour = document.getElementById('modal_hour').value;
    const subject = document.getElementById('modal_subject').value;
    const span = document.getElementById('modal_span') ? document.getElementById('modal_span').value : 1;

    fetch('timetable.php?dept=' + encodeURIComponent(currentDept) + '&class=' + encodeURIComponent(currentClass), {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            day: day, 
            hour: hour, 
            subject: subject, 
            span: span,
            dept: currentDept,
            class: currentClass
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Timetable Slot Updated Successfully!');
            location.reload();
        } else {
            alert('Error updating slot: ' + (data.message || data.error));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server connection error!');
    });
}
</script>

</body>
</html>