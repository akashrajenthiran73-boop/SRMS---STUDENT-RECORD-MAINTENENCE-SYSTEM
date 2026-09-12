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
$session_dept = $_SESSION['department'] ?? 'CS';
if (empty($session_dept) || $session_dept === 'BSC') $session_dept = 'CS';

$selected_year = $_GET['year'] ?? 'All';
$selected_student_email = $_GET['student_email'] ?? '';

// Fetch Data from 'students' Table
if ($is_staff) {
    $all_students = supabaseFetch($SUPABASE_URL, $SUPABASE_KEY, "students?select=*");
    
    // Filter by HOD Department
    $raw_students = array_filter($all_students, function($s) use ($session_dept) {
        $c = strtoupper($s['course'] ?? '');
        if ($session_dept === 'CS') {
            return (strpos($c, 'CS') !== false || strpos($c, 'COMPUTER') !== false || empty($c));
        }
        return (strpos($c, strtoupper($session_dept)) !== false);
    });

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
<?php
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Result Analysis - HOD Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
.stat-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 22px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.stat-info h4 { font-size: 11.5px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.stat-info h2 { font-size: 24px; color: #0F172A; font-weight: 800; }

.card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 24px; }
.card h3 { color: #0F172A; font-size: 15px; font-weight: 800; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; gap: 10px; }
.card h3 i { color: #7C3AED; }

.filter-form { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
.form-group { flex-grow: 1; min-width: 250px; margin: 0; }
.form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
.form-group select { width: 100%; padding: 10px 14px; border: 1px solid #E2E8F0; border-radius: 10px; font-size: 13px; font-weight: 500; background: #F8FAFC; outline: none; transition: all 0.2s; }
.form-group select:focus { border-color: #7C3AED; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1); }

.btn-reset { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
.btn-reset:hover { background: #E2E8F0; color: #0F172A; }

.grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }

.table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #E2E8F0; }
table { width: 100%; border-collapse: collapse; text-align: left; }
table th, table td { padding: 14px 18px; font-size: 13px; }
table th { background: #F8FAFC; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; border-bottom: 1px solid #E2E8F0; }
table td { border-bottom: 1px solid #F1F5F9; color: #334155; vertical-align: middle; }
table tr:last-child td { border-bottom: none; }
table tbody tr:hover td { background: #F8FAFC; }

.badge-status { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-pass { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.badge-fail { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }

@media (max-width: 1024px) {
    .sidebar { width: 70px; }
    .sidebar-brand span, .sidebar-brand h2, .sidebar-menu span, .nav-category, .sidebar-footer span { display: none; }
    .sidebar-brand { justify-content: center; padding: 15px 0; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar, .content-body { padding-left: 20px; padding-right: 20px; }
    .stats-grid, .grid-layout { grid-template-columns: 1fr; }
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
            <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>

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
            <h1>Result & Marks Analysis</h1>
            <p>Academic performance metrics, pass ratios, and semester score trajectories</p>
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
        
        <!-- TOP STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h4>Total Subjects Evaluated</h4>
                    <h2><?php echo $total_records; ?></h2>
                </div>
                <div class="stat-icon" style="background: #EFF6FF; color: #2563EB;"><i class="fa-solid fa-book-open"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h4>Overall Pass Percentage</h4>
                    <h2><?php echo $pass_rate; ?>%</h2>
                </div>
                <div class="stat-icon" style="background: #ECFDF5; color: #059669;"><i class="fa-solid fa-circle-check"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h4>Arrears / Fails Count</h4>
                    <h2><?php echo $failed_count; ?></h2>
                </div>
                <div class="stat-icon" style="background: #FEF2F2; color: #DC2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
        </div>

        <!-- FILTER -->
        <?php if ($is_staff): ?>
            <div class="card">
                <h3><i class="fa-solid fa-filter"></i> Filter Student Performance</h3>
                <form action="result_analysis.php" method="GET" class="filter-form" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px; margin: 0;">
                        <label><i class="fa-solid fa-graduation-cap" style="color: #7C3AED;"></i> Class / Year</label>
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
                        <label>Select Student from Database</label>
                        <select name="student_email" onchange="this.form.submit()">
                            <option value="">-- All Filtered Students (<?=count($students_list)?>) --</option>
                            <?php foreach ($students_list as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['email']); ?>" <?php echo ($selected_student_email === $s['email']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selected_year !== 'All' || !empty($selected_student_email)): ?>
                        <a href="result_analysis.php" class="btn-reset" style="text-decoration: none; padding: 10px 16px; background: #64748B; color: white; border-radius: 8px; font-weight: 600;"><i class="fa-solid fa-arrow-rotate-left"></i> Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <!-- CHARTS -->
        <div class="grid-layout">
            <div class="card">
                <h3><i class="fa-solid fa-chart-line"></i> Semester Progress Trajectory</h3>
                <div style="height: 260px; position: relative;">
                    <canvas id="semProgressChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-chart-pie"></i> Pass vs Fail Ratio</h3>
                <div style="height: 260px; position: relative;">
                    <canvas id="passRatioChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <h3><i class="fa-solid fa-list-check"></i> Detailed Marks Records</h3>
            <?php if (empty($clean_marks)): ?>
                <p style="text-align: center; color: #94A3B8; padding: 35px;">No marks records found for this view.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($is_staff): ?><th>Student Name</th><?php endif; ?>
                                <th>Academic Class</th>
                                <th>Semester</th>
                                <th>Subject Code & Name</th>
                                <th>Marks Scored</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clean_marks as $m): ?>
                                <tr>
                                    <?php if ($is_staff): ?>
                                        <td>
                                            <strong style="color: #0F172A; font-size: 13.5px;"><?php echo htmlspecialchars($m['student_name']); ?></strong><br>
                                            <small style="color: #64748B; font-family: monospace;"><?php echo htmlspecialchars($m['student_email']); ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span style="background: #F3E8FF; color: #7C3AED; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; border: 1px solid #E9D5FF; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-solid <?=$m['degree_level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($m['academic_class'])?>
                                        </span>
                                    </td>
                                    <td><span style="font-weight: 700; color: #475569;">Sem <?php echo htmlspecialchars($m['semester']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($m['subject_code']); ?></strong> - <?php echo htmlspecialchars($m['subject_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['marks_scored']); ?></strong> / 100</td>
                                    <td><strong style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($m['grade']); ?></strong></td>
                                    <td>
                                        <span class="badge-status <?php echo ($m['marks_scored'] >= 40) ? 'badge-pass' : 'badge-fail'; ?>">
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
                borderColor: '#7C3AED',
                backgroundColor: 'rgba(124, 58, 237, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointBackgroundColor: '#7C3AED',
                pointRadius: 4
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
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
                legend: { position: 'bottom' }
            },
            cutout: '70%'
        }
    });
});
</script>

</body>
</html>