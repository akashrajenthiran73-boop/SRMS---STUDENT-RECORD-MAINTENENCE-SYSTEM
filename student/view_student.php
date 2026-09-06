<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Session Auth Check
if (!isset($_SESSION['role'])) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

$role = strtolower($_SESSION['role']);
$display_role = $_SESSION['role'];

$base_url = ""; 

if ($role === 'student') {
    $dashboard_link = $base_url . "/student/dashboard_student.php";
    $records_link   = $base_url . "/student/student_record.php";
} else {
    $dashboard_link = $base_url . "/auth/login.php";
    $records_link   = $base_url . "/index.php";
}

// 2. Multi-location Safe .env File Loader
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

// 3. Supabase API Call
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

// 4. Helper function to decode JSON marks safely
function get_semester_marks($marks_raw) {
    if (is_string($marks_raw)) {
        return json_decode($marks_raw, true) ?? [];
    }
    return is_array($marks_raw) ? $marks_raw : [];
}
$user_name = $student['name'] ?? ($_SESSION['name'] ?? $_SESSION['username'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Record - <?php echo htmlspecialchars($student['name'] ?? 'Student'); ?></title>
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

/* Sidebar */
.sidebar {
    width: 260px;
    background: #0B132B;
    color: #FFFFFF;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    z-index: 100;
    justify-content: space-between;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
}
.sidebar-content { overflow-y: auto; flex: 1; }
.sidebar-header {
    padding: 24px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.sidebar-header .logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #FFFFFF;
    box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);
}
.sidebar-header h2 { font-size: 16px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.2px; }
.sidebar-header span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.sidebar-menu { list-style: none; padding: 16px 12px; }
.sidebar-menu li { margin-bottom: 3px; }
.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #94A3B8;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
}
.sidebar-menu a.active {
    background: #059669;
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}
.sidebar-menu a i { width: 18px; text-align: center; font-size: 14px; }
.nav-category {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748B;
    padding: 14px 14px 6px;
    font-weight: 700;
}
.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    transition: 0.2s;
}
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }

/* Main Content Area */
.main-content {
    margin-left: 260px;
    width: calc(100% - 260px);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Topbar */
.topbar {
    background: #FFFFFF;
    border-bottom: 1px solid #E2E8F0;
    padding: 16px 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 90;
}
.topbar-title h1 { font-size: 20px; font-weight: 800; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.content-body {
    padding: 32px 36px;
    width: 100%;
}

/* Profile Card */
.profile-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

/* Action Header */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid #E2E8F0;
}
.action-bar-left {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-back { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }
.btn-back:hover { background: #E2E8F0; color: #0F172A; }

.btn-dashboard { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }
.btn-dashboard:hover { background: #E2E8F0; color: #0F172A; }

.btn-print {
    background: #059669;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}
.btn-print:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.dossier-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
.dossier-header h2 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
}
.dossier-header span {
    font-size: 13px;
    color: #64748B;
}

/* Sections */
.section {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    background: #FAFBFC;
}

.section h3 {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #059669;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
}

.section h4 {
    font-size: 13.5px;
    color: #1E293B;
    margin: 20px 0 10px 0;
    font-weight: 800;
}

.profile-header-grid {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

.photo-container {
    flex-shrink: 0;
}

.photo {
    width: 120px;
    height: 150px;
    border: 2px solid #CBD5E1;
    border-radius: 12px;
    object-fit: cover;
    background: #FFFFFF;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.info-grid {
    flex-grow: 1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 12px;
}

.row {
    display: flex;
    flex-direction: column;
    background: #FFFFFF;
    padding: 12px 16px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
}

.row.full-width {
    grid-column: 1 / -1;
}

.label {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748B;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.value {
    font-size: 13.5px;
    color: #0F172A;
    font-weight: 700;
    word-break: break-word;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    margin-top: 12px;
    background: #FFFFFF;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    border-bottom: 1px solid #E2E8F0;
    padding: 12px 14px;
    text-align: left;
    font-size: 13px;
}

th {
    background: #F8FAFC;
    color: #334155;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

tr:last-child td {
    border-bottom: none;
}

td {
    color: #1E293B;
}

/* Print Styles */
@media print {
    .sidebar, .topbar, .action-bar {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    .content-body {
        padding: 0 !important;
    }
    body {
        background-color: #FFFFFF !important;
    }
    .profile-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>
</head>
<body>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Student Administration</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_student.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
            <li><a href="student_record.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
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

<!-- MAIN CONTENT WRAPPER -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Student Profile Dossier</h1>
            <p>Comprehensive academic history and permanent register archive</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($display_role); ?>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="profile-card">
            
            <!-- ACTION BUTTONS -->
            <div class="action-bar">
                <div class="action-bar-left">
                    <a href="<?php echo $records_link; ?>" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Records</a>
                    <a href="<?php echo $dashboard_link; ?>" class="btn btn-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a>
                </div>
                <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print Official Dossier</button>
            </div>
            
            <div class="dossier-header">
                <i class="fa-solid fa-file-invoice" style="font-size: 24px; color: #059669;"></i>
                <div>
                    <h2>Academic Record: <?php echo htmlspecialchars($student['name'] ?? ''); ?></h2>
                    <span>Registration No: <strong><?php echo htmlspecialchars($student['exam_reg_no'] ?? 'N/A'); ?></strong></span>
                </div>
            </div>
            
            <!-- PAGE 1 BASIC DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-user"></i> Page 1: Basic Details</h3>
                
                <div class="profile-header-grid">
                    <div class="photo-container">
                        <?php $photo = (!empty($student['photo_url'])) ? $student['photo_url'] : 'https://via.placeholder.com/120x150?text=No+Photo'; ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" class="photo" alt="Student Photo">
                    </div>
                    
                    <div class="info-grid">
                        <div class="row"><div class="label">1. Name</div><div class="value"><?php echo htmlspecialchars($student['name'] ?? '-'); ?></div></div>
                        <div class="row"><div class="label">2. Date of Birth</div><div class="value"><?php echo htmlspecialchars($student['dob'] ?? '-'); ?></div></div>
                        <div class="row"><div class="label">3. Admission Number</div><div class="value"><?php echo htmlspecialchars($student['admission_no'] ?? '-'); ?></div></div>
                        <div class="row"><div class="label">4. Roll Number</div><div class="value"><?php echo htmlspecialchars($student['roll_no'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <div class="info-grid" style="margin-top: 14px;">
                    <div class="row full-width">
                        <div class="label">5. Parent Details & Address</div>
                        <div class="value">
                            <?php echo htmlspecialchars($student['parent_name'] ?? ''); ?> 
                            <?php echo !empty($student['parent_occupation']) ? '(' . htmlspecialchars($student['parent_occupation']) . ')' : ''; ?><br>
                            <span style="font-weight: 500; color: #475569; font-size: 13px;"><?php echo htmlspecialchars($student['parent_address'] ?? '-'); ?></span>
                        </div>
                    </div>
                    <div class="row full-width"><div class="label">6. Permanent Address</div><div class="value" style="font-weight: 500; color: #475569; font-size: 13px;"><?php echo htmlspecialchars($student['permanent_address'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">7. Course</div><div class="value"><?php echo htmlspecialchars($student['course'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">8. Main Subject</div><div class="value"><?php echo htmlspecialchars($student['main_subject'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">9. Medium</div><div class="value"><?php echo htmlspecialchars($student['medium'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">10. Ancillary Subjects</div><div class="value"><?php echo htmlspecialchars($student['ancillary_subjects'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">11. Exam Register No</div><div class="value"><?php echo htmlspecialchars($student['exam_reg_no'] ?? '-'); ?></div></div>
                </div>
            </div>

            <!-- SEMESTER MARKS 1-3 -->
            <?php for ($s = 1; $s <= 3; $s++): ?>
            <div class="section">
                <h3><i class="fa-solid fa-graduation-cap"></i> Semester <?php echo $s; ?> Performance</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Subject Name / Code</th><th>U.E</th><th>I.A</th><th>Total</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php 
                        $marks_list = get_semester_marks($student["sem{$s}_marks"] ?? '[]');
                        if (!empty($marks_list) && is_array($marks_list)):
                            foreach ($marks_list as $row): 
                                $pf = $row['pass_fail'] ?? '';
                                $pf_style = ($pf === 'Pass') ? 'background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0;' : (($pf === 'Fail') ? 'background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;' : '');
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['subject'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['ue'] ?? '0'); ?></td>
                                <td><?php echo htmlspecialchars($row['ia'] ?? '0'); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['total'] ?? '0'); ?></strong></td>
                                <td>
                                    <span style="display:inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; <?php echo $pf_style; ?>">
                                        <?php echo htmlspecialchars($pf ?: '-'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; 
                        else: ?>
                            <tr><td colspan="5" style="text-align:center; color: #64748B; padding: 18px;">No marks record found for Semester <?php echo $s; ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endfor; ?>

            <!-- ATTENDANCE & OTHERS -->
            <div class="section">
                <h3><i class="fa-solid fa-chart-line"></i> Attendance & Curricular Records</h3>
                <div class="info-grid">
                    <div class="row"><div class="label">Days Present / Working Days</div><div class="value"><?php echo htmlspecialchars($student['days_present'] ?? '-'); ?> / <?php echo htmlspecialchars($student['working_days'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Class Rank</div><div class="value"><?php echo htmlspecialchars($student['class_rank'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Community Service</div><div class="value"><?php echo htmlspecialchars($student['community_service'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Special Activities</div><div class="value"><?php echo htmlspecialchars($student['special_activities'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">Final Exam Result</div><div class="value"><?php echo htmlspecialchars($student['final_exam_result'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">Scholarships & Concessions</div><div class="value" style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($student['scholarships'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">College Prizes Awarded</div><div class="value" style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($student['prizes'] ?? '-'); ?></div></div>
                </div>
            </div>

           <!-- PAGE 2 -->
            <div class="section">
                <h3><i class="fa-solid fa-id-card"></i> Page 2: Bio Data & Sem 4-6</h3>
                
                <div class="info-grid" style="margin-bottom: 20px;">
                    <div class="row"><div class="label">Date of Joining</div><div class="value"><?php echo htmlspecialchars($student['date_of_joining'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Date of Leaving</div><div class="value"><?php echo htmlspecialchars($student['date_of_leaving'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Community</div><div class="value"><?php echo htmlspecialchars($student['community'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Part I Language</div><div class="value"><?php echo htmlspecialchars($student['part1_language'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">Exam Passed at Admission</div><div class="value"><?php echo htmlspecialchars($student['exam_at_admission'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">College Last Studied</div><div class="value"><?php echo htmlspecialchars($student['last_college'] ?? '-'); ?></div></div>
                </div>

                <?php for ($s = 4; $s <= 6; $s++): ?>
                <h4>Semester <?php echo $s; ?> Performance</h4>
                <div class="table-responsive" style="margin-bottom: 16px;">
                    <table>
                        <thead>
                            <tr><th>Subject Name / Code</th><th>U.E</th><th>I.A</th><th>Total</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php 
                        $marks_list = get_semester_marks($student["sem{$s}_marks"] ?? '[]');
                        if (!empty($marks_list) && is_array($marks_list)):
                            foreach ($marks_list as $row): 
                                $pf = $row['pass_fail'] ?? '';
                                $pf_style = ($pf === 'Pass') ? 'background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0;' : (($pf === 'Fail') ? 'background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;' : '');
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['subject'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['ue'] ?? '0'); ?></td>
                                <td><?php echo htmlspecialchars($row['ia'] ?? '0'); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['total'] ?? '0'); ?></strong></td>
                                <td>
                                    <span style="display:inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; <?php echo $pf_style; ?>">
                                        <?php echo htmlspecialchars($pf ?: '-'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; 
                        else: ?>
                            <tr><td colspan="5" style="text-align:center; color: #64748B; padding: 18px;">No marks record found for Semester <?php echo $s; ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endfor; ?>

                <div class="info-grid" style="margin-top: 20px;">
                    <div class="row"><div class="label">Independent Work</div><div class="value"><?php echo htmlspecialchars($student['independent_work'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Conduct</div><div class="value"><?php echo htmlspecialchars($student['conduct'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Co-operation</div><div class="value"><?php echo htmlspecialchars($student['cooperation'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Leadership</div><div class="value"><?php echo htmlspecialchars($student['leadership'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Overall Assessment</div><div class="value"><?php echo htmlspecialchars($student['overall_assessment'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">HOD Name</div><div class="value"><?php echo htmlspecialchars($student['hod_name'] ?? '-'); ?></div></div>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>