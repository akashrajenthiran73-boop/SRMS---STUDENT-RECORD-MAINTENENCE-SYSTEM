<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'HOD'){ 
    header("Location: login.php"); 
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

$id = $_GET['id'] ?? null;
$data = [];

if($id && !empty($SUPABASE_URL) && !empty($SUPABASE_KEY)){
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data?id=eq.$id";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["apikey: $SUPABASE_KEY", "Authorization: Bearer $SUPABASE_KEY"]
    ]);
    $res = curl_exec($ch); 
    curl_close($ch);
    $decoded = json_decode($res, true);
    $data = (!empty($decoded) && is_array($decoded)) ? $decoded[0] : [];
} else if(!$id) {
    header("Location: bio_data_list.php");
    exit;
}

// Empty field na '-' kaatanum (Strict array key check)
function show($arr, $key){ 
    return (!empty($arr[$key])) ? htmlspecialchars($arr[$key]) : '-'; 
}

$raw_status = trim($data['status'] ?? 'Pending');
$status = ucfirst(strtolower(trim($raw_status, "'\" ")));
if(empty($status)) { $status = 'Pending'; }
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Bio Data - <?php echo show($data, 'name_ta_en');?> - HOD Portal</title>
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
    align-items: center;
    gap: 10px;
}

.btn { 
    padding: 9px 16px; 
    border-radius: 10px; 
    text-decoration: none; 
    font-size: 13px; 
    font-weight: 600; 
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
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

.section-card h3 {
    font-size: 14.5px;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-card h3 i {
    color: #7C3AED;
}

.photo-preview-grid {
    display: flex;
    gap: 24px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.photo-box-card {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px;
    text-align: center;
    width: 140px;
}

.photo-box-card span {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.photo-box-card img {
    width: 110px;
    height: 140px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #FFFFFF;
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
}

.no-photo-box {
    width: 110px;
    height: 140px;
    border: 2px dashed #CBD5E1;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 11px;
    color: #94A3B8;
    background: #FFFFFF;
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

.status-badge { 
    padding: 5px 12px; 
    border-radius: 20px; 
    font-weight: 700; 
    font-size: 11.5px; 
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.status-badge.Pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.status-badge.Approved { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

@media print {
    .sidebar, .topbar, .header-actions { display: none !important; }
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
            <li><a href="bio_data_list.php" class="active"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis_form_list.php"><i class="fa-solid fa-database"></i> <span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="send_notice.php"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
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
            <h1>Student Bio Data Profile</h1>
            <p>Full personal, demographic and socio-economic verification record</p>
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
                    <i class="fa-solid fa-address-card"></i>
                </div>
                <div>
                    <h1><?php echo show($data, 'name_ta_en');?></h1>
                    <p>Registration No: <b><?php echo show($data, 'tamil_reg_no');?></b> &bull; Status: <span class="status-badge <?php echo $status;?>"><i class="fa-solid fa-circle" style="font-size:6px;"></i> <?php echo $status;?></span></p>
                </div>
            </div>
            <div class="header-actions">
                <a href="bio_data_list.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print</button>
                <?php if($status === 'Pending' && !empty($id)):?>
                    <a href="bio_data_list.php?approve_reg=<?php echo $id;?>" class="btn btn-approve" onclick="return confirm('Approve <?php echo show($data, 'name_ta_en');?>?')"><i class="fa-solid fa-check"></i> Approve Bio Data</a>
                <?php endif;?>
            </div>
        </div>

        <div class="section-card">
            <h3><i class="fa-solid fa-camera"></i> Student & Guardian Identification</h3>
            <div class="photo-preview-grid">
                <div class="photo-box-card">
                    <span>Student Photo</span>
                    <?php if(!empty($data['student_photo'])):?>
                        <img src="<?php echo htmlspecialchars($data['student_photo']);?>" alt="Student Photo">
                    <?php else:?>
                        <div class="no-photo-box"><i class="fa-solid fa-user" style="font-size:20px;"></i>No Photo</div>
                    <?php endif;?>
                </div>
                <div class="photo-box-card">
                    <span>Parents Photo</span>
                    <?php if(!empty($data['parents_photo'])):?>
                        <img src="<?php echo htmlspecialchars($data['parents_photo']);?>" alt="Parents Photo">
                    <?php else:?>
                        <div class="no-photo-box"><i class="fa-solid fa-users" style="font-size:20px;"></i>No Photo</div>
                    <?php endif;?>
                </div>
            </div>

            <div class="grid-details">
                <div class="data-item"><div class="data-label">Department</div><div class="data-value"><?php echo show($data, 'department');?></div></div>
                <div class="data-item"><div class="data-label">Class</div><div class="data-value"><?php echo show($data, 'class');?></div></div>
                <div class="data-item"><div class="data-label">Academic Year</div><div class="data-value"><?php echo show($data, 'academic_year');?></div></div>
                <div class="data-item"><div class="data-label">Tamil Registration Number</div><div class="data-value"><b><?php echo show($data, 'tamil_reg_no');?></b></div></div>
            </div>
        </div>

        <div class="section-card">
            <h3><i class="fa-solid fa-id-card"></i> Personal Details</h3>
            <div class="grid-details">
                <div class="data-item"><div class="data-label">1. Student Full Name</div><div class="data-value"><?php echo show($data, 'name_ta_en');?></div></div>
                <div class="data-item"><div class="data-label">2. Parents Name</div><div class="data-value"><?php echo show($data, 'parents_name_ta');?></div></div>
                <div class="data-item"><div class="data-label">3. Date of Birth</div><div class="data-value"><?php echo show($data, 'dob');?></div></div>
                <div class="data-item"><div class="data-label">4. Community</div><div class="data-value"><?php echo show($data, 'community');?></div></div>
                <div class="data-item"><div class="data-label">5. Caste</div><div class="data-value"><?php echo show($data, 'caste');?></div></div>
                <div class="data-item"><div class="data-label">6. EMIS Number</div><div class="data-value"><?php echo show($data, 'emis_no');?></div></div>
                <div class="data-item"><div class="data-label">7. Aadhaar Number</div><div class="data-value">[Aadhaar Redacted]</div></div>
            </div>
        </div>

        <div class="section-card">
            <h3><i class="fa-solid fa-hand-holding-dollar"></i> Family & Socio-Economic Information</h3>
            <div class="grid-details">
                <div class="data-item"><div class="data-label">8. Parent's Occupation</div><div class="data-value"><?php echo show($data, 'parent_occupation');?></div></div>
                <div class="data-item"><div class="data-label">9. Parent's Annual Income</div><div class="data-value">&#8377; <?php echo show($data, 'parent_income');?></div></div>
                <div class="data-item"><div class="data-label">10. Accommodation</div><div class="data-value"><?php echo show($data, 'accommodation');?></div></div>
                <div class="data-item"><div class="data-label">11. Travel Concession</div><div class="data-value"><?php echo show($data, 'travel_concession');?></div></div>
                <div class="data-item"><div class="data-label">12. Nearest Scholarship Office</div><div class="data-value"><?php echo show($data, 'scholarship_office');?></div></div>
                <div class="data-item"><div class="data-label">13. Scholarship Details</div><div class="data-value"><?php echo show($data, 'scholarship_details');?></div></div>
                <div class="data-item"><div class="data-label">14. Previous Year's Attendance</div><div class="data-value"><?php echo show($data, 'prev_attendance');?> %</div></div>
            </div>
        </div>

        <div class="section-card">
            <h3><i class="fa-solid fa-location-dot"></i> Contact & Residential Details</h3>
            <div class="grid-details">
                <div class="data-item"><div class="data-label">Student Phone</div><div class="data-value"><?php echo show($data, 'student_phone');?></div></div>
                <div class="data-item"><div class="data-label">Parent's Phone</div><div class="data-value"><?php echo show($data, 'parent_phone');?></div></div>
                <div class="data-item"><div class="data-label">15. Contact Address</div><div class="data-value"><?php echo show($data, 'contact_address');?></div></div>
                <div class="data-item"><div class="data-label">16. Permanent Address</div><div class="data-value"><?php echo show($data, 'permanent_address');?></div></div>
                <div class="data-item"><div class="data-label">17. Child of Ex-Serviceman</div><div class="data-value"><?php echo show($data, 'ex_serviceman');?></div></div>
                <div class="data-item"><div class="data-label">18. Differently Abled</div><div class="data-value"><?php echo show($data, 'disability');?></div></div>
                <div class="data-item"><div class="data-label">19. Achievements</div><div class="data-value"><?php echo show($data, 'achievements');?></div></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>