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

require_once __DIR__ . '/../includes/college_data.php';
$departments = get_all_departments();
$selected_dept = $_GET['dept'] ?? 'All';
$selected_year = $_GET['year'] ?? 'All';
$selected_student_email = $_GET['student_email'] ?? '';

// Fetch Data from 'students' Table
if ($is_staff) {
    $all_students = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?select=*");
    
    // Filter by Department
    $raw_students = $all_students;
    if ($selected_dept !== 'All' && !empty($raw_students)) {
        $raw_students = array_filter($raw_students, function($s) use ($selected_dept) {
            $c = strtoupper($s['course'] ?? '');
            if ($selected_dept === 'CS') {
                return (strpos($c, 'CS') !== false || strpos($c, 'COMPUTER') !== false || empty($c));
            }
            return (strpos($c, strtoupper($selected_dept)) !== false);
        });
    }

    // Filter by Academic Year
    if ($selected_year !== 'All' && !empty($raw_students)) {
        $raw_students = array_filter($raw_students, function($s) use ($selected_year) {
            $info = get_student_year_info($s);
            return ($info['filter_tag'] === $selected_year || $info['level'] === $selected_year);
        });
    }

    // Populate students dropdown from this filtered list
    $students_list = $raw_students;

    // Filter by selected specific student if chosen
    if (!empty($selected_student_email)) {
        $raw_students = array_filter($raw_students, function($s) use ($selected_student_email) {
            return ($s['email'] ?? '') === $selected_student_email;
        });
    }
} else {
    // Student View
    $raw_students = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?email=eq." . urlencode($user_email));
    $students_list = [];
}

// Process JSON Marks across Semesters (Sem 1 to Sem 8)
$clean_marks = [];
$sem_averages_map = [];

foreach ($raw_students as $student) {
    $st_name  = $student['name'] ?? 'Student';
    $st_email = $student['email'] ?? '';
    $yinfo    = get_student_year_info($student);

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
                        'student_name'   => $st_name,
                        'student_email'  => $st_email,
                        'academic_class' => $yinfo['short'],
                        'degree_level'   => $yinfo['level'],
                        'department'     => $student['course'] ?? 'CS',
                        'semester'       => $sem_num,
                        'subject_code'   => $subject_code,
                        'subject_name'   => $subject_name,
                        'marks_scored'   => (int)$total_score,
                        'grade'          => $grade
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
if ($is_admin) $dashboard_url = 'dashboard_admin.php';
elseif ($is_faculty) $dashboard_url = 'dashboard_faculty.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Result Analysis - SRMS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* Sidebar Layout */
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

/* Top Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 52px;
    height: 52px;
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
    letter-spacing: 0.6px;
}
.stat-info h2 {
    font-size: 26px;
    color: #0F172A;
    font-weight: 800;
    margin-top: 4px;
}

/* Layout Grids */
.grid-layout {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    margin-bottom: 25px;
}

.card h3 {
    color: #0F172A;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1.5px solid #F1F5F9;
}

.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 8px;
}
.form-group select {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 10px;
    font-size: 13.5px;
    outline: none;
    background: #FAFBFC;
    color: #1E293B;
    transition: all 0.2s ease;
}
.form-group select:focus {
    border-color: #2563EB;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.btn-primary {
    background: #2563EB;
    color: white;
    border: none;
    padding: 11px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.btn-primary:hover {
    background: #1D4ED8;
}

/* Table */
.table-responsive {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

table th, table td {
    padding: 14px 18px;
    border-bottom: 1px solid #F1F5F9;
    font-size: 13.5px;
}

table th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
    border-bottom: 1.5px solid #E2E8F0;
}

tbody tr {
    transition: background 0.2s ease;
}
tbody tr:hover {
    background: #F8FAFC;
}

.badge-pass {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-fail {
    background: #FEF2F2;
    color: #B91C1C;
    border: 1px solid #FECACA;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
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
            <?php if ($is_admin): ?>
                <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
                <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
                <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
                <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
                <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

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
                <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
                <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
            <?php else: ?>
                <li><a href="<?php echo $dashboard_url; ?>"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
                <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <?php if ($is_admin): ?>
                <li><a href="admin_profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <?php endif; ?>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="navbar">
        <h1>📊 Result Analysis (<?php echo htmlspecialchars($role); ?> View)</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($role); ?>: <?php echo htmlspecialchars($user_name); ?>
        </div>
    </div>

    <div class="content-body">
        
        <!-- TOP STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fa-solid fa-book-open"></i></div>
                <div class="stat-info">
                    <h4>Total Subjects Evaluated</h4>
                    <h2><?php echo $total_records; ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ECFDF5; color: #10B981;"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-info">
                    <h4>Pass Percentage</h4>
                    <h2><?php echo $pass_rate; ?>%</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF2F2; color: #EF4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-info">
                    <h4>Arrears / Fails</h4>
                    <h2><?php echo $failed_count; ?></h2>
                </div>
            </div>
        </div>

        <!-- FILTER -->
        <?php if ($is_staff): ?>
            <div class="card">
                <h3><i class="fa-solid fa-filter" style="color: #2563EB;"></i> Filter Academic Results & Performance</h3>
                <form action="result_analysis.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    
                    <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                        <label><i class="fa-solid fa-building-columns" style="color: #2563EB;"></i> Department</label>
                        <select name="dept" onchange="this.form.submit()">
                            <option value="All" <?php echo $selected_dept === 'All' ? 'selected' : ''; ?>>All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['code']; ?>" <?php echo $selected_dept === $d['code'] ? 'selected' : ''; ?>>
                                    <?php echo $d['code'] . ' - ' . $d['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                        <label><i class="fa-solid fa-graduation-cap" style="color: #2563EB;"></i> Class / Year</label>
                        <select name="year" onchange="this.form.submit()">
                            <option value="All" <?=$selected_year === 'All' ? 'selected' : ''?>>All Years & Degrees</option>
                            <option value="UG_1" <?=$selected_year === 'UG_1' ? 'selected' : ''?>>UG - 1st Year (I Year)</option>
                            <option value="UG_2" <?=$selected_year === 'UG_2' ? 'selected' : ''?>>UG - 2nd Year (II Year)</option>
                            <option value="UG_3" <?=$selected_year === 'UG_3' ? 'selected' : ''?>>UG - 3rd Year (III Year)</option>
                            <option value="PG_1" <?=$selected_year === 'PG_1' ? 'selected' : ''?>>PG - 1st Year (I PG)</option>
                            <option value="PG_2" <?=$selected_year === 'PG_2' ? 'selected' : ''?>>PG - 2nd Year (II PG)</option>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1; min-width: 240px; margin: 0;">
                        <label><i class="fa-solid fa-user" style="color: #2563EB;"></i> Student</label>
                        <select name="student_email" onchange="this.form.submit()">
                            <option value="">-- All Filtered Students (<?=count($students_list)?>) --</option>
                            <?php foreach ($students_list as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['email']); ?>" <?php echo ($selected_student_email === $s['email']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($selected_dept !== 'All' || $selected_year !== 'All' || !empty($selected_student_email)): ?>
                        <a href="result_analysis.php" class="btn-primary" style="background: #64748B; text-decoration: none; padding: 10px 16px; border-radius: 8px; font-weight: 600;"><i class="fa-solid fa-rotate-left"></i> Reset Filters</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <!-- CHARTS -->
        <div class="grid-layout">
            <div class="card">
                <h3><i class="fa-solid fa-chart-line" style="color: #2563EB;"></i> Semester Progress Analysis</h3>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="semProgressChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-chart-pie" style="color: #F59E0B;"></i> Pass vs Fail Ratio</h3>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="passRatioChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <h3><i class="fa-solid fa-list-check" style="color: #10B981;"></i> Detailed Marks Records</h3>
            <?php if (empty($clean_marks)): ?>
                <p style="text-align: center; color: #94A3B8; padding: 30px; font-weight: 500;">No marks records found for this selection.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($is_staff): ?><th>Student Name</th><?php endif; ?>
                                <th>Academic Class</th>
                                <th>Semester</th>
                                <th>Subject</th>
                                <th>Marks Scored</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clean_marks as $m): ?>
                                <tr>
                                    <?php if ($is_staff): ?>
                                        <td><strong><?php echo htmlspecialchars($m['student_name']); ?></strong><br><small style="color: #64748B;"><?php echo htmlspecialchars($m['student_email']); ?></small></td>
                                    <?php endif; ?>
                                    <td>
                                        <span style="background: #F3E8FF; color: #7C3AED; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; border: 1px solid #E9D5FF; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid <?=$m['degree_level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($m['academic_class'])?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 600; color: #2563EB;">Sem <?php echo htmlspecialchars($m['semester']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['subject_code']); ?></strong> - <?php echo htmlspecialchars($m['subject_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['marks_scored']); ?></strong> / 100</td>
                                    <td><strong style="color: #0F172A;"><?php echo htmlspecialchars($m['grade']); ?></strong></td>
                                    <td>
                                        <span class="<?php echo ($m['marks_scored'] >= 40) ? 'badge-pass' : 'badge-fail'; ?>">
                                            <i class="fa-solid <?php echo ($m['marks_scored'] >= 40) ? 'fa-check' : 'fa-xmark'; ?>"></i>
                                            <?php echo ($m['marks_scored'] >= 40) ? 'PASS' : 'FAIL'; ?>
                                        </span>
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

    new Chart(document.getElementById('semProgressChart'), {
        type: 'line',
        data: {
            labels: semLabels,
            datasets: [{
                label: 'Average Score (%)',
                data: semAvg,
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2563EB',
                pointRadius: 4
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { font: { family: 'Plus Jakarta Sans', weight: '600' } }
                }
            },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#F1F5F9' } },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('passRatioChart'), {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{
                data: [<?php echo $passed_count; ?>, <?php echo $failed_count; ?>],
                backgroundColor: ['#10B981', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: 'Plus Jakarta Sans', weight: '600' } }
                }
            }
        }
    });
});
</script>

</body>
</html>