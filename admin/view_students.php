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

// 2. Dynamic ABSOLUTE Links based on User Role (Admin / HOD)
$base_url = ""; 

if ($role === 'admin' || $role === 'super admin') {
    $dashboard_link = $base_url . "/admin/dashboard_admin.php";
    $records_link   = $base_url . "/admin/student_records.php";
    $edit_link      = $base_url . "/admin/edit_student.php";
    $print_link     = $base_url . "/admin/print_student.php";
} elseif ($role === 'hod') {
    $dashboard_link = $base_url . "/hod/dashboard_hod.php"; 
    $records_link   = $base_url . "/hod/student_records.php"; 
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

// 5. Helper function for Array Parsing
function parse_array_field($field_data) {
    if (is_array($field_data)) return $field_data;
    if (is_string($field_data) && !empty($field_data)) {
        $clean = trim($field_data, '{}');
        if ($clean === '') return [];
        $decoded = json_decode($field_data, true);
        if (is_array($decoded)) return $decoded;
        return array_map(function($item) {
            return trim($item, ' "');
        }, explode(',', $clean));
    }
    return [];
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

.profile-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

/* Action Header */
.action-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1.5px solid #F1F5F9;
    align-items: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.25s ease;
}

.btn-back {
    background: #FFFFFF;
    color: #475569;
    border: 1.5px solid #E2E8F0;
}
.btn-back:hover {
    background: #F8FAFC;
    color: #0F172A;
    border-color: #CBD5E1;
}

.btn-dashboard {
    background: #F1F5F9;
    color: #334155;
    border: 1.5px solid #E2E8F0;
}
.btn-dashboard:hover {
    background: #E2E8F0;
    color: #0F172A;
}

.btn-edit {
    background: #F59E0B;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
}
.btn-edit:hover {
    background: #D97706;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
}

.btn-print {
    background: #10B981;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}
.btn-print:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

/* Sections */
.section {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    background: #FFFFFF;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.01);
}

.section h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #2563EB;
    font-weight: 700;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #F1F5F9;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section h4 {
    font-size: 13.5px;
    color: #1E3A8A;
    margin: 20px 0 10px 0;
    font-weight: 700;
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
    height: 175px;
    border: 3px solid #E2E8F0;
    border-radius: 12px;
    object-fit: cover;
    background: #F8FAFC;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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
    background: #F8FAFC;
    padding: 12px 16px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.row:hover {
    background: #F1F5F9;
    border-color: #CBD5E1;
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
    color: #1E293B;
    font-weight: 600;
}

/* Tables */
.table-responsive-wrapper {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    margin-top: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #FFFFFF;
}

th, td {
    padding: 12px 16px;
    text-align: center;
    font-size: 13px;
    border-bottom: 1px solid #F1F5F9;
}

th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1.5px solid #E2E8F0;
}

td {
    color: #334155;
}

tr:last-child td {
    border-bottom: none;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .profile-header-grid { flex-direction: column; align-items: center; }
    .info-grid { grid-template-columns: 1fr; }
    .row.full-width { grid-column: span 1; }
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
            <?php if ($role === 'hod'): ?>
                <li><a href="<?php echo $dashboard_link; ?>"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
                <li><a href="<?php echo $records_link; ?>" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
                <li><a href="../HOD/bio_data_list.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
                <li><a href="../HOD/umis_form_list.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Forms</span></a></li>
                <li><a href="../HOD/result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>
                <li><a href="../HOD/leave_approvals.php"><i class="fa-solid fa-calendar-check"></i> <span>Leave Approvals</span></a></li>
                <li><a href="../HOD/timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
                <hr class="menu-divider">
                <div class="menu-heading">HOD Panel</div>
                <li><a href="../HOD/announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
                <li><a href="../HOD/send_notice.php"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
                <li><a href="../HOD/support.php"><i class="fa-solid fa-circle-question"></i> <span>Support</span></a></li>
            <?php else: ?>
                <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
                <li><a href="student_records.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
                <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
                <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
                <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>
                <hr class="menu-divider">
                <div class="menu-heading">Admin Panel</div>
                <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
                <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
                <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
                <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
                <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <?php if ($role === 'hod'): ?>
                <li><a href="../HOD/hod_profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <?php else: ?>
                <li><a href="admin_profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <?php endif; ?>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </div>
</div>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-content">
    
    <div class="navbar">
        <h1>Student Profile Details</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <div class="profile-card">
            
            <!-- ACTION BUTTONS -->
            <div class="action-bar">
                <a href="<?php echo $records_link; ?>" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Records</a>
                <a href="<?php echo $dashboard_link; ?>" class="btn btn-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a>
                
                <?php if ($role === 'admin' || $role === 'super admin'): ?>
                    <a href="<?php echo $edit_link; ?>?id=<?php echo urlencode($id); ?>" class="btn btn-edit"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</a>
                <?php endif; ?>
                
                <a href="<?php echo $print_link; ?>?id=<?php echo urlencode($id); ?>" class="btn btn-print" target="_blank"><i class="fa-solid fa-print"></i> Print PDF</a>
            </div>
            
            <h2 style="color: #1E3A8A; font-size: 18px; margin-bottom: 20px; font-weight: 700;">
                <i class="fa-solid fa-id-badge" style="color: #2563EB;"></i> Record for: <?php echo htmlspecialchars($student['name'] ?? ''); ?>
            </h2>
            
            <!-- PAGE 1 BASIC DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-user"></i> Page 1: Basic Details</h3>
                
                <div class="profile-header-grid">
                    <div class="photo-container">
                        <?php $photo = (!empty($student['photo_url'])) ? $student['photo_url'] : 'https://via.placeholder.com/130x160?text=No+Photo'; ?>
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
            <tr>
                <th>Subject</th>
                <th>U.E</th>
                <th>I.A</th>
                <th>Total</th>
                <th>P/F</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        // Supabase JSON data decode
        $marks_raw = $student["sem{$s}_marks"] ?? '[]';
        
        if (is_string($marks_raw)) {
            $marks_list = json_decode($marks_raw, true) ?? [];
        } else {
            $marks_list = $marks_raw ?? [];
        }
        
        if (!empty($marks_list) && is_array($marks_list)):
            foreach ($marks_list as $row): 
                $pf = $row['pass_fail'] ?? '';
                $pf_class = ($pf === 'Pass') ? 'color: #10B981; font-weight: bold;' : (($pf === 'Fail') ? 'color: #EF4444; font-weight: bold;' : '');
            ?>
            <tr>
                <td style="text-align: left; padding-left: 15px;"><?php echo htmlspecialchars($row['subject'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['ue'] ?? '0'); ?></td>
                <td><?php echo htmlspecialchars($row['ia'] ?? '0'); ?></td>
                <td><b><?php echo htmlspecialchars($row['total'] ?? '0'); ?></b></td>
                <td style="<?php echo $pf_class; ?>"><?php echo htmlspecialchars($pf); ?></td>
            </tr>
        <?php endforeach; 
        else: ?>
            <tr>
                <td colspan="5" style="color: #64748B; padding: 15px; text-align: center;">
                    No marks record found for Semester <?php echo $s; ?>
                </td>
            </tr>
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

            <!-- PAGE 2 -->
            <div class="section">
                <h3><i class="fa-solid id-card"></i> Page 2: Bio Data & Sem 4-6</h3>
                
                <div class="info-grid" style="margin-bottom: 20px;">
                    <div class="row"><div class="label">3. Date of Joining</div><div class="value"><?php echo htmlspecialchars($student['date_of_joining'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">5. Date of Leaving</div><div class="value"><?php echo htmlspecialchars($student['date_of_leaving'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">4. Community</div><div class="value"><?php echo htmlspecialchars($student['community'] ?? '-'); ?></div></div>
                    <div class="row"><div class="label">8. Part I Language</div><div class="value"><?php echo htmlspecialchars($student['part1_language'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">6. Exam Passed at Admission</div><div class="value"><?php echo htmlspecialchars($student['exam_at_admission'] ?? '-'); ?></div></div>
                    <div class="row full-width"><div class="label">7. College Last Studied</div><div class="value"><?php echo htmlspecialchars($student['last_college'] ?? '-'); ?></div></div>
                </div>

<?php for ($s = 4; $s <= 6; $s++): ?>
<h4>Semester <?php echo $s; ?> Marks</h4>
<table>
    <thead>
        <tr>
            <th>Subject</th>
            <th>U.E</th>
            <th>I.A</th>
            <th>Total</th>
            <th>P/F</th>
        </tr>
    </thead>
    <tbody>
    <?php 

    $marks_raw = $student["sem{$s}_marks"] ?? '[]';
    
    if (is_string($marks_raw)) {
        $marks_list = json_decode($marks_raw, true) ?? [];
    } else {
        $marks_list = $marks_raw ?? [];
    }
    
    if (!empty($marks_list) && is_array($marks_list)):
        foreach ($marks_list as $row): 
            $pf = $row['pass_fail'] ?? '';
            $pf_class = ($pf === 'Pass') ? 'color: #10B981; font-weight: bold;' : (($pf === 'Fail') ? 'color: #EF4444; font-weight: bold;' : '');
        ?>
        <tr>
            <td style="text-align: left; padding-left: 15px;"><?php echo htmlspecialchars($row['subject'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['ue'] ?? '0'); ?></td>
            <td><?php echo htmlspecialchars($row['ia'] ?? '0'); ?></td>
            <td><b><?php echo htmlspecialchars($row['total'] ?? '0'); ?></b></td>
            <td style="<?php echo $pf_class; ?>"><?php echo htmlspecialchars($pf); ?></td>
        </tr>
    <?php endforeach; 
    else: ?>
        <tr>
            <td colspan="5" style="color: #64748B; padding: 15px; text-align: center;">
                No marks record found for Semester <?php echo $s; ?>
            </td>
        </tr>
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