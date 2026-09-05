<?php
session_start();
ini_set('display_errors', 1); 
error_reporting(E_ALL);

// Ensure only Super Admin can access this view page
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Student'){ 
    header("Location: login.php"); 
    exit;
}

$display_role = $_SESSION['role'];

// 1. Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  // SRMS/.env (Root Folder)
    __DIR__ . '/.env',                     // SRMS/admin/.env
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
    $val = $data[$key] ?? '';
    return ($val !== null && trim((string)$val) !== '') ? htmlspecialchars((string)$val) : $default;
}

$sch_list = $data['school_info'] ?? [];
if(is_string($sch_list)){
    $sch_list = json_decode($sch_list, true);
}
if(!is_array($sch_list)) $sch_list = [];
$user_name = $data['student_name_cert'] ?? $data['student_name_aadhaar'] ?? ($_SESSION['name'] ?? $_SESSION['username'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UMIS Student Details View - SRMS</title>
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

.box {
    background: #FFFFFF;
    padding: 32px;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    border-bottom: 1px solid #E2E8F0;
    padding-bottom: 20px;
    flex-wrap: wrap;
    gap: 16px;
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
    gap: 10px;
}

.btn {
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-back { background: #F1F5F9; color: #334155; border-color: #CBD5E1; }
.btn-back:hover { background: #E2E8F0; color: #0F172A; }

.btn-print {
    background: #059669;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}
.btn-print:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.status-bar {
    background: #F8FAFC;
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    margin-bottom: 25px;
    font-size: 13.5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
}
.status-green { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
.status-yellow { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }

.section-title {
    color: #059669;
    font-size: 13px;
    font-weight: 800;
    margin: 28px 0 16px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.grid-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #E2E8F0;
}

.grid-table td {
    padding: 12px 16px;
    border: 1px solid #E2E8F0;
    font-size: 13.5px;
}

.label {
    background: #F8FAFC;
    color: #64748B;
    font-weight: 700;
    width: 32%;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
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
    font-size: 13.5px;
}

table.school-tbl th {
    background: #F8FAFC;
    color: #334155;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

table.school-tbl td {
    background: #FFFFFF;
    color: #1E293B;
}

tr:nth-child(even) td {
    background: #FAFBFC;
}

@media print {
    .sidebar, .navbar, .header-actions { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .content-body { padding: 0 !important; }
    body { background: #FFFFFF; color: #000000; }
    .box { background: #FFFFFF; color: #000000; box-shadow: none; max-width: 100%; padding: 0; border: none; }
    .label { background: #F1F5F9 !important; color: #000000 !important; -webkit-print-color-adjust: exact; }
    .value { background: #FFFFFF !important; color: #000000 !important; -webkit-print-color-adjust: exact; }
    .section-title { color: #000 !important; border-bottom: 2px solid #000 !important; }
    table, td, th { border: 1px solid #CBD5E1 !important; }
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
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php" class="active"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
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
            <h1>UMIS Form Dossier</h1>
            <p>Institutional record synchronized with higher education council</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="box">
            <div class="header-actions">
                <h2><i class="fa-solid fa-database" style="color: #059669;"></i> Student UMIS Profile View</h2>
                <div class="action-buttons">
                    <a href="umis.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to UMIS</a>
                    <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print Official Dossier</button>
                </div>
            </div>

            <div class="status-bar">
                <span><b>Student ID:</b> <span style="color:#059669; font-weight:700;"><?=htmlspecialchars($student_id)?></span></span>
                <span><b>Form Status:</b> <?=(!empty($data['is_completed'])) ? '<span class="status-pill status-green"><i class="fa-solid fa-circle-check"></i> Completed</span>' : '<span class="status-pill status-yellow"><i class="fa-solid fa-clock"></i> In Progress</span>'?></span>
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
                <tr><td class="label">Aadhaar No</td><td class="value">[Aadhaar Redacted for Privacy]</td></tr>
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
                <tr><td class="label">Aadhaar Seeded Bank Info</td><td class="value">Status: <?=v('seed_status')?> | Bank: <?=v('seed_bank_name')?></td></tr>
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
                <tr><td colspan="4" style="text-align:center; padding:20px; color:#64748B;">No School Information Saved.</td></tr>
                <?php endif; ?>
            </table>
        </div>

    </div>

</div>

</body>
</html>