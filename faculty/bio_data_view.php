<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Bio Data - Faculty Portal</title>
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

.view-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.03);
}

.view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
    border-bottom: 1px solid #E2E8F0;
    padding-bottom: 20px;
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
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-primary { background: #64748B; color: white; }
.btn-primary:hover { background: #475569; }

.btn-whatsapp { background: #25D366; color: white; }
.btn-whatsapp:hover { background: #20BA5A; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); }

.btn-msg { background: #8B5CF6; color: white; }
.btn-msg:hover { background: #7C3AED; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.25); }

.btn-print { background: #10B981; color: white; }
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

.grid-rows {
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

/* Photo Box */
.photo-box {
    display: flex;
    gap: 25px;
    margin-bottom: 20px;
}

.photo-item {
    text-align: center;
}

.photo-item b {
    display: block;
    margin-bottom: 8px;
    font-size: 12px;
    color: #475569;
    text-transform: uppercase;
    font-weight: 700;
}

.photo-item img {
    width: 130px;
    height: 160px;
    object-fit: cover;
    border: 3px solid #FFFFFF;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

.photo-item .no-photo {
    width: 130px;
    height: 160px;
    border: 2px dashed #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #94A3B8;
    border-radius: 12px;
    background: #F1F5F9;
    font-weight: 600;
}

@media(max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .grid-rows { grid-template-columns: 1fr; }
    .row.full-width { grid-column: span 1; }
}

@media print {
    .sidebar, .topbar, .action-buttons { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .view-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
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
            <li><a href="bio_data.php" class="active"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
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
        <h2><i class="fa-solid fa-id-card"></i> View Bio Data Details</h2>
        <div class="topbar-right">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="view-card">
            
            <div class="view-header">
                <h2><i class="fa-solid fa-file-lines" style="color: #2563EB;"></i> Bio Data: <?php echo show($data['name_ta_en'] ?? null); ?></h2>
                <div class="action-buttons">
                    <a href="bio_data.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                    
                    <?php 
                    $student_name = $data['name_ta_en'] ?? 'Student';
                    $parent_phone = trim($data['parent_phone'] ?? '');
                    if(!empty($parent_phone)): 
                    ?>
                        <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $parent_phone); ?>?text=Hello%20Respected%20Parent,%20Regarding%20student%20<?php echo urlencode($student_name); ?>" target="_blank" class="btn btn-whatsapp">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="sms:<?php echo htmlspecialchars($parent_phone); ?>?body=Hello%20Parent,%20Regarding%20student%20<?php echo urlencode($student_name); ?>" class="btn btn-msg">
                           <i class="fa-solid fa-comment-sms"></i> Send SMS
                        </a>
                    <?php endif; ?>

                    <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <!-- PHOTOS & BASIC DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-image"></i> Photos & Basic Details</h3>
                
                <div class="photo-box">
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

                <div class="grid-rows">
                    <div class="row"><div class="label">Department</div><div class="value"><?php echo show($data['department'] ?? null); ?></div></div>
                    <div class="row"><div class="label">Class</div><div class="value"><?php echo show($data['class'] ?? null); ?></div></div>
                    <div class="row"><div class="label">Academic Year</div><div class="value"><?php echo show($data['academic_year'] ?? null); ?></div></div>
                    <div class="row"><div class="label">Tamil Registration Number</div><div class="value"><?php echo show($data['tamil_reg_no'] ?? null); ?></div></div>
                </div>
            </div>

            <!-- PERSONAL DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                <div class="grid-rows">
                    <div class="row"><div class="label">1. Student Name</div><div class="value"><?php echo show($data['name_ta_en'] ?? null); ?></div></div>
                    <div class="row"><div class="label">2. Parents Name</div><div class="value"><?php echo show($data['parents_name_ta'] ?? null); ?></div></div>
                    <div class="row"><div class="label">3. Date of Birth</div><div class="value"><?php echo show($data['dob'] ?? null); ?></div></div>
                    <div class="row"><div class="label">4. Community</div><div class="value"><?php echo show($data['community'] ?? null); ?></div></div>
                    <div class="row"><div class="label">5. Caste</div><div class="value"><?php echo show($data['caste'] ?? null); ?></div></div>
                    <div class="row"><div class="label">6. EMIS Number</div><div class="value"><?php echo show($data['emis_no'] ?? null); ?></div></div>
                    <div class="row full-width"><div class="label">7. Aadhaar Number</div><div class="value">[Aadhaar Redacted]</div></div>
                </div>
            </div>

            <!-- FAMILY & OTHER DETAILS -->
            <div class="section">
                <h3><i class="fa-solid fa-users"></i> Family & Other Details</h3>
                <div class="grid-rows">
                    <div class="row"><div class="label">8. Parent's Occupation</div><div class="value"><?php echo show($data['parent_occupation'] ?? null); ?></div></div>
                    <div class="row"><div class="label">9. Parent's Annual Income</div><div class="value">₹ <?php echo show($data['parent_income'] ?? null); ?></div></div>
                    <div class="row"><div class="label">10. Accommodation</div><div class="value"><?php echo show($data['accommodation'] ?? null); ?></div></div>
                    <div class="row"><div class="label">11. Travel Concession</div><div class="value"><?php echo show($data['travel_concession'] ?? null); ?></div></div>
                    <div class="row"><div class="label">12. Nearest Scholarship Office</div><div class="value"><?php echo show($data['scholarship_office'] ?? null); ?></div></div>
                    <div class="row"><div class="label">13. Scholarship Details</div><div class="value"><?php echo show($data['scholarship_details'] ?? null); ?></div></div>
                    <div class="row full-width"><div class="label">14. Previous Year's Attendance</div><div class="value"><?php echo show($data['prev_attendance'] ?? null); ?> %</div></div>
                </div>
            </div>

            <!-- CONTACT & ADDRESS -->
            <div class="section">
                <h3><i class="fa-solid fa-address-book"></i> Contact & Address</h3>
                <div class="grid-rows">
                    <div class="row"><div class="label">Student Phone</div><div class="value"><?php echo show($data['student_phone'] ?? null); ?></div></div>
                    <div class="row"><div class="label">Parent's Phone</div><div class="value"><?php echo show($data['parent_phone'] ?? null); ?></div></div>
                    <div class="row full-width"><div class="label">15. Contact Address</div><div class="value"><?php echo show($data['contact_address'] ?? null); ?></div></div>
                    <div class="row full-width"><div class="label">16. Permanent Address</div><div class="value"><?php echo show($data['permanent_address'] ?? null); ?></div></div>
                    <div class="row"><div class="label">17. Child of Ex-Serviceman</div><div class="value"><?php echo show($data['ex_serviceman'] ?? null); ?></div></div>
                    <div class="row"><div class="label">18. Differently Abled</div><div class="value"><?php echo show($data['disability'] ?? null); ?></div></div>
                    <div class="row full-width"><div class="label">19. Achievements</div><div class="value"><?php echo show($data['achievements'] ?? null); ?></div></div>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>