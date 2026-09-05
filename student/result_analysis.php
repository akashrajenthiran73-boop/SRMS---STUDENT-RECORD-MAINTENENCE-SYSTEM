<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role       = $_SESSION['role'] ?? 'Student';
$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$user_name  = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User';

$is_admin   = in_array($role, ['Admin', 'Super Admin']);
$is_faculty = in_array($role, ['Faculty', 'HOD']);
$is_staff   = $is_admin || $is_faculty;

// Supabase Credentials
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Helper Function for Supabase
function supabaseFetch($url, $key, $endpoint) {
    if (empty($url) || empty($key)) return [];
    $full_url = rtrim($url, '/') . "/rest/v1/" . $endpoint;
    $ch = curl_init($full_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// Fetch Students List for Admin Dropdown
$students_list = [];
if ($is_staff) {
    $students_list = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?select=email,name");
}

// Selected Student Filter for Admin View
$selected_student_email = $_GET['student_email'] ?? '';

// Fetch Data from 'students' Table
if ($is_staff) {
    if (!empty($selected_student_email)) {
        $raw_students = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?email=eq." . urlencode($selected_student_email));
    } else {
        $raw_students = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?select=*");
    }
} else {
    // Student View
    $raw_students = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?email=eq." . urlencode($user_email));
}

// Process JSON Marks across Semesters (Sem 1 to Sem 8)
$clean_marks = [];
$sem_averages_map = [];

foreach ($raw_students as $student) {
    $st_name  = $student['name'] ?? 'Student';
    $st_email = $student['email'] ?? '';

    for ($sem_num = 1; $sem_num <= 8; $sem_num++) {
        $sem_key = "sem{$sem_num}_marks";
        
        if (!empty($student[$sem_key])) {
            $marks_json = $student[$sem_key];
            
            // Handle JSON decoding if string
            if (is_string($marks_json)) {
                $marks_json = json_decode($marks_json, true) ?? [];
            }

            if (is_array($marks_json)) {
                foreach ($marks_json as $sub) {
                    // Extract subject details & total marks
                    $subject_code = $sub['code'] ?? $sub['subject_code'] ?? 'SUB';
                    $subject_name = $sub['name'] ?? $sub['subject_name'] ?? $sub['subject'] ?? 'Subject';
                    $total_score  = $sub['total'] ?? $sub['marks'] ?? $sub['marks_scored'] ?? (($sub['ia'] ?? 0) + ($sub['ue'] ?? 0));
                    $grade        = $sub['grade'] ?? ($total_score >= 40 ? 'P' : 'F');

                    $clean_marks[] = [
                        'student_name'  => $st_name,
                        'student_email' => $st_email,
                        'semester'      => $sem_num,
                        'subject_code'  => $subject_code,
                        'subject_name'  => $subject_name,
                        'marks_scored'  => (int)$total_score,
                        'grade'         => $grade
                    ];

                    $sem_averages_map[$sem_num][] = (int)$total_score;
                }
            }
        }
    }
}

// Overall Stats Calculation
$total_records = count($clean_marks);
$passed_count  = count(array_filter($clean_marks, fn($m) => $m['marks_scored'] >= 40));
$failed_count  = $total_records - $passed_count;
$pass_rate     = $total_records > 0 ? round(($passed_count / $total_records) * 100, 1) : 0;

// Chart Data Processing
ksort($sem_averages_map);
$sem_labels = [];
$sem_averages = [];

foreach ($sem_averages_map as $sem_num => $scores) {
    $sem_labels[]   = "Sem " . $sem_num;
    $sem_averages[] = round(array_sum($scores) / count($scores), 1);
}

$dashboard_url = 'dashboard_student.php';
if ($is_admin) $dashboard_url = '../admin/dashboard_admin.php';
elseif ($is_faculty) $dashboard_url = ($role === 'HOD') ? '../HOD/dashboard_hod.php' : '../faculty/dashboard_faculty.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Academic Result Analysis - SRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    --accent-green: #10B981;
    --accent-red: #EF4444;
    --accent-purple: #8B5CF6;
}

body {
    background-color: var(--bg-light);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styling */
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

/* Page Header Card */
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

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-print {
    background: #FFFFFF;
    color: var(--text-dark);
    border: 1px solid var(--border-color);
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-print:hover {
    background: #F1F5F9;
    color: var(--primary);
    border-color: var(--primary-border);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
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
    font-size: 24px;
    color: var(--text-dark);
    font-weight: 800;
    margin-top: 2px;
}

/* Filter Card */
.filter-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 18px 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.filter-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.form-group {
    flex-grow: 1;
    min-width: 260px;
}

.form-group label {
    display: block;
    font-size: 12px;
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
    font-weight: 500;
    color: var(--text-dark);
    background: #F8FAFC;
    outline: none;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: var(--primary);
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}

.btn-reset {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid var(--border-color);
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-reset:hover {
    background: #E2E8F0;
    color: var(--text-dark);
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.chart-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
}

.chart-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-badge {
    font-size: 11px;
    font-weight: 700;
    background: #F1F5F9;
    color: var(--text-muted);
    padding: 4px 10px;
    border-radius: 12px;
}

.chart-container {
    position: relative;
    flex-grow: 1;
    min-height: 260px;
    width: 100%;
}

/* Table Card */
.table-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.table-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #FFFFFF;
}

.table-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.count-pill {
    background: var(--primary-light);
    color: var(--primary);
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 12px;
    border: 1px solid var(--primary-border);
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

table th {
    background: #F8FAFC;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-color);
}

table td {
    padding: 14px 20px;
    font-size: 13.5px;
    color: #334155;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}

table tr:last-child td {
    border-bottom: none;
}

table tr:hover td {
    background-color: #F8FAFC;
}

.sem-pill {
    display: inline-block;
    background: #F1F5F9;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
}

.subject-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.subject-code {
    font-family: monospace;
    font-size: 12px;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-light);
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid var(--primary-border);
}

.badge-pass {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-fail {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
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
@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
    .stats-grid { grid-template-columns: 1fr; }
}

@media print {
    .sidebar, .topbar, .filter-card, .btn-print, .header-actions {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
    }
    .content-body {
        padding: 0 !important;
    }
    .card, .table-card, .chart-card, .stat-card {
        box-shadow: none !important;
        border: 1px solid #CCC !important;
    }
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
            <?php if ($is_staff): ?>
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
                <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
                <li class="nav-category">Portal Navigation</li>
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>"><i class="fa-solid fa-arrow-left"></i> <span>Back to Staff Panel</span></a></li>
            <?php else: ?>
                <li><a href="dashboard_student.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
                <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
                <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
                <li><a href="umis.php"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
                <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
                <li><a href="marks.php"><i class="fa-solid fa-award"></i> <span>Marks / Grades</span></a></li>

                <li class="nav-category">Academic & Services</li>
                <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
                <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
                <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
                <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
                <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
                <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
                <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="sidebar-footer">
        <?php if (!$is_staff): ?>
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
            <h1>Academic Result Analysis</h1>
            <p>Comprehensive performance metrics, grade distribution, and semester trends</p>
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
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <h1>Performance Analytics & Records</h1>
                    <p>
                        <?php if ($is_staff && !empty($selected_student_email)): ?>
                            Filtered by Student: <strong><?php echo htmlspecialchars($selected_student_email); ?></strong>
                        <?php elseif ($is_staff): ?>
                            Institution-wide consolidated overview
                        <?php else: ?>
                            Student: <strong><?php echo htmlspecialchars($user_name); ?></strong> (<?php echo htmlspecialchars($user_email); ?>)
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-print" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Analysis
                </button>
            </div>
        </div>

        <!-- Filter for Staff -->
        <?php if ($is_staff): ?>
            <div class="filter-card">
                <form action="result_analysis.php" method="GET" class="filter-form">
                    <div class="form-group">
                        <label><i class="fa-solid fa-filter" style="color: var(--primary); margin-right: 4px;"></i> Select Student from Database</label>
                        <select name="student_email" class="form-control" onchange="this.form.submit()">
                            <option value="">-- All Students Overview --</option>
                            <?php foreach ($students_list as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['email']); ?>" <?php echo ($selected_student_email === $s['email']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($selected_student_email)): ?>
                        <a href="result_analysis.php" class="btn-reset">
                            <i class="fa-solid fa-rotate-left"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #E0F2FE; color: #0284C7;">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Subjects</h4>
                    <h2><?php echo $total_records; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ECFDF5; color: #059669;">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="stat-info">
                    <h4>Pass Percentage</h4>
                    <h2><?php echo $pass_rate; ?>%</h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #DCFCE7; color: #16A34A;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-info">
                    <h4>Passed Subjects</h4>
                    <h2><?php echo $passed_count; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEE2E2; color: #DC2626;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="stat-info">
                    <h4>Arrears / Fails</h4>
                    <h2><?php echo $failed_count; ?></h2>
                </div>
            </div>
        </div>

        <!-- Visual Analytics Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <div class="chart-title">
                        <i class="fa-solid fa-arrow-trend-up" style="color: var(--primary);"></i>
                        <span>Semester Average Progression</span>
                    </div>
                    <span class="chart-badge">Sem 1 - 8</span>
                </div>
                <div class="chart-container">
                    <canvas id="semProgressChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-card-header">
                    <div class="chart-title">
                        <i class="fa-solid fa-chart-pie" style="color: #0284C7;"></i>
                        <span>Result Clearance Ratio</span>
                    </div>
                    <span class="chart-badge"><?php echo $total_records; ?> Total</span>
                </div>
                <div class="chart-container">
                    <canvas id="passRatioChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Marks Records Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-title">
                    <i class="fa-solid fa-list-check" style="color: var(--primary);"></i>
                    <span>Detailed Subject Marks & Grades</span>
                </div>
                <span class="count-pill"><?php echo count($clean_marks); ?> Evaluated Subjects</span>
            </div>

            <?php if (empty($clean_marks)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-folder-open"></i>
                    <h3>No Marks Records Found</h3>
                    <p>There are no semester exam records evaluated for this selection.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($is_staff): ?><th>Student Name</th><?php endif; ?>
                                <th>Term</th>
                                <th>Subject Details</th>
                                <th>Marks Scored</th>
                                <th>Grade</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clean_marks as $m): ?>
                                <tr>
                                    <?php if ($is_staff): ?>
                                        <td>
                                            <strong style="color: #0F172A;"><?php echo htmlspecialchars($m['student_name']); ?></strong>
                                            <div style="font-size: 11.5px; color: #64748B;"><?php echo htmlspecialchars($m['student_email']); ?></div>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="sem-pill">Sem <?php echo htmlspecialchars($m['semester']); ?></span>
                                    </td>
                                    <td>
                                        <div class="subject-cell">
                                            <span class="subject-code"><?php echo htmlspecialchars($m['subject_code']); ?></span>
                                            <span style="font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($m['subject_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="font-size: 14px; color: #0F172A;"><?php echo htmlspecialchars($m['marks_scored']); ?></strong>
                                        <span style="font-size: 11.5px; color: #64748B;">/ 100</span>
                                    </td>
                                    <td>
                                        <strong style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: #F8FAFC; border: 1px solid #E2E8F0; color: #0F172A; font-size: 12.5px;">
                                            <?php echo htmlspecialchars($m['grade']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ($m['marks_scored'] >= 40): ?>
                                            <span class="badge-pass"><i class="fa-solid fa-check"></i> PASS</span>
                                        <?php else: ?>
                                            <span class="badge-fail"><i class="fa-solid fa-xmark"></i> FAIL</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var semLabels = <?php echo json_encode($sem_labels); ?>;
    var semAvg = <?php echo json_encode($sem_averages); ?>;
    
    if (semLabels.length === 0) {
        semLabels = ['No Data'];
        semAvg = [0];
    }

    // Line Chart for Semester Progression
    var ctxLine = document.getElementById('semProgressChart').getContext('2d');
    var gradientLine = ctxLine.createLinearGradient(0, 0, 0, 260);
    gradientLine.addColorStop(0, 'rgba(5, 150, 105, 0.25)');
    gradientLine.addColorStop(1, 'rgba(5, 150, 105, 0.00)');

    new Chart(ctxLine, {
        type: 'line',
        data: {
            labels: semLabels,
            datasets: [{
                label: 'Average Score (%)',
                data: semAvg,
                borderColor: '#059669',
                borderWidth: 2.5,
                backgroundColor: gradientLine,
                pointBackgroundColor: '#059669',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.35
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F172A',
                    padding: 10,
                    titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
                    bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                    cornerRadius: 8
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    max: 100,
                    grid: { color: '#F1F5F9' },
                    ticks: {
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                        callback: function(val) { return val + '%'; }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 } }
                }
            }
        }
    });

    // Doughnut Chart for Pass vs Fail Ratio
    new Chart(document.getElementById('passRatioChart'), {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed / Arrear'],
            datasets: [{
                data: [<?php echo $passed_count; ?>, <?php echo $failed_count; ?>],
                backgroundColor: ['#10B981', '#EF4444'],
                hoverBackgroundColor: ['#059669', '#DC2626'],
                borderWidth: 2,
                borderColor: '#FFFFFF'
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '600' },
                        color: '#475569'
                    }
                },
                tooltip: {
                    backgroundColor: '#0F172A',
                    padding: 10,
                    titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12 },
                    bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
                    cornerRadius: 8
                }
            }
        }
    });
});
</script>

</body>
</html>