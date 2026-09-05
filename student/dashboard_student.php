<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Safe Session & Student Role Check
$session_user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? '');
$session_role = trim($_SESSION['role'] ?? '');

$is_student = (strcasecmp($session_role, 'Student') === 0);

if (empty($session_user_id) || !$is_student) {
    header("Location: ../auth/login.php"); 
    exit;
}

// Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  
    __DIR__ . '/.env',                     
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
        CURLOPT_HTTPHEADER => [
            "apikey: $key", 
            "Authorization: Bearer $key",
            "Content-Type: application/json"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?: [];
}

// Fetch Logged-in Student Details strictly matching session ID
$student = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY) && !empty($session_user_id)) {
    $users = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id) . "&select=*", $SUPABASE_KEY);
    if (!empty($users)) {
        $student = $users[0];
    }
}

$student_name = $student['name'] ?? 'Student';
$student_email = $student['email'] ?? '';
$reg_no = $student['reg_no'] ?? $student['faculty_id'] ?? 'REG-N/A';
$department = $student['department'] ?? 'Computer Science';
$results = "view";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard - SRMS Portal</title>
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

/* Welcome Banner Hero */
.hero-banner {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    border-radius: 20px;
    padding: 32px 36px;
    color: #FFFFFF;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.hero-banner::after {
    content: '';
    position: absolute;
    right: -40px;
    bottom: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0) 70%);
    pointer-events: none;
}

.hero-text h1 {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 6px;
}

.hero-text p {
    color: #94A3B8;
    font-size: 13.5px;
}

.hero-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.hero-chip {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 12.5px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #E2E8F0;
}

.hero-chip i {
    color: #34D399;
}

/* 4 Core Module Cards */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.module-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 24px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 155px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.module-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.06);
    border-color: #CBD5E1;
}

.module-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.module-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #FFFFFF;
}

.icon-emerald { background: linear-gradient(135deg, #10B981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); }
.icon-amber   { background: linear-gradient(135deg, #F59E0B, #D97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25); }
.icon-blue    { background: linear-gradient(135deg, #3B82F6, #1D4ED8); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25); }
.icon-purple  { background: linear-gradient(135deg, #8B5CF6, #6D28D9); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25); }

.module-info h3 {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin-top: 14px;
    margin-bottom: 4px;
}

.module-info p {
    font-size: 12.5px;
    color: #64748B;
}

.module-card-arrow {
    align-self: flex-end;
    font-size: 13px;
    font-weight: 700;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
    transition: gap 0.2s;
}

.module-card:hover .module-card-arrow {
    gap: 9px;
}

/* Two Column Bottom Section */
.bottom-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 24px;
}

@media (max-width: 992px) {
    .bottom-grid { grid-template-columns: 1fr; }
}

.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px solid #F1F5F9;
}

.card-title i {
    color: #059669;
}

/* Quick Actions Grid */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}

.action-item {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #1E293B;
    transition: all 0.2s ease;
}

.action-item:hover {
    background: #FFFFFF;
    border-color: #A7F3D0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.08);
}

.action-item i {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #ECFDF5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}

.action-item span {
    font-size: 13px;
    font-weight: 600;
}

/* Profile Key-Value Table */
.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table tr {
    border-bottom: 1px solid #F1F5F9;
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table td {
    padding: 12px 6px;
    font-size: 13.5px;
}

.info-table td.label {
    color: #64748B;
    font-weight: 600;
    width: 40%;
}

.info-table td.value {
    color: #0F172A;
    font-weight: 700;
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
            <li><a href="dashboard_student.php" class="active"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
            <li><a href="marks.php"><i class="fa-solid fa-award"></i> <span>Marks / Grades</span></a></li>

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
            <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
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
            <h1>Student Dashboard</h1>
            <p>Welcome to your academic records and services workspace</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($student_name)?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Hero Welcome Banner -->
        <div class="hero-banner">
            <div class="hero-text">
                <h1>Welcome, <?=htmlspecialchars($student_name)?>!</h1>
                <p>Track your semester progress, attendance, grades, and departmental notices in one place.</p>
            </div>
            <div class="hero-meta">
                <div class="hero-chip">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Reg No: <strong><?=htmlspecialchars($reg_no)?></strong></span>
                </div>
                <div class="hero-chip">
                    <i class="fa-solid fa-building-columns"></i>
                    <span><?=htmlspecialchars($department)?></span>
                </div>
            </div>
        </div>

        <!-- 4 Primary Module Cards -->
        <div class="modules-grid">
            <a href="student_record.php" class="module-card">
                <div class="module-card-top">
                    <div class="module-icon icon-emerald">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                </div>
                <div class="module-info">
                    <h3>Student Record</h3>
                    <p>Academic profile, semester history & credits</p>
                </div>
                <div class="module-card-arrow">
                    <span>View Record</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <a href="bio_data.php" class="module-card">
                <div class="module-card-top">
                    <div class="module-icon icon-amber">
                        <i class="fa-solid fa-address-card"></i>
                    </div>
                </div>
                <div class="module-info">
                    <h3>Bio Data</h3>
                    <p>Personal, family & socio-economic profile</p>
                </div>
                <div class="module-card-arrow">
                    <span style="color: #D97706;">Manage Bio</span>
                    <i class="fa-solid fa-arrow-right" style="color: #D97706;"></i>
                </div>
            </a>

            <a href="umis.php" class="module-card">
                <div class="module-card-top">
                    <div class="module-icon icon-blue">
                        <i class="fa-solid fa-database"></i>
                    </div>
                </div>
                <div class="module-info">
                    <h3>UMIS Details</h3>
                    <p>University Management Information forms</p>
                </div>
                <div class="module-card-arrow">
                    <span style="color: #2563EB;">View UMIS</span>
                    <i class="fa-solid fa-arrow-right" style="color: #2563EB;"></i>
                </div>
            </a>

            <a href="result_analysis.php" class="module-card">
                <div class="module-card-top">
                    <div class="module-icon icon-purple">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
                <div class="module-info">
                    <h3>Result Analysis</h3>
                    <p>CIA performance charts & semester GPA</p>
                </div>
                <div class="module-card-arrow">
                    <span style="color: #7C3AED;">Analyze Marks</span>
                    <i class="fa-solid fa-arrow-right" style="color: #7C3AED;"></i>
                </div>
            </a>
        </div>

        <!-- Bottom Grid: Quick Actions & Profile Overview -->
        <div class="bottom-grid">
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-title">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Quick Academic Actions</span>
                </div>
                <div class="quick-actions">
                    <a href="timetable.php" class="action-item">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Class Timetable</span>
                    </a>
                    <a href="marks.php" class="action-item">
                        <i class="fa-solid fa-award"></i>
                        <span>Marks / Results</span>
                    </a>
                    <a href="assignments.php" class="action-item">
                        <i class="fa-solid fa-file-pen"></i>
                        <span>Submit Assignment</span>
                    </a>
                    <a href="student_leave.php" class="action-item">
                        <i class="fa-solid fa-envelope-open-text"></i>
                        <span>Apply Leave / OD</span>
                    </a>
                    <a href="syllabus_materials.php" class="action-item">
                        <i class="fa-solid fa-book-open"></i>
                        <span>Study Materials</span>
                    </a>
                    <a href="download_certificates.php" class="action-item">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <span>Download Certs</span>
                    </a>
                </div>
            </div>

            <!-- Profile Summary -->
            <div class="card">
                <div class="card-title">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Quick Profile Summary</span>
                </div>
                <table class="info-table">
                    <tr>
                        <td class="label">Full Name</td>
                        <td class="value"><?=htmlspecialchars($student_name)?></td>
                    </tr>
                    <tr>
                        <td class="label">Register No</td>
                        <td class="value"><?=htmlspecialchars($reg_no)?></td>
                    </tr>
                    <tr>
                        <td class="label">Email Address</td>
                        <td class="value"><?=htmlspecialchars($student_email)?></td>
                    </tr>
                    <tr>
                        <td class="label">Department</td>
                        <td class="value"><?=htmlspecialchars($department)?></td>
                    </tr>
                </table>
                <div style="margin-top: 18px; text-align: right;">
                    <a href="student_profile.php" style="font-size: 12.5px; font-weight: 700; color: #059669; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                        <span>View Full Profile</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>

        </div>

    </div>
</div>

</body>
</html>