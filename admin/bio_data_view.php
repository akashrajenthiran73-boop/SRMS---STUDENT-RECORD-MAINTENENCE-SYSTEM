<?php
session_start();
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
<title>View Bio Data - SRMS</title>
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

/* Action Header */
.view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1.5px solid #F1F5F9;
    flex-wrap: wrap;
    gap: 16px;
}

.view-header h2 {
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
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    text-decoration: none;
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
    background: #F59E0B;
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
}
.btn-edit:hover {
    background: #D97706;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35);
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

/* Grid Layout for details */
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px 24px;
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

/* Photo Showcase Area */
.photo-showcase {
    display: flex;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding: 20px;
    background: #F8FAFC;
    border-radius: 14px;
    border: 1px dashed #CBD5E1;
}

.photo-card {
    text-align: center;
}

.photo-title {
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.photo-card img {
    width: 120px;
    height: 150px;
    object-fit: cover;
    border: 3px solid #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.no-photo-placeholder {
    width: 120px;
    height: 150px;
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #94A3B8;
    background: #FFFFFF;
    font-size: 12px;
    font-weight: 600;
}

.no-photo-placeholder i {
    font-size: 28px;
    color: #CBD5E1;
}

.academic-quick-info {
    flex: 1;
    min-width: 250px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

@media print {
    .sidebar, .navbar, .action-buttons, .btn-print {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
    }
    .content-body {
        padding: 0 !important;
    }
    .view-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    body {
        background: #FFFFFF !important;
    }
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
        <h1>View Bio Data Details</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <div class="view-card">
            
            <div class="view-header">
                <h2>
                    <span class="header-icon-wrap"><i class="fa-solid fa-id-card"></i></span>
                    Bio Data: <?php echo show($data['name_ta_en'] ?? null); ?>
                </h2>
                <div class="action-buttons">
                    <a href="bio_data.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                    <button onclick="window.print()" class="btn btn-print"><i class="fa-solid fa-print"></i> Print</button>
                    <a href="bio_data_form.php?id=<?php echo urlencode($id); ?>" class="btn btn-edit"><i class="fa-solid fa-pen"></i> Edit Record</a>
                </div>
            </div>

            <!-- PHOTOS & BASIC ACADEMIC DETAILS -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <h3>Photos & Academic Profile</h3>
                </div>
                
                <div class="photo-showcase">
                    <div class="photo-card">
                        <div class="photo-title"><i class="fa-solid fa-user"></i> Student</div>
                        <?php if(!empty($data['student_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($data['student_photo']); ?>" alt="Student Photo">
                        <?php else: ?>
                            <div class="no-photo-placeholder">
                                <i class="fa-regular fa-image"></i>
                                <span>No Photo</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="photo-card">
                        <div class="photo-title"><i class="fa-solid fa-people-roof"></i> Parents</div>
                        <?php if(!empty($data['parents_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($data['parents_photo']); ?>" alt="Parents Photo">
                        <?php else: ?>
                            <div class="no-photo-placeholder">
                                <i class="fa-regular fa-image"></i>
                                <span>No Photo</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="academic-quick-info">
                        <div class="detail-item">
                            <span class="detail-label">Department</span>
                            <span class="detail-value"><?php echo show($data['department'] ?? null); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Class</span>
                            <span class="detail-value"><?php echo show($data['class'] ?? null); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Academic Year</span>
                            <span class="detail-value"><?php echo show($data['academic_year'] ?? null); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tamil Registration Number</span>
                            <span class="detail-value"><?php echo show($data['tamil_reg_no'] ?? null); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PERSONAL DETAILS -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-user-check"></i>
                    <h3>Personal Information</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">1. Student Name</span>
                        <span class="detail-value"><?php echo show($data['name_ta_en'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">2. Parents Name</span>
                        <span class="detail-value"><?php echo show($data['parents_name_ta'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">3. Date of Birth</span>
                        <span class="detail-value"><?php echo show($data['dob'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">4. Community</span>
                        <span class="detail-value"><?php echo show($data['community'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">5. Caste</span>
                        <span class="detail-value"><?php echo show($data['caste'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">6. EMIS Number</span>
                        <span class="detail-value"><?php echo show($data['emis_no'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">7. Aadhaar Number</span>
                        <span class="detail-value">[Aadhaar Redacted]</span>
                    </div>
                </div>
            </div>

            <!-- FAMILY & SOCIO-ECONOMIC DETAILS -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                    <h3>Family & Socio-Economic Details</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">8. Parent's Occupation</span>
                        <span class="detail-value"><?php echo show($data['parent_occupation'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">9. Parent's Annual Income</span>
                        <span class="detail-value">₹ <?php echo show($data['parent_income'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">10. Accommodation</span>
                        <span class="detail-value"><?php echo show($data['accommodation'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">11. Travel Concession</span>
                        <span class="detail-value"><?php echo show($data['travel_concession'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">12. Nearest Scholarship Office</span>
                        <span class="detail-value"><?php echo show($data['scholarship_office'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">13. Scholarship Details</span>
                        <span class="detail-value"><?php echo show($data['scholarship_details'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">14. Previous Year's Attendance</span>
                        <span class="detail-value"><?php echo show($data['prev_attendance'] ?? null); ?> %</span>
                    </div>
                </div>
            </div>

            <!-- CONTACT & ADDITIONAL INFORMATION -->
            <div class="section">
                <div class="section-header">
                    <i class="fa-solid fa-address-book"></i>
                    <h3>Contact & Additional Information</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Student Phone</span>
                        <span class="detail-value"><?php echo show($data['student_phone'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Parent's Phone</span>
                        <span class="detail-value"><?php echo show($data['parent_phone'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">15. Contact Address</span>
                        <span class="detail-value"><?php echo show($data['contact_address'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">16. Permanent Address</span>
                        <span class="detail-value"><?php echo show($data['permanent_address'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">17. Child of Ex-Serviceman</span>
                        <span class="detail-value"><?php echo show($data['ex_serviceman'] ?? null); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">18. Differently Abled</span>
                        <span class="detail-value"><?php echo show($data['disability'] ?? null); ?></span>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <span class="detail-label">19. Achievements</span>
                        <span class="detail-value"><?php echo show($data['achievements'] ?? null); ?></span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>