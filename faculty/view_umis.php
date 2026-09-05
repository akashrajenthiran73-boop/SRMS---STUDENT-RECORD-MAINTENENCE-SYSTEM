<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Faculty Role Check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Faculty'){ 
    header("Location: ../auth/login.php"); 
    exit;
}

$display_role = $_SESSION['role'];

// 1. Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  // SRMS/.env (Root Folder)
    __DIR__ . '/.env',                     // SRMS/faculty/.env
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

function callSupabase($url, $key){
    if (empty($url) || empty($key)) {
        return [[], 0];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["apikey: $key", "Authorization: Bearer $key"]
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($res, true), $http];
}

$student_id = $_GET['student_id'] ?? '';
if(!$student_id){
    header("Location: umis.php");
    exit;
}

// Fetch Student Data
list($existing, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/umis_students?student_id=eq." . urlencode($student_id), $SUPABASE_KEY);
$data = (is_array($existing) && !empty($existing)) ? $existing[0] : [];

function v($key, $default='-'){
    global $data;
    // Redact Aadhaar data if matched from sensitive categories, or return standard value
    if(in_array($key, ['aadhaar_no'])) {
        return '[Aadhaar Redacted]';
    }
    $val = $data[$key] ?? '';
    return ($val !== null && trim((string)$val) !== '') ? htmlspecialchars((string)$val) : $default;
}

$sch_list = $data['school_info'] ?? [];
if(is_string($sch_list)){
    $sch_list = json_decode($sch_list, true);
}
if(!is_array($sch_list)) $sch_list = [];

$student_name = $data['student_name_cert'] ?? $data['student_name_aadhaar'] ?? 'Student';
$parent_phone = trim($data['parent_phone'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UMIS Student Details View - Faculty Portal</title>
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
    min-height: 100vh;
    display: flex;
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

.box {
    background: #FFFFFF;
    padding: 32px;
    border-radius: 18px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.03);
}

.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    border-bottom: 1px solid #E2E8F0;
    padding-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.header-actions h2 {
    color: #0F172A;
    font-size: 20px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 12.5px;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-back { background: #64748B; color: #FFFFFF; }
.btn-back:hover { background: #475569; }

.btn-print { background: #10B981; color: #FFFFFF; }
.btn-print:hover { background: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25); }

.btn-whatsapp { background: #25D366; color: white; }
.btn-whatsapp:hover { background: #20BA5A; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); }

.btn-msg { background: #8B5CF6; color: white; }
.btn-msg:hover { background: #7C3AED; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.25); }

.status-bar {
    background: #F8FAFC;
    padding: 14px 20px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    margin-bottom: 25px;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.section-title {
    color: #2563EB;
    font-size: 14px;
    font-weight: 700;
    margin: 25px 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 10px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.grid-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #E2E8F0;
}

.grid-table td {
    padding: 12px 18px;
    border: 1px solid #E2E8F0;
    font-size: 13.5px;
}

.label {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    width: 32%;
    font-size: 12.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.value {
    background: #FFFFFF;
    color: #0F172A;
    font-weight: 600;
    width: 68%;
}

table.school-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #E2E8F0;
}

table.school-tbl th, table.school-tbl td {
    border: 1px solid #E2E8F0;
    padding: 12px 16px;
    text-align: center;
    font-size: 13px;
}

table.school-tbl th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
}

table.school-tbl td {
    background: #FFFFFF;
    color: #1E293B;
}

tr:nth-child(even) td {
    background: #FAFBFC;
}

@media(max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .label { width: 40%; }
    .value { width: 60%; }
}

@media print {
    .sidebar, .topbar, .action-buttons { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .box { box-shadow: none; border: none; padding: 0; }
    .label { background: #F8FAFC !important; -webkit-print-color-adjust: exact; }
    .value { background: #FFFFFF !important; -webkit-print-color-adjust: exact; }
    .section-title { color: #1E3A8A !important; border-bottom: 2px solid #000 !important; }
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
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
            <li><a href="umis_data.php" class="active"><i class="fa-solid fa-clipboard-user"></i><span>UMIS Data</span></a></li>
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
        <h2><i class="fa-solid fa-clipboard-user"></i> Student UMIS Profile View</h2>
        <div class="topbar-right">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="box">
            <div class="header-actions">
                <h2><i class="fa-solid fa-user-shield" style="color: #2563EB;"></i> Profile: <?=htmlspecialchars($student_name)?></h2>
                <div class="action-buttons">
                    <a href="umis_data.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Directory</a>
                    
                    <?php if(!empty($parent_phone)): ?>
                        <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $parent_phone); ?>?text=Hello%20Respected%20Parent,%20Regarding%20UMIS%20record%20for%20student%20<?php echo urlencode($student_name); ?>" target="_blank" class="btn btn-whatsapp" title="WhatsApp Parent">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="sms:<?php echo htmlspecialchars($parent_phone); ?>?body=Hello%20Parent,%20Regarding%20student%20<?php echo urlencode($student_name); ?>" class="btn btn-msg" title="SMS Parent">
                            <i class="fa-solid fa-comment-sms"></i> SMS
                        </a>
                    <?php endif; ?>

                    <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print / PDF</button>
                </div>
            </div>

            <div class="status-bar">
                <span><b>Student ID:</b> <span style="color:#2563EB; font-weight:700;"><?=htmlspecialchars($student_id)?></span></span>
                <span><b>Form Status:</b> <?=(!empty($data['is_completed'])) ? '<span style="color:#15803D; font-weight:700; background:#DCFCE7; padding:4px 10px; border-radius:20px; font-size:12px;"><i class="fa-solid fa-circle-check"></i> Completed</span>' : '<span style="color:#B45309; font-weight:700; background:#FEF3C7; padding:4px 10px; border-radius:20px; font-size:12px;"><i class="fa-solid fa-clock"></i> In Progress</span>'?></span>
            </div>

            <h3 class="section-title"><i class="fa-solid fa-building-columns"></i> 1. College & General Information</h3>
            <table class="grid-table">
                <tr><td class="label">College Name</td><td class="value"><?=v('college_name')?></td></tr>
                <tr><td class="label">College Code</td><td class="value"><?=v('college_code')?></td></tr>
                <tr><td class="label">College District / Region</td><td class="value"><?=v('college_district')?> / <?=v('college_region')?></td></tr>
                <tr><td class="label">Student Name (Certificate)</td><td class="value"><?=v('student_name_cert')?></td></tr>
                <tr><td class="label">Student Name (Aadhaar)</td><td class="value"><?=v('student_name_aadhaar')?></td></tr>
                <tr><td class="label">EMIS ID / Reason</td><td class="value"><?=v('emis_id')?> <?=v('no_emis_reason') ? '('.v('no_emis_reason').')' : ''?></td></tr>
                <tr><td class="label">DOB / Gender / Blood Group</td><td class="value"><?=v('dob')?> / <?=v('gender')?> / <?=v('blood_group')?></td></tr>
                <tr><td class="label">Religion / Community / Caste</td><td class="value"><?=v('religion')?> / <?=v('community')?> / <?=v('caste')?></td></tr>
                <tr><td class="label">Community Cert No</td><td class="value"><?=v('community_cert_no')?></td></tr>
                <tr><td class="label">Aadhaar No</td><td class="value"><?=v('aadhaar_no')?></td></tr>
                <tr><td class="label">First Graduate / Cert No</td><td class="value"><?=v('is_first_graduate')?> <?=v('first_graduate_cert_no') ? '('.v('first_graduate_cert_no').')' : ''?></td></tr>
                <tr><td class="label">Special Quota / Category</td><td class="value"><?=v('special_quota')?> <?=v('special_quota_category') ? '('.v('special_quota_category').')' : ''?></td></tr>
                <tr><td class="label">Differently Abled / Type / %</td><td class="value"><?=v('is_differently_abled')?> <?=v('disability_type') ? '('.v('disability_type').' - '.v('disability_percentage').'%)' : ''?></td></tr>
            </table>

            <h3 class="section-title"><i class="fa-solid fa-address-book"></i> 2. Contact & Address Details</h3>
            <table class="grid-table">
                <tr><td class="label">Mobile / Email</td><td class="value"><?=v('mobile')?> / <?=v('email')?></td></tr>
                <tr><td class="label">Permanent Address</td><td class="value"><?=v('postal_address')?>, <?=v('village')?>, <?=v('taluk')?>, <?=v('district')?>, <?=v('state')?> - <?=v('pincode')?></td></tr>
                <tr><td class="label">Communication Address</td><td class="value"><?=v('comm_postal_address')?>, <?=v('comm_village')?>, <?=v('comm_taluk')?>, <?=v('comm_district')?>, <?=v('comm_state')?> - <?=v('comm_pincode')?></td></tr>
            </table>

            <h3 class="section-title"><i class="fa-solid fa-users-rectangle"></i> 3. Family & Bank Information</h3>
            <table class="grid-table">
                <tr><td class="label">Father Name / Occupation</td><td class="value"><?=v('father_name')?> (<?=v('father_occupation')?>)</td></tr>
                <tr><td class="label">Mother Name / Occupation</td><td class="value"><?=v('mother_name')?> (<?=v('mother_occupation')?>)</td></tr>
                <tr><td class="label">Guardian Name / Mobile</td><td class="value"><?=v('guardian_name')?> / <?=v('guardian_mobile')?></td></tr>
                <tr><td class="label">Annual Income / Cert No</td><td class="value">Rs. <?=v('family_income')?> / <?=v('income_cert_no')?></td></tr>
                <tr><td class="label">Bank Account No / IFSC</td><td class="value"><?=v('acc_no')?> / <?=v('ifsc_code')?></td></tr>
                <tr><td class="label">Bank Name & Branch</td><td class="value"><?=v('bank_name')?>, <?=v('bank_branch')?> (<?=v('bank_city')?>)</td></tr>
                <tr><td class="label">Aadhaar Seeded Bank Info</td><td class="value">Acc: <?=v('seed_account_no')?> | Status: <?=v('seed_status')?> | Bank: <?=v('seed_bank_name')?></td></tr>
            </table>

            <h3 class="section-title"><i class="fa-solid fa-graduation-cap"></i> 4. Academic & Schooling Information</h3>
            <table class="grid-table">
                <tr><td class="label">Course / Branch</td><td class="value"><?=v('course')?> - <?=v('specialization')?></td></tr>
                <tr><td class="label">Admission Date / Roll No</td><td class="value"><?=v('date_of_admission')?> / <?=v('roll_no')?></td></tr>
                <tr><td class="label">Medium / Mode / Year</td><td class="value"><?=v('medium_of_instruction')?> / <?=v('mode_of_study')?> / <?=v('year_of_study')?></td></tr>
            </table>

            <h3 class="section-title"><i class="fa-solid fa-school"></i> Schooling Details (6th to 12th)</h3>
            <table class="school-tbl">
                <tr>
                    <th>Class</th>
                    <th>District</th>
                    <th>School Name</th>
                    <th>School Type</th>
                </tr>
                <?php if(!empty($sch_list)): 
                    foreach($sch_list as $s): 
                ?>
                <tr>
                    <td><b><?=htmlspecialchars($s['class']??'')?></b></td>
                    <td><?=htmlspecialchars($s['district']??'-')?></td>
                    <td><?=htmlspecialchars($s['school_name']??'-')?></td>
                    <td><?=htmlspecialchars($s['school_type']??'-')?></td>
                </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                <tr><td colspan="4" style="text-align:center; padding:30px; color:#64748B;">No School Information Saved.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

</body>
</html>