<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Student Authorization Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') { 
    header("Location: ../auth/login.php"); 
    exit(); 
}

$role = strtolower($_SESSION['role']);
$display_role = $_SESSION['role'];
$student_email = trim($_SESSION['email'] ?? '');
$student_reg_no = trim($_SESSION['reg_no'] ?? '');

// Dynamic Role-based Links
$base_url = "/SRMS"; 
$dashboard_link = $base_url . "/student/dashboard_student.php";
$records_link   = $base_url . "/student/student_record.php";

// Multi-location Safe .env File Loader
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

$umis_data = null;
$error_msg = "";

// Fetch ONLY if BOTH Email AND Register Number match in Supabase
if (!empty($student_email) && !empty($student_reg_no)) {
    $base_url_api = rtrim($SUPABASE_URL, '/');
    
    // Supabase Query: Strict AND condition for both email and exam_reg_no
    $url = "$base_url_api/rest/v1/umis_students?email=eq." . urlencode($student_email) . "&exam_reg_no=eq." . urlencode($student_reg_no) . "&select=*";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["apikey: $SUPABASE_KEY", "Authorization: Bearer $SUPABASE_KEY"]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $error_msg = "cURL Connection Error: " . $curl_error;
    } else if ($http_code == 200) {
        $data = json_decode($response, true) ?? [];
        $fetched_umis = $data[0] ?? null;

        // PHP Double Verification Check
        if ($fetched_umis) {
            $db_reg = trim($fetched_umis['exam_reg_no'] ?? $fetched_umis['student_id'] ?? '');
            $db_email = trim($fetched_umis['email'] ?? '');

            if (strtolower($db_email) === strtolower($student_email) && $db_reg === $student_reg_no) {
                $umis_data = $fetched_umis;
            }
        }
    } else {
        $error_msg = "Database Error: HTTP $http_code - " . htmlspecialchars($response);
    }
}
$user_name = $umis_data['student_name_cert'] ?? $umis_data['student_name_aadhaar'] ?? ($_SESSION['name'] ?? $_SESSION['username'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My UMIS Record - SRMS</title>
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

/* Page Header Card */
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
.page-header-title {
    display: flex;
    align-items: center;
    gap: 14px;
}
.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #ECFDF5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.page-header h2 {
    font-size: 19px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}
.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
}
.btn-light {
    background: #F1F5F9;
    color: #334155;
    border: 1px solid #CBD5E1;
}
.btn-light:hover { background: #E2E8F0; color: #0F172A; }

.btn-primary {
    background: #059669;
    color: #FFFFFF;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}
.btn-primary:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.btn-print {
    background: #F8FAFC;
    color: #334155;
    border: 1px solid #CBD5E1;
}
.btn-print:hover { background: #E2E8F0; color: #0F172A; }

/* Status Badges */
.status-green { 
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
    padding: 4px 12px; 
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-transform: uppercase;
}
.status-yellow { 
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
    padding: 4px 12px; 
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-transform: uppercase;
}

/* Single UMIS Detail Card */
.profile-detail-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.record-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 24px;
    border-bottom: 1px solid #E2E8F0;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.record-hero-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.record-avatar {
    width: 68px;
    height: 68px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
    border: 2px solid #A7F3D0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #059669;
    font-size: 26px;
}
.record-hero-info h3 {
    font-size: 18px;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 4px;
}
.record-hero-info p {
    font-size: 13px;
    color: #64748B;
    display: flex;
    align-items: center;
    gap: 10px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 18px;
}

.detail-item {
    background: #F8FAFC;
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    transition: 0.2s;
}
.detail-item:hover {
    border-color: #CBD5E1;
    background: #F1F5F9;
}

.detail-item span {
    display: block;
    font-size: 11.5px;
    color: #64748B;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.detail-item strong {
    font-size: 15px;
    color: #0F172A;
    font-weight: 700;
    word-break: break-word;
}

.card-actions {
    margin-top: 28px;
    display: flex;
    gap: 12px;
    border-top: 1px solid #E2E8F0;
    padding-top: 24px;
    flex-wrap: wrap;
}

/* Empty Card */
.empty-card {
    text-align: center;
    padding: 60px 20px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
}
.empty-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #FEF2F2;
    color: #EF4444;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin-bottom: 16px;
}
.empty-card h3 {
    font-size: 18px;
    font-weight: 800;
    color: #0F172A;
}
.empty-card p {
    font-size: 13.5px;
    color: #64748B;
    margin-top: 8px;
    max-width: 480px;
    margin-left: auto;
    margin-right: auto;
}

/* Print */
@media print {
    .sidebar, .topbar, .page-header, .card-actions { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .content-body { padding: 0 !important; }
    .profile-detail-card { border: none !important; box-shadow: none !important; }
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
            <h1>My UMIS Form Record</h1>
            <p>University Management Information System student profile</p>
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
        
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-database"></i>
                </div>
                <div>
                    <h2>UMIS Institutional Record</h2>
                    <p>Government higher education portal synchronization</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="<?php echo $dashboard_link; ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- SINGLE UMIS DATA CARD -->
        <?php if ($umis_data): ?>
        <?php 
            $student_name = $umis_data['student_name_cert'] ?? $umis_data['student_name_aadhaar'] ?? $_SESSION['name'] ?? '-';
            $is_completed = (isset($umis_data['is_completed']) && $umis_data['is_completed'] == true);
        ?>
        <div class="profile-detail-card">
            
            <div class="record-hero">
                <div class="record-hero-left">
                    <div class="record-avatar">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div class="record-hero-info">
                        <h3><?php echo htmlspecialchars($student_name); ?></h3>
                        <p>
                            <span>Student ID: <strong><?php echo htmlspecialchars($umis_data['student_id'] ?? '-'); ?></strong></span>
                            <span>&bull;</span>
                            <?php if($is_completed): ?>
                                <span class="status-green"><i class="fa-solid fa-circle-check"></i> UMIS Completed</span>
                            <?php else: ?>
                                <span class="status-yellow"><i class="fa-solid fa-clock"></i> In Progress</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <span>Student Unique ID</span>
                    <strong><?php echo htmlspecialchars($umis_data['student_id'] ?? '-'); ?></strong>
                </div>
                <div class="detail-item">
                    <span>Register Number</span>
                    <strong><?php echo htmlspecialchars($umis_data['exam_reg_no'] ?? $student_reg_no); ?></strong>
                </div>
                <div class="detail-item">
                    <span>Name (As in Cert / Aadhaar)</span>
                    <strong><?php echo htmlspecialchars($student_name); ?></strong>
                </div>
                <div class="detail-item">
                    <span>College Name</span>
                    <strong><?php echo htmlspecialchars($umis_data['college_name'] ?? '-'); ?></strong>
                </div>
                <div class="detail-item">
                    <span>Registered Email</span>
                    <strong><?php echo htmlspecialchars($umis_data['email'] ?? $student_email); ?></strong>
                </div>
                <div class="detail-item">
                    <span>Verification Status</span>
                    <strong>
                        <?php if($is_completed): ?>
                            <span class="status-green"><i class="fa-solid fa-check"></i> Verified Complete</span>
                        <?php else: ?>
                            <span class="status-yellow"><i class="fa-solid fa-clock"></i> Verification Pending</span>
                        <?php endif; ?>
                    </strong>
                </div>
            </div>

            <div class="card-actions">
                <a href="umis_view.php?student_id=<?php echo urlencode($umis_data['student_id'] ?? ''); ?>" class="btn btn-primary">
                    <i class="fa-solid fa-eye"></i> View Full UMIS Details
                </a>
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fa-solid fa-print"></i> Print / Save PDF
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-card">
            <div class="empty-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3>No matching UMIS record found!</h3>
            <p>
                The Register Number (<strong><?php echo htmlspecialchars($student_reg_no); ?></strong>) and Email (<strong><?php echo htmlspecialchars($student_email); ?></strong>) do not match any UMIS record.
            </p>
        </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>