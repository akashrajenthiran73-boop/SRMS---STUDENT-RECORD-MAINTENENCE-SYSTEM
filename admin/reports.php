<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin / HOD Access Only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'HOD'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';

// Supabase Credentials
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Helper Function to Fetch Data from Supabase
function fetchSupabaseData($url, $key, $endpoint) {
    if (empty($url) || empty($key)) return [];
    $fetch_url = rtrim($url, '/') . "/rest/v1/" . $endpoint;
    $ch = curl_init($fetch_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key",
        "Authorization: Bearer $key"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// Export CSV Action
if (isset($_GET['export']) && !empty($_GET['export'])) {
    $type = $_GET['export'];
    $data = fetchSupabaseData($SUPABASE_URL, $SUPABASE_KEY, $type . "?select=*");

    if (!empty($data)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $type . '_report_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array_keys($data[0]));
        
        // CSV Rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
}

// Fetch Summary Stats
$users = fetchSupabaseData($SUPABASE_URL, $SUPABASE_KEY, "users?select=*");
$materials = fetchSupabaseData($SUPABASE_URL, $SUPABASE_KEY, "study_materials?select=*");
$assignments = fetchSupabaseData($SUPABASE_URL, $SUPABASE_KEY, "assignments?select=*");
$leaves = fetchSupabaseData($SUPABASE_URL, $SUPABASE_KEY, "student_leaves?select=*");

$total_students = count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'Student'));
$total_faculty = count(array_filter($users, fn($u) => in_array($u['role'] ?? '', ['Faculty', 'HOD'])));
$pending_leaves = count(array_filter($leaves, fn($l) => ($l['status'] ?? '') === 'Pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Reports - Admin Panel</title>
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
    display: flex;
    min-height: 100vh;
}

/* Sidebar Navigation */
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
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    display: block;
    margin-top: 4px;
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

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 11px 16px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
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

.menu-divider {
    margin: 14px 10px;
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.menu-heading {
    font-size: 10px;
    text-transform: uppercase;
    color: #64748B;
    padding: 6px 14px;
    letter-spacing: 1.2px;
    font-weight: 700;
}

.sidebar-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

/* Main Content Area */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.navbar {
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
.navbar h1 {
    color: #1E3A8A;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.3px;
}
.user-profile-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #EFF6FF;
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
}
.user-profile-badge i {
    color: #2563EB;
}

.content-body {
    padding: 35px 40px;
    flex: 1;
}

/* Stats Overview */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 35px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    transition: all 0.25s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
    border-color: #CBD5E1;
}

.stat-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.stat-info h4 {
    font-size: 12px;
    color: #64748B;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-info h2 {
    font-size: 24px;
    color: #1E3A8A;
    font-weight: 800;
    margin-top: 4px;
}

.section-title {
    color: #1E3A8A;
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Reports Grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.02);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    transition: all 0.25s ease;
}

.card:hover {
    border-color: #CBD5E1;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.04);
}

.card-content h3 {
    color: #1E3A8A;
    font-size: 16.5px;
    font-weight: 800;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-content p {
    font-size: 13px;
    color: #64748B;
    line-height: 1.5;
}

.btn-csv {
    background: #10B981;
    color: white;
    padding: 11px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    flex-shrink: 0;
}
.btn-csv:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

@media (max-width: 1050px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .reports-grid { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Arignar Anna Government Arts College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">College Operations</div>
            <li><a href="manage_departments.php"><i class="fa-solid fa-building-columns"></i> <span>Manage Departments</span></a></li>
            <li><a href="circulars.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-comments"></i> <span>Student Grievances</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php" class="active"><i class="fa-solid fa-chart-line"></i> <span>Reports</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="admin_profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="navbar">
        <h1>Analytics & Reports</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: Admin (<?php echo htmlspecialchars($user_email); ?>)
        </div>
    </div>

    <div class="content-body">
        
        <!-- STATS OVERVIEW -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;"><i class="fa-solid fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h4>Total Students</h4>
                    <h2><?php echo $total_students; ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A;"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="stat-info">
                    <h4>Total Faculty</h4>
                    <h2><?php echo $total_faculty; ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0;"><i class="fa-solid fa-book-bookmark"></i></div>
                <div class="stat-info">
                    <h4>Study Materials</h4>
                    <h2><?php echo count($materials); ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="stat-info">
                    <h4>Pending Leaves</h4>
                    <h2><?php echo $pending_leaves; ?></h2>
                </div>
            </div>
        </div>

        <!-- EXPORTABLE REPORTS SECTION -->
        <div class="section-title">
            <i class="fa-solid fa-download" style="color: #2563EB;"></i> Export Data Reports
        </div>
        
        <div class="reports-grid">
            <div class="card">
                <div class="card-content">
                    <h3><i class="fa-solid fa-users" style="color: #2563EB;"></i> Users Directory Report</h3>
                    <p>Download complete list of Students, Staff, and Admins registered in system.</p>
                </div>
                <a href="reports.php?export=users" class="btn-csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            </div>

            <div class="card">
                <div class="card-content">
                    <h3><i class="fa-solid fa-book" style="color: #10B981;"></i> Study Materials Log</h3>
                    <p>Export details of all uploaded syllabus copies, notes, and question banks.</p>
                </div>
                <a href="reports.php?export=study_materials" class="btn-csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            </div>

            <div class="card">
                <div class="card-content">
                    <h3><i class="fa-solid fa-file-pen" style="color: #F59E0B;"></i> Assignments Report</h3>
                    <p>Generate reports for assigned tasks, due dates, and subject details.</p>
                </div>
                <a href="reports.php?export=assignments" class="btn-csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            </div>

            <div class="card">
                <div class="card-content">
                    <h3><i class="fa-solid fa-envelope-open-text" style="color: #EF4444;"></i> Leave Applications</h3>
                    <p>Download all student leave records along with Approval / Rejection status.</p>
                </div>
                <a href="reports.php?export=student_leaves" class="btn-csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            </div>
        </div>

    </div>
</div>

</body>
</html>