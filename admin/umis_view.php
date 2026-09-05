<?php
session_start();
ini_set('display_errors', 1); 
error_reporting(E_ALL);

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

.view-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    border-bottom: 1.5px solid #F1F5F9;
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
    gap: 12px;
}

.header-icon-wrap {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #EFF6FF;
    color: #2563EB;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.action-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13.5px;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
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

.btn-edit {
    background: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.btn-edit:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.btn-print {
    background: #EFF6FF;
    color: #2563EB;
    border: 1.5px solid #BFDBFE;
}
.btn-print:hover {
    background: #2563EB;
    color: white;
}

.status-bar {
    background: #F8FAFC;
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    margin-bottom: 26px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

/* Status Badges */
.status-green { 
    background: #ECFDF5; 
    color: #047857; 
    border: 1px solid #A7F3D0;
    padding: 5px 14px; 
    border-radius: 20px; 
    font-size: 12.5px; 
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.status-yellow { 
    background: #FFFBEB; 
    color: #B45309; 
    border: 1px solid #FDE68A;
    padding: 5px 14px; 
    border-radius: 20px; 
    font-size: 12.5px; 
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Sections */
.section {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    background: #FFFFFF;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1.5px solid #F1F5F9;
}

.section-header i {
    color: #2563EB;
    font-size: 16px;
}

.section-header h3 {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Details Grid */
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    background: #F8FAFC;
    padding: 12px 16px;
    border-radius: 10px;
    border: 1px solid #F1F5F9;
}

.detail-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 14px;
    font-weight: 700;
    color: #1E293B;
    word-break: break-word;
}

/* Table */
.table-responsive {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    margin-top: 10px;
}

table.school-tbl {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

table.school-tbl th, table.school-tbl td {
    padding: 12px 16px;
    border-bottom: 1px solid #F1F5F9;
    font-size: 13.5px;
    text-align: left;
}

table.school-tbl th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
    border-bottom: 1.5px solid #E2E8F0;
}

table.school-tbl td {
    color: #1E293B;
}

tbody tr:hover {
    background: #F8FAFC;
}

@media print {
    body { background: #FFFFFF; color: #000000; padding: 0; }
    .sidebar, .navbar, .action-buttons, .btn-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .view-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
    .detail-item { border: 1px solid #E2E8F0 !important; background: #FFFFFF !important; }
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
        <span>Arignar Anna College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php" class="active"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="admin_profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </div>
</div>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-content">
    
    <div class="navbar">
        <h1>UMIS Profile Details</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <div class="content-body">
        
        <div class="view-card">
            
            <div class="header-actions">
                <h2>
                    <span class="header-icon-wrap"><i class="fa-solid fa-user-shield"></i></span>
                    Student UMIS Profile View
                </h2>
                <div class="action-buttons">
                    <a href="umis.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Directory</a>
                    <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print / PDF</button>
                    <a href="umis_page1.php?student_id=<?=urlencode($student_id)?>" class="btn btn-edit"><i class="fa-solid fa-pen-to-square"></i> Edit Application</a>
                </div>
            </div>

            <div class="status-bar">
                <span style="font-size: 14px;"><b>Student ID:</b> <span style="color:#2563EB; font-weight:800;"><?=htmlspecialchars($student_id)?></span></span>
                <span>
                    <b>Form Status:</b> 
                    <?=(!empty($data['is_completed'])) ? '<span class="status-green"><i class="fa-solid fa-circle-check"></i> Completed</span>' : '<span class="status-yellow"><i class="fa-solid fa-clock"></i> In Progress</span>'?>
                </span>
            </div>

            <!-- SECTION 1 -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-building-columns"></i>
                    <h3>1. College & General Information</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item"><span class="detail-label">College Name</span><span class="detail-value"><?=v('college_name')?></span></div>
                    <div class="detail-item"><span class="detail-label">College Code</span><span class="detail-value"><?=v('college_code')?></span></div>
                    <div class="detail-item"><span class="detail-label">District / Region</span><span class="detail-value"><?=v('college_district')?> / <?=v('college_region')?></span></div>
                    <div class="detail-item"><span class="detail-label">Student Name (Certificate)</span><span class="detail-value"><?=v('student_name_cert')?></span></div>
                    <div class="detail-item"><span class="detail-label">Student Name (Aadhaar)</span><span class="detail-value"><?=v('student_name_aadhaar')?></span></div>
                    <div class="detail-item"><span class="detail-label">EMIS ID / Reason</span><span class="detail-value"><?=v('emis_id')?> <?=v('no_emis_reason') ? '('.v('no_emis_reason').')' : ''?></span></div>
                    <div class="detail-item"><span class="detail-label">DOB / Gender / Blood Group</span><span class="detail-value"><?=v('dob')?> / <?=v('gender')?> / <?=v('blood_group')?></span></div>
                    <div class="detail-item"><span class="detail-label">Religion / Community / Caste</span><span class="detail-value"><?=v('religion')?> / <?=v('community')?> / <?=v('caste')?></span></div>
                    <div class="detail-item"><span class="detail-label">Community Cert No</span><span class="detail-value"><?=v('community_cert_no')?></span></div>
                    <div class="detail-item"><span class="detail-label">Aadhaar No</span><span class="detail-value"><?=v('aadhaar_no')?></span></div>
                    <div class="detail-item"><span class="detail-label">First Graduate / Cert No</span><span class="detail-value"><?=v('is_first_graduate')?> <?=v('first_graduate_cert_no') ? '('.v('first_graduate_cert_no').')' : ''?></span></div>
                    <div class="detail-item"><span class="detail-label">Special Quota / Category</span><span class="detail-value"><?=v('special_quota')?> <?=v('special_quota_category') ? '('.v('special_quota_category').')' : ''?></span></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><span class="detail-label">Differently Abled / Type / %</span><span class="detail-value"><?=v('is_differently_abled')?> <?=v('disability_type') ? '('.v('disability_type').' - '.v('disability_percentage').'%)' : ''?></span></div>
                </div>
            </div>

            <!-- SECTION 2 -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-address-book"></i>
                    <h3>2. Contact & Address Details</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item"><span class="detail-label">Mobile / Email</span><span class="detail-value"><?=v('mobile')?> / <?=v('email')?></span></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><span class="detail-label">Permanent Address</span><span class="detail-value"><?=v('postal_address')?>, <?=v('village')?>, <?=v('taluk')?>, <?=v('district')?>, <?=v('state')?> - <?=v('pincode')?></span></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><span class="detail-label">Communication Address</span><span class="detail-value"><?=v('comm_postal_address')?>, <?=v('comm_village')?>, <?=v('comm_taluk')?>, <?=v('comm_district')?>, <?=v('comm_state')?> - <?=v('comm_pincode')?></span></div>
                </div>
            </div>

            <!-- SECTION 3 -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-users-rectangle"></i>
                    <h3>3. Family & Bank Information</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item"><span class="detail-label">Father Name / Occupation</span><span class="detail-value"><?=v('father_name')?> (<?=v('father_occupation')?>)</span></div>
                    <div class="detail-item"><span class="detail-label">Mother Name / Occupation</span><span class="detail-value"><?=v('mother_name')?> (<?=v('mother_occupation')?>)</span></div>
                    <div class="detail-item"><span class="detail-label">Guardian Name / Mobile</span><span class="detail-value"><?=v('guardian_name')?> / <?=v('guardian_mobile')?></span></div>
                    <div class="detail-item"><span class="detail-label">Annual Income / Cert No</span><span class="detail-value">₹ <?=v('family_income')?> / <?=v('income_cert_no')?></span></div>
                    <div class="detail-item"><span class="detail-label">Bank Account No / IFSC</span><span class="detail-value"><?=v('acc_no')?> / <?=v('ifsc_code')?></span></div>
                    <div class="detail-item"><span class="detail-label">Bank Name & Branch</span><span class="detail-value"><?=v('bank_name')?>, <?=v('bank_branch')?> (<?=v('bank_city')?>)</span></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><span class="detail-label">Aadhaar Seeded Bank Info</span><span class="detail-value">Acc: <?=v('seed_account_no')?> | Status: <?=v('seed_status')?> | Bank: <?=v('seed_bank_name')?></span></div>
                </div>
            </div>

            <!-- SECTION 4 -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <h3>4. Academic & Schooling Information</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item"><span class="detail-label">Course / Branch</span><span class="detail-value"><?=v('course')?> - <?=v('specialization')?></span></div>
                    <div class="detail-item"><span class="detail-label">Admission Date / Roll No</span><span class="detail-value"><?=v('date_of_admission')?> / <?=v('roll_no')?></span></div>
                    <div class="detail-item"><span class="detail-label">Medium / Mode / Year</span><span class="detail-value"><?=v('medium_of_instruction')?> / <?=v('mode_of_study')?> / <?=v('year_of_study')?></span></div>
                </div>

                <div style="margin-top: 24px;">
                    <div style="font-size: 13.5px; font-weight: 700; color: #475569; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-school" style="color: #2563EB;"></i> Schooling History (6th to 12th Std)
                    </div>
                    <div class="table-responsive">
                        <table class="school-tbl">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Class</th>
                                    <th>District</th>
                                    <th>School Name</th>
                                    <th style="width: 150px;">School Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($sch_list)): 
                                    foreach($sch_list as $s): 
                                ?>
                                <tr>
                                    <td><b style="color: #2563EB;"><?=htmlspecialchars($s['class']??'')?></b></td>
                                    <td><?=htmlspecialchars($s['district']??'-')?></td>
                                    <td style="font-weight: 600;"><?=htmlspecialchars($s['school_name']??'-')?></td>
                                    <td><?=htmlspecialchars($s['school_type']??'-')?></td>
                                </tr>
                                <?php 
                                    endforeach; 
                                else: 
                                ?>
                                <tr><td colspan="4" style="text-align:center; padding:24px; color:#64748B;">No School Information Saved.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>