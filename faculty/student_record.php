<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Faculty Role Check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Faculty'){
    header("Location: ../auth/login.php"); 
    exit;
}

// 1. Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  // SRMS/.env (Root Folder)
    __DIR__ . '/.env',                     // SRMS/Faculty/.env
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

// 1. Filter by Faculty Department
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

$faculty_name = $_SESSION['name'] ?? 'Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Students Records - Faculty Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

body {
    background-color: #F8FAFC;
    color: #1E293B;
    min-height: 100vh;
    display: flex;
}

/* Sidebar Styles */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #0B132B;
    color: white;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.05);
    z-index: 100;
}

.sidebar-brand {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}
.sidebar-brand h2 {
    color: #F59E0B;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.sidebar-brand span {
    font-size: 11px;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.sidebar-nav-container {
    flex-grow: 1;
    overflow-y: auto;
    margin-top: 15px;
    padding-right: 4px;
}

.sidebar-nav-container::-webkit-scrollbar {
    width: 4px;
}
.sidebar-nav-container::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}

.sidebar-menu {
    list-style: none;
}

.sidebar-menu li {
    margin-bottom: 4px;
}

.menu-category {
    font-size: 10px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 14px 14px 6px 14px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    color: #94A3B8;
    text-decoration: none;
    font-weight: 500;
    font-size: 13.5px;
    border-radius: 10px;
    transition: all 0.25s ease;
}

.sidebar-menu a:hover {
    color: #F8FAFC;
    background: rgba(255, 255, 255, 0.06);
}

.sidebar-menu a.active {
    background: #2563EB;
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.sidebar-menu a i {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.sidebar-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

/* Main Content Area */
.main-content {
    margin-left: 260px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.topbar {
    background: #FFFFFF;
    padding: 18px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #E2E8F0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    position: sticky;
    top: 0;
    z-index: 50;
}

.topbar h2 {
    color: #1E3A8A;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.role-badge {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.content-body {
    padding: 35px 40px;
    flex: 1;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    color: #0F172A;
    font-size: 24px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
}

.search-box {
    display: flex;
    align-items: center;
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 10px;
    padding: 8px 14px;
    gap: 10px;
    width: 280px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.search-box input {
    border: none;
    outline: none;
    font-size: 13.5px;
    width: 100%;
    color: #1E293B;
}

.search-box i {
    color: #94A3B8;
}

.table-card {
    background: #FFFFFF;
    border-radius: 18px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.03);
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
    padding: 16px 20px; 
    font-size: 13.5px;
}

th { 
    background: #F8FAFC; 
    color: #475569; 
    font-weight: 700;
    border-bottom: 1px solid #E2E8F0;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
}

td { 
    border-bottom: 1px solid #F1F5F9; 
    color: #334155;
    vertical-align: middle;
}

tr:last-child td { border-bottom: none; }
tr:hover td { background: #F8FAFC; }

.btn { 
    padding: 7px 14px; 
    border-radius: 8px; 
    text-decoration: none; 
    margin-right: 4px; 
    font-size: 12.5px; 
    font-weight: 600; 
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    border: none;
}

.btn-view { background: #2563EB; color: white; }
.btn-view:hover { background: #1D4ED8; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25); }

.btn-whatsapp { background: #25D366; color: white; }
.btn-whatsapp:hover { background: #20BA5A; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); }

.btn-msg { background: #8B5CF6; color: white; }
.btn-msg:hover { background: #7C3AED; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.25); }

.badge-tag {
    background: #F1F5F9;
    color: #475569;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

@media(max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Sidebar Menu -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Faculty Portal</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <li><a href="student_record.php" class="active"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-clipboard-user"></i><span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i><span>Result Analysis</span></a></li>

            <div class="menu-category">College & Dept</div>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i><span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i><span>Events & Calendar</span></a></li>

            <div class="menu-category">Faculty Panel</div>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-user-check"></i><span>Student Leave Requests</span></a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-file-pen"></i><span>Apply Leave</span></a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-chart-line"></i><span>Marks CIA</span></a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book"></i><span>My Subjects</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open-reader"></i><span>Syllabus & Materials</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i><span>Assignments</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i><span>Timetable</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i><span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i><span>Help & Support</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="faculty_profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
        </ul>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    <div class="topbar">
        <h2><i class="fa-solid fa-users-rectangle"></i> Student Records Management</h2>
        <div class="topbar-right">
            <span style="background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-laptop-code"></i> Department of Computer Science
            </span>
            <div class="role-badge"><i class="fa-solid fa-shield-halved"></i> Role: Faculty</div>
        </div>
    </div>

    <div class="content-body">
        <div class="page-header">
            <div>
                <h1><i class="fa-solid fa-user-graduate" style="color:#2563EB;"></i> Assigned Student Directory</h1>
                <p style="color: #64748B; font-size: 13.5px; margin-top: 4px;">Department: <b><?=htmlspecialchars($session_dept)?></b> &nbsp;|&nbsp; Total Filtered Students: <b><?=count($students)?></b></p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <form method="GET" style="display:inline-flex; align-items:center; gap:6px;">
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
                    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search students...">
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Academic Class</th>
                            <th>Roll / Reg No</th>
                            <th>Parent Contact</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($students) > 0): $i = 1; foreach($students as $s): 
                        $bio = $s['bio'] ?? []; 
                        $yinfo = get_student_year_info($s);
                        $admission_no = htmlspecialchars($s['admission_no'] ?? '-'); 
                        $name = htmlspecialchars($s['name'] ?? '-');
                        $course = htmlspecialchars($bio['course'] ?? $s['course'] ?? '-');
                        $roll_no = htmlspecialchars($bio['tamil_reg_no'] ?? $s['roll_no'] ?? '-');
                        $parent_phone = trim($s['parent_phone'] ?? $bio['parent_phone'] ?? '');
                    ?>
                    <tr>
                        <td><?=$i++?></td>
                        <td><span class="badge-tag"><b><?=$admission_no?></b></span></td>
                        <td style="font-weight: 700; color: #0F172A;"><?=$name?></td>
                        <td><?=$course?></td>
                        <td>
                            <span style="background: #EFF6FF; color: #1D4ED8; font-weight: 700; font-size: 11.5px; padding: 4px 10px; border-radius: 6px; border: 1px solid #BFDBFE; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fa-solid <?=$yinfo['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($yinfo['short'])?>
                            </span>
                        </td>
                        <td><?=$roll_no?></td>
                        <td>
                            <?php if(!empty($parent_phone)): ?>
                                <span style="font-weight: 600; color: #1E293B;"><i class="fa-solid fa-phone" style="color:#2563EB; font-size:12px;"></i> <?=htmlspecialchars($parent_phone)?></span>
                            <?php else: ?>
                                <span style="color: #94A3B8;"><i class="fa-solid fa-phone-slash" style="font-size:12px;"></i> Not Available</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="view_student.php?id=<?=$s['id']?>" class="btn btn-view">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if(!empty($parent_phone)): ?>
                                <a href="https://wa.me/91<?=preg_replace('/[^0-9]/', '', $parent_phone)?>?text=Hello%20Respected%20Parent,%20Regarding%20student%20<?=urlencode($name)?>" target="_blank" class="btn btn-whatsapp" title="WhatsApp Parent">
                                    <i class="fa-brands fa-whatsapp"></i> WhatsApp
                                </a>
                                <a href="sms:<?=htmlspecialchars($parent_phone)?>?body=Hello%20Parent,%20Regarding%20student%20<?=urlencode($name)?>" class="btn btn-msg" title="SMS Parent">
                                    <i class="fa-solid fa-comment-sms"></i> SMS
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:50px 20px; color:#64748B;">No Students Found Matching Selected Criteria</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function filterTable() {
    var input = document.getElementById("searchInput");
    var filter = input.value.toLowerCase();
    var table = document.getElementById("studentTable");
    var tr = table.getElementsByTagName("tr");

    for (var i = 1; i < tr.length; i++) {
        var text = tr[i].textContent || tr[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}
</script>

</body>
</html>