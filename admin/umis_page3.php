<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

if (!function_exists('callSupabase')) {
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
    unset($payload['save_page3']);

    // Handle Income number specifically
    $payload['family_income'] = ($payload['family_income'] !== '') ? (float)$payload['family_income'] : null;

    // Convert empty fields to NULL
    foreach($payload as $k => $v){ if($v === '') $payload[$k] = null; }

    $base_url = rtrim($SUPABASE_URL, '/');
    list($res, $http) = callSupabase("$base_url/rest/v1/umis_students?student_id=eq." . urlencode($student_id), $SUPABASE_KEY, 'PATCH', $payload);

    if($http >= 200 && $http < 300) {
        header("Location: umis_page4.php?student_id=" . urlencode($student_id)); 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UMIS Form - Page 3 - SRMS</title>
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
    background: #2563EB;
    color: white;
    padding: 12px 26px;
    border: none;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border-radius: 10px;
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

button[type="submit"]:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
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
        <span>Arignar Anna Government Arts College</span>
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
                    <span class="header-icon-wrap"><i class="fa-solid fa-users-rectangle"></i></span>
                    UMIS Enrollment - Page 3 of 4
                </h2>
                <div class="student-badge"><i class="fa-solid fa-user-graduate"></i> ID: <?=htmlspecialchars($student_id)?></div>
            </div>

            <!-- Stepper Indicator -->
            <div class="stepper-container">
                <div class="step-item done"><i class="fa-solid fa-check"></i> 1. College & General</div>
                <div class="step-divider"></div>
                <div class="step-item done"><i class="fa-solid fa-check"></i> 2. Contact & Address</div>
                <div class="step-divider"></div>
                <div class="step-item active"><i class="fa-solid fa-circle-check"></i> 3. Bank & Family</div>
                <div class="step-divider"></div>
                <div class="step-item inactive">4. Academic & School</div>
            </div>

            <?php if($msg): ?>
                <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?=htmlspecialchars($msg)?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="section">
                    <div class="section-header">
                        <i class="fa-solid fa-user-group"></i>
                        <h3>Family Information</h3>
                    </div>
                    <div class="row">
                        <div class="g"><label>44. Is Orphan Category?</label>
                            <select name="is_orphan">
                                <option value="No" <?=(v('is_orphan')=='No')?'selected':''?>>No</option>
                                <option value="Yes" <?=(v('is_orphan')=='Yes')?'selected':''?>>Yes</option>
                            </select>
                        </div>
                        <div class="g"><label>50. Annual Family Income (in Rs.)</label><input type="number" name="family_income" value="<?=v('family_income')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>45. Father's Name</label><input type="text" name="father_name" value="<?=v('father_name')?>"></div>
                        <div class="g"><label>46. Father's Occupation</label>
                            <select name="father_occupation">
                                <?php foreach(['Daily Wage','Govt','N.A','Private','Self Employed','Unemployed'] as $o): ?>
                                    <option value="<?=$o?>" <?=(v('father_occupation')==$o)?'selected':''?>><?=$o?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="g"><label>47. Mother's Name</label><input type="text" name="mother_name" value="<?=v('mother_name')?>"></div>
                        <div class="g"><label>48. Mother's Occupation</label>
                            <select name="mother_occupation">
                                <?php foreach(['Daily Wage','Govt','N.A','Private','Self Employed','Unemployed'] as $o): ?>
                                    <option value="<?=$o?>" <?=(v('mother_occupation')==$o)?'selected':''?>><?=$o?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="g"><label>49. Guardian / Spouse Name</label><input type="text" name="guardian_name" value="<?=v('guardian_name')?>"></div>
                        <div class="g"><label>51. Income Cert Number</label><input type="text" name="income_cert_no" value="<?=v('income_cert_no')?>"></div>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <label>52. Parent / Guardian Mobile</label>
                        <input type="text" name="guardian_mobile" value="<?=v('guardian_mobile')?>">
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <i class="fa-solid fa-shield-halved"></i>
                        <h3>Bank Information - Aadhaar Seeding</h3>
                    </div>
                    <div class="row">
                        <div class="g"><label>53. Account Number</label><input type="text" name="seed_account_no" value="<?=v('seed_account_no')?>"></div>
                        <div class="g"><label>54. Mobile Number</label><input type="text" name="seed_mobile" value="<?=v('seed_mobile')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>55. Bank Name</label><input type="text" name="seed_bank_name" value="<?=v('seed_bank_name')?>"></div>
                        <div class="g"><label>56. Status</label>
                            <select name="seed_status">
                                <option value="Active" <?=(v('seed_status')=='Active')?'selected':''?>>Active</option>
                                <option value="Not Active" <?=(v('seed_status')=='Not Active')?'selected':''?>>Not Active</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <label>57. Message</label>
                        <input type="text" name="seed_message" value="<?=v('seed_message','Your Bank Account – Aadhaar Seeding has been done')?>">
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <i class="fa-solid fa-building-columns"></i>
                        <h3>Account Active Status</h3>
                    </div>
                    <div class="row">
                        <div class="g"><label>58. Account Number</label><input type="text" name="acc_no" value="<?=v('acc_no')?>"></div>
                        <div class="g"><label>59. IFSC Code</label><input type="text" name="ifsc_code" value="<?=v('ifsc_code')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>60. Bank Name</label><input type="text" name="bank_name" value="<?=v('bank_name')?>"></div>
                        <div class="g"><label>61. Bank Branch</label><input type="text" name="bank_branch" value="<?=v('bank_branch')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>62. City</label><input type="text" name="bank_city" value="<?=v('bank_city')?>"></div>
                        <div class="g"><label>63. Account Type</label>
                            <select name="account_type">
                                <option value="SB" <?=(v('account_type')=='SB')?'selected':''?>>SB</option>
                                <option value="CA" <?=(v('account_type')=='CA')?'selected':''?>>CA</option>
                                <option value="Other" <?=(v('account_type')=='Other')?'selected':''?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <h3>Current Academic Info</h3>
                    </div>
                    <div class="row">
                        <div class="g"><label>64. Joining Year</label><input type="text" name="join_year" value="<?=v('join_year','2025–2028')?>"></div>
                        <div class="g"><label>65. Stream Type</label><input type="text" name="stream_type" value="<?=v('stream_type')?>"></div>
                    </div>
                    <div class="row">
                        <div class="g"><label>66. Course Type</label><input type="text" name="course_type" value="<?=v('course_type', 'Regular')?>"></div>
                        <div class="g">
                            <label>67. Course / Department</label>
                            <select name="course">
                                <optgroup label="Arts & Commerce">
                                    <option value="TAM" <?=(v('course')=='TAM')?'selected':''?>>TAM - Tamil</option>
                                    <option value="ENG" <?=(v('course')=='ENG')?'selected':''?>>ENG - English</option>
                                    <option value="HIST" <?=(v('course')=='HIST')?'selected':''?>>HIST - History</option>
                                    <option value="ECO" <?=(v('course')=='ECO')?'selected':''?>>ECO - Economics</option>
                                    <option value="COMM" <?=(v('course')=='COMM')?'selected':''?>>COMM - Commerce</option>
                                </optgroup>
                                <optgroup label="Science & IT">
                                    <option value="MATH" <?=(v('course')=='MATH')?'selected':''?>>MATH - Mathematics</option>
                                    <option value="PHY" <?=(v('course')=='PHY')?'selected':''?>>PHY - Physics</option>
                                    <option value="CHEM" <?=(v('course')=='CHEM')?'selected':''?>>CHEM - Chemistry</option>
                                    <option value="BOT" <?=(v('course')=='BOT')?'selected':''?>>BOT - Botany</option>
                                    <option value="ZOO" <?=(v('course')=='ZOO')?'selected':''?>>ZOO - Zoology</option>
                                    <option value="STAT" <?=(v('course')=='STAT')?'selected':''?>>STAT - Statistics</option>
                                    <option value="CS" <?=(v('course')=='CS'||v('course')=='')?'selected':''?>>CS - Computer Science</option>
                                    <option value="BCA" <?=(v('course')=='BCA')?'selected':''?>>BCA - Computer Applications</option>
                                    <option value="IT" <?=(v('course')=='IT')?'selected':''?>>IT - Information Technology</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="g"><label>68. Branch / Specialization</label><input type="text" name="specialization" value="<?=v('specialization')?>"></div>
                    </div>
                </div>

                <div class="nav-actions">
                    <a href="umis_page2.php?student_id=<?=htmlspecialchars($student_id)?>" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Page 2</a>
                    <button type="submit" name="save_page3">Save & Go to Page 4 <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>