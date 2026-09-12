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
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

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

.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
}
.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.stat-info h4 { font-size: 12px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-info h2 { font-size: 24px; color: #0F172A; font-weight: 800; margin-top: 4px; }

.grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.02);
    margin-bottom: 25px;
}
.card h3 {
    color: #0F172A;
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group { margin-bottom: 0; }
.form-group label { display: block; font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; }
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 13.5px;
    outline: none;
    background: #FFFFFF;
}

.btn-primary {
    background: #2563EB;
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: 0.2s;
}
.btn-primary:hover { background: #1D4ED8; }

.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
table th, table td { padding: 14px 18px; text-align: left; font-size: 13.5px; border-bottom: 1px solid #E2E8F0; }
table th { background: #F8FAFC; color: #475569; font-weight: 700; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.6px; }

.badge-pass { background: #DCFCE7; color: #15803D; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-block; }
.badge-fail { background: #FEE2E2; color: #B91C1C; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-block; }

@media(max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .stats-grid { grid-template-columns: 1fr; }
    .grid-layout { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Faculty Portal</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="<?php echo $dashboard_url; ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <?php if ($role === 'Faculty' || $role === 'HOD'): ?>
                <li><a href="student_record.php"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
                <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
                <li><a href="umis_data.php"><i class="fa-solid fa-clipboard-user"></i><span>UMIS Data</span></a></li>
                <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-pie"></i><span>Result Analysis</span></a></li>

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
            <?php else: ?>
                <li><a href="result_analysis.php" class="active"><i class="fa-solid fa-chart-line"></i><span>Result Analysis</span></a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <?php if ($role === 'Faculty' || $role === 'HOD'): ?>
                <li><a href="faculty_profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a></li>
            <?php endif; ?>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h2><i class="fa-solid fa-chart-pie"></i> Result Analysis</h2>
        <div class="topbar-right">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
            </div>
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
                <div class="stat-icon" style="background: #DCFCE7; color: #16A34A;"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-info">
                    <h4>Pass Percentage</h4>
                    <h2><?php echo $pass_rate; ?>%</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #FEE2E2; color: #DC2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-info">
                    <h4>Arrears / Fails</h4>
                    <h2><?php echo $failed_count; ?></h2>
                </div>
            </div>
        </div>

        <!-- FILTER -->
        <?php if ($is_staff): ?>
            <div class="card">
                <h3><i class="fa-solid fa-filter" style="color: #2563EB;"></i> Filter Student Performance</h3>
                <form action="result_analysis.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex-grow: 1; min-width: 250px;">
                        <label>Select Student from Database</label>
                        <select name="student_email" onchange="this.form.submit()">
                            <option value="">-- All Students Overview --</option>
                            <?php foreach ($students_list as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['email']); ?>" <?php echo ($selected_student_email === $s['email']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($selected_student_email)): ?>
                        <a href="result_analysis.php" class="btn-primary" style="background: #64748B;"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <!-- CHARTS -->
        <div class="grid-layout">
            <div class="card">
                <h3><i class="fa-solid fa-chart-line" style="color: #2563EB;"></i> Semester Progress Analysis</h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="semProgressChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-chart-pie" style="color: #F59E0B;"></i> Pass vs Fail Ratio</h3>
                <div style="position: relative; height: 260px;">
                    <canvas id="passRatioChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <h3><i class="fa-solid fa-list-check" style="color: #10B981;"></i> Detailed Marks Records</h3>
            <?php if (empty($clean_marks)): ?>
                <p style="text-align: center; color: #94A3B8; padding: 40px;">No marks records found for this view.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($is_staff): ?><th>Student Name</th><?php endif; ?>
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
                                        <td><strong style="color: #0F172A;"><?php echo htmlspecialchars($m['student_name']); ?></strong><br><small style="color: #64748B;"><?php echo htmlspecialchars($m['student_email']); ?></small></td>
                                    <?php endif; ?>
                                    <td><span style="font-weight: 700; color: #2563EB;">Sem <?php echo htmlspecialchars($m['semester']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($m['subject_code']); ?></strong> - <?php echo htmlspecialchars($m['subject_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($m['marks_scored']); ?></strong> / 100</td>
                                    <td><strong><?php echo htmlspecialchars($m['grade']); ?></strong></td>
                                    <td>
                                        <span class="<?php echo ($m['marks_scored'] >= 40) ? 'badge-pass' : 'badge-fail'; ?>">
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
                fill: true,
                tension: 0.3
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });

    new Chart(document.getElementById('passRatioChart'), {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{
                data: [<?php echo $passed_count; ?>, <?php echo $failed_count; ?>],
                backgroundColor: ['#10B981', '#EF4444']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>

</body>
</html>