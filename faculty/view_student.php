<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Faculty Role Check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Faculty') { 
    header("Location: ../auth/login.php"); 
    exit; 
}

$display_role = $_SESSION['role'];
$base_url = "/SRMS"; 

$dashboard_link = $base_url . "/faculty/dashboard_faculty.php";
$records_link   = $base_url . "/faculty/student_record.php";
$print_link     = $base_url . "/faculty/print_student.php";

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
$parent_phone = trim($student['parent_phone'] ?? '');

// Helper function to decode and normalize JSON marks dynamically
function get_semester_marks($marks_raw) {
    if (empty($marks_raw)) return [];
    
    $data = is_string($marks_raw) ? json_decode($marks_raw, true) : $marks_raw;
    if (!is_array($data)) return [];

    $normalized = [];
    foreach ($data as $row) {
        // Safe key extraction for Subject, IA, UE
        $subject = $row['subject'] ?? $row['Subject'] ?? '';
        
        $ia = 0;
        if (isset($row['ia'])) $ia = intval($row['ia']);
        elseif (isset($row['ia_marks'])) $ia = intval($row['ia_marks']);
        elseif (isset($row['I.A'])) $ia = intval($row['I.A']);

        $ue = 0;
        if (isset($row['ue'])) $ue = intval($row['ue']);
        elseif (isset($row['ue_marks'])) $ue = intval($row['ue_marks']);
        elseif (isset($row['U.E'])) $ue = intval($row['U.E']);

        // Calculate Total
        $total = isset($row['total']) ? intval($row['total']) : ($ia + $ue);
        if ($total == 0 && ($ia > 0 || $ue > 0)) {
            $total = $ia + $ue;
        }

        // Determine Pass / Fail Status
        $pass_fail = '';
        if (!empty($row['pass_fail'])) {
            $pass_fail = $row['pass_fail'];
        } elseif (!empty($row['result'])) {
            $pass_fail = $row['result'];
        } elseif (!empty($row['pf'])) {
            $pass_fail = $row['pf'];
        } else {
            // Standard Academic Pass Condition: IA>=10, UE>=30, Total>=40
            $pass_fail = ($ia >= 10 && $ue >= 30 && $total >= 40) ? 'Pass' : 'Fail';
        }

        $normalized[] = [
            'subject'   => $subject,
            'ia'        => $ia,
            'ue'        => $ue,
            'total'     => $total,
            'pass_fail' => ucfirst(strtolower($pass_fail))
        ];
    }

    return $normalized;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Student - <?php echo htmlspecialchars($student['name'] ?? 'Student'); ?></title>
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

.profile-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.03);
}

/* Action Header */
.action-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #E2E8F0;
    align-items: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-back { background: #64748B; color: #FFFFFF; }
.btn-back:hover { background: #475569; }

.btn-dashboard { background: #0B132B; color: #FFFFFF; }
.btn-dashboard:hover { background: #1E293B; }

.btn-whatsapp { background: #25D366; color: #FFFFFF; }
.btn-whatsapp:hover { background: #20BA5A; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); }

.btn-msg { background: #8B5CF6; color: #FFFFFF; }
.btn-msg:hover { background: #7C3AED; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.25); }

.btn-print { background: #10B981; color: #FFFFFF; }
.btn-print:hover { background: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25); }

/* Sections */
.section {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 25px;
    background: #FAFBFC;
}

.section h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #2563EB;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
}

.section h4 {
    font-size: 14px;
    color: #1E293B;
    margin: 22px 0 12px 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-header-grid {
    display: flex;
    gap: 25px;
    align-items: flex-start;
}

.photo-container {
    flex-shrink: 0;
}

.photo {
    width: 140px;
    height: 170px;
    border: 3px solid #FFFFFF;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-radius: 12px;
    object-fit: cover;
    background: #F1F5F9;
}

.info-grid {
    flex-grow: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
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
    grid-column: span 2;
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
    font-weight: 600;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #FFFFFF;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #E2E8F0;
}

th, td {
    padding: 12px 16px;
    text-align: center;
    font-size: 13px;
    border-bottom: 1px solid #E2E8F0;
}

th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    color: #334155;
}

tr:hover td {
    background: #F8FAFC;
}

.badge-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}

.badge-pass { background: #DCFCE7; color: #15803D; }
.badge-fail { background: #FEE2E2; color: #B91C1C; }

@media(max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .profile-header-grid { flex-direction: column; align-items: center; }
    .info-grid { grid-template-columns: 1fr; }
    .row.full-width { grid-column: span 1; }
}

@media print {
    .sidebar, .topbar, .action-bar { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .profile-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
}
</style>
</head>
<body>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Faculty Portal</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="<?php echo $dashboard_link; ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <li><a href="<?php echo $records_link; ?>" class="active"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
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

<!-- MAIN CONTENT WRAPPER -->
<div class="main-content">
    
    <div class="topbar">
        <h2><i class="fa-solid fa-id-card"></i> Student Profile Details</h2>
        <div class="topbar-right">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="profile-card">
            
            <!-- ACTION BUTTONS -->
            <div class="action-bar">
                <a href="<?php echo $records_link; ?>" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Records</a>
                <a href="<?php echo $dashboard_link; ?>" class="btn btn-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a>
                
               <?php if (!empty($parent_phone)): ?>
                <!-- WhatsApp Message Button -->
                <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $parent_phone); ?>?text=Hello%20Respected%20Parent,%20Regarding%20student%20<?php echo urlencode($student['name'] ?? ''); ?>..." target="_blank" class="btn btn-whatsapp">
                    <i class="fa-brands fa-whatsapp"></i> WhatsApp Parent
                </a>
                
                <!-- SMS Message Button -->
                <a href="sms:<?php echo htmlspecialchars($parent_phone); ?>?body=Hello%20Parent,%20Regarding%20student%20<?php echo urlencode($student['name'] ?? ''); ?>" class="btn btn-msg">
                    <i class="fa-solid fa-comment-sms"></i> Send SMS
                </a>
               <?php endif; ?>
                
                <a href="<?php echo $print_link; ?>?id=<?php echo urlencode($id); ?>" class="btn btn-print" target="_blank"><i class="fa-solid fa-print"></i> Print PDF</a>
            </div>
            
            <h2 style="color: #0F172A; font-size: 20px; margin-bottom: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-id-badge" style="color: #2563EB;"></i> Record for: <?php echo htmlspecialchars($student['name'] ?? ''); ?>
            </h2>
            
            <!-- PAGE 1 BASIC DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-user"></i> Page 1: Basic Details</h3>
                
                <div class="profile-header-grid">
                    <div class="photo-container">
                        <?php $photo = (!empty($student['photo_url'])) ? $student['photo_url'] : 'https://via.placeholder.com/140x170?text=No+Photo'; ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" class="photo" alt="Student Photo">
                    </div>
                    
                    <div class="info-grid">
                        <div class="row"><div class="label">1. Name</div><div class="value"><?php echo htmlspecialchars($student['name'] ?? '-'); ?></div></div>
                        <div class="row"><div class="label">2. Date of Birth</div><div class="value"><?php echo htmlspecialchars($student['dob'] ?? '-'); ?></div></div>
                        <div class="row"><div class="label">3. Admission Number</div><div class="value"><?php echo htmlspecialchars($student['admission_no'] ?? '-'); ?></div></div>
                        <div class="row"><div class="label">4. Roll Number</div><div class="value"><?php echo htmlspecialchars($student['roll_no'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <div class="info-grid" style="margin-top: 12px;">
                    <div class="row full-width">
                        <div class="label">5. Parent Details & Address</div>
                        <div class="value">
                            <?php echo htmlspecialchars($student['parent_name'] ?? ''); ?> 
                            <?php echo !empty($student['parent_occupation']) ? '(' . htmlspecialchars($student['parent_occupation']) . ')' : ''; ?><br>
                            <span style="font-weight: 400; color: #475569;"><?php echo htmlspecialchars($student['parent_address'] ?? '-'); ?></span>
                            <?php if(!empty($parent_phone)): ?>
                                <br><span style="color: #2563EB; font-weight: 600;"><i class="fa-solid fa-phone"></i> Phone: <?php echo htmlspecialchars($parent_phone); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row full-width"><div class="label">6. Permanent Address</div><div class="value" style="font-weight: 400; color: #475569;"><?php echo htmlspecialchars($student['permanent_address'] ?? '-'); ?></div></div>
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
                <h3><i class="fa-solid fa-graduation-cap"></i> Semester <?php echo $s; ?> Marks</h3>
                <table>
                    <thead>
                        <tr><th>Subject</th><th>U.E</th><th>I.A</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php 
                    $marks_list = get_semester_marks($student["sem{$s}_marks"] ?? '[]');
                    if (!empty($marks_list) && is_array($marks_list)):
                        foreach ($marks_list as $row): 
                            $pf = $row['pass_fail'];
                            $is_pass = (strtolower($pf) === 'pass');
                        ?>
                        <tr>
                            <td style="text-align: left; padding-left: 20px; font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo htmlspecialchars($row['ue']); ?></td>
                            <td><?php echo htmlspecialchars($row['ia']); ?></td>
                            <td><b><?php echo htmlspecialchars($row['total']); ?></b></td>
                            <td><span class="badge-status <?php echo $is_pass ? 'badge-pass' : 'badge-fail'; ?>"><?php echo htmlspecialchars($pf); ?></span></td>
                        </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="5" style="color: #64748B; padding: 20px;">No marks record found for Semester <?php echo $s; ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endfor; ?>

            <!-- ATTENDANCE & OTHERS -->
            <div class="section">
                <h3><i class="fa-solid fa-chart-line"></i> Attendance & Results</h3>
                <div class="info-grid">
                    <div class="row"><div class="label">Days Present / Working Days</div><div class="value"><?php echo htmlspecialchars($student['days_present'] ?? '-'); ?> / <?php echo htmlspecialchars($student['working_days'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Class Rank</div><div class="value"><?php echo htmlspecialchars($student['class_rank'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Community Service</div><div class="value"><?php echo htmlspecialchars($student['community_service'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">Special Activities</div><div class="value"><?php echo htmlspecialchars($student['special_activities'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">Final Exam Result</div><div class="value"><?php echo htmlspecialchars($student['final_exam_result'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">Scholarships & Concessions</div><div class="value" style="font-weight: 400; color: #475569;"><?php echo htmlspecialchars($student['scholarships'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">College Prizes Awarded</div><div class="value" style="font-weight: 400; color: #475569;"><?php echo htmlspecialchars($student['prizes'] ?? '-'); ?></div></div>
                </div>
            </div>

            <!-- PAGE 2 BIO DATA & SEMESTER MARKS 4-6 -->
            <div class="section">
                <h3><i class="fa-solid fa-id-card"></i> Page 2: Bio Data & Sem 4-6</h3>
                
                <div class="info-grid" style="margin-bottom: 20px;">
                    <div class="row"><div class="label">3. Date of Joining</div><div class="value"><?php echo htmlspecialchars($student['date_of_joining'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">5. Date of Leaving</div><div class="value"><?php echo htmlspecialchars($student['date_of_leaving'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">4. Community</div><div class="value"><?php echo htmlspecialchars($student['community'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">8. Part I Language</div><div class="value"><?php echo htmlspecialchars($student['part1_language'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">6. Exam Passed at Admission</div><div class="value"><?php echo htmlspecialchars($student['exam_at_admission'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">7. College Last Studied</div><div class="value"><?php echo htmlspecialchars($student['last_college'] ?? '-'); ?></div></div>
                </div>

                <?php for ($s = 4; $s <= 6; $s++): ?>
                <h4><i class="fa-solid fa-book-bookmark" style="color: #2563EB;"></i> Semester <?php echo $s; ?> Marks</h4>
                <table>
                    <thead>
                        <tr><th>Subject</th><th>U.E</th><th>I.A</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php 
                    $marks_list = get_semester_marks($student["sem{$s}_marks"] ?? '[]');
                    if (!empty($marks_list) && is_array($marks_list)):
                        foreach ($marks_list as $row): 
                            $pf = $row['pass_fail'];
                            $is_pass = (strtolower($pf) === 'pass');
                        ?>
                        <tr>
                            <td style="text-align: left; padding-left: 20px; font-weight: 600; color: #0F172A;"><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo htmlspecialchars($row['ue']); ?></td>
                            <td><?php echo htmlspecialchars($row['ia']); ?></td>
                            <td><b><?php echo htmlspecialchars($row['total']); ?></b></td>
                            <td><span class="badge-status <?php echo $is_pass ? 'badge-pass' : 'badge-fail'; ?>"><?php echo htmlspecialchars($pf); ?></span></td>
                        </tr>
                    <?php endforeach; 
                    else: ?>
                        <tr><td colspan="5" style="color: #64748B; padding: 20px;">No marks record found for Semester <?php echo $s; ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php endfor; ?>

                <div class="info-grid" style="margin-top: 20px;">
                    <div class="row"><div class="label">15. Independent Work</div><div class="value"><?php echo htmlspecialchars($student['independent_work'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">16. Conduct</div><div class="value"><?php echo htmlspecialchars($student['conduct'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">17. Co-operation</div><div class="value"><?php echo htmlspecialchars($student['cooperation'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">18. Leadership</div><div class="value"><?php echo htmlspecialchars($student['leadership'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">19. Overall Assessment</div><div class="value"><?php echo htmlspecialchars($student['overall_assessment'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">20. HOD Name</div><div class="value"><?php echo htmlspecialchars($student['hod_name'] ?? '-'); ?></div></div>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>