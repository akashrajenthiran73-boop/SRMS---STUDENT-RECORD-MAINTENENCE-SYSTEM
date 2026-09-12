<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  // SRMS/.env (Root Folder)
    __DIR__ . '/.env',                     // SRMS/HOD/.env
    $_SERVER['DOCUMENT_ROOT'] . '/SRMS/.env'
];

foreach ($possible_env_paths as $path) {
    if (file_exists($path)) {
        $parsed = @parse_ini_file($path);
        if ($parsed) {
            $env = $parsed;
            break;
        }
    }
}

$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'HOD'){
    header("Location: ../auth/login.php"); 
    exit;
}

// APPROVE LOGIC
if(isset($_GET['approve_id'])){
    $id = $_GET['approve_id'];
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/students?id=eq.$id";
    $data = json_encode(['status' => 'Approved']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);
    header("Location: student_records_list.php?msg=approved"); 
    exit;
}

function callSupabase($url, $key) {
    if (empty($url) || empty($key)) return [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["apikey: $key", "Authorization: Bearer $key"]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

$base_url = rtrim($SUPABASE_URL, '/');

// STEP 1: STUDENTS FETCH
$students = callSupabase("$base_url/rest/v1/students?select=*&order=created_at.desc", $SUPABASE_KEY);
if(!is_array($students)) $students = [];

// STEP 2: BIO_DATA FETCH
$bio_data = callSupabase("$base_url/rest/v1/bio_data?select=*", $SUPABASE_KEY);
if(!is_array($bio_data)) $bio_data = [];

// STEP 3: MERGE ARRAYS
foreach($students as &$s){
    $s['bio'] = []; 
    foreach($bio_data as $b){
        if(isset($s['exam_reg_no'], $b['tamil_reg_no']) && $s['exam_reg_no'] == $b['tamil_reg_no']){ 
            $s['bio'] = $b;
            break;
        }
    }
}
unset($s);

require_once __DIR__ . '/../includes/college_data.php';

$session_dept = $_SESSION['department'] ?? 'CS';
if (empty($session_dept) || $session_dept === 'BSC') $session_dept = 'CS';

$selected_year = $_GET['year'] ?? 'All';

// 1. Filter by HOD Department
$students = array_filter($students, function($s) use ($session_dept) {
    $c = strtoupper($s['course'] ?? '');
    if ($session_dept === 'CS') {
        return (strpos($c, 'CS') !== false || strpos($c, 'COMPUTER') !== false || strpos($c, 'B.SC') !== false || empty($c));
    }
    return (strpos($c, strtoupper($session_dept)) !== false);
});

// 2. Filter by Academic Year / Degree Level
if ($selected_year !== 'All' && !empty($students)) {
    $students = array_filter($students, function($s) use ($selected_year) {
        $info = get_student_year_info($s);
        return ($info['filter_tag'] === $selected_year || $info['level'] === $selected_year);
    });
}

$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
$current_page = basename($_SERVER['PHP_SELF']);
$lan_ip = gethostbyname(gethostname());
$server_port = $_SERVER['SERVER_PORT'] ?? '8000';
$lan_base_url = "http://" . $lan_ip . ($server_port != '80' && $server_port != '443' ? ":$server_port" : "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Records - HOD Portal</title>
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

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

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
    gap: 12px;
}

.page-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.page-header h1 {
    color: #0F172A;
    font-size: 18px;
    font-weight: 800;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 14px;
    color: #94A3B8;
    font-size: 13px;
}

.search-input {
    padding: 9px 14px 9px 38px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    color: #0F172A;
    outline: none;
    width: 220px;
    transition: all 0.2s ease;
}

.search-input:focus {
    border-color: #7C3AED;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    width: 260px;
}

.btn-back {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
}

.btn-back:hover {
    background: #E2E8F0;
    color: #0F172A;
}

.msg { 
    background: #ECFDF5; 
    border: 1px solid #A7F3D0;
    padding: 14px 20px; 
    border-radius: 12px; 
    margin-bottom: 24px; 
    color: #065F46; 
    font-weight: 600;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Table Container Card */
.table-card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

table { 
    width: 100%; 
    border-collapse: collapse; 
    text-align: left; 
}

th, td { 
    padding: 15px 20px; 
    font-size: 13px;
}

th { 
    background: #F8FAFC; 
    color: #475569; 
    font-weight: 700;
    border-bottom: 1px solid #E2E8F0;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.6px;
    white-space: nowrap;
}

td { 
    border-bottom: 1px solid #F1F5F9; 
    color: #334155;
    vertical-align: middle;
}

tr:last-child td {
    border-bottom: none;
}

tbody tr:hover td {
    background: #F8FAFC;
}

.student-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.adm-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    font-family: monospace;
    font-weight: 700;
    font-size: 12px;
    color: #0F172A;
}

.status-pill { 
    padding: 4px 10px; 
    border-radius: 20px; 
    font-size: 11.5px; 
    font-weight: 700; 
    text-transform: capitalize; 
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.status-pill.Pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.status-pill.Approved { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

.btn-action { 
    padding: 6px 12px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-size: 12px; 
    font-weight: 600; 
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-view { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }
.btn-view:hover { background: #E2E8F0; color: #0F172A; }

.btn-approve { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; }
.btn-approve:hover { opacity: 0.92; transform: translateY(-1px); }

.approved-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #059669;
    font-weight: 700;
    font-size: 12.5px;
    margin-right: 6px;
}

@media print {
    .sidebar, .topbar, .btn-back, .search-box, .btn-action, .header-actions { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .table-card { border: none !important; box-shadow: none !important; }
}

@media (max-width: 1024px) {
    .sidebar { width: 70px; }
    .sidebar-brand span, .sidebar-brand h2, .sidebar-menu span, .nav-category, .sidebar-footer span { display: none; }
    .sidebar-brand { justify-content: center; padding: 15px 0; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar, .content-body { padding-left: 20px; padding-right: 20px; }
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
            <li><a href="student_records_list.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Students Record</span></a></li>
            <li><a href="bio_data_list.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis_form_list.php"><i class="fa-solid fa-database"></i> <span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-headset"></i> <span>Student Grievances</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
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
    <div class="topbar">
        <div class="topbar-title">
            <h1>Student Records Management</h1>
            <p>Verify, approve, and manage departmental student admissions</p>
        </div>
        <div class="user-profile">
            <span style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-building-columns" style="color:#7C3AED;"></i> B.Sc CS Dept
            </span>
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> HOD Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($hod_name)?>
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <h1>Student Records & Approvals</h1>
                    <p>Total Registered: <b><?=count($students)?></b> students across all semesters</p>
                </div>
            </div>
            <div class="header-actions">
                <form method="GET" style="display:inline-flex; align-items:center; gap:8px;">
                    <select name="year" onchange="this.form.submit()" style="padding: 9px 14px; border: 1.5px solid #CBD5E1; border-radius: 9px; font-size: 13px; font-weight: 700; outline: none; background: #FFFFFF; color: #0F172A; cursor: pointer;">
                        <option value="All" <?=$selected_year === 'All' ? 'selected' : ''?>>All Classes & Years</option>
                        <option value="UG_1" <?=$selected_year === 'UG_1' ? 'selected' : ''?>>UG - 1st Year (I Year)</option>
                        <option value="UG_2" <?=$selected_year === 'UG_2' ? 'selected' : ''?>>UG - 2nd Year (II Year)</option>
                        <option value="UG_3" <?=$selected_year === 'UG_3' ? 'selected' : ''?>>UG - 3rd Year (III Year)</option>
                        <option value="PG_1" <?=$selected_year === 'PG_1' ? 'selected' : ''?>>PG - 1st Year (I M.Sc)</option>
                        <option value="PG_2" <?=$selected_year === 'PG_2' ? 'selected' : ''?>>PG - 2nd Year (II M.Sc)</option>
                    </select>
                </form>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="tableSearch" class="search-input" placeholder="Search student or roll...">
                </div>
                <button type="button" class="btn-back" style="background:#2563EB; color:white; border-color:#2563EB; cursor:pointer;" onclick="openHodShareModal()"><i class="fa-solid fa-share-nodes"></i> Share Form Link</button>
                <a href="dashboard_hod.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="msg"><i class="fa-solid fa-circle-check"></i> Student Approved Successfully!</div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-responsive">
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Academic Class</th>
                            <th>Roll / Reg No</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                   <?php if(count($students)>0): $i=1; foreach($students as $s): 
                        $raw_status = trim($s['status'] ?? 'Pending');
                        $status = ucfirst(strtolower(trim($raw_status, "'\" ")));
                        
                        $bio = $s['bio']; 
                        $yinfo = get_student_year_info($s);
                        
                        $admission_no = htmlspecialchars($s['admission_no']?? '-'); 
                        $name = htmlspecialchars($s['name']?? '-');
                        $course = htmlspecialchars($bio['course']?? $s['course']?? '-');
                        $roll_no = htmlspecialchars($bio['tamil_reg_no']?? $s['roll_no']?? '-'); 
                        $initial = strtoupper(substr($name, 0, 1));
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: #94A3B8;"><?=$i++?></td>
                        <td><span class="adm-badge"><?=$admission_no?></span></td>
                        <td>
                            <div class="student-name-cell">
                                <div class="student-avatar"><?=$initial !== '-' ? $initial : 'S'?></div>
                                <span style="font-weight: 600; color: #0F172A;"><?=$name?></span>
                            </div>
                        </td>
                        <td><?=$course?></td>
                        <td>
                            <span style="background: #F3E8FF; color: #7C3AED; font-weight: 700; font-size: 11.5px; padding: 4px 10px; border-radius: 6px; border: 1px solid #E9D5FF; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fa-solid <?=$yinfo['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($yinfo['short'])?>
                            </span>
                        </td>
                        <td style="font-family: monospace; font-weight: 600; color: #475569;"><?=$roll_no?></td>
                        <td>
                            <span class="status-pill <?=$status?>">
                                <i class="fa-solid fa-circle" style="font-size: 6px;"></i> <?=$status?>
                            </span>
                        </td>
                        <td>
                            <?php if($status === 'Pending'):?>
                                <a href="student_records_list.php?approve_id=<?=$s['id']?>" class="btn-action btn-approve" onclick="return confirm('Approve <?=$name?>?')"><i class="fa-solid fa-check"></i> Approve</a>
                            <?php else:?>
                                <span class="approved-tag"><i class="fa-solid fa-circle-check"></i> Approved</span>
                            <?php endif;?>
                            <a href="view_student.php?id=<?=$s['id']?>" class="btn-action btn-view"><i class="fa-solid fa-eye"></i> View</a>
                        </td>
                    </tr>
                    <?php endforeach; else:?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:50px 20px; color:#94A3B8;">
                            <i class="fa-solid fa-folder-open" style="font-size:32px; margin-bottom:10px; display:block; color:#CBD5E1;"></i>
                            No student records found matching selected class.
                        </td>
                    </tr>
                    <?php endif;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- HOD SHARE STUDENT FORM LINK MODAL -->
<div id="hodShareModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:18px; max-width:560px; width:92%; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.15); max-height:92vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
            <h3 style="font-size:17px; font-weight:800; color:#1E3A8A; display:flex; align-items:center; gap:8px; margin:0;">
                <i class="fa-solid fa-share-nodes" style="color:#2563EB;"></i> Share Link - Dept of <?php echo htmlspecialchars($session_dept); ?>
            </h3>
            <button onclick="closeHodShareModal()" style="background:none; border:none; font-size:18px; color:#64748B; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <p style="font-size:12.5px; color:#64748B; margin-bottom:14px;">
            Send this link to your department students so they can submit their <b>Student Record</b>, <b>Bio Data</b>, and <b>UMIS Details</b>.
        </p>

        <!-- DEVICE ACCESS MODE SELECTOR -->
        <div style="background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px; margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <label style="font-size:12px; font-weight:700; color:#1E293B; margin:0; display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-mobile-screen-button" style="color:#2563EB;"></i> Student Mobile Access Mode:
                </label>
                <span id="hod_access_badge" style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; background:#FEF3C7; color:#92400E;">💻 Local PC Only</span>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; margin-bottom:8px;">
                <button type="button" id="hod_btn_mode_local" onclick="selectHodAccessMode('local')" style="padding:7px 6px; font-size:11px; font-weight:600; border-radius:7px; border:1.5px solid #CBD5E1; background:white; cursor:pointer; text-align:center;">
                    💻 Localhost
                </button>
                <button type="button" id="hod_btn_mode_wifi" onclick="selectHodAccessMode('wifi')" style="padding:7px 6px; font-size:11px; font-weight:600; border-radius:7px; border:1.5px solid #CBD5E1; background:white; cursor:pointer; text-align:center;">
                    📶 Wi-Fi / Hotspot
                </button>
                <button type="button" id="hod_btn_mode_public" onclick="selectHodAccessMode('public')" style="padding:7px 6px; font-size:11px; font-weight:600; border-radius:7px; border:1.5px solid #CBD5E1; background:white; cursor:pointer; text-align:center;">
                    🌐 Public / Tunnel
                </button>
            </div>
            <div style="margin-bottom:6px;">
                <input type="text" id="hod_custom_domain_input" oninput="onHodCustomDomainChange()" placeholder="Enter Base URL (e.g. <?= htmlspecialchars($lan_base_url) ?> or https://...trycloudflare.com)" style="width:100%; padding:7px 10px; font-size:12px; border:1.5px solid #CBD5E1; border-radius:6px; font-family:monospace; background:white; box-sizing:border-box;">
            </div>
            <div id="hod_mode_help_text" style="font-size:11px; color:#64748B; line-height:1.4;">
                ⚠️ <b>Localhost:</b> Only opens on this laptop. Choose <b>Wi-Fi / Hotspot</b> for mobile phones connected to same Wi-Fi or laptop hotspot.
            </div>
        </div>

        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Select Target Class / Year:</label>
            <select id="hod_modal_year" onchange="updateHodShareLink()" style="width:100%; padding:8px 10px; border:1.5px solid #CBD5E1; border-radius:8px; font-size:13px;">
                <option value="">All Years (UG & PG)</option>
                <option value="UG_1">UG - 1st Year (I Year)</option>
                <option value="UG_2">UG - 2nd Year (II Year)</option>
                <option value="UG_3">UG - 3rd Year (III Year)</option>
                <option value="PG_1">PG - 1st Year (I PG)</option>
                <option value="PG_2">PG - 2nd Year (II PG)</option>
            </select>
        </div>
        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Shareable Link (Click to Copy):</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="hod_modal_link_input" readonly style="flex:1; padding:8px 12px; font-size:12.5px; background:#F8FAFC; border:1.5px solid #CBD5E1; border-radius:8px; font-family:monospace;">
                <button type="button" onclick="copyHodShareLink()" class="btn-back" style="background:#2563EB; color:white; border:none; padding:8px 14px; font-size:12.5px; cursor:pointer;"><i class="fa-solid fa-copy"></i> Copy</button>
            </div>
        </div>
        <div style="background:#F0FDF4; border:1px solid #BBF7D0; padding:12px; border-radius:10px; margin-bottom:16px;">
            <span style="font-size:12px; font-weight:700; color:#166534; display:block; margin-bottom:4px;"><i class="fa-brands fa-whatsapp" style="color:#16A34A;"></i> WhatsApp Ready Message:</span>
            <p id="hod_whatsapp_text_preview" style="font-size:11.5px; color:#14532D; white-space:pre-wrap; margin-bottom:8px; font-family:sans-serif;"></p>
            <button type="button" onclick="copyHodWhatsAppMessage()" class="btn-back" style="background:#16A34A; color:white; width:100%; border:none; justify-content:center; padding:8px; font-size:12.5px; cursor:pointer;"><i class="fa-brands fa-whatsapp"></i> Copy WhatsApp Message</button>
        </div>
        <div style="text-align:right;">
            <button onclick="closeHodShareModal()" class="btn-back" style="padding:7px 16px; cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
// Client-side quick filter
document.getElementById('tableSearch')?.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

const hodDept = '<?php echo addslashes($session_dept); ?>';
const hodServerLanUrl = '<?= $lan_base_url ?>';
const isHodLiveDomain = (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1');
const defaultHodMode = isHodLiveDomain ? 'public' : 'wifi';
let hodCurrentMode = localStorage.getItem('srms_access_mode') || defaultHodMode;

function openHodShareModal() {
    document.getElementById('hodShareModal').style.display = 'flex';
    selectHodAccessMode(hodCurrentMode);
}

function closeHodShareModal() {
    document.getElementById('hodShareModal').style.display = 'none';
}

function selectHodAccessMode(mode) {
    hodCurrentMode = mode;
    localStorage.setItem('srms_access_mode', mode);

    const btnLocal = document.getElementById('hod_btn_mode_local');
    const btnWifi = document.getElementById('hod_btn_mode_wifi');
    const btnPublic = document.getElementById('hod_btn_mode_public');
    const input = document.getElementById('hod_custom_domain_input');
    const badge = document.getElementById('hod_access_badge');
    const help = document.getElementById('hod_mode_help_text');

    // Reset styles
    [btnLocal, btnWifi, btnPublic].forEach(b => {
        b.style.background = '#FFFFFF';
        b.style.color = '#334155';
        b.style.borderColor = '#CBD5E1';
    });

    if (mode === 'local') {
        btnLocal.style.background = '#2563EB';
        btnLocal.style.color = '#FFFFFF';
        btnLocal.style.borderColor = '#2563EB';
        input.value = window.location.origin;
        input.readOnly = true;
        badge.innerText = '💻 Local PC Only';
        badge.style.background = '#FEF3C7';
        badge.style.color = '#92400E';
        help.innerHTML = '⚠️ <b>Localhost:</b> Only opens on this laptop. Students on mobile will <b>not</b> be able to open this.';
    } else if (mode === 'wifi') {
        btnWifi.style.background = '#2563EB';
        btnWifi.style.color = '#FFFFFF';
        btnWifi.style.borderColor = '#2563EB';
        input.value = hodServerLanUrl;
        input.readOnly = true;
        badge.innerText = '📶 Same Wi-Fi / Hotspot';
        badge.style.background = '#DBEAFE';
        badge.style.color = '#1E40AF';
        help.innerHTML = '📶 <b>Same Wi-Fi / Hotspot:</b> Students can open this link on their mobile if connected to your <b>Laptop Hotspot</b> or the same <b>College Wi-Fi</b>.';
    } else if (mode === 'public') {
        btnPublic.style.background = '#2563EB';
        btnPublic.style.color = '#FFFFFF';
        btnPublic.style.borderColor = '#2563EB';
        input.readOnly = false;
        let savedPublic = localStorage.getItem('srms_custom_public_url') || (isHodLiveDomain ? window.location.origin : '');
        input.value = savedPublic;
        badge.innerText = '🌐 Public / Live';
        badge.style.background = '#DCFCE7';
        badge.style.color = '#15803D';
        help.innerHTML = isHodLiveDomain
            ? '🌐 <b>Live Cloud Domain:</b> Running on Render! Links work on <b>all student mobile phones anywhere</b>.'
            : '🌐 <b>Public / Tunnel URL:</b> Paste Cloudflare Tunnel, Render URL, Localtunnel, or Live Domain here. Students can open from <b>any mobile anywhere</b> (Jio, Airtel, etc.)!';
        if (!savedPublic) {
            input.focus();
        }
    }

    updateHodShareLink();
}

function onHodCustomDomainChange() {
    if (hodCurrentMode === 'public') {
        localStorage.setItem('srms_custom_public_url', document.getElementById('hod_custom_domain_input').value.trim());
    }
    updateHodShareLink();
}

function updateHodShareLink() {
    const year = document.getElementById('hod_modal_year').value;
    
    let base = document.getElementById('hod_custom_domain_input')?.value.trim() || window.location.origin;
    base = base.replace(/\/+$/, ''); // Strip trailing slash

    let portalPath = '/portal/student_entry.php?dept=' + encodeURIComponent(hodDept);
    if (window.location.pathname.includes('/SRMS/')) {
        portalPath = '/SRMS/portal/student_entry.php?dept=' + encodeURIComponent(hodDept);
    }

    let url = base.includes('/portal/student_entry.php') ? base : (base + portalPath);
    if (!url.includes('dept=')) {
        url += (url.includes('?') ? '&' : '?') + 'dept=' + encodeURIComponent(hodDept);
    }

    if (year) {
        url += '&year=' + encodeURIComponent(year);
    }
    document.getElementById('hod_modal_link_input').value = url;

    let targetDesc = `Department of ${hodDept} Students`;
    if (year) targetDesc = `${year} (${hodDept}) Students`;

    const msg = `🎓 *Arignar Anna Govt Arts College, Villupuram*\n\n📢 *Attention ${targetDesc}:*\nPlease fill your official *Student Record, Bio Data & UMIS details* online using this link:\n👉 ${url}\n\n⚠️ *Mandatory Details:* Please keep your Register Number, Email, EMIS No & Bank Details ready before submitting.`;
    document.getElementById('hod_whatsapp_text_preview').innerText = msg;
}

function copyHodShareLink() {
    const link = document.getElementById('hod_modal_link_input').value;
    navigator.clipboard.writeText(link).then(() => alert("Link copied to clipboard!"));
}

function copyHodWhatsAppMessage() {
    const msg = document.getElementById('hod_whatsapp_text_preview').innerText;
    navigator.clipboard.writeText(msg).then(() => alert("WhatsApp message copied to clipboard!"));
}
</script>

</body>
</html>