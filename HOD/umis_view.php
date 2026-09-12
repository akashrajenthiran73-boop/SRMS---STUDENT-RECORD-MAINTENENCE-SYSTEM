<?php
session_start();
ini_set('display_errors', 1); 
error_reporting(E_ALL);

// HOD Role Check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'HOD'){
    header("Location: ../auth/login.php"); 
    exit;
}

// 1. Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  // SRMS/.env (Root Folder)
    __DIR__ . '/.env',                     // SRMS/HOD/.env
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
    header("Location: umis_form_list.php");
    exit;
}

// Fetch Student Data
list($existing, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/umis_students?student_id=eq." . urlencode($student_id), $SUPABASE_KEY);
$data = (is_array($existing) && !empty($existing)) ? $existing[0] : [];

function v($key, $default='-'){
    global $data;
    if($key === 'aadhaar_no') {
        return '[Aadhaar Redacted]';
    }
    $val = $data[$key] ?? '';
    return ($val !== null && trim((string)$val) !== '') ? htmlspecialchars((string)$val) : $default;
}

$sch_list = $data['school_info'] ?? [];
?>
<?php
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student UMIS Profile - <?=v('student_name_cert')?> - HOD Portal</title>
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

.hero-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hero-icon {
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

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn { 
    padding: 9px 16px; 
    border-radius: 10px; 
    text-decoration: none; 
    font-weight: 600; 
    cursor: pointer; 
    border: none; 
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    transition: all 0.2s;
}

.btn-back { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
.btn-back:hover { background: #E2E8F0; color: #0F172A; }

.btn-approve { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25); }
.btn-approve:hover { opacity: 0.92; transform: translateY(-1px); }

.btn-print { background: #F8FAFC; color: #475569; border: 1px solid #E2E8F0; }
.btn-print:hover { background: #F1F5F9; color: #0F172A; }

.section-card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    padding: 24px;
    margin-bottom: 24px;
}

.profile-meta-banner {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 13.5px;
    color: #475569;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.section-title { 
    color: #0F172A; 
    border-bottom: 1px solid #F1F5F9; 
    padding-bottom: 12px; 
    margin-bottom: 18px;
    font-size: 14.5px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #7C3AED;
}

.grid-details { 
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px 20px;
}

.data-item {
    padding: 10px 14px;
    background: #F8FAFC;
    border: 1px solid #F1F5F9;
    border-radius: 10px;
}

.data-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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

table.school-tbl { 
    width: 100%; 
    border-collapse: collapse; 
}

table.school-tbl th, table.school-tbl td { 
    padding: 12px 18px; 
    text-align: left; 
    font-size: 13px;
}

table.school-tbl th { 
    background: #F8FAFC; 
    color: #475569; 
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #E2E8F0;
}

table.school-tbl td {
    border-bottom: 1px solid #F1F5F9;
    color: #334155;
}

table.school-tbl tr:last-child td {
    border-bottom: none;
}

@media print {
    .sidebar, .topbar, .header-actions, .page-header { display: none !important; }
    body { background: white !important; color: black !important; display: block !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .section-card { border: 1px solid #CBD5E1 !important; box-shadow: none !important; page-break-inside: avoid; }
}

@media (max-width: 1024px) {
    .sidebar { width: 70px; }
    .sidebar-brand span, .sidebar-brand h2, .sidebar-menu span, .nav-category, .sidebar-footer span { display: none; }
    .sidebar-brand { justify-content: center; padding: 15px 0; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar, .content-body { padding-left: 20px; padding-right: 20px; }
    .grid-details { grid-template-columns: 1fr; }
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
            <li><a href="umis_form_list.php" class="active"><i class="fa-solid fa-database"></i> <span>UMIS Data</span></a></li>
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
            <h1>Student UMIS Profile</h1>
            <p>University Management Information System comprehensive verification</p>
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
            <div class="hero-info">
                <div class="hero-icon">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <h1><?=v('student_name_cert')?></h1>
                    <p>Student ID: <b><?=htmlspecialchars($student_id)?></b> &bull; Course: <b><?=v('course')?></b></p>
                </div>
            </div>
            <div class="header-actions">
                <a href="umis_form_list.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                <?php 
                $raw_status = $data['status'] ?? 'Pending';
                $clean_status = trim($raw_status, " '\"\t\n\r\0\x0B");
                if(strtolower($clean_status) == 'pending' || strtolower($clean_status) == 'in progress'):
                ?>
                    <a href="umis_form_list.php?approve_id=<?=urlencode($student_id)?>" class="btn btn-approve" onclick="return confirm('Approve UMIS for <?=htmlspecialchars($data['student_name_cert'] ?? $student_id)?>?')"><i class="fa-solid fa-check"></i> Approve UMIS</a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print / PDF</button>
            </div>
        </div>

        <div class="section-card">
            <div class="profile-meta-banner">
                <div><b>Student ID:</b> <span style="color:#7C3AED; font-weight:700; font-family:monospace;"><?=htmlspecialchars($student_id)?></span></div>
                <div><b>Form Status:</b> <?=(!empty($data['is_completed'])) ? '<span style="color:#059669; font-weight:700;"><i class="fa-solid fa-circle-check"></i> Completed</span>' : '<span style="color:#D97706; font-weight:700;"><i class="fa-solid fa-clock"></i> In Progress</span>'?></div>
            </div>

            <h3 class="section-title"><i class="fa-solid fa-building-columns"></i> 1. College & General Information</h3>
            <div class="grid-details">
                <div class="data-item"><div class="data-label">College Name</div><div class="data-value"><?=v('college_name')?></div></div>
                <div class="data-item"><div class="data-label">College Code</div><div class="data-value"><?=v('college_code')?></div></div>
                <div class="data-item"><div class="data-label">College District / Region</div><div class="data-value"><?=v('college_district')?> / <?=v('college_region')?></div></div>
                <div class="data-item"><div class="data-label">Student Name (Certificate)</div><div class="data-value"><b><?=v('student_name_cert')?></b></div></div>
                <div class="data-item"><div class="data-label">Student Name (Aadhaar)</div><div class="data-value"><?=v('student_name_aadhaar')?></div></div>
                <div class="data-item"><div class="data-label">EMIS ID / Reason</div><div class="data-value"><?=v('emis_id')?> <?=v('no_emis_reason') ? '('.v('no_emis_reason').')' : ''?></div></div>
                <div class="data-item"><div class="data-label">DOB / Gender / Blood Group</div><div class="data-value"><?=v('dob')?> / <?=v('gender')?> / <?=v('blood_group')?></div></div>
                <div class="data-item"><div class="data-label">Religion / Community / Caste</div><div class="data-value"><?=v('religion')?> / <?=v('community')?> / <?=v('caste')?></div></div>
                <div class="data-item"><div class="data-label">Community Cert No</div><div class="data-value"><?=v('community_cert_no')?></div></div>
                <div class="data-item"><div class="data-label">Aadhaar No</div><div class="data-value"><?=v('aadhaar_no')?></div></div>
                <div class="data-item"><div class="data-label">First Graduate / Cert No</div><div class="data-value"><?=v('is_first_graduate')?> <?=v('first_graduate_cert_no') ? '('.v('first_graduate_cert_no').')' : ''?></div></div>
                <div class="data-item"><div class="data-label">Special Quota / Category</div><div class="data-value"><?=v('special_quota')?> <?=v('special_quota_category') ? '('.v('special_quota_category').')' : ''?></div></div>
                <div class="data-item"><div class="data-label">Differently Abled / Type / %</div><div class="data-value"><?=v('is_differently_abled')?> <?=v('disability_type') ? '('.v('disability_type').' - '.v('disability_percentage').'%)' : ''?></div></div>
            </div>
        </div>

        <div class="section-card">
            <h3 class="section-title"><i class="fa-solid fa-address-book"></i> 2. Contact & Address Details</h3>
            <div class="grid-details">
                <div class="data-item"><div class="data-label">Mobile Number</div><div class="data-value"><?=v('mobile')?></div></div>
                <div class="data-item"><div class="data-label">Email Address</div><div class="data-value"><?=v('email')?></div></div>
                <div class="data-item" style="grid-column: span 2;"><div class="data-label">Permanent Address</div><div class="data-value"><?=v('postal_address')?>, <?=v('village')?>, <?=v('taluk')?>, <?=v('district')?>, <?=v('state')?> - <?=v('pincode')?></div></div>
                <div class="data-item" style="grid-column: span 2;"><div class="data-label">Communication Address</div><div class="data-value"><?=v('comm_postal_address')?>, <?=v('comm_village')?>, <?=v('comm_taluk')?>, <?=v('comm_district')?>, <?=v('comm_state')?> - <?=v('comm_pincode')?></div></div>
            </div>
        </div>

        <div class="section-card">
            <h3 class="section-title"><i class="fa-solid fa-users-rectangle"></i> 3. Family & Bank Information</h3>
            <div class="grid-details">
                <div class="data-item"><div class="data-label">Father Name / Occupation</div><div class="data-value"><?=v('father_name')?> (<?=v('father_occupation')?>)</div></div>
                <div class="data-item"><div class="data-label">Mother Name / Occupation</div><div class="data-value"><?=v('mother_name')?> (<?=v('mother_occupation')?>)</div></div>
                <div class="data-item"><div class="data-label">Guardian Name / Mobile</div><div class="data-value"><?=v('guardian_name')?> / <?=v('guardian_mobile')?></div></div>
                <div class="data-item"><div class="data-label">Annual Income / Cert No</div><div class="data-value">&#8377; <?=v('family_income')?> / <?=v('income_cert_no')?></div></div>
                <div class="data-item"><div class="data-label">Bank Account No / IFSC</div><div class="data-value"><?=v('acc_no')?> / <?=v('ifsc_code')?></div></div>
                <div class="data-item"><div class="data-label">Bank Name & Branch</div><div class="data-value"><?=v('bank_name')?>, <?=v('bank_branch')?> (<?=v('bank_city')?>)</div></div>
                <div class="data-item" style="grid-column: span 2;"><div class="data-label">Aadhaar Seeded Bank Info</div><div class="data-value">Acc: <?=v('seed_account_no')?> | Status: <?=v('seed_status')?> | Bank: <?=v('seed_bank_name')?></div></div>
            </div>
        </div>

        <div class="section-card">
            <h3 class="section-title"><i class="fa-solid fa-book-open"></i> 4. Academic & Schooling Information</h3>
            <div class="grid-details" style="margin-bottom: 24px;">
                <div class="data-item"><div class="data-label">Course / Branch</div><div class="data-value"><?=v('course')?> - <?=v('specialization')?></div></div>
                <div class="data-item"><div class="data-label">Admission Date / Roll No</div><div class="data-value"><?=v('date_of_admission')?> / <?=v('roll_no')?></div></div>
                <div class="data-item" style="grid-column: span 2;"><div class="data-label">Medium / Mode / Year</div><div class="data-value"><?=v('medium_of_instruction')?> / <?=v('mode_of_study')?> / <?=v('year_of_study')?></div></div>
            </div>

            <h3 class="section-title"><i class="fa-solid fa-school"></i> Schooling Details (6th to 12th)</h3>
            <div class="table-responsive">
                <table class="school-tbl">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>District</th>
                            <th>School Name</th>
                            <th>School Type</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    if(is_string($sch_list)){
                        $sch_list = json_decode($sch_list, true);
                    }
                    if(!is_array($sch_list)) $sch_list = [];

                    if(!empty($sch_list)): 
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
                    <tr><td colspan="4" style="text-align:center; padding:35px; color:#94A3B8;">No School Information Saved.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>