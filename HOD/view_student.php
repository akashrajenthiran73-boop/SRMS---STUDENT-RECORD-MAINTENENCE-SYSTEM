<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Session Auth Check
if (!isset($_SESSION['role'])) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

$role = strtolower($_SESSION['role']);

// 2. Dynamic ABSOLUTE Links based on User Role (Admin / HOD)
$base_url = "/SRMS"; 

if ($role === 'admin') {
    $dashboard_link = $base_url . "/admin/dashboard_admin.php";
    $records_link   = $base_url . "/admin/student_records.php";
    $edit_link      = $base_url . "/admin/edit_student.php";
    $print_link     = $base_url . "/admin/print_student.php";
} elseif ($role === 'hod') {
    $dashboard_link = $base_url . "/hod/dashboard_hod.php"; 
    $records_link   = $base_url . "/hod/student_records_list.php"; 
    $print_link     = $base_url . "/hod/print_student.php";
} else {
    $dashboard_link = $base_url . "/auth/login.php";
    $records_link   = $base_url . "/index.php";
}

// 3. Multi-location Safe .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
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

if (empty($SUPABASE_URL) || empty($SUPABASE_KEY)) {
    die("Configuration Error: SUPABASE_URL or SUPABASE_ANON_KEY missing in .env file!");
}

if (!isset($_GET['id'])) { 
    header("Location: " . $records_link); 
    exit; 
}

$id = $_GET['id'];
$url = rtrim($SUPABASE_URL, '/') . "/rest/v1/students?id=eq." . urlencode($id) . "&select=*";

// 4. Supabase API Call
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $SUPABASE_KEY", 
    "Authorization: Bearer $SUPABASE_KEY"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    die("cURL Connection Error: " . htmlspecialchars($curl_error));
}

if ($http_code !== 200) {
    die("Supabase API Error (HTTP $http_code): " . htmlspecialchars($response));
}

$result = json_decode($response, true);

if (!$result || !is_array($result) || count($result) === 0) {
    die("Student record not found for ID: " . htmlspecialchars($id));
}

$student = $result[0];

// Helper function to decode JSON marks safely
function get_semester_marks($marks_raw) {
    if (is_string($marks_raw)) {
        return json_decode($marks_raw, true) ?? [];
    }
    return is_array($marks_raw) ? $marks_raw : [];
}

$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Student - <?php echo htmlspecialchars($student['name'] ?? 'Student'); ?> - HOD Portal</title>
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

/* Action Header Panel */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: #FFFFFF;
    padding: 20px 24px;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
    gap: 16px;
}

.student-hero {
    display: flex;
    align-items: center;
    gap: 16px;
}

.student-hero-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
}

.page-header h1 {
    color: #0F172A;
    font-size: 19px;
    font-weight: 800;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 9px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-back { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
.btn-back:hover { background: #E2E8F0; color: #0F172A; }

.btn-dash { background: #F8FAFC; color: #64748B; border: 1px solid #E2E8F0; }
.btn-dash:hover { background: #F1F5F9; color: #0F172A; }

.btn-edit { background: #F59E0B; color: white; }
.btn-edit:hover { background: #D97706; }

.btn-print { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
.btn-print:hover { opacity: 0.92; transform: translateY(-1px); }

/* Section Cards */
.section {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    padding: 24px;
    margin-bottom: 24px;
}

.section h3 {
    color: #0F172A;
    font-size: 15px;
    font-weight: 800;
    padding-bottom: 14px;
    border-bottom: 1px solid #F1F5F9;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section h3 i {
    color: #7C3AED;
}

.section h4 {
    color: #334155;
    font-size: 13.5px;
    font-weight: 700;
    margin: 24px 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.basic-details-wrap {
    display: flex;
    gap: 28px;
    align-items: flex-start;
}

.photo-container {
    flex-shrink: 0;
    text-align: center;
}

.photo {
    width: 130px;
    height: 160px;
    border: 3px solid #FFFFFF;
    border-radius: 12px;
    object-fit: cover;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.grid-details {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px 24px;
}

.grid-details-full {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px 24px;
}

.data-item {
    padding: 10px 14px;
    background: #F8FAFC;
    border: 1px solid #F1F5F9;
    border-radius: 10px;
}

.data-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}

.data-value {
    font-size: 13.5px;
    font-weight: 600;
    color: #0F172A;
    word-break: break-word;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
}

table { 
    width: 100%; 
    border-collapse: collapse; 
    text-align: left; 
}

th, td { 
    padding: 12px 18px; 
    font-size: 13px;
}

th { 
    background: #F8FAFC; 
    color: #475569; 
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #E2E8F0;
}

td {
    border-bottom: 1px solid #F1F5F9;
    color: #334155;
}

tr:last-child td {
    border-bottom: none;
}

tbody tr:hover td {
    background: #F8FAFC;
}

.badge-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-pass { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.badge-fail { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }

@media print {
    .sidebar, .topbar, .action-buttons { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .content-body { padding: 0 !important; }
    .section { border: 1px solid #CBD5E1 !important; box-shadow: none !important; page-break-inside: avoid; }
}

@media (max-width: 1024px) {
    .sidebar { width: 70px; }
    .sidebar-brand span, .sidebar-brand h2, .sidebar-menu span, .nav-category, .sidebar-footer span { display: none; }
    .sidebar-brand { justify-content: center; padding: 15px 0; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar, .content-body { padding-left: 20px; padding-right: 20px; }
    .basic-details-wrap { flex-direction: column; align-items: center; }
    .grid-details, .grid-details-full { grid-template-columns: 1fr; }
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
            <li><a href="student_records_list.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Students Record</span></a></li>
            <li><a href="bio_data_list.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis_form_list.php"><i class="fa-solid fa-database"></i> <span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>

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
            <h1>Student Profile Record</h1>
            <p>Comprehensive academic record and semester evaluation</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> HOD Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($hod_name)?>
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="page-header">
            <div class="student-hero">
                <div class="student-hero-icon">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($student['name'] ?? 'Student'); ?></h1>
                    <p>Admission No: <b><?php echo htmlspecialchars($student['admission_no'] ?? '-'); ?></b> &bull; Roll: <b><?php echo htmlspecialchars($student['roll_no'] ?? '-'); ?></b></p>
                </div>
            </div>
            <div class="action-buttons">
                <a href="<?php echo htmlspecialchars($records_link); ?>" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Records</a>
                <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="btn btn-dash"><i class="fa-solid fa-house"></i> Dashboard</a>
                <?php if ($role === 'admin'): ?>
                    <a href="<?php echo htmlspecialchars($edit_link); ?>?id=<?php echo urlencode($id); ?>" class="btn btn-edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($print_link); ?>?id=<?php echo urlencode($id); ?>" class="btn btn-print" target="_blank"><i class="fa-solid fa-print"></i> Print PDF</a>
            </div>
        </div>
        
        <!-- PAGE 1: BASIC DETAILS -->
        <div class="section">
            <h3><i class="fa-solid fa-address-card"></i> Basic Details & Admission Information</h3>
            <?php 
                $photo = (!empty($student['photo_url'])) ? $student['photo_url'] : 'https://via.placeholder.com/130x160?text=No+Photo';
            ?>
            <div class="basic-details-wrap">
                <div class="photo-container">
                    <img src="<?php echo htmlspecialchars($photo); ?>" class="photo" alt="Student Photo">
                </div>
                <div class="grid-details">
                    <div class="data-item"><div class="data-label">Full Name</div><div class="data-value"><?php echo htmlspecialchars($student['name'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Date of Birth</div><div class="data-value"><?php echo htmlspecialchars($student['dob'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Admission Number</div><div class="data-value"><?php echo htmlspecialchars($student['admission_no'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Roll Number</div><div class="data-value"><?php echo htmlspecialchars($student['roll_no'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Parent / Guardian</div><div class="data-value"><?php echo htmlspecialchars($student['parent_name'] ?? '-'); ?> (<?php echo htmlspecialchars($student['parent_occupation'] ?? '-'); ?>)</div></div>
                    <div class="data-item"><div class="data-label">Parent Contact / Address</div><div class="data-value"><?php echo htmlspecialchars($student['parent_address'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Permanent Address</div><div class="data-value"><?php echo htmlspecialchars($student['permanent_address'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Course</div><div class="data-value"><?php echo htmlspecialchars($student['course'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Main Subject</div><div class="data-value"><?php echo htmlspecialchars($student['main_subject'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Medium of Instruction</div><div class="data-value"><?php echo htmlspecialchars($student['medium'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Ancillary Subjects</div><div class="data-value"><?php echo htmlspecialchars($student['ancillary_subjects'] ?? '-'); ?></div></div>
                    <div class="data-item"><div class="data-label">Exam Register Number</div><div class="data-value"><?php echo htmlspecialchars($student['exam_reg_no'] ?? '-'); ?></div></div>
                </div>
            </div>
        </div>

        <!-- SEMESTER MARKS 1-3 -->
        <?php for ($s = 1; $s <= 3; $s++): ?>
        <div class="section">
            <h3><i class="fa-solid fa-book-open"></i> Semester <?php echo $s; ?> Evaluation</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Subject</th><th>University Exam (UE)</th><th>Internal (IA)</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php 
                    $marks_list = get_semester_marks($student["sem{$s}_marks"] ?? '[]');
                    if (!empty($marks_list) && is_array($marks_list)):
                        foreach ($marks_list as $row): 
                            $pf = $row['pass_fail'] ?? '';
                            $badge_class = (strtolower($pf) === 'pass') ? 'badge-pass' : ((strtolower($pf) === 'fail') ? 'badge-fail' : '');
                        ?>
                        <tr>
                            <td style="font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($row['subject'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['ue'] ?? '0'); ?></td>
                            <td><?php echo htmlspecialchars($row['ia'] ?? '0'); ?></td>
                            <td style="font-weight: 700;"><?php echo htmlspecialchars($row['total'] ?? '0'); ?></td>
                            <td>
                                <?php if($badge_class): ?>
                                    <span class="badge-status <?php echo $badge_class; ?>"><?php echo htmlspecialchars($pf); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($pf); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#94A3B8; padding: 25px;">No marks recorded for Semester <?php echo $s; ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endfor; ?>

        <!-- ATTENDANCE & OTHERS -->
        <div class="section">
            <h3><i class="fa-solid fa-chart-line"></i> Attendance & Academic Recognition</h3>
            <div class="grid-details-full">
                <div class="data-item"><div class="data-label">Days Present / Working Days</div><div class="data-value"><?php echo htmlspecialchars($student['days_present'] ?? '-'); ?> / <?php echo htmlspecialchars($student['working_days'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Community Service</div><div class="data-value"><?php echo htmlspecialchars($student['community_service'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Special Activities</div><div class="data-value"><?php echo htmlspecialchars($student['special_activities'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Final Exam Result</div><div class="data-value"><?php echo htmlspecialchars($student['final_exam_result'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Scholarships Awarded</div><div class="data-value"><?php echo htmlspecialchars($student['scholarships'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Prizes & Honors</div><div class="data-value"><?php echo htmlspecialchars($student['prizes'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Class Rank</div><div class="data-value"><?php echo htmlspecialchars($student['class_rank'] ?? '-'); ?></div></div>
            </div>
        </div>

       <!-- PAGE 2: BIO DATA & SEM 4-6 -->
        <div class="section">
            <h3><i class="fa-solid fa-id-card"></i> Advanced Bio Data & Higher Semesters (4 - 6)</h3>
            <div class="grid-details-full">
                <div class="data-item"><div class="data-label">Date of Joining</div><div class="data-value"><?php echo htmlspecialchars($student['date_of_joining'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Community / Category</div><div class="data-value"><?php echo htmlspecialchars($student['community'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Date of Leaving</div><div class="data-value"><?php echo htmlspecialchars($student['date_of_leaving'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Qualifying Exam at Admission</div><div class="data-value"><?php echo htmlspecialchars($student['exam_at_admission'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Last School / College Attended</div><div class="data-value"><?php echo htmlspecialchars($student['last_college'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Part I Language</div><div class="data-value"><?php echo htmlspecialchars($student['part1_language'] ?? '-'); ?></div></div>
            </div>

            <?php for ($s = 4; $s <= 6; $s++): ?>
            <h4><i class="fa-solid fa-layer-group" style="color:#7C3AED;"></i> Semester <?php echo $s; ?> Evaluation</h4>
            <div class="table-responsive" style="margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr><th>Subject</th><th>University Exam (UE)</th><th>Internal (IA)</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php 
                    $marks_list = get_semester_marks($student["sem{$s}_marks"] ?? '[]');
                    if (!empty($marks_list) && is_array($marks_list)):
                        foreach ($marks_list as $row): 
                            $pf = $row['pass_fail'] ?? '';
                            $badge_class = (strtolower($pf) === 'pass') ? 'badge-pass' : ((strtolower($pf) === 'fail') ? 'badge-fail' : '');
                        ?>
                        <tr>
                            <td style="font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($row['subject'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['ue'] ?? '0'); ?></td>
                            <td><?php echo htmlspecialchars($row['ia'] ?? '0'); ?></td>
                            <td style="font-weight: 700;"><?php echo htmlspecialchars($row['total'] ?? '0'); ?></td>
                            <td>
                                <?php if($badge_class): ?>
                                    <span class="badge-status <?php echo $badge_class; ?>"><?php echo htmlspecialchars($pf); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($pf); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#94A3B8; padding: 25px;">No marks recorded for Semester <?php echo $s; ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endfor; ?>

            <h4 style="margin-top:25px;"><i class="fa-solid fa-clipboard-user" style="color:#7C3AED;"></i> Character & Conduct Evaluation</h4>
            <div class="grid-details-full">
                <div class="data-item"><div class="data-label">Capacity for Independent Work</div><div class="data-value"><?php echo htmlspecialchars($student['independent_work'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">General Conduct</div><div class="data-value"><?php echo htmlspecialchars($student['conduct'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Co-operation & Team Spirit</div><div class="data-value"><?php echo htmlspecialchars($student['cooperation'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Leadership Qualities</div><div class="data-value"><?php echo htmlspecialchars($student['leadership'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Overall Assessment</div><div class="data-value"><?php echo htmlspecialchars($student['overall_assessment'] ?? '-'); ?></div></div>
                <div class="data-item"><div class="data-label">Head of Department</div><div class="data-value"><?php echo htmlspecialchars($student['hod_name'] ?? '-'); ?></div></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>