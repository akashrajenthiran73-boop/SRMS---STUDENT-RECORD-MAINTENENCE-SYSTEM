<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

if(!isset($_SESSION['role'])){ 
    header("Location: ../auth/login.php"); 
    exit; 
}

$role = strtolower($_SESSION['role']);
$display_role = $_SESSION['role'];

// Dynamic Role-based Links
$base_url = "/SRMS"; 
if ($role === 'student' || $role === 'student') {
    $dashboard_link = $base_url . "/student/dashboard_student.php";
}  else {
    $dashboard_link = $base_url . "/auth/login.php";
}

// Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  
    __DIR__ . '/.env',                     
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
} else {
    header("Location: bio_data.php");
    exit;
}

// Safe show helper function
function show($val){ 
    return (isset($val) && trim((string)$val) !== '') ? htmlspecialchars((string)$val) : '-'; 
}
$user_name = $data['name_ta_en'] ?? ($_SESSION['name'] ?? $_SESSION['username'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Bio Data Details - SRMS</title>
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

.view-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid #E2E8F0;
    flex-wrap: wrap;
    gap: 16px;
}

.view-header h2 {
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
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

.btn-primary { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }
.btn-primary:hover { background: #E2E8F0; color: #0F172A; }

.btn-download {
    background: #059669;
    color: white;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}
.btn-download:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.btn-edit { background: #F59E0B; color: white; }
.btn-edit:hover { background: #D97706; }

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

.row {
    display: flex;
    margin-bottom: 12px;
    border-bottom: 1px dashed #E2E8F0;
    padding-bottom: 10px;
    align-items: center;
}

.row:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.label {
    font-weight: 700;
    width: 35%;
    color: #64748B;
    font-size: 12.5px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.value {
    width: 65%;
    color: #0F172A;
    font-size: 14px;
    font-weight: 600;
    word-break: break-word;
}

/* Photo Box */
.photo-box {
    display: flex;
    gap: 20px;
}

.photo-item {
    text-align: center;
}

.photo-item b {
    display: block;
    margin-bottom: 6px;
    font-size: 11px;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.photo-item img {
    width: 100px;
    height: 130px;
    object-fit: cover;
    border: 2px solid #CBD5E1;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.photo-item .no-photo {
    width: 100px;
    height: 130px;
    border: 2px dashed #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11.5px;
    font-weight: 600;
    color: #94A3B8;
    border-radius: 10px;
    background: #FFFFFF;
}

/* Print Styling */
@media print {
    .sidebar, .topbar, .action-buttons, .sidebar-footer {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    .content-body {
        padding: 0 !important;
    }
    .view-card {
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
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
            <li><a href="bio_data.php" class="active"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
            <li><a href="marks.php"><i class="fa-solid fa-award"></i> <span>Marks / Grades</span></a></li>

            <li class="nav-category">College & Campus</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievance.php"><i class="fa-solid fa-headset"></i> <span>Student Grievance</span></a></li>

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
            <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
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
            <h1>Bio Data Dossier</h1>
            <p>Comprehensive verified student personal profile</p>
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
        
        <div class="view-card">
            
            <div class="view-header">
                <h2><i class="fa-solid fa-address-card" style="color: #059669;"></i> Bio Data: <?php echo show($data['name_ta_en'] ?? null); ?></h2>
                <div class="action-buttons">
                    <a href="bio_data.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Back to Bio Data</a>
                    <button onclick="window.print()" class="btn btn-download"><i class="fa-solid fa-print"></i> Print Official Dossier</button>
                    <?php if ($role === 'admin' || $role === 'super admin'): ?>
                        <a href="bio_data_form.php?id=<?php echo urlencode($id); ?>" class="btn btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PHOTOS & BASIC DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-image"></i> Photographs & Registration</h3>
                
                <div class="row">
                    <div class="label">Official Photographs</div>
                    <div class="value photo-box">
                        <div class="photo-item">
                            <b>Student Photo</b>
                            <?php if(!empty($data['student_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($data['student_photo']); ?>" alt="Student Photo">
                            <?php else: ?>
                                <div class="no-photo">No Photo</div>
                            <?php endif; ?>
                        </div>
                        <div class="photo-item">
                            <b>Parents Photo</b>
                            <?php if(!empty($data['parents_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($data['parents_photo']); ?>" alt="Parents Photo">
                            <?php else: ?>
                                <div class="no-photo">No Photo</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row"><div class="label">Department</div><div class="value"><?php echo show($data['department'] ?? null); ?></div></div>
                <div class="row"><div class="label">Class / Year</div><div class="value"><?php echo show($data['class'] ?? null); ?></div></div>
                <div class="row"><div class="label">Academic Year</div><div class="value"><?php echo show($data['academic_year'] ?? null); ?></div></div>
                <div class="row"><div class="label">Tamil Registration Number</div><div class="value"><?php echo show($data['tamil_reg_no'] ?? null); ?></div></div>
            </div>

            <!-- PERSONAL DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                <div class="row"><div class="label">1. Student Name</div><div class="value"><?php echo show($data['name_ta_en'] ?? null); ?></div></div>
                <div class="row"><div class="label">2. Parents Name</div><div class="value"><?php echo show($data['parents_name_ta'] ?? null); ?></div></div>
                <div class="row"><div class="label">3. Date of Birth</div><div class="value"><?php echo show($data['dob'] ?? null); ?></div></div>
                <div class="row"><div class="label">4. Community</div><div class="value"><?php echo show($data['community'] ?? null); ?></div></div>
                <div class="row"><div class="label">5. Caste</div><div class="value"><?php echo show($data['caste'] ?? null); ?></div></div>
                <div class="row"><div class="label">6. EMIS Number</div><div class="value"><?php echo show($data['emis_no'] ?? null); ?></div></div>
                <div class="row"><div class="label">7. Aadhaar Number</div><div class="value">[Aadhaar Redacted for Privacy]</div></div>
            </div>

            <!-- FAMILY & OTHER DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-users"></i> Family & Welfare Details</h3>
                <div class="row"><div class="label">8. Parent's Occupation</div><div class="value"><?php echo show($data['parent_occupation'] ?? null); ?></div></div>
                <div class="row"><div class="label">9. Parent's Annual Income</div><div class="value">₹ <?php echo show($data['parent_income'] ?? null); ?></div></div>
                <div class="row"><div class="label">10. Accommodation</div><div class="value"><?php echo show($data['accommodation'] ?? null); ?></div></div>
                <div class="row"><div class="label">11. Travel Concession</div><div class="value"><?php echo show($data['travel_concession'] ?? null); ?></div></div>
                <div class="row"><div class="label">12. Nearest Scholarship Office</div><div class="value"><?php echo show($data['scholarship_office'] ?? null); ?></div></div>
                <div class="row"><div class="label">13. Scholarship Details</div><div class="value"><?php echo show($data['scholarship_details'] ?? null); ?></div></div>
                <div class="row"><div class="label">14. Previous Year's Attendance</div><div class="value"><?php echo show($data['prev_attendance'] ?? null); ?> %</div></div>
            </div>

            <!-- CONTACT & ADDRESS -->
            <div class="section">
                <h3><i class="fa-solid fa-address-book"></i> Contact & Residential Details</h3>
                <div class="row"><div class="label">Student Phone</div><div class="value"><?php echo show($data['student_phone'] ?? null); ?></div></div>
                <div class="row"><div class="label">Parent's Phone</div><div class="value"><?php echo show($data['parent_phone'] ?? null); ?></div></div>
                <div class="row"><div class="label">15. Contact Address</div><div class="value"><?php echo show($data['contact_address'] ?? null); ?></div></div>
                <div class="row"><div class="label">16. Permanent Address</div><div class="value"><?php echo show($data['permanent_address'] ?? null); ?></div></div>
                <div class="row"><div class="label">17. Child of Ex-Serviceman</div><div class="value"><?php echo show($data['ex_serviceman'] ?? null); ?></div></div>
                <div class="row"><div class="label">18. Differently Abled</div><div class="value"><?php echo show($data['disability'] ?? null); ?></div></div>
                <div class="row"><div class="label">19. Achievements</div><div class="value"><?php echo show($data['achievements'] ?? null); ?></div></div>
            </div>

        </div>

    </div>

</div>

</body>
</html>