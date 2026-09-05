<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';

// Check if user can post announcements
$can_post = in_array($role, ['Admin', 'Super Admin', 'HOD', 'Faculty']);

// Supabase Credentials
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Handle Post Action (Admin / Faculty / HOD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_announcement') {
    if (!$can_post) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
    }

    $title           = trim($_POST['title'] ?? '');
    $target_audience = trim($_POST['target_audience'] ?? 'All');
    $message         = trim($_POST['message'] ?? '');

    $payload = [
        'title'           => $title,
        'target_audience' => $target_audience,
        'message'         => $message,
        'posted_by'       => $user_email . " (" . $role . ")"
    ];

    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/announcements";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code == 200 || $code == 201) {
        header("Location: announcements.php?msg=success");
        exit();
    } else {
        echo "Database Error: " . $res;
        exit();
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $can_post) {
    $del_id = $_GET['id'] ?? '';
    if (!empty($del_id)) {
        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/announcements?id=eq." . urlencode($del_id);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    header("Location: announcements.php");
    exit();
}

// Fetch Announcements from Supabase
$announcements = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/announcements?select=*&order=id.desc";
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
    $announcements = json_decode($res, true) ?? [];
}

// User Name and Dashboard URL
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User';
$is_student = ($role === 'Student');

$dashboard_url = 'dashboard_student.php';
if (in_array($role, ['Admin', 'Super Admin'])) {
    $dashboard_url = '../admin/dashboard_admin.php';
} elseif ($role === 'Faculty' || $role === 'HOD') {
    $dashboard_url = ($role === 'HOD') ? '../HOD/dashboard_hod.php' : '../faculty/dashboard_faculty.php';
}

// Stats
$total_announcements = count($announcements);
$student_announcements_count = count(array_filter($announcements, fn($a) => in_array($a['target_audience'] ?? '', ['All', 'Students Only'])));
$latest_date = !empty($announcements) ? date('M d, Y', strtotime($announcements[0]['created_at'] ?? 'now')) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>College Announcements - SRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

:root {
    --primary: #059669;
    --primary-dark: #047857;
    --primary-light: #ECFDF5;
    --primary-border: #A7F3D0;
    --sidebar-bg: #0B132B;
    --bg-light: #F8FAFC;
    --card-bg: #FFFFFF;
    --border-color: #E2E8F0;
    --text-dark: #0F172A;
    --text-muted: #64748B;
    --accent-blue: #0284C7;
    --accent-amber: #D97706;
    --accent-purple: #7C3AED;
}

body {
    background-color: var(--bg-light);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--sidebar-bg);
    color: #FFFFFF;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 100;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
}

.sidebar-top {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px;
}

.logo-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.sidebar-brand h2 {
    font-size: 17px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.2px;
}

.sidebar-brand span {
    font-size: 11px;
    color: #94A3B8;
    display: block;
    font-weight: 500;
}

.sidebar-menu {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 8px;
    max-height: calc(100vh - 170px);
    overflow-y: auto;
    padding-right: 4px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 9px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
}

.sidebar-menu li a.active {
    background: var(--primary);
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.sidebar-menu li a i {
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.nav-category {
    font-size: 10.5px;
    text-transform: uppercase;
    color: #64748B;
    font-weight: 700;
    letter-spacing: 0.8px;
    padding: 12px 14px 4px;
}

.sidebar-footer {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.sidebar-footer a:hover {
    background: rgba(255, 255, 255, 0.06);
}

/* Main Content */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Topbar */
.topbar {
    background: #FFFFFF;
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    position: sticky;
    top: 0;
    z-index: 90;
}

.topbar-title h1 {
    font-size: 19px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.3px;
}

.topbar-title p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 2px;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-badge {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid var(--primary-border);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.content-body {
    padding: 30px 40px 60px;
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Success Banner */
.alert-success {
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #065F46;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Stats Row */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-info h4 {
    font-size: 11.5px;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-info h2 {
    font-size: 22px;
    color: var(--text-dark);
    font-weight: 800;
    margin-top: 2px;
}

/* Content Layout */
.layout-grid {
    display: grid;
    grid-template-columns: <?php echo $can_post ? '380px 1fr' : '1fr'; ?>;
    gap: 24px;
    align-items: start;
}

/* Card */
.card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Form Styling */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 13.5px;
    color: var(--text-dark);
    background: #F8FAFC;
    outline: none;
    transition: all 0.2s ease;
}

.form-control:focus {
    background: #FFFFFF;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

textarea.form-control {
    height: 120px;
    resize: vertical;
}

.btn-submit {
    width: 100%;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #FFFFFF;
    border: none;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
}

.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
}

/* Announcements Feed */
.feed-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    min-width: 220px;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 13px;
}

.search-box input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    background: #F8FAFC;
    transition: all 0.2s ease;
}

.search-box input:focus {
    background: #FFFFFF;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

.filter-pills {
    display: flex;
    gap: 6px;
    background: #F1F5F9;
    padding: 4px;
    border-radius: 10px;
}

.filter-pill {
    padding: 6px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-pill.active {
    background: #FFFFFF;
    color: var(--text-dark);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.anno-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 16px;
}

.anno-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--primary);
    border-radius: 12px;
    padding: 20px 22px;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.anno-card:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
    border-color: var(--primary-border);
    border-left-color: var(--primary);
}

.anno-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
}

.anno-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    letter-spacing: -0.2px;
}

.anno-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.badge-all {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}

.badge-students {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
}

.badge-faculty {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FDE68A;
}

.anno-msg {
    font-size: 13.5px;
    color: #334155;
    line-height: 1.6;
    margin-bottom: 16px;
}

.anno-footer {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px dashed var(--border-color);
    padding-top: 12px;
    flex-wrap: wrap;
    gap: 8px;
}

.posted-by {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #475569;
}

.btn-del {
    color: #EF4444;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.btn-del:hover {
    background: #FEE2E2;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    color: #CBD5E1;
    margin-bottom: 12px;
}

.empty-state h3 {
    font-size: 16px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
}

.empty-state p {
    font-size: 13px;
}

/* Responsive */
@media (max-width: 1024px) {
    .layout-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Unified Sidebar -->
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
            <?php if (!$is_student): ?>
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
                <li><a href="announcements.php" class="active"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
                <li class="nav-category">Portal Navigation</li>
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>"><i class="fa-solid fa-arrow-left"></i> <span>Back to Staff Panel</span></a></li>
            <?php else: ?>
                <li><a href="dashboard_student.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
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
                <li><a href="announcements.php" class="active"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
                <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="sidebar-footer">
        <?php if ($is_student): ?>
            <a href="student_profile.php" style="color:#94A3B8;">
                <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>My Profile</span>
            </a>
        <?php endif; ?>
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
            <h1>Campus Announcements</h1>
            <p>Official circulars, academic notifications, examination updates, and events</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($role); ?>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div>
                    <h1>Institutional Circulars & News</h1>
                    <p>Live feed for: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Announcement has been successfully published to the institutional notice board!</span>
            </div>
        <?php endif; ?>

        <!-- Stat Row -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #E0F2FE; color: #0284C7;">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Circulars</h4>
                    <h2><?php echo $total_announcements; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ECFDF5; color: #059669;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-info">
                    <h4>For Students</h4>
                    <h2><?php echo $student_announcements_count; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h4>Latest Bulletin</h4>
                    <h2 style="font-size: 18px;"><?php echo $latest_date; ?></h2>
                </div>
            </div>
        </div>

        <!-- Layout Grid -->
        <div class="layout-grid">
            
            <?php if ($can_post): ?>
            <!-- Composer Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-pen-nib" style="color: var(--primary);"></i>
                        <span>Publish New Notice</span>
                    </div>
                </div>
                <form action="announcements.php" method="POST">
                    <input type="hidden" name="action" value="post_announcement">

                    <div class="form-group">
                        <label>Announcement Headline</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. End Semester Exam Schedule" required>
                    </div>

                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_audience" class="form-control">
                            <option value="All">All Students & Staff</option>
                            <option value="Students Only">Students Only</option>
                            <option value="Faculty Only">Faculty Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Message Content</label>
                        <textarea name="message" class="form-control" placeholder="Enter detailed notice content, dates, and instructions..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Publish Bulletin
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Feed Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-newspaper" style="color: var(--primary);"></i>
                        <span>Notice Board Feed</span>
                    </div>
                    <div class="feed-controls">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="searchInput" placeholder="Search notices..." onkeyup="filterAnnouncements()">
                        </div>
                        <div class="filter-pills">
                            <button class="filter-pill active" onclick="setAudienceFilter('all', this)">All</button>
                            <button class="filter-pill" onclick="setAudienceFilter('students', this)">Students</button>
                            <button class="filter-pill" onclick="setAudienceFilter('faculty', this)">Staff</button>
                        </div>
                    </div>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-bell-slash"></i>
                        <h3>No Announcements Posted</h3>
                        <p>All institutional notices, schedules, and memos will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="anno-list" id="announcementsFeed">
                        <?php foreach ($announcements as $item): 
                            $aud = $item['target_audience'] ?? 'All';
                            $badge_class = 'badge-all';
                            if ($aud === 'Students Only') $badge_class = 'badge-students';
                            elseif ($aud === 'Faculty Only') $badge_class = 'badge-faculty';
                        ?>
                            <div class="anno-card" data-audience="<?php echo strtolower($aud); ?>">
                                <div class="anno-header">
                                    <div class="anno-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <span class="anno-badge <?php echo $badge_class; ?>">
                                        <i class="fa-solid fa-bullseye"></i> <?php echo htmlspecialchars($aud); ?>
                                    </span>
                                </div>
                                <div class="anno-msg"><?php echo nl2br(htmlspecialchars($item['message'])); ?></div>
                                <div class="anno-footer">
                                    <span class="posted-by">
                                        <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i>
                                        <?php echo htmlspecialchars($item['posted_by']); ?>
                                    </span>
                                    <span>
                                        <i class="fa-regular fa-clock" style="margin-right: 4px;"></i>
                                        <?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?>
                                        <?php if ($can_post): ?>
                                            &nbsp;|&nbsp;
                                            <a href="announcements.php?action=delete&id=<?php echo $item['id']; ?>" class="btn-del" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<script>
let currentAudience = 'all';

function setAudienceFilter(aud, btn) {
    currentAudience = aud;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    filterAnnouncements();
}

function filterAnnouncements() {
    var query = document.getElementById('searchInput').value.toLowerCase();
    var cards = document.querySelectorAll('#announcementsFeed .anno-card');

    cards.forEach(function(card) {
        var cardText = card.innerText.toLowerCase();
        var cardAud = card.getAttribute('data-audience') || '';
        
        var matchesQuery = cardText.includes(query);
        var matchesAud = (currentAudience === 'all') || (cardAud.includes(currentAudience));

        if (matchesQuery && matchesAud) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

</body>
</html>