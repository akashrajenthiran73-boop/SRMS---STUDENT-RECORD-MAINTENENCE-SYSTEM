<?php 
session_start(); 
ini_set('display_errors', 1); 
error_reporting(E_ALL);

if(!isset($_SESSION['user_id'])) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

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

// Function to get count from Supabase
function getSupabaseCount($url, $key, $query_params = ''){
    if (empty($url) || empty($key)) return 0;
    $ch = curl_init($url . "/rest/v1/umis_students?select=student_id" . $query_params);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_HTTPHEADER => [
            "apikey: $key", 
            "Authorization: Bearer $key",
            "Prefer: count=exact"
        ],
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    curl_close($ch);
    
    if (preg_match('/content-range:\s*.*\/(\d+)/i', $header, $matches)) {
        return intval($matches[1]);
    }
    return 0;
}

$base_url = rtrim($SUPABASE_URL, '/');
$total_students = getSupabaseCount($base_url, $SUPABASE_KEY);
$pending_count = getSupabaseCount($base_url, $SUPABASE_KEY, '&is_completed=is.false');

// Assign HOD Name and Department from Session safely
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
$hod_dept = $_SESSION['department'] ?? 'Computer Science';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HOD Dashboard - SRMS Portal</title>
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

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

/* Welcome Greeting */
.welcome-banner { background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%); border-radius: 18px; padding: 28px 32px; color: white; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(30, 27, 75, 0.15); }
.welcome-banner::after { content: ''; position: absolute; right: -30px; top: -30px; width: 180px; height: 180px; background: rgba(255,255,255,0.04); border-radius: 50%; }
.welcome-banner h2 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
.welcome-banner p { font-size: 13.5px; color: #C7D2FE; }

/* Stat Cards */
.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 28px; }
@media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
.stat-box { background: #FFFFFF; padding: 22px 26px; border-radius: 16px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); display: flex; justify-content: space-between; align-items: center; }
.stat-info h4 { color: #64748B; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.stat-info .num { font-size: 32px; font-weight: 800; color: #0F172A; }
.stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.stat-students .stat-icon { background: #EFF6FF; color: #2563EB; }
.stat-pending .stat-icon { background: #FEF3C7; color: #D97706; }

/* Grid of Action Cards */
.section-title { font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.section-title i { color: #7C3AED; }
.grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
@media (max-width: 1200px) { .grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }

.card-link { text-decoration: none; }
.card { background: #FFFFFF; border: 1px solid #E2E8F0; padding: 26px 20px; border-radius: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); transition: all 0.25s ease; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06); border-color: #CBD5E1; }

.card .icon-wrap { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 14px; transition: transform 0.2s ease; }
.card:hover .icon-wrap { transform: scale(1.08); }

.card-blue .icon-wrap { background: #EFF6FF; color: #2563EB; }
.card-purple .icon-wrap { background: #F3E8FF; color: #7C3AED; }
.card-emerald .icon-wrap { background: #ECFDF5; color: #059669; }
.card-amber .icon-wrap { background: #FEF3C7; color: #D97706; }

.card h3 { font-size: 15px; font-weight: 700; color: #0F172A; margin-bottom: 6px; }
.card p { font-size: 12.5px; color: #64748B; font-weight: 500; }

/* Notice Banner */
.notice-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 22px 28px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03); flex-wrap: wrap; gap: 16px; }
.notice-info h3 { font-size: 15px; font-weight: 700; color: #0F172A; margin-bottom: 4px; }
.notice-info p { font-size: 12.5px; color: #64748B; }
.notice-btn { padding: 11px 22px; background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 13.5px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25); display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.notice-btn:hover { opacity: 0.95; transform: translateY(-1px); }
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
            <li><a href="dashboard_hod.php" class="active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="student_records_list.php"><i class="fa-solid fa-user-graduate"></i> Students Record</a></li>
            <li><a href="bio_data_list.php"><i class="fa-solid fa-address-card"></i> Bio Data</a></li>
            <li><a href="umis_form_list.php"><i class="fa-solid fa-database"></i> UMIS Data</a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> Result Analysis</a></li>

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> Circulars & Notices</a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> Events & Calendar</a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-headset"></i> Student Grievances</a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> Leave Approvals</a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="send_notice.php"><i class="fa-solid fa-paper-plane"></i> Send Notice</a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> Announcements</a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> Help & Support</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="hod_profile.php" class="sidebar-menu" style="display:flex; align-items:center; gap:12px; color:#94A3B8; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> Profile
        </a>
        <a href="../auth/logout.php" style="display:flex; align-items:center; gap:12px; color:#F87171; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Department Control Panel</h1>
            <p>Head of Department executive operations and student records management</p>
        </div>
        <div class="user-profile">
            <span style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-building-columns" style="color:#7C3AED;"></i> <?=htmlspecialchars($hod_dept)?> Dept
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
        
        <div class="welcome-banner">
            <div>
                <div style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.12); padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; margin-bottom:8px; border:1px solid rgba(255,255,255,0.2);">
                    <i class="fa-solid fa-laptop-code"></i> Department of <?=htmlspecialchars($hod_dept)?>
                </div>
                <h2>Welcome Back, <?=htmlspecialchars($hod_name)?> 👋</h2>
                <p>Monitor departmental academics, college circulars, student grievances, and timetable schedules.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-box stat-students">
                <div class="stat-info">
                    <h4>Total Registered Students (CS)</h4>
                    <div class="num"><?=intval($total_students)?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div class="stat-box stat-pending">
                <div class="stat-info">
                    <h4>Pending Form Approvals</h4>
                    <div class="num"><?=intval($pending_count)?></div>
                </div>
                <div class="stat-icon">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        <div class="section-title">
            <i class="fa-solid fa-shapes"></i> Department Management Modules
        </div>

        <div class="grid">
            <a href="student_records_list.php" class="card-link">
                <div class="card card-blue">
                    <div class="icon-wrap"><i class="fa-solid fa-user-graduate"></i></div>
                    <h3>Student Records</h3>
                    <p>View All Registered Students</p>
                </div>
            </a>

            <a href="bio_data_list.php" class="card-link">
                <div class="card card-purple">
                    <div class="icon-wrap"><i class="fa-solid fa-address-card"></i></div>
                    <h3>Bio Data Details</h3>
                    <p>View & Approve Changes</p>
                </div>
            </a>

            <a href="umis_form_list.php" class="card-link">
                <div class="card card-emerald">
                    <div class="icon-wrap"><i class="fa-solid fa-database"></i></div>
                    <h3>UMIS Form Details</h3>
                    <p>View + Export PDF/Excel</p>
                </div>
            </a>

            <a href="result_analysis.php" class="card-link">
                <div class="card card-amber">
                    <div class="icon-wrap"><i class="fa-solid fa-chart-line"></i></div>
                    <h3>Result Analysis</h3>
                    <p>Pass/Fail & Semester Stats</p>
                </div>
            </a>
        </div>

        <div class="section-title" style="margin-top: 10px;">
            <i class="fa-solid fa-building-columns"></i> College Operations & Support
        </div>

        <div class="grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 28px;">
            <a href="circulars.php" class="card-link">
                <div class="card card-purple">
                    <div class="icon-wrap"><i class="fa-solid fa-bullhorn"></i></div>
                    <h3>Circulars & Notices</h3>
                    <p>College & Dept Circulars</p>
                </div>
            </a>

            <a href="events.php" class="card-link">
                <div class="card card-blue">
                    <div class="icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
                    <h3>Academic Events</h3>
                    <p>Symposiums & Key Dates</p>
                </div>
            </a>

            <a href="grievances.php" class="card-link">
                <div class="card card-amber">
                    <div class="icon-wrap"><i class="fa-solid fa-headset"></i></div>
                    <h3>Student Grievances</h3>
                    <p>Review & Resolve Tickets</p>
                </div>
            </a>
        </div>

        <div class="notice-card">
            <div class="notice-info">
                <h3>Broadcast Department Notice</h3>
                <p>Send instant announcements and academic notifications directly to students and staff.</p>
            </div>
            <a href="send_notice.php" class="notice-btn">
                <i class="fa-solid fa-paper-plane"></i> Send Notice to Students
            </a>
        </div>

    </div>
</div>

</body>
</html>