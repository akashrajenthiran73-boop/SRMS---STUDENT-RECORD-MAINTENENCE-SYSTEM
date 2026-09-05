<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Faculty Role Check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Faculty'){
    header("Location: login.php"); 
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

$faculty_name = $_SESSION['name'] ?? 'Faculty';
$faculty_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? '');

// Fetch dynamic counts if Supabase is connected
$total_students = 0;
$total_subjects = 0;
$pending_leaves = 0;
$result_analysis = "view"; // Default placeholder or fetch logic

if(!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    // Optional: You can fetch counts here from your tables if needed
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faculty Dashboard - SRMS</title>
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
    font-size: 22px;
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

.welcome-banner {
    background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 100%);
    border-radius: 18px;
    padding: 28px 32px;
    color: white;
    margin-bottom: 30px;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.welcome-banner h1 {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.3px;
    margin-bottom: 6px;
}

.welcome-banner p {
    font-size: 14px;
    opacity: 0.9;
    font-weight: 500;
}

.welcome-badge {
    background: rgba(255, 255, 255, 0.18);
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.25);
}

/* Dashboard Cards Grid */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 35px;
}

.stat-card {
    border-radius: 18px;
    padding: 26px;
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 160px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    position: relative;
    overflow: hidden;
    transition: all 0.25s ease;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.12);
}

.stat-card.blue { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); }
.stat-card.purple { background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%); }
.stat-card.green { background: linear-gradient(135deg, #10B981 0%, #047857 100%); }
.stat-card.orange { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); }

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.card-icon {
    background: rgba(255, 255, 255, 0.22);
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.card-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    opacity: 0.95;
}

.card-value {
    font-size: 22px;
    font-weight: 800;
    margin-top: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cards-grid a {
    text-decoration: none;
    color: inherit;
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
            <li><a href="dashboard_faculty.php" class="active"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-clipboard-user"></i><span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i><span>Result Analysis</span></a></li>

            <div class="menu-category">Faculty Panel</div>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-user-check"></i><span>Student Leave Requests</span></a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-file-pen"></i><span>Apply Leave</span></a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-chart-line"></i><span>Marks CIA</span></a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book"></i><span>My Subjects</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open-reader"></i><span>Syllabus & Materials</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i><span>Assignments</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i><span>Timetable</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>
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
        <h2><i class="fa-solid fa-chalkboard-user"></i> Faculty Portal</h2>
        <div class="topbar-right">
            <div class="role-badge"><i class="fa-solid fa-shield-halved"></i> Role: Faculty</div>
        </div>
    </div>

    <div class="content-body">
        <div class="welcome-banner">
            <div>
                <h1>Welcome back, <?=htmlspecialchars($faculty_name)?>!</h1>
                <p>Arignar Anna College Student Record Maintenance System</p>
            </div>
            <div class="welcome-badge">
                <i class="fa-regular fa-calendar-check"></i> Academic Session Active
            </div>
        </div>

        <!-- 4 Stat Cards with Links -->
        <div class="cards-grid">
            <a href="student_record.php">
                <div class="stat-card blue">
                    <div class="card-top">
                        <span class="card-title">Students Record</span>
                        <div class="card-icon"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="card-value">View Details <i class="fa-solid fa-arrow-right" style="font-size: 16px;"></i></div>
                </div>
            </a>

            <a href="bio_data.php">
                <div class="stat-card purple">
                    <div class="card-top">
                        <span class="card-title">Bio Data</span>
                        <div class="card-icon"><i class="fa-solid fa-id-card"></i></div>
                    </div>
                    <div class="card-value">View Bio Data <i class="fa-solid fa-arrow-right" style="font-size: 16px;"></i></div>
                </div>
            </a>

            <a href="umis_data.php">
                <div class="stat-card green">
                    <div class="card-top">
                        <span class="card-title">UMIS Data</span>
                        <div class="card-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                    </div>
                    <div class="card-value">View UMIS <i class="fa-solid fa-arrow-right" style="font-size: 16px;"></i></div>
                </div>
            </a>

            <a href="result_analysis.php">
                <div class="stat-card orange">
                    <div class="card-top">
                        <span class="card-title">Result Analysis</span>
                        <div class="card-icon"><i class="fa-solid fa-chart-pie"></i></div>
                    </div>
                    <div class="card-value"><?=htmlspecialchars($result_analysis)?> <i class="fa-solid fa-arrow-right" style="font-size: 16px;"></i></div>
                </div>
            </a>
        </div>

    </div>
</div>

</body>
</html>