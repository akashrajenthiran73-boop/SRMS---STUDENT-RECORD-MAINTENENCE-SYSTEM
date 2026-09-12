<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

if(!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])){ 
    header("Location: ../auth/login.php"); 
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

if (empty($SUPABASE_URL) || empty($SUPABASE_KEY)) {
    die("Configuration Error: SUPABASE_URL or SUPABASE_ANON_KEY missing in .env file!");
}

$id = $_GET['id'] ?? null;
$data = [];

// 2. Fetch data for editing
if($id){
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data?id=eq." . urlencode($id);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $SUPABASE_KEY", "Authorization: Bearer $SUPABASE_KEY"]);
    $res = curl_exec($ch); 
    curl_close($ch);
    $decoded = json_decode($res, true);
    $data = (!empty($decoded) && is_array($decoded)) ? $decoded[0] : [];
}

require_once __DIR__ . '/../includes/college_data.php';
$curr_yinfo = get_student_year_info($data);
$curr_dept = $curr_yinfo['dept_code'];
$curr_ycode = $curr_yinfo['class_code'];

// 3. Handle Form Submission
if(isset($_POST['submit'])){
    $post_data = $_POST;
    unset($post_data['submit']); 

    if(empty($post_data['id'])){
        unset($post_data['id']);
    }

    if(isset($post_data['prev_attendance'])){
        $post_data['prev_attendance'] = str_replace('%', '', $post_data['prev_attendance']);
    }

    // Clean up numeric and select/dropdown fields if empty
    $numeric_fields = ['parent_income']; 
    foreach ($numeric_fields as $field) {
        if (isset($post_data[$field]) && trim($post_data[$field]) === '') {
            $post_data[$field] = null;
        }
    }

    // Fix empty select fields to avoid database check constraint violations
    $select_fields = ['community', 'accommodation', 'travel_concession', 'ex_serviceman'];
    foreach ($select_fields as $field) {
        if (isset($post_data[$field]) && (trim($post_data[$field]) === '' || $post_data[$field] === 'Select')) {
            $post_data[$field] = null;
        }
    }
    
    $target_id = $_POST['id'] ?? null; 

    // Calculate academic year and class fields
    $dept = strtoupper(trim($post_data['department'] ?? 'CS'));
    $ylevel = $post_data['academic_year_level'] ?? 'UG_1';
    $post_data['academic_year_level'] = $ylevel;
    $post_data['degree_level'] = (strpos($ylevel, 'PG') === 0) ? 'PG' : 'UG';
    $classes = get_department_classes($dept);
    $post_data['academic_class'] = $classes[$ylevel]['short'] ?? ($post_data['class'] ?? 'III B.Sc');

    $executeRequest = function($payload) use ($target_id, $SUPABASE_URL, $SUPABASE_KEY) {
        if($target_id){
            $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data?id=eq." . urlencode($target_id);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        } else {
            $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY", 
            "Authorization: Bearer $SUPABASE_KEY", 
            "Content-Type: application/json", 
            "Prefer: return=representation"
        ]);
        
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res];
    };

    list($http_code, $response) = $executeRequest($post_data);

    // If Supabase schema doesn't have academic_class/academic_year_level yet, gracefully retry without them
    if ($http_code == 400 && strpos($response, 'Could not find the') !== false) {
        unset($post_data['academic_class']);
        unset($post_data['academic_year_level']);
        unset($post_data['degree_level']);
        list($http_code, $response) = $executeRequest($post_data);
    }

    if ($http_code >= 200 && $http_code < 300) {
        header("Location: bio_data.php?success=1"); 
        exit; 
    } else {
        echo "<h3>Database Error (HTTP $http_code)</h3>";
        echo "<pre>". htmlspecialchars($response). "</pre>";
        echo "<br><a href='bio_data_form.php'>← Back to Form</a>";
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $id? 'Edit' : 'Add';?> Bio Data - SRMS</title>
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

.form-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.form-header-title {
    font-size: 20px;
    color: #1E3A8A;
    font-weight: 800;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 18px;
    border-bottom: 1.5px solid #F1F5F9;
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
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #F1F5F9;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
}

.form-group {
    margin-bottom: 16px;
}

label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #475569;
    margin-bottom: 6px;
}

input, textarea, select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13.5px;
    color: #1E293B;
    background: #FFFFFF;
    outline: none;
    transition: all 0.2s ease;
}

input:focus, textarea:focus, select:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/* Actions */
.actions {
    display: flex !important;
    flex-direction: row;
    gap: 14px;
    margin-top: 30px;
    margin-bottom: 15px;
    border-top: 1.5px solid #F1F5F9;
    padding-top: 24px;
    width: 100%;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.25s ease;
    min-width: 150px;
}

.btn-submit {
    background: #10B981;
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}
.btn-submit:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
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

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .grid-2 { grid-template-columns: 1fr; }
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
            <li><a href="bio_data.php" class="active"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
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
        <h1><?php echo $id? 'Edit Bio Data Form' : 'Add New Bio Data Form';?></h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <div class="form-card">
            
            <div class="form-header-title">
                <i class="fa-solid fa-pen-to-square" style="color: #2563EB;"></i> <?php echo $id? 'EDIT BIO DATA' : 'ADD NEW BIO DATA';?>
            </div>

            <form method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id ?? $_GET['student_id'] ?? ''); ?>">
                
                <!-- PHOTOS & BASIC DETAILS -->
                <div class="section">
                    <h3><i class="fa-solid fa-image"></i> Photos & Basic Details</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>Exam Register Number</label><input type="text" name="exam_reg_no" value="<?php echo htmlspecialchars($data['exam_reg_no'] ?? ''); ?>" placeholder="Enter Exam Register Number"></div>
                        <div class="form-group"><label>Student Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>" placeholder="Enter Student Email"></div>
                    </div>
                    <div class="form-group"><label>Student Photo URL</label><input type="text" name="student_photo" value="<?php echo htmlspecialchars($data['student_photo']??''); ?>"></div>
                    <div class="form-group"><label>Parents Photo URL</label><input type="text" name="parents_photo" value="<?php echo htmlspecialchars($data['parents_photo']??''); ?>"></div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department" id="bio_dept_select" required onchange="syncBioClass()">
                                <optgroup label="Arts & Commerce">
                                    <option value="TAM" <?php echo ($curr_dept==='TAM'?'selected':''); ?>>TAM - Department of Tamil</option>
                                    <option value="ENG" <?php echo ($curr_dept==='ENG'?'selected':''); ?>>ENG - Department of English</option>
                                    <option value="HIST" <?php echo ($curr_dept==='HIST'?'selected':''); ?>>HIST - Department of History</option>
                                    <option value="ECO" <?php echo ($curr_dept==='ECO'?'selected':''); ?>>ECO - Department of Economics</option>
                                    <option value="COMM" <?php echo ($curr_dept==='COMM'?'selected':''); ?>>COMM - Department of Commerce</option>
                                </optgroup>
                                <optgroup label="Science & IT">
                                    <option value="MATH" <?php echo ($curr_dept==='MATH'?'selected':''); ?>>MATH - Department of Mathematics</option>
                                    <option value="PHY" <?php echo ($curr_dept==='PHY'?'selected':''); ?>>PHY - Department of Physics</option>
                                    <option value="CHEM" <?php echo ($curr_dept==='CHEM'?'selected':''); ?>>CHEM - Department of Chemistry</option>
                                    <option value="BOT" <?php echo ($curr_dept==='BOT'?'selected':''); ?>>BOT - Department of Botany</option>
                                    <option value="ZOO" <?php echo ($curr_dept==='ZOO'?'selected':''); ?>>ZOO - Department of Zoology</option>
                                    <option value="STAT" <?php echo ($curr_dept==='STAT'?'selected':''); ?>>STAT - Department of Statistics</option>
                                    <option value="CS" <?php echo ($curr_dept==='CS' || empty($curr_dept)?'selected':''); ?>>CS - Department of Computer Science</option>
                                    <option value="BCA" <?php echo ($curr_dept==='BCA'?'selected':''); ?>>BCA - Department of Computer Applications</option>
                                    <option value="IT" <?php echo ($curr_dept==='IT'?'selected':''); ?>>IT - Department of Information Technology</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Degree Level & Academic Year</label>
                            <select id="bio_year_select" name="academic_year_level" onchange="syncBioClass()">
                                <option value="UG_1" <?php echo ($curr_ycode==='UG_1'?'selected':''); ?>>UG - 1st Year (I Year)</option>
                                <option value="UG_2" <?php echo ($curr_ycode==='UG_2'?'selected':''); ?>>UG - 2nd Year (II Year)</option>
                                <option value="UG_3" <?php echo ($curr_ycode==='UG_3' || empty($curr_ycode)?'selected':''); ?>>UG - 3rd Year (III Year)</option>
                                <option value="PG_1" <?php echo ($curr_ycode==='PG_1'?'selected':''); ?>>PG - 1st Year (I PG)</option>
                                <option value="PG_2" <?php echo ($curr_ycode==='PG_2'?'selected':''); ?>>PG - 2nd Year (II PG)</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="academic_class" id="bio_academic_class" value="<?php echo htmlspecialchars($data['academic_class'] ?? ($data['class'] ?? 'III B.Sc')); ?>">
                    <input type="hidden" name="degree_level" id="bio_degree_level" value="<?php echo htmlspecialchars($data['degree_level'] ?? (strpos($curr_ycode, 'PG') === 0 ? 'PG' : 'UG')); ?>">
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Class Designation</label>
                            <input type="text" name="class" id="bio_class_input" value="<?php echo htmlspecialchars($data['class'] ?? 'III B.Sc'); ?>" placeholder="e.g. III B.Sc">
                        </div>
                        <div class="form-group">
                            <label>Academic Year / Batch</label>
                            <input type="text" name="academic_year" value="<?php echo htmlspecialchars($data['academic_year'] ?? '2024-2027'); ?>" placeholder="e.g. 2024-2027">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tamil Registration Number</label>
                        <input type="text" name="tamil_reg_no" value="<?php echo htmlspecialchars($data['tamil_reg_no']??''); ?>" placeholder="Enter Tamil Reg Number">
                    </div>
                </div>

                <!-- PERSONAL DETAILS -->
                <div class="section">
                    <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                    <div class="form-group"><label>1. Student Name (Tamil & English)</label><input type="text" name="name_ta_en" value="<?php echo htmlspecialchars($data['name_ta_en']??''); ?>"></div>
                    <div class="form-group"><label>2. Father's Name, Mother's Name (in Tamil)</label><input type="text" name="parents_name_ta" value="<?php echo htmlspecialchars($data['parents_name_ta']??''); ?>"></div>
                    <div class="form-group"><label>3. Date of Birth</label><input type="date" name="dob" value="<?php echo htmlspecialchars($data['dob']??''); ?>"></div>
                    <div class="grid-2">
                        <div class="form-group"><label>4. Community</label>
                            <select name="community">
                                <option value="<?php echo htmlspecialchars($data['community']??''); ?>"><?php echo $data['community']??'Select'; ?></option>
                                <option value="SC">SC</option><option value="ST">ST</option><option value="MBC">MBC</option><option value="BC">BC</option><option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="form-group"><label>5. Caste</label><input type="text" name="caste" value="<?php echo htmlspecialchars($data['caste']??''); ?>"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>6. EMIS Number</label><input type="text" name="emis_no" value="<?php echo htmlspecialchars($data['emis_no']??''); ?>"></div>
                        <div class="form-group"><label>7. Aadhaar Number</label><input type="text" name="aadhaar_no" value="<?php echo htmlspecialchars($data['aadhaar_no']??''); ?>"></div>
                    </div>
                </div>

                <!-- FAMILY & OTHER DETAILS -->
                <div class="section">
                    <h3><i class="fa-solid fa-users"></i> Family & Other Details</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>8. Parent's Occupation</label><input type="text" name="parent_occupation" value="<?php echo htmlspecialchars($data['parent_occupation']??''); ?>"></div>
                        <div class="form-group"><label>9. Parent's Annual Income</label><input type="number" name="parent_income" value="<?php echo htmlspecialchars($data['parent_income']??''); ?>"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>10. Accommodation</label>
                            <select name="accommodation">
                                <option value="<?php echo htmlspecialchars($data['accommodation']??''); ?>"><?php echo $data['accommodation']??'Select'; ?></option>
                                <option value="Day Scholar">Day Scholar</option><option value="Hostel">Hostel</option>
                            </select>
                        </div>
                        <div class="form-group"><label>11. Travel Concession Required</label>
                            <select name="travel_concession">
                                <option value="<?php echo htmlspecialchars($data['travel_concession']??''); ?>"><?php echo $data['travel_concession']??'Select'; ?></option>
                                <option value="Yes">Yes</option><option value="No">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>12. Nearest Scholarship Office</label><input type="text" name="scholarship_office" value="<?php echo htmlspecialchars($data['scholarship_office']??''); ?>"></div>
                    <div class="form-group"><label>13. Scholarship Details</label><textarea name="scholarship_details" rows="2"><?php echo htmlspecialchars($data['scholarship_details']??''); ?></textarea></div>
                    <div class="form-group"><label>14. Previous Year's Attendance %</label><input type="text" name="prev_attendance" value="<?php echo htmlspecialchars($data['prev_attendance']??''); ?>"></div>
                </div>

              <!-- CONTACT & ADDRESS -->
                <div class="section">
                    <h3><i class="fa-solid fa-address-book"></i> Contact & Address</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>Student Phone</label><input type="text" name="student_phone" value="<?php echo htmlspecialchars($data['student_phone']??''); ?>"></div>
                        <div class="form-group"><label>Parent's Phone</label><input type="text" name="parent_phone" value="<?php echo htmlspecialchars($data['parent_phone']??''); ?>"></div>
                    </div>
                    <div class="form-group"><label>15. Contact Address</label><textarea name="contact_address" rows="2"><?php echo htmlspecialchars($data['contact_address']??''); ?></textarea></div>
                    <div class="form-group"><label>16. Permanent Address</label><textarea name="permanent_address" rows="2"><?php echo htmlspecialchars($data['permanent_address']??''); ?></textarea></div>
                    <div class="grid-2">
                        <div class="form-group"><label>17. Child of Ex-Serviceman?</label>
                            <select name="ex_serviceman">
                                <option value="<?php echo htmlspecialchars($data['ex_serviceman']??''); ?>"><?php echo $data['ex_serviceman']??'Select'; ?></option>
                                <option value="No">No</option><option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group"><label>18. Differently Abled?</label><input type="text" name="disability" placeholder="If Yes, Specify Type" value="<?php echo htmlspecialchars($data['disability']??''); ?>"></div>
                    </div>
                    <div class="form-group"><label>19. Achievements / Sports / NCC / NSS</label><textarea name="achievements" rows="2"><?php echo htmlspecialchars($data['achievements']??''); ?></textarea></div>
                </div>

                <div class="actions">
                    <button type="submit" name="submit" class="btn btn-submit">
                        <i class="fa-solid fa-check"></i> Save Bio Data
                    </button>
                    <button type="button" class="btn btn-back" onclick="window.location.href='bio_data.php';">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                </div>

            </form>
        </div>

    </div>

<script>
function syncBioClass() {
    const dept = document.getElementById('bio_dept_select').value;
    const yearLevel = document.getElementById('bio_year_select').value;
    const classInput = document.getElementById('bio_class_input');

    const artsDepts = ['TAM', 'ENG', 'HIST', 'ECO'];
    const isArts = artsDepts.includes(dept);
    const isComm = (dept === 'COMM');
    const isBCA  = (dept === 'BCA');

    let degPrefix = 'B.Sc';
    if (yearLevel.startsWith('PG')) {
        if (isArts) degPrefix = 'M.A';
        else if (isComm) degPrefix = 'M.Com';
        else degPrefix = 'M.Sc';
    } else {
        if (isArts) degPrefix = 'B.A';
        else if (isComm) degPrefix = 'B.Com';
        else if (isBCA)  degPrefix = 'BCA';
        else degPrefix = 'B.Sc';
    }

    let yearNum = 'III';
    if (yearLevel === 'UG_1' || yearLevel === 'PG_1') yearNum = 'I';
    else if (yearLevel === 'UG_2' || yearLevel === 'PG_2') yearNum = 'II';
    else if (yearLevel === 'UG_3') yearNum = 'III';

    const shortClass = `${yearNum} ${degPrefix}`;
    classInput.value = shortClass;
    if (document.getElementById('bio_academic_class')) {
        document.getElementById('bio_academic_class').value = shortClass;
    }
    if (document.getElementById('bio_degree_level')) {
        document.getElementById('bio_degree_level').value = yearLevel.startsWith('PG') ? 'PG' : 'UG';
    }
}
</script>
</body>
</html>