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

function callSupabase($url, $key, $method='GET', $data=null){
    if (empty($url) || empty($key)) {
        return [[], 0];
    }
    $ch = curl_init($url);
    $headers = ["apikey: $key", "Authorization: Bearer $key"];
    if($method == 'POST' || $method == 'PATCH'){ 
        $headers[] = "Content-Type: application/json"; 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); 
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch); 
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch);
    return [json_decode($res, true), $http];
}

$student_id = $_GET['student_id'] ?? $_SESSION['student_id'] ?? '';
if(!$student_id) { 
    header("Location: umis_page1.php"); 
    exit; 
}
$_SESSION['student_id'] = $student_id;
$msg = "";

if (empty($SUPABASE_URL) || empty($SUPABASE_KEY)) {
    $msg = "Configuration Error: .env file missing or Supabase keys are not set!";
} elseif($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = $_POST;
    unset($payload['save_page4']);

    // Build School Info JSON Structure
    $school_data = [];
    $classes = ['12th','11th','10th','9th','8th','7th','6th'];
    foreach($classes as $c){
        $school_data[] = [
            "class" => $c,
            "district" => $_POST["sch_dist_$c"] ?? '',
            "school_name" => $_POST["sch_name_$c"] ?? '',
            "school_type" => $_POST["sch_type_$c"] ?? ''
        ];
        unset($payload["sch_dist_$c"], $payload["sch_name_$c"], $payload["sch_type_$c"]);
    }
    $payload['school_info'] = json_encode($school_data); 
    $payload['is_completed'] = true;

    // Convert empty fields to NULL
    foreach($payload as $k => $v){ if($v === '') $payload[$k] = null; }

    $base_url = rtrim($SUPABASE_URL, '/');
    list($res, $http) = callSupabase("$base_url/rest/v1/umis_students?student_id=eq." . urlencode($student_id), $SUPABASE_KEY, 'PATCH', $payload);

    if($http >= 200 && $http < 300) {
        header("Location: umis.php?msg=success"); 
        exit; 
    } else { 
        $msg = "Save Error (HTTP $http)! Check fields properly."; 
    }
}

$data = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    list($existing) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/umis_students?student_id=eq." . urlencode($student_id), $SUPABASE_KEY);
    $data = (is_array($existing) && !empty($existing)) ? $existing[0] : [];
}

function v($key, $d=''){ global $data; return htmlspecialchars((string)($data[$key] ?? $d)); }

// Safe School JSON Decoding
$sch_list = $data['school_info'] ?? [];
if (is_string($sch_list)) {
    $sch_list = json_decode($sch_list, true) ?? [];
}

$sch_map = [];
if (is_array($sch_list)) {
    foreach($sch_list as $s){ 
        if (is_array($s) && isset($s['class'])) {
            $sch_map[$s['class']] = $s; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UMIS Form - Page 4 - SRMS</title>
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

.box {
    max-width: 1000px;
    margin: 0 auto;
    background: #FFFFFF;
    padding: 36px;
    border-radius: 18px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    border-bottom: 1.5px solid #F1F5F9;
    padding-bottom: 18px;
    flex-wrap: wrap;
    gap: 15px;
}

.form-header h2 {
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

.student-badge {
    background: #EFF6FF;
    color: #1E40AF;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #BFDBFE;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Stepper Indicator */
.stepper-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
    background: #F8FAFC;
    padding: 16px 24px;
    border-radius: 14px;
    border: 1px solid #E2E8F0;
    overflow-x: auto;
    gap: 8px;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 12.5px;
    font-weight: 700;
    white-space: nowrap;
}
.step-item.active {
    background: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.step-item.done {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
}
.step-item.inactive {
    background: #FFFFFF;
    color: #94A3B8;
    border: 1px solid #E2E8F0;
}
.step-divider {
    flex: 1;
    min-width: 20px;
    height: 2px;
    background: #E2E8F0;
}

.error-msg {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
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

.row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 16px;
}

.row:last-child {
    margin-bottom: 0;
}

.row .g, .form-group {
    display: flex;
    flex-direction: column;
}

label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}

input, select {
    width: 100%;
    padding: 11px 14px;
    background: #FAFBFC;
    color: #1E293B;
    border: 1px solid #CBD5E1;
    border-radius: 10px;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

input:focus, select:focus {
    border-color: #2563EB;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* School Table */
.table-responsive {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    margin: 16px 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

th, td {
    padding: 12px 14px;
    border-bottom: 1px solid #F1F5F9;
    font-size: 13px;
    text-align: left;
}

th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
    border-bottom: 1.5px solid #E2E8F0;
}

td {
    color: #1E293B;
}

table input, table select {
    padding: 8px 12px;
    font-size: 13px;
    border-radius: 8px;
}

.nav-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1.5px solid #F1F5F9;
    flex-wrap: wrap;
    gap: 15px;
}

a.back-btn {
    color: #475569;
    background: #FFFFFF;
    text-decoration: none;
    font-weight: 600;
    font-size: 13.5px;
    padding: 11px 20px;
    border-radius: 10px;
    border: 1.5px solid #E2E8F0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
}

a.back-btn:hover {
    background: #F8FAFC;
    color: #0F172A;
    border-color: #CBD5E1;
}

button[type="submit"] {
    background: #10B981;
    color: white;
    padding: 12px 28px;
    border: none;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border-radius: 10px;
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}

button[type="submit"]:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
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
        <h1>UMIS Student Enrollment</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <div class="content-body">
        <div class="box">
            <div class="form-header">
                <h2>
                    <span class="header-icon-wrap"><i class="fa-solid fa-graduation-cap"></i></span>
                    UMIS Enrollment - Page 4 of 4
                </h2>
                <div class="student-badge"><i class="fa-solid fa-user-graduate"></i> ID: <?=htmlspecialchars($student_id)?></div>
            </div>

            <!-- Stepper Indicator -->
            <div class="stepper-container">
                <div class="step-item done"><i class="fa-solid fa-check"></i> 1. College & General</div>
                <div class="step-divider"></div>
                <div class="step-item done"><i class="fa-solid fa-check"></i> 2. Contact & Address</div>
                <div class="step-divider"></div>
                <div class="step-item done"><i class="fa-solid fa-check"></i> 3. Bank & Family</div>
                <div class="step-divider"></div>
                <div class="step-item active"><i class="fa-solid fa-circle-check"></i> 4. Academic & School</div>
            </div>

            <?php if($msg): ?>
                <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?=htmlspecialchars($msg)?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="section">
                    <div class="section-header">
                        <i class="fa-solid fa-book-open"></i>
                        <h3>Academic Information</h3>
                    </div>
                    <div class="row">
                        <div class="g"><label>69. Medium of Instruction</label>
                            <select name="medium_of_instruction">
                                <option value="Tamil" <?=(v('medium_of_instruction')=='Tamil')?'selected':''?>>Tamil</option>
                                <option value="English" <?=(v('medium_of_instruction')=='English')?'selected':''?>>English</option>
                            </select>
                        </div>
                        <div class="g"><label>70. Mode of Study</label>
                            <select name="mode_of_study">
                                <option value="Regular" <?=(v('mode_of_study')=='Regular')?'selected':''?>>Regular</option>
                                <option value="Distance" <?=(v('mode_of_study')=='Distance')?'selected':''?>>Distance</option>
                                <option value="Part-time" <?=(v('mode_of_study')=='Part-time')?'selected':''?>>Part-time</option>
                                <option value="Week-End" <?=(v('mode_of_study')=='Week-End')?'selected':''?>>Week-End</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="g"><label>71. Date of Admission</label><input type="date" name="date_of_admission" value="<?=v('date_of_admission')?>"></div>
                        <div class="g"><label>72. Type of Admission</label><input type="text" name="type_of_admission" value="<?=v('type_of_admission')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>73. Counselling / Admission No</label><input type="text" name="counselling_no" value="<?=v('counselling_no')?>"></div>
                        <div class="g"><label>74. Roll Number</label><input type="text" name="roll_no" value="<?=v('roll_no')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>75. Lateral Entry?</label>
                            <select name="is_lateral_entry">
                                <option value="No" <?=(v('is_lateral_entry')=='No')?'selected':''?>>No</option>
                                <option value="Yes" <?=(v('is_lateral_entry')=='Yes')?'selected':''?>>Yes</option>
                            </select>
                        </div>
                        <div class="g"><label>76. Hosteller?</label>
                            <select name="is_hosteller">
                                <option value="No" <?=(v('is_hosteller')=='No')?'selected':''?>>No</option>
                                <option value="Yes" <?=(v('is_hosteller')=='Yes')?'selected':''?>>Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="g"><label>77. Current Status</label><input type="text" name="current_status" value="<?=v('current_status','Studying in this Institute')?>"></div>
                        <div class="g"><label>78. Course Completed Year</label><input type="text" name="year_of_course_completed" value="<?=v('year_of_course_completed')?>"></div>
                        <div class="g"><label>79. Year of Study</label><input type="text" name="year_of_study" value="<?=v('year_of_study','1st Year')?>"></div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Parent Phone Number</label>
                        <input type="text" name="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>" placeholder="Enter parent phone number">
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <i class="fa-solid fa-school"></i>
                        <h3>80. School of Study Information (6th to 12th Std)</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Class</th>
                                    <th>District</th>
                                    <th>School Name as per Records</th>
                                    <th style="width: 140px;">Type of School</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach(['12th','11th','10th','9th','8th','7th','6th'] as $cls): 
                                    $sd = $sch_map[$cls] ?? [];
                                ?>
                                <tr>
                                    <td><b style="color: #2563EB; font-weight: 700;"><?=$cls?></b></td>
                                    <td><input type="text" name="sch_dist_<?=$cls?>" value="<?=htmlspecialchars($sd['district'] ?? '')?>"></td>
                                    <td><input type="text" name="sch_name_<?=$cls?>" value="<?=htmlspecialchars($sd['school_name'] ?? '')?>"></td>
                                    <td>
                                        <select name="sch_type_<?=$cls?>">
                                            <option value="Govt" <?=($sd['school_type']??'')=='Govt'?'selected':''?>>Govt</option>
                                            <option value="Aided" <?=($sd['school_type']??'')=='Aided'?'selected':''?>>Aided</option>
                                            <option value="Unaided" <?=($sd['school_type']??'')=='Unaided'?'selected':''?>>Unaided</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="nav-actions">
                    <a href="umis_page3.php?student_id=<?=urlencode($student_id)?>" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Page 3</a>
                    <button type="submit" name="save_page4">Final Submit <i class="fa-solid fa-check"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>